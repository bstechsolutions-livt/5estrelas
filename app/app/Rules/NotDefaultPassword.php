<?php

namespace App\Rules;

use App\Support\DefaultUserPassword;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotDefaultPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && DefaultUserPassword::is($value)) {
            $fail('Escolha uma senha diferente da senha padrão de primeiro acesso.');
        }
    }
}
