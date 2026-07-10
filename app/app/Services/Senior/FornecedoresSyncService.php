<?php

namespace App\Services\Senior;

use App\Models\Payable;
use App\Models\SeniorSupplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FornecedoresSyncService
{
    public function __construct(
        private SeniorFornecedorClient $client,
        private FornecedorMapper $mapper,
    ) {
    }

    public static function make(): self
    {
        return new self(SeniorFornecedorClient::fromConfig(), new FornecedorMapper());
    }

    /** @return array{status:string, inserted:int, updated:int, errors:int, enriched:int, message:?string} */
    public function run(string $trigger = 'manual'): array
    {
        if (!config('senior.enabled', false)) {
            return [
                'status' => 'skipped', 'inserted' => 0, 'updated' => 0, 'errors' => 0, 'enriched' => 0,
                'message' => 'Integração Senior desabilitada por configuração.',
            ];
        }

        $codEmps = config('senior.cod_emps');
        if (empty($codEmps)) {
            $codEmps = [(int) config('senior.cod_emp', 2)];
        }

        $inserted = 0;
        $updated = 0;
        $errors = 0;
        $pageSize = max(10, (int) config('senior.fornecedor_page_size', 100));

        foreach ($codEmps as $codEmp) {
            $indicePagina = 1;
            while (true) {
                try {
                    $fornecedores = $this->client->consultarGeral((int) $codEmp, 1, $indicePagina, $pageSize);
                    if ($fornecedores === []) {
                        break;
                    }
                    foreach ($fornecedores as $fornecedor) {
                        $fornecedor['codEmp'] ??= (int) $codEmp;
                        DB::transaction(function () use ($fornecedor, &$inserted, &$updated) {
                            $this->upsert($fornecedor, $inserted, $updated);
                        });
                    }
                    $count = count($fornecedores);
                    if ($count < $pageSize) {
                        break;
                    }
                    // Senior usa indicePagina como offset 1-based na lista, não número da página.
                    $indicePagina += $count;
                } catch (SeniorException $e) {
                    $errors++;
                    Log::warning('[senior-fornecedor] erro na empresa', ['codEmp' => $codEmp, 'erro' => $e->getMessage()]);
                    break;
                }
            }
        }

        $enriched = $this->enrichPayables();

        return [
            'status' => $errors > 0 && $inserted + $updated === 0 ? 'failed' : 'success',
            'inserted' => $inserted,
            'updated' => $updated,
            'errors' => $errors,
            'enriched' => $enriched,
            'message' => null,
        ];
    }

    private function upsert(array $fornecedor, int &$inserted, int &$updated): void
    {
        $attrs = $this->mapper->map($fornecedor);
        if ($attrs['cod_emp'] < 1 || $attrs['cod_for'] < 1) {
            return;
        }

        $existing = SeniorSupplier::where('cod_emp', $attrs['cod_emp'])
            ->where('cod_for', $attrs['cod_for'])
            ->first();

        if (!$existing) {
            SeniorSupplier::create($attrs);
            $inserted++;

            return;
        }

        if ($existing->senior_raw == $fornecedor) {
            return;
        }

        $existing->update($attrs);
        $updated++;
    }

    /** Atualiza supplier_name/cnpj dos títulos já importados. */
    public function enrichPayables(): int
    {
        $count = 0;
        Payable::query()
            ->whereNotNull('codemp')
            ->whereNotNull('codfor')
            ->chunkById(200, function ($payables) use (&$count) {
                foreach ($payables as $payable) {
                    $supplier = SeniorSupplier::where('cod_emp', (int) $payable->codemp)
                        ->where('cod_for', (int) $payable->codfor)
                        ->first();
                    if (!$supplier) {
                        continue;
                    }
                    $dirty = false;
                    if ($payable->supplier_name !== $supplier->name) {
                        $payable->supplier_name = $supplier->name;
                        $dirty = true;
                    }
                    if ($supplier->cnpj && $payable->supplier_cnpj !== $supplier->cnpj) {
                        $payable->supplier_cnpj = $supplier->cnpj;
                        $dirty = true;
                    }
                    if ($dirty) {
                        $payable->save();
                        $count++;
                    }
                }
            });

        return $count;
    }
}
