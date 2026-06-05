<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas do módulo de Gestão de Contratos (portado da intranet Biglar).
 * Reconstruído para PostgreSQL a partir dos models (a base original era Oracle/legado).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tipos de índice (IGPM, IPCA, etc.)
        Schema::create('bs_gestao_tipos_indice', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Tipos de alvará
        Schema::create('bs_gestao_tipos_alvara', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->integer('dias_alerta_1')->nullable();
            $table->integer('dias_alerta_2')->nullable();
            $table->integer('dias_alerta_3')->nullable();
            $table->integer('dias_alerta_4')->nullable();
            $table->timestamps();
        });

        // Contratos (locação / serviço)
        Schema::create('bs_gestao_contratos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo')->default('LOCACAO'); // LOCACAO | SERVICO
            $table->unsignedBigInteger('filial_id')->nullable();
            $table->string('razao_social_loja')->nullable();
            $table->string('cnpj_loja')->nullable();
            $table->string('contrato_em_nome_de')->nullable();
            $table->string('tipo_pessoa')->nullable();
            $table->string('nome_locador')->nullable();
            $table->string('documento_locador')->nullable();
            $table->string('email_locador')->nullable();
            $table->string('telefone_locador')->nullable();
            $table->string('imobiliaria')->nullable();
            $table->string('telefone_imobiliaria')->nullable();
            $table->text('endereco_imovel')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado')->nullable();
            $table->string('cep')->nullable();
            $table->string('banco')->nullable();
            $table->string('agencia')->nullable();
            $table->string('conta')->nullable();
            $table->string('tipo_conta')->nullable();
            $table->string('pix')->nullable();
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->boolean('renovacao_automatica')->default(false);
            $table->boolean('pagamento_antecipado')->default(false);
            $table->integer('dia_vencimento')->nullable();
            $table->decimal('valor_mensal', 15, 2)->nullable();
            $table->decimal('valor_condominio', 15, 2)->nullable();
            $table->decimal('valor_iptu', 15, 2)->nullable();
            $table->unsignedBigInteger('tipo_indice_id')->nullable();
            $table->date('data_proximo_reajuste')->nullable();
            $table->decimal('percentual_reajuste_fixo', 10, 4)->nullable();
            $table->string('tipo_servico')->nullable();
            $table->text('descricao_servico')->nullable();
            $table->string('numero_contrato')->nullable();
            $table->string('negociador')->nullable();
            $table->string('responsavel_interno')->nullable();
            $table->boolean('retencao_irrf')->default(false);
            $table->decimal('percentual_irrf', 8, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->string('status')->default('ATIVO');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            // Vínculo Compras / recorrência (Compras ainda não existe no 5E)
            $table->unsignedBigInteger('id_solicitacao_compras')->nullable();
            $table->decimal('provisao_mensal', 15, 2)->nullable();
            $table->decimal('percentual_divergencia', 8, 2)->nullable();
            $table->integer('dia_envio_nf')->nullable();
            $table->integer('dias_alerta_antes')->nullable();
            // Campos adicionais
            $table->json('locadores_adicionais')->nullable();
            $table->date('periodo_apuracao_inicio')->nullable();
            $table->date('periodo_apuracao_fim')->nullable();
            $table->json('iptu_inscricoes')->nullable();
            $table->json('indices_adicionais')->nullable();
            $table->decimal('valor_proposto_locador', 15, 2)->nullable();
            // Melhorias locação
            $table->boolean('iptu_pago_carne')->default(false);
            $table->integer('dia_apuracao')->nullable();
            $table->integer('dia_apuracao_fim')->nullable();
            $table->decimal('valor_anterior', 15, 2)->nullable();
            $table->boolean('tem_condominio')->default(false);
            $table->integer('prazo_contrato_meses')->nullable();
            $table->integer('mes_base_reajuste')->nullable();
            $table->date('data_vencimento_reajuste')->nullable();
            $table->json('historico_anual')->nullable();
            $table->boolean('reajuste_fixo_contrato')->default(false);
            $table->decimal('valor_reajuste_fixo', 15, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tipo');
            $table->index('status');
            $table->index('filial_id');
            $table->index('data_fim');
        });

        // Reajustes do contrato
        Schema::create('bs_gestao_contratos_reajustes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contrato_id');
            $table->date('data_reajuste')->nullable();
            $table->decimal('valor_anterior', 15, 2)->nullable();
            $table->decimal('valor_reajustado', 15, 2)->nullable();
            $table->decimal('valor_proposto', 15, 2)->nullable();
            $table->decimal('percentual_aplicado', 10, 4)->nullable();
            $table->string('indice_utilizado')->nullable();
            $table->decimal('valor_indice', 10, 4)->nullable();
            $table->string('mes_base_indice')->nullable();
            $table->decimal('valor_negociado', 15, 2)->nullable();
            $table->decimal('reducao_obtida', 15, 2)->nullable();
            $table->string('negociador')->nullable();
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('contrato_id');
        });

        // Anexos do contrato
        Schema::create('bs_gestao_contratos_anexos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contrato_id');
            $table->string('tipo')->nullable();
            $table->string('nome_arquivo');
            $table->string('caminho');
            $table->unsignedBigInteger('tamanho')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('descricao')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('contrato_id');
        });

        // Alvarás
        Schema::create('bs_gestao_alvaras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('filial_id')->nullable();
            $table->unsignedBigInteger('tipo_alvara_id')->nullable();
            $table->string('numero_documento')->nullable();
            $table->text('descricao')->nullable();
            $table->string('orgao_emissor')->nullable();
            $table->date('data_emissao')->nullable();
            $table->date('data_validade')->nullable();
            $table->string('status')->default('VIGENTE');
            $table->string('responsavel_renovacao')->nullable();
            $table->string('responsavel_email')->nullable();
            $table->string('responsavel_telefone')->nullable();
            $table->decimal('custo_renovacao', 15, 2)->nullable();
            $table->text('requisitos_renovacao')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('arquivo_path')->nullable();
            $table->string('arquivo_nome')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('filial_id');
            $table->index('status');
            $table->index('data_validade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_gestao_alvaras');
        Schema::dropIfExists('bs_gestao_contratos_anexos');
        Schema::dropIfExists('bs_gestao_contratos_reajustes');
        Schema::dropIfExists('bs_gestao_contratos');
        Schema::dropIfExists('bs_gestao_tipos_alvara');
        Schema::dropIfExists('bs_gestao_tipos_indice');
    }
};
