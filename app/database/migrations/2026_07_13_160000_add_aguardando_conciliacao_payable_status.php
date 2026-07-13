<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payables DROP CONSTRAINT IF EXISTS payables_status_check');
            DB::statement("ALTER TABLE payables ADD CONSTRAINT payables_status_check CHECK (status::text = ANY (ARRAY['pendente','em_preparacao','aguardando_aprovacao','aprovado','reprovado','pago','aguardando_conciliacao','conciliado','divergente','encerrado']::text[]))");
        }

        DB::table('payables')->where('status', 'pago')->update(['status' => 'aguardando_conciliacao']);
    }

    public function down(): void
    {
        DB::table('payables')->where('status', 'aguardando_conciliacao')->update(['status' => 'pago']);

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE payables DROP CONSTRAINT IF EXISTS payables_status_check');
        DB::statement("ALTER TABLE payables ADD CONSTRAINT payables_status_check CHECK (status::text = ANY (ARRAY['pendente','em_preparacao','aguardando_aprovacao','aprovado','reprovado','pago','conciliado','divergente']::text[]))");
    }
};
