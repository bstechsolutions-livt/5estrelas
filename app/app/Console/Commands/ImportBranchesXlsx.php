<?php

namespace App\Console\Commands;

use App\Models\Branch;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportBranchesXlsx extends Command
{
    protected $signature = 'import:branches-xlsx {file : Caminho do arquivo .xlsx}';
    protected $description = 'Importa filiais a partir de planilha Excel.';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("Arquivo não encontrado: {$file}");
            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $imported = 0;
        $updated = 0;

        foreach ($rows as $i => $row) {
            if ($i === 1) continue; // header

            $name = trim($row['A'] ?? '');
            $cnpj = trim((string) ($row['B'] ?? ''));
            $code = trim((string) ($row['C'] ?? ''));

            if (empty($name)) continue;

            // Normaliza CNPJ (remove pontuação)
            $cnpjClean = preg_replace('/\D/', '', $cnpj);

            $branch = Branch::where('cnpj', $cnpjClean)
                ->orWhere('code', $code)
                ->first();

            if ($branch) {
                $branch->update(['name' => $name, 'cnpj' => $cnpjClean, 'code' => $code]);
                $this->line("  Atualizado: {$name}");
                $updated++;
            } else {
                Branch::create(['name' => $name, 'cnpj' => $cnpjClean, 'code' => $code, 'is_active' => true]);
                $this->info("  Criado: {$name}");
                $imported++;
            }
        }

        $this->newLine();
        $this->info("✅ Importação concluída! Criadas: {$imported} | Atualizadas: {$updated}");

        return self::SUCCESS;
    }
}
