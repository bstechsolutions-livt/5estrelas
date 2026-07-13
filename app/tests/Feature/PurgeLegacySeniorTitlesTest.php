<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\PayableSyncRun;
use App\Services\Senior\PayableMapper;
use App\Services\Senior\PayablesSyncService;
use App\Services\Senior\SeniorCpClient;
use App\Services\Senior\StatusMapper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeLegacySeniorTitlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_remove_payables_before_2026(): void
    {
        Payable::create([
            'title_number' => 'OLD',
            'supplier_name' => 'Fornecedor A',
            'amount' => 100,
            'due_date' => '2025-12-31',
            'senior_id' => 'old-1',
            'status' => 'pendente',
        ]);
        Payable::create([
            'title_number' => 'NEW',
            'supplier_name' => 'Fornecedor B',
            'amount' => 200,
            'due_date' => '2026-01-15',
            'senior_id' => 'new-1',
            'status' => 'pendente',
        ]);

        $this->artisan('senior:purge-legacy-titles', ['--force' => true, '--cp' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('payables', ['senior_id' => 'old-1']);
        $this->assertDatabaseHas('payables', ['senior_id' => 'new-1']);
    }

    public function test_sync_ignores_titles_before_min_due_date(): void
    {
        config([
            'senior.enabled' => true,
            'senior.cod_emps' => [1],
            'senior.emp_enabled' => [],
            'senior.cp_strategy' => 'sweep',
            'senior.cod_for_start' => 1000,
            'senior.cod_for_end' => 1000,
            'senior.min_due_date' => '2026-01-01',
        ]);

        $titulos = [
            [
                'codEmp' => 1, 'codFil' => 1, 'numTit' => 'OLD', 'codTpt' => 'DP', 'codFor' => 1000,
                'sitTit' => 'NOR', 'vlrOri' => 100, 'vlrAbe' => 100, 'vctPro' => '2025-06-01',
            ],
            [
                'codEmp' => 1, 'codFil' => 1, 'numTit' => 'NEW', 'codTpt' => 'DP', 'codFor' => 1000,
                'sitTit' => 'NOR', 'vlrOri' => 200, 'vlrAbe' => 200, 'vctPro' => '2026-07-01',
            ],
        ];

        $fake = new class($titulos) extends SeniorCpClient {
            public function __construct(private array $fakeTitulos)
            {
                parent::__construct(config('senior'));
            }

            public function consultarTitulosPorFornecedor(int $codEmp, int $codFor, ?Carbon $vctIni = null, ?Carbon $vctFim = null): array
            {
                return $this->fakeTitulos;
            }
        };

        $service = new PayablesSyncService($fake, new PayableMapper(), new StatusMapper());
        $service->run(PayableSyncRun::MODE_FULL, PayableSyncRun::TRIGGER_MANUAL);

        $this->assertEquals(1, Payable::count());
        $this->assertDatabaseHas('payables', ['title_number' => 'NEW']);
        $this->assertDatabaseMissing('payables', ['title_number' => 'OLD']);
    }
}
