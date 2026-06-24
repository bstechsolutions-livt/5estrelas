<?php

namespace App\Services;

use App\Models\ApprovalStep;
use App\Models\ApprovalTrail;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Motor de workflow de aprovação multinível (Fluxo v3.0).
 *
 * - sendForApproval: cria os steps baseado na trilha da área do payable
 * - approve: avança pro próximo nível ou finaliza
 * - reject: reprova e volta ao departamento
 * - currentStep: retorna o step pendente atual
 * - myPendingApprovals: lista os payables aguardando aprovação do usuário
 */
class ApprovalWorkflowService
{
    /**
     * Envia um payable para aprovação, criando os steps da trilha.
     */
    public function sendForApproval(Payable $payable, User $sender, ?string $area = null): void
    {
        $area = $area ?? $this->detectArea($payable);
        $trail = ApprovalTrail::trailFor($area);

        if ($trail->isEmpty()) {
            // Fallback: trilha genérica (departamento → financeiro → presidência)
            $trail = ApprovalTrail::trailFor('matriz');
        }

        DB::transaction(function () use ($payable, $sender, $trail) {
            // Remove steps antigos (caso de reenvio após reprovação)
            ApprovalStep::where('payable_id', $payable->id)->delete();

            foreach ($trail as $level) {
                ApprovalStep::create([
                    'payable_id' => $payable->id,
                    'order' => $level->order,
                    'level_name' => $level->level_name,
                    'status' => 'pendente',
                    'assigned_to' => $level->default_user_id,
                ]);
            }

            $payable->update([
                'status' => 'aguardando_aprovacao',
                'prepared_by' => $payable->prepared_by ?? $sender->id,
                'sent_for_approval_at' => now(),
            ]);

            PayableComment::create([
                'payable_id' => $payable->id,
                'user_id' => $sender->id,
                'body' => 'Enviou para aprovação (fluxo: ' . (ApprovalTrail::AREAS[$this->detectArea($payable)] ?? 'padrão') . ')',
                'type' => 'status_change',
            ]);

            AuditLogger::log(
                event: 'contas_pagar.enviado_aprovacao',
                module: 'financeiro.contas_pagar',
                description: "Título {$payable->title_number} enviado para aprovação multinível",
                auditable: $payable,
            );
        });
    }

    /**
     * Aprova o step atual. Se for o último, marca o payable como aprovado.
     */
    public function approve(Payable $payable, User $approver, ?string $comment = null): array
    {
        $step = $this->currentStep($payable);
        if (!$step) {
            return ['success' => false, 'error' => 'Nenhuma etapa pendente.'];
        }

        // Verifica se o approver é o assignee (ou tem permissão wildcard pra demo)
        if ($step->assigned_to && $step->assigned_to !== $approver->id && !$approver->hasPermission('*')) {
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

        // Verifica se era o último step
        $nextStep = $this->currentStep($payable);
        if (!$nextStep) {
            // Fluxo completo — payable aprovado
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

        return ['success' => true, 'finished' => false, 'next_level' => $nextStep->level_name, 'message' => 'Aprovado. Próximo nível: ' . (ApprovalStep::LEVEL_LABELS[$nextStep->level_name] ?? $nextStep->level_name)];
    }

    /**
     * Reprova o step atual. O payable volta a reprovado.
     */
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

    /**
     * Retorna o step pendente atual (o primeiro não resolvido em ordem).
     */
    public function currentStep(Payable $payable): ?ApprovalStep
    {
        return ApprovalStep::where('payable_id', $payable->id)
            ->where('status', 'pendente')
            ->orderBy('order')
            ->first();
    }

    /**
     * Lista payables com step pendente atribuído ao usuário.
     */
    public function myPendingApprovals(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $payableIds = ApprovalStep::where('assigned_to', $user->id)
            ->where('status', 'pendente')
            ->pluck('payable_id')
            ->unique();

        return Payable::whereIn('id', $payableIds)
            ->where('status', 'aguardando_aprovacao')
            ->with(['branch:id,name', 'preparer:id,name'])
            ->orderByDesc('sent_for_approval_at')
            ->get();
    }

    /**
     * Detecta a área do payable (baseado em branch/category/descrição).
     * Por enquanto usa 'matriz' como default — futuramente vincula ao departamento.
     */
    private function detectArea(Payable $payable): string
    {
        // TODO: Vincular payable a uma área real (via branch ou campo 'area')
        return 'matriz';
    }
}
