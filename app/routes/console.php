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
        // TTL do mutex no Redis: se o sync passar disso, outro ciclo sobe em paralelo (zumbi).
        // Sync real leva 8–15+ min; 120 min cobre travamento SOAP sem empilhar.
        ->withoutOverlapping(120)
        // Em background: o loop supervisor (schedule:run a cada 60s) não pode ficar
        // bloqueado 15+ min. Se o PHP do sync travar no enrich pós-SOAP, o agendador
        // inteiro parava. Anti-empilhamento = Cache::lock + em_andamento no service.
        ->runInBackground();

    // Lançador Senior (UsuGer) → senior_cod_usu → departamento do usuário intranet.
    // Teto alto + bulk por empresa no service; prioriza títulos sem UsuGer mais novos.
    $enrichMax = max(1, (int) config('senior.enrich_launcher_max_lookups', 400));
    Schedule::command("senior:enrich-payable-launchers --max={$enrichMax} --scheduled")
        ->cron($cron)
        ->withoutOverlapping(120)
        ->runInBackground();

    // Fornecedores faltantes / placeholders “Fornecedor N” — acompanha o ciclo do CP.
    Schedule::command('senior:sync-fornecedores --scheduled')
        ->cron($cron)
        ->withoutOverlapping(120)
        ->runInBackground();

    // Sync de filiais/empresas (cad_filial) — muda pouco, roda 1x/dia de madrugada.
    Schedule::command('senior:sync-filiais --scheduled')
        ->dailyAt('03:20')
        ->withoutOverlapping()
        ->runInBackground();

    // CR a cada hora (varredura é pesada; não disputa o loop de 5 min do CP).
    Schedule::command('senior:sync-receivables --scheduled')
        ->hourly()
        ->withoutOverlapping(90)
        ->runInBackground();

    Schedule::command('senior:sync-chart-of-accounts')
        ->dailyAt('03:45')
        ->withoutOverlapping()
        ->runInBackground();
}

// Borderôs automáticos — regras ativas rodam diariamente às 6h.
Schedule::command('borderos:auto-generate --scheduled')
    ->dailyAt('06:00')
    ->withoutOverlapping();
