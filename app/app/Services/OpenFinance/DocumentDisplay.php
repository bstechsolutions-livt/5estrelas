<?php

namespace App\Services\OpenFinance;

use App\Support\CnpjNormalizer;

/** Máscaras de CPF/CNPJ para UI — não substitui a identidade interna (dígitos). */
final class DocumentDisplay
{
    public static function mask(?string $value): string
    {
        $digits = CnpjNormalizer::digits($value) ?? '';

        if (strlen($digits) === 14) {
            return substr($digits, 0, 2).'.***.***/'.substr($digits, 8, 4).'-**';
        }

        if (strlen($digits) === 11) {
            return substr($digits, 0, 3).'.***.***-**';
        }

        return '**—**';
    }
}
