<?php

namespace App\Jobs\Senior;

use App\Services\Senior\FornecedoresSyncService;
use App\Services\Senior\PayablesSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ResolvePayableReadinessJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  list<int>  $payableIds
     */
    public function __construct(
        public array $payableIds,
        public string $trigger = 'queue',
        public bool $cacheEnrichFirst = true,
    ) {
        $this->onQueue((string) config('senior.enrich_readiness_queue', 'senior-readiness'));
    }

    public function handle(): void
    {
        if ($this->payableIds === []) {
            return;
        }

        if ($this->cacheEnrichFirst) {
            FornecedoresSyncService::make()->enrichPayables($this->payableIds);
        }

        $changed = PayablesSyncService::make()->resolveDepartmentsAfterSync($this->payableIds);

        Log::info('[senior-enrich-queue] prontidão', [
            'trigger' => $this->trigger,
            'payable_ids' => count($this->payableIds),
            'changed' => $changed,
        ]);
    }
}
