<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos do registro de pagamento (Spec contas-pagar-alcada-pagamento).
 * A transição aprovado -> pago passa a gravar quando, como e por quem foi pago.
 * O enum de `status` já contempla 'pago' (create_payables_tables); aqui só
 * adicionamos os metadados do pagamento. Pagamento é sempre TOTAL (= amount),
 * por isso não há coluna de valor pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->date('paid_at')->nullable()->after('approved_at'); // data do pagamento
            $table->string('payment_method')->nullable()->after('paid_at'); // forma: PIX, TED, Boleto, Dinheiro, Outro
            $table->foreignId('paid_by')->nullable()->after('payment_method')->constrained('users')->nullOnDelete(); // quem pagou
        });
    }

    public function down(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by');
            $table->dropColumn(['paid_at', 'payment_method']);
        });
    }
};
