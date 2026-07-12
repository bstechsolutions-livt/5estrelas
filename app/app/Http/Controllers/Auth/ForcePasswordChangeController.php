<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\NotDefaultPassword;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ForcePasswordChangeController extends Controller
{
    public function show(Request $request)
    {
        if (!$request->user()?->must_change_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/ForcePasswordChange', [
            'email' => $request->user()->email,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user?->must_change_password) {
            return redirect()->route('dashboard');
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', new \App\Rules\StrongPassword(), new NotDefaultPassword()],
        ]);

        $user->password = $data['password'];
        $user->must_change_password = false;
        $user->saveQuietly();

        AuditLogger::log(
            event: 'auth.password_forced_change',
            module: 'auth',
            description: 'Trocou a senha padrão no primeiro acesso',
            auditable: $user,
        );

        return redirect()->route('dashboard')->with('success', 'Senha definida com sucesso.');
    }
}
