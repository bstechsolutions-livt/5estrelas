<?php

namespace App\Services\Senior;

use App\Models\Payable;
use App\Models\PayableSyncRun;
use App\Services\AuditLogger;
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
        } catch (\Throwable $e) {
            // req 2.3 / 9.4: falha de comunicação não altera dados; registra erro truncado.
            // Captura \Throwable (não só SeniorException) para que um erro inesperado
            // NÃO deixe a execução presa em RUNNING e bloqueie os próximos syncs (concorrência).
            $run->update([
                'status' => PayableSyncRun::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            return $run->fresh();
        }

        $inserted = 0;
        $updated = 0;
        $businessKeys = [];

        foreach (array_chunk($titulos, (int) config('senior.batch_size', 500)) as $chunk) {
            try {
                DB::transaction(function () use ($chunk, &$inserted, &$updated, &$businessKeys) {
                    foreach ($chunk as $titulo) {
                        $bk = $this->mapper->businessKey($titulo);
                        if ($bk === null) {
                            Log::warning('[senior-cp] título sem Business_Key derivável, descartado', ['titulo' => $titulo]);
                            continue;
                        }
                        $businessKeys[] = $bk;
                        $this->upsertTitulo($bk, $titulo, $inserted, $updated);
                    }
                });
            } catch (\Throwable $e) {
                // req 4.8: lote revertido (transaction), estado anterior preservado, lote registrado.
                Log::error('[senior-cp] falha ao gravar lote do sync', ['erro' => $e->getMessage(), 'tamanho' => count($chunk)]);
            }
        }

        // req 7: títulos ausentes na Senior — somente no Full_Sync.
        $missing = 0;
        if ($mode === PayableSyncRun::MODE_FULL) {
            $missing = $this->marcarAusentes($businessKeys);
        }

        $run->update([
            'status' => PayableSyncRun::STATUS_SUCCESS,
            'finished_at' => now(),
            'inserted_count' => $inserted,
            'updated_count' => $updated,
            'missing_count' => $missing,
        ]);

        // req 9.3: auditoria quando houve inserção/atualização.
        if ($inserted + $updated > 0) {
            AuditLogger::log(
                event: 'contas_pagar.sync.concluido',
                module: 'financeiro.contas_pagar',
                description: "Sync Contas a Pagar: {$inserted} inseridos, {$updated} atualizados, {$missing} ausentes",
                metadata: ['sync_run_id' => $run->id, 'inserted' => $inserted, 'updated' => $updated, 'missing' => $missing],
            );
        }

        return $run->fresh();
    }

    /**
     * Varre as empresas (cod_emps) × fornecedores (cod_for_start..cod_for_end) e
     * coleta os títulos abertos de cada (codEmp, codFor). codFor é OBRIGATÓRIO no
     * contrato real da Senior, por isso a consulta é por fornecedor.
     *
     * - Erro de NEGÓCIO num codFor específico (ex.: fornecedor inexistente) é
     *   ignorado e a varredura continua.
     * - Falha de TRANSPORTE (timeout/indisponível) repetida aborta a execução
     *   inteira (relança SeniorException → run() marca FAILED, sem tocar nos dados).
     */
    private function collectTitulos(?Carbon $vctIni, ?Carbon $vctFim): array
    {
        $codEmps = config('senior.cod_emps');
        if (empty($codEmps)) {
            $codEmps = [(int) config('senior.cod_emp', 1)];
        }
        $forStart = (int) config('senior.cod_for_start', 1);
        $forEnd = (int) config('senior.cod_for_end', 9999);
        $delayMs = (int) config('senior.sweep_delay_ms', 0);
        $maxTransport = max(1, (int) config('senior.sweep_max_transport_failures', 3));

        $all = [];
        $consecutiveTransport = 0;

        foreach ($codEmps as $codEmp) {
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

    /** Janela de vencimento [ini, fim] conforme o modo (req 5). */
    private function window(string $mode, ?Carbon $overrideFrom = null, ?Carbon $overrideTo = null): array
    {
        if ($overrideFrom && $overrideTo) {
            return [$overrideFrom->copy()->startOfDay(), $overrideTo->copy()->endOfDay()];
        }

        if ($mode === PayableSyncRun::MODE_FULL) {
            return [null, null];
        }

        $back = $this->clamp((int) config('senior.window_days_back', 90), 1, 3650);
        $forward = $this->clamp((int) config('senior.window_days_forward', 90), 1, 3650);

        // req 5.5: usa o fim da última execução bem-sucedida como início, se houver.
        $lastOk = PayableSyncRun::where('status', PayableSyncRun::STATUS_SUCCESS)
            ->orderByDesc('finished_at')->first();

        $ini = $lastOk && $lastOk->finished_at
            ? $lastOk->finished_at->copy()
            : now()->subDays($back);

        return [$ini->startOfDay(), now()->addDays($forward)->endOfDay()];
    }

    /** Insere ou atualiza um título preservando Workflow_Fields (req 4, 8). */
    private function upsertTitulo(string $bk, array $titulo, int &$inserted, int &$updated): void
    {
        $attrs = $this->mapper->mapHeader($titulo);
        $existing = Payable::where('senior_id', $bk)->first();

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

            return;
        }

        // req 4.5: idempotência — se o conteúdo bruto não mudou, não grava nada.
        $semMudanca = $existing->senior_raw == $titulo;
        $voltouDaAusencia = $existing->senior_missing_at !== null; // req 7.4

        if ($semMudanca && !$voltouDaAusencia) {
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
        } elseif ($voltouDaAusencia) {
            // Conteúdo igual mas estava marcado como ausente: só limpa o flag (req 7.4).
            $existing->update(['senior_missing_at' => null]);
        }
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

    /** Marca como ausentes os títulos de origem Senior não retornados (req 7.1). */
    private function marcarAusentes(array $businessKeys): int
    {
        $query = Payable::whereNotNull('senior_id')->whereNull('senior_missing_at');
        if ($businessKeys !== []) {
            $query->whereNotIn('senior_id', array_values(array_unique($businessKeys)));
        }

        return $query->update(['senior_missing_at' => now()]);
    }

    private function clamp(int $v, int $min, int $max): int
    {
        return max($min, min($max, $v));
    }
}
