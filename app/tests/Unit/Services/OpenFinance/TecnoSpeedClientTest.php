<?php

namespace Tests\Unit\Services\OpenFinance;

use App\Services\OpenFinance\TecnoSpeedClient;
use App\Services\OpenFinance\TecnoSpeedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TecnoSpeedClientTest extends TestCase
{
    private const TOKEN = 'token-secreto-de-teste-nao-logar';

    private const CNPJ = '01001001000113';

    public function test_nao_esta_configurado_sem_credenciais_ou_flag(): void
    {
        $this->assertFalse((new TecnoSpeedClient($this->baseConfig(['enabled' => false])))->isConfigured());
        $this->assertFalse((new TecnoSpeedClient($this->baseConfig([
            'credentials' => ['cnpj_sh' => '', 'token_sh' => self::TOKEN],
        ])))->isConfigured());
        $this->assertFalse((new TecnoSpeedClient($this->baseConfig([
            'credentials' => ['cnpj_sh' => self::CNPJ, 'token_sh' => ''],
        ])))->isConfigured());
        $this->assertTrue((new TecnoSpeedClient($this->baseConfig()))->isConfigured());
    }

    public function test_check_connection_nao_chama_api_quando_nao_configurado(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        $result = (new TecnoSpeedClient($this->baseConfig(['enabled' => false])))->checkConnection();

        $this->assertFalse($result->configured);
        $this->assertFalse($result->connected);
        $this->assertSame(TecnoSpeedException::KIND_NOT_CONFIGURED, $result->code);
        $this->assertStringContainsString('não está configurada', $result->message);
        Http::assertNothingSent();
    }

    public function test_ping_sucesso_envia_headers_oficiais_e_nao_expoe_payload(): void
    {
        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/payer/list' => Http::response([
                'payers' => [
                    ['name' => 'TESTES', 'token' => 'token-de-pagador-nao-deve-vazar', 'cpfCnpj' => self::CNPJ],
                ],
            ], 200),
        ]);

        $client = new TecnoSpeedClient($this->baseConfig());
        $result = $client->checkConnection();

        $this->assertTrue($result->configured);
        $this->assertTrue($result->connected);
        $this->assertSame('ok', $result->code);
        $this->assertSame('Conexão OK. Autenticação e comunicação com a TecnoSpeed estão funcionando.', $result->message);

        $frontend = $result->toFrontend();
        $encoded = json_encode($frontend, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::TOKEN, $encoded);
        $this->assertStringNotContainsString('token-de-pagador', $encoded);
        $this->assertArrayNotHasKey('payers', $frontend);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://staging.pagamentobancario.com.br/api/v1/payer/list'
                && $request->method() === 'GET'
                && $request->hasHeader('cnpjsh', self::CNPJ)
                && $request->hasHeader('tokensh', self::TOKEN)
                && $request->hasHeader('Content-Type', 'application/json');
        });
    }

    public function test_autenticacao_negada_401(): void
    {
        Http::fake([
            '*' => Http::response(['code' => 401, 'message' => 'CNPJ ou TOKEN incorretos, verifique sua conta em https://conta.tecnospeed.com.br'], 401),
        ]);

        $result = (new TecnoSpeedClient($this->baseConfig()))->checkConnection();

        $this->assertTrue($result->configured);
        $this->assertFalse($result->connected);
        $this->assertSame(TecnoSpeedException::KIND_AUTH, $result->code);
        $this->assertStringContainsString('recusou a autenticação', $result->message);
        $this->assertStringNotContainsString(self::TOKEN, $result->message);
        $this->assertFalse($result->toFrontend()['show_ip_release']);
        $this->assertNull($result->toFrontend()['ip_release_url']);
    }

    public function test_credencial_obrigatoria_ausente_422(): void
    {
        Http::fake([
            '*' => Http::response(['code' => 422, 'message' => 'Campo cnpjsh é obrigatório'], 422),
        ]);

        $result = (new TecnoSpeedClient($this->baseConfig()))->checkConnection();

        $this->assertSame(TecnoSpeedException::KIND_AUTH, $result->code);
        $this->assertFalse($result->connected);
    }

    public function test_timeout_mapeia_mensagem_controlada(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 20000 milliseconds');
        });

        $result = (new TecnoSpeedClient($this->baseConfig()))->checkConnection();

        $this->assertSame(TecnoSpeedException::KIND_TIMEOUT, $result->code);
        $this->assertStringContainsString('não respondeu a tempo', $result->message);
        $this->assertFalse($result->toFrontend()['show_ip_release']);
        $this->assertNull($result->toFrontend()['ip_release_url']);
    }

    public function test_html_403_de_waf_e_bloqueio_de_ip(): void
    {
        Http::fake(['*' => Http::response('<html><head><title>403 Forbidden</title></head></html>', 403)]);

        $result = (new TecnoSpeedClient($this->baseConfig()))->checkConnection();
        $frontend = $result->toFrontend();

        $this->assertSame(TecnoSpeedException::KIND_IP_BLOCKED, $result->code);
        $this->assertSame(
            'Não foi possível acessar a TecnoSpeed a partir deste ambiente. Verifique a liberação do IP junto ao provedor.',
            $result->message
        );
        $this->assertTrue($frontend['show_ip_release']);
        $this->assertSame(config('open_finance.ip_release_form_url'), $frontend['ip_release_url']);
        $this->assertStringNotContainsString(self::TOKEN, json_encode($frontend, JSON_THROW_ON_ERROR));
    }

    public function test_json_403_continua_autenticacao_negada(): void
    {
        Http::fake(['*' => Http::response(['code' => 403, 'message' => 'forbidden'], 403)]);

        $result = (new TecnoSpeedClient($this->baseConfig()))->checkConnection();

        $this->assertSame(TecnoSpeedException::KIND_AUTH, $result->code);
    }

    public function test_indisponibilidade_503(): void
    {
        Http::fake(['*' => Http::response('unavailable', 503)]);

        $result = (new TecnoSpeedClient($this->baseConfig()))->checkConnection();

        $this->assertSame(TecnoSpeedException::KIND_UNAVAILABLE, $result->code);
        $this->assertStringContainsString('indisponível', $result->message);
        $this->assertFalse($result->toFrontend()['show_ip_release']);
        $this->assertNull($result->toFrontend()['ip_release_url']);
    }

    public function test_resposta_inesperada_nao_expoe_corpo(): void
    {
        Http::fake(['*' => Http::response('<html>token-secreto-de-teste-nao-logar</html>', 200)]);

        $result = (new TecnoSpeedClient($this->baseConfig()))->checkConnection();

        $this->assertSame(TecnoSpeedException::KIND_UNEXPECTED, $result->code);
        $this->assertStringContainsString('inesperada', $result->message);
        $this->assertStringNotContainsString(self::TOKEN, $result->message);
        $this->assertStringNotContainsString('<html>', $result->message);
    }

    public function test_logs_nunca_registram_token_ou_cnpj(): void
    {
        Log::spy();
        Http::fake(['*' => Http::response(['code' => 401, 'message' => 'CNPJ ou TOKEN incorretos '.self::TOKEN], 401)]);

        (new TecnoSpeedClient($this->baseConfig()))->checkConnection();

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
            $encoded = json_encode([$message, $context], JSON_THROW_ON_ERROR);

            return str_contains($message, '[open-finance]')
                && ! str_contains($encoded, self::TOKEN)
                && ! str_contains($encoded, self::CNPJ)
                && ($context['kind'] ?? null) === TecnoSpeedException::KIND_AUTH;
        });
    }

    public function test_normaliza_cnpj_com_mascara_e_usa_ambiente_producao(): void
    {
        Http::fake([
            'https://api.pagamentobancario.com.br/api/v1/payer/list' => Http::response(['payers' => []], 200),
        ]);

        $client = new TecnoSpeedClient($this->baseConfig([
            'environment' => 'production',
            'credentials' => ['cnpj_sh' => '01.001.001/0001-13', 'token_sh' => self::TOKEN],
        ]));

        $this->assertTrue($client->checkConnection()->connected);

        Http::assertSent(fn ($request) => $request->hasHeader('cnpjsh', self::CNPJ)
            && $request->url() === 'https://api.pagamentobancario.com.br/api/v1/payer/list');
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
                'cnpj_sh' => self::CNPJ,
                'token_sh' => self::TOKEN,
            ],
            'timeout' => [
                'connect' => 2,
                'response' => 2,
            ],
        ], $overrides);
    }
}
