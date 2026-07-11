<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Comercial\Filial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_display_name_pelo_cod_fil_e_apelido(): void
    {
        Filial::create([
            'cod_emp' => 2,
            'cod_fil' => 3,
            'senior_id' => '2-3',
            'nome' => '5 ESTRELAS SISTEMA DE SEGURANCA LTDA',
            'fantasia' => '5 ESTRELAS',
            'apelido' => '5 ESTRELAS GO',
            'ativo' => true,
        ]);

        Filial::create([
            'cod_emp' => 2,
            'cod_fil' => 1,
            'senior_id' => '2-1',
            'nome' => '5 ESTRELAS SISTEMA DE SEGURANCA LTDA',
            'fantasia' => '5 ESTRELAS',
            'apelido' => '5 ESTRELAS MATRIZ',
            'ativo' => true,
        ]);

        $branchGo = Branch::create([
            'name' => '5 ESTRELAS FILIAL GO',
            'code' => '3',
            'is_active' => true,
        ]);

        $branchMatriz = Branch::create([
            'name' => '5 ESTRELAS MATRIZ',
            'code' => '1',
            'is_active' => true,
        ]);

        $this->assertSame('5 ESTRELAS GO', $branchGo->resolveDisplayName());
        $this->assertSame('5 ESTRELAS MATRIZ', $branchMatriz->resolveDisplayName());
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
}
