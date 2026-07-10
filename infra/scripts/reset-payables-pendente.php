<?php

/**
 * Reseta TODOS os títulos para pendente limpo (sem borderô, sem fluxo de aprovação).
 *
 * Uso:
 *   php infra/scripts/reset-payables-pendente.php --all
 *   php infra/scripts/reset-payables-pendente.php [id,id,...]   (apenas IDs informados)
 */

require dirname(__DIR__, 2) . '/app/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ApprovalStep;
use App\Models\Bordero;
use App\Models\Payable;
use Illuminate\Support\Facades\DB;

$all = in_array('--all', $argv, true);
$ids = array_filter(array_map('intval', array_values(array_filter($argv, fn ($a) => $a !== '--all'))));

if (! $all && ! $ids) {
    echo "Informe --all para resetar 100% dos títulos, ou passe IDs específicos.\n";
    exit(1);
}

$query = Payable::query();
if (! $all) {
    $query->whereIn('id', $ids);
}

$total = (clone $query)->count();
if ($total === 0) {
    echo "Nenhum título para resetar.\n";
    exit(0);
}

echo $all
    ? "Resetando TODOS os {$total} título(s) para pendente...\n"
    : "Resetando {$total} título(s)...\n";

DB::transaction(function () use ($all, $ids) {
    $payableQuery = Payable::query();
    if (! $all) {
        $payableQuery->whereIn('id', $ids);
    }
    $payableIds = $payableQuery->pluck('id');

    ApprovalStep::whereIn('payable_id', $payableIds)->delete();

    $payableQuery->update([
        'status' => 'pendente',
        'bordero_id' => null,
        'prepared_by' => null,
        'approved_by' => null,
        'sent_for_approval_at' => null,
        'approved_at' => null,
        'rejection_reason' => null,
        'department_id' => null,
        'paid_at' => null,
        'payment_method' => null,
        'paid_by' => null,
        'conciliated_at' => null,
        'conciliated_by' => null,
        'conciliation_notes' => null,
        'divergence_reason' => null,
    ]);

    Bordero::query()->delete();
});

$pendentes = Payable::where('status', 'pendente')->count();
$borderos = Bordero::count();

echo "Concluído.\n";
echo "  Títulos pendentes: {$pendentes}\n";
echo "  Borderôs restantes: {$borderos}\n";
