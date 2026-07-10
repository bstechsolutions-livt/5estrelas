<?php

namespace Tests\Feature;

use App\Models\ApprovalFlowArea;
use App\Models\ApprovalTrail;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalFlowConfigTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => 'financeiro.workflows.configurar'], ['label' => 'Configurar fluxos', 'module' => 'financeiro'])->id
        );

        return $user;
    }

    private function regularUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => 'financeiro.contas_pagar.visualizar'], ['label' => 'Ver CP', 'module' => 'financeiro'])->id
        );

        return $user;
    }

    public function test_index_requires_permission(): void
    {
        $user = $this->regularUser();
        $this->actingAs($user)->get('/financeiro/fluxos-aprovacao')->assertStatus(403);
    }

    public function test_index_accessible_with_permission(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin)->get('/financeiro/fluxos-aprovacao')->assertStatus(200);
    }

    public function test_update_changes_approver(): void
    {
        $admin = $this->adminUser();
        $newUser = User::factory()->create(['is_active' => true]);

        ApprovalFlowArea::create(['area' => 'matriz', 'label' => 'Matriz']);
        $trail = ApprovalTrail::create([
            'area' => 'matriz', 'order' => 1, 'level_name' => 'gerencia',
            'role_label' => 'Gerência', 'approver_type' => ApprovalTrail::TYPE_USUARIO,
            'default_user_id' => null,
        ]);

        $this->actingAs($admin)
            ->post('/financeiro/fluxos-aprovacao', [
                'trails' => [[
                    'area' => 'matriz',
                    'area_label' => 'Matriz',
                    'levels' => [[
                        'id' => $trail->id,
                        'order' => 1,
                        'role_label' => 'Gerência',
                        'approver_type' => ApprovalTrail::TYPE_USUARIO,
                        'default_user_id' => $newUser->id,
                        'approver_department_id' => null,
                    ]],
                ]],
                'deleted_areas' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('approval_trails', [
            'id' => $trail->id,
            'default_user_id' => $newUser->id,
        ]);
    }

    public function test_update_creates_new_flow(): void
    {
        $admin = $this->adminUser();
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->post('/financeiro/fluxos-aprovacao', [
                'trails' => [[
                    'area' => 'area_novo',
                    'area_label' => 'Novo Setor',
                    'levels' => [[
                        'id' => null,
                        'order' => 1,
                        'role_label' => 'Gestor',
                        'approver_type' => ApprovalTrail::TYPE_GESTOR_DEPTO,
                        'default_user_id' => null,
                        'approver_department_id' => null,
                    ], [
                        'id' => null,
                        'order' => 2,
                        'role_label' => 'Aprovação final',
                        'approver_type' => ApprovalTrail::TYPE_USUARIO,
                        'default_user_id' => $user->id,
                        'approver_department_id' => null,
                    ]],
                ]],
                'deleted_areas' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('approval_flow_areas', ['area' => 'novo_setor', 'label' => 'Novo Setor']);
        $this->assertDatabaseHas('approval_trails', [
            'area' => 'novo_setor',
            'approver_type' => ApprovalTrail::TYPE_USUARIO,
            'default_user_id' => $user->id,
        ]);
    }

    public function test_update_403_without_permission(): void
    {
        $user = $this->regularUser();
        ApprovalFlowArea::create(['area' => 'matriz', 'label' => 'Matriz']);
        $trail = ApprovalTrail::create([
            'area' => 'matriz', 'order' => 1, 'level_name' => 'gerencia',
            'role_label' => 'Gerência', 'approver_type' => ApprovalTrail::TYPE_USUARIO,
            'default_user_id' => null,
        ]);

        $this->actingAs($user)
            ->post('/financeiro/fluxos-aprovacao', [
                'trails' => [[
                    'area' => 'matriz',
                    'area_label' => 'Matriz',
                    'levels' => [[
                        'id' => $trail->id,
                        'order' => 1,
                        'role_label' => 'Gerência',
                        'approver_type' => ApprovalTrail::TYPE_USUARIO,
                        'default_user_id' => 1,
                        'approver_department_id' => null,
                    ]],
                ]],
                'deleted_areas' => [],
            ])
            ->assertStatus(403);
    }
}
