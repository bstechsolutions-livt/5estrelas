<?php

namespace Tests\Browser;

use App\Models\Comercial\Cliente;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) da tela Comercial — Saúde Contratual.
 * Render + seleção de cliente via combobox de busca (SearchSelect) que navega
 * para o dashboard do contrato (/comercial/saude?cliente=).
 *
 * Gotcha Dusk: .stat-label é uppercase via CSS (KPIs vistos em MAIÚSCULAS).
 */
class ComercialSaudeTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_tela_saude_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/saude')
                ->waitForText('Saúde Contratual', 10)
                ->assertSee('Saúde Contratual')
                // Combobox de seleção de contrato presente
                ->assertVisible('@saude-select-cliente');
        });
    }

    public function test_busca_e_seleciona_cliente_abre_dashboard(): void
    {
        $nome = 'Cliente Saude Dusk '.uniqid();
        Cliente::where('nome', 'like', 'Cliente Saude Dusk%')->delete();
        $cli = Cliente::create([
            'nome' => $nome,
            'situacao' => 'ativo',
            'valor_mensal' => 50000,
            'cidade' => 'Brasília',
            'uf' => 'DF',
        ]);

        $this->browse(function (Browser $browser) use ($cli, $nome) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/saude')
                ->waitForText('Saúde Contratual', 10)
                // Busca pelo nome e seleciona a sugestão (value = id numérico)
                ->click('@saude-select-cliente')
                ->type('@saude-select-cliente', 'Cliente Saude Dusk')
                ->waitFor('@saude-cli-opt-'.$cli->id, 6)
                ->click('@saude-cli-opt-'.$cli->id)
                // Navega para o contrato e mostra o dashboard (KPIs uppercase via CSS)
                ->waitForText('MARGEM DO CONTRATO', 10)
                ->assertInputValue('@saude-select-cliente', $nome);
        });

        $cli->delete();
    }
}
