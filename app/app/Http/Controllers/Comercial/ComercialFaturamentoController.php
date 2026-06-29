<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Models\Comercial\Cliente;
use App\Models\Comercial\Faturamento;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Módulo Comercial — Faturamento mensal por local/contrato.
 * Tabela editável com seleção de ano, KPIs e modo comparativo.
 */
class ComercialFaturamentoController extends Controller
{
    /** Tela principal (Inertia). */
    public function index()
    {
        return Inertia::render('Comercial/Faturamento/Index', [
            'dados' => $this->montarDados(),
            'clientes' => Cliente::orderBy('nome')->get(['id', 'nome', 'situacao']),
        ]);
    }

    /** JSON com dados de faturamento. */
    public function dados()
    {
        return response()->json($this->montarDados());
    }

    /** Salvar (upsert) linhas de faturamento de um ano. */
    public function salvar(Request $request)
    {
        $data = $request->validate([
            'ano' => 'required|integer|min:2020|max:2099',
            'locais' => 'required|array',
            'locais.*.id' => 'nullable|integer',
            'locais.*.local_nome' => 'required|string|max:255',
            'locais.*.jan' => 'nullable|numeric',
            'locais.*.fev' => 'nullable|numeric',
            'locais.*.mar' => 'nullable|numeric',
            'locais.*.abr' => 'nullable|numeric',
            'locais.*.mai' => 'nullable|numeric',
            'locais.*.jun' => 'nullable|numeric',
            'locais.*.jul' => 'nullable|numeric',
            'locais.*.ago' => 'nullable|numeric',
            'locais.*.setembro' => 'nullable|numeric',
            'locais.*.out' => 'nullable|numeric',
            'locais.*.nov' => 'nullable|numeric',
            'locais.*.dez' => 'nullable|numeric',
            'locais.*.cliente_id' => 'nullable|integer',
        ]);

        $ano = $data['ano'];

        foreach ($data['locais'] as $local) {
            $valores = [];
            foreach (Faturamento::MESES as $mes) {
                $valores[$mes] = round((float) ($local[$mes] ?? 0), 2);
            }

            Faturamento::updateOrCreate(
                ['ano' => $ano, 'local_nome' => $local['local_nome']],
                array_merge($valores, [
                    'cliente_id' => $local['cliente_id'] ?? null,
                ])
            );
        }

        return response()->json(['sucesso' => true, 'mensagem' => 'Faturamento salvo com sucesso.']);
    }

    /** Adicionar uma linha (local) zerada para o ano. */
    public function adicionarLocal(Request $request)
    {
        $data = $request->validate([
            'ano' => 'required|integer|min:2020|max:2099',
            'local_nome' => 'required|string|max:255',
            'cliente_id' => 'nullable|integer|exists:bs_comercial_clientes,id',
        ]);

        // Verifica se já existe
        $existente = Faturamento::where('ano', $data['ano'])
            ->where('local_nome', $data['local_nome'])
            ->first();

        if ($existente) {
            return response()->json(['sucesso' => false, 'mensagem' => 'Local já existe para este ano.'], 422);
        }

        $faturamento = Faturamento::create([
            'ano' => $data['ano'],
            'local_nome' => $data['local_nome'],
            'cliente_id' => $data['cliente_id'] ?? null,
        ]);

        return response()->json(['sucesso' => true, 'id' => $faturamento->id]);
    }

    /** Excluir uma linha de faturamento. */
    public function excluirLocal(int $id)
    {
        $faturamento = Faturamento::findOrFail($id);
        $faturamento->delete();

        return response()->json(['sucesso' => true]);
    }

    // ─── Privado ─────────────────────────────────────────────────────────────────

    /** Monta a estrutura { 2025: { locais: [...] }, 2026: { locais: [...] } } */
    private function montarDados(): array
    {
        $anos = [2025, 2026];
        $resultado = [];

        foreach ($anos as $ano) {
            $locais = Faturamento::where('ano', $ano)
                ->orderBy('local_nome')
                ->get()
                ->map(function (Faturamento $f) {
                    $item = [
                        'id' => $f->id,
                        'local_nome' => $f->local_nome,
                        'cliente_id' => $f->cliente_id,
                    ];
                    foreach (Faturamento::MESES as $mes) {
                        $item[$mes] = (float) $f->{$mes};
                    }
                    $item['total'] = $f->total();
                    return $item;
                });

            $resultado[$ano] = ['locais' => $locais->toArray()];
        }

        return $resultado;
    }
}
