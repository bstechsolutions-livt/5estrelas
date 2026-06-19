<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\PayableRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableConciliacaoTest extends TestCase
{
    use RefreshDatabase;

    /** Usuário ativo com as permissões dadas (default: ver contas a pagar). */
    private function activeUser(array $keys = ['financeiro.contas_pagar.visualizar']): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id
            );
        }

        return $user;
    }

    /** Conciliador: usuário com permissão de ver o módulo + associado ao papel `conciliador`. */
    private function conciliador(bool $active = true): User
    {
        $user = $this->activeUser();
        if (! $active) {
            $user->update(['is_active' => false]);
        }
        PayableRole::create(['role' => 'conciliador', 'user_id' => $user->id]);

        return $user;
    }

    private function makePayable(string $status = 'pago'): Payable
    {
        return Payable::create([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 2500.00,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => $status,
        ]);
    }

    private function conciliarUrl(Payable $p): string
    {
        return "/financeiro/contas-pagar/{$p->id}/conciliar";
    }

    private function divergenciaUrl(Payable $p): string
    {
        return "/financeiro/contas-pagar/{$p->id}/divergencia";
    }

    // ─── CONCILIAÇÃO ──────────────────────────────────────────────────────

    public function test_conciliador_concilia_titulo_pago(): void
    {
        $conciliador = $this->conciliador();
        $payable = $this->makePayable('pago');

        $this->actingAs($conciliador)
            ->post($this->conciliarUrl($payable))
            ->assertRedirect();

        $this->assertDatabaseHas('payables', [
            'id' => $payable->id,
            'status' => 'conciliado',
            'conciliated_by' => $conciliador->id,
        ]);
        $this->assertDatabaseHas('payable_comments', [
            'payable_id' => $payable->id,
            'type' => 'conciliation',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'contas_pagar.conciliado',
            'auditable_id' => $payable->id,
        ]);
    }

    public function test_conciliador_com_observacao(): void
    {
        $conciliador = $this->conciliador();
        $payable = $this->makePayable('pago');

        $this->actingAs($conciliador)
            ->post($this->conciliarUrl($payable), ['notes' => 'Conferido com extrato do Banco X'])
            ->assertRedirect();

        $this->assertDatabaseHas('payables', [
            'id' => $payable->id,
            'status' => 'conciliado',
            'conciliation_notes' => 'Conferido com extrato do Banco X',
        ]);
    }

    public function test_conciliador_sem_observacao_funciona(): void
    {
        $conciliador = $this->conciliador();
        $payable = $this->makePayable('pago');

        $this->actingAs($conciliador)
            ->post($this->conciliarUrl($payable), ['notes' => null])
            ->assertRedirect();

        $this->assertDatabaseHas('payables', [
            'id' => $payable->id,
            'status' => 'conciliado',
            'conciliation_notes' => null,
        ]);
    }

    public function test_nao_conciliador_com_curinga_recebe_403(): void
    {
        // Tem '*' (acesso total), mas NÃO está na alçada como conciliador.
        $admin = $this->activeUser(['*']);
        $payable = $this->makePayable('pago');

        $this->actingAs($admin)
            ->post($this->conciliarUrl($payable))
            ->assertStatus(403);

        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'pago']);
    }

    public function test_conciliador_inativo_recebe_403(): void
    {
        $conciliador = $this->conciliador(active: false);
        $payable = $this->makePayable('pago');

        $this->actingAs($conciliador)
            ->post($this->conciliarUrl($payable))
            ->assertStatus(403);

        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'pago']);
    }

    public function test_titulo_nao_pago_nao_pode_ser_conciliado(): void
    {
        $conciliador = $this->conciliador();

        foreach (['pendente', 'aprovado', 'conciliado'] as $status) {
            $payable = $this->makePayable($status);

            $this->actingAs($conciliador)
                ->post($this->conciliarUrl($payable))
                ->assertRedirect()
                ->assertSessionHas('error');

            $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => $status]);
        }
    }

    public function test_conciliacao_idempotente(): void
    {
        $conciliador = $this->conciliador();
        $payable = $this->makePayable('pago');
        $url = $this->conciliarUrl($payable);

        // Primeira conciliação: sucesso
        $this->actingAs($conciliador)->post($url)->assertRedirect();
        // Segunda conciliação: recusada (status já é 'conciliado', não 'pago')
        $this->actingAs($conciliador)->post($url)->assertRedirect()->assertSessionHas('error');

        $this->assertEquals('conciliado', $payable->fresh()->status);
        $this->assertEquals(1, PayableComment::where('payable_id', $payable->id)->where('type', 'conciliation')->count());
        $this->assertEquals(1, AuditLog::where('event', 'contas_pagar.conciliado')->where('auditable_id', $payable->id)->count());
    }

    // ─── DIVERGÊNCIA ──────────────────────────────────────────────────────

    public function test_conciliador_registra_divergencia(): void
    {
        $conciliador = $this->conciliador();
        $payable = $this->makePayable('pago');

        $this->actingAs($conciliador)
            ->post($this->divergenciaUrl($payable), ['reason' => 'Valor no extrato difere do título em R$ 150,00'])
            ->assertRedirect();

        $this->assertDatabaseHas('payables', [
            'id' => $payable->id,
            'status' => 'divergente',
            'divergence_reason' => 'Valor no extrato difere do título em R$ 150,00',
        ]);
        $this->assertDatabaseHas('payable_comments', [
            'payable_id' => $payable->id,
            'type' => 'divergence',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'contas_pagar.divergente',
            'auditable_id' => $payable->id,
        ]);
    }

    public function test_divergencia_sem_motivo_retorna_422(): void
    {
        $conciliador = $this->conciliador();
        $payable = $this->makePayable('pago');

        $this->actingAs($conciliador)
            ->postJson($this->divergenciaUrl($payable), ['reason' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_divergencia_motivo_menor_que_10_chars_retorna_422(): void
    {
        $conciliador = $this->conciliador();
        $payable = $this->makePayable('pago');

        $this->actingAs($conciliador)
            ->postJson($this->divergenciaUrl($payable), ['reason' => 'Curto'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_divergencia_motivo_maior_que_1000_chars_retorna_422(): void
    {
        $conciliador = $this->conciliador();
        $payable = $this->makePayable('pago');

        $this->actingAs($conciliador)
            ->postJson($this->divergenciaUrl($payable), ['reason' => str_repeat('A', 1001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    // ─── SEM CONCILIADOR CONFIGURADO ──────────────────────────────────────

    public function test_sem_conciliador_configurado_ninguem_concilia(): void
    {
        // Ninguém na alçada como conciliador; mesmo com '*', 403.
        $admin = $this->activeUser(['*']);
        $payable = $this->makePayable('pago');

        $this->actingAs($admin)
            ->post($this->conciliarUrl($payable))
            ->assertStatus(403);

        $this->assertDatabaseHas('payables', ['id' => $payable->id, 'status' => 'pago']);
    }

    // ─── FILTRO DE STATUS NO INDEX ────────────────────────────────────────

    public function test_filtro_status_conciliado_na_index(): void
    {
        $user = $this->activeUser();
        $conciliado = $this->makePayable('conciliado');
        $pago = $this->makePayable('pago');

        $response = $this->actingAs($user)
            ->get('/financeiro/contas-pagar?status=conciliado')
            ->assertOk();

        $payables = $response->original->getData()['page']['props']['payables']['data'] ?? [];
        $ids = collect($payables)->pluck('id')->all();

        $this->assertContains($conciliado->id, $ids);
        $this->assertNotContains($pago->id, $ids);
    }

    public function test_filtro_status_divergente_na_index(): void
    {
        $user = $this->activeUser();
        $divergente = $this->makePayable('divergente');
        $pago = $this->makePayable('pago');

        $response = $this->actingAs($user)
            ->get('/financeiro/contas-pagar?status=divergente')
            ->assertOk();

        $payables = $response->original->getData()['page']['props']['payables']['data'] ?? [];
        $ids = collect($payables)->pluck('id')->all();

        $this->assertContains($divergente->id, $ids);
        $this->assertNotContains($pago->id, $ids);
    }
}
