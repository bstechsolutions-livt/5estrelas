<?php

namespace Tests\Feature;

use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableDocument;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableBatchActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ApprovalTrail::create([
            'area' => 'matriz',
            'order' => 1,
            'level_name' => 'departamento',
            'role_label' => 'Departamento',
            'default_user_id' => null,
        ]);
    }

    private function sender(): User
    {
        $manager = User::factory()->create(['is_active' => true]);
        $dept = Department::create(['name' => 'Compras', 'is_active' => true]);
        $dept->forceFill(['area_key' => 'matriz', 'manager_id' => $manager->id])->save();

        $user = User::factory()->create(['is_active' => true, 'department_id' => $dept->id]);
        foreach ([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.ver_todos_departamentos',
            'financeiro.contas_pagar.ver_todas_filiais',
        ] as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id,
            );
        }

        return $user;
    }

    private function makePayable(array $attrs = []): Payable
    {
        return Payable::create(array_merge([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 500,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pendente',
        ], $attrs));
    }

    public function test_enviar_aprovacao_em_lote(): void
    {
        $user = $this->sender();

        $withDoc = $this->makePayable();
        PayableDocument::create([
            'payable_id' => $withDoc->id,
            'uploaded_by' => $user->id,
            'name' => 'nf.pdf',
            'doc_type' => 'nota_fiscal',
            'path' => 'payables/docs/nf.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);
        $noDoc = $this->makePayable();

        $this->actingAs($user)
            ->post('/financeiro/contas-pagar/lote/enviar-aprovacao', [
                'payable_ids' => [$withDoc->id, $noDoc->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('aguardando_aprovacao', $withDoc->fresh()->status);
        $this->assertSame('pendente', $noDoc->fresh()->status);
    }

    public function test_aprovar_em_lote_sem_titulos_elegiveis(): void
    {
        $payable = $this->makePayable(['status' => 'pendente']);

        $this->actingAs($this->sender())
            ->post('/financeiro/contas-pagar/lote/aprovar', [
                'payable_ids' => [$payable->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
