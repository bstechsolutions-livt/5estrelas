<?php

namespace Tests\Feature;

use App\Models\Bordero;
use App\Models\BorderoAutoConfig;
use App\Models\Payable;
use App\Models\Permission;
use App\Models\User;
use App\Services\BorderoAutoGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorderoAutoConfigTest extends TestCase
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

    public function test_tela_exige_permissao(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/financeiro/borderos/automatico')
            ->assertForbidden();
    }

    public function test_tela_carrega_com_simulacao(): void
    {
        Payable::create([
            'title_number' => 'T-1',
            'supplier_name' => 'Fornecedor A',
            'amount' => 100,
            'due_date' => now()->addDays(5),
            'status' => 'pendente',
            'codemp' => 2,
            'codccu' => '2363',
            'senior_id' => '2-1-T-01-100',
        ]);
        Payable::create([
            'title_number' => 'T-2',
            'supplier_name' => 'Fornecedor B',
            'amount' => 200,
            'due_date' => now()->addDays(5),
            'status' => 'pendente',
            'codemp' => 2,
            'codccu' => '2363',
            'senior_id' => '2-1-T-02-100',
        ]);

        $this->actingAs($this->manager())
            ->get('/financeiro/borderos/automatico')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Borderos/AutoConfig', false)
                ->has('config')
                ->has('preview.groups', 1)
                ->has('preview.rules_summary')
            );
    }

    public function test_salvar_configuracao(): void
    {
        $this->actingAs($this->manager())
            ->post('/financeiro/borderos/automatico', [
                'min_titles_per_group' => 3,
                'due_grouping' => BorderoAutoConfig::DUE_SAME_DAY,
                'max_due_span_days' => 5,
                'eligibility_mode' => BorderoAutoConfig::ELIGIBILITY_ALL,
                'eligibility_due_days' => null,
                'cron_enabled' => true,
            ])
            ->assertRedirect();

        $config = BorderoAutoConfig::current();
        $this->assertSame(BorderoAutoConfig::DUE_SAME_DAY, $config->due_grouping);
        $this->assertSame(3, $config->min_titles_per_group);
        $this->assertTrue($config->cron_enabled);
    }

    public function test_agrupa_por_mesmo_dia_de_vencimento(): void
    {
        BorderoAutoConfig::current()->update(['due_grouping' => BorderoAutoConfig::DUE_SAME_DAY]);

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

        $preview = app(BorderoAutoGroupService::class)->preview($this->manager());

        $this->assertCount(2, $preview['groups']);
    }

    public function test_eligibility_due_within_days_filtra_titulos(): void
    {
        BorderoAutoConfig::current()->update([
            'eligibility_mode' => BorderoAutoConfig::ELIGIBILITY_DUE_WITHIN,
            'eligibility_due_days' => 7,
        ]);

        Payable::create([
            'title_number' => 'NEAR',
            'supplier_name' => 'F',
            'amount' => 10,
            'due_date' => now()->addDays(3),
            'status' => 'pendente',
            'codemp' => 2,
            'codccu' => '1111',
            'senior_id' => '2-1-NEAR-01',
        ]);
        Payable::create([
            'title_number' => 'FAR',
            'supplier_name' => 'F',
            'amount' => 10,
            'due_date' => now()->addDays(60),
            'status' => 'pendente',
            'codemp' => 2,
            'codccu' => '1111',
            'senior_id' => '2-1-FAR-01',
        ]);

        $preview = app(BorderoAutoGroupService::class)->preview($this->manager());

        $this->assertSame(1, $preview['summary']['eligible_titles']);
    }

    public function test_comando_cron_gera_borderos(): void
    {
        foreach (['X1', 'X2'] as $num) {
            $this->makePayable([
                'title_number' => $num,
                'amount' => 150,
            ]);
        }

        $this->artisan('borderos:auto-generate --scheduled')->assertSuccessful();

        $this->assertSame(1, Bordero::count());
        $config = BorderoAutoConfig::current();
        $this->assertNotNull($config->last_cron_run_at);
        $this->assertSame(1, $config->last_cron_created_count);
    }

    public function test_comando_cron_respeita_desligado(): void
    {
        BorderoAutoConfig::current()->update(['cron_enabled' => false]);

        foreach (['X1', 'X2'] as $num) {
            $this->makePayable([
                'title_number' => $num,
                'amount' => 150,
            ]);
        }

        $this->artisan('borderos:auto-generate --scheduled')->assertSuccessful();

        $this->assertSame(0, Bordero::count());
    }

    public function test_gerar_manual_cria_borderos_em_rascunho(): void
    {
        foreach (['X1', 'X2'] as $num) {
            $this->makePayable([
                'title_number' => $num,
                'amount' => 150,
            ]);
        }

        $preview = app(BorderoAutoGroupService::class)->preview($this->manager());
        $key = $preview['groups'][0]['key'];

        $this->actingAs($this->manager())
            ->post('/financeiro/borderos/automatico/gerar', [
                'group_keys' => [$key],
            ])
            ->assertRedirect('/financeiro/borderos?status=rascunho');

        $this->assertSame(1, Bordero::count());
        $this->assertSame('rascunho', Bordero::first()->status);
    }
}
