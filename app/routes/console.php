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
