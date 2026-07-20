<?php

namespace App\Services\Senior;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableSyncRun;
use App\Models\User;
use App\Support\PayableEmpresaExclusion;
use App\Services\AuditLogger;
use App\Support\SeniorDueDatePolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payables_Sync (requirements 2, 4, 5, 6, 7, 8, 9, 12): consulta a Senior e faz
 * upsert idempotente dos títulos em `payables`, preservando os Workflow_Fields.
 *
 * Em modo desabilitado por config, registra execução "ignorado" sem tocar nos dados
 * (req 12.4/12.5). Respeita concorrência (req 6.4/6.5) e janela incremental/full (req 5).
 */
class PayablesSyncService
{
    public function __construct(
        private SeniorCpClient $client,
        private PayableMapper $mapper,
        private StatusMapper $statusMapper,
    ) {
    }

    public static function make(): self
    {
        return new self(SeniorCpClient::fromConfig(), new PayableMapper(), new StatusMapper());
    }

    /**
     * @param  string|null  $mode  PayableSyncRun::MODE_* (default incremental — req 5.3)
     * @param  string  $trigger  PayableSyncRun::TRIGGER_*
     * @param  Carbon|null  $windowFrom  Janela manual (vctIni)
     * @param  Carbon|null  $windowTo  Janela manual (vctFim)
     */
    public function run(
        ?string $mode = null,
        string $trigger = PayableSyncRun::TRIGGER_SCHEDULED,
        ?Carbon $windowFrom = null,
        ?Carbon $windowTo = null,
    ): PayableSyncRun {
        $mode ??= PayableSyncRun::MODE_INCREMENTAL;
        $env = strtoupper(config('senior.environment', 'HML'));

        // req 12.5: desabilitado por configuração → execução ignorada, sem tocar nos dados.
        if (!config('senior.enabled', false)) {
            return PayableSyncRun::create([
                'environment' => $env, 'mode' => $mode, 'trigger' => $trigger,
                'status' => PayableSyncRun::STATUS_SKIPPED,
                'started_at' => now(), 'finished_at' => now(),
                'error_message' => 'Integração Senior desabilitada por configuração (senior.enabled=false).',
            ]);
        }

        // Lock Redis: cobre race entre dois processos que passam no EXISTS ao mesmo tempo.
        // TTL alto (2h) — se o processo morrer, o lock expira sozinho.
        $lock = Cache::lock('senior:sync-payables', 7200);
        if (! $lock->get()) {
            // Kill/supervisor pode deixar lock “fantasma” sem em_andamento real.
            $this->failStaleRunningRuns();
            $reallyRunning = PayableSyncRun::query()
                ->where('status', PayableSyncRun::STATUS_RUNNING)
                ->whereNull('finished_at')
                ->exists();

            if ($reallyRunning) {
                AuditLogger::log(
                    event: 'contas_pagar.sync.sobreposicao',
                    module: 'financeiro.contas_pagar',
                    description: 'Execução do sync ignorada: lock Redis já em uso.',
                );

                return PayableSyncRun::create([
                    'environment' => $env, 'mode' => $mode, 'trigger' => $trigger,
                    'status' => PayableSyncRun::STATUS_SKIPPED,
                    'started_at' => now(), 'finished_at' => now(),
                    'error_message' => 'Execução ignorada: lock Redis indica outro sync em andamento.',
                ]);
            }

            try {
                Cache::lock('senior:sync-payables')->forceRelease();
            } catch (\Throwable) {
                // ignore
            }

            if (! $lock->get()) {
                AuditLogger::log(
                    event: 'contas_pagar.sync.sobreposicao',
                    module: 'financeiro.contas_pagar',
                    description: 'Execução do sync ignorada: lock Redis já em uso.',
                );

                return PayableSyncRun::create([
                    'environment' => $env, 'mode' => $mode, 'trigger' => $trigger,
                    'status' => PayableSyncRun::STATUS_SKIPPED,
                    'started_at' => now(), 'finished_at' => now(),
                    'error_message' => 'Execução ignorada: lock Redis indica outro sync em andamento.',
                ]);
            }

            Log::warning('[senior-cp] lock Redis fantasma liberado; sync seguindo');
        }

        $pendingEnrich = null;
        try {
            $this->failStaleRunningRuns();

            // req 6.4/6.5: impede execução concorrente (também no banco).
            if (PayableSyncRun::where('status', PayableSyncRun::STATUS_RUNNING)->whereNull('finished_at')->exists()) {
                AuditLogger::log(
                    event: 'contas_pagar.sync.sobreposicao',
                    module: 'financeiro.contas_pagar',
                    description: 'Execução do sync ignorada: já existe outra em andamento.',
                );

                return PayableSyncRun::create([
                    'environment' => $env, 'mode' => $mode, 'trigger' => $trigger,
                    'status' => PayableSyncRun::STATUS_SKIPPED,
                    'started_at' => now(), 'finished_at' => now(),
                    'error_message' => 'Execução ignorada: já havia um sync em andamento.',
                ]);
            }

            $pendingEnrich = $this->executeRun($mode, $trigger, $env, $windowFrom, $windowTo);
        } finally {
            optional($lock)->release();
        }

        // Enrich DEPOIS de soltar o lock: se a Senior travar no UsuGer/fornecedor,
        // o próximo ciclo de 10 min não fica bloqueado e o run já está "sucesso".
        if (is_array($pendingEnrich)) {
            $run = $pendingEnrich['run'];
            if ($run->status === PayableSyncRun::STATUS_SUCCESS && $pendingEnrich['enrich_ids'] !== []) {
                $this->safePostSyncEnrich($run, $pendingEnrich['enrich_ids']);
            }

            return $run->fresh();
        }

        return $pendingEnrich;
    }

