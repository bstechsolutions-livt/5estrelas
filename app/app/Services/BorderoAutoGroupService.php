<?php

namespace App\Services;

use App\Models\Bordero;
use App\Models\BorderoAutoRule;
use App\Models\Payable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BorderoAutoGroupService
{
    public function __construct(
        private PayableBranchScope $branchScope,
        private PayableDepartmentClassifier $classifier,
    ) {}

    /** @return Builder<Payable> */
    public function eligibleQuery(?User $user, BorderoAutoRule $rule): Builder
    {
        $query = Payable::query()
            ->whereNull('bordero_id')
            ->whereIn('status', ['pendente', 'em_preparacao', 'reprovado']);

        if ($user) {
            $this->branchScope->applyFilter($query, $user);
        }

        if ($rule->eligibility_mode === BorderoAutoRule::ELIGIBILITY_DUE_WITHIN) {
            $days = max(1, (int) ($rule->eligibility_due_days ?? 30));
            $limit = Carbon::today()->addDays($days)->toDateString();
            $query->whereNotNull('due_date')->whereDate('due_date', '<=', $limit);
        }

        return $query;
    }

    /**
     * @return array{
     *   groups: list<array<string, mixed>>,
     *   summary: array<string, int|float>,
     *   skipped_singles: int,
     *   unclassified_count: int,
     *   rules_summary: list<string>
     * }
     */
    public function preview(?User $user, BorderoAutoRule $rule): array
    {
        $payables = $this->eligibleQuery($user, $rule)
            ->orderBy('codemp')
            ->orderBy('due_date')
            ->get();

        Payable::attachEmpresaNome($payables);

        $rawGroups = $this->bucketPayables($payables, $rule);
        $min = max(2, (int) $rule->min_titles_per_group);

        $groups = [];
        $skippedSingles = 0;
        $unclassified = 0;

        foreach ($rawGroups as $bucket) {
            if (count($bucket['payables']) < $min) {
                $skippedSingles += count($bucket['payables']);
                continue;
            }

            if ($bucket['segment_type'] === 'unclassified') {
                $unclassified += count($bucket['payables']);
            }

            $groups[] = $this->formatGroup($bucket, $rule);
        }

        usort($groups, fn ($a, $b) => $b['titles_count'] <=> $a['titles_count']);

        $eligibleTotal = $payables->count();
        $groupedTitles = array_sum(array_column($groups, 'titles_count'));

        return [
            'groups' => $groups,
            'summary' => [
                'eligible_titles' => $eligibleTotal,
                'suggested_groups' => count($groups),
                'titles_in_groups' => $groupedTitles,
                'titles_outside_groups' => $eligibleTotal - $groupedTitles,
                'total_amount_in_groups' => round(array_sum(array_column($groups, 'total_amount')), 2),
            ],
            'skipped_singles' => $skippedSingles,
            'unclassified_count' => $unclassified,
            'rules_summary' => $rule->rulesSummary(),
        ];
    }

    /** Aplica a regra nos títulos abertos (todos os grupos da simulação). */
    public function applyRule(?User $user, BorderoAutoRule $rule): array
    {
        $preview = $this->preview($user, $rule);
        $result = $this->createBorderosFromGroups($preview['groups'], $user, $rule);

        $rule->update([
            'last_applied_at' => now(),
            'last_applied_count' => $result['created'],
        ]);

        return $result;
    }

    /** Cron: executa todas as regras ativas em ordem. */
    public function runActiveRulesForCron(): array
    {
        $rules = BorderoAutoRule::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $totalCreated = 0;
        $ruleResults = [];

        foreach ($rules as $rule) {
            $preview = $this->preview(null, $rule);
            $result = $this->createBorderosFromGroups($preview['groups'], null, $rule);

            $rule->update([
                'last_cron_at' => now(),
                'last_cron_count' => $result['created'],
            ]);

            $totalCreated += $result['created'];
            $ruleResults[] = [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'created' => $result['created'],
            ];
        }

        return [
            'created' => $totalCreated,
            'rules' => $ruleResults,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return array{created: int, bordero_ids: list<int>}
     */
    private function createBorderosFromGroups(array $groups, ?User $user, BorderoAutoRule $rule): array
    {
        if ($groups === []) {
            return ['created' => 0, 'bordero_ids' => []];
        }

        $createdIds = [];

        DB::transaction(function () use ($groups, $user, $rule, &$createdIds) {
            foreach ($groups as $group) {
                $payableIds = $group['payable_ids'];
                $payables = Payable::whereIn('id', $payableIds)
                    ->whereNull('bordero_id')
                    ->whereIn('status', ['pendente', 'em_preparacao', 'reprovado'])
                    ->get();

                if ($payables->count() < max(2, (int) $rule->min_titles_per_group)) {
                    continue;
                }

                $bordero = Bordero::create([
                    'number' => Bordero::generateNumber(),
                    'description' => $group['bordero_description'],
                    'status' => 'rascunho',
                    'created_by' => $user?->id,
                    'auto_rule_id' => $rule->id ?: null,
                ]);

                $update = ['bordero_id' => $bordero->id];
                if ($user) {
                    $update['prepared_by'] = $user->id;
                }

                Payable::whereIn('id', $payables->pluck('id'))->update($update);

                $bordero->recalculate();
                $createdIds[] = $bordero->id;

                $source = $user ? 'regra' : 'cron';
                AuditLogger::log(
                    event: 'bordero.created',
                    module: 'financeiro.contas_pagar',
                    description: "Borderô automático ({$source}: {$rule->name}) {$bordero->number}: {$bordero->items_count} título(s) — {$group['label']}",
                    auditable: $bordero,
                );
            }
        });

        return [
            'created' => count($createdIds),
            'bordero_ids' => $createdIds,
        ];
    }

    /**
     * @param  Collection<int, Payable>  $payables
     * @return list<array<string, mixed>>
     */
    private function bucketPayables(Collection $payables, BorderoAutoRule $rule): array
    {
        $baseBuckets = [];

        foreach ($payables as $payable) {
            $segment = $this->resolveSegment($payable);
            $emp = (int) ($payable->codemp ?? 0);
            $baseKey = "{$emp}|{$segment['type']}:{$segment['id']}";

            if (! isset($baseBuckets[$baseKey])) {
                $baseBuckets[$baseKey] = [
                    'base_key' => $baseKey,
                    'codemp' => $emp,
                    'empresa_nome' => $payable->getAttribute('empresa_nome') ?? ($emp ? "Empresa {$emp}" : 'Sem empresa'),
                    'segment_type' => $segment['type'],
                    'segment_id' => $segment['id'],
                    'segment_label' => $segment['label'],
                    'department_id' => $segment['department_id'] ?? null,
                    'codccu' => $segment['codccu'] ?? null,
                    'payables' => [],
                ];
            }

            $baseBuckets[$baseKey]['payables'][] = $payable;
        }

        $final = [];

        foreach ($baseBuckets as $base) {
            $chunks = match ($rule->due_grouping) {
                BorderoAutoRule::DUE_SAME_DAY => $this->splitBySameDay($base),
                BorderoAutoRule::DUE_MAX_SPAN => $this->splitByMaxSpan($base, max(1, (int) $rule->max_due_span_days)),
                default => [$base],
            };

            foreach ($chunks as $i => $chunk) {
                $chunk['key'] = $this->finalizeKey($chunk, $rule, $i);
                $chunk['due_label'] = $this->dueLabelForChunk($chunk, $rule);
                $final[] = $chunk;
            }
        }

        return $final;
    }

    /** @param array<string, mixed> $base */
    private function splitBySameDay(array $base): array
    {
        $byDate = [];

        foreach ($base['payables'] as $payable) {
            $dateKey = $payable->due_date?->toDateString() ?? 'sem-vencimento';
            $byDate[$dateKey][] = $payable;
        }

        $chunks = [];
        foreach ($byDate as $dateKey => $items) {
            $chunks[] = array_merge($base, [
                'payables' => $items,
                'due_date_key' => $dateKey,
            ]);
        }

        return $chunks;
    }

    /** @param array<string, mixed> $base */
    private function splitByMaxSpan(array $base, int $maxDays): array
    {
        $withDate = [];
        $withoutDate = [];

        foreach ($base['payables'] as $payable) {
            if ($payable->due_date) {
                $withDate[] = $payable;
            } else {
                $withoutDate[] = $payable;
            }
        }

        usort($withDate, fn (Payable $a, Payable $b) => $a->due_date <=> $b->due_date);

        $chunks = [];
        $current = [];
        $anchor = null;

        foreach ($withDate as $payable) {
            $date = Carbon::parse($payable->due_date)->startOfDay();

            if ($current === []) {
                $current = [$payable];
                $anchor = $date;
                continue;
            }

            if ($date->diffInDays($anchor) <= $maxDays) {
                $current[] = $payable;
            } else {
                $chunks[] = $this->chunkWithSpanMeta($base, $current);
                $current = [$payable];
                $anchor = $date;
            }
        }

        if ($current !== []) {
            $chunks[] = $this->chunkWithSpanMeta($base, $current);
        }

        if ($withoutDate !== []) {
            $chunks[] = array_merge($base, [
                'payables' => $withoutDate,
                'due_date_key' => 'sem-vencimento',
            ]);
        }

        return $chunks;
    }

    /** @param list<Payable> $payables */
    private function chunkWithSpanMeta(array $base, array $payables): array
    {
        $dates = array_map(fn (Payable $p) => $p->due_date->toDateString(), $payables);

        return array_merge($base, [
            'payables' => $payables,
            'due_date_key' => min($dates) . '_' . max($dates),
            'due_span_from' => min($dates),
            'due_span_to' => max($dates),
        ]);
    }

    /** @param array<string, mixed> $chunk */
    private function finalizeKey(array $chunk, BorderoAutoRule $rule, int $index): string
    {
        $key = $chunk['base_key'];

        if ($rule->due_grouping === BorderoAutoRule::DUE_SAME_DAY) {
            $key .= '|date:' . ($chunk['due_date_key'] ?? $index);
        }

        if ($rule->due_grouping === BorderoAutoRule::DUE_MAX_SPAN) {
            $key .= '|span:' . ($chunk['due_date_key'] ?? $index);
        }

        return $key;
    }

    /** @param array<string, mixed> $chunk */
    private function dueLabelForChunk(array $chunk, BorderoAutoRule $rule): ?string
    {
        if ($rule->due_grouping === BorderoAutoRule::DUE_SAME_DAY) {
            $key = $chunk['due_date_key'] ?? null;
            if ($key === 'sem-vencimento' || ! $key) {
                return 'Sem vencimento';
            }

            return 'Venc. ' . Carbon::parse($key)->format('d/m/Y');
        }

        if ($rule->due_grouping === BorderoAutoRule::DUE_MAX_SPAN) {
            if (($chunk['due_date_key'] ?? '') === 'sem-vencimento') {
                return 'Sem vencimento';
            }
            if (! empty($chunk['due_span_from']) && ! empty($chunk['due_span_to'])) {
                $from = Carbon::parse($chunk['due_span_from'])->format('d/m');
                $to = Carbon::parse($chunk['due_span_to'])->format('d/m');

                return "Venc. {$from}–{$to}";
            }
        }

        return null;
    }

    /** @param array<string, mixed> $bucket */
    private function formatGroup(array $bucket, BorderoAutoRule $rule): array
    {
        /** @var list<Payable> $payables */
        $payables = $bucket['payables'];
        $amount = array_sum(array_map(fn (Payable $p) => (float) $p->amount, $payables));
        $ids = array_map(fn (Payable $p) => $p->id, $payables);

        $labelParts = [$bucket['empresa_nome'], $bucket['segment_label']];
        if (! empty($bucket['due_label'])) {
            $labelParts[] = $bucket['due_label'];
        }

        $label = implode(' · ', array_filter($labelParts));

        return [
            'key' => $bucket['key'],
            'label' => $label,
            'codemp' => $bucket['codemp'],
            'empresa_nome' => $bucket['empresa_nome'],
            'segment_type' => $bucket['segment_type'],
            'segment_label' => $bucket['segment_label'],
            'department_id' => $bucket['department_id'],
            'codccu' => $bucket['codccu'],
            'due_label' => $bucket['due_label'] ?? null,
            'titles_count' => count($payables),
            'total_amount' => round($amount, 2),
            'payable_ids' => $ids,
            'bordero_description' => 'Auto (' . $rule->name . '): ' . $label,
            'sample_titles' => array_slice(array_map(fn (Payable $p) => [
                'id' => $p->id,
                'title_number' => $p->title_number,
                'supplier_name' => $p->supplier_name,
                'amount' => (float) $p->amount,
                'due_date' => $p->due_date?->toDateString(),
            ], $payables), 0, 3),
        ];
    }

    /** @return array{type: string, id: string, label: string, department_id?: int, codccu?: string} */
    private function resolveSegment(Payable $payable): array
    {
        $department = $this->classifier->departmentForPayable($payable);
        if ($department) {
            return [
                'type' => 'dept',
                'id' => (string) $department->id,
                'label' => $department->name,
                'department_id' => $department->id,
            ];
        }

        $codccu = trim((string) ($payable->codccu ?? ''));
        if ($codccu !== '') {
            return [
                'type' => 'ccu',
                'id' => $codccu,
                'label' => "CCU {$codccu}",
                'codccu' => $codccu,
            ];
        }

        return [
            'type' => 'unclassified',
            'id' => '0',
            'label' => 'Sem departamento/CCU',
        ];
    }
}
