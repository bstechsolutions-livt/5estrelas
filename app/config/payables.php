<?php

/**
 * Regras de classificação de títulos por departamento (fallback legado).
 *
 * Classificação primária: senior_cod_usu do lançador → departamento do usuário.
 * Fallback quando senior_cod_usu ausente: codCcu + padrões em obsTcp.
 *
 * Chave = slug do departamento (departments.slug).
 */
return [
    /**
     * Empresas (codEmp Senior) ocultas em Contas a Pagar — não pertencem ao fluxo do grupo.
     * 4 = ARI ADM, 9 = BALUARTE, 12 = LSR
     */
    'excluded_cod_emp' => [4, 9, 12],

    'department_rules' => [
        'dp_rh' => [
            'codccu' => ['2363', '2566', '2631', '2847'],
            'description' => ['%GFD%', '%TRCT%', '%PENSÃO%', '%PENSAO%', '%VT AVULSO%', '%RESCISÃO%', '%RESCISAO%'],
        ],
        'filiais' => [
            'codccu' => ['2559', '2740', '2784', '4594', '6534', '2583'],
            'description' => ['%MANUTEN%', '%FUNDO FIXO%', '%PEDAGIO%', '%MOTOC%', '%MOTO %', '%BATERIA%'],
        ],
        'financeiro' => [
            'codccu' => ['3061', '3081', '403'],
            'description' => ['%SERASA%', '%SPC%', '%MICROSOFT%', '%LICENÇA%', '%LICENCA%', '%TAXA HOMOLOG%'],
        ],
        'juridico' => [
            'codccu' => [],
            'description' => ['%PROCESSO%', '%EXECUÇÃO%', '%EXECUCAO%', '%CONSIGNAÇÃO%', '%CONSIGNACAO%'],
        ],
        'compras' => [
            'codccu' => ['6289'],
            'description' => ['%UBER%', '%COMBUST%', '%ABASTEC%'],
        ],
    ],
];
