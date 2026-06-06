<?php

namespace App\Services\Notifications\Channels;

use Illuminate\Support\Facades\DB;

class ChannelFactory
{
    /**
     * Cria instância do driver de canal pelo slug.
     */
    public static function make(string $channelSlug): NotificationChannelInterface
    {
        $driverClass = DB::table('notification_channels')
            ->where('slug', $channelSlug)
            ->value('driver_class');

        if (!$driverClass || !class_exists($driverClass)) {
            throw new \RuntimeException("Driver não encontrado para canal: {$channelSlug}");
        }

        return app($driverClass);
    }

    /**
     * Cria instância do driver pelo channel_id.
     */
    public static function makeById(int $channelId): NotificationChannelInterface
    {
        $channel = DB::table('notification_channels')->find($channelId);

        if (!$channel || !$channel->driver_class || !class_exists($channel->driver_class)) {
            throw new \RuntimeException("Driver não encontrado para channel_id: {$channelId}");
        }

        return app($channel->driver_class);
    }
}
