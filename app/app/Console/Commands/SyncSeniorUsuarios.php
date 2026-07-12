<?php

namespace App\Console\Commands;

use App\Services\Senior\SeniorException;
use App\Services\Senior\SeniorUsuarioClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncSeniorUsuarios extends Command
{
    protected $signature = 'senior:sync-usuarios
        {--emp= : codEmp único (default: todos de senior.cod_emps)}
        {--fil=1 : codFil na ExportarAbrangencia}';

    protected $description = 'Exporta usuários da Senior (cad_usuario / ExportarAbrangencia) para cache local';

    public function handle(): int
    {
        if (!config('senior.enabled', false)) {
            $this->warn('Senior desabilitado (SENIOR_ENABLED=false). Abortando.');

            return self::FAILURE;
        }

        $client = SeniorUsuarioClient::fromConfig();
        $codFil = (int) $this->option('fil');
        $emps = $this->option('emp') !== null
            ? [(int) $this->option('emp')]
            : config('senior.cod_emps', [2, 3]);

        $all = [];
        foreach ($emps as $codEmp) {
            if ($codEmp <= 0) {
                continue;
            }
            try {
                $rows = $client->exportarAbrangencia($codEmp, $codFil);
            } catch (SeniorException $e) {
                $this->error("emp {$codEmp}: {$e->getMessage()}");

                continue;
            }

            foreach ($rows as $row) {
                $codUsu = (int) ($row['codUsu'] ?? 0);
                if ($codUsu <= 0) {
                    continue;
                }
                $all[$codUsu] = [
                    'codUsu' => $codUsu,
                    'usuPlt' => trim((string) ($row['usuPlt'] ?? '')),
                    'codEmp' => (int) ($row['codEmp'] ?? $codEmp),
                    'codFil' => (int) ($row['codFil'] ?? $codFil),
                ];
            }

            $this->line("emp {$codEmp}: " . count($rows) . ' registros');
        }

        $payload = [
            'synced_at' => now()->toIso8601String(),
            'count' => count($all),
            'usuarios' => array_values($all),
        ];

        Storage::disk('local')->put('senior_usuarios.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Total único: ' . count($all) . ' usuários → storage/app/senior_usuarios.json');
        $this->comment('A API não retorna nomes — use usuPlt para cruzar com e-mail/login ou preencha senior_cod_usu manualmente no cadastro.');

        return self::SUCCESS;
    }
}
