<?php

namespace App\Services\OpenFinance;

use App\Support\CnpjNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client da API de Pagamentos TecnoSpeed (Open Finance).
 *
 * Autenticação oficial: headers `cnpjsh` + `tokensh` (+ `payercpfcnpj` nas ops do pagador).
 * Pedaço 1: GET /payer/list (handshake).
 * Pedaço 2: GET/POST/PUT /payer e POST/GET/PUT /account.
 *
 * Não registra tokensh, token do pagador, CNPJ da SH, openfinanceLink nem payload bruto.
 */
class TecnoSpeedClient
{
    private const PING_PATH = '/payer/list';

    private const PAYER_PATH = '/payer';

    private const PAYER_LIST_PATH = '/payer/list';

    private const ACCOUNT_PATH = '/account';

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function fromConfig(): self
    {
        return new self(config('open_finance'));
    }

    public function environment(): string
    {
        $env = strtolower((string) ($this->config['environment'] ?? 'staging'));

        return $env === 'production' ? 'production' : 'staging';
    }

    public function isConfigured(): bool
    {
        if (! ($this->config['enabled'] ?? false)) {
            return false;
        }

        return $this->cnpjSh() !== '' && $this->tokenSh() !== '';
    }

    public function checkConnection(): ConnectionCheckResult
    {
        $environment = $this->environment();

        if (! $this->isConfigured()) {
            return new ConnectionCheckResult(
                configured: false,
                connected: false,
                code: TecnoSpeedException::KIND_NOT_CONFIGURED,
                message: 'A integração com a TecnoSpeed ainda não está configurada. Peça ao administrador para habilitar o Open Finance e preencher o CNPJ e o token da Software House neste ambiente.',
                environment: $environment,
            );
        }

        try {
            $this->ping();
        } catch (TecnoSpeedException $e) {
            $this->logFailure($e);

            return new ConnectionCheckResult(
                configured: true,
                connected: false,
                code: $e->kind,
                message: $e->getMessage(),
                environment: $environment,
            );
        }

        $this->logSuccess();

        return new ConnectionCheckResult(
            configured: true,
            connected: true,
            code: 'ok',
            message: 'Conexão OK. Autenticação e comunicação com a TecnoSpeed estão funcionando.',
            environment: $environment,
        );
    }

    /**
     * Chamada autenticada simples (GET /api/v1/payer/list).
     * Não devolve a lista de pagadores — só valida o handshake.
     *
     * @throws TecnoSpeedException
     */
    public function ping(): void
    {
        $this->assertConfigured();

        $response = $this->request('GET', self::PING_PATH);
        $this->assertSuccessfulPing($response);
    }

    /**
     * Localiza pagador pelo CPF/CNPJ (com ou sem máscara).
     *
     * Consulta oficial: GET /api/v1/payer com header `payercpfcnpj`.
     * Se a consulta pontual não confirmar (422/404/documento ausente), recorre a
     * GET /api/v1/payer/list e compara `cpfCnpj` normalizado.
     *
     * @throws TecnoSpeedException
     */
    public function findPayerByDocument(string $cpfCnpj): ?PayerSnapshot
    {
        $this->assertConfigured();
        $document = $this->normalizeDocument($cpfCnpj);
        if ($document === '') {
            throw new TecnoSpeedException(
                'O CPF/CNPJ do pagador é inválido.',
                TecnoSpeedException::KIND_VALIDATION,
            );
        }

        $response = $this->request('GET', self::PAYER_PATH, extraHeaders: [
            'payercpfcnpj' => $document,
        ]);

        $status = $response->status();

        if ($status === 200) {
            $payload = $response->json();
            if (! is_array($payload)) {
                throw new TecnoSpeedException(
                    'A TecnoSpeed respondeu de forma inesperada. Não foi possível consultar o pagador.',
                    TecnoSpeedException::KIND_UNEXPECTED,
                    $status,
                );
            }

            if ($this->payloadMatchesDocument($payload, $document)) {
                return PayerSnapshot::fromApi($payload, true);
            }
        } elseif (! in_array($status, [404, 422], true)) {
            $this->throwFromResponse($response, treat422AsAuth: false);
        }

        return $this->findPayerInList($document);
    }

