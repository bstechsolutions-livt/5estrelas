<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comercial — Saúde Contratual. Lançamentos mensais por cliente/contrato e metas.
 * Porte do protótipo Gestão 360º (view-saude).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Lançamentos mensais (evolução mensal): 1 por mês por cliente.
        Schema::create('bs_comercial_saude_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('bs_comercial_clientes')->cascadeOnDelete();
            $table->string('mes_ref', 7); // ex: 2026-01
            $table->decimal('faturamento_real', 15, 2)->default(0);
            $table->decimal('custo_folha', 15, 2)->default(0);
            $table->decimal('custo_beneficios', 15, 2)->default(0);
            $table->decimal('custo_insumos', 15, 2)->default(0);
            $table->decimal('inadimplencia', 15, 2)->default(0);
            $table->text('obs')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cliente_id', 'mes_ref']);
            $table->index('mes_ref');
        });

        // Metas por cliente (configuração da saúde contratual).
        Schema::create('bs_comercial_saude_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->unique()->constrained('bs_comercial_clientes')->cascadeOnDelete();
            $table->decimal('margem_minima', 5, 2)->default(2.5);     // %
            $table->decimal('margem_alvo', 5, 2)->default(3.0);        // %
            $table->decimal('max_folha_pct', 5, 2)->default(75.0);     // % folha/faturamento
            $table->decimal('inadimplencia_max', 15, 2)->default(0);   // R$
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_comercial_saude_metas');
        Schema::dropIfExists('bs_comercial_saude_lancamentos');
    }
};
