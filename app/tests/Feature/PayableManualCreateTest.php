<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableManualCreateTest extends TestCase
{
    use RefreshDatabase;

    private function attachPerms(User $user, array $keys): void
    {
        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(
                    ['key' => $key],
                    ['label' => $key, 'module' => 'financeiro'],
                )->id,
            );
        }
    }

    private function userWith(array $keys): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->attachPerms($user, $keys);

        return $user;
    }

    private function seedBranch(): Branch
    {
        return Branch::create([
            'name' => 'Filial Matriz Teste',
            'apelido' => 'Matriz',
            'code' => '1',
            'cod_emp' => 2,
            'cod_fil' => 1,
            'is_active' => true,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title_number' => 'HUB-001',
            'supplier_name' => 'Fornecedor Manual LTDA',
            'supplier_cnpj' => '12.345.678/0001-99',
            'amount' => 1500.50,
            'due_date' => '2026-08-20',
            'issue_date' => '2026-07-15',
            'description' => 'Lançamento de teste',
            'filial' => '2-1',
            'codntg' => 100,
            'codccu' => '2363',
            'ctafin' => 10,
        ], $overrides);
    }

    public function test_usuario_com_permissao_acessa_formulario(): void
    {
        $this->seedBranch();
        $user = $this->userWith([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.lancar',
            'financeiro.contas_pagar.ver_todas_filiais',
        ]);

        $this->actingAs($user)
            ->get('/financeiro/contas-pagar/lancar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payables/Create', false)
                ->has('filiais')
                ->where('defaultDueDate', fn ($v) => is_string($v) && $v !== ''));
    }

    public function test_cria_titulo_manual_valido(): void
    {
        $branch = $this->seedBranch();
        $dept = Department::create(['name' => 'Compras', 'slug' => 'compras', 'is_active' => true]);
        $user = $this->userWith([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.lancar',
            'financeiro.contas_pagar.ver_todas_filiais',
            'financeiro.ver_todos_departamentos',
        ]);

        $response = $this->actingAs($user)
            ->post('/financeiro/contas-pagar', $this->validPayload([
                'department_id' => $dept->id,
                'nickname' => 'Teste Hub',
                'codfor' => 55,
                'codtns' => '9001',
            ]));

        $payable = Payable::query()->where('supplier_name', 'Fornecedor Manual LTDA')->first();
        $this->assertNotNull($payable);
        $response->assertRedirect("/financeiro/contas-pagar/{$payable->id}");

        $this->assertNull($payable->senior_id);
        $this->assertTrue($payable->isHubManual());
        $this->assertSame('pendente', $payable->status);
        $this->assertSame('HUB-001', $payable->title_number);
        $this->assertSame('Teste Hub', $payable->nickname);
        $this->assertSame('1500.50', (string) $payable->amount);
        $this->assertSame('2026-08-20', $payable->due_date->toDateString());
        $this->assertSame(2, (int) $payable->codemp);
        $this->assertSame(1, (int) $payable->codfil);
        $this->assertSame($branch->id, $payable->branch_id);
        $this->assertSame($dept->id, $payable->department_id);
        $this->assertSame($user->id, $payable->prepared_by);
        $this->assertSame(55, (int) $payable->codfor);
        $this->assertSame('2363', $payable->codccu);
        $this->assertSame(100, (int) $payable->codntg);
        $this->assertSame('Transação 9001', $payable->category);
        $this->assertDatabaseHas('payable_comments', [
            'payable_id' => $payable->id,
            'type' => 'status_change',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'contas_pagar.lancado_manual',
            'auditable_id' => $payable->id,
        ]);
    }

    public function test_sem_vencimento_aplica_default_72h(): void
    {
        $this->seedBranch();
        $user = $this->userWith([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.lancar',
            'financeiro.contas_pagar.ver_todas_filiais',
            'financeiro.ver_todos_departamentos',
        ]);

        $expected = Payable::defaultDueDate()->toDateString();

        $this->actingAs($user)
            ->post('/financeiro/contas-pagar', $this->validPayload([
                'due_date' => null,
            ]))
            ->assertRedirect();

        $payable = Payable::first();
        $this->assertNotNull($payable);
        $this->assertSame($expected, $payable->due_date->toDateString());
        $this->assertSame($expected, $payable->vctpro?->toDateString());
    }

    public function test_rejeita_sem_campos_obrigatorios(): void
    {
        $this->seedBranch();
        $user = $this->userWith([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.lancar',
            'financeiro.contas_pagar.ver_todas_filiais',
            'financeiro.ver_todos_departamentos',
        ]);

        $this->actingAs($user)
            ->post('/financeiro/contas-pagar', [])
            ->assertSessionHasErrors(['supplier_name', 'amount', 'filial']);

        $this->assertSame(0, Payable::count());
    }

    public function test_usuario_sem_permissao_nao_lanca(): void
    {
        $this->seedBranch();
        $user = $this->userWith(['financeiro.contas_pagar.visualizar']);

        $this->actingAs($user)
            ->get('/financeiro/contas-pagar/lancar')
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/financeiro/contas-pagar', $this->validPayload())
            ->assertForbidden();

        $this->assertSame(0, Payable::count());
    }

    public function test_listagem_indica_can_lancar(): void
    {
        $user = $this->userWith([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.lancar',
            'financeiro.contas_pagar.ver_todas_filiais',
        ]);

        $this->actingAs($user)
            ->get('/financeiro/contas-pagar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canLancar', true));
    }
}
