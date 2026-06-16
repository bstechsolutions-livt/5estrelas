<?php

namespace Tests\Browser;

use App\Models\Comercial\Cliente;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) da tela Comercial — Clientes / Contratos.
 * Listagem (KPIs + tabela), criação via modal, navegação para o detalhe (Show)
 * e o modal "Vincular Proposta".
 *
 * Rodam contra o servidor local (APP_URL do .env.dusk.local) com o banco real.
 * Rode apenas com Selenium/ChromeDriver disponível
 * (php artisan dusk --filter=ComercialClienteTest).
 *
 * Gotcha Dusk: .stat-label é uppercase via CSS, então o Selenium "vê" os KPIs
 * em MAIÚSCULAS (mesmo gotcha das telas Cotação/Propostas).
 */
class ComercialClienteTest extends DuskTestCase
{
    private function bruno(): User
    {
        // Bruno tem permissão wildcard no ambiente local.
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_listagem_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/clientes')
                ->waitForText('Clientes / Contratos', 10)
                ->assertSee('Clientes / Contratos')
                // KPIs (stat-label uppercase via CSS)
                ->assertSee('TOTAL CLIENTES')
                ->assertSee('ATIVOS')
                ->assertSee('FATURAMENTO MENSAL')
                ->assertSee('COLABORADORES')
                // Ação principal
                ->assertSee('Novo Cliente')
                // Tabela/empty state renderizada
                ->assertVisible('.contracts-table-wrap');
        });
    }

    public function test_novo_cliente_cria_e_aparece_na_lista(): void
    {
        $nome = 'Cliente Dusk QA '.uniqid();

        // Limpa remanescentes para a assertiva ser estável.
        Cliente::where('nome', 'like', 'Cliente Dusk QA%')->delete();

        $this->browse(function (Browser $browser) use ($nome) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/clientes')
                ->waitForText('Clientes / Contratos', 10)
                // Abre o modal de novo cliente
                ->click('@btn-novo-cliente')
                ->waitForText('Novo Cliente', 5)
                ->waitFor('@input-cliente-nome', 5)
                ->type('@input-cliente-nome', $nome)
                ->click('@btn-salvar-cliente')
                // Toast de sucesso + a linha nova aparece após o reload
                ->waitForText('Cliente cadastrado', 10)
                ->waitForText($nome, 10)
                ->assertSee($nome);
        });

        // Confirma persistência.
        $this->assertDatabaseHas('bs_comercial_clientes', [
            'nome' => $nome,
        ]);

        Cliente::where('nome', $nome)->delete();
    }

    public function test_clicar_cliente_abre_detalhe(): void
    {
        $nome = 'Cliente Dusk Detalhe '.uniqid();
        $cliente = Cliente::create([
            'nome' => $nome,
            'contato_nome' => 'Fulano de Tal',
            'cidade' => 'Brasília',
            'uf' => 'DF',
            'situacao' => 'ativo',
            'valor_mensal' => 12345.67,
            'total_colaboradores' => 8,
            'total_postos' => 3,
        ]);

        $this->browse(function (Browser $browser) use ($nome) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/clientes')
                ->waitForText('Clientes / Contratos', 10)
                ->waitForText($nome, 10)
                // Clica na linha do cliente → navega para o detalhe (Show)
                ->clickAtXPath("//tr[contains(., '".$nome."')]")
                ->waitForText('Vincular Proposta', 10)
                // Dados do cliente no detalhe
                ->assertSee($nome)
                ->assertSee('Fulano de Tal')
                ->assertSee('VALOR MENSAL CONTRATADO');
        });

        $cliente->delete();
    }

    public function test_detalhe_abre_modal_vincular_proposta(): void
    {
        $nome = 'Cliente Dusk Vincular '.uniqid();
        $cliente = Cliente::create([
            'nome' => $nome,
            'situacao' => 'ativo',
        ]);

        $this->browse(function (Browser $browser) use ($cliente, $nome) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/clientes/'.$cliente->id)
                ->waitForText($nome, 10)
                ->assertSee($nome)
                // Abre o modal "Vincular Proposta"
                ->click('@btn-vincular-proposta')
                ->waitForText('Vincular Proposta ao Cliente', 10)
                ->assertSee('Vincular Proposta ao Cliente');
        });

        $cliente->delete();
    }
}
