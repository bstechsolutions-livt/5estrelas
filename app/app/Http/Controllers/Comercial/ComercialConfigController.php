<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Models\Comercial\Categoria;
use App\Models\Comercial\Cct;
use App\Models\Comercial\Encargo;
use App\Models\Comercial\Escala;
use App\Models\Comercial\Indice;
use App\Models\Comercial\Insumo;
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
            'encargos' => Encargo::orderBy('ordem')->get(),
            'insumos' => Insumo::orderBy('ordem')->get(),
        ]);
    }

    // ─── CCT ─────────────────────────────────────────────────
    public function storeCct(Request $request)
    {
        $cct = Cct::create($this->dadosCct($request));

        return response()->json(['sucesso' => true, 'dados' => $cct]);
    }

    public function updateCct(Request $request, $id)
    {
        $cct = Cct::findOrFail($id);
        $cct->update($this->dadosCct($request));

        return response()->json(['sucesso' => true, 'dados' => $cct]);
    }

    private function dadosCct(Request $request): array
    {
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'titulo' => 'nullable|string|max:255',
            'servico' => 'nullable|string|max:50',
            'sindicato' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:2',
            'ano_base' => 'nullable|string|max:50',
            'data_base' => 'nullable|date',
            'vigencia_inicio' => 'nullable|date',
            'vigencia_fim' => 'nullable|date',
            'ativo' => 'boolean',
            'observacao' => 'nullable|string',
            'horas_mes' => 'nullable|numeric',
            'dias_mes' => 'nullable|numeric',
            'salario_base' => 'nullable|numeric',
            'periculosidade_pct' => 'nullable|numeric',
            'adicional_noturno_pct' => 'nullable|numeric',
            'intrajornada_h' => 'nullable|numeric',
            'desconto_vt_pct' => 'nullable|numeric',
            'va' => 'nullable|numeric',
            'vt' => 'nullable|numeric',
            'plano_saude' => 'nullable|numeric',
            'fundo_social' => 'nullable|numeric',
            'sst' => 'nullable|numeric',
            'cna' => 'nullable|numeric',
            'seguro_vida' => 'nullable|numeric',
            'uniforme' => 'nullable|numeric',
            'reciclagem' => 'nullable|numeric',
            'gta' => 'nullable|numeric',
            'cofre' => 'nullable|numeric',
            'arma' => 'nullable|numeric',
            'colete' => 'nullable|numeric',
        ]);

        return $data;
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
        $escala = Escala::create($this->dadosEscala($request));

        return response()->json(['sucesso' => true, 'dados' => $escala]);
    }

    public function updateEscala(Request $request, $id)
    {
        $escala = Escala::findOrFail($id);
        $escala->update($this->dadosEscala($request));

        return response()->json(['sucesso' => true, 'dados' => $escala]);
    }

    private function dadosEscala(Request $request): array
    {
        return $request->validate([
            'nome' => 'required|string|max:255',
            'dias_mes' => 'numeric',
            'horas_mes' => 'numeric',
            'qtd_diurno' => 'nullable|integer',
            'qtd_noturno' => 'nullable|integer',
            'func_por_posto' => 'nullable|integer',
            'tem_an' => 'boolean',
            'jornada' => 'nullable|string|max:255',
            'ativo' => 'boolean',
        ]);
    }

    public function destroyEscala($id)
    {
        Escala::findOrFail($id)->delete();

        return response()->json(['sucesso' => true]);
    }

    // ─── Insumos (global) ────────────────────────────────────
    public function salvarInsumos(Request $request)
    {
        $insumos = $request->input('insumos', []);
        foreach ($insumos as $item) {
            if (empty($item['id'])) {
                continue;
            }
            Insumo::where('id', $item['id'])->update(['valor' => $item['valor'] ?? 0]);
        }

        return response()->json(['sucesso' => true, 'insumos' => Insumo::orderBy('ordem')->get()]);
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

    // ─── Encargos (detalhamento A/B/C/D) ─────────────────────
    public function salvarEncargos(Request $request)
    {
        $encargos = $request->input('encargos', []);
        foreach ($encargos as $item) {
            if (empty($item['id'])) {
                continue;
            }
            Encargo::where('id', $item['id'])->update([
                'percentual' => $item['percentual'] ?? 0,
            ]);
        }

        // Atualiza o índice total de encargos com o somatório
        Indice::updateOrCreate(
            ['chave' => 'encargos'],
            ['valor' => Encargo::totalGeral(), 'descricao' => 'Encargos sociais (%)'],
        );

        return response()->json([
            'sucesso' => true,
            'encargos' => Encargo::orderBy('ordem')->get(),
            'total' => Encargo::totalGeral(),
        ]);
    }
}
