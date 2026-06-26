<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Configura os departamentos com hierarquia conforme Fluxo de Aprovação v3.0.
 * Vincula gestores e diretores conforme o documento.
 */
class DepartmentHierarchySeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            'erismar' => User::where('email', 'erismar@grupo5estrelas.com.br')->first(),
            'dionei' => User::where('email', 'dionei@grupo5estrelas.com.br')->first(),
            'leiliane' => User::where('email', 'leiliane@grupo5estrelas.com.br')->first(),
            'ana_paula' => User::where('email', 'anapaula@grupo5estrelas.com.br')->first(),
            'leticia' => User::where('email', 'leticia@grupo5estrelas.com.br')->first(),
            'luiz' => User::where('email', 'luiz.farias@grupo5estrelas.com.br')->first(),
            'silene' => User::where('email', 'silene@grupo5estrelas.com.br')->first(),
            'alexyxandra' => User::where('email', 'alexyxandra@grupo5estrelas.com.br')->first(),
            'karen' => User::where('email', 'karen@grupo5estrelas.com.br')->first(),
            'leonardo' => User::where('email', 'leonardo@grupo5estrelas.com.br')->first(),
            'matheus' => User::where('email', 'like', '%matheus%grupo5estrelas%')->first(),
        ];

        $depts = [
            // área_key, nome, gestor, diretor
            ['matriz', 'Matriz / Operações', 'erismar', 'dionei'],
            ['matriz', 'Filiais', 'erismar', 'dionei'],
            ['matriz', 'Compras', 'matheus', 'dionei'],
            ['matriz', 'Modernização / TI', 'matheus', 'dionei'],
            ['comercial', 'Comercial', 'leiliane', 'ana_paula'],
            ['comercial', 'Faturamento', 'leiliane', 'ana_paula'],
            ['comercial', 'Marketing', 'leiliane', 'ana_paula'],
            ['licitacao', 'Licitação', 'leticia', 'luiz'],
            ['dp_rh', 'DP / RH', 'silene', null], // sem diretoria
            ['juridico', 'Jurídico', 'alexyxandra', null], // sem diretoria
            ['financeiro', 'Financeiro', 'karen', 'dionei'],
            ['presidencia', 'Presidência', 'leonardo', null],
        ];

        foreach ($depts as [$areaKey, $nome, $managerKey, $directorKey]) {
            $dept = Department::updateOrCreate(
                ['name' => $nome],
                [
                    'is_active' => true,
                    'area_key' => $areaKey,
                    'manager_id' => $users[$managerKey]?->id ?? null,
                    'director_id' => $directorKey ? ($users[$directorKey]?->id ?? null) : null,
                ]
            );
        }

        // Vincula usuários aos seus departamentos
        $assignments = [
            'erismar' => 'Matriz / Operações',
            'dionei' => 'Matriz / Operações',
            'leiliane' => 'Comercial',
            'ana_paula' => 'Comercial',
            'leticia' => 'Licitação',
            'luiz' => 'Licitação',
            'silene' => 'DP / RH',
            'alexyxandra' => 'Jurídico',
            'karen' => 'Financeiro',
            'leonardo' => 'Presidência',
            'matheus' => 'Compras',
        ];

        foreach ($assignments as $key => $deptName) {
            $user = $users[$key] ?? null;
            $dept = Department::where('name', $deptName)->first();
            if ($user && $dept) {
                $user->update(['department_id' => $dept->id]);
            }
        }

        $this->command->info('✅ Departamentos + hierarquia configurados (12 depts, gestores e diretores vinculados).');
    }
}
