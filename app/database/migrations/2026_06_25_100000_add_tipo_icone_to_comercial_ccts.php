<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona colunas `tipo` e `icone` à tabela bs_comercial_ccts
 * para suportar serviços customizados (além dos 4 padrão).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_comercial_ccts', function (Blueprint $table) {
            if (! Schema::hasColumn('bs_comercial_ccts', 'tipo')) {
                $table->string('tipo', 10)->nullable()->after('servico'); // "seg" ou "apoio"
            }
            if (! Schema::hasColumn('bs_comercial_ccts', 'icone')) {
                $table->string('icone', 50)->nullable()->after('tipo');   // emoji ou identificador
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_comercial_ccts', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'icone']);
        });
    }
};
