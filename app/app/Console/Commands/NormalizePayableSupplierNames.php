<?php

namespace App\Console\Commands;

use App\Models\Payable;
use App\Models\SeniorSupplier;
use App\Services\Senior\SupplierDisplayNameResolver;
use Illuminate\Console\Command;

class NormalizePayableSupplierNames extends Command
{
    protected $signature = 'payables:normalize-supplier-names
        {--dry-run : Apenas conta títulos que seriam corrigidos}
        {--execute : Grava nomes normalizados}';

    protected $description = 'Corrige supplier_name de títulos Senior com descrição/parcela no lugar do fornecedor';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        if ($this->option('dry-run')) {
            $execute = false;
        }

        if (! $execute && ! $this->option('dry-run')) {
            $this->info('Modo DRY-RUN (use --execute para aplicar).');
        }

        $resolver = new SupplierDisplayNameResolver();
        $wouldUpdate = 0;
        $updated = 0;

        Payable::query()
            ->whereNotNull('codemp')
            ->whereNotNull('codfor')
            ->orderBy('id')
            ->chunkById(500, function ($payables) use ($resolver, $execute, &$wouldUpdate, &$updated) {
                $pairs = $payables
                    ->map(fn ($p) => (int) $p->codemp . '-' . (int) $p->codfor)
                    ->unique();

                $supplierByPair = SeniorSupplier::query()
                    ->where(function ($q) use ($pairs) {
                        foreach ($pairs as $pair) {
                            [$codEmp, $codFor] = explode('-', $pair, 2);
                            $q->orWhere(fn ($qq) => $qq->where('cod_emp', (int) $codEmp)->where('cod_for', (int) $codFor));
                        }
                    })
                    ->get()
                    ->keyBy(fn ($s) => $s->cod_emp . '-' . $s->cod_for);

                foreach ($payables as $payable) {
                    $key = (int) $payable->codemp . '-' . (int) $payable->codfor;
                    $supplier = $supplierByPair->get($key);
                    $resolved = $resolver->resolveForPayable($payable, $supplier);
                    if ($payable->supplier_name === $resolved) {
                        continue;
                    }

                    $wouldUpdate++;
                    if ($execute) {
                        $payable->supplier_name = $resolved;
                        $payable->save();
                        $updated++;
                    }
                }
            });

        if ($execute) {
            $this->info("Títulos atualizados: {$updated}");
        } else {
            $this->info("Títulos que seriam corrigidos: {$wouldUpdate}");
        }

        return self::SUCCESS;
    }
}
