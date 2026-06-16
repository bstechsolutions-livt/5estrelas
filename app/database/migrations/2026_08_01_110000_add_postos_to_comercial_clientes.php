<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona coluna `postos` (JSON) à tabela bs_comercial_clientes para guardar
 * a lista de postos ativos do contrato (tipo, escala, qtd, colab, valor).
 * Permite o detalhe de postos na tela Show e o reajuste por item/posto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_comercial_clientes', function (Blueprint $table) {
            $table->json('postos')->nullable()->after('total_postos');
        });
    }

    public function down(): void
    {
        Schema::table('bs_comercial_clientes', function (Blueprint $table) {
            $table->dropColumn('postos');
        });
    }
};
