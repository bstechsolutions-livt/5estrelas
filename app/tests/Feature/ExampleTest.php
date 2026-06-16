<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Smoke test: o app sobe e a raiz redireciona o visitante não autenticado
     * (raiz → /dashboard → /login). Garante que o boot (settings/tema/middleware) não dá 500.
     */
    public function test_visitante_e_redirecionado(): void
    {
        // Raiz redireciona para /dashboard
        $this->get('/')->assertRedirect('/dashboard');

        // E o dashboard (protegido) manda o visitante para o login
        $this->get('/dashboard')->assertRedirect('/login');
    }

    /**
     * A tela de login responde 200 (rota pública).
     */
    public function test_tela_de_login_responde(): void
    {
        $this->get('/login')->assertStatus(200);
    }
}
