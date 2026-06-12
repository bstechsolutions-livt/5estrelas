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
                ->assertSee('Nova Cotação de Custos')
                ->assertSee('Quadro-Resumo')
                ->assertSee('Calcular');
        });
    }

    public function test_calcular_mostra_resultado(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/cotacao')
                ->waitForText('Calcular', 10)
                ->press('Calcular')
                // Após calcular, o quadro mostra "Custo / empregado" e o valor do posto
                ->waitForText('Custo / empregado', 10)
                ->assertSee('Custo / empregado')
                ->assertSee('Valor do Posto');
        });
    }

    public function test_tela_valores_renderiza_abas(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/configuracoes')
                ->waitForText('Comercial — Valores', 10)
                ->assertSee('CCTs')
                ->assertSee('Categorias')
                ->assertSee('Escalas')
                ->assertSee('Encargos')
                ->assertSee('Insumos');
        });
    }
}
