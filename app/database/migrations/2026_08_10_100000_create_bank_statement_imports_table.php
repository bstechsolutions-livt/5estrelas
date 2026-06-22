<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name')->nullable();
            $table->string('bank_id', 10)->nullable();
            $table->string('account_number', 50);
            $table->string('branch_number', 20)->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('balance', 15, 2)->nullable();
            $table->string('status', 20)->default('processing');
            $table->unsignedInteger('transaction_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_imports');
    }
};
