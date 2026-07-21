<?php

namespace App\Http\Controllers;

use App\Models\Payable;
use App\Models\PayableSyncRun;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel read-only do sync Contas a Pagar (Senior) — status, contagens e falhas 503.
 */
class PayableSyncMonitorController extends Controller
{
    public function index(Request $request): Response
    {
        $running = PayableSyncRun::query()
            ->where('status', PayableSyncRun::STATUS_RUNNING)
            ->whereNull('finished_at')
            ->orderByDesc('started_at')
            ->first();

        $runs = PayableSyncRun::query()
            ->orderByDesc('started_at')
            ->limit(40)
            ->get()
            ->map(fn (PayableSyncRun $run) => $this->serializeRun($run));

        $since24h = now()->subDay();
        $failedRecent = PayableSyncRun::query()
            ->where('status', PayableSyncRun::STATUS_FAILED)
            ->where('started_at', '>=', $since24h)
            ->count();

        $failed503Recent = PayableSyncRun::query()
            ->where('status', PayableSyncRun::STATUS_FAILED)
            ->where('started_at', '>=', $since24h)
            ->where(function ($q) {
                // LIKE case-insensitive via lower() — compatível com pgsql e sqlite.
                $q->whereRaw('LOWER(error_message) LIKE ?', ['%503%'])
                    ->orWhereRaw('LOWER(error_message) LIKE ?', ['%unavailable%'])
                    ->orWhereRaw('LOWER(error_message) LIKE ?', ['%timed out%'])
                    ->orWhereRaw('LOWER(error_message) LIKE ?', ['%timeout%']);
            })
            ->count();

        return Inertia::render('Financeiro/SyncMonitor/Index', [
            'config' => [
                'enabled' => (bool) config('senior.enabled', false),
                'environment' => (string) config('senior.environment', 'PRD'),
                'sync_interval_minutes' => (int) config('senior.sync_interval_minutes', 10),
                'sync_http_timeout' => (int) config('senior.sync_http_timeout', config('senior.cp_timeout_response', 60)),
                'cp_strategy' => (string) config('senior.cp_strategy', 'bulk'),
                'cod_emps' => config('senior.cod_emps', []),
            ],
            'current_run' => $running ? $this->serializeRun($running) : null,
            'runs' => $runs,
            'stats' => [
                'failed_24h' => $failedRecent,
                'failed_503_or_timeout_24h' => $failed503Recent,
            ],
            'charts_12h' => $this->hourlyChartsLast12h(),
            'by_empresa' => $this->cheapEmpresaBreakdown(),
            'next_steps' => [
                'Breakdown por departamento na próxima versão (quando o volume e índices permitirem sem pesar a tela).',
            ],
        ]);
    }

    /**
     * Agrega payable_sync_runs das últimas 12h por hora (America/Sao_Paulo).
     * Volume típico (~5 min) cabe em PHP sem SQL por driver.
     *
     * @return array{
     *   labels: list<string>,
     *   sucesso: list<int>,
     *   falha: list<int>,
     *   ignorado: list<int>,
     *   inserted: list<int>,
     *   updated: list<int>,
     *   missing: list<int>
     * }
     */
    private function hourlyChartsLast12h(): array
    {
        $tz = 'America/Sao_Paulo';
        $nowHour = Carbon::now($tz)->startOfHour();
        $since = $nowHour->copy()->subHours(11);

        $buckets = [];
        for ($i = 0; $i < 12; $i++) {
            $hour = $since->copy()->addHours($i);
            $key = $hour->format('Y-m-d H:00');
            $buckets[$key] = [
                'label' => $hour->format('H:i'),
                'sucesso' => 0,
                'falha' => 0,
                'ignorado' => 0,
                'inserted' => 0,
                'updated' => 0,
                'missing' => 0,
            ];
        }

        $runs = PayableSyncRun::query()
            ->where('started_at', '>=', $since)
            ->whereNotNull('started_at')
            ->get(['status', 'started_at', 'inserted_count', 'updated_count', 'missing_count']);

        foreach ($runs as $run) {
            $key = $run->started_at->copy()->timezone($tz)->format('Y-m-d H:00');
            if (! isset($buckets[$key])) {
                continue;
            }

            match ($run->status) {
                PayableSyncRun::STATUS_SUCCESS => $buckets[$key]['sucesso']++,
                PayableSyncRun::STATUS_FAILED => $buckets[$key]['falha']++,
                PayableSyncRun::STATUS_SKIPPED => $buckets[$key]['ignorado']++,
                default => null,
            };

            $buckets[$key]['inserted'] += (int) $run->inserted_count;
            $buckets[$key]['updated'] += (int) $run->updated_count;
            $buckets[$key]['missing'] += (int) $run->missing_count;
        }

        $rows = array_values($buckets);

        return [
            'labels' => array_column($rows, 'label'),
            'sucesso' => array_column($rows, 'sucesso'),
            'falha' => array_column($rows, 'falha'),
            'ignorado' => array_column($rows, 'ignorado'),
            'inserted' => array_column($rows, 'inserted'),
            'updated' => array_column($rows, 'updated'),
            'missing' => array_column($rows, 'missing'),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeRun(PayableSyncRun $run): array
    {
        $progress = is_array($run->progress) ? $run->progress : null;

        return [
            'id' => $run->id,
            'environment' => $run->environment,
            'mode' => $run->mode,
            'trigger' => $run->trigger,
            'status' => $run->status,
            'started_at' => optional($run->started_at)?->toIso8601String(),
            'finished_at' => optional($run->finished_at)?->toIso8601String(),
            'inserted_count' => (int) $run->inserted_count,
            'updated_count' => (int) $run->updated_count,
            'missing_count' => (int) $run->missing_count,
            'window_start' => optional($run->window_start)?->toDateString(),
            'window_end' => optional($run->window_end)?->toDateString(),
            'error_message' => $run->error_message,
            'progress' => $progress,
            'duration_seconds' => $run->started_at && $run->finished_at
                ? $run->started_at->diffInSeconds($run->finished_at)
                : ($run->started_at && $run->status === PayableSyncRun::STATUS_RUNNING
                    ? $run->started_at->diffInSeconds(now())
                    : null),
        ];
    }

    /**
     * Contagem barata de títulos Senior ativos por empresa (se a coluna existir).
     *
     * @return list<array{cod_emp: int|null, total: int}>|null
     */
    private function cheapEmpresaBreakdown(): ?array
    {
        if (! Schema::hasColumn('payables', 'cod_emp')) {
            return null;
        }

        return Payable::query()
            ->whereNotNull('senior_id')
            ->whereNull('senior_missing_at')
            ->selectRaw('cod_emp, COUNT(*) as total')
            ->groupBy('cod_emp')
            ->orderBy('cod_emp')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'cod_emp' => $row->cod_emp !== null ? (int) $row->cod_emp : null,
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
