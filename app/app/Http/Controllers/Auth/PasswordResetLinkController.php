<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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

        // Tenta enviar o link. Não revelamos se o e-mail existe — sempre mensagem genérica.
        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'Se o e-mail estiver cadastrado, você receberá um link de redefinição em alguns instantes.');
    }
}
