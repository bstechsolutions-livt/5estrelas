<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use App\Services\Senior\PayableMapper;
use App\Services\Senior\PayablesSyncService;
use App\Services\Senior\SeniorCpClient;
use App\Services\Senior\StatusMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableDepartmentSyncAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Department $compras;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::factory()->create(['name' => 'Bruno Sync', 'is_active' => true]);
        $this->compras = Department::create(['name' => 'Compras', 'slug' => 'compras', 'is_active' => true]);
        Department::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'is_active' => true]);

        $this->attachPerm($this->operator, 'financeiro.contas_pagar.visualizar');
        $this->attachPerm($this->operator, 'financeiro.contas_pagar.vincular_departamento_sync');
        $this->attachPerm($this->operator, 'financeiro.ver_todos_departamentos');
        $this->attachPerm($this->operator, 'financeiro.contas_pagar.ver_todas_filiais');
    }

    private function attachPerm(User $user, string $key): void
    {
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id,
        );
    }

    private function awaitingPayable(): Payable
    {
        return Payable::create([
            'title_number' => 'SYNC-DEPT-1',
            'supplier_name' => 'Fornecedor 123',
            'amount' => 100,
            'due_date' => '2026-10-13',
            'status' => Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO,
            'senior_id' => '2-1-T-SYNC-01-1',
        ]);
    }

    public function test_awaiting_tab_hidden_without_permission(): void
    {
        $viewer = User::factory()->create(['is_active' => true]);
        $this->attachPerm($viewer, 'financeiro.contas_pagar.visualizar');
        $this->attachPerm($viewer, 'financeiro.ver_todos_departamentos');
        $this->attachPerm($viewer, 'financeiro.contas_pagar.ver_todas_filiais');

        $this->actingAs($viewer)
            ->get('/financeiro/contas-pagar?status=aguardando_vinculo_departamento')
            ->assertRedirect('/financeiro/contas-pagar?status=pendente');
    }

    public function test_operator_can_assign_department_on_awaiting_title(): void
    {
        $payable = $this->awaitingPayable();

        $this->actingAs($this->operator)
            ->post("/financeiro/contas-pagar/{$payable->id}/departamento-sync", [
                'department_id' => $this->compras->id,
            ])
            ->assertRedirect();

        $fresh = $payable->fresh();
        $this->assertSame($this->compras->id, (int) $fresh->department_id);
        $this->assertSame($this->operator->id, (int) $fresh->department_assigned_by);
        $this->assertNotNull($fresh->department_assigned_at);
        $this->assertSame(Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO, $fresh->status);
    }

    private function syncService(): PayablesSyncService
    {
        return new PayablesSyncService(
            new class extends SeniorCpClient {
                public function __construct()
                {
                    parent::__construct(config('senior'));
                }
            },
            new PayableMapper(),
            new StatusMapper(),
        );
    }

    public function test_manual_department_is_not_overwritten_by_sync(): void
    {
        $payable = $this->awaitingPayable();
        $payable->update([
            'department_id' => $this->compras->id,
            'department_assigned_by' => $this->operator->id,
            'department_assigned_at' => now(),
        ]);

        User::factory()->create([
            'is_active' => true,
            'senior_cod_usu' => 999,
            'department_id' => Department::where('slug', 'financeiro')->value('id'),
        ]);
        $payable->update(['senior_cod_usu' => 999]);

        $this->syncService()->applyPostSyncReadiness($payable->fresh());

        $fresh = $payable->fresh();
        $this->assertSame($this->compras->id, (int) $fresh->department_id);
        $this->assertSame($this->operator->id, (int) $fresh->department_assigned_by);
    }

    public function test_cannot_reassign_after_manual_assignment(): void
    {
        $payable = $this->awaitingPayable();
        $payable->update([
            'department_id' => $this->compras->id,
            'department_assigned_by' => $this->operator->id,
            'department_assigned_at' => now(),
        ]);

        $outro = Department::create(['name' => 'Marketing', 'slug' => 'marketing', 'is_active' => true]);

        $this->actingAs($this->operator)
            ->from('/financeiro/contas-pagar?status=aguardando_vinculo_departamento')
            ->post("/financeiro/contas-pagar/{$payable->id}/departamento-sync", [
                'department_id' => $outro->id,
            ])
            ->assertRedirect('/financeiro/contas-pagar?status=aguardando_vinculo_departamento')
            ->assertSessionHas('error');

        $this->assertSame($this->compras->id, (int) $payable->fresh()->department_id);
    }
}
