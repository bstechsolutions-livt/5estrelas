<?php

namespace App\Services\Notifications\Channels;

use App\Models\NotificationDelivery;

interface NotificationChannelInterface
{
    /**
     * Enviar notificação por este canal.
     *
     * @param NotificationDelivery $delivery
     * @return array|false  Array com dados do envio (ex: ['external_id' => '...']) ou false se falhou
     */
    public function send(NotificationDelivery $delivery): array|false;
}
