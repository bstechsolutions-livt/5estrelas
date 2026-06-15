<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Models\Comercial\Cliente;
use App\Models\Comercial\Proposta;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Módulo Comercial — Clientes/Contratos.
 * Listagem de clientes e detalhe com propostas vinculadas e postos ativos.
 */
class ComercialClienteController extends Controller
{
    /** Tela de listagem (Inertia). */
    public function index()
    {
        $clientes = Cliente::query()
            ->withCount('propostas')
            ->orderBy('nome')
            ->get()
            ->map(fn (Cliente $c) => [
                'id' => $c->id,
                'nome' => $c->nome,
                'contato_nome' => $c->contato_nome,
                'contato_email' => $c->contato_email,
                'contato_telefone' => $c->contato_telefone,
                'cidade' => $c->cidade,
                'uf' => $c->uf,
                'situacao' => $c->situacao,
                'valor_mensal' => (float) $c->valor_mensal,
                'total_colaboradores' => $c->total_colaboradores,
                'total_postos' => $c->total_postos,
                'observacao' => $c->observacao,
                'propostas_count' => $c->propostas_count,
                'created_at' => $c->created_at?->toISOString(),
            ]);

        return Inertia::render('Comercial/Clientes/Index', [
            'clientes' => $clientes,
            'situacaoLabels' => Cliente::SITUACAO_LABELS,
        ]);
    }

    /** Tela de detalhe (Inertia). */
    public function show(int $id)
    {
        $cliente = Cliente::findOrFail($id);

        $propostas = Proposta::where('cliente_id', $id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Proposta $p) => [
                'id' => $p->id,
                'numero' => $p->numero,
                'cliente' => $p->cliente,
                'servicos' => $p->servicos,
                'empresa' => $p->empresa,
                'posto' => $p->posto,
                'valor' => (float) $p->valor,
                'situacao' => $p->situacao ?: 'EM ANÁLISE',
                'data_proposta' => optional($p->data_proposta)->format('Y-m-d'),
                'valor_aprovado' => $p->valor_aprovado !== null ? (float) $p->valor_aprovado : null,
                'data_aprovacao' => optional($p->data_aprovacao)->format('Y-m-d'),
                'postos' => $p->postos,
                'qtd_postos' => $p->qtd_postos,
                'qtd_funcionarios' => $p->qtd_funcionarios,
                'total_mensal' => (float) $p->total_mensal,
            ]);

        return Inertia::render('Comercial/Clientes/Show', [
            'cliente' => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'contato_nome' => $cliente->contato_nome,
                'contato_email' => $cliente->contato_email,
                'contato_telefone' => $cliente->contato_telefone,
                'cidade' => $cliente->cidade,
                'uf' => $cliente->uf,
                'situacao' => $cliente->situacao,
                'valor_mensal' => (float) $cliente->valor_mensal,
                'total_colaboradores' => $cliente->total_colaboradores,
                'total_postos' => $cliente->total_postos,
                'observacao' => $cliente->observacao,
            ],
            'propostas' => $propostas,
            'situacaoLabels' => Cliente::SITUACAO_LABELS,
        ]);
    }

    /** Cria cliente. */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'nome' => 'required|string|max:255',
            'contato_nome' => 'nullable|string|max:255',
            'contato_email' => 'nullable|email|max:255',
            'contato_telefone' => 'nullable|string|max:50',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:2',
            'situacao' => 'nullable|in:ativo,inativo,prospecto',
            'valor_mensal' => 'nullable|numeric',
            'total_colaboradores' => 'nullable|integer|min:0',
            'total_postos' => 'nullable|integer|min:0',
            'observacao' => 'nullable|string',
        ]);

        $dados['created_by'] = $request->user()->id;

        $cliente = Cliente::create($dados);

        return response()->json([
            'sucesso' => true,
            'id' => $cliente->id,
        ]);
    }

    /** Atualiza cliente. */
    public function update(Request $request, int $id)
    {
        $cliente = Cliente::findOrFail($id);

        $dados = $request->validate([
            'nome' => 'required|string|max:255',
            'contato_nome' => 'nullable|string|max:255',
            'contato_email' => 'nullable|email|max:255',
            'contato_telefone' => 'nullable|string|max:50',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:2',
            'situacao' => 'nullable|in:ativo,inativo,prospecto',
            'valor_mensal' => 'nullable|numeric',
            'total_colaboradores' => 'nullable|integer|min:0',
            'total_postos' => 'nullable|integer|min:0',
            'observacao' => 'nullable|string',
        ]);

        $cliente->update($dados);

        return response()->json(['sucesso' => true]);
    }

    /** Exclui cliente. */
    public function destroy(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->delete();

        return response()->json(['sucesso' => true]);
    }

    /** Vincula uma proposta a este cliente. */
    public function vincularProposta(Request $request, int $id)
    {
        $cliente = Cliente::findOrFail($id);

        $dados = $request->validate([
            'proposta_id' => 'required|exists:bs_comercial_propostas,id',
        ]);

        $proposta = Proposta::findOrFail($dados['proposta_id']);
        $proposta->update(['cliente_id' => $cliente->id]);

        // Recalcula totalizadores do cliente
        $this->recalcularTotais($cliente);

        return response()->json(['sucesso' => true]);
    }

    /** Desvincula uma proposta do cliente. */
    public function desvincularProposta(int $clienteId, int $propostaId)
    {
        $cliente = Cliente::findOrFail($clienteId);
        $proposta = Proposta::where('id', $propostaId)
            ->where('cliente_id', $clienteId)
            ->firstOrFail();

        $proposta->update(['cliente_id' => null]);

        // Recalcula totalizadores do cliente
        $this->recalcularTotais($cliente);

        return response()->json(['sucesso' => true]);
    }

    /** Recalcula valor_mensal, total_postos e total_colaboradores do cliente a partir das propostas vinculadas. */
    private function recalcularTotais(Cliente $cliente): void
    {
        $propostas = Proposta::where('cliente_id', $cliente->id)->get();

        $valorMensal = $propostas->sum('total_mensal');
        $totalPostos = $propostas->sum('qtd_postos');
        $totalColaboradores = $propostas->sum('qtd_funcionarios');

        $cliente->update([
            'valor_mensal' => round($valorMensal, 2),
            'total_postos' => $totalPostos,
            'total_colaboradores' => $totalColaboradores,
        ]);
    }
}
