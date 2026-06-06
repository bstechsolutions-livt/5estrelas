<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recria a view "INTRANET_PARAMETROS" expondo as colunas em AMBAS as grafias
 * (MAIÚSCULA e minúscula).
 *
 * Motivo: o código portado da Biglar (herança Oracle, case-insensitive) mistura
 * as grafias na MESMA query — ex.: ->where('MENU', ...) (maiúsculo) combinado com
 * ->select('condicao1')/->pluck('condicao1') (minúsculo). No PostgreSQL os nomes
 * citados são case-sensitive, então a view precisa oferecer as duas versões para
 * não quebrar (e, pior, envenenar a transação corrente — um erro de coluna deixa
 * a transação em estado "aborted" e faz o COMMIT seguinte virar rollback).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS "INTRANET_PARAMETROS"');
        DB::statement('CREATE VIEW "INTRANET_PARAMETROS" AS
            SELECT
                id,                 id        AS "ID",
                menu,               menu      AS "MENU",
                submenu,            submenu   AS "SUBMENU",
                parametro,          parametro AS "PARAMETRO",
                valor,              valor     AS "VALOR",
                condicao1,          condicao1 AS "CONDICAO1",
                condicao2,          condicao2 AS "CONDICAO2",
                condicao3,          condicao3 AS "CONDICAO3"
            FROM intranet_parametros');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS "INTRANET_PARAMETROS"');
        DB::statement('CREATE VIEW "INTRANET_PARAMETROS" AS
            SELECT id AS "ID", menu AS "MENU", submenu AS "SUBMENU", parametro AS "PARAMETRO",
                   valor AS "VALOR", condicao1 AS "CONDICAO1", condicao2 AS "CONDICAO2", condicao3 AS "CONDICAO3"
            FROM intranet_parametros');
    }
};
