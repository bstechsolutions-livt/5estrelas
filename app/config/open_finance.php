<?php

/**
 * Integração Open Finance via API de Pagamentos da TecnoSpeed.
 *
 * Autenticação oficial (docs.pagamentobancario.com.br):
 *  - headers `cnpjsh` (CNPJ da Software House) e `tokensh`
 *  - `payercpfcnpj` nas operações de pagador/conta
 *  - Content-Type: application/json
 *
 * Pedaço 1: GET /api/v1/payer/list (handshake).
 * Pedaço 2: GET/POST/PUT /api/v1/payer + POST/GET/PUT /api/v1/account
 *           (pagador, conta, openfinanceLink, openfinanceId). Sem extrato.
 *
 * @see https://docs.pagamentobancario.com.br/
 * @see https://atendimento.tecnospeed.com.br/hc/pt-br/articles/35067267960343-Comece-por-aqui-Primeiros-passos-com-a-API-de-Extrato-via-Open-Finance-da-Tecnospeed
 * @see https://atendimento.tecnospeed.com.br/hc/pt-br/articles/35691080001047-Criar-Pagador-e-Conta-para-Extrato-Open-Finance
 */
return [
    'enabled' => (bool) env('OPEN_FINANCE_ENABLED', false),

    // Ambiente alvo da TecnoSpeed: 'staging' ou 'production'.
    'environment' => env('TECNOSPEED_ENV', 'staging'),

    'endpoints' => [
        'staging' => env('TECNOSPEED_STAGING_BASE', 'https://staging.pagamentobancario.com.br/api/v1'),
        'production' => env('TECNOSPEED_PRODUCTION_BASE', 'https://api.pagamentobancario.com.br/api/v1'),
    ],

    'credentials' => [
        'cnpj_sh' => env('TECNOSPEED_CNPJ_SH', ''),
        'token_sh' => env('TECNOSPEED_TOKEN_SH', ''),
    ],

    'timeout' => [
        'connect' => (int) env('TECNOSPEED_TIMEOUT_CONNECT', 10),
        'response' => (int) env('TECNOSPEED_TIMEOUT_RESPONSE', 20),
    ],

    // Formulário oficial da TecnoSpeed para liberação de IP (403 HTML/WAF).
    'ip_release_form_url' => 'https://docs.google.com/forms/d/e/1FAIpQLScGcLPpn6y1LLDt9pyrA1ec38V5boaUA7Lf6KBAVQt5XJw30g/viewform',
];
