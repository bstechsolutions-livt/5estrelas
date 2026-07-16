<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progresso ao vivo do sync CP (por empresa / fase) para o painel Sync Senior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payable_sync_runs', function (Blueprint $table) {
            $table->json('progress')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('payable_sync_runs', function (Blueprint $table) {
            $table->dropColumn('progress');
        });
    }
};
