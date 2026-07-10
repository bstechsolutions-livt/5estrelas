<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Departamentos + gestor/diretor conforme organograma de aprovação v3.0.
 *
 * Gestor (manager_id) = 1ª aprovação (gerência/head da área).
 * Diretor (director_id) = etapa diretoria (quando existe na trilha).
 */
class DepartmentHierarchySeeder extends Seeder
{
    /** @var array<string, User|null> */
    private array $users = [];

    public function run(): void
    {
        $this->users = [
            'erismar' => $this->findUser('erismar@grupo5estrelas.com.br'),
            'cilas' => $this->findUser('cilas@grupo5estrelas.com.br'),
            'matheus' => $this->findUser('matheus.xavier@grupo5estrelas.com.br'),
            'leiliane' => $this->findUser('leiliane@grupo5estrelas.com.br'),
            'leticia' => $this->findUser('leticia@grupo5estrelas.com.br'),
            'luiz' => $this->findUser('luiz.farias@grupo5estrelas.com.br'),
            'silene' => $this->findUser('silene@grupo5estrelas.com.br'),
            'alexyxandra' => $this->findUser('alexyxandra@grupo5estrelas.com.br'),
            'dionei' => $this->findUser('dionei@grupo5estrelas.com.br'),
            'ana_paula' => $this->findUser('anapaula@grupo5estrelas.com.br'),
        ];

        $departments = [
            // slug, nome, area_key, gestor, diretor
            ['matriz', 'Matriz', 'matriz', 'erismar', 'dionei'],
            ['filiais', 'Filiais', 'filiais', 'cilas', 'dionei'],
            ['compras', 'Compras', 'compras', 'erismar', 'dionei'],
            ['modernizacao', 'Modernização', 'modernizacao', 'matheus', 'dionei'],
            ['comercial', 'Comercial', 'comercial', 'leiliane', 'ana_paula'],
            ['faturamento', 'Faturamento', 'comercial', 'leiliane', 'ana_paula'],
            ['marketing', 'Marketing', 'comercial', 'leiliane', 'ana_paula'],
            ['licitacao', 'Licitação', 'licitacao', 'leticia', 'luiz'],
            ['dp_rh', 'DP / RH', 'dp_rh', 'silene', null],
            ['juridico', 'Jurídico', 'juridico', 'alexyxandra', null],
            ['multi', 'Multi', 'multi_star', 'luiz', null],
            ['star', 'Star', 'multi_star', 'luiz', null],
            ['baluarte', 'Baluarte', 'baluarte', 'erismar', 'ana_paula'],
        ];

        $activeSlugs = [];

        foreach ($departments as [$slug, $name, $areaKey, $managerKey, $directorKey]) {
            $activeSlugs[] = $slug;
            Department::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'is_active' => true,
                    'area_key' => $areaKey,
                    'manager_id' => $this->users[$managerKey]?->id,
                    'director_id' => $directorKey ? $this->users[$directorKey]?->id : null,
                ]
            );
        }

        $legacyNames = [
            'Matriz / Operações',
            'Modernização / TI',
            'Financeiro',
            'Presidência',
        ];

        $names = array_column($departments, 1);

        Department::whereNull('slug')->whereIn('name', $names)->update(['is_active' => false]);
        Department::whereIn('name', $legacyNames)->update(['is_active' => false]);

        $this->command?->info('✅ Departamentos sincronizados com organograma (' . count($departments) . ' unidades).');
    }

    private function findUser(string $email): ?User
    {
        return User::where('email', $email)->where('is_active', true)->first();
    }
}
