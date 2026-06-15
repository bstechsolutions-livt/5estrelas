<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Acrescenta `fone` (e mantém os demais) à view INTRANET_USUARIO — as relações
 * Funcionario do módulo de Solicitações fazem ->select(['matricula','nome','email','areaatuacao','fone']).
 * users não tem telefone, então fone vem nulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('DROP VIEW IF EXISTS "INTRANET_USUARIO"');
        DB::statement("CREATE VIEW \"INTRANET_USUARIO\" AS
            SELECT
                id,
                id AS matricula,
                name AS nome,
                name,
                email,
                NULL::text AS fone,
                department_id,
                department_id AS areaatuacao,
                department_id AS departamento,
                CASE WHEN is_active THEN 'A' ELSE 'I' END AS situacao,
                NULL::bigint AS foto_perfil_id,
                avatar_path
            FROM users");
    }

    public function down(): void
    {
        // mantém a view (sem rollback específico)
    }
};
