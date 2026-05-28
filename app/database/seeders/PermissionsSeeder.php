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

            // Backups
            ['key' => 'backups.gerenciar', 'label' => 'Gerenciar backups', 'module' => 'backups'],
        ];

        foreach ($permissions as $row) {
            Permission::firstOrCreate(
                ['key' => $row['key']],
                array_merge(['description' => null], $row)
            );
        }
    }
}
