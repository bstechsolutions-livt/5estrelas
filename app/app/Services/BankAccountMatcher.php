<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\User;
use App\Services\Ofx\OfxMeta;
use App\Support\OfficialBankAccountCatalog;

class BankAccountMatcher
{
    /**
     * Sugere conta Hub a partir dos metadados do OFX (banco + número da conta).
     */
    public function suggest(?string $bankCode, ?string $accountNumber): ?BankAccount
    {
        $code = $this->normalizeBankCode($bankCode);
        $digits = BankAccount::normalizeAccountDigits($accountNumber);

        if ($code === '' || $digits === '') {
            return null;
        }

        $candidates = BankAccount::query()
            ->active()
            ->whereNotNull('bank_code')
            ->whereNotNull('account_number')
            ->get()
            ->filter(fn (BankAccount $account) => $this->normalizeBankCode($account->bank_code) === $code);

        foreach ($candidates as $account) {
            if ($this->digitsMatch($digits, $account->accountDigitsForMatch(), $account->agency)) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Encontra conta existente ou cria a partir do OFX (catálogo oficial quando possível).
     *
     * @return array{account: BankAccount, created: bool}|null
     */
    public function resolveOrCreate(OfxMeta $meta, ?User $user = null): ?array
    {
        $existing = $this->suggest($meta->bankId, $meta->accountId);
        if ($existing !== null) {
            return ['account' => $existing, 'created' => false];
        }

        if (! filled($meta->bankId) || ! filled($meta->accountId)) {
            return null;
        }

        $catalog = $this->matchCatalog($meta->bankId, $meta->accountId);
        $attrs = $catalog !== null
            ? $this->attributesFromCatalog($catalog, $user)
            : $this->attributesFromOfx($meta, $user);

        if ($meta->balance !== null) {
            $attrs['opening_balance'] = $meta->balance;
            $attrs['opening_balance_date'] = $meta->balanceDate?->toDateString()
                ?? $meta->periodEnd?->toDateString()
                ?? $meta->periodStart?->toDateString();
        }

        $account = BankAccount::create($attrs);

        AuditLogger::log(
            event: 'financeiro.bancos.conta_criada_ofx',
            module: 'financeiro.bancos',
            description: $catalog !== null
                ? "Conta bancária criada automaticamente via OFX (catálogo): {$account->name}"
                : "Conta bancária criada automaticamente via OFX: {$account->name}",
            auditable: $account,
            newValues: [
                'bank_code' => $account->bank_code,
                'bank_name' => $account->bank_name,
                'agency' => $account->agency,
                'account_number' => $account->account_number,
                'account_digit' => $account->account_digit,
                'opening_balance' => $account->opening_balance,
                'opening_balance_date' => $account->opening_balance_date?->toDateString(),
                'from_catalog' => $catalog !== null,
                'ofx_bank_id' => $meta->bankId,
                'ofx_account_id' => $meta->accountId,
                'ofx_org' => $meta->orgName,
            ],
        );

        return ['account' => $account->fresh(), 'created' => true];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function matchCatalog(?string $bankCode, ?string $accountNumber): ?array
    {
        $code = $this->normalizeBankCode($bankCode);
        $digits = BankAccount::normalizeAccountDigits($accountNumber);

        if ($code === '' || $digits === '') {
            return null;
        }

        foreach (OfficialBankAccountCatalog::all() as $row) {
            if ($this->normalizeBankCode($row['bank_code']) !== $code) {
                continue;
            }

            $accountDigits = BankAccount::normalizeAccountDigits($row['account_number'].$row['account_digit']);
            if ($this->digitsMatch($digits, $accountDigits, $row['agency'])) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function attributesFromCatalog(array $row, ?User $user): array
    {
        return [
            'name' => trim($row['unit'].' — '.$row['bank_name']),
            'is_active' => true,
            'bank_code' => $row['bank_code'],
            'bank_name' => $row['bank_name'],
            'agency' => $row['agency'],
            'account_number' => $row['account_number'],
            'account_digit' => $row['account_digit'],
            'senior_codemp' => $row['senior_codemp'] ?? null,
            'senior_num_cco' => $row['senior_num_cco'] ?? null,
            'created_by' => $user?->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFromOfx(OfxMeta $meta, ?User $user): array
    {
        $bankCode = BankAccount::normalizeAccountDigits($meta->bankId);
        if (strlen($bankCode) < 3) {
            $bankCode = str_pad($bankCode, 3, '0', STR_PAD_LEFT);
        }

        $acctDigits = BankAccount::normalizeAccountDigits($meta->accountId);
        $branchDigits = BankAccount::normalizeAccountDigits($meta->branchId);

        $agency = $branchDigits !== '' ? $branchDigits : null;
        $accountNumber = $acctDigits;
        $accountDigit = null;

        // Se ACCTID inclui agência no início e BRANCHID vazio, tenta separar último dígito
        if ($agency === null && strlen($acctDigits) > 1) {
            $accountDigit = substr($acctDigits, -1);
            $accountNumber = substr($acctDigits, 0, -1);
        } elseif (strlen($acctDigits) > 1) {
            $accountDigit = substr($acctDigits, -1);
            $accountNumber = substr($acctDigits, 0, -1);
        }

        $bankName = $meta->orgName ?: ('Banco '.$bankCode);
        $labelAccount = $accountNumber.($accountDigit ? '-'.$accountDigit : '');
        $name = $agency
            ? "{$bankName} — ag {$agency} / {$labelAccount}"
            : "{$bankName} — {$labelAccount}";

        return [
            'name' => $name,
            'is_active' => true,
            'bank_code' => $bankCode,
            'bank_name' => $bankName,
            'agency' => $agency,
            'account_number' => $accountNumber,
            'account_digit' => $accountDigit,
            'created_by' => $user?->id,
        ];
    }

    private function digitsMatch(string $ofxDigits, string $accountDigits, ?string $agency): bool
    {
        if ($accountDigits === '') {
            return false;
        }

        $withAgency = BankAccount::normalizeAccountDigits($agency).$accountDigits;

        return $accountDigits === $ofxDigits
            || $withAgency === $ofxDigits
            || str_ends_with($ofxDigits, $accountDigits)
            || str_ends_with($accountDigits, $ofxDigits);
    }

    private function normalizeBankCode(?string $bankCode): string
    {
        $digits = BankAccount::normalizeAccountDigits($bankCode);

        if ($digits === '') {
            return '';
        }

        return ltrim($digits, '0') ?: '0';
    }
}
