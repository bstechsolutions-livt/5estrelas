<?php

namespace App\Jobs\Senior;

use App\Services\Senior\FornecedoresSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncPayableSuppliersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout;

    /**
     * @param  list<int>  $payableIds
     */
    public function __construct(
        public array $payableIds,
        public int $maxLookups,
        public string $trigger = 'queue',
    ) {
        $this->timeout = max(60, (int) config('senior.sync_http_timeout', 120));
        $this->onQueue((string) config('senior.enrich_supplier_queue', 'senior-supplier'));
    }

    public function handle(): void
    {
        if ($this->payableIds === [] || $this->maxLookups <= 0) {
            return;
        }

        $result = FornecedoresSyncService::make()->syncMissingFromPayables(
            $this->trigger,
            maxLookups: $this->maxLookups,
            prioritizePayableIds: $this->payableIds,
        );

        Log::info('[senior-enrich-queue] fornecedores', [
            'trigger' => $this->trigger,
            'payable_ids' => count($this->payableIds),
            'result' => $result,
        ]);
    }
}
