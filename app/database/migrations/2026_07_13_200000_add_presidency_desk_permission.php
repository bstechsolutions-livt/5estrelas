<?php

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::updateOrCreate(
            ['key' => 'financeiro.presidencia.painel'],
            [
                'label' => 'Painel de assinatura presidência',
                'module' => 'financeiro',
                'description' => 'Acessar o painel rápido de aprovação na etapa Presidência (assinatura final).',
            ],
        );

        $leonardo = User::where('email', 'leonardo@grupo5estrelas.com.br')->first();
        if (! $leonardo) {
            return;
        }

        $extraKeys = [
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.ver_todas_filiais',
            'financeiro.ver_todos_departamentos',
        ];

        $permissionIds = Permission::whereIn('key', array_merge([$permission->key], $extraKeys))
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('user_permission')->updateOrInsert(
                ['user_id' => $leonardo->id, 'permission_id' => $permissionId],
                [],
            );
        }
    }

    public function down(): void
    {
        $permission = Permission::where('key', 'financeiro.presidencia.painel')->first();
        if ($permission) {
            DB::table('user_permission')->where('permission_id', $permission->id)->delete();
            $permission->delete();
        }
    }
};
