<?php

namespace App\Console\Commands;

use App\Models\Bordero;
use App\Models\Payable;
use Illuminate\Console\Command;

class RestoreMisclassifiedSyncReadiness extends Command
{
    protected $signature = 'payables:restore-sync-readiness-misclassified
        {--apply : Aplica a correção (sem isso, só simula)}';

    protected $description = 'Restaura títulos em borderô aberto que foram rebaixados para aguardando sync por engano';

    public function handle(): int
    {
        $query = Payable::query()
            ->where('status', Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO)
            ->whereNotNull('bordero_id')
            ->whereHas('bordero', fn ($q) => $q->whereIn('status', [
                Bordero::STATUS_PENDENTE,
                Bordero::STATUS_EM_PREPARACAO,
            ]));

        $items = $query->with('bordero:id,number,status')->get(['id', 'title_number', 'bordero_id', 'status']);
        if ($items->isEmpty()) {
            $this->info('Nenhum título misclassificado encontrado.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Encontrados %d título(s) em borderô aberto com status aguardando sync:', $items->count()));
        foreach ($items as $payable) {
            $this->line(sprintf(
                '  #%d %s → borderô %s (%s)',
                $payable->id,
                $payable->title_number,
                $payable->bordero?->number ?? '?',
                $payable->bordero?->status ?? '?',
            ));
        }

        if (! $this->option('apply')) {
            $this->comment('Simulação. Use --apply para restaurar status pendente.');

            return self::SUCCESS;
        }

        $updated = Payable::query()
            ->whereIn('id', $items->pluck('id'))
            ->update(['status' => 'pendente']);

        $this->info("Restaurados {$updated} título(s) para pendente.");

        return self::SUCCESS;
    }
}
