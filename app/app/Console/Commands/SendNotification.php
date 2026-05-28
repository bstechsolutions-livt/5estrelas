<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendNotification extends Command
{
    /**
     * Disparo manual de notificações para teste.
     *
     * Exemplos:
     *   php artisan notify:send --user=22 --title="Bem-vindo" --message="Notificação de teste"
     *   php artisan notify:send --email=bruno@bstechsolutions.com --title="Olá" --type=success
     *   php artisan notify:send --all --title="Aviso geral" --message="Aviso para todos"
     *   php artisan notify:send --user=22 --title="Confira" --link=/auditoria --type=warning
     */
    protected $signature = 'notify:send
        {--user= : ID do usuário destino}
        {--email= : Email do usuário destino}
        {--all : Envia para todos os usuários ativos}
        {--title= : Título da notificação (obrigatório)}
        {--message= : Mensagem (opcional)}
        {--link= : Link de destino (opcional)}
        {--type=info : info|success|warning|danger}
        {--icon= : Classe de ícone (ex: pi pi-bell)}';

    protected $description = 'Dispara uma notificação manualmente (para teste).';

    public function handle(): int
    {
        $title = $this->option('title');
        if (!$title) {
            $this->error('Use --title="..."');

            return self::FAILURE;
        }

        $message = $this->option('message');
        $link = $this->option('link');
        $type = $this->option('type') ?: 'info';
        $icon = $this->option('icon');

        if ($this->option('all')) {
            $count = NotificationService::broadcast($title, $message, $link, $type, $icon);
            $this->info("Broadcast enviado para {$count} usuário(s) ativo(s).");

            return self::SUCCESS;
        }

        $user = null;
        if ($this->option('user')) {
            $user = User::find((int) $this->option('user'));
        } elseif ($this->option('email')) {
            $user = User::where('email', $this->option('email'))->first();
        }

        if (!$user) {
            $this->error('Use --user=ID, --email=... ou --all.');

            return self::FAILURE;
        }

        $n = NotificationService::send($user, $title, $message, $link, $type, $icon);
        $this->info("Notificação #{$n->id} enviada para {$user->name} <{$user->email}>.");

        return self::SUCCESS;
    }
}
