<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Models\Comercial\Reajuste;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Módulo Comercial — Reajustes de contrato (esteira de reajuste anual por cliente).
 * Listagem com KPIs e agrupamento por empresa (Segurança / Apoio), mudança de status
 * e detalhe dos itens. Porte do protótipo Gestão 360º (view-reajuste).
 */
class ComercialReajusteController extends Controller
{
    /** Tela de listagem (Inertia). */
    public function index()
    {
        return Inertia::render('Comercial/Reajustes/Index', [
            'reajustes' => $this->lista(),
            'statusLabels' => Reajuste::STATUS_LABELS,
        ]);
    }

    /** Mesma lista em JSON (refresh após mutações). */
    public function dados()
    {
        return response()->json(['reajustes' => $this->lista()]);
    }

    private function lista()
    {
        return Reajuste::query()
            ->orderBy('cliente_nome')
            ->get()
            ->map(fn (Reajuste $r) => [
                'id' => $r->id,
                'cliente_nome' => $r->cliente_nome,
                'cliente_id' => $r->cliente_id,
                'empresa' => $r->empresa,
                'tipo' => $r->tipo,
                'pct' => (float) $r->pct,
                'competencia' => $r->competencia,
                'obs' => $r->obs,
                'status' => $r->status,
                'valor_atual' => (float) $r->valor_atual,
                'impacto_mensal' => (float) $r->impacto_mensal,
                'novo_valor' => round((float) $r->valor_atual + (float) $r->impacto_mensal, 2),
                'itens' => $r->itens ?? [],
            ]);
    }

    /** Altera o status de um reajuste (workflow). */
    public function updateStatus(Request $request, int $id)
    {
        $reajuste = Reajuste::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', Reajuste::statusValidos())],
        ]);

        $reajuste->update(['status' => $data['status']]);

        AuditLogger::log(
            event: 'comercial.reajuste.status',
            module: 'comercial',
            description: "Reajuste de {$reajuste->cliente_nome} → " . Reajuste::STATUS_LABELS[$data['status']],
            auditable: $reajuste,
        );

        return response()->json(['sucesso' => true]);
    }

    /** Exclui um reajuste. */
    public function destroy(int $id)
    {
        $reajuste = Reajuste::findOrFail($id);
        $reajuste->delete();

        return response()->json(['sucesso' => true]);
    }
}
