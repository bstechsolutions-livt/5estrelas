<?php

namespace Tests\Unit\Services\OpenFinance;

use App\Models\Branch;
use App\Models\Comercial\Filial;
use App\Models\OpenFinancePayer;
use App\Services\OpenFinance\EligiblePayerCatalog;
use App\Services\OpenFinance\EnsurePayerResult;
use App\Services\OpenFinance\PayerService;
use App\Services\OpenFinance\TecnoSpeedClient;
use App\Services\OpenFinance\TecnoSpeedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PayerServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_SH = 'token-secreto-de-teste-nao-logar';

    private const CNPJ_SH = '01001001000113';

    private const PAYER_CNPJ = '19593175000188';

    private const PAYER_TOKEN = 'token-de-pagador-nao-deve-vazar';

    public function test_ensure_cadastra_quando_nao_existe_com_statement_actived(): void
    {
        $this->seedFilial();
        $this->fakeNotFoundThenCreate();

        $result = $this->service()->ensurePayer('19.593.175/0001-88');

        $this->assertTrue($result->succeeded);
        $this->assertSame(EnsurePayerResult::OUTCOME_CREATED, $result->outcome);
        $this->assertTrue($result->payer?->statementActived);
        $this->assertDatabaseHas('open_finance_payers', [
            'cpf_cnpj' => self::PAYER_CNPJ,
            'provider' => 'tecnospeed',
            'remote_exists' => true,
            'statement_activated' => true,
            'last_status' => 'created',
        ]);

        $posts = Http::recorded(fn ($request) => $request->method() === 'POST')->values();
        $this->assertCount(1, $posts);
        $this->assertSame(true, $posts[0][0]['statementActived']);
        $this->assertSame(self::PAYER_CNPJ, $posts[0][0]['cpfCnpj']);
        $puts = Http::recorded(fn ($request) => $request->method() === 'PUT')->values();
        $this->assertCount(0, $puts);
    }

    public function test_ensure_nao_duplica_quando_ja_esta_ativo(): void
    {
        $this->seedFilial();
        Http::fake(function ($request) {
            $this->assertNotSame('POST', $request->method());
            $this->assertNotSame('PUT', $request->method());

            return Http::response([
                'name' => '5 ESTRELAS',
                'cpfCnpj' => self::PAYER_CNPJ,
                'statementActived' => true,
                'token' => self::PAYER_TOKEN,
            ], 200);
        });

        $service = $this->service();
        $first = $service->ensurePayer(self::PAYER_CNPJ);
        $second = $service->ensurePayer(self::PAYER_CNPJ);

        $this->assertSame(EnsurePayerResult::OUTCOME_UNCHANGED, $first->outcome);
        $this->assertSame(EnsurePayerResult::OUTCOME_UNCHANGED, $second->outcome);
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'POST'));
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'PUT'));
        $this->assertSame(1, OpenFinancePayer::query()->count());
    }

    public function test_ensure_ativa_quando_existe_inativo(): void
    {
        $this->seedFilial();
        Http::fake(function ($request) {
            if ($request->method() === 'PUT') {
                return Http::response([
                    'name' => '5 ESTRELAS',
                    'cpfCnpj' => self::PAYER_CNPJ,
                    'statementActived' => true,
                    'token' => self::PAYER_TOKEN,
                ], 200);
            }

            if ($request->method() === 'POST') {
                $this->fail('Não deveria criar pagador já existente');
            }

            return Http::response([
                'name' => '5 ESTRELAS',
                'cpfCnpj' => self::PAYER_CNPJ,
                'statementActived' => false,
                'token' => self::PAYER_TOKEN,
            ], 200);
        });

        $result = $this->service()->ensurePayer(self::PAYER_CNPJ);

        $this->assertSame(EnsurePayerResult::OUTCOME_ACTIVATED, $result->outcome);
        $this->assertTrue($result->payer?->statementActived);
        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'PUT'));
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'POST'));
        $this->assertDatabaseHas('open_finance_payers', [
            'cpf_cnpj' => self::PAYER_CNPJ,
            'statement_activated' => true,
            'last_status' => 'activated',
        ]);
    }

    public function test_cnpj_com_e_sem_mascara_sao_o_mesmo_pagador(): void
    {
        $this->seedFilial();
        $created = false;
        Http::fake(function ($request) use (&$created) {
            if ($request->method() === 'POST') {
                $created = true;

                return Http::response([
                    'name' => '5 ESTRELAS SEGURANCA LTDA',
                    'cpfCnpj' => self::PAYER_CNPJ,
                    'statementActived' => true,
                    'token' => self::PAYER_TOKEN,
                ], 201);
            }

            if ($created && $request->method() === 'GET' && ! str_ends_with($request->url(), '/payer/list')) {
                return Http::response([
                    'name' => '5 ESTRELAS',
                    'cpfCnpj' => self::PAYER_CNPJ,
                    'statementActived' => true,
                ], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($request->url(), '/payer/list')) {
                return Http::response(['payers' => []], 200);
            }

            if ($request->method() === 'PUT') {
                $this->fail('PUT inesperado');
            }

            return Http::response(['code' => 422, 'message' => 'Invalid Param'], 422);
        });

        $first = $this->service()->ensurePayer('19.593.175/0001-88');
        $second = $this->service()->ensurePayer(self::PAYER_CNPJ);

        $this->assertSame(EnsurePayerResult::OUTCOME_CREATED, $first->outcome);
        $this->assertSame(EnsurePayerResult::OUTCOME_UNCHANGED, $second->outcome);
        $this->assertCount(1, Http::recorded(fn ($request) => $request->method() === 'POST'));
        $this->assertSame(1, OpenFinancePayer::query()->count());
        $this->assertSame(self::PAYER_CNPJ, OpenFinancePayer::query()->value('cpf_cnpj'));
    }

    public function test_catalogo_nao_hardcoda_cnpj_e_deduplica(): void
    {
        $this->seedFilial();
        Filial::create([
            'senior_id' => '3-1',
            'cod_emp' => 3,
            'cod_fil' => 1,
            'nome' => 'OUTRA EMPRESA',
            'cnpj' => '86.492.313/0001-20',
            'uf' => 'GO',
            'ativo' => true,
            'senior_raw' => [
                'baiFil' => 'CENTRO',
                'cidFil' => 'GOIANIA',
                'sigUfs' => 'GO',
                'cepFil' => '74000000',
                'eenFil' => '1',
            ],
        ]);
        Branch::create([
            'name' => 'MESMA FILIAL',
            'cnpj' => '19.593.175/0001-88',
            'is_active' => true,
        ]);

        $catalog = (new EligiblePayerCatalog)->all();
        $cnpjs = array_map(fn ($p) => $p->cpfCnpj, $catalog);

        $this->assertContains(self::PAYER_CNPJ, $cnpjs);
        $this->assertContains('86492313000120', $cnpjs);
        $this->assertCount(2, $cnpjs);
        $this->assertSame(1, count(array_filter($cnpjs, fn ($c) => $c === self::PAYER_CNPJ)));
    }

    public function test_ip_blocked_nao_finge_sucesso(): void
    {
        $this->seedFilial();
        Http::fake(['*' => Http::response('<html><title>403 Forbidden</title></html>', 403)]);

        $result = $this->service()->ensurePayer(self::PAYER_CNPJ);

        $this->assertFalse($result->succeeded);
        $this->assertSame(TecnoSpeedException::KIND_IP_BLOCKED, $result->code);
        $this->assertDatabaseHas('open_finance_payers', [
            'cpf_cnpj' => self::PAYER_CNPJ,
            'last_status' => 'ip_blocked',
        ]);
        $row = OpenFinancePayer::query()->first();
        $this->assertNull($row->remote_exists);
        $this->assertNull($row->statement_activated);
    }

    public function test_falhas_http_sao_persistidas_sem_sucesso(): void
    {
        $this->seedFilial();
        $status = 401;
        Http::fake(function () use (&$status) {
            return Http::response(['message' => self::PAYER_TOKEN], $status);
        });

        foreach ([
            401 => TecnoSpeedException::KIND_AUTH,
            422 => TecnoSpeedException::KIND_VALIDATION,
            503 => TecnoSpeedException::KIND_UNAVAILABLE,
        ] as $httpStatus => $kind) {
            OpenFinancePayer::query()->delete();
            $status = $httpStatus;
            $result = $this->service()->ensurePayer(self::PAYER_CNPJ);
            $this->assertFalse($result->succeeded);
            $this->assertSame($kind, $result->code);
            $this->assertStringNotContainsString(self::PAYER_TOKEN, $result->message);
            $this->assertDatabaseHas('open_finance_payers', [
                'cpf_cnpj' => self::PAYER_CNPJ,
                'last_status' => $kind,
            ]);
        }
    }

    public function test_timeout_e_persistido_sem_sucesso(): void
    {
        $this->seedFilial();
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $timeout = $this->service()->ensurePayer(self::PAYER_CNPJ);

        $this->assertSame(TecnoSpeedException::KIND_TIMEOUT, $timeout->code);
        $this->assertFalse($timeout->succeeded);
    }

    public function test_ensure_nao_posta_quando_endereco_falta(): void
    {
        Filial::create([
            'senior_id' => '2-1',
            'cod_emp' => 2,
            'cod_fil' => 1,
            'nome' => 'SEM ENDERECO',
            'cnpj' => self::PAYER_CNPJ,
            'uf' => 'DF',
            'ativo' => true,
            'senior_raw' => ['nenFil' => 'SEM ENDERECO'],
        ]);
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                $this->fail('Não deveria cadastrar sem endereço');
            }

            if ($request->method() === 'GET' && str_ends_with($request->url(), '/payer/list')) {
                return Http::response(['payers' => []], 200);
            }

            return Http::response(['code' => 422], 422);
        });

        $result = $this->service()->ensurePayer(self::PAYER_CNPJ);

        $this->assertFalse($result->succeeded);
        $this->assertSame(TecnoSpeedException::KIND_VALIDATION, $result->code);
        $this->assertCount(0, Http::recorded(fn ($request) => $request->method() === 'POST'));
    }

    public function test_logs_nao_trazem_tokens_nem_cnpj_da_sh(): void
    {
        $this->seedFilial();
        Log::spy();
        $this->fakeNotFoundThenCreate();

        $this->service()->ensurePayer(self::PAYER_CNPJ);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) {
            $encoded = json_encode([$message, $context], JSON_THROW_ON_ERROR);

            return str_contains($message, '[open-finance]')
                && ! str_contains($encoded, self::TOKEN_SH)
                && ! str_contains($encoded, self::PAYER_TOKEN)
                && ! str_contains($encoded, self::CNPJ_SH);
        });
    }

    public function test_verify_nao_cria_nem_ativa(): void
    {
        $this->seedFilial();
        Http::fake([
            'https://staging.pagamentobancario.com.br/api/v1/payer' => Http::response(['code' => 422], 422),
            'https://staging.pagamentobancario.com.br/api/v1/payer/list' => Http::response(['payers' => []], 200),
        ]);

        $result = $this->service()->verify(self::PAYER_CNPJ);

        $this->assertTrue($result->succeeded);
        $this->assertSame('not_found', $result->code);
        $this->assertCount(0, Http::recorded(fn ($request) => in_array($request->method(), ['POST', 'PUT'], true)));
        $this->assertDatabaseHas('open_finance_payers', [
            'cpf_cnpj' => self::PAYER_CNPJ,
            'remote_exists' => false,
            'last_status' => 'not_found',
        ]);
    }

    private function service(): PayerService
    {
        return new PayerService(new EligiblePayerCatalog, new TecnoSpeedClient($this->baseConfig()));
    }

    private function seedFilial(): Filial
    {
        return Filial::create([
            'senior_id' => '2-1',
            'cod_emp' => 2,
            'cod_fil' => 1,
            'nome' => '5 ESTRELAS SEGURANCA LTDA',
            'fantasia' => 'MATRIZ',
            'apelido' => '5 Estrelas',
            'cnpj' => '19.593.175/0001-88',
            'uf' => 'DF',
            'ativo' => true,
            'senior_raw' => [
                'nenFil' => '5 ESTRELAS SEGURANCA LTDA',
                'endFil' => 'SQS 102',
                'baiFil' => 'ASA SUL',
                'cidFil' => 'BRASILIA',
                'cepFil' => '70330000',
                'eenFil' => '10',
                'sigUfs' => 'DF',
            ],
        ]);
    }

    private function fakeNotFoundThenCreate(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                return Http::response([
                    'name' => '5 ESTRELAS SEGURANCA LTDA',
                    'cpfCnpj' => self::PAYER_CNPJ,
                    'statementActived' => true,
                    'token' => self::PAYER_TOKEN,
                ], 201);
            }

            if ($request->method() === 'GET' && str_ends_with($request->url(), '/payer/list')) {
                return Http::response(['payers' => []], 200);
            }

            if ($request->method() === 'PUT') {
                $this->fail('PUT inesperado ao cadastrar pagador novo');
            }

            return Http::response(['code' => 422, 'message' => 'Invalid Param'], 422);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function baseConfig(): array
    {
        return [
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
        ];
    }
}
