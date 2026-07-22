<?php

namespace Tests\Feature;

use App\Models\Bordero;
use App\Models\Department;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use App\Services\FinanceiroDepartmentScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class FinanceiroDepartmentScopeTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $keys, ?int $departmentId = null): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'department_id' => $departmentId,
        ]);

        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id
            );
        }

        return $user;
    }

    public function test_module_permission_bypasses_department_lock(): void
    {
        $dept = Department::create(['name' => 'Financeiro', 'is_active' => true]);
        $user = $this->userWithPermissions([
            'financeiro.ver_todos_departamentos',
        ], $dept->id);

        $this->assertNull(app(FinanceiroDepartmentScope::class)->resolve($user));
        $this->assertTrue(app(FinanceiroDepartmentScope::class)->canBypass($user));
    }

    public function test_legacy_cp_permission_still_bypasses(): void
    {
        $dept = Department::create(['name' => 'Financeiro', 'is_active' => true]);
        $user = $this->userWithPermissions([
            'financeiro.contas_pagar.ver_todos_departamentos',
        ], $dept->id);

        $this->assertNull(app(FinanceiroDepartmentScope::class)->resolve($user));
    }

    public function test_user_without_permission_is_locked_to_department(): void
    {
        $dept = Department::create(['name' => 'DP / RH', 'is_active' => true]);
        $user = $this->userWithPermissions([
            'financeiro.contas_pagar.visualizar',
        ], $dept->id);

        $this->assertSame($dept->id, app(FinanceiroDepartmentScope::class)->resolve($user));
    }

    public function test_extra_departments_expand_allowed_ids(): void
    {
        $financeiro = Department::create(['name' => 'Financeiro', 'is_active' => true]);
        $filiais = Department::create(['name' => 'Filiais', 'is_active' => true]);
        $outro = Department::create(['name' => 'Outro', 'is_active' => true]);

        $user = $this->userWithPermissions([
            'financeiro.contas_pagar.visualizar',
        ], $financeiro->id);
        $user->extraDepartments()->sync([$filiais->id]);

        $scope = app(FinanceiroDepartmentScope::class);
        $allowed = $scope->allowedDepartmentIds($user);

        $this->assertEqualsCanonicalizing([$financeiro->id, $filiais->id], $allowed);
        $this->assertNull($scope->resolve($user));
        $this->assertNotContains($outro->id, $allowed);
    }

    public function test_user_with_extra_sees_payables_from_home_and_extra_only(): void
    {
        $financeiro = Department::create(['name' => 'Financeiro', 'is_active' => true]);
        $filiais = Department::create(['name' => 'Filiais', 'is_active' => true]);
        $outro = Department::create(['name' => 'Outro', 'is_active' => true]);

        Payable::create([
            'title_number' => 'P-FIN',
            'supplier_name' => 'Fornecedor Fin',
            'amount' => 100,
            'due_date' => now()->toDateString(),
            'status' => 'pendente',
            'department_id' => $financeiro->id,
        ]);
        Payable::create([
            'title_number' => 'P-FIL',
            'supplier_name' => 'Fornecedor Fil',
            'amount' => 200,
            'due_date' => now()->toDateString(),
            'status' => 'pendente',
            'department_id' => $filiais->id,
        ]);
        Payable::create([
            'title_number' => 'P-OUT',
            'supplier_name' => 'Fornecedor Out',
            'amount' => 300,
            'due_date' => now()->toDateString(),
            'status' => 'pendente',
            'department_id' => $outro->id,
        ]);

        $user = $this->userWithPermissions([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.ver_todas_filiais',
        ], $financeiro->id);
        $user->extraDepartments()->sync([$filiais->id]);

        $this->actingAs($user)
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('payables.data', 2)
                ->where('canChangeDepartmentFilter', true)
                ->has('allowedDepartments', 2)
            );
    }

    public function test_user_without_extra_still_locked_to_own_department(): void
    {
        $financeiro = Department::create(['name' => 'Financeiro', 'is_active' => true]);
        $filiais = Department::create(['name' => 'Filiais', 'is_active' => true]);

        Payable::create([
            'title_number' => 'P-FIN',
            'supplier_name' => 'Fornecedor Fin',
            'amount' => 100,
            'due_date' => now()->toDateString(),
            'status' => 'pendente',
            'department_id' => $financeiro->id,
        ]);
        Payable::create([
            'title_number' => 'P-FIL',
            'supplier_name' => 'Fornecedor Fil',
            'amount' => 200,
            'due_date' => now()->toDateString(),
            'status' => 'pendente',
            'department_id' => $filiais->id,
        ]);

        $user = $this->userWithPermissions([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.ver_todas_filiais',
        ], $financeiro->id);

        $this->actingAs($user)
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('payables.data', 1)
                ->where('payables.data.0.title_number', 'P-FIN')
                ->where('canChangeDepartmentFilter', false)
            );
    }

    public function test_resolve_filter_allows_choice_among_extras(): void
    {
        $financeiro = Department::create(['name' => 'Financeiro', 'is_active' => true]);
        $filiais = Department::create(['name' => 'Filiais', 'is_active' => true]);

        $user = $this->userWithPermissions([
            'financeiro.contas_pagar.visualizar',
        ], $financeiro->id);
        $user->extraDepartments()->sync([$filiais->id]);

        $request = Request::create('/financeiro/contas-pagar', 'GET', [
            'department_id' => $filiais->id,
        ]);
        $request->setUserResolver(fn () => $user);

        $ctx = app(FinanceiroDepartmentScope::class)->resolveFilter($request);

        $this->assertTrue($ctx['can_change']);
        $this->assertSame($filiais->id, $ctx['department_id']);
        $this->assertSame([$filiais->id], $ctx['department_ids']);
    }

    public function test_bordero_with_extra_department_titles_is_visible(): void
    {
        $financeiro = Department::create(['name' => 'Financeiro', 'is_active' => true]);
        $filiais = Department::create(['name' => 'Filiais', 'is_active' => true]);
        $outro = Department::create(['name' => 'Outro', 'is_active' => true]);
        $creator = User::factory()->create(['is_active' => true]);

        $borderoFil = Bordero::create([
            'number' => 'B-FIL',
            'description' => 'Borderô Filiais',
            'status' => 'aguardando_aprovacao',
            'total_amount' => 100,
            'items_count' => 1,
            'created_by' => $creator->id,
        ]);
        Payable::create([
            'title_number' => 'P-FIL',
            'supplier_name' => 'Fornecedor Fil',
            'amount' => 100,
            'due_date' => now()->toDateString(),
            'status' => 'aguardando_aprovacao',
            'department_id' => $filiais->id,
            'bordero_id' => $borderoFil->id,
        ]);

        $borderoOut = Bordero::create([
            'number' => 'B-OUT',
            'description' => 'Borderô Outro',
            'status' => 'aguardando_aprovacao',
            'total_amount' => 200,
            'items_count' => 1,
            'created_by' => $creator->id,
        ]);
        Payable::create([
            'title_number' => 'P-OUT',
            'supplier_name' => 'Fornecedor Out',
            'amount' => 200,
            'due_date' => now()->toDateString(),
            'status' => 'aguardando_aprovacao',
            'department_id' => $outro->id,
            'bordero_id' => $borderoOut->id,
        ]);

        $user = $this->userWithPermissions([
            'financeiro.borderos.visualizar',
            'financeiro.contas_pagar.ver_todas_filiais',
        ], $financeiro->id);
        $user->extraDepartments()->sync([$filiais->id]);

        $this->actingAs($user)
            ->get('/financeiro/borderos?status=aguardando_aprovacao')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('borderos.data', 1)
                ->where('borderos.data.0.number', 'B-FIL')
            );
    }

    public function test_bordero_list_scopes_by_department(): void
    {
        $deptA = Department::create(['name' => 'Dept A', 'is_active' => true]);
        $deptB = Department::create(['name' => 'Dept B', 'is_active' => true]);
        $creator = User::factory()->create(['is_active' => true]);

        $borderoA = Bordero::create([
            'number' => 'B-A',
            'description' => 'Borderô A',
            'status' => 'aguardando_aprovacao',
            'total_amount' => 100,
            'items_count' => 1,
            'created_by' => $creator->id,
        ]);
        Payable::create([
            'title_number' => 'P-A',
            'supplier_name' => 'Fornecedor A',
            'amount' => 100,
            'due_date' => now()->toDateString(),
            'status' => 'aguardando_aprovacao',
            'department_id' => $deptA->id,
            'bordero_id' => $borderoA->id,
        ]);

        $borderoB = Bordero::create([
            'number' => 'B-B',
            'description' => 'Borderô B',
            'status' => 'aguardando_aprovacao',
            'total_amount' => 200,
            'items_count' => 1,
            'created_by' => $creator->id,
        ]);
        Payable::create([
            'title_number' => 'P-B',
            'supplier_name' => 'Fornecedor B',
            'amount' => 200,
            'due_date' => now()->toDateString(),
            'status' => 'aguardando_aprovacao',
            'department_id' => $deptB->id,
            'bordero_id' => $borderoB->id,
        ]);

        $scopedUser = $this->userWithPermissions([
            'financeiro.borderos.visualizar',
            'financeiro.contas_pagar.ver_todas_filiais',
        ], $deptA->id);

        $this->actingAs($scopedUser)
            ->get('/financeiro/borderos?status=aguardando_aprovacao')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('borderos.data', 1)
                ->where('borderos.data.0.number', 'B-A')
            );

        $globalUser = $this->userWithPermissions([
            'financeiro.borderos.visualizar',
            'financeiro.ver_todos_departamentos',
            'financeiro.contas_pagar.ver_todas_filiais',
        ], $deptA->id);

        $this->actingAs($globalUser)
            ->get('/financeiro/borderos?status=aguardando_aprovacao')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('borderos.data', 2));
    }
}
