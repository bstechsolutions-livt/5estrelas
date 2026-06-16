<?php

namespace Tests\Feature;

use App\Models\Comercial\Cliente;
use App\Models\Comercial\SaudeLancamento;
use App\Models\Comercial\SaudeMeta;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ComercialSaudeTest extends TestCase
{
    use RefreshDatabase;

    private function userComPermissao(array $keys = ['comercial.visualizar', 'comercial.cotar']): User
    {
        $user = User::factory()->create();
        foreach ($keys as $key) {
            $perm = Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'comercial']);
            $user->permissions()->attach($perm->id);
        }

        return $user;
    }

    private function novoCliente(array $attrs = []): Cliente
    {
        return Cliente::create(array_merge([
            'nome' => 'CLIENTE TESTE SAÚDE',
            'valor_mensal' => 50000.00,
        ], $attrs));
    }

    private function novoLancamento(int $clienteId, array $attrs = []): SaudeLancamento
    {
        return SaudeLancamento::create(array_merge([
            'cliente_id' => $clienteId,
            'mes_ref' => '2026-01',
            'faturamento_real' => 55000.00,
            'custo_folha' => 35000.00,
            'custo_beneficios' => 5000.00,
            'custo_insumos' => 3000.00,
            'inadimplencia' => 0,
        ], $attrs));
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_index_renderiza_com_permissao(): void
    {
        $user = $this->userComPermissao();

        $response = $this->actingAs($user)->get('/comercial/saude');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Comercial/Saude/Index', false)
                ->has('clientes')
                ->where('clienteAtivo', null)
                ->where('lancamentos', [])
        );
    }

    public function test_index_com_cliente_passa_lancamentos_e_metas(): void
    {
        $user = $this->userComPermissao();
        $cliente = $this->novoCliente();
        $this->novoLancamento($cliente->id);
        SaudeMeta::create([
            'cliente_id' => $cliente->id,
            'margem_minima' => 2.5,
            'margem_alvo' => 3.5,
            'max_folha_pct' => 70,
            'inadimplencia_max' => 1000,
        ]);

        $response = $this->actingAs($user)->get("/comercial/saude?cliente={$cliente->id}");

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Comercial/Saude/Index', false)
                ->where('clienteAtivo.id', $cliente->id)
                ->where('clienteAtivo.nome', 'CLIENTE TESTE SAÚDE')
                ->has('lancamentos', 1)
                ->where('lancamentos.0.mes_ref', '2026-01')
                ->where('lancamentos.0.faturamento_real', 55000)
                ->where('metas.margem_alvo', '3.50')
        );
    }

    // ─── Store Lancamento ─────────────────────────────────────────────────────

    public function test_store_lancamento_cria(): void
    {
        $user = $this->userComPermissao();
        $cliente = $this->novoCliente();

        $response = $this->actingAs($user)->postJson("/comercial/saude/{$cliente->id}/lancamento", [
            'mes_ref' => '2026-03',
            'faturamento_real' => 60000,
            'custo_folha' => 40000,
            'custo_beneficios' => 4000,
            'custo_insumos' => 2000,
            'inadimplencia' => 500,
            'obs' => 'Teste',
        ]);

        $response->assertOk()->assertJson(['sucesso' => true]);
        $this->assertDatabaseHas('bs_comercial_saude_lancamentos', [
            'cliente_id' => $cliente->id,
            'mes_ref' => '2026-03',
            'faturamento_real' => 60000,
            'custo_folha' => 40000,
        ]);
    }

    public function test_store_lancamento_upsert_por_mes_ref(): void
    {
        $user = $this->userComPermissao();
        $cliente = $this->novoCliente();
        $this->novoLancamento($cliente->id, ['mes_ref' => '2026-02', 'faturamento_real' => 10000]);

        // Mesmo mes_ref → atualiza, não duplica
        $response = $this->actingAs($user)->postJson("/comercial/saude/{$cliente->id}/lancamento", [
            'mes_ref' => '2026-02',
            'faturamento_real' => 99000,
            'custo_folha' => 50000,
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('bs_comercial_saude_lancamentos', 1);
        $this->assertDatabaseHas('bs_comercial_saude_lancamentos', [
            'cliente_id' => $cliente->id,
            'mes_ref' => '2026-02',
            'faturamento_real' => 99000,
        ]);
    }

    public function test_store_lancamento_valida_campos(): void
    {
        $user = $this->userComPermissao();
        $cliente = $this->novoCliente();

        $response = $this->actingAs($user)->postJson("/comercial/saude/{$cliente->id}/lancamento", [
            'mes_ref' => '',
            'faturamento_real' => -10,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mes_ref', 'faturamento_real']);
    }

    // ─── Destroy Lancamento ───────────────────────────────────────────────────

    public function test_destroy_lancamento(): void
    {
        $user = $this->userComPermissao();
        $cliente = $this->novoCliente();
        $lanc = $this->novoLancamento($cliente->id);

        $response = $this->actingAs($user)->deleteJson("/comercial/saude/{$cliente->id}/lancamento/{$lanc->id}");

        $response->assertOk()->assertJson(['sucesso' => true]);
        $this->assertDatabaseMissing('bs_comercial_saude_lancamentos', ['id' => $lanc->id]);
    }

    // ─── Store Metas ──────────────────────────────────────────────────────────

    public function test_store_metas(): void
    {
        $user = $this->userComPermissao(['comercial.visualizar', 'comercial.configurar']);
        $cliente = $this->novoCliente();

        $response = $this->actingAs($user)->postJson("/comercial/saude/{$cliente->id}/metas", [
            'margem_minima' => 2.0,
            'margem_alvo' => 4.0,
            'max_folha_pct' => 68,
            'inadimplencia_max' => 5000,
        ]);

        $response->assertOk()->assertJson(['sucesso' => true]);
        $this->assertDatabaseHas('bs_comercial_saude_metas', [
            'cliente_id' => $cliente->id,
            'margem_alvo' => 4.0,
            'max_folha_pct' => 68,
        ]);
    }

    // ─── Permissões ───────────────────────────────────────────────────────────

    public function test_index_exige_permissao_visualizar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/comercial/saude')->assertStatus(403);
    }

    public function test_store_lancamento_exige_permissao_cotar(): void
    {
        $user = $this->userComPermissao(['comercial.visualizar']);
        $cliente = $this->novoCliente();

        $this->actingAs($user)->postJson("/comercial/saude/{$cliente->id}/lancamento", [
            'mes_ref' => '2026-05',
            'faturamento_real' => 10000,
        ])->assertStatus(403);
    }

    public function test_destroy_lancamento_exige_permissao_cotar(): void
    {
        $user = $this->userComPermissao(['comercial.visualizar']);
        $cliente = $this->novoCliente();
        $lanc = $this->novoLancamento($cliente->id);

        $this->actingAs($user)->deleteJson("/comercial/saude/{$cliente->id}/lancamento/{$lanc->id}")
            ->assertStatus(403);
        $this->assertDatabaseHas('bs_comercial_saude_lancamentos', ['id' => $lanc->id]);
    }

    public function test_store_metas_exige_permissao_configurar(): void
    {
        $user = $this->userComPermissao(['comercial.visualizar', 'comercial.cotar']);
        $cliente = $this->novoCliente();

        $this->actingAs($user)->postJson("/comercial/saude/{$cliente->id}/metas", [
            'margem_alvo' => 5,
        ])->assertStatus(403);
    }
}
