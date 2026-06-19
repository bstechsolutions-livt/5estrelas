<?php

namespace Tests\Browser;

use App\Models\Payable;
use App\Models\PayableRole;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) do "Registrar pagamento" no detalhe do título.
 * Cobre desktop (Dialog) e mobile (bottom sheet), além do botão ausente quando
 * o título não está aprovado. Bruno é incluído na alçada como pagador.
 */
class PayablePagamentoTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    private function makePayable(string $status): Payable
    {
        return Payable::create([
            'title_number' => 'DUSK-' . uniqid(),
            'supplier_name' => 'Fornecedor Dusk Pgto',
            'amount' => 999.99,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => $status,
        ]);
    }

    public function test_pagador_registra_pagamento_no_desktop(): void
    {
        PayableRole::firstOrCreate(['role' => 'pagador', 'user_id' => $this->bruno()->id]);
        $p = $this->makePayable('aprovado');

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Pgto', 10)
                ->waitFor('@open-payment', 10)
                ->click('@open-payment')
                ->waitForText('Registrar pagamento', 5)
                ->waitFor('@confirm-payment', 5)
                ->click('@confirm-payment')
                ->waitForText('Pagamento registrado', 10)
                ->waitFor('@payment-info', 10)
                ->assertPresent('@payment-info');
        });

        $this->assertDatabaseHas('payables', ['id' => $p->id, 'status' => 'pago', 'paid_by' => $this->bruno()->id]);
        $p->delete();
    }

    public function test_pagador_registra_pagamento_no_mobile_bottom_sheet(): void
    {
        PayableRole::firstOrCreate(['role' => 'pagador', 'user_id' => $this->bruno()->id]);
        $p = $this->makePayable('aprovado');

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->resize(390, 844)
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Pgto', 10)
                ->waitFor('@open-payment', 10);
            // Centraliza o botão na viewport para o bottom nav fixo não interceptar o clique.
            $browser->script("document.querySelector('[dusk=open-payment]').scrollIntoView({block:'center'})");
            $browser->pause(300)
                ->click('@open-payment')
                ->waitFor('@payment-sheet', 5)
                ->assertPresent('@payment-sheet')
                ->click('@confirm-payment')
                ->waitForText('Pagamento registrado', 10);
        });

        $this->assertDatabaseHas('payables', ['id' => $p->id, 'status' => 'pago']);
        $p->delete();
    }

    public function test_botao_pagamento_ausente_quando_nao_aprovado(): void
    {
        PayableRole::firstOrCreate(['role' => 'pagador', 'user_id' => $this->bruno()->id]);
        $p = $this->makePayable('pendente');

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Pgto', 10)
                ->assertMissing('@open-payment');
        });

        $p->delete();
    }
}
