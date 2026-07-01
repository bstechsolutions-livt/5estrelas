<?php

namespace Tests\Browser;

use App\Models\Comercial\Filial;
use App\Models\Payable;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * A3 — Coluna Empresa (por NOME) na tela principal (browser).
 *
 * A listagem exibe o NOME da empresa (fantasia), resolvido pelo codEmp do
 * título via bs_comercial_filiais. Nunca o código.
 */
class PayableEmpresaColunaTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_listagem_mostra_nome_da_empresa(): void
    {
        Filial::firstOrCreate(
            ['senior_id' => '777-1'],
            ['cod_emp' => 777, 'cod_fil' => 1, 'nome' => 'Empresa Teste Dusk LTDA', 'fantasia' => 'EMPRESA TESTE DUSK', 'ativo' => true]
        );

        $supplier = 'FornecedorDuskEmpresa' . uniqid();
        $p = Payable::create([
            'title_number' => 'DUSK-EMP-' . uniqid(),
            'supplier_name' => $supplier,
            'amount' => 1234.00,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
            'codemp' => 777,
        ]);

        $this->browse(function (Browser $browser) use ($supplier) {
            $browser->loginAs($this->bruno())
                // Query string evita a restauração de filtros do cache (onMounted).
                ->visit('/financeiro/contas-pagar?status=pendente&search=' . urlencode($supplier))
                ->waitForText($supplier, 10)
                ->assertSee('EMPRESA TESTE DUSK');
        });

        $p->delete();
    }
}
