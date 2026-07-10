<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Models\PayableDocument;
use App\Models\ApprovalStep;
use App\Services\AuditLogger;
use App\Services\ApprovalWorkflowService;
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

        // Título em borderô não aparece nos pendentes — o agrupamento é feito pelo borderô.
        if ($status === 'pendente') {
            $query->whereNull('bordero_id');
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
        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', (float) $request->amount_min);
        }
        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', (float) $request->amount_max);
        }

        $payables = $query->paginate($request->input('per_page', 20))->withQueryString();

        // A3: resolve o NOME da empresa (nunca código) para cada título da página.
        Payable::attachEmpresaNome($payables->getCollection());

        if ($request->wantsJson() || $request->header('X-Json-Only') === '1') {
            return response()->json($payables);
        }

        // Totais por status (pendente em borderô não entra na aba Pendentes)
        $totals = Payable::query()
            ->where(function ($q) {
                $q->where('status', '!=', 'pendente')
                    ->orWhereNull('bordero_id');
            })
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

        // A3: nome da empresa (nunca código) também no detalhe.
        Payable::attachEmpresaNome([$payable]);

        // Approval steps (workflow multinível)
        $approvalSteps = ApprovalStep::where('payable_id', $id)
            ->with(['assignee:id,name', 'resolver:id,name'])
            ->orderBy('order')
            ->get();

        $workflow = app(ApprovalWorkflowService::class);
        $currentStep = $workflow->currentStep($payable);
        $canApproveStep = $workflow->canUserApprove($payable, $user);

        return Inertia::render('Payables/Show', [
            'payable' => $payable,
            'statusLabels' => Payable::STATUS_LABELS,
            'statusColors' => Payable::STATUS_COLORS,
            'canPay' => $isPagador && $payable->status === 'aprovado',
            'paymentMethods' => Payable::PAYMENT_METHODS,
            'pagadorConfigured' => $alcada->hasRole('pagador'),
            'canConciliate' => $isConciliador && $payable->status === 'pago',
            'conciliadorConfigured' => $alcada->hasRole('conciliador'),
            'canFinalSign' => $alcada->isAssigned($user, 'assinante') && $payable->status === 'conciliado',
            'canEditDueDate' => $user?->hasPermission('financeiro.contas_pagar.editar_vencimento') ?? false,
            'approvalSteps' => $approvalSteps,
            'currentStep' => $currentStep,
            'canApproveStep' => $canApproveStep,
            'mentionableUsers' => app(\App\Services\MentionService::class)->mentionableUsers($user, $id),
            'approvalPreview' => $workflow->buildPreviewStepsForSender($user),
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

        // Processa @menções (notificação + visibilidade)
        app(\App\Services\MentionService::class)->processComment($comment);

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
            'type' => ['nullable', Rule::in(array_keys(PayableDocument::TYPES))],
        ]);

        $file = $request->file('file');
        $path = $file->store('payables/docs', 'public');

        PayableDocument::create([
            'payable_id' => $payable->id,
            'uploaded_by' => $request->user()->id,
            'name' => $file->getClientOriginalName(),
            'doc_type' => $request->input('type', 'outro'),
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

        // Trava (feedback do cliente): não pode ir pra aprovação sem NENHUM documento
        // anexado (nota fiscal, boleto, relatório ou comprovante). Nem todo título tem
        // tudo, mas não pode ser aprovado "sem nada".
        if ($payable->documents()->count() === 0) {
            return back()->with('error', 'Anexe ao menos um documento (nota fiscal, boleto, relatório ou comprovante) antes de enviar para aprovação.');
        }

        $workflow = app(ApprovalWorkflowService::class);
        $preview = $workflow->buildPreviewStepsForSender($request->user());
        if (! $preview['ok']) {
            return back()->with('error', $preview['errors'][0] ?? 'Não foi possível enviar para aprovação.');
        }

        try {
            $workflow->sendForApproval($payable, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Enviado para aprovação multinível.');
    }

    public function approve(Request $request, int $id)
    {
        $payable = Payable::findOrFail($id);

        if ($payable->status !== 'aguardando_aprovacao') {
            return back()->with('error', 'Este título não está aguardando aprovação.');
        }

        // Trava (feedback do cliente): não é possível aprovar um título sem documentos.
        // Guarda defensiva — um documento pode ter sido removido depois do envio.
        if ($payable->documents()->count() === 0) {
            return back()->with('error', 'Não é possível aprovar um título sem documentos anexados.');
        }

        $workflow = app(ApprovalWorkflowService::class);
        $result = $workflow->approve($payable, $request->user(), $request->input('comment'));

        if (!$result['success']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['message']);
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

        $workflow = app(ApprovalWorkflowService::class);
        $result = $workflow->reject($payable, $request->user(), $data['reason']);

        if (!$result['success']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['message']);
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
                    'doc_type' => 'comprovacao',
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


 // Cria step automatico de 2a assinatura (presidencia) pos-conciliacao
 $assinante = \App\Models\PayableRole::where("role", "assinante")->first();
 if ($assinante) {
 \App\Models\ApprovalStep::create([
 "payable_id" => $fresh->id, "order" => 99, "level_name" => "presidencia_2",
 "status" => "pendente", "assigned_to" => $assinante->user_id,
 ]);
 \App\Models\Notification::create([
 "user_id" => $assinante->user_id, "title" => "2a assinatura pendente",
 "body" => "Titulo {$fresh->title_number} conciliado - aguarda encerramento",
 "type" => "approval_pending", "link" => "/financeiro/contas-pagar/{$fresh->id}",
 "data" => ["payable_id" => $fresh->id],
 ]);
 }
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

    /**
     * A4: altera o vencimento de um título. Restrito ao FINANCEIRO
     * (permissão `financeiro.contas_pagar.editar_vencimento`; curinga `*` concede).
     */
    public function updateDueDate(Request $request, int $id)
    {
        $payable = Payable::findOrFail($id);
        $user = $request->user();

        if (! $user?->hasPermission('financeiro.contas_pagar.editar_vencimento')) {
            abort(403, 'Apenas o financeiro pode alterar o vencimento.');
        }

        $data = $request->validate([
            'due_date' => ['required', 'date'],
        ]);

        $old = $payable->due_date?->toDateString();
        $novo = Carbon::parse($data['due_date'])->toDateString();
        $payable->update(['due_date' => $novo]);

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $user->id,
            'body' => 'Vencimento alterado de '
                . ($old ? Carbon::parse($old)->format('d/m/Y') : '—')
                . ' para ' . Carbon::parse($novo)->format('d/m/Y'),
            'type' => 'status_change',
        ]);

        AuditLogger::log(
            event: 'contas_pagar.vencimento_alterado',
            module: 'financeiro.contas_pagar',
            description: "Vencimento do título {$payable->title_number} alterado para " . Carbon::parse($novo)->format('d/m/Y'),
            auditable: $payable,
            oldValues: ['due_date' => $old],
            newValues: ['due_date' => $novo],
        );

        return back()->with('success', 'Vencimento atualizado.');
    }

    public function removeDocument(int $payableId, int $docId)
    {
        $doc = PayableDocument::where('payable_id', $payableId)->findOrFail($docId);
        Storage::disk('public')->delete($doc->path);
        $doc->delete();

        return back();
    }

    /**
     * 2ª assinatura do presidente — encerra o ciclo após conciliação (Fluxo v3.0 passo 8).
     * Só o presidente (ou substituto configurado) pode executar.
     */
    public function finalSign(Request $request, int $id, PayableAlcadaService $alcada)
    {
        $payable = Payable::findOrFail($id);
        $user = $request->user();

        // Só quem está na alçada como 'assinante' pode dar a 2ª assinatura
        if (!$alcada->isAssigned($user, 'assinante')) {
            abort(403, 'Você não está na alçada como assinante (2ª assinatura).');
        }

        if ($payable->status !== 'conciliado') {
            return back()->with('error', 'Este título precisa estar conciliado para encerrar.');
        }

        $payable->update(['status' => 'encerrado']);

        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $user->id,
            'body' => '2ª assinatura — ciclo encerrado pela Presidência',
            'type' => 'approval',
        ]);

        AuditLogger::log(
            event: 'contas_pagar.encerrado',
            module: 'financeiro.contas_pagar',
            description: "Título {$payable->title_number} encerrado (2ª assinatura: {$user->name})",
            auditable: $payable,
            oldValues: ['status' => 'conciliado'],
            newValues: ['status' => 'encerrado'],
        );

        return back()->with('success', 'Ciclo encerrado (2ª assinatura).');
    }

    /**
     * Lista usuários mencionáveis em comentários deste payable (pra autocomplete @mention).
     */
    public function mentionableUsers(Request $request, int $id)
    {
        $mentionService = app(\App\Services\MentionService::class);
        $users = $mentionService->mentionableUsers($request->user(), $id);

        return response()->json($users);
    }
}
