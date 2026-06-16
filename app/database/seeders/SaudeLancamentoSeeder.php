<?php

namespace Database\Seeders;

use App\Models\Comercial\Cliente;
use App\Models\Comercial\SaudeLancamento;
use App\Models\Comercial\SaudeMeta;
use Illuminate\Database\Seeder;

/**
 * Popula lançamentos mensais de saúde contratual para os clientes ativos.
 * Gera 6 meses (jan–jun 2026) com custos proporcionais ao valor_mensal do cliente,
 * com variação aleatória para simular margem real. Idempotente (upsert).
 */
class SaudeLancamentoSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = Cliente::where('situacao', 'ativo')
            ->where('valor_mensal', '>', 0)
            ->get();

        $meses = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'];
        $total = 0;

        foreach ($clientes as $cliente) {
            $fat = (float) $cliente->valor_mensal;
            if ($fat <= 0) {
                continue;
            }

            // Metas (se não existir)
            SaudeMeta::firstOrCreate(
                ['cliente_id' => $cliente->id],
                ['margem_minima' => 2.5, 'margem_alvo' => 3.5, 'max_folha_pct' => 75, 'inadimplencia_max' => $fat * 0.02],
            );

            foreach ($meses as $mes) {
                // Custos proporcionais com variação aleatória.
                $folhaPct = random_int(55, 72) / 100; // 55-72% do faturamento
                $benefPct = random_int(5, 12) / 100;   // 5-12%
                $insumPct = random_int(2, 8) / 100;    // 2-8%
                $inadPct = random_int(0, 100) < 15 ? random_int(1, 5) / 100 : 0; // 15% chance de inadimplência

                // Faturamento real = contratado ± variação de 5%.
                $fatReal = round($fat * (1 + (random_int(-5, 5) / 100)), 2);

                SaudeLancamento::updateOrCreate(
                    ['cliente_id' => $cliente->id, 'mes_ref' => $mes],
                    [
                        'faturamento_real' => $fatReal,
                        'custo_folha' => round($fat * $folhaPct, 2),
                        'custo_beneficios' => round($fat * $benefPct, 2),
                        'custo_insumos' => round($fat * $insumPct, 2),
                        'inadimplencia' => round($fat * $inadPct, 2),
                    ],
                );
                $total++;
            }
        }

        $this->command?->info("✅ {$total} lançamentos de saúde contratual gerados (6 meses × " . $clientes->count() . " clientes).");
    }
}
