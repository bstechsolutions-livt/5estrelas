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

    'status_map' => [
        'awaiting-analysis' => 'em_preparacao',
        'awaiting-reanalysis' => 'em_preparacao',
        'awaiting-receipt' => 'em_preparacao',
        'awaiting-approval' => 'aguardando_aprovacao',
        'awaiting-inclusion' => 'aprovado',
        'awaiting-rectification' => 'pendente',
        'awaiting-release' => 'pendente',
        'draft' => 'pendente',
    ],

    'skipped_statuses' => ['included'],

    'convex' => [
        'deployment_url' => env('GESTOR_CONVEX_URL', 'https://energized-schnauzer-304.convex.cloud'),
        'deploy_key' => env('GESTOR_CONVEX_DEPLOY_KEY'),
        'legado_path' => env('GESTOR_LEGADO_PATH', base_path('../infra/legado')),
        'url_batch_size' => 40,
    ],
];
