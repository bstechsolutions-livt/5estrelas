<?php

namespace App\Support;

use App\Models\Payable;
use App\Models\User;
use App\Services\AuditLogger;
use App\Models\PayableComment;
use Illuminate\Support\Carbon;

/**
 * Regra de negócio: não enviar título para aprovação com vencimento a menos de 72h
 * (3 dias corridos a partir de hoje; fim de semana empurra para dia útil).
 * O financeiro pode liberar envio urgente com permissão dedicada.
 */
class PayableApprovalDeadline
{
    public const PERMISSION_BYPASS = 'financeiro.contas_pagar.enviar_aprovacao_urgente';

    public static function minDueDateForApproval(?Carbon $from = null): Carbon
    {
        return Payable::defaultDueDate($from);
    }

    public static function meetsDeadline(?Carbon $dueDate, ?Carbon $from = null): bool
    {
        if (! $dueDate) {
            return false;
        }

        return $dueDate->copy()->startOfDay()->gte(self::minDueDateForApproval($from));
    }

    public static function canBypass(User $user): bool
    {
        return $user->hasPermission(self::PERMISSION_BYPASS);
    }

    /**
     * @return array{ok: bool, error: ?string, min_due_date: string, bypassed: bool}
     */
    public static function validateForSend(Payable $payable, User $user, bool $urgent = false, ?Carbon $from = null): array
    {
        $min = self::minDueDateForApproval($from);
        $minStr = $min->toDateString();

        if (self::meetsDeadline($payable->due_date, $from)) {
            return [
                'ok' => true,
                'error' => null,
                'min_due_date' => $minStr,
                'bypassed' => false,
            ];
        }

        if ($urgent && self::canBypass($user)) {
            return [
                'ok' => true,
                'error' => null,
                'min_due_date' => $minStr,
                'bypassed' => true,
            ];
        }

        $dueLabel = $payable->due_date
            ? $payable->due_date->format('d/m/Y')
            : 'sem vencimento';

        return [
            'ok' => false,
            'error' => sprintf(
                'Título %s vence em %s. Só é possível enviar para aprovação com vencimento a partir de %s (regra de 72 horas).',
                $payable->title_number,
                $dueLabel,
                $min->format('d/m/Y'),
            ),
            'min_due_date' => $minStr,
            'bypassed' => false,
        ];
    }

    public static function logUrgentSend(Payable $payable, User $user): void
    {
        PayableComment::create([
            'payable_id' => $payable->id,
            'user_id' => $user->id,
            'body' => 'Enviado para aprovação com urgência (fora do prazo de 72 horas)',
            'type' => 'status_change',
        ]);

        AuditLogger::log(
            event: 'contas_pagar.enviado_aprovacao_urgente',
            module: 'financeiro.contas_pagar',
            description: "Título {$payable->title_number} enviado para aprovação fora do prazo de 72h por {$user->name}",
            auditable: $payable,
        );
    }
}
