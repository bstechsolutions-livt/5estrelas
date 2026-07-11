<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FinanceiroDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $key = 'financeiro.contas_pagar.visualizar'): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id
        );

        return $user;
    }

    public function test_dashboard_requires_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/financeiro/dashboard')
            ->assertForbidden();
    }

    public function test_dashboard_renders_with_kpis(): void
    {
        Payable::create([
            'title_number' => 'T-DASH',
            'supplier_name' => 'Fornecedor X',
            'amount' => 1500,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
        ]);

        $this->actingAs($this->userWithPermission())
            ->get('/financeiro/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Financeiro/Dashboard', false)
                ->has('kpis')
                ->has('payables_by_status')
                ->has('proximos_vencimentos')
                ->where('kpis.em_aberto.count', 1)
            );
    }

    public function test_dashboard_scopes_payables_by_user_department(): void
    {
        $deptA = Department::create(['name' => 'Dept A', 'is_active' => true]);
        $deptB = Department::create(['name' => 'Dept B', 'is_active' => true]);

        Payable::create([
            'title_number' => 'A1',
            'supplier_name' => 'Fornecedor A',
            'amount' => 100,
            'due_date' => now()->toDateString(),
            'status' => 'pendente',
            'department_id' => $deptA->id,
        ]);
        Payable::create([
            'title_number' => 'B1',
            'supplier_name' => 'Fornecedor B',
            'amount' => 200,
            'due_date' => now()->toDateString(),
            'status' => 'pendente',
            'department_id' => $deptB->id,
        ]);

        $user = $this->userWithPermission();
        $user->update(['department_id' => $deptA->id]);

        $this->actingAs($user)
            ->get('/financeiro/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('kpis.em_aberto.count', 1)
                ->where('department.id', $deptA->id)
            );
    }

    public function test_admin_sees_all_departments(): void
    {
        $deptA = Department::create(['name' => 'Dept A', 'is_active' => true]);
        $deptB = Department::create(['name' => 'Dept B', 'is_active' => true]);

        Payable::create([
            'title_number' => 'A1',
            'supplier_name' => 'Fornecedor A',
            'amount' => 100,
            'due_date' => now()->toDateString(),
            'status' => 'pendente',
            'department_id' => $deptA->id,
        ]);
        Payable::create([
            'title_number' => 'B1',
            'supplier_name' => 'Fornecedor B',
            'amount' => 200,
            'due_date' => now()->toDateString(),
            'status' => 'pendente',
            'department_id' => $deptB->id,
        ]);

        $admin = $this->userWithPermission('*');

        $this->actingAs($admin)
            ->get('/financeiro/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('kpis.em_aberto.count', 2)
                ->where('department', null)
            );
    }
}
