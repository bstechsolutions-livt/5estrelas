<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Comercial\Filial;
use Illuminate\Console\Command;

class BackfillBranchSeniorVinculos extends Command
{
    protected $signature = 'branches:backfill-senior-vinculos {--dry-run : Apenas simula, sem gravar}';

    protected $description = 'Vincula filiais locais (branches) à Senior: cod_emp, cod_fil e apelido';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;

        foreach (Branch::orderBy('id')->get() as $branch) {
            $pair = $this->resolvePair($branch);

            if ($pair === null) {
                $this->warn("SKIP #{$branch->id} {$branch->name} — sem vínculo Senior");
                $skipped++;

                continue;
            }

            $payload = [
                'cod_emp' => $pair['cod_emp'],
                'cod_fil' => $pair['cod_fil'],
                'apelido' => $pair['apelido'],
            ];

            $this->line(sprintf(
                '%s #%d %s → emp %d / fil %d / apelido "%s"',
                $dryRun ? '[dry]' : 'OK',
                $branch->id,
                $branch->name,
                $payload['cod_emp'],
                $payload['cod_fil'],
                $payload['apelido'],
            ));

            if (! $dryRun) {
                $branch->update($payload);
            }

            $updated++;
        }

        $this->info("Concluído: {$updated} vinculadas, {$skipped} sem match.");

        return self::SUCCESS;
    }

    /** @return array{cod_emp:int,cod_fil:int,apelido:string}|null */
    private function resolvePair(Branch $branch): ?array
    {
        $name = mb_strtoupper($branch->name);
        $code = trim((string) ($branch->code ?? ''));

        if ($code === 'MATRIZ' || $name === 'MATRIZ') {
            return [
                'cod_emp' => 2,
                'cod_fil' => 1,
                'apelido' => '5 ESTRELAS MATRIZ',
            ];
        }

        if (str_contains($name, 'SISTEMA DE SEGURAN') && (str_contains($name, 'FILIAL') || str_contains($name, 'MATRIZ GERENCIAL'))) {
            return [
                'cod_emp' => 2,
                'cod_fil' => is_numeric($code) ? (int) $code : 1,
                'apelido' => $this->regionalApelido($branch->name, '5 ESTRELAS'),
            ];
        }

        if (str_contains($name, 'APOIO ADM') || str_contains($name, 'SERVICOS DE APOIO') || str_contains($name, 'SERVIÇOS DE APOIO')) {
            return [
                'cod_emp' => 3,
                'cod_fil' => is_numeric($code) ? (int) $code : 1,
                'apelido' => str_contains($name, 'GERENCIAL')
                    ? 'SERV APOIO GERENCIAL'
                    : (Filial::apelidoEmpresa(3) ?? 'SERV APOIO'),
            ];
        }

        if (str_contains($name, 'SS SERVICOS') || str_contains($name, 'MANUTENCOES DE LIMPEZA') || str_contains($name, 'MANUTENÇÕES DE LIMPEZA')) {
            return [
                'cod_emp' => 8,
                'cod_fil' => 1,
                'apelido' => Filial::apelidoEmpresa(8) ?? 'SS SRV',
            ];
        }

        $filial = $branch->resolveComercialFilial();
        if ($filial === null) {
            return null;
        }

        return [
            'cod_emp' => (int) $filial->cod_emp,
            'cod_fil' => 1,
            'apelido' => filled($branch->apelido)
                ? trim($branch->apelido)
                : ($filial->apelido ?: $filial->fantasia ?: $filial->nome),
        ];
    }

    private function regionalApelido(string $branchName, string $empresaApelido): string
    {
        if (preg_match('/FILIAL\s+([A-ZÀ-Ú]{2,})\b/iu', $branchName, $match)) {
            return trim($empresaApelido.' '.mb_strtoupper($match[1]));
        }

        if (preg_match('/\bMATRIZ\s+GERENCIAL\b/iu', $branchName)) {
            return trim($empresaApelido.' MATRIZ');
        }

        return $empresaApelido;
    }
}
