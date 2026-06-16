<?php

namespace Tests\Feature;

use App\Models\Comercial\Cliente;
use App\Models\Comercial\Proposta;
use App\Models\Comercial\Reajuste;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ComercialDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function userComPermissao(array $keys = ['comercial.visualizar']): User
    {
        $user = User::factory()->create();
        foreach ($keys as $key) {
            $perm = Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'comercial']);
            $user->permissions()->attach($perm->id);
        }

        return $user;
    }

    public function test_index_renderiza_kpis(): void
    {
        $user = $this->userComPermissao();

        Cliente::create(['nome' => 'SESI', 'situacao' => 'ativo', 'valor_mensal' => 50000, 'uf' => 'DF']);
        Cliente::create(['nome' => 'Inativo', 'situacao' => 'inativo', 'valor_mensal' => 1000]);
        Proposta::create(['numero' => 'Nº 900', 'modelo' => 'manual', 'situacao' => 'EM ANÁLISE', 'valor' => 10000, 'postos' => [['id' => 1]]]);
        Proposta::create(['numero' => 'Nº 901', 'modelo' => 'manual', 'situacao' => 'APROVADO', 'valor' => 5000, 'empresa' => 'seg-df', 'postos' => [['id' => 1]]]);
        Reajuste::create(['cliente_nome' => 'X', 'status' => 'pendente', 'pct' => 5, 'valor_atual' => 1000, 'impacto_mensal' => 50]);

        $response = $this->actingAs($user)->get('/comercial/dashboard');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Comercial/Dashboard/Index', false)
                ->where('kpis.clientes_ativos', 1) // só ativos
                ->where('kpis.faturamento_mensal', 50000)
                ->where('kpis.propostas_analise', 1)
                ->where('kpis.reajustes_pendentes', 1)
                ->where('kpis.taxa_aprovacao', 50) // 1 de 2
                ->has('topClientes', 1)
                ->has('funil', 4)
                ->has('distribuicao')
                ->has('split.seg')
                ->has('split.apoio')
        );
    }

    public function test_index_exige_permissao_visualizar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/comercial/dashboard')->assertStatus(403);
    }
}
