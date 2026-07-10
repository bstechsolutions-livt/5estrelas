<?php

namespace App\Console\Commands;

use App\Services\Senior\FornecedoresSyncService;
use Illuminate\Console\Command;

class SyncSeniorFornecedores extends Command
{
    protected $signature = 'senior:sync-fornecedores {--scheduled : Marca a execução como agendada}';

    protected $description = 'Sincroniza fornecedores da Senior (cad_fornecedor) e enriquece títulos';

    public function handle(): int
    {
        $r = FornecedoresSyncService::make()->run(
            $this->option('scheduled') ? 'agendado' : 'manual'
        );

        $this->info(sprintf(
            'Sync fornecedores [%s]: %d inseridos, %d atualizados, %d erros, %d títulos enriquecidos.',
            $r['status'],
            $r['inserted'],
            $r['updated'],
            $r['errors'],
            $r['enriched'],
        ));

        if ($r['status'] === 'failed') {
            $this->error('Falha: ' . ($r['message'] ?? 'desconhecida'));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
