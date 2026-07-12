<?php

namespace App\Services\Senior;

use App\Models\Payable;
use App\Models\SeniorSupplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FornecedoresSyncService
{
    public function __construct(
        private SeniorFornecedorClient $client,
        private FornecedorMapper $mapper,
        private SupplierDisplayNameResolver $displayNameResolver,
    ) {
    }

    public static function make(): self
    {
        return new self(
            SeniorFornecedorClient::fromConfig(),
            new FornecedorMapper(),
            new SupplierDisplayNameResolver(),
        );
    }

    /**
     * @return array{status:string, inserted:int, updated:int, errors:int, enriched:int, enriched_desc:int, message:?string, looked_up:int}
     */
    public function run(string $trigger = 'manual', bool $fullCatalog = false): array
    {
        if (!config('senior.enabled', false)) {
            return $this->skippedResult();
        }

        if ($fullCatalog) {
            return $this->syncFullCatalog($trigger);
        }

        return $this->syncMissingFromPayables($trigger);
    }

    /**
     * Delta: busca na Senior só os codFor que aparecem em títulos e ainda não estão no cache.
     */
    public function syncMissingFromPayables(string $trigger = 'manual'): array
    {
        if (!config('senior.enabled', false)) {
            return $this->skippedResult();
        }

        $missingByEmp = $this->missingSupplierPairs()
            ->groupBy(fn ($pair) => (int) $pair->codemp)
            ->map(fn ($rows) => $rows->pluck('codfor')->map(fn ($v) => (int) $v)->unique()->values()->all());

        $inserted = 0;
        $updated = 0;
        $errors = 0;
        $lookedUp = 0;
        $codFil = (int) config('senior.cod_fil', 1);

        foreach ($missingByEmp as $codEmp => $missingList) {
            foreach ($missingList as $codFor) {
                if ($codFor < 1) {
                    continue;
                }
                $lookedUp++;
                try {
                    $fornecedor = $this->client->consultarPorCodFor((int) $codEmp, (int) $codFor, $codFil);
                } catch (SeniorException $e) {
                    $errors++;
                    Log::debug('[senior-fornecedor] codFor não resolvido', [
                        'codEmp' => $codEmp,
                        'codFor' => $codFor,
                        'erro' => $e->getMessage(),
                    ]);
                    continue;
                }

                if ($fornecedor === null) {
                    continue;
                }

                $fornecedor['codEmp'] ??= (int) $codEmp;
                DB::transaction(function () use ($fornecedor, &$inserted, &$updated) {
                    $this->upsert($fornecedor, $inserted, $updated);
                });
            }
        }

        $enriched = $this->enrichPayables();
        $enrichedDesc = $this->enrichFromDescriptions();

        if ($lookedUp > 0 || $enriched > 0 || $enrichedDesc > 0) {
            Log::info('[senior-fornecedor] sync delta concluído', [
                'trigger' => $trigger,
                'looked_up' => $lookedUp,
                'inserted' => $inserted,
                'updated' => $updated,
                'enriched' => $enriched,
                'enriched_desc' => $enrichedDesc,
                'errors' => $errors,
            ]);
        }

        return [
            'status' => $errors > 0 && $inserted + $updated === 0 && $lookedUp > 0 ? 'failed' : 'success',
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors,
            'enriched' => $enriched,
            'enriched_desc' => $enrichedDesc,
            'looked_up' => $lookedUp,
            'message' => null,
        ];
    }

    /**
     * Full: pagina o catálogo inteiro via ConsultarGeral (bootstrap / manutenção noturna).
     *
     * @return array{status:string, inserted:int, updated:int, errors:int, enriched:int, enriched_desc:int, message:?string, looked_up:int}
     */
    public function syncFullCatalog(string $trigger = 'manual'): array
    {
        $codEmps = config('senior.cod_emps') ?: [(int) config('senior.cod_emp', 2)];
        $inserted = 0;
        $updated = 0;
        $errors = 0;
        $pageSize = max(10, (int) config('senior.fornecedor_page_size', 100));
        $maxPages = max(1, (int) config('senior.fornecedor_max_pages', 500));

        foreach ($codEmps as $codEmp) {
            $indicePagina = 1;
            $pages = 0;
            while ($pages < $maxPages) {
                try {
                    $fornecedores = $this->client->consultarGeral((int) $codEmp, 1, $indicePagina, $pageSize);
                    if ($fornecedores === []) {
                        break;
                    }
                    foreach ($fornecedores as $fornecedor) {
                        $fornecedor['codEmp'] ??= (int) $codEmp;
                        DB::transaction(function () use ($fornecedor, &$inserted, &$updated) {
                            $this->upsert($fornecedor, $inserted, $updated);
                        });
                    }
                    $indicePagina++;
                    $pages++;
                } catch (SeniorException $e) {
                    $errors++;
                    Log::warning('[senior-fornecedor] erro na empresa (full)', ['codEmp' => $codEmp, 'erro' => $e->getMessage()]);
                    break;
                }
            }
        }

        $enriched = $this->enrichPayables();
        $enrichedDesc = $this->enrichFromDescriptions();

        Log::info('[senior-fornecedor] sync full concluído', [
            'trigger' => $trigger,
            'inserted' => $inserted,
            'updated' => $updated,
            'enriched' => $enriched,
            'enriched_desc' => $enrichedDesc,
            'errors' => $errors,
        ]);

        return [
            'status' => $errors > 0 && $inserted + $updated === 0 ? 'failed' : 'success',
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors,
            'enriched' => $enriched,
            'enriched_desc' => $enrichedDesc,
            'looked_up' => 0,
            'message' => null,
        ];
    }

    /** Pares (codemp, codfor) presentes em payables mas ausentes em senior_suppliers. */
    public function missingSupplierPairs(): \Illuminate\Support\Collection
    {
        return Payable::query()
            ->select('codemp', 'codfor')
            ->whereNotNull('codemp')
            ->whereNotNull('codfor')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('senior_suppliers as s')
                    ->whereColumn('s.cod_emp', 'payables.codemp')
                    ->whereColumn('s.cod_for', 'payables.codfor');
            })
            ->distinct()
            ->get();
    }

    public function countMissingSuppliers(): int
    {
        return $this->missingSupplierPairs()->count();
    }

    private function skippedResult(): array
    {
        return [
            'status' => 'skipped', 'inserted' => 0, 'updated' => 0, 'errors' => 0,
            'enriched' => 0, 'enriched_desc' => 0, 'looked_up' => 0,
            'message' => 'Integração Senior desabilitada por configuração.',
        ];
    }

    private function upsert(array $fornecedor, int &$inserted, int &$updated): void
    {
        $attrs = $this->mapper->map($fornecedor);
        if ($attrs['cod_emp'] < 1 || $attrs['cod_for'] < 1) {
            return;
        }

        $existing = SeniorSupplier::where('cod_emp', $attrs['cod_emp'])
            ->where('cod_for', $attrs['cod_for'])
            ->first();

        if (!$existing) {
            SeniorSupplier::create($attrs);
            $inserted++;

            return;
        }

        if ($existing->senior_raw == $fornecedor) {
            return;
        }

        $existing->update($attrs);
        $updated++;
    }

    /** Atualiza supplier_name/cnpj dos títulos a partir do cache senior_suppliers. */
    public function enrichPayables(): int
    {
        $count = 0;
        Payable::query()
            ->whereNotNull('codemp')
            ->whereNotNull('codfor')
            ->chunkById(200, function ($payables) use (&$count) {
                foreach ($payables as $payable) {
                    $supplier = SeniorSupplier::where('cod_emp', (int) $payable->codemp)
                        ->where('cod_for', (int) $payable->codfor)
                        ->first();
                    if (!$supplier) {
                        continue;
                    }
                    $dirty = false;
                    if ($payable->supplier_name !== $supplier->name) {
                        $payable->supplier_name = $supplier->name;
                        $dirty = true;
                    }
                    if ($supplier->cnpj && $payable->supplier_cnpj !== $supplier->cnpj) {
                        $payable->supplier_cnpj = $supplier->cnpj;
                        $dirty = true;
                    }
                    if ($dirty) {
                        $payable->save();
                        $count++;
                    }
                }
            });

        return $count;
    }

    /** Preenche nomes genéricos a partir de obsTcp (GFD, TRCT, VT, etc.). */
    public function enrichFromDescriptions(): int
    {
        $count = 0;
        Payable::query()
            ->where('supplier_name', 'like', 'Fornecedor %')
            ->whereNotNull('description')
            ->chunkById(200, function ($payables) use (&$count) {
                foreach ($payables as $payable) {
                    $name = $this->displayNameResolver->fromDescription($payable->description);
                    if ($name === null || $name === $payable->supplier_name) {
                        continue;
                    }
                    $payable->supplier_name = $name;
                    $payable->save();
                    $count++;
                }
            });

        return $count;
    }
}
