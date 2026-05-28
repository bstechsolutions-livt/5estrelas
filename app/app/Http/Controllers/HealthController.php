<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'reverb' => $this->checkReverb(),
        ];

        $allOk = collect($checks)->every(fn ($c) => $c['ok'] === true);

        return response()->json([
            'status' => $allOk ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'env' => config('app.env'),
            'version' => config('app.version', '1.0.0'),
            'time' => now()->toIso8601String(),
            'checks' => $checks,
        ], $allOk ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->select('select 1');
            return [
                'ok' => true,
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health-check-' . uniqid();
            cache()->put($key, 'ping', 10);
            $val = cache()->get($key);
            cache()->forget($key);
            return ['ok' => $val === 'ping'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk(config('filesystems.default'));
            $disk->files();
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkReverb(): array
    {
        try {
            $host = config('reverb.servers.reverb.hostname', '127.0.0.1');
            $port = (int) config('reverb.servers.reverb.port', 8080);
            $fp = @fsockopen($host, $port, $errno, $errstr, 1.0);
            if ($fp) {
                fclose($fp);
                return ['ok' => true, 'host' => "{$host}:{$port}"];
            }
            return ['ok' => false, 'error' => $errstr ?: 'connection refused'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
