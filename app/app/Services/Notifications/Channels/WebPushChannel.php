<?php

namespace App\Services\Notifications\Channels;

use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\Log;

class WebPushChannel implements NotificationChannelInterface
{
    /**
     * Push Web (dialog modal obrigatório).
     * Na Fase 1: apenas marca como sent. O frontend consulta via API.
     */
    public function send(NotificationDelivery $delivery): array|false
    {
        try {
            $notification = $delivery->notification;

            // Broadcast via Reverb para o canal do usuário
            $canal = "public.intranet.notifications.{$delivery->user_id}";

            reverbSend($canal, 'web-push', [
                'notification_id' => $notification->uuid,
                'delivery_id' => $delivery->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'data' => $notification->data,
                'priority' => $notification->priority,
                'requires_confirmation' => true,
            ]);

            return ['external_id' => "web_push_{$delivery->user_id}_{$delivery->id}"];
        } catch (\Throwable $e) {
            Log::error('WebPushChannel: Falha', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
