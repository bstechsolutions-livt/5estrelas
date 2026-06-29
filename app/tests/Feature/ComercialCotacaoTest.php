<?php

namespace Tests\Feature;

use App\Models\Comercial\Categoria;
use App\Models\Comercial\Cct;
use App\Models\Comercial\Escala;
use App\Models\Comercial\Indice;
use App\Models\Comercial\Proposta;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Cobertura completa do módulo Comercial — Cotação (IN 05 / Modelo 5 Estrelas).
 * Cobre cada rota do ComercialCotacaoController (index, dados, calcular, calcular5e),
 * permissões e a forma das respostas. O motor de cálculo (ComposicaoCustoService) já
 * é verificado ao centavo em tests/Unit/ComposicaoCustoServiceTest.php — aqui validamos
 * apenas que os ENDPOINTS respondem corretamente.
 */
class ComercialCotacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // User com acesso de visualização ao módulo Comercial.
        $this->user = User::factory()->create();
        $this->user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'comercial.visualizar'],
                ['label' => 'Comercial — Visualizar', 'module' => 'comercial']
            )->id
        );
    }

    /** Cria um usuário SEM nenhuma permissão comercial. */
    private function userSemPermissao(): User
    {
        return User::factory()->create();
    }

    /** Semeia 1 CCT + 1 escala + 1 categoria + 1 índice (todos ativos). */
    private function semearConfig(): array
    {
        $cct = Cct::create([
            'nome' => 'CCT Vigilância — DF',
            'uf' => 'df',
            'ano_base' => '2026',
            'ativo' => true,
        ]);

        $escala = Escala::create([
            'nome' => '12x36 — Diurno',
            'dias_mes' => 15.5,
            'horas_mes' => 220,
            'qtd_diurno' => 1,
            'qtd_noturno' => 0,
            'func_por_posto' => 1,
            'tem_an' => false,
            'ativo' => true,
        ]);

        $categoria = Categoria::create([
            'cct_id' => $cct->id,
            'nome' => 'Vigilante',
            'salario_base' => 1850,
            'ativo' => true,
        ]);

        $indice = Indice::create([
            'chave' => 'encargos',
            'valor' => 72.11,
            'descricao' => 'Encargos sociais (%)',
        ]);

        return compact('cct', 'escala', 'categoria', 'indice');
    }

    public function test_index_retorna_200_e_componente_inertia(): void
    {
        $this->actingAs($this->user)
            ->get('/comercial/cotacao')
            ->assertStatus(200)
            ->assertInertia(
                fn (AssertableInertia $page) => $page->component('Comercial/Cotacao/Index', false)
                    ->where('propostaInicial', null)
            );
    }

    public function test_index_reabre_proposta_da_plataforma_restaurando_snapshot(): void
    {
        $proposta = Proposta::create([
            'numero' => 'Nº 200',
            'modelo' => 'in05',
            'cliente' => 'Condomínio Reabrir',
            'empresa' => 'seg-df',
            'cct' => 'CCT Vigilância — DF',
            'periodicidade' => 'Mensal',
            'data_proposta' => '2026-06-16',
            'da_cotacao' => true,
            'postos' => [
                ['id' => 1, 'cat' => 'Vigilante', 'escala' => '12x36 — Diurno', 'funcPosto' => 1,
                    'qtdPostos' => 2, 'unitVal' => 5000, 'totalMensal' => 10000, 'vaUnit' => 300, 'modelo' => 'in05'],
            ],
            'identificacao' => ['cliente' => 'Condomínio Reabrir', 'modelo' => 'in05'],
        ]);

        $this->actingAs($this->user)
            ->get('/comercial/cotacao?proposta=' . $proposta->id)
            ->assertStatus(200)
            ->assertInertia(
                fn (AssertableInertia $page) => $page->component('Comercial/Cotacao/Index', false)
                    ->where('propostaInicial.id', $proposta->id)
                    ->where('propostaInicial.cliente', 'Condomínio Reabrir')
                    ->where('propostaInicial.modelo', 'in05')
                    ->where('propostaInicial.da_cotacao', true)
                    ->has('propostaInicial.postos', 1)
            );
    }

    public function test_index_com_proposta_inexistente_ignora(): void
    {
        $this->actingAs($this->user)
            ->get('/comercial/cotacao?proposta=999999')
            ->assertStatus(200)
            ->assertInertia(
                fn (AssertableInertia $page) => $page->component('Comercial/Cotacao/Index', false)
                    ->where('propostaInicial', null)
            );
    }

    public function test_dados_retorna_json_com_chaves_esperadas(): void
    {
        $this->semearConfig();

        $response = $this->actingAs($this->user)
            ->getJson('/comercial/cotacao/dados');

        $response->assertOk()
            ->assertJsonStructure(['ccts', 'escalas', 'categorias', 'indices', 'filiais', 'clientes']);

        // Os registros semeados precisam vir no JSON.
        $this->assertCount(1, $response->json('ccts'));
        $this->assertCount(1, $response->json('escalas'));
        $this->assertCount(1, $response->json('categorias'));
        $this->assertSame('CCT Vigilância — DF', $response->json('ccts.0.nome'));
        $this->assertSame('Vigilante', $response->json('categorias.0.nome'));
        // indices é mapa chave => valor.
        $this->assertArrayHasKey('encargos', $response->json('indices'));
        // filiais e clientes (parametrizados) também são expostos para os comboboxes.
        $this->assertIsArray($response->json('filiais'));
        $this->assertIsArray($response->json('clientes'));
    }

    public function test_dados_omite_registros_inativos(): void
    {
        // CCT inativa não deve aparecer (controller filtra ativo=true).
        Cct::create(['nome' => 'CCT Inativa', 'uf' => 'go', 'ativo' => false]);
        Escala::create(['nome' => 'Escala Inativa', 'ativo' => false]);
        Categoria::create(['nome' => 'Categoria Inativa', 'ativo' => false]);

        $response = $this->actingAs($this->user)
            ->getJson('/comercial/cotacao/dados');

        $response->assertOk();
        $this->assertCount(0, $response->json('ccts'));
        $this->assertCount(0, $response->json('escalas'));
        $this->assertCount(0, $response->json('categorias'));
    }

    public function test_calcular_in05_retorna_resultado_valido(): void
    {
        // Payload representativo do formulário IN 05 (defaults do protótipo).
        $payload = [
            'sal' => 1850, 'dias_mes' => 15.5, 'horas_mes' => 220,
            'peric_pct' => 0, 'insal_pct' => 0, 'an_pct' => 0, 'hnr_pct' => 0, 'outros1_pct' => 0,
            'inss_pct' => 20, 'saledu_pct' => 2.5, 'sat_pct' => 3.28, 'sesc_pct' => 1.5,
            'senai_pct' => 1, 'sebrae_pct' => 0.6, 'incra_pct' => 0.2, 'fgts_pct' => 8,
            'vt_dia' => 10.4, 'va_dia' => 30, 'medico' => 0, 'odonto' => 0, 'cesta' => 0,
            'seguro' => 14.2, 'pmq' => 0, 'outros23' => 0,
            'avisoind_pct' => 1, 'avistrab_pct' => 0.59, 'ausleg_pct' => 0.1,
            'paterni_pct' => 0.02, 'acident_pct' => 0.1, 'matern_pct' => 0.02, 'intrajornada' => 0,
            'uniforme' => 89.5, 'materiais' => 0, 'ferramental' => 0, 'epi' => 0, 'treinamento' => 0, 'sso' => 18,
            'custoind_pct' => 5, 'lucro_pct' => 3, 'iss_pct' => 5, 'pis_pct' => 1.65, 'cofins_pct' => 7.6,
            'colaboradores' => 2,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/comercial/cotacao/calcular', $payload);

        $response->assertOk()
            ->assertJson(['sucesso' => true])
            ->assertJsonStructure([
                'sucesso',
                'resultado' => [
                    'modulo1', 'modulo2', 'modulo3', 'modulo4', 'modulo5', 'modulo6',
                    'subtotal', 'preco_empregado', 'colaboradores', 'valor_posto_mensal',
                ],
            ]);

        $preco = $response->json('resultado.preco_empregado');
        $this->assertIsNumeric($preco);
        $this->assertGreaterThan(0, $preco);

        // valor_posto_mensal = preco_empregado * colaboradores.
        $this->assertSame(2, $response->json('resultado.colaboradores'));
        $this->assertEqualsWithDelta(
            round($preco * 2, 2),
            $response->json('resultado.valor_posto_mensal'),
            0.01
        );
    }

    public function test_calcular_in05_aceita_payload_vazio_usando_defaults(): void
    {
        // Todos os campos são nullable — payload vazio não deve dar 422.
        $response = $this->actingAs($this->user)
            ->postJson('/comercial/cotacao/calcular', []);

        $response->assertOk()->assertJson(['sucesso' => true]);
        $this->assertIsNumeric($response->json('resultado.preco_empregado'));
    }

    public function test_calcular_in05_rejeita_tipo_invalido(): void
    {
        // sal não numérico → 422.
        $this->actingAs($this->user)
            ->postJson('/comercial/cotacao/calcular', ['sal' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sal']);
    }

    public function test_calcular_5e_retorna_resultado_valido(): void
    {
        $payload = [
            'qtd_diurno' => 2, 'sal_diurno' => 2000, 'qtd_noturno' => 0, 'sal_noturno' => 0,
            'an_diurno' => 0, 'an_noturno' => 1,
            'encargos_pct' => 72.11, 'pct_adm' => 5, 'pct_lucro' => 3, 'pct_impostos' => 8.65,
            'peric_pct' => 0, 'intra_h' => 1.5, 'desc_vt_pct' => 6, 'dias_mes' => 15.5, 'horas_mes' => 220,
            'beneficios' => [
                'uniforme' => 89.5, 'saude' => 242, 'fundo' => 31.5, 'sst' => 18,
                'cna' => 22, 'seguro' => 14.2, 'gta' => 47, 'cofre' => 55,
                'arma' => 126, 'reciclag' => 32, 'vt' => 10.4, 'va' => 30,
            ],
            'meses' => 12,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/comercial/cotacao/calcular-5e', $payload);

        $response->assertOk()
            ->assertJson(['sucesso' => true])
            ->assertJsonStructure([
                'sucesso',
                'resultado' => [
                    'total_funcionarios', 'remuneracao', 'modulo1', 'modulo2', 'modulo3',
                    'mensal', 'anual', 'valor_por_funcionario', 'va_total',
                ],
            ]);

        $mensal = $response->json('resultado.mensal');
        $this->assertIsNumeric($mensal);
        $this->assertGreaterThan(0, $mensal);
        $this->assertSame(2, $response->json('resultado.total_funcionarios'));
    }

    public function test_calcular_5e_sem_funcionarios_nao_quebra(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/comercial/cotacao/calcular-5e', [
                'qtd_diurno' => 0, 'qtd_noturno' => 0,
            ]);

        $response->assertOk()->assertJson(['sucesso' => true]);
        $this->assertSame(0, $response->json('resultado.total_funcionarios'));
    }

    public function test_calcular_5e_rejeita_tipo_invalido(): void
    {
        // qtd_diurno deve ser inteiro → string não numérica dá 422.
        $this->actingAs($this->user)
            ->postJson('/comercial/cotacao/calcular-5e', ['qtd_diurno' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['qtd_diurno']);
    }

    public function test_acesso_negado_sem_permissao_visualizar(): void
    {
        $semPerm = $this->userSemPermissao();

        $this->actingAs($semPerm)
            ->get('/comercial/cotacao')
            ->assertStatus(403);

        $this->actingAs($semPerm)
            ->getJson('/comercial/cotacao/dados')
            ->assertStatus(403);

        $this->actingAs($semPerm)
            ->postJson('/comercial/cotacao/calcular', [])
            ->assertStatus(403);

        $this->actingAs($semPerm)
            ->postJson('/comercial/cotacao/calcular-5e', [])
            ->assertStatus(403);
    }

    public function test_acesso_negado_sem_login(): void
    {
        // Sem autenticação → redirect para login (web) / 401 (json).
        $this->get('/comercial/cotacao')->assertRedirect('/login');
    }
}
