<?php

namespace Tests\Feature;

use App\Models\ApprovalStep;
use App\Models\ApprovalTrail;
use App\Models\Bordero;
use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableComment;
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

    private User $gestorDept;

    private User $gerente;

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
        $this->gestorDept->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.borderos.visualizar'],
                ['label' => 'Borderôs', 'module' => 'financeiro']
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

        $this->sender = $this->userWithPerm([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.preparar',
            'financeiro.borderos.visualizar',
        ]);
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
            'title_number' => 'BOR-'.uniqid(),
            'supplier_name' => 'Fornecedor Borderô',
            'amount' => 1500.00,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pendente',
            'department_id' => $this->department->id,
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

    private function borderoPendente(array $payableIds): Bordero
    {
        $bordero = Bordero::create([
            'number' => Bordero::generateNumber(),
            'status' => Bordero::STATUS_PENDENTE,
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
        $bordero = $this->borderoPendente([$p1->id, $p2->id]);

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

    public function test_reprovar_bordero_devolve_pacote_para_pendente_mantendo_titulos(): void
    {
        $p1 = $this->payableComDocumento();
        $p2 = $this->payableComDocumento();
        $bordero = $this->borderoPendente([$p1->id, $p2->id]);

        $this->actingAs($this->sender)->post(
            "/financeiro/borderos/{$bordero->id}/enviar-aprovacao"
        );

        $admin = $this->userWithPerm(['*', 'financeiro.contas_pagar.visualizar', 'financeiro.borderos.visualizar']);
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
        $this->assertSame($bordero->id, $p1->bordero_id);
        $this->assertSame($bordero->id, $p2->bordero_id);
        $this->assertSame('pendente', $bordero->status);
        $this->assertSame('Documentação incompleta no borderô', $bordero->rejection_reason);
        $this->assertGreaterThan(0, PayableComment::where('payable_id', $p1->id)->where('type', 'rejection')->count());
    }

    public function test_expulsar_titulo_remove_do_bordero_para_cp_avulso(): void
    {
        $p1 = $this->payableComDocumento();
        $p2 = $this->payableComDocumento();
        $bordero = $this->borderoPendente([$p1->id, $p2->id]);

        $this->actingAs($this->sender)->post("/financeiro/borderos/{$bordero->id}/enviar-aprovacao");

        $response = $this->actingAs($this->gestorDept)->post(
            "/financeiro/borderos/{$bordero->id}/titulos/{$p1->id}/expulsar",
            ['reason' => 'Título com valor divergente']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $p1->refresh();
        $p2->refresh();
        $bordero->refresh();

        $this->assertNull($p1->bordero_id);
        $this->assertSame('pendente', $p1->status);
        $this->assertSame('Título com valor divergente', $p1->rejection_reason);
        $this->assertSame($bordero->id, $p2->bordero_id);
        $this->assertSame('aguardando_aprovacao', $bordero->status);
        $this->assertSame(1, $bordero->items_count);
    }

    public function test_liberar_titulo_exige_permissao_especial(): void
    {
        $p1 = $this->payableComDocumento();
        $bordero = $this->borderoPendente([$p1->id]);

        $this->actingAs($this->sender)->post("/financeiro/borderos/{$bordero->id}/enviar-aprovacao");

        $denied = $this->actingAs($this->gestorDept)->post(
            "/financeiro/borderos/{$bordero->id}/titulos/{$p1->id}/liberar",
            ['reason' => 'Urgente']
        );
        $denied->assertRedirect();
        $denied->assertSessionHas('error');

        $liberador = $this->userWithPerm([
            'financeiro.contas_pagar.visualizar',
            'financeiro.borderos.visualizar',
            'financeiro.borderos.liberar_titulo',
        ]);

        $ok = $this->actingAs($liberador)->post(
            "/financeiro/borderos/{$bordero->id}/titulos/{$p1->id}/liberar",
            ['reason' => 'Urgente — seguir avulso']
        );
        $ok->assertRedirect();
        $ok->assertSessionHas('success');

        $p1->refresh();
        $this->assertNull($p1->bordero_id);
        $this->assertSame('aguardando_aprovacao', $p1->status);
        $this->assertGreaterThan(0, ApprovalStep::where('payable_id', $p1->id)->count());
    }

    public function test_desfazer_bordero_pendente_libera_titulos(): void
    {
        $p1 = $this->payableComDocumento();
        $bordero = $this->borderoPendente([$p1->id]);

        $user = $this->userWithPerm([
            'financeiro.borderos.visualizar',
            'financeiro.borderos.desfazer',
        ]);

        $response = $this->actingAs($user)->post("/financeiro/borderos/{$bordero->id}/desfazer", [
            'reason' => 'Montagem incorreta',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $p1->refresh();
        $this->assertNull($p1->bordero_id);
        $this->assertNull(Bordero::find($bordero->id));
    }

    public function test_titulo_em_bordero_nao_aparece_em_cp_pendente_avulso(): void
    {
        $p1 = $this->payableComDocumento();
        $this->borderoPendente([$p1->id]);

        $response = $this->actingAs($this->sender)->get('/financeiro/contas-pagar?status=pendente');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('payables.data', 0)
        );
    }
}
