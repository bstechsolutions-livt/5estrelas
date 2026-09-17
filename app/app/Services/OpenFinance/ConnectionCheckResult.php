<?php

namespace App\Services\OpenFinance;

/**
 * Resultado seguro da verificação de conexão (sem credenciais, token ou payload da API).
 */
final readonly class ConnectionCheckResult
{
    public function __construct(
        public bool $configured,
        public bool $connected,
        public string $code,
        public string $message,
        public string $environment,
    ) {}

    /**
     * @return array{
     *     configured: bool,
     *     connected: bool,
     *     code: string,
     *     message: string,
     *     environment: string,
     *     environment_label: string,
     *     show_ip_release: bool,
     *     ip_release_url: ?string
     * }
     */
    public function toFrontend(): array
    {
        $ipBlocked = $this->code === TecnoSpeedException::KIND_IP_BLOCKED;
        $formUrl = (string) config('open_finance.ip_release_form_url', '');

        return [
            'configured' => $this->configured,
            'connected' => $this->connected,
            'code' => $this->code,
            'message' => $this->message,
            'environment' => $this->environment,
            'environment_label' => $this->environment === 'production' ? 'Produção' : 'Homologação',
            'show_ip_release' => $ipBlocked && $formUrl !== '',
            'ip_release_url' => $ipBlocked && $formUrl !== '' ? $formUrl : null,
        ];
    }
}
