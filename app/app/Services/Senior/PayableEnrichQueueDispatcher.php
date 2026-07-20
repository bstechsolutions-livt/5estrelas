<?php

namespace App\Services\Senior;

use App\Jobs\Senior\EnrichPayableLaunchersJob;
use App\Jobs\Senior\ResolvePayableReadinessJob;
use App\Jobs\Senior\SyncPayableSuppliersJob;
use Illuminate\Support\Facades\Log;

/**
 * Despacha enrich Senior (UsuGer + fornecedor + prontidão) em filas separadas.
 */
class PayableEnrichQueueDispatcher
{
    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  list<int>  $enrichIds
     * @return array{launcher_jobs: int, supplier_jobs: int, readiness_jobs: int, payable_ids: int}
     */
    public function dispatchPostSync(array $enrichIds, string $trigger = 'pos-payables-sync'): array
    {
        return $this->dispatchBatch($enrichIds, $trigger, [
            'launcher_max' => (int) config('senior.post_sync_launcher_lookups', 0),
            'supplier_max' => (int) config('senior.post_sync_supplier_lookups', 0),
            'merge_awaiting' => true,
            'awaiting_limit' => max(
                (int) config('senior.post_sync_launcher_lookups', 0),
                (int) config('senior.post_sync_supplier_lookups', 0),
                80,
            ),
        ]);
    }

    /**
     * @return array{launcher_jobs: int, supplier_jobs: int, readiness_jobs: int, payable_ids: int}
     */
    public function dispatchCron(string $trigger = 'cron'): array
    {
        $launcherMax = max(1, (int) config('senior.enrich_cron_launcher_max', 80));
        $supplierMax = max(1, (int) config('senior.enrich_cron_supplier_max', 80));
        $awaitingLimit = max($launcherMax, $supplierMax, 200);

        $awaitingIds = PayablesSyncService::make()->awaitingSyncPayableIds($awaitingLimit);

        return $this->dispatchBatch($awaitingIds, $trigger, [
            'launcher_max' => $launcherMax,
            'supplier_max' => $supplierMax,
            'merge_awaiting' => false,
            'awaiting_limit' => 0,
        ]);
    }

    /**
     * @param  list<int>  $enrichIds
     * @param  array{launcher_max: int, supplier_max: int, merge_awaiting: bool, awaiting_limit: int}  $limits
     * @return array{launcher_jobs: int, supplier_jobs: int, readiness_jobs: int, payable_ids: int}
     */
    public function dispatchBatch(array $enrichIds, string $trigger, array $limits): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $enrichIds), fn (int $id) => $id > 0)));

        if (($limits['merge_awaiting'] ?? false) && (int) ($limits['awaiting_limit'] ?? 0) > 0) {
            $ids = PayablesSyncService::make()->mergedEnrichPayableIds(
                $ids,
                (int) $limits['awaiting_limit'],
            );
        }

        if ($ids === []) {
            return [
                'launcher_jobs' => 0,
                'supplier_jobs' => 0,
                'readiness_jobs' => 0,
                'payable_ids' => 0,
            ];
        }

        $chunkSize = max(1, (int) config('senior.enrich_job_chunk_size', 40));
        $launcherQueue = (string) config('senior.enrich_launcher_queue', 'senior-launcher');
        $supplierQueue = (string) config('senior.enrich_supplier_queue', 'senior-supplier');
        $readinessQueue = (string) config('senior.enrich_readiness_queue', 'senior-readiness');
        $launcherMax = max(0, (int) ($limits['launcher_max'] ?? 0));
        $supplierMax = max(0, (int) ($limits['supplier_max'] ?? 0));

        $launcherJobs = 0;
        $supplierJobs = 0;
        $readinessJobs = 0;

        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            if ($launcherMax > 0) {
                EnrichPayableLaunchersJob::dispatch($chunk, $launcherMax, $trigger)
                    ->onQueue($launcherQueue);
                $launcherJobs++;
            }

            if ($supplierMax > 0) {
                SyncPayableSuppliersJob::dispatch($chunk, $supplierMax, $trigger)
                    ->onQueue($supplierQueue);
                $supplierJobs++;
            }

            ResolvePayableReadinessJob::dispatch($chunk, $trigger)
                ->onQueue($readinessQueue);
            $readinessJobs++;
        }

        Log::info('[senior-enrich-queue] jobs despachados', [
            'trigger' => $trigger,
            'payable_ids' => count($ids),
            'launcher_jobs' => $launcherJobs,
            'supplier_jobs' => $supplierJobs,
            'readiness_jobs' => $readinessJobs,
        ]);

        return [
            'launcher_jobs' => $launcherJobs,
            'supplier_jobs' => $supplierJobs,
            'readiness_jobs' => $readinessJobs,
            'payable_ids' => count($ids),
        ];
    }
}
