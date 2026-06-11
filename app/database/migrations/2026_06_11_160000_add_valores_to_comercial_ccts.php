<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comercial — a CCT no protótipo é (UF × serviço) e carrega o conjunto de valores
 * (salário, benefícios, insumos) daquele estado/serviço. Aqui estendemos a tabela
 * para guardar esses valores, espelhando CCT_DEFAULTS/CCT_META do protótipo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_comercial_ccts', function (Blueprint $table) {
            $table->string('servico')->nullable()->after('nome');   // vigilancia, portaria, limpeza, bombeiro
            $table->string('titulo')->nullable()->after('servico');
            $table->decimal('horas_mes', 8, 2)->default(220)->after('data_base');
            $table->decimal('dias_mes', 6, 2)->default(30)->after('horas_mes');
            // Remuneração
            $table->decimal('salario_base', 12, 2)->default(0);
            $table->decimal('periculosidade_pct', 6, 2)->default(0);
            $table->decimal('adicional_noturno_pct', 6, 2)->default(0);
            $table->decimal('intrajornada_h', 6, 2)->default(1.5);
            $table->decimal('desconto_vt_pct', 6, 2)->default(6);
            // Benefícios
            $table->decimal('va', 12, 2)->default(0);
            $table->decimal('vt', 12, 2)->default(0);
            $table->decimal('plano_saude', 12, 2)->default(0);
            $table->decimal('fundo_social', 12, 2)->default(0);
            $table->decimal('sst', 12, 2)->default(0);
            $table->decimal('cna', 12, 2)->default(0);
            $table->decimal('seguro_vida', 12, 2)->default(0);
            // Insumos
            $table->decimal('uniforme', 12, 2)->default(0);
            $table->decimal('reciclagem', 12, 2)->default(0);
            $table->decimal('gta', 12, 2)->default(0);
            $table->decimal('cofre', 12, 2)->default(0);
            $table->decimal('arma', 12, 2)->default(0);
            $table->decimal('colete', 12, 2)->default(0);
            $table->index(['uf', 'servico']);
        });
    }

    public function down(): void
    {
        Schema::table('bs_comercial_ccts', function (Blueprint $table) {
            $table->dropColumn([
                'servico', 'titulo', 'horas_mes', 'dias_mes',
                'salario_base', 'periculosidade_pct', 'adicional_noturno_pct', 'intrajornada_h', 'desconto_vt_pct',
                'va', 'vt', 'plano_saude', 'fundo_social', 'sst', 'cna', 'seguro_vida',
                'uniforme', 'reciclagem', 'gta', 'cofre', 'arma', 'colete',
            ]);
        });
    }
};
