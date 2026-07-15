<?php

namespace App\Services;

use App\Models\ApprovalStep;
use App\Models\Bordero;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BorderoActionService
{
    public function __construct(
        private ApprovalWorkflowService $workflow,
    ) {
    }

    public function canLiberarTitulo(User $user): bool
    {
        return $user->hasPermission('financeiro.borderos.liberar_titulo') || $user->hasPermission('*');
    }

    public function canExpulsarTitulo(User $user, Payable $payable): bool
    {
        if ($user->hasPermission('*') || $user->hasPermission('financeiro.borderos.expulsar_titulo')) {
            return true;
        }

        return $this->workflow->canUserApprove($payable, $user);
    }

    public function canReprovarBordero(Bordero $bordero, User $user): bool
    {
        if ($user->hasPermission('*') || $user->hasPermission('financeiro.borderos.reprovar')) {
            return true;
        }

        return $bordero->payables->contains(fn (Payable $p) => $this->workflow->canUserApprove($p, $user));
    }

    public function canDesfazer(User $user): bool
    {
        return $user->hasPermission('financeiro.borderos.desfazer') || $user->hasPermission('*');
    }

    /** Libera título do borderô para seguir fluxo avulso (mantém etapas de aprovação). */
    public function liberarTitulo(Bordero $bordero, Payable $payable, User $actor, string $reason): void
    {
        if ($bordero->status !== 'aguardando_aprovacao') {
            throw new \InvalidArgumentException('Só é possível liberar títulos de borderôs em aprovação.');
        }

        if ($payable->bordero_id !== $bordero->id) {
            throw new \InvalidArgumentException('Título não pertence a este borderô.');
        }

        if ($payable->status !== 'aguardando_aprovacao') {
            throw new \InvalidArgumentException('O título precisa estar aguardando aprovação.');
        }

        if (! $this->canLiberarTitulo($actor)) {
            throw new \InvalidArgumentException('Sem permissão para liberar título do borderô.');
        }

        DB::transaction(function () use ($bordero, $payable, $actor, $reason) {
            $payable->update(['bordero_id' => null]);

            $this->comment(
                $payable,
                $actor,
                "Liberado do borderô {$bordero->number} por {$actor->name} em ".now()->format('d/m/Y H:i').". "
                ."Motivo: {$reason}. O título segue o fluxo de aprovação avulso a partir desta etapa. "
                ."O borderô {$bordero->number} continua em aprovação com os demais títulos.",
                'status_change',
            );

            $bordero->recalculate();
            $bordero->syncStatusFromPayables();
        });

        AuditLogger::log(
            event: 'bordero.titulo_liberado',
            module: 'financeiro.contas_pagar',
            description: "Título {$payable->title_number} liberado do borderô {$bordero->number}: {$reason}",
            auditable: $bordero,
        );
    }

    /** Expulsa título reprovado do borderô para CP pendente avulso. */
    public function expulsarTitulo(Bordero $bordero, Payable $payable, User $actor, string $reason): void
    {
        if ($bordero->status !== 'aguardando_aprovacao') {
            throw new \InvalidArgumentException('Só é possível expulsar títulos de borderôs em aprovação.');
        }

        if ($payable->bordero_id !== $bordero->id) {
            throw new \InvalidArgumentException('Título não pertence a este borderô.');
        }

        if (! $this->canExpulsarTitulo($actor, $payable)) {
            throw new \InvalidArgumentException('Sem permissão para expulsar este título do borderô.');
        }

        DB::transaction(function () use ($bordero, $payable, $actor, $reason) {
            ApprovalStep::where('payable_id', $payable->id)->delete();

            $payable->update([
                'bordero_id' => null,
                'status' => 'pendente',
                'rejection_reason' => $reason,
                'sent_for_approval_at' => null,
                'approved_at' => null,
            ]);

            $this->comment(
                $payable,
                $actor,
                "Expulso do borderô {$bordero->number} por {$actor->name} em ".now()->format('d/m/Y H:i').". "
                ."Motivo: {$reason}. Devolvido para Contas a Pagar (pendente avulso). "
                ."O borderô {$bordero->number} continua em aprovação com os demais títulos.",
                'rejection',
            );

            $bordero->recalculate();

            if ($bordero->items_count === 0) {
                $bordero->delete();

                return;
            }

            $bordero->syncStatusFromPayables();
        });

        AuditLogger::log(
            event: 'bordero.titulo_expulso',
            module: 'financeiro.contas_pagar',
            description: "Título {$payable->title_number} expulso do borderô {$bordero->number}: {$reason}",
            auditable: $payable,
        );

        $this->workflow->notifyPreparerOfRejection(
            $payable->fresh(),
            $actor,
            $reason,
            'expulsao',
        );
    }

    /** Reprova o borderô inteiro: pacote volta para pendente, títulos permanecem dentro. */
    public function reprovarBordero(Bordero $bordero, User $actor, string $reason): void
    {
        if ($bordero->status !== 'aguardando_aprovacao') {
            throw new \InvalidArgumentException('Este borderô não está aguardando aprovação.');
        }

        $bordero->load('payables');

        if (! $this->canReprovarBordero($bordero, $actor)) {
            throw new \InvalidArgumentException('Sem permissão para reprovar este borderô.');
        }

        DB::transaction(function () use ($bordero, $actor, $reason) {
            foreach ($bordero->payables as $payable) {
                ApprovalStep::where('payable_id', $payable->id)->delete();

                $payable->update([
                    'status' => 'pendente',
                    'rejection_reason' => $reason,
                    'sent_for_approval_at' => null,
                    'approved_at' => null,
                ]);

                $this->comment(
                    $payable,
                    $actor,
                    "Borderô {$bordero->number} reprovado por {$actor->name} em ".now()->format('d/m/Y H:i').". "
                    ."Motivo: {$reason}. O título permanece neste borderô (agora pendente) para correção e reenvio. "
                    .'Não aparece na lista avulsa de Contas a Pagar.',
                    'rejection',
                );
            }

            $bordero->update([
                'status' => 'pendente',
                'rejection_reason' => $reason,
                'sent_for_approval_at' => null,
                'approved_at' => null,
                'approved_by' => $actor->id,
            ]);
        });

        AuditLogger::log(
            event: 'bordero.reprovado',
            module: 'financeiro.contas_pagar',
            description: "Borderô {$bordero->number} reprovado (pacote devolvido para pendente): {$reason}",
            auditable: $bordero,
        );

        $bordero->load('payables');
        foreach ($bordero->payables as $payable) {
            $this->workflow->notifyPreparerOfRejection(
                $payable,
                $actor,
                $reason,
                'bordero',
                "/financeiro/borderos/{$bordero->id}",
            );
        }
    }

    /** Desfaz borderô pendente/em preparação e libera títulos para CP avulso. */
    public function desfazer(Bordero $bordero, User $actor, ?string $reason = null): void
    {
        if (! in_array($bordero->status, ['pendente', 'em_preparacao'], true)) {
            throw new \InvalidArgumentException('Só é possível desfazer borderôs pendentes ou em preparação.');
        }

        if (! $this->canDesfazer($actor)) {
            throw new \InvalidArgumentException('Sem permissão para desfazer borderô.');
        }

        $reason ??= 'Borderô desfeito manualmente.';

        DB::transaction(function () use ($bordero, $actor, $reason) {
            $payables = $bordero->payables()->get();

            foreach ($payables as $payable) {
                $payable->update(['bordero_id' => null]);

                $this->comment(
                    $payable,
                    $actor,
                    "Borderô {$bordero->number} desfeito por {$actor->name} em ".now()->format('d/m/Y H:i').". "
                    ."Motivo: {$reason}. Título devolvido para Contas a Pagar (pendente avulso).",
                    'status_change',
                );
            }

            $number = $bordero->number;
            $bordero->delete();

            AuditLogger::log(
                event: 'bordero.desfeito',
                module: 'financeiro.contas_pagar',
                description: "Borderô {$number} desfeito: {$reason}",
            );
        });
    }

    private function comment(Payable $payable, User $actor, string $body, string $type): void
    {
        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $actor->id,
            'body' => $body,
            'type' => $type,
        ]);
    }
}
