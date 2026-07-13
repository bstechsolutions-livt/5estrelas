<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Comercial\Filial;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableFilialFilterTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(array $keys = ['financeiro.contas_pagar.visualizar']): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach (array_merge($keys, ['financeiro.contas_pagar.ver_todas_filiais']) as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => 'financeiro'])->id
            );
        }

        return $user;
    }

    private function seedEmpresa(int $codEmp, string $nome, string $apelido): void
    {
        Filial::create([
            'cod_emp' => $codEmp,
            'cod_fil' => 1,
            'senior_id' => "{$codEmp}-1",
            'nome' => $nome,
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

    private function indexJson(User $user, string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->withHeaders(['X-Json-Only' => '1'])
            ->get('/financeiro/contas-pagar' . $query);
    }

    public function test_index_nao_lista_empresas_excluidas_no_filtro(): void
    {
        $this->seedEmpresa(2, '5 ESTRELAS SISTEMA DE SEGURANCA LTDA', '5 ESTRELAS');
        $this->seedEmpresa(4, 'ARI CONSTRUTORA E ADMINISTRADORA LTDA', 'ARI ADM');
        $this->seedEmpresa(9, 'BALUARTE VIGILANCIA PATRIMONIAL LTDA', 'BALUARTE');
        $this->seedEmpresa(12, 'LSR LTDA', 'LSR');

        $this->actingAs($this->activeUser())
            ->get('/financeiro/contas-pagar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('empresas', 2)
                ->where('empresas.0.label', '5 ESTRELAS')
                ->where('empresas.1.label', 'BALUARTE')
            );
    }

    public function test_index_filtra_por_filial_operacional(): void
    {
        $this->seedEmpresa(2, '5 ESTRELAS SISTEMA DE SEGURANCA LTDA', '5 ESTRELAS');
        Filial::create([
            'cod_emp' => 2,
            'cod_fil' => 5,
            'senior_id' => '2-5',
            'nome' => '5 ESTRELAS SISTEMA DE SEGURANCA LTDA - MATRIZ GERENCIAL',
            'fantasia' => '5 ESTRELAS MATRIZ',
            'apelido' => '5 ESTRELAS MATRIZ',
            'ativo' => true,
        ]);

        Branch::create([
            'name' => '5 ESTRELAS SISTEMA DE SEGURANCA LTDA - MATRIZ GERENCIAL',
            'apelido' => '5 ESTRELAS MATRIZ',
            'cod_emp' => 2,
            'cod_fil' => 5,
            'code' => '5',
            'is_active' => true,
        ]);

        $this->makePayable([
            'supplier_name' => 'Fornecedor GO',
            'codemp' => 2,
            'codfil' => 2,
            'status' => 'pendente',
        ]);
        $this->makePayable([
            'supplier_name' => 'Fornecedor Gerencial',
            'codemp' => 2,
            'codfil' => 5,
            'status' => 'pendente',
        ]);

        $resp = $this->indexJson($this->activeUser(), '?status=pendente&filial=2-5')
            ->assertOk();

        $names = collect($resp->json('data'))->pluck('supplier_name')->all();

        $this->assertSame(['Fornecedor Gerencial'], $names);
    }

    public function test_titulos_de_empresa_excluida_nao_aparecem_na_listagem(): void
    {
        $this->seedEmpresa(4, 'ARI CONSTRUTORA E ADMINISTRADORA LTDA', 'ARI ADM');

        $this->makePayable([
            'supplier_name' => 'Fornecedor ARI',
            'codemp' => 4,
            'codfil' => 1,
            'status' => 'pendente',
        ]);

        $resp = $this->indexJson($this->activeUser(), '?status=pendente')
            ->assertOk();

        $this->assertCount(0, $resp->json('data'));
    }
}
