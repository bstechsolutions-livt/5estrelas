<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Models\Comercial\Proposta;
use Illuminate\Http\Request;

/**
 * Módulo Comercial — Propostas.
 * Persiste a cotação montada na tela Nova Cotação numa tabela de propostas.
 * (A tela de listagem/funil fica para a próxima fatia.)
 */
class ComercialPropostaController extends Controller
{
    /** Salva uma proposta (snapshot da cotação). */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'cliente' => 'nullable|string|max:255',
            'empresa' => 'nullable|string',
            'modelo' => 'required|in:5estrelas,in05',
            'periodicidade' => 'nullable|string',
            'cct' => 'nullable|string',
            'data_proposta' => 'nullable|date',
            'total_mensal' => 'numeric',
            'total_anual' => 'numeric',
            'qtd_postos' => 'integer',
            'qtd_funcionarios' => 'integer',
            'va_total' => 'nullable|numeric',
            'postos' => 'required|array|min:1',
            'identificacao' => 'nullable|array',
        ]);

        $dados['numero'] = Proposta::gerarNumero();
        $dados['status'] = 'rascunho';
        $dados['created_by'] = $request->user()->id;

        // A trait Auditable registra o evento `created` automaticamente.
        $proposta = Proposta::create($dados);

        return response()->json([
            'sucesso' => true,
            'numero' => $proposta->numero,
            'id' => $proposta->id,
        ]);
    }
}