    /**
     * Lista pagadores da SH (GET /api/v1/payer/list). Sem token nem accounts.
     *
     * @return list<PayerSnapshot>
     *
     * @throws TecnoSpeedException
     */
    public function listPayers(): array
    {
        $this->assertConfigured();

        $response = $this->request('GET', self::PAYER_LIST_PATH);
        if ($response->status() !== 200) {
            $this->throwFromResponse($response, treat422AsAuth: false);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new TecnoSpeedException(
                'A TecnoSpeed respondeu de forma inesperada. Não foi possível listar os pagadores.',
                TecnoSpeedException::KIND_UNEXPECTED,
                $response->status(),
            );
        }

        return $this->snapshotsFromListPayload($payload);
    }

    /**
     * Cadastra pagador com Extrato/Open Finance ativo (POST /api/v1/payer).
     *
     * @throws TecnoSpeedException
     */
    public function createPayer(PayerRegistrationData $data): PayerSnapshot
    {
        $this->assertConfigured();

        if (! $data->isComplete()) {
            throw new TecnoSpeedException(
                'Não foi possível cadastrar o pagador: faltam dados cadastrais obrigatórios (razão social, bairro, cidade, UF e CEP).',
                TecnoSpeedException::KIND_VALIDATION,
            );
        }

        $response = $this->request('POST', self::PAYER_PATH, body: $data->toCreatePayload());
        $status = $response->status();

        if (! in_array($status, [200, 201], true)) {
            $this->throwFromResponse($response, treat422AsAuth: false);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new TecnoSpeedException(
                'A TecnoSpeed respondeu de forma inesperada. Não foi possível confirmar o cadastro do pagador.',
                TecnoSpeedException::KIND_UNEXPECTED,
                $status,
            );
        }

        return PayerSnapshot::fromApi($payload, true);
    }

    /**
     * Ativa Extrato/Open Finance em pagador existente (PUT /api/v1/payer).
     *
     * @throws TecnoSpeedException
     */
    public function activateStatement(string $cpfCnpj): PayerSnapshot
    {
        $this->assertConfigured();
        $document = $this->normalizeDocument($cpfCnpj);
        if ($document === '') {
            throw new TecnoSpeedException(
                'O CPF/CNPJ do pagador é inválido.',
                TecnoSpeedException::KIND_VALIDATION,
            );
        }

        $response = $this->request('PUT', self::PAYER_PATH, extraHeaders: [
            'payercpfcnpj' => $document,
        ], body: [
            'statementActived' => true,
        ]);

        if ($response->status() !== 200) {
            $this->throwFromResponse($response, treat422AsAuth: false);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new TecnoSpeedException(
                'A TecnoSpeed respondeu de forma inesperada. Não foi possível confirmar a ativação do Open Finance.',
                TecnoSpeedException::KIND_UNEXPECTED,
                $response->status(),
            );
        }

        $snapshot = PayerSnapshot::fromApi($payload, true);
        $statement = array_key_exists('statementActived', $payload) || array_key_exists('stamentActived', $payload)
            ? $snapshot->statementActived
            : true;

        return new PayerSnapshot(
            cpfCnpj: $snapshot->cpfCnpj !== '' ? $snapshot->cpfCnpj : $document,
            name: $snapshot->name,
            exists: true,
            statementActived: $statement,
            status: $snapshot->status,
            active: $snapshot->active,
        );
    }

    /**
     * Lista contas do pagador (GET /api/v1/account).
     *
     * @return list<AccountSnapshot>
     *
     * @throws TecnoSpeedException
     */
    public function listAccounts(string $payerCpfCnpj): array
    {
        $this->assertConfigured();
        $document = $this->requireDocument($payerCpfCnpj);

        $response = $this->request('GET', self::ACCOUNT_PATH, extraHeaders: [
            'payercpfcnpj' => $document,
        ]);

        if ($response->status() !== 200) {
            $this->throwFromResponse($response, treat422AsAuth: false, context: 'account');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new TecnoSpeedException(
                'A TecnoSpeed respondeu de forma inesperada. Não foi possível listar as contas.',
                TecnoSpeedException::KIND_UNEXPECTED,
                $response->status(),
            );
        }

        return AccountSnapshot::manyFromApi($payload);
    }

