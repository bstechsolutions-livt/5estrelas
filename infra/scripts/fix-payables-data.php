<?php
require '/var/www/5estrelas/app/vendor/autoload.php';
$app = require '/var/www/5estrelas/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = App\Models\User::pluck('id')->toArray();
$reasons = ['Valor acima do orçamento aprovado', 'Documento fiscal inválido', 'Falta comprovante de entrega', 'Duplicidade com título anterior', 'Centro de custo incorreto'];

App\Models\Payable::where('status', 'em_preparacao')->update(['prepared_by' => $users[array_rand($users)]]);

App\Models\Payable::where('status', 'aguardando_aprovacao')->chunk(500, function($items) use ($users) {
    foreach ($items as $p) {
        $p->update(['prepared_by' => $users[array_rand($users)], 'sent_for_approval_at' => now()->subDays(random_int(1,5))]);
    }
});

App\Models\Payable::where('status', 'aprovado')->chunk(500, function($items) use ($users) {
    foreach ($items as $p) {
        $preparer = $users[array_rand($users)];
        $approver = collect($users)->reject(fn($u) => $u === $preparer)->random();
        $p->update(['prepared_by' => $preparer, 'approved_by' => $approver, 'sent_for_approval_at' => now()->subDays(random_int(3,10)), 'approved_at' => now()->subDays(random_int(1,3))]);
    }
});

App\Models\Payable::where('status', 'reprovado')->chunk(500, function($items) use ($users, $reasons) {
    foreach ($items as $p) {
        $preparer = $users[array_rand($users)];
        $approver = collect($users)->reject(fn($u) => $u === $preparer)->random();
        $p->update(['prepared_by' => $preparer, 'approved_by' => $approver, 'sent_for_approval_at' => now()->subDays(random_int(3,10)), 'approved_at' => now()->subDays(random_int(1,3)), 'rejection_reason' => $reasons[array_rand($reasons)]]);
    }
});

App\Models\Payable::where('status', 'pago')->chunk(500, function($items) use ($users) {
    foreach ($items as $p) {
        $preparer = $users[array_rand($users)];
        $approver = collect($users)->reject(fn($u) => $u === $preparer)->random();
        $p->update(['prepared_by' => $preparer, 'approved_by' => $approver, 'sent_for_approval_at' => now()->subDays(random_int(10,20)), 'approved_at' => now()->subDays(random_int(5,10))]);
    }
});

echo "Dados corrigidos. Total: " . App\Models\Payable::count() . "\n";
