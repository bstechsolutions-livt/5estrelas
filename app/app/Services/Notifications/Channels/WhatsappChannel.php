<?php

namespace App\Services\Notifications\Channels;

use App\Models\NotificationDelivery;
use App\Models\v2\BsChatbotConfig;
use App\Services\Notifications\Support\ResolveTelefoneWhatsapp;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsappChannel implements NotificationChannelInterface
{
    use ResolveTelefoneWhatsapp;

    /**
     * Envia notificação por WhatsApp via Z-API.
     */
    public function send(NotificationDelivery $delivery): array|false
    {
        try {
            $notification = $delivery->notification;
            $userId = $delivery->user_id;

            // Buscar telefone do usuário em 3 fontes (perfil intranet → celular → fone)
            $celular = $this->buscarTelefoneColaborador((string) $userId);

            if (empty($celular)) {
                Log::info('WhatsappChannel: Usuário sem telefone cadastrado', ['user_id' => $userId]);
                return false;
            }

            // Formatar telefone (remove não-dígitos, adiciona DDI 55, valida)
            $telefone = $this->formatarTelefoneWhatsapp($celular);

            if (empty($telefone)) {
                Log::info('WhatsappChannel: Telefone em formato inválido', ['user_id' => $userId, 'telefone' => $celular]);
                return false;
            }

            // Montar mensagem formatada (WhatsApp markdown)
            $mensagem = "━━━━━━━━━━━━━━━\n"
                . "📢 *{$notification->title}*\n"
                . "━━━━━━━━━━━━━━━\n\n"
                . "{$notification->body}\n";

            if (!empty($notification->data['link'])) {
                $mensagem .= "\n🔗 Acesse: " . url($notification->data['link']);
            }

            $mensagem .= "\n\n_EasyIntra • Grupo Big_";

            // Buscar credenciais Z-API
            $creds = BsChatbotConfig::getZapiCredentials();
            if (empty($creds['instance_id']) || empty($creds['token']) || empty($creds['client_token'])) {
                Log::warning('WhatsappChannel: Credenciais Z-API não configuradas');
                return false;
            }

            // Enviar via WhatsappService (texto formatado)
            $whatsapp = app(WhatsappService::class);

            $resultado = $whatsapp->sendMessageZapi(
                $telefone,
                $mensagem,
                $creds['instance_id'],
                $creds['token'],
                $creds['client_token']
            );

            $sucesso = $resultado['sucesso'] ?? false;

            if ($sucesso) {
                return ['external_id' => $resultado['messageId'] ?? "zapi_{$telefone}"];
            }

            Log::warning('WhatsappChannel: Z-API retornou falha', [
                'delivery_id' => $delivery->id,
                'response' => $resultado,
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsappChannel: Falha', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
