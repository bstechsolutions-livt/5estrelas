<?php

namespace App\Services\Senior;

use App\Models\ChartOfAccount;
use App\Models\Payable;
use App\Models\PayableRateio;
use App\Models\Receivable;
use App\Models\ReceivableRateio;
use Illuminate\Support\Facades\DB;

/**
 * Plano de contas interim: extrai códigos distintos de ctaFin e codCcu
 * já presentes em payables/receivables (e rateios).
 *
 * TODO: substituir por consulta ao serviço Senior com_senior_g5_co_mct_ctb_planocontacontabil
 * quando parametrizado no Sistema Integrado EASYTECH.
 */
class ChartOfAccountsSyncService
{
    public function run(): array
    {
        $rows = $this->collectDerivedAccounts();
        $inserted = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $existing = ChartOfAccount::query()
                ->where('code', $row['code'])
                ->where('account_type', $row['account_type'])
                ->where('codemp', $row['codemp'])
                ->first();

            if (!$existing) {
                ChartOfAccount::create(array_merge($row, ['synced_at' => now()]));
                $inserted++;
                continue;
            }

            if ($existing->description !== $row['description'] || $existing->source !== $row['source']) {
                $existing->update([
                    'description' => $row['description'],
                    'source' => $row['source'],
                    'synced_at' => now(),
                ]);
                $updated++;
            }
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'total_distinct' => count($rows),
        ];
    }

    /** @return list<array{code:string,description:?string,account_type:string,codemp:?int,source:string,senior_raw:?array}> */
    private function collectDerivedAccounts(): array
    {
        $accounts = [];

        $this->addFromQuery(
            $accounts,
            Payable::query()
                ->select(['codemp', 'ctafin'])
                ->whereNotNull('ctafin')
                ->where('ctafin', '>', 0)
                ->distinct(),
            ChartOfAccount::TYPE_CONTA_FINANCEIRA,
            'ctafin',
        );

        $this->addFromQuery(
            $accounts,
            Payable::query()
                ->select(['codemp', 'codccu'])
                ->whereNotNull('codccu')
                ->where('codccu', '!=', '')
                ->distinct(),
            ChartOfAccount::TYPE_CENTRO_CUSTO,
            'codccu',
        );

        $this->addFromQuery(
            $accounts,
            PayableRateio::query()
                ->join('payables', 'payables.id', '=', 'payable_rateios.payable_id')
                ->select(['payables.codemp', 'payable_rateios.ctafin'])
                ->whereNotNull('payable_rateios.ctafin')
                ->where('payable_rateios.ctafin', '>', 0)
                ->distinct(),
            ChartOfAccount::TYPE_CONTA_FINANCEIRA,
            'ctafin',
        );

        $this->addFromQuery(
            $accounts,
            PayableRateio::query()
                ->join('payables', 'payables.id', '=', 'payable_rateios.payable_id')
                ->select(['payables.codemp', 'payable_rateios.codccu'])
                ->whereNotNull('payable_rateios.codccu')
                ->where('payable_rateios.codccu', '!=', '')
                ->distinct(),
            ChartOfAccount::TYPE_CENTRO_CUSTO,
            'codccu',
        );

        if ($this->hasReceivablesTable()) {
            $this->addFromQuery(
                $accounts,
                Receivable::query()
                    ->select(['codemp', 'ctafin'])
                    ->whereNotNull('ctafin')
                    ->where('ctafin', '>', 0)
                    ->distinct(),
                ChartOfAccount::TYPE_CONTA_FINANCEIRA,
                'ctafin',
            );

            $this->addFromQuery(
                $accounts,
                Receivable::query()
                    ->select(['codemp', 'codccu'])
                    ->whereNotNull('codccu')
                    ->where('codccu', '!=', '')
                    ->distinct(),
                ChartOfAccount::TYPE_CENTRO_CUSTO,
                'codccu',
            );
        }

        return array_values($accounts);
    }

    private function hasReceivablesTable(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('receivables');
        } catch (\Throwable) {
            return false;
        }
    }

    private function addFromQuery(array &$accounts, $query, string $type, string $column): void
    {
        foreach ($query->cursor() as $row) {
            $code = trim((string) ($row->{$column} ?? ''));
            if ($code === '' || $code === '0') {
                continue;
            }
            $codemp = isset($row->codemp) ? (int) $row->codemp : null;
            $key = "{$type}|{$codemp}|{$code}";
            if (isset($accounts[$key])) {
                continue;
            }
            $accounts[$key] = [
                'code' => $code,
                'description' => null,
                'account_type' => $type,
                'codemp' => $codemp,
                'source' => 'derived',
                'senior_raw' => null,
            ];
        }
    }
}
