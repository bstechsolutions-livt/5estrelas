<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Solicitacao;
use App\Models\SolicitacaoCom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Módulo de Tickets (Solicitações) — portado da intranet Biglar.
 * Cobre as telas (index), o redirect legado /fila → /lista, permissões e comentários.
 */
class SolicitacoesTest extends TestCase
{
    use RefreshDatabase;

    private function userComPermissao(array $keys = ['solicitacoes.visualizar']): User
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

    // ─── Redirect legado ─────────────────────────────────────────────────────────

    public function test_fila_redireciona_para_lista(): void
    {
        $user = $this->userComPermissao();

        $this->actingAs($user)->get('/solicitacoes/fila')
            ->assertRedirect('/solicitacoes/lista');
    }

    // ─── Telas (index) ─────────────────────────────────────────────────────────

    public function test_index_lista_renderiza(): void
    {
        $user = $this->userComPermissao(['solicitacoes.visualizar', 'solicitacoes.lista.ver-todos-depto']);

        $response = $this->actingAs($user)->get('/solicitacoes/lista');

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Solicitacoes/Lista/Index', false)
                ->has('departamentos')
                ->has('usuarioLogado')
        );
    }

    public function test_index_nova_renderiza(): void
    {
        $user = $this->userComPermissao();
        $this->actingAs($user)->get('/solicitacoes/nova')->assertOk();
    }

    public function test_index_minhas_renderiza(): void
    {
        $user = $this->userComPermissao();
        $this->actingAs($user)->get('/solicitacoes/minhas')->assertOk();
    }

    public function test_index_dashboard_renderiza(): void
    {
        $user = $this->userComPermissao();
        $this->actingAs($user)->get('/solicitacoes/dashboard')->assertOk();
    }

    public function test_index_relatorios_renderiza(): void
    {
        $user = $this->userComPermissao();
        $this->actingAs($user)->get('/solicitacoes/relatorios')->assertOk();
    }

    public function test_index_agendamento_renderiza(): void
    {
        $user = $this->userComPermissao();
        $this->actingAs($user)->get('/solicitacoes/agendamento')->assertOk();
    }

    // ─── Permissão ───────────────────────────────────────────────────────────────

    public function test_lista_exige_permissao_visualizar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/solicitacoes/lista')->assertStatus(403);
    }

    public function test_fila_exige_permissao_visualizar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/solicitacoes/fila')->assertStatus(403);
    }

    // ─── Comentar ─────────────────────────────────────────────────────────────────

    public function test_comentar_cria_comentario(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao();

        $response = $this->actingAs($user)->postJson('/solicitacoes/lista/comentar', [
            'solicitacao' => ['id' => $sol->id, 'status' => 'aberto'],
            'comentario' => 'Primeiro comentário do atendimento',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('intranet_solicitacao_com', [
            'solicitacao_id' => $sol->id,
            'usuario' => $user->id,
            'comentario' => 'Primeiro comentário do atendimento',
        ]);
    }

    public function test_iniciar_atendimento_muda_status(): void
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
        // Movimentação de início registrada.
        $this->assertDatabaseHas('intranet_solicitacao_mov', [
            'solicitacao_id' => $sol->id,
            'tipo_movimentacao' => 'Inicio de atendimento',
        ]);
    }

    public function test_excluir_comentario_de_outro_usuario_proibido(): void
    {
        $user = $this->userComPermissao();
        $sol = $this->novaSolicitacao();
        $comentario = SolicitacaoCom::create([
            'solicitacao_id' => $sol->id,
            'usuario' => 999, // outro usuário
            'comentario' => 'Comentário alheio',
        ]);

        $this->actingAs($user)->deleteJson("/solicitacoes/lista/comentario/{$comentario->id}")
            ->assertStatus(403);
    }
}
