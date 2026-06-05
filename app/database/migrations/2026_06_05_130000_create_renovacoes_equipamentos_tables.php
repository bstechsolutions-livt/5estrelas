<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas de Renovação de contratos + Controle de Equipamentos
 * (portado da intranet Biglar, reconstruído para PostgreSQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Renovações de contrato ──
        Schema::create('gestao_contratos_renovacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contrato_id');
            $table->date('data_renovacao');
            $table->date('nova_data_inicio');
            $table->date('nova_data_fim');
            $table->decimal('valor_anterior', 15, 2)->default(0);
            $table->decimal('valor_novo', 15, 2)->default(0);
            $table->decimal('percentual_variacao', 8, 2)->default(0);
            $table->decimal('percentual_divergencia_limite', 8, 2)->default(0);
            $table->boolean('dentro_divergencia')->default(true);
            $table->unsignedBigInteger('id_solicitacao_compras_nova')->nullable();
            $table->string('status', 30)->default('APROVADA'); // APROVADA, PENDENTE_COMPRAS, REJEITADA
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('contrato_id');
            $table->index('status');
        });

        // ── Tipos de equipamento ──
        Schema::create('bs_gestao_tipos_equipamento', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('descricao', 500)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('ativo');
        });

        // ── Equipamentos ──
        Schema::create('bs_gestao_equipamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('filial_id');
            $table->unsignedBigInteger('tipo_equipamento_id');
            $table->string('numero_identificacao')->nullable();
            $table->string('carga')->nullable();
            $table->decimal('peso_kg', 10, 2)->nullable();
            $table->integer('qtd_projeto')->nullable();
            $table->string('localizacao')->nullable();
            $table->date('data_validade');
            $table->string('status', 20)->default('VIGENTE');
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('filial_id');
            $table->index('tipo_equipamento_id');
            $table->index('status');
            $table->index('data_validade');
        });

        // ── Fotos (polimórfico: equipamento ou ocorrência) ──
        Schema::create('bs_gestao_equipamento_fotos', function (Blueprint $table) {
            $table->id();
            $table->string('fotoable_type');
            $table->unsignedBigInteger('fotoable_id');
            $table->string('arquivo_path');
            $table->string('arquivo_nome')->nullable();
            $table->unsignedBigInteger('arquivo_tamanho')->nullable();
            $table->string('arquivo_mime')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['fotoable_type', 'fotoable_id']);
        });

        // ── Ocorrências ──
        Schema::create('bs_gestao_equipamento_ocorrencias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipamento_id');
            $table->string('tipo_ocorrencia')->nullable();
            $table->string('tipo_ocorrencia_descricao')->nullable();
            $table->text('descricao')->nullable();
            $table->date('data_ocorrencia')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('equipamento_id');
        });

        // ── Tratativas ──
        Schema::create('bs_gestao_equipamento_tratativas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipamento_id');
            $table->text('descricao');
            $table->date('data_registro')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_nome')->nullable();
            $table->timestamps();

            $table->index('equipamento_id');
        });

        // ── Histórico de validade (alterações de data_validade do equipamento) ──
        Schema::create('bs_gestao_equipamento_hist_validade', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipamento_id');
            $table->date('data_validade_anterior')->nullable();
            $table->date('data_validade_nova')->nullable();
            $table->string('status_anterior', 20)->nullable();
            $table->string('status_novo', 20)->nullable();
            $table->unsignedBigInteger('alterado_por')->nullable();
            $table->timestamp('alterado_em')->nullable();

            $table->index('equipamento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_gestao_equipamento_hist_validade');
        Schema::dropIfExists('bs_gestao_equipamento_tratativas');
        Schema::dropIfExists('bs_gestao_equipamento_ocorrencias');
        Schema::dropIfExists('bs_gestao_equipamento_fotos');
        Schema::dropIfExists('bs_gestao_equipamentos');
        Schema::dropIfExists('bs_gestao_tipos_equipamento');
        Schema::dropIfExists('gestao_contratos_renovacoes');
    }
};
