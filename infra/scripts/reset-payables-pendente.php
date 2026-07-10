<?php

/**
 * Reseta títulos para status pendente limpo (sem borderô, sem etapas de aprovação).
 * Uso: php infra/scripts/reset-payables-pendente.php [id,id,...]
 * Sem IDs: reseta todos com status pendente que estão em borderô.
 */

require dirname(__DIR__, 2) . '/app/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ApprovalStep;
use App\Models\Bordero;
use App\Models\Payable;
use Illuminate\Support\Facades\DB;

$ids = array_filter(array_map('intval', array_slice($argv, 1)));

$query = Payable::query();
if ($ids) {
    $query->whereIn('id', $ids);
} else {
    $query->where('status', 'pendente')->whereNotNull('bordero_id');
}

$payables = $query->get();
if ($payables->isEmpty()) {
    echo "Nenhum título para resetar.\n";
    exit(0);
}

$borderoIds = $payables->pluck('bordero_id')->filter()->unique()->values();

DB::transaction(function () use ($payables) {
    foreach ($payables as $payable) {
        ApprovalStep::where('payable_id', $payable->id)->delete();

        $payable->update([
            'status' => 'pendente',
            'bordero_id' => null,
            'prepared_by' => null,
            'approved_by' => null,
            'sent_for_approval_at' => null,
            'approved_at' => null,
            'rejection_reason' => null,
            'department_id' => null,
        ]);

        echo "Reset: #{$payable->id} {$payable->title_number} — {$payable->supplier_name}\n";
    }
});

foreach ($borderoIds as $borderoId) {
    $bordero = Bordero::find($borderoId);
    if (! $bordero) {
        continue;
    }

    $bordero->recalculate();
    if ($bordero->items_count === 0) {
        echo "Borderô removido (vazio): {$bordero->number}\n";
        $bordero->delete();
    } else {
        $bordero->syncStatusFromPayables();
        echo "Borderô atualizado: {$bordero->number} ({$bordero->items_count} títulos)\n";
    }
}

echo "Concluído. {$payables->count()} título(s) em pendente limpo.\n";
