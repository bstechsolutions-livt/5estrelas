<?php

namespace Tests\Feature;

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

        $trail = ApprovalTrail::create([
            'area' => 'matriz', 'order' => 1, 'level_name' => 'gerencia',
            'role_label' => 'Gerência', 'default_user_id' => null,
        ]);

        $this->actingAs($admin)
            ->post('/financeiro/fluxos-aprovacao', [
                'levels' => [['id' => $trail->id, 'default_user_id' => $newUser->id]],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('approval_trails', [
            'id' => $trail->id,
            'default_user_id' => $newUser->id,
        ]);
    }

    public function test_update_403_without_permission(): void
    {
        $user = $this->regularUser();
        $trail = ApprovalTrail::create([
            'area' => 'matriz', 'order' => 1, 'level_name' => 'gerencia',
            'role_label' => 'Gerência', 'default_user_id' => null,
        ]);

        $this->actingAs($user)
            ->post('/financeiro/fluxos-aprovacao', [
                'levels' => [['id' => $trail->id, 'default_user_id' => 1]],
            ])
            ->assertStatus(403);
    }
}
