<?php

namespace App\Console\Commands;

use App\Models\ApprovalStep;
use App\Models\Payable;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use Illuminate\Console\Command;

class BackfillPayableApprovalSteps extends Command
{
    protected $signature = 'payables:backfill-approval-steps
        {--dry-run : Apenas simula, sem gravar}
        {--id= : ID específico do título}';

    protected $description = 'Cria etapas de aprovação para títulos em aguardando_aprovacao sem fluxo';

    public function handle(ApprovalWorkflowService $workflow): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $id = $this->option('id');

        $query = Payable::query()
            ->where('status', 'aguardando_aprovacao')
            ->whereDoesntHave('approvalSteps')
            ->with('preparer:id,name,department_id');

        if ($id) {
            $query->whereKey((int) $id);
        }

        $payables = $query->get();
        if ($payables->isEmpty()) {
            $this->info('Nenhum título pendente de backfill.');

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($payables as $payable) {
            $sender = $payable->preparer;
            if (! $sender && $payable->prepared_by) {
                $sender = User::find($payable->prepared_by);
            }

            if ($dryRun) {
                if (! $sender) {
                    $this->warn("#{$payable->id} {$payable->title_number}: sem preparador");
                    $failed++;

                    continue;
                }

                $preview = $workflow->buildPreviewStepsForPayable($payable);
                if (! ($preview['ok'] ?? false)) {
                    $this->warn("#{$payable->id} {$payable->title_number}: " . implode('; ', $preview['errors'] ?? ['erro']));
                    $failed++;

                    continue;
                }
                $first = $preview['steps'][0] ?? null;
                $this->line("#{$payable->id} {$payable->title_number}: {$first['assignee_name']} ({$first['role_label']})");
                $created++;

                continue;
            }

            $result = $workflow->ensureWorkflowSteps($payable);
            if (! ($result['ok'] ?? false)) {
                $this->warn("#{$payable->id} {$payable->title_number}: " . ($result['error'] ?? 'erro'));
                $failed++;

                continue;
            }

            if ($result['skipped'] ?? false) {
                $skipped++;

                continue;
            }

            $step = ApprovalStep::where('payable_id', $payable->id)
                ->where('status', 'pendente')
                ->orderBy('order')
                ->with('assignee:id,name')
                ->first();

            $label = $step?->assignee?->name ?? $step?->role_label ?? '—';
            $this->line("#{$payable->id} {$payable->title_number}: {$label}");
            $created++;
        }

        $this->newLine();
        $this->info("Concluído: {$created} processados, {$skipped} ignorados, {$failed} falhas.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
