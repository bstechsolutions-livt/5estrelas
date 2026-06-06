<?php

namespace App\Services\Notifications;

use App\Jobs\SendNotificationJob;
use App\Jobs\SendDelayedNotificationJob;
use App\Models\Notification;
use App\Models\NotificationChannel;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\UserNotificationSetting;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class NotificationCenter
{
    /**
     * Ponto único de entrada para enviar notificações.
     *
     * @param string $eventSlug   Ex: 'agendamento.criado', 'solicitacoes.comentario'
     * @param array  $payload     ['title' => '...', 'body' => '...', 'data' => [...]]
     * @param array  $userIds     Matrículas dos destinatários
     * @param array  $options     ['priority', 'origin', 'created_by', 'scheduled_at', 'channels' => [...], 'respect_delay' => true]
     * @return Notification
     */
    public function dispatch(
        string $eventSlug,
        array $payload,
        array $userIds,
        array $options = []
    ): Notification {
        $event = NotificationEvent::where('slug', $eventSlug)->first();

        // Criar notificação (cabeçalho)
        $notification = Notification::create([
            'uuid'         => Str::uuid()->toString(),
            'event_id'     => $event?->id,
            'title'        => $payload['title'],
            'body'         => $payload['body'] ?? null,
            'data'         => $payload['data'] ?? null,
            'priority'     => $options['priority'] ?? 'normal',
            'origin'       => $options['origin'] ?? null,
            'created_by'   => $options['created_by'] ?? null,
            'scheduled_at' => $options['scheduled_at'] ?? null,
            'expires_at'   => $options['expires_at'] ?? now()->addHours(24),
        ]);

        // Canais habilitados para o evento (com flag is_mandatory por canal)
        $eventChannels = $this->resolveEventChannels($event, $options);
        $respectDelay = $options['respect_delay'] ?? true;

        // Para cada destinatário, criar deliveries respeitando as preferências dele
        foreach ($userIds as $userId) {
            $userId = (string) $userId;

            // Configurações globais do usuário (delays + quiet hours)
            $settings = UserNotificationSetting::find($userId);

            foreach ($eventChannels as $ec) {
                $channel = $ec['channel'];
                $isMandatory = $ec['is_mandatory'];

                // Se não é obrigatório, respeitar a preferência do usuário (liga/desliga).
                // Eventos/canais mandatórios ignoram a preferência.
                if (! $isMandatory && $event) {
                    if (! $this->usuarioAceitaCanal($userId, $event->id, $channel->id)) {
                        continue;
                    }
                }

                // Calcular o delay efetivo (preferência do usuário sobrescreve o padrão do canal)
                $delayMin = $this->resolveDelay($channel, $settings);

                // Base do agendamento
                $scheduledFor = ($respectDelay && $delayMin > 0)
                    ? now()->addMinutes($delayMin)
                    : now();

                // Quiet hours só afeta canais com delay (intrusivos: whatsapp, email, mobile_push).
                // In-app e web_push são silenciosos / críticos e passam direto.
                if ($respectDelay && $this->canalRespeitaQuietHours($channel)) {
                    $scheduledFor = $this->aplicarQuietHours($scheduledFor, $settings);
                }

                $delivery = NotificationDelivery::create([
                    'notification_id' => $notification->id,
                    'channel_id'      => $channel->id,
                    'user_id'         => $userId,
                    'status'          => 'pending',
                    'scheduled_for'   => $scheduledFor,
                ]);

                // Despachar job: imediato se scheduledFor <= agora, senão com delay
                if ($scheduledFor->lessThanOrEqualTo(now()->addSeconds(1))) {
                    SendNotificationJob::dispatch($delivery->id);
                } else {
                    SendDelayedNotificationJob::dispatch($delivery->id)
                        ->delay($scheduledFor);
                }
            }
        }

        return $notification;
    }

    /**
     * Atalho: enviar para um único usuário.
     */
    public function sendToUser(string $userId, string $eventSlug, array $payload, array $options = []): Notification
    {
        return $this->dispatch($eventSlug, $payload, [$userId], $options);
    }

    /**
     * Resolve os canais habilitados para o evento, retornando também a flag is_mandatory.
     *
     * @return array<int, array{channel: NotificationChannel, is_mandatory: bool}>
     */
    private function resolveEventChannels(?NotificationEvent $event, array $options): array
    {
        // Override explícito de canais (todos tratados como não-mandatórios)
        if (! empty($options['channels'])) {
            return NotificationChannel::whereIn('slug', $options['channels'])
                ->where('is_active', true)
                ->get()
                ->map(fn ($ch) => ['channel' => $ch, 'is_mandatory' => false])
                ->all();
        }

        // Canais configurados para o evento (notification_event_channels)
        if ($event) {
            $rows = DB::table('notification_event_channels as ec')
                ->join('notification_channels as c', 'c.id', '=', 'ec.channel_id')
                ->where('ec.event_id', $event->id)
                ->where('ec.is_default', true)
                ->where('c.is_active', true)
                ->select('ec.channel_id', 'ec.is_mandatory')
                ->get();

            $channelIds = $rows->pluck('channel_id')->all();
            $mandatoryMap = $rows->pluck('is_mandatory', 'channel_id');

            return NotificationChannel::whereIn('id', $channelIds)
                ->get()
                ->map(fn ($ch) => [
                    'channel' => $ch,
                    'is_mandatory' => (bool) ($mandatoryMap[$ch->id] ?? false),
                ])
                ->all();
        }

        // Fallback: todos os canais ativos
        return NotificationChannel::where('is_active', true)
            ->get()
            ->map(fn ($ch) => ['channel' => $ch, 'is_mandatory' => false])
            ->all();
    }

    /**
     * Verifica se o usuário aceita receber este evento por este canal.
     * Default: aceita (se não houver registro de preferência, considera habilitado).
     * In-app nunca é desligável.
     */
    private function usuarioAceitaCanal(string $userId, int $eventId, int $channelId): bool
    {
        $pref = DB::table('user_notification_preferences')
            ->where('user_id', $userId)
            ->where('event_id', $eventId)
            ->where('channel_id', $channelId)
            ->first();

        // Sem registro = usa o default do evento (habilitado)
        if (! $pref) {
            return true;
        }

        return (bool) $pref->is_enabled;
    }

    /**
     * Resolve o delay efetivo do canal: preferência do usuário sobrescreve o padrão global.
     */
    private function resolveDelay(NotificationChannel $channel, ?UserNotificationSetting $settings): int
    {
        if ($settings) {
            if ($channel->slug === 'whatsapp' && ! is_null($settings->whatsapp_delay_min)) {
                return (int) $settings->whatsapp_delay_min;
            }
            if ($channel->slug === 'email' && ! is_null($settings->email_delay_min)) {
                return (int) $settings->email_delay_min;
            }
        }

        return (int) $channel->delay_minutes;
    }

    /**
     * Canais que respeitam quiet hours (os intrusivos). In-app e web push não.
     */
    private function canalRespeitaQuietHours(NotificationChannel $channel): bool
    {
        return in_array($channel->slug, ['mobile_push', 'whatsapp', 'email'], true);
    }

    /**
     * Se o horário cai dentro da janela "não perturbe" do usuário, reagenda para o fim da janela.
     */
    private function aplicarQuietHours(Carbon $scheduledFor, ?UserNotificationSetting $settings): Carbon
    {
        if (! $settings || ! $settings->quiet_hours_enabled) {
            return $scheduledFor;
        }
        if (empty($settings->quiet_hours_start) || empty($settings->quiet_hours_end)) {
            return $scheduledFor;
        }

        [$startH, $startM] = array_pad(explode(':', $settings->quiet_hours_start), 2, '0');
        [$endH, $endM] = array_pad(explode(':', $settings->quiet_hours_end), 2, '0');

        $momento = $scheduledFor->copy();
        $inicio = $momento->copy()->setTime((int) $startH, (int) $startM);
        $fim = $momento->copy()->setTime((int) $endH, (int) $endM);

        // Janela que vira o dia (ex: 22:00 → 07:00)
        if ($inicio->greaterThan($fim)) {
            // Está dentro se: depois do início (noite) OU antes do fim (madrugada)
            if ($momento->greaterThanOrEqualTo($inicio)) {
                // Madrugada do dia seguinte
                return $momento->copy()->addDay()->setTime((int) $endH, (int) $endM);
            }
            if ($momento->lessThan($fim)) {
                // Mesma madrugada
                return $momento->copy()->setTime((int) $endH, (int) $endM);
            }
            return $scheduledFor;
        }

        // Janela no mesmo dia (ex: 12:00 → 13:00)
        if ($momento->greaterThanOrEqualTo($inicio) && $momento->lessThan($fim)) {
            return $fim;
        }

        return $scheduledFor;
    }
}
