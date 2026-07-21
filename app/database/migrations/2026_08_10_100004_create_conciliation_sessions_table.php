<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 20)->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bank_account_id', 'year', 'month'], 'conciliation_sessions_account_period_unique');
        });

        Schema::table('bank_statement_imports', function (Blueprint $table) {
            $table->foreignId('conciliation_session_id')
                ->nullable()
                ->after('bank_account_id')
                ->constrained('conciliation_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conciliation_session_id');
        });

        Schema::dropIfExists('conciliation_sessions');
    }
};
