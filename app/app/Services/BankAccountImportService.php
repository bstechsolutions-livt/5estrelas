<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\User;
use App\Services\Senior\SeniorException;
use App\Services\Senior\SeniorTesContasClient;
use App\Support\OfficialBankAccountCatalog;
use Illuminate\Support\Facades\DB;

class BankAccountImportService
{
    public function __construct(
        private ?SeniorTesContasClient $client = null,
    ) {
        $this->client ??= SeniorTesContasClient::fromConfig();
    }

    /**
     * Importação one-shot: cruza a Senior com a relação oficial do Financeiro.
     * A Senior possui centenas de contas internas que não são contas bancárias;
     * portanto, nenhuma heurística por descrição é usada aqui.
     *
     * @return array{created: int, updated: int, removed: int, skipped: int, total_senior: int}
     *
     * @throws SeniorException
     */
    public function importFromSenior(?User $actor = null): array
    {
        $rows = $this->client->obterTodasContas();
        $catalog = OfficialBankAccountCatalog::all();
        $rowsByKey = collect($rows)->keyBy(
            fn (array $row) => $this->seniorKey(
                (int) ($row['codigoEmpresa'] ?? 0),
                (string) ($row['numeroConta'] ?? ''),
            ),
        );
        $officialKeys = collect($catalog)
            ->map(fn (array $account) => $this->seniorKey(
                (int) $account['senior_codemp'],
                (string) $account['senior_num_cco'],
            ))
            ->all();

        $created = 0;
        $updated = 0;
        $removed = 0;

        DB::transaction(function () use (
            $catalog,
            $rowsByKey,
            $officialKeys,
            $actor,
            &$created,
            &$updated,
            &$removed,
        ) {
            // Limpa somente registros provenientes da Senior. Contas criadas
            // manualmente no Hub permanecem intactas.
            BankAccount::query()
                ->whereNotNull('senior_codemp')
                ->get()
                ->reject(fn (BankAccount $account) => in_array(
                    $this->seniorKey(
                        (int) $account->senior_codemp,
                        (string) $account->senior_num_cco,
                    ),
                    $officialKeys,
                    true,
                ))
                ->each(function (BankAccount $account) use (&$removed) {
                    $account->delete();
                    $removed++;
                });

            foreach ($catalog as $official) {
                $codEmp = (int) $official['senior_codemp'];
                $numCco = (string) $official['senior_num_cco'];
                $senior = $rowsByKey->get($this->seniorKey($codEmp, $numCco));
                $descricaoSenior = trim((string) ($senior['descricaoConta'] ?? '')) ?: null;

                $existing = BankAccount::query()
                    ->where('senior_codemp', $codEmp)
                    ->where('senior_num_cco', $numCco)
                    ->first();

                if (! $existing) {
                    BankAccount::create([
                        'name' => "{$official['unit']} — {$official['bank_name']}",
                        'is_active' => true,
                        'senior_codemp' => $codEmp,
                        'senior_codfil' => (int) ($senior['codigoFilial'] ?? 0),
                        'senior_num_cco' => $numCco,
                        'senior_descricao' => $descricaoSenior,
                        'bank_code' => $official['bank_code'],
                        'bank_name' => $official['bank_name'],
                        'agency' => $official['agency'],
                        'account_number' => $official['account_number'],
                        'account_digit' => $official['account_digit'],
                        'imported_from_senior_at' => now(),
                        'created_by' => $actor?->id,
                    ]);
                    $created++;

                    continue;
                }

                $existing->fill([
                    'senior_codfil' => (int) ($senior['codigoFilial'] ?? 0),
                    'senior_descricao' => $descricaoSenior,
                    'imported_from_senior_at' => now(),
                ]);

                // Primeiro carregamento da relação oficial: completa registros
                // que vieram do import amplo anterior. Depois disso, edições
                // feitas no Hub não são sobrescritas por uma nova execução.
                if (blank($existing->bank_code) && blank($existing->account_number)) {
                    $existing->fill([
                        'name' => "{$official['unit']} — {$official['bank_name']}",
                        'bank_code' => $official['bank_code'],
                        'bank_name' => $official['bank_name'],
                        'agency' => $official['agency'],
                        'account_number' => $official['account_number'],
                        'account_digit' => $official['account_digit'],
                    ]);
                }

                $existing->save();
                $updated++;
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'removed' => $removed,
            'skipped' => max(0, count($rows) - count($catalog)),
            'total_senior' => count($rows),
        ];
    }

    private function seniorKey(int $codEmp, string $numCco): string
    {
        return $codEmp.'|'.trim($numCco);
    }
}
