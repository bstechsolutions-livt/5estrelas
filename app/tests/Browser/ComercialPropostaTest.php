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
 * Cobre cada ação principal clicando de verdade: render + KPIs, entrada manual,
 * edição, alterar situação (aprovar e reprovar — este com confirmação SweetAlert),
 * exclusão (com confirmação), filtro de busca e o botão Exportar (stub "em breve").
 *
 * Seletores estáveis via atributos dusk="prop-..." na tela. Para as ações por linha
 * usamos dusk parametrizado pelo id (ex.: @prop-editar-{id}).
 *
 * Gotcha Dusk: .stat-label é uppercase via CSS, então o Selenium "vê" os KPIs em
 * MAIÚSCULAS. swalConfirm é SweetAlert2 → o botão de confirmar é `.swal2-confirm`.
 *
 * Rode apenas com Selenium/ChromeDriver disponível
 * (php artisan dusk --filter=ComercialPropostaTest).
 */
class ComercialPropostaTest extends DuskTestCase
{
    private function bruno(): User
    {
        // Bruno tem permissão wildcard no ambiente local.
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    /** Cria uma proposta manual conhecida para uma asserção estável. */
    private function novaProposta(array $attrs = []): Proposta
    {
        return Proposta::create(array_merge([
            'numero' => 'Nº '.random_int(900, 9999),
            'modelo' => 'manual',
            'cliente' => 'Cliente Dusk QA',
            'servicos' => 'Vigilância',
            'empresa' => 'seg-df',
            'situacao' => 'EM ANÁLISE',
            'valor' => 12345.67,
            'da_cotacao' => false,
            'postos' => [['id' => 1]],
        ], $attrs));
    }

    public function test_tela_propostas_renderiza(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/propostas')
                ->waitForText('Controle de Propostas', 10)
                ->assertSee('Controle de Propostas')
                // KPIs do funil (.stat-label é uppercase via CSS)
                ->assertSee('TOTAL DE PROPOSTAS')
                ->assertSee('EM ANÁLISE')
                ->assertSee('APROVADAS')
                ->assertSee('REPROVADAS')
                ->assertSee('VALOR TOTAL APROVADO')
                // Ações do cabeçalho
                ->assertSee('Exportar')
                ->assertSee('Nova Entrada Manual');
        });
    }

    public function test_nova_entrada_manual_cria_proposta(): void
    {
        $cliente = 'Cliente Manual Dusk '.uniqid();
        Proposta::where('cliente', 'like', 'Cliente Manual Dusk%')->delete();

        $this->browse(function (Browser $browser) use ($cliente) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/propostas')
                ->waitForText('Controle de Propostas', 10)
                ->click('@prop-nova')
                ->waitForText('Nova Entrada de Proposta', 5)
                ->waitFor('@prop-form-cliente', 5)
                ->type('@prop-form-cliente', $cliente)
                ->type('@prop-form-valor', '8500')
                ->click('@prop-salvar')
                ->waitForText('Proposta registrada', 10)
                ->waitForText($cliente, 10)
                ->assertSee($cliente);
        });

        $this->assertDatabaseHas('bs_comercial_propostas', [
            'cliente' => $cliente,
            'modelo' => 'manual',
            'da_cotacao' => false,
        ]);

        Proposta::where('cliente', $cliente)->delete();
    }

    public function test_editar_proposta_atualiza(): void
    {
        $nomeNovo = 'Cliente Editado Dusk '.uniqid();
        $proposta = $this->novaProposta(['cliente' => 'Cliente Original Dusk']);

        $this->browse(function (Browser $browser) use ($proposta, $nomeNovo) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/propostas')
                ->waitForText('Controle de Propostas', 10)
                ->waitForText('Cliente Original Dusk', 10)
                ->click('@prop-editar-'.$proposta->id)
                ->waitForText('Editar Proposta', 5)
                ->waitFor('@prop-form-cliente', 5)
                ->clear('@prop-form-cliente')
                ->type('@prop-form-cliente', $nomeNovo)
                ->click('@prop-salvar')
                ->waitForText('Proposta atualizada', 10)
                ->waitForText($nomeNovo, 10)
                ->assertSee($nomeNovo);
        });

        $this->assertDatabaseHas('bs_comercial_propostas', [
            'id' => $proposta->id,
            'cliente' => $nomeNovo,
        ]);

        $proposta->delete();
    }

    public function test_alterar_situacao_aprovar(): void
    {
        $proposta = $this->novaProposta(['cliente' => 'Cliente Aprovar Dusk', 'situacao' => 'EM ANÁLISE']);

        $this->browse(function (Browser $browser) use ($proposta) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/propostas')
                ->waitForText('Controle de Propostas', 10)
                ->waitForText('Cliente Aprovar Dusk', 10)
                ->click('@prop-situacao-'.$proposta->id)
                ->waitForText('Alterar Situação', 5)
                ->click('@sit-opt-aprovado')
                ->waitForText('Situação atualizada', 10);
        });

        $this->assertDatabaseHas('bs_comercial_propostas', [
            'id' => $proposta->id,
            'situacao' => 'APROVADO',
        ]);

        $proposta->delete();
    }

    public function test_reprovar_pede_confirmacao_e_persiste(): void
    {
        $proposta = $this->novaProposta(['cliente' => 'Cliente Reprovar Dusk', 'situacao' => 'EM ANÁLISE']);

        $this->browse(function (Browser $browser) use ($proposta) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/propostas')
                ->waitForText('Controle de Propostas', 10)
                ->waitForText('Cliente Reprovar Dusk', 10)
                ->click('@prop-situacao-'.$proposta->id)
                ->waitForText('Alterar Situação', 5)
                ->click('@sit-opt-reprovado')
                // swalConfirm (SweetAlert2) — confirma a ação sensível.
                ->waitFor('.swal2-confirm', 5)
                ->click('.swal2-confirm')
                ->waitForText('Situação atualizada', 10);
        });

        $this->assertDatabaseHas('bs_comercial_propostas', [
            'id' => $proposta->id,
            'situacao' => 'REPROVADO',
        ]);

        $proposta->delete();
    }

    public function test_excluir_proposta_pede_confirmacao(): void
    {
        $proposta = $this->novaProposta(['cliente' => 'Cliente Excluir Dusk']);

        $this->browse(function (Browser $browser) use ($proposta) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/propostas')
                ->waitForText('Controle de Propostas', 10)
                ->waitForText('Cliente Excluir Dusk', 10)
                ->click('@prop-excluir-'.$proposta->id)
                // swalConfirm (SweetAlert2) — confirma a exclusão.
                ->waitFor('.swal2-confirm', 5)
                ->click('.swal2-confirm')
                ->waitForText('Proposta excluída', 10);
        });

        $this->assertDatabaseMissing('bs_comercial_propostas', [
            'id' => $proposta->id,
        ]);
    }

    public function test_filtro_busca_filtra_lista(): void
    {
        $alvo = 'AlvoBusca'.uniqid();
        $outro = 'OutroCliente'.uniqid();
        $pAlvo = $this->novaProposta(['cliente' => $alvo]);
        $pOutro = $this->novaProposta(['cliente' => $outro]);

        $this->browse(function (Browser $browser) use ($alvo, $outro) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/propostas')
                ->waitForText('Controle de Propostas', 10)
                ->waitForText($alvo, 10)
                ->type('@prop-filtro-busca', $alvo)
                ->pause(400)
                ->assertSee($alvo)
                ->assertDontSee($outro)
                // Limpar filtros traz tudo de volta.
                ->click('@prop-limpar-filtros')
                ->pause(400)
                ->assertSee($outro);
        });

        $pAlvo->delete();
        $pOutro->delete();
    }

    public function test_reabrir_proposta_na_cotacao(): void
    {
        // Proposta gerada na plataforma (da_cotacao) com snapshot de postos.
        $proposta = $this->novaProposta([
            'cliente' => 'Cliente Reabrir Dusk',
            'modelo' => 'in05',
            'da_cotacao' => true,
            'postos' => [
                ['id' => 1, 'cat' => 'Vigilante', 'escala' => '12x36 — Diurno', 'funcPosto' => 1,
                    'qtdPostos' => 2, 'unitVal' => 5000, 'totalMensal' => 10000, 'vaUnit' => 300, 'modelo' => 'in05'],
            ],
            'identificacao' => ['cliente' => 'Cliente Reabrir Dusk', 'modelo' => 'in05'],
        ]);

        $this->browse(function (Browser $browser) use ($proposta) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/propostas')
                ->waitForText('Controle de Propostas', 10)
                ->waitForText('Cliente Reabrir Dusk', 10)
                // Clica na linha (gerada na plataforma) → navega para a Cotação.
                ->click('@prop-abrir-' . $proposta->id)
                ->waitForLocation('/comercial/cotacao', 10)
                ->waitForText('reaberta', 10)
                // O cliente foi restaurado no formulário da cotação.
                ->waitFor('@cot-cliente', 10)
                ->assertInputValue('@cot-cliente', 'Cliente Reabrir Dusk');
        });

        $proposta->delete();
    }

    public function test_exportar_xlsx_mostra_sucesso(): void
    {
        // Garante ao menos uma proposta na lista para a exportação ter conteúdo.
        $proposta = $this->novaProposta(['cliente' => 'Cliente Export Dusk']);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->bruno())
                ->visit('/comercial/propostas')
                ->waitForText('Controle de Propostas', 10)
                ->waitForText('Cliente Export Dusk', 10)
                ->click('@prop-exportar')
                ->waitForText('exportada', 5);
        });

        $proposta->delete();
    }
}
