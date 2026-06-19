<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adiciona os status 'conciliado' e 'divergente' ao CHECK constraint da
 * coluna `status` da tabela `payables` (Spec contas-pagar-conciliacao).
 *
 * O campo foi criado como enum (que no PostgreSQL vira CHECK constraint).
 * Precisamos substituir por um CHECK que inclua os novos valores.
 * Em SQLite (usado nos testes) não há CHECK constraint para enum — skip.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE payables DROP CONSTRAINT payables_status_check');
        DB::statement("ALTER TABLE payables ADD CONSTRAINT payables_status_check CHECK (status::text = ANY (ARRAY['pendente','em_preparacao','aguardando_aprovacao','aprovado','reprovado','pago','conciliado','divergente']::text[]))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE payables DROP CONSTRAINT payables_status_check');
        DB::statement("ALTER TABLE payables ADD CONSTRAINT payables_status_check CHECK (status::text = ANY (ARRAY['pendente','em_preparacao','aguardando_aprovacao','aprovado','reprovado','pago']::text[]))");
    }
};
