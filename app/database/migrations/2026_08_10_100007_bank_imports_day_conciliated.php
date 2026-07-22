<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_statement_imports', function (Blueprint $table) {
            $table->string('retained_path')->nullable()->after('file_path');
            $table->timestamp('day_conciliated_at')->nullable()->after('matched_count');
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_imports', function (Blueprint $table) {
            $table->dropColumn(['retained_path', 'day_conciliated_at']);
        });
    }
};
