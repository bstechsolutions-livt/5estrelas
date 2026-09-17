<?php

namespace App\Services\OpenFinance;

use App\Support\CnpjNormalizer;

/** Máscaras de CPF/CNPJ e conta para UI — não substitui a identidade interna (dígitos). */
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

    public static function maskAccount(?string $number, ?string $digit = null): string
    {
        $digits = preg_replace('/\D+/', '', (string) $number) ?? '';
        $dac = preg_replace('/\D+/', '', (string) $digit) ?? '';

        if ($digits === '') {
            return '—';
        }

        $visible = strlen($digits) <= 4 ? $digits : substr($digits, -4);
        $masked = str_repeat('*', max(0, strlen($digits) - strlen($visible))).$visible;

        return $dac !== '' ? $masked.'-'.$dac : $masked;
    }
}
