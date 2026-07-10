<?php

namespace App\Services;

use App\Models\ApprovalStep;
use App\Models\ApprovalTrail;
use App\Models\Bordero;
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
        $department = null;

        if ($area === null) {
            $preview = $this->buildPreviewStepsForSender($sender);
            if (! $preview['ok']) {
                throw new \InvalidArgumentException($preview['errors'][0] ?? 'Não foi possível enviar para aprovação.');
            }
            $area = $preview['area'];
            $payable->update(['department_id' => $preview['department']['id']]);
            $payable->refresh();
            $department = Department::with(['manager:id,name', 'director:id,name'])->find($preview['department']['id']);
        } else {
            if ($sender->department_id) {
                $payable->update(['department_id' => $sender->department_id]);
                $payable->refresh();
            }
            if ($payable->department_id) {
                $department = Department::with(['manager:id,name', 'director:id,name'])->find($payable->department_id);
            }
        }

        $trails = $this->resolveTrails($area);

        DB::transaction(function () use ($payable, $sender, $trails, $area, $department) {
            ApprovalStep::where('payable_id', $payable->id)->delete();

            $order = 1;
            foreach ($this->iterEffectiveTrailLevels($area, $department) as $item) {
                $level = $item['level'];
                $trailArea = $item['trail_area'];
                ApprovalStep::create([
                    'payable_id' => $payable->id,
                    'order' => $order++,
                    'level_name' => $level->level_name,
                    'status' => 'pendente',
                    'assigned_to' => $this->resolveAssigneeId($level, $department, $trailArea),
                ]);
            }

            $payable->update([
                'status' => 'aguardando_aprovacao',
                'prepared_by' => $payable->prepared_by ?? $sender->id,
                'sent_for_approval_at' => now(),
                'rejection_reason' => null,
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

        $stepLabel = ApprovalStep::LEVEL_LABELS[$step->level_name] ?? $step->level_name;

        DB::transaction(function () use ($payable, $rejector, $reason, $step, $stepLabel) {
            // Encerra o fluxo atual — na próxima submissão as etapas são recriadas.
            ApprovalStep::where('payable_id', $payable->id)->delete();

            $payable->update([
                'status' => 'pendente',
                'approved_by' => $rejector->id,
                'rejection_reason' => $reason,
                'sent_for_approval_at' => null,
                'approved_at' => null,
            ]);

            PayableComment::create([
                'payable_id' => $payable->id,
                'user_id' => $rejector->id,
                'body' => "Reprovado na etapa {$stepLabel} e devolvido para pendente: {$reason}",
                'type' => 'rejection',
            ]);

            $this->syncBorderoAfterPayableReject($payable, $rejector, $reason);
        });

        AuditLogger::log(
            event: 'contas_pagar.reprovado',
            module: 'financeiro.contas_pagar',
            description: "Título {$payable->title_number} reprovado na etapa {$step->level_name} e devolvido para pendente: {$reason}",
            auditable: $payable,
        );

        return [
            'success' => true,
            'message' => 'Título devolvido para pendente. Corrija e reenvie para aprovação.',
        ];
    }

    /** Após reprovação, borderô com títulos pendentes volta para rascunho. */
    private function syncBorderoAfterPayableReject(Payable $payable, User $rejector, string $reason): void
    {
        if (! $payable->bordero_id) {
            return;
        }

        $bordero = Bordero::find($payable->bordero_id);
        if (! $bordero) {
            return;
        }

        $bordero->syncStatusFromPayables();

        if ($bordero->status === 'rascunho') {
            $bordero->update([
                'rejection_reason' => $reason,
                'approved_by' => $rejector->id,
                'sent_for_approval_at' => null,
                'approved_at' => null,
            ]);
        }
    }

    public function currentStep(Payable $payable): ?ApprovalStep
    {
        return ApprovalStep::where('payable_id', $payable->id)
            ->where('status', 'pendente')
            ->orderBy('order')
            ->first();
    }

    public function canUserApprove(Payable $payable, User $user): bool
    {
        if ($payable->status !== 'aguardando_aprovacao') {
            return false;
        }

        $step = $this->currentStep($payable);

        return $step && $this->canApproveStep($step, $user, $payable);
    }

    /**
     * Aprova, no borderô, cada título em que o usuário é o aprovador da etapa atual.
     *
     * @return array{count: int, message: string, error: ?string}
     */
    public function approveEligibleInBordero(iterable $payables, User $approver, ?string $comment = null): array
    {
        $approved = 0;
        $message = '';
        $errors = [];

        foreach ($payables as $payable) {
            if (! $this->canUserApprove($payable, $approver)) {
                continue;
            }

            $result = $this->approve($payable, $approver, $comment);
            if ($result['success']) {
                $approved++;
                $message = $result['message'];
            } else {
                $errors[] = $result['error'];
            }
        }

        if ($approved === 0) {
            return [
                'count' => 0,
                'message' => '',
                'error' => $errors[0] ?? 'Nenhum título neste borderô aguarda sua aprovação nesta etapa.',
            ];
        }

        return ['count' => $approved, 'message' => $message, 'error' => null];
    }

    /**
     * Reprova, no borderô, cada título em que o usuário é o aprovador da etapa atual.
     *
     * @return array{count: int, message: string, error: ?string}
     */
    public function rejectEligibleInBordero(iterable $payables, User $rejector, string $reason): array
    {
        $rejected = 0;
        $message = '';
        $errors = [];

        foreach ($payables as $payable) {
            if (! $this->canUserApprove($payable, $rejector)) {
                continue;
            }

            $result = $this->reject($payable, $rejector, $reason);
            if ($result['success']) {
                $rejected++;
                $message = $result['message'];
            } else {
                $errors[] = $result['error'];
            }
        }

        if ($rejected === 0) {
            return [
                'count' => 0,
                'message' => '',
                'error' => $errors[0] ?? 'Nenhum título neste borderô aguarda sua reprovação nesta etapa.',
            ];
        }

        return ['count' => $rejected, 'message' => $message, 'error' => null];
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
     * Valida se o remetente pode submeter e monta o preview da trilha de aprovação.
     *
     * @return array{
     *   ok: bool,
     *   errors: string[],
     *   department: ?array{id: int, name: string},
     *   area: ?string,
     *   area_label: ?string,
     *   steps: array<int, array{order: int, level_name: string, level_label: string, role_label: string, assignee_id: ?int, assignee_name: ?string, configured: bool}>
     * }
     */
    public function buildPreviewStepsForSender(User $sender): array
    {
        $base = $this->validateSenderDepartment($sender);
        if (! $base['department']) {
            return [
                'ok' => false,
                'errors' => $base['errors'],
                'department' => null,
                'area' => null,
                'area_label' => null,
                'steps' => [],
            ];
        }

        /** @var Department $department */
        $department = $base['department'];
        $errors = $base['errors'];
        $area = $base['area'];

        if (! $area) {
            return [
                'ok' => false,
                'errors' => array_values(array_unique($errors)),
                'department' => ['id' => $department->id, 'name' => $department->name],
                'area' => null,
                'area_label' => null,
                'steps' => [],
            ];
        }

        $steps = [];
        $order = 1;
        foreach ($this->iterEffectiveTrailLevels($area, $department) as $item) {
            $level = $item['level'];
            $trailArea = $item['trail_area'];
            $assigneeId = $this->resolveAssigneeId($level, $department, $trailArea);
            $assigneeName = $assigneeId ? User::whereKey($assigneeId)->value('name') : null;
            $roleLabel = $this->effectiveRoleLabel($level, $department, $trailArea);
            $steps[] = [
                'order' => $order++,
                'level_name' => $level->level_name,
                'level_label' => ApprovalStep::LEVEL_LABELS[$level->level_name] ?? $level->level_name,
                'role_label' => $roleLabel,
                'assignee_id' => $assigneeId,
                'assignee_name' => $assigneeName,
                'configured' => $assigneeId !== null,
            ];
        }

        foreach ($steps as $step) {
            if (! $step['configured']) {
                $errors[] = "A etapa \"{$step['role_label']}\" não tem aprovador configurado.";
            }
        }

        if ($steps === []) {
            $errors[] = 'Não há etapas de aprovação configuradas para o seu departamento.';
        }

        $errors = array_values(array_unique($errors));

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'department' => ['id' => $department->id, 'name' => $department->name],
            'area' => $area,
            'area_label' => ApprovalTrail::AREAS[$area] ?? $area,
            'steps' => $steps,
        ];
    }

    /** @return array{errors: string[], department: ?Department, area: ?string} */
    private function validateSenderDepartment(User $sender): array
    {
        $errors = [];

        if (! $sender->department_id) {
            return [
                'errors' => ['Seu usuário não está vinculado a um departamento. Solicite ao administrador.'],
                'department' => null,
                'area' => null,
            ];
        }

        $department = Department::with(['manager:id,name', 'director:id,name'])
            ->where('is_active', true)
            ->find($sender->department_id);

        if (! $department) {
            return [
                'errors' => ['Departamento do usuário não encontrado ou inativo.'],
                'department' => null,
                'area' => null,
            ];
        }

        if (! $department->area_key) {
            $errors[] = "O departamento \"{$department->name}\" não possui área de aprovação configurada.";
        }

        $area = $department->area_key;

        if ($area && ! $this->areaHasTrail($area)) {
            $label = ApprovalTrail::AREAS[$area] ?? $area;
            $errors[] = "Não há fluxo de aprovação configurado para \"{$label}\".";
        }

        return [
            'errors' => $errors,
            'department' => $department,
            'area' => $area,
        ];
    }

    private function areaHasTrail(string $area): bool
    {
        if ($area === 'baluarte') {
            return ApprovalTrail::trailFor('matriz')->isNotEmpty()
                && ApprovalTrail::trailFor('comercial')->isNotEmpty();
        }

        if ($area === 'multi_star') {
            return ApprovalTrail::trailFor('licitacao')->isNotEmpty();
        }

        return ApprovalTrail::trailFor($area)->isNotEmpty();
    }

    private function resolveAssigneeId(ApprovalTrail $level, ?Department $department, ?string $area = null): ?int
    {
        if ($level->level_name === 'departamento') {
            if ($department?->manager_id) {
                return $department->manager_id;
            }
            if ($area) {
                return ApprovalTrail::where('area', $area)
                    ->where('level_name', 'gerencia')
                    ->value('default_user_id');
            }

            return null;
        }

        if ($level->level_name === 'diretoria' && $department?->director_id) {
            return $department->director_id;
        }

        return $level->default_user_id;
    }

    /** @return \Generator<int, array{level: ApprovalTrail, trail_area: string}> */
    private function iterEffectiveTrailLevels(string $area, ?Department $department): \Generator
    {
        foreach ($this->resolveTrails($area) as $trail) {
            $trailArea = $trail->first()?->area ?? $area;
            $skipGerencia = $this->shouldSkipGerenciaStep($department, $trailArea);

            foreach ($trail as $level) {
                if ($skipGerencia && $level->level_name === 'gerencia') {
                    continue;
                }
                yield ['level' => $level, 'trail_area' => $trailArea];
            }
        }
    }

    private function shouldSkipGerenciaStep(?Department $department, string $area): bool
    {
        // A 1ª etapa (departamento) absorve a gerência — evita Erismar duas vezes.
        return ApprovalTrail::where('area', $area)->where('level_name', 'gerencia')->exists();
    }

    private function effectiveRoleLabel(ApprovalTrail $level, ?Department $department, string $area): string
    {
        if ($level->level_name === 'departamento') {
            $gerencia = ApprovalTrail::where('area', $area)->where('level_name', 'gerencia')->first();
            if ($gerencia) {
                return $gerencia->role_label;
            }
        }

        return $level->role_label;
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
