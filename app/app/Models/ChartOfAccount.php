<?php

namespace App\Models;

use App\Models\Comercial\Filial;
use App\Services\PayableDepartmentClassifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ChartOfAccount extends Model
{
    public const TYPE_CONTA_FINANCEIRA = 'conta_financeira';
    public const TYPE_CENTRO_CUSTO = 'centro_custo';

    public const TYPE_LABELS = [
        self::TYPE_CONTA_FINANCEIRA => 'Conta financeira',
        self::TYPE_CENTRO_CUSTO => 'Centro de custo',
    ];

    protected $fillable = [
        'code', 'description', 'account_type', 'codemp', 'source', 'senior_raw', 'synced_at',
    ];

    protected $casts = [
        'codemp' => 'integer',
        'senior_raw' => 'array',
        'synced_at' => 'datetime',
    ];

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->account_type] ?? $this->account_type;
    }

    /** Resolve apelido da empresa pelo codEmp (mesmo padrão de CP/CR). */
    public static function attachEmpresaNome(iterable $accounts): void
    {
        $items = collect($accounts);
        $codEmps = $items->map(fn (self $a) => $a->codemp)->filter()->unique()->values();

        $map = $codEmps->isEmpty()
            ? collect()
            : Filial::whereIn('cod_emp', $codEmps)
                ->get(['cod_emp', 'nome', 'fantasia', 'apelido'])
                ->groupBy('cod_emp')
                ->map(fn ($grupo) => $grupo->first()->apelido ?: $grupo->first()->fantasia ?: $grupo->first()->nome);

        foreach ($items as $account) {
            $account->setAttribute('empresa_nome', $account->codemp ? ($map[$account->codemp] ?? null) : null);
        }
    }

    /** Preenche descrições derivadas quando a coluna está vazia (fonte interim). */
    public static function attachDerivedDescriptions(iterable $accounts): void
    {
        $items = collect($accounts);
        if ($items->isEmpty()) {
            return;
        }

        $ccuMap = self::centroCustoDescriptionMap();
        $needsFin = $items->filter(
            fn (self $a) => $a->account_type === self::TYPE_CONTA_FINANCEIRA && blank($a->description),
        );
        $finMap = self::contaFinanceiraDescriptionMap($needsFin);

        foreach ($items as $account) {
            if (filled($account->description)) {
                continue;
            }

            $account->setAttribute('description', self::deriveDescription(
                $account->account_type,
                $account->code,
                $account->codemp,
                $ccuMap,
                $finMap,
            ));
        }
    }

    public static function deriveDescription(
        string $accountType,
        string $code,
        ?int $codemp,
        ?array $ccuMap = null,
        ?array $finMap = null,
    ): string {
        if ($accountType === self::TYPE_CENTRO_CUSTO) {
            $ccuMap ??= self::centroCustoDescriptionMap();

            return $ccuMap[$code] ?? 'Centro de custo '.$code;
        }

        $finMap ??= self::contaFinanceiraDescriptionMap(collect([
            (object) ['account_type' => self::TYPE_CONTA_FINANCEIRA, 'code' => $code, 'codemp' => $codemp],
        ]));

        return $finMap[self::finMapKey($codemp, $code)] ?? 'Conta financeira '.$code;
    }

    /** @return array<string, string> codCcu => nome do departamento */
    private static function centroCustoDescriptionMap(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $classifier = app(PayableDepartmentClassifier::class);
        $rules = $classifier->rules();
        $departments = Department::query()
            ->whereIn('slug', array_keys($rules))
            ->where('is_active', true)
            ->get()
            ->keyBy('slug');

        $map = [];
        foreach ($rules as $slug => $rule) {
            $name = $departments[$slug]->name ?? null;
            if (! $name) {
                continue;
            }
            foreach ($rule['codccu'] ?? [] as $codccu) {
                $map[(string) $codccu] = $name;
            }
        }

        return $cache = $map;
    }

    /** @param Collection<int, self|object> $accounts */
    private static function contaFinanceiraDescriptionMap(Collection $accounts): array
    {
        if ($accounts->isEmpty()) {
            return [];
        }

        $map = [];
        $grouped = $accounts->groupBy(fn ($a) => (int) ($a->codemp ?? 0));

        foreach ($grouped as $codemp => $group) {
            $codes = $group->map(fn ($a) => (int) $a->code)->filter(fn ($c) => $c > 0)->unique()->values();
            if ($codes->isEmpty()) {
                continue;
            }

            $obsCol = PayableRateio::seniorColumn('obsRat');
            $ctaCol = PayableRateio::seniorColumn('ctaFin');

            $query = PayableRateio::query()
                ->join('payables', 'payables.id', '=', 'payable_rateios.payable_id')
                ->whereIn("payable_rateios.{$ctaCol}", $codes)
                ->whereNotNull("payable_rateios.{$obsCol}")
                ->where("payable_rateios.{$obsCol}", '!=', '');

            if ($codemp > 0) {
                $query->where('payables.codemp', $codemp);
            }

            foreach ($query->get(["payable_rateios.{$ctaCol}", "payable_rateios.{$obsCol}"]) as $row) {
                $key = self::finMapKey($codemp > 0 ? $codemp : null, (string) $row->{$ctaCol});
                $obs = trim((string) $row->{$obsCol});
                if ($obs === '') {
                    continue;
                }
                $map[$key] ??= $obs;
            }
        }

        return $map;
    }

    private static function finMapKey(?int $codemp, string $code): string
    {
        return ($codemp ?? 0).'|'.$code;
    }
}
