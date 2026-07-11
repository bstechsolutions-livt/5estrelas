<?php

namespace App\Console\Commands;

use App\Services\Senior\FornecedoresSyncService;
use Illuminate\Console\Command;

class SyncSeniorFornecedores extends Command
{
    protected $signature = 'senior:sync-fornecedores
        {--full : Sincroniza o catálogo completo via paginação (bootstrap/noturno)}
        {--missing : Sincroniza só fornecedores dos títulos sem cache (default)}
        {--scheduled : Marca a execução como agendada}';

    protected $description = 'Sincroniza fornecedores da Senior (cad_fornecedor) e enriquece títulos';

    public function handle(): int
    {
        $full = (bool) $this->option('full');
        $trigger = $this->option('scheduled') ? 'agendado' : 'manual';

        $r = FornecedoresSyncService::make()->run($trigger, $full);

        $mode = $full ? 'full' : 'delta';
        $this->info(sprintf(
            'Sync fornecedores [%s/%s]: %d pendentes, %d inseridos, %d atualizados, %d erros, %d enriquecidos (cache), %d enriquecidos (descrição).',
            $r['status'],
            $mode,
            $r['looked_up'] ?? 0,
            $r['inserted'],
            $r['updated'],
            $r['errors'],
            $r['enriched'],
            $r['enriched_desc'] ?? 0,
        ));

        if ($r['status'] === 'failed') {
            $this->error('Falha: ' . ($r['message'] ?? 'desconhecida'));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
