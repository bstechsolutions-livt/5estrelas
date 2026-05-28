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

        return response()->json(['groups' => $groups, 'query' => $q]);
    }
}
