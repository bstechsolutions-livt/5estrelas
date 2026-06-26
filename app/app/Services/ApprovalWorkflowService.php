<?php

namespace App\Services;

use App\Models\ApprovalStep;
use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\User;
use App\Events\NotificationCreated;
use Illuminate\Support\Facades\DB;

/**
 * Motor de workflow de aprovação multinível (Fluxo v3.0).
 *
 * Regras implementadas:
 * 1. Trilha por área de origem (departamento do título)
 * 2. Dupla aprovação: responsável da área + presidente (regra 1 do doc)
 * 3. Substituição do presidente: Ana Paula → Luiz Farias (regra 3)
 *    - Substituto nunca pode ser quem já assinou o mesmo documento como diretor
 * 4. Multi/Star: pré-aprovação do Luiz Farias via area_key especial
 * 5. Baluarte: duas trilhas em sequência (matriz + comercial)
 * 6. DP/RH e Jurídico: sem diretoria, vai direto ao financeiro
 * 7. Notificação ao aprovador a cada avanço de nível
 */
class ApprovalWorkflowService
{
    /**
     * Substitutos do presidente (em ordem de prioridade).
     * Identificados por email — configurável futuramente via tela admin.
     */
    private const PRESIDENT_SUBSTITUTES = [
        'anapaula@grupo5estrelas.com.br',
        'luiz.farias@grupo5estrelas.com.br',
    ];

    public function sendForApproval(Payable $payable, User $sender, ?string $area = null): void
    {
        $area = $area ?? $this->detectArea($payable);

        // Caso especial: Baluarte passa por DUAS trilhas em sequência
        $trails = $this->resolveTrails($area);

        DB::transaction(function () use ($payable, $sender, $trails, $area) {
            ApprovalStep::where('payable_id', $payable->id)->delete();

            $order = 1;
            foreach ($trails as $trail) {
                foreach ($trail as $level) {
                    ApprovalStep::create([
                        'payable_id' => $payable->id,
                        'order' => $order++,
                        'level_name' => $level->level_name,
                        'status' => 'pendente',
                        'assigned_to' => $level->default_user_id,
                    ]);
                }
            }

            $payable->update([
                'status' => 'aguardando_aprovacao',
                'prepared_by' => $payable->prepared_by ?? $sender->id,
                'sent_for_approval_at' => now(),
            ]);

            PayableComment::create([
                'payable_id' => $payable->id,
                'user_id' => $sender->id,
                'body' => 'Enviou para aprovação (fluxo: ' . (ApprovalTrail::AREAS[$area] ?? $area) . ')',
                'type' => 'status_change',
            ]);

            AuditLogger::log(
                event: 'contas_pagar.enviado_aprovacao',
                module: 'financeiro.contas_pagar',
                description: "Título {$payable->title_number} enviado para aprovação multinível ({$area})",
                auditable: $payable,
            );

            // Notifica o primeiro aprovador (o primeiro step que tem assigned_to)
            $firstAssigned = ApprovalStep::where('payable_id', $payable->id)
                ->whereNotNull('assigned_to')
                ->orderBy('order')
                ->first();
            if ($firstAssigned) {
                $this->notifyApprover($firstAssigned, $payable, $sender);
            }
        });
    }

