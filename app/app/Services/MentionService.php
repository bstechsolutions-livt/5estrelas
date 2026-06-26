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
 * - Listar usuários "mencionáveis" (mesmo dept + participantes do processo)
 */
class MentionService
{
    /**
     * Processa @menções no body de um comentário já criado.
     * Formato reconhecido: @[Nome do Usuário](id:123)
     */
    public function processComment(PayableComment $comment): void
    {
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
     * Lista usuários que podem ser mencionados neste payable.
     * Regras:
     * - Pessoas do mesmo departamento do usuário logado
     * - Pessoas que já participaram do processo (comentaram, aprovaram, criaram)
     * - Com permissão especial: qualquer pessoa ativa
     */
    public function mentionableUsers(User $currentUser, int $payableId): array
    {
        $query = User::where('is_active', true)->where('id', '!=', $currentUser->id);

        // Se o usuário tem permissão ampla, retorna todos
        if ($currentUser->hasPermission('*') || $currentUser->hasPermission('financeiro.contas_pagar.mencionar_todos')) {
            return $query->orderBy('name')
                ->get(['id', 'name', 'email', 'department_id'])
                ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
                ->values()->all();
        }

        // Senão: mesmo departamento + participantes do processo
        $deptId = $currentUser->department_id;
        $participantIds = collect();

        // Quem já comentou neste payable
        $commenters = PayableComment::where('payable_id', $payableId)->pluck('user_id');
        $participantIds = $participantIds->merge($commenters);

        // Quem já foi mencionado
        $mentioned = CommentMention::whereHas('comment', fn($q) => $q->where('payable_id', $payableId))
            ->pluck('mentioned_user_id');
        $participantIds = $participantIds->merge($mentioned);

        $query->where(function ($q) use ($deptId, $participantIds) {
            if ($deptId) {
                $q->where('department_id', $deptId);
            }
            if ($participantIds->isNotEmpty()) {
                $q->orWhereIn('id', $participantIds->unique());
            }
        });

        return $query->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
            ->values()->all();
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
            event(new NotificationCreated($notification));
        }
    }
}
