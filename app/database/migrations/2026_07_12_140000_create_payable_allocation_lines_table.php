<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rateio manual (planilha) para conciliação bancária linha a linha.
 * Separado de payable_rateios (espelho Senior).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payable_allocation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payable_id')->constrained('payables')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_order')->nullable();
            $table->string('person_name')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('document_id', 20)->nullable();
            $table->string('role_label')->nullable();
            $table->string('pix_key')->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->decimal('unit_amount', 15, 2)->nullable();
            $table->decimal('amount', 15, 2);
            $table->foreignId('matched_bank_transaction_id')->nullable()
                ->constrained('bank_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['payable_id', 'line_order']);
        });

        Schema::table('payables', function (Blueprint $table) {
            $table->timestamp('allocation_imported_at')->nullable()->after('divergence_reason');
            $table->foreignId('allocation_imported_by')->nullable()->after('allocation_imported_at')
                ->constrained('users')->nullOnDelete();
            $table->string('allocation_source_file')->nullable()->after('allocation_imported_by');
        });
    }

    public function down(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('allocation_imported_by');
            $table->dropColumn(['allocation_imported_at', 'allocation_source_file']);
        });

        Schema::dropIfExists('payable_allocation_lines');
    }
};
