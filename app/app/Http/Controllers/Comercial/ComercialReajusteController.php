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
            'clientes' => \App\Models\Comercial\Cliente::orderBy('nome')
                ->get(['id', 'nome', 'valor_mensal', 'observacao'])
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'nome' => $c->nome,
                    'valor_mensal' => (float) $c->valor_mensal,
                ]),
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

    /** Cria um reajuste a partir de um cliente (Iniciar Reajuste). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => 'nullable|exists:bs_comercial_clientes,id',
            'cliente_nome' => 'required|string|max:255',
            'empresa' => 'nullable|string|max:50',
            'tipo' => 'nullable|string|max:30',
            'pct' => 'required|numeric|min:0|max:100',
            'competencia' => 'nullable|string|max:20',
            'obs' => 'nullable|string',
            'valor_atual' => 'required|numeric|min:0',
        ]);

        $pct = round((float) $data['pct'], 2);
        $valorAtual = round((float) $data['valor_atual'], 2);
        $novoValor = round($valorAtual * (1 + $pct / 100), 2);
        $variacao = round($novoValor - $valorAtual, 2);

        $reajuste = Reajuste::create([
            'cliente_id' => $data['cliente_id'] ?? null,
            'cliente_nome' => $data['cliente_nome'],
            'empresa' => $data['empresa'] ?? null,
            'tipo' => $data['tipo'] ?? 'manual',
            'pct' => $pct,
            'competencia' => $data['competencia'] ?? null,
            'obs' => $data['obs'] ?? null,
            'status' => 'calculado',
            'valor_atual' => $valorAtual,
            'impacto_mensal' => $variacao,
            'data_criacao' => now()->toDateString(),
            'created_by' => $request->user()->id,
            'historico' => [['data' => now()->toDateString(), 'status' => 'calculado', 'nota' => 'Reajuste iniciado']],
            'itens' => [[
                'nome' => 'Contrato', 'escala' => 'Mensal', 'qtd' => 1,
                'valorAtual' => $valorAtual, 'pct' => $pct,
                'novoValor' => $novoValor, 'variacao' => $variacao, 'selecionado' => true,
            ]],
        ]);

        return response()->json(['sucesso' => true, 'id' => $reajuste->id]);
    }

    /** Edita a planilha de um reajuste (índice/%, itens, observação) e recalcula totais. */
    public function update(Request $request, int $id)
    {
        $reajuste = Reajuste::findOrFail($id);

        $data = $request->validate([
            'tipo' => 'nullable|string|max:30',
            'pct' => 'nullable|numeric|min:0|max:100',
            'competencia' => 'nullable|string|max:20',
            'obs' => 'nullable|string',
            'itens' => 'required|array|min:1',
            'itens.*.nome' => 'nullable|string',
            'itens.*.valorAtual' => 'required|numeric',
            'itens.*.pct' => 'nullable|numeric',
            'itens.*.selecionado' => 'nullable|boolean',
        ]);

        // Recalcula cada item e os totais a partir dos selecionados (fonte da verdade no backend).
        $valorAtualTotal = 0.0;
        $impacto = 0.0;
        $itens = array_map(function ($it) use (&$valorAtualTotal, &$impacto) {
            $valorAtual = round((float) ($it['valorAtual'] ?? 0), 2);
            $pct = round((float) ($it['pct'] ?? 0), 2);
            $novoValor = round($valorAtual * (1 + $pct / 100), 2);
            $variacao = round($novoValor - $valorAtual, 2);
            $sel = (bool) ($it['selecionado'] ?? true);
            if ($sel) {
                $valorAtualTotal += $valorAtual;
                $impacto += $variacao;
            }

            return [
                'nome' => $it['nome'] ?? '—', 'escala' => $it['escala'] ?? 'Mensal',
                'valorAtual' => $valorAtual, 'pct' => $pct,
                'novoValor' => $novoValor, 'variacao' => $variacao, 'selecionado' => $sel,
            ];
        }, $data['itens']);

        $reajuste->update([
            'tipo' => $data['tipo'] ?? $reajuste->tipo,
            'pct' => isset($data['pct']) ? round((float) $data['pct'], 2) : $reajuste->pct,
            'competencia' => $data['competencia'] ?? $reajuste->competencia,
            'obs' => $data['obs'] ?? $reajuste->obs,
            'itens' => $itens,
            'valor_atual' => round($valorAtualTotal, 2),
            'impacto_mensal' => round($impacto, 2),
        ]);

        return response()->json(['sucesso' => true]);
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
