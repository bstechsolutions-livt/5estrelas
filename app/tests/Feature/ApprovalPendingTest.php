<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\ApprovalTrail;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalPendingTest extends TestCase
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

    public function test_pending_page_loads(): void
    {
        $user = $this->userWith(['financeiro.contas_pagar.visualizar']);
        $this->actingAs($user)->get('/financeiro/pendencias')->assertOk();
    }

    public function test_shows_assigned_payables(): void
    {
        // Setup trail
        $approver = User::factory()->create(['is_active' => true]);
        $approver->permissions()->attach(
            Permission::firstOrCreate(['key' => 'financeiro.contas_pagar.visualizar'], ['label' => 'CP', 'module' => 'financeiro'])->id
        );
        ApprovalTrail::create(['area' => 'matriz', 'order' => 1, 'level_name' => 'gerencia', 'role_label' => 'G', 'default_user_id' => $approver->id]);

        $payable = Payable::create([
            'title_number' => 'P-1', 'supplier_name' => 'F', 'amount' => 100,
            'due_date' => now()->toDateString(), 'status' => 'pendente',
        ]);

        $sender = User::factory()->create(['is_active' => true]);
        $workflow = new ApprovalWorkflowService();
        $workflow->sendForApproval($payable, $sender, 'matriz');

        $response = $this->actingAs($approver)->get('/financeiro/pendencias');
        $response->assertOk();
        // O payable deve estar na lista de pendências do approver
        $props = $response->original->getData()['page']['props'] ?? [];
        $payables = $props['payables'] ?? [];
        $this->assertTrue(collect($payables)->contains('id', $payable->id));
    }
}
