<?php

namespace App\Services;

use App\Models\BankDayOperation;

/**
 * Classifica débitos OFX que não são títulos a pagar:
 * tarifa bancária, aplicação e resgate.
 */
class OfxBankOperationClassifier
{
    public function classify(?string $description, ?string $memo = null): ?string
    {
        $text = $this->normalize($description.' '.$memo);

        if ($text === '') {
            return null;
        }

        if (str_contains($text, 'RESGATE')) {
            return BankDayOperation::CATEGORY_RESGATE;
        }

        if (str_contains($text, 'APLICACAO') || str_contains($text, 'APLICAÇÃO')) {
            return BankDayOperation::CATEGORY_APLICACAO;
        }

        // TARIFA…, TAR LIQ…, TARIF…
        if (
            str_contains($text, 'TARIFA')
            || str_contains($text, 'TARIF ')
            || str_contains($text, 'TAR LIQ')
            || preg_match('/\bTAR\b/', $text) === 1
        ) {
            return BankDayOperation::CATEGORY_TARIFA;
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
