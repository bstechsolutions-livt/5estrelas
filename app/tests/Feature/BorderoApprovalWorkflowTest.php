<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\ApprovalTrail;
use App\Models\Bordero;
use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableDocument;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BorderoApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;

    private User $gerente;

    private User $gestorDept;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->gestorDept = User::factory()->create(['name' => 'Gestor Compras', 'is_active' => true]);
        $this->gestorDept->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Visualizar', 'module' => 'financeiro']
            )->id
        );
        $this->gerente = User::factory()->create(['name' => 'Gerente Borderô', 'is_active' => true]);
        $this->gerente->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Visualizar', 'module' => 'financeiro']
            )->id
        );
        $this->department = Department::create([
            'name' => 'Compras Matriz',
            'is_active' => true,
        ]);
        $this->department->forceFill([
            'area_key' => 'matriz',
            'manager_id' => $this->gestorDept->id,
        ])->save();

        $this->sender = $this->userWithPerm(['financeiro.contas_pagar.visualizar', 'financeiro.contas_pagar.preparar']);
        $this->sender->forceFill(['department_id' => $this->department->id])->save();

        $financeiro = User::factory()->create(['name' => 'Financeiro', 'is_active' => true]);
        $diretor = User::factory()->create(['name' => 'Diretor', 'is_active' => true]);
        $presidente = User::factory()->create(['name' => 'Presidente', 'is_active' => true]);

        foreach ([
            [1, 'departamento', 'Departamento', null],
            [2, 'gerencia', 'Gerência', $this->gerente->id],
            [3, 'diretoria', 'Diretoria', $diretor->id],
            [4, 'financeiro', 'Financeiro', $financeiro->id],
            [5, 'presidencia', 'Presidência', $presidente->id],
        ] as [$order, $level, $label, $userId]) {
            ApprovalTrail::create([
                'area' => 'matriz',
                'order' => $order,
                'level_name' => $level,
                'role_label' => $label,
                'default_user_id' => $userId,
            ]);
        }
    }

    private function userWithPerm(array $keys): User
    {
        $user = User::factory()->create();
        foreach ($keys as $key) {
            $perm = Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro']);
            $user->permissions()->attach($perm->id);
        }

        return $user;
    }

    private function payableComDocumento(): Payable
    {
        $payable = Payable::create([
            'title_number' => 'BOR-' . uniqid(),
            'supplier_name' => 'Fornecedor Borderô',
            'amount' => 1500.00,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pendente',
        ]);

        PayableDocument::create([
            'payable_id' => $payable->id,
            'name' => 'nf.pdf',
            'path' => 'payables/test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $this->sender->id,
        ]);

        return $payable;
    }

    private function borderoRascunho(array $payableIds): Bordero
    {
        $bordero = Bordero::create([
            'number' => Bordero::generateNumber(),
            'status' => 'rascunho',
            'created_by' => $this->sender->id,
        ]);

        Payable::whereIn('id', $payableIds)->update(['bordero_id' => $bordero->id]);
        $bordero->recalculate();

        return $bordero;
    }

    public function test_enviar_bordero_cria_steps_de_aprovacao_para_cada_titulo(): void
    {
        $p1 = $this->payableComDocumento();
        $p2 = $this->payableComDocumento();
        $bordero = $this->borderoRascunho([$p1->id, $p2->id]);

        $response = $this->actingAs($this->sender)->post(
            "/financeiro/borderos/{$bordero->id}/enviar-aprovacao"
        );

        $response->assertRedirect();
        $bordero->refresh();
        $this->assertSame('aguardando_aprovacao', $bordero->status);

        foreach ([$p1->id, $p2->id] as $payableId) {
            $payable = Payable::find($payableId);
            $this->assertSame('aguardando_aprovacao', $payable->status);
            $this->assertSame($this->department->id, $payable->department_id);
            $this->assertGreaterThan(0, ApprovalStep::where('payable_id', $payableId)->count());
        }
    }

    public function test_enviar_bordero_exige_documento_em_todos_titulos(): void
    {
        $comDoc = $this->payableComDocumento();
        $semDoc = Payable::create([
            'title_number' => 'SEM-DOC-' . uniqid(),
            'supplier_name' => 'Sem anexo',
            'amount' => 500,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
        ]);
        $bordero = $this->borderoRascunho([$comDoc->id, $semDoc->id]);

        $response = $this->actingAs($this->sender)->post(
            "/financeiro/borderos/{$bordero->id}/enviar-aprovacao"
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $bordero->refresh();
        $this->assertSame('rascunho', $bordero->status);
    }

    public function test_aprovar_bordero_avanca_etapa_apenas_para_aprovador_correto(): void
    {
        $p1 = $this->payableComDocumento();
        $p2 = $this->payableComDocumento();
        $bordero = $this->borderoRascunho([$p1->id, $p2->id]);

        $this->actingAs($this->sender)->post(
            "/financeiro/borderos/{$bordero->id}/enviar-aprovacao"
        );

        // Usuário com acesso ao módulo, mas que não é o aprovador da etapa atual
        $outro = User::factory()->create();
        $outro->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Visualizar', 'module' => 'financeiro']
            )->id
        );
        $denied = $this->actingAs($outro)->post("/financeiro/borderos/{$bordero->id}/aprovar");
        $denied->assertRedirect();
        $denied->assertSessionHas('error');

        // 1ª etapa (departamento): gestor do departamento
        $this->actingAs($this->gestorDept)->post("/financeiro/borderos/{$bordero->id}/aprovar")->assertSessionHas('success');

        // 2ª etapa (gerência): gerente aprova os dois títulos
        $ok = $this->actingAs($this->gerente)->post("/financeiro/borderos/{$bordero->id}/aprovar");
        $ok->assertRedirect();
        $ok->assertSessionHas('success');

        $p1->refresh();
        $p2->refresh();
        $this->assertSame('aguardando_aprovacao', $p1->status);
        $this->assertSame('aguardando_aprovacao', $p2->status);

        $bordero->refresh();
        $this->assertSame('aguardando_aprovacao', $bordero->status);
    }

    public function test_bordero_fica_aprovado_quando_todos_titulos_concluem_fluxo(): void
    {
        $payable = $this->payableComDocumento();
        $bordero = $this->borderoRascunho([$payable->id]);

        $this->actingAs($this->sender)->post(
            "/financeiro/borderos/{$bordero->id}/enviar-aprovacao"
        );

        $admin = $this->userWithPerm(['*']);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($admin)->post("/financeiro/borderos/{$bordero->id}/aprovar");
            $response->assertRedirect();
            $response->assertSessionHas('success');
        }

        $payable->refresh();
        $bordero->refresh();
        $this->assertSame('aprovado', $payable->status);
        $this->assertSame('aprovado', $bordero->status);
    }

    public function test_reprovar_bordero_devolve_titulos_para_pendente_e_bordero_para_rascunho(): void
    {
        $p1 = $this->payableComDocumento();
        $p2 = $this->payableComDocumento();
        $bordero = $this->borderoRascunho([$p1->id, $p2->id]);

        $this->actingAs($this->sender)->post(
            "/financeiro/borderos/{$bordero->id}/enviar-aprovacao"
        );

        $admin = $this->userWithPerm(['*', 'financeiro.contas_pagar.visualizar']);
        $response = $this->actingAs($admin)->post("/financeiro/borderos/{$bordero->id}/reprovar", [
            'reason' => 'Documentação incompleta no borderô',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $p1->refresh();
        $p2->refresh();
        $bordero->refresh();

        $this->assertSame('pendente', $p1->status);
        $this->assertSame('pendente', $p2->status);
        $this->assertSame('Documentação incompleta no borderô', $p1->rejection_reason);
        $this->assertSame('rascunho', $bordero->status);
        $this->assertSame('Documentação incompleta no borderô', $bordero->rejection_reason);
    }

    public function test_enviar_bordero_bloqueia_sem_departamento_no_usuario(): void
    {
        $semDept = $this->userWithPerm(['financeiro.contas_pagar.visualizar', 'financeiro.contas_pagar.preparar']);
        $p1 = $this->payableComDocumento();
        $bordero = $this->borderoRascunho([$p1->id]);

        $response = $this->actingAs($semDept)->post(
            "/financeiro/borderos/{$bordero->id}/enviar-aprovacao"
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $bordero->refresh();
        $this->assertSame('rascunho', $bordero->status);
    }

    public function test_enviar_bordero_bloqueia_sem_gestor_no_departamento(): void
    {
        $this->department->forceFill(['manager_id' => null])->save();
        $p1 = $this->payableComDocumento();
        $bordero = $this->borderoRascunho([$p1->id]);

        $response = $this->actingAs($this->sender)->post(
            "/financeiro/borderos/{$bordero->id}/enviar-aprovacao"
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $bordero->refresh();
        $this->assertSame('rascunho', $bordero->status);
    }
}
