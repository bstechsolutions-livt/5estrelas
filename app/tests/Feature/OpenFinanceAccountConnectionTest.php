<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\OpenFinanceAccountConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithOpenFinance;
use Tests\TestCase;

class OpenFinanceAccountConnectionTest extends TestCase
{
    use InteractsWithOpenFinance;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_usuario_sem_gerenciar_nao_inicia_configuracao(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        Http::fake();

        $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->post('/open-finance/connections', $this->prepareForm($account, $branch))
            ->assertForbidden();

        $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get("/open-finance/accounts/{$account->id}/preview")
            ->assertForbidden();

        $this->assertDatabaseCount('open_finance_account_connections', 0);
        $this->assertDatabaseCount('bank_accounts', 1);
    }

    public function test_usuario_com_visualizar_consulta_lista_sem_mutar(): void
    {
        $this->configureIntegration();
        $this->makeBankAccount();
        $state = [];
        $this->fakeTecnoSpeed($state);

        $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can_manage', false)
                ->has('accounts', 1)
                ->where('accounts.0.status_label', 'Não configurada'));

        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() !== 'GET'));
    }

    public function test_fluxo_configurar_autorizar_verificar(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        $state = [];
        $this->fakeTecnoSpeed($state);

        $manager = $this->userWith(['open_finance.visualizar', 'open_finance.gerenciar']);

        $this->actingAs($manager)
            ->post('/open-finance/connections', $this->prepareForm($account, $branch))
            ->assertRedirect();

        $connection = OpenFinanceAccountConnection::query()->first();
        $this->assertSame(OpenFinanceAccountConnection::STATUS_AWAITING_AUTHORIZATION, $connection->status);
        $this->assertDatabaseCount('bank_accounts', 1);

        $this->actingAs($manager)
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('accounts.0.status', 'aguardando_autorizacao')
                ->where('accounts.0.status_label', 'Aguardando autorização')
                ->where('accounts.0.has_authorization_link', true)
                ->missing('accounts.0.openfinance_link')
                ->missing('accounts.0.authorization_url'));

        $link = $this->actingAs($manager)
            ->get("/open-finance/connections/{$connection->id}/authorization-link")
            ->assertOk()
            ->assertJson(['url' => self::OPENFINANCE_LINK]);

        $this->assertStringNotContainsString(self::TOKEN_SH, $link->getContent());

        $state['openfinance_id'] = self::OPENFINANCE_ID;

        $this->actingAs($manager)
            ->post("/open-finance/connections/{$connection->id}/verify")
            ->assertRedirect();

        $connection->refresh();
        $this->assertSame(OpenFinanceAccountConnection::STATUS_CONNECTED, $connection->status);
        $this->assertSame(self::OPENFINANCE_ID, $connection->openfinance_id);

        $this->actingAs($manager)
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('accounts.0.status', 'conectada')
                ->where('accounts.0.status_label', 'Conectada'));
    }

    public function test_verificar_sem_conector_nao_e_erro_fatal(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        $state = [];
        $this->fakeTecnoSpeed($state);
        $manager = $this->userWith(['open_finance.visualizar', 'open_finance.gerenciar']);
        $this->actingAs($manager)->post('/open-finance/connections', $this->prepareForm($account, $branch));
        $connection = OpenFinanceAccountConnection::query()->first();

        $this->actingAs($manager)
            ->post("/open-finance/connections/{$connection->id}/verify")
            ->assertRedirect()
            ->assertSessionHas('warning', 'A autorização ainda não foi concluída ou ainda está sendo processada pela instituição.');

        $this->assertSame(
            OpenFinanceAccountConnection::STATUS_AWAITING_AUTHORIZATION,
            $connection->fresh()->status,
        );
    }

    public function test_viewer_nao_verifica_nem_pega_link(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        $state = [];
        $this->fakeTecnoSpeed($state);
        $this->actingAs($this->userWith(['open_finance.visualizar', 'open_finance.gerenciar']))
            ->post('/open-finance/connections', $this->prepareForm($account, $branch));
        $connection = OpenFinanceAccountConnection::query()->first();

        $viewer = $this->userWith(['open_finance.visualizar']);
        $this->actingAs($viewer)
            ->get("/open-finance/connections/{$connection->id}/authorization-link")
            ->assertForbidden();
        $this->actingAs($viewer)
            ->post("/open-finance/connections/{$connection->id}/verify")
            ->assertForbidden();
    }

    public function test_index_nao_expoe_json_tecnico_nem_hash_como_campo_principal(): void
    {
        $this->configureIntegration();
        $this->makeBankAccount();
        $state = [];
        $this->fakeTecnoSpeed($state);

        $this->actingAs($this->userWith(['open_finance.visualizar']))
            ->get('/open-finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('accounts.0.provider_account_hash')
                ->missing('accounts.0.raw')
                ->missing('status.payload'));
    }

    public function test_prepare_falho_nao_marca_pronto_e_nao_duplica_conta_local(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        $state = ['fail_create_payer' => 503];
        $this->fakeTecnoSpeed($state);

        $this->actingAs($this->userWith(['open_finance.visualizar', 'open_finance.gerenciar']))
            ->post('/open-finance/connections', $this->prepareForm($account, $branch))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bank_accounts', 1);
        $connection = OpenFinanceAccountConnection::query()->first();
        $this->assertSame(OpenFinanceAccountConnection::STATUS_ERROR, $connection->status);
        $this->assertNull($connection->openfinance_id);
    }

    public function test_auditoria_e_logs_nao_levam_link_nem_token(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();
        $state = [];
        $this->fakeTecnoSpeed($state);

        $this->actingAs($this->userWith(['open_finance.visualizar', 'open_finance.gerenciar']))
            ->post('/open-finance/connections', $this->prepareForm($account, $branch));

        $this->assertGreaterThan(0, AuditLog::query()->count());
        foreach (AuditLog::query()->get() as $log) {
            $blob = json_encode($log->toArray(), JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString(self::OPENFINANCE_LINK, $blob);
            $this->assertStringNotContainsString(self::TOKEN_SH, $blob);
            $this->assertStringNotContainsString(self::PAYER_TOKEN, $blob);
        }
    }

    public function test_preview_pre_seleciona_branch_inequivoca(): void
    {
        $this->configureIntegration();
        $branch = $this->makeBranch();
        $account = $this->makeBankAccount();

        $this->actingAs($this->userWith(['open_finance.gerenciar']))
            ->get("/open-finance/accounts/{$account->id}/preview")
            ->assertOk()
            ->assertJsonPath('suggested_branch_id', $branch->id)
            ->assertJsonPath('match', 'exact')
            ->assertJsonPath('bank_account.id', $account->id);
    }
}
