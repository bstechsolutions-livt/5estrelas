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
}
