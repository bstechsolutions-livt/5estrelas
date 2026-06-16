<?php

namespace Database\Seeders;

use App\Models\Comercial\Cliente;
use App\Models\Comercial\Faturamento;
use App\Models\Comercial\Proposta;
use Illuminate\Database\Seeder;

/**
 * Massa REAL do Comercial, extraída do protótipo Gestão 360º:
 * - 32 propostas (Nº 100–131) do HISTORICO_INICIAL
 * - 45 clientes do SEED_CLIENTES
 *
 * Idempotente: upsert por número (propostas) e por nome (clientes).
 * Dados em database/seeders/data/propostas_historico.json e clientes_seed.json.
 */
class ComercialRealSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedClientes();
        $this->seedPropostas();
        $this->seedFaturamento();
    }

    private function readJson(string $arquivo): array
    {
        $path = database_path("seeders/data/{$arquivo}");
        if (! is_file($path)) {
            $this->command?->warn("  ! Arquivo de seed não encontrado: {$arquivo}");

            return [];
        }

        return json_decode(file_get_contents($path), true) ?: [];
    }

    /** UF a partir do código de empresa (seg-go → GO, apoio-df → DF, ...). */
    private function ufDaEmpresa(?string $empresa): ?string
    {
        if (! $empresa || ! str_contains($empresa, '-')) {
            return null;
        }
        $suf = strtoupper(substr($empresa, strrpos($empresa, '-') + 1));

        return in_array($suf, ['DF', 'GO', 'SP', 'MT', 'MG'], true) ? $suf : null;
    }

    private function seedClientes(): void
    {
        $clientes = $this->readJson('clientes_seed.json');
        foreach ($clientes as $c) {
            Cliente::updateOrCreate(
                ['nome' => $c['nome']],
                [
                    'situacao' => $c['status'] ?? 'ativo',
                    'valor_mensal' => round((float) ($c['valor'] ?? 0), 2),
                    'total_postos' => (int) ($c['postos_qtd'] ?? 0),
                    'uf' => $this->ufDaEmpresa($c['empresa'] ?? null),
                    // serviços não tem coluna dedicada → vai na observação (visível na tela).
                    'observacao' => trim((string) ($c['obs'] ?? '')) !== ''
                        ? $c['obs']
                        : ('Serviços: ' . ($c['servicos'] ?? '—')),
                ],
            );
        }
        $this->command?->info('✅ ' . count($clientes) . ' clientes reais importados.');
    }

    private function seedPropostas(): void
    {
        $propostas = $this->readJson('propostas_historico.json');
        foreach ($propostas as $p) {
            $situacao = $p['situacao'] ?? 'EM ANÁLISE';
            $status = match ($situacao) {
                'APROVADO' => 'aprovada',
                'REPROVADO' => 'reprovada',
                default => 'enviada',
            };

            Proposta::updateOrCreate(
                ['numero' => $p['num']],
                [
                    'revisao' => $p['rev'] ?? 'N/A',
                    'cliente' => $p['cliente'] ?? null,
                    'servicos' => $p['servicos'] ?? null,
                    'empresa' => $p['empresa'] ?? null,
                    'posto' => $p['posto'] ?? null,
                    'valor' => isset($p['valor']) && $p['valor'] !== null ? (float) $p['valor'] : 0,
                    'contato' => $p['contato'] ?? null,
                    'data_proposta' => ! empty($p['dataEnvio']) ? $p['dataEnvio'] : null,
                    'situacao' => $situacao,
                    'valor_aprovado' => isset($p['valorAprov']) && $p['valorAprov'] !== null
                        ? (float) $p['valorAprov'] : null,
                    'data_aprovacao' => ! empty($p['dataAprov']) ? $p['dataAprov'] : null,
                    'observacao' => $p['obs'] ?? null,
                    'modelo' => 'manual',
                    'status' => $status,
                    'da_cotacao' => false,
                ],
            );
        }
        $this->command?->info('✅ ' . count($propostas) . ' propostas reais (Nº 100–131) importadas.');
    }

    /**
     * Faturamento mensal real por local (2025 completo + 2026 parcial).
     * O protótipo usa a chave 'set' para setembro; aqui mapeamos para a coluna 'setembro'.
     */
    private function seedFaturamento(): void
    {
        $dados = $this->readJson('faturamento_seed.json');
        $total = 0;

        foreach (['2025', '2026'] as $ano) {
            foreach (($dados[$ano]['locais'] ?? []) as $local) {
                $valores = [];
                foreach (Faturamento::MESES as $mes) {
                    // 'setembro' (coluna) ← 'set' (protótipo); demais 1:1.
                    $chave = $mes === 'setembro' ? 'set' : $mes;
                    $valores[$mes] = round((float) ($local[$chave] ?? 0), 2);
                }

                Faturamento::updateOrCreate(
                    ['ano' => (int) $ano, 'local_nome' => $local['nome']],
                    $valores,
                );
                $total++;
            }
        }

        $this->command?->info("✅ {$total} linhas de faturamento real importadas (2025 + 2026).");
    }
}
