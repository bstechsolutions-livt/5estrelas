<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comercial — Filiais/Empresas do grupo, ESPELHADAS da Senior ERP.
 *
 * A fonte da verdade é a Senior (serviço cad_filial / mapa de empresas codEmp).
 * Esta tabela é populada pelo sync `senior:sync-filiais` (e, enquanto o web
 * service de cad_filial não está liberado no Senior, semeada com o mapa de
 * empresas já confirmado — F000EMP). NÃO é um cadastro manual inventado.
 *
 * Identidade Senior = (cod_emp, cod_fil); senior_id = "codEmp-codFil".
 * Os campos tipo/tag são apenas apresentação local (badge/classificação).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_comercial_filiais', function (Blueprint $table) {
            $table->id();
            $table->integer('cod_emp')->nullable();          // código da empresa na Senior
            $table->integer('cod_fil')->nullable()->default(1); // código da filial na Senior
            $table->string('senior_id')->nullable()->unique(); // "codEmp-codFil" (business key)
            $table->string('nome');                            // razão social (Senior nomFil/nenFil)
            $table->string('fantasia')->nullable();            // nome fantasia
            $table->string('tag')->nullable();                 // sigla curta (badge — apresentação)
            $table->string('tipo')->nullable();                // seguranca | apoio (classificação local)
            $table->string('uf', 2)->nullable();               // Senior sigUfs
            $table->string('cnpj')->nullable();                // Senior numCgc
            $table->boolean('ativo')->default(true);
            $table->integer('ordem')->default(0);
            $table->json('senior_raw')->nullable();            // payload bruto da Senior
            $table->timestamp('senior_synced_at')->nullable(); // última sincronização
            $table->timestamps();

            $table->index('cod_emp');
            $table->index('tipo');
            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_comercial_filiais');
    }
};
