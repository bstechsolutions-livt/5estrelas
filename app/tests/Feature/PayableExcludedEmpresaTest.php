<?php

namespace Tests\Feature;

use App\Models\Comercial\Filial;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use App\Services\PayableBranchScope;
use App\Services\ReceivableBranchScope;
use App\Support\PayableEmpresaExclusion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableExcludedEmpresaTest extends TestCase
{
    use RefreshDatabase;

    private function cpUserWithAllBranches(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ([
            'financeiro.contas_pagar.visualizar',
            'financeiro.contas_pagar.ver_todas_filiais',
        ] as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id,
            );
        }

        return $user;
    }

    private function makeFilial(int $codEmp, string $apelido): Filial
    {
        return Filial::create([
            'cod_emp' => $codEmp,
            'cod_fil' => 1,
            'senior_id' => "{$codEmp}-1",
            'nome' => "{$apelido} LTDA",
            'fantasia' => $apelido,
            'apelido' => $apelido,
            'ativo' => true,
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

    public function test_filter_cod_emps_remove_excluidas(): void
    {
        $this->assertSame([2, 3], PayableEmpresaExclusion::filterCodEmps([2, 3, 4, 9, 12]));
    }

    public function test_config_exclui_ari_adm_baluarte_e_lsr(): void
    {
        $this->assertSame([4, 9, 12], PayableEmpresaExclusion::excludedCodEmps());
    }

    public function test_empresa_options_nao_incluem_excluidas(): void
    {
        $this->makeFilial(2, '5 ESTRELAS');
        $this->makeFilial(4, 'ARI ADM');
        $this->makeFilial(9, 'BALUARTE');
        $this->makeFilial(12, 'LSR');

        $user = $this->cpUserWithAllBranches();
        $options = app(PayableBranchScope::class)->empresaOptionsForUser($user);

        $values = array_column($options, 'value');
        $this->assertContains(2, $values);
        $this->assertNotContains(4, $values);
        $this->assertNotContains(9, $values);
    }

    public function test_receivable_empresa_options_tambem_excluem(): void
    {
        $this->makeFilial(2, '5 ESTRELAS');
        $this->makeFilial(4, 'ARI ADM');

        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_receber.ver_todas_filiais'],
                ['label' => 'Ver todas filiais CR', 'module' => 'financeiro'],
            )->id,
        );

        $options = app(ReceivableBranchScope::class)->empresaOptionsForUser($user);
        $values = array_column($options, 'value');

        $this->assertContains(2, $values);
        $this->assertNotContains(4, $values);
    }

    public function test_index_nao_lista_titulos_de_empresas_excluidas(): void
    {
        $this->makeFilial(2, '5 ESTRELAS');
        $this->makeFilial(4, 'ARI ADM');
        $this->makeFilial(9, 'BALUARTE');

        $this->makePayable(['codemp' => 2, 'supplier_name' => 'TituloValido']);
        $this->makePayable(['codemp' => 4, 'supplier_name' => 'TituloAri']);
        $this->makePayable(['codemp' => 9, 'supplier_name' => 'TituloBaluarte']);

        $resp = $this->actingAs($this->cpUserWithAllBranches())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('supplier_name')->all();
        $this->assertSame(['TituloValido'], $names);
    }

    public function test_filtro_codemp_excluido_retorna_vazio(): void
    {
        $this->makeFilial(4, 'ARI ADM');
        $this->makePayable(['codemp' => 4, 'supplier_name' => 'TituloAri']);

        $resp = $this->actingAs($this->cpUserWithAllBranches())
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente&codemp=4')
            ->assertOk();

        $this->assertCount(0, $resp->json('data'));
    }

    public function test_show_bloqueia_titulo_de_empresa_excluida(): void
    {
        $this->makeFilial(4, 'ARI ADM');
        $payable = $this->makePayable(['codemp' => 4]);

        $this->actingAs($this->cpUserWithAllBranches())
            ->get("/financeiro/contas-pagar/{$payable->id}")
            ->assertForbidden();
    }

    public function test_inertia_index_nao_expoe_empresas_excluidas(): void
    {
        $this->makeFilial(2, '5 ESTRELAS');
        $this->makeFilial(4, 'ARI ADM');
        $this->makeFilial(9, 'BALUARTE');

        $this->actingAs($this->cpUserWithAllBranches())
            ->get('/financeiro/contas-pagar?status=pendente')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('empresas', 1)
                ->where('empresas.0.value', 2),
            );
    }
}
