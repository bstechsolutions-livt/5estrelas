<?php

namespace App\Services\Senior;

use App\Models\Payable;
use Illuminate\Support\Facades\Log;

/**
 * Preenche payables.senior_cod_usu a partir do UsuGer (lançador) do prj.contaspagar.
 * Sem fallback: se a Senior não devolver UsuGer > 0, o título permanece sem lançador.
 */
class PayableLauncherSyncService
{
    public function __construct(private SeniorPrjContasPagarClient $client)
    {
    }

    public static function make(): self
    {
        return new self(SeniorPrjContasPagarClient::fromConfig());
    }

    /**
     * @return array{status:string, bulk_matched:int, looked_up:int, updated:int, errors:int, skipped:int, message:?string}
     */
    public function run(
        ?int $codEmp = null,
        ?int $codFil = null,
        ?int $maxLookups = null,
        string $trigger = 'manual',
        ?int $createdWithinMinutes = null,
    ): array {
        if (! config('senior.enabled', false)) {
            return [
                'status' => 'skipped',
                'bulk_matched' => 0,
                'looked_up' => 0,
                'updated' => 0,
                'errors' => 0,
                'skipped' => 0,
                'message' => 'Integração Senior desabilitada.',
            ];
        }

        $bulkMatched = 0;
        $updated = 0;
        $errors = 0;
        $lookedUp = 0;
        $skipped = 0;

        if ($codEmp && $codFil) {
            try {
                $bulkMatched = $this->applyBulkConsultarGeral($codEmp, $codFil);
                $updated += $bulkMatched;
            } catch (SeniorException $e) {
                $errors++;
                Log::warning('[senior-launcher] ConsultarGeral falhou', [
                    'codEmp' => $codEmp,
                    'codFil' => $codFil,
                    'erro' => $e->getMessage(),
                    'trigger' => $trigger,
                ]);
            }
        }

        $query = Payable::query()
            ->whereNotNull('codemp')
            ->whereNotNull('codfil')
            ->whereNotNull('codfor')
            ->whereNotNull('title_number')
            ->where(function ($q) {
                $q->whereNull('senior_cod_usu')->orWhere('senior_cod_usu', '<=', 0);
            });

        if ($codEmp) {
            $query->where('codemp', $codEmp);
        }
        if ($codFil) {
            $query->where('codfil', $codFil);
        }
        if ($createdWithinMinutes !== null && $createdWithinMinutes > 0) {
            $query->where('created_at', '>=', now()->subMinutes($createdWithinMinutes));
        }

        // chunkById (ASC) ignorava orderByDesc e consumia o --max nos títulos
        // mais antigos sem UsuGer — lançamentos novos ficavam sem departamento.
        $query->chunkByIdDesc(100, function ($chunk) use (
            &$lookedUp, &$updated, &$errors, &$skipped, $maxLookups
        ) {
            foreach ($chunk as $payable) {
                if ($maxLookups !== null && $lookedUp >= $maxLookups) {
                    return false;
                }

                $result = $this->enrichOnePayable($payable);
                if ($result === 'updated') {
                    $lookedUp++;
                    $updated++;
                } elseif ($result === 'error') {
                    $lookedUp++;
                    $errors++;
                } elseif ($result === 'lookup_skipped') {
                    $lookedUp++;
                    $skipped++;
                } else {
                    $skipped++;
                }
            }

            return true;
        });

        Log::info('[senior-launcher] enrich concluído', [
            'trigger' => $trigger,
            'codEmp' => $codEmp,
            'codFil' => $codFil,
            'created_within_minutes' => $createdWithinMinutes,
            'bulk_matched' => $bulkMatched,
            'looked_up' => $lookedUp,
            'updated' => $updated,
            'errors' => $errors,
            'skipped' => $skipped,
        ]);

        return [
            'status' => 'ok',
            'bulk_matched' => $bulkMatched,
            'looked_up' => $lookedUp,
            'updated' => $updated,
            'errors' => $errors,
            'skipped' => $skipped,
            'message' => null,
        ];
    }

