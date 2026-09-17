<?php

namespace App\Support;

/**
 * Normaliza CNPJ/CPF gravados a partir da Senior (número/float sem zeros à esquerda).
 *
 * Conservador: só completa 12–13 dígitos para CNPJ 14, ou 15 dígitos com zero extra
 * de serialização, quando o dígito verificador fecha. Não promove 8–11 dígitos a CNPJ.
 */
class CnpjNormalizer
{
    public static function normalize(mixed $value): ?string
    {
        $digits = self::digits($value);
        if ($digits === null) {
            return null;
        }

        if (self::isValidCnpj($digits)) {
            return $digits;
        }

        if (strlen($digits) === 15 && str_ends_with($digits, '0') && self::isValidCnpj(substr($digits, 0, 14))) {
            return substr($digits, 0, 14);
        }

        if (strlen($digits) === 12 || strlen($digits) === 13) {
            $padded = str_pad($digits, 14, '0', STR_PAD_LEFT);
            if (self::isValidCnpj($padded)) {
                return $padded;
            }
        }

        if (self::isValidCpf($digits)) {
            return $digits;
        }

        if (strlen($digits) < 9) {
            return null;
        }

        return $digits;
    }

    public static function isCnpj(?string $digits): bool
    {
        return $digits !== null && self::isValidCnpj($digits);
    }

    /** CNPJ 14 válido, ou null. */
    public static function cnpj14(mixed $value): ?string
    {
        $normalized = self::normalize($value);

        return self::isCnpj($normalized) ? $normalized : null;
    }

    public static function isCpf(?string $digits): bool
    {
        return $digits !== null && self::isValidCpf($digits);
    }

    /**
     * CNPJ do cadastro, se válido; senão o documento já gravado no título, normalizado.
     */
    public static function preferCadastro(?string $cadastro, ?string $titulo): ?string
    {
        $fromCadastro = self::normalize($cadastro);
        if (self::isCnpj($fromCadastro)) {
            return $fromCadastro;
        }

        $fromTitulo = self::normalize($titulo);
        if (self::isCnpj($fromTitulo)) {
            return $fromTitulo;
        }

        return $fromCadastro ?? $fromTitulo;
    }

    public static function digits(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        if (is_array($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/\.0+$/', '', $raw) ?? $raw;
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '' || preg_match('/^0+$/', $digits)) {
            return null;
        }

        return $digits;
    }

    public static function isValidCnpj(string $digits): bool
    {
        if (strlen($digits) !== 14 || ! ctype_digit($digits) || preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        $d1 = self::checkDigit(substr($digits, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $d2 = self::checkDigit(substr($digits, 0, 12).$d1, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return substr($digits, 12, 2) === $d1.$d2;
    }

    public static function isValidCpf(string $digits): bool
    {
        if (strlen($digits) !== 11 || ! ctype_digit($digits) || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        $d1 = self::checkDigit(substr($digits, 0, 9), range(10, 2));
        $d2 = self::checkDigit(substr($digits, 0, 9).$d1, range(11, 2));

        return substr($digits, 9, 2) === $d1.$d2;
    }

    /** @param list<int> $weights */
    private static function checkDigit(string $base, array $weights): string
    {
        $sum = 0;
        foreach ($weights as $i => $weight) {
            $sum += (int) $base[$i] * $weight;
        }
        $mod = $sum % 11;

        return $mod < 2 ? '0' : (string) (11 - $mod);
    }
}
