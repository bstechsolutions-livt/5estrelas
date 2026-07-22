<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankStatementImport;
use App\Models\PayableRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\BankAccountImportService;
use App\Services\BankAccountMatcher;
use App\Services\Senior\SeniorTesContasClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $keys): User
    {
        $user = User::factory()->create(['is_active' => true]);
        foreach ($keys as $key) {
            $user->permissions()->attach(
                Permission::firstOrCreate(
                    ['key' => $key],
                    ['label' => $key, 'module' => 'financeiro'],
                )->id,
            );
        }

        return $user;
    }

    public function test_index_requires_visualizar_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/financeiro/bancos')
            ->assertForbidden();
    }

    public function test_index_lists_accounts_for_viewer(): void
    {
        $user = $this->userWith(['financeiro.bancos.visualizar']);
        BankAccount::create([
            'name' => 'MATRIZ BRB 050',
            'is_active' => true,
            'senior_codemp' => 2,
            'senior_num_cco' => '103',
            'senior_descricao' => 'MATRIZ BRB 050',
        ]);

        $this->actingAs($user)
            ->get('/financeiro/bancos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Banks/Index', false)
                ->has('accounts.data', 1)
                ->where('accounts.data.0.name', 'MATRIZ BRB 050')
                ->where('canManage', false));
    }

    public function test_store_requires_gerenciar_permission(): void
    {
        $user = $this->userWith(['financeiro.bancos.visualizar']);

        $this->actingAs($user)
            ->post('/financeiro/bancos', ['name' => 'Conta X'])
            ->assertForbidden();
    }

    public function test_manager_can_create_and_update_account(): void
    {
        $user = $this->userWith([
            'financeiro.bancos.visualizar',
            'financeiro.bancos.gerenciar',
        ]);

        $this->actingAs($user)
            ->post('/financeiro/bancos', [
                'name' => 'BRB Matriz',
                'bank_code' => '070',
                'bank_name' => 'Banco de Brasília',
                'agency' => '0001',
                'account_number' => '0460001329',
                'account_digit' => '0',
                'opening_balance' => 15230.45,
                'opening_balance_date' => '2026-07-17',
            ])
            ->assertRedirect();

        $account = BankAccount::first();
        $this->assertNotNull($account);
        $this->assertSame('BRB Matriz', $account->name);
        $this->assertSame('070', $account->bank_code);
        $this->assertSame('15230.45', $account->opening_balance);
        $this->assertSame('2026-07-17', $account->opening_balance_date->toDateString());

        $this->actingAs($user)
            ->put("/financeiro/bancos/{$account->id}", [
                'name' => 'BRB Matriz Editada',
                'bank_code' => '070',
                'bank_name' => 'Banco de Brasília',
                'agency' => '0001',
                'account_number' => '0460001329',
                'account_digit' => '0',
                'opening_balance' => -125.50,
                'opening_balance_date' => '2026-07-18',
            ])
            ->assertRedirect('/financeiro/bancos');

        $this->assertSame('BRB Matriz Editada', $account->fresh()->name);
        $this->assertSame('-125.50', $account->fresh()->opening_balance);
    }

    public function test_import_from_senior_is_idempotent_and_keeps_local_edits(): void
    {
        $client = Mockery::mock(SeniorTesContasClient::class);
        $client->shouldReceive('obterTodasContas')->twice()->andReturn([
            [
                'codigoEmpresa' => 2,
                'nomeEmpresa' => '5 ESTRELAS',
                'codigoFilial' => 0,
                'nomeFilial' => null,
                'numeroConta' => '103',
                'descricaoConta' => 'MATRIZ BRB 050',
                'saldo' => 10.0,
                'siglaMoeda' => 'R$',
            ],
            [
                'codigoEmpresa' => 2,
                'nomeEmpresa' => '5 ESTRELAS',
                'codigoFilial' => 0,
                'nomeFilial' => null,
                'numeroConta' => '112',
                'descricaoConta' => 'MATRIZ 060',
                'saldo' => 20.0,
                'siglaMoeda' => 'R$',
            ],
        ]);

        $importer = new BankAccountImportService($client);

        $first = $importer->importFromSenior();
        $this->assertSame(32, $first['created']);
        $this->assertDatabaseCount('bank_accounts', 32);

        $brb = BankAccount::where('senior_num_cco', '103')->firstOrFail();
        $brb->update([
            'name' => 'BRB Matriz (Hub)',
            'bank_code' => '070',
            'account_number' => '0460001329',
        ]);

        $second = $importer->importFromSenior();
        $this->assertSame(0, $second['created']);
        $this->assertSame(32, $second['updated']);

        $this->assertDatabaseCount('bank_accounts', 32);
        $brb->refresh();
        $this->assertSame('BRB Matriz (Hub)', $brb->name);
        $this->assertSame('070', $brb->bank_code);
        $this->assertSame('0460001329', $brb->account_number);
        $this->assertSame('MATRIZ BRB 050', $brb->senior_descricao);
    }

    public function test_import_uses_only_the_official_bank_account_catalog(): void
    {
        $client = Mockery::mock(SeniorTesContasClient::class);
        $client->shouldReceive('obterTodasContas')->once()->andReturn([
            ['codigoEmpresa' => 2, 'codigoFilial' => 0, 'numeroConta' => '103', 'descricaoConta' => 'MATRIZ BRB 050'],
            ['codigoEmpresa' => 2, 'codigoFilial' => 0, 'numeroConta' => '07', 'descricaoConta' => 'ALELO BRENO'],
            ['codigoEmpresa' => 2, 'codigoFilial' => 0, 'numeroConta' => '41', 'descricaoConta' => 'CARTÃO ALELO ANA PAULA'],
            ['codigoEmpresa' => 2, 'codigoFilial' => 0, 'numeroConta' => '48', 'descricaoConta' => 'CAIXA MATRIZ'],
            ['codigoEmpresa' => 8, 'codigoFilial' => 0, 'numeroConta' => '50', 'descricaoConta' => 'SS CAIXA'],
            ['codigoEmpresa' => 8, 'codigoFilial' => 0, 'numeroConta' => '09', 'descricaoConta' => 'PERDAS E LUCROS'],
            ['codigoEmpresa' => 8, 'codigoFilial' => 0, 'numeroConta' => '01', 'descricaoConta' => 'PRESTACAO DE CONTAS'],
            ['codigoEmpresa' => 2, 'codigoFilial' => 0, 'numeroConta' => '62', 'descricaoConta' => 'ENCERRADA CTA VINC ANATEL MT'],
            // Caixa Econômica é banco de verdade — não pode cair no filtro de caixa interno
            ['codigoEmpresa' => 12, 'codigoFilial' => 0, 'numeroConta' => '02', 'descricaoConta' => 'L&R CAIXA ECONOMICA'],
            ['codigoEmpresa' => 2, 'codigoFilial' => 0, 'numeroConta' => '68', 'descricaoConta' => 'MATRIZ CEF'],
        ]);

        $result = (new BankAccountImportService($client))->importFromSenior();

        $this->assertSame(32, $result['created']);
        $this->assertDatabaseCount('bank_accounts', 32);
        $this->assertDatabaseMissing('bank_accounts', ['name' => 'ALELO BRENO']);
        $this->assertDatabaseMissing('bank_accounts', ['name' => 'CAIXA MATRIZ']);
        $this->assertDatabaseHas('bank_accounts', [
            'name' => 'MATRIZ — BRB',
            'agency' => '050',
            'account_number' => '039912',
            'account_digit' => '3',
        ]);
        $this->assertDatabaseHas('bank_accounts', [
            'name' => 'LRB — CEF',
            'agency' => '4316',
            'account_number' => '577498751',
            'account_digit' => '1',
        ]);
    }

    public function test_matcher_suggests_account_by_bank_and_number(): void
    {
        BankAccount::create([
            'name' => 'BRB',
            'is_active' => true,
            'bank_code' => '070',
            'account_number' => '0460001329',
        ]);

        $match = app(BankAccountMatcher::class)->suggest('070', '0460001329');
        $this->assertNotNull($match);
        $this->assertSame('BRB', $match->name);
    }

    public function test_matcher_recognizes_ofx_account_with_agency_prefix(): void
    {
        $account = BankAccount::create([
            'name' => 'MATRIZ — BRB',
            'is_active' => true,
            'bank_code' => '070',
            'agency' => '046',
            'account_number' => '000134',
            'account_digit' => '5',
        ]);

        $match = app(BankAccountMatcher::class)->suggest('070', '0460001345');

        $this->assertNotNull($match);
        $this->assertSame($account->id, $match->id);
    }

    public function test_index_shows_latest_ofx_balance_instead_of_opening_balance(): void
    {
        $user = $this->userWith(['financeiro.bancos.visualizar']);
        $account = BankAccount::create([
            'name' => 'BRB Matriz',
            'is_active' => true,
            'opening_balance' => 1000,
            'opening_balance_date' => '2026-06-01',
        ]);

        BankStatementImport::create([
            'user_id' => $user->id,
            'bank_account_id' => $account->id,
            'account_number' => '0399123',
            'file_name' => 'junho.ofx',
            'file_path' => 'bank-statements/junho.ofx',
            'period_end' => '2026-06-30',
            'balance' => 2450.75,
            'status' => 'done',
        ]);

        $this->actingAs($user)
            ->get('/financeiro/bancos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('accounts.data.0.current_balance', 2450.75)
                ->where('accounts.data.0.balance_date', '2026-06-30')
                ->where('accounts.data.0.balance_source', 'ofx'));
    }

    public function test_ofx_upload_links_suggested_bank_account(): void
    {
        $user = $this->userWith([
            'financeiro.contas_pagar.visualizar',
            'financeiro.conciliacao.visualizar',
        ]);
        PayableRole::create(['role' => 'conciliador', 'user_id' => $user->id]);

        $account = BankAccount::create([
            'name' => 'BRB Matriz',
            'is_active' => true,
            'bank_code' => '070',
            'account_number' => '0460001329',
        ]);

        $path = base_path('tests/fixtures/ofx/brb.ofx');
        $file = new UploadedFile($path, 'brb.ofx', 'application/octet-stream', null, true);

        $this->actingAs($user)
            ->post(route('bank-conciliation.upload'), [
                'file' => $file,
                'bank_account_id' => $account->id,
            ])
            ->assertRedirect();

        $import = BankStatementImport::first();
        $this->assertNotNull($import);
        $this->assertSame($account->id, $import->bank_account_id);
    }
}
