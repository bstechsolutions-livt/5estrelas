<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BackupController extends Controller
{
    private string $disk = 'backups';

    public function index()
    {
        return Inertia::render('Backups/Index', [
            'backups' => $this->listBackups(),
            'config' => [
                'retention_days' => config('backup.cleanup.default_strategy.keep_all_backups_for_days', 7),
                'schedule' => '03:00 (diário)',
            ],
        ]);
    }

    public function run(Request $request)
    {
        $exitCode = Artisan::call('backup:run', ['--only-db' => true]);
        $output = Artisan::output();

        if ($exitCode === 0) {
            AuditLogger::log(
                event: 'backups.run',
                module: 'backups',
                description: 'Backup do banco executado manualmente',
                metadata: ['output' => trim($output)],
            );

            return back()->with('success', 'Backup concluído com sucesso.');
        }

        AuditLogger::log(
            event: 'backups.run_failed',
            module: 'backups',
            description: 'Falha ao executar backup manual',
            metadata: ['output' => trim($output), 'exit_code' => $exitCode],
        );

        return back()->with('error', 'Falha ao executar backup. Veja os logs.');
    }

    public function download(string $filename)
    {
        $path = $this->resolvePath($filename);
        if (!$path || !Storage::disk($this->disk)->exists($path)) {
            return back()->with('error', 'Arquivo não encontrado.');
        }

        AuditLogger::log(
            event: 'backups.downloaded',
            module: 'backups',
            description: "Backup {$filename} baixado",
        );

        return Storage::disk($this->disk)->download($path);
    }

    public function destroy(string $filename)
    {
        $path = $this->resolvePath($filename);
        if (!$path || !Storage::disk($this->disk)->exists($path)) {
            return back()->with('error', 'Arquivo já não existe.');
        }

        Storage::disk($this->disk)->delete($path);

        AuditLogger::log(
            event: 'backups.deleted',
            module: 'backups',
            description: "Backup {$filename} excluído",
        );

        return back()->with('success', 'Backup excluído.');
    }

    private function listBackups(): array
    {
        $disk = Storage::disk($this->disk);
        $files = $disk->allFiles();

        $list = collect($files)
            ->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->map(function ($path) use ($disk) {
                $size = $disk->size($path);
                $lastModified = $disk->lastModified($path);

                return [
                    'name' => basename($path),
                    'path' => $path,
                    'size' => $size,
                    'size_human' => $this->formatBytes($size),
                    'created_at' => Carbon::createFromTimestamp($lastModified)->toIso8601String(),
                ];
            })
            ->sortByDesc('created_at')
            ->values()
            ->all();

        return $list;
    }

    private function resolvePath(string $filename): ?string
    {
        $disk = Storage::disk($this->disk);
        foreach ($disk->allFiles() as $f) {
            if (basename($f) === $filename) {
                return $f;
            }
        }

        return null;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
