<?php
require '/var/www/5estrelas/app/vendor/autoload.php';
$app = require '/var/www/5estrelas/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = App\Models\User::where('email', 'bruno@bstechsolutions.com')->first();
$u->password = password_hash('123456', PASSWORD_BCRYPT);
$u->save();
echo "Senha alterada em produção.\n";
