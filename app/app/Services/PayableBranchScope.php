<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Comercial\Filial as ComercialFilial;
use App\Models\Payable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PayableBranchScope
{
    public const NO_BRANCH_ACCESS_MESSAGE = 'Você não tem permissão para acessar nenhuma filial. Solicite ao administrador do sistema a liberação das filiais necessárias.';

    public function canBypass(User $user): bool
    {
        return $user->hasPermission('*')
            || $user->hasPermission('financeiro.contas_pagar.ver_todas_filiais');
    }

    /**
     * @return array{
     *     restricted: bool,
     *     no_branch_access: bool,
     *     branch_ids: int[],
     *     codfils: array<int|string>,
     *     codemps: int[],
     *     cod_pairs: array<int, array{0:int,1:int}>,
     *     locked_branches: array<int, array{id:int,name:string}>
     * }
     */
    public function resolve(User $user): array
    {
        if ($this->canBypass($user)) {
            return [
                'restricted' => false,
                'no_branch_access' => false,
                'branch_ids' => [],
                'codfils' => [],
                'codemps' => [],
                'cod_pairs' => [],
                'locked_branches' => [],
            ];
        }

        $branches = $user->branches()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'cod_emp', 'cod_fil']);

        if ($branches->isEmpty()) {
            return [
                'restricted' => true,
                'no_branch_access' => true,
                'branch_ids' => [],
                'codfils' => [],
                'codemps' => [],
                'cod_pairs' => [],
                'locked_branches' => [],
            ];
        }

        $branchIds = $branches->pluck('id')->map(fn ($id) => (int) $id)->all();
        $codfils = [];
        $codemps = [];
        $codPairs = [];

        foreach ($branches as $branch) {
            if ($branch->cod_emp !== null && $branch->cod_fil !== null) {
                $codPairs[] = [(int) $branch->cod_emp, (int) $branch->cod_fil];

                continue;
            }

            $code = trim((string) ($branch->code ?? ''));
            if ($code !== '' && is_numeric($code)) {
                $c = (int) $code;
                $codfils[] = $c;
                $codemps[] = $c;
            }
        }

        $codfils = array_values(array_unique($codfils));
        $codemps = array_values(array_unique($codemps));

        return [
            'restricted' => true,
            'no_branch_access' => false,
            'branch_ids' => $branchIds,
            'codfils' => $codfils,
            'codemps' => $codemps,
            'cod_pairs' => $codPairs,
            'locked_branches' => $branches->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->display_name,
            ])->values()->all(),
        ];
    }

    public function applyFilter(Builder $query, User $user): void
    {
        $scope = $this->resolve($user);
        if (!$scope['restricted']) {
            return;
        }

        $query->where(function (Builder $q) use ($scope) {
            $applied = false;

            if ($scope['branch_ids'] !== []) {
                $q->whereIn('branch_id', $scope['branch_ids']);
                $applied = true;
            }

            foreach ($scope['cod_pairs'] as [$codemp, $codfil]) {
                $clause = fn (Builder $sub) => $sub->where('codemp', $codemp)->where('codfil', $codfil);
                $applied ? $q->orWhere($clause) : $q->where($clause);
                $applied = true;
            }

            if ($scope['codfils'] !== []) {
                $applied
                    ? $q->orWhereIn('codfil', $scope['codfils'])
                    : $q->whereIn('codfil', $scope['codfils']);
                $applied = true;
            }

            if ($scope['codemps'] !== []) {
                $applied
                    ? $q->orWhereIn('codemp', $scope['codemps'])
                    : $q->whereIn('codemp', $scope['codemps']);
            }

            if (!$applied) {
                $q->whereRaw('0 = 1');
            }
        });
    }

    public function canAccessPayable(User $user, Payable $payable): bool
    {
        $scope = $this->resolve($user);
        if (!$scope['restricted']) {
            return true;
        }

        if ($scope['no_branch_access']) {
            return false;
        }

        if ($payable->branch_id && in_array((int) $payable->branch_id, $scope['branch_ids'], true)) {
            return true;
        }

        foreach ($scope['cod_pairs'] as [$codemp, $codfil]) {
            if ((int) $payable->codemp === $codemp && (int) $payable->codfil === $codfil) {
                return true;
            }
        }

        if ($payable->codfil && in_array((int) $payable->codfil, $scope['codfils'], true)) {
            return true;
        }

        if ($payable->codemp && in_array((int) $payable->codemp, $scope['codemps'], true)) {
            return true;
        }

        return false;
    }

    /** @return array<int, array{label:string,value:int}> */
    public function empresaOptionsForUser(User $user): array
    {
        $options = ComercialFilial::selectOptions();
        $scope = $this->resolve($user);

        if (!$scope['restricted']) {
            return $options;
        }

        if ($scope['no_branch_access'] || $scope['codemps'] === []) {
            return [];
        }

        $allowed = array_flip($scope['codemps']);

        return array_values(array_filter(
            $options,
            fn (array $row) => isset($allowed[(int) $row['value']]),
        ));
    }
}
