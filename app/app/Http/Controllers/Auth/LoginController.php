<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoginThrottle;
use App\Support\DefaultUserPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required'],
        ]);

        // 1) Throttle por IP (anti força-bruta sem alvo)
        $waitIp = LoginThrottle::tooManyAttemptsByIp($request);
        if ($waitIp > 0) {
            return back()->withErrors([
                'email' => $this->throttleMessage($waitIp),
            ])->onlyInput('email');
        }

        $identifier = $request->input('email');

        // Permite login por e-mail ou ID numérico
        if (is_numeric($identifier)) {
            $user = User::find((int) $identifier);
            $credentials = $user
                ? ['email' => $user->email, 'password' => $request->password]
                : ['email' => $identifier, 'password' => $request->password];
        } else {
            $user = User::where('email', $identifier)->first();
            $credentials = ['email' => $identifier, 'password' => $request->password];
        }

        // 2) Conta bloqueada por excesso de tentativas erradas?
        $waitUser = LoginThrottle::userLockedSeconds($user);
        if ($waitUser > 0) {
            AuditLogger::log(
                event: 'auth.login.locked',
                module: 'auth',
                description: 'Tentativa de login em conta bloqueada',
                metadata: ['identifier' => $identifier, 'unlock_in_seconds' => $waitUser],
            );

            return back()->withErrors([
                'email' => $this->lockoutMessage($waitUser),
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // 3) Sucesso: zera contador
            LoginThrottle::clearIp($request);
            LoginThrottle::clearUser(Auth::user());

            AuditLogger::log(
                event: 'auth.login.success',
                module: 'auth',
                description: 'Login efetuado',
            );

            $user = Auth::user();
            if (DefaultUserPassword::is($request->password)) {
                if (!$user->must_change_password) {
                    $user->must_change_password = true;
                    $user->saveQuietly();
                }

                return redirect()->route('password.force-change');
            }

            return redirect()->intended('/dashboard');
        }

        // 4) Falha: incrementa contador IP + usuário (se identificado)
        LoginThrottle::hitIp($request);
        LoginThrottle::registerUserFailure($user);

        AuditLogger::log(
            event: 'auth.login.failed',
            module: 'auth',
            description: 'Tentativa de login com credenciais inválidas',
            metadata: [
                'identifier' => $identifier,
                'attempts' => $user?->failed_login_attempts,
            ],
        );

        // Avisa se acabou de bloquear
        $waitAfter = LoginThrottle::userLockedSeconds($user);
        if ($waitAfter > 0) {
            AuditLogger::log(
                event: 'auth.account.locked',
                module: 'auth',
                description: "Conta {$user->email} bloqueada por excesso de tentativas",
                auditable: $user,
            );

            return back()->withErrors([
                'email' => $this->lockoutMessage($waitAfter),
            ])->onlyInput('email');
        }

        return back()->withErrors([
            'email' => 'As credenciais informadas não conferem.',
        ])->onlyInput('email');
    }

    public function destroy(Request $request)
    {
        AuditLogger::log(
            event: 'auth.logout',
            module: 'auth',
            description: 'Logout efetuado',
        );

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function throttleMessage(int $seconds): string
    {
        $min = (int) ceil($seconds / 60);
        return "Muitas tentativas a partir do seu IP. Tente novamente em {$min} min.";
    }

    private function lockoutMessage(int $seconds): string
    {
        $min = (int) ceil($seconds / 60);
        return "Conta bloqueada temporariamente por excesso de tentativas. Tente novamente em {$min} min.";
    }
}
