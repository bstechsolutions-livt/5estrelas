<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Comercial ("Gestão 360º") — Spec 1: Configuração / Valores.
 * Base de parâmetros que alimenta o cálculo da planilha de custos (IN 05):
 * CCTs, categorias profissionais, escalas e índices globais.
 */
return new class extends Migration
{
    public function up(): void
    {
        // CCT — Convenção Coletiva de Trabalho
        Schema::create('bs_comercial_ccts', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('sindicato')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('ano_base')->nullable();
            $table->date('data_base')->nullable();
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fim')->nullable();
            $table->boolean('ativo')->default(true);
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->index(['uf', 'ativo']);
        });

        // Categorias profissionais (cargo/CBO) com valores base
        Schema::create('bs_comercial_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cct_id')->nullable()->constrained('bs_comercial_ccts')->nullOnDelete();
            $table->string('nome');
            $table->string('cbo')->nullable();
            $table->string('icone')->nullable();
            $table->string('cor')->nullable();
            // Remuneração
            $table->decimal('salario_base', 12, 2)->default(0);
            $table->decimal('periculosidade_pct', 6, 2)->default(0);
            $table->decimal('intrajornada_h', 6, 2)->default(1.5);
            $table->decimal('desconto_vt_pct', 6, 2)->default(6);
            // Benefícios
            $table->decimal('va', 12, 2)->default(0);          // auxílio alimentação
            $table->decimal('vt', 12, 2)->default(0);          // vale transporte (R$/dia)
            $table->decimal('plano_saude', 12, 2)->default(0);
            $table->decimal('fundo_social', 12, 2)->default(0);
            $table->decimal('sst', 12, 2)->default(0);         // saúde/segurança do trabalho
            $table->decimal('cna', 12, 2)->default(0);         // contrib. negocial/assistencial
            $table->decimal('seguro_vida', 12, 2)->default(0);
            // Insumos
            $table->decimal('uniforme', 12, 2)->default(0);
            $table->decimal('reciclagem', 12, 2)->default(0);
            $table->decimal('gta', 12, 2)->default(0);
            $table->decimal('cofre', 12, 2)->default(0);
            $table->decimal('arma', 12, 2)->default(0);
            $table->decimal('colete', 12, 2)->default(0);
            $table->boolean('tem_arma')->default(false);
            $table->boolean('tem_moto')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index('cct_id');
        });

        // Escalas (12x36, 24h, etc.) → dias/horas por mês
        Schema::create('bs_comercial_escalas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->decimal('dias_mes', 6, 2)->default(30);
            $table->decimal('horas_mes', 8, 2)->default(220);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Índices globais (encargos/adm/lucro/impostos/iss/pis/cofins) — chave/valor
        Schema::create('bs_comercial_indices', function (Blueprint $table) {
            $table->id();
            $table->string('chave')->unique();
            $table->decimal('valor', 8, 4)->default(0);
            $table->string('descricao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_comercial_indices');
        Schema::dropIfExists('bs_comercial_escalas');
        Schema::dropIfExists('bs_comercial_categorias');
        Schema::dropIfExists('bs_comercial_ccts');
    }
};
