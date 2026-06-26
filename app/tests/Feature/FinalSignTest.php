<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\PayableRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalSignTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $keys): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id
            );
        }
        return $user;
    }

    private function makePayable(string $status = 'conciliado'): Payable
    {
        return Payable::create([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor',
            'amount' => 3000,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => $status,
        ]);
    }

    public function test_assinante_can_finalize_conciliado_title(): void
    {
        $user = $this->userWith(['financeiro.contas_pagar.visualizar']);
        PayableRole::create(['role' => 'assinante', 'user_id' => $user->id]);
        $payable = $this->makePayable('conciliado');

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/encerrar")
            ->assertRedirect();

        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'encerrado']);
        $this->assertDatabaseHas('payable_comments', ['payable_id' => $payable->id, 'type' => 'approval']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'contas_pagar.encerrado']);
    }

    public function test_non_assinante_gets_403(): void
    {
        $user = $this->userWith(['financeiro.contas_pagar.visualizar']);
        // NOT in alçada as assinante
        $payable = $this->makePayable('conciliado');

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/encerrar")
            ->assertStatus(403);

        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'conciliado']);
    }

    public function test_cannot_finalize_non_conciliado(): void
    {
        $user = $this->userWith(['financeiro.contas_pagar.visualizar']);
        PayableRole::create(['role' => 'assinante', 'user_id' => $user->id]);
        $payable = $this->makePayable('pago'); // not conciliado

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/encerrar")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'pago']);
    }

    public function test_wildcard_without_assinante_role_gets_403(): void
    {
        $user = $this->userWith(['*']);
        // Has wildcard but NOT in alçada as assinante — segregação de função
        $payable = $this->makePayable('conciliado');

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/encerrar")
            ->assertStatus(403);
    }
}
