<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de FLUXO / Workflow das Solicitações (portado da intranet Biglar).
 *
 * Estas tabelas já existiam como migrations Laravel/Blueprint limpas na Biglar
 * (PostgreSQL-compatíveis). Aqui são consolidadas com as colunas finais
 * inferidas dos Models (várias colunas foram adicionadas por migrations ALTER
 * ao longo do tempo: versao, etapa_andamento_id, manter_responsavel,
 * responsavel_padrao, permitir_solicitante_avancar, exibir_campos_assunto,
 * prazo_horas, instrucoes, abrir_solicitacao_assunto_id, etc).
 *
 * Permite configurar fluxos automáticos por assunto: ao concluir uma etapa,
 * a solicitação avança para a próxima conforme as decisões configuradas.
 *
 * Convenções de portabilidade: referências a pessoa (matrícula) viram
 * unsignedBigInteger nullable sem FK; flags 'S'/'N' permanecem string(1).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ───────────────────────────────────────────────────────────
        // 1. FLUXOS — workflow vinculado a um assunto
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_fluxos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assunto_id');
            $table->string('nome', 150);
            $table->text('descricao')->nullable();
            $table->string('ativo', 1)->default('S');     // flag S/N
            $table->integer('versao')->default(1);
            $table->timestamps();

            $table->index(['assunto_id', 'ativo']);
        });

        // ───────────────────────────────────────────────────────────
        // 2. ETAPAS DO FLUXO
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_fluxo_etapas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fluxo_id');
            $table->string('nome', 150);
            $table->text('descricao')->nullable();
            $table->string('departamento', 100)->nullable();      // texto legado (depto responsável)
            $table->unsignedBigInteger('assunto_id')->nullable(); // assunto do depto destino
            $table->unsignedBigInteger('etapa_andamento_id')->nullable();
            $table->string('manter_responsavel', 1)->default('N');           // flag S/N
            $table->unsignedBigInteger('responsavel_padrao')->nullable();    // matrícula -> users.id
            $table->string('permitir_responsavel_externo', 1)->default('N'); // flag S/N
            $table->string('permitir_solicitante_avancar', 1)->default('N'); // flag S/N
            $table->string('exibir_campos_assunto', 1)->default('N');        // flag S/N
            $table->integer('prazo_horas')->nullable();
            $table->text('instrucoes')->nullable();
            $table->string('cor', 20)->default('#3B82F6');
            $table->string('icone', 50)->default('pi pi-circle');
            $table->integer('ordem')->default(0);
            $table->string('ativo', 1)->default('S');     // flag S/N
            $table->string('tipo', 1)->default('E');      // E = Etapa, I = Início, F = Fim
            $table->timestamps();

            $table->index(['fluxo_id', 'ordem']);
            $table->index('assunto_id');
            $table->index('etapa_andamento_id');
        });

        // ───────────────────────────────────────────────────────────
        // 3. DECISÕES — opções de saída de cada etapa
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_fluxo_decisoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('etapa_fluxo_id');
            $table->string('label', 150);
            $table->string('cor', 20)->default('#3B82F6');
            $table->string('icone', 50)->nullable();
            $table->unsignedBigInteger('etapa_destino_id')->nullable();
            $table->string('acao', 30)->default('avancar'); // avancar, finalizar, voltar, cancelar
            $table->unsignedBigInteger('etapa_andamento_id')->nullable();
            $table->unsignedBigInteger('abrir_solicitacao_assunto_id')->nullable();
            $table->integer('ordem')->default(0);
            $table->timestamps();

            $table->index('etapa_fluxo_id');
            $table->index('etapa_destino_id');
        });

        // ───────────────────────────────────────────────────────────
        // 4. EXECUÇÃO — estado atual da solicitação dentro do fluxo
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_fluxo_execucao', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitacao_id');
            $table->unsignedBigInteger('fluxo_id');
            $table->unsignedBigInteger('etapa_atual_id');
            $table->string('status', 30)->default('em_andamento');
            $table->dateTime('prazo_inicio')->nullable();
            $table->unsignedBigInteger('solicitacao_pai_id')->nullable();
            $table->unsignedBigInteger('usuario_alteracao')->nullable();
            $table->timestamps();

            $table->unique('solicitacao_id'); // 1 fluxo ativo por solicitação
            $table->index(['fluxo_id', 'status']);
            $table->index('etapa_atual_id');
        });

        // ───────────────────────────────────────────────────────────
        // 5. HISTÓRICO — auditoria de transições no fluxo
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_solicitacao_fluxo_historico', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitacao_id');
            $table->unsignedBigInteger('fluxo_id');
            $table->unsignedBigInteger('etapa_anterior_id')->nullable();
            $table->unsignedBigInteger('etapa_nova_id')->nullable();
            $table->unsignedBigInteger('decisao_id')->nullable();
            $table->string('decisao_label', 150)->nullable();
            $table->unsignedBigInteger('usuario_alteracao')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['solicitacao_id', 'created_at']);
            $table->index('fluxo_id');
        });

        // ───────────────────────────────────────────────────────────
        // 6. CAMPOS POR ETAPA — campos preenchidos ao avançar a etapa
        //    (nome de tabela curto: intranet_sol_fluxo_etapa_campos)
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_sol_fluxo_etapa_campos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('etapa_fluxo_id');
            $table->unsignedBigInteger('decisao_id')->nullable();
            $table->string('label', 150);
            $table->string('tipo', 30)->default('texto'); // texto, textarea, numero, data, selecao, checkbox
            $table->string('placeholder', 150)->nullable();
            $table->text('opcoes')->nullable();            // cast array (JSON)
            $table->string('obrigatorio', 1)->default('N'); // flag S/N
            $table->integer('ordem')->default(0);
            $table->string('predefinido', 1)->default('N'); // flag S/N
            $table->string('campo_predefinido_key', 50)->nullable();
            $table->timestamps();

            $table->index(['etapa_fluxo_id', 'ordem'], 'idx_sol_fluxo_etapa_campos_ordem');
            $table->index('decisao_id');
        });

        // ───────────────────────────────────────────────────────────
        // 7. VALORES DOS CAMPOS — respostas preenchidas por execução
        //    (nome de tabela curto: intranet_sol_fluxo_campo_valores)
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_sol_fluxo_campo_valores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('execucao_id');
            $table->unsignedBigInteger('etapa_campo_id');
            $table->text('valor')->nullable();
            $table->unsignedBigInteger('usuario_preenchimento')->nullable();
            $table->timestamps();

            $table->unique(['execucao_id', 'etapa_campo_id'], 'uq_sol_fluxo_cv_exec_campo');
            $table->index('execucao_id', 'idx_sol_fluxo_cv_execucao');
            $table->index('etapa_campo_id');
        });

        // ───────────────────────────────────────────────────────────
        // 8. RESPONSÁVEIS DA ETAPA (quem pode atuar na etapa)
        //    (nome de tabela curto: intranet_sol_fluxo_etapa_responsaveis)
        // ───────────────────────────────────────────────────────────
        Schema::create('intranet_sol_fluxo_etapa_responsaveis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('etapa_fluxo_id');
            $table->unsignedBigInteger('matricula'); // matrícula -> users.id (solto)
            $table->timestamps();

            $table->index('etapa_fluxo_id', 'idx_sol_fluxo_etapa_resp_etapa');
            $table->index('matricula', 'idx_sol_fluxo_etapa_resp_mat');
            $table->unique(['etapa_fluxo_id', 'matricula'], 'uq_sol_fluxo_etapa_resp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_sol_fluxo_etapa_responsaveis');
        Schema::dropIfExists('intranet_sol_fluxo_campo_valores');
        Schema::dropIfExists('intranet_sol_fluxo_etapa_campos');
        Schema::dropIfExists('intranet_solicitacao_fluxo_historico');
        Schema::dropIfExists('intranet_solicitacao_fluxo_execucao');
        Schema::dropIfExists('intranet_solicitacao_fluxo_decisoes');
        Schema::dropIfExists('intranet_solicitacao_fluxo_etapas');
        Schema::dropIfExists('intranet_solicitacao_fluxos');
    }
};
