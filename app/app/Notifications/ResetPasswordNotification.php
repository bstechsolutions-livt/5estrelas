<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $appName = Setting::get('app_name', '5 Estrelas');
        $primaryColor = Setting::get('primary_color', '#3b82f6');

        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject("Redefinição de senha — {$appName}")
            ->greeting("Olá!")
            ->line("Você está recebendo este e-mail porque foi solicitada uma redefinição de senha para a sua conta no {$appName}.")
            ->action('Redefinir senha', $url)
            ->line('Este link de redefinição expira em ' . config('auth.passwords.users.expire', 60) . ' minutos.')
            ->line('Se você não solicitou esta redefinição, nenhuma ação é necessária.')
            ->salutation("Atenciosamente,\n{$appName}");
    }
}
