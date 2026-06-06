<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enriquece a view INTRANET_USUARIO para servir de fonte ao model `Funcionario`,
 * expondo as colunas que o controller de Solicitações consulta com nomes da Biglar
 * (matricula, nome, areaatuacao, departamento, situacao). Mapeia tudo de `users`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS "INTRANET_USUARIO"');
        DB::statement("CREATE VIEW \"INTRANET_USUARIO\" AS
            SELECT
                id,
                id AS matricula,
                name AS nome,
                name,
                email,
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
        DB::statement('DROP VIEW IF EXISTS "INTRANET_USUARIO"');
        DB::statement('CREATE VIEW "INTRANET_USUARIO" AS
            SELECT id AS matricula, id, name AS nome, email,
                   department_id, NULL::bigint AS foto_perfil_id, avatar_path
            FROM users');
    }
};
