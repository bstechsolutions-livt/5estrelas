<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Models\Comercial\Cliente;
use App\Models\Comercial\SaudeLancamento;
use App\Models\Comercial\SaudeMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Módulo Comercial — Saúde Contratual.
 * Score de saúde, composição financeira, evolução mensal, alertas e metas por cliente.
 * Porte do protótipo Gestão 360º (view-saude).
 */
class ComercialSaudeController extends Controller
{
    /** Tela principal (Inertia). */
    public function index(Request $request)
    {
        $clienteId = $request->integer('cliente');
        $cliente = $clienteId ? Cliente::find($clienteId) : null;

        return Inertia::render('Comercial/Saude/Index', [
            'clientes' => Cliente::orderBy('nome')->get(['id', 'nome', 'valor_mensal']),
            'clienteAtivo' => $cliente ? ['id' => $cliente->id, 'nome' => $cliente->nome, 'valor_mensal' => (float) $cliente->valor_mensal] : null,
            'lancamentos' => $cliente ? $this->lancamentosDo($cliente->id) : [],
            'metas' => $cliente ? (SaudeMeta::where('cliente_id', $cliente->id)->first()?->toArray() ?? $this->metasPadrao()) : $this->metasPadrao(),
        ]);
    }

    /** JSON dos lançamentos de um cliente. */
    public function dados(int $clienteId)
    {
        return response()->json([
            'lancamentos' => $this->lancamentosDo($clienteId),
            'metas' => SaudeMeta::where('cliente_id', $clienteId)->first()?->toArray() ?? $this->metasPadrao(),
        ]);
    }

    /** Criar/atualizar lançamento mensal. */
    public function storeLancamento(Request $request, int $clienteId)
    {
        Cliente::findOrFail($clienteId);

        $data = $request->validate([
            'mes_ref' => 'required|string|size:7', // 2026-01
            'faturamento_real' => 'required|numeric|min:0',
            'custo_folha' => 'nullable|numeric|min:0',
            'custo_beneficios' => 'nullable|numeric|min:0',
            'custo_insumos' => 'nullable|numeric|min:0',
            'inadimplencia' => 'nullable|numeric|min:0',
            'obs' => 'nullable|string',
        ]);

        $lancamento = SaudeLancamento::updateOrCreate(
            ['cliente_id' => $clienteId, 'mes_ref' => $data['mes_ref']],
            array_merge($data, ['created_by' => $request->user()->id]),
        );

        return response()->json(['sucesso' => true, 'id' => $lancamento->id]);
    }

    /** Excluir lançamento. */
    public function destroyLancamento(int $clienteId, int $lancId)
    {
        $lanc = SaudeLancamento::where('cliente_id', $clienteId)->findOrFail($lancId);
        $lanc->delete();

        return response()->json(['sucesso' => true]);
    }

    /** Salvar metas de um cliente. */
    public function storeMetas(Request $request, int $clienteId)
    {
        Cliente::findOrFail($clienteId);

        $data = $request->validate([
            'margem_minima' => 'nullable|numeric|min:0|max:100',
            'margem_alvo' => 'nullable|numeric|min:0|max:100',
            'max_folha_pct' => 'nullable|numeric|min:0|max:100',
            'inadimplencia_max' => 'nullable|numeric|min:0',
        ]);

        SaudeMeta::updateOrCreate(
            ['cliente_id' => $clienteId],
            $data,
        );

        return response()->json(['sucesso' => true]);
    }

    // ─── Privado ─────────────────────────────────────────────────────────────────

    private function lancamentosDo(int $clienteId): array
    {
        return SaudeLancamento::where('cliente_id', $clienteId)
            ->orderBy('mes_ref')
            ->get()
            ->map(fn (SaudeLancamento $l) => [
                'id' => $l->id,
                'mes_ref' => $l->mes_ref,
                'faturamento_real' => (float) $l->faturamento_real,
                'custo_folha' => (float) $l->custo_folha,
                'custo_beneficios' => (float) $l->custo_beneficios,
                'custo_insumos' => (float) $l->custo_insumos,
                'inadimplencia' => (float) $l->inadimplencia,
                'custo_total' => $l->custoTotal(),
                'resultado' => $l->resultado(),
                'margem' => $l->margem(),
                'obs' => $l->obs,
            ])
            ->toArray();
    }

    private function metasPadrao(): array
    {
        return [
            'margem_minima' => 2.5,
            'margem_alvo' => 3.0,
            'max_folha_pct' => 75.0,
            'inadimplencia_max' => 0,
        ];
    }
}
