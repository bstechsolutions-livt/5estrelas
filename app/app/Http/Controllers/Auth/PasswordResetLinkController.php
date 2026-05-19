<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class PasswordResetLinkController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink($request->only('email'));

        AuditLogger::log(
            event: 'auth.password.reset_requested',
            module: 'auth',
            description: 'Solicitação de redefinição de senha',
            metadata: ['email' => $request->input('email')],
        );

        return back()->with('success', 'Se o e-mail estiver cadastrado, você receberá um link de redefinição em alguns instantes.');
    }
}
