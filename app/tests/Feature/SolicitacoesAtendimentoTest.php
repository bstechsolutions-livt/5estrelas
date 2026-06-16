<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ciclo de ATENDIMENTO de tickets (Solicitações) — SolicitacoesController.
 *
 * Cobre as ações da tela /solicitacoes/lista que movimentam o chamado pelo
 * fluxo de atendimento (iniciar, pausar, resolver, recusar, finalizar, cancelar,
 * retorno ao solicitante), além de mudança de prioridade/responsável, troca de
 * departamento/solicitante, previsão de entrega, consultas (busca, detalhe,
 * departamentos, possui-resolvidas) e checagem de permissão (403).
 *
 * NOTA SQLite: o módulo foi portado da Biglar e depende da view legada
 * `INTRANET_USUARIO` (model `Funcionario`), que as migrations só criam no
 * PostgreSQL. Os testes rodam em SQLite :memory:, então recriamos uma view
 * equivalente no setUp (mapeando `users`) para que os métodos que carregam
 * relações de pessoa (getSolicitacoes/getSolicitacao/mudarResponsavel/
 * alterarSolicitante) funcionem igual à produção.
 */
class SolicitacoesAtendimentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Recria a view legada INTRANET_USUARIO no SQLite (espelha a versão Postgres
        // das migrations 100003/100004/100005, sem os casts ::bigint/::text).
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('CREATE VIEW IF NOT EXISTS "INTRANET_USUARIO" AS
                SELECT
                    id,
                    id AS matricula,
                    name AS nome,
                    name,
                    email,
                    NULL AS fone,
                    department_id,
                    department_id AS areaatuacao,
                    department_id AS departamento,
                    CASE WHEN is_active THEN \'A\' ELSE \'I\' END AS situacao,
                    NULL AS foto_perfil_id,
                    avatar_path
                FROM users');
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function userComPermissao(array $keys = ['solicitacoes.visualizar', 'solicitacoes.lista.ver-todos-depto']): User
    {
        $dept = Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);
        $user = User::factory()->create(['department_id' => $dept->id]);
        foreach ($keys as $key) {
            $perm = Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'solicitacoes']);
            $user->permissions()->attach($perm->id);
        }

        return $user;
    }

    private function novaSolicitacao(array $attrs = []): Solicitacao
    {
        $dept = Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);

        return Solicitacao::create(array_merge([
            'titulo' => 'Ticket Teste',
            'descricao' => 'Descrição do ticket',
            'status' => 'aberto',
            'prioridade' => 'normal',
            'usuario_solicitante' => 1,
            'departamento_responsavel' => $dept->id,
        ], $attrs));
    }

    // ─── Consultas ────────────────────────────────────────────────────────────────

    public function test_get_solicitacoes_retorna_tickets(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['usuario_solicitante' => $user->id]);

        // Com ver-todos-depto + filtro por id, a query devolve o ticket.
        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/buscar-solicitacoes', [
            'id' => $sol->id,
            'porPagina' => 10,
            'pagina' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('solicitacoes.data.0.id', $sol->id);
    }

    public function test_get_solicitacao_retorna_um_ticket(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['usuario_solicitante' => $user->id]);

        $response = $this->actingAs($user)->getJson("/solicitacoes/lista/solicitacao/{$sol->id}");

        $response->assertOk();
        $response->assertJsonPath('id', $sol->id);
        $response->assertJsonPath('titulo', 'Ticket Teste');
    }

    public function test_get_depto_ativo_retorna_departamentos(): void
    {
        $user = $this->userComPermissao();
        // Garante ao menos um departamento ativo.
        Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);

        $response = $this->actingAs($user)->getJson('/solicitacoes/lista/buscar-departamentos');

        $response->assertOk();
        $response->assertJsonStructure(['departamentos']);
    }

    public function test_possui_resolvidas_retorna_true_quando_existe(): void
    {
        $user = $this->userComPermissao();
        $this->novaSolicitacao(['usuario_solicitante' => $user->id, 'status' => 'resolvida']);

        $response = $this->actingAs($user)->getJson('/solicitacoes/possui-resolvidas');

        $response->assertOk();
        $this->assertTrue($response->json());
    }

    public function test_possui_resolvidas_retorna_false_sem_resolvidas(): void
    {
        $user = $this->userComPermissao();

        $response = $this->actingAs($user)->getJson('/solicitacoes/possui-resolvidas');

        $response->assertOk();
        // Body é o literal `false` — TestResponse::json() rejeita `false`, então comparamos o conteúdo cru.
        $this->assertSame('false', $response->getContent());
    }

    // ─── Prioridade / Responsável ──────────────────────────────────────────────────

    public function test_mudar_prioridade_atualiza_banco(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['prioridade' => 'baixa']);

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/mudar-prioridade', [
            'solicitacao' => ['id' => $sol->id],
            'novaPrioridade' => 'urgente',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'prioridade' => 'urgente',
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Mudança de prioridade',
        ]);
    }

    public function test_mudar_responsavel_atribui_usuario(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao();

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/mudar-responsavel', [
            'solicitacao' => ['id' => $sol->id],
            'responsavel' => $user->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'usuario_responsavel' => $user->id,
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Mudança de responsável',
        ]);
    }

    // ─── Ciclo de atendimento ───────────────────────────────────────────────────────

    public function test_iniciar_atendimento_muda_status_e_cria_movimentacao(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['status' => 'aberto']);

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/iniciar-atendimento', [
            'solicitacao' => ['id' => $sol->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'status' => 'em atendimento',
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Inicio de atendimento',
        ]);
    }

    public function test_pausar_atendimento_muda_status(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['status' => 'em atendimento']);

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/pausar-atendimento', [
            'solicitacao' => ['id' => $sol->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'status' => 'atendimento pausado',
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Pausa de atendimento',
        ]);
    }

    public function test_resolver_atendimento_muda_status_e_conclui(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['status' => 'em atendimento']);

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/resolver-atendimento', [
            'solicitacao' => ['id' => $sol->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'status' => 'resolvida',
        ]);
        $this->assertNotNull($sol->fresh()->data_conclusao);
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Atendimento resolvido',
        ]);
    }

    public function test_recusar_atendimento_muda_status_e_comenta(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['status' => 'resolvida']);

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/recusar-atendimento', [
            'solicitacao' => ['id' => $sol->id],
            'comentario' => 'Não foi isso que pedi',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'status' => 'resolução recusada',
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Resolução recusada',
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_com', [
            'solicitacao_id' => $sol->id,
            'usuario' => $user->id,
        ]);
    }

    public function test_finalizar_atendimento_muda_status(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['status' => 'resolvida']);

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/finalizar-atendimento', [
            'solicitacao' => ['id' => $sol->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'status' => 'finalizada',
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Atendimento finalizado',
        ]);
    }

    public function test_cancelar_atendimento_muda_status(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['status' => 'em atendimento']);

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/cancelar-atendimento', [
            'solicitacao' => ['id' => $sol->id],
            'comentario' => 'Não é mais necessário',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'status' => 'cancelada',
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Solicitação cancelada',
        ]);
    }

    public function test_retorno_solicitante_muda_status(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['status' => 'em atendimento']);

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/retorno-solicitante', [
            'solicitacao' => ['id' => $sol->id],
            'comentario' => 'Preciso de mais informações',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'status' => 'retorno solicitante',
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Retorno ao solicitante',
        ]);
    }

    // ─── Previsão de entrega ─────────────────────────────────────────────────────────

    public function test_atualizar_previsao_entrega_pelo_responsavel(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao([
            'status' => 'em atendimento',
            'usuario_responsavel' => $user->id,
        ]);
        $previsao = now()->addDays(3)->format('Y-m-d');

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/atualizar-previsao-entrega', [
            'solicitacao_id' => $sol->id,
            'previsao_entrega' => $previsao,
        ]);

        $response->assertOk();
        $this->assertNotNull($sol->fresh()->previsao_entrega);
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Previsão de entrega atualizada',
        ]);
    }

    public function test_atualizar_previsao_entrega_negada_para_nao_responsavel(): void
    {
        $user = $this->userComPermissao();
        // Responsável é outro usuário.
        $sol = $this->novaSolicitacao([
            'status' => 'em atendimento',
            'usuario_responsavel' => 999999,
        ]);

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/atualizar-previsao-entrega', [
            'solicitacao_id' => $sol->id,
            'previsao_entrega' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertStatus(403);
    }

    // ─── Departamento / Solicitante ───────────────────────────────────────────────────

    public function test_alterar_departamento_atualiza_responsavel(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao();
        $novoDepto = Department::firstOrCreate(['name' => 'Financeiro'], ['is_active' => true]);

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/alterar-departamento', [
            'solicitacao_id' => $sol->id,
            'deptoSelecionado' => (string) $novoDepto->id,
            'assunto_id' => null,
            'comentario' => 'Pertence ao financeiro',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'departamento_responsavel' => $novoDepto->id,
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Departamento responsável foi alterado.',
        ]);
    }

    public function test_alterar_solicitante_atualiza_banco(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['usuario_solicitante' => $user->id]);
        // Novo solicitante precisa existir como "Funcionario" (view INTRANET_USUARIO).
        $novoSolicitante = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/alterar-solicitante', [
            'solicitacao_id' => $sol->id,
            'novo_solicitante' => $novoSolicitante->id,
            'comentario' => 'Solicitante correto',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'usuario_solicitante' => $novoSolicitante->id,
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Solicitante foi alterado',
        ]);
    }

    // ─── Permissão (403) ───────────────────────────────────────────────────────────────

    public function test_buscar_solicitacoes_sem_permissao_403(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/solicitacoes/lista/buscar-solicitacoes', [])
            ->assertStatus(403);
    }

    public function test_get_solicitacao_sem_permissao_403(): void
    {
        $user = User::factory()->create();
        $sol = $this->novaSolicitacao();
        $this->actingAs($user)->getJson("/solicitacoes/lista/solicitacao/{$sol->id}")
            ->assertStatus(403);
    }

    public function test_iniciar_atendimento_sem_permissao_403(): void
    {
        $user = User::factory()->create();
        $sol = $this->novaSolicitacao();
        $this->actingAs($user)->postJson('/solicitacoes/lista/iniciar-atendimento', [
            'solicitacao' => ['id' => $sol->id],
        ])->assertStatus(403);
    }

    public function test_possui_resolvidas_sem_permissao_403(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/solicitacoes/possui-resolvidas')
            ->assertStatus(403);
    }
}
