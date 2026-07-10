<?php

namespace Tests\Feature;

use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\Payable;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalFinanceDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = app(ApprovalWorkflowService::class);

        $gerente = User::factory()->create(['name' => 'Gerente', 'is_active' => true]);
        $financeiro1 = User::factory()->create(['name' => 'Financeiro A', 'is_active' => true]);
        $financeiro2 = User::factory()->create(['name' => 'Financeiro B', 'is_active' => true]);
        $presidente = User::factory()->create(['name' => 'Presidente', 'is_active' => true]);

        $financeDept = Department::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'is_active' => true]);
        $financeiro1->forceFill(['department_id' => $financeDept->id])->save();
        $financeiro2->forceFill(['department_id' => $financeDept->id])->save();

        foreach ([
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Gerência', $gerente->id],
            [3, 'financeiro', 'Financeiro (auditoria)', $presidente->id],
            [4, 'presidencia', 'Presidência', $presidente->id],
        ] as [$order, $level, $label, $userId]) {
            ApprovalTrail::create([
                'area' => 'dp_rh',
                'order' => $order,
                'level_name' => $level,
                'role_label' => $label,
                'default_user_id' => $userId,
            ]);
        }
    }

    public function test_qualquer_integrante_do_financeiro_aprova_etapa_financeiro(): void
    {
        $sender = User::factory()->create(['is_active' => true]);
        $payable = Payable::create([
            'title_number' => 'FIN-' . uniqid(),
            'supplier_name' => 'Fornecedor',
            'amount' => 100,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
        ]);

        $this->workflow->sendForApproval($payable, $sender, 'dp_rh');

        $gerente = User::where('name', 'Gerente')->first();
        $this->workflow->approve($payable, $gerente);

        $step = $this->workflow->currentStep($payable);
        $this->assertSame('financeiro', $step->level_name);
        $this->assertNull($step->assigned_to);

        $financeiroB = User::where('name', 'Financeiro B')->first();
        $outro = User::factory()->create(['is_active' => true]);

        $denied = $this->workflow->approve($payable, $outro);
        $this->assertFalse($denied['success']);

        $ok = $this->workflow->approve($payable, $financeiroB);
        $this->assertTrue($ok['success']);
    }

    public function test_update_fluxo_ignora_etapa_financeiro(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->permissions()->attach(
            \App\Models\Permission::firstOrCreate(
                ['key' => 'financeiro.workflows.configurar'],
                ['label' => 'Config', 'module' => 'financeiro']
            )->id
        );

        $trail = ApprovalTrail::where('area', 'dp_rh')->where('level_name', 'financeiro')->first();
        $outro = User::factory()->create(['is_active' => true]);
        $originalUserId = $trail->default_user_id;

        $this->actingAs($admin)
            ->post('/financeiro/fluxos-aprovacao', [
                'levels' => [['id' => $trail->id, 'default_user_id' => $outro->id]],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('approval_trails', [
            'id' => $trail->id,
            'default_user_id' => $originalUserId,
        ]);
    }
}
