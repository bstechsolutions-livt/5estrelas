<?php

namespace App\Services\Senior;

use App\Models\Comercial\Filial;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza as filiais/empresas do grupo a partir da Senior (cad_filial).
 *
 * Mesma filosofia do PayablesSyncService:
 *  - Em modo desabilitado por config (senior.enabled=false) → execução IGNORADA,
 *    sem tocar nos dados (o seed das empresas reais permanece como fonte local).
 *  - Erro de negócio numa empresa (ex.: web service ainda não parametrizado no
 *    Senior) é registrado e ignorado — NÃO destrói os dados existentes.
 *  - NÃO marca filiais ausentes (empresas não somem); apenas insere/atualiza.
 *  - Preserva os campos de apresentação local (tipo/tag) no update.
 *
 * Read-only na Senior (apenas ConsultarGeral).
 */
class FiliaisSyncService
{
    public function __construct(
        private SeniorFilialClient $client,
        private FilialMapper $mapper,
    ) {
    }

    public static function make(): self
    {
        return new self(SeniorFilialClient::fromConfig(), new FilialMapper());
    }

    /**
     * @return array{status:string, inserted:int, updated:int, errors:int, message:?string}
     */
    public function run(string $trigger = 'manual'): array
    {
        // Desabilitado por configuração → ignora sem tocar nos dados.
        if (!config('senior.enabled', false)) {
            return [
                'status' => 'skipped',
                'inserted' => 0,
                'updated' => 0,
                'errors' => 0,
                'message' => 'Integração Senior desabilitada por configuração (senior.enabled=false).',
            ];
        }

        $codEmps = config('senior.cod_emps');
        if (empty($codEmps)) {
            $codEmps = range(2, 12); // empresas operacionais do grupo
        }

        $inserted = 0;
        $updated = 0;
        $errors = 0;
        $transportFailures = 0;
        $maxTransport = max(1, (int) config('senior.sweep_max_transport_failures', 3));

        foreach ($codEmps as $codEmp) {
            try {
                $filiais = $this->client->consultarGeral((int) $codEmp);
                $transportFailures = 0;
                foreach ($filiais as $filial) {
                    $bk = $this->mapper->businessKey($filial);
                    if ($bk === null) {
                        continue;
                    }
                    DB::transaction(function () use ($bk, $filial, &$inserted, &$updated) {
                        $this->upsert($bk, $filial, $inserted, $updated);
                    });
                }
            } catch (SeniorException $e) {
                if ($e->isTransient()) {
                    $transportFailures++;
                    Log::warning('[senior-filial] falha de transporte', ['codEmp' => $codEmp, 'erro' => $e->getMessage()]);
                    if ($transportFailures >= $maxTransport) {
                        return [
                            'status' => 'failed', 'inserted' => $inserted, 'updated' => $updated,
                            'errors' => $errors + 1, 'message' => $e->getMessage(),
                        ];
                    }
                } else {
                    // Erro de negócio (ex.: web service não parametrizado): ignora e segue.
                    $errors++;
                    Log::info('[senior-filial] empresa com erro de negócio, ignorada', [
                        'codEmp' => $codEmp, 'erro' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($inserted + $updated > 0) {
            AuditLogger::log(
                event: 'comercial.filiais.sync',
                module: 'comercial',
                description: "Sync de filiais (Senior): {$inserted} inseridas, {$updated} atualizadas",
                metadata: ['inserted' => $inserted, 'updated' => $updated, 'errors' => $errors, 'trigger' => $trigger],
            );
        }

        return [
            'status' => 'success',
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors,
            'message' => null,
        ];
    }

    /** Insere ou atualiza a filial preservando os campos de apresentação local (tipo/tag). */
    private function upsert(string $bk, array $filial, int &$inserted, int &$updated): void
    {
        $attrs = $this->mapper->mapHeader($filial);
        $existing = Filial::where('senior_id', $bk)->first();

        if (!$existing) {
            $f = new Filial();
            $f->forceFill($attrs);
            $f->senior_id = $bk;
            $f->ativo = true;
            $f->senior_synced_at = now();
            $f->save();
            $inserted++;

            return;
        }

        // Idempotência: nada mudou no payload bruto → não grava.
        if ($existing->senior_raw == $filial) {
            return;
        }

        // Atualiza origem Senior; preserva tipo/tag/ativo/ordem (apresentação local).
        $existing->forceFill($attrs);
        $existing->senior_synced_at = now();
        $existing->save();
        $updated++;
    }
}
