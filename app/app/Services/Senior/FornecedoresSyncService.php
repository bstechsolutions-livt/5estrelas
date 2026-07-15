<?php

namespace App\Services\Senior;

use App\Models\Payable;
use App\Models\SeniorSupplier;
use App\Support\PayableEmpresaExclusion;
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

        return $this->syncMissingFromPayables(
            $trigger,
            maxLookups: (int) config('senior.fornecedor_max_lookups_per_sync', 40),
        );
    }

    /**
     * Delta: busca na Senior só os codFor que aparecem em títulos e ainda não estão no cache resolvido.
     *
     * @param  list<int>|null  $prioritizePayableIds  Pares destes títulos entram primeiro (pós-insert).
     */
    public function syncMissingFromPayables(
        string $trigger = 'manual',
        ?int $maxLookups = null,
        ?array $prioritizePayableIds = null,
    ): array {
        if (!config('senior.enabled', false)) {
            return $this->skippedResult();
        }

        $missingPairs = $this->missingSupplierPairs($prioritizePayableIds);
        $cooldownKeys = $this->unresolvedCooldownKeys(
            $missingPairs->map(fn ($p) => [(int) $p->codemp, (int) $p->codfor])->all()
        );
        $missingByEmp = $missingPairs
            ->groupBy(fn ($pair) => (int) $pair->codemp)
            ->map(fn ($rows) => $rows->pluck('codfor')->map(fn ($v) => (int) $v)->unique()->values()->all());

        $inserted = 0;
        $updated = 0;
        $errors = 0;
        $lookedUp = 0;
        $codFil = (int) config('senior.cod_fil', 1);

        foreach ($missingByEmp as $codEmp => $missingList) {
            foreach ($missingList as $codFor) {
                if ($maxLookups !== null && $lookedUp >= $maxLookups) {
                    break 2;
                }
                if ($codFor < 1) {
                    continue;
                }
                if (isset($cooldownKeys[$codEmp . '-' . $codFor])) {
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
                    // Cooldown: evita monopolizar o teto de lookups; ainda conta como faltante após TTL.
                    $this->rememberUnresolved((int) $codEmp, (int) $codFor);
                    continue;
                }

                if ($fornecedor === null) {
                    $this->rememberUnresolved((int) $codEmp, (int) $codFor);
                    continue;
                }

                $fornecedor['codEmp'] ??= (int) $codEmp;
                DB::transaction(function () use ($fornecedor, &$inserted, &$updated) {
                    $this->upsert($fornecedor, $inserted, $updated);
                });
            }
        }

        $enriched = $this->enrichPayables($prioritizePayableIds);
        $enrichedDesc = $this->enrichFromDescriptions($prioritizePayableIds);

        if ($lookedUp > 0 || $enriched > 0 || $enrichedDesc > 0) {
            Log::info('[senior-fornecedor] sync delta concluído', [
                'trigger' => $trigger,
                'looked_up' => $lookedUp,
                'inserted' => $inserted,
                'updated' => $updated,
                'enriched' => $enriched,
                'enriched_desc' => $enrichedDesc,
                'errors' => $errors,
                'max_lookups' => $maxLookups,
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
     * Pares (codemp, codfor) presentes em payables sem cache resolvido.
     * Stubs unresolved (ou com retry_after expirado) continuam como faltantes.
     * Prioriza pares dos títulos recém-inseridos e, em seguida, os mais novos.
     *
     * @param  list<int>|null  $prioritizePayableIds
     */
    public function missingSupplierPairs(?array $prioritizePayableIds = null): \Illuminate\Support\Collection
    {
        $priorityPairs = collect();
        $ids = array_values(array_unique(array_filter(array_map('intval', $prioritizePayableIds ?? []), fn (int $id) => $id > 0)));
        if ($ids !== []) {
            $priorityPairs = Payable::query()
                ->select('codemp', 'codfor', DB::raw('MAX(id) as latest_id'))
                ->whereIn('id', $ids)
                ->whereNotNull('codemp')
                ->whereNotNull('codfor')
                ->where('codfor', '>', 0)
                ->whereNotExists($this->resolvedOrCooldownSupplierExists())
                ->groupBy('codemp', 'codfor')
                ->orderByDesc('latest_id')
                ->get();
        }

        $rest = Payable::query()
            ->select('codemp', 'codfor', DB::raw('MAX(id) as latest_id'))
            ->whereNotNull('codemp')
            ->whereNotNull('codfor')
            ->where('codfor', '>', 0)
            ->whereNotExists($this->resolvedOrCooldownSupplierExists())
            ->groupBy('codemp', 'codfor')
            ->orderByDesc('latest_id')
            ->get();

        if ($priorityPairs->isEmpty()) {
            return $rest;
        }

        $seen = [];
        $ordered = collect();
        foreach ($priorityPairs->concat($rest) as $pair) {
            $key = (int) $pair->codemp . '-' . (int) $pair->codfor;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $ordered->push($pair);
        }

        return $ordered;
    }

    /**
     * Existe cache resolvido (não-stub). Stubs unresolved NÃO satisfazem — o par continua faltante.
     * O cooldown de retry é aplicado em PHP (ver unresolvedCooldownKeys), portável SQLite/Pg.
     */
    private function resolvedOrCooldownSupplierExists(): \Closure
    {
        return function ($q) {
            $q->select(DB::raw(1))
                ->from('senior_suppliers as s')
                ->whereColumn('s.cod_emp', 'payables.codemp')
                ->whereColumn('s.cod_for', 'payables.codfor')
                ->where(function ($resolved) {
                    $driver = DB::connection()->getDriverName();
                    if ($driver === 'pgsql') {
                        $resolved->whereNull('s.senior_raw')
                            ->orWhereRaw("coalesce((s.senior_raw->>'unresolved')::boolean, false) = false");

                        return;
                    }

                    // SQLite / demais: json_extract; ausência de chave = resolvido.
                    $resolved->whereNull('s.senior_raw')
                        ->orWhereRaw("json_extract(s.senior_raw, '$.unresolved') is null")
                        ->orWhereRaw("json_extract(s.senior_raw, '$.unresolved') = 0");
                });
        };
    }

    /**
     * @param  list<array{0:int,1:int}>  $pairs
     * @return array<string, true>  chaves "codEmp-codFor" ainda em cooldown
     */
    private function unresolvedCooldownKeys(array $pairs): array
    {
        if ($pairs === []) {
            return [];
        }

        $query = SeniorSupplier::query()->where(function ($q) use ($pairs) {
            foreach ($pairs as [$codEmp, $codFor]) {
                $q->orWhere(fn ($qq) => $qq->where('cod_emp', $codEmp)->where('cod_for', $codFor));
            }
        });

        $keys = [];
        foreach ($query->get(['cod_emp', 'cod_for', 'senior_raw']) as $row) {
            if (! SeniorSupplier::isUnresolvedRaw($row->senior_raw)) {
                continue;
            }
            $retryAfter = $row->senior_raw['retry_after'] ?? null;
            if (! is_string($retryAfter) || $retryAfter === '') {
                // Stub legado sem TTL: elegível para retry imediato (não bloqueia).
                continue;
            }
            try {
                if (now()->lt(\Illuminate\Support\Carbon::parse($retryAfter))) {
                    $keys[$row->cod_emp . '-' . $row->cod_for] = true;
                }
            } catch (\Throwable) {
                $keys[$row->cod_emp . '-' . $row->cod_for] = true;
            }
        }

        return $keys;
    }

    /**
     * Full: pagina o catálogo inteiro via ConsultarGeral (bootstrap / manutenção noturna).
     *
     * @return array{status:string, inserted:int, updated:int, errors:int, enriched:int, enriched_desc:int, message:?string, looked_up:int}
     */
    public function syncFullCatalog(string $trigger = 'manual'): array
    {
        $codEmps = config('senior.cod_emps') ?: [(int) config('senior.cod_emp', 2)];
        $codEmps = PayableEmpresaExclusion::filterCodEmps($codEmps);
        $inserted = 0;
        $updated = 0;
        $errors = 0;
        $pageSize = max(10, (int) config('senior.fornecedor_page_size', 100));
        $maxPages = max(1, (int) config('senior.fornecedor_max_pages', 500));

        foreach ($codEmps as $codEmp) {
            $pages = 0;
            while ($pages < $maxPages) {
                $indicePagina = SeniorFornecedorClient::offsetForPage($pages + 1, $pageSize);
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
                    $pages++;
                    if (count($fornecedores) < $pageSize) {
                        break;
                    }
                } catch (SeniorException $e) {
                    $errors++;
                    Log::warning('[senior-fornecedor] erro na empresa (full)', [
                        'codEmp' => $codEmp,
                        'indicePagina' => $indicePagina,
                        'erro' => $e->getMessage(),
                    ]);
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

    public function countMissingSuppliers(): int
    {
        return $this->missingSupplierPairs()->count();
    }

    /**
     * Remove stubs unresolved do cache (liberam retry imediato no próximo delta).
     */
    public function purgeUnresolvedStubs(): int
    {
        return $this->unresolvedStubsQuery()->delete();
    }

    public function countUnresolvedStubs(): int
    {
        return $this->unresolvedStubsQuery()->count();
    }

    private function unresolvedStubsQuery()
    {
        $q = SeniorSupplier::query();
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            return $q->whereRaw("coalesce((senior_raw->>'unresolved')::boolean, false) = true");
        }

        return $q->whereRaw("json_extract(senior_raw, '$.unresolved') = 1");
    }

    /**
     * Marca codFor sem retorno no Exportar com cooldown (TTL).
     * Não bloqueia forever: após retry_after o par volta a faltar em missingSupplierPairs.
     */
    private function rememberUnresolved(int $codEmp, int $codFor): void
    {
        if ($codEmp < 1 || $codFor < 1) {
            return;
        }

        $ttlMinutes = max(1, (int) config('senior.fornecedor_unresolved_ttl_minutes', 360));
        $retryAfter = now()->addMinutes($ttlMinutes)->toIso8601String();
        $payload = [
            'unresolved' => true,
            'at' => now()->toIso8601String(),
            'retry_after' => $retryAfter,
        ];

        $existing = SeniorSupplier::query()
            ->where('cod_emp', $codEmp)
            ->where('cod_for', $codFor)
            ->first();

        if ($existing && ! SeniorSupplier::isUnresolvedRaw($existing->senior_raw)) {
            // Já tem cache real — não sobrescrever.
            return;
        }

        if ($existing) {
            $existing->update([
                'name' => 'Fornecedor ' . $codFor,
                'trade_name' => null,
                'cnpj' => null,
                'senior_raw' => $payload,
                'senior_synced_at' => now(),
            ]);

            return;
        }

        SeniorSupplier::create([
            'cod_emp' => $codEmp,
            'cod_for' => $codFor,
            'name' => 'Fornecedor ' . $codFor,
            'trade_name' => null,
            'cnpj' => null,
            'senior_raw' => $payload,
            'senior_synced_at' => now(),
        ]);
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

    /**
     * Atualiza supplier_name/cnpj dos títulos a partir do cache senior_suppliers.
     *
     * @param  list<int>|null  $prioritizePayableIds
     */
    public function enrichPayables(?array $prioritizePayableIds = null): int
    {
        $count = 0;
        $query = Payable::query()
            ->whereNotNull('codemp')
            ->whereNotNull('codfor');

        $ids = array_values(array_unique(array_filter(array_map('intval', $prioritizePayableIds ?? []), fn (int $id) => $id > 0)));
        if ($ids !== []) {
            // Pós-insert: primeiro os novos; depois placeholders restantes.
            $count += $this->enrichPayableQuery(
                (clone $query)->whereIn('id', $ids)
            );
            $count += $this->enrichPayableQuery(
                (clone $query)
                    ->where(function ($q) {
                        $q->whereNull('supplier_name')
                            ->orWhere('supplier_name', 'like', 'Fornecedor %');
                    })
                    ->whereNotIn('id', $ids)
            );

            return $count;
        }

        return $this->enrichPayableQuery($query);
    }

    private function enrichPayableQuery($query): int
    {
        $count = 0;
        $query->orderByDesc('id')->chunkById(200, function ($payables) use (&$count) {
            $pairs = $payables
                ->map(fn (Payable $p) => (int) $p->codemp . '-' . (int) $p->codfor)
                ->unique()
                ->values();

            if ($pairs->isEmpty()) {
                return;
            }

            $supplierByPair = SeniorSupplier::query()
                ->where(function ($q) use ($pairs) {
                    foreach ($pairs as $pair) {
                        [$codEmp, $codFor] = explode('-', $pair, 2);
                        $q->orWhere(fn ($qq) => $qq->where('cod_emp', (int) $codEmp)->where('cod_for', (int) $codFor));
                    }
                })
                ->get()
                ->keyBy(fn (SeniorSupplier $s) => $s->cod_emp . '-' . $s->cod_for);

            foreach ($payables as $payable) {
                $key = (int) $payable->codemp . '-' . (int) $payable->codfor;
                $supplier = $supplierByPair->get($key);
                if ($supplier && SeniorSupplier::isUnresolvedRaw($supplier->senior_raw)) {
                    $supplier = null;
                }
                $resolved = $this->displayNameResolver->resolveForPayable($payable, $supplier);

                $dirty = false;
                if ($payable->supplier_name !== $resolved) {
                    $payable->supplier_name = $resolved;
                    $dirty = true;
                }
                if ($supplier?->cnpj && $payable->supplier_cnpj !== $supplier->cnpj) {
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

    /**
     * Preenche nomes genéricos a partir de obsTcp (GFD, TRCT, VT, etc.).
     *
     * @param  list<int>|null  $prioritizePayableIds
     */
    public function enrichFromDescriptions(?array $prioritizePayableIds = null): int
    {
        $count = 0;
        $query = Payable::query()
            ->where('supplier_name', 'like', 'Fornecedor %')
            ->whereNotNull('description');

        $ids = array_values(array_unique(array_filter(array_map('intval', $prioritizePayableIds ?? []), fn (int $id) => $id > 0)));
        if ($ids !== []) {
            $query->where(function ($q) use ($ids) {
                $q->whereIn('id', $ids)
                    ->orWhere('supplier_name', 'like', 'Fornecedor %');
            });
        }

        $query->orderByDesc('id')->chunkById(200, function ($payables) use (&$count) {
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
