<?php

namespace Tests\Browser;

use App\Models\Comercial\Reajuste;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Testes de browser (Dusk) da tela Comercial — Reajustes de Contrato.
 * Render + KPIs, detalhe (itens), alterar status, excluir (confirmação SweetAlert).
 * Roda contra o servidor local com o banco real.
 */
class ComercialReajusteTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    private function novo(array $attrs = []): Reajuste
    {
        return Reajuste::create(array_merge([
            'cliente_nome' => 'Cliente Reajuste Dusk',
            'empresa' => 'apoio-df',
            'tipo' => 'manual',
            'pct' => 6.5,
            'status' => 'pendente',
            'valor_atual' => 10000,
            'impacto_mensal' => 650,
            'itens' => [['nome' => 'Limpeza', 'valorAtual' => 10000, 'pct' => 6.5, 'novoValor' => 10650, 'variacao' => 650]],
        ], $attrs));
    }

    public function test_tela_reajustes_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/reajustes')
                ->waitForText('Reajuste de Contratos', 10)
                ->assertSee('Reajuste de Contratos')
                // KPIs (stat-label uppercase via CSS)
                ->assertSee('TOTAL DE CONTRATOS')
                ->assertSee('EM ANÁLISE')
                ->assertSee('ENVIADOS')
                ->assertSee('APROVADOS')
                // Seções por empresa
                ->assertSee('5 Estrelas Sistemas de Segurança Ltda')
                ->assertSee('5 Estrelas Serviços de Apoio Administrativo');
        });
    }

    public function test_ver_detalhe_dos_itens(): void
    {
        $r = $this->novo(['cliente_nome' => 'Cliente Detalhe Reajuste']);

        $this->browse(function (Browser $browser) use ($r) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/reajustes')
                ->waitForText('Cliente Detalhe Reajuste', 10)
                ->click('@raj-detalhe-' . $r->id)
                ->waitForText('Itens do Reajuste', 5)
                ->assertSee('Limpeza');
        });

        $r->delete();
    }

    public function test_alterar_status(): void
    {
        $r = $this->novo(['cliente_nome' => 'Cliente Status Reajuste', 'status' => 'pendente']);

        $this->browse(function (Browser $browser) use ($r) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/reajustes')
                ->waitForText('Cliente Status Reajuste', 10)
                ->click('@raj-status-' . $r->id)
                ->waitForText('Alterar Status', 5)
                ->click('@raj-opt-enviado')
                ->waitForText('Status atualizado', 10);
        });

        $this->assertDatabaseHas('bs_comercial_reajustes', ['id' => $r->id, 'status' => 'enviado']);
        $r->delete();
    }

    public function test_excluir_com_confirmacao(): void
    {
        $r = $this->novo(['cliente_nome' => 'Cliente Excluir Reajuste']);

        $this->browse(function (Browser $browser) use ($r) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/reajustes')
                ->waitForText('Cliente Excluir Reajuste', 10)
                ->click('@raj-excluir-' . $r->id)
                ->waitFor('.swal2-confirm', 5)
                ->click('.swal2-confirm')
                ->waitForText('Reajuste excluído', 10);
        });

        $this->assertDatabaseMissing('bs_comercial_reajustes', ['id' => $r->id]);
    }
}
