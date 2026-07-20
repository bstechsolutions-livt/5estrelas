<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableSyncRun;
use App\Models\SeniorSupplier;
use App\Models\User;
use App\Services\Senior\FornecedoresSyncService;
use App\Services\Senior\PayableLauncherSyncService;
use App\Services\Senior\PayableMapper;
use App\Services\Senior\PayablesSyncService;
use App\Services\Senior\SeniorCpClient;
use App\Services\Senior\SeniorFornecedorClient;
use App\Services\Senior\SeniorPrjContasPagarClient;
use App\Services\Senior\StatusMapper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pós-sync automático: UsuGer (depto) + nome do fornecedor nos inserts e updates.
 */
class PayablePostSyncEnrichTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrich_by_ids_grava_senior_cod_usu(): void
    {
        config(['senior.enabled' => true]);

        $payable = Payable::create([
            'title_number' => '9730171/36',
            'supplier_name' => 'Fornecedor 219',
            'amount' => 97301.71,
            'due_date' => '2026-08-01',
            'status' => 'pendente',
            'codemp' => 8,
            'codfil' => 1,
            'codfor' => 219,
            'codtpt' => '01',
            'senior_id' => '8-1-9730171/36-01-219',
        ]);

        $client = new class extends SeniorPrjContasPagarClient {
            public function __construct()
            {
                parent::__construct(config('senior'));
            }

            public function exportarEspecifico(
                int $codEmp,
                int $codFil,
                string $numTit,
                int $codFor,
                string $codTpt,
            ): ?array {
                return [
                    'NumTit' => $numTit,
                    'CodEmp' => $codEmp,
                    'CodFil' => $codFil,
                    'CodFor' => $codFor,
                    'CodTpt' => $codTpt,
                    'UsuGer' => 55,
                ];
            }

            public function consultarGeral(int $codEmp, int $codFil): array
            {
                return [];
            }
        };

        $r = (new PayableLauncherSyncService($client))->enrichByPayableIds([$payable->id], 10, 'test');

        $this->assertSame('ok', $r['status']);
        $this->assertSame(1, $r['updated']);
        $this->assertSame(55, (int) $payable->fresh()->senior_cod_usu);
    }

    public function test_sync_missing_prioriza_pares_dos_ids_inseridos(): void
    {
        config(['senior.enabled' => true]);

        Payable::create([
            'title_number' => 'OLD-1',
            'supplier_name' => 'Fornecedor 10',
            'amount' => 1,
            'due_date' => '2026-07-01',
            'status' => 'pendente',
            'codemp' => 2,
            'codfor' => 10,
        ]);
        $new = Payable::create([
            'title_number' => 'NEW-219',
            'supplier_name' => 'Fornecedor 219',
            'amount' => 1,
            'due_date' => '2026-07-01',
            'status' => 'pendente',
            'codemp' => 8,
            'codfor' => 219,
        ]);

        $looked = [];
        $client = new class($looked) extends SeniorFornecedorClient {
            public function __construct(private array &$looked)
            {
                parent::__construct(config('senior'));
            }

            public function consultarPorCodFor(int $codEmp, int $codFor, int $codFil = 1): ?array
            {
                $this->looked[] = [$codEmp, $codFor];

                return [
                    'codEmp' => $codEmp,
                    'codFor' => $codFor,
                    'nomFor' => $codFor === 219 ? 'PROGIRO SERVICOS' : 'OUTRO',
                ];
            }
        };

        $service = new FornecedoresSyncService(
            $client,
            new \App\Services\Senior\FornecedorMapper(),
            new \App\Services\Senior\SupplierDisplayNameResolver(),
        );

        $r = $service->syncMissingFromPayables('test', maxLookups: 1, prioritizePayableIds: [$new->id]);

        $this->assertSame([[8, 219]], $looked);
        $this->assertSame(1, $r['looked_up']);
        $this->assertDatabaseHas('senior_suppliers', [
            'cod_emp' => 8,
            'cod_for' => 219,
            'name' => 'PROGIRO SERVICOS',
        ]);
        $this->assertSame('PROGIRO SERVICOS', $new->fresh()->supplier_name);
    }

    public function test_sync_payables_usa_cache_fornecedor_no_insert(): void
    {
        config([
            'senior.enabled' => true,
            'senior.cod_emps' => [8],
            'senior.emp_enabled' => [],
            'senior.cp_strategy' => 'sweep',
            'senior.cod_for_start' => 219,
            'senior.cod_for_end' => 219,
            'senior.post_sync_launcher_lookups' => 0,
            'senior.post_sync_supplier_lookups' => 0,
        ]);

        SeniorSupplier::create([
            'cod_emp' => 8,
            'cod_for' => 219,
            'name' => 'PROGIRO SERVICOS LTDA',
            'senior_synced_at' => now(),
        ]);

        $titulo = [
            'codEmp' => 8, 'codFil' => 1, 'numTit' => '9730171/36', 'codTpt' => '01', 'codFor' => 219,
            'sitTit' => 'NOR', 'vlrOri' => 97301.71, 'vlrAbe' => 97301.71, 'vctPro' => '2026-08-01',
            'obsTcp' => 'PARCELA PROGIRO',
            'rateios' => [['perRat' => 100, 'vlrRat' => 97301.71, 'seqRat' => 1]],
        ];

        $fakeCp = new class([$titulo]) extends SeniorCpClient {
            public function __construct(private array $fakeTitulos)
            {
                parent::__construct(config('senior'));
            }

            public function consultarTitulosPorFornecedor(int $codEmp, int $codFor, ?Carbon $vctIni, ?Carbon $vctFim): array
            {
                return $this->fakeTitulos;
            }
        };

        $run = (new PayablesSyncService($fakeCp, new PayableMapper(), new StatusMapper()))
            ->run(PayableSyncRun::MODE_FULL);

        $this->assertSame(PayableSyncRun::STATUS_SUCCESS, $run->status);
        $payable = Payable::where('title_number', '9730171/36')->firstOrFail();
        $this->assertSame('PROGIRO SERVICOS LTDA', $payable->supplier_name);
    }

    public function test_resync_preenche_fornecedor_quando_cache_chega_depois(): void
    {
        config([
            'senior.enabled' => true,
            'senior.cod_emps' => [8],
            'senior.emp_enabled' => [],
            'senior.cp_strategy' => 'sweep',
            'senior.cod_for_start' => 219,
            'senior.cod_for_end' => 219,
            'senior.post_sync_launcher_lookups' => 0,
            'senior.post_sync_supplier_lookups' => 0,
        ]);

        $titulo = [
            'codEmp' => 8, 'codFil' => 1, 'numTit' => '9730171/36', 'codTpt' => '01', 'codFor' => 219,
            'sitTit' => 'NOR', 'vlrOri' => 100.0, 'vlrAbe' => 100.0, 'vctPro' => '2026-08-01',
            'rateios' => [['perRat' => 100, 'vlrRat' => 100.0, 'seqRat' => 1]],
        ];

        $fakeCp = new class([$titulo]) extends SeniorCpClient {
            public function __construct(private array $fakeTitulos)
            {
                parent::__construct(config('senior'));
            }

            public function consultarTitulosPorFornecedor(int $codEmp, int $codFor, ?Carbon $vctIni, ?Carbon $vctFim): array
            {
                return $this->fakeTitulos;
            }
        };

        $service = new PayablesSyncService($fakeCp, new PayableMapper(), new StatusMapper());
        $service->run(PayableSyncRun::MODE_FULL);

        $payable = Payable::where('title_number', '9730171/36')->firstOrFail();
        $this->assertTrue((new \App\Services\Senior\SupplierDisplayNameResolver())->isGeneric($payable->supplier_name));

        SeniorSupplier::create([
            'cod_emp' => 8,
            'cod_for' => 219,
            'name' => 'PROGIRO SERVICOS LTDA',
            'senior_synced_at' => now(),
        ]);

        // Raw igual — ainda assim deve preencher o nome a partir do cache.
        $service->run(PayableSyncRun::MODE_FULL);

        $this->assertSame('PROGIRO SERVICOS LTDA', $payable->fresh()->supplier_name);
    }

    public function test_depto_vem_do_usuario_do_usuger(): void
    {
        $compras = Department::create(['name' => 'Compras', 'slug' => 'compras', 'is_active' => true]);
        Department::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'is_active' => true]);
        User::factory()->create([
            'is_active' => true,
            'senior_cod_usu' => 55,
            'department_id' => $compras->id,
        ]);

        $payable = Payable::create([
            'title_number' => 'T-USU',
            'supplier_name' => 'ACME',
            'amount' => 10,
            'due_date' => '2026-08-01',
            'status' => 'pendente',
            'senior_cod_usu' => 55,
        ]);

        $assigned = (new PayablesSyncService(
            new class extends SeniorCpClient {
                public function __construct()
                {
                    parent::__construct(config('senior'));
                }
            },
            new PayableMapper(),
            new StatusMapper(),
        ))->resolveDepartmentsAfterSync([$payable->id]);

        $this->assertSame(1, $assigned);
        $this->assertSame($compras->id, (int) $payable->fresh()->department_id);
    }

    public function test_depto_aguarda_vinculo_quando_sem_lancador(): void
    {
        Department::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'is_active' => true]);

        $payable = Payable::create([
            'title_number' => 'T-FIN',
            'supplier_name' => 'ACME',
            'amount' => 10,
            'due_date' => '2026-08-01',
            'status' => 'pendente',
            'senior_id' => '2-1-T-FIN-01-1',
        ]);

        $changed = (new PayablesSyncService(
            new class extends SeniorCpClient {
                public function __construct()
                {
                    parent::__construct(config('senior'));
                }
            },
            new PayableMapper(),
            new StatusMapper(),
        ))->resolveDepartmentsAfterSync([$payable->id]);

        $this->assertSame(1, $changed);
        $fresh = $payable->fresh();
        $this->assertNull($fresh->department_id);
        $this->assertSame(Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO, $fresh->status);
    }

    public function test_depto_liberado_quando_usuger_mapeado(): void
    {
        $compras = Department::create(['name' => 'Compras', 'slug' => 'compras', 'is_active' => true]);
        Department::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'is_active' => true]);
        User::factory()->create([
            'is_active' => true,
            'senior_cod_usu' => 55,
            'department_id' => $compras->id,
        ]);

        $payable = Payable::create([
            'title_number' => 'T-UNLOCK',
            'supplier_name' => 'ACME LTDA',
            'amount' => 10,
            'due_date' => '2026-08-01',
            'status' => Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO,
            'senior_id' => '2-1-T-UNLOCK-01-1',
            'senior_cod_usu' => 55,
        ]);

        $changed = (new PayablesSyncService(
            new class extends SeniorCpClient {
                public function __construct()
                {
                    parent::__construct(config('senior'));
                }
            },
            new PayableMapper(),
            new StatusMapper(),
        ))->resolveDepartmentsAfterSync([$payable->id]);

        $this->assertSame(1, $changed);
        $fresh = $payable->fresh();
        $this->assertSame($compras->id, (int) $fresh->department_id);
        $this->assertSame('pendente', $fresh->status);
    }

    public function test_aguarda_sync_quando_fornecedor_generico(): void
    {
        $compras = Department::create(['name' => 'Compras', 'slug' => 'compras', 'is_active' => true]);
        User::factory()->create([
            'is_active' => true,
            'senior_cod_usu' => 77,
            'department_id' => $compras->id,
        ]);

        $payable = Payable::create([
            'title_number' => 'T-SUP',
            'supplier_name' => 'Fornecedor 999',
            'amount' => 10,
            'due_date' => '2026-08-01',
            'status' => 'pendente',
            'senior_id' => '2-1-T-SUP-01-1',
            'senior_cod_usu' => 77,
        ]);

        $changed = (new PayablesSyncService(
            new class extends SeniorCpClient {
                public function __construct()
                {
                    parent::__construct(config('senior'));
                }
            },
            new PayableMapper(),
            new StatusMapper(),
        ))->resolveDepartmentsAfterSync([$payable->id]);

        $this->assertSame(1, $changed);
        $fresh = $payable->fresh();
        $this->assertSame($compras->id, (int) $fresh->department_id);
        $this->assertSame(Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO, $fresh->status);
    }

    public function test_financeiro_fallback_stale_sem_usuger_vai_para_aguardando(): void
    {
        $financeiro = Department::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'is_active' => true]);

        $payable = Payable::create([
            'title_number' => 'T-STALE-FIN',
            'supplier_name' => 'ACME LTDA',
            'amount' => 10,
            'due_date' => '2026-08-01',
            'status' => 'pendente',
            'senior_id' => '2-1-T-STALE-FIN-01-1',
            'department_id' => $financeiro->id,
        ]);

        $changed = (new PayablesSyncService(
            new class extends SeniorCpClient {
                public function __construct()
                {
                    parent::__construct(config('senior'));
                }
            },
            new PayableMapper(),
            new StatusMapper(),
        ))->resolveDepartmentsAfterSync([$payable->id]);

        $this->assertSame(1, $changed);
        $fresh = $payable->fresh();
        $this->assertNull($fresh->department_id);
        $this->assertSame(Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO, $fresh->status);
    }

    public function test_sync_insert_aguarda_vinculo_sem_lancador(): void
    {
        Department::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'is_active' => true]);

        config([
            'senior.enabled' => true,
            'senior.cod_emps' => [8],
            'senior.emp_enabled' => [],
            'senior.cp_strategy' => 'sweep',
            'senior.cod_for_start' => 219,
            'senior.cod_for_end' => 219,
            'senior.post_sync_launcher_lookups' => 0,
            'senior.post_sync_supplier_lookups' => 0,
        ]);

        $titulo = [
            'codEmp' => 8, 'codFil' => 1, 'numTit' => 'T-FB', 'codTpt' => '01', 'codFor' => 219,
            'sitTit' => 'NOR', 'vlrOri' => 50.0, 'vlrAbe' => 50.0, 'vctPro' => '2026-08-01',
            'rateios' => [['perRat' => 100, 'vlrRat' => 50.0, 'seqRat' => 1]],
        ];

        $fakeCp = new class([$titulo]) extends SeniorCpClient {
            public function __construct(private array $fakeTitulos)
            {
                parent::__construct(config('senior'));
            }

            public function consultarTitulosPorFornecedor(int $codEmp, int $codFor, ?Carbon $vctIni, ?Carbon $vctFim): array
            {
                return $this->fakeTitulos;
            }
        };

        (new PayablesSyncService($fakeCp, new PayableMapper(), new StatusMapper()))
            ->run(PayableSyncRun::MODE_FULL);

        $payable = Payable::where('title_number', 'T-FB')->firstOrFail();
        $this->assertNull($payable->department_id);
        $this->assertSame(Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO, $payable->status);
    }
}
