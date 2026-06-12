<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Models\Comercial\Categoria;
use App\Models\Comercial\Cct;
use App\Models\Comercial\Escala;
use App\Models\Comercial\Indice;
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
    public function index()
    {
        return Inertia::render('Comercial/Cotacao/Index');
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
}
