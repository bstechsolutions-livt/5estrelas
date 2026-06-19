<?php

namespace Tests\Browser;

use App\Models\Payable;
use App\Models\PayableRole;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) da "Conciliação Bancária" no detalhe do título.
 * Cobre desktop (Dialog) e mobile (bottom sheet), read-only para conciliado/divergente,
 * e o botão ausente quando o título não está em status pago.
 */
class PayableConciliacaoTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    private function makePayable(string $status, array $extra = []): Payable
    {
        return Payable::create(array_merge([
            'title_number' => 'DUSK-CONC-' . uniqid(),
            'supplier_name' => 'Fornecedor Dusk Conciliacao',
            'amount' => 1500.00,
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => $status,
        ], $extra));
    }

    private function ensureConciliador(): void
    {
        PayableRole::firstOrCreate(['role' => 'conciliador', 'user_id' => $this->bruno()->id]);
    }

    public function test_desktop_conciliar_titulo_pago(): void
    {
        $this->ensureConciliador();
        $p = $this->makePayable('pago');

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Conciliacao', 10)
                ->waitFor('@open-conciliation', 10)
                ->click('@open-conciliation')
                ->waitFor('@action-conciliate', 5)
                ->click('@action-conciliate')
                ->waitFor('@conciliation-notes', 5)
                ->waitFor('@confirm-conciliation', 5)
                ->click('@confirm-conciliation')
                ->waitForText('Pronto', 10)
                ->waitFor('@conciliation-info', 10)
                ->assertPresent('@conciliation-info');
        });

        $this->assertDatabaseHas('payables', ['id' => $p->id, 'status' => 'conciliado']);
        $p->delete();
    }

    public function test_desktop_registrar_divergencia(): void
    {
        $this->ensureConciliador();
        $p = $this->makePayable('pago');

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Conciliacao', 10)
                ->waitFor('@open-conciliation', 10)
                ->click('@open-conciliation')
                ->waitFor('@action-diverge', 5)
                ->click('@action-diverge')
                ->waitFor('@divergence-reason', 5)
                ->type('@divergence-reason', 'Valor divergente no extrato bancário - diferença de R$50')
                ->waitFor('@confirm-divergence', 5)
                ->click('@confirm-divergence')
                ->waitForText('Pronto', 10)
                ->waitFor('@divergence-info', 10)
                ->assertPresent('@divergence-info');
        });

        $this->assertDatabaseHas('payables', ['id' => $p->id, 'status' => 'divergente']);
        $p->delete();
    }

    public function test_mobile_conciliar_via_bottom_sheet(): void
    {
        $this->ensureConciliador();
        $p = $this->makePayable('pago');

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->resize(375, 800)
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Conciliacao', 10)
                ->waitFor('@open-conciliation', 10);

            $browser->script("document.querySelector('[dusk=open-conciliation]').scrollIntoView({block:'center'})");
            $browser->pause(300)
                ->click('@open-conciliation')
                ->waitFor('@conciliation-sheet', 5)
                ->assertPresent('@conciliation-sheet')
                ->click('@action-conciliate')
                ->waitFor('@confirm-conciliation', 5)
                ->click('@confirm-conciliation')
                ->waitForText('Pronto', 10);
        });

        $this->assertDatabaseHas('payables', ['id' => $p->id, 'status' => 'conciliado']);
        $p->delete();
    }

    public function test_botao_conciliar_ausente_quando_nao_pago(): void
    {
        $this->ensureConciliador();
        $p = $this->makePayable('pendente');

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Conciliacao', 10)
                ->assertMissing('@open-conciliation');
        });

        $p->delete();
    }

    public function test_readonly_conciliado_mostra_info(): void
    {
        $this->ensureConciliador();
        $p = $this->makePayable('conciliado', [
            'conciliated_at' => now()->toDateString(),
            'conciliated_by' => $this->bruno()->id,
            'conciliation_notes' => 'Conferido com extrato Itaú',
        ]);

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Conciliacao', 10)
                ->waitFor('@conciliation-info', 10)
                ->assertPresent('@conciliation-info');
        });

        $p->delete();
    }

    public function test_readonly_divergente_mostra_info(): void
    {
        $this->ensureConciliador();
        $p = $this->makePayable('divergente', [
            'conciliated_at' => now()->toDateString(),
            'conciliated_by' => $this->bruno()->id,
            'divergence_reason' => 'Pagamento em duplicidade detectado no extrato',
        ]);

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Conciliacao', 10)
                ->waitFor('@divergence-info', 10)
                ->assertPresent('@divergence-info');
        });

        $p->delete();
    }
}
