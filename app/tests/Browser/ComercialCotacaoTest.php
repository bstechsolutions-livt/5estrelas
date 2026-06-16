<?php

namespace Tests\Browser;

use App\Models\Comercial\Proposta;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) da tela Comercial — Nova Cotação de Custos.
 * Rodam contra o servidor local (APP_URL do .env.dusk.local) com o banco real
 * (que tem a Config do Comercial semeada: categorias, escalas, CCTs, índices).
 *
 * Observação: rode apenas com Selenium/ChromeDriver disponível
 * (php artisan dusk --filter=ComercialCotacaoTest).
 *
 * Gotcha Dusk: textos com text-transform:uppercase no CSS (ex.: .module-title,
 * .section-title de subtítulos) são "vistos" em MAIÚSCULAS pelo Selenium — as
 * asserções abaixo já casam com o texto exibido.
 */
class ComercialCotacaoTest extends DuskTestCase
{
    private function bruno(): User
    {
        // Bruno tem permissão wildcard no ambiente local.
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_tela_cotacao_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/cotacao')
                ->waitForText('Nova Cotação de Custos', 10)
                ->assertSee('Nova Cotação de Custos')
                // Seções (módulo-title/labels são uppercase via CSS)
                ->assertSee('IDENTIFICAÇÃO DA PROPOSTA')
                ->assertSee('CONFIGURAR POSTO')
                ->assertSee('RESUMO DOS POSTOS')
                // Ações do cabeçalho (1:1 com o protótipo)
                ->assertSee('Importar Planilha')
                ->assertSee('Salvar Proposta');
        });
    }

    public function test_seleciona_categoria_e_escala_atualiza_preview(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/cotacao')
                ->waitForText('Nova Cotação de Custos', 10)
                // Categoria/Escala vêm do backend (/comercial/cotacao/dados)
                ->waitForText('Vigilante', 10)
                ->assertSee('Vigilante')
                ->assertSee('24 Horas (12x36)')
                // Seleciona uma categoria e uma escala clicando nos botões da lista
                ->clickAtXPath("//div[@id='cat-btns']//button[contains(., 'Vigilante')]")
                ->clickAtXPath("//div[@id='esc-btns']//button[contains(., '12x36 — Diurno')]")
                // O banner "valores aplicados" aparece e o preview do custo é exibido
                ->waitFor('#valores-banner', 10)
                ->assertVisible('#valores-banner')
                ->assertVisible('#calc-preview')
                // Preview reflete func./posto da escala diurna (1 posto = 2 func.)
                ->waitForTextIn('#prev-func', 'func.', 10)
                ->assertSeeIn('#valores-banner', 'Vigilante');
        });
    }

    public function test_adicionar_posto_ao_resumo(): void
    {
        // O cálculo é reativo via backend (debounce). Após carregar os dados, o custo
        // já está calculado; basta adicionar o posto ao resumo (coluna direita).
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/cotacao')
                ->waitForText('Nova Cotação de Custos', 10)
                // Estado inicial: resumo vazio
                ->assertVisible('#resumo-vazio')
                ->pause(1800) // aguarda o recálculo (debounce + backend) antes de adicionar
                ->click('@btn-adicionar-posto')
                // A tabela de resumo aparece (some o estado vazio) e o total mensal é exibido
                ->waitFor('#resumo-table', 10)
                ->assertVisible('#resumo-table')
                ->assertMissing('#resumo-vazio')
                ->assertSeeIn('#resumo-table', 'Total Mensal')
                // O posto adicionado (Vigilante, default) aparece na coluna direita
                ->assertSeeIn('#resumo-tbody', 'Vigilante');
        });
    }

    public function test_troca_modelo_planilha_muda_detalhamento(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/cotacao')
                ->waitForText('Nova Cotação de Custos', 10)
                // Abre o detalhamento colapsável
                ->clickAtXPath("//button[contains(., 'Ver / editar detalhes do cálculo')]")
                ->pause(400)
                // Modelo 5 Estrelas (default): mostra o Módulo 02 — Benefícios
                ->waitForText('MÓDULO 02 — BENEFÍCIOS', 10)
                ->assertSee('MÓDULO 02 — BENEFÍCIOS')
                // Troca para IN 05: o detalhamento muda para os módulos da IN 05
                ->select('#modelo-select', 'in05')
                ->pause(600)
                ->waitForText('MÓDULO 2 — ENCARGOS E BENEFÍCIOS ANUAIS, MENSAIS E DIÁRIOS', 10)
                ->assertSee('MÓDULO 2 — ENCARGOS E BENEFÍCIOS ANUAIS, MENSAIS E DIÁRIOS')
                ->assertDontSee('MÓDULO 02 — BENEFÍCIOS')
                // Volta para 5 Estrelas
                ->select('#modelo-select', '5estrelas')
                ->pause(600)
                ->waitForText('MÓDULO 02 — BENEFÍCIOS', 10)
                ->assertSee('MÓDULO 02 — BENEFÍCIOS');
        });
    }

    public function test_abre_detalhamento_do_calculo(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/cotacao')
                ->waitForText('Nova Cotação de Custos', 10)
                // O detalhamento começa fechado
                ->assertMissing('#total-m1')
                ->clickAtXPath("//button[contains(., 'Ver / editar detalhes do cálculo')]")
                ->pause(400)
                // Abre e mostra o Módulo 01 do Modelo 5 Estrelas
                ->waitForText('MÓDULO 01 — COMPOSIÇÃO DA REMUNERAÇÃO', 10)
                ->assertSee('MÓDULO 01 — COMPOSIÇÃO DA REMUNERAÇÃO')
                ->assertVisible('#total-m1');
        });
    }

    public function test_salvar_proposta_persiste_e_mostra_numero(): void
    {
        $cliente = 'Cliente Dusk Cotacao '.uniqid();

        // Limpa qualquer remanescente para a assertiva ser estável.
        Proposta::where('cliente', 'like', 'Cliente Dusk Cotacao%')->delete();

        $this->browse(function (Browser $browser) use ($cliente) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/cotacao')
                ->waitForText('Nova Cotação de Custos', 10)
                ->type('#cliente', $cliente)
                ->pause(1800) // aguarda o recálculo (debounce + backend)
                // Adiciona um posto ao resumo (pré-requisito para salvar)
                ->click('@btn-adicionar-posto')
                ->waitFor('#resumo-table', 10)
                // Salva a proposta
                ->click('@btn-salvar-proposta')
                // Toast de sucesso "Proposta Nº ... salva!"
                ->waitForText('salva', 10)
                ->assertSee('salva');
        });

        // Confirma persistência da proposta criada pela cotação.
        $this->assertDatabaseHas('bs_comercial_propostas', [
            'cliente' => $cliente,
            'da_cotacao' => true,
        ]);

        Proposta::where('cliente', $cliente)->delete();
    }
}
