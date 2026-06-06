<?php

namespace App\Services\Notifications\Channels;

use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\Log;

class InAppChannel implements NotificationChannelInterface
{
    /**
     * Envia notificação in-app: marca como sent + broadcast via Reverb.
     */
    public function send(NotificationDelivery $delivery): array|false
    {
        try {
            $notification = $delivery->notification;

            // Broadcast via Reverb para o canal do usuário (real-time)
            $canal = "public.intranet.notifications.{$delivery->user_id}";
            $evento = 'new-notification';

            $dados = [
                'notification_id' => $notification->uuid,
                'delivery_id' => $delivery->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'data' => $notification->data,
                'priority' => $notification->priority,
                'created_at' => $notification->created_at->toISOString(),
            ];

            // Tentar enviar via Reverb (não falha se Reverb estiver offline)
            try {
                reverbSend($canal, $evento, $dados);
            } catch (\Throwable $e) {
                \Log::warning('InAppChannel: Reverb offline, delivery salva mas sem broadcast', [
                    'delivery_id' => $delivery->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return ['external_id' => "inapp_{$delivery->user_id}_{$delivery->id}"];
        } catch (\Throwable $e) {
            Log::error('InAppChannel: Falha ao enviar', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
