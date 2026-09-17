<?php

namespace App\Services\OpenFinance;

/**
 * Resultado da operação de domínio ensurePayer / verifyPayer.
 * Sem token, credenciais ou payload bruto da TecnoSpeed.
 */
final readonly class EnsurePayerResult
{
    public const OUTCOME_CREATED = 'created';

    public const OUTCOME_ACTIVATED = 'activated';

    public const OUTCOME_UNCHANGED = 'unchanged';

    public const OUTCOME_VERIFIED = 'verified';

    public const OUTCOME_FAILED = 'failed';

    public function __construct(
        public string $outcome,
        public string $code,
        public string $message,
        public bool $succeeded,
        public ?PayerSnapshot $payer = null,
    ) {}

    public static function failed(string $code, string $message, ?PayerSnapshot $payer = null): self
    {
        return new self(self::OUTCOME_FAILED, $code, $message, false, $payer);
    }
}
