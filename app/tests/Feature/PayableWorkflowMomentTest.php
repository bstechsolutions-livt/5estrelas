<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableWorkflowMomentTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach (['financeiro.contas_pagar.visualizar', 'financeiro.contas_pagar.ver_todas_filiais'] as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id
            );
        }

        return $user;
    }

    private function makePayable(array $attrs = []): Payable
    {
        return Payable::create(array_merge([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 1000.00,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
        ], $attrs));
    }

    public function test_attach_workflow_moment_for_aguardando_aprovacao_uses_current_step(): void
    {
        $assignee = User::factory()->create(['name' => 'Gerente Financeiro', 'is_active' => true]);
        $payable = $this->makePayable(['status' => 'aguardando_aprovacao', 'supplier_name' => 'FornecedorAprov']);

        ApprovalStep::create([
            'payable_id' => $payable->id,
            'order' => 1,
            'level_name' => 'departamento',
            'role_label' => 'Departamento',
            'status' => 'aprovado',
            'assigned_to' => $assignee->id,
        ]);
        ApprovalStep::create([
            'payable_id' => $payable->id,
            'order' => 2,
            'level_name' => 'financeiro',
            'role_label' => 'Financeiro',
            'status' => 'pendente',
            'assigned_to' => $assignee->id,
        ]);

        $resp = $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=aguardando_aprovacao')
            ->assertOk();

        $row = collect($resp->json('data'))->firstWhere('supplier_name', 'FornecedorAprov');

        $this->assertNotNull($row);
        $this->assertSame('Gerente Financeiro', $row['workflow_moment']);
        $this->assertSame('Financeiro', $row['workflow_moment_detail']);
        $this->assertSame('warn', $row['workflow_moment_tone']);
    }

    public function test_attach_workflow_moment_pendente_aguardando_envio(): void
    {
        $this->makePayable(['supplier_name' => 'FornecedorPendente']);

        $resp = $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk();

        $row = collect($resp->json('data'))->firstWhere('supplier_name', 'FornecedorPendente');

        $this->assertSame('Aguardando envio', $row['workflow_moment']);
        $this->assertNull($row['workflow_moment_detail']);
        $this->assertSame('warn', $row['workflow_moment_tone']);
    }

    public function test_attach_workflow_moment_pendente_recusado(): void
    {
        $this->makePayable([
            'supplier_name' => 'FornecedorRecusado',
            'rejection_reason' => 'Documento inválido',
        ]);

        $resp = $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk();

        $row = collect($resp->json('data'))->firstWhere('supplier_name', 'FornecedorRecusado');

        $this->assertSame('Recusado — corrigir', $row['workflow_moment']);
        $this->assertSame('danger', $row['workflow_moment_tone']);
    }

    public function test_attach_workflow_moment_aprovado_aguardando_pagamento(): void
    {
        $this->makePayable(['status' => 'aprovado', 'supplier_name' => 'FornecedorAprovado']);

        $resp = $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=aprovado')
            ->assertOk();

        $row = collect($resp->json('data'))->firstWhere('supplier_name', 'FornecedorAprovado');

        $this->assertSame('Aguardando pagamento', $row['workflow_moment']);
        $this->assertSame('success', $row['workflow_moment_tone']);
    }

    public function test_attach_workflow_moment_without_step_shows_missing_flow(): void
    {
        $this->makePayable(['status' => 'aguardando_aprovacao', 'supplier_name' => 'FornecedorSemFluxo']);

        $resp = $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=aguardando_aprovacao')
            ->assertOk();

        $row = collect($resp->json('data'))->firstWhere('supplier_name', 'FornecedorSemFluxo');

        $this->assertSame('Fluxo não iniciado', $row['workflow_moment']);
        $this->assertSame('Etapa de aprovação ausente', $row['workflow_moment_detail']);
    }

    public function test_lista_ordenada_por_aprovador(): void
    {
        $assigneeA = User::factory()->create(['name' => 'Ana Aprovadora', 'is_active' => true]);
        $assigneeZ = User::factory()->create(['name' => 'Zeca Aprovador', 'is_active' => true]);

        $payableA = $this->makePayable(['status' => 'aguardando_aprovacao', 'supplier_name' => 'FornecedorA']);
        $payableZ = $this->makePayable(['status' => 'aguardando_aprovacao', 'supplier_name' => 'FornecedorZ']);
        $payableSem = $this->makePayable(['status' => 'aguardando_aprovacao', 'supplier_name' => 'FornecedorSem']);

        foreach ([$payableA, $payableZ, $payableSem] as $payable) {
            ApprovalStep::create([
                'payable_id' => $payable->id,
                'order' => 1,
                'level_name' => 'financeiro',
                'role_label' => 'Financeiro',
                'status' => 'pendente',
                'assigned_to' => match ($payable->supplier_name) {
                    'FornecedorA' => $assigneeA->id,
                    'FornecedorZ' => $assigneeZ->id,
                    default => null,
                },
            ]);
        }

        ApprovalStep::where('payable_id', $payableSem->id)->update([
            'role_label' => 'Financeiro (auditoria)',
        ]);

        $resp = $this->actingAs($this->activeUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=aguardando_aprovacao&sort=workflow_moment&dir=asc')
            ->assertOk();

        $names = collect($resp->json('data'))
            ->pluck('workflow_moment')
            ->filter()
            ->values()
            ->all();

        $this->assertSame(['Ana Aprovadora', 'Financeiro (auditoria)', 'Zeca Aprovador'], $names);
    }
}
