<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Permission;
use App\Models\SolicitacaoAssunto;
use App\Models\SolicitacaoEquipamentos;
use App\Models\SolicitacaoEtapa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Módulo de Tickets (Solicitações) — CONFIGURAÇÕES + CRIAÇÃO.
 *
 * Cobre as rotas administrativas sob /solicitacoes/configuracoes
 * (exigem permissão `solicitacoes.configurar`) e a criação de tickets
 * em /solicitacoes/nova/criar. Payloads/asserções derivados do código
 * legado portado em SolicitacoesController.
 *
 * NÃO coberto aqui (e o porquê):
 *  - indexNova: já testado em SolicitacoesTest (test_index_nova_renderiza).
 *  - getFiliaisWinthor / getFuncoesWinthor / getRegionais /
 *    getDepartamentosCompras / getDepartamentosFuncionario / importar(xlsx):
 *    dependem de fontes externas/legadas (Winthor/ERP/planilha) que não
 *    existem no ambiente de teste. Operam fora das tabelas intranet_solicitacao*.
 */
class SolicitacoesConfigTest extends TestCase
{
    use RefreshDatabase;

    /** Usuário com permissão de configurar (e visualizar). */
    private function userConfig(): User
    {
        return $this->userComPermissoes(['solicitacoes.visualizar', 'solicitacoes.configurar']);
    }

    /** Usuário apenas com visualizar (sem configurar). */
    private function userVisualizar(): User
    {
        return $this->userComPermissoes(['solicitacoes.visualizar']);
    }

