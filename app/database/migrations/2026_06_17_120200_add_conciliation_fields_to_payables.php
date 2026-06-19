<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos da conciliação bancária (Spec contas-pagar-conciliacao).
 * Após o pagamento, o conciliador verifica se o pagamento registrado
 * confere com o extrato bancário e marca como 'conciliado' ou 'divergente'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->date('conciliated_at')->nullable()->after('paid_by');
            $table->foreignId('conciliated_by')->nullable()->after('conciliated_at')->constrained('users')->nullOnDelete();
            $table->text('conciliation_notes')->nullable()->after('conciliated_by');
            $table->text('divergence_reason')->nullable()->after('conciliation_notes');
        });
    }

    public function down(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conciliated_by');
            $table->dropColumn(['conciliated_at', 'conciliation_notes', 'divergence_reason']);
        });
    }
};
