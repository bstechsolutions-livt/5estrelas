<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Lista as notificações do usuário autenticado.
     * Query: ?limit=20&unread_only=1&since=ISO8601
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $limit = (int) $request->input('limit', 20);
        $limit = max(1, min($limit, 100));

        $query = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        if ($since = $request->input('since')) {
            $query->where('created_at', '>', $since);
        }

        // Paginação via cursor: passa o id do último item já carregado
        if ($beforeId = $request->input('before_id')) {
            $cursor = Notification::where('user_id', $user->id)->find((int) $beforeId);
            if ($cursor) {
                $query->where(function ($q) use ($cursor) {
                    $q->where('created_at', '<', $cursor->created_at)
                        ->orWhere(function ($q2) use ($cursor) {
                            $q2->where('created_at', '=', $cursor->created_at)
                                ->where('id', '<', $cursor->id);
                        });
                });
            }
        }

        $items = $query->limit($limit)->get();

        $unreadCount = Notification::where('user_id', $user->id)->whereNull('read_at')->count();

        return response()->json([
            'items' => $items,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Devolve apenas o contador (uso pra badge).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    /**
     * Marca uma notificação como lida.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        if (!$notification->isRead()) {
            $notification->markAsRead();
        }

        return response()->json([
            'ok' => true,
            'notification' => $notification->fresh(),
        ]);
    }

    /**
     * Marca todas como lidas.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $affected = Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($affected > 0) {
            AuditLogger::log(
                event: 'notificacoes.all_read',
                module: 'notificacoes',
                description: "Marcou {$affected} notificação(ões) como lida(s)",
                metadata: ['affected' => $affected],
            );
        }

        return response()->json(['ok' => true, 'affected' => $affected]);
    }

    /**
     * Exclui uma notificação.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->delete();

        return response()->json(['ok' => true]);
    }
}
