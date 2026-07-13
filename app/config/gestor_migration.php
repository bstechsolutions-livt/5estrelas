<?php

/**
 * Mapeamentos para migração do Gestor de Conciliações (Convex legado) → intranet CP.
 *
 * codEmp: derivado do CNPJ da empresa no gestor (NÃO usar enterprise.number).
 * Fonte: cruzamento CNPJ ↔ apelidos Senior (bs_comercial_filiais / F000EMP).
 */
return [
    'enterprise_cnpj_to_codemp' => [
        '72591894000142' => 2,  // 5 ESTRELAS MATRIZ / GERENCIAL
        '72591894000223' => 2,  // FILIAL GO
        '72591894000304' => 2,  // FILIAL MT
        '72591894000495' => 2,  // FILIAL MG
        '72591894000576' => 2,  // FILIAL SP
        '02830621000128' => 3,  // SERV APOIO (+ gerencial)
        '09070428000185' => 4,  // LRB
        '20764618000135' => 5,  // REFEIÇÕES
        '19541626000133' => 6,  // SRV ESPEC
        '11707071000145' => 7,  // BEST
        '07179495000107' => 8,  // SS
        '10383948000127' => 9,  // BALUARTE
        '00741759000125' => 10, // MULTI
        '02713790000188' => 11, // STAR
        '64583647000176' => 12, // STAR FIVE / LSR
    ],

    /**
     * Gestor status → status intranet CP.
     *
     * awaiting-inclusion: pago no banco, aguardando conciliação na Senior.
     * included: baixado/conciliado no Senior.
     */
    'status_map' => [
        'awaiting-rectification' => 'pendente',
        'awaiting-department-approval' => 'aguardando_aprovacao',
        'awaiting-analysis' => 'aguardando_aprovacao',
        'awaiting-reanalysis' => 'pendente',
        'awaiting-approval' => 'aguardando_aprovacao',
        'awaiting-release' => 'pendente',
        'awaiting-receipt' => 'aprovado',
        'awaiting-inclusion' => 'aguardando_conciliacao',
        'included' => 'conciliado',
        'draft' => 'pendente',
    ],

    /**
     * Status ignorados na carga do export.
     * archived: TBD — aguardando definição de negócio para intranet.
     */
    'skipped_statuses' => ['archived'],

    /**
     * Fases do fluxo de aprovação intranet para status Gestor em aguardando_aprovacao.
     * Usado por GestorWorkflowMapper::applyWorkflowPosition.
     */
    'gestor_workflow_phase' => [
        'awaiting-department-approval' => 'department',
        'awaiting-analysis' => 'analysis',
        'awaiting-approval' => 'final',
    ],

    /** Tolerância (dias) entre vencimento Gestor e Senior no fallback de match. */
    'due_date_tolerance_days' => 1,

    'convex' => [
        'deployment_url' => env('GESTOR_CONVEX_URL', 'https://energized-schnauzer-304.convex.cloud'),
        'deploy_key' => env('GESTOR_CONVEX_DEPLOY_KEY'),
        'legado_path' => env('GESTOR_LEGADO_PATH', base_path('../infra/legado')),
        'url_batch_size' => 40,
    ],
];
