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

class ApprovalWorkflowService
{
    public function sendForApproval(Payable $payable, User $sender, ?string $area = null): void
    {
        $area = $area ?? $this->detectArea($payable);
        $trail = ApprovalTrail::trailFor($area);

        if ($trail->isEmpty()) {
            $trail = ApprovalTrail::trailFor('matriz');
        }

        DB::transaction(function () use ($payable, $sender, $trail, $area) {
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
                'body' => 'Enviou para aprovação (fluxo: ' . (ApprovalTrail::AREAS[$area] ?? 'padrão') . ')',
                'type' => 'status_change',
            ]);

            AuditLogger::log(
                event: 'contas_pagar.enviado_aprovacao',
                module: 'financeiro.contas_pagar',
                description: "Título {$payable->title_number} enviado para aprovação multinível",
                auditable: $payable,
            );

            // Notifica o primeiro aprovador
            $firstStep = ApprovalStep::where('payable_id', $payable->id)->orderBy('order')->first();
            if ($firstStep && $firstStep->assigned_to) {
                $this->notifyApprover($firstStep, $payable, $sender);
            }
        });
    }

    public function approve(Payable $payable, User $approver, ?string $comment = null): array
    {
        $step = $this->currentStep($payable);
        if (!$step) {
            return ['success' => false, 'error' => 'Nenhuma etapa pendente.'];
        }

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

        return ['success' => true, 'finished' => false, 'next_level' => $nextStep->level_name, 'message' => 'Aprovado. Próximo nível: ' . (ApprovalStep::LEVEL_LABELS[$nextStep->level_name] ?? $nextStep->level_name)];
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
            event(new NotificationCreated($notification));
        }
    }
}
