<?php

namespace Tests\Unit\Services\OpenFinance;

use App\Services\OpenFinance\AccountSnapshot;
use App\Services\OpenFinance\TecnoSpeedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithOpenFinance;
use Tests\TestCase;

class TecnoSpeedAccountClientTest extends TestCase
{
    use InteractsWithOpenFinance;

    public function test_create_account_envia_mapeamento_e_statement_actived(): void
    {
        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/account' => Http::response([
                'accounts' => [$this->accountPayload()],
            ], 201),
        ]);

        $snapshot = $this->client()->createAccount(self::PAYER_CNPJ, [
            'bankCode' => '070',
            'agency' => '0501',
            'agencyDigit' => '',
            'accountNumber' => '00123456',
            'accountNumberDigit' => '7',
            'accountDac' => '7',
            'statementActived' => true,
        ]);

        $this->assertSame(self::ACCOUNT_HASH, $snapshot->accountHash);
        $this->assertSame(self::OPENFINANCE_LINK, $snapshot->openfinanceLink);
        $this->assertTrue($snapshot->statementActived);
        $this->assertArrayNotHasKey('openfinance_link', $snapshot->toSafeArray());
        $this->assertTrue($snapshot->toSafeArray()['has_openfinance_link']);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $account = $body[0] ?? $body;

            return $request->method() === 'POST'
                && $request->url() === 'https://staging.pagamentobancario.com.br/api/v1/account'
                && $request->hasHeader('cnpjsh', self::CNPJ_SH)
                && $request->hasHeader('tokensh', self::TOKEN_SH)
                && $request->hasHeader('payercpfcnpj', self::PAYER_CNPJ)
                && ($account['bankCode'] ?? null) === '070'
                && ($account['agency'] ?? null) === '0501'
                && ($account['accountNumber'] ?? null) === '00123456'
                && ($account['accountNumberDigit'] ?? null) === '7'
                && ($account['statementActived'] ?? null) === true;
        });
    }

    public function test_get_account_captura_openfinance_id(): void
    {
        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/account/'.self::ACCOUNT_HASH => Http::response([
                'accounts' => $this->accountPayload(['openfinanceId' => self::OPENFINANCE_ID]),
            ], 200),
        ]);

        $snapshot = $this->client()->getAccount(self::PAYER_CNPJ, self::ACCOUNT_HASH);

        $this->assertSame(self::OPENFINANCE_ID, $snapshot->openfinanceId);
        $this->assertTrue($snapshot->isConnected());
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->hasHeader('payercpfcnpj', self::PAYER_CNPJ));
    }

    public function test_list_accounts_casa_banco_agencia_conta(): void
    {
        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/account' => Http::response([
                'accounts' => [
                    $this->accountPayload(['bankCode' => '001', 'accountHash' => 'outra']),
                    $this->accountPayload(),
                ],
            ], 200),
        ]);

        $list = $this->client()->listAccounts(self::PAYER_CNPJ);
        $matches = array_values(array_filter(
            $list,
            fn (AccountSnapshot $item) => $item->matchesLocal('070', '0501', '00123456', '7'),
        ));

        $this->assertCount(1, $matches);
        $this->assertSame(self::ACCOUNT_HASH, $matches[0]->accountHash);
    }

    public function test_update_account_ativa_statement(): void
    {
        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/account/'.self::ACCOUNT_HASH => Http::response(
                $this->accountPayload(['statementActived' => true]),
                200,
            ),
        ]);

        $snapshot = $this->client()->updateAccount(self::PAYER_CNPJ, self::ACCOUNT_HASH, [
            'statementActived' => true,
        ]);

        $this->assertTrue($snapshot->statementActived);
        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && ($request->data()['statementActived'] ?? null) === true);
    }

    public function test_erro_422_controlado(): void
    {
        Http::fake(['*' => Http::response(['code' => 422], 422)]);
        try {
            $this->client()->createAccount(self::PAYER_CNPJ, ['bankCode' => '070', 'agency' => '1', 'accountNumber' => '1', 'statementActived' => true]);
            $this->fail();
        } catch (TecnoSpeedException $e) {
            $this->assertSame(TecnoSpeedException::KIND_VALIDATION, $e->kind);
        }
    }

    public function test_erro_503_controlado(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        try {
            $this->client()->listAccounts(self::PAYER_CNPJ);
            $this->fail();
        } catch (TecnoSpeedException $e) {
            $this->assertSame(TecnoSpeedException::KIND_UNAVAILABLE, $e->kind);
        }
    }

    public function test_timeout_na_consulta_da_conta(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });
        try {
            $this->client()->getAccount(self::PAYER_CNPJ, self::ACCOUNT_HASH);
            $this->fail();
        } catch (TecnoSpeedException $e) {
            $this->assertSame(TecnoSpeedException::KIND_TIMEOUT, $e->kind);
            $this->assertStringNotContainsString(self::OPENFINANCE_LINK, $e->getMessage());
        }
    }
}
