<?php

namespace App\Support;

class DefaultUserPassword
{
    public const VALUE = '5Estrelas';

    public static function is(string $password): bool
    {
        return $password === self::VALUE;
    }
}
