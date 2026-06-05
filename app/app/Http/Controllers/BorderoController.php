<?php

namespace App\Http\Controllers;

use App\Models\Bordero;
use App\Models\Payable;
use App\Models\PayableComment;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BorderoController extends Controller
{
    public function index(Request $request)
    {
        $query = Bordero::query()
            ->with('creator:id,name')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('number', 'ilike', "%{$s}%")
                    ->orWhere('description', 'ilike', "%{$s}%");
            });
        }

        $borderos = $query->paginate($request->input('per_page', 20))->withQueryString();

        $totals = Bordero::query()
            ->selectRaw("status, count(*) as count, coalesce(sum(total_amount), 0) as total")
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return Inertia::render('Borderos/Index', [
            'borderos' => $borderos,
            'totals' => $totals,
            'filters' => $request->only(['status', 'search']),
            'statusOptions' => Bordero::STATUS_LABELS,
        ]);
    }

    public function show(int $id)
    {
        $bordero = Bordero::with([
            'creator:id,name',
            'approver:id,name',
            'payables' => fn ($q) => $q->with('branch:id,name')->withCount('documents')->orderBy('due_date'),
        ])->findOrFail($id);

        return Inertia::render('Borderos/Show', [
            'bordero' => $bordero,
            'statusLabels' => Bordero::STATUS_LABELS,
            'statusColors' => Bordero::STATUS_COLORS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'payable_ids' => ['required', 'array', 'min:1'],
            'payable_ids.*' => ['integer', 'exists:payables,id'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        // Valida que os títulos estão em status que permite agrupar
        $payables = Payable::whereIn('id', $data['payable_ids'])
            ->whereIn('status', ['pendente', 'em_preparacao', 'reprovado'])
            ->whereNull('bordero_id')
            ->get();

        if ($payables->isEmpty()) {
            return back()->with('error', 'Nenhum título válido para agrupar (devem estar pendentes e fora de outro borderô).');
        }

        $bordero = DB::transaction(function () use ($payables, $data, $request) {
            $bordero = Bordero::create([
                'number' => Bordero::generateNumber(),
                'description' => $data['description'] ?? null,
                'status' => 'rascunho',
                'created_by' => $request->user()->id,
            ]);

            Payable::whereIn('id', $payables->pluck('id'))->update([
                'bordero_id' => $bordero->id,
                'prepared_by' => $request->user()->id,
            ]);

            $bordero->recalculate();

            return $bordero;
        });

        AuditLogger::log(
            event: 'bordero.created',
            module: 'financeiro.contas_pagar',
            description: "Borderô {$bordero->number} criado com {$bordero->items_count} título(s) - R$ {$bordero->total_amount}",
            auditable: $bordero,
        );

        return redirect("/financeiro/borderos/{$bordero->id}")->with('success', 'Borderô criado.');
    }

    public function removePayable(int $borderoId, int $payableId)
    {
        $bordero = Bordero::findOrFail($borderoId);

        if ($bordero->status !== 'rascunho') {
            return back()->with('error', 'Só é possível alterar borderôs em rascunho.');
        }

        Payable::where('id', $payableId)->where('bordero_id', $borderoId)
            ->update(['bordero_id' => null]);

        $bordero->recalculate();

        if ($bordero->items_count === 0) {
            $bordero->delete();
            return redirect('/financeiro/borderos')->with('success', 'Borderô removido (ficou vazio).');
        }

        return back()->with('success', 'Título removido do borderô.');
    }

    public function sendForApproval(Request $request, int $id)
    {
        $bordero = Bordero::findOrFail($id);

        if ($bordero->status !== 'rascunho') {
            return back()->with('error', 'Este borderô não pode ser enviado.');
        }

        DB::transaction(function () use ($bordero) {
            $bordero->update([
                'status' => 'aguardando_aprovacao',
                'sent_for_approval_at' => now(),
            ]);

            $bordero->payables()->update([
                'status' => 'aguardando_aprovacao',
                'sent_for_approval_at' => now(),
            ]);
        });

        AuditLogger::log(
            event: 'bordero.enviado_aprovacao',
            module: 'financeiro.contas_pagar',
            description: "Borderô {$bordero->number} enviado para aprovação (R$ {$bordero->total_amount})",
            auditable: $bordero,
        );

        return back()->with('success', 'Borderô enviado para aprovação.');
    }

    public function approve(Request $request, int $id)
    {
        $bordero = Bordero::findOrFail($id);

        if ($bordero->status !== 'aguardando_aprovacao') {
            return back()->with('error', 'Este borderô não está aguardando aprovação.');
        }

        DB::transaction(function () use ($bordero, $request) {
            $bordero->update([
                'status' => 'aprovado',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $bordero->payables()->update([
                'status' => 'aprovado',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            foreach ($bordero->payables as $p) {
                PayableComment::create([
                    'payable_id' => $p->id,
                    'user_id' => $request->user()->id,
                    'body' => "Aprovado via borderô {$bordero->number}",
                    'type' => 'approval',
                ]);
            }
        });

        AuditLogger::log(
            event: 'bordero.aprovado',
            module: 'financeiro.contas_pagar',
            description: "Borderô {$bordero->number} aprovado (R$ {$bordero->total_amount}, {$bordero->items_count} títulos)",
            auditable: $bordero,
        );

        return back()->with('success', 'Borderô aprovado.');
    }

    public function reject(Request $request, int $id)
    {
        $bordero = Bordero::findOrFail($id);

        if ($bordero->status !== 'aguardando_aprovacao') {
            return back()->with('error', 'Este borderô não está aguardando aprovação.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($bordero, $request, $data) {
            $bordero->update([
                'status' => 'reprovado',
                'approved_by' => $request->user()->id,
                'rejection_reason' => $data['reason'],
            ]);

            $bordero->payables()->update([
                'status' => 'reprovado',
                'approved_by' => $request->user()->id,
                'rejection_reason' => $data['reason'],
            ]);

            foreach ($bordero->payables as $p) {
                PayableComment::create([
                    'payable_id' => $p->id,
                    'user_id' => $request->user()->id,
                    'body' => "Reprovado via borderô {$bordero->number}: {$data['reason']}",
                    'type' => 'rejection',
                ]);
            }
        });

        AuditLogger::log(
            event: 'bordero.reprovado',
            module: 'financeiro.contas_pagar',
            description: "Borderô {$bordero->number} reprovado: {$data['reason']}",
            auditable: $bordero,
        );

        return back()->with('success', 'Borderô reprovado.');
    }
}
