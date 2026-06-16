<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) — render das demais telas do módulo de Tickets.
 *
 * Cada tela: carrega sem travar no overlay de loading, mostra o título/seção
 * e a sidebar (item "Acompanhar" do grupo Tickets).
 *
 * Bruno tem permissão wildcard ('*'), então acessa inclusive Configurações.
 * Roda contra o servidor local com o banco real (Postgres de dev).
 */
class SolicitacoesTelasTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_minhas_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/solicitacoes/minhas')
                ->waitUntilMissing('@tickets-loading', 15)
                ->waitForText('Meus Tickets', 10)
                ->assertSee('Meus Tickets')
                // Sidebar (grupo Tickets)
                ->assertSee('Acompanhar');
        });
    }

    public function test_dashboard_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/solicitacoes/dashboard')
                ->waitUntilMissing('@tickets-loading', 15)
                ->waitForText('Dashboard de Tickets', 10)
                ->assertSee('Dashboard de Tickets')
                ->assertSee('Acompanhar');
        });
    }

    public function test_relatorios_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/solicitacoes/relatorios')
                ->waitUntilMissing('@tickets-loading', 15)
                ->waitForText('Relatórios de Tickets', 10)
                ->assertSee('Relatórios de Tickets')
                ->assertSee('Acompanhar');
        });
    }

    public function test_agendamento_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/solicitacoes/agendamento')
                // Esta tela usa o overlay `loading` (não `loadingInicial`); o título
                // aparece junto com o conteúdo, então esperamos pelo título direto.
                ->waitForText('Agendamentos', 15)
                ->assertSee('Agendamentos')
                ->assertSee('Acompanhar');
        });
    }

    public function test_configuracoes_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/solicitacoes/configuracoes')
                // Aba inicial = "departamentos"
                ->waitForText('Gerencie os departamentos habilitados para tickets', 15)
                ->assertSee('Departamentos')
                ->assertSee('Acompanhar');
        });
    }
}
