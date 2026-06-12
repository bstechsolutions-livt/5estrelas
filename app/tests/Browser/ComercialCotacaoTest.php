<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) do módulo Comercial.
 * Rodam contra o servidor local (APP_URL do .env.dusk.local) com o banco real
 * (que tem a Config do Comercial semeada).
 */
class ComercialCotacaoTest extends DuskTestCase
{
    private function bruno(): User
    {
        // Bruno (id 2) tem permissão wildcard no ambiente local
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_tela_cotacao_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/cotacao')
                ->waitForText('Nova Cotação de Custos', 10)
                ->assertSee('Identificação da Proposta')
                ->assertSee('Configurar Posto')
                ->assertSee('Composição Detalhada')
                ->assertSee('Calcular Custo');
        });
    }

    public function test_calcular_e_adicionar_posto(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/cotacao')
                ->waitForText('Calcular Custo', 10)
                ->press('Calcular Custo')
                ->pause(1500)
                ->press('Adicionar ao Resumo')
                ->waitForText('Total Geral Mensal', 10)
                ->assertSee('Total Geral Mensal');
        });
    }

    public function test_tela_valores_renderiza_abas(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/configuracoes')
                ->waitForText('Índices', 10)
                ->assertSee('Convenções Coletivas')
                ->assertSee('Taxas')
                ->assertSee('Insumos')
                // estado tabs (cct-tab-nome é uppercase via CSS)
                ->assertSee('BRASÍLIA')
                ->assertSee('GOIÁS');
        });
    }

    public function test_valores_aba_taxas_mostra_encargos(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/configuracoes')
                ->waitForText('Taxas', 10)
                ->press('Taxas')
                ->pause(500)
                // module-title é uppercase via CSS
                ->assertSee('ENCARGOS SOCIAIS')
                ->assertSee('ADMINISTRAÇÃO')
                ->assertSee('LUCRO');
        });
    }
}
