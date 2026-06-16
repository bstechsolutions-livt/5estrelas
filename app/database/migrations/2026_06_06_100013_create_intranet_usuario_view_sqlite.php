<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cria a view INTRANET_USUARIO no ambiente de testes (SQLite).
 *
 * Em produção (PostgreSQL) essa view é criada pelas migrations
 * 100003/100004/100005 (fonte do model App\Models\Funcionario, usado pelo módulo
 * de Solicitações — aprovações/agendamento). Aquelas migrations pulam o SQLite por
 * causa da case-sensitivity e do cast `::bigint`. Aqui fornecemos o equivalente em
 * sintaxe SQLite, para que os testes do adaptador de Solicitações consigam resolver
 * Funcionario (matricula, nome, email, areaatuacao, situacao, foto_perfil_id) a
 * partir de `users`. Read-only; `users` continua sendo a fonte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
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
                NULL AS fone,
                department_id,
                department_id AS areaatuacao,
                department_id AS departamento,
                CASE WHEN is_active = 1 THEN 'A' ELSE 'I' END AS situacao,
                NULL AS foto_perfil_id,
                avatar_path
            FROM users");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }
        DB::statement('DROP VIEW IF EXISTS "INTRANET_USUARIO"');
    }
};