    /**
     * Enrich pontual (ex.: backfill operacional) — Exportar E só nos IDs informados.
     *
     * @param  list<int>  $payableIds
     * @return array{status:string, bulk_matched:int, looked_up:int, updated:int, errors:int, skipped:int, message:?string}
     */
    public function enrichPayableIds(array $payableIds, string $trigger = 'ids'): array
    {
        if (! config('senior.enabled', false)) {
            return [
                'status' => 'skipped',
                'bulk_matched' => 0,
                'looked_up' => 0,
                'updated' => 0,
                'errors' => 0,
                'skipped' => 0,
                'message' => 'Integração Senior desabilitada.',
            ];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $payableIds), fn (int $id) => $id > 0)));
        $updated = 0;
        $errors = 0;
        $lookedUp = 0;
        $skipped = 0;

        if ($ids === []) {
            return [
                'status' => 'ok',
                'bulk_matched' => 0,
                'looked_up' => 0,
                'updated' => 0,
                'errors' => 0,
                'skipped' => 0,
                'message' => null,
            ];
        }

        $payables = Payable::query()
            ->whereIn('id', $ids)
            ->where(function ($q) {
                $q->whereNull('senior_cod_usu')->orWhere('senior_cod_usu', '<=', 0);
            })
            ->orderByDesc('id')
            ->get();

        foreach ($payables as $payable) {
            $result = $this->enrichOnePayable($payable);
            if ($result === 'updated') {
                $lookedUp++;
                $updated++;
            } elseif ($result === 'error') {
                $lookedUp++;
                $errors++;
            } elseif ($result === 'lookup_skipped') {
                $lookedUp++;
                $skipped++;
            } else {
                $skipped++;
            }
        }

        Log::info('[senior-launcher] enrich por ids concluído', [
            'trigger' => $trigger,
            'ids' => count($ids),
            'looked_up' => $lookedUp,
            'updated' => $updated,
            'errors' => $errors,
            'skipped' => $skipped,
        ]);

        return [
            'status' => 'ok',
            'bulk_matched' => 0,
            'looked_up' => $lookedUp,
            'updated' => $updated,
            'errors' => $errors,
            'skipped' => $skipped,
            'message' => null,
        ];
    }

    /** @return 'updated'|'error'|'lookup_skipped'|'skipped' */
    private function enrichOnePayable(Payable $payable): string
    {
        if ($payable->senior_cod_usu && (int) $payable->senior_cod_usu > 0) {
            return 'skipped';
        }

        $codEmp = (int) $payable->codemp;
        $codFil = (int) $payable->codfil;
        $codFor = (int) $payable->codfor;
        $numTit = trim((string) $payable->title_number);
        $codTpt = trim((string) ($payable->codtpt ?? ''));
        if ($codEmp < 1 || $codFil < 1 || $codFor < 1 || $numTit === '' || $codTpt === '') {
            return 'skipped';
        }

        try {
            $row = $this->client->exportarEspecifico($codEmp, $codFil, $numTit, $codFor, $codTpt);
        } catch (SeniorException $e) {
            Log::debug('[senior-launcher] Exportar E falhou', [
                'payable_id' => $payable->id,
                'erro' => $e->getMessage(),
            ]);

            return 'error';
        }

        $usuGer = (int) ($row['UsuGer'] ?? 0);
        if ($usuGer <= 0) {
            return 'lookup_skipped';
        }

        $payable->update([
            'senior_cod_usu' => $usuGer,
        ]);

        return 'updated';
    }

    private function applyBulkConsultarGeral(int $codEmp, int $codFil): int
    {
        $rows = $this->client->consultarGeral($codEmp, $codFil);
        $updated = 0;

        foreach ($rows as $row) {
            $usuGer = (int) ($row['UsuGer'] ?? 0);
            if ($usuGer <= 0) {
                continue;
            }

            $numTit = ltrim((string) $row['NumTit'], '0');
            if ($numTit === '') {
                $numTit = (string) $row['NumTit'];
            }

            $query = Payable::query()
                ->where('codemp', (int) $row['CodEmp'])
                ->where('codfil', (int) $row['CodFil'])
                ->where('codfor', (int) $row['CodFor'])
                ->where(function ($q) {
                    $q->whereNull('senior_cod_usu')->orWhere('senior_cod_usu', '<=', 0);
                })
                ->where(function ($q) use ($row, $numTit) {
                    $q->where('title_number', (string) $row['NumTit'])
                        ->orWhere('title_number', $numTit);
                });

            if ($row['CodTpt'] !== '') {
                $query->where('codtpt', $row['CodTpt']);
            }

            $payables = $query->get();
            foreach ($payables as $payable) {
                $payable->update([
                    'senior_cod_usu' => $usuGer,
                ]);
                $updated++;
            }
        }

        return $updated;
    }
}
