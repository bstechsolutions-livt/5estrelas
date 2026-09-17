<?php

namespace Tests\Unit\Services\OpenFinance;

use App\Models\OpenFinanceAccountConnection;
use App\Models\OpenFinancePayer;
use App\Services\OpenFinance\AccountConnectionService;
use App\Services\OpenFinance\BranchResolver;
use App\Services\OpenFinance\PayerProfileBuilder;
use App\Services\OpenFinance\PayerRegistrationData;
use App\Services\OpenFinance\TecnoSpeedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\InteractsWithOpenFinance;
use Tests\TestCase;

class AccountConnectionServiceTest extends TestCase
{
    use InteractsWithOpenFinance;
    use RefreshDatabase;

    private function service(): AccountConnectionService
    {
        return new AccountConnectionService($this->client(), new BranchResolver, new PayerProfileBuilder);
    }

    public function test_branch_exata_pelo_par_senior(): void
    {
        $this->makeBranch();
        $this->makeBranch(['name' => 'Outra', 'cnpj' => '00360305000104', 'cod_emp' => 3, 'cod_fil' => 1, 'code' => '3']);
        $account = $this->makeBankAccount();

        $resolution = (new BranchResolver)->resolve($account);

        $this->assertSame('exact', $resolution['match']);
        $this->assertNotNull($resolution['suggested_branch_id']);
    }

    public function test_branch_ambigua_exige_escolha(): void
    {
        $this->makeBranch(['cod_fil' => 1]);
        $this->makeBranch(['name' => 'Filial 2', 'cnpj' => '00360305000104', 'cod_emp' => 2, 'cod_fil' => 2, 'code' => '22']);
        $account = $this->makeBankAccount(['senior_codfil' => 0]);

        $resolution = (new BranchResolver)->resolve($account);

        $this->assertSame('ambiguous', $resolution['match']);
        $this->assertNull($resolution['suggested_branch_id']);
    }

    public function test_prepare_cria_pagador_e_conta_sem_duplicar_bank_account(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        $state = [];
        $this->fakeTecnoSpeed($state);

        $connection = $this->service()->prepare($account, $branch, $this->prepareForm($account, $branch), 'staging');

        $this->assertSame(OpenFinanceAccountConnection::STATUS_AWAITING_AUTHORIZATION, $connection->status);
        $this->assertSame(self::ACCOUNT_HASH, $connection->provider_account_hash);
        $this->assertSame(self::OPENFINANCE_LINK, $connection->openfinance_link);
        $this->assertNull($connection->openfinance_id);
        $this->assertDatabaseCount('bank_accounts', 1);
        $this->assertDatabaseCount('open_finance_payers', 1);
        $this->assertDatabaseCount('open_finance_account_connections', 1);

        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/payer')));
        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/account')));
        $this->assertTrue(collect(Http::recorded())->every(
            fn ($pair) => ! str_contains($pair[0]->url(), '://api.pagamentobancario.com.br')
        ));
    }

