<?php

namespace App\Services;

use App\Models\BankAccount;

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
            $accountDigits = $account->accountDigitsForMatch();
            if ($accountDigits === '') {
                continue;
            }

            $withAgency = BankAccount::normalizeAccountDigits($account->agency).$accountDigits;
            if (
                $accountDigits === $digits
                || $withAgency === $digits
                || str_ends_with($digits, $accountDigits)
                || str_ends_with($accountDigits, $digits)
            ) {
                return $account;
            }
        }

        return null;
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
