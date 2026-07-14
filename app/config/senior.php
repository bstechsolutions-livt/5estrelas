<?php

/**
 * Configuração da integração com o Senior ERP (G5 / Gestão Empresarial).
 * Spec: senior-contas-pagar-sync.
 *
 * Por padrão DESABILITADA (enabled=false): localmente e enquanto a whitelist de IP
 * + liberação de firewall do cliente não estiverem prontas, o sync conclui sem
 * invocar a Senior e o DemoSeeder popula a massa (requirement 12).
 *
 * CONTRATO REAL validado em produção:
 *  - Modo sweep (legado): codEmp + codFor + retRat — varredura codFor 1..N.
 *  - Modo bulk (CliOpcAbr ativo, 12/07/2026): codEmp + codFil + retRat, SEM codFor.
 *  - Datas no formato dd/MM/yyyy (ISO yyyy-MM-dd é rejeitado pela Senior).
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

    // prj.contaspagar — Exportar/ConsultarGeral com UsuGer (lançador do título).
    'prj_contaspagar_service' => env(
        'SENIOR_PRJ_CONTASPAGAR_SERVICE',
        'sapiens_Synccom_senior_g5_co_prj_contaspagar'
    ),

    // Servlet SOAP do serviço de Contas a Receber (ConsultarTitulosAbertosCR).
    'cr_service' => 'sapiens_Synccom_senior_g5_co_mfi_cre_titulos',

    // Servlet SOAP do serviço de Cadastro de Filial (cad_filial / ConsultarGeral).
    'filial_service' => 'sapiens_Synccom_senior_g5_co_cad_filial',

    // Servlet SOAP do cadastro de usuários (cad_usuario / ExportarAbrangencia).
    'usuario_service' => 'sapiens_Synccom_senior_g5_co_ger_cad_usuario',

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

    // Empresas alvo (codEmp). Lista separada por vírgula (ex.: "2,3,4").
    'cod_emps' => array_values(array_filter(
        array_map('intval', explode(',', (string) env('SENIOR_COD_EMPS', '2,3'))),
        fn ($v) => $v > 0,
    )),

    // Rollout gradual: só sincroniza estas empresas quando preenchido (ex.: "2" ou "2,3").
    // Vazio = usa todas de cod_emps.
    'emp_enabled' => array_values(array_filter(
        array_map('intval', explode(',', (string) env('SENIOR_EMP_ENABLED', ''))),
        fn ($v) => $v > 0,
    )),

    // Estratégia CP: bulk (1 chamada/empresa+filial, CliOpcAbr) ou sweep (varredura codFor).
    'cp_strategy' => env('SENIOR_CP_STRATEGY', 'bulk'),

    // Filial padrão na consulta bulk (matriz = 1).
    'cod_fil' => (int) env('SENIOR_COD_FIL', 1),

    // Janela de vencimento no modo bulk (sync agendado puxa nesta faixa).
    'bulk_vct_ini' => env('SENIOR_BULK_VCT_INI', '2026-01-01'),
    'bulk_vct_fim' => env('SENIOR_BULK_VCT_FIM', '2030-12-31'),

    // Títulos com vencimento anterior a esta data não são importados nem mantidos no sync.
    'min_due_date' => env('SENIOR_MIN_DUE_DATE', '2026-01-01'),

    // Timeout maior para resposta bulk (muitos títulos em uma chamada).
    'cp_timeout_response' => (int) env('SENIOR_CP_TIMEOUT_RESPONSE', 180),

    // Compat: codEmp único (fallback usado quando cod_emps fica vazio e pelo DemoSeeder).
    'cod_emp' => (int) env('SENIOR_COD_EMP', 1),

    // Varredura de fornecedores (codFor é OBRIGATÓRIO no contrato real da Senior).
    // Para puxar TODOS os títulos, iteramos codFor nesta faixa por empresa.
    // Os fornecedores são esparsos (gaps); em produção o max cod_for em senior_suppliers
    // fica abaixo de 2000 — use SENIOR_CODFOR_END para ajustar (ex.: 2000).
    'cod_for_start' => (int) env('SENIOR_CODFOR_START', 1),
    'cod_for_end' => (int) env('SENIOR_CODFOR_END', 2000),

    // Varredura de clientes (codCli é OBRIGATÓRIO no contrato real da Senior CR).
    'cod_cli_start' => (int) env('SENIOR_CODCLI_START', 1),
    'cod_cli_end' => (int) env('SENIOR_CODCLI_END', 9999),

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
    'vct_base_date' => env('SENIOR_VCT_BASE', '2026-01-01'),

    // Janela do sync incremental em dias (requirement 5.1): default 90/90, faixa 1..3650.
    'window_days_back' => (int) env('SENIOR_WINDOW_BACK', 90),
    'window_days_forward' => (int) env('SENIOR_WINDOW_FORWARD', 90),

    // Intervalo do agendador em minutos (requirement 6): default 5, faixa 1..1440.
    'sync_interval_minutes' => (int) env('SENIOR_SYNC_INTERVAL', 5),

    // Pós-sync AbertosCP: quantos Exportar E de UsuGer rodar imediatamente nos inserts.
    'post_sync_launcher_lookups' => (int) env('SENIOR_POST_SYNC_LAUNCHER_LOOKUPS', 80),

    // Pós-sync: quantos codFor faltantes buscar no cad_fornecedor (prioriza títulos novos).
    'post_sync_supplier_lookups' => (int) env('SENIOR_POST_SYNC_SUPPLIER_LOOKUPS', 200),

    // Cron enrich UsuGer: teto de Exportar E por ciclo (após bulk ConsultarGeral).
    'enrich_launcher_max_lookups' => (int) env('SENIOR_ENRICH_LAUNCHER_MAX', 400),

    // Tamanho de lote para upsert e paginação (requirement 4.6 / 1.8).
    'batch_size' => 500,
];
