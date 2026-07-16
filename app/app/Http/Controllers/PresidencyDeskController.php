<?php

namespace App\Http\Controllers;

use App\Models\PayableDocument;
use App\Services\ApprovalWorkflowService;
use App\Support\FilterDate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PresidencyDeskController extends Controller
{
    /**
     * Default: "A vencer / Esta semana" (hoje → domingo da semana corrente).
     * Limpar filtro: ?all=1
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $workflow = app(ApprovalWorkflowService::class);

        $wantsAll = $request->boolean('all');
        $hasDueQuery = $request->query->has('due_from') || $request->query->has('due_to');

        if (! $wantsAll && ! $hasDueQuery) {
            [$dueFrom, $dueTo] = $this->defaultDueThisWeekRange();
        } else {
            $dueFrom = FilterDate::parse($request->input('due_from'));
            $dueTo = FilterDate::parse($request->input('due_to'));
        }

        $payables = $workflow->presidencyDeskPayables($user, $dueFrom, $dueTo);

        return Inertia::render('Approvals/PresidencyDesk', [
            'payables' => $payables,
            'pendingCount' => $payables->count(),
            'filters' => [
                'due_from' => $dueFrom,
                'due_to' => $dueTo,
                'all' => $wantsAll,
            ],
            'docTypeLabels' => PayableDocument::TYPES,
        ]);
    }

    /**
     * Espelha o preset JS `av_semana`: de hoje até o domingo (semana iniciando na segunda).
     *
     * @return array{0: string, 1: string}
     */
    private function defaultDueThisWeekRange(): array
    {
        $today = Carbon::today();
        $dayOfWeekIso = (int) $today->dayOfWeekIso; // 1=seg … 7=dom
        $sunday = $today->copy()->addDays(7 - $dayOfWeekIso);

        return [$today->toDateString(), $sunday->toDateString()];
    }
}
