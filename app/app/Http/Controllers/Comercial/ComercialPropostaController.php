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

        // Recalcula os totais a partir dos itens no backend (não confiar só no front).
        // Cada item traz: totalMensal, qtdPostos, funcPosto, vaUnit.
        $itens = $dados['postos'];
        $totalMensal = 0.0;
        $qtdPostos = 0;
        $qtdFunc = 0;
        $vaTotal = 0.0;
        foreach ($itens as $item) {
            $qtd = (int) ($item['qtdPostos'] ?? 0);
            $totalMensal += (float) ($item['totalMensal'] ?? 0);
            $qtdPostos += $qtd;
            $qtdFunc += $qtd * (int) ($item['funcPosto'] ?? 0);
            $vaTotal += (float) ($item['vaUnit'] ?? 0) * $qtd;
        }

        $dados['total_mensal'] = round($totalMensal, 2);
        $dados['total_anual'] = round($totalMensal * 12, 2);
        $dados['qtd_postos'] = $qtdPostos;
        $dados['qtd_funcionarios'] = $qtdFunc;
        $dados['va_total'] = round($vaTotal, 2);

        // A trait Auditable registra o evento `created` automaticamente.
        $proposta = Proposta::create($dados);

        return response()->json([
            'sucesso' => true,
            'numero' => $proposta->numero,
            'id' => $proposta->id,
        ]);
    }
}
