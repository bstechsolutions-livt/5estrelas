<?php

namespace App\Services\OpenFinance;

use App\Models\BankAccount;
use App\Models\Branch;

/**
 * Resolve a Branch correspondente a uma BankAccount sem chute.
 *
 * Só pré-seleciona quando o par Senior (cod_emp + cod_fil) ou o único
 * Branch ativo da empresa for inequívoco.
 */
final class BranchResolver
{
    /**
     * @return array{
     *     suggested_branch_id: int|null,
     *     match: 'exact'|'unique_company'|'none'|'ambiguous',
     *     candidates: list<array<string, mixed>>
     * }
     */
    public function resolve(BankAccount $account): array
    {
        $active = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $candidates = $active;
        $match = 'none';
        $suggested = null;

        $codEmp = $account->senior_codemp ? (int) $account->senior_codemp : null;
        $codFil = $account->senior_codfil ? (int) $account->senior_codfil : null;

        if ($codEmp !== null) {
            $byEmp = $active->where('cod_emp', $codEmp)->values();

            if ($codFil !== null && $codFil > 0) {
                $exact = $byEmp->where('cod_fil', $codFil)->values();
                if ($exact->count() === 1) {
                    $suggested = $exact->first();
                    $candidates = $exact;
                    $match = 'exact';
                } elseif ($exact->count() > 1) {
                    $candidates = $exact;
                    $match = 'ambiguous';
                } elseif ($byEmp->count() === 1) {
                    $suggested = $byEmp->first();
                    $candidates = $byEmp;
                    $match = 'unique_company';
                } elseif ($byEmp->count() > 1) {
                    $candidates = $byEmp;
                    $match = 'ambiguous';
                }
            } elseif ($byEmp->count() === 1) {
                $suggested = $byEmp->first();
                $candidates = $byEmp;
                $match = 'unique_company';
            } elseif ($byEmp->count() > 1) {
                $candidates = $byEmp;
                $match = 'ambiguous';
            }
        }

        return [
            'suggested_branch_id' => $suggested?->id,
            'match' => $match,
            'candidates' => $candidates->map(fn (Branch $branch) => $this->toOption($branch))->values()->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, display_name: string, cnpj: ?string, cnpj_masked: string, cod_emp: ?int, cod_fil: ?int}
     */
    public function toOption(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'display_name' => $branch->resolveDisplayName(),
            'cnpj' => $branch->cnpj,
            'cnpj_masked' => DocumentDisplay::mask($branch->cnpj),
            'cod_emp' => $branch->cod_emp,
            'cod_fil' => $branch->cod_fil,
        ];
    }
}
