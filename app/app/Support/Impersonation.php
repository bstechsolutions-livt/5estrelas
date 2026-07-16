<?php

namespace App\Support;

use App\Models\User;

class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public static function isActive(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public static function impersonatorId(): ?int
    {
        $id = session(self::SESSION_KEY);

        return $id ? (int) $id : null;
    }

    public static function start(User $impersonator, User $target): void
    {
        session()->put(self::SESSION_KEY, $impersonator->id);
        auth()->login($target);
        session()->regenerate();
    }

    public static function stop(): ?User
    {
        $impersonatorId = self::impersonatorId();
        if (! $impersonatorId) {
            return null;
        }

        $impersonator = User::find($impersonatorId);
        session()->forget(self::SESSION_KEY);

        if ($impersonator) {
            auth()->login($impersonator);
            session()->regenerate();
        }

        return $impersonator;
    }
}
