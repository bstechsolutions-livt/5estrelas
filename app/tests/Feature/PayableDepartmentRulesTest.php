<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Payable;
use App\Models\PayableDepartmentRule;
use App\Models\Permission;
use App\Models\User;
use App\Services\PayableDepartmentClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayableDepartmentRulesTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['key' => 'financeiro.contas_pagar.classificacao_gerenciar'],
                ['label' => 'Classificação CP', 'module' => 'financeiro'],
            )->id,
        );

        return $user;
    }

    public function test_tela_exige_permissao(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/financeiro/contas-pagar/classificacao-departamentos')
            ->assertForbidden();
    }

    public function test_salvar_regras_persiste_no_banco(): void
    {
        $dept = Department::create(['name' => 'DP / RH', 'slug' => 'dp_rh', 'is_active' => true]);

        $this->actingAs($this->adminUser())
            ->post('/financeiro/contas-pagar/classificacao-departamentos', [
                'rules' => [
                    [
                        'department_id' => $dept->id,
                        'codccu_text' => "2363\n2566",
                        'description_text' => "GFD\nTRCT",
                    ],
                ],
            ])
            ->assertRedirect();

        $rule = PayableDepartmentRule::where('department_id', $dept->id)->first();
        $this->assertNotNull($rule);
        $this->assertSame(['2363', '2566'], $rule->codccu);
        $this->assertSame(['%GFD%', '%TRCT%'], $rule->description_patterns);
    }

    public function test_regras_do_banco_alimentam_classificador(): void
    {
        $dept = Department::create(['name' => 'Financeiro', 'slug' => 'financeiro', 'is_active' => true]);
        PayableDepartmentRule::create([
            'department_id' => $dept->id,
            'codccu' => [],
            'description_patterns' => ['%SERASA%'],
        ]);

        $payable = Payable::create([
            'title_number' => 'T-1',
            'supplier_name' => 'Teste',
            'amount' => 100,
            'due_date' => now()->addDay(),
            'status' => 'pendente',
            'description' => 'TAXA SERASA MENSAL',
        ]);

        $resolved = app(PayableDepartmentClassifier::class)->departmentForPayable($payable);

        $this->assertSame($dept->id, $resolved->id);
    }

    public function test_limpar_campos_remove_regra(): void
    {
        $dept = Department::create(['name' => 'Compras', 'slug' => 'compras', 'is_active' => true]);
        PayableDepartmentRule::create([
            'department_id' => $dept->id,
            'codccu' => ['6289'],
            'description_patterns' => ['%UBER%'],
        ]);

        $this->actingAs($this->adminUser())
            ->post('/financeiro/contas-pagar/classificacao-departamentos', [
                'rules' => [
                    [
                        'department_id' => $dept->id,
                        'codccu_text' => '',
                        'description_text' => '',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertNull(PayableDepartmentRule::where('department_id', $dept->id)->first());
    }
}
