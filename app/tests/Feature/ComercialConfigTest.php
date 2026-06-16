<?php

namespace Tests\Feature;

use App\Models\Comercial\Categoria;
use App\Models\Comercial\Cct;
use App\Models\Comercial\Encargo;
use App\Models\Comercial\Escala;
use App\Models\Comercial\Indice;
use App\Models\Comercial\Insumo;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Cobertura completa do módulo Comercial — Configuração / Valores.
 * Cobre cada rota do ComercialConfigController (index, dados, estados, CCT,
 * categorias, escalas, índices, encargos, insumos), incluindo caminho feliz
 * (assertDatabaseHas/Missing), validação (422) e permissões (403).
 *
 * Permissões:
 *  - index/dados: comercial.visualizar
 *  - escrita (POST/PUT/DELETE): comercial.configurar
 */
class ComercialConfigTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // User admin de comercial: visualizar + configurar.
        $this->user = User::factory()->create();
        $this->attachPermissao($this->user, 'comercial.visualizar', 'Comercial — Visualizar');
        $this->attachPermissao($this->user, 'comercial.configurar', 'Comercial — Configurar');
    }

    private function attachPermissao(User $user, string $key, string $label): void
    {
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => $key],
                ['label' => $label, 'module' => 'comercial']
            )->id
        );
    }

    /** User que só pode visualizar (sem comercial.configurar). */
    private function userSomenteVisualizar(): User
    {
        $u = User::factory()->create();
        $this->attachPermissao($u, 'comercial.visualizar', 'Comercial — Visualizar');
        return $u;
    }

    // ─── index / dados ───────────────────────────────────────

    public function test_index_retorna_200_e_componente_inertia(): void
    {
        $this->actingAs($this->user)
            ->get('/comercial/configuracoes')
            ->assertStatus(200)
            ->assertInertia(
                fn (AssertableInertia $page) => $page->component('Comercial/Configuracoes/Index', false)
            );
    }

    public function test_dados_retorna_json_com_chaves_esperadas(): void
    {
        Cct::create(['nome' => 'CCT Teste', 'uf' => 'df', 'ativo' => true]);
        Indice::create(['chave' => 'encargos', 'valor' => 72.11]);
        Encargo::create(['grupo' => 'A', 'codigo' => 'a01', 'label' => 'INSS', 'percentual' => 20, 'ordem' => 1]);
        Insumo::create(['chave' => 'uniforme', 'label' => 'Uniforme', 'valor' => 89.5, 'ordem' => 1]);

        $response = $this->actingAs($this->user)
            ->getJson('/comercial/configuracoes/dados');

        $response->assertOk()
            ->assertJsonStructure(['ccts', 'categorias', 'escalas', 'indices', 'encargos', 'insumos']);

        $this->assertCount(1, $response->json('ccts'));
        $this->assertCount(1, $response->json('indices'));
        $this->assertCount(1, $response->json('encargos'));
        $this->assertCount(1, $response->json('insumos'));
    }

    // ─── Estados (cria UF com 4 CCTs padrão) ─────────────────

    public function test_store_estado_cria_4_ccts_padrao(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/estados', [
                'uf' => 'SP',
                'nome' => 'São Paulo',
            ]);

        $response->assertOk()->assertJson(['sucesso' => true]);

        // 4 CCTs padrão criadas (uf gravada em minúsculas pelo controller).
        $this->assertSame(4, Cct::whereRaw('LOWER(uf) = ?', ['sp'])->count());
        foreach (['vigilancia', 'bombeiro', 'portaria', 'limpeza'] as $servico) {
            $this->assertDatabaseHas('bs_comercial_ccts', [
                'uf' => 'sp',
                'servico' => $servico,
            ]);
        }
    }

    public function test_store_estado_uf_duplicada_rejeita_422(): void
    {
        // Já existe CCT com uf=rj.
        Cct::create(['nome' => 'CCT RJ', 'uf' => 'rj', 'ativo' => true]);

        $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/estados', [
                'uf' => 'RJ',
                'nome' => 'Rio de Janeiro',
            ])
            ->assertStatus(422)
            ->assertJson(['sucesso' => false]);

        // Não criou as 4 CCTs padrão.
        $this->assertSame(1, Cct::whereRaw('LOWER(uf) = ?', ['rj'])->count());
    }

    public function test_store_estado_valida_campos_obrigatorios(): void
    {
        // uf com tamanho != 2 e nome ausente → 422.
        $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/estados', ['uf' => 'XYZ'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['uf', 'nome']);
    }

    // ─── CCT ─────────────────────────────────────────────────

    public function test_store_cct(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/ccts', [
                'nome' => 'CCT Vigilância — MG',
                'uf' => 'mg',
                'salario_base' => 1850.50,
                'ativo' => true,
            ]);

        $response->assertOk()->assertJson(['sucesso' => true]);
        $this->assertDatabaseHas('bs_comercial_ccts', [
            'nome' => 'CCT Vigilância — MG',
            'uf' => 'mg',
            'salario_base' => 1850.50,
        ]);
    }

    public function test_store_cct_valida_nome_obrigatorio(): void
    {
        $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/ccts', ['uf' => 'mg'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nome']);
    }

    public function test_update_cct_atualiza_salario_base(): void
    {
        $cct = Cct::create(['nome' => 'CCT Update', 'uf' => 'ba', 'salario_base' => 1000, 'ativo' => true]);

        $this->actingAs($this->user)
            ->putJson("/comercial/configuracoes/ccts/{$cct->id}", [
                'nome' => 'CCT Update',
                'salario_base' => 2500.75,
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_ccts', [
            'id' => $cct->id,
            'salario_base' => 2500.75,
        ]);
    }

    public function test_destroy_cct(): void
    {
        $cct = Cct::create(['nome' => 'CCT Delete', 'uf' => 'pr', 'ativo' => true]);

        $this->actingAs($this->user)
            ->deleteJson("/comercial/configuracoes/ccts/{$cct->id}")
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseMissing('bs_comercial_ccts', ['id' => $cct->id]);
    }

    // ─── Categorias ──────────────────────────────────────────

    public function test_store_categoria(): void
    {
        $cct = Cct::create(['nome' => 'CCT Cat', 'uf' => 'df', 'ativo' => true]);

        $response = $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/categorias', [
                'cct_id' => $cct->id,
                'nome' => 'Agente de Portaria',
                'salario_base' => 1600,
                'ativo' => true,
            ]);

        $response->assertOk()->assertJson(['sucesso' => true]);
        $this->assertDatabaseHas('bs_comercial_categorias', [
            'nome' => 'Agente de Portaria',
            'cct_id' => $cct->id,
        ]);
    }

    public function test_store_categoria_valida_nome_obrigatorio(): void
    {
        $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/categorias', ['salario_base' => 1000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nome']);
    }

    public function test_update_categoria(): void
    {
        $cat = Categoria::create(['nome' => 'Cat Original', 'salario_base' => 1000, 'ativo' => true]);

        $this->actingAs($this->user)
            ->putJson("/comercial/configuracoes/categorias/{$cat->id}", [
                'nome' => 'Cat Atualizada',
                'salario_base' => 1999.90,
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_categorias', [
            'id' => $cat->id,
            'nome' => 'Cat Atualizada',
            'salario_base' => 1999.90,
        ]);
    }

    public function test_destroy_categoria(): void
    {
        $cat = Categoria::create(['nome' => 'Cat Delete', 'ativo' => true]);

        $this->actingAs($this->user)
            ->deleteJson("/comercial/configuracoes/categorias/{$cat->id}")
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseMissing('bs_comercial_categorias', ['id' => $cat->id]);
    }

    // ─── Escalas ─────────────────────────────────────────────

    public function test_store_escala(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/escalas', [
                'nome' => '12x36 — Noturno',
                'dias_mes' => 15.5,
                'horas_mes' => 220,
                'qtd_diurno' => 0,
                'qtd_noturno' => 1,
                'func_por_posto' => 1,
                'tem_an' => true,
                'ativo' => true,
            ]);

        $response->assertOk()->assertJson(['sucesso' => true]);
        $this->assertDatabaseHas('bs_comercial_escalas', [
            'nome' => '12x36 — Noturno',
            'qtd_noturno' => 1,
        ]);
    }

    public function test_store_escala_valida_nome_obrigatorio(): void
    {
        $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/escalas', ['dias_mes' => 22])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nome']);
    }

    public function test_update_escala(): void
    {
        $escala = Escala::create(['nome' => 'Escala Original', 'dias_mes' => 22, 'horas_mes' => 220, 'ativo' => true]);

        $this->actingAs($this->user)
            ->putJson("/comercial/configuracoes/escalas/{$escala->id}", [
                'nome' => 'Escala Atualizada',
                'dias_mes' => 26,
                'horas_mes' => 220,
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_escalas', [
            'id' => $escala->id,
            'nome' => 'Escala Atualizada',
            'dias_mes' => 26,
        ]);
    }

    public function test_destroy_escala(): void
    {
        $escala = Escala::create(['nome' => 'Escala Delete', 'ativo' => true]);

        $this->actingAs($this->user)
            ->deleteJson("/comercial/configuracoes/escalas/{$escala->id}")
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseMissing('bs_comercial_escalas', ['id' => $escala->id]);
    }

    // ─── Índices ─────────────────────────────────────────────

    public function test_salvar_indices_faz_upsert(): void
    {
        $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/indices', [
                'indices' => [
                    ['chave' => 'adm', 'valor' => 5, 'descricao' => 'Administração (%)'],
                    ['chave' => 'lucro', 'valor' => 3, 'descricao' => 'Lucro (%)'],
                ],
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_indices', ['chave' => 'adm', 'valor' => 5]);
        $this->assertDatabaseHas('bs_comercial_indices', ['chave' => 'lucro', 'valor' => 3]);

        // Roda de novo alterando o valor — deve ATUALIZAR (updateOrCreate), não duplicar.
        $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/indices', [
                'indices' => [['chave' => 'adm', 'valor' => 7.5]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('bs_comercial_indices', ['chave' => 'adm', 'valor' => 7.5]);
        $this->assertSame(1, Indice::where('chave', 'adm')->count());
    }

    // ─── Encargos ────────────────────────────────────────────

    public function test_salvar_encargos_atualiza_percentual_e_total(): void
    {
        $e1 = Encargo::create(['grupo' => 'A', 'codigo' => 'a01', 'label' => 'INSS', 'percentual' => 0, 'ordem' => 1]);
        $e2 = Encargo::create(['grupo' => 'A', 'codigo' => 'a02', 'label' => 'FGTS', 'percentual' => 0, 'ordem' => 2]);

        $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/encargos', [
                'encargos' => [
                    ['id' => $e1->id, 'percentual' => 20],
                    ['id' => $e2->id, 'percentual' => 8],
                ],
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_encargos', ['id' => $e1->id, 'percentual' => 20]);
        $this->assertDatabaseHas('bs_comercial_encargos', ['id' => $e2->id, 'percentual' => 8]);

        // O índice 'encargos' deve refletir o somatório (28).
        $this->assertDatabaseHas('bs_comercial_indices', ['chave' => 'encargos', 'valor' => 28]);
    }

    // ─── Insumos ─────────────────────────────────────────────

    public function test_salvar_insumos_atualiza_valor(): void
    {
        $insumo = Insumo::create(['chave' => 'epi', 'label' => 'EPI', 'valor' => 0, 'ordem' => 1]);

        $this->actingAs($this->user)
            ->postJson('/comercial/configuracoes/insumos', [
                'insumos' => [
                    ['id' => $insumo->id, 'valor' => 45.90],
                ],
            ])
            ->assertOk()
            ->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('bs_comercial_insumos', ['id' => $insumo->id, 'valor' => 45.90]);
    }

    // ─── Permissões ──────────────────────────────────────────

    public function test_dados_acessivel_com_apenas_visualizar(): void
    {
        $this->actingAs($this->userSomenteVisualizar())
            ->getJson('/comercial/configuracoes/dados')
            ->assertOk();
    }

    public function test_escrita_exige_permissao_configurar(): void
    {
        $semConfig = $this->userSomenteVisualizar();

        // POST estados
        $this->actingAs($semConfig)
            ->postJson('/comercial/configuracoes/estados', ['uf' => 'sp', 'nome' => 'SP'])
            ->assertStatus(403);

        // POST cct
        $this->actingAs($semConfig)
            ->postJson('/comercial/configuracoes/ccts', ['nome' => 'X'])
            ->assertStatus(403);

        // PUT cct
        $cct = Cct::create(['nome' => 'Protegida', 'uf' => 'df', 'ativo' => true]);
        $this->actingAs($semConfig)
            ->putJson("/comercial/configuracoes/ccts/{$cct->id}", ['nome' => 'Hack', 'salario_base' => 1])
            ->assertStatus(403);

        // DELETE cct
        $this->actingAs($semConfig)
            ->deleteJson("/comercial/configuracoes/ccts/{$cct->id}")
            ->assertStatus(403);

        // POST índices / encargos / insumos
        $this->actingAs($semConfig)
            ->postJson('/comercial/configuracoes/indices', ['indices' => [['chave' => 'adm', 'valor' => 5]]])
            ->assertStatus(403);
        $this->actingAs($semConfig)
            ->postJson('/comercial/configuracoes/encargos', ['encargos' => []])
            ->assertStatus(403);
        $this->actingAs($semConfig)
            ->postJson('/comercial/configuracoes/insumos', ['insumos' => []])
            ->assertStatus(403);

        // Nada foi alterado.
        $this->assertDatabaseHas('bs_comercial_ccts', ['id' => $cct->id, 'nome' => 'Protegida']);
        $this->assertSame(0, Cct::whereRaw('LOWER(uf) = ?', ['sp'])->count());
    }

    public function test_acesso_negado_sem_login(): void
    {
        $this->get('/comercial/configuracoes')->assertRedirect('/login');
    }
}
