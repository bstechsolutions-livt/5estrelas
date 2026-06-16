<?php

namespace App\Services\Senior;

/**
 * Falha na comunicação/consulta com o Senior_CP_Service.
 * `kind` distingue timeout, indisponibilidade e erro de negócio retornado pela Senior.
 */
class SeniorException extends \RuntimeException
{
    public const KIND_TIMEOUT = 'timeout';
    public const KIND_UNAVAILABLE = 'unavailable';
    public const KIND_BUSINESS = 'business';

    public function __construct(
        string $message,
        public readonly string $kind = self::KIND_UNAVAILABLE,
    ) {
        parent::__construct($message);
    }

    public function isTransient(): bool
    {
        return in_array($this->kind, [self::KIND_TIMEOUT, self::KIND_UNAVAILABLE], true);
    }
}
