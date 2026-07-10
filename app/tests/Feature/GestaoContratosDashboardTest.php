<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use App\Models\v2\BsGestaoContrato;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o endpoint /v2/gestao-contratos/dashboard, com foco no bug do KPI
 * "Comprometido/mês" que exibia NaN: no PostgreSQL o sum() de coluna decimal
 * retornava string, e o frontend concatenava em vez de somar. O controller
 * passou a fazer cast (float), então a API precisa devolver NÚMERO (não string).
 */
class GestaoContratosDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function userComPermissao(array $keys = ['contratos.visualizar']): User
    {
        $user = User::factory()->create();
        foreach ($keys as $key) {
            $perm = Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'contratos']);
            $user->permissions()->attach($perm->id);
        }

        return $user;
    }

    private function criarContrato(string $tipo, float $valor, string $status = 'ATIVO'): BsGestaoContrato
    {
        return BsGestaoContrato::create([
            'tipo' => $tipo,
            'status' => $status,
            'valor_mensal' => $valor,
            'data_inicio' => now()->subMonths(2),
            'data_fim' => now()->addYear(),
        ]);
    }

    public function test_dashboard_exige_permissao(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/v2/gestao-contratos/dashboard')->assertStatus(403);
    }

    public function test_dashboard_retorna_valores_como_numero_e_nao_string(): void
    {
        $user = $this->userComPermissao();

        $this->criarContrato('LOCACAO', 12345.67);
        $this->criarContrato('SERVICO', 8901.23);

        $response = $this->actingAs($user)->getJson('/v2/gestao-contratos/dashboard');

        $response->assertOk()->assertJsonPath('sucesso', true);

        $contratos = $response->json('dados.contratos');

        // O cerne da correção: precisa ser numérico (float/int), NUNCA string.
        // Se voltar string, no frontend o "+" concatena e vira NaN.
        $this->assertIsNumeric($contratos['valor_total_locacao']);
        $this->assertIsNumeric($contratos['valor_total_servico']);
        $this->assertIsNotString($contratos['valor_total_locacao']);
        $this->assertIsNotString($contratos['valor_total_servico']);

        $this->assertEqualsWithDelta(12345.67, $contratos['valor_total_locacao'], 0.001);
        $this->assertEqualsWithDelta(8901.23, $contratos['valor_total_servico'], 0.001);

        // KPI "Comprometido/mês" vem pronto do backend (evita soma no frontend com strings).
        $this->assertArrayHasKey('valor_comprometido_mes', $contratos);
        $this->assertIsNumeric($contratos['valor_comprometido_mes']);
        $this->assertIsNotString($contratos['valor_comprometido_mes']);
        $this->assertEqualsWithDelta(21246.90, $contratos['valor_comprometido_mes'], 0.001);
        $this->assertFalse(is_nan((float) $contratos['valor_comprometido_mes']));

        // A soma dos dois (o que o KPI mostra) tem que ser um número válido.
        $soma = $contratos['valor_total_locacao'] + $contratos['valor_total_servico'];
        $this->assertEqualsWithDelta(21246.90, $soma, 0.001);
        $this->assertFalse(is_nan((float) $soma));
    }

    public function test_dashboard_soma_apenas_contratos_ativos(): void
    {
        $user = $this->userComPermissao();

        $this->criarContrato('LOCACAO', 1000.00, 'ATIVO');
        $this->criarContrato('LOCACAO', 500.00, 'ATIVO');
        $this->criarContrato('LOCACAO', 9999.00, 'ENCERRADO'); // não conta
        $this->criarContrato('SERVICO', 2000.00, 'ATIVO');

        $response = $this->actingAs($user)->getJson('/v2/gestao-contratos/dashboard');

        $response->assertOk();
        $contratos = $response->json('dados.contratos');

        $this->assertEqualsWithDelta(1500.00, $contratos['valor_total_locacao'], 0.001);
        $this->assertEqualsWithDelta(2000.00, $contratos['valor_total_servico'], 0.001);
        $this->assertSame(2, $contratos['total_locacao']);
        $this->assertSame(1, $contratos['total_servico']);
    }

    public function test_dashboard_sem_contratos_retorna_zero(): void
    {
        $user = $this->userComPermissao();

        $response = $this->actingAs($user)->getJson('/v2/gestao-contratos/dashboard');

        $response->assertOk();
        $contratos = $response->json('dados.contratos');

        // Sem linhas, sum() = null -> cast (float) => 0.0 (número, não null/string).
        $this->assertIsNumeric($contratos['valor_total_locacao']);
        $this->assertIsNumeric($contratos['valor_total_servico']);
        $this->assertEqualsWithDelta(0, $contratos['valor_total_locacao'], 0.001);
        $this->assertEqualsWithDelta(0, $contratos['valor_total_servico'], 0.001);
    }
}
