<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comercial — Reajustes de contrato (esteira de reajuste anual por cliente).
 * Espelha a estrutura do protótipo Gestão 360º (SEED_REAJUSTES).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_comercial_reajustes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('origem_id')->nullable()->unique(); // _id do protótipo (idempotência)
            $table->foreignId('cliente_id')->nullable()->constrained('bs_comercial_clientes')->nullOnDelete();
            $table->string('cliente_nome');
            $table->string('empresa')->nullable();          // seg-df, apoio-go, ...
            $table->string('tipo')->default('manual');      // manual | indice
            $table->decimal('pct', 8, 2)->default(0);        // percentual de reajuste
            $table->date('data_ref')->nullable();
            $table->string('competencia')->nullable();      // ex.: 2026-01
            $table->text('obs')->nullable();
            $table->string('status')->default('pendente');  // pendente|calculado|enviado|aprovado|recusado
            $table->decimal('valor_atual', 15, 2)->default(0);
            $table->decimal('impacto_mensal', 15, 2)->default(0);
            $table->date('data_criacao')->nullable();
            $table->json('historico')->nullable();
            $table->json('itens')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('cliente_id');
            $table->index('empresa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_comercial_reajustes');
    }
};
