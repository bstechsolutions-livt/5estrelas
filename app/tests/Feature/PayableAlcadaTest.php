<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PayableRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableAlcadaTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $keys, bool $active = true): User
    {
        $user = User::factory()->create(['is_active' => $active]);
        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id
            );
        }

        return $user;
    }

    public function test_index_retorna_200_com_permissao(): void
    {
        $user = $this->userWith(['financeiro.contas_pagar.alcada_gerenciar']);

        $this->actingAs($user)
            ->get('/financeiro/contas-pagar/alcada')
            ->assertStatus(200);
    }

    public function test_index_retorna_200_com_curinga(): void
    {
        $user = $this->userWith(['*']);

        $this->actingAs($user)
            ->get('/financeiro/contas-pagar/alcada')
            ->assertStatus(200);
    }

    public function test_index_retorna_403_sem_permissao(): void
    {
        $user = $this->userWith([]);

        $this->actingAs($user)
            ->get('/financeiro/contas-pagar/alcada')
            ->assertStatus(403);
    }

    public function test_store_associa_usuario_ao_papel_e_audita(): void
    {
        $admin = $this->userWith(['financeiro.contas_pagar.alcada_gerenciar']);
        $alvo = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->post('/financeiro/contas-pagar/alcada', ['role' => 'pagador', 'user_id' => $alvo->id])
            ->assertRedirect();

        $this->assertDatabaseHas('payable_roles', ['role' => 'pagador', 'user_id' => $alvo->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'contas_pagar.alcada_atribuido']);
    }

    public function test_store_valida_papel_invalido(): void
    {
        $admin = $this->userWith(['financeiro.contas_pagar.alcada_gerenciar']);
        $alvo = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->postJson('/financeiro/contas-pagar/alcada', ['role' => 'xpto', 'user_id' => $alvo->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_store_valida_usuario_inexistente(): void
    {
        $admin = $this->userWith(['financeiro.contas_pagar.alcada_gerenciar']);

        $this->actingAs($admin)
            ->postJson('/financeiro/contas-pagar/alcada', ['role' => 'pagador', 'user_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_store_nao_duplica_associacao(): void
    {
        $admin = $this->userWith(['financeiro.contas_pagar.alcada_gerenciar']);
        $alvo = User::factory()->create(['is_active' => true]);
        $payload = ['role' => 'pagador', 'user_id' => $alvo->id];

        $this->actingAs($admin)->post('/financeiro/contas-pagar/alcada', $payload);
        $this->actingAs($admin)->post('/financeiro/contas-pagar/alcada', $payload);

        $this->assertEquals(1, PayableRole::where($payload)->count());
    }

    public function test_destroy_remove_associacao_e_audita(): void
    {
        $admin = $this->userWith(['financeiro.contas_pagar.alcada_gerenciar']);
        $alvo = User::factory()->create(['is_active' => true]);
        PayableRole::create(['role' => 'pagador', 'user_id' => $alvo->id]);

        $this->actingAs($admin)
            ->delete("/financeiro/contas-pagar/alcada/pagador/{$alvo->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('payable_roles', ['role' => 'pagador', 'user_id' => $alvo->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'contas_pagar.alcada_removido']);
    }

    public function test_store_403_sem_permissao(): void
    {
        $user = $this->userWith([]);
        $alvo = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post('/financeiro/contas-pagar/alcada', ['role' => 'pagador', 'user_id' => $alvo->id])
            ->assertStatus(403);

        $this->assertDatabaseMissing('payable_roles', ['user_id' => $alvo->id]);
    }

    public function test_destroy_403_sem_permissao(): void
    {
        $user = $this->userWith([]);
        $alvo = User::factory()->create(['is_active' => true]);
        PayableRole::create(['role' => 'pagador', 'user_id' => $alvo->id]);

        $this->actingAs($user)
            ->delete("/financeiro/contas-pagar/alcada/pagador/{$alvo->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('payable_roles', ['role' => 'pagador', 'user_id' => $alvo->id]);
    }
}
