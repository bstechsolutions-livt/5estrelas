<?php

namespace Tests\Feature;

use App\Models\ApprovalFlowArea;
use App\Models\ApprovalStep;
use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableDocument;
use App\Models\Permission;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayablePaymentPriorityTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalWorkflowService $workflow;

    private User $gerente;

    private User $financeiro;

    private Department $senderDept;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->workflow = app(ApprovalWorkflowService::class);

        $this->gerente = User::factory()->create(['name' => 'Gerente', 'is_active' => true]);
        $this->financeiro = User::factory()->create(['name' => 'Financeiro', 'is_active' => true]);
        $presidente = User::factory()->create(['name' => 'Presidente', 'is_active' => true]);

        $this->senderDept = Department::create([
            'name' => 'DP/RH', 'slug' => 'dp-rh', 'is_active' => true,
            'area_key' => 'dp_rh', 'manager_id' => $this->gerente->id,
        ]);
        $this->gerente->forceFill(['department_id' => $this->senderDept->id])->save();

        $financeDept = Department::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'is_active' => true]);
        $this->financeiro->forceFill(['department_id' => $financeDept->id])->save();
        $this->attachPerm($this->financeiro, 'financeiro.contas_pagar.visualizar');
        $this->attachPerm($this->gerente, 'financeiro.contas_pagar.visualizar');

        ApprovalFlowArea::create(['area' => 'dp_rh', 'label' => 'DP / RH']);

        foreach ([
            [1, 'departamento', ApprovalTrail::TYPE_GESTOR_DEPTO, null],
            [2, 'financeiro', ApprovalTrail::TYPE_DEPT_FINANCEIRO, null],
            [3, 'presidencia', ApprovalTrail::TYPE_USUARIO, $presidente->id],
        ] as [$order, $level, $type, $userId]) {
            ApprovalTrail::create([
                'area' => 'dp_rh',
                'order' => $order,
                'level_name' => $level,
                'role_label' => $level,
                'approver_type' => $type,
                'assignee_id' => $userId,
            ]);
        }
    }

    private function attachPerm(User $user, string $key): void
    {
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id,
        );
    }

    private function payableInFinanceStep(): Payable
    {
        $payable = Payable::create([
            'title_number' => 'PRI-1',
            'supplier_name' => 'Fornecedor',
            'amount' => 500,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'aguardando_aprovacao',
        ]);

        PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $this->gerente->id,
            'name' => 'boleto.pdf',
            'doc_type' => 'boleto',
            'path' => 'payables/docs/test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        ApprovalStep::create([
            'payable_id' => $payable->id,
            'order' => 1,
            'level_name' => 'departamento',
            'role_label' => 'Departamento',
            'approver_type' => ApprovalTrail::TYPE_GESTOR_DEPTO,
            'assigned_to' => $this->gerente->id,
            'status' => 'aprovado',
            'resolved_by' => $this->gerente->id,
            'resolved_at' => now(),
        ]);
        ApprovalStep::create([
            'payable_id' => $payable->id,
            'order' => 2,
            'level_name' => 'financeiro',
            'role_label' => 'Financeiro',
            'approver_type' => ApprovalTrail::TYPE_DEPT_FINANCEIRO,
            'status' => 'pendente',
        ]);

        return $payable->fresh();
    }

    public function test_finance_approval_requires_priority(): void
    {
        $payable = $this->payableInFinanceStep();

        $this->actingAs($this->financeiro)
            ->post("/financeiro/contas-pagar/{$payable->id}/aprovar", [])
            ->assertSessionHasErrors(['payment_priority']);

        $payable->refresh();
        $this->assertNull($payable->payment_priority);
    }

    public function test_finance_approval_sets_priority_and_sla(): void
    {
        $payable = $this->payableInFinanceStep();
        $sla = now()->addDays(5)->toDateString();

        $this->actingAs($this->financeiro)
            ->post("/financeiro/contas-pagar/{$payable->id}/aprovar", [
                'payment_priority' => 'urgente',
                'payment_sla_date' => $sla,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $payable->refresh();
        $this->assertSame('urgente', $payable->payment_priority);
        $this->assertSame($sla, $payable->payment_sla_date->toDateString());
        $this->assertSame($this->financeiro->id, $payable->priority_set_by);
        $this->assertNotNull($payable->priority_set_at);
    }

    public function test_rejection_keeps_priority(): void
    {
        $payable = $this->payableInFinanceStep();
        $payable->update([
            'payment_priority' => 'alta',
            'payment_sla_date' => now()->addDays(3)->toDateString(),
            'priority_set_by' => $this->financeiro->id,
            'priority_set_at' => now(),
        ]);

        $this->actingAs($this->financeiro)
            ->post("/financeiro/contas-pagar/{$payable->id}/reprovar", [
                'reason' => 'Corrigir valor',
            ]);

        $payable->refresh();
        $this->assertSame('pendente', $payable->status);
        $this->assertSame('alta', $payable->payment_priority);
    }

    public function test_manage_priority_permission_updates_anytime(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->attachPerm($user, 'financeiro.contas_pagar.visualizar');
        $this->attachPerm($user, 'financeiro.contas_pagar.prioridade_gerenciar');

        $payable = Payable::create([
            'title_number' => 'PRI-2',
            'supplier_name' => 'Fornecedor',
            'amount' => 200,
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'aprovado',
        ]);

        $sla = now()->addDays(2)->toDateString();

        $this->actingAs($user)
            ->post("/financeiro/contas-pagar/{$payable->id}/prioridade", [
                'payment_priority' => 'alta',
                'payment_sla_date' => $sla,
            ])
            ->assertSessionHasNoErrors();

        $payable->refresh();
        $this->assertSame('alta', $payable->payment_priority);
        $this->assertSame($sla, $payable->payment_sla_date->toDateString());
    }

    public function test_sla_status_resolution(): void
    {
        $overdue = Payable::create([
            'title_number' => 'SLA-1',
            'supplier_name' => 'X',
            'amount' => 10,
            'due_date' => now()->toDateString(),
            'status' => 'aprovado',
            'payment_sla_date' => now()->subDay()->toDateString(),
        ]);

        $warning = Payable::create([
            'title_number' => 'SLA-2',
            'supplier_name' => 'Y',
            'amount' => 10,
            'due_date' => now()->toDateString(),
            'status' => 'aprovado',
            'payment_sla_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->assertSame('overdue', Payable::resolveSlaStatus($overdue));
        $this->assertSame('warning', Payable::resolveSlaStatus($warning));
    }
}
