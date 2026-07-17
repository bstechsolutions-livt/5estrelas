<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableSortTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Visualizar CP', 'module' => 'financeiro'],
            )->id,
        );
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.ver_todos_departamentos'],
                ['label' => 'Ver todos deptos', 'module' => 'financeiro'],
            )->id,
        );
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.ver_todas_filiais'],
                ['label' => 'Ver todas filiais', 'module' => 'financeiro'],
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
        ], $attrs));
    }

    public function test_ordenacao_por_vencimento_crescente(): void
    {
        $older = $this->makePayable([
            'supplier_name' => 'AAA',
            'due_date' => '2026-07-01',
        ]);
        $newer = $this->makePayable([
            'supplier_name' => 'ZZZ',
            'due_date' => '2026-07-15',
        ]);

        $response = $this->actingAs($this->activeUser())
            ->get('/financeiro/contas-pagar?status=pendente&sort=due_date&dir=asc')
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['payables']['data'])->pluck('id')->all();

        $this->assertSame([$older->id, $newer->id], $ids);
    }

    /**
     * Regressão: usuário restrito a um departamento ordenando por aprovador
     * (workflow_moment) gerava "column reference department_id is ambiguous",
     * pois o join com users/approval_steps duplica colunas do filtro.
     */
    public function test_ordenacao_por_aprovador_com_usuario_restrito_a_departamento(): void
    {
        $department = Department::create([
            'name' => 'Compras',
            'slug' => 'compras',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'is_active' => true,
            'department_id' => $department->id,
            'senior_cod_usu' => 999,
        ]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Visualizar CP', 'module' => 'financeiro'],
            )->id,
        );
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.ver_todas_filiais'],
                ['label' => 'Ver todas filiais', 'module' => 'financeiro'],
            )->id,
        );

        $mine = $this->makePayable([
            'status' => 'aguardando_aprovacao',
            'department_id' => $department->id,
        ]);
        $this->makePayable(['status' => 'aguardando_aprovacao']);

        $response = $this->actingAs($user)
            ->get('/financeiro/contas-pagar?status=aguardando_aprovacao&sort=workflow_moment&dir=asc')
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['payables']['data'])->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
    }

    public function test_ordenacao_por_valor_decrescente(): void
    {
        $small = $this->makePayable(['amount' => 100.00]);
        $large = $this->makePayable(['amount' => 9000.00]);

        $response = $this->actingAs($this->activeUser())
            ->get('/financeiro/contas-pagar?status=pendente&sort=amount&dir=desc')
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['payables']['data'])->pluck('id')->all();

        $this->assertSame([$large->id, $small->id], $ids);
    }
}
