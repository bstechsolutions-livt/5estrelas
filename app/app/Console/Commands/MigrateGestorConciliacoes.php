<?php

namespace App\Console\Commands;

use App\Services\GestorConciliacoesMigrationService;
use Illuminate\Console\Command;

class MigrateGestorConciliacoes extends Command
{
    protected $signature = 'gestor:migrate
        {--export-path= : Caminho do export unpacked do Convex (default: infra/legado/exports/unpacked)}
        {--execute : Aplica alterações (default: dry-run)}
        {--confidence=high : Nível mínimo de confiança para migrar (high|medium|low)}
        {--cod-emp= : Filtra empresa Senior (codEmp)}
        {--cod-fil= : Filtra filial Senior / número da enterprise no Gestor}
        {--skip-comments : Não importa comentários}
        {--skip-files : Não baixa anexos do Convex}
        {--files-only : Importa apenas anexos (não altera status/comentários)}
        {--report= : Caminho do relatório JSON (default: storage/app/gestor-migration-report.json)}';

    protected $description = 'Migra workflow, comentários e anexos do Gestor de Conciliações (Convex legado) para payables';

    public function handle(): int
    {
        $exportPath = $this->option('export-path')
            ?: base_path('../infra/legado/exports/unpacked');

        $reportPath = $this->option('report')
            ?: storage_path('app/gestor-migration-report.json');

        $confidence = (string) $this->option('confidence');
        if (! in_array($confidence, ['high', 'medium', 'low'], true)) {
            $this->error('confidence deve ser high, medium ou low');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $codEmp = $this->option('cod-emp') !== null && $this->option('cod-emp') !== ''
            ? (int) $this->option('cod-emp')
            : null;
        $codFil = $this->option('cod-fil') !== null && $this->option('cod-fil') !== ''
            ? (int) $this->option('cod-fil')
            : null;

        if (! is_dir($exportPath)) {
            $this->error("Export não encontrado: {$exportPath}");

            return self::FAILURE;
        }

        if ($execute) {
            $this->warn('Modo EXECUTE — alterações serão gravadas.');
        } else {
            $this->info('Modo DRY-RUN (use --execute para aplicar).');
        }

        if ($codEmp !== null || $codFil !== null) {
            $this->info(sprintf(
                'Escopo: emp=%s fil=%s',
                $codEmp ?? '*',
                $codFil ?? '*',
            ));
        }

        $service = new GestorConciliacoesMigrationService(
            exportPath: $exportPath,
            confidence: $confidence,
            execute: $execute,
            skipComments: (bool) $this->option('skip-comments'),
            skipFiles: (bool) $this->option('skip-files'),
            filesOnly: (bool) $this->option('files-only'),
            reportPath: $reportPath,
            codEmp: $codEmp,
            codFil: $codFil,
        );

        $result = $service->run();

        if (! empty($result['scope'])) {
            $this->line(sprintf(
                'Escopo aplicado: %d payables × %d docs gestor',
                $result['scope']['payables'] ?? 0,
                $result['scope']['gestor_docs'] ?? 0,
            ));
        }

        $matching = $result['matching'];
        $this->table(
            ['Métrica', 'Qtd'],
            [
                ['Docs abertos (gestor)', $matching['total_open'] ?? '—'],
                ['Match alta confiança', $matching['high'] ?? 0],
                ['Match média confiança', $matching['medium'] ?? 0],
                ['Match baixa confiança', $matching['low'] ?? 0],
                ['Ambíguos', $matching['ambiguous'] ?? 0],
                ['Sem match', $matching['none'] ?? 0],
                ['Migrados (' . $confidence . ')', count($result['migrated'])],
                ['Falhas', count($result['failures'])],
                ['Falhas workflow', count($result['workflow_failures'] ?? [])],
            ],
        );

        if (! empty($result['workflow_failures'])) {
            $this->warn('Falhas de posicionamento no fluxo:');
            foreach (array_slice($result['workflow_failures'], 0, 10) as $f) {
                $this->line("  - payable #{$f['payable_id']} ({$f['gestor_status']}): {$f['error']}");
            }
        }

        if (! empty($result['migrated'])) {
            $statusBreakdown = collect($result['migrated'])
                ->groupBy('gestor_status')
                ->map->count()
                ->sortDesc();

            $this->newLine();
            $this->info('Breakdown por status gestor (migrados):');
            $this->table(
                ['Status gestor', 'Qtd'],
                $statusBreakdown->map(fn ($c, $s) => [$s, $c])->values()->all(),
            );
        }

        if (! $this->option('skip-files')) {
            $this->line(sprintf(
                'Arquivos: %d tentados, %d importados, %d falhas',
                $result['files']['attempted'],
                $result['files']['imported'],
                $result['files']['failed'],
            ));
        }

        if (! $this->option('skip-comments')) {
            $this->line(sprintf(
                'Comentários: %d tentados, %d importados',
                $result['comments']['attempted'],
                $result['comments']['imported'],
            ));
        }

        $this->newLine();
        $this->info("Relatório: {$reportPath}");

        if (! empty($result['failures'])) {
            $this->warn('Falhas:');
            foreach (array_slice($result['failures'], 0, 10) as $f) {
                $this->line("  - {$f['gestor_id']}: {$f['error']}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
