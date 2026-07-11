<?php

/**
 * Regras de classificação de títulos por departamento (Senior → CP).
 *
 * Títulos importados não trazem department_id; usamos codCcu + padrões em obsTcp
 * até existir mapeamento oficial centro-de-custo → departamento.
 *
 * Chave = slug do departamento (departments.slug).
 */
return [
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
