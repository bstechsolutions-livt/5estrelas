<?php

namespace App\Support;

use App\Models\User;

class MenuCatalog
{
    /**
     * Estrutura completa do menu com suporte a grupos/submenus.
     * Items sem 'group' ficam no nível raiz.
     * Items com 'group' são agrupados automaticamente.
     */
    public static function all(): array
    {
        return [
            // Raiz (sem grupo)

            // Plano de Voo
            ['key' => 'dashboard', 'label' => 'Painel de Entrada', 'icon' => 'pi pi-home', 'href' => '/dashboard', 'permission' => null, 'group' => 'Plano de Voo'],

            // Conteúdo
            ['key' => 'noticias', 'label' => 'Notícias', 'icon' => 'pi pi-megaphone', 'href' => '/noticias', 'permission' => 'noticias.gerenciar', 'group' => 'Conteúdo'],

            // Financeiro
            ['key' => 'contas_pagar', 'label' => 'Contas a Pagar', 'icon' => 'pi pi-wallet', 'href' => '/financeiro/contas-pagar', 'permission' => 'financeiro.contas_pagar.visualizar', 'group' => 'Financeiro'],
            ['key' => 'borderos', 'label' => 'Borderôs', 'icon' => 'pi pi-list-check', 'href' => '/financeiro/borderos', 'permission' => 'financeiro.contas_pagar.visualizar', 'group' => 'Financeiro'],

            // Contratos (portado da Biglar)
            ['key' => 'contratos_dashboard', 'label' => 'Painel', 'icon' => 'pi pi-chart-pie', 'href' => '/pagina/gestao-contratos', 'permission' => 'contratos.visualizar', 'group' => 'Contratos'],
            ['key' => 'contratos_locacao', 'label' => 'Locação', 'icon' => 'pi pi-building', 'href' => '/pagina/gestao-contratos/locacao', 'permission' => 'contratos.visualizar', 'group' => 'Contratos'],
            ['key' => 'contratos_servicos', 'label' => 'Serviços', 'icon' => 'pi pi-briefcase', 'href' => '/pagina/gestao-contratos/servicos', 'permission' => 'contratos.visualizar', 'group' => 'Contratos'],
            ['key' => 'contratos_alvaras', 'label' => 'Alvarás', 'icon' => 'pi pi-id-card', 'href' => '/pagina/gestao-contratos/alvaras', 'permission' => 'contratos.visualizar', 'group' => 'Contratos'],
            ['key' => 'contratos_renovacao', 'label' => 'Renovações', 'icon' => 'pi pi-refresh', 'href' => '/pagina/gestao-contratos/renovacao', 'permission' => 'contratos.visualizar', 'group' => 'Contratos'],
            ['key' => 'contratos_equipamentos', 'label' => 'Equipamentos', 'icon' => 'pi pi-box', 'href' => '/pagina/gestao-contratos/equipamentos', 'permission' => 'contratos.visualizar', 'group' => 'Contratos'],

            // Cadastros
            ['key' => 'usuarios', 'label' => 'Usuários', 'icon' => 'pi pi-users', 'href' => '/usuarios', 'permission' => 'usuarios.listar', 'group' => 'Cadastros'],
            ['key' => 'departamentos', 'label' => 'Departamentos', 'icon' => 'pi pi-building', 'href' => '/departamentos', 'permission' => 'departamentos.gerenciar', 'group' => 'Cadastros'],
            ['key' => 'filiais', 'label' => 'Filiais', 'icon' => 'pi pi-map-marker', 'href' => '/filiais', 'permission' => 'filiais.gerenciar', 'group' => 'Cadastros'],

            // Configurações
            ['key' => 'aparencia', 'label' => 'Aparência', 'icon' => 'pi pi-palette', 'href' => '/settings/aparencia', 'permission' => 'aparencia.editar', 'group' => 'Configurações'],
            ['key' => 'backups', 'label' => 'Backups', 'icon' => 'pi pi-database', 'href' => '/backups', 'permission' => 'backups.gerenciar', 'group' => 'Configurações'],
            ['key' => 'auditoria', 'label' => 'Auditoria', 'icon' => 'pi pi-history', 'href' => '/auditoria', 'permission' => 'auditoria.visualizar', 'group' => 'Configurações'],
            ['key' => 'perfil', 'label' => 'Meu perfil', 'icon' => 'pi pi-user', 'href' => '/perfil', 'permission' => null, 'group' => 'Configurações'],
        ];
    }

    /**
     * Retorna items planos (sem agrupamento) acessíveis ao user.
     * Usado por: atalhos, busca global, drawer mobile, bottom nav.
     */
    public static function availableTo(User $user): array
    {
        return collect(self::all())
            ->filter(fn ($i) => !$i['permission'] || $user->hasPermission($i['permission']))
            ->values()
            ->all();
    }

    /**
     * Retorna menu agrupado (com submenus) acessível ao user.
     * Usado por: sidebar desktop e drawer.
     *
     * Formato retornado:
     * [
     *   { type: 'item', ...item },               // item raiz
     *   { type: 'group', label: 'Pessoas', items: [...] },  // grupo com filhos
     * ]
     */
    public static function groupedFor(User $user): array
    {
        $items = collect(self::all())
            ->filter(fn ($i) => !$i['permission'] || $user->hasPermission($i['permission']));

        $result = [];
        $groups = [];

        foreach ($items as $item) {
            if (empty($item['group'])) {
                $result[] = array_merge($item, ['type' => 'item']);
            } else {
                $groups[$item['group']][] = $item;
            }
        }

        foreach ($groups as $label => $groupItems) {
            $result[] = [
                'type' => 'group',
                'label' => $label,
                'items' => array_values($groupItems),
            ];
        }

        return $result;
    }

    public static function findByKey(string $key): ?array
    {
        return collect(self::all())->firstWhere('key', $key);
    }
}
