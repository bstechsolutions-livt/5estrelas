<?php

namespace Tests\Feature;

use App\Models\Comercial\Proposta;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComercialPropostaTest extends TestCase
{
    use RefreshDatabase;

    private function userComPermissao(array $keys = ['comercial.visualizar', 'comercial.cotar']): User
    {
        $user = User::factory()->create();

        foreach ($keys as $key) {
            $perm = Permission::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'module' => 'comercial'],
            );
            $user->permissions()->attach($perm->id);
        }

        return $user;
    }

    public function test_salva_proposta_com_payload_valido(): void
    {
        $user = $this->userComPermissao();

        $payload = [
            'cliente' => 'Condomínio Teste',
            'empresa' => 'seg-df',
            'modelo' => '5estrelas',
            'periodicidade' => 'Mensal',
            'cct' => 'SINDESP-DF 2026',
            'data_proposta' => '2026-06-12',
            'total_mensal' => 15000.50,
            'total_anual' => 180006.00,
            'qtd_postos' => 3,
            'qtd_funcionarios' => 6,
            'va_total' => 900.00,
            'postos' => [
                [
                    'id' => 1,
                    'cat' => 'Vigilante',
                    'escala' => '12x36 — Diurno',
                    'funcPosto' => 2,
                    'qtdPostos' => 3,
                    'unitVal' => 5000.17,
                    'totalMensal' => 15000.50,
                    'vaUnit' => 300.00,
                    'modelo' => '5estrelas',
                ],
            ],
            'identificacao' => ['cliente' => 'Condomínio Teste', 'modelo' => '5estrelas'],
        ];

        $response = $this->actingAs($user)->postJson('/comercial/propostas', $payload);

        $response->assertOk()
            ->assertJson(['sucesso' => true, 'numero' => 'Nº 132']);

        $this->assertDatabaseHas('bs_comercial_propostas', [
            'numero' => 'Nº 132',
            'cliente' => 'Condomínio Teste',
            'modelo' => '5estrelas',
            'status' => 'rascunho',
            'created_by' => $user->id,
            // Totais recalculados no backend a partir dos itens (não confiando no front).
            'qtd_postos' => 3,
            'qtd_funcionarios' => 6,
            'total_mensal' => 15000.50,
            'total_anual' => 180006.00,
            'va_total' => 900.00,
        ]);
    }

    public function test_numeracao_incrementa_a_partir_da_ultima(): void
    {
        $user = $this->userComPermissao();

        Proposta::create([
            'numero' => 'Nº 132',
            'modelo' => 'in05',
            'postos' => [['id' => 1]],
        ]);

        $this->assertEquals('Nº 133', Proposta::gerarNumero());
    }

    public function test_rejeita_quando_postos_vazio(): void
    {
        $user = $this->userComPermissao();

        $payload = [
            'cliente' => 'Cliente Sem Postos',
            'modelo' => 'in05',
            'total_mensal' => 0,
            'total_anual' => 0,
            'qtd_postos' => 0,
            'qtd_funcionarios' => 0,
            'postos' => [],
        ];

        $response = $this->actingAs($user)->postJson('/comercial/propostas', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('postos');
    }

    public function test_index_renderiza_lista_para_usuario_com_permissao(): void
    {
        $user = $this->userComPermissao();

        Proposta::create([
            'numero' => 'Nº 132',
            'modelo' => 'manual',
            'cliente' => 'Condomínio Aurora',
            'situacao' => 'EM ANÁLISE',
            'valor' => 12000.00,
            'postos' => [['id' => 1]],
        ]);

        $response = $this->actingAs($user)->get('/comercial/propostas');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                // 2º arg `false`: pula a checagem de existência em disco do Inertia.
                // O config padrão aponta para `js/pages` (minúsculo) mas o projeto usa
                // `js/Pages`; em FS case-sensitive (Linux) a checagem falharia. O nome
                // do componente continua sendo asseverado normalmente.
                ->component('Comercial/Propostas/Index', false)
                ->has('propostas', 1)
                ->where('propostas.0.cliente', 'Condomínio Aurora')
                ->where('propostas.0.situacao', 'EM ANÁLISE')
                ->has('situacaoLabels')
                ->has('filiais')
                ->has('clientes')
        );
    }

    public function test_update_situacao_persiste_e_espelha_aprovacao(): void
    {
        $user = $this->userComPermissao(['comercial.visualizar', 'comercial.aprovar']);

        $proposta = Proposta::create([
            'numero' => 'Nº 140',
            'modelo' => 'manual',
            'cliente' => 'Cliente X',
            'situacao' => 'EM ANÁLISE',
            'valor' => 9000.00,
            'postos' => [['id' => 1]],
        ]);

        $response = $this->actingAs($user)->patchJson("/comercial/propostas/{$proposta->id}/situacao", [
            'situacao' => 'APROVADO',
            'valor_aprovado' => 9000.00,
            'data_aprovacao' => '2026-06-25',
        ]);

        $response->assertOk()->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_propostas', [
            'id' => $proposta->id,
            'situacao' => 'APROVADO',
            'valor_aprovado' => 9000.00,
        ]);
    }

    public function test_update_situacao_rejeita_valor_invalido(): void
    {
        $user = $this->userComPermissao(['comercial.visualizar', 'comercial.aprovar']);

        $proposta = Proposta::create([
            'numero' => 'Nº 141',
            'modelo' => 'manual',
            'situacao' => 'EM ANÁLISE',
            'postos' => [['id' => 1]],
        ]);

        $response = $this->actingAs($user)->patchJson("/comercial/propostas/{$proposta->id}/situacao", [
            'situacao' => 'INEXISTENTE',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('situacao');
    }

    public function test_destroy_exclui_proposta(): void
    {
        $user = $this->userComPermissao();

        $proposta = Proposta::create([
            'numero' => 'Nº 150',
            'modelo' => 'manual',
            'situacao' => 'EM ANÁLISE',
            'postos' => [['id' => 1]],
        ]);

        $response = $this->actingAs($user)->deleteJson("/comercial/propostas/{$proposta->id}");

        $response->assertOk()->assertJson(['sucesso' => true]);
        $this->assertDatabaseMissing('bs_comercial_propostas', ['id' => $proposta->id]);
    }

    // ─── Entrada manual (storeManual) ────────────────────────────────────────────

    public function test_store_manual_cria_proposta(): void
    {
        $user = $this->userComPermissao();

        $payload = [
            'cliente' => 'Cliente Manual',
            'servicos' => 'Portaria',
            'empresa' => 'seg-df',
            'posto' => 'PORT 12H',
            'valor' => 7500.00,
            'contato' => 'comercial@cliente.com',
            'data_proposta' => '2026-06-16',
            'situacao' => 'EM ANÁLISE',
        ];

        $response = $this->actingAs($user)->postJson('/comercial/propostas/manual', $payload);

        $response->assertOk()->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_propostas', [
            'cliente' => 'Cliente Manual',
            'modelo' => 'manual',
            'da_cotacao' => false,
            'valor' => 7500.00,
            'situacao' => 'EM ANÁLISE',
            'created_by' => $user->id,
        ]);
    }

    public function test_store_manual_gera_numero_quando_vazio(): void
    {
        $user = $this->userComPermissao();

        $response = $this->actingAs($user)->postJson('/comercial/propostas/manual', [
            'cliente' => 'Sem Numero',
            'valor' => 1000.00,
            'situacao' => 'EM ANÁLISE',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('numero'));
        $this->assertStringStartsWith('Nº', $response->json('numero'));
    }

    public function test_store_manual_valida_campos_obrigatorios(): void
    {
        $user = $this->userComPermissao();

        // Sem 'valor' (required) e sem 'situacao' (required).
        $response = $this->actingAs($user)->postJson('/comercial/propostas/manual', [
            'cliente' => 'Faltando Campos',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['valor', 'situacao']);
    }

    public function test_store_manual_rejeita_numero_duplicado(): void
    {
        $user = $this->userComPermissao();

        Proposta::create([
            'numero' => 'Nº 200',
            'modelo' => 'manual',
            'situacao' => 'EM ANÁLISE',
            'valor' => 100,
            'postos' => [['id' => 1]],
        ]);

        $response = $this->actingAs($user)->postJson('/comercial/propostas/manual', [
            'numero' => 'Nº 200',
            'cliente' => 'Duplicado',
            'valor' => 500,
            'situacao' => 'EM ANÁLISE',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('numero');
    }

    // ─── Edição (update) ─────────────────────────────────────────────────────────

    public function test_update_edita_proposta(): void
    {
        $user = $this->userComPermissao();

        $proposta = Proposta::create([
            'numero' => 'Nº 210',
            'modelo' => 'manual',
            'cliente' => 'Nome Antigo',
            'situacao' => 'EM ANÁLISE',
            'valor' => 1000.00,
            'postos' => [['id' => 1]],
        ]);

        $response = $this->actingAs($user)->putJson("/comercial/propostas/{$proposta->id}", [
            'numero' => 'Nº 210',
            'cliente' => 'Nome Novo',
            'servicos' => 'Limpeza',
            'valor' => 2500.00,
            'situacao' => 'ESTIMATIVA',
        ]);

        $response->assertOk()->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_propostas', [
            'id' => $proposta->id,
            'cliente' => 'Nome Novo',
            'valor' => 2500.00,
            'situacao' => 'ESTIMATIVA',
        ]);
    }

    public function test_update_permite_manter_o_proprio_numero(): void
    {
        $user = $this->userComPermissao();

        $proposta = Proposta::create([
            'numero' => 'Nº 211',
            'modelo' => 'manual',
            'situacao' => 'EM ANÁLISE',
            'valor' => 1000.00,
            'postos' => [['id' => 1]],
        ]);

        // Reenvia o mesmo número (unique deve ignorar o próprio id).
        $response = $this->actingAs($user)->putJson("/comercial/propostas/{$proposta->id}", [
            'numero' => 'Nº 211',
            'cliente' => 'Mesmo Numero',
            'valor' => 1500.00,
            'situacao' => 'EM ANÁLISE',
        ]);

        $response->assertOk()->assertJson(['sucesso' => true]);
    }

    // ─── Endpoint de dados (JSON) ──────────────────────────────────────────────────

    public function test_dados_retorna_json_com_propostas(): void
    {
        $user = $this->userComPermissao();

        Proposta::create([
            'numero' => 'Nº 220',
            'modelo' => 'manual',
            'cliente' => 'Cliente JSON',
            'situacao' => 'EM ANÁLISE',
            'valor' => 3000.00,
            'postos' => [['id' => 1]],
        ]);

        $response = $this->actingAs($user)->getJson('/comercial/propostas/dados');

        $response->assertOk()
            ->assertJsonStructure(['propostas' => [['id', 'numero', 'cliente', 'situacao', 'valor']]])
            ->assertJsonFragment(['cliente' => 'Cliente JSON']);
    }

    // ─── Permissão (403) ───────────────────────────────────────────────────────────

    public function test_store_exige_permissao_cotar(): void
    {
        // Só visualizar — não pode salvar cotação.
        $user = $this->userComPermissao(['comercial.visualizar']);

        $response = $this->actingAs($user)->postJson('/comercial/propostas', [
            'modelo' => 'in05',
            'postos' => [['id' => 1, 'qtdPostos' => 1, 'totalMensal' => 100]],
        ]);

        $response->assertStatus(403);
    }

    public function test_store_manual_exige_permissao_cotar(): void
    {
        $user = $this->userComPermissao(['comercial.visualizar']);

        $response = $this->actingAs($user)->postJson('/comercial/propostas/manual', [
            'cliente' => 'Sem Permissao',
            'valor' => 100,
            'situacao' => 'EM ANÁLISE',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_exige_permissao_cotar(): void
    {
        $user = $this->userComPermissao(['comercial.visualizar']);

        $proposta = Proposta::create([
            'numero' => 'Nº 230',
            'modelo' => 'manual',
            'situacao' => 'EM ANÁLISE',
            'valor' => 100,
            'postos' => [['id' => 1]],
        ]);

        $response = $this->actingAs($user)->putJson("/comercial/propostas/{$proposta->id}", [
            'cliente' => 'Tentativa',
            'valor' => 200,
            'situacao' => 'EM ANÁLISE',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_situacao_exige_permissao_aprovar(): void
    {
        // Tem cotar mas não aprovar → 403 na rota de situação.
        $user = $this->userComPermissao(['comercial.visualizar', 'comercial.cotar']);

        $proposta = Proposta::create([
            'numero' => 'Nº 240',
            'modelo' => 'manual',
            'situacao' => 'EM ANÁLISE',
            'valor' => 100,
            'postos' => [['id' => 1]],
        ]);

        $response = $this->actingAs($user)->patchJson("/comercial/propostas/{$proposta->id}/situacao", [
            'situacao' => 'APROVADO',
        ]);

        $response->assertStatus(403);
    }

    public function test_destroy_exige_permissao_cotar(): void
    {
        $user = $this->userComPermissao(['comercial.visualizar']);

        $proposta = Proposta::create([
            'numero' => 'Nº 250',
            'modelo' => 'manual',
            'situacao' => 'EM ANÁLISE',
            'valor' => 100,
            'postos' => [['id' => 1]],
        ]);

        $response = $this->actingAs($user)->deleteJson("/comercial/propostas/{$proposta->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('bs_comercial_propostas', ['id' => $proposta->id]);
    }

    public function test_index_exige_permissao_visualizar(): void
    {
        // Usuário sem nenhuma permissão de comercial.
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/comercial/propostas');

        $response->assertStatus(403);
    }
}
