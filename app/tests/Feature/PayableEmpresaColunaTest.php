<?php

namespace Tests\Feature;

use App\Models\Comercial\Filial;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A3 — Coluna Empresa (por NOME, nunca código) na tela principal.
 *
 * O nome da empresa é resolvido pelo codEmp do título a partir da tabela de
 * empresas/filiais espelhada da Senior (bs_comercial_filiais). Regra do projeto:
 * nunca exibir código, sempre nome.
 */
class PayableEmpresaColunaTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(array $keys = ['financeiro.contas_pagar.visualizar']): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id
            );
        }

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

    private function makeFilial(int $codEmp, string $nome, ?string $fantasia = null): Filial
    {
        return Filial::create([
            'cod_emp' => $codEmp,
            'cod_fil' => 1,
            'senior_id' => "{$codEmp}-1",
            'nome' => $nome,
            'fantasia' => $fantasia,
            'ativo' => true,
        ]);
    }

    private function indexJson(User $user): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar?status=pendente');
    }

    public function test_index_resolve_nome_da_empresa_pelo_codemp(): void
    {
        $this->makeFilial(2, '5 ESTRELAS SISTEMA DE SEGURANCA LTDA', '5 ESTRELAS');
        $this->makePayable(['codemp' => 2, 'supplier_name' => 'FornecedorEmp2']);

        $user = $this->activeUser();
        $resp = $this->indexJson($user)->assertOk();

        $data = collect($resp->json('data'));
        $row = $data->firstWhere('supplier_name', 'FornecedorEmp2');

        $this->assertNotNull($row);
        // Preferência pela fantasia (abreviada), nunca o código.
        $this->assertSame('5 ESTRELAS', $row['empresa_nome']);
        $this->assertArrayHasKey('empresa_nome', $row);
    }

    public function test_usa_razao_social_quando_sem_fantasia(): void
    {
        $this->makeFilial(3, '5 ESTRELAS SERVICOS DE APOIO ADMINISTRATIVO LTDA', null);
        $this->makePayable(['codemp' => 3, 'supplier_name' => 'FornecedorEmp3']);

        $resp = $this->indexJson($this->activeUser())->assertOk();
        $row = collect($resp->json('data'))->firstWhere('supplier_name', 'FornecedorEmp3');

        $this->assertSame('5 ESTRELAS SERVICOS DE APOIO ADMINISTRATIVO LTDA', $row['empresa_nome']);
    }

    public function test_empresa_nome_nulo_quando_titulo_sem_codemp(): void
    {
        $this->makePayable(['supplier_name' => 'FornecedorSemEmpresa']); // codemp null

        $resp = $this->indexJson($this->activeUser())->assertOk();
        $row = collect($resp->json('data'))->firstWhere('supplier_name', 'FornecedorSemEmpresa');

        $this->assertNull($row['empresa_nome']);
    }

    public function test_show_expoe_nome_da_empresa(): void
    {
        $this->makeFilial(2, 'Empresa Dois LTDA', 'EMP DOIS');
        $payable = $this->makePayable(['codemp' => 2]);

        $this->actingAs($this->activeUser())
            ->get("/financeiro/contas-pagar/{$payable->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('payable.empresa_nome', 'EMP DOIS')
            );
    }
}
