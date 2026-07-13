<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Comercial\Filial as ComercialFilial;
use App\Models\Receivable;
use App\Models\User;
use App\Support\PayableEmpresaExclusion;
use Illuminate\Database\Eloquent\Builder;

class ReceivableBranchScope
{
    public const NO_BRANCH_ACCESS_MESSAGE = 'Você não tem permissão para acessar nenhuma filial. Solicite ao administrador do sistema a liberação das filiais necessárias.';

    public function canBypass(User $user): bool
    {
        return $user->hasPermission('*')
            || $user->hasPermission('financeiro.contas_receber.ver_todas_filiais');
    }

    /** @return array{restricted:bool,no_branch_access:bool,branch_ids:int[],codfils:array,codemps:int[],locked_branches:array} */
    public function resolve(User $user): array
    {
        if ($this->canBypass($user)) {
            return [
                'restricted' => false,
                'no_branch_access' => false,
                'branch_ids' => [],
                'codfils' => [],
                'codemps' => [],
                'locked_branches' => [],
            ];
        }

        $branches = $user->branches()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        if ($branches->isEmpty()) {
            return [
                'restricted' => true,
                'no_branch_access' => true,
                'branch_ids' => [],
                'codfils' => [],
                'codemps' => [],
                'locked_branches' => [],
            ];
        }

        $branchIds = $branches->pluck('id')->map(fn ($id) => (int) $id)->all();
        $codes = $branches->pluck('code')->filter()->map(fn ($c) => trim((string) $c))->unique()->values();
        $codfils = $codes->filter(fn ($c) => is_numeric($c))->map(fn ($c) => (int) $c)->values()->all();
        $codemps = $codfils;

        return [
            'restricted' => true,
            'no_branch_access' => false,
            'branch_ids' => $branchIds,
            'codfils' => $codfils,
            'codemps' => $codemps,
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

    public function canAccessReceivable(User $user, Receivable $receivable): bool
    {
        $scope = $this->resolve($user);
        if (!$scope['restricted']) {
            return true;
        }

        if ($scope['no_branch_access']) {
            return false;
        }

        if ($receivable->branch_id && in_array((int) $receivable->branch_id, $scope['branch_ids'], true)) {
            return true;
        }

        if ($receivable->codfil && in_array((int) $receivable->codfil, $scope['codfils'], true)) {
            return true;
        }

        if ($receivable->codemp && in_array((int) $receivable->codemp, $scope['codemps'], true)) {
            return true;
        }

        return false;
    }

    /** @return array<int, array{label:string,value:int}> */
    public function empresaOptionsForUser(User $user): array
    {
        $options = PayableEmpresaExclusion::filterOptions(ComercialFilial::selectOptions());
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
