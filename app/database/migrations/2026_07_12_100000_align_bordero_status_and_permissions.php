<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE borderos DROP CONSTRAINT IF EXISTS borderos_status_check');
        }

        DB::table('borderos')->where('status', 'rascunho')->update(['status' => 'pendente']);
        DB::table('borderos')->where('status', 'reprovado')->update(['status' => 'pendente']);

        DB::table('payables')->where('status', 'reprovado')->update(['status' => 'pendente']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE borderos ADD CONSTRAINT borderos_status_check CHECK (status::text = ANY (ARRAY['pendente','em_preparacao','aguardando_aprovacao','aprovado','pago']::text[]))");
            DB::statement("ALTER TABLE borderos ALTER COLUMN status SET DEFAULT 'pendente'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE borderos DROP CONSTRAINT IF EXISTS borderos_status_check');
        }

        DB::table('borderos')->where('status', 'pendente')->whereNull('sent_for_approval_at')->update(['status' => 'rascunho']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE borderos ADD CONSTRAINT borderos_status_check CHECK (status::text = ANY (ARRAY['rascunho','aguardando_aprovacao','aprovado','reprovado','pago']::text[]))");
            DB::statement("ALTER TABLE borderos ALTER COLUMN status SET DEFAULT 'rascunho'");
        }
    }
};
