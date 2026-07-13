<?php

namespace App\Services;

use App\Models\ApprovalStep;
use App\Models\Payable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GestorWorkflowMapper
{
    /** @var callable(string): ?int */
    private $resolveGestorUserId;

    public function __construct(
        private readonly ApprovalWorkflowService $workflow,
    ) {}

    /**
     * @param  callable(string): ?int  $resolver
     */
    public function setUserResolver(callable $resolver): void
    {
        $this->resolveGestorUserId = $resolver;
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    public function resolveGestorPhase(string $gestorStatus, array $history): ?string
    {
        $configured = config('gestor_migration.gestor_workflow_phase', []);
        if (isset($configured[$gestorStatus])) {
            return $configured[$gestorStatus];
        }

        $statusMap = config('gestor_migration.status_map', []);
        if (($statusMap[$gestorStatus] ?? null) !== 'aguardando_aprovacao') {
            return null;
        }

        return $this->inferPhaseFromHistory($history);
    }

    /**
     * @param  array<string, mixed>  $match
     * @return array<string, mixed>
     */
    public function buildWorkflowUpdate(array $match): array
    {
        $statusMap = config('gestor_migration.status_map', []);
        $gestorStatus = $match['status'];
        $newStatus = $statusMap[$gestorStatus] ?? 'pendente';

        $update = ['status' => $newStatus];
        $history = $match['history'] ?? [];

        $rejectionReason = $this->resolveRejectionReason($gestorStatus, $match, $history);
        if ($rejectionReason !== null) {
            $update['rejection_reason'] = $rejectionReason;
        } elseif (in_array($newStatus, ['aguardando_aprovacao', 'aprovado', 'pago', 'aguardando_conciliacao', 'conciliado'], true)) {
            $update['rejection_reason'] = null;
        }

        $actors = $this->extractHistoryActors($history);
        if ($actors['prepared_by']) {
            $update['prepared_by'] = $actors['prepared_by'];
        }
        if ($actors['approved_by']) {
            $update['approved_by'] = $actors['approved_by'];
        }
        if ($actors['sent_for_approval_at']) {
            $update['sent_for_approval_at'] = $actors['sent_for_approval_at'];
        }
        if ($actors['approved_at']) {
            $update['approved_at'] = $actors['approved_at'];
        }

        if ($gestorStatus === 'awaiting-inclusion') {
            $paidAt = $this->extractPaidAt($history);
            $update['paid_at'] = $paidAt ?? now()->toDateString();
            $update['conciliated_at'] = null;
            $update['conciliated_by'] = null;
        }

        if ($gestorStatus === 'included') {
            $conciliatedAt = $this->extractConciliatedAt($history);
            $update['conciliated_at'] = $conciliatedAt ?? now()->toDateString();
            if ($paidAt = $this->extractPaidAt($history)) {
                $update['paid_at'] = $paidAt;
            }
        }

        if ($gestorStatus === 'awaiting-receipt') {
            $update['approved_at'] = $update['approved_at'] ?? now();
        }

        return $update;
    }

    /**
     * @param  list<array<string, mixed>>  $history
     * @return array{ok: bool, phase?: string, target_order?: int, error?: string, cleared?: bool}
     */
    public function applyWorkflowPosition(Payable $payable, string $gestorStatus, array $history): array
    {
        $statusMap = config('gestor_migration.status_map', []);
        $newStatus = $statusMap[$gestorStatus] ?? 'pendente';

        if ($newStatus === 'pendente' && $this->shouldClearSteps($gestorStatus)) {
            $this->clearApprovalSteps($payable);

            return ['ok' => true, 'cleared' => true];
        }

        if (in_array($newStatus, ['aprovado', 'pago', 'aguardando_conciliacao', 'conciliado'], true)) {
            $this->clearApprovalSteps($payable);

            return ['ok' => true, 'cleared' => true];
        }

        if ($newStatus !== 'aguardando_aprovacao') {
            return ['ok' => true, 'skipped' => true];
        }

        $phase = $this->resolveGestorPhase($gestorStatus, $history);
        if (! $phase) {
            return ['ok' => false, 'error' => 'fase_nao_resolvida'];
        }

        $sender = $payable->prepared_by ? User::find($payable->prepared_by) : null;
        if (! $sender) {
            return ['ok' => false, 'error' => 'sem_preparador'];
        }

        $approvedAt = $this->resolveApprovedAtForPhase($phase, $history);

        return $this->workflow->createStepsAtGestorPhase($payable, $sender, $phase, $approvedAt);
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function inferPhaseFromHistory(array $history): ?string
    {
        $lastRelevant = $this->lastHistoryEventOfTypes($history, [
            'sent-to-approval',
            'sent-to-analysis',
            'sent-to-department-approval',
            'sent-to-reanalysis',
        ]);

        if (! $lastRelevant) {
            return null;
        }

        return match ($lastRelevant['type']) {
            'sent-to-approval' => 'final',
            'sent-to-analysis' => 'analysis',
            'sent-to-department-approval' => $this->hasLaterEvent($history, (int) $lastRelevant['at'], ['sent-to-analysis'])
                ? 'analysis'
                : 'department',
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $history
     * @param  list<string>  $types
     * @return ?array<string, mixed>
     */
    private function lastHistoryEventOfTypes(array $history, array $types): ?array
    {
        $events = collect($history)
            ->filter(fn (array $e) => in_array($e['type'] ?? '', $types, true))
            ->sortBy('at')
            ->values();

        return $events->last() ?: null;
    }

    /**
     * @param  list<array<string, mixed>>  $history
     * @param  list<string>  $types
     */
    private function hasLaterEvent(array $history, int $afterAt, array $types): bool
    {
        foreach ($history as $event) {
            $at = (int) ($event['at'] ?? 0);
            if ($at > $afterAt && in_array($event['type'] ?? '', $types, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $match
     * @param  list<array<string, mixed>>  $history
     */
    private function resolveRejectionReason(string $gestorStatus, array $match, array $history): ?string
    {
        return match ($gestorStatus) {
            'awaiting-rectification' => filled($match['rectificationReason'] ?? null)
                ? $match['rectificationReason']
                : $this->extractRejectionFromHistory($history),
            'awaiting-reanalysis' => filled($match['rectificationReason'] ?? null)
                ? $match['rectificationReason']
                : $this->extractRejectionFromHistory($history, 'sent-to-reanalysis'),
            'awaiting-release' => filled($match['retentionReason'] ?? null)
                ? $match['retentionReason']
                : $this->extractRejectionFromHistory($history),
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function extractRejectionFromHistory(array $history, ?string $afterType = null): ?string
    {
        $afterAt = 0;
        if ($afterType) {
            $anchor = $this->lastHistoryEventOfTypes($history, [$afterType]);
            $afterAt = (int) ($anchor['at'] ?? 0);
        }

        foreach (collect($history)->sortBy('at') as $event) {
            if ((int) ($event['at'] ?? 0) < $afterAt) {
                continue;
            }

            $type = $event['type'] ?? '';
            if (in_array($type, ['rejected', 'rectified', 'retained', 'sent-to-rectification'], true)) {
                return $event['reason'] ?? $event['comment'] ?? $event['message'] ?? 'Reprovado no Gestor';
            }
        }

        return 'Reprovado no Gestor';
    }

    /**
     * @param  list<array<string, mixed>>  $history
     * @return array{prepared_by: ?int, approved_by: ?int, sent_for_approval_at: ?Carbon, approved_at: ?Carbon}
     */
    private function extractHistoryActors(array $history): array
    {
        $preparedBy = null;
        $approvedBy = null;
        $sentAt = null;
        $approvedAt = null;

        foreach ($history as $event) {
            $type = $event['type'] ?? '';
            $userId = $this->resolveUserId($event['by'] ?? null);
            $at = isset($event['at']) ? Carbon::createFromTimestampMs((int) $event['at']) : null;

            if (in_array($type, ['sent-to-analysis', 'sent-to-department-approval', 'sent-to-reanalysis'], true) && $userId) {
                $preparedBy = $userId;
            }
            if ($type === 'sent-to-approval' && $at) {
                $sentAt = $at;
            }
            if ($type === 'approved' && $userId) {
                $approvedBy = $userId;
                $approvedAt = $at ?? $approvedAt;
            }
        }

        return [
            'prepared_by' => $preparedBy,
            'approved_by' => $approvedBy,
            'sent_for_approval_at' => $sentAt,
            'approved_at' => $approvedAt,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function extractPaidAt(array $history): ?string
    {
        $paidTypes = ['paid', 'payment-registered', 'marked-as-paid', 'sent-to-inclusion'];

        foreach (collect($history)->sortByDesc('at') as $event) {
            if (in_array($event['type'] ?? '', $paidTypes, true) && isset($event['at'])) {
                return Carbon::createFromTimestampMs((int) $event['at'])->toDateString();
            }
        }

        $approved = $this->lastHistoryEventOfTypes($history, ['approved']);

        return isset($approved['at'])
            ? Carbon::createFromTimestampMs((int) $approved['at'])->toDateString()
            : null;
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function extractConciliatedAt(array $history): ?string
    {
        $types = ['included', 'conciliated', 'reconciled'];

        foreach (collect($history)->sortByDesc('at') as $event) {
            if (in_array($event['type'] ?? '', $types, true) && isset($event['at'])) {
                return Carbon::createFromTimestampMs((int) $event['at'])->toDateString();
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function resolveApprovedAtForPhase(string $phase, array $history): ?Carbon
    {
        $type = match ($phase) {
            'analysis' => 'sent-to-analysis',
            'final' => 'sent-to-approval',
            default => null,
        };

        if (! $type) {
            return null;
        }

        $event = $this->lastHistoryEventOfTypes($history, [$type]);

        return isset($event['at'])
            ? Carbon::createFromTimestampMs((int) $event['at'])
            : null;
    }

    private function shouldClearSteps(string $gestorStatus): bool
    {
        return in_array($gestorStatus, [
            'awaiting-rectification',
            'awaiting-reanalysis',
            'awaiting-release',
            'draft',
        ], true);
    }

    private function clearApprovalSteps(Payable $payable): void
    {
        DB::transaction(fn () => ApprovalStep::where('payable_id', $payable->id)->delete());
    }

    private function resolveUserId(?string $gestorAuthorId): ?int
    {
        if (! $gestorAuthorId || ! $this->resolveGestorUserId) {
            return null;
        }

        return ($this->resolveGestorUserId)($gestorAuthorId);
    }
}
