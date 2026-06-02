<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportUsersXlsx extends Command
{
    protected $signature = 'import:users-xlsx {file : Caminho do arquivo .xlsx}';
    protected $description = 'Importa usuários e departamentos a partir de planilha Excel.';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("Arquivo não encontrado: {$file}");
            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getSheetByName('Todos os Usuários');

        if (!$sheet) {
            $this->error("Aba 'Todos os Usuários' não encontrada.");
            return self::FAILURE;
        }

        $rows = $sheet->toArray(null, true, true, true);
        $header = null;
        $imported = 0;
        $skipped = 0;

        $wildcardPerm = Permission::where('key', '*')->first();

        foreach ($rows as $i => $row) {
            if ($i === 1) {
                $header = $row;
                continue;
            }

            $dept = trim($row['A'] ?? '');
            $name = trim($row['B'] ?? '');
            $email = trim($row['C'] ?? '');
            $funcao = trim($row['D'] ?? '');
            // $aprovador = trim($row['E'] ?? ''); // Ignorado conforme decisão

            if (empty($name) || empty($email)) {
                continue;
            }

            // Criar/encontrar departamento
            $department = null;
            if ($dept) {
                $department = Department::firstOrCreate(
                    ['slug' => \Illuminate\Support\Str::slug($dept)],
                    ['name' => $dept, 'is_active' => true]
                );
            }

            // Criar/atualizar usuário
            $user = User::where('email', strtolower($email))->first();

            if ($user) {
                $user->update([
                    'name' => $name,
                    'department_id' => $department?->id,
                ]);
                $this->line("  Atualizado: {$email}");
                $skipped++;
            } else {
                $user = User::create([
                    'name' => $name,
                    'email' => strtolower($email),
                    'password' => Hash::make('5Estrelas@2026'),
                    'is_active' => true,
                    'department_id' => $department?->id,
                ]);
                $this->info("  Criado: {$email}");
                $imported++;
            }

            // Atribuir permissões baseado na função
            if ($wildcardPerm && strtolower($funcao) === 'admin') {
                $user->permissions()->syncWithoutDetaching([$wildcardPerm->id]);
            }
        }

        $deptCount = Department::count();
        $this->newLine();
        $this->info("✅ Importação concluída!");
        $this->info("   Departamentos: {$deptCount}");
        $this->info("   Usuários criados: {$imported}");
        $this->info("   Usuários atualizados: {$skipped}");
        $this->info("   Senha padrão: 5Estrelas@2026");

        return self::SUCCESS;
    }
}
