<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Events\NotificationCreated;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Envia uma notificação para um único usuário.
     */
    public static function send(
        User|int $user,
        string $title,
        ?string $message = null,
        ?string $link = null,
        string $type = Notification::TYPE_INFO,
        ?string $icon = null,
        ?array $metadata = null,
    ): Notification {
        $userId = $user instanceof User ? $user->id : $user;

        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'icon' => $icon,
            'metadata' => $metadata,
        ]);

        AuditLogger::log(
            event: 'notificacoes.sent',
            module: 'notificacoes',
            description: "Notificação \"{$title}\" enviada para user_id={$userId}",
            auditable: $notification,
            newValues: [
                'user_id' => $userId,
                'title' => $title,
                'type' => $type,
            ],
        );

        // Dispara em tempo real via Reverb (silencioso em caso de erro de broadcast)
        try {
            event(new NotificationCreated($notification));
        } catch (\Throwable $e) {
            // Loga mas não impede a criação da notificação
            \Log::warning('Falha ao broadcastar notificação: ' . $e->getMessage());
        }

        // Dispara push via FCM (stub por enquanto, ativa via FCM_ENABLED=true)
        try {
            FcmService::sendToUser($userId, $notification);
        } catch (\Throwable $e) {
            \Log::warning('Falha ao enviar FCM: ' . $e->getMessage());
        }

        return $notification;
    }

    /**
     * Envia a mesma notificação para vários usuários.
     */
    public static function sendMany(
        Collection|array $users,
        string $title,
        ?string $message = null,
        ?string $link = null,
        string $type = Notification::TYPE_INFO,
        ?string $icon = null,
        ?array $metadata = null,
    ): Collection {
        $list = collect($users);
        $created = collect();

        foreach ($list as $user) {
            $created->push(self::send($user, $title, $message, $link, $type, $icon, $metadata));
        }

        return $created;
    }

    /**
     * Envia para todos os usuários ativos.
     */
    public static function broadcast(
        string $title,
        ?string $message = null,
        ?string $link = null,
        string $type = Notification::TYPE_INFO,
        ?string $icon = null,
        ?array $metadata = null,
    ): int {
        $count = 0;
        User::where('is_active', true)->chunk(100, function ($users) use (
            &$count,
            $title,
            $message,
            $link,
            $type,
            $icon,
            $metadata
        ) {
            foreach ($users as $u) {
                self::send($u, $title, $message, $link, $type, $icon, $metadata);
                $count++;
            }
        });

        return $count;
    }
}
