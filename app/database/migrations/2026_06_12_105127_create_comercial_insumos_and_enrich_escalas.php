<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comercial — Config/Valores: aba "Insumos" (global) + enriquecimento das Escalas
 * (qtd diurno/noturno, func por posto, adicional noturno, jornada) conforme o protótipo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_comercial_insumos', function (Blueprint $table) {
            $table->id();
            $table->string('chave')->unique();   // uniforme, epi, colete, reciclag, ...
            $table->string('label');
            $table->decimal('valor', 12, 2)->default(0);
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });

        Schema::table('bs_comercial_escalas', function (Blueprint $table) {
            $table->integer('qtd_diurno')->default(0)->after('horas_mes');
            $table->integer('qtd_noturno')->default(0)->after('qtd_diurno');
            $table->integer('func_por_posto')->default(1)->after('qtd_noturno');
            $table->boolean('tem_an')->default(false)->after('func_por_posto');
            $table->string('jornada')->nullable()->after('tem_an');
        });
    }

    public function down(): void
    {
        Schema::table('bs_comercial_escalas', function (Blueprint $table) {
            $table->dropColumn(['qtd_diurno', 'qtd_noturno', 'func_por_posto', 'tem_an', 'jornada']);
        });
        Schema::dropIfExists('bs_comercial_insumos');
    }
};
