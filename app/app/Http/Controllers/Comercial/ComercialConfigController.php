<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Models\Comercial\Categoria;
use App\Models\Comercial\Cct;
use App\Models\Comercial\Encargo;
use App\Models\Comercial\Escala;
use App\Models\Comercial\Filial;
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
            'filiais' => Filial::orderBy('ordem')->orderBy('nome')->get(),
        ]);
    }

    // ─── Estados (criar UF com 4 CCTs padrão) ──────────────
    public function storeEstado(Request $request)
    {
        $request->validate([
            'uf' => 'required|string|size:2',
            'nome' => 'required|string|max:100',
        ]);

        $uf = strtolower($request->input('uf'));

        // Verifica se já existem CCTs com essa UF (case-insensitive)
        if (Cct::whereRaw('LOWER(uf) = ?', [$uf])->exists()) {
            return response()->json(['sucesso' => false, 'mensagem' => 'UF já cadastrada.'], 422);
        }

        $servicosPadrao = [
            ['servico' => 'vigilancia', 'titulo' => "CCT Vigilância — " . strtoupper($uf), 'tipo' => 'seg', 'icone' => '🛡️'],
            ['servico' => 'bombeiro', 'titulo' => "CCT Bombeiro Civil — " . strtoupper($uf), 'tipo' => 'seg', 'icone' => '🔥'],
            ['servico' => 'portaria', 'titulo' => "CCT Portaria e Recepção — " . strtoupper($uf), 'tipo' => 'apoio', 'icone' => '🏢'],
            ['servico' => 'limpeza', 'titulo' => "CCT Limpeza e Conservação — " . strtoupper($uf), 'tipo' => 'apoio', 'icone' => '🧹'],
        ];

        $ccts = [];
        foreach ($servicosPadrao as $s) {
            $ccts[] = Cct::create([
                'nome' => $s['titulo'],
                'titulo' => $s['titulo'],
                'servico' => $s['servico'],
                'tipo' => $s['tipo'],
                'icone' => $s['icone'],
                'uf' => $uf,
                'ano_base' => (string) date('Y'),
                'ativo' => true,
                'horas_mes' => 220,
                'dias_mes' => $s['tipo'] === 'seg' ? 15.5 : 22,
                'salario_base' => 0,
                'periculosidade_pct' => 0,
                'adicional_noturno_pct' => 0,
                'intrajornada_h' => 1.5,
                'desconto_vt_pct' => 6,
                'va' => 0, 'vt' => 0, 'plano_saude' => 0, 'fundo_social' => 0,
                'sst' => 0, 'cna' => 0, 'seguro_vida' => 0,
                'uniforme' => 0, 'reciclagem' => 0, 'gta' => 0, 'cofre' => 0, 'arma' => 0, 'colete' => 0,
            ]);
        }

        return response()->json(['sucesso' => true, 'ccts' => $ccts]);
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
            'tipo' => 'nullable|string|max:10',
            'icone' => 'nullable|string|max:50',
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

    // ─── Filiais / Empresas do grupo (espelhadas da Senior) ──────────────────
    // A fonte da verdade é a Senior (cad_filial). Não há criação/exclusão manual:
    // sincroniza-se da Senior e edita-se apenas os campos de apresentação local
    // (tipo/tag) e o flag de exibição (ativo).

    /** Dispara a sincronização das filiais com a Senior (read-only). */
    public function sincronizarFiliais()
    {
        $r = \App\Services\Senior\FiliaisSyncService::make()->run('manual');

        $mensagem = match ($r['status']) {
            'success' => "Sincronizado: {$r['inserted']} inseridas, {$r['updated']} atualizadas.",
            'skipped' => 'Integração Senior desabilitada — exibindo as empresas já cadastradas.',
            default => 'Falha ao sincronizar: ' . ($r['message'] ?? 'erro desconhecido'),
        };

        return response()->json([
            'sucesso' => $r['status'] !== 'failed',
            'status' => $r['status'],
            'mensagem' => $mensagem,
            'filiais' => Filial::orderBy('ordem')->orderBy('nome')->get(),
        ]);
    }

    /** Edita apenas os campos de apresentação local (tipo, tag, ativo). */
    public function updateFilial(Request $request, $id)
    {
        $filial = Filial::findOrFail($id);

        $data = $request->validate([
            'tag' => 'nullable|string|max:50',
            'tipo' => ['nullable', 'in:'.implode(',', Filial::tiposValidos())],
            'ativo' => 'boolean',
            'ordem' => 'nullable|integer',
        ]);

        $filial->update($data);

        return response()->json(['sucesso' => true, 'dados' => $filial]);
    }

    /** Ativa/desativa a exibição da filial nos seletores do Comercial. */
    public function toggleFilial($id)
    {
        $filial = Filial::findOrFail($id);
        $filial->update(['ativo' => ! $filial->ativo]);

        return response()->json(['sucesso' => true, 'dados' => $filial]);
    }
}
