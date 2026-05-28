<?php

namespace App\Rules;

use App\Models\Setting;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Se vier vazio (campo opcional, ex: edição sem trocar senha), ignora.
        // O validator superior cuida do "required" ou "nullable".
        if ($value === null || $value === '') {
            return;
        }

        $minLength = (int) Setting::get('security.password_min_length', 8);
        $requireLetter = (bool) Setting::get('security.password_require_letter', true);
        $requireNumber = (bool) Setting::get('security.password_require_number', true);

        if (!is_string($value)) {
            $fail('A senha é inválida.');
            return;
        }

        if (mb_strlen($value) < $minLength) {
            $fail("A senha precisa ter pelo menos {$minLength} caracteres.");
            return;
        }

        if ($requireLetter && !preg_match('/\p{L}/u', $value)) {
            $fail('A senha precisa conter pelo menos uma letra.');
            return;
        }

        if ($requireNumber && !preg_match('/\d/', $value)) {
            $fail('A senha precisa conter pelo menos um número.');
            return;
        }
    }

    public static function rules(): array
    {
        return [new self()];
    }
}
