<?php

namespace Tests\Feature;

use App\Models\ApprovalTrail;
use App\Models\Bordero;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationPanelTest extends TestCase
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

    public function test_panel_requires_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get('/financeiro/autorizacoes')->assertForbidden();
    }

    public function test_panel_loads(): void
    {
        $user = $this->userWith(['financeiro.contas_pagar.visualizar']);
        $this->actingAs($user)->get('/financeiro/autorizacoes')->assertOk();
    }

    public function test_summary_counts_and_my_action_items(): void
    {
        $approver = User::factory()->create(['is_active' => true]);
        $approver->permissions()->attach(
            Permission::firstOrCreate(['key' => '*'], ['label' => '*', 'module' => 'system'])->id
        );

        ApprovalTrail::create(['area' => 'matriz', 'order' => 1, 'level_name' => 'departamento', 'role_label' => 'Departamento', 'default_user_id' => null]);
        ApprovalTrail::create(['area' => 'matriz', 'order' => 2, 'level_name' => 'gerencia', 'role_label' => 'G', 'default_user_id' => $approver->id]);

        $payable = Payable::create([
            'title_number' => 'P-AUTH-1',
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 250,
            'due_date' => now()->toDateString(),
            'status' => 'pendente',
        ]);

        $sender = User::factory()->create(['is_active' => true]);
        $workflow = new ApprovalWorkflowService();
        $workflow->sendForApproval($payable, $sender, 'matriz');

        $response = $this->actingAs($approver)->get('/financeiro/autorizacoes');
        $response->assertOk();

        $props = $response->original->getData()['page']['props'] ?? [];

        $this->assertSame(1, $props['summary']['aguardando_aprovacao']['count']);
        $this->assertSame(1, $props['summary']['aguardando_aprovacao']['payables']);
        $this->assertTrue(collect($props['my_action'])->contains(fn ($i) => $i['type'] === 'payable' && $i['id'] === $payable->id));
        $this->assertTrue(collect($props['in_approval'])->contains(fn ($i) => $i['type'] === 'payable' && $i['id'] === $payable->id));
    }

    public function test_includes_borderos_when_user_has_permission(): void
    {
        $user = $this->userWith([
            '*',
            'financeiro.contas_pagar.visualizar',
            'financeiro.borderos.visualizar',
        ]);

        Bordero::create([
            'number' => 'B-001',
            'description' => 'Teste',
            'status' => 'aguardando_aprovacao',
            'total_amount' => 500,
            'items_count' => 1,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/financeiro/autorizacoes');
        $response->assertOk();

        $props = $response->original->getData()['page']['props'] ?? [];
        $this->assertTrue($props['permissions']['borderos']);
        $this->assertGreaterThanOrEqual(1, $props['summary']['aguardando_aprovacao']['borderos']);
    }
}
