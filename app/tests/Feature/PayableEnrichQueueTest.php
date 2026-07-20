<?php

namespace Tests\Feature;

use App\Jobs\Senior\EnrichPayableLaunchersJob;
use App\Jobs\Senior\ResolvePayableReadinessJob;
use App\Jobs\Senior\SyncPayableSuppliersJob;
use App\Models\Payable;
use App\Services\Senior\PayableEnrichQueueDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PayableEnrichQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_enfileira_jobs_quando_fila_ativa(): void
    {
        Queue::fake();

        config([
            'senior.enrich_use_queue' => true,
            'senior.post_sync_launcher_lookups' => 0,
            'senior.post_sync_supplier_lookups' => 0,
            'senior.enrich_job_chunk_size' => 50,
        ]);

        $payable = Payable::create([
            'title_number' => 'Q-1',
            'supplier_name' => 'Fornecedor 1',
            'amount' => 10,
            'due_date' => '2026-08-01',
            'status' => Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO,
            'senior_id' => '2-1-Q-1-01-1',
        ]);

        $result = PayableEnrichQueueDispatcher::make()->dispatchPostSync([$payable->id], 'test');

        $this->assertSame(1, $result['payable_ids']);
        $this->assertSame(0, $result['launcher_jobs']);
        $this->assertSame(0, $result['supplier_jobs']);
        $this->assertSame(1, $result['readiness_jobs']);

        Queue::assertPushed(ResolvePayableReadinessJob::class, 1);
        Queue::assertNotPushed(EnrichPayableLaunchersJob::class);
        Queue::assertNotPushed(SyncPayableSuppliersJob::class);
    }

    public function test_dispatcher_cron_enfileira_lancador_e_fornecedor(): void
    {
        Queue::fake();

        config([
            'senior.enrich_cron_launcher_max' => 10,
            'senior.enrich_cron_supplier_max' => 10,
            'senior.enrich_job_chunk_size' => 50,
        ]);

        Payable::create([
            'title_number' => 'Q-2',
            'supplier_name' => 'Fornecedor 2',
            'amount' => 10,
            'due_date' => '2026-08-01',
            'status' => Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO,
            'senior_id' => '2-1-Q-2-01-1',
        ]);

        $result = PayableEnrichQueueDispatcher::make()->dispatchCron('test');

        $this->assertSame(1, $result['payable_ids']);
        $this->assertSame(1, $result['launcher_jobs']);
        $this->assertSame(1, $result['supplier_jobs']);
        $this->assertSame(1, $result['readiness_jobs']);

        Queue::assertPushed(EnrichPayableLaunchersJob::class, 1);
        Queue::assertPushed(SyncPayableSuppliersJob::class, 1);
        Queue::assertPushed(ResolvePayableReadinessJob::class, 1);
    }
}
