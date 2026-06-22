<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('bank_statement_imports')->cascadeOnDelete();
            $table->string('fitid', 100)->nullable();
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->string('type', 10);
            $table->string('description', 500)->nullable();
            $table->string('memo', 500)->nullable();
            $table->string('check_number', 50)->nullable();
            $table->foreignId('matched_payable_id')->nullable()->constrained('payables')->nullOnDelete();
            $table->string('match_status', 20)->default('pending');
            $table->string('match_confidence', 10)->default('none');
            $table->jsonb('raw_data')->nullable();
            $table->timestamps();

            $table->index(['import_id', 'match_status']);
            $table->index('matched_payable_id');
            $table->index(['import_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
