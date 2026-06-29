<?php

namespace Tests\Feature;

use App\Models\Comercial\Cliente;
use App\Models\Comercial\Faturamento;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobertura completa do módulo Comercial — Faturamento.
 * Cobre cada rota/ação do ComercialFaturamentoController (index, dados,
 * salvar, adicionarLocal, excluirLocal), validação, permissões e o
 * helper total() do model.
 */
class ComercialFaturamentoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // User com permissões de visualizar + cotar (acesso total ao módulo).
        $this->user = User::factory()->create();
        $this->user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'comercial.visualizar'],
                ['label' => 'Comercial — Visualizar', 'module' => 'comercial']
            )->id
        );
        $this->user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'comercial.cotar'],
                ['label' => 'Comercial — Cotar', 'module' => 'comercial']
            )->id
        );
    }

    /** Cria um user que só pode visualizar (sem comercial.cotar). */
    private function userSomenteVisualizar(): User
    {
        $u = User::factory()->create();
        $u->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'comercial.visualizar'],
                ['label' => 'Comercial — Visualizar', 'module' => 'comercial']
            )->id
        );
        return $u;
    }

    public function test_index_retorna_200(): void
    {
        $this->actingAs($this->user)
            ->get('/comercial/faturamento')
            ->assertStatus(200);
    }

    public function test_dados_retorna_json(): void
    {
        // Semeia um local em cada ano para garantir estrutura preenchida.
        Faturamento::create(['ano' => 2025, 'local_nome' => 'Local 2025', 'jan' => 100]);
        Faturamento::create(['ano' => 2026, 'local_nome' => 'Local 2026', 'fev' => 200]);

        $response = $this->actingAs($this->user)
            ->getJson('/comercial/faturamento/dados');

        $response->assertOk()
            ->assertJsonStructure([
                '2025' => ['locais'],
                '2026' => ['locais'],
            ]);

        $this->assertCount(1, $response->json('2025.locais'));
        $this->assertCount(1, $response->json('2026.locais'));
        $this->assertSame('Local 2025', $response->json('2025.locais.0.local_nome'));
    }

    public function test_adicionar_local(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/comercial/faturamento/local', [
                'ano' => 2025,
                'local_nome' => 'Hospital São Lucas — Vigilância',
            ]);

        $response->assertOk()->assertJson(['sucesso' => true]);
        $this->assertDatabaseHas('bs_comercial_faturamento', [
            'ano' => 2025,
            'local_nome' => 'Hospital São Lucas — Vigilância',
        ]);
    }

    public function test_adicionar_local_vincula_cliente(): void
    {
        $cli = Cliente::create(['nome' => 'Cliente Vinc Fat', 'situacao' => 'ativo']);

        $this->actingAs($this->user)
            ->postJson('/comercial/faturamento/local', [
                'ano' => 2025,
                'local_nome' => 'Cliente Vinc Fat',
                'cliente_id' => $cli->id,
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_faturamento', [
            'ano' => 2025,
            'local_nome' => 'Cliente Vinc Fat',
            'cliente_id' => $cli->id,
        ]);
    }

    public function test_adicionar_local_rejeita_cliente_inexistente(): void
    {
        $this->actingAs($this->user)
            ->postJson('/comercial/faturamento/local', [
                'ano' => 2025,
                'local_nome' => 'Local X',
                'cliente_id' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cliente_id']);
    }

    public function test_adicionar_local_duplicado_rejeita(): void
    {
        $payload = ['ano' => 2025, 'local_nome' => 'Local Duplicado'];

        $this->actingAs($this->user)
            ->postJson('/comercial/faturamento/local', $payload)
            ->assertOk();

        // Segunda tentativa com o mesmo {ano, local_nome} → 422.
        $this->actingAs($this->user)
            ->postJson('/comercial/faturamento/local', $payload)
            ->assertStatus(422)
            ->assertJson(['sucesso' => false]);

        // Não duplicou.
        $this->assertSame(1, Faturamento::where('ano', 2025)
            ->where('local_nome', 'Local Duplicado')->count());
    }

    public function test_salvar_faz_upsert(): void
    {
        $payload = [
            'ano' => 2025,
            'locais' => [
                [
                    'local_nome' => 'Contrato Alpha',
                    'jan' => 1000.50,
                    'fev' => 2000.75,
                    'setembro' => 500,
                ],
            ],
        ];

        $this->actingAs($this->user)
            ->postJson('/comercial/faturamento/salvar', $payload)
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_faturamento', [
            'ano' => 2025,
            'local_nome' => 'Contrato Alpha',
            'jan' => 1000.50,
            'fev' => 2000.75,
            'setembro' => 500.00,
        ]);

        $this->assertSame(1, Faturamento::where('ano', 2025)
            ->where('local_nome', 'Contrato Alpha')->count());

        // Roda de novo com valores alterados — deve ATUALIZAR, não duplicar.
        $payloadAtualizado = [
            'ano' => 2025,
            'locais' => [
                [
                    'local_nome' => 'Contrato Alpha',
                    'jan' => 9999.99,
                    'fev' => 0,
                ],
            ],
        ];

        $this->actingAs($this->user)
            ->postJson('/comercial/faturamento/salvar', $payloadAtualizado)
            ->assertOk();

        $this->assertDatabaseHas('bs_comercial_faturamento', [
            'ano' => 2025,
            'local_nome' => 'Contrato Alpha',
            'jan' => 9999.99,
            'fev' => 0,
        ]);

        // Continua sendo apenas 1 registro (upsert, não insert).
        $this->assertSame(1, Faturamento::where('ano', 2025)
            ->where('local_nome', 'Contrato Alpha')->count());
    }

    public function test_excluir_local(): void
    {
        $faturamento = Faturamento::create([
            'ano' => 2026,
            'local_nome' => 'Local a Excluir',
            'jan' => 123.45,
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/comercial/faturamento/{$faturamento->id}")
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseMissing('bs_comercial_faturamento', [
            'id' => $faturamento->id,
        ]);
    }

    public function test_salvar_exige_permissao(): void
    {
        $semCotar = $this->userSomenteVisualizar();

        $this->actingAs($semCotar)
            ->postJson('/comercial/faturamento/salvar', [
                'ano' => 2025,
                'locais' => [['local_nome' => 'Sem Permissão', 'jan' => 100]],
            ])
            ->assertStatus(403);

        // Não persistiu nada.
        $this->assertDatabaseMissing('bs_comercial_faturamento', [
            'local_nome' => 'Sem Permissão',
        ]);
    }

    public function test_adicionar_local_exige_permissao(): void
    {
        $semCotar = $this->userSomenteVisualizar();

        $this->actingAs($semCotar)
            ->postJson('/comercial/faturamento/local', [
                'ano' => 2025,
                'local_nome' => 'Bloqueado',
            ])
            ->assertStatus(403);
    }

    public function test_excluir_local_exige_permissao(): void
    {
        $faturamento = Faturamento::create(['ano' => 2025, 'local_nome' => 'Protegido']);
        $semCotar = $this->userSomenteVisualizar();

        $this->actingAs($semCotar)
            ->deleteJson("/comercial/faturamento/{$faturamento->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('bs_comercial_faturamento', [
            'id' => $faturamento->id,
        ]);
    }

    public function test_salvar_valida_campos_obrigatorios(): void
    {
        // ano ausente → 422
        $this->actingAs($this->user)
            ->postJson('/comercial/faturamento/salvar', [
                'locais' => [['local_nome' => 'X']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ano']);

        // local sem nome → 422
        $this->actingAs($this->user)
            ->postJson('/comercial/faturamento/salvar', [
                'ano' => 2025,
                'locais' => [['jan' => 10]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['locais.0.local_nome']);
    }

    public function test_total_do_model(): void
    {
        $faturamento = Faturamento::create([
            'ano' => 2025,
            'local_nome' => 'Total Test',
            'jan' => 100.10,
            'fev' => 200.20,
            'setembro' => 300.30,
            'dez' => 400.40,
        ]);

        $esperado = 100.10 + 200.20 + 300.30 + 400.40;
        $this->assertEqualsWithDelta($esperado, $faturamento->total(), 0.001);
    }
}
