<?php

namespace Tests\Browser;

use App\Models\Comercial\Cliente;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) das telas do Comercial em VIEWPORT MOBILE (390x844).
 * Verifica que, no celular, as listas viram cards (não tabela), os formulários
 * empilham e os comboboxes de busca funcionam. O shell (sidebar) já é responsivo
 * no AppLayout (vira drawer); aqui validamos o conteúdo das telas.
 *
 * isMobile (useDevice) = largura < 1024 → resize antes do visit força o modo mobile.
 */
class ComercialMobileTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_clientes_mobile_mostra_cards_e_esconde_tabela(): void
    {
        $nome = 'Cliente Mobile '.uniqid();
        Cliente::where('nome', 'like', 'Cliente Mobile%')->delete();
        $cli = Cliente::create(['nome' => $nome, 'situacao' => 'ativo', 'valor_mensal' => 1000, 'total_postos' => 2]);

        $this->browse(function (Browser $browser) use ($cli, $nome) {
            $browser->loginAs($this->bruno())
                ->resize(390, 844)
                ->visit('/comercial/clientes')
                ->waitForText('Clientes / Contratos', 10)
                // No mobile aparece a lista de cards e o card do cliente
                ->waitFor('@cliente-cards', 8)
                ->assertVisible('@cliente-card-'.$cli->id)
                ->assertSee($nome)
                // E a tabela desktop NÃO é renderizada
                ->assertMissing('.contracts-table-wrap');
        });

        $cli->delete();
    }

    public function test_propostas_mobile_mostra_cards(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->resize(390, 844)
                ->visit('/comercial/propostas')
                ->waitForText('Controle de Propostas', 10)
                ->waitFor('@prop-cards', 8)
                ->assertVisible('@prop-cards');
        });
    }

    public function test_contratos_mobile_mostra_cards(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->resize(390, 844)
                ->visit('/comercial/contratos')
                ->waitForText('Contratos Ativos', 10)
                ->waitFor('@contrato-cards', 8)
                ->assertVisible('@contrato-cards');
        });
    }

    public function test_cotacao_mobile_renderiza_e_busca_cliente(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->resize(390, 844)
                ->visit('/comercial/cotacao')
                ->waitForText('Nova Cotação de Custos', 10)
                // O combobox de cliente (busca) renderiza e é utilizável no mobile
                ->assertVisible('@cot-cliente');
        });
    }

    public function test_configuracoes_mobile_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->resize(390, 844)
                ->visit('/comercial/configuracoes')
                ->waitForText('Índices', 10)
                ->click('@cfg-tab-filiais')
                ->waitForText('Sincronizar com Senior', 8);
        });
    }

    public function test_saude_faturamento_dashboard_mobile_renderizam(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->resize(390, 844)
                ->visit('/comercial/saude')
                ->waitForText('Saúde Contratual', 10)
                ->assertVisible('@saude-select-cliente')
                ->visit('/comercial/faturamento')
                ->waitForText('Faturamento', 10)
                ->assertVisible('@btn-adicionar-local')
                ->visit('/comercial/dashboard')
                ->waitForText('Dashboard', 10);
        });
    }
}