    /**
     * Runs órfãos (processo morto / kill) bloqueariam o sync para sempre.
     * Após N minutos em em_andamento, marca falha automaticamente.
     */
    private function failStaleRunningRuns(): void
    {
        $minutes = max(30, (int) config('senior.sync_stale_running_minutes', 120));
        $cutoff = now()->subMinutes($minutes);

        $stale = PayableSyncRun::query()
            ->where('status', PayableSyncRun::STATUS_RUNNING)
            ->whereNull('finished_at')
            ->where('started_at', '<=', $cutoff)
            ->get();

        foreach ($stale as $run) {
            $run->update([
                'status' => PayableSyncRun::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => "Abortado automaticamente: sync em_andamento há mais de {$minutes} min (órfão/zumbi).",
            ]);
            Log::warning('[senior-cp] sync órfão marcado como falha', [
                'run_id' => $run->id,
                'started_at' => $run->started_at?->toIso8601String(),
                'stale_minutes' => $minutes,
            ]);
        }
    }

    /**
     * @return array{run: PayableSyncRun, enrich_ids: list<int>}
     */
    private function executeRun(
        string $mode,
        string $trigger,
        string $env,
        ?Carbon $windowFrom,
        ?Carbon $windowTo,
    ): array {
        [$vctIni, $vctFim] = $this->window($mode, $windowFrom, $windowTo);

        $run = PayableSyncRun::create([
            'environment' => $env, 'mode' => $mode, 'trigger' => $trigger,
            'status' => PayableSyncRun::STATUS_RUNNING,
            'started_at' => now(),
            'window_start' => $vctIni, 'window_end' => $vctFim,
            'progress' => $this->initialProgress(),
        ]);

        try {
            $titulos = $this->collectTitulos($vctIni, $vctFim, $run);
        } catch (\Throwable $e) {
            // req 2.3 / 9.4: falha de comunicação não altera dados; registra erro truncado.
            // Captura \Throwable (não só SeniorException) para que um erro inesperado
            // NÃO deixe a execução presa em RUNNING e bloqueie os próximos syncs (concorrência).
            $this->patchProgress($run, [
                'phase' => 'erro',
                'phase_label' => 'Falha na coleta',
            ]);
            $run->update([
                'status' => PayableSyncRun::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            return [
                'run' => $run->fresh(),
                'enrich_ids' => [],
            ];
        }

        $this->patchProgress($run, [
            'phase' => 'gravacao',
            'phase_label' => 'Gravando títulos no Hub',
            'titulos_coletados' => count($titulos),
            'percent' => 92,
        ]);

        $inserted = 0;
        $updated = 0;
        $businessKeys = [];
        // Insert + update + re-sync sem mudança de raw: ainda preenche depto/fornecedor.
        $enrichIds = [];
        /** @var array<int, array{inserted:int, updated:int}> $byEmpMutations */
        $byEmpMutations = [];

        foreach (array_chunk($titulos, (int) config('senior.batch_size', 500)) as $chunk) {
            try {
                DB::transaction(function () use ($chunk, &$inserted, &$updated, &$businessKeys, &$enrichIds, &$byEmpMutations) {
                    foreach ($chunk as $titulo) {
                        $bk = $this->mapper->businessKey($titulo);
                        if ($bk === null) {
                            Log::warning('[senior-cp] título sem Business_Key derivável, descartado', ['titulo' => $titulo]);
                            continue;
                        }
                        $businessKeys[] = $bk;
                        $beforeIns = $inserted;
                        $beforeUpd = $updated;
                        $this->upsertTitulo($bk, $titulo, $inserted, $updated, $enrichIds);
                        $codEmp = (int) ($titulo['codEmp'] ?? 0);
                        if ($codEmp > 0 && ($inserted > $beforeIns || $updated > $beforeUpd)) {
                            $byEmpMutations[$codEmp] ??= ['inserted' => 0, 'updated' => 0];
                            if ($inserted > $beforeIns) {
                                $byEmpMutations[$codEmp]['inserted']++;
                            }
                            if ($updated > $beforeUpd) {
                                $byEmpMutations[$codEmp]['updated']++;
                            }
                        }
                    }
                });
            } catch (\Throwable $e) {
                // req 4.8: lote revertido (transaction), estado anterior preservado, lote registrado.
                Log::error('[senior-cp] falha ao gravar lote do sync', ['erro' => $e->getMessage(), 'tamanho' => count($chunk)]);
            }
        }

        $this->mergeEmpresasStats($run, $byEmpMutations, []);

        // req 7: títulos ausentes — NUNCA com coleta vazia/falha (evita mass-mark tipo #1508).
        $okCodEmps = $this->okCodEmpsFromProgress($run);
        $this->patchProgress($run, [
            'phase' => 'ausentes',
            'phase_label' => 'Marcando ausentes',
            'percent' => 95,
        ]);
        [$missing, $byEmpMissing] = $this->marcarAusentes($businessKeys, $vctIni, $vctFim, $okCodEmps);
        $this->mergeEmpresasStats($run, [], $byEmpMissing);

        // Sucesso = SOAP + upsert + ausentes ok. Enrich é best-effort (fora do lock).
        $run->update([
            'status' => PayableSyncRun::STATUS_SUCCESS,
            'finished_at' => now(),
            'inserted_count' => $inserted,
            'updated_count' => $updated,
            'missing_count' => $missing,
        ]);

        if ($inserted + $updated > 0) {
            AuditLogger::log(
                event: 'contas_pagar.sync.concluido',
                module: 'financeiro.contas_pagar',
                description: "Sync Contas a Pagar: {$inserted} inseridos, {$updated} atualizados, {$missing} ausentes",
                metadata: ['sync_run_id' => $run->id, 'inserted' => $inserted, 'updated' => $updated, 'missing' => $missing],
            );
        }

        $this->patchProgress($run, [
            'phase' => 'concluido',
            'phase_label' => 'Concluído',
            'percent' => 100,
            'inserted_total' => $inserted,
            'updated_total' => $updated,
            'missing_total' => $missing,
        ]);

        return [
            'run' => $run->fresh(),
            'enrich_ids' => $enrichIds,
        ];
    }

    /**
     * Pós-sync best-effort. Não altera o progress para "98% enrich" — o run já está
     * sucesso/100%; o monitor não deve parecer travado. SOAP de UsuGer/fornecedor
     * é opcional (desligar com SENIOR_POST_SYNC_*_LOOKUPS=0; o cron separado cobre).
     *
     * @param  list<int>  $enrichIds
     */
    private function safePostSyncEnrich(PayableSyncRun $run, array $enrichIds): void
    {
        if (config('senior.enrich_use_queue', false)) {
            PayableEnrichQueueDispatcher::make()->dispatchPostSync($enrichIds);

            return;
        }

        $launcherMax = (int) config('senior.post_sync_launcher_lookups', 80);
        $supplierMax = (int) config('senior.post_sync_supplier_lookups', 0);
        $maxSec = max(15, (int) config('senior.post_sync_enrich_max_seconds', 90));
        $deadline = microtime(true) + $maxSec;
        $enrichIds = $this->mergeEnrichIdsWithAwaitingSync($enrichIds, $launcherMax);

        try {
            if ($launcherMax > 0 && microtime(true) < $deadline) {
                $this->enrichLaunchersAfterSync($enrichIds);
            }

            if ($supplierMax > 0 && microtime(true) < $deadline) {
                $this->syncMissingSuppliersAfterPayables($enrichIds);
            }

            // Depto + status: inclui fila aguardando sync (não só inserts/updates do run).
            $this->resolveDepartmentsAfterSync($enrichIds);

            if ($supplierMax > 0 && microtime(true) >= $deadline) {
                Log::warning('[senior-cp] enrich fornecedores pulado (teto de tempo)', [
                    'run_id' => $run->id,
                    'max_seconds' => $maxSec,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[senior-cp] enrich pós-sync falhou', ['erro' => $e->getMessage(), 'run_id' => $run->id]);
        }
    }

    /**
     * Pós-sync: busca UsuGer na Senior e grava senior_cod_usu (insert e update).
     *
     * @param  list<int>  $enrichIds
     */
    private function enrichLaunchersAfterSync(array $enrichIds): void
    {
        if ($enrichIds === []) {
            return;
        }

        $max = (int) config('senior.post_sync_launcher_lookups', 80);
        if ($max <= 0) {
            return;
        }

        try {
            $r = PayableLauncherSyncService::make()->enrichByPayableIds(
                $enrichIds,
                maxLookups: $max,
                trigger: 'pos-payables-sync',
            );
            if (($r['updated'] ?? 0) > 0 || ($r['looked_up'] ?? 0) > 0) {
                Log::info('[senior-cp] lançadores pós-sync', $r);
            }
        } catch (\Throwable $e) {
            Log::warning('[senior-cp] lançadores pós-sync falhou', ['erro' => $e->getMessage()]);
        }
    }

    /**
     * IDs de títulos Senior bloqueados aguardando dept/fornecedor.
     *
     * @return list<int>
     */
    public function awaitingSyncPayableIds(?int $limit = null): array
    {
        $query = Payable::query()
            ->where('status', Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO)
            ->whereNull('senior_missing_at')
            ->orderByDesc('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->pluck('id')->all();
    }

    /**
     * @param  list<int>  $enrichIds
     * @return list<int>
     */
    public function mergedEnrichPayableIds(array $enrichIds, int $awaitingLimit = 80): array
    {
        return $this->mergeEnrichIdsWithAwaitingSync($enrichIds, $awaitingLimit);
    }

    /**
     * @param  list<int>  $enrichIds
     * @return list<int>
     */
    private function mergeEnrichIdsWithAwaitingSync(array $enrichIds, int $awaitingLimit = 80): array
    {
        $awaiting = $this->awaitingSyncPayableIds(max(1, $awaitingLimit));

        return array_values(array_unique(array_merge(
            array_map('intval', $enrichIds),
            $awaiting,
        )));
    }

    /**
     * Materializa department_id e status de prontidão (depto + fornecedor).
     *
     * @param  list<int>  $payableIds
     */
    public function resolveDepartmentsAfterSync(array $payableIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $payableIds), fn (int $id) => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $financeiroId = Department::financeDepartmentId();
        $changed = 0;

        Payable::query()
            ->whereIn('id', $ids)
            ->whereIn('status', Payable::SYNC_READINESS_STATUSES)
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($financeiroId, &$changed) {
                foreach ($chunk as $payable) {
                    if ($this->applyPostSyncReadiness($payable, $financeiroId)) {
                        $changed++;
                    }
                }
            });

        if ($changed > 0) {
            Log::info('[senior-cp] prontidão pós-sync (depto/fornecedor)', ['changed' => $changed]);
        }

        return $changed;
    }

    /**
     * Resolve department_id e status após enrich de UsuGer/fornecedor.
     * Título Senior só libera (pendente) com depto e nome real do fornecedor.
     */
    public function applyPostSyncReadiness(Payable $payable, ?int $financeiroId = null): bool
    {
        $financeiroId ??= Department::financeDepartmentId();
        $resolver = new SupplierDisplayNameResolver();
        $nextDeptId = $this->resolveDepartmentIdForPayable($payable, $financeiroId);
        $hasDept = $nextDeptId !== null;
        $resolvedSupplier = $resolver->resolveForPayable($payable);
        $hasSupplier = ! $resolver->isGeneric($resolvedSupplier);

        if (! $payable->senior_id) {
            if ($hasDept && (int) $payable->department_id !== $nextDeptId) {
                $payable->update(['department_id' => $nextDeptId]);

                return true;
            }

            return false;
        }

        if (! $payable->isSyncReadinessEligible()) {
            return false;
        }

        $ready = $hasDept && $hasSupplier;
        $updates = [];

        if ($hasDept && (int) $payable->department_id !== $nextDeptId) {
            $updates['department_id'] = $nextDeptId;
        }
        if ($hasSupplier && $payable->supplier_name !== $resolvedSupplier) {
            $updates['supplier_name'] = $resolvedSupplier;
        } elseif ($payable->department_id !== null
            && $financeiroId
            && (int) $payable->department_id === (int) $financeiroId) {
            $updates['department_id'] = null;
        }

        if ($ready) {
            if ($payable->status === Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO) {
                $updates['status'] = 'pendente';
            }
        } elseif (in_array($payable->status, ['pendente', 'em_preparacao'], true)) {
            $updates['status'] = Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO;
        }

        if ($updates === []) {
            return false;
        }

        $payable->update($updates);

        return true;
    }

    /** @deprecated Use applyPostSyncReadiness */
    public function applyDepartmentResolution(Payable $payable, ?int $financeiroId = null): bool
    {
        return $this->applyPostSyncReadiness($payable, $financeiroId);
    }

    /**
     * Backfill: UsuGer + fornecedor + reclassifica títulos abertos (aguardando sync vs pendente).
     *
     * @return array{launchers_looked_up:int, launchers_updated:int, suppliers_looked_up:int, suppliers_enriched:int, readiness_changed:int, moved_to_aguardando:int}
     */
    public function reconcileOpenSyncReadiness(?int $supplierMaxLookups = null, ?int $launcherMaxLookups = null): array
    {
        $launcherMax = $launcherMaxLookups ?? max(200, (int) config('senior.enrich_launcher_max_lookups', 400));
        $launcherIds = Payable::query()
            ->whereNotNull('senior_id')
            ->whereNull('senior_missing_at')
            ->whereIn('status', ['pendente', Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO, 'em_preparacao'])
            ->where(function ($q) {
                $q->whereNull('senior_cod_usu')->orWhere('senior_cod_usu', '<=', 0);
            })
            ->orderByDesc('id')
            ->limit($launcherMax)
            ->pluck('id')
            ->all();

        $launcher = ['looked_up' => 0, 'updated' => 0];
        if ($launcherIds !== []) {
            try {
                $launcher = PayableLauncherSyncService::make()->enrichByPayableIds(
                    $launcherIds,
                    maxLookups: $launcherMax,
                    trigger: 'reconcile-open',
                );
            } catch (\Throwable $e) {
                Log::warning('[senior-cp] reconcile lançadores falhou', ['erro' => $e->getMessage()]);
            }
        }

        $supplierMax = $supplierMaxLookups ?? max(500, (int) config('senior.post_sync_supplier_lookups', 80));
        $sup = ['looked_up' => 0, 'enriched' => 0, 'enriched_desc' => 0];
        try {
            $sup = FornecedoresSyncService::make()->syncMissingFromPayables(
                'reconcile-open',
                maxLookups: $supplierMax,
            );
        } catch (\Throwable $e) {
            Log::warning('[senior-cp] reconcile fornecedores falhou', ['erro' => $e->getMessage()]);
        }

        $openIds = Payable::query()
            ->whereNotNull('senior_id')
            ->whereNull('senior_missing_at')
            ->whereIn('status', ['pendente', Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO, 'em_preparacao'])
            ->whereNull('bordero_id')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        $beforeAguardando = Payable::query()
            ->whereIn('id', $openIds)
            ->where('status', Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO)
            ->count();

        $readinessChanged = $this->resolveDepartmentsAfterSync($openIds);

        $afterAguardando = Payable::query()
            ->whereIn('id', $openIds)
            ->where('status', Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO)
            ->count();

        return [
            'launchers_looked_up' => (int) ($launcher['looked_up'] ?? 0),
            'launchers_updated' => (int) ($launcher['updated'] ?? 0),
            'suppliers_looked_up' => (int) ($sup['looked_up'] ?? 0),
            'suppliers_enriched' => (int) (($sup['enriched'] ?? 0) + ($sup['enriched_desc'] ?? 0)),
            'readiness_changed' => $readinessChanged,
            'moved_to_aguardando' => max(0, $afterAguardando - $beforeAguardando),
        ];
    }

    /**
     * @return array{suppliers_looked_up:int, suppliers_enriched:int, departments_assigned:int}
     */
    public function backfillOpenSupplierAndDepartment(?int $supplierMaxLookups = null): array
    {
        $result = $this->reconcileOpenSyncReadiness($supplierMaxLookups);

        return [
            'suppliers_looked_up' => $result['suppliers_looked_up'],
            'suppliers_enriched' => $result['suppliers_enriched'],
            'departments_assigned' => $result['readiness_changed'],
        ];
    }

    private function resolveDepartmentIdForPayable(Payable $payable, ?int $financeiroId): ?int
    {
        $codUsu = (int) ($payable->senior_cod_usu ?? 0);
        if ($codUsu > 0) {
            $user = User::query()
                ->where('senior_cod_usu', $codUsu)
                ->whereNotNull('department_id')
                ->first();
            if ($user?->department_id) {
                $dept = Department::query()->find($user->department_id);
                if ($dept && $dept->is_active) {
                    // Materializa depto do lançador; sobrescreve só se vazio ou era fallback Financeiro.
                    if ($payable->department_id === null || (int) $payable->department_id === (int) $financeiroId) {
                        return (int) $dept->id;
                    }
                }
            }

            $legacyDeptId = Department::departmentIdForLegacySeniorCodUsu($codUsu);
            if ($legacyDeptId !== null) {
                if ($payable->department_id === null || (int) $payable->department_id === (int) $financeiroId) {
                    return $legacyDeptId;
                }
            }
        }

        if ($payable->department_id) {
            if ($financeiroId && (int) $payable->department_id === (int) $financeiroId) {
                $codUsu = (int) ($payable->senior_cod_usu ?? 0);
                if ($codUsu <= 0) {
                    return null;
                }
            }

            return (int) $payable->department_id;
        }

        return null;
    }

    /**
     * Após importar títulos, resolve nomes dos fornecedores (cache + placeholders “Fornecedor N”).
     *
     * @param  list<int>  $enrichIds
     */
    private function syncMissingSuppliersAfterPayables(array $enrichIds = []): void
    {
        $max = (int) config('senior.post_sync_supplier_lookups', 200);
        if ($max <= 0) {
            return;
        }

        try {
            $r = FornecedoresSyncService::make()->syncMissingFromPayables(
                'pos-payables',
                maxLookups: $max,
                prioritizePayableIds: $enrichIds,
            );
            if (($r['looked_up'] ?? 0) > 0 || ($r['enriched'] ?? 0) > 0 || ($r['enriched_desc'] ?? 0) > 0) {
                Log::info('[senior-cp] fornecedores delta pós-sync', $r);
            }
        } catch (\Throwable $e) {
            Log::warning('[senior-cp] fornecedores delta pós-sync falhou', ['erro' => $e->getMessage()]);
        }
    }

    /**
     * Coleta títulos conforme a estratégia configurada (bulk ou sweep).
     */
    private function collectTitulos(?Carbon $vctIni, ?Carbon $vctFim, ?PayableSyncRun $run = null): array
    {
        if (config('senior.cp_strategy', 'bulk') === 'bulk') {
            return $this->collectTitulosBulk($vctIni, $vctFim, $run);
        }

        return $this->collectTitulosSweep($vctIni, $vctFim, $run);
    }

    /**
     * Bulk (CliOpcAbr): 1 chamada SOAP por empresa (todas as filiais) — sem codFor/codFil.
     * Validado em PRD 16/07/2026: reduz ~15 round-trips (emp×fil) para ~N empresas.
     */
    private function collectTitulosBulk(?Carbon $vctIni, ?Carbon $vctFim, ?PayableSyncRun $run = null): array
    {
        $all = [];
        $emps = $this->activeCodEmps();
        $total = count($emps);

        $empresas = [];
        foreach ($emps as $codEmp) {
            $empresas[] = [
                'cod_emp' => (int) $codEmp,
                'status' => 'pendente',
                'titulos' => 0,
                'inserted' => 0,
                'updated' => 0,
                'missing' => 0,
                'duration_seconds' => null,
                'error' => null,
            ];
        }

        $this->patchProgress($run, [
            'phase' => 'coleta',
            'phase_label' => 'Consultando Senior (por empresa)',
            'strategy' => 'bulk',
            'total_empresas' => $total,
            'done_empresas' => 0,
            'percent' => $total > 0 ? 0 : 100,
            'current_cod_emp' => null,
            'empresas' => $empresas,
            'titulos_coletados' => 0,
        ]);

        foreach ($emps as $index => $codEmp) {
            $empresas[$index]['status'] = 'em_andamento';
            $t0 = microtime(true);
            $this->patchProgress($run, [
                'phase' => 'coleta',
                'phase_label' => "Consultando empresa {$codEmp}",
                'current_cod_emp' => (int) $codEmp,
                'done_empresas' => $index,
                'percent' => $total > 0 ? (int) floor(($index / $total) * 90) : 0,
                'empresas' => $empresas,
            ]);

            Log::info('[senior-cp] bulk', ['codEmp' => $codEmp, 'escopo' => 'empresa']);
            $titulos = [];
            try {
                $titulos = $this->client->consultarTitulosAbertosPorEmpresa((int) $codEmp, $vctIni, $vctFim);
                $empresas[$index]['status'] = 'ok';
                $empresas[$index]['titulos'] = count($titulos);
            } catch (SeniorException $e) {
                // No bulk por empresa, "Não foi possível executar o serviço solicitado"
                // costuma ser queda/instabilidade do servlet — NÃO tratar como "sem títulos".
                $abortBulk = $e->isTransient()
                    || str_contains($e->getMessage(), 'Não foi possível executar o serviço solicitado');
                if ($abortBulk) {
                    $empresas[$index]['status'] = 'erro';
                    $empresas[$index]['error'] = $e->getMessage();
                    $empresas[$index]['duration_seconds'] = (int) round(microtime(true) - $t0);
                    $this->patchProgress($run, [
                        'empresas' => $empresas,
                        'current_cod_emp' => (int) $codEmp,
                    ]);
                    throw $e;
                }
                Log::warning('[senior-cp] bulk ignorado (erro de negócio)', [
                    'codEmp' => $codEmp,
                    'erro' => $e->getMessage(),
                ]);
                $empresas[$index]['status'] = 'ignorado';
                $empresas[$index]['error'] = $e->getMessage();
                $titulos = [];
            }

            $empresas[$index]['duration_seconds'] = (int) round(microtime(true) - $t0);

            foreach ($titulos as $t) {
                $all[] = $t;
            }

            $done = $index + 1;
            $this->patchProgress($run, [
                'done_empresas' => $done,
                'percent' => $total > 0 ? (int) floor(($done / $total) * 90) : 90,
                'empresas' => $empresas,
                'titulos_coletados' => count($all),
                'current_cod_emp' => null,
                'phase_label' => $done >= $total
                    ? 'Coleta concluída'
                    : "Consultando Senior ({$done}/{$total} empresas)",
            ]);

            Log::info('[senior-cp] bulk concluído', [
                'codEmp' => $codEmp,
                'titulos' => count($titulos),
                'total' => count($all),
                'duration_s' => $empresas[$index]['duration_seconds'],
            ]);
        }

        return $this->dedupTitulos($all);
    }

    /**
     * Varre as empresas (cod_emps) × fornecedores (cod_for_start..cod_for_end).
     * Fallback quando CliOpcAbr não está ativo.
     */
    private function collectTitulosSweep(?Carbon $vctIni, ?Carbon $vctFim, ?PayableSyncRun $run = null): array
    {
        $codEmps = $this->activeCodEmps();
        $forStart = (int) config('senior.cod_for_start', 1);
        $forEnd = (int) config('senior.cod_for_end', 9999);
        $delayMs = (int) config('senior.sweep_delay_ms', 0);
        $maxTransport = max(1, (int) config('senior.sweep_max_transport_failures', 3));

        $all = [];
        $consecutiveTransport = 0;
        $total = count($codEmps);
        $empresas = [];
        foreach ($codEmps as $codEmp) {
            $empresas[] = [
                'cod_emp' => (int) $codEmp,
                'status' => 'pendente',
                'titulos' => 0,
                'inserted' => 0,
                'updated' => 0,
                'missing' => 0,
                'duration_seconds' => null,
                'error' => null,
            ];
        }
        $this->patchProgress($run, [
            'phase' => 'coleta',
            'phase_label' => 'Varredura por fornecedor',
            'strategy' => 'sweep',
            'total_empresas' => $total,
            'done_empresas' => 0,
            'percent' => 0,
            'empresas' => $empresas,
        ]);

        foreach ($codEmps as $index => $codEmp) {
            $empresas[$index]['status'] = 'em_andamento';
            $this->patchProgress($run, [
                'current_cod_emp' => (int) $codEmp,
                'empresas' => $empresas,
                'phase_label' => "Varredura empresa {$codEmp}",
                'percent' => $total > 0 ? (int) floor(($index / $total) * 90) : 0,
            ]);

            Log::info('[senior-cp] varredura', [
                'codEmp' => $codEmp,
                'codFor' => $forStart,
                'total' => count($all),
                'evento' => 'empresa',
            ]);

            $empTitulos = 0;
            for ($codFor = $forStart; $codFor <= $forEnd; $codFor++) {
                try {
                    $titulos = $this->client->consultarTitulosPorFornecedor((int) $codEmp, $codFor, $vctIni, $vctFim);
                    $consecutiveTransport = 0;
                    foreach ($titulos as $t) {
                        $all[] = $t;
                        $empTitulos++;
                    }
                } catch (SeniorException $e) {
                    if ($e->isTransient()) {
                        // Falha de transporte: o callOnce já tentou novamente (backoff).
                        $consecutiveTransport++;
                        Log::warning('[senior-cp] falha de transporte na varredura', [
                            'codEmp' => $codEmp, 'codFor' => $codFor,
                            'consecutivas' => $consecutiveTransport, 'erro' => $e->getMessage(),
                        ]);
                        if ($consecutiveTransport >= $maxTransport) {
                            $empresas[$index]['status'] = 'erro';
                            $empresas[$index]['error'] = $e->getMessage();
                            $this->patchProgress($run, ['empresas' => $empresas]);
                            throw $e; // aborta o sync inteiro
                        }
                    } else {
                        // Erro de negócio para um codFor: ignora e segue a varredura.
                        $consecutiveTransport = 0;
                        Log::debug('[senior-cp] codFor com erro de negócio, ignorado na varredura', [
                            'codEmp' => $codEmp, 'codFor' => $codFor, 'erro' => $e->getMessage(),
                        ]);
                    }
                }

                if ($codFor % 100 === 0) {
                    Log::info('[senior-cp] varredura', [
                        'codEmp' => $codEmp,
                        'codFor' => $codFor,
                        'total' => count($all),
                    ]);
                }

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }

            $empresas[$index]['status'] = 'ok';
            $empresas[$index]['titulos'] = $empTitulos;
            $done = $index + 1;
            $this->patchProgress($run, [
                'empresas' => $empresas,
                'done_empresas' => $done,
                'percent' => $total > 0 ? (int) floor(($done / $total) * 90) : 90,
                'titulos_coletados' => count($all),
                'current_cod_emp' => null,
            ]);
        }

        return $this->dedupTitulos($all);
    }

    /** @return array<string, mixed> */
    private function initialProgress(): array
    {
        return [
            'phase' => 'iniciando',
            'phase_label' => 'Iniciando sync',
            'strategy' => (string) config('senior.cp_strategy', 'bulk'),
            'total_empresas' => 0,
            'done_empresas' => 0,
            'percent' => 0,
            'current_cod_emp' => null,
            'empresas' => [],
            'titulos_coletados' => 0,
        ];
    }

    /** @param  array<string, mixed>  $patch */
    private function patchProgress(?PayableSyncRun $run, array $patch): void
    {
        if ($run === null) {
            return;
        }

        $progress = array_merge($run->progress ?? $this->initialProgress(), $patch);
        $run->forceFill(['progress' => $progress])->save();
    }

    /** Remove títulos duplicados pela Business_Key. */
    private function dedupTitulos(array $titulos): array
    {
        $seen = [];
        $out = [];
        foreach ($titulos as $t) {
            $key = $this->mapper->businessKey($t) ?? json_encode($t);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $t;
            }
        }

        return $out;
    }

    /** Empresas ativas no sync (rollout gradual via SENIOR_EMP_ENABLED). */
    private function activeCodEmps(): array
    {
        $enabled = config('senior.emp_enabled', []);
        if (!empty($enabled)) {
            return PayableEmpresaExclusion::filterCodEmps($enabled);
        }

        $codEmps = config('senior.cod_emps');
        if (!empty($codEmps)) {
            return PayableEmpresaExclusion::filterCodEmps($codEmps);
        }

        return PayableEmpresaExclusion::filterCodEmps([(int) config('senior.cod_emp', 1)]);
    }

    /**
     * Pares (codEmp, codFil) para sync bulk — uma chamada SOAP por par.
     * Usa filiais cadastradas em branches; fallback cod_fil da config.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function activeCodEmpFilPairs(): array
    {
        $fallbackFil = (int) config('senior.cod_fil', 1);
        $pairs = [];

        foreach ($this->activeCodEmps() as $codEmp) {
            $fils = Branch::query()
                ->where('is_active', true)
                ->where('cod_emp', (int) $codEmp)
                ->whereNotNull('cod_fil')
                ->distinct()
                ->orderBy('cod_fil')
                ->pluck('cod_fil')
                ->map(fn ($f) => (int) $f)
                ->filter(fn ($f) => $f > 0)
                ->values()
                ->all();

            if ($fils === []) {
                $fils = [$fallbackFil];
            }

            foreach ($fils as $codFil) {
                $pairs[] = [(int) $codEmp, (int) $codFil];
            }
        }

        return $pairs;
    }

    /** Janela de vencimento [ini, fim] conforme o modo (req 5). */
    private function window(string $mode, ?Carbon $overrideFrom = null, ?Carbon $overrideTo = null): array
    {
        if ($overrideFrom && $overrideTo) {
            return [$overrideFrom->copy()->startOfDay(), $overrideTo->copy()->endOfDay()];
        }

        // Bulk: janela configurada, respeitando corte mínimo de vencimento.
        if (config('senior.cp_strategy', 'bulk') === 'bulk') {
            return [
                SeniorDueDatePolicy::windowFrom(Carbon::parse(config('senior.bulk_vct_ini', '2026-01-01'))),
                Carbon::parse(config('senior.bulk_vct_fim', '2030-12-31'))->endOfDay(),
            ];
        }

        if ($mode === PayableSyncRun::MODE_FULL) {
            return [null, null];
        }

        $forward = $this->clamp((int) config('senior.window_days_forward', 90), 1, 3650);
        $base = config('senior.vct_base_date');
        $ini = $base
            ? SeniorDueDatePolicy::windowFrom(Carbon::parse($base))
            : SeniorDueDatePolicy::windowFrom(now()->subDays($this->clamp((int) config('senior.window_days_back', 90), 1, 3650)));

        return [$ini, now()->addDays($forward)->endOfDay()];
    }

    /** Insere ou atualiza um título preservando Workflow_Fields (req 4, 8). */
    private function upsertTitulo(string $bk, array $titulo, int &$inserted, int &$updated, array &$enrichIds = []): void
    {
        $attrs = $this->mapper->mapHeader($titulo);
        if (PayableEmpresaExclusion::isExcluded(isset($attrs['codemp']) ? (int) $attrs['codemp'] : null)) {
            return;
        }
        $attrs['branch_id'] = Branch::idForSeniorPair(
            isset($attrs['codemp']) ? (int) $attrs['codemp'] : null,
            isset($attrs['codfil']) ? (int) $attrs['codfil'] : null,
        );
        $dueDate = isset($attrs['due_date']) ? Carbon::parse($attrs['due_date']) : null;
        if (!SeniorDueDatePolicy::isAllowed($dueDate)) {
            Log::debug('[senior-cp] título ignorado por vencimento anterior ao corte', [
                'business_key' => $bk,
                'due_date' => $dueDate?->toDateString(),
            ]);

            return;
        }

        $existing = Payable::where('senior_id', $bk)->first();
        $resolver = new SupplierDisplayNameResolver();

        // AbertosCP não traz UsuGer; não apagar senior_cod_usu já enriquecido via prj.contaspagar.
        if ($existing) {
            $prevCod = (int) ($existing->senior_cod_usu ?? 0);
            $newCod = (int) ($attrs['senior_cod_usu'] ?? 0);
            if ($prevCod > 0 && $newCod <= 0) {
                $attrs['senior_cod_usu'] = $prevCod;
            }

            // Não rebaixar nome real para placeholder “Fornecedor N” se o cache ainda não carregou.
            $prevName = (string) ($existing->supplier_name ?? '');
            $newName = (string) ($attrs['supplier_name'] ?? '');
            if ($prevName !== '' && ! $resolver->isGeneric($prevName) && $resolver->isGeneric($newName)) {
                $attrs['supplier_name'] = $prevName;
                if (! empty($existing->supplier_cnpj) && empty($attrs['supplier_cnpj'])) {
                    $attrs['supplier_cnpj'] = $existing->supplier_cnpj;
                }
            }
        }

        if (!$existing) {
            // req 4.2 / 8.2: novo título — status inicial derivado da Senior.
            $payable = new Payable();
            $payable->forceFill($attrs);
            $payable->senior_id = $bk;
            $payable->status = $this->statusMapper->map($titulo['sitTit'] ?? null);
            $payable->senior_synced_at = now();
            $payable->save();
            $this->syncRateios($payable, $titulo);
            $inserted++;
            $enrichIds[] = (int) $payable->id;

            return;
        }

        // req 4.5: idempotência — se o conteúdo bruto não mudou, não grava nada.
        $semMudanca = $existing->senior_raw == $titulo;
        $voltouDaAusencia = $existing->senior_missing_at !== null; // req 7.4

        if ($semMudanca && !$voltouDaAusencia) {
            $silent = [];
            if ($existing->branch_id === null && ! empty($attrs['branch_id'])) {
                $silent['branch_id'] = $attrs['branch_id'];
            }

            // Cache de fornecedor pode ter chegado depois do insert — preenche sem contar como update de raw.
            $prevName = (string) ($existing->supplier_name ?? '');
            $newName = (string) ($attrs['supplier_name'] ?? '');
            if (($prevName === '' || $resolver->isGeneric($prevName)) && $newName !== '' && ! $resolver->isGeneric($newName)) {
                $silent['supplier_name'] = $newName;
                if (! empty($attrs['supplier_cnpj'])) {
                    $silent['supplier_cnpj'] = $attrs['supplier_cnpj'];
                }
            }

            if ($silent !== []) {
                $existing->update($silent);
                $existing->refresh();
            }

            // Ainda precisa de UsuGer / depto / fornecedor? Enfileira para pós-sync.
            if ($this->needsPostSyncEnrich($existing)) {
                $enrichIds[] = (int) $existing->id;
            }

            return;
        }

        if (!$semMudanca) {
            // req 4.3/4.4: atualiza campos de origem Senior, preserva Workflow_Fields (status etc.).
            $existing->forceFill($attrs);
            $existing->senior_synced_at = now();
            $existing->senior_missing_at = null; // req 7.4
            $existing->save();
            $this->syncRateios($existing, $titulo);
            $updated++;
            if ($this->needsPostSyncEnrich($existing)) {
                $enrichIds[] = (int) $existing->id;
            }
        } elseif ($voltouDaAusencia) {
            // Conteúdo igual mas estava marcado como ausente: só limpa o flag (req 7.4).
            $existing->update(['senior_missing_at' => null]);
            if ($this->needsPostSyncEnrich($existing)) {
                $enrichIds[] = (int) $existing->id;
            }
        }
    }

    /** Título ainda sem depto materializado, aguardando vínculo ou com fornecedor genérico. */
    private function needsPostSyncEnrich(Payable $payable): bool
    {
        if (! $payable->isSyncReadinessEligible()) {
            return false;
        }

        if ($payable->status === Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO) {
            return true;
        }

        if ($payable->department_id === null) {
            return true;
        }

        $name = (string) ($payable->supplier_name ?? '');
        if ($name === '' || (new SupplierDisplayNameResolver())->isGeneric($name)) {
            return true;
        }

        $codUsu = (int) ($payable->senior_cod_usu ?? 0);
        if ($codUsu <= 0) {
            return true;
        }

        return false;
    }

    /** Substitui os rateios do título (req 3.3 / 3.9). */
    private function syncRateios(Payable $payable, array $titulo): void
    {
        $payable->rateios()->delete();
        foreach (($titulo['rateios'] ?? []) as $rateio) {
            if (is_array($rateio) && $rateio !== []) {
                $payable->rateios()->create($this->mapper->mapRateio($rateio));
            }
        }
    }

    /**
     * Marca como ausentes os títulos de origem Senior não retornados (req 7.1).
     * No incremental, restringe à janela de vencimento consultada (vctIni/vctFim).
     *
     * Segurança: se a coleta não trouxe chaves (SOAP falhou / vazio), NÃO marca ninguém —
     * businessKeys=[] + whereNotIn omitido apagaria a lista inteira (#1508).
     *
     * @param  list<string>  $businessKeys
     * @param  list<int>|null  $okCodEmps  Só marca ausentes nessas empresas (status ok na coleta).
     * @return array{0: int, 1: array<int, int>} [total, map codEmp => qtd]
     */
    private function marcarAusentes(
        array $businessKeys,
        ?Carbon $vctIni = null,
        ?Carbon $vctFim = null,
        ?array $okCodEmps = null,
    ): array {
        $businessKeys = array_values(array_unique(array_filter($businessKeys)));
        if ($businessKeys === []) {
            Log::warning('[senior-cp] marcarAusentes abortado: coleta sem títulos (evita mass-mark)');

            return [0, []];
        }

        if ($okCodEmps !== null) {
            $okCodEmps = array_values(array_unique(array_filter(array_map('intval', $okCodEmps))));
            if ($okCodEmps === []) {
                Log::warning('[senior-cp] marcarAusentes abortado: nenhuma empresa ok na coleta');

                return [0, []];
            }
        }

        $query = Payable::whereNotNull('senior_id')->whereNull('senior_missing_at')
            ->whereNotIn('senior_id', $businessKeys);

        if ($okCodEmps !== null) {
            $query->whereIn('codemp', $okCodEmps);
        } else {
            $emps = $this->activeCodEmps();
            if ($emps !== []) {
                $query->whereIn('codemp', $emps);
            }
        }

        // No modo bulk a janela respeita o corte mínimo — marca ausentes só na faixa válida.
        if (config('senior.cp_strategy', 'bulk') !== 'bulk') {
            if ($vctIni !== null) {
                $query->where('due_date', '>=', $vctIni);
            }
            if ($vctFim !== null) {
                $query->where('due_date', '<=', $vctFim);
            }
        } else {
            $query->where('due_date', '>=', SeniorDueDatePolicy::minDueDate());
            if ($vctFim !== null) {
                $query->where('due_date', '<=', $vctFim);
            }
        }

        $byEmp = [];
        foreach ((clone $query)->get(['codemp']) as $row) {
            $cod = (int) ($row->codemp ?? 0);
            if ($cod <= 0) {
                continue;
            }
            $byEmp[$cod] = ($byEmp[$cod] ?? 0) + 1;
        }

        $total = (int) $query->update(['senior_missing_at' => now()]);

        return [$total, $byEmp];
    }

    /** @return list<int> */
    private function okCodEmpsFromProgress(PayableSyncRun $run): array
    {
        $empresas = $run->progress['empresas'] ?? [];
        if (! is_array($empresas)) {
            return [];
        }

        $ok = [];
        foreach ($empresas as $emp) {
            if (($emp['status'] ?? null) === 'ok') {
                $cod = (int) ($emp['cod_emp'] ?? 0);
                if ($cod > 0) {
                    $ok[] = $cod;
                }
            }
        }

        return array_values(array_unique($ok));
    }

    /**
     * @param  array<int, array{inserted?:int, updated?:int}>  $mutations
     * @param  array<int, int>  $missingByEmp
     */
    private function mergeEmpresasStats(?PayableSyncRun $run, array $mutations, array $missingByEmp): void
    {
        if ($run === null) {
            return;
        }

        $empresas = $run->progress['empresas'] ?? [];
        if (! is_array($empresas) || $empresas === []) {
            return;
        }

        foreach ($empresas as $i => $emp) {
            $cod = (int) ($emp['cod_emp'] ?? 0);
            if ($cod <= 0) {
                continue;
            }
            if (isset($mutations[$cod])) {
                $empresas[$i]['inserted'] = (int) ($mutations[$cod]['inserted'] ?? 0);
                $empresas[$i]['updated'] = (int) ($mutations[$cod]['updated'] ?? 0);
            }
            if (isset($missingByEmp[$cod])) {
                $empresas[$i]['missing'] = (int) $missingByEmp[$cod];
            }
        }

        $this->patchProgress($run, ['empresas' => $empresas]);
    }

    private function clamp(int $v, int $min, int $max): int
    {
        return max($min, min($max, $v));
    }
}
