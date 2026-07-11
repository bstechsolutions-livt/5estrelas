<?php

namespace Tests\Feature;

use App\Models\Bordero;
use App\Models\BorderoAutoRule;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use App\Services\BorderoAutoGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorderoAutoRuleTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ([
            'financeiro.borderos.automatico_gerenciar' => 'Borderô auto',
            'financeiro.borderos.visualizar' => 'Ver borderôs',
            'financeiro.contas_pagar.ver_todas_filiais' => 'Ver todas filiais',
        ] as $key => $label) {
            $user->permissions()->attach(
                Permission::firstOrCreate(
                    ['key' => $key],
                    ['label' => $label, 'module' => 'financeiro'],
                )->id,
            );
        }

        return $user;
    }

    /** @param array<string, mixed> $attrs */
    private function makePayable(array $attrs = []): Payable
    {
        return Payable::create(array_merge([
            'title_number' => 'TIT-' . uniqid(),
            'supplier_name' => 'Fornecedor',
            'amount' => 100,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pendente',
            'codemp' => 2,
            'codccu' => '3333',
            'senior_id' => '2-1-' . uniqid() . '-01-100',
        ], $attrs));
    }

    public function test_lista_exige_permissao(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/financeiro/borderos/automatico')
            ->assertForbidden();
    }

    public function test_lista_regras_vazia(): void
    {
        $this->actingAs($this->manager())
            ->get('/financeiro/borderos/automatico')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Borderos/AutoRules/Index', false)
                ->has('rules', 0)
            );
    }

    public function test_criar_regra_aguardar_cron(): void
    {
        $this->actingAs($this->manager())
            ->post('/financeiro/borderos/automatico', [
                'name' => 'Regra DP',
                'min_titles_per_group' => 2,
                'due_grouping' => BorderoAutoRule::DUE_NONE,
                'max_due_span_days' => 7,
                'eligibility_mode' => BorderoAutoRule::ELIGIBILITY_ALL,
                'eligibility_due_days' => null,
                'apply_mode' => 'cron',
            ])
            ->assertRedirect('/financeiro/borderos/automatico');

        $this->assertDatabaseHas('bordero_auto_rules', [
            'name' => 'Regra DP',
            'is_active' => true,
        ]);
        $this->assertSame(0, Bordero::count());
    }

    public function test_criar_regra_aplicar_agora(): void
    {
        foreach (['X1', 'X2'] as $num) {
            $this->makePayable(['title_number' => $num, 'amount' => 150]);
        }

        $this->actingAs($this->manager())
            ->post('/financeiro/borderos/automatico', [
                'name' => 'Aplicar já',
                'min_titles_per_group' => 2,
                'due_grouping' => BorderoAutoRule::DUE_NONE,
                'max_due_span_days' => 7,
                'eligibility_mode' => BorderoAutoRule::ELIGIBILITY_ALL,
                'apply_mode' => 'now',
            ])
            ->assertRedirect('/financeiro/borderos?status=rascunho');

        $this->assertSame(1, Bordero::count());
        $rule = BorderoAutoRule::first();
        $this->assertSame(1, $rule->last_applied_count);
    }

    public function test_simular_retorna_preview(): void
    {
        $this->makePayable();
        $this->makePayable();

        $this->actingAs($this->manager())
            ->postJson('/financeiro/borderos/automatico/simular', [
                'min_titles_per_group' => 2,
                'due_grouping' => BorderoAutoRule::DUE_NONE,
                'max_due_span_days' => 7,
                'eligibility_mode' => BorderoAutoRule::ELIGIBILITY_ALL,
            ])
            ->assertOk()
            ->assertJsonPath('summary.suggested_groups', 1);
    }

    public function test_agrupa_por_mesmo_dia_de_vencimento(): void
    {
        $rule = BorderoAutoRule::create([
            'name' => 'Mesmo dia',
            'is_active' => true,
            'due_grouping' => BorderoAutoRule::DUE_SAME_DAY,
            'min_titles_per_group' => 2,
        ]);

        foreach (['A', 'B'] as $num) {
            Payable::create([
                'title_number' => $num,
                'supplier_name' => 'Fornecedor',
                'amount' => 50,
                'due_date' => '2026-07-15',
                'status' => 'pendente',
                'codemp' => 2,
                'codccu' => '8888',
                'senior_id' => "2-1-{$num}-01-100",
            ]);
        }
        Payable::create([
            'title_number' => 'C',
            'supplier_name' => 'Fornecedor',
            'amount' => 50,
            'due_date' => '2026-07-20',
            'status' => 'pendente',
            'codemp' => 2,
            'codccu' => '8888',
            'senior_id' => '2-1-C-01-100',
        ]);
        Payable::create([
            'title_number' => 'D',
            'supplier_name' => 'Fornecedor',
            'amount' => 50,
            'due_date' => '2026-07-20',
            'status' => 'pendente',
            'codemp' => 2,
            'codccu' => '8888',
            'senior_id' => '2-1-D-01-100',
        ]);

        $preview = app(BorderoAutoGroupService::class)->preview($this->manager(), $rule);

        $this->assertCount(2, $preview['groups']);
    }

    public function test_cron_executa_regras_ativas(): void
    {
        BorderoAutoRule::create([
            'name' => 'Cron test',
            'is_active' => true,
            'min_titles_per_group' => 2,
        ]);

        foreach (['X1', 'X2'] as $num) {
            $this->makePayable(['title_number' => $num, 'amount' => 150]);
        }

        $this->artisan('borderos:auto-generate --scheduled')->assertSuccessful();

        $this->assertSame(1, Bordero::count());
        $rule = BorderoAutoRule::first();
        $this->assertNotNull($rule->last_cron_at);
        $this->assertSame(1, $rule->last_cron_count);
    }

    public function test_cron_ignora_regra_inativa(): void
    {
        BorderoAutoRule::create([
            'name' => 'Inativa',
            'is_active' => false,
            'min_titles_per_group' => 2,
        ]);

        foreach (['X1', 'X2'] as $num) {
            $this->makePayable(['title_number' => $num, 'amount' => 150]);
        }

        $this->artisan('borderos:auto-generate --scheduled')->assertSuccessful();

        $this->assertSame(0, Bordero::count());
    }

    public function test_toggle_regra(): void
    {
        $rule = BorderoAutoRule::create([
            'name' => 'Toggle',
            'is_active' => true,
            'min_titles_per_group' => 2,
        ]);

        $this->actingAs($this->manager())
            ->post("/financeiro/borderos/automatico/{$rule->id}/toggle")
            ->assertRedirect();

        $this->assertFalse($rule->fresh()->is_active);
    }
}
