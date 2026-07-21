<?php

namespace App\Services;

use App\Models\CommentMention;
use App\Models\Notification;
use App\Models\PayableComment;
use App\Models\User;
use App\Events\NotificationCreated;

/**
 * Serviço de @menções em comentários.
 *
 * Responsabilidades:
 * - Extrair @menções do texto do comentário
 * - Criar registros de CommentMention
 * - Gerar notificações pro sininho
 * - Listar usuários mencionáveis (todos ativos, se tiver permissão)
 */
class MentionService
{
    public const PERMISSION_MENTION = 'financeiro.contas_pagar.mencionar';

    /**
     * Processa @menções no body de um comentário já criado.
     * Formato reconhecido: @[Nome do Usuário](id:123)
     * Só aplica se o autor tiver permissão de mencionar.
     */
    public function processComment(PayableComment $comment): void
    {
        $author = $comment->relationLoaded('user')
            ? $comment->user
            : User::find($comment->user_id);

        if (! $author || ! $this->canMention($author)) {
            return;
        }

        $mentions = $this->extractMentions($comment->body);

        foreach ($mentions as $userId) {
            // Não menciona a si mesmo
            if ($userId == $comment->user_id) continue;

            $mention = CommentMention::firstOrCreate([
                'payable_comment_id' => $comment->id,
                'mentioned_user_id' => $userId,
            ]);

            // Cria notificação
            if ($mention->wasRecentlyCreated) {
                $this->notify($comment, $userId);
            }
        }
    }

    public function canMention(User $user): bool
    {
        return $user->hasPermission('*') || $user->hasPermission(self::PERMISSION_MENTION);
    }

    /**
     * Extrai IDs de usuário das @menções no texto.
     * Formato: @[Nome](id:123) ou @[Nome](123)
     */
    public function extractMentions(string $body): array
    {
        $ids = [];
        // Formato: @[Qualquer Nome](id:123) ou @[Nome](123)
        if (preg_match_all('/@\[[^\]]+\]\((?:id:)?(\d+)\)/', $body, $matches)) {
            $ids = array_map('intval', $matches[1]);
        }
        return array_unique($ids);
    }

    /**
     * Lista usuários que podem ser mencionados.
     * Com permissão de mencionar: todos os usuários ativos (exceto o próprio).
     */
    public function mentionableUsers(User $currentUser, int $payableId): array
    {
        if (! $this->canMention($currentUser)) {
            return [];
        }

        return User::where('is_active', true)
            ->where('id', '!=', $currentUser->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
            ->values()
            ->all();
    }

    /**
     * Gera notificação para o usuário mencionado.
     */
    private function notify(PayableComment $comment, int $userId): void
    {
        $payable = $comment->payable;
        $sender = $comment->user;

        $notification = Notification::create([
            'user_id' => $userId,
            'title' => 'Você foi mencionado',
            'body' => ($sender->name ?? 'Alguém') . " mencionou você no título {$payable->title_number}",
            'type' => 'mention',
            'link' => "/financeiro/contas-pagar/{$payable->id}",
            'data' => [
                'payable_id' => $payable->id,
                'comment_id' => $comment->id,
                'sender_id' => $comment->user_id,
            ],
        ]);

        // Dispara evento pro WebSocket (sino em tempo real)
        if (class_exists(NotificationCreated::class)) {
            try { event(new NotificationCreated($notification)); } catch (\Throwable $e) {}
        }
    }
}
