<?php

namespace Tests\Feature;

use App\Models\Comercial\Cliente;
use App\Models\Comercial\Proposta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComercialClienteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria user com permissões necessárias
        $this->user = User::factory()->create();
        $this->user->permissions()->attach(
            \App\Models\Permission::firstOrCreate(
                ['key' => 'comercial.visualizar'],
                ['label' => 'Comercial — Visualizar', 'module' => 'comercial']
            )->id
        );
        $this->user->permissions()->attach(
            \App\Models\Permission::firstOrCreate(
                ['key' => 'comercial.cotar'],
                ['label' => 'Comercial — Cotar', 'module' => 'comercial']
            )->id
        );
    }

    public function test_index_retorna_200_com_componente_inertia(): void
    {
        $this->actingAs($this->user)
            ->get('/comercial/clientes')
            ->assertStatus(200);
    }

    public function test_store_cria_cliente(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/comercial/clientes', [
                'nome' => 'Empresa Teste LTDA',
                'cidade' => 'Brasília',
                'uf' => 'DF',
                'situacao' => 'ativo',
            ]);

        $response->assertOk()->assertJson(['sucesso' => true]);
        $this->assertDatabaseHas('bs_comercial_clientes', ['nome' => 'Empresa Teste LTDA']);
    }

    public function test_show_retorna_200_com_componente_inertia(): void
    {
        $cliente = Cliente::create([
            'nome' => 'Cliente Show Test',
            'situacao' => 'ativo',
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get("/comercial/clientes/{$cliente->id}")
            ->assertStatus(200);
    }

    public function test_vincular_proposta(): void
    {
        $cliente = Cliente::create([
            'nome' => 'Cliente Vincular',
            'situacao' => 'ativo',
            'created_by' => $this->user->id,
        ]);

        $proposta = Proposta::create([
            'numero' => 'Nº 999',
            'modelo' => '5estrelas',
            'total_mensal' => 50000,
            'qtd_postos' => 3,
            'qtd_funcionarios' => 12,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/comercial/clientes/{$cliente->id}/vincular", [
                'proposta_id' => $proposta->id,
            ]);

        $response->assertOk()->assertJson(['sucesso' => true]);

        // Verifica que a proposta foi vinculada
        $this->assertEquals($cliente->id, $proposta->fresh()->cliente_id);

        // Verifica que os totais do cliente foram recalculados
        $cliente->refresh();
        $this->assertEquals(50000, (float) $cliente->valor_mensal);
        $this->assertEquals(3, $cliente->total_postos);
        $this->assertEquals(12, $cliente->total_colaboradores);
    }

    public function test_desvincular_proposta(): void
    {
        $cliente = Cliente::create([
            'nome' => 'Cliente Desvincular',
            'situacao' => 'ativo',
            'valor_mensal' => 50000,
            'total_postos' => 3,
            'total_colaboradores' => 12,
            'created_by' => $this->user->id,
        ]);

        $proposta = Proposta::create([
            'numero' => 'Nº 998',
            'modelo' => 'in05',
            'total_mensal' => 50000,
            'qtd_postos' => 3,
            'qtd_funcionarios' => 12,
            'cliente_id' => $cliente->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/comercial/clientes/{$cliente->id}/desvincular/{$proposta->id}");

        $response->assertOk()->assertJson(['sucesso' => true]);

        // Proposta desvinculada
        $this->assertNull($proposta->fresh()->cliente_id);

        // Totais zerados
        $cliente->refresh();
        $this->assertEquals(0, (float) $cliente->valor_mensal);
    }
}
