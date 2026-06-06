<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payloads especializados (C-*) do módulo de SOLICITAÇÕES.
 *
 * Tabelas legadas Oracle sem migration original, reconstruídas a partir dos
 * Models App\Models\SolicitacaoC{Rot,Acessos,Dest,Equip,Vendas} e dos blocos
 * ::create([...]) no SolicitacoesController.
 *
 * Esses models NÃO têm chave primária ($primaryKey = null, $incrementing = false)
 * e NÃO usam timestamps ($timestamps = false), portanto as tabelas não recebem
 * $table->id() nem $table->timestamps().
 *
 * Convenções de portabilidade Oracle -> PostgreSQL (iguais às demais migrations
 * do módulo): matrículas/filiais/códigos viram unsignedBigInteger nullable sem
 * FK rígida; textos viram string/text; valores monetários decimal; datas date.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ───────────────────────────────────────────────────────────
        // C_ROT — rotinas (origem Winthor) solicitadas
        // create([... 'solicitacao_id', 'rotina' ...])
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_c_rot', function (Blueprint $table) {
            $table->unsignedBigInteger('solicitacao_id')->nullable();
            $table->string('rotina', 100)->nullable();

            $table->index('solicitacao_id');
        });

        // ───────────────────────────────────────────────────────────
        // C_ACESSOS — liberações de acesso (Filiais/Moedas/Departamentos/
        // Bancos/Centros de Custo): tipo + codigo
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_c_acessos', function (Blueprint $table) {
            $table->unsignedBigInteger('solicitacao_id')->nullable();
            $table->string('tipo', 100)->nullable();
            $table->string('codigo', 100)->nullable();

            $table->index('solicitacao_id');
        });

        // ───────────────────────────────────────────────────────────
        // C_DEST — usuários de destino (matrícula -> users.id)
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_c_dest', function (Blueprint $table) {
            $table->unsignedBigInteger('solicitacao_id')->nullable();
            $table->unsignedBigInteger('matricula')->nullable();   // users.id (solto)

            $table->index('solicitacao_id');
        });

        // ───────────────────────────────────────────────────────────
        // C_EQUIP — equipamentos: equipamento, operacao, quantidade, observacao
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_c_equip', function (Blueprint $table) {
            $table->unsignedBigInteger('solicitacao_id')->nullable();
            $table->string('equipamento', 255)->nullable();
            $table->string('operacao', 50)->nullable();
            $table->integer('quantidade')->nullable();
            $table->text('observacao')->nullable();

            $table->index('solicitacao_id');
        });

        // ───────────────────────────────────────────────────────────
        // C_VENDAS — info de vendas: filial, caixas (json), valor, data, operador
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_c_vendas', function (Blueprint $table) {
            $table->unsignedBigInteger('solicitacao_id')->nullable();
            $table->unsignedBigInteger('filial')->nullable();      // branches.id (solto)
            $table->text('caixas')->nullable();                    // json_encode([...])
            $table->decimal('valor', 15, 2)->nullable();
            $table->date('data')->nullable();
            $table->unsignedBigInteger('operador')->nullable();    // matrícula -> users.id (solto)

            $table->index('solicitacao_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_solicitacao_c_vendas');
        Schema::dropIfExists('intranet_solicitacao_c_equip');
        Schema::dropIfExists('intranet_solicitacao_c_dest');
        Schema::dropIfExists('intranet_solicitacao_c_acessos');
        Schema::dropIfExists('intranet_solicitacao_c_rot');
    }
};
