<?php

namespace Tests\Feature;

use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableLauncherShowTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => '*'],
                ['label' => 'Admin', 'module' => 'sistema'],
            )->id,
        );

        return $user;
    }

    private function makePayable(array $attrs = []): Payable
    {
        return Payable::create(array_merge([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor Teste',
            'amount' => 1000.00,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'pendente',
            'senior_id' => '2-1-' . uniqid() . '-01-1',
        ], $attrs));
    }

    public function test_show_exibe_nome_do_lancador_mapeado(): void
    {
        User::factory()->create([
            'name' => 'Maria Lançadora',
            'senior_cod_usu' => 166,
            'is_active' => true,
        ]);

        $payable = $this->makePayable(['senior_cod_usu' => 166]);

        $this->actingAs($this->activeUser())
            ->get("/financeiro/contas-pagar/{$payable->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('payable.launcher_nome', 'Maria Lançadora')
                ->where('payable.launcher_label', 'Maria Lançadora')
                ->where('payable.field_origins.launcher_nome', 'senior'));
    }

    public function test_show_exibe_codigo_quando_usuario_nao_mapeado(): void
    {
        $payable = $this->makePayable(['senior_cod_usu' => 99]);

        $this->actingAs($this->activeUser())
            ->get("/financeiro/contas-pagar/{$payable->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('payable.launcher_nome', null)
                ->where('payable.launcher_label', 'Usuário Senior #99'));
    }

    public function test_show_sem_usu_ger_deixa_lancador_vazio(): void
    {
        $payable = $this->makePayable(['senior_cod_usu' => null]);

        $this->actingAs($this->activeUser())
            ->get("/financeiro/contas-pagar/{$payable->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('payable.launcher_nome', null)
                ->where('payable.launcher_label', null));
    }
}
