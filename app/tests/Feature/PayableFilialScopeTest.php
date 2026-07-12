<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableFilialScopeTest extends TestCase
{
    use RefreshDatabase;

    private function cpUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.visualizar'],
                ['label' => 'Visualizar CP', 'module' => 'financeiro'],
            )->id,
        );

        return $user;
    }

    private function makeBranch(string $name, string $code): Branch
    {
        return Branch::create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
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

    public function test_usuario_sem_filiais_nao_ve_titulos(): void
    {
        $this->makePayable(['supplier_name' => 'TituloA', 'codemp' => 1]);
        $this->makePayable(['supplier_name' => 'TituloB', 'codemp' => 2]);

        $resp = $this->actingAs($this->cpUser())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk();

        $this->assertCount(0, $resp->json('data'));
    }

    public function test_usuario_sem_filiais_recebe_alerta_na_lista(): void
    {
        $this->actingAs($this->cpUser())
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('noBranchAccess', true));
    }

    public function test_usuario_com_cod_emp_filial_ve_titulo_da_filial(): void
    {
        $filialGo = Branch::create([
            'name' => '5 ESTRELAS - FILIAL GO',
            'apelido' => 'GO',
            'cod_emp' => 2,
            'cod_fil' => 5,
            'code' => '15',
            'is_active' => true,
        ]);

        $user = $this->cpUser();
        $user->branches()->attach([$filialGo->id]);

        $this->makePayable(['supplier_name' => 'TituloGO', 'codemp' => 2, 'codfil' => 5]);
        $this->makePayable(['supplier_name' => 'TituloSP', 'codemp' => 2, 'codfil' => 6]);

        $resp = $this->actingAs($user)
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('supplier_name')->all();
        $this->assertSame(['TituloGO'], $names);
    }

    public function test_usuario_com_filiais_ve_apenas_titulos_liberados(): void
    {
        $filialA = $this->makeBranch('Filial A', '10');
        $this->makeBranch('Filial B', '20');

        $user = $this->cpUser();
        $user->branches()->attach([$filialA->id]);

        $this->makePayable(['supplier_name' => 'Liberado', 'branch_id' => $filialA->id, 'codemp' => 10]);
        $this->makePayable(['supplier_name' => 'Bloqueado', 'codemp' => 20]);

        $resp = $this->actingAs($user)
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('supplier_name')->all();
        $this->assertSame(['Liberado'], $names);
    }

    public function test_usuario_nao_acessa_titulo_de_filial_nao_liberada(): void
    {
        $filialA = $this->makeBranch('Filial A', '10');
        $user = $this->cpUser();
        $user->branches()->attach([$filialA->id]);

        $payable = $this->makePayable(['codemp' => 99]);

        $this->actingAs($user)
            ->get("/financeiro/contas-pagar/{$payable->id}")
            ->assertForbidden();
    }

    public function test_cadastro_usuario_sincroniza_filiais(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'usuarios.criar'],
                ['label' => 'Criar usuários', 'module' => 'usuarios'],
            )->id,
        );

        $filial = $this->makeBranch('Filial Sync', '15');

        $this->actingAs($admin)->post('/usuarios', [
            'name' => 'Usuario Filial',
            'email' => 'filial@test.com',
            'password' => 'SenhaForte1!',
            'is_active' => true,
            'branch_ids' => [$filial->id],
        ])->assertRedirect('/usuarios');

        $created = User::where('email', 'filial@test.com')->first();
        $this->assertNotNull($created);
        $this->assertSame([$filial->id], $created->branches()->pluck('branches.id')->all());
    }

    public function test_lista_usuarios_inclui_filiais(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'usuarios.listar'],
                ['label' => 'Listar usuários', 'module' => 'usuarios'],
            )->id,
        );

        $filial = $this->makeBranch('Filial Lista', '25');
        $target = User::factory()->create(['name' => 'Usuario Com Filial', 'is_active' => true]);
        $target->branches()->attach([$filial->id]);

        $this->actingAs($admin)
            ->get('/usuarios')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 2)
                ->where('users.data', fn ($users) => collect($users)->contains(
                    fn ($u) => $u['id'] === $target->id
                        && count($u['branches']) === 1
                        && $u['branches'][0]['id'] === $filial->id,
                )),
            );
    }

    public function test_atualizacao_usuario_sincroniza_filiais_e_audita(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'usuarios.editar'],
                ['label' => 'Editar usuários', 'module' => 'usuarios'],
            )->id,
        );

        $filialA = $this->makeBranch('Filial A Update', '30');
        $filialB = $this->makeBranch('Filial B Update', '31');

        $target = User::factory()->create([
            'name' => 'Usuario Update',
            'email' => 'update@test.com',
            'is_active' => true,
        ]);
        $target->branches()->attach([$filialA->id]);

        $this->actingAs($admin)->put("/usuarios/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'is_active' => true,
            'branch_ids' => [$filialB->id],
        ])->assertRedirect('/usuarios');

        $target->refresh();
        $this->assertSame([$filialB->id], $target->branches()->pluck('branches.id')->all());

        $log = AuditLog::where('event', 'usuarios.filiais_atualizadas')
            ->where('auditable_id', $target->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame([$filialA->id], $log->old_values['branch_ids']);
        $this->assertSame([$filialB->id], $log->new_values['branch_ids']);
    }
}
