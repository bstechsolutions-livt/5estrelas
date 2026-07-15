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
    /**
     * Pares (codEmp, codFil) cuja consulta bulk concluiu sem erro transitório.
     * null = modo sweep / sem rastreio (marca ausentes só com proteções de keys).
     *
     * @var list<array{0: int, 1: int}>|null
     */
    private ?array $collectOkPairs = null;

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

        // Libera jobs presos (503/timeout/kill) antes de checar concorrência.
        $this->failStaleRunningRuns();

        // req 6.4/6.5: impede execução concorrente.
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

        [$vctIni, $vctFim] = $this->window($mode, $windowFrom, $windowTo);

        $run = PayableSyncRun::create([
            'environment' => $env, 'mode' => $mode, 'trigger' => $trigger,
            'status' => PayableSyncRun::STATUS_RUNNING,
            'started_at' => now(),
            'window_start' => $vctIni, 'window_end' => $vctFim,
        ]);

        try {
            $titulos = $this->collectTitulos($vctIni, $vctFim);

            $inserted = 0;
            $updated = 0;
            $businessKeys = [];
            $logicalKeys = [];
            // Insert + update + re-sync sem mudança de raw: ainda preenche depto/fornecedor.
            $enrichIds = [];

            foreach (array_chunk($titulos, (int) config('senior.batch_size', 500)) as $chunk) {
                try {
                    DB::transaction(function () use ($chunk, &$inserted, &$updated, &$businessKeys, &$logicalKeys, &$enrichIds) {
                        foreach ($chunk as $titulo) {
                            $bk = $this->mapper->businessKey($titulo);
                            if ($bk === null) {
                                Log::warning('[senior-cp] título sem Business_Key derivável, descartado', ['titulo' => $titulo]);
                                continue;
                            }
                            $businessKeys[] = $bk;
                            $logical = $this->logicalKeyFromTitulo($titulo);
                            if ($logical !== null) {
                                $logicalKeys[] = $logical;
                            }
                            $this->upsertTitulo($bk, $titulo, $inserted, $updated, $enrichIds);
                        }
                    });
                } catch (\Throwable $e) {
                    // req 4.8: lote revertido (transaction), estado anterior preservado, lote registrado.
                    Log::error('[senior-cp] falha ao gravar lote do sync', ['erro' => $e->getMessage(), 'tamanho' => count($chunk)]);
                }
            }

            // req 7: ausentes — só com keys retornadas; protege irmãs multi-filial.
            $missing = $this->marcarAusentes($businessKeys, $logicalKeys, $vctIni, $vctFim);

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

            // A cada ciclo (insert e update): UsuGer → depto; senão Financeiro; nome do fornecedor.
            $this->enrichLaunchersAfterSync($enrichIds);
            $this->resolveDepartmentsAfterSync($enrichIds);
            $this->syncMissingSuppliersAfterPayables($enrichIds);
        } catch (\Throwable $e) {
            // req 2.3 / 9.4: falha de comunicação (503/timeout) ou erro inesperado —
            // NÃO altera payables / NÃO marca ausentes; fecha o run para não travar o cron.
            $msg = mb_substr($e->getMessage(), 0, 2000);
            Log::error('[senior-cp] sync falhou', [
                'sync_run_id' => $run->id,
                'erro' => $msg,
                'tipo' => $e instanceof SeniorException ? $e->kind : $e::class,
            ]);
            $run->update([
                'status' => PayableSyncRun::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => $msg,
            ]);
        }

        return $run->fresh();
    }

    /**
     * Marca como falha runs em_andamento órfãos (sem finished_at há tempo demais).
     * Evita bloqueio permanente do cron após 503/timeout/processo morto.
     */
    public function failStaleRunningRuns(): int
    {
        $minutes = max(5, (int) config('senior.sync_stale_minutes', 45));
        $cutoff = now()->subMinutes($minutes);

        $stale = PayableSyncRun::query()
            ->where('status', PayableSyncRun::STATUS_RUNNING)
            ->whereNull('finished_at')
            ->where('started_at', '<=', $cutoff)
            ->get(['id']);

        if ($stale->isEmpty()) {
            return 0;
        }

        $n = PayableSyncRun::query()
            ->whereIn('id', $stale->pluck('id'))
            ->update([
                'status' => PayableSyncRun::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => "Execução interrompida: em_andamento sem finished_at há mais de {$minutes} min (stale lock).",
            ]);

        if ($n > 0) {
            Log::warning('[senior-cp] runs em_andamento liberados (stale)', [
                'count' => $n,
                'ids' => $stale->pluck('id')->all(),
                'stale_minutes' => $minutes,
            ]);
            AuditLogger::log(
                event: 'contas_pagar.sync.stale_liberado',
                module: 'financeiro.contas_pagar',
                description: "Sync: {$n} execução(ões) em_andamento órfã(s) marcada(s) como falha.",
                metadata: ['ids' => $stale->pluck('id')->all(), 'stale_minutes' => $minutes],
            );
        }

        return $n;
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
     * Materializa department_id: usuário do UsuGer → depto; se não houver → Financeiro.
     * Não sobrescreve department_id já definido (exceto upgrade do fallback Financeiro via launcher).
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
        $assigned = 0;

        Payable::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($financeiroId, &$assigned) {
                foreach ($chunk as $payable) {
                    $nextId = $this->resolveDepartmentIdForPayable($payable, $financeiroId);
                    if ($nextId === null || (int) $payable->department_id === $nextId) {
                        continue;
                    }
                    $payable->update(['department_id' => $nextId]);
                    $assigned++;
                }
            });

        if ($assigned > 0) {
            Log::info('[senior-cp] departamentos materializados pós-sync', ['assigned' => $assigned]);
        }

        return $assigned;
    }

    /**
     * Backfill: fornecedores faltantes + depto (UsuGer já gravado ou Financeiro) em títulos abertos.
     *
     * @return array{suppliers_looked_up:int, suppliers_enriched:int, departments_assigned:int}
     */
    public function backfillOpenSupplierAndDepartment(?int $supplierMaxLookups = null): array
    {
        $supplierMax = $supplierMaxLookups ?? max(200, (int) config('senior.post_sync_supplier_lookups', 200));
        $sup = ['looked_up' => 0, 'enriched' => 0, 'enriched_desc' => 0];
        try {
            $sup = FornecedoresSyncService::make()->syncMissingFromPayables(
                'backfill-open',
                maxLookups: $supplierMax,
            );
        } catch (\Throwable $e) {
            Log::warning('[senior-cp] backfill fornecedores falhou', ['erro' => $e->getMessage()]);
        }

        $openIds = Payable::query()
            ->whereNull('senior_missing_at')
            ->whereNotIn('status', ['pago', 'aguardando_conciliacao', 'conciliado', 'encerrado'])
            ->where(function ($q) {
                $q->whereNull('department_id')
                    ->orWhereNull('supplier_name')
                    ->orWhere('supplier_name', 'like', 'Fornecedor %');
            })
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        $depts = $this->resolveDepartmentsAfterSync($openIds);

        return [
            'suppliers_looked_up' => (int) ($sup['looked_up'] ?? 0),
            'suppliers_enriched' => (int) (($sup['enriched'] ?? 0) + ($sup['enriched_desc'] ?? 0)),
            'departments_assigned' => $depts,
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
        }

        if ($payable->department_id) {
            return (int) $payable->department_id;
        }

        return $financeiroId;
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
    private function collectTitulos(?Carbon $vctIni, ?Carbon $vctFim): array
    {
        $this->collectOkPairs = null;

        if (config('senior.cp_strategy', 'bulk') === 'bulk') {
            return $this->collectTitulosBulk($vctIni, $vctFim);
        }

        return $this->collectTitulosSweep($vctIni, $vctFim);
    }

    /**
     * Bulk (CliOpcAbr): 1 chamada SOAP por (codEmp, codFil) — sem codFor.
     */
    private function collectTitulosBulk(?Carbon $vctIni, ?Carbon $vctFim): array
    {
        $all = [];
        $okPairs = [];

        foreach ($this->activeCodEmpFilPairs() as [$codEmp, $codFil]) {
            Log::info('[senior-cp] bulk', ['codEmp' => $codEmp, 'codFil' => $codFil]);
            try {
                $titulos = $this->client->consultarTitulosAbertosPorEmpresaFilial((int) $codEmp, (int) $codFil, $vctIni, $vctFim);
            } catch (SeniorException $e) {
                if ($e->isTransient()) {
                    throw $e;
                }
                Log::warning('[senior-cp] bulk ignorado (erro de negócio)', [
                    'codEmp' => $codEmp,
                    'codFil' => $codFil,
                    'erro' => $e->getMessage(),
                ]);
                continue;
            }
            $okPairs[] = [(int) $codEmp, (int) $codFil];
            foreach ($titulos as $t) {
                $all[] = $t;
            }
            Log::info('[senior-cp] bulk concluído', [
                'codEmp' => $codEmp,
                'codFil' => $codFil,
                'titulos' => count($titulos),
                'total' => count($all),
            ]);
        }

        $this->collectOkPairs = $okPairs;

        return $this->dedupTitulos($all);
    }

    /**
     * Varre as empresas (cod_emps) × fornecedores (cod_for_start..cod_for_end).
     * Fallback quando CliOpcAbr não está ativo.
     */
    private function collectTitulosSweep(?Carbon $vctIni, ?Carbon $vctFim): array
    {
        $codEmps = $this->activeCodEmps();
        $forStart = (int) config('senior.cod_for_start', 1);
        $forEnd = (int) config('senior.cod_for_end', 9999);
        $delayMs = (int) config('senior.sweep_delay_ms', 0);
        $maxTransport = max(1, (int) config('senior.sweep_max_transport_failures', 3));

        $all = [];
        $consecutiveTransport = 0;

        foreach ($codEmps as $codEmp) {
            Log::info('[senior-cp] varredura', [
                'codEmp' => $codEmp,
                'codFor' => $forStart,
                'total' => count($all),
                'evento' => 'empresa',
            ]);

            for ($codFor = $forStart; $codFor <= $forEnd; $codFor++) {
                try {
                    $titulos = $this->client->consultarTitulosPorFornecedor((int) $codEmp, $codFor, $vctIni, $vctFim);
                    $consecutiveTransport = 0;
                    foreach ($titulos as $t) {
                        $all[] = $t;
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
        }

        return $this->dedupTitulos($all);
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
            $enrichIds[] = (int) $existing->id;
        } elseif ($voltouDaAusencia) {
            // Conteúdo igual mas estava marcado como ausente: só limpa o flag (req 7.4).
            $existing->update(['senior_missing_at' => null]);
            if ($this->needsPostSyncEnrich($existing)) {
                $enrichIds[] = (int) $existing->id;
            }
        }
    }

    /** Título ainda sem depto materializado ou com fornecedor genérico. */
    private function needsPostSyncEnrich(Payable $payable): bool
    {
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
     * Proteções:
     * - Sem business keys → não marca ninguém (evita sumiço em massa se collect vazio/falhou).
     * - Bulk: só considera pares (codEmp,codFil) que responderam com sucesso.
     * - Irmãs multi-filial (mesmo emp+numTit+codTpt+codFor, outra fil) não são marcadas
     *   se qualquer filial retornou o título lógico.
     *
     * @param  list<string>  $businessKeys
     * @param  list<string>  $logicalKeys  chaves "emp|numTit|codTpt|codFor"
     */
    private function marcarAusentes(
        array $businessKeys,
        array $logicalKeys = [],
        ?Carbon $vctIni = null,
        ?Carbon $vctFim = null,
    ): int {
        $keys = array_values(array_unique(array_filter($businessKeys, fn ($k) => is_string($k) && $k !== '')));
        if ($keys === []) {
            Log::warning('[senior-cp] marcarAusentes ignorado: nenhuma business key retornada');

            return 0;
        }

        $query = Payable::whereNotNull('senior_id')->whereNull('senior_missing_at')
            ->whereNotIn('senior_id', $keys);

        $emps = $this->activeCodEmps();
        if ($emps !== []) {
            $query->whereIn('codemp', $emps);
        }

        // Bulk: só marca ausentes em filiais cuja consulta SOAP concluiu.
        if ($this->collectOkPairs !== null) {
            if ($this->collectOkPairs === []) {
                Log::warning('[senior-cp] marcarAusentes ignorado: nenhum par emp/fil concluído');

                return 0;
            }
            $query->where(function ($q) {
                foreach ($this->collectOkPairs as [$codEmp, $codFil]) {
                    $q->orWhere(function ($qq) use ($codEmp, $codFil) {
                        $qq->where('codemp', $codEmp)->where('codfil', $codFil);
                    });
                }
            });
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

        // Irmãs multi-filial: se o título lógico voltou em qualquer fil, não ocultar a cópia.
        $logicalSet = array_fill_keys(array_values(array_unique(array_filter($logicalKeys))), true);
        if ($logicalSet !== []) {
            $candidateIds = (clone $query)->pluck('id');
            if ($candidateIds->isEmpty()) {
                return 0;
            }

            $protectedIds = Payable::query()
                ->whereIn('id', $candidateIds)
                ->get(['id', 'codemp', 'title_number', 'codtpt', 'codfor'])
                ->filter(function (Payable $p) use ($logicalSet) {
                    $logical = $this->logicalKeyFromPayable($p);

                    return $logical !== null && isset($logicalSet[$logical]);
                })
                ->pluck('id')
                ->all();

            if ($protectedIds !== []) {
                $query->whereNotIn('id', $protectedIds);
                Log::info('[senior-cp] marcarAusentes: irmãs multi-filial protegidas', [
                    'protected' => count($protectedIds),
                ]);
            }
        }

        return $query->update(['senior_missing_at' => now()]);
    }

    /** Chave lógica sem filial: emp|numTit|codTpt|codFor. */
    private function logicalKeyFromTitulo(array $titulo): ?string
    {
        $emp = $this->mapperAsString($titulo['codEmp'] ?? null);
        $num = $this->mapperAsString($titulo['numTit'] ?? null);
        $tpt = $this->mapperAsString($titulo['codTpt'] ?? null);
        $for = $this->mapperAsString($titulo['codFor'] ?? null);
        if ($emp === null || $emp === '' || $num === null || $num === ''
            || $tpt === null || $tpt === '' || $for === null || $for === '') {
            return null;
        }

        return trim($emp).'|'.trim($num).'|'.trim($tpt).'|'.trim($for);
    }

    private function logicalKeyFromPayable(Payable $payable): ?string
    {
        $emp = $payable->codemp;
        $num = $payable->title_number;
        $tpt = $payable->codtpt;
        $for = $payable->codfor;
        if ($emp === null || $num === null || $num === '' || $tpt === null || $tpt === '' || $for === null) {
            return null;
        }

        return (string) $emp.'|'.trim((string) $num).'|'.trim((string) $tpt).'|'.(string) $for;
    }

    private function mapperAsString(mixed $v): ?string
    {
        if ($v === null || (is_array($v) && $v === [])) {
            return null;
        }
        if (is_array($v)) {
            $v = reset($v);
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function clamp(int $v, int $min, int $max): int
    {
        return max($min, min($max, $v));
    }
}
