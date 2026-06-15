<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comercial — Propostas. Persiste a cotação montada na tela Nova Cotação.
 * Numeração automática (ver App\Models\Comercial\Proposta::gerarNumero()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_comercial_propostas', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();      // numeração automática (ex.: "PRP-0001")
            $table->string('cliente')->nullable();
            $table->string('empresa')->nullable();    // value do select empresa (ex.: 'seg-df')
            $table->string('modelo');                 // '5estrelas' | 'in05'
            $table->string('periodicidade')->nullable();
            $table->string('cct')->nullable();
            $table->date('data_proposta')->nullable();
            $table->string('status')->default('rascunho'); // rascunho, enviada, aprovada, reprovada
            $table->decimal('total_mensal', 15, 2)->default(0);
            $table->decimal('total_anual', 15, 2)->default(0);
            $table->integer('qtd_postos')->default(0);
            $table->integer('qtd_funcionarios')->default(0);
            $table->decimal('va_total', 15, 2)->default(0);
            $table->json('postos')->nullable();          // snapshot da lista de postos do resumo
            $table->json('identificacao')->nullable();   // snapshot dos campos de identificação + modelo
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_comercial_propostas');
    }
};
