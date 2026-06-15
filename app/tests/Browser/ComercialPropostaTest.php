<?php

namespace Tests\Browser;

use App\Models\Comercial\Proposta;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) da tela Controle de Propostas (porte 1:1 do protótipo).
 * Rodam contra o servidor local com o banco real (DemoSeeder popula as propostas).
 *
 * Observação: rode apenas com Selenium/ChromeDriver disponível
 * (php artisan dusk --filter=ComercialPropostaTest).
 */
class ComercialPropostaTest extends DuskTestCase
{
    private function bruno(): User
    {
        // Bruno tem permissão wildcard no ambiente local.
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_tela_propostas_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/propostas')
                ->waitForText('Controle de Propostas', 10)
                ->assertSee('Controle de Propostas')
                // KPIs do funil (.stat-label é uppercase via CSS, então o Selenium
                // "vê" o texto em MAIÚSCULAS — mesmo gotcha das telas Cotação/Valores).
                ->assertSee('TOTAL DE PROPOSTAS')
                ->assertSee('EM ANÁLISE')
                ->assertSee('APROVADAS')
                ->assertSee('REPROVADAS')
                ->assertSee('VALOR TOTAL APROVADO')
                // Ações do cabeçalho (botões não têm uppercase via CSS)
                ->assertSee('Exportar')
                ->assertSee('Nova Entrada Manual');
        });
    }

    public function test_mostra_proposta_semeada_e_abre_modal_de_situacao(): void
    {
        // Garante ao menos uma proposta conhecida para a assertiva ser estável.
        $proposta = Proposta::create([
            'numero' => 'Nº 999',
            'modelo' => 'manual',
            'cliente' => 'Cliente Dusk QA',
            'servicos' => 'Vigilância',
            'empresa' => 'seg-df',
            'situacao' => 'EM ANÁLISE',
            'valor' => 12345.67,
            'da_cotacao' => false,
            'postos' => [['id' => 1]],
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/propostas')
                ->waitForText('Controle de Propostas', 10)
                ->assertSee('Cliente Dusk QA')
                ->assertSee('Nº 999')
                // Abre o modal "Alterar Situação" pela ação da linha (botão com title)
                ->clickAtXPath("//tr[contains(., 'Cliente Dusk QA')]//button[@title='Alterar situação']")
                ->waitForText('Alterar Situação', 5)
                ->assertSee('Alterar Situação');
        });

        $proposta->delete();
    }
}
