<?php

namespace Tests\Feature;

use App\Models\ApprovalTrail;
use App\Models\Department;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentApprovalConfigTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(['key' => 'departamentos.gerenciar'], ['label' => 'Gerenciar deptos', 'module' => 'admin'])->id
        );

        return $user;
    }

    public function test_atualiza_area_gestor_e_diretor_do_departamento(): void
    {
        $manager = User::factory()->create(['is_active' => true]);
        $director = User::factory()->create(['is_active' => true]);
        $dept = Department::create(['name' => 'Compras', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->put("/departamentos/{$dept->id}", [
                'name' => 'Compras',
                'is_active' => true,
                'area_key' => 'matriz',
                'manager_id' => $manager->id,
                'director_id' => $director->id,
            ])
            ->assertRedirect('/departamentos');

        $dept->refresh();
        $this->assertSame('matriz', $dept->area_key);
        $this->assertSame($manager->id, $dept->manager_id);
        $this->assertSame($director->id, $dept->director_id);
    }

    public function test_rejeita_area_key_invalida(): void
    {
        $dept = Department::create(['name' => 'RH', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->put("/departamentos/{$dept->id}", [
                'name' => 'RH',
                'is_active' => true,
                'area_key' => 'area_inexistente',
            ])
            ->assertSessionHasErrors('area_key');
    }
}
