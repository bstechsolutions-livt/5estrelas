<?php

namespace App\Http\Controllers;

use App\Models\PayableDocument;
use App\Services\ApprovalWorkflowService;
use App\Support\FilterDate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PresidencyDeskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $workflow = app(ApprovalWorkflowService::class);

        $dueFrom = FilterDate::parse($request->input('due_from'));
        $dueTo = FilterDate::parse($request->input('due_to'));

        $payables = $workflow->presidencyDeskPayables($user, $dueFrom, $dueTo);

        return Inertia::render('Approvals/PresidencyDesk', [
            'payables' => $payables,
            'pendingCount' => $payables->count(),
            'filters' => [
                'due_from' => $dueFrom,
                'due_to' => $dueTo,
            ],
            'docTypeLabels' => PayableDocument::TYPES,
        ]);
    }
}
