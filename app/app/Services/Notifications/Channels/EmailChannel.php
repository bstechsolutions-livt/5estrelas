<?php

namespace App\Services\Notifications\Channels;

use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailChannel implements NotificationChannelInterface
{
    /**
     * Envia notificação por e-mail via SMTP.
     */
    public function send(NotificationDelivery $delivery): array|false
    {
        try {
            $notification = $delivery->notification;
            $userId = $delivery->user_id;

            // Buscar email do usuário na pcempr
            $email = DB::table('pcempr')
                ->where('matricula', $userId)
                ->value('email');

            if (empty($email)) {
                Log::info('EmailChannel: Usuário sem email cadastrado', ['user_id' => $userId]);
                return false;
            }

            $titulo = $notification->title;
            $mensagem = strip_tags($notification->body ?? '');

            // Link precisa ser ABSOLUTO no e-mail (link relativo vira host vazio
            // tipo "http:///..." em alguns clientes). Usa APP_URL via url().
            $linkRaw = $notification->data['link'] ?? null;
            $link = null;
            if (! empty($linkRaw)) {
                $link = preg_match('#^https?://#i', $linkRaw) ? $linkRaw : url($linkRaw);
            }

            Mail::send('emails.notification-v2', [
                'titulo' => $titulo,
                'mensagem' => $mensagem,
                'link' => $link,
                'rem_matricula' => $userId,
                'rem_nome' => DB::table('pcempr')->where('matricula', $userId)->value('nome') ?? 'Colaborador',
            ], function ($mail) use ($email, $titulo) {
                $mail->to($email)->subject($titulo);
            });

            return ['external_id' => "email_{$email}"];
        } catch (\Throwable $e) {
            Log::error('EmailChannel: Falha', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
