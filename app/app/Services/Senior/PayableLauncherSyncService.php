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
     * Enrich imediato pós-insert do sync AbertosCP (só os IDs novos).
     *
     * @param  list<int>  $payableIds
     * @return array{status:string, bulk_matched:int, looked_up:int, updated:int, errors:int, skipped:int, message:?string}
     */
    public function enrichByPayableIds(array $payableIds, ?int $maxLookups = null, string $trigger = 'pos-insert'): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $payableIds), fn (int $id) => $id > 0)));
        if ($ids === []) {
            return $this->emptyResult('ok');
        }

        if (! config('senior.enabled', false)) {
            return $this->emptyResult('skipped', 'Integração Senior desabilitada.');
        }

        $payables = Payable::query()
            ->whereIn('id', $ids)
            ->whereNotNull('codemp')
            ->whereNotNull('codfil')
            ->whereNotNull('codfor')
            ->whereNotNull('title_number')
            ->where(function ($q) {
                $q->whereNull('senior_cod_usu')->orWhere('senior_cod_usu', '<=', 0);
            })
            ->orderByDesc('id')
            ->get();

        $bulkMatched = 0;
        $errors = 0;
        $pairs = $payables
            ->map(fn (Payable $p) => [(int) $p->codemp, (int) $p->codfil])
            ->unique(fn (array $pair) => $pair[0] . '-' . $pair[1])
            ->values();

        foreach ($pairs as [$codEmp, $codFil]) {
            if ($codEmp < 1 || $codFil < 1) {
                continue;
            }
            try {
                $bulkMatched += $this->applyBulkConsultarGeral($codEmp, $codFil);
            } catch (SeniorException $e) {
                $errors++;
                Log::warning('[senior-launcher] ConsultarGeral falhou (pos-insert)', [
                    'codEmp' => $codEmp,
                    'codFil' => $codFil,
                    'erro' => $e->getMessage(),
                    'trigger' => $trigger,
                ]);
            }
        }

        // Recarrega quem ainda falta após o bulk.
        $remaining = Payable::query()
            ->whereIn('id', $ids)
            ->where(function ($q) {
                $q->whereNull('senior_cod_usu')->orWhere('senior_cod_usu', '<=', 0);
            })
            ->orderByDesc('id')
            ->get();

        $lookedUp = 0;
        $updated = $bulkMatched;
        $skipped = 0;

        foreach ($remaining as $payable) {
            if ($maxLookups !== null && $lookedUp >= $maxLookups) {
                break;
            }
            $result = $this->exportAndApply($payable);
            if ($result === 'updated') {
                $lookedUp++;
                $updated++;
            } elseif ($result === 'error') {
                $lookedUp++;
                $errors++;
            } elseif ($result === 'looked') {
                $lookedUp++;
                $skipped++;
            } else {
                $skipped++;
            }
        }

        Log::info('[senior-launcher] enrich por IDs concluído', [
            'trigger' => $trigger,
            'ids' => count($ids),
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
     * @return array{status:string, bulk_matched:int, looked_up:int, updated:int, errors:int, skipped:int, message:?string}
     */
    public function run(
        ?int $codEmp = null,
        ?int $codFil = null,
        ?int $maxLookups = null,
        string $trigger = 'manual',
    ): array {
        if (! config('senior.enabled', false)) {
            return $this->emptyResult('skipped', 'Integração Senior desabilitada.');
        }

        $bulkMatched = 0;
        $updated = 0;
        $errors = 0;
        $lookedUp = 0;
        $skipped = 0;

        $bulkPairs = $this->bulkPairsForRun($codEmp, $codFil);
        foreach ($bulkPairs as [$emp, $fil]) {
            try {
                $bulkMatched += $this->applyBulkConsultarGeral($emp, $fil);
            } catch (SeniorException $e) {
                $errors++;
                Log::warning('[senior-launcher] ConsultarGeral falhou', [
                    'codEmp' => $emp,
                    'codFil' => $fil,
                    'erro' => $e->getMessage(),
                    'trigger' => $trigger,
                ]);
            }
        }
        $updated += $bulkMatched;

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

        // Mais novos primeiro — evita backlog antigo empurrar títulos recém-sincronizados.
        // Carrega IDs em ordem desc e processa em fatias (chunkById ignoraria o orderByDesc).
        $candidateIds = (clone $query)->orderByDesc('id')->pluck('id');
        foreach ($candidateIds->chunk(100) as $idChunk) {
            $chunk = Payable::query()->whereIn('id', $idChunk->all())->get()->keyBy('id');
            foreach ($idChunk as $id) {
                if ($maxLookups !== null && $lookedUp >= $maxLookups) {
                    break 2;
                }
                $payable = $chunk->get($id);
                if (! $payable) {
                    continue;
                }
                if ($payable->senior_cod_usu && (int) $payable->senior_cod_usu > 0) {
                    $skipped++;

                    continue;
                }

                $result = $this->exportAndApply($payable);
                if ($result === 'updated') {
                    $lookedUp++;
                    $updated++;
                } elseif ($result === 'error') {
                    $lookedUp++;
                    $errors++;
                } elseif ($result === 'looked') {
                    $lookedUp++;
                    $skipped++;
                } else {
                    $skipped++;
                }
            }
        }

        Log::info('[senior-launcher] enrich concluído', [
            'trigger' => $trigger,
            'codEmp' => $codEmp,
            'codFil' => $codFil,
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
     * @return array{status:string, bulk_matched:int, looked_up:int, updated:int, errors:int, skipped:int, message:?string}
     */
    private function emptyResult(string $status, ?string $message = null): array
    {
        return [
            'status' => $status,
            'bulk_matched' => 0,
            'looked_up' => 0,
            'updated' => 0,
            'errors' => 0,
            'skipped' => 0,
            'message' => $message,
        ];
    }

    /**
     * Pares (codEmp, codFil) para bulk ConsultarGeral.
     * Sem filtro: todas as filiais ativas com títulos ainda sem lançador.
     *
     * @return list<array{0:int,1:int}>
     */
    private function bulkPairsForRun(?int $codEmp, ?int $codFil): array
    {
        if ($codEmp && $codFil) {
            return [[$codEmp, $codFil]];
        }

        $query = Payable::query()
            ->select('codemp', 'codfil')
            ->whereNotNull('codemp')
            ->whereNotNull('codfil')
            ->where(function ($q) {
                $q->whereNull('senior_cod_usu')->orWhere('senior_cod_usu', '<=', 0);
            });

        if ($codEmp) {
            $query->where('codemp', $codEmp);
        }
        if ($codFil) {
            $query->where('codfil', $codFil);
        }

        return $query
            ->distinct()
            ->orderBy('codemp')
            ->orderBy('codfil')
            ->get()
            ->map(fn ($row) => [(int) $row->codemp, (int) $row->codfil])
            ->filter(fn (array $pair) => $pair[0] > 0 && $pair[1] > 0)
            ->values()
            ->all();
    }

    /** @return 'updated'|'looked'|'error'|'skipped' */
    private function exportAndApply(Payable $payable): string
    {
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
            return 'looked';
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
