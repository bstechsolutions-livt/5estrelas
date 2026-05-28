<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type', 'news');
        if (!in_array($type, ['highlight', 'news'], true)) {
            $type = 'news';
        }

        $perPage = (int) $request->input('per_page', 10);
        $page = (int) $request->input('page', 1);

        $posts = Post::query()
            ->where('type', $type)
            ->withCount(['likes', 'comments'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        $posts->getCollection()->transform(function (Post $p) {
            $p->setAppends(['image_url']);
            return $p;
        });

        if ($request->wantsJson() || $request->header('X-Json-Only') === '1') {
            return response()->json($posts);
        }

        return Inertia::render('Posts/Index', [
            'posts' => $posts,
            'filters' => ['type' => $type, 'per_page' => $perPage],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Posts/Form', [
            'mode' => 'create',
            'post' => null,
            'initialType' => $request->input('type', 'news'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        $post = Post::create([
            'type' => $data['type'],
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'published_at' => $data['published_at'] ?? now(),
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('image')) {
            $path = $this->storeImage($request->file('image'), $post);
            $post->image_path = $path;
            $post->save();
        }

        return redirect("/noticias?type={$post->type}")->with('success', 'Postagem criada com sucesso.');
    }

    public function edit(int $id)
    {
        $post = Post::findOrFail($id);
        $post->setAppends(['image_url']);

        return Inertia::render('Posts/Form', [
            'mode' => 'edit',
            'post' => $post,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $post = Post::findOrFail($id);
        $data = $this->validatePayload($request, $post);

        $post->fill([
            'type' => $data['type'],
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'published_at' => $data['published_at'] ?? $post->published_at,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $data['is_active'] ?? $post->is_active,
        ]);

        if ($request->hasFile('image')) {
            // remove old
            if ($post->image_path && Storage::disk('public')->exists($post->image_path)) {
                Storage::disk('public')->delete($post->image_path);
            }
            $path = $this->storeImage($request->file('image'), $post);
            $post->image_path = $path;
        }

        $post->save();

        return redirect("/noticias?type={$post->type}")->with('success', 'Postagem atualizada.');
    }

    public function toggleActive(int $id)
    {
        $post = Post::findOrFail($id);
        $post->is_active = !$post->is_active;
        $post->save();

        return back()->with('success', $post->is_active ? 'Postagem ativada.' : 'Postagem inativada.');
    }

    public function destroy(int $id)
    {
        $post = Post::findOrFail($id);

        if ($post->image_path && Storage::disk('public')->exists($post->image_path)) {
            Storage::disk('public')->delete($post->image_path);
        }

        $post->delete();

        return back()->with('success', 'Postagem excluída.');
    }

    private function validatePayload(Request $request, ?Post $post = null): array
    {
        $maxImageKb = $request->input('type') === Post::TYPE_HIGHLIGHT ? 5120 : 10240;

        return $request->validate([
            'type' => ['required', 'in:highlight,news'],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['nullable', 'string'],
            'image' => [
                $post && $post->image_path ? 'nullable' : ($request->input('type') === Post::TYPE_HIGHLIGHT ? 'required' : 'nullable'),
                'image',
                'mimes:jpg,jpeg,png,webp',
                "max:{$maxImageKb}",
            ],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:published_at'],
            'is_active' => ['boolean'],
        ]);
    }

    private function storeImage($file, Post $post): string
    {
        $ext = $file->getClientOriginalExtension();
        $filename = "posts/{$post->type}_{$post->id}_" . time() . ".{$ext}";
        Storage::disk('public')->putFileAs('', $file, $filename);
        return $filename;
    }
}
