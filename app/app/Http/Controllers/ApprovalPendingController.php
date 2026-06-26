<?php

namespace App\Http\Controllers;

use App\Models\ApprovalStep;
use App\Models\Payable;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApprovalPendingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $workflow = app(ApprovalWorkflowService::class);

        // Títulos onde o usuário é o aprovador do step pendente atual
        $pendingPayables = $workflow->myPendingApprovals($user);

        // Contagem pra badge no menu
        $pendingCount = $pendingPayables->count();

        return Inertia::render('Approvals/Pending', [
            'payables' => $pendingPayables,
            'pendingCount' => $pendingCount,
        ]);
    }
}
