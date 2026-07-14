<?php

namespace App\Console\Commands;

use App\Models\ApprovalStep;
use App\Models\Payable;
use App\Services\ApprovalWorkflowService;
use Illuminate\Console\Command;

class DedupePayableApprovalSteps extends Command
{
    protected $signature = 'payables:dedupe-approval-steps
        {--dry-run : Apenas lista títulos afetados}
        {--execute : Remove etapas duplicadas}
        {--id= : ID específico do título}';

    protected $description = 'Remove etapas consecutivas com o mesmo aprovador em títulos em aprovação';

    public function handle(ApprovalWorkflowService $workflow): int
    {
        $execute = (bool) $this->option('execute');
        if ($this->option('dry-run')) {
            $execute = false;
        }

        if (! $execute && ! $this->option('dry-run')) {
            $this->info('Modo DRY-RUN (use --execute para aplicar).');
        }

        $query = Payable::query()
            ->where('status', 'aguardando_aprovacao')
            ->whereHas('approvalSteps')
            ->with(['approvalSteps' => fn ($q) => $q->orderBy('order')->with('assignee:id,name')]);

        if ($id = $this->option('id')) {
            $query->whereKey((int) $id);
        }

        $payables = $query->get()->filter(fn (Payable $p) => $workflow->hasConsecutiveDuplicateAssignees($p));

        if ($payables->isEmpty()) {
            $this->info('Nenhum título com etapas duplicadas.');

            return self::SUCCESS;
        }

        $fixed = 0;
        $removedTotal = 0;

        foreach ($payables as $payable) {
            $before = $payable->approvalSteps
                ->map(fn (ApprovalStep $s) => sprintf(
                    '%d:%s=%s',
                    $s->order,
                    $s->level_name,
                    $s->assignee?->name ?? '—',
                ))
                ->implode(' | ');

            if (! $execute) {
                $this->line("#{$payable->id} {$payable->title_number}: {$before}");
                $fixed++;

                continue;
            }

            $result = $workflow->dedupeConsecutiveAssigneeSteps($payable);
            $deleted = (int) ($result['deleted'] ?? 0);

            if ($deleted === 0) {
                continue;
            }

            $after = ApprovalStep::where('payable_id', $payable->id)
                ->orderBy('order')
                ->with('assignee:id,name')
                ->get()
                ->map(fn (ApprovalStep $s) => sprintf(
                    '%d:%s=%s',
                    $s->order,
                    $s->level_name,
                    $s->assignee?->name ?? '—',
                ))
                ->implode(' | ');

            $this->line("#{$payable->id} {$payable->title_number}: -{$deleted} → {$after}");
            $fixed++;
            $removedTotal += $deleted;
        }

        $this->newLine();
        $mode = $execute ? 'Corrigidos' : 'Encontrados';
        $this->info("{$mode}: {$fixed} título(s)" . ($execute ? ", {$removedTotal} etapa(s) removida(s)" : '') . '.');

        return self::SUCCESS;
    }
}
