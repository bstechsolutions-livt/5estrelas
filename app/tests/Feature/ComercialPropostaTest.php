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

    private function userComPermissao(): User
    {
        $user = User::factory()->create();

        foreach (['comercial.visualizar', 'comercial.cotar'] as $key) {
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
            ->assertJson(['sucesso' => true, 'numero' => 'PRP-0001']);

        $this->assertDatabaseHas('bs_comercial_propostas', [
            'numero' => 'PRP-0001',
            'cliente' => 'Condomínio Teste',
            'modelo' => '5estrelas',
            'status' => 'rascunho',
            'created_by' => $user->id,
        ]);
    }

    public function test_numeracao_incrementa_a_partir_da_ultima(): void
    {
        $user = $this->userComPermissao();

        Proposta::create([
            'numero' => 'PRP-0132',
            'modelo' => 'in05',
            'postos' => [['id' => 1]],
        ]);

        $this->assertEquals('PRP-0133', Proposta::gerarNumero());
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
}
