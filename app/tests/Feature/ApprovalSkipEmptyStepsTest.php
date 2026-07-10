<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\Payable;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalSkipEmptyStepsTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = app(ApprovalWorkflowService::class);
    }

    public function test_etapa_sem_aprovador_e_pulada(): void
    {
        $gestor = User::factory()->create(['is_active' => true]);
        $financeiro = User::factory()->create(['is_active' => true]);
        $presidente = User::factory()->create(['is_active' => true]);

        $financeDept = Department::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'is_active' => true]);
        $financeiro->forceFill(['department_id' => $financeDept->id])->save();

        foreach ([
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Gerência', $gestor->id],
            [3, 'diretoria', 'Diretoria', null],
            [4, 'financeiro', 'Financeiro', $financeiro->id],
            [5, 'presidencia', 'Presidência', $presidente->id],
        ] as [$order, $level, $label, $userId]) {
            ApprovalTrail::create([
                'area' => 'licitacao',
                'order' => $order,
                'level_name' => $level,
                'role_label' => $label,
                'default_user_id' => $userId,
            ]);
        }

        $dept = Department::create(['name' => 'Licitação', 'slug' => 'licitacao', 'is_active' => true]);
        $dept->forceFill(['area_key' => 'licitacao', 'manager_id' => $gestor->id, 'director_id' => null])->save();

        $sender = User::factory()->create(['is_active' => true, 'department_id' => $dept->id]);
        $payable = Payable::create([
            'title_number' => 'SKIP-' . uniqid(),
            'supplier_name' => 'Fornecedor',
            'amount' => 100,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
        ]);

        $preview = $this->workflow->buildPreviewStepsForSender($sender);
        $this->assertTrue($preview['ok']);
        $this->assertFalse(collect($preview['steps'])->pluck('level_name')->contains('diretoria'));

        $this->workflow->sendForApproval($payable, $sender);

        $levels = ApprovalStep::where('payable_id', $payable->id)->orderBy('order')->pluck('level_name');
        $this->assertEquals(['departamento', 'financeiro', 'presidencia'], $levels->all());
    }
}
