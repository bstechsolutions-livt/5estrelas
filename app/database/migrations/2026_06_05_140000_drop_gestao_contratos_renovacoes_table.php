<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de Renovação removido do 5 Estrelas (não se aplica sem o módulo de Compras).
 * Remove a tabela de renovações. As telas/controller/model também foram removidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gestao_contratos_renovacoes');
    }

    public function down(): void
    {
        // Sem rollback: o módulo de renovação foi descontinuado neste projeto.
    }
};
