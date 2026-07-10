<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\v2\BsGestaoContrato;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Teste de browser (Dusk) do painel de Gestão de Contratos.
 * Regressão do bug do KPI "Comprometido / mês": com contratos ativos de
 * LOCAÇÃO e SERVIÇO ao mesmo tempo, o valor vinha como string do Postgres e o
 * frontend concatenava, exibindo "R$ NaN". O card não pode mostrar NaN e deve
 * exibir a soma formatada em Real.
 */
class GestaoContratosDashboardTest extends DuskTestCase
{
    private function bruno(): User
    {
        return User::query()->where('email', 'bruno@bstechsolutions.com')->firstOrFail();
    }

    public function test_kpi_comprometido_mes_nao_mostra_nan(): void
    {
        // Limpa qualquer resíduo de execuções anteriores
        BsGestaoContrato::where('numero_contrato', 'like', 'DUSK-NAN-%')->forceDelete();

        // Contratos ativos nos DOIS tipos ao mesmo tempo (condição que reproduzia o NaN)
        $loc = BsGestaoContrato::create([
            'tipo' => 'LOCACAO', 'status' => 'ATIVO', 'valor_mensal' => 12345.67,
            'numero_contrato' => 'DUSK-NAN-LOC', 'data_inicio' => now()->subMonth(), 'data_fim' => now()->addYear(),
        ]);
        $srv = BsGestaoContrato::create([
            'tipo' => 'SERVICO', 'status' => 'ATIVO', 'valor_mensal' => 8901.23,
            'numero_contrato' => 'DUSK-NAN-SRV', 'data_inicio' => now()->subMonth(), 'data_fim' => now()->addYear(),
        ]);

        try {
            $this->browse(function (Browser $browser) {
                $browser->loginAs($this->bruno())
                    ->visit('/pagina/gestao-contratos')
                    ->waitForText('Dashboard - Gestão de Contratos e Alvarás', 10)
                    ->waitFor('@kpi-comprometido-mes', 10)
                    // Não pode conter NaN
                    ->assertDontSeeIn('@kpi-comprometido-mes', 'NaN')
                    // Deve exibir valor formatado em Real
                    ->assertSeeIn('@kpi-comprometido-mes', 'R$');
            });
        } finally {
            $loc->forceDelete();
            $srv->forceDelete();
        }
    }
}
