<?php

namespace App\Jobs\Senior;

use App\Services\Senior\PayableLauncherSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnrichPayableLaunchersJob implements ShouldQueue
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
        $this->onQueue((string) config('senior.enrich_launcher_queue', 'senior-launcher'));
    }

    public function handle(): void
    {
        if ($this->payableIds === [] || $this->maxLookups <= 0) {
            return;
        }

        $result = PayableLauncherSyncService::make()->enrichByPayableIds(
            $this->payableIds,
            maxLookups: $this->maxLookups,
            trigger: $this->trigger,
        );

        Log::info('[senior-enrich-queue] lançadores', [
            'trigger' => $this->trigger,
            'payable_ids' => count($this->payableIds),
            'result' => $result,
        ]);
    }
}
