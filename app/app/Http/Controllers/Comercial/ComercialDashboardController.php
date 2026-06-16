<?php

namespace App\Http\Controllers\Comercial;

use App\Http\Controllers\Controller;
use App\Models\Comercial\Cliente;
use App\Models\Comercial\Faturamento;
use App\Models\Comercial\Proposta;
use App\Models\Comercial\Reajuste;
use Inertia\Inertia;

/**
 * Módulo Comercial — Dashboard (porte do protótipo view-dashboard).
 * KPIs globais, split por empresa (SEG/APOIO), top clientes, funil e distribuição.
 */
class ComercialDashboardController extends Controller
{
    public function index()
    {
        $clientes = Cliente::all();
        $propostas = Proposta::all();
        $reajustes = Reajuste::all();

        $ativos = $clientes->where('situacao', 'ativo');
        $totalFat = $ativos->sum('valor_mensal');
        $emAnalise = $propostas->where('situacao', 'EM ANÁLISE');
        $aprovadas = $propostas->where('situacao', 'APROVADO');
        $rajPendentes = $reajustes->whereIn('status', ['pendente', 'calculado', 'enviado']);
        $taxaAprovacao = $propostas->count() > 0
            ? round($aprovadas->count() / $propostas->count() * 100, 1)
            : 0;

        // Split por empresa (seg* vs apoio*).
        $segClientes = $clientes->filter(fn ($c) => str_starts_with($c->observacao ?? '', 'Serviços: ') && !str_contains(strtolower($c->observacao ?? ''), 'portaria') && !str_contains(strtolower($c->observacao ?? ''), 'limpeza'));
        // Mais confiável: derivar da lista de propostas pela coluna empresa.
        $segPropostas = $propostas->filter(fn ($p) => str_starts_with($p->empresa ?? '', 'seg'));
        $apoioPropostas = $propostas->filter(fn ($p) => str_starts_with($p->empresa ?? '', 'apoio'));

        // Top clientes por faturamento (top 10).
        $topClientes = $ativos->sortByDesc('valor_mensal')->take(10)->values()->map(fn ($c) => [
            'id' => $c->id, 'nome' => $c->nome, 'valor_mensal' => (float) $c->valor_mensal,
            'situacao' => $c->situacao, 'uf' => $c->uf,
        ]);

        // Funil de propostas.
        $funil = [
            ['label' => 'Em Análise', 'count' => $emAnalise->count(), 'valor' => $emAnalise->sum('valor'), 'cor' => 'var(--blue)'],
            ['label' => 'Aprovadas', 'count' => $aprovadas->count(), 'valor' => $aprovadas->sum('valor'), 'cor' => 'var(--green)'],
            ['label' => 'Reprovadas', 'count' => $propostas->where('situacao', 'REPROVADO')->count(), 'valor' => $propostas->where('situacao', 'REPROVADO')->sum('valor'), 'cor' => 'var(--red)'],
            ['label' => 'Estimativa', 'count' => $propostas->where('situacao', 'ESTIMATIVA')->count(), 'valor' => $propostas->where('situacao', 'ESTIMATIVA')->sum('valor'), 'cor' => 'var(--orange)'],
        ];

        // Distribuição por UF.
        $distribuicao = $ativos->groupBy('uf')->map(fn ($g, $uf) => [
            'uf' => $uf ?: '—', 'count' => $g->count(), 'valor' => $g->sum('valor_mensal'),
        ])->sortByDesc('valor')->values()->toArray();

        return Inertia::render('Comercial/Dashboard/Index', [
            'kpis' => [
                'clientes_ativos' => $ativos->count(),
                'faturamento_mensal' => round($totalFat, 2),
                'anual_projetado' => round($totalFat * 12, 2),
                'propostas_analise' => $emAnalise->count(),
                'propostas_analise_valor' => round($emAnalise->sum('valor'), 2),
                'reajustes_pendentes' => $rajPendentes->count(),
                'taxa_aprovacao' => $taxaAprovacao,
            ],
            'split' => [
                'seg' => [
                    'clientes' => $segPropostas->pluck('cliente')->unique()->count() ?: '—',
                    'fat' => round($segPropostas->where('situacao', 'APROVADO')->sum('valor'), 2),
                    'propostas' => $segPropostas->count(),
                ],
                'apoio' => [
                    'clientes' => $apoioPropostas->pluck('cliente')->unique()->count() ?: '—',
                    'fat' => round($apoioPropostas->where('situacao', 'APROVADO')->sum('valor'), 2),
                    'propostas' => $apoioPropostas->count(),
                ],
            ],
            'topClientes' => $topClientes,
            'funil' => $funil,
            'distribuicao' => $distribuicao,
        ]);
    }
}
