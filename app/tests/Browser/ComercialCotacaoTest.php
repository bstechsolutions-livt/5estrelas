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
        // Tela portada 1:1 do protótipo (.g360). Os .module-title têm text-transform:uppercase
        // via CSS, então o Selenium "vê" o texto em MAIÚSCULAS (mesmo gotcha da tela Valores).
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/cotacao')
                ->waitForText('Nova Cotação de Custos', 10)
                ->assertSee('IDENTIFICAÇÃO DA PROPOSTA')
                ->assertSee('CONFIGURAR POSTO')
                ->assertSee('RESUMO DOS POSTOS')
                // Botões do cabeçalho (1:1 com o protótipo)
                ->assertSee('Importar Planilha')
                ->assertSee('Salvar Proposta');
        });
    }

    public function test_seletor_categoria_escala_e_detalhes(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/cotacao')
                ->waitForText('Nova Cotação de Custos', 10)
                // Categoria/Escala vêm do backend (/comercial/cotacao/dados)
                ->waitForText('Vigilante', 10)
                ->assertSee('Vigilante')
                ->assertSee('24 Horas (12x36)')
                // Abre o detalhamento colapsável e confere um módulo do Modelo 5 Estrelas
                ->clickAtXPath("//button[contains(., 'Ver / editar detalhes do cálculo')]")
                ->pause(400)
                ->assertSee('MÓDULO 01 — COMPOSIÇÃO DA REMUNERAÇÃO');
        });
    }

    public function test_adicionar_posto_ao_resumo(): void
    {
        // O cálculo é reativo via backend (debounce). Após carregar os dados, o custo
        // já está calculado; basta adicionar o posto ao resumo.
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/cotacao')
                ->waitForText('Nova Cotação de Custos', 10)
                ->pause(1500) // aguarda o recálculo (debounce + backend)
                ->clickAtXPath("//button[contains(., 'Adicionar este posto ao resumo')]")
                ->waitForText('Total Mensal', 10)
                ->assertSee('Total Mensal');
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