    /**
     * Consulta uma conta pelo accountHash (GET /api/v1/account/:accountHash).
     *
     * @throws TecnoSpeedException
     */
    public function getAccount(string $payerCpfCnpj, string $accountHash): AccountSnapshot
    {
        $this->assertConfigured();
        $document = $this->requireDocument($payerCpfCnpj);
        $hash = $this->requireAccountHash($accountHash);

        $response = $this->request('GET', self::ACCOUNT_PATH.'/'.rawurlencode($hash), extraHeaders: [
            'payercpfcnpj' => $document,
        ]);

        if ($response->status() !== 200) {
            $this->throwFromResponse($response, treat422AsAuth: false, context: 'account');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new TecnoSpeedException(
                'A TecnoSpeed respondeu de forma inesperada. Não foi possível consultar a conta.',
                TecnoSpeedException::KIND_UNEXPECTED,
                $response->status(),
            );
        }

        $snapshot = AccountSnapshot::fromApi($payload);
        if ($snapshot === null) {
            throw new TecnoSpeedException(
                'A TecnoSpeed respondeu de forma inesperada. Não foi possível identificar a conta consultada.',
                TecnoSpeedException::KIND_UNEXPECTED,
                $response->status(),
            );
        }

        return $snapshot;
    }

    /**
     * Cadastra conta com Extrato/Open Finance ativo (POST /api/v1/account).
     * O body oficial é um array de contas.
     *
     * @param  array<string, mixed>  $account
     *
     * @throws TecnoSpeedException
     */
    public function createAccount(string $payerCpfCnpj, array $account): AccountSnapshot
    {
        $this->assertConfigured();
        $document = $this->requireDocument($payerCpfCnpj);

        $response = $this->request('POST', self::ACCOUNT_PATH, extraHeaders: [
            'payercpfcnpj' => $document,
        ], body: [$account]);

        $status = $response->status();
        if (! in_array($status, [200, 201], true)) {
            $this->throwFromResponse($response, treat422AsAuth: false, context: 'account');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new TecnoSpeedException(
                'A TecnoSpeed respondeu de forma inesperada. Não foi possível confirmar o cadastro da conta.',
                TecnoSpeedException::KIND_UNEXPECTED,
                $status,
            );
        }

        $snapshot = AccountSnapshot::fromApi($payload);
        if ($snapshot === null) {
            throw new TecnoSpeedException(
                'A TecnoSpeed respondeu de forma inesperada. Não foi possível identificar a conta cadastrada.',
                TecnoSpeedException::KIND_UNEXPECTED,
                $status,
            );
        }

        return $snapshot;
    }

    /**
     * Atualiza conta (PUT /api/v1/account/:accountHash). Usado para statementActived=true.
     *
     * @param  array<string, mixed>  $body
     *
     * @throws TecnoSpeedException
     */
    public function updateAccount(string $payerCpfCnpj, string $accountHash, array $body): AccountSnapshot
    {
        $this->assertConfigured();
        $document = $this->requireDocument($payerCpfCnpj);
        $hash = $this->requireAccountHash($accountHash);

        $response = $this->request('PUT', self::ACCOUNT_PATH.'/'.rawurlencode($hash), extraHeaders: [
            'payercpfcnpj' => $document,
        ], body: $body);

        if ($response->status() !== 200) {
            $this->throwFromResponse($response, treat422AsAuth: false, context: 'account');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new TecnoSpeedException(
                'A TecnoSpeed respondeu de forma inesperada. Não foi possível confirmar a atualização da conta.',
                TecnoSpeedException::KIND_UNEXPECTED,
                $response->status(),
            );
        }

        $snapshot = AccountSnapshot::fromApi($payload);
        if ($snapshot === null) {
            return $this->getAccount($document, $hash);
        }

        return $snapshot;
    }

