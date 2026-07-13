<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Política de corte de vencimento para títulos Senior (CP/CR).
 * Títulos com vencimento anterior a min_due_date não são importados nem mantidos.
 */
class SeniorDueDatePolicy
{
    public static function minDueDate(): Carbon
    {
        return Carbon::parse(config('senior.min_due_date', '2026-01-01'))->startOfDay();
    }

    public static function isAllowed(?Carbon $dueDate): bool
    {
        if ($dueDate === null) {
            return true;
        }

        return $dueDate->copy()->startOfDay()->gte(self::minDueDate());
    }

    public static function windowFrom(?Carbon $from): ?Carbon
    {
        if ($from === null) {
            return self::minDueDate();
        }

        $min = self::minDueDate();

        return $from->copy()->startOfDay()->lt($min) ? $min->copy() : $from->copy()->startOfDay();
    }
}
