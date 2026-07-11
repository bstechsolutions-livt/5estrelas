<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup diário do banco às 3h da manhã + limpeza dos antigos
Schedule::command('backup:clean')->daily()->at('02:55');
Schedule::command('backup:run --only-db')->daily()->at('03:00');

// ── Sync Contas a Pagar (Senior) — spec senior-contas-pagar-sync ──
// Só agenda quando a integração está habilitada (evita execuções ignoradas em série
// localmente / com a whitelist pendente). O comando manual continua disponível.
if (config('senior.enabled', false)) {
    $interval = (int) config('senior.sync_interval_minutes', 5);
    if ($interval < 1 || $interval > 1440) {
        // req 6.3: intervalo inválido → mantém o padrão de 5 minutos e registra erro.
        \Illuminate\Support\Facades\Log::error("[senior-cp] intervalo de sync inválido ({$interval}); usando 5 minutos.");
        $interval = 5;
    }
    $cron = $interval < 60
        ? "*/{$interval} * * * *"
        : '0 */' . max(1, intdiv($interval, 60)) . ' * * *';

    Schedule::command('senior:sync-payables --scheduled')
        ->cron($cron)
        ->withoutOverlapping(); // req 6.4: sem execução concorrente

    // Sync de filiais/empresas (cad_filial) — muda pouco, roda 1x/dia de madrugada.
    Schedule::command('senior:sync-filiais --scheduled')
        ->dailyAt('03:20')
        ->withoutOverlapping();

    // Delta de fornecedores: só os codFor dos títulos sem cache local (a cada sync CP).
    Schedule::command('senior:sync-fornecedores --missing --scheduled')
        ->cron($cron)
        ->withoutOverlapping();

    // Catálogo completo de fornecedores — bootstrap/manutenção noturna.
    Schedule::command('senior:sync-fornecedores --full --scheduled')
        ->dailyAt('03:30')
        ->withoutOverlapping();
}

// Borderôs automáticos — regras ativas rodam diariamente às 6h.
Schedule::command('borderos:auto-generate --scheduled')
    ->dailyAt('06:00')
    ->withoutOverlapping();
