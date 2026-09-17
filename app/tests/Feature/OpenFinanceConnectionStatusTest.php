<?php

namespace Tests\Feature;

use App\Models\Comercial\Filial;
use App\Models\Permission;
use App\Models\User;
use App\Support\FinanceiroConfigCatalog;
use App\Support\MenuCatalog;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OpenFinanceConnectionStatusTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-secreto-feature-nao-expor';

    public function test_seeder_cria_permissao_open_finance(): void
    {
        $this->seed(PermissionsSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'key' => 'open_finance.visualizar',
            'module' => 'open_finance',
        ]);
        $this->assertDatabaseHas('permissions', [
            'key' => 'open_finance.gerenciar',
            'module' => 'open_finance',
        ]);
    }

    public function test_visitante_e_redirecionado(): void
    {
        $this->get('/open-finance')->assertRedirect('/login');
    }

    public function test_exige_permissao(): void
    {
        $user = $this->userWith(['financeiro.contas_pagar.visualizar']);

        $this->actingAs($user)
            ->get('/open-finance')
            ->assertForbidden();
    }

    public function test_tela_informa_quando_nao_configurada(): void
    {
        config()->set('open_finance.enabled', false);
        config()->set('open_finance.environment', 'staging');
        config()->set('open_finance.credentials.cnpj_sh', '');
        config()->set('open_finance.credentials.token_sh', '');

        Http::fake();
        Http::preventStrayRequests();

        $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('OpenFinance/Index', false)
                ->where('status.configured', false)
                ->where('status.connected', false)
                ->where('status.code', 'not_configured')
                ->where('status.environment', 'staging')
                ->where('status.environment_label', 'Homologação')
                ->where('status.message', fn (string $message) => str_contains($message, 'não está configurada'))
                ->has('payers')
                ->where('can_manage', false)
                ->missing('status.token')
                ->missing('status.credentials')
                ->missing('status.payers'));

        Http::assertNothingSent();
    }

    public function test_abrir_tela_nao_cadastra_pagador(): void
    {
        $this->configureIntegration();
        $this->seedFilial();

        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/payer/list' => Http::response([
                'payers' => [['token' => self::TOKEN, 'cpfCnpj' => '01001001000113']],
            ], 200),
        ]);

        $this->actingAs($this->userWith(['open_finance.visualizar', 'open_finance.gerenciar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('payers', 1)
                ->where('payers.0.cpf_cnpj_masked', '19.***.***/0001-**')
                ->where('payers.0.tecnospeed_state', 'unknown')
                ->where('can_manage', true));

        Http::assertSentCount(1);
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'POST'));
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'PUT'));
        $this->assertDatabaseCount('open_finance_payers', 0);
    }

    public function test_tela_mostra_conexao_ok_sem_expor_segredos(): void
    {
        $this->configureIntegration();

        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/payer/list' => Http::response([
                'payers' => [
                    ['token' => self::TOKEN, 'cpfCnpj' => '01001001000113'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('OpenFinance/Index', false)
                ->where('status.configured', true)
                ->where('status.connected', true)
                ->where('status.code', 'ok')
                ->where('status.message', 'Conexão OK. Autenticação e comunicação com a TecnoSpeed estão funcionando.'));

        $payload = $response->getContent();
        $this->assertStringNotContainsString(self::TOKEN, $payload);
        $this->assertStringNotContainsString('token-de-pagador', $payload);
    }

    public function test_tela_mostra_erro_de_autenticacao(): void
    {
        $this->configureIntegration();

        Http::fake(['*' => Http::response(['code' => 401, 'message' => 'CNPJ ou TOKEN incorretos '.self::TOKEN], 401)]);

        $response = $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('status.configured', true)
                ->where('status.connected', false)
                ->where('status.code', 'auth')
                ->where('status.message', fn (string $message) => str_contains($message, 'recusou a autenticação')));

        $encoded = $response->getContent();
        $this->assertStringNotContainsString(self::TOKEN, $encoded);
    }

    public function test_tela_mostra_indisponibilidade(): void
    {
        $this->configureIntegration();
        Http::fake(['*' => Http::response('error', 503)]);

        $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('status.code', 'unavailable')
                ->where('status.connected', false)
                ->where('status.show_ip_release', false)
                ->where('status.ip_release_url', null));
    }

    public function test_tela_mostra_bloqueio_de_ip_com_link_de_liberacao(): void
    {
        $this->configureIntegration();
        Http::fake(['*' => Http::response('<html><title>403 Forbidden</title></html>', 403)]);

        $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('status.code', 'ip_blocked')
                ->where('status.connected', false)
                ->where('status.show_ip_release', true)
                ->where('status.ip_release_url', config('open_finance.ip_release_form_url'))
                ->where(
                    'status.message',
                    'Não foi possível acessar a TecnoSpeed a partir deste ambiente. Verifique a liberação do IP junto ao provedor.'
                ));
    }

    public function test_tela_de_autenticacao_nao_mostra_liberacao_de_ip(): void
    {
        $this->configureIntegration();
        Http::fake(['*' => Http::response(['code' => 401, 'message' => 'CNPJ ou TOKEN incorretos'], 401)]);

        $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('status.code', 'auth')
                ->where('status.show_ip_release', false)
                ->where('status.ip_release_url', null));
    }

    public function test_tela_de_timeout_nao_mostra_liberacao_de_ip(): void
    {
        $this->configureIntegration();
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('status.code', 'timeout')
                ->where('status.show_ip_release', false)
                ->where('status.ip_release_url', null));
    }

    public function test_menu_e_catalogo_exigem_permissao(): void
    {
        $with = $this->userWith(['open_finance.visualizar']);
        $without = $this->userWith(['financeiro.contas_pagar.visualizar']);

        $this->assertContains('open_finance', collect(MenuCatalog::availableTo($with))->pluck('key'));
        $this->assertNotContains('open_finance', collect(MenuCatalog::availableTo($without))->pluck('key'));

        $this->assertContains('open_finance', collect(FinanceiroConfigCatalog::accessibleTo($with))->pluck('key'));
        $this->assertNotContains('open_finance', collect(FinanceiroConfigCatalog::accessibleTo($without))->pluck('key'));
    }

    /**
     * @param  list<string>  $keys
     */
    private function userWith(array $keys): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($keys as $key) {
            $module = str_starts_with($key, 'open_finance') ? 'open_finance' : 'financeiro';
            $user->permissions()->attach(
                Permission::firstOrCreate(['key' => $key], ['label' => $key, 'module' => $module])->id
            );
        }

        return $user;
    }

    private function configureIntegration(): void
    {
        config()->set('open_finance.enabled', true);
        config()->set('open_finance.environment', 'staging');
        config()->set('open_finance.credentials.cnpj_sh', '01.001.001/0001-13');
        config()->set('open_finance.credentials.token_sh', self::TOKEN);
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
