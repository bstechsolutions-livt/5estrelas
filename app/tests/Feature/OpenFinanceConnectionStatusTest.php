<?php

namespace Tests\Feature;

use App\Models\OpenFinanceAccountConnection;
use App\Support\FinanceiroConfigCatalog;
use App\Support\MenuCatalog;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithOpenFinance;
use Tests\TestCase;

class OpenFinanceConnectionStatusTest extends TestCase
{
    use InteractsWithOpenFinance;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_seeder_cria_permissoes_open_finance(): void
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
                ->where('status.environment_label', 'Homologação')
                ->where('p2_blocked', true)
                ->has('accounts')
                ->where('can_manage', false)
                ->missing('status.token')
                ->missing('status.credentials'));

        Http::assertNothingSent();
    }

    public function test_abrir_tela_nao_cadastra_pagador_nem_conta(): void
    {
        $this->configureIntegration();
        $this->makeBankAccount();

        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/payer/list' => Http::response([
                'payers' => [['token' => self::TOKEN_SH, 'cpfCnpj' => self::PAYER_CNPJ]],
            ], 200),
        ]);

        $this->actingAs($this->userWith(['open_finance.visualizar', 'open_finance.gerenciar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('accounts', 1)
                ->where('accounts.0.name', 'MATRIZ BRB 050')
                ->where('accounts.0.status', 'not_configured')
                ->where('accounts.0.account_masked', fn (string $masked) => str_contains($masked, '*'))
                ->where('can_manage', true)
                ->where('p2_blocked', false));

        Http::assertSentCount(1);
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'POST'));
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'PUT'));
        $this->assertDatabaseCount('open_finance_account_connections', 0);
    }

    public function test_tela_mostra_conexao_ok_sem_expor_segredos(): void
    {
        $this->configureIntegration();
        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/payer/list' => Http::response([
                'payers' => [['token' => self::TOKEN_SH, 'openfinanceLink' => self::OPENFINANCE_LINK]],
            ], 200),
        ]);

        $response = $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('status.connected', true)
                ->where('status.code', 'ok'));

        $payload = $response->getContent();
        $this->assertStringNotContainsString(self::TOKEN_SH, $payload);
        $this->assertStringNotContainsString(self::OPENFINANCE_LINK, $payload);
        $this->assertStringNotContainsString(self::PAYER_TOKEN, $payload);
    }

    public function test_tela_mostra_erro_de_autenticacao(): void
    {
        $this->configureIntegration();
        Http::fake(['*' => Http::response(['code' => 401, 'message' => 'CNPJ ou TOKEN '.self::TOKEN_SH], 401)]);

        $response = $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('status.code', 'auth')
                ->where('status.connected', false)
                ->where('p2_blocked', true));

        $this->assertStringNotContainsString(self::TOKEN_SH, $response->getContent());
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
                ->where('p2_blocked', true));
    }

    public function test_tela_mostra_bloqueio_de_ip(): void
    {
        $this->configureIntegration();
        Http::fake(['*' => Http::response('<html><title>403 Forbidden</title></html>', 403)]);

        $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('status.code', 'ip_blocked')
                ->where('status.show_ip_release', true)
                ->where('p2_blocked', true));
    }

    public function test_tela_de_timeout(): void
    {
        $this->configureIntegration();
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('status.code', 'timeout'));
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
}
