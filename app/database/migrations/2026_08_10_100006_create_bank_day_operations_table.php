<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_day_operations', function (Blueprint $table) {
            $table->id();
            $table->date('reference_date');
            $table->string('category', 20); // tarifa | aplicacao | resgate
            $table->string('description', 500)->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->foreignId('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
            $table->foreignId('import_id')->constrained('bank_statement_imports')->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('fitid', 100)->nullable();
            $table->string('ofx_file_name');
            $table->string('ofx_file_path');
            $table->foreignId('conciliated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('conciliated_at')->nullable();
            $table->timestamps();

            $table->unique('bank_transaction_id');
            $table->index(['reference_date', 'category']);
            $table->index('import_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_day_operations');
    }
};
