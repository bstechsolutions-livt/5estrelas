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
use App\Models\UserRepresentative;
use App\Events\NotificationCreated;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
        'farias@grupo5estrelas.com.br',
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

        DB::transaction(function () use ($payable, $sender, $area, $department) {
            $this->replaceApprovalSteps($payable, $area, $department);

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

            // Notifica o primeiro aprovador da 1ª etapa efetiva
            $firstStep = ApprovalStep::where('payable_id', $payable->id)
                ->where('status', 'pendente')
                ->orderBy('order')
                ->first();
            if ($firstStep && ($firstStep->assigned_to || $this->isWildcardStep($firstStep))) {
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
            'body' => "Aprovado na etapa: " . ($step->role_label ?: (ApprovalStep::LEVEL_LABELS[$step->level_name] ?? $step->level_name))
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
        if ($this->activeDelegatedTo($nextStep) || $nextStep->assigned_to || $this->isWildcardStep($nextStep)) {
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

    /** Após reprovação avulsa de título ainda no borderô (legado); expulsão usa BorderoActionService. */
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

        if ($bordero->status === Bordero::STATUS_PENDENTE) {
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

    /**
     * Cria etapas de aprovação para títulos que estão em aguardando_aprovacao sem fluxo (ex.: migração Gestor).
     *
     * @return array{ok: bool, skipped?: bool, created?: int, error?: string}
     */
    public function ensureWorkflowSteps(Payable $payable): array
    {
        if ($payable->status !== 'aguardando_aprovacao') {
            return ['ok' => false, 'error' => 'Status não é aguardando_aprovacao'];
        }

        if (ApprovalStep::where('payable_id', $payable->id)->exists()) {
            return ['ok' => true, 'skipped' => true];
        }

        $sender = $payable->relationLoaded('preparer')
            ? $payable->preparer
            : ($payable->prepared_by ? User::find($payable->prepared_by) : null);

        if (! $sender) {
            return ['ok' => false, 'error' => 'Sem preparador vinculado'];
        }

        if (! $payable->department_id && $sender->department_id) {
            $payable->update(['department_id' => $sender->department_id]);
            $payable->refresh();
        }

        $preview = $this->buildPreviewStepsForSender($sender);
        if (! $preview['ok']) {
            return ['ok' => false, 'error' => implode('; ', $preview['errors'])];
        }

        $department = Department::with(['manager:id,name', 'director:id,name'])
            ->find($preview['department']['id']);

        $created = DB::transaction(function () use ($payable, $preview, $department) {
            return $this->replaceApprovalSteps($payable, $preview['area'], $department);
        });

        return ['ok' => true, 'created' => $created];
    }

    /**
     * Cria etapas e posiciona o fluxo conforme fase migrada do Gestor.
     *
     * @return array{ok: bool, phase?: string, target_order?: int, target_level?: string, created?: int, error?: string}
     */
    public function createStepsAtGestorPhase(
        Payable $payable,
        User $sender,
        string $phase,
        ?Carbon $priorStepsApprovedAt = null,
    ): array {
        if (! in_array($phase, ['department', 'analysis', 'final'], true)) {
            return ['ok' => false, 'error' => "Fase inválida: {$phase}"];
        }

        if (! $payable->department_id && $sender->department_id) {
            $payable->update(['department_id' => $sender->department_id]);
            $payable->refresh();
        }

        $preview = $this->buildPreviewStepsForSender($sender);
        if (! $preview['ok']) {
            return ['ok' => false, 'error' => implode('; ', $preview['errors'])];
        }

        $department = Department::with(['manager:id,name', 'director:id,name'])
            ->find($preview['department']['id']);

        $resolvedAt = $priorStepsApprovedAt ?? now();

        $result = DB::transaction(function () use ($payable, $preview, $department, $phase, $resolvedAt) {
            $created = $this->replaceApprovalSteps($payable, $preview['area'], $department);

            $steps = ApprovalStep::where('payable_id', $payable->id)
                ->orderBy('order')
                ->get();

            $targetOrder = $this->resolveGestorPhaseTargetOrder($steps, $phase);
            $targetStep = $steps->firstWhere('order', $targetOrder);

            foreach ($steps as $step) {
                if ($step->order < $targetOrder) {
                    $step->update([
                        'status' => 'aprovado',
                        'resolved_at' => $resolvedAt,
                    ]);
                }
            }

            return [
                'created' => $created,
                'target_order' => $targetOrder,
                'target_level' => $targetStep?->level_name,
            ];
        });

        return [
            'ok' => true,
            'phase' => $phase,
            'created' => $result['created'],
            'target_order' => $result['target_order'],
            'target_level' => $result['target_level'],
        ];
    }

    /**
     * @param  Collection<int, ApprovalStep>  $steps
     */
    private function resolveGestorPhaseTargetOrder(Collection $steps, string $phase): int
    {
        if ($steps->isEmpty()) {
            return 1;
        }

        return match ($phase) {
            'department' => (int) $steps->first()->order,
            'analysis' => $this->resolveAnalysisTargetOrder($steps),
            'final' => (int) $steps->last()->order,
            default => (int) $steps->first()->order,
        };
    }

    /**
     * @param  Collection<int, ApprovalStep>  $steps
     */
    private function resolveAnalysisTargetOrder(Collection $steps): int
    {
        $finance = $steps->firstWhere('level_name', 'financeiro');
        if ($finance) {
            return (int) $finance->order;
        }

        $department = $steps->firstWhere('level_name', 'departamento');
        if ($department) {
            $next = $steps->first(fn (ApprovalStep $s) => $s->order > $department->order);

            return (int) ($next?->order ?? $department->order);
        }

        return (int) $steps->first()->order;
    }

    private function replaceApprovalSteps(Payable $payable, string $area, ?Department $department): int
    {
        ApprovalStep::where('payable_id', $payable->id)->delete();

        $order = 1;
        foreach ($this->iterEffectiveTrailLevels($area, $department) as $item) {
            $level = $item['level'];
            $trailArea = $item['trail_area'];
            ApprovalStep::create([
                'payable_id' => $payable->id,
                'order' => $order++,
                'level_name' => $level->level_name,
                'role_label' => $level->role_label,
                'approver_type' => $level->effectiveApproverType(),
                'approver_department_id' => $level->approver_department_id,
                'status' => 'pendente',
                'assigned_to' => $this->resolveAssigneeId($level, $department, $trailArea),
            ]);
        }

        return $order - 1;
    }

    public function isFinanceStep(?ApprovalStep $step): bool
    {
        return $step !== null && $step->level_name === 'financeiro';
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
        $payableIds = ApprovalStep::where('assigned_to', $user->id)
            ->where('status', 'pendente')
            ->get()
            ->reject(function (ApprovalStep $step) use ($user) {
                $delegateId = $this->activeDelegatedTo($step);

                return $delegateId !== null && $delegateId !== $user->id;
            })
            ->pluck('payable_id');

        $delegatedPayableIds = ApprovalStep::where('status', 'pendente')
            ->whereNotNull('delegated_to')
            ->get()
            ->filter(fn (ApprovalStep $step) => $this->activeDelegatedTo($step) === $user->id)
            ->pluck('payable_id');

        $payableIds = $payableIds->merge($delegatedPayableIds);

        $representedIds = app(UserRepresentativeService::class)
            ->representedUserIds($user, UserRepresentative::SCOPE_FINANCEIRO_APROVACAO);

        if ($representedIds !== []) {
            $asRepPayableIds = ApprovalStep::whereIn('assigned_to', $representedIds)
                ->where('status', 'pendente')
                ->get()
                ->reject(function (ApprovalStep $step) use ($user) {
                    $delegateId = $this->activeDelegatedTo($step);

                    return $delegateId !== null && $delegateId !== $user->id;
                })
                ->pluck('payable_id');
            $payableIds = $payableIds->merge($asRepPayableIds);
        }

        if ($this->userBelongsToFinanceDepartment($user)) {
            $financePayables = ApprovalStep::where('approver_type', ApprovalTrail::TYPE_DEPT_FINANCEIRO)
                ->where('status', 'pendente')
                ->pluck('payable_id');
            $payableIds = $payableIds->merge($financePayables);
        }

        if ($user->hasPermission('*') === false) {
            $presidencyIds = ApprovalStep::where('approver_type', ApprovalTrail::TYPE_USUARIO)
                ->whereIn('level_name', ApprovalStep::presidencyLevelNames())
                ->where('status', 'pendente')
                ->pluck('payable_id');
            if ($presidencyIds->isNotEmpty() && $this->isPresidentSubstitute($user)) {
                $payableIds = $payableIds->merge($presidencyIds);
            }
        }

        $payables = Payable::whereIn('id', $payableIds->unique())
            ->where('status', 'aguardando_aprovacao')
            ->with(['branch:id,name,code', 'preparer:id,name'])
            ->orderByDesc('sent_for_approval_at')
            ->get();

        // Etapas futuras nascem como "pendente"; só entra na fila quem age na etapa atual.
        $payables = $payables
            ->filter(fn (Payable $payable) => $this->canUserApprove($payable, $user))
            ->values();

        Payable::attachEmpresaNome($payables);
        Payable::attachFilialNome($payables);
        Payable::attachSupplierDisplayName($payables);

        return $payables;
    }

    /**
     * Títulos na etapa Presidência aguardando ação do usuário (painel dedicado).
     */
    public function presidencyDeskPayables(User $user): Collection
    {
        $branchScope = app(PayableBranchScope::class);

        $candidateIds = ApprovalStep::query()
            ->whereIn('level_name', ApprovalStep::presidencyLevelNames())
            ->where('status', 'pendente')
            ->pluck('payable_id')
            ->unique();

        if ($candidateIds->isEmpty()) {
            return collect();
        }

        $payables = Payable::query()
            ->whereIn('id', $candidateIds)
            ->where('status', 'aguardando_aprovacao')
            ->with([
                'documents.uploader:id,name',
                'preparer:id,name',
                'department:id,name',
                'branch:id,name,code',
            ])
            ->orderBy('due_date')
            ->orderBy('sent_for_approval_at')
            ->get();

        $payables = $payables->filter(function (Payable $payable) use ($user, $branchScope) {
            if (! $branchScope->canAccessPayable($user, $payable)) {
                return false;
            }

            $current = $this->currentStep($payable);

            return $current
                && ApprovalStep::isPresidencyLevel($current->level_name)
                && $this->canUserApprove($payable, $user);
        })->values();

        Payable::attachEmpresaNome($payables);
        Payable::attachFilialNome($payables);
        Payable::attachSupplierDisplayName($payables);

        return $payables;
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
            $assigneeName = $this->resolveAssigneeName($level, $assigneeId);
            $roleLabel = $level->role_label;
            $type = $level->effectiveApproverType();
            $configured = match ($type) {
                ApprovalTrail::TYPE_DEPT_FINANCEIRO => Department::financeApprovers()->exists() || $assigneeId !== null,
                ApprovalTrail::TYPE_DEPARTAMENTO => $assigneeId !== null || $this->departmentHasMembers($level->approver_department_id),
                default => $assigneeId !== null,
            };
            $steps[] = [
                'order' => $order++,
                'level_name' => $level->level_name,
                'level_label' => $roleLabel,
                'role_label' => $roleLabel,
                'approver_type' => $type,
                'assignee_id' => $assigneeId,
                'assignee_name' => $assigneeName,
                'configured' => $configured,
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
        return match ($level->effectiveApproverType()) {
            ApprovalTrail::TYPE_GESTOR_DEPTO => $department?->manager_id
                ?? ($area ? ApprovalTrail::where('area', $area)->where('level_name', 'gerencia')->value('default_user_id') : null),
            ApprovalTrail::TYPE_DIRETOR_DEPTO => $department?->director_id ?? $level->default_user_id,
            ApprovalTrail::TYPE_DEPT_FINANCEIRO => Department::financeApprovers()->exists()
                ? null
                : $level->default_user_id,
            ApprovalTrail::TYPE_DEPARTAMENTO => $this->resolveDepartmentManagerId($level->approver_department_id),
            ApprovalTrail::TYPE_USUARIO => $level->default_user_id,
            default => $level->default_user_id,
        };
    }

    private function resolveDepartmentManagerId(?int $departmentId): ?int
    {
        if (! $departmentId) {
            return null;
        }

        return Department::whereKey($departmentId)->value('manager_id');
    }

    private function departmentHasMembers(?int $departmentId): bool
    {
        if (! $departmentId) {
            return false;
        }

        return User::where('department_id', $departmentId)->where('is_active', true)->exists();
    }

    /** @return \Generator<int, array{level: ApprovalTrail, trail_area: string}> */
    private function iterEffectiveTrailLevels(string $area, ?Department $department): \Generator
    {
        foreach ($this->resolveTrails($area) as $trail) {
            $trailArea = $trail->first()?->area ?? $area;

            foreach ($trail as $level) {
                if ($this->shouldSkipLevel($level, $department, $trailArea)) {
                    continue;
                }
                yield ['level' => $level, 'trail_area' => $trailArea];
            }
        }
    }

    private function shouldSkipLevel(ApprovalTrail $level, ?Department $department, string $area): bool
    {
        if ($level->level_name === 'gerencia' && $this->trailHasGestorDepto($area)) {
            return true;
        }

        $type = $level->effectiveApproverType();

        if (in_array($type, [ApprovalTrail::TYPE_GESTOR_DEPTO, ApprovalTrail::TYPE_DEPT_FINANCEIRO], true)) {
            if ($type === ApprovalTrail::TYPE_DEPT_FINANCEIRO && Department::financeApprovers()->exists()) {
                return false;
            }

            return false;
        }

        if ($type === ApprovalTrail::TYPE_DEPARTAMENTO) {
            return $this->resolveAssigneeId($level, $department, $area) === null
                && ! $this->departmentHasMembers($level->approver_department_id);
        }

        return $this->resolveAssigneeId($level, $department, $area) === null;
    }

    private function trailHasGestorDepto(string $area): bool
    {
        return ApprovalTrail::where('area', $area)
            ->where(function ($q) {
                $q->where('approver_type', ApprovalTrail::TYPE_GESTOR_DEPTO)
                    ->orWhere('level_name', 'departamento');
            })
            ->exists();
    }

    private function resolveAssigneeName(ApprovalTrail $level, ?int $assigneeId): ?string
    {
        $type = $level->effectiveApproverType();

        if ($type === ApprovalTrail::TYPE_DEPT_FINANCEIRO && Department::financeApprovers()->exists()) {
            $count = Department::financeApprovers()->count();

            return "Equipe Financeiro ({$count})";
        }

        if ($type === ApprovalTrail::TYPE_DEPARTAMENTO && ! $assigneeId && $level->approver_department_id) {
            $dept = Department::find($level->approver_department_id);
            $count = User::where('department_id', $level->approver_department_id)->where('is_active', true)->count();

            return $dept ? "{$dept->name} ({$count})" : null;
        }

        return $assigneeId ? User::whereKey($assigneeId)->value('name') : null;
    }

    private function stepApproverType(ApprovalStep $step): string
    {
        if ($step->approver_type) {
            return $step->approver_type;
        }

        return match ($step->level_name) {
            'departamento' => ApprovalTrail::TYPE_GESTOR_DEPTO,
            'diretoria' => ApprovalTrail::TYPE_DIRETOR_DEPTO,
            'financeiro' => ApprovalTrail::TYPE_DEPT_FINANCEIRO,
            default => ApprovalTrail::TYPE_USUARIO,
        };
    }

    private function isWildcardStep(ApprovalStep $step): bool
    {
        $type = $this->stepApproverType($step);

        return $type === ApprovalTrail::TYPE_DEPT_FINANCEIRO
            || ($type === ApprovalTrail::TYPE_DEPARTAMENTO && $step->approver_department_id);
    }

    private function userBelongsToDepartment(User $user, int $departmentId): bool
    {
        return $user->department_id === $departmentId;
    }

    private function userBelongsToFinanceDepartment(User $user): bool
    {
        $financeId = Department::financeDepartmentId();

        return $financeId && $user->department_id === $financeId;
    }

    /**
     * Verifica se o usuário pode aprovar este step.
     *
     * Elegível se:
     * 1. É o assigned_to direto (ou representante ativo configurado no usuário), OU
     * 2. Tem permissão wildcard '*', OU
     * 3. É um substituto válido do presidente (step=presidencia, assigned ausente,
     *    E o substituto NÃO assinou este mesmo documento como diretor — regra 3 do doc)
     */
    private function canApproveStep(ApprovalStep $step, User $approver, Payable $payable): bool
    {
        $delegateId = $this->activeDelegatedTo($step);

        if ($delegateId !== null) {
            if ($approver->id === $delegateId) {
                return true;
            }
        } elseif ($step->assigned_to === $approver->id) {
            return true;
        } elseif ($step->assigned_to && $this->isUserRepresentativeOf($approver, (int) $step->assigned_to)) {
            return true;
        }

        // Caso 2: wildcard (admin)
        if ($approver->hasPermission('*')) {
            return true;
        }

        // Etapa financeiro: qualquer integrante do departamento Financeiro
        if ($this->stepApproverType($step) === ApprovalTrail::TYPE_DEPT_FINANCEIRO
            && $this->userBelongsToFinanceDepartment($approver)) {
            return true;
        }

        // Etapa departamento específico: gestor ou qualquer membro ativo
        if ($this->stepApproverType($step) === ApprovalTrail::TYPE_DEPARTAMENTO
            && $step->approver_department_id
            && $this->userBelongsToDepartment($approver, (int) $step->approver_department_id)) {
            return true;
        }

        // Caso 3: substituto do presidente
        if (ApprovalStep::isPresidencyLevel($step->level_name) && $this->isPresidentSubstitute($approver)) {
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

    private function isUserRepresentativeOf(User $representative, int $userId): bool
    {
        return app(UserRepresentativeService::class)->isActiveRepresentative(
            $representative,
            $userId,
            UserRepresentative::SCOPE_FINANCEIRO_APROVACAO,
        );
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
        $recipients = collect();
        $delegateId = $this->activeDelegatedTo($step);

        if ($delegateId !== null) {
            $recipients = collect([$delegateId]);
        } elseif ($this->stepApproverType($step) === ApprovalTrail::TYPE_DEPT_FINANCEIRO) {
            $recipients = Department::financeApprovers()->pluck('id');
            if ($recipients->isEmpty() && $step->assigned_to) {
                $recipients = collect([$step->assigned_to]);
            }
        } elseif ($this->stepApproverType($step) === ApprovalTrail::TYPE_DEPARTAMENTO && $step->approver_department_id) {
            $recipients = User::where('department_id', $step->approver_department_id)
                ->where('is_active', true)
                ->pluck('id');
            if ($recipients->isEmpty() && $step->assigned_to) {
                $recipients = collect([$step->assigned_to]);
            }
        } elseif ($step->assigned_to) {
            $recipients = collect([$step->assigned_to]);
        }

        if ($step->assigned_to && $delegateId === null) {
            $repIds = UserRepresentative::query()
                ->where('user_id', $step->assigned_to)
                ->where('is_active', true)
                ->whereDate('starts_at', '<=', now()->toDateString())
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', now()->toDateString());
                })
                ->get()
                ->filter(fn (UserRepresentative $r) => $r->coversScope(UserRepresentative::SCOPE_FINANCEIRO_APROVACAO))
                ->pluck('representative_id');
            $recipients = $recipients->merge($repIds);
        }

        foreach ($recipients->unique()->filter() as $userId) {
            $notification = Notification::create([
                'user_id' => $userId,
                'title' => 'Aprovação pendente',
                'body' => "Título {$payable->title_number} (R$ " . number_format($payable->amount, 2, ',', '.') . ") aguarda sua aprovação na etapa: " . ($step->role_label ?: (ApprovalStep::LEVEL_LABELS[$step->level_name] ?? $step->level_name)),
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

    public function activeDelegatedTo(ApprovalStep $step): ?int
    {
        if ($step->status !== 'pendente' || ! $step->delegated_to) {
            return null;
        }

        if ($step->delegation_expires_at && $step->delegation_expires_at->isPast()) {
            return null;
        }

        return (int) $step->delegated_to;
    }

    public function canManageStepDelegation(User $actor, ApprovalStep $step, Payable $payable): bool
    {
        if ($payable->status !== 'aguardando_aprovacao' || $step->status !== 'pendente') {
            return false;
        }

        if (! $this->isDelegatableStep($step)) {
            return false;
        }

        if ($actor->hasPermission('*') || $actor->hasPermission('financeiro.workflows.delegar')) {
            return true;
        }

        return $step->assigned_to === $actor->id;
    }

    public function isDelegatableStep(ApprovalStep $step): bool
    {
        if ($step->status !== 'pendente') {
            return false;
        }

        if ($step->assigned_to) {
            return true;
        }

        return in_array($this->stepApproverType($step), [
            ApprovalTrail::TYPE_USUARIO,
            ApprovalTrail::TYPE_GESTOR_DEPTO,
            ApprovalTrail::TYPE_DIRETOR_DEPTO,
        ], true);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function delegateStep(
        ApprovalStep $step,
        Payable $payable,
        User $actor,
        User $delegate,
        ?Carbon $expiresAt = null,
        ?string $reason = null,
    ): array {
        if (! $this->canManageStepDelegation($actor, $step, $payable)) {
            return ['ok' => false, 'error' => 'Sem permissão para delegar esta etapa.'];
        }

        if (! $delegate->is_active) {
            return ['ok' => false, 'error' => 'O substituto precisa ser um usuário ativo.'];
        }

        if ($step->assigned_to && $delegate->id === $step->assigned_to) {
            return ['ok' => false, 'error' => 'Escolha alguém diferente do aprovador original.'];
        }

        $step->update([
            'delegated_to' => $delegate->id,
            'delegated_by' => $actor->id,
            'delegated_at' => now(),
            'delegation_expires_at' => $expiresAt,
            'delegation_reason' => $reason ? trim($reason) : null,
        ]);

        $stepLabel = $step->role_label ?: (ApprovalStep::LEVEL_LABELS[$step->level_name] ?? $step->level_name);
        $original = $step->assignee?->name ?? 'aprovador designado';
        $expiryText = $expiresAt ? ' até ' . $expiresAt->format('d/m/Y') : '';
        $body = "Delegou a etapa \"{$stepLabel}\" ({$original}) para {$delegate->name}{$expiryText}";
        if ($reason) {
            $body .= " — {$reason}";
        }

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $actor->id,
            'body' => $body,
            'type' => 'status_change',
        ]);

        if ($this->currentStep($payable)?->id === $step->id) {
            $this->notifyApprover($step->fresh(), $payable, $actor);
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function revokeStepDelegation(ApprovalStep $step, Payable $payable, User $actor): array
    {
        if (! $this->canManageStepDelegation($actor, $step, $payable)) {
            return ['ok' => false, 'error' => 'Sem permissão para remover delegação.'];
        }

        if (! $step->delegated_to) {
            return ['ok' => false, 'error' => 'Esta etapa não tem delegação ativa.'];
        }

        $delegateName = $step->delegatee?->name ?? 'substituto';
        $step->update([
            'delegated_to' => null,
            'delegated_by' => null,
            'delegated_at' => null,
            'delegation_expires_at' => null,
            'delegation_reason' => null,
        ]);

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $actor->id,
            'body' => "Removeu a delegação temporária de {$delegateName} na etapa " . ($step->role_label ?: $step->level_name),
            'type' => 'status_change',
        ]);

        if ($this->currentStep($payable)?->id === $step->id && $step->assigned_to) {
            $this->notifyApprover($step->fresh(), $payable, $actor);
        }

        return ['ok' => true];
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    public function delegateCandidateUsers(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function hasConsecutiveDuplicateAssignees(Payable $payable): bool
    {
        $steps = ApprovalStep::where('payable_id', $payable->id)->orderBy('order')->get();

        for ($i = 0; $i < $steps->count() - 1; $i++) {
            $current = $steps[$i];
            $next = $steps[$i + 1];

            if ($current->assigned_to && $current->assigned_to === $next->assigned_to) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove etapas consecutivas com o mesmo aprovador (snapshot antigo gestor + head).
     *
     * @return array{ok: bool, deleted: int}
     */
    public function dedupeConsecutiveAssigneeSteps(Payable $payable): array
    {
        $deleted = 0;

        while (true) {
            $steps = ApprovalStep::where('payable_id', $payable->id)->orderBy('order')->get();
            $removed = false;

            for ($i = 0; $i < $steps->count() - 1; $i++) {
                $current = $steps[$i];
                $next = $steps[$i + 1];

                if (! $current->assigned_to || $current->assigned_to !== $next->assigned_to) {
                    continue;
                }

                $this->pickDuplicateStepToRemove($current, $next)->delete();
                $deleted++;
                $removed = true;
                break;
            }

            if (! $removed) {
                break;
            }
        }

        if ($deleted > 0) {
            $this->renumberApprovalSteps($payable);
        }

        return ['ok' => true, 'deleted' => $deleted];
    }

    private function pickDuplicateStepToRemove(ApprovalStep $a, ApprovalStep $b): ApprovalStep
    {
        if ($a->level_name === 'departamento' && $b->level_name === 'gerencia') {
            return $a;
        }

        if ($b->level_name === 'departamento' && $a->level_name === 'gerencia') {
            return $b;
        }

        return $a;
    }

    private function renumberApprovalSteps(Payable $payable): void
    {
        $steps = ApprovalStep::where('payable_id', $payable->id)->orderBy('order')->get();

        foreach ($steps as $idx => $step) {
            $order = $idx + 1;
            if ((int) $step->order !== $order) {
                $step->update(['order' => $order]);
            }
        }
    }
}
