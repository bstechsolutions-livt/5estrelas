<?php

namespace App\Services\OpenFinance;

/**
 * Falha na comunicação/autenticação com a API TecnoSpeed.
 * `kind` distingue configuração, autenticação, timeout, bloqueio de IP,
 * indisponibilidade, validação e resposta inesperada.
 *
 * A mensagem nunca deve conter tokensh, token do pagador, openfinanceLink,
 * payload bruto ou CNPJ da SH.
 */
class TecnoSpeedException extends \RuntimeException
{
    public const KIND_NOT_CONFIGURED = 'not_configured';

    public const KIND_AUTH = 'auth';

    public const KIND_TIMEOUT = 'timeout';

    public const KIND_UNAVAILABLE = 'unavailable';

    public const KIND_IP_BLOCKED = 'ip_blocked';

    public const KIND_UNEXPECTED = 'unexpected';

    public const KIND_VALIDATION = 'validation';

    public const KIND_UNRESOLVED_DUPLICATE = 'unresolved_duplicate';

    public function __construct(
        string $message,
        public readonly string $kind = self::KIND_UNAVAILABLE,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }
}
