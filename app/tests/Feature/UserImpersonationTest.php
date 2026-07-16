<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function grant(User $user, string $key): void
    {
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'module' => 'usuarios'],
            )->id,
        );
        $user->flushPermissionCache();
    }

    public function test_admin_pode_entrar_como_outro_usuario(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $target = User::factory()->create(['is_active' => true, 'name' => 'Maria Silva']);
        $this->grant($admin, 'usuarios.impersonar');
        $this->grant($admin, 'usuarios.listar');

        $this->actingAs($admin)
            ->post("/usuarios/{$target->id}/impersonar")
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($target);
        $this->assertEquals($admin->id, Impersonation::impersonatorId());

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.id', $target->id)
                ->where('auth.impersonator.id', $admin->id));
    }

    public function test_usuario_sem_permissao_nao_pode_impersonar(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $target = User::factory()->create(['is_active' => true]);
        $this->grant($user, 'usuarios.listar');

        $this->actingAs($user)
            ->post("/usuarios/{$target->id}/impersonar")
            ->assertForbidden();

        $this->assertAuthenticatedAs($user);
    }

    public function test_nao_pode_entrar_como_usuario_inativo(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $target = User::factory()->create(['is_active' => false]);
        $this->grant($admin, 'usuarios.impersonar');

        $this->actingAs($admin)
            ->post("/usuarios/{$target->id}/impersonar")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_pode_sair_da_impersonacao(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $target = User::factory()->create(['is_active' => true]);
        $this->grant($admin, 'usuarios.impersonar');

        $this->actingAs($admin)->post("/usuarios/{$target->id}/impersonar");

        $this->actingAs($target)
            ->withSession([Impersonation::SESSION_KEY => $admin->id])
            ->post('/impersonacao/sair')
            ->assertRedirect(route('users.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(Impersonation::isActive());
    }

    public function test_impersonacao_registra_auditoria(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'name' => 'Admin']);
        $target = User::factory()->create(['is_active' => true, 'name' => 'Alvo']);
        $this->grant($admin, 'usuarios.impersonar');

        $this->actingAs($admin)->post("/usuarios/{$target->id}/impersonar");

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'usuarios.impersonation_start',
            'user_id' => $admin->id,
            'auditable_id' => $target->id,
        ]);
    }

    public function test_impersonacao_ignora_troca_obrigatoria_de_senha(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $target = User::factory()->create([
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $this->grant($admin, 'usuarios.impersonar');

        $this->actingAs($admin)->post("/usuarios/{$target->id}/impersonar");

        $this->actingAs($target)
            ->withSession([Impersonation::SESSION_KEY => $admin->id])
            ->get('/dashboard')
            ->assertOk();
    }
}
