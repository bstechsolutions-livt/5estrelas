<?php

namespace Tests\Feature;

use App\Models\ApprovalFlowArea;
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

        $senderDept = Department::create([
            'name' => 'DP/RH', 'slug' => 'dp-rh', 'is_active' => true,
            'area_key' => 'dp_rh', 'manager_id' => $gerente->id,
        ]);
        $gerente->forceFill(['department_id' => $senderDept->id])->save();

        $financeDept = Department::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'is_active' => true]);
        $financeiro1->forceFill(['department_id' => $financeDept->id])->save();
        $financeiro2->forceFill(['department_id' => $financeDept->id])->save();

        ApprovalFlowArea::create(['area' => 'dp_rh', 'label' => 'DP / RH']);

        foreach ([
            [1, 'departamento', 'Departamento', ApprovalTrail::TYPE_GESTOR_DEPTO, null],
            [2, 'financeiro', 'Financeiro (auditoria)', ApprovalTrail::TYPE_DEPT_FINANCEIRO, null],
            [3, 'presidencia', 'Presidência', ApprovalTrail::TYPE_USUARIO, $presidente->id],
        ] as [$order, $level, $label, $type, $userId]) {
            ApprovalTrail::create([
                'area' => 'dp_rh',
                'order' => $order,
                'level_name' => $level,
                'role_label' => $label,
                'approver_type' => $type,
                'default_user_id' => $userId,
            ]);
        }
    }

    public function test_qualquer_integrante_do_financeiro_aprova_etapa_financeiro(): void
    {
        $sender = User::where('name', 'Gerente')->first();
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

        $this->actingAs($admin)
            ->post('/financeiro/fluxos-aprovacao', [
                'trails' => [[
                    'area' => 'dp_rh',
                    'area_label' => 'DP / RH',
                    'levels' => [[
                        'id' => $trail->id,
                        'order' => $trail->order,
                        'role_label' => $trail->role_label,
                        'approver_type' => ApprovalTrail::TYPE_DEPT_FINANCEIRO,
                        'default_user_id' => $outro->id,
                        'approver_department_id' => null,
                    ]],
                ]],
                'deleted_areas' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('approval_trails', [
            'id' => $trail->id,
            'default_user_id' => null,
        ]);
    }

    public function test_fluxo_origem_financeiro_vai_direto_a_diretoria_e_presidencia(): void
    {
        $diretor = User::factory()->create(['name' => 'Dionei', 'is_active' => true]);
        $presidente = User::factory()->create(['name' => 'Leonardo', 'is_active' => true]);
        $karen = User::where('name', 'Financeiro A')->first();

        $financeDept = Department::where('slug', 'financeiro')->first();
        $financeDept->forceFill([
            'area_key' => 'financeiro',
            'manager_id' => $karen->id,
            'director_id' => $diretor->id,
        ])->save();
        $karen->forceFill(['department_id' => $financeDept->id])->save();

        ApprovalTrail::where('area', 'financeiro')->delete();
        ApprovalFlowArea::create(['area' => 'financeiro', 'label' => 'Financeiro']);

        foreach ([
            [1, 'departamento', 'Departamento', ApprovalTrail::TYPE_GESTOR_DEPTO, null],
            [2, 'diretoria', 'Diretoria', ApprovalTrail::TYPE_DIRETOR_DEPTO, $diretor->id],
            [3, 'presidencia', 'Presidência', ApprovalTrail::TYPE_USUARIO, $presidente->id],
        ] as [$order, $level, $label, $type, $userId]) {
            ApprovalTrail::create([
                'area' => 'financeiro',
                'order' => $order,
                'level_name' => $level,
                'role_label' => $label,
                'approver_type' => $type,
                'default_user_id' => $userId,
            ]);
        }

        $preview = $this->workflow->buildPreviewStepsForSender($karen->fresh());

        $this->assertTrue($preview['ok']);
        $this->assertSame('financeiro', $preview['area']);
        $this->assertCount(3, $preview['steps']);
        $this->assertSame(['departamento', 'diretoria', 'presidencia'], array_column($preview['steps'], 'level_name'));
        $this->assertNotContains('financeiro', array_column($preview['steps'], 'level_name'));
    }
}