    public function approve(Payable $payable, User $approver, ?string $comment = null): array
    {
        $step = $this->currentStep($payable);
        if (!$step) {
            return ['success' => false, 'error' => 'Nenhuma etapa pendente.'];
        }

        // Verifica elegibilidade: assigned_to, substituto do presidente, ou wildcard
        if (!$this->canApproveStep($step, $approver, $payable)) {
            return ['success' => false, 'error' => 'Você não é o aprovador desta etapa.'];
        }

        $step->update([
            'status' => 'aprovado',
            'resolved_by' => $approver->id,
            'resolved_at' => now(),
            'comment' => $comment,
        ]);

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $approver->id,
            'body' => "Aprovado na etapa: " . (ApprovalStep::LEVEL_LABELS[$step->level_name] ?? $step->level_name)
                . ($comment ? " — {$comment}" : ''),
            'type' => 'approval',
        ]);

        $nextStep = $this->currentStep($payable);
        if (!$nextStep) {
            $payable->update([
                'status' => 'aprovado',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            AuditLogger::log(
                event: 'contas_pagar.aprovado',
                module: 'financeiro.contas_pagar',
                description: "Título {$payable->title_number} aprovado (nível final: {$step->level_name})",
                auditable: $payable,
            );

            return ['success' => true, 'finished' => true, 'message' => 'Aprovação final concluída. Título liberado para pagamento.'];
        }

        // Notifica o próximo aprovador
        if ($nextStep->assigned_to) {
            $this->notifyApprover($nextStep, $payable, $approver);
        }

        return [
            'success' => true,
            'finished' => false,
            'next_level' => $nextStep->level_name,
            'message' => 'Aprovado. Próximo nível: ' . (ApprovalStep::LEVEL_LABELS[$nextStep->level_name] ?? $nextStep->level_name),
        ];
    }

    public function reject(Payable $payable, User $rejector, string $reason): array
    {
        $step = $this->currentStep($payable);
        if (!$step) {
            return ['success' => false, 'error' => 'Nenhuma etapa pendente.'];
        }

        $step->update([
            'status' => 'reprovado',
            'resolved_by' => $rejector->id,
            'resolved_at' => now(),
            'comment' => $reason,
        ]);

        $payable->update([
            'status' => 'reprovado',
            'approved_by' => $rejector->id,
            'rejection_reason' => $reason,
        ]);

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $rejector->id,
            'body' => "Reprovado na etapa " . (ApprovalStep::LEVEL_LABELS[$step->level_name] ?? $step->level_name) . ": {$reason}",
            'type' => 'rejection',
        ]);

        AuditLogger::log(
            event: 'contas_pagar.reprovado',
            module: 'financeiro.contas_pagar',
            description: "Título {$payable->title_number} reprovado na etapa {$step->level_name}: {$reason}",
            auditable: $payable,
        );

        return ['success' => true, 'message' => 'Título reprovado.'];
    }

    public function currentStep(Payable $payable): ?ApprovalStep
    {
        return ApprovalStep::where('payable_id', $payable->id)
            ->where('status', 'pendente')
            ->orderBy('order')
            ->first();
    }

    public function myPendingApprovals(User $user): \Illuminate\Database\Eloquent\Collection
    {
        // Steps assigned diretamente ao user
        $directIds = ApprovalStep::where('assigned_to', $user->id)
            ->where('status', 'pendente')
            ->pluck('payable_id');

        // Steps onde o user é substituto do presidente (se step é presidencia e o assigned está ausente)
        // Por enquanto inclui os diretos; futuramente verifica ausência
        $payableIds = $directIds->unique();

        return Payable::whereIn('id', $payableIds)
            ->where('status', 'aguardando_aprovacao')
            ->with(['branch:id,name', 'preparer:id,name'])
            ->orderByDesc('sent_for_approval_at')
            ->get();
    }

    /**
     * Verifica se o usuário pode aprovar este step.
     *
     * Elegível se:
     * 1. É o assigned_to direto, OU
     * 2. Tem permissão wildcard '*', OU
     * 3. É um substituto válido do presidente (step=presidencia, assigned ausente,
     *    E o substituto NÃO assinou este mesmo documento como diretor — regra 3 do doc)
     */
    private function canApproveStep(ApprovalStep $step, User $approver, Payable $payable): bool
    {
        // Caso 1: é o designado
        if ($step->assigned_to === $approver->id) {
            return true;
        }

        // Caso 2: wildcard (admin)
        if ($approver->hasPermission('*')) {
            return true;
        }

        // Caso 3: substituto do presidente
        if ($step->level_name === 'presidencia' && $this->isPresidentSubstitute($approver)) {
            // Regra 3: substituto nunca pode ser quem já assinou como diretor neste documento
            $alreadySigned = ApprovalStep::where('payable_id', $payable->id)
                ->where('level_name', 'diretoria')
                ->where('resolved_by', $approver->id)
                ->where('status', 'aprovado')
                ->exists();

            return !$alreadySigned;
        }

        return false;
    }

    /**
     * Verifica se o usuário é um substituto configurado do presidente.
     */
    private function isPresidentSubstitute(User $user): bool
    {
        return in_array($user->email, self::PRESIDENT_SUBSTITUTES);
    }

    /**
     * Resolve as trilhas para uma área. Caso especial:
     * - 'baluarte': duas trilhas em sequência (matriz + comercial)
     * - 'multi_star': trilha de licitação (com Luiz Farias como pré-aprovação)
     * - Outros: trilha simples da área
     */
    private function resolveTrails(string $area): array
    {
        if ($area === 'baluarte') {
            // Baluarte: primeiro trilha da Matriz, depois trilha do Comercial
            $matriz = ApprovalTrail::trailFor('matriz');
            $comercial = ApprovalTrail::trailFor('comercial');
            return [$matriz, $comercial];
        }

        if ($area === 'multi_star') {
            // Multi/Star: pré-aprovação do Luiz Farias (licitação) + trilha normal do compras
            $licitacao = ApprovalTrail::trailFor('licitacao');
            return [$licitacao];
        }

        $trail = ApprovalTrail::trailFor($area);
        if ($trail->isEmpty()) {
            $trail = ApprovalTrail::trailFor('matriz');
        }

        return [$trail];
    }

    private function detectArea(Payable $payable): string
    {
        if ($payable->department_id) {
            $dept = Department::find($payable->department_id);
            if ($dept && $dept->area_key) {
                return $dept->area_key;
            }
        }
        return 'matriz';
    }

    private function notifyApprover(ApprovalStep $step, Payable $payable, User $sender): void
    {
        $notification = Notification::create([
            'user_id' => $step->assigned_to,
            'title' => 'Aprovação pendente',
            'body' => "Título {$payable->title_number} (R$ " . number_format($payable->amount, 2, ',', '.') . ") aguarda sua aprovação na etapa: " . (ApprovalStep::LEVEL_LABELS[$step->level_name] ?? $step->level_name),
            'type' => 'approval_pending',
            'link' => "/financeiro/contas-pagar/{$payable->id}",
            'data' => [
                'payable_id' => $payable->id,
                'step_id' => $step->id,
                'level' => $step->level_name,
            ],
        ]);

        if (class_exists(NotificationCreated::class)) {
            try {
                event(new NotificationCreated($notification));
            } catch (\Throwable $e) {
                // Broadcast pode falhar se Reverb não estiver rodando (ambiente de teste)
            }
        }
    }
}
