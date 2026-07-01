<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback do cliente: os documentos do contas a pagar devem ficar separados por
 * tipo (Nota Fiscal, Boleto, Relatório, Comprovação). Adiciona o tipo ao anexo.
 * Nullable → anexos antigos ficam como "Outros".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payable_documents', function (Blueprint $table) {
            $table->string('doc_type')->nullable()->after('name');
            $table->index('doc_type');
        });
    }

    public function down(): void
    {
        Schema::table('payable_documents', function (Blueprint $table) {
            $table->dropIndex(['doc_type']);
            $table->dropColumn('doc_type');
        });
    }
};
