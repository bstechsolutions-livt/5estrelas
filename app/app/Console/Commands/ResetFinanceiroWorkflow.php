<?php

namespace App\Console\Commands;

use App\Models\ApprovalStep;
use App\Models\Bordero;
use App\Models\Payable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetFinanceiroWorkflow extends Command
{
    protected $signature = 'financeiro:reset-workflow
        {--all : Reseta todos os títulos para pendente e remove borderôs}
        {--ids= : IDs específicos separados por vírgula}
        {--force : Executa sem confirmação (produção/CI)}';

    protected $description = 'Reseta workflow de CP (aprovados, borderôs, pagos) mantendo os títulos importados';

    public function handle(): int
    {
        $all = (bool) $this->option('all');
        $ids = array_filter(array_map('intval', explode(',', (string) $this->option('ids'))));

        if (! $all && $ids === []) {
            $this->error('Informe --all ou --ids=1,2,3');

            return self::FAILURE;
        }

        $query = Payable::query();
        if (! $all) {
            $query->whereIn('id', $ids);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->warn('Nenhum título para resetar.');

            return self::SUCCESS;
        }

        $before = [
            'titulos' => $total,
            'aprovados' => (clone $query)->where('status', 'aprovado')->count(),
            'aguardando' => (clone $query)->where('status', 'aguardando_aprovacao')->count(),
            'pagos' => (clone $query)->where('status', 'pago')->count(),
            'com_bordero' => (clone $query)->whereNotNull('bordero_id')->count(),
            'borderos' => Bordero::count(),
            'etapas' => ApprovalStep::whereIn('payable_id', (clone $query)->pluck('id'))->count(),
        ];

        $this->table(
            ['Antes', 'Qtd'],
            collect($before)->map(fn ($v, $k) => [$k, $v])->values()->all()
        );

        if (! $this->option('force') && ! $this->confirm($all
            ? "Resetar TODOS os {$total} título(s) para pendente e apagar {$before['borderos']} borderô(s)?"
            : "Resetar {$total} título(s) selecionado(s)?", true)) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

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

            if ($all) {
                Bordero::query()->delete();
            }
        });

        $after = [
            'pendentes' => Payable::where('status', 'pendente')->count(),
            'aprovados' => Payable::where('status', 'aprovado')->count(),
            'borderos' => Bordero::count(),
            'etapas' => ApprovalStep::count(),
        ];

        $this->newLine();
        $this->info('Concluído.');
        $this->table(
            ['Depois', 'Qtd'],
            collect($after)->map(fn ($v, $k) => [$k, $v])->values()->all()
        );

        return self::SUCCESS;
    }
}
