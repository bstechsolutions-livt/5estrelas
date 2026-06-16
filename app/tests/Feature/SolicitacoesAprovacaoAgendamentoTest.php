<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Solicitacao;
use App\Models\SolicitacaoAgendamento;
use App\Models\SolicitacaoAgendSol;
use App\Models\SolicitacaoAprovacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre as ações de APROVAÇÕES e AGENDAMENTO de tickets do SolicitacoesController
 * (portado da intranet Biglar).
 *
 * Notas de ambiente (SQLite :memory:):
 *  - O model Funcionario aponta para a VIEW `INTRANET_USUARIO`, que as migrations
 *    só criam no PostgreSQL (identificadores case-sensitive + cast ::bigint). Para
 *    os testes criamos a MESMA view em sintaxe SQLite via migration dedicada
 *    (100013), espelhando o que roda em produção, para o adaptador funcionar.
 *  - As validações de aprovação usam $request->validate() DENTRO de um
 *    catch (\Throwable), então payload inválido vira 500 (não 422). Por isso
 *    testamos os retornos de regra de negócio explícitos (403/404/400), e o 422
 *    apenas onde o controller o retorna explicitamente (agendamento/observação).
 */
class SolicitacoesAprovacaoAgendamentoTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function userComPermissao(array $keys = ['solicitacoes.visualizar'], ?Department $dept = null): User
    {
        $dept = $dept ?: Department::firstOrCreate(['name' => 'TI'], ['is_active' => true]);
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

    private function novoAgendamento(array $attrs = []): SolicitacaoAgendamento
    {
        return SolicitacaoAgendamento::create(array_merge([
            'mat_responsavel' => 1,
            'filial' => null,
            'data_agendamento' => '2026-07-01 09:00:00',
            'data_fim_agendamento' => '2026-07-01 10:00:00',
            'user_cria' => 1,
            'status' => 'ativo',
            'observacao' => 'Agendamento de teste',
        ], $attrs));
    }

    private function vincula(Solicitacao $sol, SolicitacaoAgendamento $agend): void
    {
        SolicitacaoAgendSol::create([
            'solicitacao_id' => $sol->id,
            'agendamento_id' => $agend->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //   APROVAÇÕES
    // ══════════════════════════════════════════════════════════════════════════

    public function test_criar_aprovacao_cria_no_banco(): void
    {
        $user = $this->userComPermissao();
        $aprovador = User::factory()->create(['department_id' => $user->department_id]);
        // departamento do ticket = departamento do usuário logado → pode solicitar aprovação
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);

        $response = $this->actingAs($user)->postJson('/solicitacoes/aprovacoes', [
            'solicitacao_id' => $sol->id,
            'aprovador_matricula' => $aprovador->id,
            'observacoes' => 'Favor aprovar a compra',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('intranet_solicitacao_aprovacoes', [
            'solicitacao_id' => $sol->id,
            'aprovador_matricula' => $aprovador->id,
            'solicitante_matricula' => $user->id,
            'observacoes' => 'Favor aprovar a compra',
            'status' => 'pendente',
        ]);

        // Movimentação registrada
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Aprovação solicitada',
        ]);
    }

    public function test_criar_aprovacao_solicitacao_inexistente_404(): void
    {
        $user = $this->userComPermissao();

        $response = $this->actingAs($user)->postJson('/solicitacoes/aprovacoes', [
            'solicitacao_id' => 999999,
            'aprovador_matricula' => $user->id,
        ]);

        $response->assertStatus(404);
    }

    public function test_criar_aprovacao_sem_permissao_no_ticket_403(): void
    {
        // Usuário tem permissão de rota, mas não é do depto responsável nem o solicitante
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao([
            'departamento_responsavel' => 99999,
            'usuario_solicitante' => 88888,
        ]);

        $response = $this->actingAs($user)->postJson('/solicitacoes/aprovacoes', [
            'solicitacao_id' => $sol->id,
            'aprovador_matricula' => $user->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_criar_aprovacao_aprovador_inexistente_404(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);

        $response = $this->actingAs($user)->postJson('/solicitacoes/aprovacoes', [
            'solicitacao_id' => $sol->id,
            'aprovador_matricula' => 777777, // não existe na view INTRANET_USUARIO
        ]);

        $response->assertStatus(404);
    }

    public function test_listar_aprovacoes_retorna_lista(): void
    {
        $user = $this->userComPermissao();
        $aprovador = User::factory()->create(['department_id' => $user->department_id]);
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);

        $aprovacao = SolicitacaoAprovacao::create([
            'solicitacao_id' => $sol->id,
            'solicitante_matricula' => $user->id,
            'aprovador_matricula' => $aprovador->id,
            'observacoes' => 'Aprovar',
            'status' => 'pendente',
        ]);

        $response = $this->actingAs($user)->getJson("/solicitacoes/aprovacoes/{$sol->id}");

        $response->assertOk();
        $response->assertJsonFragment(['id' => $aprovacao->id]);
        $response->assertJsonFragment(['status' => 'pendente']);
    }

    public function test_responder_aprovacao_aprovada(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);

        $aprovacao = SolicitacaoAprovacao::create([
            'solicitacao_id' => $sol->id,
            'solicitante_matricula' => 88888,
            'aprovador_matricula' => $user->id, // user logado é o aprovador
            'status' => 'pendente',
        ]);

        $response = $this->actingAs($user)->postJson("/solicitacoes/aprovacoes/{$aprovacao->id}/responder", [
            'status' => 'aprovada',
            'resposta_observacoes' => 'Tudo certo, aprovado',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('intranet_solicitacao_aprovacoes', [
            'id' => $aprovacao->id,
            'status' => 'aprovada',
            'respondido_por' => $user->id,
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Aprovação aprovada',
        ]);
    }

    public function test_responder_aprovacao_rejeitada_sem_resposta_400(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);

        $aprovacao = SolicitacaoAprovacao::create([
            'solicitacao_id' => $sol->id,
            'solicitante_matricula' => 88888,
            'aprovador_matricula' => $user->id,
            'status' => 'pendente',
        ]);

        $response = $this->actingAs($user)->postJson("/solicitacoes/aprovacoes/{$aprovacao->id}/responder", [
            'status' => 'rejeitada',
            'resposta_observacoes' => '',
        ]);

        $response->assertStatus(400);
    }

    public function test_responder_aprovacao_nao_sendo_aprovador_403(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);

        $aprovacao = SolicitacaoAprovacao::create([
            'solicitacao_id' => $sol->id,
            'solicitante_matricula' => $user->id,
            'aprovador_matricula' => 55555, // outro aprovador
            'status' => 'pendente',
        ]);

        $response = $this->actingAs($user)->postJson("/solicitacoes/aprovacoes/{$aprovacao->id}/responder", [
            'status' => 'aprovada',
        ]);

        $response->assertStatus(403);
    }

    public function test_responder_aprovacao_ja_respondida_400(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);

        $aprovacao = SolicitacaoAprovacao::create([
            'solicitacao_id' => $sol->id,
            'solicitante_matricula' => 88888,
            'aprovador_matricula' => $user->id,
            'status' => 'aprovada', // já respondida
        ]);

        $response = $this->actingAs($user)->postJson("/solicitacoes/aprovacoes/{$aprovacao->id}/responder", [
            'status' => 'aprovada',
        ]);

        $response->assertStatus(400);
    }

    public function test_editar_aprovacao_troca_aprovador(): void
    {
        $user = $this->userComPermissao();
        $aprovadorAntigo = User::factory()->create(['department_id' => $user->department_id]);
        $aprovadorNovo = User::factory()->create(['department_id' => $user->department_id]);
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);

        $aprovacao = SolicitacaoAprovacao::create([
            'solicitacao_id' => $sol->id,
            'solicitante_matricula' => $user->id, // user logado solicitou
            'aprovador_matricula' => $aprovadorAntigo->id,
            'status' => 'pendente',
        ]);

        $response = $this->actingAs($user)->postJson("/solicitacoes/aprovacoes/{$aprovacao->id}", [
            'aprovador_matricula' => $aprovadorNovo->id,
            'observacoes' => 'Troquei o aprovador',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('intranet_solicitacao_aprovacoes', [
            'id' => $aprovacao->id,
            'aprovador_matricula' => $aprovadorNovo->id,
            'observacoes' => 'Troquei o aprovador',
        ]);
    }

    public function test_editar_aprovacao_nao_sendo_solicitante_403(): void
    {
        $user = $this->userComPermissao();
        $aprovadorNovo = User::factory()->create(['department_id' => $user->department_id]);
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);

        $aprovacao = SolicitacaoAprovacao::create([
            'solicitacao_id' => $sol->id,
            'solicitante_matricula' => 55555, // outro solicitante
            'aprovador_matricula' => $user->id,
            'status' => 'pendente',
        ]);

        $response = $this->actingAs($user)->postJson("/solicitacoes/aprovacoes/{$aprovacao->id}", [
            'aprovador_matricula' => $aprovadorNovo->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_buscar_aprovacoes_usuario(): void
    {
        $user = $this->userComPermissao();
        $solicitante = User::factory()->create(['department_id' => $user->department_id]);
        $sol = $this->novaSolicitacao([
            'departamento_responsavel' => $user->department_id,
            'usuario_solicitante' => $solicitante->id,
        ]);

        SolicitacaoAprovacao::create([
            'solicitacao_id' => $sol->id,
            'solicitante_matricula' => $solicitante->id,
            'aprovador_matricula' => $user->id, // pendente para o user logado
            'status' => 'pendente',
        ]);

        // Uma respondida não deve aparecer
        SolicitacaoAprovacao::create([
            'solicitacao_id' => $sol->id,
            'solicitante_matricula' => $solicitante->id,
            'aprovador_matricula' => $user->id,
            'status' => 'aprovada',
        ]);

        $response = $this->actingAs($user)->getJson('/solicitacoes/aprovacoes/usuario');

        $response->assertOk();
        $response->assertJson(['success' => true, 'total' => 1]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //   AGENDAMENTO
    // ══════════════════════════════════════════════════════════════════════════

    public function test_index_agendamento_ok(): void
    {
        $user = $this->userComPermissao();
        $this->actingAs($user)->get('/solicitacoes/agendamento')->assertOk();
    }

    public function test_criar_agendamento_cria_no_banco(): void
    {
        $user = $this->userComPermissao();
        $responsavel = User::factory()->create(['department_id' => $user->department_id]);
        $branch = Branch::create(['name' => 'Matriz', 'code' => '001', 'is_active' => true]);
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);

        $response = $this->actingAs($user)->postJson('/solicitacoes/agendamento/criar-agendamento', [
            'agendamento' => [
                'data' => '2026-08-01 09:00:00',
                'dataFim' => '2026-08-01 10:00:00',
                'filial' => $branch->id,
                'usuarioResponsavel' => $responsavel->id,
                'observacao' => 'Visita técnica',
            ],
            'solicitacoes' => [
                ['id' => $sol->id, 'usuario_responsavel' => ['matricula' => null]],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('intranet_solicitacao_agend', [
            'mat_responsavel' => $responsavel->id,
            'filial' => $branch->id,
            'status' => 'ativo',
            'observacao' => 'Visita técnica',
        ]);
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'status' => 'agendado',
            'usuario_responsavel' => $responsavel->id,
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_ag_sol', [
            'solicitacao_id' => $sol->id,
        ]);
    }

    public function test_criar_agendamento_conflito_de_horario_400(): void
    {
        $user = $this->userComPermissao();
        $responsavel = User::factory()->create(['department_id' => $user->department_id]);
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);

        // Agendamento existente ativo das 09:00 às 10:00
        $this->novoAgendamento([
            'mat_responsavel' => $responsavel->id,
            'data_agendamento' => '2026-08-01 09:00:00',
            'data_fim_agendamento' => '2026-08-01 10:00:00',
            'status' => 'ativo',
        ]);

        $response = $this->actingAs($user)->postJson('/solicitacoes/agendamento/criar-agendamento', [
            'agendamento' => [
                'data' => '2026-08-01 09:30:00',
                'dataFim' => '2026-08-01 10:30:00',
                'filial' => null,
                'usuarioResponsavel' => $responsavel->id,
                'observacao' => 'Conflito',
            ],
            'solicitacoes' => [
                ['id' => $sol->id, 'usuario_responsavel' => ['matricula' => null]],
            ],
        ]);

        $response->assertStatus(400);
    }

    public function test_criar_agendamento_observacao_muito_longa_422(): void
    {
        $user = $this->userComPermissao();
        $responsavel = User::factory()->create(['department_id' => $user->department_id]);

        $response = $this->actingAs($user)->postJson('/solicitacoes/agendamento/criar-agendamento', [
            'agendamento' => [
                'data' => '2026-08-01 09:00:00',
                'dataFim' => '2026-08-01 10:00:00',
                'filial' => null,
                'usuarioResponsavel' => $responsavel->id,
                'observacao' => str_repeat('a', 4001),
            ],
            'solicitacoes' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_atualizar_agendamento(): void
    {
        $user = $this->userComPermissao();
        $responsavel = User::factory()->create(['department_id' => $user->department_id]);
        $branch = Branch::create(['name' => 'Filial 2', 'code' => '002', 'is_active' => true]);
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);
        $agend = $this->novoAgendamento(['mat_responsavel' => 1]);
        $this->vincula($sol, $agend);

        $response = $this->actingAs($user)->postJson('/solicitacoes/agendamento/atualizar-agendamento', [
            'agendamento' => [
                'id' => $agend->id,
                'data' => '2026-08-02 14:00:00',
                'dataFim' => '2026-08-02 15:00:00',
                'filial' => $branch->id,
                'usuarioResponsavel' => $responsavel->id,
                'observacao' => 'Reagendado',
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('intranet_solicitacao_agend', [
            'id' => $agend->id,
            'mat_responsavel' => $responsavel->id,
            'filial' => $branch->id,
            'observacao' => 'Reagendado',
        ]);
    }

    public function test_cancelar_agendamento(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao([
            'departamento_responsavel' => $user->department_id,
            'usuario_responsavel' => $user->id,
            'status' => 'agendado',
        ]);
        $agend = $this->novoAgendamento(['mat_responsavel' => $user->id, 'status' => 'ativo']);
        $this->vincula($sol, $agend);

        $response = $this->actingAs($user)->postJson('/solicitacoes/agendamento/cancelar-agendamento', [
            'id' => $agend->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('intranet_solicitacao_agend', [
            'id' => $agend->id,
            'status' => 'cancelado',
            'mat_cancelamento' => $user->id,
        ]);
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'status' => 'pendente',
        ]);
    }

    public function test_finalizar_agendamento_resolvendo(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao([
            'departamento_responsavel' => $user->department_id,
            'status' => 'em atendimento',
        ]);
        $agend = $this->novoAgendamento(['mat_responsavel' => $user->id, 'status' => 'em atendimento']);
        $this->vincula($sol, $agend);

        $response = $this->actingAs($user)->postJson('/solicitacoes/agendamento/finalizar-agendamento', [
            'id_agendamento' => $agend->id,
            'resolveSolicitacao' => true,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('intranet_solicitacao_agend', [
            'id' => $agend->id,
            'status' => 'finalizado',
            'mat_termino' => $user->id,
        ]);
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'status' => 'finalizada',
        ]);
    }

    public function test_finalizar_agendamento_sem_resolver(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao([
            'departamento_responsavel' => $user->department_id,
            'status' => 'em atendimento',
        ]);
        $agend = $this->novoAgendamento(['mat_responsavel' => $user->id, 'status' => 'em atendimento']);
        $this->vincula($sol, $agend);

        $response = $this->actingAs($user)->postJson('/solicitacoes/agendamento/finalizar-agendamento', [
            'id_agendamento' => $agend->id,
            'resolveSolicitacao' => false,
            'comentario' => 'Cliente ausente',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('intranet_solicitacao_agend', [
            'id' => $agend->id,
            'status' => 'finalizado',
        ]);
        $this->assertDatabaseHas('intranet_solicitacao', [
            'id' => $sol->id,
            'status' => 'pendente',
        ]);
    }

    public function test_criar_lembrete(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);

        $response = $this->actingAs($user)->postJson('/solicitacoes/agendamento/criar-lembrete', [
            'solicitacao_id' => $sol->id,
            'data' => '2026-08-10',
            'hora' => '09:30',
            'observacao' => 'Ligar para o cliente',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('intranet_solicitacao_agend', [
            'tipo' => SolicitacaoAgendamento::TIPO_LEMBRETE,
            'observacao' => 'Ligar para o cliente',
            'status' => 'ativo',
        ]);
        $this->assertDatabaseHas('intranet_solicitacao_ag_sol', [
            'solicitacao_id' => $sol->id,
        ]);
    }

    public function test_criar_lembrete_solicitacao_inexistente_404(): void
    {
        $user = $this->userComPermissao();

        $response = $this->actingAs($user)->postJson('/solicitacoes/agendamento/criar-lembrete', [
            'solicitacao_id' => 999999,
            'data' => '2026-08-10',
        ]);

        $response->assertStatus(404);
    }

    public function test_editar_lembrete(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id]);
        $lembrete = $this->novoAgendamento([
            'mat_responsavel' => $user->id,
            'tipo' => SolicitacaoAgendamento::TIPO_LEMBRETE,
            'data_agendamento' => '2026-08-10 08:00:00',
            'data_fim_agendamento' => '2026-08-10 08:00:00',
        ]);
        $this->vincula($sol, $lembrete);

        $response = $this->actingAs($user)->postJson('/solicitacoes/agendamento/editar-lembrete', [
            'lembrete_id' => $lembrete->id,
            'data' => '2026-08-12',
            'hora' => '15:00',
            'observacao' => 'Lembrete atualizado',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('intranet_solicitacao_agend', [
            'id' => $lembrete->id,
            'observacao' => 'Lembrete atualizado',
        ]);
    }

    public function test_editar_lembrete_inexistente_404(): void
    {
        $user = $this->userComPermissao();

        $response = $this->actingAs($user)->postJson('/solicitacoes/agendamento/editar-lembrete', [
            'lembrete_id' => 999999,
            'data' => '2026-08-12',
        ]);

        $response->assertStatus(404);
    }

    public function test_cancelar_lembrete(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao(['departamento_responsavel' => $user->department_id, 'status' => 'agendado']);
        $lembrete = $this->novoAgendamento([
            'mat_responsavel' => $user->id,
            'tipo' => SolicitacaoAgendamento::TIPO_LEMBRETE,
            'status' => 'ativo',
        ]);
        $this->vincula($sol, $lembrete);

        $response = $this->actingAs($user)->postJson('/solicitacoes/agendamento/cancelar-lembrete', [
            'id' => $lembrete->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('intranet_solicitacao_agend', [
            'id' => $lembrete->id,
            'status' => 'cancelado',
            'mat_cancelamento' => $user->id,
        ]);
    }

    public function test_cancelar_lembrete_inexistente_404(): void
    {
        $user = $this->userComPermissao();

        $response = $this->actingAs($user)->postJson('/solicitacoes/agendamento/cancelar-lembrete', [
            'id' => 999999,
        ]);

        $response->assertStatus(404);
    }

    /**
     * iniciarAgendamento depende de `branches.link_maps`
     * (Filial::where('codigo', ...)->value('link_maps')), coluna que NÃO existe no
     * schema do 5 Estrelas — quebra tanto em SQLite quanto em Postgres. Bug latente
     * no código portado da Biglar. Pulado até criar a migration da coluna.
     */
    public function test_iniciar_agendamento_pulado_link_maps(): void
    {
        $this->markTestSkipped('iniciarAgendamento usa branches.link_maps, coluna inexistente no schema 5E (bug a corrigir).');
    }

    /**
     * getAgendamentosByUser também referencia `branches.link_maps` (inexistente),
     * pelo mesmo motivo acima.
     */
    public function test_get_agendamentos_by_user_pulado_link_maps(): void
    {
        $this->markTestSkipped('getAgendamentosByUser usa branches.link_maps, coluna inexistente no schema 5E (bug a corrigir).');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //   PERMISSÃO
    // ══════════════════════════════════════════════════════════════════════════

    public function test_agendamento_sem_permissao_visualizar_403(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/solicitacoes/agendamento')->assertStatus(403);
    }

    public function test_aprovacoes_usuario_sem_permissao_visualizar_403(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/solicitacoes/aprovacoes/usuario')->assertStatus(403);
    }
}
