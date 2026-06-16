<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Models\Comercial\Cliente;
use Inertia\Inertia;

/**
 * Módulo Comercial — Contratos Ativos.
 * Visão tabular dos clientes com contrato ativo (porte do protótipo view-contratos).
 * Os dados vêm da tabela de clientes (situação ativo + postos + valor_mensal).
 */
class ComercialContratoController extends Controller
{
    public function index()
    {
        $contratos = Cliente::where('situacao', 'ativo')
            ->orderBy('nome')
            ->get()
            ->map(function (Cliente $c, int $i) {
                $postos = is_array($c->postos) ? $c->postos : [];
                $servicos = collect($postos)->pluck('tipo')->unique()->implode(', ') ?: '—';
                $qtdPostos = collect($postos)->sum('qtd') ?: $c->total_postos;
                $qtdFunc = collect($postos)->sum('colab') ?: $c->total_colaboradores;

                return [
                    'id' => $c->id,
                    'numero' => date('Y') . '-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                    'cliente' => $c->nome,
                    'servico' => $servicos,
                    'postos' => $qtdPostos,
                    'funcionarios' => $qtdFunc,
                    'custo_mensal' => (float) $c->valor_mensal,
                    'situacao' => $c->situacao,
                    'uf' => $c->uf,
                ];
            });

        return Inertia::render('Comercial/Contratos/Index', [
            'contratos' => $contratos,
        ]);
    }
}
