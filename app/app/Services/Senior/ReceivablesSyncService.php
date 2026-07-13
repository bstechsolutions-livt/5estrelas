<?php

namespace App\Services\Senior;

use App\Models\Receivable;
use App\Models\ReceivableSyncRun;
use App\Services\AuditLogger;
use App\Support\PayableEmpresaExclusion;
use App\Support\SeniorDueDatePolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceivablesSyncService
{
    public function __construct(
        private SeniorCrClient $client,
        private ReceivableMapper $mapper,
    ) {
    }

    public static function make(): self
    {
        return new self(SeniorCrClient::fromConfig(), new ReceivableMapper());
    }

    public function run(
        ?string $mode = null,
        string $trigger = ReceivableSyncRun::TRIGGER_SCHEDULED,
        ?Carbon $windowFrom = null,
        ?Carbon $windowTo = null,
    ): ReceivableSyncRun {
        $mode ??= ReceivableSyncRun::MODE_INCREMENTAL;
        $env = strtoupper(config('senior.environment', 'HML'));

        if (!config('senior.enabled', false)) {
            return ReceivableSyncRun::create([
                'environment' => $env, 'mode' => $mode, 'trigger' => $trigger,
                'status' => ReceivableSyncRun::STATUS_SKIPPED,
                'started_at' => now(), 'finished_at' => now(),
                'error_message' => 'Integração Senior desabilitada por configuração (senior.enabled=false).',
            ]);
        }

        if (ReceivableSyncRun::where('status', ReceivableSyncRun::STATUS_RUNNING)->whereNull('finished_at')->exists()) {
            AuditLogger::log(
                event: 'contas_receber.sync.sobreposicao',
                module: 'financeiro.contas_receber',
                description: 'Execução do sync ignorada: já existe outra em andamento.',
            );

            return ReceivableSyncRun::create([
                'environment' => $env, 'mode' => $mode, 'trigger' => $trigger,
                'status' => ReceivableSyncRun::STATUS_SKIPPED,
                'started_at' => now(), 'finished_at' => now(),
                'error_message' => 'Execução ignorada: já havia um sync em andamento.',
            ]);
        }

        [$vctIni, $vctFim] = $this->window($mode, $windowFrom, $windowTo);

        $run = ReceivableSyncRun::create([
            'environment' => $env, 'mode' => $mode, 'trigger' => $trigger,
            'status' => ReceivableSyncRun::STATUS_RUNNING,
            'started_at' => now(),
            'window_start' => $vctIni, 'window_end' => $vctFim,
        ]);

        try {
            $titulos = $this->collectTitulos($vctIni, $vctFim);
        } catch (\Throwable $e) {
            $run->update([
                'status' => ReceivableSyncRun::STATUS_FAILED,
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
                            Log::warning('[senior-cr] título sem Business_Key derivável, descartado', ['titulo' => $titulo]);
                            continue;
                        }
                        $businessKeys[] = $bk;
                        $this->upsertTitulo($bk, $titulo, $inserted, $updated);
                    }
                });
            } catch (\Throwable $e) {
                Log::error('[senior-cr] falha ao gravar lote do sync', ['erro' => $e->getMessage(), 'tamanho' => count($chunk)]);
            }
        }

        $missing = $this->marcarAusentes($businessKeys, $vctIni, $vctFim);

        $run->update([
            'status' => ReceivableSyncRun::STATUS_SUCCESS,
            'finished_at' => now(),
            'inserted_count' => $inserted,
            'updated_count' => $updated,
            'missing_count' => $missing,
        ]);

        if ($inserted + $updated > 0) {
            AuditLogger::log(
                event: 'contas_receber.sync.concluido',
                module: 'financeiro.contas_receber',
                description: "Sync Contas a Receber: {$inserted} inseridos, {$updated} atualizados, {$missing} ausentes",
                metadata: ['sync_run_id' => $run->id, 'inserted' => $inserted, 'updated' => $updated, 'missing' => $missing],
            );
        }

        return $run->fresh();
    }

    private function collectTitulos(?Carbon $vctIni, ?Carbon $vctFim): array
    {
        $codEmps = config('senior.cod_emps');
        if (empty($codEmps)) {
            $codEmps = [(int) config('senior.cod_emp', 1)];
        }
        $codEmps = PayableEmpresaExclusion::filterCodEmps($codEmps);
        $cliStart = (int) config('senior.cod_cli_start', 1);
        $cliEnd = (int) config('senior.cod_cli_end', 9999);
        $delayMs = (int) config('senior.sweep_delay_ms', 0);
        $maxTransport = max(1, (int) config('senior.sweep_max_transport_failures', 3));

        $all = [];
        $consecutiveTransport = 0;

        foreach ($codEmps as $codEmp) {
            for ($codCli = $cliStart; $codCli <= $cliEnd; $codCli++) {
                try {
                    $titulos = $this->client->consultarTitulosPorCliente((int) $codEmp, $codCli, $vctIni, $vctFim);
                    $consecutiveTransport = 0;
                    foreach ($titulos as $t) {
                        $all[] = $t;
                    }
                } catch (SeniorException $e) {
                    if ($e->isTransient()) {
                        $consecutiveTransport++;
                        if ($consecutiveTransport >= $maxTransport) {
                            throw $e;
                        }
                    } else {
                        $consecutiveTransport = 0;
                    }
                }

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        return $this->dedupTitulos($all);
    }

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

    private function window(string $mode, ?Carbon $overrideFrom = null, ?Carbon $overrideTo = null): array
    {
        if ($overrideFrom && $overrideTo) {
            return [$overrideFrom->copy()->startOfDay(), $overrideTo->copy()->endOfDay()];
        }

        if ($mode === ReceivableSyncRun::MODE_FULL) {
            return [null, null];
        }

        $forward = max(1, min(3650, (int) config('senior.window_days_forward', 90)));
        $base = config('senior.vct_base_date');
        $ini = $base
            ? SeniorDueDatePolicy::windowFrom(Carbon::parse($base))
            : SeniorDueDatePolicy::windowFrom(now()->subDays(max(1, min(3650, (int) config('senior.window_days_back', 90)))));

        return [$ini, now()->addDays($forward)->endOfDay()];
    }

    private function upsertTitulo(string $bk, array $titulo, int &$inserted, int &$updated): void
    {
        $attrs = $this->mapper->mapHeader($titulo);
        $dueDate = isset($attrs['due_date']) ? Carbon::parse($attrs['due_date']) : null;
        if (!SeniorDueDatePolicy::isAllowed($dueDate)) {
            Log::debug('[senior-cr] título ignorado por vencimento anterior ao corte', [
                'business_key' => $bk,
                'due_date' => $dueDate?->toDateString(),
            ]);

            return;
        }

        $existing = Receivable::where('senior_id', $bk)->first();

        if (!$existing) {
            $receivable = new Receivable();
            $receivable->forceFill($attrs);
            $receivable->senior_id = $bk;
            $receivable->senior_synced_at = now();
            $receivable->save();
            $this->syncRateios($receivable, $titulo);
            $inserted++;

            return;
        }

        $semMudanca = $existing->senior_raw == $titulo;
        $voltouDaAusencia = $existing->senior_missing_at !== null;

        if ($semMudanca && !$voltouDaAusencia) {
            return;
        }

        if (!$semMudanca) {
            $existing->forceFill($attrs);
            $existing->senior_synced_at = now();
            $existing->senior_missing_at = null;
            $existing->save();
            $this->syncRateios($existing, $titulo);
            $updated++;
        } elseif ($voltouDaAusencia) {
            $existing->update(['senior_missing_at' => null]);
        }
    }

    private function syncRateios(Receivable $receivable, array $titulo): void
    {
        $receivable->rateios()->delete();
        foreach (($titulo['rateios'] ?? []) as $rateio) {
            if (is_array($rateio) && $rateio !== []) {
                $receivable->rateios()->create($this->mapper->mapRateio($rateio));
            }
        }
    }

    private function marcarAusentes(array $businessKeys, ?Carbon $vctIni = null, ?Carbon $vctFim = null): int
    {
        $query = Receivable::whereNotNull('senior_id')->whereNull('senior_missing_at');
        if ($businessKeys !== []) {
            $query->whereNotIn('senior_id', array_values(array_unique($businessKeys)));
        }
        if ($vctIni !== null) {
            $query->where('due_date', '>=', SeniorDueDatePolicy::windowFrom($vctIni));
        } else {
            $query->where('due_date', '>=', SeniorDueDatePolicy::minDueDate());
        }
        if ($vctFim !== null) {
            $query->where('due_date', '<=', $vctFim);
        }

        return $query->update(['senior_missing_at' => now()]);
    }
}
