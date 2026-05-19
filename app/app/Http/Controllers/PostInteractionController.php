<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostComment;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostInteractionController extends Controller
{
    public function toggleLike(int $id, Request $request): JsonResponse
    {
        $post = Post::findOrFail($id);
        $userId = $request->user()->id;

        $exists = $post->likes()->where('user_id', $userId)->exists();

        if ($exists) {
            $post->likes()->detach($userId);
            AuditLogger::log(
                event: 'noticias.post.unliked',
                module: 'noticias',
                description: "Removeu curtida em \"{$post->title}\"",
                auditable: $post,
            );
            $liked = false;
        } else {
            $post->likes()->attach($userId);
            AuditLogger::log(
                event: 'noticias.post.liked',
                module: 'noticias',
                description: "Curtiu \"{$post->title}\"",
                auditable: $post,
            );
            $liked = true;
        }

        return response()->json([
            'liked' => $liked,
            'likes_count' => $post->likes()->count(),
        ]);
    }

    public function comments(int $id, Request $request): JsonResponse
    {
        $post = Post::findOrFail($id);

        $comments = $post->comments()
            ->with('user:id,name,avatar_path')
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($comments);
    }

    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $page = (int) $request->input('page', 1);

        $news = Post::active()
            ->news()
            ->withCount(['likes', 'comments'])
            ->with(['likes' => fn ($q) => $q->where('users.id', $user->id)])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(5, ['*'], 'page', $page);

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

        return response()->json($news);
    }

    public function storeComment(int $id, Request $request): JsonResponse
    {
        $post = Post::findOrFail($id);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ]);

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

        $comment->load('user:id,name,avatar_path');

        return response()->json([
            'comment' => $comment,
            'comments_count' => $post->comments()->count(),
        ]);
    }

    public function destroyComment(int $postId, int $commentId, Request $request): JsonResponse
    {
        $comment = PostComment::where('post_id', $postId)
            ->where('id', $commentId)
            ->firstOrFail();

        if ($comment->user_id !== $request->user()->id && !$request->user()->hasPermission('noticias.gerenciar')) {
            abort(403, 'Você só pode excluir seus próprios comentários.');
        }

        $comment->delete();

        $count = PostComment::where('post_id', $postId)->count();

        return response()->json([
            'deleted' => true,
            'comments_count' => $count,
        ]);
    }
}
