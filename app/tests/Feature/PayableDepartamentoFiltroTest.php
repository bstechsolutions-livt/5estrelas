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

    public function test_classifica_por_descricao_gfd(): void
    {
        $dept = $this->dpRhDepartment();
        $payable = $this->makePayable([
            'supplier_name' => 'TituloGfd',
            'description' => 'GFD JUNHO 2026 - FOLHA',
        ]);

        $resolved = app(PayableDepartmentClassifier::class)->departmentForPayable($payable);

        $this->assertNotNull($resolved);
        $this->assertSame($dept->id, $resolved->id);
    }

    public function test_classifica_por_codccu(): void
    {
        $dept = $this->dpRhDepartment();
        $payable = $this->makePayable([
            'supplier_name' => 'TituloCcu',
            'codccu' => '2363',
            'description' => 'Pagamento diverso',
        ]);

        $resolved = app(PayableDepartmentClassifier::class)->departmentForPayable($payable);

        $this->assertSame($dept->id, $resolved->id);
    }

    public function test_filtro_por_departamento_inclui_titulos_senior_sem_department_id(): void
    {
        $dept = $this->dpRhDepartment();
        $this->financeiroDepartment();

        $this->makePayable([
            'supplier_name' => 'MatchDpRh',
            'description' => 'TRCT FUNCIONARIO 123',
        ]);
        $this->makePayable([
            'supplier_name' => 'MatchFinanceiro',
            'description' => 'TAXA SERASA CONSULTA',
        ]);
        $this->makePayable([
            'supplier_name' => 'SemMatch',
            'description' => 'Material de escritorio',
        ]);

        $resp = $this->actingAs($this->userComTodosDepartamentos())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get("/financeiro/contas-pagar?status=pendente&department_id={$dept->id}")
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('supplier_name')->all();

        $this->assertContains('MatchDpRh', $names);
        $this->assertNotContains('MatchFinanceiro', $names);
        $this->assertNotContains('SemMatch', $names);
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
            'supplier_name' => 'SoHeuristicaDpRh',
            'description' => 'GFD JULHO',
        ]);

        $resp = $this->actingAs($this->userComTodosDepartamentos())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get("/financeiro/contas-pagar?status=pendente&department_id={$dept->id}")
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('supplier_name')->all();

        $this->assertContains('ExplicitoFinanceiro', $names);
        $this->assertNotContains('SoHeuristicaDpRh', $names);
    }

    public function test_index_exibe_department_nome(): void
    {
        $this->dpRhDepartment();
        $this->makePayable([
            'supplier_name' => 'ComDepto',
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
        $this->financeiroDepartment();

        $user = User::factory()->create(['is_active' => true, 'department_id' => $dept->id]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Visualizar CP', 'module' => 'financeiro'],
            )->id,
        );

        $this->makePayable(['supplier_name' => 'DoDpRh', 'description' => 'GFD JULHO']);
        $this->makePayable(['supplier_name' => 'DoFinanceiro', 'description' => 'TAXA SERASA']);

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
        $this->dpRhDepartment();

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

        $this->makePayable(['supplier_name' => 'DoDpRh', 'description' => 'GFD JULHO']);
        $this->makePayable(['supplier_name' => 'DoFinanceiro', 'description' => 'TAXA SERASA']);

        $resp = $this->actingAs($user)
            ->withHeaders(['X-Json-Only' => '1'])
            ->get("/financeiro/contas-pagar?status=pendente&department_id={$dept->id}")
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('supplier_name')->all();

        $this->assertContains('DoFinanceiro', $names);
        $this->assertNotContains('DoDpRh', $names);
    }
}
