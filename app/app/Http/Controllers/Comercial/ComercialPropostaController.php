<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Models\Comercial\Cliente;
use App\Models\Comercial\Filial;
use App\Models\Comercial\Proposta;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Módulo Comercial — Controle de Propostas.
 * Tela de listagem/acompanhamento (porte 1:1 do protótipo Gestão 360º) +
 * entrada manual e o store usado pela tela de Cotação.
 */
class ComercialPropostaController extends Controller
{
    /** Tela de listagem (Inertia). */
    public function index()
    {
        return Inertia::render('Comercial/Propostas/Index', [
            'propostas' => $this->lista(),
            'situacaoLabels' => Proposta::SITUACAO_LABELS,
            'filiais' => Filial::where('ativo', true)->orderBy('ordem')->orderBy('nome')->get(),
            'clientes' => Cliente::orderBy('nome')->get(['id', 'nome', 'situacao', 'cidade', 'uf']),
        ]);
    }

    /** Mesma lista em JSON (refresh após mutações no front). */
    public function dados()
    {
        return response()->json(['propostas' => $this->lista()]);
    }

    /**
     * Lista ordenada por número (inteiro) desc, mapeada para o formato da tela.
     */
    private function lista()
    {
        return Proposta::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            // Ordena por número (parte numérica) desc, de forma agnóstica ao banco.
            // (Evita SQL específico do PostgreSQL para manter a portabilidade/testes.)
            ->sortByDesc(fn (Proposta $p) => (int) preg_replace('/\D/', '', (string) $p->numero))
            ->values()
            ->map(fn (Proposta $p) => [
                'id' => $p->id,
                'numero' => $p->numero,
                'revisao' => $p->revisao ?: 'N/A',
                'cliente' => $p->cliente,
                'servicos' => $p->servicos,
                'empresa' => $p->empresa,
                'posto' => $p->posto,
                'valor' => (float) $p->valor,
                'contato' => $p->contato,
                'data_proposta' => optional($p->data_proposta)->format('Y-m-d'),
                'situacao' => $p->situacao ?: 'EM ANÁLISE',
                'valor_aprovado' => $p->valor_aprovado !== null ? (float) $p->valor_aprovado : null,
                'data_aprovacao' => optional($p->data_aprovacao)->format('Y-m-d'),
                'observacao' => $p->observacao,
                'da_cotacao' => (bool) $p->da_cotacao,
            ]);
    }

    /** Salva uma proposta gerada pela tela de Cotação (snapshot da cotação). */
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
        $servicosPartes = [];
        foreach ($itens as $item) {
            $qtd = (int) ($item['qtdPostos'] ?? 0);
            $totalMensal += (float) ($item['totalMensal'] ?? 0);
            $qtdPostos += $qtd;
            $qtdFunc += $qtd * (int) ($item['funcPosto'] ?? 0);
            $vaTotal += (float) ($item['vaUnit'] ?? 0) * $qtd;

            $cat = trim((string) ($item['cat'] ?? ''));
            $escala = trim((string) ($item['escala'] ?? ''));
            $parte = trim($cat.($escala !== '' ? ' — '.$escala : ''));
            if ($parte !== '') {
                $servicosPartes[] = $parte;
            }
        }

        $dados['total_mensal'] = round($totalMensal, 2);
        $dados['total_anual'] = round($totalMensal * 12, 2);
        $dados['qtd_postos'] = $qtdPostos;
        $dados['qtd_funcionarios'] = $qtdFunc;
        $dados['va_total'] = round($vaTotal, 2);

        // Campos do "Controle de Propostas" derivados da cotação.
        $dados['valor'] = round($totalMensal, 2);
        $dados['situacao'] = 'EM ANÁLISE';
        $dados['servicos'] = $servicosPartes ? implode(' | ', $servicosPartes) : null;
        $dados['posto'] = ($dados['modelo'] === 'in05') ? 'IN 05' : 'Modelo 5 Estrelas';
        $dados['revisao'] = 'N/A';
        $dados['da_cotacao'] = true;

        // A trait Auditable registra o evento `created` automaticamente.
        $proposta = Proposta::create($dados);

        return response()->json([
            'sucesso' => true,
            'numero' => $proposta->numero,
            'id' => $proposta->id,
        ]);
    }

    /** Entrada manual de proposta (modal da tela Controle de Propostas). */
    public function storeManual(Request $request)
    {
        $dados = $this->validarManual($request);

        if (empty($dados['numero'])) {
            $dados['numero'] = Proposta::gerarNumero();
        }

        $dados['modelo'] = 'manual';
        $dados['status'] = 'rascunho';
        $dados['da_cotacao'] = false;
        $dados['created_by'] = $request->user()->id;

        $proposta = Proposta::create($dados);

        return response()->json([
            'sucesso' => true,
            'id' => $proposta->id,
            'numero' => $proposta->numero,
        ]);
    }

    /** Edita uma proposta (mesmos campos da entrada manual). */
    public function update(Request $request, int $id)
    {
        $proposta = Proposta::findOrFail($id);

        $dados = $this->validarManual($request, $proposta->id);

        if (empty($dados['numero'])) {
            $dados['numero'] = $proposta->numero;
        }

        $proposta->update($dados);

        return response()->json(['sucesso' => true]);
    }

    /** Altera apenas a situação (e dados de aprovação) de uma proposta. */
    public function updateSituacao(Request $request, int $id)
    {
        $proposta = Proposta::findOrFail($id);

        $dados = $request->validate([
            'situacao' => ['required', 'in:'.implode(',', Proposta::situacoesValidas())],
            'valor_aprovado' => 'nullable|numeric',
            'data_aprovacao' => 'nullable|date',
        ]);

        $proposta->update($dados);

        return response()->json(['sucesso' => true]);
    }

    /** Exclui uma proposta (o número volta para a fila quando > 131). */
    public function destroy(int $id)
    {
        $proposta = Proposta::findOrFail($id);
        $proposta->delete();

        return response()->json(['sucesso' => true]);
    }

    /**
     * Validação compartilhada entre criar (manual) e editar.
     */
    private function validarManual(Request $request, ?int $ignoreId = null): array
    {
        $uniqueNumero = 'unique:bs_comercial_propostas,numero';
        if ($ignoreId !== null) {
            $uniqueNumero .= ','.$ignoreId;
        }

        return $request->validate([
            'numero' => ['nullable', 'string', 'max:255', $uniqueNumero],
            'revisao' => 'nullable|string|max:255',
            'cliente' => 'nullable|string|max:255',
            'servicos' => 'nullable|string|max:255',
            'empresa' => 'nullable|string|max:255',
            'posto' => 'nullable|string|max:255',
            'valor' => 'required|numeric',
            'contato' => 'nullable|string|max:255',
            'data_proposta' => 'nullable|date',
            'situacao' => ['required', 'in:'.implode(',', Proposta::situacoesValidas())],
            'valor_aprovado' => 'nullable|numeric',
            'data_aprovacao' => 'nullable|date',
            'observacao' => 'nullable|string',
        ]);
    }
}
