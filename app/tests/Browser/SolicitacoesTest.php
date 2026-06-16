<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) do módulo de Tickets (Solicitações).
 * Valida que a tela de Acompanhar carrega de fato (loading some, sidebar aparece)
 * e que a URL legada /solicitacoes/fila redireciona para /lista.
 *
 * Roda contra o servidor local com o banco real. Bruno tem permissão wildcard.
 */
class SolicitacoesTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_lista_carrega_e_mostra_sidebar(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/solicitacoes/lista')
                // O overlay "Carregando Tickets" deve sumir (loadingInicial → false).
                ->waitUntilMissing('@tickets-loading', 15)
                // Breadcrumb da página de Tickets.
                ->waitForText('Atendimento', 10)
                ->assertSee('Atendimento')
                // A sidebar/menu volta a aparecer (item do grupo Tickets).
                ->assertSee('Acompanhar');
        });
    }

    public function test_fila_redireciona_para_lista(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/solicitacoes/fila')
                // Redireciona para a rota correta.
                ->waitForLocation('/solicitacoes/lista', 10)
                ->waitUntilMissing('@tickets-loading', 15)
                ->assertSee('Atendimento');
        });
    }
}
