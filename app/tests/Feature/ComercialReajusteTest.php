<?php

namespace Tests\Feature;

use App\Models\Comercial\Reajuste;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ComercialReajusteTest extends TestCase
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

    private function novoReajuste(array $attrs = []): Reajuste
    {
        return Reajuste::create(array_merge([
            'cliente_nome' => 'BINATURAL',
            'empresa' => 'apoio-go',
            'tipo' => 'manual',
            'pct' => 6.0,
            'status' => 'aprovado',
            'valor_atual' => 18750.05,
            'impacto_mensal' => 1125.00,
            'itens' => [['nome' => 'Portaria', 'valorAtual' => 18750.05, 'pct' => 6.0, 'novoValor' => 19875.05, 'variacao' => 1125.0]],
        ], $attrs));
    }

    public function test_index_renderiza_com_permissao(): void
    {
        $user = $this->userComPermissao();
        $this->novoReajuste();

        $response = $this->actingAs($user)->get('/comercial/reajustes');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Comercial/Reajustes/Index', false)
                ->has('reajustes', 1)
                ->where('reajustes.0.cliente_nome', 'BINATURAL')
                ->where('reajustes.0.novo_valor', 19875.05) // valor_atual + impacto
                ->has('statusLabels')
        );
    }

    public function test_dados_retorna_json(): void
    {
        $user = $this->userComPermissao();
        $this->novoReajuste();

        $this->actingAs($user)->getJson('/comercial/reajustes/dados')
            ->assertOk()
            ->assertJsonStructure(['reajustes' => [['id', 'cliente_nome', 'status', 'novo_valor', 'itens']]]);
    }

    public function test_update_status_persiste(): void
    {
        $user = $this->userComPermissao(['comercial.visualizar', 'comercial.aprovar']);
        $r = $this->novoReajuste(['status' => 'pendente']);

        $this->actingAs($user)->patchJson("/comercial/reajustes/{$r->id}/status", ['status' => 'enviado'])
            ->assertOk()->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_reajustes', ['id' => $r->id, 'status' => 'enviado']);
    }

    public function test_update_status_rejeita_invalido(): void
    {
        $user = $this->userComPermissao(['comercial.visualizar', 'comercial.aprovar']);
        $r = $this->novoReajuste();

        $this->actingAs($user)->patchJson("/comercial/reajustes/{$r->id}/status", ['status' => 'INEXISTENTE'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_update_status_exige_permissao_aprovar(): void
    {
        $user = $this->userComPermissao(['comercial.visualizar', 'comercial.cotar']);
        $r = $this->novoReajuste();

        $this->actingAs($user)->patchJson("/comercial/reajustes/{$r->id}/status", ['status' => 'enviado'])
            ->assertStatus(403);
    }

    public function test_destroy_exclui(): void
    {
        $user = $this->userComPermissao();
        $r = $this->novoReajuste();

        $this->actingAs($user)->deleteJson("/comercial/reajustes/{$r->id}")
            ->assertOk()->assertJson(['sucesso' => true]);
        $this->assertDatabaseMissing('bs_comercial_reajustes', ['id' => $r->id]);
    }

    public function test_destroy_exige_permissao_cotar(): void
    {
        $user = $this->userComPermissao(['comercial.visualizar']);
        $r = $this->novoReajuste();

        $this->actingAs($user)->deleteJson("/comercial/reajustes/{$r->id}")
            ->assertStatus(403);
        $this->assertDatabaseHas('bs_comercial_reajustes', ['id' => $r->id]);
    }

    public function test_index_exige_permissao_visualizar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/comercial/reajustes')->assertStatus(403);
    }
}
