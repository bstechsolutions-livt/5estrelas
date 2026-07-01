<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A4 — Trava de vencimento +72h automático + edição restrita ao financeiro.
 *
 * Decisão: novo lançamento manual (sem origem Senior) sem vencimento recebe
 * vencimento = base + 3 dias corridos, rolando para o próximo dia útil se cair
 * em fim de semana. Só o financeiro pode alterar o vencimento.
 */
class PayableVencimentoTest extends TestCase
{
    use RefreshDatabase;

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

    private function makePayable(array $attrs = []): Payable
    {
        return Payable::create(array_merge([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 1000.00,
            'status' => 'pendente',
        ], $attrs));
    }

    // ─── +72h automático ─────────────────────────────────────────────────

    public function test_default_due_date_soma_3_dias_uteis_do_exemplo(): void
    {
        // Exemplo do cliente: lançamento 29/06/2026 (segunda) → vencimento 02/07 (quinta).
        $this->assertSame('2026-07-02', Payable::defaultDueDate('2026-06-29')->toDateString());
    }

    public function test_default_due_date_rola_fim_de_semana_para_dia_util(): void
    {
        // 02/07/2026 (quinta) + 3 = 05/07 (domingo) → rola para 06/07 (segunda).
        $this->assertSame('2026-07-06', Payable::defaultDueDate('2026-07-02')->toDateString());
    }

    public function test_lancamento_manual_sem_vencimento_recebe_72h(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-29')); // segunda
        $payable = $this->makePayable(); // sem due_date, sem senior_id
        Carbon::setTestNow();

        $this->assertSame('2026-07-02', $payable->due_date->toDateString());
    }

    public function test_vencimento_informado_nao_e_sobrescrito(): void
    {
        $payable = $this->makePayable(['due_date' => '2026-12-25']);
        $this->assertSame('2026-12-25', $payable->due_date->toDateString());
    }

    public function test_titulo_da_senior_nao_recebe_72h(): void
    {
        // Título com origem Senior mantém o vencimento real (não aplica +72h).
        Carbon::setTestNow(Carbon::parse('2026-06-29'));
        $payable = $this->makePayable(['senior_id' => 'SR-' . uniqid(), 'due_date' => '2027-01-15']);
        Carbon::setTestNow();

        $this->assertSame('2027-01-15', $payable->due_date->toDateString());
    }

    // ─── Edição restrita ao financeiro ───────────────────────────────────

    private function url(Payable $p): string
    {
        return "/financeiro/contas-pagar/{$p->id}/vencimento";
    }

    public function test_financeiro_altera_vencimento(): void
    {
        $user = $this->activeUser(['financeiro.contas_pagar.visualizar', 'financeiro.contas_pagar.editar_vencimento']);
        $payable = $this->makePayable(['due_date' => '2026-07-02']);

        $this->actingAs($user)
            ->post($this->url($payable), ['due_date' => '2026-07-10'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('2026-07-10', $payable->fresh()->due_date->toDateString());
        $this->assertDatabaseHas('audit_logs', ['event' => 'contas_pagar.vencimento_alterado', 'auditable_id' => $payable->id]);
    }

    public function test_sem_permissao_nao_altera_vencimento(): void
    {
        // Tem acesso ao módulo (passa o middleware) mas não a permissão de editar vencimento.
        $user = $this->activeUser(['financeiro.contas_pagar.visualizar']);
        $payable = $this->makePayable(['due_date' => '2026-07-02']);

        $this->actingAs($user)
            ->post($this->url($payable), ['due_date' => '2026-07-10'])
            ->assertStatus(403);

        $this->assertSame('2026-07-02', $payable->fresh()->due_date->toDateString());
    }

    public function test_vencimento_invalido_retorna_422(): void
    {
        $user = $this->activeUser(['financeiro.contas_pagar.visualizar', 'financeiro.contas_pagar.editar_vencimento']);
        $payable = $this->makePayable(['due_date' => '2026-07-02']);

        $this->actingAs($user)
            ->postJson($this->url($payable), ['due_date' => 'nao-e-data'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    }
}
