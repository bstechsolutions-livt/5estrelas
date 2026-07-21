<?php

namespace Tests\Feature;

use App\Models\Bordero;
use App\Models\BorderoAutoRule;
use App\Models\BorderoAutoSetting;
use App\Models\Department;
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

    public function test_criar_regra_com_condicao_especifica(): void
    {
        $this->actingAs($this->manager())
            ->post('/financeiro/borderos/automatico', [
                'name' => 'CCU 3333',
                'filters' => [
                    ['field' => 'codccu', 'operator' => 'eq', 'value' => '3333'],
                ],
                'filter_logic' => 'and',
                'min_titles_per_group' => 2,
                'due_grouping' => BorderoAutoRule::DUE_NONE,
                'max_due_span_days' => 7,
                'eligibility_mode' => BorderoAutoRule::ELIGIBILITY_ALL,
                'apply_mode' => 'cron',
            ])
            ->assertRedirect('/financeiro/borderos/automatico');

        $rule = BorderoAutoRule::first();
        $this->assertSame('CCU 3333', $rule->name);
        $this->assertSame('3333', $rule->normalizedFilters()[0]['value']);
    }

    public function test_aplicar_regra_filtra_por_ccu_especifico(): void
    {
        $this->makePayable(['codccu' => '3333', 'amount' => 150]);
        $this->makePayable(['codccu' => '3333', 'amount' => 150]);
        $this->makePayable(['codccu' => '9999', 'amount' => 150]);
        $this->makePayable(['codccu' => '9999', 'amount' => 150]);

        $this->actingAs($this->manager())
            ->post('/financeiro/borderos/automatico', [
                'name' => 'Só CCU 3333',
                'filters' => [
                    ['field' => 'codccu', 'operator' => 'eq', 'value' => '3333'],
                ],
                'filter_logic' => 'and',
                'min_titles_per_group' => 2,
                'due_grouping' => BorderoAutoRule::DUE_NONE,
                'max_due_span_days' => 7,
                'eligibility_mode' => BorderoAutoRule::ELIGIBILITY_ALL,
                'apply_mode' => 'now',
            ])
            ->assertRedirect('/financeiro/borderos?status=pendente');

        $this->assertSame(1, Bordero::count());
        $this->assertSame(2, Payable::whereNotNull('bordero_id')->count());
    }

    public function test_simular_retorna_preview(): void
    {
        $this->makePayable(['codccu' => '3333']);
        $this->makePayable(['codccu' => '3333']);

        $this->actingAs($this->manager())
            ->postJson('/financeiro/borderos/automatico/simular', [
                'filters' => [
                    ['field' => 'codccu', 'operator' => 'eq', 'value' => '3333'],
                ],
                'filter_logic' => 'and',
                'min_titles_per_group' => 2,
                'due_grouping' => BorderoAutoRule::DUE_NONE,
                'max_due_span_days' => 7,
                'eligibility_mode' => BorderoAutoRule::ELIGIBILITY_ALL,
            ])
            ->assertOk()
            ->assertJsonPath('summary.suggested_groups', 1)
            ->assertJsonPath('summary.eligible_titles', 2);
    }

    public function test_filtro_or_aceita_qualquer_condicao(): void
    {
        $this->makePayable(['codccu' => '1111']);
        $this->makePayable(['codccu' => '2222']);

        $rule = BorderoAutoRule::fromPayload([
            'filters' => [
                ['field' => 'codccu', 'operator' => 'eq', 'value' => '1111'],
                ['field' => 'codccu', 'operator' => 'eq', 'value' => '2222'],
            ],
            'filter_logic' => 'or',
            'min_titles_per_group' => 2,
        ]);

        $preview = app(BorderoAutoGroupService::class)->preview($this->manager(), $rule);

        $this->assertSame(2, $preview['summary']['eligible_titles']);
    }

    public function test_cron_executa_regras_ativas(): void
    {
        BorderoAutoSetting::instance()->update(['cron_enabled' => true]);

        BorderoAutoRule::create([
            'name' => 'Cron test',
            'filters' => [['field' => 'codccu', 'operator' => 'eq', 'value' => '3333']],
            'filter_logic' => 'and',
            'is_active' => true,
            'min_titles_per_group' => 2,
        ]);

        foreach (['X1', 'X2'] as $num) {
            $this->makePayable(['title_number' => $num, 'codccu' => '3333', 'amount' => 150]);
        }

        $this->artisan('borderos:auto-generate --scheduled')->assertSuccessful();

        $this->assertSame(1, Bordero::count());
    }

    public function test_agendamento_pausado_nao_cria_bordero(): void
    {
        BorderoAutoSetting::instance()->update(['cron_enabled' => false]);

        BorderoAutoRule::create([
            'name' => 'Regra ativa mas cron off',
            'filters' => [['field' => 'codccu', 'operator' => 'eq', 'value' => '3333']],
            'filter_logic' => 'and',
            'is_active' => true,
            'min_titles_per_group' => 2,
        ]);

        $this->makePayable(['codccu' => '3333']);
        $this->makePayable(['codccu' => '3333']);

        $this->artisan('borderos:auto-generate --scheduled')->assertSuccessful();

        $this->assertSame(0, Bordero::count());
    }

    public function test_toggle_agendamento_global(): void
    {
        $this->actingAs($this->manager())
            ->post('/financeiro/borderos/automatico/agendamento/toggle')
            ->assertRedirect('/financeiro/borderos/automatico');

        $this->assertFalse(BorderoAutoSetting::instance()->fresh()->cron_enabled);

        $this->actingAs($this->manager())
            ->post('/financeiro/borderos/automatico/agendamento/toggle')
            ->assertRedirect('/financeiro/borderos/automatico');

        $this->assertTrue(BorderoAutoSetting::instance()->fresh()->cron_enabled);
    }

    public function test_toggle_regra_individual(): void
    {
        $rule = BorderoAutoRule::create([
            'name' => 'Toggle test',
            'filters' => [['field' => 'codccu', 'operator' => 'eq', 'value' => '3333']],
            'filter_logic' => 'and',
            'is_active' => true,
            'min_titles_per_group' => 2,
        ]);

        $this->actingAs($this->manager())
            ->post("/financeiro/borderos/automatico/{$rule->id}/toggle")
            ->assertRedirect('/financeiro/borderos/automatico');

        $this->assertFalse($rule->fresh()->is_active);
    }

    public function test_opcoes_filtro_retorna_valores(): void
    {
        $this->makePayable(['codntg' => 42, 'codccu' => '5555']);

        $this->actingAs($this->manager())
            ->getJson('/financeiro/borderos/automatico/opcoes-filtro?field=codntg')
            ->assertOk()
            ->assertJsonFragment(['value' => '42']);
    }

    public function test_filtro_por_descricao_e_agrupa_por_vencimento(): void
    {
        $dayA = now()->addDays(3)->toDateString();
        $dayB = now()->addDays(10)->toDateString();

        $this->makePayable([
            'description' => 'FUNDO FIXO: TIAGO DOS SANTOS MENEZES — pedágio',
            'due_date' => $dayA,
            'amount' => 50,
        ]);
        $this->makePayable([
            'description' => 'FUNDO FIXO: TIAGO MENEZES — estacionamento',
            'due_date' => $dayA,
            'amount' => 40,
        ]);
        $this->makePayable([
            'description' => 'FUNDO FIXO: TIAGO DOS SANTOS MENEZES — outro',
            'due_date' => $dayB,
            'amount' => 30,
        ]);
        $this->makePayable([
            'description' => 'FUNDO FIXO: TIAGO DOS SANTOS MENEZES — solo no dia B',
            'due_date' => $dayB,
            'amount' => 25,
        ]);
        $this->makePayable([
            'description' => 'REF A LINHA VOZ E DADOS',
            'due_date' => $dayA,
            'amount' => 99,
        ]);

        $this->actingAs($this->manager())
            ->post('/financeiro/borderos/automatico', [
                'name' => 'Fundo fixo Tiago',
                'filters' => [
                    ['field' => 'description', 'operator' => 'contains', 'value' => 'FUNDO FIXO: TIAGO'],
                ],
                'filter_logic' => 'and',
                'min_titles_per_group' => 2,
                'due_grouping' => BorderoAutoRule::DUE_SAME_DAY,
                'max_due_span_days' => 7,
                'eligibility_mode' => BorderoAutoRule::ELIGIBILITY_ALL,
                'apply_mode' => 'now',
            ])
            ->assertRedirect('/financeiro/borderos?status=pendente');

        $this->assertSame(2, Bordero::count());
        $this->assertSame(4, Payable::whereNotNull('bordero_id')->count());
        $this->assertSame(0, Payable::where('description', 'like', '%LINHA VOZ%')->whereNotNull('bordero_id')->count());
    }

    public function test_filtro_agrupa_por_mes_de_vencimento(): void
    {
        $month = now()->startOfMonth()->addMonth();
        $dayA = $month->copy()->day(1)->toDateString();
        $dayB = $month->copy()->day(3)->toDateString();
        $otherMonth = $month->copy()->addMonth()->day(5)->toDateString();

        $this->makePayable([
            'description' => 'CARTAO CREDITO — parcela A',
            'due_date' => $dayA,
            'amount' => 50,
        ]);
        $this->makePayable([
            'description' => 'CARTAO CREDITO — parcela B',
            'due_date' => $dayB,
            'amount' => 40,
        ]);
        $this->makePayable([
            'description' => 'CARTAO CREDITO — outro mes 1',
            'due_date' => $otherMonth,
            'amount' => 30,
        ]);
        $this->makePayable([
            'description' => 'CARTAO CREDITO — outro mes 2',
            'due_date' => $otherMonth,
            'amount' => 25,
        ]);

        $this->actingAs($this->manager())
            ->post('/financeiro/borderos/automatico', [
                'name' => 'Cartão de crédito',
                'filters' => [
                    ['field' => 'description', 'operator' => 'contains', 'value' => 'CARTAO CREDITO'],
                ],
                'filter_logic' => 'and',
                'min_titles_per_group' => 2,
                'due_grouping' => BorderoAutoRule::DUE_SAME_MONTH,
                'max_due_span_days' => 7,
                'eligibility_mode' => BorderoAutoRule::ELIGIBILITY_ALL,
                'apply_mode' => 'now',
            ])
            ->assertRedirect('/financeiro/borderos?status=pendente');

        $this->assertSame(2, Bordero::count());
        $this->assertSame(4, Payable::whereNotNull('bordero_id')->count());
    }
}
