<?php

namespace Tests\Feature;

use App\Models\ApprovalFlowArea;
use App\Models\ApprovalFlowOverride;
use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableDocument;
use App\Models\Permission;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalFlowOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function workflowAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => 'financeiro.workflows.configurar'], ['label' => 'x', 'module' => 'financeiro'])->id
        );

        return $user;
    }

    private function sender(): User
    {
        $user = User::factory()->create(['is_active' => true, 'department_id' => null]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => 'financeiro.contas_pagar.visualizar'], ['label' => 'x', 'module' => 'financeiro'])->id
        );

        return $user;
    }

    public function test_override_matches_codccu(): void
    {
        $payable = Payable::create([
            'title_number' => 'TIT-1',
            'supplier_name' => 'Fornecedor',
            'amount' => 100,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pendente',
            'codccu' => '6289',
        ]);

        $rule = ApprovalFlowOverride::create([
            'area' => 'compras',
            'step_order' => 1,
            'codccu' => ['6289', '2559'],
            'title_patterns' => [],
            'approver_user_id' => User::factory()->create()->id,
        ]);

        $this->assertTrue($rule->matchesPayable($payable));

        $payable->update(['codccu' => '9999']);
        $this->assertFalse($rule->fresh()->matchesPayable($payable->fresh()));
    }

    public function test_override_matches_title_pattern(): void
    {
        $payable = Payable::create([
            'title_number' => '58 03/03',
            'supplier_name' => 'Fornecedor',
            'amount' => 100,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pendente',
        ]);

        $rule = ApprovalFlowOverride::create([
            'area' => 'compras',
            'step_order' => 1,
            'codccu' => [],
            'title_patterns' => ['58 03'],
            'approver_user_id' => User::factory()->create()->id,
        ]);

        $this->assertTrue($rule->matchesPayable($payable));
    }

    public function test_config_screen_saves_override(): void
    {
        $admin = $this->workflowAdmin();
        $silas = User::factory()->create(['name' => 'Silas Teste', 'is_active' => true]);

        ApprovalFlowArea::create(['area' => 'compras', 'label' => 'Compras']);
        ApprovalTrail::create([
            'area' => 'compras',
            'order' => 1,
            'level_name' => 'gerencia',
            'role_label' => 'Gerência Operacional',
            'approver_type' => ApprovalTrail::TYPE_USUARIO,
            'default_user_id' => User::factory()->create()->id,
        ]);

        $this->actingAs($admin)
            ->post('/financeiro/fluxos-aprovacao', [
                'trails' => [[
                    'area' => 'compras',
                    'area_label' => 'Compras',
                    'levels' => [[
                        'id' => ApprovalTrail::first()->id,
                        'order' => 1,
                        'role_label' => 'Gerência Operacional',
                        'approver_type' => ApprovalTrail::TYPE_USUARIO,
                        'default_user_id' => ApprovalTrail::first()->default_user_id,
                        'approver_department_id' => null,
                    ]],
                    'overrides' => [[
                        'step_order' => 1,
                        'label' => 'Silas CC obra',
                        'codccu_text' => "6289\n2559",
                        'title_patterns_text' => '',
                        'approver_user_id' => $silas->id,
                        'priority' => 10,
                        'is_active' => true,
                    ]],
                ]],
                'deleted_areas' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('approval_flow_overrides', [
            'area' => 'compras',
            'step_order' => 1,
            'approver_user_id' => $silas->id,
        ]);
    }

    public function test_send_for_approval_uses_override_on_first_step(): void
    {
        $erismar = User::factory()->create(['name' => 'Erismar', 'is_active' => true]);
        $silas = User::factory()->create(['name' => 'Silas', 'is_active' => true]);
        $dionei = User::factory()->create(['name' => 'Dionei', 'is_active' => true]);

        $dept = Department::create([
            'name' => 'Compras',
            'slug' => 'compras',
            'area_key' => 'compras',
            'is_active' => true,
            'manager_id' => $erismar->id,
            'director_id' => $dionei->id,
        ]);

        ApprovalFlowArea::create(['area' => 'compras', 'label' => 'Compras']);
        ApprovalTrail::insert([
            [
                'area' => 'compras', 'order' => 1, 'level_name' => 'gerencia',
                'role_label' => 'Gerência', 'approver_type' => ApprovalTrail::TYPE_USUARIO,
                'default_user_id' => $erismar->id, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'area' => 'compras', 'order' => 2, 'level_name' => 'diretoria',
                'role_label' => 'Diretoria', 'approver_type' => ApprovalTrail::TYPE_DIRETOR_DEPTO,
                'default_user_id' => $dionei->id, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        ApprovalFlowOverride::create([
            'area' => 'compras',
            'step_order' => 1,
            'codccu' => ['6289'],
            'title_patterns' => [],
            'approver_user_id' => $silas->id,
            'priority' => 0,
        ]);

        $payable = Payable::create([
            'title_number' => 'TIT-CC',
            'supplier_name' => 'Fornecedor',
            'amount' => 500,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'em_preparacao',
            'department_id' => $dept->id,
            'codccu' => '6289',
        ]);

        PayableDocument::create([
            'payable_id' => $payable->id,
            'name' => 'nf.pdf',
            'path' => 'payables/docs/nf.pdf',
            'doc_type' => 'nota_fiscal',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $sender = $this->sender();
        $sender->update(['department_id' => $dept->id]);

        app(ApprovalWorkflowService::class)->sendForApproval($payable->fresh(), $sender);

        $first = $payable->fresh()->approvalSteps()->orderBy('order')->first();
        $this->assertSame($silas->id, $first->assigned_to);

        $payable2 = Payable::create([
            'title_number' => 'TIT-OUTRO',
            'supplier_name' => 'Fornecedor',
            'amount' => 500,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'em_preparacao',
            'department_id' => $dept->id,
            'codccu' => '1111',
        ]);
        PayableDocument::create([
            'payable_id' => $payable2->id,
            'name' => 'nf2.pdf',
            'path' => 'payables/docs/nf2.pdf',
            'doc_type' => 'nota_fiscal',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        app(ApprovalWorkflowService::class)->sendForApproval($payable2->fresh(), $sender);

        $firstDefault = $payable2->fresh()->approvalSteps()->orderBy('order')->first();
        $this->assertSame($erismar->id, $firstDefault->assigned_to);
    }
}
