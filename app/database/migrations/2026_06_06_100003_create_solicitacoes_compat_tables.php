<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Camada de COMPATIBILIDADE para o módulo de Solicitações portado da Biglar.
 * O controller faz SQL cru em tabelas legado da Biglar. Em vez de reescrever ~71
 * queries, fornecemos essas tabelas/views no nosso banco (mantendo o código idêntico).
 *
 * Atenção PostgreSQL: identificadores são case-sensitive quando citados. O controller
 * usa tanto `intranet_parametros` (minúsculo) quanto `INTRANET_PARAMETROS` (maiúsculo);
 * criamos a tabela real em minúsculo e uma VIEW em maiúsculo apontando para ela.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Parâmetros (tabela real, colunas minúsculas)
        Schema::create('intranet_parametros', function (Blueprint $table) {
            $table->id();
            $table->string('menu')->nullable();
            $table->string('submenu')->nullable();
            $table->string('parametro')->nullable();
            $table->string('valor')->nullable();
            $table->string('condicao1')->nullable();
            $table->string('condicao2')->nullable();
            $table->string('condicao3')->nullable();
            $table->timestamps();
            $table->index(['menu', 'submenu', 'parametro']);
        });

        // Arquivos (tabela real) — usada por anexos e fotos de perfil
        Schema::create('intranet_files', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('external_link')->nullable();
            $table->string('path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->boolean('disabled')->default(false);
            $table->timestamps();
        });

        // Canais de notificação
        Schema::create('INTRANET_NOTIF_CANAL', function (Blueprint $table) {
            $table->id();
            $table->string('canal')->nullable();
            $table->string('descricao')->nullable();
            $table->string('ativo', 1)->default('S');
            $table->timestamps();
        });

        // Anexos de agendamento
        Schema::create('INTRANET_AGEND_ANEXOS', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_agendamento')->nullable();
            $table->unsignedBigInteger('arquivo_id')->nullable();
            $table->string('nome')->nullable();
            $table->string('caminho')->nullable();
            $table->timestamps();
            $table->index('id_agendamento');
        });

        // Permissões de usuário (Biglar) — tabela mínima (vazia) para os joins de visibilidade não quebrarem
        Schema::create('INTRANET_USUARIO_PERMISSAO', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->unsignedBigInteger('permissao_id')->nullable();
            $table->index(['usuario_id', 'permissao_id']);
        });

        // VIEW maiúscula dos parâmetros (PG case-sensitive)
        DB::statement('CREATE VIEW "INTRANET_PARAMETROS" AS
            SELECT id AS "ID", menu AS "MENU", submenu AS "SUBMENU", parametro AS "PARAMETRO",
                   valor AS "VALOR", condicao1 AS "CONDICAO1", condicao2 AS "CONDICAO2", condicao3 AS "CONDICAO3"
            FROM intranet_parametros');

        // VIEW INTRANET_USUARIO sobre nossos users (matrícula = id; foto_perfil_id nulo por enquanto)
        DB::statement('CREATE VIEW "INTRANET_USUARIO" AS
            SELECT id AS matricula, id, name AS nome, email,
                   department_id, NULL::bigint AS foto_perfil_id, avatar_path
            FROM users');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS "INTRANET_USUARIO"');
        DB::statement('DROP VIEW IF EXISTS "INTRANET_PARAMETROS"');
        Schema::dropIfExists('INTRANET_USUARIO_PERMISSAO');
        Schema::dropIfExists('INTRANET_AGEND_ANEXOS');
        Schema::dropIfExists('INTRANET_NOTIF_CANAL');
        Schema::dropIfExists('intranet_files');
        Schema::dropIfExists('intranet_parametros');
    }
};
