<?php

namespace App\Console\Commands;

use App\Models\Payable;
use App\Services\Senior\PayableMapper;
use Illuminate\Console\Command;

class BackfillPayableSeniorCodUsu extends Command
{
    protected $signature = 'payables:backfill-senior-cod-usu
        {--dry-run : Apenas simula, sem gravar}';

    protected $description = 'Preenche payables.senior_cod_usu a partir de senior_raw (codUsu/codFav)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $empty = 0;

        Payable::query()
            ->whereNotNull('senior_raw')
            ->whereNull('senior_cod_usu')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($dryRun, &$updated, &$empty) {
                foreach ($chunk as $payable) {
                    $cod = PayableMapper::resolveLauncherCodUsu($payable->senior_raw ?? []);
                    if (!$cod) {
                        $empty++;

                        continue;
                    }

                    if (!$dryRun) {
                        $payable->update(['senior_cod_usu' => $cod]);
                    }

                    $updated++;
                }
            });

        $this->info("Concluído: {$updated} preenchidos, {$empty} sem codUsu/codFav > 0 no senior_raw.");

        return self::SUCCESS;
    }
}
