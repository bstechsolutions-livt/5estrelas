<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas extras do módulo de Solicitações que dependiam de models fora do prefixo
 * Solicitacao* (BsFilialDeptoSelect, Regional, AgendamentoAnexos) + acerto de casing
 * do agend_anexos (model usa minúsculo, controller usa maiúsculo).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Recria agend_anexos com as colunas do model AgendamentoAnexos (minúsculo = real)
        Schema::dropIfExists('INTRANET_AGEND_ANEXOS');
        Schema::create('intranet_agend_anexos', function (Blueprint $table) {
            $table->id();
            $table->string('nome_arquivo')->nullable();
            $table->string('id_caminho')->nullable();
            $table->unsignedBigInteger('id_agendamento')->nullable();
            $table->string('tipo_arquivo')->nullable();
            $table->unsignedBigInteger('user_cria')->nullable();
            $table->timestamps();
            $table->index('id_agendamento');
        });
        // View maiúscula para os DB::table('INTRANET_AGEND_ANEXOS') do controller (Postgres-only)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE VIEW "INTRANET_AGEND_ANEXOS" AS SELECT * FROM intranet_agend_anexos');
        }

        // Filial x Departamento selecionados na solicitação
        Schema::create('intranet_solicitacao_filial_depto_select', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitacao_id')->nullable();
            $table->unsignedBigInteger('filial')->nullable();
            $table->unsignedBigInteger('departamento')->nullable();
            $table->timestamps();
            $table->index('solicitacao_id');
        });

        // Regionais (Biglar) — vazia (regionais não existem no 5E)
        Schema::create('bs_regionais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cod_filial')->nullable();
            $table->string('filial')->nullable();
            $table->string('nome')->nullable();
            $table->string('gerente')->nullable();
            $table->string('email')->nullable();
            $table->string('ativo', 1)->default('S');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_regionais');
        Schema::dropIfExists('intranet_solicitacao_filial_depto_select');
        DB::statement('DROP VIEW IF EXISTS "INTRANET_AGEND_ANEXOS"');
        Schema::dropIfExists('intranet_agend_anexos');
    }
};
