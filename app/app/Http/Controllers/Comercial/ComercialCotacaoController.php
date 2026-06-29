<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Models\Comercial\Categoria;
use App\Models\Comercial\Cct;
use App\Models\Comercial\Cliente;
use App\Models\Comercial\Escala;
use App\Models\Comercial\Filial;
use App\Models\Comercial\Indice;
use App\Models\Comercial\Proposta;
use App\Services\Comercial\ComposicaoCustoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Módulo Comercial — Cotação (Spec 2).
 * Tela de montagem da planilha de custos (IN 05) por posto, consumindo a Config
 * (CCT/escala/índices) e o motor ComposicaoCustoService (verificado 1:1 com o protótipo).
 */
class ComercialCotacaoController extends Controller
{
    public function index(Request $request)
    {
        // Reabrir uma proposta na cotação (botão "Abrir na cotação" — 1:1 protótipo
        // abrirCotacaoDaProposta). Quando veio da plataforma (da_cotacao), restaura o
        // snapshot completo de postos; senão, pré-preenche cliente/número.
        $propostaInicial = null;
        if ($request->filled('proposta')) {
            $p = Proposta::find($request->integer('proposta'));
            if ($p) {
                $propostaInicial = [
                    'id' => $p->id,
                    'numero' => $p->numero,
                    'cliente' => $p->cliente,
                    'empresa' => $p->empresa,
                    'modelo' => $p->modelo,
                    'cct' => $p->cct,
                    'periodicidade' => $p->periodicidade,
                    'data_proposta' => optional($p->data_proposta)->format('Y-m-d'),
                    'postos' => $p->postos ?? [],
                    'identificacao' => $p->identificacao ?? [],
                    'da_cotacao' => (bool) $p->da_cotacao,
                ];
            }
        }

        return Inertia::render('Comercial/Cotacao/Index', [
            'propostaInicial' => $propostaInicial,
        ]);
    }

    /** Dados para os seletores da cotação. */
    public function dados()
    {
        $indices = Indice::pluck('valor', 'chave');

        return response()->json([
            'ccts' => Cct::where('ativo', true)->orderBy('uf')->orderBy('servico')->get(),
            'escalas' => Escala::where('ativo', true)->orderBy('nome')->get(),
            'categorias' => Categoria::where('ativo', true)->orderBy('nome')->get(),
            'indices' => $indices,
            'filiais' => Filial::where('ativo', true)->orderBy('ordem')->orderBy('nome')->get(),
            'clientes' => Cliente::orderBy('nome')->get(['id', 'nome', 'situacao', 'cidade', 'uf']),
        ]);
    }

    /** Calcula a planilha IN 05 para um posto (motor backend = fonte da verdade). */
    public function calcular(Request $request, ComposicaoCustoService $svc)
    {
        $in = $request->validate([
            'sal' => 'nullable|numeric', 'dias_mes' => 'nullable|numeric', 'horas_mes' => 'nullable|numeric',
            'peric_pct' => 'nullable|numeric', 'insal_pct' => 'nullable|numeric', 'an_pct' => 'nullable|numeric',
            'hnr_pct' => 'nullable|numeric', 'outros1_pct' => 'nullable|numeric',
            'inss_pct' => 'nullable|numeric', 'saledu_pct' => 'nullable|numeric', 'sat_pct' => 'nullable|numeric',
            'sesc_pct' => 'nullable|numeric', 'senai_pct' => 'nullable|numeric', 'sebrae_pct' => 'nullable|numeric',
            'incra_pct' => 'nullable|numeric', 'fgts_pct' => 'nullable|numeric',
            'vt_dia' => 'nullable|numeric', 'va_dia' => 'nullable|numeric', 'medico' => 'nullable|numeric',
            'odonto' => 'nullable|numeric', 'cesta' => 'nullable|numeric', 'seguro' => 'nullable|numeric',
            'pmq' => 'nullable|numeric', 'outros23' => 'nullable|numeric',
            'avisoind_pct' => 'nullable|numeric', 'avistrab_pct' => 'nullable|numeric', 'ausleg_pct' => 'nullable|numeric',
            'paterni_pct' => 'nullable|numeric', 'acident_pct' => 'nullable|numeric', 'matern_pct' => 'nullable|numeric',
            'intrajornada' => 'nullable|numeric',
            'uniforme' => 'nullable|numeric', 'materiais' => 'nullable|numeric', 'ferramental' => 'nullable|numeric',
            'epi' => 'nullable|numeric', 'treinamento' => 'nullable|numeric', 'sso' => 'nullable|numeric',
            'custoind_pct' => 'nullable|numeric', 'lucro_pct' => 'nullable|numeric',
            'iss_pct' => 'nullable|numeric', 'pis_pct' => 'nullable|numeric', 'cofins_pct' => 'nullable|numeric',
            'colaboradores' => 'nullable|integer',
        ]);

        $resultado = $svc->calcularIN05($in);

        // Multiplicação por nº de colaboradores do posto (motor calcula por empregado)
        $colab = max(1, (int) ($in['colaboradores'] ?? 1));
        $resultado['colaboradores'] = $colab;
        $resultado['valor_posto_mensal'] = round($resultado['preco_empregado'] * $colab, 2);

        return response()->json(['sucesso' => true, 'resultado' => $resultado]);
    }

    /** Calcula pelo Modelo 5 Estrelas (diurno/noturno). */
    public function calcular5e(Request $request, ComposicaoCustoService $svc)
    {
        $in = $request->validate([
            'qtd_diurno' => 'nullable|integer', 'sal_diurno' => 'nullable|numeric',
            'qtd_noturno' => 'nullable|integer', 'sal_noturno' => 'nullable|numeric',
            'an_diurno' => 'nullable|integer', 'an_noturno' => 'nullable|integer',
            'encargos_pct' => 'nullable|numeric', 'pct_adm' => 'nullable|numeric',
            'pct_lucro' => 'nullable|numeric', 'pct_impostos' => 'nullable|numeric',
            'peric_pct' => 'nullable|numeric', 'intra_h' => 'nullable|numeric', 'desc_vt_pct' => 'nullable|numeric',
            'dias_mes' => 'nullable|numeric', 'horas_mes' => 'nullable|numeric',
            'beneficios' => 'nullable|array', 'meses' => 'nullable|integer',
        ]);

        return response()->json(['sucesso' => true, 'resultado' => $svc->calcular5Estrelas($in)]);
    }
}
