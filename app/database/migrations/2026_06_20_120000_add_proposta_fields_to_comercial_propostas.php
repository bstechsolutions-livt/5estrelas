<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comercial — Propostas: campos adicionais do "Controle de Propostas" (porte 1:1 do
 * protótipo Gestão 360º). Aditiva — não recria a tabela existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_comercial_propostas', function (Blueprint $table) {
            $table->string('revisao')->nullable()->after('numero');       // "N/A", "Rev.01"
            $table->string('servicos')->nullable()->after('cliente');      // "VIGILÂNCIA", "PORTARIA"...
            $table->string('posto')->nullable()->after('servicos');        // "VIG 24H", "Modelo 5 Estrelas"...
            $table->string('contato')->nullable()->after('posto');         // contato de envio
            $table->decimal('valor', 15, 2)->default(0)->after('contato'); // valor da proposta
            $table->decimal('valor_aprovado', 15, 2)->nullable()->after('valor');
            $table->date('data_aprovacao')->nullable()->after('valor_aprovado');
            $table->text('observacao')->nullable()->after('data_aprovacao');
            $table->string('situacao')->default('EM ANÁLISE')->after('status'); // EM ANÁLISE, APROVADO, REPROVADO, ESTIMATIVA, REDUÇÃO
            $table->boolean('da_cotacao')->default(false)->after('situacao');    // gerada pela tela de Cotação

            $table->index('situacao');
        });
    }

    public function down(): void
    {
        Schema::table('bs_comercial_propostas', function (Blueprint $table) {
            $table->dropIndex(['situacao']);
            $table->dropColumn([
                'revisao',
                'servicos',
                'posto',
                'contato',
                'valor',
                'valor_aprovado',
                'data_aprovacao',
                'observacao',
                'situacao',
                'da_cotacao',
            ]);
        });
    }
};
