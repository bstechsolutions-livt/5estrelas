<?php

namespace App\Services\Notifications\Channels;

use App\Models\NotificationDelivery;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PushMobileChannel implements NotificationChannelInterface
{
    /**
     * Push Mobile via Firebase Cloud Messaging (FCM API v1).
     */
    public function send(NotificationDelivery $delivery): array|false
    {
        try {
            $notification = $delivery->notification;
            $userId = $delivery->user_id;

            // Buscar tokens ativos do usuário
            $tokens = DB::table('notification_device_tokens')
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->pluck('token')
                ->toArray();

            if (empty($tokens)) {
                Log::info('PushMobileChannel: Nenhum token ativo', ['user_id' => $userId]);
                return false;
            }

            $firebase = app(FirebaseService::class);
            $dados = array_merge(
                ['notification_id' => $notification->uuid, 'link' => $notification->data['link'] ?? ''],
                is_array($notification->data) ? $notification->data : []
            );

            $successCount = 0;
            foreach ($tokens as $token) {
                $result = $firebase->enviarParaToken(
                    $token,
                    $notification->title,
                    $notification->body ?? '',
                    $dados
                );
                if ($result) $successCount++;
            }

            if ($successCount > 0) {
                return ['external_id' => "fcm_{$userId}_{$successCount}_tokens"];
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('PushMobileChannel: Falha', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
