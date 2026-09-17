<?php

namespace App\Services\OpenFinance;

/**
 * Visão de uma conta TecnoSpeed para o fluxo Open Finance.
 *
 * `openfinanceLink` é operacional e sensível: não entra em toSafeArray(),
 * logs, auditoria nem exceptions.
 */
final readonly class AccountSnapshot
{
    public function __construct(
        public string $accountHash,
        public string $bankCode,
        public string $agency,
        public string $accountNumber,
        public string $accountNumberDigit,
        public bool $statementActived,
        public ?string $openfinanceLink = null,
        public ?string $openfinanceId = null,
        public ?string $agencyDigit = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(array $payload): ?self
    {
        $item = self::unwrap($payload);
        if ($item === null) {
            return null;
        }

        $hash = trim((string) ($item['accountHash'] ?? $item['account_hash'] ?? ''));
        if ($hash === '') {
            return null;
        }

        return new self(
            accountHash: $hash,
            bankCode: self::digits((string) ($item['bankCode'] ?? $item['bank_code'] ?? '')),
            agency: self::digits((string) ($item['agency'] ?? '')),
            accountNumber: self::digits((string) ($item['accountNumber'] ?? $item['account_number'] ?? '')),
            accountNumberDigit: self::digits((string) ($item['accountNumberDigit'] ?? $item['account_number_digit'] ?? '')),
            statementActived: self::boolValue(
                $item['statementActived'] ?? $item['stamentActived'] ?? false
            ),
            openfinanceLink: self::optionalString(
                $item['openfinanceLink'] ?? $item['openFinanceLink'] ?? $item['openfinance_link'] ?? null
            ),
            openfinanceId: self::optionalString(
                $item['openfinanceId'] ?? $item['openFinanceId'] ?? $item['openfinance_id'] ?? null
            ),
            agencyDigit: self::optionalString($item['agencyDigit'] ?? $item['agency_digit'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<self>
     */
    public static function manyFromApi(array $payload): array
    {
        $items = $payload['accounts'] ?? $payload;
        if (! is_array($items)) {
            $one = self::fromApi($payload);

            return $one ? [$one] : [];
        }

        if (! array_is_list($items)) {
            $one = self::fromApi(['accounts' => $items]);

            return $one ? [$one] : [];
        }

        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $snapshot = self::fromApi($item);
            if ($snapshot !== null) {
                $out[] = $snapshot;
            }
        }

        return $out;
    }

    public function matchesLocal(string $bankCode, string $agency, string $accountNumber, ?string $digit = null): bool
    {
        if ($this->bankCode !== self::digits($bankCode) || $this->agency !== self::digits($agency)) {
            return false;
        }

        $localNumber = self::digits($accountNumber);
        if ($this->accountNumber !== $localNumber) {
            return false;
        }

        $localDigit = self::digits((string) $digit);
        if ($localDigit === '' || $this->accountNumberDigit === '') {
            return true;
        }

        return $this->accountNumberDigit === $localDigit;
    }

    public function isConnected(): bool
    {
        return $this->openfinanceId !== null && $this->openfinanceId !== '';
    }

    /**
     * @return array{
     *     account_hash: string,
     *     bank_code: string,
     *     agency: string,
     *     account_number: string,
     *     account_number_digit: string,
     *     statement_activated: bool,
     *     has_openfinance_link: bool,
     *     has_openfinance_id: bool
     * }
     */
    public function toSafeArray(): array
    {
        return [
            'account_hash' => $this->accountHash,
            'bank_code' => $this->bankCode,
            'agency' => $this->agency,
            'account_number' => $this->accountNumber,
            'account_number_digit' => $this->accountNumberDigit,
            'statement_activated' => $this->statementActived,
            'has_openfinance_link' => $this->openfinanceLink !== null && $this->openfinanceLink !== '',
            'has_openfinance_id' => $this->isConnected(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private static function unwrap(array $payload): ?array
    {
        if (isset($payload['accounts']) && is_array($payload['accounts'])) {
            $accounts = $payload['accounts'];
            if (array_is_list($accounts)) {
                $first = $accounts[0] ?? null;

                return is_array($first) ? $first : null;
            }

            return $accounts;
        }

        if (isset($payload['accountHash']) || isset($payload['account_hash'])) {
            return $payload;
        }

        return null;
    }

    private static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private static function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
