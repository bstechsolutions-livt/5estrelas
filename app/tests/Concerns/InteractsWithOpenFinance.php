<?php

namespace Tests\Concerns;

use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\User;
use App\Services\OpenFinance\TecnoSpeedClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

trait InteractsWithOpenFinance
{
    private const TOKEN_SH = 'token-secreto-de-teste-nao-logar';

    private const CNPJ_SH = '01001001000113';

    private const PAYER_CNPJ = '19593175000188';

    private const PAYER_TOKEN = 'token-de-pagador-nao-deve-vazar';

    private const ACCOUNT_HASH = 'hashContaOfTeste';

    private const OPENFINANCE_LINK = 'https://staging.pagamentobancario.com.br/gui/link-sensivel-nao-logar';

    private const OPENFINANCE_ID = 'ab4dafd0-0b00-4a5a-8dd0-13b8ae90a37e';

    /**
     * @param  list<string>  $keys
     */
    protected function userWith(array $keys): User
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function configureIntegration(array $overrides = []): void
    {
        config()->set('open_finance.enabled', $overrides['enabled'] ?? true);
        config()->set('open_finance.environment', $overrides['environment'] ?? 'staging');
        config()->set('open_finance.credentials.cnpj_sh', $overrides['cnpj_sh'] ?? '01.001.001/0001-13');
        config()->set('open_finance.credentials.token_sh', $overrides['token_sh'] ?? self::TOKEN_SH);
        config()->set('open_finance.endpoints.staging', 'https://staging.pagamentobancario.com.br/api/v1');
        config()->set('open_finance.endpoints.production', 'https://api.pagamentobancario.com.br/api/v1');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function clientConfig(array $overrides = []): array
    {
        return array_replace_recursive([
            'enabled' => true,
            'environment' => 'staging',
            'endpoints' => [
                'staging' => 'https://staging.pagamentobancario.com.br/api/v1',
                'production' => 'https://api.pagamentobancario.com.br/api/v1',
            ],
            'credentials' => [
                'cnpj_sh' => self::CNPJ_SH,
                'token_sh' => self::TOKEN_SH,
            ],
            'timeout' => [
                'connect' => 2,
                'response' => 2,
            ],
        ], $overrides);
    }

    protected function client(array $overrides = []): TecnoSpeedClient
    {
        return new TecnoSpeedClient($this->clientConfig($overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeBranch(array $overrides = []): Branch
    {
        return Branch::create(array_merge([
            'name' => '5 ESTRELAS SEGURANCA LTDA',
            'cnpj' => '19.593.175/0001-88',
            'code' => '2',
            'cod_emp' => 2,
            'cod_fil' => 1,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeBankAccount(array $overrides = []): BankAccount
    {
        return BankAccount::create(array_merge([
            'name' => 'MATRIZ BRB 050',
            'is_active' => true,
            'senior_codemp' => 2,
            'senior_codfil' => 1,
            'senior_num_cco' => '103',
            'bank_code' => '070',
            'bank_name' => 'BRB',
            'agency' => '0501',
            'account_number' => '00123456',
            'account_digit' => '7',
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    protected function payerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => '5 ESTRELAS SEGURANCA LTDA',
            'cpfCnpj' => self::PAYER_CNPJ,
            'neighborhood' => 'ASA SUL',
            'city' => 'BRASILIA',
            'state' => 'DF',
            'zipcode' => '70330000',
            'statementActived' => true,
            'token' => self::PAYER_TOKEN,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function accountPayload(array $overrides = []): array
    {
        return array_merge([
            'bankCode' => '070',
            'accountHash' => self::ACCOUNT_HASH,
            'agency' => '0501',
            'accountNumber' => '00123456',
            'accountNumberDigit' => '7',
            'statementActived' => true,
            'openfinanceLink' => self::OPENFINANCE_LINK,
        ], $overrides);
    }

    /**
     * Um único Http::fake por teste (fakes subsequentes são mesclados).
     * Mude $state para evoluir o provedor entre prepare e verify.
     *
     * @param  array<string, mixed>  $state
     */
    protected function fakeTecnoSpeed(array &$state): void
    {
        $state = array_merge([
            'payer_exists' => false,
            'payer_statement' => false,
            'account_exists' => false,
            'account_statement' => false,
            'openfinance_id' => null,
            'fail_create_payer' => null,
            'fail_handshake' => null,
        ], $state);

        Http::fake(function ($request) use (&$state) {
            $url = $request->url();
            $method = $request->method();

            if ($state['fail_handshake']) {
                return is_int($state['fail_handshake'])
                    ? Http::response($state['fail_handshake'] === 403 ? '<html>403</html>' : 'down', $state['fail_handshake'])
                    : throw $state['fail_handshake'];
            }

            if ($method === 'GET' && str_ends_with($url, '/payer/list')) {
                $payers = $state['payer_exists']
                    ? [$this->payerPayload(['statementActived' => $state['payer_statement']])]
                    : [];

                return Http::response(['payers' => $payers], 200);
            }

            if ($method === 'GET' && str_ends_with($url, '/payer')) {
                if (! $state['payer_exists']) {
                    return Http::response(['code' => 422], 422);
                }

                return Http::response($this->payerPayload([
                    'statementActived' => $state['payer_statement'],
                ]), 200);
            }

            if ($method === 'POST' && str_ends_with($url, '/payer')) {
                if ($state['fail_create_payer']) {
                    return Http::response('down', $state['fail_create_payer']);
                }
                $state['payer_exists'] = true;
                $state['payer_statement'] = true;

                return Http::response($this->payerPayload(), 201);
            }

            if ($method === 'PUT' && str_ends_with($url, '/payer')) {
                $state['payer_statement'] = true;

                return Http::response($this->payerPayload(), 200);
            }

            if ($method === 'GET' && str_contains($url, '/account/'.self::ACCOUNT_HASH)) {
                return Http::response([
                    'accounts' => $this->accountPayload([
                        'statementActived' => $state['account_statement'] || $state['account_exists'],
                        'openfinanceId' => $state['openfinance_id'],
                    ]),
                ], 200);
            }

            if ($method === 'GET' && str_ends_with($url, '/account')) {
                $accounts = $state['account_exists']
                    ? [$this->accountPayload(['statementActived' => $state['account_statement']])]
                    : [];

                return Http::response(['accounts' => $accounts], 200);
            }

            if ($method === 'POST' && str_ends_with($url, '/account')) {
                $state['account_exists'] = true;
                $state['account_statement'] = true;

                return Http::response(['accounts' => [$this->accountPayload()]], 201);
            }

            if ($method === 'PUT' && str_contains($url, '/account/')) {
                $state['account_statement'] = true;

                return Http::response($this->accountPayload(['statementActived' => true]), 200);
            }

            return Http::response('nope '.$method.' '.$url, 500);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function prepareForm(BankAccount $account, Branch $branch, array $overrides = []): array
    {
        return array_merge([
            'bank_account_id' => $account->id,
            'branch_id' => $branch->id,
            'name' => $branch->name,
            'cpf_cnpj' => $branch->cnpj,
            'neighborhood' => 'ASA SUL',
            'city' => 'BRASILIA',
            'state' => 'DF',
            'zipcode' => '70330000',
        ], $overrides);
    }
}
