<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DefaultUserPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_com_senha_padrao_redireciona_para_trocar_senha(): void
    {
        $user = User::factory()->create([
            'email' => 'teste@grupo5estrelas.com.br',
            'password' => DefaultUserPassword::VALUE,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => DefaultUserPassword::VALUE,
        ])->assertRedirect(route('password.force-change'));

        $user->refresh();
        $this->assertTrue($user->must_change_password);
    }

    public function test_usuario_com_flag_e_redirecionado_do_dashboard(): void
    {
        $user = User::factory()->create([
            'password' => 'OutraSenha1!',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('password.force-change'));
    }

    public function test_troca_senha_libera_acesso(): void
    {
        $user = User::factory()->create([
            'password' => DefaultUserPassword::VALUE,
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->post('/trocar-senha', [
                'password' => 'NovaSenha1!',
                'password_confirmation' => 'NovaSenha1!',
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
    }

    public function test_nao_aceita_senha_padrao_na_troca(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->post('/trocar-senha', [
                'password' => DefaultUserPassword::VALUE,
                'password_confirmation' => DefaultUserPassword::VALUE,
            ])
            ->assertSessionHasErrors('password');
    }
}
