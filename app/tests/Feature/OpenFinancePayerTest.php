<?php

namespace Tests\Feature;

use App\Models\Comercial\Filial;
use App\Models\OpenFinancePayer;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OpenFinancePayerTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_SH = 'token-secreto-feature-nao-expor';

    private const PAYER_TOKEN = 'token-de-pagador-nao-deve-vazar';

    private const PAYER_CNPJ = '19593175000188';

    public function test_verify_exige_login(): void
    {
        $this->post('/open-finance/payers/verify', ['cpf_cnpj' => self::PAYER_CNPJ])
            ->assertRedirect('/login');
    }

    public function test_ensure_exige_permissao_gerenciar(): void
    {
        $this->seedFilial();
        $this->configureIntegration();
        Http::fake();

        $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->post('/open-finance/payers/ensure', ['cpf_cnpj' => self::PAYER_CNPJ])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_verify_consulta_sem_criar_e_nao_expoe_token(): void
    {
        $this->seedFilial();
        $this->configureIntegration();
        Http::fake(function ($request) {
            if ($request->method() === 'POST' || $request->method() === 'PUT') {
                $this->fail('Verify não deve criar/ativar');
            }

            if (str_ends_with($request->url(), '/payer/list')) {
                return Http::response(['payers' => []], 200);
            }

            return Http::response(['code' => 422], 422);
        });

        $response = $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->from('/open-finance')
            ->post('/open-finance/payers/verify', ['cpf_cnpj' => '19.593.175/0001-88']);

        $response->assertRedirect('/open-finance');
        $this->assertDatabaseHas('open_finance_payers', [
            'cpf_cnpj' => self::PAYER_CNPJ,
            'remote_exists' => false,
            'last_status' => 'not_found',
        ]);
        $this->assertStringNotContainsString(self::PAYER_TOKEN, $response->headers->get('Location') ?? '');
    }

    public function test_ensure_cadastra_pagador_inexistente(): void
    {
        $this->seedFilial();
        $this->configureIntegration();
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response([
                    'name' => '5 ESTRELAS SEGURANCA LTDA',
                    'cpfCnpj' => self::PAYER_CNPJ,
                    'statementActived' => true,
                    'token' => self::PAYER_TOKEN,
                ], 201);
            }
            if (str_ends_with($request->url(), '/payer/list')) {
                return Http::response(['payers' => []], 200);
            }

            return Http::response(['code' => 422], 422);
        });

        $this->actingAs($this->userWith(['open_finance.visualizar', 'open_finance.gerenciar']))
            ->from('/open-finance')
            ->post('/open-finance/payers/ensure', ['cpf_cnpj' => self::PAYER_CNPJ])
            ->assertRedirect('/open-finance');

        $this->assertDatabaseHas('open_finance_payers', [
            'cpf_cnpj' => self::PAYER_CNPJ,
            'statement_activated' => true,
            'last_status' => 'created',
        ]);
        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'POST'));
    }

    public function test_ensure_ip_blocked_nao_finge_sucesso(): void
    {
        $this->seedFilial();
        $this->configureIntegration();
        Http::fake(['*' => Http::response('<html><title>403 Forbidden</title></html>', 403)]);

        $this->actingAs($this->userWith(['open_finance.visualizar', 'open_finance.gerenciar']))
            ->from('/open-finance')
            ->post('/open-finance/payers/ensure', ['cpf_cnpj' => self::PAYER_CNPJ])
            ->assertRedirect('/open-finance')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('open_finance_payers', [
            'cpf_cnpj' => self::PAYER_CNPJ,
            'last_status' => 'ip_blocked',
        ]);
        $this->assertNull(OpenFinancePayer::query()->value('remote_exists'));
    }

    public function test_index_apos_verify_nao_expoe_token_do_pagador(): void
    {
        $this->seedFilial();
        $this->configureIntegration();
        OpenFinancePayer::create([
            'cpf_cnpj' => self::PAYER_CNPJ,
            'name' => '5 ESTRELAS SEGURANCA LTDA',
            'provider' => 'tecnospeed',
            'remote_exists' => true,
            'statement_activated' => true,
            'last_status' => 'unchanged',
            'last_checked_at' => now(),
        ]);

        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/payer/list' => Http::response([
                'payers' => [['token' => self::PAYER_TOKEN, 'cpfCnpj' => self::PAYER_CNPJ]],
            ], 200),
        ]);

        $response = $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('payers.0.cpf_cnpj_masked', '19.***.***/0001-**')
                ->where('payers.0.statement_activated', true)
                ->where('payers.0.tecnospeed_state', 'registered')
                ->missing('payers.0.token'));

        $this->assertStringNotContainsString(self::PAYER_TOKEN, $response->getContent());
        $this->assertStringNotContainsString(self::TOKEN_SH, $response->getContent());
    }

    /**
     * @param  list<string>  $keys
     */
    private function userWith(array $keys): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(
                    ['key' => $key],
                    ['label' => $key, 'module' => str_starts_with($key, 'open_finance') ? 'open_finance' : 'financeiro'],
                )->id
            );
        }

        return $user;
    }

    private function configureIntegration(): void
    {
        config()->set('open_finance.enabled', true);
        config()->set('open_finance.environment', 'staging');
        config()->set('open_finance.credentials.cnpj_sh', '01.001.001/0001-13');
        config()->set('open_finance.credentials.token_sh', self::TOKEN_SH);
        config()->set('open_finance.endpoints.staging', 'https://staging.pagamentobancario.com.br/api/v1');
    }

    private function seedFilial(): void
    {
        Filial::create([
            'senior_id' => '2-1',
            'cod_emp' => 2,
            'cod_fil' => 1,
            'nome' => '5 ESTRELAS SEGURANCA LTDA',
            'apelido' => '5 Estrelas',
            'cnpj' => '19.593.175/0001-88',
            'uf' => 'DF',
            'ativo' => true,
            'senior_raw' => [
                'baiFil' => 'ASA SUL',
                'cidFil' => 'BRASILIA',
                'sigUfs' => 'DF',
                'cepFil' => '70330000',
                'eenFil' => '10',
            ],
        ]);
    }
}
