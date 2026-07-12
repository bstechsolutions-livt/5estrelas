<?php

namespace App\Support;

use App\Models\User;

class FinanceiroConfigCatalog
{
    /**
     * Páginas de configuração do módulo Financeiro (hub → subpáginas).
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'alcada',
                'label' => 'Alçada (Contas a Pagar)',
                'description' => 'Defina quem pode pagar, conciliar e assinar títulos.',
                'icon' => 'pi pi-sitemap',
                'href' => '/financeiro/contas-pagar/alcada',
                'permission' => 'financeiro.contas_pagar.alcada_gerenciar',
            ],
            [
                'key' => 'classificacao',
                'label' => 'Classificação por Departamento',
                'description' => 'Regras de classificação automática de títulos por departamento.',
                'icon' => 'pi pi-tags',
                'href' => '/financeiro/contas-pagar/classificacao-departamentos',
                'permission' => 'financeiro.contas_pagar.classificacao_gerenciar',
            ],
            [
                'key' => 'fluxos',
                'label' => 'Fluxos de Aprovação',
                'description' => 'Configure trilhas e etapas de aprovação por área.',
                'icon' => 'pi pi-sliders-h',
                'href' => '/financeiro/fluxos-aprovacao',
                'permission' => 'financeiro.workflows.configurar',
            ],
            [
                'key' => 'borderos_auto',
                'label' => 'Regras de Borderô Automático',
                'description' => 'Crie e gerencie regras para geração automática de borderôs.',
                'icon' => 'pi pi-bolt',
                'href' => '/financeiro/borderos/automatico',
                'permission' => 'financeiro.borderos.automatico_gerenciar',
            ],
            [
                'key' => 'plano_contas',
                'label' => 'Plano de Contas',
                'description' => 'Contas financeiras e centros de custo derivados de CP/CR (base para DRE e relatórios).',
                'icon' => 'pi pi-book',
                'href' => '/financeiro/plano-de-contas',
                'permission' => 'financeiro.plano_contas.visualizar',
            ],
        ];
    }

    /** @return list<string> */
    public static function permissionKeys(): array
    {
        return array_values(array_unique(array_column(self::all(), 'permission')));
    }

    public static function accessibleTo(User $user): array
    {
        return collect(self::all())
            ->filter(fn (array $item) => $user->hasPermission($item['permission']))
            ->values()
            ->all();
    }

    public static function userCanAccessHub(User $user): bool
    {
        return self::accessibleTo($user) !== [];
    }
}
