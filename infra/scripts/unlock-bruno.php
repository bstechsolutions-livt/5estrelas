<?php
require '/var/www/5estrelas/app/vendor/autoload.php';
$app = require '/var/www/5estrelas/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = App\Models\User::where('email', 'bruno@bstechsolutions.com')->first();
$u->failed_login_attempts = 0;
$u->last_failed_login_at = null;
$u->locked_until = null;
$u->saveQuietly();

// Limpa rate limit do cache
Illuminate\Support\Facades\Cache::flush();

echo "Desbloqueado + cache limpo.\n";
