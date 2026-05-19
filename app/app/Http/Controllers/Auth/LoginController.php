<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
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
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            AuditLogger::log(
                event: 'auth.login.success',
                module: 'auth',
                description: 'Login efetuado',
            );

            return redirect()->intended('/dashboard');
        }

        AuditLogger::log(
            event: 'auth.login.failed',
            module: 'auth',
            description: 'Tentativa de login com credenciais inválidas',
            metadata: ['email' => $request->input('email')],
        );

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
}
