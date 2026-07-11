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
        private BorderoAutoRuleFilterService $filters,
    ) {}

    /** @return Builder<Payable> */
    public function eligibleQuery(?User $user, BorderoAutoRule $rule): Builder
    {
        $query = $this->filters->baseOpenQuery($user);

        return $this->filters->applyRule($rule, $query);
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
            ->orderBy('due_date')
            ->get();

        Payable::attachEmpresaNome($payables);

        $rawGroups = $this->bucketPayables($payables, $rule);
        $min = max(2, (int) $rule->min_titles_per_group);

        $groups = [];
        $skippedSingles = 0;

        foreach ($rawGroups as $bucket) {
            if (count($bucket['payables']) < $min) {
                $skippedSingles += count($bucket['payables']);
                continue;
            }

            $groups[] = $this->formatGroup($bucket, $rule);
        }

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
            'unclassified_count' => 0,
            'rules_summary' => $rule->rulesSummary(),
        ];
    }

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
                $payables = Payable::whereIn('id', $group['payable_ids'])
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
                    description: "Borderô automático ({$source}: {$rule->name}) {$bordero->number}: {$bordero->items_count} título(s)",
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
        if ($payables->isEmpty()) {
            return [];
        }

        $base = [
            'base_key' => 'matched',
            'label' => $rule->name,
            'payables' => $payables->all(),
        ];

        $chunks = match ($rule->due_grouping) {
            BorderoAutoRule::DUE_SAME_DAY => $this->splitBySameDay($base),
            BorderoAutoRule::DUE_MAX_SPAN => $this->splitByMaxSpan($base, max(1, (int) $rule->max_due_span_days)),
            default => [$base],
        };

        $final = [];
        foreach ($chunks as $i => $chunk) {
            $chunk['key'] = $this->finalizeKey($chunk, $rule, $i);
            $chunk['due_label'] = $this->dueLabelForChunk($chunk, $rule);
            $final[] = $chunk;
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

        $label = $bucket['label'];
        if (! empty($bucket['due_label'])) {
            $label .= ' · ' . $bucket['due_label'];
        }

        return [
            'key' => $bucket['key'],
            'label' => $label,
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
            ], $payables), 0, 5),
        ];
    }
}
