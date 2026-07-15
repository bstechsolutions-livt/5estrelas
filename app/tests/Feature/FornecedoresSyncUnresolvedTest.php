<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\SeniorSupplier;
use App\Services\Senior\FornecedoresSyncService;
use App\Services\Senior\SeniorFornecedorClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FornecedoresSyncUnresolvedTest extends TestCase
{
    use RefreshDatabase;

    private function makePayable(int $codEmp, int $codFor, string $name = null): Payable
    {
        return Payable::create([
            'title_number' => "T-{$codEmp}-{$codFor}",
            'supplier_name' => $name ?? "Fornecedor {$codFor}",
            'amount' => 100,
            'due_date' => '2026-08-01',
            'status' => 'pendente',
            'codemp' => $codEmp,
            'codfil' => 1,
            'codfor' => $codFor,
            'codtpt' => '01',
            'senior_id' => "{$codEmp}-1-T-{$codEmp}-{$codFor}-01-{$codFor}",
        ]);
    }

    public function test_stub_unresolved_ainda_conta_como_faltante(): void
    {
        $this->makePayable(2, 3944);

        SeniorSupplier::create([
            'cod_emp' => 2,
            'cod_for' => 3944,
            'name' => 'Fornecedor 3944',
            'senior_raw' => ['unresolved' => true, 'at' => now()->toIso8601String()],
            'senior_synced_at' => now(),
        ]);

        $svc = FornecedoresSyncService::make();
        $this->assertSame(1, $svc->countMissingSuppliers());
        $this->assertSame(1, $svc->countUnresolvedStubs());
    }

    public function test_cache_resolvido_nao_conta_como_faltante(): void
    {
        $this->makePayable(2, 100);

        SeniorSupplier::create([
            'cod_emp' => 2,
            'cod_for' => 100,
            'name' => 'FORNECEDOR REAL LTDA',
            'senior_raw' => ['codFor' => 100, 'nomFor' => 'FORNECEDOR REAL LTDA'],
            'senior_synced_at' => now(),
        ]);

        $this->assertSame(0, FornecedoresSyncService::make()->countMissingSuppliers());
    }

    public function test_purge_unresolved_remove_apenas_stubs(): void
    {
        SeniorSupplier::create([
            'cod_emp' => 2,
            'cod_for' => 1,
            'name' => 'Fornecedor 1',
            'senior_raw' => ['unresolved' => true],
            'senior_synced_at' => now(),
        ]);
        SeniorSupplier::create([
            'cod_emp' => 2,
            'cod_for' => 2,
            'name' => 'REAL LTDA',
            'senior_raw' => ['codFor' => 2, 'nomFor' => 'REAL LTDA'],
            'senior_synced_at' => now(),
        ]);

        $svc = FornecedoresSyncService::make();
        $this->assertSame(1, $svc->purgeUnresolvedStubs());
        $this->assertDatabaseMissing('senior_suppliers', ['cod_for' => 1]);
        $this->assertDatabaseHas('senior_suppliers', ['cod_for' => 2, 'name' => 'REAL LTDA']);
    }

    public function test_sync_missing_exportar_sobrescreve_stub_e_enriquece(): void
    {
        config(['senior.enabled' => true]);

        $payable = $this->makePayable(2, 3944, 'Fornecedor 3944');

        SeniorSupplier::create([
            'cod_emp' => 2,
            'cod_for' => 3944,
            'name' => 'Fornecedor 3944',
            'senior_raw' => ['unresolved' => true, 'at' => now()->toIso8601String()],
            'senior_synced_at' => now(),
        ]);

        $fakeClient = new class extends SeniorFornecedorClient {
            public function __construct()
            {
                parent::__construct(config('senior'));
            }

            public function consultarPorCodFor(int $codEmp, int $codFor, int $codFil = 1): ?array
            {
                return [
                    'codEmp' => $codEmp,
                    'codFor' => $codFor,
                    'nomFor' => 'VT SOLUCOES ADMINISTRACAO E SERVICOS LTDA',
                    'cgcCpf' => '19593175000188',
                ];
            }
        };

        $svc = new FornecedoresSyncService(
            $fakeClient,
            new \App\Services\Senior\FornecedorMapper(),
            new \App\Services\Senior\SupplierDisplayNameResolver(),
        );

        $r = $svc->syncMissingFromPayables('test', maxLookups: 10);

        $this->assertSame(1, $r['looked_up']);
        $this->assertGreaterThanOrEqual(1, $r['inserted'] + $r['updated']);
        $this->assertSame(0, $svc->countUnresolvedStubs());
        $this->assertSame('VT SOLUCOES ADMINISTRACAO E SERVICOS LTDA', $payable->fresh()->supplier_name);
    }

    public function test_resolve_name_ignora_stub_unresolved(): void
    {
        SeniorSupplier::create([
            'cod_emp' => 2,
            'cod_for' => 99,
            'name' => 'Fornecedor 99',
            'senior_raw' => ['unresolved' => true],
            'senior_synced_at' => now(),
        ]);

        $this->assertNull(SeniorSupplier::resolveName(2, 99));
    }
}
