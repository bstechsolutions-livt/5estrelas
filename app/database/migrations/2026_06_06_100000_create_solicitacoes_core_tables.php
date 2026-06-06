<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Núcleo do módulo de SOLICITAÇÕES (portado da intranet Biglar/Oracle).
 *
 * As tabelas legadas no Oracle eram criadas direto (sem migration Laravel),
 * portanto foram reconstruídas a partir dos Models já adaptados em
 * app/Models/Solicitacao*.php ($table, $fillable, $casts).
 *
 * Convenções de portabilidade Oracle -> PostgreSQL:
 *  - Referências a PESSOA/usuário (matrícula Oracle string) viram
 *    unsignedBigInteger nullable, SEM foreign key (acoplamento solto com users.id).
 *  - Referência a FILIAL vira unsignedBigInteger nullable (branches.id), sem FK.
 *  - Flags Oracle 'S'/'N' permanecem string(campo, 1) (o controller compara texto).
 *  - Vínculos lógicos internos do módulo recebem index (sem FK rígida).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ───────────────────────────────────────────────────────────
        // ASSUNTOS — catálogo de tipos de solicitação
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_assuntos', function (Blueprint $table) {
            $table->id();
            $table->string('departamento', 100)->nullable();          // texto legado (Oracle)
            $table->unsignedBigInteger('department_id')->nullable();   // vínculo 5E -> departments.id
            $table->string('assunto', 150);
            $table->unsignedBigInteger('responsavel')->nullable();     // matrícula -> users.id (solto)
            $table->string('prioridade', 20)->nullable();
            $table->string('ativo', 1)->default('S');                  // flag S/N
            $table->integer('qtd_min_anexos')->default(0);
            $table->text('instrucoes')->nullable();
            $table->boolean('redirect')->default(false);               // cast boolean no model
            $table->text('redirect_mensagem')->nullable();
            $table->text('redirect_mensagem_sim')->nullable();
            $table->boolean('redirect_nao')->default(false);           // cast boolean no model
            $table->text('redirect_mensagem_nao')->nullable();
            $table->string('redirect_departamento', 100)->nullable();
            $table->unsignedBigInteger('redirect_assunto_id')->nullable();
            $table->timestamps();

            $table->index('department_id');
            $table->index('ativo');
            $table->index('redirect_assunto_id');
        });

        // ───────────────────────────────────────────────────────────
        // SOLICITAÇÃO — registro principal
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 255);
            $table->text('descricao')->nullable();
            $table->string('status', 30)->default('aberto');
            $table->string('prioridade', 20)->nullable();
            $table->unsignedBigInteger('usuario_solicitante')->nullable();
            $table->unsignedBigInteger('usuario_responsavel')->nullable();
            $table->string('departamento_responsavel', 100)->nullable(); // texto legado
            $table->unsignedBigInteger('department_id')->nullable();      // vínculo 5E -> departments.id
            $table->unsignedBigInteger('filial_id')->nullable();          // -> branches.id (solto)
            $table->unsignedBigInteger('assunto_id')->nullable();
            $table->unsignedBigInteger('usuario_origem')->nullable();
            $table->dateTime('previsao_entrega')->nullable();
            $table->unsignedBigInteger('solicitacao_pai_id')->nullable();
            $table->string('hash_duplicata', 191)->nullable();
            $table->dateTime('data_conclusao')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('assunto_id');
            $table->index('filial_id');
            $table->index('department_id');
            $table->index('usuario_solicitante');
            $table->index('usuario_responsavel');
            $table->index('solicitacao_pai_id');
            $table->index('hash_duplicata');
        });

        // ───────────────────────────────────────────────────────────
        // CAMPOS — campos extras (legado) configurados por assunto
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_campos', function (Blueprint $table) {
            $table->id();
            $table->string('descricao', 255);
            $table->unsignedBigInteger('assunto_id');
            $table->string('observacao', 255)->nullable();
            $table->string('obrigatorio', 1)->default('0');  // controller compara == 0
            $table->string('tipo', 20)->nullable()->default('texto');
            $table->json('opcoes_titulo')->nullable();        // cast array
            $table->timestamps();

            $table->index('assunto_id');
        });

        // ───────────────────────────────────────────────────────────
        // MOVIMENTAÇÕES — histórico de transferências/ações
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_mov', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitacao_id');
            $table->unsignedBigInteger('usuario_origem')->nullable();
            $table->unsignedBigInteger('usuario_destino')->nullable();
            $table->string('tipo_movimentacao', 50)->nullable();
            $table->text('descricao')->nullable();
            $table->unsignedBigInteger('usuario_movimentacao')->nullable();
            $table->json('dados_extras')->nullable();          // cast array
            $table->timestamps();

            $table->index('solicitacao_id');
        });

        // ───────────────────────────────────────────────────────────
        // COMENTÁRIOS (com SoftDeletes)
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_com', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitacao_id');
            $table->unsignedBigInteger('usuario')->nullable();
            $table->text('comentario')->nullable();
            $table->string('private', 1)->default('N');        // flag S/N
            $table->timestamps();
            $table->softDeletes();

            $table->index('solicitacao_id');
        });

        // ───────────────────────────────────────────────────────────
        // ANEXOS DE COMENTÁRIO
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_com_arq', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitacao_id');
            $table->unsignedBigInteger('comentario_id');
            $table->unsignedBigInteger('arquivo_id')->nullable(); // -> files (solto)
            $table->unsignedBigInteger('usuario')->nullable();
            $table->timestamps();

            $table->index('solicitacao_id');
            $table->index('comentario_id');
            $table->index('arquivo_id');
        });

        // ───────────────────────────────────────────────────────────
        // ANEXOS DA SOLICITAÇÃO
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_arq', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitacao_id');
            $table->unsignedBigInteger('arquivo_id')->nullable(); // -> files (solto)
            $table->unsignedBigInteger('usuario')->nullable();
            $table->timestamps();

            $table->index('solicitacao_id');
            $table->index('arquivo_id');
        });

        // ───────────────────────────────────────────────────────────
        // SELEÇÃO — definição de campos dinâmicos (formulário do assunto)
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_sel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assunto_id');
            $table->string('label', 255);
            $table->string('obrigatorio', 1)->default('N');   // flag S/N
            $table->string('observacao', 255)->nullable();
            $table->string('tipo', 30)->nullable();
            $table->string('tipo_data', 30)->nullable();
            $table->integer('dias_minimos')->nullable();
            $table->string('multiplo', 1)->default('N');       // flag S/N
            $table->string('exibir_nova', 1)->default('S');    // flag S/N
            $table->string('exibir_atendimento', 1)->default('S'); // flag S/N
            $table->integer('ordem')->default(0);
            $table->unsignedBigInteger('campo_pai_id')->nullable();
            $table->string('valor_condicional', 255)->nullable();
            $table->timestamps();

            $table->index('assunto_id');
            $table->index('campo_pai_id');
        });

        // ───────────────────────────────────────────────────────────
        // SELEÇÃO - ITENS (opções de um campo de seleção)
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_s_itens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('selecao_id');
            $table->string('valor', 255)->nullable();
            $table->timestamps(); // model usa $timestamps=false mas referencia created/updated

            $table->index('selecao_id');
        });

        // ───────────────────────────────────────────────────────────
        // SELEÇÃO - RESPOSTAS (valores preenchidos por solicitação)
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_s_resp', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('itens_id')->nullable();
            $table->unsignedBigInteger('assunto_id')->nullable();
            $table->unsignedBigInteger('solicitacao_id');
            $table->unsignedBigInteger('selecao_id')->nullable();
            $table->dateTime('data1')->nullable();
            $table->dateTime('data2')->nullable();
            $table->text('texto_resposta')->nullable();
            $table->string('valor_winthor', 255)->nullable();
            $table->unsignedBigInteger('file_id')->nullable(); // -> files (solto)
            $table->timestamp('created_at')->nullable();        // model $timestamps=false (só created_at no fillable)

            $table->index('solicitacao_id');
            $table->index('selecao_id');
            $table->index('itens_id');
            $table->index('assunto_id');
        });

        // ───────────────────────────────────────────────────────────
        // EQUIPAMENTOS — catálogo simples
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_equip', function (Blueprint $table) {
            $table->id();
            $table->string('equipamento', 255);
            // model usa $timestamps = false
        });

        // ───────────────────────────────────────────────────────────
        // ASSUNTO -> RESPONSÁVEIS (permissão por assunto)
        // ───────────────────────────────────────────────────────────
        Schema::create('solicitacao_assunto_responsaveis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assunto_id');
            $table->unsignedBigInteger('matricula'); // matrícula -> users.id (solto)
            $table->timestamps();

            $table->index('assunto_id', 'idx_assunto_resp_assunto_id');
            $table->index('matricula', 'idx_assunto_resp_matricula');
            $table->unique(['assunto_id', 'matricula'], 'unique_assunto_responsavel');
        });

        // ───────────────────────────────────────────────────────────
        // ASSUNTO -> MODELOS (arquivos modelo do assunto)
        // ───────────────────────────────────────────────────────────
        Schema::create('solicitacao_assunto_modelos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitacao_assunto_id');
            $table->unsignedBigInteger('file_id')->nullable(); // -> files (solto)
            $table->timestamps();

            $table->index('solicitacao_assunto_id');
            $table->index('file_id');
        });

        // ───────────────────────────────────────────────────────────
        // ASSUNTO -> LIBERAÇÕES (regras de quem pode abrir)
        // ───────────────────────────────────────────────────────────
        Schema::create('solicitacao_assunto_liberacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assunto_id');
            $table->string('tipo', 50)->nullable();
            $table->string('valor', 255)->nullable();
            $table->timestamps();

            $table->index('assunto_id');
        });

        // ───────────────────────────────────────────────────────────
        // AGENDAMENTOS
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_agend', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mat_responsavel')->nullable();
            $table->string('rota', 255)->nullable();
            $table->dateTime('data_agendamento')->nullable();
            $table->unsignedBigInteger('filial')->nullable();   // -> branches.id (solto)
            $table->unsignedBigInteger('user_cria')->nullable();
            $table->dateTime('data_fim_agendamento')->nullable();
            $table->string('tipo_finalizacao', 50)->nullable();
            $table->dateTime('data_cancelamento')->nullable();
            $table->unsignedBigInteger('mat_cancelamento')->nullable();
            $table->unsignedBigInteger('mat_termino')->nullable();
            $table->dateTime('data_termino')->nullable();
            $table->dateTime('inicio_atendimento')->nullable();
            $table->unsignedBigInteger('mat_inicio_atendimento')->nullable();
            $table->string('status', 30)->default('agendado');
            $table->unsignedBigInteger('id_arquivo_assinatura')->nullable();
            $table->text('observacao')->nullable();
            $table->string('tipo', 50)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('mat_responsavel');
            $table->index('filial');
        });

        // ───────────────────────────────────────────────────────────
        // PIVÔ AGENDAMENTO <-> SOLICITAÇÃO
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_ag_sol', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitacao_id');
            $table->unsignedBigInteger('agendamento_id');
            // model usa $timestamps = false

            $table->index('solicitacao_id');
            $table->index('agendamento_id');
        });

        // ───────────────────────────────────────────────────────────
        // APROVAÇÕES
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_aprovacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitacao_id');
            $table->unsignedBigInteger('solicitante_matricula')->nullable();
            $table->unsignedBigInteger('aprovador_matricula')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('status', 30)->default('pendente');
            $table->text('resposta_observacoes')->nullable();
            $table->unsignedBigInteger('respondido_por')->nullable();
            $table->dateTime('respondido_em')->nullable();
            $table->timestamps();

            $table->index('solicitacao_id');
            $table->index('status');
            $table->index('aprovador_matricula');
        });

        // ───────────────────────────────────────────────────────────
        // ETAPAS (andamento configurável por assunto)
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_etapas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assunto_id');
            $table->string('nome', 100);
            $table->string('descricao', 255)->nullable();
            $table->string('cor', 20)->default('#3B82F6');
            $table->string('icone', 50)->default('pi pi-circle');
            $table->integer('ordem')->default(0);
            $table->string('ativo', 1)->default('S');
            $table->timestamps();

            $table->index(['assunto_id', 'ativo']);
        });

        // ───────────────────────────────────────────────────────────
        // ETAPA ATUAL (1 por solicitação)
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_etapa_atual', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitacao_id');
            $table->unsignedBigInteger('etapa_id');
            $table->unsignedBigInteger('usuario_alteracao')->nullable();
            $table->timestamp('data_alteracao')->useCurrent();
            $table->timestamps();

            $table->unique('solicitacao_id');
            $table->index('etapa_id');
        });

        // ───────────────────────────────────────────────────────────
        // HISTÓRICO DE ETAPAS
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_etapa_historico', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitacao_id');
            $table->unsignedBigInteger('etapa_anterior_id')->nullable();
            $table->unsignedBigInteger('etapa_nova_id')->nullable();
            $table->unsignedBigInteger('usuario_alteracao')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['solicitacao_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_solicitacao_etapa_historico');
        Schema::dropIfExists('intranet_solicitacao_etapa_atual');
        Schema::dropIfExists('intranet_solicitacao_etapas');
        Schema::dropIfExists('intranet_solicitacao_aprovacoes');
        Schema::dropIfExists('intranet_solicitacao_ag_sol');
        Schema::dropIfExists('intranet_solicitacao_agend');
        Schema::dropIfExists('solicitacao_assunto_liberacoes');
        Schema::dropIfExists('solicitacao_assunto_modelos');
        Schema::dropIfExists('solicitacao_assunto_responsaveis');
        Schema::dropIfExists('intranet_solicitacao_equip');
        Schema::dropIfExists('intranet_solicitacao_s_resp');
        Schema::dropIfExists('intranet_solicitacao_s_itens');
        Schema::dropIfExists('intranet_solicitacao_sel');
        Schema::dropIfExists('intranet_solicitacao_arq');
        Schema::dropIfExists('intranet_solicitacao_com_arq');
        Schema::dropIfExists('intranet_solicitacao_com');
        Schema::dropIfExists('intranet_solicitacao_mov');
        Schema::dropIfExists('intranet_solicitacao_campos');
        Schema::dropIfExists('intranet_solicitacao');
        Schema::dropIfExists('intranet_solicitacao_assuntos');
    }
};
