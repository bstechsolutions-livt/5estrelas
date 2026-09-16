<?php

namespace App\Services\OpenFinance;

use App\Models\OpenFinancePayer;
use Illuminate\Support\Facades\Log;

/**
 * Operações de domínio do pagador Open Finance.
 *
 * GET /open-finance NÃO chama ensurePayer — criação/ativação remota exige ação explícita.
 */
class PayerService
{
    public function __construct(
        private readonly EligiblePayerCatalog $catalog,
        private readonly TecnoSpeedClient $client,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForFrontend(): array
    {
        $stored = OpenFinancePayer::query()
            ->where('provider', OpenFinancePayer::PROVIDER_TECNOSPEED)
            ->get()
            ->keyBy('cpf_cnpj');

        $rows = [];
        foreach ($this->catalog->all() as $eligible) {
            $rows[] = $this->toFrontend($eligible, $stored->get($eligible->cpfCnpj));
        }

        return $rows;
    }

    public function verify(string $cpfCnpj): EnsurePayerResult
    {
        $eligible = $this->catalog->find($cpfCnpj);
        if ($eligible === null) {
            return EnsurePayerResult::failed(
                'not_eligible',
                'Este CNPJ não está entre as empresas/filiais elegíveis do sistema.',
            );
        }

        if (! $this->client->isConfigured()) {
            $this->persistStatus($eligible, null, TecnoSpeedException::KIND_NOT_CONFIGURED);

            return EnsurePayerResult::failed(
                TecnoSpeedException::KIND_NOT_CONFIGURED,
                'A integração com a TecnoSpeed ainda não está configurada.',
            );
        }

        try {
            $remote = $this->client->findPayerByDocument($eligible->cpfCnpj);
        } catch (TecnoSpeedException $e) {
            $this->logPayerFailure('verify', $e);
            $this->persistStatus($eligible, null, $e->kind);

            return EnsurePayerResult::failed($e->kind, $e->getMessage());
        }

        if ($remote === null) {
            $snapshot = PayerSnapshot::notFound($eligible->cpfCnpj, $eligible->name);
            $this->persistStatus($eligible, $snapshot, 'not_found');

            return new EnsurePayerResult(
                outcome: EnsurePayerResult::OUTCOME_VERIFIED,
                code: 'not_found',
                message: 'Pagador não encontrado na TecnoSpeed.',
                succeeded: true,
                payer: $snapshot,
            );
        }

        $code = $remote->statementActived ? 'active' : 'inactive';
        $this->persistStatus($eligible, $remote, $code);

        return new EnsurePayerResult(
            outcome: EnsurePayerResult::OUTCOME_VERIFIED,
            code: $code,
            message: $remote->statementActived
                ? 'Pagador encontrado na TecnoSpeed com Open Finance ativo.'
                : 'Pagador encontrado na TecnoSpeed, mas o Open Finance/Extrato ainda não está ativo.',
            succeeded: true,
            payer: $remote,
        );
    }

    /**
     * Caso A: não existe → POST com statementActived=true.
     * Caso B: existe e inativo → PUT statementActived=true.
     * Caso C: existe e ativo → não cria nem faz PUT.
     */
    public function ensurePayer(string $cpfCnpj): EnsurePayerResult
    {
        $eligible = $this->catalog->find($cpfCnpj);
        if ($eligible === null) {
            return EnsurePayerResult::failed(
                'not_eligible',
                'Este CNPJ não está entre as empresas/filiais elegíveis do sistema.',
            );
        }

        if (! $this->client->isConfigured()) {
            $this->persistStatus($eligible, null, TecnoSpeedException::KIND_NOT_CONFIGURED);

            return EnsurePayerResult::failed(
                TecnoSpeedException::KIND_NOT_CONFIGURED,
                'A integração com a TecnoSpeed ainda não está configurada.',
            );
        }

        try {
            $remote = $this->client->findPayerByDocument($eligible->cpfCnpj);

            if ($remote === null) {
                if (! $eligible->canRegister()) {
                    $this->persistStatus($eligible, PayerSnapshot::notFound($eligible->cpfCnpj, $eligible->name), 'missing_address');

                    return EnsurePayerResult::failed(
                        TecnoSpeedException::KIND_VALIDATION,
                        'Não foi possível cadastrar o pagador: faltam dados de endereço (bairro, cidade, UF e CEP) no cadastro da empresa/filial.',
                    );
                }

                $created = $this->client->createPayer($eligible->registration);
                $this->logPayerAction('created');
                $this->persistStatus($eligible, $created, EnsurePayerResult::OUTCOME_CREATED);

                return new EnsurePayerResult(
                    outcome: EnsurePayerResult::OUTCOME_CREATED,
                    code: EnsurePayerResult::OUTCOME_CREATED,
                    message: 'Pagador cadastrado na TecnoSpeed com Open Finance/Extrato ativo.',
                    succeeded: true,
                    payer: $created,
                );
            }

            if ($remote->statementActived) {
                $this->logPayerAction('unchanged');
                $this->persistStatus($eligible, $remote, EnsurePayerResult::OUTCOME_UNCHANGED);

                return new EnsurePayerResult(
                    outcome: EnsurePayerResult::OUTCOME_UNCHANGED,
                    code: EnsurePayerResult::OUTCOME_UNCHANGED,
                    message: 'Pagador já existe na TecnoSpeed com Open Finance ativo. Nenhuma alteração foi enviada.',
                    succeeded: true,
                    payer: $remote,
                );
            }

            $activated = $this->client->activateStatement($eligible->cpfCnpj);
            $this->logPayerAction('activated');
            $this->persistStatus($eligible, $activated, EnsurePayerResult::OUTCOME_ACTIVATED);

            return new EnsurePayerResult(
                outcome: EnsurePayerResult::OUTCOME_ACTIVATED,
                code: EnsurePayerResult::OUTCOME_ACTIVATED,
                message: 'Open Finance/Extrato ativado para o pagador já cadastrado na TecnoSpeed.',
                succeeded: true,
                payer: $activated,
            );
        } catch (TecnoSpeedException $e) {
            $this->logPayerFailure('ensure', $e);
            $this->persistStatus($eligible, null, $e->kind);

            return EnsurePayerResult::failed($e->kind, $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toFrontend(EligiblePayer $eligible, ?OpenFinancePayer $stored): array
    {
        $lastStatus = $stored?->last_status;

        return [
            'cpf_cnpj' => $eligible->cpfCnpj,
            'cpf_cnpj_masked' => DocumentDisplay::mask($eligible->cpfCnpj),
            'name' => $eligible->name,
            'company_label' => $eligible->companyLabel,
            'remote_exists' => $stored?->remote_exists,
            'statement_activated' => $stored?->statement_activated,
            'tecnospeed_state' => $this->tecnospeedState($stored),
            'tecnospeed_state_label' => $this->tecnospeedStateLabel($stored),
            'open_finance_label' => $this->openFinanceLabel($stored),
            'last_status' => $lastStatus,
            'last_status_label' => $this->lastStatusLabel($lastStatus, $stored),
            'last_checked_at' => $stored?->last_checked_at?->toIso8601String(),
            'can_register' => $eligible->canRegister(),
            'missing_address' => ! $eligible->canRegister(),
        ];
    }

    private function tecnospeedState(?OpenFinancePayer $stored): string
    {
        if ($stored === null || $stored->last_checked_at === null) {
            return 'unknown';
        }

        if ($stored->last_status === TecnoSpeedException::KIND_IP_BLOCKED) {
            return 'ip_blocked';
        }

        if ($stored->remote_exists === true) {
            return 'registered';
        }

        if ($stored->remote_exists === false) {
            return 'missing';
        }

        return 'unknown';
    }

    private function tecnospeedStateLabel(?OpenFinancePayer $stored): string
    {
        return match ($this->tecnospeedState($stored)) {
            'registered' => 'Cadastrado na TecnoSpeed',
            'missing' => 'Não cadastrado',
            'ip_blocked' => 'Bloqueio de IP',
            default => 'Não verificado',
        };
    }

    private function openFinanceLabel(?OpenFinancePayer $stored): string
    {
        if ($stored === null || $stored->statement_activated === null) {
            return '—';
        }

        return $stored->statement_activated ? 'Ativo' : 'Inativo';
    }

    private function lastStatusLabel(?string $status, ?OpenFinancePayer $stored): string
    {
        if ($stored === null || $stored->last_checked_at === null) {
            return 'Ainda não verificado';
        }

        return match ($status) {
            'created' => 'Cadastrado agora',
            'activated' => 'Open Finance ativado',
            'unchanged' => 'Já estava ativo',
            'verified', 'active' => 'Verificado — ativo',
            'inactive' => 'Verificado — inativo',
            'not_found' => 'Não encontrado na TecnoSpeed',
            'missing_address' => 'Cadastro local incompleto',
            TecnoSpeedException::KIND_IP_BLOCKED => 'Falha: IP bloqueado (WAF)',
            TecnoSpeedException::KIND_AUTH => 'Falha: autenticação recusada',
            TecnoSpeedException::KIND_TIMEOUT => 'Falha: tempo esgotado',
            TecnoSpeedException::KIND_UNAVAILABLE => 'Falha: API indisponível',
            TecnoSpeedException::KIND_VALIDATION => 'Falha: dados recusados',
            TecnoSpeedException::KIND_NOT_CONFIGURED => 'Integração não configurada',
            TecnoSpeedException::KIND_UNEXPECTED => 'Falha: resposta inesperada',
            default => 'Última verificação registrada',
        };
    }

    private function persistStatus(EligiblePayer $eligible, ?PayerSnapshot $snapshot, string $status): void
    {
        $attrs = [
            'name' => $snapshot?->name ?: $eligible->name,
            'comercial_filial_id' => $eligible->comercialFilialId,
            'branch_id' => $eligible->branchId,
            'last_status' => $status,
            'last_checked_at' => now(),
        ];

        if ($snapshot !== null) {
            $attrs['remote_exists'] = $snapshot->exists;
            $attrs['statement_activated'] = $snapshot->exists ? $snapshot->statementActived : false;
            if ($snapshot->name !== '') {
                $attrs['name'] = $snapshot->name;
            }
        }

        OpenFinancePayer::query()->updateOrCreate(
            [
                'provider' => OpenFinancePayer::PROVIDER_TECNOSPEED,
                'cpf_cnpj' => $eligible->cpfCnpj,
            ],
            $attrs,
        );
    }

    private function logPayerAction(string $action): void
    {
        Log::info('[open-finance] operação de pagador', [
            'environment' => $this->client->environment(),
            'action' => $action,
        ]);
    }

    private function logPayerFailure(string $action, TecnoSpeedException $e): void
    {
        Log::warning('[open-finance] falha na operação de pagador', [
            'environment' => $this->client->environment(),
            'action' => $action,
            'kind' => $e->kind,
            'http_status' => $e->httpStatus,
        ]);
    }
}
