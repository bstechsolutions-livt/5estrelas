<?php

/**
 * Configuração da integração com o Senior ERP (G5 / Gestão Empresarial).
 * Spec: senior-contas-pagar-sync.
 *
 * Por padrão DESABILITADA (enabled=false): localmente e enquanto a whitelist de IP
 * + liberação de firewall do cliente não estiverem prontas, o sync conclui sem
 * invocar a Senior e o DemoSeeder popula a massa (requirement 12).
 *
 * CONTRATO REAL validado em produção (22-23/06/2026):
 *  - `ConsultarTitulosAbertosCP` exige codEmp + codFor + retRat (+ janela vctIni/vctFim).
 *  - codFor é OBRIGATÓRIO; para puxar todos os títulos varremos codFor por empresa.
 *  - Datas vão no formato dd/MM/yyyy (ISO yyyy-MM-dd é rejeitado pela Senior).
 *  - Empresas operacionais com títulos confirmados: codEmp 2 e 3 (codFil=1).
 *  - tipoRetorno=1 = sucesso.
 */
return [
    // Liga/desliga a sincronização real. Quando false, o Payables_Sync registra
    // uma execução "ignorado por configuração" e não toca na tabela payables.
    'enabled' => env('SENIOR_ENABLED', false),

    // Ambiente alvo: 'HML' (homologação) ou 'PRD' (produção).
    // PRD por padrão: o HML está respondendo 503 (indisponível) do lado da Senior.
    'environment' => env('SENIOR_ENV', 'PRD'),

    'endpoints' => [
        'HML' => env('SENIOR_HML_BASE', 'https://webh17.seniorcloud.com.br:30661/g5-senior-services'),
        'PRD' => env('SENIOR_PRD_BASE', 'https://webp27.seniorcloud.com.br:30361/g5-senior-services'),
    ],

    // Servlet SOAP do serviço de Contas a Pagar (sapiens_Sync + nome do serviço).
    'cp_service' => 'sapiens_Synccom_senior_g5_co_mfi_cpa_titulos',

    // Servlet SOAP do serviço de Cadastro de Filial (cad_filial / ConsultarGeral).
    'filial_service' => 'sapiens_Synccom_senior_g5_co_cad_filial',

    // Servlet SOAP do serviço de Cadastro de Fornecedor (cad_fornecedor / ConsultarGeral).
    'fornecedor_service' => 'sapiens_Synccom_senior_g5_co_cad_fornecedor',
    'fornecedor_page_size' => (int) env('SENIOR_FORNECEDOR_PAGE_SIZE', 100),
    'fornecedor_max_pages' => (int) env('SENIOR_FORNECEDOR_MAX_PAGES', 500),
    // codFor máximo observado no cad_fornecedor (ConsultarGeral). Acima disso são
    // favorecidos de folha (GFD/TRCT) — nome vem de obsTcp, não do cadastro.
    'fornecedor_catalog_max_cod' => (int) env('SENIOR_FORNECEDOR_CATALOG_MAX_COD', 120),

    // Sigla do "Sistema Integrado" registrada no Senior (identificadorSistema),
    // exigida pelos serviços de cadastro (filial/fornecedor). Confirmada: EASYTECH.
    'identificador_sistema' => env('SENIOR_IDENTIFICADOR_SISTEMA', 'EASYTECH'),

    'credentials' => [
        'user' => env('SENIOR_USER', '5estrelas.integracao'),
        'password' => env('SENIOR_PASSWORD', ''),
        // 0 = sem criptografia (padrão Senior).
        'encryption' => env('SENIOR_ENCRYPTION', '0'),
    ],

    // Empresas operacionais a varrer (codEmp). Confirmadas com títulos em aberto: 2 e 3.
    // Lista separada por vírgula no env (ex.: "2,3,4").
    'cod_emps' => array_values(array_filter(
        array_map('intval', explode(',', (string) env('SENIOR_COD_EMPS', '2,3'))),
        fn ($v) => $v > 0,
    )),

    // Compat: codEmp único (fallback usado quando cod_emps fica vazio e pelo DemoSeeder).
    'cod_emp' => (int) env('SENIOR_COD_EMP', 1),

    // Varredura de fornecedores (codFor é OBRIGATÓRIO no contrato real da Senior).
    // Para puxar TODOS os títulos, iteramos codFor nesta faixa por empresa.
    // Os fornecedores são esparsos (gaps), por isso a faixa padrão é ampla; para uma
    // execução rápida (teste/validação) configure uma faixa pequena via env.
    'cod_for_start' => (int) env('SENIOR_CODFOR_START', 1),
    'cod_for_end' => (int) env('SENIOR_CODFOR_END', 9999),

    // Pausa (ms) entre as chamadas da varredura, para não martelar a Senior.
    // 0 = sem pausa. Útil quando a faixa de codFor é grande.
    'sweep_delay_ms' => (int) env('SENIOR_SWEEP_DELAY_MS', 0),

    // Quantas falhas de TRANSPORTE consecutivas na varredura abortam o sync inteiro.
    // Erros de negócio (fornecedor inexistente etc.) não contam — a varredura segue.
    'sweep_max_transport_failures' => (int) env('SENIOR_SWEEP_MAX_TRANSPORT_FAILURES', 3),

    // retRat: 'S' faz a Senior retornar os rateios aninhados; 'N' não. Default 'N'.
    'ret_rat' => env('SENIOR_RET_RAT', 'N'),

    // Timeouts (segundos) — faixa válida 5..300, default 60 (requirement 2.1).
    'timeout_connect' => (int) env('SENIOR_TIMEOUT_CONNECT', 60),
    'timeout_response' => (int) env('SENIOR_TIMEOUT_RESPONSE', 60),

    // Tentativas adicionais em erro transitório (requirement 1.10 / 2.4).
    'max_retries' => 3,

    // Data-base da janela de vencimento (vctIni) no sync incremental.
    // Títulos com vencimento a partir desta data entram na varredura a cada ciclo.
    'vct_base_date' => env('SENIOR_VCT_BASE', '2026-06-01'),

    // Janela do sync incremental em dias (requirement 5.1): default 90/90, faixa 1..3650.
    'window_days_back' => (int) env('SENIOR_WINDOW_BACK', 90),
    'window_days_forward' => (int) env('SENIOR_WINDOW_FORWARD', 90),

    // Intervalo do agendador em minutos (requirement 6): default 5, faixa 1..1440.
    'sync_interval_minutes' => (int) env('SENIOR_SYNC_INTERVAL', 5),

    // Tamanho de lote para upsert e paginação (requirement 4.6 / 1.8).
    'batch_size' => 500,
];
