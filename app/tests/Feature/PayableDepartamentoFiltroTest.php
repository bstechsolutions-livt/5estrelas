<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use App\Services\PayableDepartmentClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableDepartamentoFiltroTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Visualizar CP', 'module' => 'financeiro'],
            )->id,
        );
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.ver_todas_filiais'],
                ['label' => 'Ver todas filiais', 'module' => 'financeiro'],
            )->id,
        );

        return $user;
    }

    private function userComTodosDepartamentos(): User
    {
        $user = $this->activeUser();
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.ver_todos_departamentos'],
                ['label' => 'Ver todos deptos', 'module' => 'financeiro'],
            )->id,
        );

        return $user;
    }

    private function makePayable(array $attrs = []): Payable
    {
        return Payable::create(array_merge([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 1000.00,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
        ], $attrs));
    }

    private function dpRhDepartment(): Department
    {
        return Department::create([
            'name' => 'DP / RH',
            'slug' => 'dp_rh',
            'is_active' => true,
        ]);
    }

    private function financeiroDepartment(): Department
    {
        return Department::create([
            'name' => 'Financeiro',
            'slug' => 'financeiro',
            'is_active' => true,
        ]);
    }

    public function test_sem_lancador_e_sem_department_id_fica_sem_departamento(): void
    {
        $this->dpRhDepartment();
        $payable = $this->makePayable([
            'supplier_name' => 'TituloGfd',
            'description' => 'GFD JUNHO 2026 - FOLHA',
            'codccu' => '2363',
        ]);

        $resolved = app(PayableDepartmentClassifier::class)->departmentForPayable($payable);

        $this->assertNull($resolved);
    }

    public function test_classifica_por_senior_cod_usu_do_lancador(): void
    {
        $dept = $this->financeiroDepartment();
        $this->dpRhDepartment();

        User::factory()->create([
            'department_id' => $dept->id,
            'senior_cod_usu' => 77,
        ]);

        $payable = $this->makePayable([
            'supplier_name' => 'TituloLancador',
            'senior_cod_usu' => 77,
            'description' => 'GFD JUNHO 2026 - FOLHA',
        ]);

        $resolved = app(PayableDepartmentClassifier::class)->departmentForPayable($payable);

        $this->assertSame($dept->id, $resolved->id);
    }

    public function test_filtro_por_departamento_inclui_titulos_por_senior_cod_usu(): void
    {
        $dept = $this->financeiroDepartment();
        $this->dpRhDepartment();

        User::factory()->create([
            'department_id' => $dept->id,
            'senior_cod_usu' => 88,
        ]);

        $this->makePayable([
            'supplier_name' => 'MatchFinanceiroLauncher',
            'senior_cod_usu' => 88,
            'description' => 'Despesa operacional',
        ]);
        $this->makePayable([
            'supplier_name' => 'SemLancador',
            'description' => 'TRCT FUNCIONARIO 123',
        ]);

        $resp = $this->actingAs($this->userComTodosDepartamentos())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get("/financeiro/contas-pagar?status=pendente&department_id={$dept->id}")
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('supplier_name')->all();

        $this->assertContains('MatchFinanceiroLauncher', $names);
        $this->assertNotContains('SemLancador', $names);
    }

    public function test_filtro_respeita_department_id_explicito(): void
    {
        $dept = $this->financeiroDepartment();
        $this->dpRhDepartment();

        $this->makePayable([
            'supplier_name' => 'ExplicitoFinanceiro',
            'department_id' => $dept->id,
            'description' => 'GFD que seria DP/RH',
        ]);
        $this->makePayable([
            'supplier_name' => 'SemDepto',
            'description' => 'GFD JULHO',
        ]);

        $resp = $this->actingAs($this->userComTodosDepartamentos())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get("/financeiro/contas-pagar?status=pendente&department_id={$dept->id}")
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('supplier_name')->all();

        $this->assertContains('ExplicitoFinanceiro', $names);
        $this->assertNotContains('SemDepto', $names);
    }

    public function test_index_exibe_department_nome_do_lancador(): void
    {
        $dept = $this->dpRhDepartment();
        User::factory()->create([
            'department_id' => $dept->id,
            'senior_cod_usu' => 91,
        ]);

        $this->makePayable([
            'supplier_name' => 'ComDepto',
            'senior_cod_usu' => 91,
            'description' => 'PENSÃO ALIMENTICIA',
        ]);

        $resp = $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk();

        $row = collect($resp->json('data'))->firstWhere('supplier_name', 'ComDepto');

        $this->assertNotNull($row);
        $this->assertSame('DP / RH', $row['department_nome']);
    }

    public function test_usuario_sem_permissao_ve_apenas_seu_departamento(): void
    {
        $dept = $this->dpRhDepartment();
        $fin = $this->financeiroDepartment();

        $user = User::factory()->create(['is_active' => true, 'department_id' => $dept->id]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Visualizar CP', 'module' => 'financeiro'],
            )->id,
        );
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.ver_todas_filiais'],
                ['label' => 'Ver todas filiais', 'module' => 'financeiro'],
            )->id,
        );

        User::factory()->create(['department_id' => $dept->id, 'senior_cod_usu' => 201]);
        User::factory()->create(['department_id' => $fin->id, 'senior_cod_usu' => 202]);

        $this->makePayable(['supplier_name' => 'DoDpRh', 'senior_cod_usu' => 201]);
        $this->makePayable(['supplier_name' => 'DoFinanceiro', 'senior_cod_usu' => 202]);

        $resp = $this->actingAs($user)
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('supplier_name')->all();

        $this->assertContains('DoDpRh', $names);
        $this->assertNotContains('DoFinanceiro', $names);
    }

    public function test_usuario_com_permissao_ver_todos_pode_filtrar_outro_departamento(): void
    {
        $dept = $this->financeiroDepartment();
        $dp = $this->dpRhDepartment();

        $user = User::factory()->create(['is_active' => true, 'department_id' => $dept->id]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.ver_todos_departamentos'],
                ['label' => 'Ver todos deptos', 'module' => 'financeiro'],
            )->id,
        );
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Visualizar CP', 'module' => 'financeiro'],
            )->id,
        );
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.ver_todas_filiais'],
                ['label' => 'Ver todas filiais', 'module' => 'financeiro'],
            )->id,
        );

        User::factory()->create(['department_id' => $dp->id, 'senior_cod_usu' => 301]);
        User::factory()->create(['department_id' => $dept->id, 'senior_cod_usu' => 302]);

        $this->makePayable(['supplier_name' => 'DoDpRh', 'senior_cod_usu' => 301]);
        $this->makePayable(['supplier_name' => 'DoFinanceiro', 'senior_cod_usu' => 302]);

        $resp = $this->actingAs($user)
            ->withHeaders(['X-Json-Only' => '1'])
            ->get("/financeiro/contas-pagar?status=pendente&department_id={$dept->id}")
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('supplier_name')->all();

        $this->assertContains('DoFinanceiro', $names);
        $this->assertNotContains('DoDpRh', $names);
    }
}
