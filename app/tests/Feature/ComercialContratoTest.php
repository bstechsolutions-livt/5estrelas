<?php

namespace Tests\Feature;

use App\Models\Comercial\Cliente;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ComercialContratoTest extends TestCase
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

    public function test_index_renderiza_com_contratos_ativos(): void
    {
        $user = $this->userComPermissao();

        Cliente::create(['nome' => 'SESI', 'situacao' => 'ativo', 'valor_mensal' => 800000, 'total_postos' => 5, 'postos' => [['tipo' => 'Portaria', 'escala' => 'Mensal', 'qtd' => 3, 'colab' => 6, 'valor' => 300000]]]);
        Cliente::create(['nome' => 'Inativo', 'situacao' => 'inativo', 'valor_mensal' => 1000]);

        $response = $this->actingAs($user)->get('/comercial/contratos');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Comercial/Contratos/Index', false)
                ->has('contratos', 1) // só ativos
                ->where('contratos.0.cliente', 'SESI')
                ->where('contratos.0.custo_mensal', 800000)
                ->where('contratos.0.postos', 3) // derivado dos postos JSON
                ->where('contratos.0.servico', 'Portaria')
        );
    }

    public function test_index_exige_permissao_visualizar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/comercial/contratos')->assertStatus(403);
    }
}
