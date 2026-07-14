<?php

namespace App\Http\Controllers;

use App\Models\PayableDocument;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PresidencyDeskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $workflow = app(ApprovalWorkflowService::class);

        $payables = $workflow->presidencyDeskPayables($user);

        return Inertia::render('Approvals/PresidencyDesk', [
            'payables' => $payables,
            'pendingCount' => $payables->count(),
            'docTypeLabels' => PayableDocument::TYPES,
        ]);
    }
}
