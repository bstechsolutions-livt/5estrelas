<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Curinga (admin)
            ['key' => '*', 'label' => 'Acesso total (admin)', 'module' => 'sistema', 'description' => 'Concede todas as permissões do sistema.'],

            // Usuários
            ['key' => 'usuarios.listar', 'label' => 'Listar usuários', 'module' => 'usuarios'],
            ['key' => 'usuarios.criar', 'label' => 'Criar usuários', 'module' => 'usuarios'],
            ['key' => 'usuarios.editar', 'label' => 'Editar usuários', 'module' => 'usuarios'],
            ['key' => 'usuarios.excluir', 'label' => 'Excluir usuários', 'module' => 'usuarios'],
            ['key' => 'usuarios.gerenciar_permissoes', 'label' => 'Gerenciar permissões', 'module' => 'usuarios'],

            // Aparência
            ['key' => 'aparencia.visualizar', 'label' => 'Ver aparência', 'module' => 'aparencia'],
            ['key' => 'aparencia.editar', 'label' => 'Editar aparência', 'module' => 'aparencia'],

            // Auditoria
            ['key' => 'auditoria.visualizar', 'label' => 'Ver logs de auditoria', 'module' => 'auditoria'],

            // Notícias
            ['key' => 'noticias.gerenciar', 'label' => 'Gerenciar destaques e notícias', 'module' => 'noticias'],

            // Departamentos
            ['key' => 'departamentos.gerenciar', 'label' => 'Gerenciar departamentos', 'module' => 'departamentos'],

            // Filiais
            ['key' => 'filiais.gerenciar', 'label' => 'Gerenciar filiais', 'module' => 'filiais'],

            // Financeiro - Contas a Pagar
            ['key' => 'financeiro.contas_pagar.visualizar', 'label' => 'Ver contas a pagar', 'module' => 'financeiro'],
            ['key' => 'financeiro.contas_pagar.preparar', 'label' => 'Preparar contas a pagar', 'module' => 'financeiro'],
            ['key' => 'financeiro.contas_pagar.aprovar', 'label' => 'Aprovar contas a pagar', 'module' => 'financeiro'],

            // Backups
            ['key' => 'backups.gerenciar', 'label' => 'Gerenciar backups', 'module' => 'backups'],

            // Gestão de Contratos (portado da Biglar)
            ['key' => 'contratos.visualizar', 'label' => 'Ver gestão de contratos', 'module' => 'contratos'],
            ['key' => 'contratos.gerenciar', 'label' => 'Gerenciar contratos (criar/editar/excluir)', 'module' => 'contratos'],

            // Solicitações (portado da Biglar)
            ['key' => 'solicitacoes.visualizar', 'label' => 'Ver solicitações', 'module' => 'solicitacoes'],
            ['key' => 'solicitacoes.criar', 'label' => 'Abrir solicitações', 'module' => 'solicitacoes'],
            ['key' => 'solicitacoes.configurar', 'label' => 'Configurar solicitações (assuntos/fluxos)', 'module' => 'solicitacoes'],
            ['key' => 'solicitacoes.aprovar', 'label' => 'Aprovar solicitações', 'module' => 'solicitacoes'],
        ];

        foreach ($permissions as $row) {
            Permission::firstOrCreate(
                ['key' => $row['key']],
                array_merge(['description' => null], $row)
            );
        }
    }
}
