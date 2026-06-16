<?php

/**
 * Configuração da integração com o Senior ERP (G5 / Gestão Empresarial).
 * Spec: senior-contas-pagar-sync.
 *
 * Por padrão DESABILITADA (enabled=false): localmente e enquanto a whitelist de IP
 * + liberação de firewall do cliente não estiverem prontas, o sync conclui sem
 * invocar a Senior e o DemoSeeder popula a massa (requirement 12).
 */
return [
    // Liga/desliga a sincronização real. Quando false, o Payables_Sync registra
    // uma execução "ignorado por configuração" e não toca na tabela payables.
    'enabled' => env('SENIOR_ENABLED', false),

    // Ambiente alvo: 'HML' (homologação) ou 'PRD' (produção).
    'environment' => env('SENIOR_ENV', 'HML'),

    'endpoints' => [
        'HML' => env('SENIOR_HML_BASE', 'https://webh17.seniorcloud.com.br:30661/g5-senior-services'),
        'PRD' => env('SENIOR_PRD_BASE', 'https://webp27.seniorcloud.com.br:30361/g5-senior-services'),
    ],

    // Servlet SOAP do serviço de Contas a Pagar (sapiens_Sync + nome do serviço).
    'cp_service' => 'sapiens_Synccom_senior_g5_co_mfi_cpa_titulos',

    'credentials' => [
        'user' => env('SENIOR_USER', '5estrelas.integracao'),
        'password' => env('SENIOR_PASSWORD', ''),
        // 0 = sem criptografia (padrão Senior).
        'encryption' => env('SENIOR_ENCRYPTION', '0'),
    ],

    // Empresa padrão para a consulta (codEmp). Confirmar com o cliente.
    'cod_emp' => env('SENIOR_COD_EMP', 1),

    // Timeouts (segundos) — faixa válida 5..300, default 60 (requirement 2.1).
    'timeout_connect' => (int) env('SENIOR_TIMEOUT_CONNECT', 60),
    'timeout_response' => (int) env('SENIOR_TIMEOUT_RESPONSE', 60),

    // Tentativas adicionais em erro transitório (requirement 1.10 / 2.4).
    'max_retries' => 3,

    // Janela do sync incremental em dias (requirement 5.1): default 90/90, faixa 1..3650.
    'window_days_back' => (int) env('SENIOR_WINDOW_BACK', 90),
    'window_days_forward' => (int) env('SENIOR_WINDOW_FORWARD', 90),

    // Intervalo do agendador em minutos (requirement 6): default 5, faixa 1..1440.
    'sync_interval_minutes' => (int) env('SENIOR_SYNC_INTERVAL', 5),

    // Tamanho de lote para upsert e paginação (requirement 4.6 / 1.8).
    'batch_size' => 500,
];