    public function baseUrl(): string
    {
        $environment = $this->environment();
        $endpoints = $this->config['endpoints'] ?? [];
        $configured = is_array($endpoints) ? ($endpoints[$environment] ?? null) : null;
        $fallback = $environment === 'production'
            ? 'https://api.pagamentobancario.com.br/api/v1'
            : 'https://staging.pagamentobancario.com.br/api/v1';

        return rtrim((string) ($configured ?: $fallback), '/');
    }

    /**
     * @param  array<string, string>  $extraHeaders
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $body
     *
     * @throws TecnoSpeedException
     */
    private function request(string $method, string $path, array $extraHeaders = [], ?array $body = null): Response
    {
        $url = $this->baseUrl().$path;

        try {
            $pending = Http::withHeaders($this->headers($extraHeaders))
                ->acceptJson()
                ->connectTimeout($this->connectTimeout())
                ->timeout($this->responseTimeout());

            return match (strtoupper($method)) {
                'GET' => $pending->get($url),
                'POST' => $pending->post($url, $body ?? []),
                'PUT' => $pending->put($url, $body ?? []),
                default => throw new TecnoSpeedException(
                    'A TecnoSpeed respondeu de forma inesperada. Não foi possível confirmar a conexão.',
                    TecnoSpeedException::KIND_UNEXPECTED,
                ),
            };
        } catch (ConnectionException $e) {
            throw $this->mapConnectionException($e);
        }
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function headers(array $extra = []): array
    {
        return array_merge([
            'cnpjsh' => $this->cnpjSh(),
            'tokensh' => $this->tokenSh(),
            'Content-Type' => 'application/json',
        ], $extra);
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new TecnoSpeedException(
                'A integração com a TecnoSpeed ainda não está configurada.',
                TecnoSpeedException::KIND_NOT_CONFIGURED,
            );
        }
    }

    private function assertSuccessfulPing(Response $response): void
    {
        $status = $response->status();

        if ($status !== 200) {
            $this->throwFromResponse($response, treat422AsAuth: true);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new TecnoSpeedException(
                'A TecnoSpeed respondeu de forma inesperada. Não foi possível confirmar a conexão.',
                TecnoSpeedException::KIND_UNEXPECTED,
                $status,
            );
        }
    }

    /**
     * @throws TecnoSpeedException
     */
    private function throwFromResponse(Response $response, bool $treat422AsAuth, string $context = 'payer'): never
    {
        $status = $response->status();

        if ($status === 401) {
            throw new TecnoSpeedException(
                'A TecnoSpeed recusou a autenticação. Verifique o CNPJ e o token da Software House.',
                TecnoSpeedException::KIND_AUTH,
                $status,
            );
        }

        if ($status === 403) {
            if (is_array($response->json())) {
                throw new TecnoSpeedException(
                    'A TecnoSpeed recusou a autenticação. Verifique o CNPJ e o token da Software House.',
                    TecnoSpeedException::KIND_AUTH,
                    $status,
                );
            }

            throw new TecnoSpeedException(
                'Não foi possível acessar a TecnoSpeed a partir deste ambiente. Verifique a liberação do IP junto ao provedor.',
                TecnoSpeedException::KIND_IP_BLOCKED,
                $status,
            );
        }

        if ($status === 422) {
            if ($treat422AsAuth) {
                throw new TecnoSpeedException(
                    'A TecnoSpeed recusou as credenciais de autenticação. Confira o CNPJ e o token da Software House.',
                    TecnoSpeedException::KIND_AUTH,
                    $status,
                );
            }

            $target = $context === 'account' ? 'da conta bancária' : 'do pagador';

            throw new TecnoSpeedException(
                "A TecnoSpeed recusou os dados {$target}. Verifique o cadastro e tente novamente.",
                TecnoSpeedException::KIND_VALIDATION,
                $status,
            );
        }

        if ($status >= 500) {
            throw new TecnoSpeedException(
                'A API da TecnoSpeed está indisponível no momento.',
                TecnoSpeedException::KIND_UNAVAILABLE,
                $status,
            );
        }

        throw new TecnoSpeedException(
            'A TecnoSpeed respondeu de forma inesperada. Não foi possível concluir a operação.',
            TecnoSpeedException::KIND_UNEXPECTED,
            $status,
        );
    }

