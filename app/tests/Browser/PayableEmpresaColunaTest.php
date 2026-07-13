<?php

namespace Tests\Browser;

use App\Models\Comercial\Filial;
use App\Models\Payable;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * A3 — Empresa/filial na tela principal (browser).
 *
 * A listagem não exibe mais coluna Empresa; o nome aparece na coluna Filial.
 */
class PayableEmpresaColunaTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_listagem_mostra_coluna_filial(): void
    {
        Filial::firstOrCreate(
            ['senior_id' => '777-1'],
            ['cod_emp' => 777, 'cod_fil' => 1, 'nome' => 'Empresa Teste Dusk LTDA', 'fantasia' => 'EMPRESA TESTE DUSK', 'ativo' => true]
        );

        $supplier = 'FornecedorDuskFilial' . uniqid();
        $p = Payable::create([
            'title_number' => 'DUSK-FIL-' . uniqid(),
            'supplier_name' => $supplier,
            'amount' => 1234.00,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
            'codemp' => 777,
            'codfil' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($supplier) {
            $browser->loginAs($this->bruno())
                ->visit('/financeiro/contas-pagar?status=pendente&search=' . urlencode($supplier))
                ->waitForText($supplier, 10)
                ->assertPresent('@col-filial')
                ->assertSee('EMPRESA TESTE DUSK');
        });

        $p->delete();
    }

    public function test_listagem_nao_tem_scroll_horizontal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->resize(1280, 800)
                ->visit('/financeiro/contas-pagar?status=pendente')
                ->waitForText('Contas a Pagar', 10)
                ->pause(400);

            // A tabela (table-layout: fixed; width:100%) não pode exceder o container.
            $m = $browser->script(
                'var t=document.querySelector("table"); if(!t) return {no:1};'
                . ' var p=t.parentElement; var cs=getComputedStyle(t);'
                . ' return {tsw:t.scrollWidth, tow:t.offsetWidth, pcw:p.clientWidth, psw:p.scrollWidth, tl:cs.tableLayout, tw:cs.width, pcls:p.className};'
            )[0];
            $this->assertTrue(
                ($m['tsw'] ?? 99999) <= ($m['pcw'] ?? 0) + 2,
                'Layout: ' . json_encode($m)
            );
        });
    }
}
