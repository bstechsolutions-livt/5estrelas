<?php

namespace App\Console\Commands;

use App\Services\GestorConciliacoesMigrationService;
use Illuminate\Console\Command;

class ResyncGestorWorkflow extends Command
{
    protected $signature = 'gestor:resync-workflow
        {--dry-run : Apenas simula, sem gravar (default se --execute omitido)}
        {--execute : Aplica alterações}
        {--confidence=high : Nível mínimo de confiança (high|medium|low)}
        {--export-path= : Caminho do export unpacked do Convex}
        {--report= : Caminho do relatório JSON}';

    protected $description = 'Reaplica status e posicionamento de workflow Gestor → payables já migrados';

    public function handle(): int
    {
        $exportPath = $this->option('export-path')
            ?: base_path('../infra/legado/exports/unpacked');

        $reportPath = $this->option('report')
            ?: storage_path('app/gestor-workflow-resync-report.json');

        $confidence = (string) $this->option('confidence');
        if (! in_array($confidence, ['high', 'medium', 'low'], true)) {
            $this->error('confidence deve ser high, medium ou low');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        if ($this->option('dry-run')) {
            $execute = false;
        }

        if (! is_dir($exportPath)) {
            $this->error("Export não encontrado: {$exportPath}");

            return self::FAILURE;
        }

        if ($execute) {
            $this->warn('Modo EXECUTE — status e etapas serão atualizados.');
        } else {
            $this->info('Modo DRY-RUN (use --execute para aplicar).');
        }

        $service = new GestorConciliacoesMigrationService(
            exportPath: $exportPath,
            confidence: $confidence,
            execute: $execute,
            skipComments: true,
            skipFiles: true,
            filesOnly: false,
            reportPath: $reportPath,
            workflowOnly: true,
        );

        $result = $service->run();

        $matching = $result['matching'];
        $this->table(
            ['Métrica', 'Qtd'],
            [
                ['Docs abertos (gestor)', $matching['total_open'] ?? '—'],
                ['Match alta confiança', $matching['high'] ?? 0],
                ['Re-sincronizados (' . $confidence . ')', count($result['migrated'])],
                ['Falhas gerais', count($result['failures'])],
                ['Falhas workflow', count($result['workflow_failures'] ?? [])],
            ],
        );

        if (! empty($result['status_transitions'])) {
            $this->newLine();
            $this->info('Transições gestor → intranet:');
            $rows = collect($result['status_transitions'])
                ->sortDesc()
                ->map(fn ($count, $key) => [$key, $count])
                ->values()
                ->all();
            $this->table(['Transição', 'Qtd'], $rows);
        }

        if (! empty($result['workflow_failures'])) {
            $this->newLine();
            $this->warn('Payables sem posicionamento de workflow:');
            foreach (array_slice($result['workflow_failures'], 0, 20) as $failure) {
                $this->line(sprintf(
                    '  - payable #%s (gestor %s / %s): %s',
                    $failure['payable_id'] ?? '—',
                    $failure['gestor_id'] ?? '—',
                    $failure['gestor_status'] ?? '—',
                    $failure['error'] ?? 'erro',
                ));
            }
            if (count($result['workflow_failures']) > 20) {
                $this->line('  ... e mais ' . (count($result['workflow_failures']) - 20));
            }
        }

        if (! empty($result['failures'])) {
            $this->newLine();
            $this->warn('Falhas gerais:');
            foreach (array_slice($result['failures'], 0, 10) as $f) {
                $this->line("  - {$f['gestor_id']}: {$f['error']}");
            }
        }

        $this->newLine();
        $this->info("Relatório: {$reportPath}");

        $hasFailures = ! empty($result['failures']) || ! empty($result['workflow_failures']);

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }
}
