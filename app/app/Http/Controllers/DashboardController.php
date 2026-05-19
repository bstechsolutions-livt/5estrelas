<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\UserShortcut;
use App\Support\MenuCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Destaques ativos
        $highlights = Post::active()
            ->highlights()
            ->with('creator:id,name,avatar_path')
            ->withCount(['likes', 'comments'])
            ->with(['likes' => fn ($q) => $q->where('users.id', $user->id)])
            ->orderByDesc('published_at')
            ->limit(20)
            ->get()
            ->map(function (Post $p) use ($user) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'content' => $p->content,
                    'image_url' => $p->image_url,
                    'published_at' => $p->published_at,
                    'likes_count' => $p->likes_count,
                    'comments_count' => $p->comments_count,
                    'liked' => $p->likes->contains('id', $user->id),
                    'creator' => $p->creator ? [
                        'name' => $p->creator->name,
                        'avatar_url' => $p->creator->avatar_path ? \Illuminate\Support\Facades\Storage::url($p->creator->avatar_path) : null,
                    ] : null,
                ];
            });

        // Notícias ativas (primeira página)
        $news = Post::active()
            ->news()
            ->withCount(['likes', 'comments'])
            ->with(['likes' => fn ($q) => $q->where('users.id', $user->id)]) // só pra saber se o user curtiu
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(5);

        $news->getCollection()->transform(function (Post $p) use ($user) {
            return [
                'id' => $p->id,
                'title' => $p->title,
                'content' => $p->content,
                'image_url' => $p->image_url,
                'published_at' => $p->published_at,
                'likes_count' => $p->likes_count,
                'comments_count' => $p->comments_count,
                'liked' => $p->likes->contains('id', $user->id),
            ];
        });

        // Catálogo de menus disponíveis para o usuário
        $menuOptions = MenuCatalog::availableTo($user);

        // Atalhos do usuário (resolvendo via catálogo)
        $shortcutKeys = UserShortcut::where('user_id', $user->id)
            ->orderBy('position')
            ->pluck('menu_key')
            ->toArray();

        $shortcuts = collect($shortcutKeys)
            ->map(fn ($key) => MenuCatalog::findByKey($key))
            ->filter()
            ->values()
            ->all();

        return Inertia::render('Dashboard', [
            'highlights' => $highlights,
            'news' => $news,
            'shortcuts' => $shortcuts,
            'menuOptions' => $menuOptions,
        ]);
    }
}
