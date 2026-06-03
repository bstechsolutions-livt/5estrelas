<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\PayableDocument;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PayableController extends Controller
{
    public function index(Request $request)
    {
        $query = Payable::query()
            ->with(['branch:id,name', 'preparer:id,name'])
            ->orderByDesc('due_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('supplier_name', 'ilike', "%{$s}%")
                    ->orWhere('title_number', 'ilike', "%{$s}%")
                    ->orWhere('description', 'ilike', "%{$s}%");
            });
        }
        if ($request->filled('due_from')) {
            $query->where('due_date', '>=', $request->due_from);
        }
        if ($request->filled('due_to')) {
            $query->where('due_date', '<=', $request->due_to);
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $payables = $query->paginate(20)->withQueryString();

        if ($request->wantsJson() || $request->header('X-Json-Only') === '1') {
            return response()->json($payables);
        }

        // Totais por status
        $totals = Payable::query()
            ->selectRaw("status, count(*) as count, coalesce(sum(amount), 0) as total")
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return Inertia::render('Payables/Index', [
            'payables' => $payables,
            'totals' => $totals,
            'filters' => $request->only(['status', 'search', 'due_from', 'due_to', 'branch_id']),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statusOptions' => Payable::STATUS_LABELS,
        ]);
    }

    public function show(int $id)
    {
        $payable = Payable::with([
            'branch:id,name',
            'preparer:id,name',
            'approver:id,name',
            'documents.uploader:id,name',
            'comments.user:id,name,avatar_path',
        ])->findOrFail($id);

        return Inertia::render('Payables/Show', [
            'payable' => $payable,
            'statusLabels' => Payable::STATUS_LABELS,
            'statusColors' => Payable::STATUS_COLORS,
        ]);
    }

    public function addComment(Request $request, int $id)
    {
        $payable = Payable::findOrFail($id);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment = PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'type' => 'comment',
        ]);

        // Se estava pendente, marca como em preparação
        if ($payable->status === 'pendente') {
            $payable->update([
                'status' => 'em_preparacao',
                'prepared_by' => $request->user()->id,
            ]);
        }

        return back();
    }

    public function addDocument(Request $request, int $id)
    {
        $payable = Payable::findOrFail($id);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB
        ]);

        $file = $request->file('file');
        $path = $file->store('payables/docs', 'public');

        PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $request->user()->id,
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        // Se estava pendente, marca como em preparação
        if ($payable->status === 'pendente') {
            $payable->update([
                'status' => 'em_preparacao',
                'prepared_by' => $request->user()->id,
            ]);
        }

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $request->user()->id,
            'body' => "Anexou documento: {$file->getClientOriginalName()}",
            'type' => 'status_change',
        ]);

        return back();
    }

    public function sendForApproval(Request $request, int $id)
    {
        $payable = Payable::findOrFail($id);

        if (!in_array($payable->status, ['pendente', 'em_preparacao', 'reprovado'])) {
            return back()->with('error', 'Este título não pode ser enviado para aprovação.');
        }

        $payable->update([
            'status' => 'aguardando_aprovacao',
            'prepared_by' => $payable->prepared_by ?? $request->user()->id,
            'sent_for_approval_at' => now(),
        ]);

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $request->user()->id,
            'body' => 'Enviou para aprovação',
            'type' => 'status_change',
        ]);

        AuditLogger::log(
            event: 'contas_pagar.enviado_aprovacao',
            module: 'financeiro.contas_pagar',
            description: "Título {$payable->title_number} enviado para aprovação (R$ {$payable->amount})",
            auditable: $payable,
        );

        return back()->with('success', 'Enviado para aprovação.');
    }

    public function approve(Request $request, int $id)
    {
        $payable = Payable::findOrFail($id);

        if ($payable->status !== 'aguardando_aprovacao') {
            return back()->with('error', 'Este título não está aguardando aprovação.');
        }

        $payable->update([
            'status' => 'aprovado',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $request->user()->id,
            'body' => $request->input('comment', 'Aprovado'),
            'type' => 'approval',
        ]);

        AuditLogger::log(
            event: 'contas_pagar.aprovado',
            module: 'financeiro.contas_pagar',
            description: "Título {$payable->title_number} aprovado (R$ {$payable->amount})",
            auditable: $payable,
        );

        return back()->with('success', 'Título aprovado.');
    }

    public function reject(Request $request, int $id)
    {
        $payable = Payable::findOrFail($id);

        if ($payable->status !== 'aguardando_aprovacao') {
            return back()->with('error', 'Este título não está aguardando aprovação.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $payable->update([
            'status' => 'reprovado',
            'approved_by' => $request->user()->id,
            'rejection_reason' => $data['reason'],
        ]);

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $request->user()->id,
            'body' => "Reprovado: {$data['reason']}",
            'type' => 'rejection',
        ]);

        AuditLogger::log(
            event: 'contas_pagar.reprovado',
            module: 'financeiro.contas_pagar',
            description: "Título {$payable->title_number} reprovado: {$data['reason']}",
            auditable: $payable,
        );

        return back()->with('success', 'Título reprovado.');
    }

    public function removeDocument(int $payableId, int $docId)
    {
        $doc = PayableDocument::where('payable_id', $payableId)->findOrFail($docId);
        Storage::disk('public')->delete($doc->path);
        $doc->delete();

        return back();
    }
}
