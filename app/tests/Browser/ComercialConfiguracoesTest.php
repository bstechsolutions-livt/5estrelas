<?php

namespace Tests\Browser;

use App\Models\Comercial\Filial;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) da tela Comercial — Configurações / Valores (Índices).
 * Convenções Coletivas (CCTs), Taxas (encargos/administração/lucro/tributos) e Insumos.
 *
 * Rodam contra o servidor local (APP_URL do .env.dusk.local) com o banco real
 * (Config do Comercial semeada). Rode apenas com Selenium/ChromeDriver disponível
 * (php artisan dusk --filter=ComercialConfiguracoesTest).
 *
 * Gotcha Dusk: textos com text-transform:uppercase no CSS (ex.: .module-title,
 * .cct-tab-nome) são "vistos" em MAIÚSCULAS pelo Selenium — as asserções já casam
 * com o texto exibido.
 */
class ComercialConfiguracoesTest extends DuskTestCase
{
    private function bruno(): User
    {
        // Bruno tem permissão wildcard no ambiente local.
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_tela_valores_renderiza_abas(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/configuracoes')
                ->waitForText('Índices', 10)
                ->assertSee('Índices')
                // Abas internas (texto dos botões, sem uppercase)
                ->assertSee('Convenções Coletivas')
                ->assertSee('Taxas')
                ->assertSee('Insumos')
                // Estado tabs da aba CCT (cct-tab-nome é uppercase via CSS)
                ->assertSee('BRASÍLIA')
                ->assertSee('GOIÁS');
        });
    }

    public function test_troca_entre_abas_muda_conteudo(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/configuracoes')
                ->waitForText('Índices', 10)
                // Começa na aba Convenções Coletivas → estado tabs visíveis
                ->assertSee('BRASÍLIA')
                // Vai para Taxas → mostra Encargos/Administração/Lucro e some o conteúdo de CCT
                ->press('Taxas')
                ->pause(500)
                ->waitForText('ENCARGOS SOCIAIS', 10)
                ->assertSee('ENCARGOS SOCIAIS')
                ->assertSee('ADMINISTRAÇÃO')
                ->assertSee('LUCRO')
                ->assertDontSee('BRASÍLIA')
                // Vai para Insumos → mostra a introdução de insumos
                ->press('Insumos')
                ->pause(500)
                ->waitForText('Custos de insumos operacionais', 10)
                ->assertSee('Custos de insumos operacionais')
                ->assertSee('UNIFORMES E EPIS')
                ->assertDontSee('ENCARGOS SOCIAIS')
                // Volta para Convenções Coletivas
                ->press('Convenções Coletivas')
                ->pause(500)
                ->waitForText('BRASÍLIA', 10)
                ->assertSee('BRASÍLIA');
        });
    }

    public function test_troca_de_estado_fecha_painel_de_detalhe(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/configuracoes')
                ->waitForText('Índices', 10)
                // Abre um card de CCT do estado atual → painel de detalhe aparece
                ->waitForText('Vigilância', 10)
                ->clickAtXPath("//div[contains(@class,'cct-card-nome')][contains(., 'Vigilância')]")
                ->waitFor('#cct-painel', 10)
                ->assertVisible('#cct-painel')
                // Troca de estado (UF) → fecharPainel() roda e o painel some.
                // (XPath casa com o texto cru do DOM "Goiás"; o uppercase é só CSS visual.)
                ->clickAtXPath("//button[contains(@class,'cct-estado-tab')][contains(., 'Goiás')]")
                ->pause(500)
                ->assertMissing('#cct-painel');
        });
    }

    public function test_abre_card_de_cct_mostra_painel_detalhe(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/configuracoes')
                ->waitForText('Índices', 10)
                ->waitForText('Vigilância', 10)
                ->clickAtXPath("//div[contains(@class,'cct-card-nome')][contains(., 'Vigilância')]")
                ->waitFor('#cct-painel', 10)
                ->assertVisible('#cct-painel')
                // O painel mostra os módulos de edição (module-title uppercase via CSS)
                ->assertSee('REMUNERAÇÃO BASE')
                ->assertSee('BENEFÍCIOS')
                // Botão de fechar do painel
                ->assertSee('Fechar');
        });
    }

    public function test_botao_novo_estado_abre_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/configuracoes')
                ->waitForText('Índices', 10)
                // Botão "+" de novo estado (cct-add-tab)
                ->clickAtXPath("//button[contains(@class,'cct-add-tab')]")
                ->waitForText('Adicionar novo Estado', 5)
                ->assertSee('Adicionar novo Estado')
                // Label do form é uppercase via CSS (.form-label)
                ->assertSee('UF (ESTADO)');
        });
    }

    public function test_botao_novo_servico_abre_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/configuracoes')
                ->waitForText('Índices', 10)
                // Card "+" de novo serviço (cct-card-add)
                ->clickAtXPath("(//div[contains(@class,'cct-card-add')])[1]")
                ->waitForText('Novo Serviço', 5)
                ->assertSee('Novo Serviço')
                // Label do form é uppercase via CSS (.form-label)
                ->assertSee('NOME DO SERVIÇO');
        });
    }

    public function test_salvar_taxas_mostra_toast(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/configuracoes')
                ->waitForText('Índices', 10)
                ->press('Taxas')
                ->pause(500)
                ->waitForText('ENCARGOS SOCIAIS', 10)
                // Salva as taxas (índices) — bruno tem comercial.configurar (wildcard)
                ->press('Salvar Taxas')
                ->waitForText('Taxas salvas', 10)
                ->assertSee('Taxas salvas');
        });
    }

    public function test_aba_filiais_lista_e_sincroniza(): void
    {
        // O seed popula as empresas reais da Senior (codEmp 2..12). A aba lista
        // essas empresas e o botão Sincronizar dispara o sync (skip em local).
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/configuracoes')
                ->waitForText('Índices', 10)
                ->click('@cfg-tab-filiais')
                ->waitForText('5 ESTRELAS SISTEMA DE SEGURANCA LTDA', 8)
                ->assertSee('Sincronizar com Senior')
                ->click('@cfg-filial-sincronizar')
                // Local com integração desabilitada → mensagem informativa.
                ->waitForText('exibindo as empresas', 10);
        });
    }

    public function test_toggle_filial_ativa_desativa(): void
    {
        $f = Filial::create(['senior_id' => 'dusk-'.uniqid(), 'cod_emp' => 901, 'nome' => 'Filial Toggle Dusk', 'tipo' => 'seguranca', 'tag' => 'TGL', 'ativo' => true, 'ordem' => 99]);

        $this->browse(function (Browser $browser) use ($f) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/configuracoes')
                ->waitForText('Índices', 10)
                ->click('@cfg-tab-filiais')
                ->waitFor('@cfg-filial-toggle-'.$f->id, 8)
                ->click('@cfg-filial-toggle-'.$f->id)
                ->waitForText('Filial desativada', 10);
        });

        $this->assertDatabaseHas('bs_comercial_filiais', ['id' => $f->id, 'ativo' => false]);

        $f->delete();
    }

    public function test_edita_apresentacao_da_filial(): void
    {
        $f = Filial::create(['senior_id' => 'dusk-'.uniqid(), 'cod_emp' => 902, 'nome' => 'Filial Edit Dusk', 'tipo' => 'seguranca', 'tag' => 'OLD', 'ativo' => true, 'ordem' => 99]);

        $this->browse(function (Browser $browser) use ($f) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/configuracoes')
                ->waitForText('Índices', 10)
                ->click('@cfg-tab-filiais')
                ->waitFor('@cfg-filial-editar-'.$f->id, 8)
                ->click('@cfg-filial-editar-'.$f->id)
                ->waitForText('Editar Empresa', 5)
                // Tipo → Apoio
                ->click('@cfg-filial-tipo')
                ->waitFor('@cfg-filial-tipo-opt-apoio', 5)
                ->click('@cfg-filial-tipo-opt-apoio')
                ->click('@cfg-filial-salvar')
                ->waitForText('Filial atualizada', 10);
        });

        $this->assertDatabaseHas('bs_comercial_filiais', ['id' => $f->id, 'tipo' => 'apoio']);

        $f->delete();
    }
}
