<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SearchController extends Controller
{
    /**
     * Busca global cross-entidade (users, posts).
     * Usa LIKE/ILIKE simples — em produção, substituir por Meilisearch quando crescer.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $user = $request->user();

        if (mb_strlen($q) < 2) {
            return response()->json(['groups' => []]);
        }

        $groups = [];

        // Usuários
        if ($user->hasPermission('usuarios.listar')) {
            $users = User::query()
                ->where(function ($qq) use ($q) {
                    $qq->where('name', 'ilike', "%{$q}%")
                        ->orWhere('email', 'ilike', "%{$q}%");
                })
                ->limit(5)
                ->get(['id', 'name', 'email', 'avatar_path'])
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'title' => $u->name,
                    'subtitle' => $u->email,
                    'icon' => 'pi pi-user',
                    'avatar_url' => $u->avatar_path ? Storage::url($u->avatar_path) : null,
                    'href' => "/usuarios/{$u->id}/editar",
                ]);

            if ($users->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Usuários',
                    'items' => $users,
                ];
            }
        }

        // Posts (destaques + notícias) — se pode gerenciar
        $posts = Post::query()
            ->where('is_active', true)
            ->where(function ($qq) use ($q) {
                $qq->where('title', 'ilike', "%{$q}%")
                    ->orWhere('content', 'ilike', "%{$q}%");
            })
            ->limit(5)
            ->get(['id', 'title', 'type', 'image_path']);

        if ($posts->isNotEmpty()) {
            $groups[] = [
                'label' => 'Notícias e destaques',
                'items' => $posts->map(fn ($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'subtitle' => $p->type === 'highlight' ? 'Destaque' : 'Notícia',
                    'icon' => $p->type === 'highlight' ? 'pi pi-star' : 'pi pi-megaphone',
                    'avatar_url' => $p->image_path ? Storage::url($p->image_path) : null,
                    'href' => $user->hasPermission('noticias.gerenciar') ? "/noticias/{$p->id}/editar" : '/dashboard',
                ]),
            ];
        }

        // Páginas do sistema
        $pageMatches = collect(\App\Support\MenuCatalog::availableTo($user))
            ->filter(fn ($m) => str_contains(mb_strtolower($m['label']), mb_strtolower($q)))
            ->take(5)
            ->values()
            ->map(fn ($m) => [
                'id' => "page-{$m['key']}",
                'title' => $m['label'],
                'subtitle' => 'Página do sistema',
                'icon' => $m['icon'],
                'href' => $m['href'],
            ]);

        if ($pageMatches->isNotEmpty()) {
            $groups[] = [
                'label' => 'Páginas',
                'items' => $pageMatches,
            ];
        }

        // Departamentos
        if ($user->hasPermission('departamentos.gerenciar')) {
            $depts = \App\Models\Department::query()
                ->where('name', 'ilike', "%{$q}%")
                ->limit(5)
                ->get(['id', 'name'])
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'title' => $d->name,
                    'subtitle' => 'Departamento',
                    'icon' => 'pi pi-building',
                    'href' => "/departamentos/{$d->id}/editar",
                ]);

            if ($depts->isNotEmpty()) {
                $groups[] = ['label' => 'Departamentos', 'items' => $depts];
            }
        }

        // Filiais
        if ($user->hasPermission('filiais.gerenciar')) {
            $branches = \App\Models\Branch::query()
                ->where(function ($qq) use ($q) {
                    $qq->where('name', 'ilike', "%{$q}%")
                        ->orWhere('cnpj', 'like', "%{$q}%");
                })
                ->limit(5)
                ->get(['id', 'name', 'cnpj'])
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'title' => $b->name,
                    'subtitle' => $b->cnpj ? "CNPJ: {$b->cnpj}" : 'Filial',
                    'icon' => 'pi pi-map-marker',
                    'href' => "/filiais/{$b->id}/editar",
                ]);

            if ($branches->isNotEmpty()) {
                $groups[] = ['label' => 'Filiais', 'items' => $branches];
            }
        }

        // Contas a Pagar
        if ($user->hasPermission('financeiro.contas_pagar.visualizar')) {
            $payables = \App\Models\Payable::query()
                ->where(function ($qq) use ($q) {
                    $qq->where('supplier_name', 'ilike', "%{$q}%")
                        ->orWhere('title_number', 'ilike', "%{$q}%");
                })
                ->limit(5)
                ->get(['id', 'supplier_name', 'title_number', 'amount', 'status'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'title' => $p->supplier_name,
                    'subtitle' => ($p->title_number ?? '') . ' · R$ ' . number_format($p->amount, 2, ',', '.'),
                    'icon' => 'pi pi-wallet',
                    'href' => "/financeiro/contas-pagar/{$p->id}",
                ]);

            if ($payables->isNotEmpty()) {
                $groups[] = ['label' => 'Contas a Pagar', 'items' => $payables];
            }

            // Borderôs
            $borderos = \App\Models\Bordero::query()
                ->where(function ($qq) use ($q) {
                    $qq->where('number', 'ilike', "%{$q}%")
                        ->orWhere('description', 'ilike', "%{$q}%");
                })
                ->limit(5)
                ->get(['id', 'number', 'description', 'total_amount', 'items_count'])
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'title' => $b->number,
                    'subtitle' => ($b->items_count ?? 0) . ' títulos · R$ ' . number_format($b->total_amount, 2, ',', '.'),
                    'icon' => 'pi pi-list-check',
                    'href' => "/financeiro/borderos/{$b->id}",
                ]);

            if ($borderos->isNotEmpty()) {
                $groups[] = ['label' => 'Borderôs', 'items' => $borderos];
            }
        }

        // Gestão de Contratos
        if ($user->hasPermission('contratos.visualizar')) {
            $contratos = \App\Models\v2\BsGestaoContrato::query()
                ->where(function ($qq) use ($q) {
                    $qq->where('numero_contrato', 'ilike', "%{$q}%")
                        ->orWhere('nome_locador', 'ilike', "%{$q}%")
                        ->orWhere('razao_social_loja', 'ilike', "%{$q}%")
                        ->orWhere('tipo_servico', 'ilike', "%{$q}%");
                })
                ->limit(5)
                ->get(['id', 'tipo', 'numero_contrato', 'nome_locador', 'razao_social_loja', 'tipo_servico', 'valor_mensal'])
                ->map(function ($c) {
                    $titulo = $c->numero_contrato ?: ($c->tipo === 'LOCACAO' ? ($c->razao_social_loja ?: 'Contrato de locação') : ($c->tipo_servico ?: 'Contrato de serviço'));
                    $sub = match ($c->tipo) {
                        'LOCACAO' => 'Locação',
                        'SERVICO_PRESTADO' => 'Serviço prestado',
                        default => 'Serviço contratado',
                    };
                    if ($c->nome_locador) {
                        $sub .= ' · ' . $c->nome_locador;
                    }
                    $rota = match ($c->tipo) {
                        'LOCACAO' => 'locacao',
                        'SERVICO_PRESTADO' => 'servicos-prestados',
                        default => 'servicos',
                    };
                    return [
                        'id' => $c->id,
                        'title' => $titulo,
                        'subtitle' => $sub,
                        'icon' => $c->tipo === 'LOCACAO' ? 'pi pi-building' : 'pi pi-briefcase',
                        'href' => "/pagina/gestao-contratos/{$rota}/{$c->id}",
                    ];
                });

            if ($contratos->isNotEmpty()) {
                $groups[] = ['label' => 'Contratos', 'items' => $contratos];
            }

            // Alvarás
            $alvaras = \App\Models\v2\BsGestaoAlvara::query()
                ->where(function ($qq) use ($q) {
                    $qq->where('numero_documento', 'ilike', "%{$q}%")
                        ->orWhere('descricao', 'ilike', "%{$q}%")
                        ->orWhere('orgao_emissor', 'ilike', "%{$q}%");
                })
                ->limit(5)
                ->get(['id', 'numero_documento', 'descricao', 'orgao_emissor'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->numero_documento ?: 'Alvará',
                    'subtitle' => $a->orgao_emissor ?: ($a->descricao ?: 'Alvará/Licença'),
                    'icon' => 'pi pi-id-card',
                    'href' => "/pagina/gestao-contratos/alvaras/{$a->id}",
                ]);

            if ($alvaras->isNotEmpty()) {
                $groups[] = ['label' => 'Alvarás', 'items' => $alvaras];
            }

            // Equipamentos
            $equipamentos = \App\Models\v2\BsGestaoEquipamento::query()
                ->with('tipoEquipamento:id,nome')
                ->where(function ($qq) use ($q) {
                    $qq->where('numero_identificacao', 'ilike', "%{$q}%")
                        ->orWhere('localizacao', 'ilike', "%{$q}%")
                        ->orWhere('carga', 'ilike', "%{$q}%");
                })
                ->limit(5)
                ->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'title' => $e->numero_identificacao ?: ('Equipamento #' . $e->id),
                    'subtitle' => trim(($e->tipoEquipamento->nome ?? 'Equipamento') . ($e->localizacao ? ' · ' . $e->localizacao : '')),
                    'icon' => 'pi pi-box',
                    'href' => "/pagina/gestao-contratos/equipamentos/{$e->id}",
                ]);

            if ($equipamentos->isNotEmpty()) {
                $groups[] = ['label' => 'Equipamentos', 'items' => $equipamentos];
            }

            // Tipos de Equipamento (têm tela de cadastro própria)
            $tiposEquip = \App\Models\v2\BsGestaoTipoEquipamento::query()
                ->where('nome', 'ilike', "%{$q}%")
                ->limit(5)
                ->get(['id', 'nome'])
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'title' => $t->nome,
                    'subtitle' => 'Tipo de equipamento',
                    'icon' => 'pi pi-tags',
                    'href' => '/pagina/gestao-contratos/equipamentos/tipos',
                ]);

            if ($tiposEquip->isNotEmpty()) {
                $groups[] = ['label' => 'Tipos de Equipamento', 'items' => $tiposEquip];
            }
        }

        // Solicitações
        if ($user->hasPermission('solicitacoes.visualizar')) {
            $solicitacoes = \App\Models\Solicitacao::query()
                ->where(function ($qq) use ($q) {
                    $qq->where('titulo', 'ilike', "%{$q}%")
                        ->orWhere('descricao', 'ilike', "%{$q}%");
                })
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'titulo', 'status', 'assunto_id'])
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'title' => $s->titulo ?: ('Solicitação #' . $s->id),
                    'subtitle' => 'Solicitação · ' . ucfirst((string) $s->status),
                    'icon' => 'pi pi-inbox',
                    'href' => '/solicitacoes/lista?solicitacao=' . $s->id,
                ]);

            if ($solicitacoes->isNotEmpty()) {
                $groups[] = ['label' => 'Solicitações', 'items' => $solicitacoes];
            }

            // Assuntos de solicitação (têm tela de configuração própria)
            $assuntos = \App\Models\SolicitacaoAssunto::query()
                ->where('assunto', 'ilike', "%{$q}%")
                ->where('ativo', 'S')
                ->limit(5)
                ->get(['id', 'assunto'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->assunto,
                    'subtitle' => 'Assunto de solicitação',
                    'icon' => 'pi pi-tag',
                    'href' => '/solicitacoes/configuracoes',
                ]);

            if ($assuntos->isNotEmpty()) {
                $groups[] = ['label' => 'Assuntos de Solicitação', 'items' => $assuntos];
            }
        }

        return response()->json(['groups' => $groups, 'query' => $q]);
    }
}
