<?php

namespace App\Support;

use App\Models\User;

class MenuCatalog
{
    /**
     * Estrutura completa do menu com suporte a grupos/submenus.
     * Items sem 'group' ficam no nível raiz.
     * Items com 'group' são agrupados automaticamente.
     *
     * Ordem dos grupos: "Plano de Voo" fixo no topo; os demais em ordem alfabética.
     */
    public static function all(): array
    {
        return [
            // Plano de Voo (sempre primeiro)
            ['key' => 'dashboard', 'label' => 'Painel de Entrada', 'icon' => 'pi pi-home', 'href' => '/dashboard', 'permission' => null, 'group' => 'Plano de Voo'],
            ['key' => 'presidencia_desk', 'label' => 'Assinaturas', 'icon' => 'pi pi-verified', 'href' => '/financeiro/presidencia', 'permission' => 'financeiro.presidencia.painel', 'group' => 'Plano de Voo'],

            // Cadastros
            ['key' => 'departamentos', 'label' => 'Departamentos', 'icon' => 'pi pi-building', 'href' => '/departamentos', 'permission' => 'departamentos.gerenciar', 'group' => 'Cadastros'],
            ['key' => 'filiais', 'label' => 'Filiais', 'icon' => 'pi pi-map-marker', 'href' => '/filiais', 'permission' => 'filiais.gerenciar', 'group' => 'Cadastros'],
            ['key' => 'contratos_equip_tipos', 'label' => 'Tipos de Equip.', 'icon' => 'pi pi-tags', 'href' => '/pagina/gestao-contratos/equipamentos/tipos', 'permission' => 'contratos.visualizar', 'group' => 'Cadastros'],
            ['key' => 'usuarios', 'label' => 'Usuários', 'icon' => 'pi pi-users', 'href' => '/usuarios', 'permission' => 'usuarios.listar', 'group' => 'Cadastros'],

            // Comercial ("Gestão 360º")
            ['key' => 'comercial_dashboard', 'label' => 'Dashboard', 'icon' => 'pi pi-th-large', 'href' => '/comercial/dashboard', 'permission' => 'comercial.visualizar', 'group' => 'Comercial'],
            ['key' => 'comercial_cotacao', 'label' => 'Nova Cotação', 'icon' => 'pi pi-calculator', 'href' => '/comercial/cotacao', 'permission' => 'comercial.cotar', 'group' => 'Comercial'],
            ['key' => 'comercial_propostas', 'label' => 'Propostas', 'icon' => 'pi pi-file-edit', 'href' => '/comercial/propostas', 'permission' => 'comercial.cotar', 'group' => 'Comercial'],
            ['key' => 'comercial_clientes', 'label' => 'Clientes', 'icon' => 'pi pi-briefcase', 'href' => '/comercial/clientes', 'permission' => 'comercial.cotar', 'group' => 'Comercial'],
            ['key' => 'comercial_reajustes', 'label' => 'Reajustes', 'icon' => 'pi pi-percentage', 'href' => '/comercial/reajustes', 'permission' => 'comercial.cotar', 'group' => 'Comercial'],
            ['key' => 'comercial_contratos', 'label' => 'Contratos', 'icon' => 'pi pi-file', 'href' => '/comercial/contratos', 'permission' => 'comercial.cotar', 'group' => 'Comercial'],
            ['key' => 'comercial_faturamento', 'label' => 'Faturamento', 'icon' => 'pi pi-chart-bar', 'href' => '/comercial/faturamento', 'permission' => 'comercial.cotar', 'group' => 'Comercial'],
            ['key' => 'comercial_config', 'label' => 'Valores', 'icon' => 'pi pi-dollar', 'href' => '/comercial/configuracoes', 'permission' => 'comercial.configurar', 'group' => 'Comercial'],
            ['key' => 'comercial_saude', 'label' => 'Saúde Contratual', 'icon' => 'pi pi-heart', 'href' => '/comercial/saude', 'permission' => 'comercial.cotar', 'group' => 'Comercial'],

            // Configurações
            ['key' => 'aparencia', 'label' => 'Aparência', 'icon' => 'pi pi-palette', 'href' => '/settings/aparencia', 'permission' => 'aparencia.editar', 'group' => 'Configurações'],
            ['key' => 'auditoria', 'label' => 'Auditoria', 'icon' => 'pi pi-history', 'href' => '/auditoria', 'permission' => 'auditoria.visualizar', 'group' => 'Configurações'],
            ['key' => 'backups', 'label' => 'Backups', 'icon' => 'pi pi-database', 'href' => '/backups', 'permission' => 'backups.gerenciar', 'group' => 'Configurações'],
            ['key' => 'perfil', 'label' => 'Meu perfil', 'icon' => 'pi pi-user', 'href' => '/perfil', 'permission' => null, 'group' => 'Configurações'],

            // Conteúdo
            ['key' => 'noticias', 'label' => 'Notícias', 'icon' => 'pi pi-megaphone', 'href' => '/noticias', 'permission' => 'noticias.gerenciar', 'group' => 'Conteúdo'],

            // Contratos (portado da Biglar)
            ['key' => 'contratos_dashboard', 'label' => 'Painel', 'icon' => 'pi pi-chart-pie', 'href' => '/pagina/gestao-contratos', 'permission' => 'contratos.visualizar', 'group' => 'Contratos'],
            ['key' => 'contratos_locacao', 'label' => 'Locação', 'icon' => 'pi pi-building', 'href' => '/pagina/gestao-contratos/locacao', 'permission' => 'contratos.visualizar', 'group' => 'Contratos'],
            ['key' => 'contratos_servicos', 'label' => 'Serviços Contratados', 'icon' => 'pi pi-briefcase', 'href' => '/pagina/gestao-contratos/servicos', 'permission' => 'contratos.visualizar', 'group' => 'Contratos'],
            ['key' => 'contratos_servicos_prestados', 'label' => 'Serviços Prestados', 'icon' => 'pi pi-send', 'href' => '/pagina/gestao-contratos/servicos-prestados', 'permission' => 'contratos.visualizar', 'group' => 'Contratos'],
            ['key' => 'contratos_alvaras', 'label' => 'Alvarás', 'icon' => 'pi pi-id-card', 'href' => '/pagina/gestao-contratos/alvaras', 'permission' => 'contratos.visualizar', 'group' => 'Contratos'],
            ['key' => 'contratos_equipamentos', 'label' => 'Equipamentos', 'icon' => 'pi pi-box', 'href' => '/pagina/gestao-contratos/equipamentos', 'permission' => 'contratos.visualizar', 'group' => 'Contratos'],

            // Financeiro
            ['key' => 'financeiro_dashboard', 'label' => 'Dashboard', 'icon' => 'pi pi-chart-pie', 'href' => '/financeiro/dashboard', 'permission' => 'financeiro.contas_pagar.visualizar', 'group' => 'Financeiro'],
            ['key' => 'financeiro_pendencias', 'label' => 'Pendências', 'icon' => 'pi pi-clock', 'href' => '/financeiro/pendencias', 'permission' => 'financeiro.contas_pagar.visualizar', 'group' => 'Financeiro'],
            ['key' => 'contas_pagar', 'label' => 'Contas a Pagar', 'icon' => 'pi pi-wallet', 'href' => '/financeiro/contas-pagar', 'permission' => 'financeiro.contas_pagar.visualizar', 'group' => 'Financeiro'],
            ['key' => 'contas_receber', 'label' => 'Contas a Receber', 'icon' => 'pi pi-money-bill', 'href' => '/financeiro/contas-receber', 'permission' => 'financeiro.contas_receber.visualizar', 'group' => 'Financeiro'],
            ['key' => 'borderos', 'label' => 'Borderôs', 'icon' => 'pi pi-list-check', 'href' => '/financeiro/borderos', 'permission' => 'financeiro.borderos.visualizar', 'group' => 'Financeiro'],
            ['key' => 'contas_pagar_conciliacao', 'label' => 'Conciliação Bancária', 'icon' => 'pi pi-file-import', 'href' => '/financeiro/contas-pagar/conciliacao', 'permission' => 'financeiro.conciliacao.visualizar', 'group' => 'Financeiro'],
            ['key' => 'financeiro_sync_senior', 'label' => 'Sync Senior', 'icon' => 'pi pi-sync', 'href' => '/financeiro/sync-senior', 'permission' => 'financeiro.workflows.configurar', 'group' => 'Financeiro'],
            ['key' => 'financeiro_configuracao', 'label' => 'Configuração', 'icon' => 'pi pi-cog', 'href' => '/financeiro/configuracao', 'any_permissions' => FinanceiroConfigCatalog::permissionKeys(), 'group' => 'Financeiro'],
            // Tickets (portado da Biglar)
            ['key' => 'sol_nova', 'label' => 'Novo Ticket', 'icon' => 'pi pi-plus-circle', 'href' => '/solicitacoes/nova', 'permission' => 'solicitacoes.visualizar', 'group' => 'Tickets'],
            ['key' => 'sol_lista', 'label' => 'Acompanhar', 'icon' => 'pi pi-list', 'href' => '/solicitacoes/lista', 'permission' => 'solicitacoes.visualizar', 'group' => 'Tickets'],
            ['key' => 'sol_minhas', 'label' => 'Meus Tickets', 'icon' => 'pi pi-user', 'href' => '/solicitacoes/minhas', 'permission' => 'solicitacoes.visualizar', 'group' => 'Tickets'],
            ['key' => 'sol_dashboard', 'label' => 'Painel', 'icon' => 'pi pi-chart-pie', 'href' => '/solicitacoes/dashboard', 'permission' => 'solicitacoes.visualizar', 'group' => 'Tickets'],
            ['key' => 'sol_agenda', 'label' => 'Agenda', 'icon' => 'pi pi-calendar', 'href' => '/solicitacoes/agendamento', 'permission' => 'solicitacoes.visualizar', 'group' => 'Tickets'],
            ['key' => 'sol_relatorios', 'label' => 'Relatórios', 'icon' => 'pi pi-chart-bar', 'href' => '/solicitacoes/relatorios', 'permission' => 'solicitacoes.visualizar', 'group' => 'Tickets'],
            ['key' => 'sol_config', 'label' => 'Configurações', 'icon' => 'pi pi-cog', 'href' => '/solicitacoes/configuracoes', 'permission' => 'solicitacoes.configurar', 'group' => 'Tickets'],
        ];
    }

    /**
     * Retorna items planos (sem agrupamento) acessíveis ao user.
     * Usado por: atalhos, busca global, drawer mobile, bottom nav.
     */
    public static function availableTo(User $user): array
    {
        return collect(self::all())
            ->filter(fn ($i) => self::isAccessible($i, $user))
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
            ->filter(fn ($i) => self::isAccessible($i, $user));

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

    private static function isAccessible(array $item, User $user): bool
    {
        if (! empty($item['any_permissions'])) {
            return collect($item['any_permissions'])->contains(fn (string $p) => $user->hasPermission($p));
        }

        return ! $item['permission'] || $user->hasPermission($item['permission']);
    }
}
