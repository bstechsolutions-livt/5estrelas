<?php

namespace Tests\Browser;

use App\Models\Payable;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * A4 — Edição de vencimento restrita ao financeiro (browser).
 *
 * bruno@bstechsolutions.com tem permissão wildcard, então vê o botão de editar
 * vencimento. Abre o dialog, salva e confirma o feedback de sucesso.
 */
class PayableVencimentoTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_financeiro_edita_vencimento_pela_tela(): void
    {
        $p = Payable::create([
            'title_number' => 'DUSK-VENC-' . uniqid(),
            'supplier_name' => 'Fornecedor Dusk Vencimento',
            'amount' => 700.00,
            'due_date' => '2026-07-02',
            'status' => 'pendente',
        ]);

        $this->browse(function (Browser $browser) use ($p) {
            $browser->loginAs($this->bruno())
                ->visit("/financeiro/contas-pagar/{$p->id}")
                ->waitForText('Fornecedor Dusk Vencimento', 10)
                ->waitFor('@btn-edit-due-date', 10)
                ->click('@btn-edit-due-date')
                ->waitFor('@due-date-dialog', 10)
                ->assertVisible('@due-date-dialog')
                ->click('@confirm-due-date')
                ->waitForText('Vencimento atualizado', 10);
        });

        $p->delete();
    }
}
