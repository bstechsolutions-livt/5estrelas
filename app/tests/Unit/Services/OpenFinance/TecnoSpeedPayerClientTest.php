<?php

namespace Tests\Unit\Services\OpenFinance;

use App\Services\OpenFinance\PayerRegistrationData;
use App\Services\OpenFinance\PayerSnapshot;
use App\Services\OpenFinance\TecnoSpeedClient;
use App\Services\OpenFinance\TecnoSpeedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TecnoSpeedPayerClientTest extends TestCase
{
    private const TOKEN_SH = 'token-secreto-de-teste-nao-logar';

    private const CNPJ_SH = '01001001000113';

    private const PAYER_CNPJ = '19593175000188';

    private const PAYER_TOKEN = 'token-de-pagador-nao-deve-vazar';

    public function test_consulta_pagador_por_header_payercpfcnpj(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET' && $request->url() === 'https://staging.pagamentobancario.com.br/api/v1/payer') {
                return Http::response([
                    'name' => '5 ESTRELAS',
                    'cpfCnpj' => self::PAYER_CNPJ,
                    'statementActived' => true,
                    'status' => 1,
                    'active' => true,
                    'token' => self::PAYER_TOKEN,
                    'accounts' => [['accountHash' => 'hash-secreto']],
                ], 200);
            }

            $this->fail('Não deveria chamar '.$request->method().' '.$request->url());
        });

        $snapshot = $this->client()->findPayerByDocument('19.593.175/0001-88');

        $this->assertNotNull($snapshot);
        $this->assertTrue($snapshot->exists);
        $this->assertTrue($snapshot->statementActived);
        $this->assertSame(self::PAYER_CNPJ, $snapshot->cpfCnpj);
        $this->assertSame('5 ESTRELAS', $snapshot->name);
        $this->assertSame(1, $snapshot->status);
        $encoded = json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::PAYER_TOKEN, $encoded);
        $this->assertStringNotContainsString('hash-secreto', $encoded);
        $this->assertStringNotContainsString(self::TOKEN_SH, $encoded);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://staging.pagamentobancario.com.br/api/v1/payer'
                && $request->hasHeader('cnpjsh', self::CNPJ_SH)
                && $request->hasHeader('tokensh', self::TOKEN_SH)
                && $request->hasHeader('payercpfcnpj', self::PAYER_CNPJ)
                && $request->hasHeader('Content-Type', 'application/json');
        });
    }

    public function test_consulta_recorre_a_lista_quando_get_retorna_422(): void
    {
        Http::fake(function ($request) {
            if ($request->url() === 'https://staging.pagamentobancario.com.br/api/v1/payer' && $request->method() === 'GET') {
                return Http::response(['code' => 422, 'message' => 'Invalid Param'], 422);
            }
            if (str_ends_with($request->url(), '/payer/list')) {
                return Http::response([
                    'payers' => [[
                        'name' => 'TESTES',
                        'cpfCnpj' => '19.593.175/0001-88',
                        'stamentActived' => false,
                        'token' => self::PAYER_TOKEN,
                    ]],
                ], 200);
            }

            $this->fail('Chamada inesperada '.$request->method().' '.$request->url());
        });

        $snapshot = $this->client()->findPayerByDocument(self::PAYER_CNPJ);

        $this->assertNotNull($snapshot);
        $this->assertFalse($snapshot->statementActived);
        $this->assertSame(self::PAYER_CNPJ, $snapshot->cpfCnpj);
        $this->assertStringNotContainsString(self::PAYER_TOKEN, json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_consulta_retorna_nulo_quando_lista_nao_contem_cnpj(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/payer') && ! str_ends_with($request->url(), '/payer/list')) {
                return Http::response(['code' => 422, 'message' => 'Invalid Param'], 422);
            }

            return Http::response(['payers' => []], 200);
        });

        $this->assertNull($this->client()->findPayerByDocument(self::PAYER_CNPJ));
    }

    public function test_cria_pagador_com_statement_actived_e_nao_expoe_token(): void
    {
        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/payer' => Http::response([
                'name' => '5 ESTRELAS',
                'cpfCnpj' => self::PAYER_CNPJ,
                'statementActived' => true,
                'token' => self::PAYER_TOKEN,
            ], 201),
        ]);

        $data = new PayerRegistrationData(
            name: '5 ESTRELAS SEGURANCA LTDA',
            cpfCnpj: self::PAYER_CNPJ,
            neighborhood: 'ASA SUL',
            city: 'BRASILIA',
            state: 'DF',
            zipcode: '70330000',
            addressNumber: '10',
            street: 'SQS 102',
        );

        $snapshot = $this->client()->createPayer($data);

        $this->assertTrue($snapshot->exists);
        $this->assertTrue($snapshot->statementActived);
        $this->assertStringNotContainsString(self::PAYER_TOKEN, json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR));

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://staging.pagamentobancario.com.br/api/v1/payer'
                && $request->hasHeader('cnpjsh', self::CNPJ_SH)
                && $request->hasHeader('tokensh', self::TOKEN_SH)
                && ($body['statementActived'] ?? null) === true
                && ($body['cpfCnpj'] ?? null) === self::PAYER_CNPJ
                && ($body['name'] ?? null) === '5 ESTRELAS SEGURANCA LTDA'
                && ($body['neighborhood'] ?? null) === 'ASA SUL'
                && ($body['city'] ?? null) === 'BRASILIA'
                && ($body['state'] ?? null) === 'DF'
                && ($body['zipcode'] ?? null) === '70330000'
                && ! array_key_exists('token', $body)
                && ! array_key_exists('accounts', $body);
        });
    }

    public function test_ativa_statement_com_put_e_header_payercpfcnpj(): void
    {
        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/payer' => Http::response([
                'name' => '5 ESTRELAS',
                'cpfCnpj' => self::PAYER_CNPJ,
                'statementActived' => true,
                'token' => self::PAYER_TOKEN,
            ], 200),
        ]);

        $snapshot = $this->client()->activateStatement('19.593.175/0001-88');

        $this->assertTrue($snapshot->statementActived);
        $this->assertStringNotContainsString(self::PAYER_TOKEN, json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR));

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->method() === 'PUT'
                && $request->url() === 'https://staging.pagamentobancario.com.br/api/v1/payer'
                && $request->hasHeader('payercpfcnpj', self::PAYER_CNPJ)
                && $body === ['statementActived' => true];
        });
    }

    #[DataProvider('payerHttpFailureProvider')]
    public function test_falhas_http_do_pagador(int $status, mixed $body, string $kind): void
    {
        Http::fake(['*' => Http::response($body, $status)]);

        try {
            $this->client()->createPayer($this->registration());
            $this->fail("Deveria falhar para HTTP {$status}");
        } catch (TecnoSpeedException $e) {
            $this->assertSame($kind, $e->kind, "status {$status}");
            $this->assertStringNotContainsString(self::TOKEN_SH, $e->getMessage());
            $this->assertStringNotContainsString(self::PAYER_TOKEN, $e->getMessage());
            $this->assertStringNotContainsString(self::CNPJ_SH, $e->getMessage());
        }
    }

    /**
     * @return array<string, array{int, mixed, string}>
     */
    public static function payerHttpFailureProvider(): array
    {
        return [
            '401' => [401, ['code' => 401, 'message' => 'CNPJ ou TOKEN token-secreto-de-teste-nao-logar'], TecnoSpeedException::KIND_AUTH],
            '403 json' => [403, ['code' => 403, 'message' => 'forbidden'], TecnoSpeedException::KIND_AUTH],
            '403 html waf' => [403, '<html><title>403 Forbidden</title></html>', TecnoSpeedException::KIND_IP_BLOCKED],
            '422' => [422, ['code' => 422, 'message' => 'Invalid Param token-de-pagador-nao-deve-vazar'], TecnoSpeedException::KIND_VALIDATION],
            '503' => [503, 'unavailable', TecnoSpeedException::KIND_UNAVAILABLE],
        ];
    }

    public function test_timeout_no_create(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        try {
            $this->client()->createPayer($this->registration());
            $this->fail('Deveria lançar timeout');
        } catch (TecnoSpeedException $e) {
            $this->assertSame(TecnoSpeedException::KIND_TIMEOUT, $e->kind);
        }
    }

    public function test_snapshot_from_api_descarta_token(): void
    {
        $snapshot = PayerSnapshot::fromApi([
            'name' => 'X',
            'cpfCnpj' => self::PAYER_CNPJ,
            'token' => self::PAYER_TOKEN,
            'statementActived' => true,
            'accounts' => [['accountHash' => 'abc']],
        ]);

        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::PAYER_TOKEN, $encoded);
        $this->assertStringNotContainsString('accountHash', $encoded);
        $this->assertFalse(property_exists($snapshot, 'token'));
    }

    public function test_logs_de_falha_nao_trazem_segredos(): void
    {
        Log::spy();
        Http::fake(['*' => Http::response(['code' => 401, 'message' => self::TOKEN_SH.' '.self::CNPJ_SH], 401)]);

        (new TecnoSpeedClient($this->baseConfig()))->checkConnection();

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
            $encoded = json_encode([$message, $context], JSON_THROW_ON_ERROR);

            return ! str_contains($encoded, self::TOKEN_SH)
                && ! str_contains($encoded, self::CNPJ_SH)
                && ! str_contains($encoded, self::PAYER_TOKEN);
        });
    }

    private function client(): TecnoSpeedClient
    {
        return new TecnoSpeedClient($this->baseConfig());
    }

    private function registration(): PayerRegistrationData
    {
        return new PayerRegistrationData(
            name: '5 ESTRELAS SEGURANCA LTDA',
            cpfCnpj: self::PAYER_CNPJ,
            neighborhood: 'ASA SUL',
            city: 'BRASILIA',
            state: 'DF',
            zipcode: '70330000',
            addressNumber: '10',
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function baseConfig(array $overrides = []): array
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
}
