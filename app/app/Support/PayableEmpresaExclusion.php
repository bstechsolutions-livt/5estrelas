<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Empresas (codEmp Senior) ocultas do módulo Contas a Pagar.
 *
 * Permanecem no cadastro comercial (sync Senior) mas não aparecem em filtros
 * nem listagens CP — ver config/payables.php excluded_cod_emp.
 */
class PayableEmpresaExclusion
{
    /** @return int[] */
    public static function excludedCodEmps(): array
    {
        return array_values(array_map(
            'intval',
            config('payables.excluded_cod_emp', []),
        ));
    }

    public static function isExcluded(?int $codemp): bool
    {
        return $codemp !== null && in_array($codemp, self::excludedCodEmps(), true);
    }

    /**
     * Remove empresas excluídas do CP antes de varreduras/cron Senior.
     *
     * @param  int[]  $codEmps
     * @return int[]
     */
    public static function filterCodEmps(array $codEmps): array
    {
        $excluded = array_flip(self::excludedCodEmps());

        return array_values(array_filter(
            $codEmps,
            fn (int $cod) => ! isset($excluded[$cod]),
        ));
    }

    public static function applyToQuery(Builder $query, string $column = 'codemp'): void
    {
        $excluded = self::excludedCodEmps();
        if ($excluded !== []) {
            $query->where(function (Builder $q) use ($column, $excluded) {
                $q->whereNull($column)
                    ->orWhereNotIn($column, $excluded);
            });
        }
    }

    /**
     * @param  array<int, array{label:string,value:int}>  $options
     * @return array<int, array{label:string,value:int}>
     */
    public static function filterOptions(array $options): array
    {
        $excluded = array_flip(self::excludedCodEmps());

        return array_values(array_filter(
            $options,
            fn (array $row) => !isset($excluded[(int) $row['value']]),
        ));
    }
}
