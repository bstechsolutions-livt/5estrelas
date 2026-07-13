<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Comercial\Filial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    private function seedEmpresaSeguranca(): Filial
    {
        return Filial::create([
            'cod_emp' => 2,
            'cod_fil' => 1,
            'senior_id' => '2-1',
            'nome' => '5 ESTRELAS SISTEMA DE SEGURANCA LTDA',
            'fantasia' => '5 ESTRELAS',
            'apelido' => '5 ESTRELAS',
            'ativo' => true,
        ]);
    }

    public function test_apelido_local_tem_prioridade_sobre_senior(): void
    {
        $this->seedEmpresaSeguranca();

        Filial::create([
            'cod_emp' => 2,
            'cod_fil' => 5,
            'senior_id' => '2-5',
            'nome' => '5 ESTRELAS SISTEMA DE SEGURANCA LTDA - FILIAL GO',
            'apelido' => '5 ESTRELAS GO',
            'ativo' => true,
        ]);

        $branch = Branch::create([
            'name' => '5 ESTRELAS SISTEMA DE SEGURANCA LTDA - FILIAL GO',
            'apelido' => 'GOIANIA',
            'cod_emp' => 2,
            'cod_fil' => 5,
            'code' => '15',
            'is_active' => true,
        ]);

        $this->assertSame('GOIANIA', $branch->resolveDisplayName());
        $this->assertSame('5 ESTRELAS', $branch->empresaApelido());
    }

    public function test_filial_regional_usa_apelido_da_empresa_mais_sufixo(): void
    {
        $this->seedEmpresaSeguranca();

        $branchGo = Branch::create([
            'name' => '5 ESTRELAS SISTEMA DE SEGURANCA LTDA - FILIAL GO',
            'code' => '2',
            'cnpj' => '72591894000223',
            'is_active' => true,
        ]);

        $branchMt = Branch::create([
            'name' => '5 ESTRELAS SISTEMA DE SEGURANCA LTDA - FILIAL MT',
            'code' => '15',
            'cnpj' => '72591894000304',
            'is_active' => true,
        ]);

        $this->assertSame('5 ESTRELAS GO', $branchGo->resolveDisplayName());
        $this->assertSame('5 ESTRELAS MT', $branchMt->resolveDisplayName());
    }

    public function test_cod_fil_nao_confunde_com_cod_emp_de_outra_empresa(): void
    {
        $this->seedEmpresaSeguranca();

        Filial::create([
            'cod_emp' => 6,
            'cod_fil' => 1,
            'senior_id' => '6-1',
            'nome' => '5 ESTRELAS SERVICOS ESPECIALIZADOS',
            'fantasia' => 'SRV ESPEC',
            'apelido' => 'SRV ESPEC',
            'ativo' => true,
        ]);

        $branchSp = Branch::create([
            'name' => '5 ESTRELAS SISTEMA DE SEGURANCA LTDA - FILIAL SP',
            'code' => '6',
            'cnpj' => '72591894000576',
            'is_active' => true,
        ]);

        $this->assertSame('5 ESTRELAS SP', $branchSp->resolveDisplayName());
    }

    public function test_matriz_gerencial_da_empresa_seguranca(): void
    {
        $this->seedEmpresaSeguranca();

        $branch = Branch::create([
            'name' => '5 ESTRELAS SISTEMA DE SEGURANCA LTDA - MATRIZ GERENCIAL',
            'code' => '5',
            'cnpj' => '72591894000142',
            'is_active' => true,
        ]);

        $this->assertSame('5 ESTRELAS MATRIZ', $branch->resolveDisplayName());
        $this->assertSame('MATRIZ GERENCIAL', $branch->operationalFilialName());
    }

    public function test_resolve_display_name_por_cnpj_quando_cod_fil_ambiguo(): void
    {
        Filial::create([
            'cod_emp' => 2,
            'cod_fil' => 1,
            'senior_id' => '2-1',
            'nome' => 'Filial A',
            'apelido' => 'APELIDO A',
            'cnpj' => '12.345.678/0001-90',
            'ativo' => true,
        ]);

        Filial::create([
            'cod_emp' => 5,
            'cod_fil' => 1,
            'senior_id' => '5-1',
            'nome' => 'Filial B',
            'apelido' => 'APELIDO B',
            'cnpj' => '98.765.432/0001-10',
            'ativo' => true,
        ]);

        $branch = Branch::create([
            'name' => 'Filial B cadastro',
            'code' => '1',
            'cnpj' => '98765432000110',
            'is_active' => true,
        ]);

        $this->assertSame('APELIDO B', $branch->resolveDisplayName());
    }

    public function test_empresa_distinta_sem_sufixo_regional(): void
    {
        Filial::create([
            'cod_emp' => 5,
            'cod_fil' => 1,
            'senior_id' => '5-1',
            'nome' => '5 ESTRELAS REFEICOES COLETIVAS',
            'fantasia' => 'REFEICOES',
            'apelido' => 'REFEICOES',
            'ativo' => true,
        ]);

        Filial::create([
            'cod_emp' => 6,
            'cod_fil' => 1,
            'senior_id' => '6-1',
            'nome' => '5 ESTRELAS SERVICOS ESPECIALIZADOS',
            'fantasia' => 'SRV ESPEC',
            'apelido' => 'SRV ESPEC',
            'ativo' => true,
        ]);

        $refeicoes = Branch::create([
            'name' => '5 ESTRELAS REFEIÇÕES COLETIVAS EIRELI',
            'code' => '22',
            'is_active' => true,
        ]);

        $srvEspec = Branch::create([
            'name' => '5 ESTRELAS SERVIÇOS ESPECIALIZADOS',
            'code' => '24',
            'is_active' => true,
        ]);

        $this->assertSame('REFEICOES', $refeicoes->resolveDisplayName());
        $this->assertSame('SRV ESPEC', $srvEspec->resolveDisplayName());
    }
}
