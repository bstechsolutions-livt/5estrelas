<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\PayableDocument;
use App\Services\AuditLogger;
use App\Services\PayableAlcadaService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PayableController extends Controller
{
    public function index(Request $request)
    {
        $query = Payable::query()
            ->with(['branch:id,name', 'preparer:id,name', 'bordero:id,number'])
            ->orderByDesc('due_date');

        // Sempre filtra por um status (default: pendente). Não existe "ver todos".
        $status = $request->input('status') ?: 'pendente';
        $query->where('status', $status);
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
        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', (float) $request->amount_min);
        }
        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', (float) $request->amount_max);
        }

        $payables = $query->paginate($request->input('per_page', 20))->withQueryString();

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
            'filters' => array_merge(
                $request->only(['search', 'due_from', 'due_to', 'branch_id', 'amount_min', 'amount_max']),
                ['status' => $status],
            ),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statusOptions' => Payable::STATUS_LABELS,
        ]);
    }

    public function show(int $id, PayableAlcadaService $alcada)
    {
        $payable = Payable::with([
            'branch:id,name',
            'preparer:id,name',
            'approver:id,name',
            'payer:id,name',
            'conciliator:id,name',
            'documents.uploader:id,name',
            'comments.user:id,name,avatar_path',
        ])->findOrFail($id);

        $user = request()->user();
        $isPagador = $user ? $alcada->isAssigned($user, 'pagador') : false;
        $isConciliador = $user ? $alcada->isAssigned($user, 'conciliador') : false;

        return Inertia::render('Payables/Show', [
            'payable' => $payable,
            'statusLabels' => Payable::STATUS_LABELS,
            'statusColors' => Payable::STATUS_COLORS,
            'canPay' => $isPagador && $payable->status === 'aprovado',
            'paymentMethods' => Payable::PAYMENT_METHODS,
            'pagadorConfigured' => $alcada->hasRole('pagador'),
            'canConciliate' => $isConciliador && $payable->status === 'pago',
            'conciliadorConfigured' => $alcada->hasRole('conciliador'),
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

    /**
     * Registra o pagamento de um título APROVADO (aprovado -> pago).
     * Governado pela ALÇADA: só quem está como `pagador` paga — nem o curinga '*' fura.
     */
    public function pay(Request $request, int $id, PayableAlcadaService $alcada)
    {
        $payable = Payable::findOrFail($id);
        $user = $request->user();

        // Elegibilidade pela alçada (segregação de função). Não checamos permissão aqui.
        if (! $alcada->isAssigned($user, 'pagador')) {
            abort(403, 'Você não está na alçada como pagador deste módulo.');
        }

        $data = $request->validate([
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(Payable::PAYMENT_METHODS))],
            'file' => ['nullable', 'file', 'max:10240'], // 10MB — comprovante opcional
        ]);

        $paid = DB::transaction(function () use ($payable, $user, $data, $request) {
            // Trava o registro para evitar pagamento concorrente (idempotência).
            $fresh = Payable::whereKey($payable->id)->lockForUpdate()->first();

            // Só de 'aprovado'. Um 2º request concorrente encontra 'pago' e cai aqui.
            if ($fresh->status !== 'aprovado') {
                return false;
            }

            $old = $fresh->status;
            $fresh->update([
                'status' => 'pago',
                'paid_at' => $data['paid_at'],
                'payment_method' => $data['payment_method'] ?? null,
                'paid_by' => $user->id,
            ]);

            // Comprovante (opcional) reaproveita a estrutura de documentos do título.
            $docName = null;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = $file->store('payables/docs', 'public');
                PayableDocument::create([
                    'payable_id' => $fresh->id,
                    'uploaded_by' => $user->id,
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
                $docName = $file->getClientOriginalName();
            }

            $dataFmt = Carbon::parse($data['paid_at'])->format('d/m/Y');
            $forma = $data['payment_method'] ?? null;

            PayableComment::create([
                'payable_id' => $fresh->id,
                'user_id' => $user->id,
                'body' => "Pagamento registrado em {$dataFmt}"
                    . ($forma ? " · {$forma}" : '')
                    . ($docName ? " · Comprovante: {$docName}" : ''),
                'type' => 'payment',
            ]);

            AuditLogger::log(
                event: 'contas_pagar.pago',
                module: 'financeiro.contas_pagar',
                description: "Título {$fresh->title_number} pago (R$ {$fresh->amount}) em {$dataFmt}",
                auditable: $fresh,
                oldValues: ['status' => $old],
                newValues: [
                    'status' => 'pago',
                    'paid_at' => $data['paid_at'],
                    'payment_method' => $forma,
                    'paid_by' => $user->id,
                ],
            );

            return true;
        });

        if (! $paid) {
            return back()->with('error', 'Este título não está apto a ser pago.');
        }

        return back()->with('success', 'Pagamento registrado.');
    }

    /**
     * Registra a conciliação de um título PAGO (pago -> conciliado).
     * Governado pela ALÇADA: só quem está como `conciliador` concilia — nem o curinga '*' fura.
     */
    public function conciliate(Request $request, int $id, PayableAlcadaService $alcada)
    {
        $payable = Payable::findOrFail($id);
        $user = $request->user();

        if (! $alcada->isAssigned($user, 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador deste módulo.');
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $done = DB::transaction(function () use ($payable, $user, $data) {
            $fresh = Payable::whereKey($payable->id)->lockForUpdate()->first();

            if ($fresh->status !== 'pago') {
                return false;
            }

            $old = $fresh->status;
            $fresh->update([
                'status' => 'conciliado',
                'conciliated_at' => now()->toDateString(),
                'conciliated_by' => $user->id,
                'conciliation_notes' => $data['notes'] ?? null,
            ]);

            PayableComment::create([
                'payable_id' => $fresh->id,
                'user_id' => $user->id,
                'body' => 'Conciliação realizada'
                    . (($data['notes'] ?? null) ? " — {$data['notes']}" : ''),
                'type' => 'conciliation',
            ]);

            AuditLogger::log(
                event: 'contas_pagar.conciliado',
                module: 'financeiro.contas_pagar',
                description: "Título {$fresh->title_number} conciliado (R$ {$fresh->amount})",
                auditable: $fresh,
                oldValues: ['status' => $old],
                newValues: [
                    'status' => 'conciliado',
                    'conciliated_at' => now()->toDateString(),
                    'conciliated_by' => $user->id,
                    'conciliation_notes' => $data['notes'] ?? null,
                ],
            );

            return true;
        });

        if (! $done) {
            return back()->with('error', 'Este título não está apto a ser conciliado.');
        }

        return back()->with('success', 'Conciliação registrada.');
    }

    /**
     * Registra divergência de um título PAGO (pago -> divergente).
     * Governado pela ALÇADA: só quem está como `conciliador` registra divergência — nem o curinga '*' fura.
     */
    public function diverge(Request $request, int $id, PayableAlcadaService $alcada)
    {
        $payable = Payable::findOrFail($id);
        $user = $request->user();

        if (! $alcada->isAssigned($user, 'conciliador')) {
            abort(403, 'Você não está na alçada como conciliador deste módulo.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $done = DB::transaction(function () use ($payable, $user, $data) {
            $fresh = Payable::whereKey($payable->id)->lockForUpdate()->first();

            if ($fresh->status !== 'pago') {
                return false;
            }

            $old = $fresh->status;
            $fresh->update([
                'status' => 'divergente',
                'conciliated_at' => now()->toDateString(),
                'conciliated_by' => $user->id,
                'divergence_reason' => $data['reason'],
            ]);

            PayableComment::create([
                'payable_id' => $fresh->id,
                'user_id' => $user->id,
                'body' => "Divergência registrada — {$data['reason']}",
                'type' => 'divergence',
            ]);

            AuditLogger::log(
                event: 'contas_pagar.divergente',
                module: 'financeiro.contas_pagar',
                description: "Título {$fresh->title_number} marcado como divergente: {$data['reason']}",
                auditable: $fresh,
                oldValues: ['status' => $old],
                newValues: [
                    'status' => 'divergente',
                    'conciliated_at' => now()->toDateString(),
                    'conciliated_by' => $user->id,
                    'divergence_reason' => $data['reason'],
                ],
            );

            return true;
        });

        if (! $done) {
            return back()->with('error', 'Este título não está apto para registro de divergência.');
        }

        return back()->with('success', 'Divergência registrada.');
    }

    public function removeDocument(int $payableId, int $docId)
    {
        $doc = PayableDocument::where('payable_id', $payableId)->findOrFail($docId);
        Storage::disk('public')->delete($doc->path);
        $doc->delete();

        return back();
    }
}
