<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Models\Comercial\Categoria;
use App\Models\Comercial\Cct;
use App\Models\Comercial\Escala;
use App\Models\Comercial\Indice;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Módulo Comercial — Configuração / Valores (Spec 1).
 * Gerencia CCTs, categorias profissionais, escalas e índices globais
 * que alimentam o cálculo da planilha de custos (IN 05).
 */
class ComercialConfigController extends Controller
{
    public function index()
    {
        return Inertia::render('Comercial/Configuracoes/Index');
    }

    // ─── Dados (carga inicial) ───────────────────────────────
    public function dados()
    {
        return response()->json([
            'ccts' => Cct::orderBy('nome')->get(),
            'categorias' => Categoria::with('cct:id,nome')->orderBy('nome')->get(),
            'escalas' => Escala::orderBy('nome')->get(),
            'indices' => Indice::orderBy('chave')->get(),
        ]);
    }

    // ─── CCT ─────────────────────────────────────────────────
    public function storeCct(Request $request)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'sindicato' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:2',
            'ano_base' => 'nullable|string|max:50',
            'data_base' => 'nullable|date',
            'vigencia_inicio' => 'nullable|date',
            'vigencia_fim' => 'nullable|date',
            'ativo' => 'boolean',
            'observacao' => 'nullable|string',
        ]);

        $cct = Cct::create($data);

        return response()->json(['sucesso' => true, 'dados' => $cct]);
    }

    public function updateCct(Request $request, $id)
    {
        $cct = Cct::findOrFail($id);
        $cct->update($request->only([
            'nome', 'sindicato', 'uf', 'ano_base', 'data_base',
            'vigencia_inicio', 'vigencia_fim', 'ativo', 'observacao',
        ]));

        return response()->json(['sucesso' => true, 'dados' => $cct]);
    }

    public function destroyCct($id)
    {
        Cct::findOrFail($id)->delete();

        return response()->json(['sucesso' => true]);
    }

    // ─── Categorias ──────────────────────────────────────────
    public function storeCategoria(Request $request)
    {
        $data = $this->validarCategoria($request);
        $cat = Categoria::create($data);

        return response()->json(['sucesso' => true, 'dados' => $cat]);
    }

    public function updateCategoria(Request $request, $id)
    {
        $cat = Categoria::findOrFail($id);
        $cat->update($this->validarCategoria($request));

        return response()->json(['sucesso' => true, 'dados' => $cat]);
    }

    public function destroyCategoria($id)
    {
        Categoria::findOrFail($id)->delete();

        return response()->json(['sucesso' => true]);
    }

    private function validarCategoria(Request $request): array
    {
        return $request->validate([
            'cct_id' => 'nullable|exists:bs_comercial_ccts,id',
            'nome' => 'required|string|max:255',
            'cbo' => 'nullable|string|max:50',
            'icone' => 'nullable|string|max:50',
            'cor' => 'nullable|string|max:20',
            'salario_base' => 'numeric',
            'periculosidade_pct' => 'numeric',
            'intrajornada_h' => 'numeric',
            'desconto_vt_pct' => 'numeric',
            'va' => 'numeric',
            'vt' => 'numeric',
            'plano_saude' => 'numeric',
            'fundo_social' => 'numeric',
            'sst' => 'numeric',
            'cna' => 'numeric',
            'seguro_vida' => 'numeric',
            'uniforme' => 'numeric',
            'reciclagem' => 'numeric',
            'gta' => 'numeric',
            'cofre' => 'numeric',
            'arma' => 'numeric',
            'colete' => 'numeric',
            'tem_arma' => 'boolean',
            'tem_moto' => 'boolean',
            'ativo' => 'boolean',
        ]);
    }

    // ─── Escalas ─────────────────────────────────────────────
    public function storeEscala(Request $request)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'dias_mes' => 'numeric',
            'horas_mes' => 'numeric',
            'ativo' => 'boolean',
        ]);
        $escala = Escala::create($data);

        return response()->json(['sucesso' => true, 'dados' => $escala]);
    }

    public function updateEscala(Request $request, $id)
    {
        $escala = Escala::findOrFail($id);
        $escala->update($request->only(['nome', 'dias_mes', 'horas_mes', 'ativo']));

        return response()->json(['sucesso' => true, 'dados' => $escala]);
    }

    public function destroyEscala($id)
    {
        Escala::findOrFail($id)->delete();

        return response()->json(['sucesso' => true]);
    }

    // ─── Índices ─────────────────────────────────────────────
    public function salvarIndices(Request $request)
    {
        $indices = $request->input('indices', []);
        foreach ($indices as $item) {
            if (empty($item['chave'])) {
                continue;
            }
            Indice::updateOrCreate(
                ['chave' => $item['chave']],
                ['valor' => $item['valor'] ?? 0, 'descricao' => $item['descricao'] ?? null],
            );
        }

        return response()->json(['sucesso' => true, 'dados' => Indice::orderBy('chave')->get()]);
    }
}
