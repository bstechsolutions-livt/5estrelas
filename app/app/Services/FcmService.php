<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Stub do envio de push via Firebase Cloud Messaging.
 *
 * Quando o cliente entregar o `firebase-service-account.json`, basta:
 * 1. Subir o arquivo em storage/app/private/firebase/
 * 2. Adicionar a dependência: composer require kreait/laravel-firebase
 * 3. Configurar FIREBASE_CREDENTIALS no .env
 * 4. Implementar o `sendToTokens()` usando \Kreait\Firebase\Messaging\CloudMessage
 *
 * Por enquanto, este service apenas registra que SERIA disparado.
 */
class FcmService
{
    public static function isEnabled(): bool
    {
        return (bool) env('FCM_ENABLED', false);
    }

    /**
     * Envia push pra todos os tokens de um usuário.
     */
    public static function sendToUser(int $userId, Notification $notification): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $tokens = DeviceToken::where('user_id', $userId)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        self::sendToTokens($tokens, [
            'title' => $notification->title,
            'body' => $notification->message ?? '',
            'data' => [
                'notification_id' => (string) $notification->id,
                'link' => $notification->link ?? '',
                'type' => $notification->type,
            ],
        ]);
    }

    /**
     * Envio real. Substituir pelo SDK do Firebase quando configurado.
     *
     * Implementação futura usando kreait/firebase-php:
     *
     *  $messaging = app('firebase.messaging');
     *  $message = CloudMessage::new()->withNotification(
     *      FirebaseNotification::create($payload['title'], $payload['body'])
     *  )->withData($payload['data']);
     *  $messaging->sendMulticast($message, $tokens);
     */
    private static function sendToTokens(array $tokens, array $payload): void
    {
        Log::info('FCM (stub) seria enviado', [
            'tokens_count' => count($tokens),
            'payload' => $payload,
        ]);
    }
}
