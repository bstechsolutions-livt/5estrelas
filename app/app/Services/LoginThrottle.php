<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginThrottle
{
    /**
     * Limite global de requisições POST /login por IP (anti força-bruta sem alvo).
     * Independente do bloqueio por usuário (que zera ao acertar a senha).
     */
    public static function ipKey(Request $request): string
    {
        return 'login:ip:' . $request->ip();
    }

    public static function maxAttempts(): int
    {
        return max(1, (int) Setting::get('security.max_login_attempts', 5));
    }

    public static function lockoutMinutes(): int
    {
        return max(1, (int) Setting::get('security.lockout_minutes', 15));
    }

    /**
     * Retorna o número de segundos restantes de bloqueio por IP.
     */
    public static function tooManyAttemptsByIp(Request $request): int
    {
        $key = self::ipKey($request);
        if (RateLimiter::tooManyAttempts($key, self::maxAttempts())) {
            return RateLimiter::availableIn($key);
        }

        return 0;
    }

    public static function hitIp(Request $request): void
    {
        RateLimiter::hit(self::ipKey($request), self::lockoutMinutes() * 60);
    }

    public static function clearIp(Request $request): void
    {
        RateLimiter::clear(self::ipKey($request));
    }

    /**
     * Conta tentativa falha contra um usuário específico e bloqueia se necessário.
     */
    public static function registerUserFailure(?User $user): void
    {
        if (!$user) {
            return;
        }

        $user->failed_login_attempts = ($user->failed_login_attempts ?? 0) + 1;
        $user->last_failed_login_at = now();

        $max = self::maxAttempts();
        if ($user->failed_login_attempts >= $max) {
            $user->locked_until = now()->addMinutes(self::lockoutMinutes());
        }

        $user->saveQuietly();
    }

    /**
     * Limpa o estado ao logar com sucesso.
     */
    public static function clearUser(User $user): void
    {
        if ($user->failed_login_attempts || $user->locked_until) {
            $user->failed_login_attempts = 0;
            $user->last_failed_login_at = null;
            $user->locked_until = null;
            $user->saveQuietly();
        }
    }

    /**
     * Retorna o tempo restante (em segundos) do bloqueio do usuário, ou 0 se não bloqueado.
     */
    public static function userLockedSeconds(?User $user): int
    {
        if (!$user || !$user->locked_until) {
            return 0;
        }

        $until = Carbon::parse($user->locked_until);
        if ($until->isPast()) {
            // Expirou: zera
            $user->locked_until = null;
            $user->failed_login_attempts = 0;
            $user->saveQuietly();
            return 0;
        }

        return now()->diffInSeconds($until);
    }
}