    private function mapConnectionException(ConnectionException $e): TecnoSpeedException
    {
        $raw = strtolower($e->getMessage());
        $isTimeout = str_contains($raw, 'timed out')
            || str_contains($raw, 'timeout')
            || str_contains($raw, 'cURL error 28');

        if ($isTimeout) {
            return new TecnoSpeedException(
                'A TecnoSpeed não respondeu a tempo. Tente novamente em alguns instantes.',
                TecnoSpeedException::KIND_TIMEOUT,
            );
        }

        return new TecnoSpeedException(
            'A API da TecnoSpeed está indisponível no momento.',
            TecnoSpeedException::KIND_UNAVAILABLE,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadMatchesDocument(array $payload, string $document): bool
    {
        $candidate = CnpjNormalizer::normalize($payload['cpfCnpj'] ?? $payload['cpf_cnpj'] ?? null)
            ?? CnpjNormalizer::digits($payload['cpfCnpj'] ?? $payload['cpf_cnpj'] ?? null);

        return $candidate !== null && $candidate === $document;
    }

    /**
     * @throws TecnoSpeedException
     */
    private function findPayerInList(string $document): ?PayerSnapshot
    {
        foreach ($this->listPayers() as $snapshot) {
            if ($snapshot->cpfCnpj === $document) {
                return $snapshot;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<PayerSnapshot>
     *
     * @throws TecnoSpeedException
     */
    private function snapshotsFromListPayload(array $payload): array
    {
        if (! array_key_exists('payers', $payload)) {
            return [];
        }

        $payers = $payload['payers'];
        if (! is_array($payers)) {
            throw new TecnoSpeedException(
                'A TecnoSpeed respondeu de forma inesperada. Não foi possível listar os pagadores.',
                TecnoSpeedException::KIND_UNEXPECTED,
            );
        }

        if ($payers !== [] && ! array_is_list($payers)) {
            throw new TecnoSpeedException(
                'A TecnoSpeed respondeu de forma inesperada. Não foi possível listar os pagadores.',
                TecnoSpeedException::KIND_UNEXPECTED,
            );
        }

        $out = [];
        foreach ($payers as $item) {
            if (! is_array($item)) {
                continue;
            }
            $out[] = PayerSnapshot::fromApi($item, true);
        }

        return $out;
    }

    private function requireDocument(string $value): string
    {
        $document = $this->normalizeDocument($value);
        if ($document === '') {
            throw new TecnoSpeedException(
                'O CPF/CNPJ do pagador é inválido.',
                TecnoSpeedException::KIND_VALIDATION,
            );
        }

        return $document;
    }

    private function requireAccountHash(string $hash): string
    {
        $trimmed = trim($hash);
        if ($trimmed === '') {
            throw new TecnoSpeedException(
                'Não foi possível identificar a conta na TecnoSpeed.',
                TecnoSpeedException::KIND_VALIDATION,
            );
        }

        return $trimmed;
    }

    private function normalizeDocument(string $value): string
    {
        return CnpjNormalizer::normalize($value)
            ?? CnpjNormalizer::digits($value)
            ?? '';
    }

    private function logSuccess(): void
    {
        Log::info('[open-finance] conexão TecnoSpeed confirmada', [
            'environment' => $this->environment(),
        ]);
    }

    private function logFailure(TecnoSpeedException $e): void
    {
        Log::warning('[open-finance] falha na verificação TecnoSpeed', [
            'environment' => $this->environment(),
            'kind' => $e->kind,
            'http_status' => $e->httpStatus,
        ]);
    }

    private function cnpjSh(): string
    {
        $raw = (string) ($this->config['credentials']['cnpj_sh'] ?? '');

        return preg_replace('/\D+/', '', $raw) ?? '';
    }

    private function tokenSh(): string
    {
        return trim((string) ($this->config['credentials']['token_sh'] ?? ''));
    }

    private function connectTimeout(): int
    {
        $value = (int) ($this->config['timeout']['connect'] ?? 10);

        return max(1, $value);
    }

    private function responseTimeout(): int
    {
        $value = (int) ($this->config['timeout']['response'] ?? 20);

        return max(1, $value);
    }
}