    private function userComPermissoes(array $keys): User
    {
        $dept = Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);
        $user = User::factory()->create(['department_id' => $dept->id]);
        foreach ($keys as $key) {
            $perm = Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'solicitacoes']);
            $user->permissions()->attach($perm->id);
        }

        return $user;
    }

    private function novoAssunto(?int $departmentId = null, array $attrs = []): SolicitacaoAssunto
    {
        $assunto = SolicitacaoAssunto::create(array_merge([
            'assunto' => 'Assunto Teste',
            'prioridade' => 'normal',
            'ativo' => 'S',
        ], $attrs));

        if ($departmentId !== null) {
            $assunto->department_id = $departmentId;
            $assunto->save();
        }

        return $assunto;
    }

    // ─── indexConfiguracoes ────────────────────────────────────────────────────

    public function test_index_configuracoes_renderiza(): void
    {
        $user = $this->userConfig();

        $response = $this->actingAs($user)->get('/solicitacoes/configuracoes');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Solicitacoes/Configuracoes/Index', false)
                ->has('departamentos')
        );
    }

    // ─── Departamentos ──────────────────────────────────────────────────────────

    public function test_get_departamentos_separa_ativos_e_inativos(): void
    {
        $user = $this->userConfig();
        $ativo = Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);
        $inativo = Department::create(['name' => 'Arquivado', 'is_active' => false]);

        $response = $this->actingAs($user)->get('/solicitacoes/configuracoes/departamentos');

        $response->assertOk();
        $response->assertJsonStructure(['ativos', 'inativos']);
        $response->assertJsonFragment(['label' => 'TI', 'value' => $ativo->id]);
        $response->assertJsonFragment(['label' => 'Arquivado', 'value' => $inativo->id]);
    }

    public function test_store_departamentos_ativa_e_inativa(): void
    {
        $user = $this->userConfig();
        $deptAtivar = Department::create(['name' => 'Compras', 'is_active' => false]);
        $deptInativar = Department::create(['name' => 'Antigo', 'is_active' => true]);

        // Estrutura legada: request[0] = inativos, request[1] = ativos
        $response = $this->actingAs($user)->postJson('/solicitacoes/configuracoes/departamentos', [
            [['value' => $deptInativar->id]],
            [['value' => $deptAtivar->id]],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('departments', ['id' => $deptAtivar->id, 'is_active' => true]);
        $this->assertDatabaseHas('departments', ['id' => $deptInativar->id, 'is_active' => false]);
    }

    // ─── Assuntos ─────────────────────────────────────────────────────────────────

    public function test_get_assuntos_retorna_estrutura(): void
    {
        $user = $this->userConfig();
        $dept = Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);
        $this->novoAssunto($dept->id, ['assunto' => 'Acesso ao sistema']);

        $response = $this->actingAs($user)->postJson('/solicitacoes/configuracoes/assuntos', [
            'departamento' => $dept->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['assuntos', 'responsaveis', 'campos', 'prazoResolucao']);
        $response->assertJsonFragment(['assunto' => 'Acesso ao sistema']);
    }

    public function test_salvar_assuntos_cria_novo_assunto(): void
    {
        $user = $this->userConfig();
        $dept = Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);

        $response = $this->actingAs($user)->postJson('/solicitacoes/configuracoes/salvar-assuntos', [
            'departamento' => $dept->id,
            'prazoResolucao' => null,
            'assuntos' => [
                [
                    'id' => null,
                    'assunto' => 'Reset de senha',
                    'responsavel' => null,
                    'prioridade' => 'normal',
                    'ativo' => 'S',
                    'qtd_min_anexos' => 0,
                    'campos' => [],
                    'selects' => [],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao_assuntos', [
            'assunto' => 'Reset de senha',
            'department_id' => $dept->id,
            'ativo' => 'S',
        ]);
    }

    public function test_salvar_assuntos_edita_existente(): void
    {
        $user = $this->userConfig();
        $dept = Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);
        $assunto = $this->novoAssunto($dept->id, ['assunto' => 'Nome antigo']);

        $response = $this->actingAs($user)->postJson('/solicitacoes/configuracoes/salvar-assuntos', [
            'departamento' => $dept->id,
            'prazoResolucao' => null,
            'assuntos' => [
                [
                    'id' => $assunto->id,
                    'assunto' => 'Nome novo',
                    'responsavel' => null,
                    'prioridade' => 'alta',
                    'ativo' => 'S',
                    'qtd_min_anexos' => 0,
                    'campos' => [],
                    'selects' => [],
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao_assuntos', [
            'id' => $assunto->id,
            'assunto' => 'Nome novo',
            'prioridade' => 'alta',
        ]);
    }

    public function test_duplicar_assunto(): void
    {
        $user = $this->userConfig();
        $dept = Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);
        $assunto = $this->novoAssunto($dept->id, ['assunto' => 'Original']);

        $response = $this->actingAs($user)->postJson('/solicitacoes/configuracoes/duplicar-assunto', [
            'assunto_id' => $assunto->id,
            'novo_nome' => 'Cópia do Original',
        ]);

        $response->assertOk();
        $response->assertJson(['sucesso' => true]);
        $this->assertDatabaseHas('intranet_solicitacao_assuntos', [
            'assunto' => 'Cópia do Original',
        ]);
    }

    public function test_toggle_ativo_assunto(): void
    {
        $user = $this->userConfig();
        $dept = Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);
        $assunto = $this->novoAssunto($dept->id, ['assunto' => 'Toggle', 'ativo' => 'S']);

        // Primeira chamada: S → N
        $response = $this->actingAs($user)->postJson('/solicitacoes/configuracoes/toggle-ativo-assunto', [
            'assunto_id' => $assunto->id,
        ]);
        $response->assertOk();
        $response->assertJson(['sucesso' => true, 'ativo' => 'N']);
        $this->assertDatabaseHas('intranet_solicitacao_assuntos', ['id' => $assunto->id, 'ativo' => 'N']);

        // Segunda chamada: N → S
        $response = $this->actingAs($user)->postJson('/solicitacoes/configuracoes/toggle-ativo-assunto', [
            'assunto_id' => $assunto->id,
        ]);
        $response->assertJson(['ativo' => 'S']);
        $this->assertDatabaseHas('intranet_solicitacao_assuntos', ['id' => $assunto->id, 'ativo' => 'S']);
    }

    // ─── Equipamentos ───────────────────────────────────────────────────────────

    public function test_get_equipamentos(): void
    {
        $user = $this->userConfig();
        SolicitacaoEquipamentos::create(['equipamento' => 'Notebook']);
        SolicitacaoEquipamentos::create(['equipamento' => 'Mouse']);

        $response = $this->actingAs($user)->get('/solicitacoes/configuracoes/buscar-equipamentos');

        $response->assertOk();
        $response->assertJsonFragment(['equipamento' => 'Notebook']);
        $response->assertJsonFragment(['equipamento' => 'Mouse']);
    }

    public function test_add_equipamento_cria_e_edita(): void
    {
        $user = $this->userConfig();

        // Criar (sem id)
        $response = $this->actingAs($user)->postJson('/solicitacoes/configuracoes/salvar-equipamento', [
            'equipamentos' => [
                ['equipamento' => 'Teclado'],
            ],
        ]);
        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao_equip', ['equipamento' => 'Teclado']);

        // Editar (com id)
        $criado = SolicitacaoEquipamentos::where('equipamento', 'Teclado')->first();
        $response = $this->actingAs($user)->postJson('/solicitacoes/configuracoes/salvar-equipamento', [
            'equipamentos' => [
                ['id' => $criado->id, 'equipamento' => 'Teclado Mecânico'],
            ],
        ]);
        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao_equip', [
            'id' => $criado->id,
            'equipamento' => 'Teclado Mecânico',
        ]);
    }

    public function test_delete_equipamento(): void
    {
        $user = $this->userConfig();
        $equip = SolicitacaoEquipamentos::create(['equipamento' => 'Monitor']);

        $response = $this->actingAs($user)->postJson('/solicitacoes/configuracoes/remover-equipamento', [
            'idEquipamento' => $equip->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('intranet_solicitacao_equip', ['id' => $equip->id]);
    }

    // ─── Canais de notificação ────────────────────────────────────────────────────

    public function test_get_canais_notif(): void
    {
        $user = $this->userConfig();
        DB::table('INTRANET_NOTIF_CANAL')->insert(['canal' => 'EMAIL']);

        $response = $this->actingAs($user)->get('/solicitacoes/configuracoes/canais-notif');

        $response->assertOk();
        $response->assertJsonFragment(['canal' => 'EMAIL']);
        // O método cria o parâmetro GERAL com valor 0 quando não existe.
        $this->assertDatabaseHas('intranet_parametros', [
            'menu' => 'SOLICITACOES',
            'parametro' => 'NOTIFICACAO',
            'condicao1' => 'EMAIL',
            'condicao2' => 'GERAL',
        ]);
    }

    // ─── Etapas ───────────────────────────────────────────────────────────────────

    public function test_get_etapas(): void
    {
        $user = $this->userConfig();
        $dept = Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);
        $assunto = $this->novoAssunto($dept->id);
        SolicitacaoEtapa::create([
            'assunto_id' => $assunto->id,
            'nome' => 'Triagem',
            'ordem' => 0,
            'ativo' => 'S',
        ]);

        $response = $this->actingAs($user)->get("/solicitacoes/configuracoes/etapas/{$assunto->id}");

        $response->assertOk();
        $response->assertJsonFragment(['nome' => 'Triagem']);
    }

    public function test_salvar_etapas_cria(): void
    {
        $user = $this->userConfig();
        $dept = Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);
        $assunto = $this->novoAssunto($dept->id);

        $response = $this->actingAs($user)->postJson('/solicitacoes/configuracoes/salvar-etapas', [
            'assunto_id' => $assunto->id,
            'etapas' => [
                ['id' => null, 'nome' => 'Triagem'],
                ['id' => null, 'nome' => 'Em análise'],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('intranet_solicitacao_etapas', [
            'assunto_id' => $assunto->id,
            'nome' => 'Triagem',
            'ordem' => 0,
            'ativo' => 'S',
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_etapas', [
            'assunto_id' => $assunto->id,
            'nome' => 'Em análise',
            'ordem' => 1,
        ]);
    }

    // ─── Permissão (sem solicitacoes.configurar) ────────────────────────────────────

    public function test_configuracoes_exige_permissao_configurar(): void
    {
        $user = $this->userVisualizar();

        $this->actingAs($user)->get('/solicitacoes/configuracoes')->assertStatus(403);
        $this->actingAs($user)->get('/solicitacoes/configuracoes/departamentos')->assertStatus(403);
        $this->actingAs($user)->get('/solicitacoes/configuracoes/buscar-equipamentos')->assertStatus(403);
        $this->actingAs($user)->get('/solicitacoes/configuracoes/canais-notif')->assertStatus(403);
        $this->actingAs($user)->postJson('/solicitacoes/configuracoes/assuntos', ['departamento' => 1])->assertStatus(403);
        $this->actingAs($user)->postJson('/solicitacoes/configuracoes/toggle-ativo-assunto', ['assunto_id' => 1])->assertStatus(403);
    }

    // ─── Criação de ticket ──────────────────────────────────────────────────────────

    public function test_criar_solicitacao_persiste_ticket(): void
    {
        $user = $this->userVisualizar();
        $dept = Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);
        $assunto = $this->novoAssunto($dept->id, ['assunto' => 'Notebook novo']);

        $response = $this->actingAs($user)->postJson('/solicitacoes/nova/criar', [
            'titulo' => 'Preciso de um notebook',
            'descricao' => 'Notebook para novo colaborador',
            'departamento_responsavel' => $dept->name,
            'prioridade' => 'normal',
            'assunto_id' => $assunto->id,
            'filial_id' => null,
            'usuario_responsavel' => null,
            'departamento' => null,
            'filial' => null,
            'arquivos' => [],
            'rotinas' => [],
            'dadosLiberacao' => [],
            'infoVendas' => [],
            'equipamentos' => [],
            'usuariosDestino' => [],
            'selects' => [],
        ]);

        $response->assertOk();
        $response->assertJson(['sucesso' => true]);

        $this->assertDatabaseHas('intranet_solicitacao', [
            'titulo' => 'Preciso de um notebook',
            'descricao' => 'Notebook para novo colaborador',
            'usuario_solicitante' => $user->id,
            'assunto_id' => $assunto->id,
            'status' => 'pendente',
        ]);

        // Movimentação de criação registrada.
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'tipo_movimentacao' => 'Solicitação criada',
            'usuario_movimentacao' => $user->id,
        ]);
    }
}
