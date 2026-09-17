<?php

namespace App\Services\OpenFinance;

use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\OpenFinanceAccountConnection;
use App\Models\OpenFinancePayer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Orquestra pagador + conta TecnoSpeed a partir de uma BankAccount local.
 *
 * Idempotente: consulta vínculo local, depois o provedor, e só cria quando
 * não houver recurso correspondente. Não trata "já existe" como sucesso
 * sem identificar o recurso.
 */
class AccountConnectionService
{
    public function __construct(
        private readonly TecnoSpeedClient $client,
        private readonly BranchResolver $branches,
        private readonly PayerProfileBuilder $profiles,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForFrontend(string $environment): array
    {
        $accounts = BankAccount::query()
            ->orderBy('name')
            ->get();

        $connections = OpenFinanceAccountConnection::query()
            ->with('payer.branch')
            ->where('environment', $environment)
            ->whereIn('bank_account_id', $accounts->pluck('id'))
            ->get()
            ->keyBy('bank_account_id');

        return $accounts->map(function (BankAccount $account) use ($connections, $environment) {
            /** @var OpenFinanceAccountConnection|null $connection */
            $connection = $connections->get($account->id);

            return $this->presentAccount($account, $connection, $environment);
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(BankAccount $account, string $environment): array
    {
        $resolution = $this->branches->resolve($account);
        $connection = $this->localConnection($account, $environment);
        $suggestedId = $connection?->payer?->branch_id ?? $resolution['suggested_branch_id'];
        $branch = $suggestedId
            ? Branch::query()->find($suggestedId)
            : null;
        $payer = $branch
            ? $this->localPayer($branch, $environment) ?? $connection?->payer
            : $connection?->payer;

        $profile = $branch
            ? $this->profiles->build($branch, $payer)
            : [
                'name' => '',
                'cpf_cnpj' => '',
                'cpf_cnpj_masked' => DocumentDisplay::mask(null),
                'email' => '',
                'zipcode' => '',
                'street' => '',
                'address_number' => '',
                'address_complement' => '',
                'neighborhood' => '',
                'city' => '',
                'state' => '',
                'missing' => ['name', 'cpf_cnpj', 'neighborhood', 'city', 'state', 'zipcode'],
                'complete' => false,
            ];

        return [
            'bank_account' => $this->presentBankAccount($account),
            'suggested_branch_id' => $suggestedId,
            'match' => $resolution['match'],
            'requires_explicit_branch' => $resolution['match'] !== 'exact' && $resolution['match'] !== 'unique_company',
            'branches' => $resolution['candidates'],
            'payer' => $profile,
            'connection' => $connection ? $this->presentConnection($connection) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws TecnoSpeedException
     */
    public function prepare(BankAccount $account, Branch $branch, array $input, string $environment): OpenFinanceAccountConnection
    {
        $registration = $this->profiles->toRegistration($input + [
            'name' => $input['name'] ?? $branch->name,
            'cpf_cnpj' => $input['cpf_cnpj'] ?? $branch->cnpj,
        ]);

        if (! $registration->isComplete()) {
            $connection = $this->persistPending($account, $branch, $registration, $environment);
            $connection->status = OpenFinanceAccountConnection::STATUS_PENDING_DATA;
            $connection->last_error_kind = TecnoSpeedException::KIND_VALIDATION;
            $connection->last_error_message = 'Preencha os dados obrigatórios do pagador (razão social, CNPJ, bairro, cidade, UF e CEP) antes de falar com a TecnoSpeed.';
            $connection->save();

            throw new TecnoSpeedException(
                $connection->last_error_message,
                TecnoSpeedException::KIND_VALIDATION,
            );
        }

        $this->assertConnectionReady();

        // Não mantém transação aberta durante HTTP à TecnoSpeed (lock longo + rollback
        // apagaria o status de erro). A unicidade (bank_account_id, environment) impede duplicata local.
        $connection = $this->lockLocalConnection($account, $environment);

        if ($connection->status === OpenFinanceAccountConnection::STATUS_CONNECTED && filled($connection->openfinance_id)) {
            return $connection;
        }

        try {
            $payer = $this->ensurePayer($branch, $registration, $environment);
            $connection->open_finance_payer_id = $payer->id;
            $connection->status = OpenFinanceAccountConnection::STATUS_PAYER_READY;
            $connection->last_error_kind = null;
            $connection->last_error_message = null;
            $connection->save();

            $accountSnapshot = $this->ensureRemoteAccount($account, $payer);
            $this->applyAccountSnapshot($connection, $accountSnapshot);
            $connection->save();

            $this->logOperation('conta preparada', $connection);

            return $connection->refresh();
        } catch (TecnoSpeedException $e) {
            $connection->status = OpenFinanceAccountConnection::STATUS_ERROR;
            $connection->last_error_kind = $e->kind;
            $connection->last_error_message = $e->getMessage();
            $connection->save();
            $this->logOperation('falha ao preparar conta', $connection, $e);
            throw $e;
        }
    }

    /**
     * @throws TecnoSpeedException
     */
    public function authorizationUrl(OpenFinanceAccountConnection $connection): string
    {
        if (! $connection->hasAuthorizationLink()) {
            throw new TecnoSpeedException(
                'Esta conta ainda não tem um link de autorização. Conclua a preparação da conexão.',
                TecnoSpeedException::KIND_VALIDATION,
            );
        }

        return (string) $connection->openfinance_link;
    }

    /**
     * Consulta o conector oficial. Sem openfinanceId permanece aguardando — não é erro fatal.
     *
     * @throws TecnoSpeedException
     */
    public function verify(OpenFinanceAccountConnection $connection): OpenFinanceAccountConnection
    {
        $this->assertConnectionReady();

        if ($connection->provider_account_hash === null || $connection->provider_account_hash === '') {
            throw new TecnoSpeedException(
                'A conta ainda não foi preparada na TecnoSpeed.',
                TecnoSpeedException::KIND_VALIDATION,
            );
        }

        $payer = $connection->payer;
        if ($payer === null) {
            throw new TecnoSpeedException(
                'Não há pagador vinculado a esta conta.',
                TecnoSpeedException::KIND_VALIDATION,
            );
        }

        $alreadyConnected = $connection->isConnected();

        try {
            $snapshot = $this->client->getAccount($payer->cpf_cnpj, $connection->provider_account_hash);
        } catch (TecnoSpeedException $e) {
            $connection->last_error_kind = $e->kind;
            $connection->last_error_message = $e->getMessage();
            $connection->status = OpenFinanceAccountConnection::STATUS_ERROR;
            $connection->save();
            $this->logOperation('falha ao verificar conexão', $connection, $e);
            throw $e;
        }

        $this->applyAccountSnapshot($connection, $snapshot);
        $connection->last_error_kind = null;
        $connection->last_error_message = null;
        $connection->save();

        if ($alreadyConnected && $connection->isConnected()) {
            $this->logOperation('verificação idempotente', $connection);
        } else {
            $this->logOperation(
                $connection->isConnected() ? 'conector confirmado' : 'autorização ainda pendente',
                $connection,
            );
        }

        return $connection->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentAccount(BankAccount $account, ?OpenFinanceAccountConnection $connection, string $environment): array
    {
        $status = $connection?->status ?? OpenFinanceAccountConnection::STATUS_NOT_CONFIGURED;
        $payer = $connection?->payer;

        return [
            'id' => $account->id,
            'connection_id' => $connection?->id,
            'name' => $account->name,
            'bank_code' => $account->bank_code,
            'bank_name' => $account->bank_name,
            'agency' => $account->agency,
            'account_masked' => DocumentDisplay::maskAccount($account->account_number, $account->account_digit),
            'is_active' => $account->is_active,
            'empresa' => $payer?->name ?? $payer?->branch?->resolveDisplayName(),
            'status' => $status,
            'status_label' => OpenFinanceAccountConnection::statusLabel($status),
            'statement_activated' => (bool) ($connection?->statement_activated),
            'has_authorization_link' => (bool) $connection?->hasAuthorizationLink(),
            'authorized_at' => $connection?->authorized_at?->toIso8601String(),
            'last_error_message' => $connection?->status === OpenFinanceAccountConnection::STATUS_ERROR
                ? $connection->last_error_message
                : null,
            'environment' => $environment,
            'actions' => $this->actionsFor($status, (bool) $connection?->hasAuthorizationLink()),
        ];
    }

    /**
     * @throws TecnoSpeedException
     */
    private function assertConnectionReady(): void
    {
        $status = $this->client->checkConnection();
        if ($status->connected) {
            return;
        }

        throw new TecnoSpeedException(
            $status->message,
            $status->code,
        );
    }

    private function ensurePayer(Branch $branch, PayerRegistrationData $registration, string $environment): OpenFinancePayer
    {
        $payer = $this->localPayer($branch, $environment)
            ?? OpenFinancePayer::query()
                ->where('environment', $environment)
                ->where('cpf_cnpj', $registration->cpfCnpj)
                ->first();

        if ($payer === null) {
            $payer = new OpenFinancePayer([
                'environment' => $environment,
                'provider' => OpenFinancePayer::PROVIDER_TECNOSPEED,
            ]);
        }

        $payer->fill([
            'branch_id' => $branch->id,
            'cpf_cnpj' => $registration->cpfCnpj,
            'name' => $registration->name,
            'email' => $registration->email,
            'zipcode' => $registration->zipcode,
            'street' => $registration->street,
            'address_number' => $registration->addressNumber,
            'address_complement' => $registration->addressComplement,
            'neighborhood' => $registration->neighborhood,
            'city' => $registration->city,
            'state' => $registration->state,
        ]);
        $payer->save();

        $remote = $this->client->findPayerByDocument($registration->cpfCnpj);

        if ($remote === null) {
            try {
                $remote = $this->client->createPayer($registration);
            } catch (TecnoSpeedException $e) {
                if ($e->kind !== TecnoSpeedException::KIND_VALIDATION) {
                    throw $e;
                }

                $remote = $this->client->findPayerByDocument($registration->cpfCnpj);
                if ($remote === null) {
                    throw new TecnoSpeedException(
                        'A TecnoSpeed recusou o cadastro do pagador e não foi possível identificar um pagador existente com este CNPJ. Nenhuma duplicidade foi assumida.',
                        TecnoSpeedException::KIND_UNRESOLVED_DUPLICATE,
                        $e->httpStatus,
                    );
                }
            }
        }

        if (! $remote->statementActived) {
            $remote = $this->client->activateStatement($registration->cpfCnpj);
        }

        $payer->statement_activated = $remote->statementActived;
        $payer->provider_status = $remote->exists ? 'registered' : 'missing';
        $payer->provider_synced_at = now();
        $payer->save();

        $this->logOperation('pagador sincronizado', null);

        return $payer;
    }

    /**
     * @throws TecnoSpeedException
     */
    private function ensureRemoteAccount(BankAccount $account, OpenFinancePayer $payer): AccountSnapshot
    {
        $payload = $this->accountPayload($account);

        $listed = $this->client->listAccounts($payer->cpf_cnpj);
        $matches = array_values(array_filter(
            $listed,
            fn (AccountSnapshot $item) => $item->matchesLocal(
                $payload['bankCode'],
                $payload['agency'],
                $payload['accountNumber'],
                $payload['accountNumberDigit'] ?? null,
            ),
        ));

        if (count($matches) > 1) {
            throw new TecnoSpeedException(
                'Existe mais de uma conta na TecnoSpeed com o mesmo banco, agência e número. A reconciliação automática foi bloqueada para não vincular a conta errada.',
                TecnoSpeedException::KIND_UNRESOLVED_DUPLICATE,
            );
        }

        if (count($matches) === 1) {
            $snapshot = $matches[0];
            if (! $snapshot->statementActived) {
                $snapshot = $this->client->updateAccount($payer->cpf_cnpj, $snapshot->accountHash, [
                    'statementActived' => true,
                ]);
            }

            return $this->refreshAccount($payer->cpf_cnpj, $snapshot);
        }

        try {
            $created = $this->client->createAccount($payer->cpf_cnpj, $payload);
        } catch (TecnoSpeedException $e) {
            if ($e->kind !== TecnoSpeedException::KIND_VALIDATION) {
                throw $e;
            }

            $listed = $this->client->listAccounts($payer->cpf_cnpj);
            $matches = array_values(array_filter(
                $listed,
                fn (AccountSnapshot $item) => $item->matchesLocal(
                    $payload['bankCode'],
                    $payload['agency'],
                    $payload['accountNumber'],
                    $payload['accountNumberDigit'] ?? null,
                ),
            ));

            if (count($matches) !== 1) {
                throw new TecnoSpeedException(
                    'A TecnoSpeed recusou o cadastro da conta e não foi possível identificar com segurança uma conta existente correspondente. Nenhuma duplicidade foi assumida.',
                    TecnoSpeedException::KIND_UNRESOLVED_DUPLICATE,
                    $e->httpStatus,
                );
            }

            $created = $matches[0];
        }

        return $this->refreshAccount($payer->cpf_cnpj, $created);
    }

    private function refreshAccount(string $document, AccountSnapshot $snapshot): AccountSnapshot
    {
        try {
            return $this->client->getAccount($document, $snapshot->accountHash);
        } catch (TecnoSpeedException) {
            return $snapshot;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function accountPayload(BankAccount $account): array
    {
        $bankCode = str_pad(substr(preg_replace('/\D+/', '', (string) $account->bank_code) ?? '', 0, 3), 3, '0', STR_PAD_LEFT);
        $agency = preg_replace('/\D+/', '', (string) $account->agency) ?? '';
        $number = preg_replace('/\D+/', '', (string) $account->account_number) ?? '';
        $digit = preg_replace('/\D+/', '', (string) $account->account_digit) ?? '';

        if ($bankCode === '' || $agency === '' || $number === '') {
            throw new TecnoSpeedException(
                'A conta bancária local precisa de banco, agência e número para ser enviada à TecnoSpeed.',
                TecnoSpeedException::KIND_VALIDATION,
            );
        }

        $payload = [
            'bankCode' => $bankCode,
            'agency' => mb_substr($agency, 0, 10),
            'agencyDigit' => '',
            'accountNumber' => mb_substr($number, 0, 12),
            'accountNumberDigit' => mb_substr($digit, 0, 2),
            'accountDac' => mb_substr($digit, 0, 2),
            'statementActived' => true,
        ];

        return $payload;
    }

    private function applyAccountSnapshot(OpenFinanceAccountConnection $connection, AccountSnapshot $snapshot): void
    {
        $connection->provider_account_hash = $snapshot->accountHash;
        $connection->statement_activated = $snapshot->statementActived;
        $connection->provider_synced_at = now();

        if ($snapshot->openfinanceLink) {
            $connection->openfinance_link = $snapshot->openfinanceLink;
        }

        if ($snapshot->isConnected()) {
            $connection->openfinance_id = $snapshot->openfinanceId;
            $connection->status = OpenFinanceAccountConnection::STATUS_CONNECTED;
            $connection->authorized_at = $connection->authorized_at ?? now();

            return;
        }

        if ($connection->hasAuthorizationLink() || $snapshot->openfinanceLink) {
            $connection->status = OpenFinanceAccountConnection::STATUS_AWAITING_AUTHORIZATION;

            return;
        }

        $connection->status = OpenFinanceAccountConnection::STATUS_ACCOUNT_READY;
    }

    private function persistPending(
        BankAccount $account,
        Branch $branch,
        PayerRegistrationData $registration,
        string $environment,
    ): OpenFinanceAccountConnection {
        $payer = $this->localPayer($branch, $environment)
            ?? new OpenFinancePayer([
                'environment' => $environment,
                'provider' => OpenFinancePayer::PROVIDER_TECNOSPEED,
            ]);

        $payer->fill([
            'branch_id' => $branch->id,
            'cpf_cnpj' => $registration->cpfCnpj,
            'name' => $registration->name,
            'email' => $registration->email,
            'zipcode' => $registration->zipcode,
            'street' => $registration->street,
            'address_number' => $registration->addressNumber,
            'address_complement' => $registration->addressComplement,
            'neighborhood' => $registration->neighborhood,
            'city' => $registration->city,
            'state' => $registration->state,
        ]);

        if ($registration->cpfCnpj !== '') {
            $payer->save();
        }

        $connection = $this->localConnection($account, $environment) ?? new OpenFinanceAccountConnection([
            'bank_account_id' => $account->id,
            'environment' => $environment,
            'status' => OpenFinanceAccountConnection::STATUS_PENDING_DATA,
        ]);

        if ($payer->exists) {
            $connection->open_finance_payer_id = $payer->id;
        }
        $connection->save();

        return $connection;
    }

    private function lockLocalConnection(BankAccount $account, string $environment): OpenFinanceAccountConnection
    {
        try {
            return OpenFinanceAccountConnection::query()->firstOrCreate(
                [
                    'bank_account_id' => $account->id,
                    'environment' => $environment,
                ],
                [
                    'status' => OpenFinanceAccountConnection::STATUS_PENDING_DATA,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            return OpenFinanceAccountConnection::query()
                ->where('bank_account_id', $account->id)
                ->where('environment', $environment)
                ->firstOrFail();
        }
    }

    private function localConnection(BankAccount $account, string $environment): ?OpenFinanceAccountConnection
    {
        return OpenFinanceAccountConnection::query()
            ->with('payer.branch')
            ->where('bank_account_id', $account->id)
            ->where('environment', $environment)
            ->first();
    }

    private function localPayer(Branch $branch, string $environment): ?OpenFinancePayer
    {
        $document = \App\Support\CnpjNormalizer::cnpj14($branch->cnpj);
        $query = OpenFinancePayer::query()->where('environment', $environment);

        if ($document) {
            $byDocument = (clone $query)->where('cpf_cnpj', $document)->first();
            if ($byDocument) {
                return $byDocument;
            }
        }

        return $query->where('branch_id', $branch->id)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentBankAccount(BankAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'bank_code' => $account->bank_code,
            'bank_name' => $account->bank_name,
            'agency' => $account->agency,
            'account_masked' => DocumentDisplay::maskAccount($account->account_number, $account->account_digit),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentConnection(OpenFinanceAccountConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'status' => $connection->status,
            'status_label' => OpenFinanceAccountConnection::statusLabel($connection->status),
            'has_authorization_link' => $connection->hasAuthorizationLink(),
        ];
    }

    /**
     * @return list<string>
     */
    private function actionsFor(string $status, bool $hasLink): array
    {
        return match ($status) {
            OpenFinanceAccountConnection::STATUS_NOT_CONFIGURED,
            OpenFinanceAccountConnection::STATUS_PENDING_DATA,
            OpenFinanceAccountConnection::STATUS_ERROR => ['configure'],
            OpenFinanceAccountConnection::STATUS_PAYER_READY,
            OpenFinanceAccountConnection::STATUS_ACCOUNT_READY => ['continue'],
            OpenFinanceAccountConnection::STATUS_AWAITING_AUTHORIZATION => $hasLink
                ? ['authorize', 'verify']
                : ['continue', 'verify'],
            OpenFinanceAccountConnection::STATUS_CONNECTED => ['verify'],
            default => ['configure'],
        };
    }

    private function logOperation(string $action, ?OpenFinanceAccountConnection $connection, ?TecnoSpeedException $error = null): void
    {
        $context = [
            'environment' => $this->client->environment(),
            'action' => $action,
        ];

        if ($connection !== null) {
            $context['connection_id'] = $connection->id;
            $context['status'] = $connection->status;
            $context['has_openfinance_id'] = filled($connection->openfinance_id);
        }

        if ($error !== null) {
            $context['kind'] = $error->kind;
            $context['http_status'] = $error->httpStatus;
            Log::warning('[open-finance] '.$action, $context);

            return;
        }

        Log::info('[open-finance] '.$action, $context);
    }
}
