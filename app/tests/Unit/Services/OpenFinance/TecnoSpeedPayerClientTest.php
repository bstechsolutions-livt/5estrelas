<?php

namespace Tests\Unit\Services\OpenFinance;

use App\Services\OpenFinance\PayerRegistrationData;
use App\Services\OpenFinance\PayerSnapshot;
use App\Services\OpenFinance\TecnoSpeedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithOpenFinance;
use Tests\TestCase;

class TecnoSpeedPayerClientTest extends TestCase
{
    use InteractsWithOpenFinance;

    public function test_consulta_pagador_por_header_payercpfcnpj(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET' && $request->url() === 'https://staging.pagamentobancario.com.br/api/v1/payer') {
                return Http::response($this->payerPayload(['accounts' => [['accountHash' => 'hash-secreto']]]), 200);
            }
            $this->fail('Não deveria chamar '.$request->method().' '.$request->url());
        });

        $snapshot = $this->client()->findPayerByDocument('19.593.175/0001-88');

        $this->assertNotNull($snapshot);
        $this->assertTrue($snapshot->exists);
        $this->assertTrue($snapshot->statementActived);
        $this->assertSame(self::PAYER_CNPJ, $snapshot->cpfCnpj);
        $encoded = json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::PAYER_TOKEN, $encoded);
        $this->assertStringNotContainsString('hash-secreto', $encoded);

        Http::assertSent(fn ($request) => $request->hasHeader('payercpfcnpj', self::PAYER_CNPJ)
            && $request->hasHeader('cnpjsh', self::CNPJ_SH)
            && $request->hasHeader('tokensh', self::TOKEN_SH));
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
                        'stamentActived' => true,
                        'token' => self::PAYER_TOKEN,
                    ]],
                ], 200);
            }

            return Http::response('nope', 500);
        });

        $snapshot = $this->client()->findPayerByDocument(self::PAYER_CNPJ);
        $this->assertNotNull($snapshot);
        $this->assertTrue($snapshot->statementActived);
        $this->assertStringNotContainsString(self::PAYER_TOKEN, json_encode($snapshot->toArray()));
    }

    public function test_create_payer_envia_statement_actived_e_documento_normalizado(): void
    {
        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/payer' => Http::response($this->payerPayload(), 201),
        ]);

        $data = new PayerRegistrationData(
            name: '5 ESTRELAS SEGURANCA LTDA',
            cpfCnpj: self::PAYER_CNPJ,
            neighborhood: 'ASA SUL',
            city: 'BRASILIA',
            state: 'DF',
            zipcode: '70330000',
            addressNumber: '10',
        );

        $snapshot = $this->client()->createPayer($data);
        $this->assertTrue($snapshot->statementActived);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://staging.pagamentobancario.com.br/api/v1/payer'
                && $request->hasHeader('cnpjsh', self::CNPJ_SH)
                && $request->hasHeader('tokensh', self::TOKEN_SH)
                && ($body['cpfCnpj'] ?? null) === self::PAYER_CNPJ
                && ($body['statementActived'] ?? null) === true
                && ($body['neighborhood'] ?? null) === 'ASA SUL'
                && ! isset($body['token']);
        });
    }

    public function test_campo_obrigatorio_faltante_bloqueia_antes_da_api(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        $data = new PayerRegistrationData(
            name: 'Empresa',
            cpfCnpj: self::PAYER_CNPJ,
            neighborhood: '',
            city: 'BRASILIA',
            state: 'DF',
            zipcode: '70330000',
        );

        try {
            $this->client()->createPayer($data);
            $this->fail('Deveria bloquear cadastro incompleto');
        } catch (TecnoSpeedException $e) {
            $this->assertSame(TecnoSpeedException::KIND_VALIDATION, $e->kind);
            $this->assertStringContainsString('faltam dados cadastrais', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_activate_statement_envia_put_oficial(): void
    {
        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/payer' => Http::response($this->payerPayload(), 200),
        ]);

        $snapshot = $this->client()->activateStatement('19.593.175/0001-88');
        $this->assertTrue($snapshot->statementActived);

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->hasHeader('payercpfcnpj', self::PAYER_CNPJ)
            && ($request->data()['statementActived'] ?? null) === true);
    }

    public static function errorProvider(): array
    {
        return [
            '401' => [401, ['code' => 401, 'message' => 'token '.self::TOKEN_SH], TecnoSpeedException::KIND_AUTH],
            '403 json' => [403, ['code' => 403], TecnoSpeedException::KIND_AUTH],
            '403 html' => [403, '<html>forbidden</html>', TecnoSpeedException::KIND_IP_BLOCKED],
            '422' => [422, ['code' => 422, 'message' => 'Invalid'], TecnoSpeedException::KIND_VALIDATION],
            '503' => [503, 'down', TecnoSpeedException::KIND_UNAVAILABLE],
        ];
    }

    #[DataProvider('errorProvider')]
    public function test_erros_http_controlados_sem_segredo(int $status, mixed $body, string $kind): void
    {
        Http::fake(['*' => Http::response($body, $status)]);

        try {
            $this->client()->createPayer(new PayerRegistrationData(
                name: 'Empresa',
                cpfCnpj: self::PAYER_CNPJ,
                neighborhood: 'ASA SUL',
                city: 'BRASILIA',
                state: 'DF',
                zipcode: '70330000',
            ));
            $this->fail('Deveria lançar TecnoSpeedException');
        } catch (TecnoSpeedException $e) {
            $this->assertSame($kind, $e->kind);
            $this->assertStringNotContainsString(self::TOKEN_SH, $e->getMessage());
            $this->assertStringNotContainsString(self::PAYER_TOKEN, $e->getMessage());
        }
    }

    public function test_timeout_na_criacao(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $this->expectException(TecnoSpeedException::class);
        try {
            $this->client()->createPayer(new PayerRegistrationData(
                name: 'Empresa',
                cpfCnpj: self::PAYER_CNPJ,
                neighborhood: 'ASA SUL',
                city: 'BRASILIA',
                state: 'DF',
                zipcode: '70330000',
            ));
        } catch (TecnoSpeedException $e) {
            $this->assertSame(TecnoSpeedException::KIND_TIMEOUT, $e->kind);
            throw $e;
        }
    }

    public function test_from_api_descarta_token(): void
    {
        $snapshot = PayerSnapshot::fromApi($this->payerPayload());
        $this->assertArrayNotHasKey('token', $snapshot->toArray());
        $this->assertStringNotContainsString(self::PAYER_TOKEN, json_encode($snapshot->toArray()));
    }
}
