<?php

namespace App\Services\Senior;

use App\Models\Department;
use App\Models\Payable;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Preenche payables.senior_cod_usu a partir do UsuGer (lançador) do prj.contaspagar.
 * Sempre pontual: 1 título = 1 Exportar E. Sem ConsultarGeral em lote.
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
     * Enrich pontual (fila / pós-sync): 1 título = 1 Exportar E na Senior.
     * Sem ConsultarGeral em lote — sync AbertosCP já trouxe os títulos.
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

        $lookedUp = 0;
        $updated = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($payables as $payable) {
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

        Log::info('[senior-launcher] enrich pontual por IDs', [
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

    /**
     * Cron/manual: UsuGer só via Exportar pontual (1 título por chamada).
     *
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

        $updated = 0;
        $errors = 0;
        $lookedUp = 0;
        $skipped = 0;

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

        // Prioriza fila aguardando sync, depois os mais novos.
        $candidateIds = (clone $query)
            ->orderByRaw("CASE WHEN status = '".Payable::STATUS_AGUARDANDO_VINCULO_DEPARTAMENTO."' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->pluck('id');
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

        Log::info('[senior-launcher] enrich pontual concluído', [
            'trigger' => $trigger,
            'codEmp' => $codEmp,
            'codFil' => $codFil,
            'looked_up' => $lookedUp,
            'updated' => $updated,
            'errors' => $errors,
            'skipped' => $skipped,
        ]);

        $awaitingIds = PayablesSyncService::make()->awaitingSyncPayableIds(500);
        if ($awaitingIds !== []) {
            PayablesSyncService::make()->resolveDepartmentsAfterSync($awaitingIds);
        }

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

        $payable->update($this->launcherUpdatePayload($payable, $usuGer));

        PayablesSyncService::make()->applyPostSyncReadiness($payable->fresh());

        return 'updated';
    }

    /**
     * @return array{senior_cod_usu: int, department_id?: int}
     */
    private function launcherUpdatePayload(Payable $payable, int $usuGer): array
    {
        $payload = ['senior_cod_usu' => $usuGer];
        $financeiroId = Department::financeDepartmentId();

        $user = User::query()
            ->where('senior_cod_usu', $usuGer)
            ->whereNotNull('department_id')
            ->first();
        if ($user?->department_id) {
            $dept = Department::query()->find($user->department_id);
            if ($dept && $dept->is_active) {
                if ($payable->department_id === null || (int) $payable->department_id === (int) $financeiroId) {
                    $payload['department_id'] = (int) $dept->id;
                }
            }
        } else {
            $legacyDeptId = Department::departmentIdForLegacySeniorCodUsu($usuGer);
            if ($legacyDeptId !== null
                && ($payable->department_id === null || (int) $payable->department_id === (int) $financeiroId)) {
                $payload['department_id'] = $legacyDeptId;
            }
        }

        return $payload;
    }
}
