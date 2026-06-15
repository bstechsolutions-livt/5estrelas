<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comercial — Adiciona FK cliente_id na tabela de propostas para vincular propostas a clientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_comercial_propostas', function (Blueprint $table) {
            $table->foreignId('cliente_id')
                ->nullable()
                ->after('id')
                ->constrained('bs_comercial_clientes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bs_comercial_propostas', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropColumn('cliente_id');
        });
    }
};
