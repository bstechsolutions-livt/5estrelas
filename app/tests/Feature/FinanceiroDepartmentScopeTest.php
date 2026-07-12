<?php

namespace Tests\Feature;

use App\Models\Bordero;
use App\Models\Department;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use App\Services\FinanceiroDepartmentScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
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
