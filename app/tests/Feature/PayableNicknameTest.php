<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableNicknameTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Ver CP', 'module' => 'financeiro'],
            )->id,
        );
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.ver_todos_departamentos'],
                ['label' => 'Ver todos deptos', 'module' => 'financeiro'],
            )->id,
        );
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.ver_todas_filiais'],
                ['label' => 'Ver todas filiais', 'module' => 'financeiro'],
            )->id,
        );

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

    public function test_atualizar_apelido_no_detalhe(): void
    {
        $payable = $this->makePayable();

        $this->actingAs($this->viewer())
            ->post("/financeiro/contas-pagar/{$payable->id}/apelido", [
                'nickname' => 'Energia jul',
            ])
            ->assertRedirect();

        $this->assertSame('Energia jul', $payable->fresh()->nickname);
    }

    public function test_lista_exibe_apelido(): void
    {
        $payable = $this->makePayable(['nickname' => 'Aluguel filial 2']);
        $this->makePayable(['nickname' => 'Outro']);

        $response = $this->actingAs($this->viewer())
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk();

        $row = collect($response->viewData('page')['props']['payables']['data'])
            ->firstWhere('id', $payable->id);

        $this->assertSame('Aluguel filial 2', $row['nickname']);
    }

    public function test_visao_em_lote_carrega(): void
    {
        $this->makePayable(['nickname' => 'CCU 2363']);

        $response = $this->actingAs($this->viewer())
            ->get('/financeiro/contas-pagar/lote?status=pendente')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('Payables/Batch', $page['component']);
        $this->assertSame('CCU 2363', $page['props']['payables']['data'][0]['nickname']);
    }

    public function test_salvar_apelidos_em_lote(): void
    {
        $a = $this->makePayable();
        $b = $this->makePayable();

        $this->actingAs($this->viewer())
            ->postJson('/financeiro/contas-pagar/lote/apelidos', [
                'items' => [
                    ['id' => $a->id, 'nickname' => 'Titulo A'],
                    ['id' => $b->id, 'nickname' => 'Titulo B'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('updated', 2);

        $this->assertSame('Titulo A', $a->fresh()->nickname);
        $this->assertSame('Titulo B', $b->fresh()->nickname);
    }
}