    public function test_pagador_existente_nao_duplica_e_ativa_statement(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        $state = ['payer_exists' => true, 'payer_statement' => false];
        $this->fakeTecnoSpeed($state);

        $this->service()->prepare($account, $branch, $this->prepareForm($account, $branch), 'staging');

        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/payer')));
        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'PUT' && str_ends_with($request->url(), '/payer')));
        $this->assertTrue(OpenFinancePayer::query()->first()->statement_activated);
    }

    public function test_reexecucao_reutiliza_conta_remota_e_vinculo_local(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        $state = [];
        $this->fakeTecnoSpeed($state);
        $service = $this->service();
        $service->prepare($account, $branch, $this->prepareForm($account, $branch), 'staging');

        $service->prepare($account, $branch, $this->prepareForm($account, $branch), 'staging');

        $this->assertDatabaseCount('open_finance_account_connections', 1);
        $this->assertDatabaseCount('open_finance_payers', 1);
        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/account')));
        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/payer')));
    }

    public function test_falha_da_api_nao_marca_como_pronto(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        $state = ['fail_create_payer' => 503];
        $this->fakeTecnoSpeed($state);

        try {
            $this->service()->prepare($account, $branch, $this->prepareForm($account, $branch), 'staging');
            $this->fail();
        } catch (TecnoSpeedException $e) {
            $this->assertSame(TecnoSpeedException::KIND_UNAVAILABLE, $e->kind);
        }

        $connection = OpenFinanceAccountConnection::query()->first();
        $this->assertSame(OpenFinanceAccountConnection::STATUS_ERROR, $connection->status);
        $this->assertNull($connection->provider_account_hash);
        $this->assertNull($connection->openfinance_id);
    }

    public function test_campo_obrigatorio_bloqueia_antes_da_api(): void
    {
        $this->configureIntegration();
        Http::fake();
        Http::preventStrayRequests();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();

        try {
            $this->service()->prepare($account, $branch, $this->prepareForm($account, $branch, [
                'neighborhood' => '',
            ]), 'staging');
            $this->fail();
        } catch (TecnoSpeedException $e) {
            $this->assertSame(TecnoSpeedException::KIND_VALIDATION, $e->kind);
        }

        Http::assertNothingSent();
    }

    public function test_verify_sem_conector_mantem_aguardando(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        $state = [];
        $this->fakeTecnoSpeed($state);
        $service = $this->service();
        $connection = $service->prepare($account, $branch, $this->prepareForm($account, $branch), 'staging');

        $updated = $service->verify($connection->fresh());
        $this->assertSame(OpenFinanceAccountConnection::STATUS_AWAITING_AUTHORIZATION, $updated->status);
        $this->assertNull($updated->openfinance_id);
    }

    public function test_verify_com_conector_marca_conectada_e_e_idempotente(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        $state = [];
        $this->fakeTecnoSpeed($state);
        $service = $this->service();
        $connection = $service->prepare($account, $branch, $this->prepareForm($account, $branch), 'staging');

        $state['openfinance_id'] = self::OPENFINANCE_ID;
        $first = $service->verify($connection->fresh());
        $this->assertSame(OpenFinanceAccountConnection::STATUS_CONNECTED, $first->status);
        $this->assertSame(self::OPENFINANCE_ID, $first->openfinance_id);
        $this->assertNotNull($first->authorized_at);

        $second = $service->verify($first->fresh());
        $this->assertSame(OpenFinanceAccountConnection::STATUS_CONNECTED, $second->status);
        $this->assertTrue($first->authorized_at->equalTo($second->authorized_at));
    }

    public function test_staging_e_production_nao_compartilham_vinculo(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        $state = [];
        $this->fakeTecnoSpeed($state);
        $this->service()->prepare($account, $branch, $this->prepareForm($account, $branch), 'staging');

        $rows = $this->service()->listForFrontend('production');
        $this->assertSame(OpenFinanceAccountConnection::STATUS_NOT_CONFIGURED, $rows[0]['status']);
        $this->assertNull($rows[0]['connection_id']);
    }

    public function test_p1_nao_ok_bloqueia_p2(): void
    {
        $this->configureIntegration();
        $state = ['fail_handshake' => 403];
        $this->fakeTecnoSpeed($state);
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();

        try {
            $this->service()->prepare($account, $branch, $this->prepareForm($account, $branch), 'staging');
            $this->fail();
        } catch (TecnoSpeedException $e) {
            $this->assertSame(TecnoSpeedException::KIND_IP_BLOCKED, $e->kind);
        }

        $this->assertDatabaseCount('open_finance_account_connections', 0);
    }

    public function test_logs_nao_incluem_link_nem_token(): void
    {
        Log::spy();
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        $state = [];
        $this->fakeTecnoSpeed($state);
        $this->service()->prepare($account, $branch, $this->prepareForm($account, $branch), 'staging');

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) {
            $encoded = json_encode([$message, $context], JSON_THROW_ON_ERROR);

            return ! str_contains($encoded, self::TOKEN_SH)
                && ! str_contains($encoded, self::OPENFINANCE_LINK)
                && ! str_contains($encoded, self::PAYER_TOKEN);
        });
    }

    public function test_registration_data_completa(): void
    {
        $data = new PayerRegistrationData(
            name: 'Empresa',
            cpfCnpj: self::PAYER_CNPJ,
            neighborhood: 'Centro',
            city: 'Brasilia',
            state: 'DF',
            zipcode: '70000000',
        );
        $this->assertTrue($data->isComplete());
        $this->assertTrue($data->toCreatePayload()['statementActived']);
    }
}
