<?php

namespace App\Services;

use App\Models\Solicitacao;
use App\Models\SolicitacaoAssunto;
use App\Models\SolicitacaoFluxoDecisao;
use App\Models\SolicitacaoFluxoEtapa;
use App\Models\SolicitacaoFluxoEtapaCampo;
use App\Models\SolicitacaoFluxoEtapaCampoValor;
use App\Models\SolicitacaoFluxoExecucao;
use App\Models\SolicitacaoFluxoHistorico;
use App\Models\SolicitacaoMov;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Motor de execução do Workflow de Solicitações.
 *
 * Responsável por:
 * - Iniciar o fluxo quando uma solicitação é criada (se o assunto tem fluxo ativo)
 * - Avançar para a próxima etapa (sem decisão = próxima na ordem)
 * - Processar decisões (com decisão = vai para etapa definida na decisão)
 * - Transferir automaticamente a solicitação entre departamentos
 * - Registrar histórico de cada transição
 */
class WorkflowService
{
    protected SolicitacaoReverbService $reverbService;

    public function __construct(SolicitacaoReverbService $reverbService)
    {
        $this->reverbService = $reverbService;
    }

    // ─── INICIAR FLUXO ────────────────────────────────────────────

    /**
     * Verifica se o assunto tem fluxo ativo e inicia a execução.
     * Chamado automaticamente ao criar uma solicitação.
     *
     * @return SolicitacaoFluxoExecucao|null Retorna a execução ou null se não há fluxo
     */
    public function iniciarFluxo(Solicitacao $solicitacao, int $matriculaUsuario): ?SolicitacaoFluxoExecucao
    {
        $assunto = SolicitacaoAssunto::with('fluxoAtivo.etapasAtivas')->find($solicitacao->assunto_id);

        if (! $assunto || ! $assunto->fluxoAtivo) {
            return null;
        }

        $fluxo = $assunto->fluxoAtivo;
        $primeiraEtapa = $fluxo->primeiraEtapa();

        if (! $primeiraEtapa) {
            Log::warning('Workflow: Fluxo sem etapas ativas', [
                'fluxo_id' => $fluxo->id,
                'assunto_id' => $assunto->id,
            ]);

            return null;
        }

        return DB::transaction(function () use ($solicitacao, $fluxo, $primeiraEtapa, $matriculaUsuario) {
            // Determinar status inicial baseado no modo da etapa (exclusivo do solicitante / decisões / em andamento)
            $statusInicial = $this->statusParaEtapa($primeiraEtapa);

            // Criar registro de execução
            $execucao = SolicitacaoFluxoExecucao::create([
                'solicitacao_id' => $solicitacao->id,
                'fluxo_id' => $fluxo->id,
                'etapa_atual_id' => $primeiraEtapa->id,
                'status' => $statusInicial,
                'prazo_inicio' => $primeiraEtapa->prazo_horas ? Carbon::now() : null,
                'usuario_alteracao' => $matriculaUsuario,
            ]);

            // Registrar histórico de início
            SolicitacaoFluxoHistorico::create([
                'solicitacao_id' => $solicitacao->id,
                'fluxo_id' => $fluxo->id,
                'etapa_anterior_id' => null,
                'etapa_nova_id' => $primeiraEtapa->id,
                'usuario_alteracao' => $matriculaUsuario,
                'observacao' => 'Fluxo iniciado automaticamente',
            ]);

            // Atualizar assunto se a etapa tem assunto específico
            if ($primeiraEtapa->assunto_id && $primeiraEtapa->assunto_id !== $solicitacao->assunto_id) {
                $solicitacao->assunto_id = $primeiraEtapa->assunto_id;
                $solicitacao->save();
            }

            // Transferir departamento para o da primeira etapa (se diferente)
            if ($primeiraEtapa->departamento && $primeiraEtapa->departamento !== $solicitacao->departamento_responsavel) {
                $departamentoAnterior = $solicitacao->departamento_responsavel;
                $solicitacao->departamento_responsavel = $primeiraEtapa->departamento;
                $solicitacao->usuario_responsavel = null;
                $solicitacao->save();
            }

            // Auto-atribuir responsável padrão da primeira etapa (se configurado)
            // Exceto quando a etapa é do Modo Exclusivo ('E'): a etapa é do solicitante,
            // portanto usuario_responsavel deve ficar nulo.
            if ($primeiraEtapa->responsavel_padrao && $primeiraEtapa->permitir_solicitante_avancar !== 'E') {
                $solicitacao->usuario_responsavel = $primeiraEtapa->responsavel_padrao;
                $solicitacao->save();
            } elseif ($primeiraEtapa->permitir_solicitante_avancar === 'E') {
                $solicitacao->usuario_responsavel = null;
                $solicitacao->save();
            }

            // Comentário automático de início do fluxo (sempre)
            $etapasOrdenadas = $fluxo->etapasAtivas->sortBy('ordem')->values();
            $listaEtapas = $etapasOrdenadas->map(fn($e, $i) => ($i + 1) . '. ' . $e->nome . ' (' . ($e->departamento ?? '?') . ')')->implode(PHP_EOL);

            $textoComentario = 'Fluxo de workflow iniciado: "' . $fluxo->nome . '"'
                . PHP_EOL . PHP_EOL . 'Etapas do fluxo:' . PHP_EOL . $listaEtapas
                . PHP_EOL . PHP_EOL . 'Etapa atual: ' . $primeiraEtapa->nome . ' (' . ($primeiraEtapa->departamento ?? '?') . ')';

            $solicitacao->comentarios()->create([
                'usuario' => $matriculaUsuario,
                'comentario' => $textoComentario,
            ]);

            // Movimentação
            $this->registrarMovimentacao($solicitacao, $matriculaUsuario, 'Fluxo iniciado', 'Fluxo de workflow iniciado na etapa "' . $primeiraEtapa->nome . '"', $primeiraEtapa);

            // Atualizar etapa de andamento automaticamente (se configurada na primeira etapa)
            if ($primeiraEtapa->etapa_andamento_id) {
                $this->atualizarEtapaAndamento($solicitacao, $primeiraEtapa->etapa_andamento_id, $matriculaUsuario);
            }

            Log::info('Workflow: Fluxo iniciado', [
                'solicitacao_id' => $solicitacao->id,
                'fluxo_id' => $fluxo->id,
                'etapa_id' => $primeiraEtapa->id,
                'etapa_nome' => $primeiraEtapa->nome,
            ]);

            return $execucao;
        });
    }

    // ─── AVANÇAR ETAPA (SEM DECISÃO) ──────────────────────────────

    /**
     * Avança para a próxima etapa na ordem sequencial.
     * Usado quando a etapa NÃO tem decisões configuradas.
     * Ao concluir a etapa, vai direto para a próxima na ordem.
     *
     * @return array{sucesso: bool, mensagem: string, etapa_nova: ?SolicitacaoFluxoEtapa}
     */
    public function avancarEtapa(Solicitacao $solicitacao, int $matriculaUsuario, ?string $observacao = null): array
    {

        $execucao = SolicitacaoFluxoExecucao::where('solicitacao_id', $solicitacao->id)
            ->with('etapaAtual')
            ->first();

        if (! $execucao || ! $execucao->isAtivo()) {
            return ['sucesso' => false, 'mensagem' => 'Solicitação não está em um fluxo ativo', 'etapa_nova' => null];
        }

        $etapaAtual = $execucao->etapaAtual;

        // Guarda de autorização: etapa em Modo Exclusivo do Solicitante só pode ser avançada pelo solicitante.
        // Executa antes de qualquer escrita para garantir zero efeito colateral no 403.
        $guarda = $this->guardSolicitante($solicitacao, $etapaAtual, $matriculaUsuario);
        if ($guarda) {
            return $guarda;
        }

        // Se a etapa tem decisões, não pode avançar diretamente
        if ($etapaAtual->temDecisoes()) {
            return ['sucesso' => false, 'mensagem' => 'Esta etapa requer uma decisão para avançar', 'etapa_nova' => null];
        }

        // Validar campos obrigatórios da etapa antes de avançar
        $validacaoCampos = $this->validarCamposObrigatorios($execucao, $etapaAtual);
        if (! $validacaoCampos['valido']) {
            return ['sucesso' => false, 'mensagem' => $validacaoCampos['mensagem'], 'etapa_nova' => null];
        }

        $proximaEtapa = $etapaAtual->proximaEtapa();

        // Se não tem próxima etapa, o fluxo finaliza
        if (! $proximaEtapa) {
            return $this->finalizarFluxo($solicitacao, $execucao, $etapaAtual, $matriculaUsuario, $observacao);
        }

        return $this->moverParaEtapa($solicitacao, $execucao, $etapaAtual, $proximaEtapa, $matriculaUsuario, $observacao);
    }

    // ─── VOLTAR ETAPA ─────────────────────────────────────────────

    /**
     * Volta a solicitação para a etapa anterior do fluxo.
     * Registra histórico como "Fluxo retornou".
     *
     * @return array{sucesso: bool, mensagem: string, etapa_nova: ?SolicitacaoFluxoEtapa}
     */
    public function voltarEtapa(Solicitacao $solicitacao, int $matriculaUsuario, ?string $observacao = null): array
    {
        $execucao = SolicitacaoFluxoExecucao::where('solicitacao_id', $solicitacao->id)
            ->with('etapaAtual')
            ->first();

        if (! $execucao || ! $execucao->isAtivo()) {
            return ['sucesso' => false, 'mensagem' => 'Solicitação não está em um fluxo ativo', 'etapa_nova' => null];
        }

        $etapaAtual = $execucao->etapaAtual;
        $etapaAnterior = $etapaAtual->etapaAnterior();

        if (! $etapaAnterior) {
            return ['sucesso' => false, 'mensagem' => 'Já está na primeira etapa do fluxo', 'etapa_nova' => null];
        }

        return $this->moverParaEtapa($solicitacao, $execucao, $etapaAtual, $etapaAnterior, $matriculaUsuario, $observacao);
    }

    // ─── PROCESSAR DECISÃO ────────────────────────────────────────

    /**
     * Processa uma decisão tomada pelo responsável.
     * Ex: "Aprovado" → vai pra Contabilidade, "Reprovado" → volta pro RH
     *
     * @return array{sucesso: bool, mensagem: string, etapa_nova: ?SolicitacaoFluxoEtapa}
     */
    public function processarDecisao(Solicitacao $solicitacao, int $decisaoId, int $matriculaUsuario, ?string $observacao = null, ?int $responsavelMatricula = null): array
    {
        $execucao = SolicitacaoFluxoExecucao::where('solicitacao_id', $solicitacao->id)
            ->with('etapaAtual')
            ->first();

        if (! $execucao || ! $execucao->isAtivo()) {
            return ['sucesso' => false, 'mensagem' => 'Solicitação não está em um fluxo ativo', 'etapa_nova' => null];
        }

        $decisao = SolicitacaoFluxoDecisao::with('etapaDestino')->find($decisaoId);

        if (! $decisao || $decisao->etapa_fluxo_id !== $execucao->etapa_atual_id) {
            return ['sucesso' => false, 'mensagem' => 'Decisão inválida para a etapa atual', 'etapa_nova' => null];
        }

        $etapaAtual = $execucao->etapaAtual;

        // Guarda de autorização: etapa em Modo Exclusivo do Solicitante só pode ser avançada pelo solicitante.
        // Executa antes de qualquer escrita para garantir zero efeito colateral no 403.
        $guarda = $this->guardSolicitante($solicitacao, $etapaAtual, $matriculaUsuario);
        if ($guarda) {
            return $guarda;
        }

        // Se a decisão finaliza o fluxo
        if ($decisao->isFinalizacao()) {
            $validacaoCampos = $this->validarCamposObrigatorios($execucao, $etapaAtual, $decisaoId);
            if (! $validacaoCampos['valido']) {
                return ['sucesso' => false, 'mensagem' => $validacaoCampos['mensagem'], 'etapa_nova' => null];
            }

            return $this->finalizarFluxo($solicitacao, $execucao, $etapaAtual, $matriculaUsuario, $observacao, $decisao);
        }

        // Se a decisão cancela
        if ($decisao->acao === 'cancelar') {
            return $this->cancelarFluxo($solicitacao, $execucao, $etapaAtual, $matriculaUsuario, $observacao, $decisao);
        }

        // Se a decisão resolve a solicitação
        if ($decisao->isResolucao()) {
            $validacaoCampos = $this->validarCamposObrigatorios($execucao, $etapaAtual, $decisaoId);
            if (! $validacaoCampos['valido']) {
                return ['sucesso' => false, 'mensagem' => $validacaoCampos['mensagem'], 'etapa_nova' => null];
            }

            return $this->resolverFluxo($solicitacao, $execucao, $etapaAtual, $matriculaUsuario, $observacao, $decisao);
        }

        // Se a decisão volta para o solicitante
        if ($decisao->isVoltarSolicitante()) {
            $validacaoCampos = $this->validarCamposObrigatorios($execucao, $etapaAtual, $decisaoId);
            if (! $validacaoCampos['valido']) {
                return ['sucesso' => false, 'mensagem' => $validacaoCampos['mensagem'], 'etapa_nova' => null];
            }

            return $this->voltarParaSolicitante($solicitacao, $execucao, $etapaAtual, $matriculaUsuario, $observacao, $decisao);
        }

        // Se a decisão abre uma solicitação vinculada
        if ($decisao->isAbrirSolicitacao()) {
            $validacaoCampos = $this->validarCamposObrigatorios($execucao, $etapaAtual, $decisaoId);
            if (! $validacaoCampos['valido']) {
                return ['sucesso' => false, 'mensagem' => $validacaoCampos['mensagem'], 'etapa_nova' => null];
            }

            return $this->abrirSolicitacaoVinculada($solicitacao, $execucao, $etapaAtual, $decisao, $matriculaUsuario, $observacao);
        }

        // Validar campos obrigatórios antes de avançar para outra etapa
        $validacaoCampos = $this->validarCamposObrigatorios($execucao, $etapaAtual, $decisaoId);
        if (! $validacaoCampos['valido']) {
            return ['sucesso' => false, 'mensagem' => $validacaoCampos['mensagem'], 'etapa_nova' => null];
        }

        // Mover para a etapa destino da decisão
        $etapaDestino = $decisao->etapaDestino;

        if (! $etapaDestino) {
            return ['sucesso' => false, 'mensagem' => 'Etapa destino não encontrada', 'etapa_nova' => null];
        }

        return $this->moverParaEtapa($solicitacao, $execucao, $etapaAtual, $etapaDestino, $matriculaUsuario, $observacao, $decisao, $responsavelMatricula);
    }

    // ─── MOVER PARA ETAPA ─────────────────────────────────────────

    /**
     * Move a solicitação de uma etapa para outra.
     * Se o departamento muda, transfere automaticamente a solicitação.
     */
    private function moverParaEtapa(
        Solicitacao $solicitacao,
        SolicitacaoFluxoExecucao $execucao,
        SolicitacaoFluxoEtapa $etapaAnterior,
        SolicitacaoFluxoEtapa $etapaNova,
        int $matriculaUsuario,
        ?string $observacao = null,
        ?SolicitacaoFluxoDecisao $decisao = null,
        ?int $responsavelMatricula = null
    ): array {
        return DB::transaction(function () use ($solicitacao, $execucao, $etapaAnterior, $etapaNova, $matriculaUsuario, $observacao, $decisao, $responsavelMatricula) {

            $departamentoAnterior = $solicitacao->departamento_responsavel;
            $departamentoNovo = $etapaNova->departamento;
            $mudouDepartamento = $departamentoAnterior !== $departamentoNovo;

            // Atualizar execução
            $statusNovo = $this->statusParaEtapa($etapaNova);

            $execucao->update([
                'etapa_atual_id' => $etapaNova->id,
                'status' => $statusNovo,
                'prazo_inicio' => $etapaNova->prazo_horas ? Carbon::now() : null,
                'usuario_alteracao' => $matriculaUsuario,
            ]);

            // Registrar histórico
            SolicitacaoFluxoHistorico::create([
                'solicitacao_id' => $solicitacao->id,
                'fluxo_id' => $execucao->fluxo_id,
                'etapa_anterior_id' => $etapaAnterior->id,
                'etapa_nova_id' => $etapaNova->id,
                'decisao_id' => $decisao?->id,
                'decisao_label' => $decisao?->label,
                'usuario_alteracao' => $matriculaUsuario,
                'observacao' => $observacao,
            ]);

            // Detectar se é retorno (voltando para etapa anterior)
            $isRetorno = $etapaNova->ordem < $etapaAnterior->ordem;

            // Etapa em Modo Exclusivo do Solicitante ('E'): o ator é o solicitante,
            // portanto usuario_responsavel deve ficar nulo (solicitante acessa via
            // "Minhas Solicitações"). Departamento segue a regra de transferência normal.
            $etapaNovaEhExclusiva = $etapaNova->permitir_solicitante_avancar === 'E';

            // Se o departamento mudou, transferir a solicitação automaticamente
            if ($mudouDepartamento) {
                $this->transferirDepartamento($solicitacao, $departamentoAnterior, $etapaNova, $matriculaUsuario, $observacao, $decisao);

                // Se é retorno e não tem responsavel_padrao, restaurar responsável histórico
                if ($isRetorno && ! $etapaNova->responsavel_padrao && $etapaNova->manter_responsavel !== 'S' && ! $etapaNovaEhExclusiva) {
                    $responsavelHistorico = $this->buscarResponsavelHistorico($solicitacao, $execucao, $etapaNova);
                    if ($responsavelHistorico) {
                        $solicitacao->refresh();
                        $solicitacao->usuario_responsavel = $responsavelHistorico;
                        $solicitacao->save();
                    }
                }

                // Em Modo Exclusivo, limpar qualquer responsável que tenha vindo do transferirDepartamento
                if ($etapaNovaEhExclusiva) {
                    $solicitacao->refresh();
                    $solicitacao->usuario_responsavel = null;
                    $solicitacao->save();
                }
            } else {
                // Limpar responsável apenas se a etapa não estiver configurada para manter
                if ($etapaNova->manter_responsavel !== 'S') {
                    $solicitacao->usuario_responsavel = null;
                }

                // Em Modo Exclusivo, ignorar responsavel_padrao e histórico — etapa é do solicitante
                if ($etapaNovaEhExclusiva) {
                    $solicitacao->usuario_responsavel = null;
                } elseif ($etapaNova->responsavel_padrao) {
                    // Auto-atribuir responsável padrão da etapa (se configurado)
                    $solicitacao->usuario_responsavel = $etapaNova->responsavel_padrao;
                } elseif ($isRetorno) {
                    // Se é retorno, restaurar responsável histórico
                    $responsavelHistorico = $this->buscarResponsavelHistorico($solicitacao, $execucao, $etapaNova);
                    if ($responsavelHistorico) {
                        $solicitacao->usuario_responsavel = $responsavelHistorico;
                    }
                }

                // Se tem assunto específico, atualizar
                if ($etapaNova->assunto_id && $etapaNova->assunto_id !== $solicitacao->assunto_id) {
                    $solicitacao->assunto_id = $etapaNova->assunto_id;
                }

                $solicitacao->save();
            }

            // Override: se a decisão forneceu uma matrícula explícita (atribuir_avancar).
            // Em etapa Modo Exclusivo, ignorar — etapa é do solicitante por definição.
            if ($responsavelMatricula && ! $etapaNovaEhExclusiva) {
                $solicitacao->refresh();
                $solicitacao->usuario_responsavel = $responsavelMatricula;
                $solicitacao->save();
            }

            // Movimentação — registrar se avançou ou retornou
            $tipoMov = $isRetorno ? 'Fluxo retornou' : 'Fluxo avançou';
            $descricao = ($isRetorno ? 'Fluxo retornou' : 'Fluxo avançou') . ' de "' . $etapaAnterior->nome . '" para "' . $etapaNova->nome . '"';
            if ($decisao) {
                $descricao .= ' (Decisão: ' . $decisao->label . ')';
            }
            if ($responsavelMatricula) {
                $func = \App\Models\Funcionario::where('matricula', $responsavelMatricula)->first(['matricula', 'nome']);
                $nomeResp = $func ? mb_convert_encoding($func->nome, 'UTF-8', 'auto') : "Mat. {$responsavelMatricula}";
                $descricao .= ' — Responsável atribuído: ' . $nomeResp;
            }
            if ($mudouDepartamento) {
                $descricao .= ' — Transferido para ' . $departamentoNovo;
            }

            $this->registrarMovimentacao($solicitacao, $matriculaUsuario, $tipoMov, $descricao, $etapaNova);

            // Atualizar etapa de andamento automaticamente
            // Prioridade: decisão > etapa destino
            $andamentoId = ($decisao && $decisao->etapa_andamento_id)
                ? $decisao->etapa_andamento_id
                : $etapaNova->etapa_andamento_id;

            if ($andamentoId) {
                $this->atualizarEtapaAndamento($solicitacao, $andamentoId, $matriculaUsuario);
            }

            // Notificar via Reverb
            $this->notificarTransicao($solicitacao, $departamentoAnterior, $departamentoNovo, $mudouDepartamento);

            Log::info('Workflow: Etapa avançada', [
                'solicitacao_id' => $solicitacao->id,
                'etapa_anterior' => $etapaAnterior->nome,
                'etapa_nova' => $etapaNova->nome,
                'departamento_novo' => $departamentoNovo,
                'decisao' => $decisao?->label,
            ]);

            return [
                'sucesso' => true,
                'mensagem' => $mudouDepartamento
                    ? 'Solicitação transferida para ' . $departamentoNovo . ' — Etapa: ' . $etapaNova->nome
                    : 'Avançado para etapa "' . $etapaNova->nome . '"',
                'etapa_nova' => $etapaNova,
            ];
        });
    }

    // ─── BUSCAR RESPONSÁVEL HISTÓRICO ───────────────────────────

    /**
     * Busca no histórico quem era o responsável quando a solicitação
     * passou pela etapa anteriormente (para restaurar em retornos).
     */
    private function buscarResponsavelHistorico(
        Solicitacao $solicitacao,
        SolicitacaoFluxoExecucao $execucao,
        SolicitacaoFluxoEtapa $etapaDestino
    ): ?int {
        // Buscar no histórico quem fez a decisão quando a solicitação saiu desta etapa
        $historico = SolicitacaoFluxoHistorico::where('solicitacao_id', $solicitacao->id)
            ->where('fluxo_id', $execucao->fluxo_id)
            ->where('etapa_anterior_id', $etapaDestino->id)
            ->whereNotNull('usuario_alteracao')
            ->orderBy('created_at', 'desc')
            ->first();

        return $historico?->usuario_alteracao;
    }

    // ─── TRANSFERIR DEPARTAMENTO ──────────────────────────────────

    /**
     * Transfere automaticamente a solicitação para o departamento
     * da nova etapa do fluxo.
     */
    private function transferirDepartamento(
        Solicitacao $solicitacao,
        string $departamentoAnterior,
        SolicitacaoFluxoEtapa $etapaNova,
        int $matriculaUsuario,
        ?string $observacao = null,
        ?SolicitacaoFluxoDecisao $decisao = null
    ): void {
        $solicitacao->departamento_responsavel = $etapaNova->departamento;
        // Limpar responsável apenas se a etapa não estiver configurada para manter
        if ($etapaNova->manter_responsavel !== 'S') {
            $solicitacao->usuario_responsavel = null;
        }

        // Auto-atribuir responsável padrão da etapa (se configurado)
        if ($etapaNova->responsavel_padrao) {
            $solicitacao->usuario_responsavel = $etapaNova->responsavel_padrao;
        }

        // Se a etapa tem assunto específico, atualizar
        if ($etapaNova->assunto_id) {
            $solicitacao->assunto_id = $etapaNova->assunto_id;
        }

        $solicitacao->save();

        // Comentário automático
        $textoComentario = 'Transferencia automatica via fluxo: "' . $etapaNova->fluxo->nome . '"'
            . PHP_EOL . 'De: ' . $departamentoAnterior . ' -> Para: ' . $etapaNova->departamento
            . PHP_EOL . 'Etapa: ' . $etapaNova->nome;

        if ($decisao) {
            $textoComentario .= PHP_EOL . 'Decisao: ' . $decisao->label;
        }

        if ($observacao) {
            $textoComentario .= PHP_EOL . 'Observacao: ' . $observacao;
        }

        $solicitacao->comentarios()->create([
            'usuario' => $matriculaUsuario,
            'comentario' => $textoComentario,
        ]);
    }

    // ─── FINALIZAR FLUXO ──────────────────────────────────────────

    /**
     * Finaliza o fluxo de workflow.
     */
    private function finalizarFluxo(
        Solicitacao $solicitacao,
        SolicitacaoFluxoExecucao $execucao,
        SolicitacaoFluxoEtapa $etapaAtual,
        int $matriculaUsuario,
        ?string $observacao = null,
        ?SolicitacaoFluxoDecisao $decisao = null
    ): array {
        return DB::transaction(function () use ($solicitacao, $execucao, $etapaAtual, $matriculaUsuario, $observacao, $decisao) {
            $execucao->update([
                'status' => 'concluido',
                'usuario_alteracao' => $matriculaUsuario,
            ]);

            SolicitacaoFluxoHistorico::create([
                'solicitacao_id' => $solicitacao->id,
                'fluxo_id' => $execucao->fluxo_id,
                'etapa_anterior_id' => $etapaAtual->id,
                'etapa_nova_id' => null,
                'decisao_id' => $decisao?->id,
                'decisao_label' => $decisao?->label,
                'usuario_alteracao' => $matriculaUsuario,
                'observacao' => $observacao ?? 'Fluxo concluido',
            ]);

            $descricao = 'Fluxo de workflow concluido na etapa "' . $etapaAtual->nome . '"';
            if ($decisao) {
                $descricao .= ' (Decisao: ' . $decisao->label . ')';
            }
            $this->registrarMovimentacao($solicitacao, $matriculaUsuario, 'Fluxo concluido', $descricao, $etapaAtual);

            // Comentário no chat
            $textoComentario = 'Fluxo concluído na etapa "' . $etapaAtual->nome . '"';
            if ($decisao) {
                $textoComentario .= PHP_EOL . 'Decisão: ' . $decisao->label;
            }
            if ($observacao && $observacao !== 'Fluxo concluido') {
                $textoComentario .= PHP_EOL . 'Observação: ' . $observacao;
            }
            $solicitacao->comentarios()->create([
                'usuario' => $matriculaUsuario,
                'comentario' => $textoComentario,
            ]);

            // Atualizar etapa de andamento (decisão tem prioridade)
            $andamentoId = ($decisao && $decisao->etapa_andamento_id)
                ? $decisao->etapa_andamento_id
                : null;
            if ($andamentoId) {
                $this->atualizarEtapaAndamento($solicitacao, $andamentoId, $matriculaUsuario);
            }

            // Resolver a solicitação automaticamente
            $solicitacao->update(['status' => 'resolvida']);

            $this->registrarMovimentacao(
                $solicitacao,
                $matriculaUsuario,
                'Atendimento resolvido',
                'Solicitação resolvida automaticamente pelo workflow — fluxo concluído na etapa "' . $etapaAtual->nome . '"'
            );

            $this->notificarTransicao($solicitacao, $solicitacao->departamento_responsavel, null, false);

            Log::info('Workflow: Fluxo concluído', [
                'solicitacao_id' => $solicitacao->id,
                'etapa_final' => $etapaAtual->nome,
            ]);

            return [
                'sucesso' => true,
                'mensagem' => 'Fluxo concluído com sucesso!',
                'etapa_nova' => null,
            ];
        });
    }

    // ─── RESOLVER FLUXO (SOLICITAÇÃO RESOLVIDA) ───────────────────

    /**
     * Resolve o fluxo de workflow — conclui o fluxo e marca a solicitação como resolvida.
     * Diferente de "Finalizar", a ação "Resolver" tem semântica de resolução do chamado.
     */
    private function resolverFluxo(
        Solicitacao $solicitacao,
        SolicitacaoFluxoExecucao $execucao,
        SolicitacaoFluxoEtapa $etapaAtual,
        int $matriculaUsuario,
        ?string $observacao = null,
        ?SolicitacaoFluxoDecisao $decisao = null
    ): array {
        return DB::transaction(function () use ($solicitacao, $execucao, $etapaAtual, $matriculaUsuario, $observacao, $decisao) {
            // 1) Concluir a execução do fluxo
            $execucao->update([
                'status' => 'concluido',
                'usuario_alteracao' => $matriculaUsuario,
            ]);

            SolicitacaoFluxoHistorico::create([
                'solicitacao_id' => $solicitacao->id,
                'fluxo_id' => $execucao->fluxo_id,
                'etapa_anterior_id' => $etapaAtual->id,
                'etapa_nova_id' => null,
                'decisao_id' => $decisao?->id,
                'decisao_label' => $decisao?->label,
                'usuario_alteracao' => $matriculaUsuario,
                'observacao' => $observacao ?? 'Solicitação resolvida via fluxo',
            ]);

            $descricao = 'Solicitação resolvida via fluxo na etapa "' . $etapaAtual->nome . '"';
            if ($decisao) {
                $descricao .= ' (Decisão: ' . $decisao->label . ')';
            }
            $this->registrarMovimentacao($solicitacao, $matriculaUsuario, 'Fluxo resolvido', $descricao, $etapaAtual);

            // Comentário no chat
            $textoComentario = 'Solicitação resolvida via fluxo na etapa "' . $etapaAtual->nome . '"';
            if ($decisao) {
                $textoComentario .= PHP_EOL . 'Decisão: ' . $decisao->label;
            }
            if ($observacao && $observacao !== 'Solicitação resolvida via fluxo') {
                $textoComentario .= PHP_EOL . 'Observação: ' . $observacao;
            }
            $solicitacao->comentarios()->create([
                'usuario' => $matriculaUsuario,
                'comentario' => $textoComentario,
            ]);

            // Atualizar etapa de andamento (decisão tem prioridade)
            $andamentoId = ($decisao && $decisao->etapa_andamento_id)
                ? $decisao->etapa_andamento_id
                : null;
            if ($andamentoId) {
                $this->atualizarEtapaAndamento($solicitacao, $andamentoId, $matriculaUsuario);
            }

            // 2) Resolver a solicitação
            $solicitacao->update(['status' => 'resolvida']);

            $this->registrarMovimentacao(
                $solicitacao,
                $matriculaUsuario,
                'Atendimento resolvido',
                'Solicitação resolvida pelo workflow — decisão "' . ($decisao?->label ?? 'Resolver') . '" na etapa "' . $etapaAtual->nome . '"'
            );

            // 3) Notificar via Reverb
            $this->notificarTransicao($solicitacao, $solicitacao->departamento_responsavel, null, false);

            Log::info('Workflow: Solicitação resolvida via fluxo', [
                'solicitacao_id' => $solicitacao->id,
                'etapa_final' => $etapaAtual->nome,
                'decisao' => $decisao?->label,
            ]);

            return [
                'sucesso' => true,
                'mensagem' => 'Solicitação resolvida com sucesso!',
                'etapa_nova' => null,
            ];
        });
    }

    // ─── CANCELAR FLUXO ───────────────────────────────────────────

    private function cancelarFluxo(
        Solicitacao $solicitacao,
        SolicitacaoFluxoExecucao $execucao,
        SolicitacaoFluxoEtapa $etapaAtual,
        int $matriculaUsuario,
        ?string $observacao = null,
        ?SolicitacaoFluxoDecisao $decisao = null
    ): array {
        return DB::transaction(function () use ($solicitacao, $execucao, $etapaAtual, $matriculaUsuario, $observacao, $decisao) {
            // 1) Cancelar a execução do fluxo
            $execucao->update([
                'status' => 'cancelado',
                'usuario_alteracao' => $matriculaUsuario,
            ]);

            SolicitacaoFluxoHistorico::create([
                'solicitacao_id' => $solicitacao->id,
                'fluxo_id' => $execucao->fluxo_id,
                'etapa_anterior_id' => $etapaAtual->id,
                'etapa_nova_id' => null,
                'decisao_id' => $decisao?->id,
                'decisao_label' => $decisao?->label,
                'usuario_alteracao' => $matriculaUsuario,
                'observacao' => $observacao ?? 'Fluxo cancelado',
            ]);

            $descricaoFluxo = 'Fluxo de workflow cancelado na etapa "' . $etapaAtual->nome . '"';
            if ($decisao) {
                $descricaoFluxo .= ' (Decisão: ' . $decisao->label . ')';
            }
            if ($observacao && $observacao !== 'Fluxo cancelado') {
                $descricaoFluxo .= ' — Motivo: ' . $observacao;
            }
            $this->registrarMovimentacao($solicitacao, $matriculaUsuario, 'Fluxo cancelado', $descricaoFluxo, $etapaAtual);

            // Comentário no chat
            $textoComentario = 'Fluxo cancelado na etapa "' . $etapaAtual->nome . '"';
            if ($decisao) {
                $textoComentario .= PHP_EOL . 'Decisão: ' . $decisao->label;
            }
            if ($observacao && $observacao !== 'Fluxo cancelado') {
                $textoComentario .= PHP_EOL . 'Motivo: ' . $observacao;
            }
            $solicitacao->comentarios()->create([
                'usuario' => $matriculaUsuario,
                'comentario' => $textoComentario,
            ]);

            // Atualizar etapa de andamento (decisão tem prioridade)
            $andamentoId = ($decisao && $decisao->etapa_andamento_id)
                ? $decisao->etapa_andamento_id
                : null;
            if ($andamentoId) {
                $this->atualizarEtapaAndamento($solicitacao, $andamentoId, $matriculaUsuario);
            }

            // 2) Cancelar a solicitação (chamado) automaticamente
            $solicitacao->update(['status' => 'cancelada']);

            $this->registrarMovimentacao(
                $solicitacao,
                $matriculaUsuario,
                'Solicitação cancelada',
                'Solicitação cancelada automaticamente pelo workflow — fluxo cancelado na etapa "' . $etapaAtual->nome . '"'
            );

            // 3) Notificar via Reverb
            $this->notificarTransicao($solicitacao, $solicitacao->departamento_responsavel, null, false);

            Log::info('Workflow: Fluxo e solicitação cancelados', [
                'solicitacao_id' => $solicitacao->id,
                'etapa_final' => $etapaAtual->nome,
                'decisao' => $decisao?->label,
            ]);

            return [
                'sucesso' => true,
                'mensagem' => 'Fluxo cancelado e solicitação encerrada',
                'etapa_nova' => null,
            ];
        });
    }

    // ─── ENCERRAR FLUXO POR TROCA DE ASSUNTO ──────────────────────

    /**
     * Encerra a execução ativa do fluxo da solicitação por motivo de
     * troca de assunto/departamento. Diferente do cancelarFluxo, NÃO
     * cancela a solicitação — apenas marca a execução do fluxo como
     * cancelada e registra histórico/movimentação para auditoria.
     *
     * Uso típico: quando o atendente transfere uma solicitação para um
     * assunto diferente. Caso o novo assunto tenha fluxo ativo, deve-se
     * chamar `iniciarFluxo()` em seguida para começar o novo workflow.
     *
     * @return bool true quando havia execução ativa e foi encerrada
     */
    public function encerrarFluxoPorTrocaAssunto(
        Solicitacao $solicitacao,
        int $matriculaUsuario,
        string $assuntoAnterior,
        string $assuntoNovo,
        ?string $observacao = null
    ): bool {
        $execucao = SolicitacaoFluxoExecucao::where('solicitacao_id', $solicitacao->id)
            ->whereIn('status', ['em_andamento', 'aguardando_decisao', 'aguardando_solicitante'])
            ->with('etapaAtual')
            ->first();

        if (! $execucao) {
            return false;
        }

        return DB::transaction(function () use ($solicitacao, $execucao, $matriculaUsuario, $assuntoAnterior, $assuntoNovo, $observacao) {
            $etapaAtual = $execucao->etapaAtual;
            $obsHistorico = 'Fluxo encerrado por troca de assunto: "' . $assuntoAnterior . '" → "' . $assuntoNovo . '"';
            if ($observacao) {
                $obsHistorico .= PHP_EOL . 'Motivo: ' . $observacao;
            }

            $execucao->update([
                'status' => 'cancelado',
                'usuario_alteracao' => $matriculaUsuario,
            ]);

            SolicitacaoFluxoHistorico::create([
                'solicitacao_id' => $solicitacao->id,
                'fluxo_id' => $execucao->fluxo_id,
                'etapa_anterior_id' => $etapaAtual?->id,
                'etapa_nova_id' => null,
                'usuario_alteracao' => $matriculaUsuario,
                'observacao' => $obsHistorico,
            ]);

            $this->registrarMovimentacao(
                $solicitacao,
                $matriculaUsuario,
                'Fluxo encerrado',
                $obsHistorico,
                $etapaAtual
            );

            Log::info('Workflow: Fluxo encerrado por troca de assunto', [
                'solicitacao_id' => $solicitacao->id,
                'fluxo_id' => $execucao->fluxo_id,
                'assunto_anterior' => $assuntoAnterior,
                'assunto_novo' => $assuntoNovo,
            ]);

            return true;
        });
    }

    // ─── REATIVAR FLUXO POR RECUSA DE RESOLUÇÃO ────────────────────

    /**
     * Reativa a execução do fluxo quando o solicitante recusa a resolução.
     * Volta o status da execução de `concluido`/`cancelado` para `em_andamento`,
     * mantendo a etapa atual onde estava ao resolver. Registra histórico e
     * movimentação para auditoria.
     *
     * @return bool true quando havia execução encerrada e foi reativada
     */
    public function reativarFluxoPorRecusa(
        Solicitacao $solicitacao,
        int $matriculaUsuario,
        ?string $observacao = null
    ): bool {
        $execucao = SolicitacaoFluxoExecucao::where('solicitacao_id', $solicitacao->id)
            ->whereIn('status', ['concluido', 'cancelado'])
            ->with('etapaAtual')
            ->orderByDesc('updated_at')
            ->first();

        if (! $execucao || ! $execucao->etapaAtual) {
            return false;
        }

        return DB::transaction(function () use ($solicitacao, $execucao, $matriculaUsuario, $observacao) {
            $etapaAtual = $execucao->etapaAtual;

            $statusReativado = $this->statusParaEtapa($etapaAtual);
            $execucao->update([
                'status' => $statusReativado,
                'usuario_alteracao' => $matriculaUsuario,
            ]);

            $obsHistorico = 'Fluxo reativado — solicitante recusou a resolução';
            if ($observacao) {
                $obsHistorico .= PHP_EOL . 'Motivo: ' . $observacao;
            }

            SolicitacaoFluxoHistorico::create([
                'solicitacao_id' => $solicitacao->id,
                'fluxo_id' => $execucao->fluxo_id,
                'etapa_anterior_id' => null,
                'etapa_nova_id' => $etapaAtual->id,
                'usuario_alteracao' => $matriculaUsuario,
                'observacao' => $obsHistorico,
            ]);

            $this->registrarMovimentacao(
                $solicitacao,
                $matriculaUsuario,
                'Fluxo reativado',
                'Fluxo reativado na etapa "' . $etapaAtual->nome . '" — solicitante recusou a resolução',
                $etapaAtual
            );

            // Restaurar a etapa de andamento da etapa atual (se configurada),
            // tirando a solicitação do estado "Finalizado".
            if ($etapaAtual->etapa_andamento_id) {
                $this->atualizarEtapaAndamento($solicitacao, $etapaAtual->etapa_andamento_id, $matriculaUsuario);
            }

            Log::info('Workflow: Fluxo reativado por recusa de resolução', [
                'solicitacao_id' => $solicitacao->id,
                'fluxo_id' => $execucao->fluxo_id,
                'etapa_id' => $etapaAtual->id,
                'etapa_nome' => $etapaAtual->nome,
            ]);

            return true;
        });
    }

    // ─── VOLTAR PARA SOLICITANTE ──────────────────────────────────

    /**
     * Devolve a solicitação para o departamento do solicitante original.
     * O fluxo é pausado (status = 'aguardando_solicitante') e a solicitação
     * volta ao departamento de origem do solicitante para complemento/correção.
     */
    private function voltarParaSolicitante(
        Solicitacao $solicitacao,
        SolicitacaoFluxoExecucao $execucao,
        SolicitacaoFluxoEtapa $etapaAtual,
        int $matriculaUsuario,
        ?string $observacao = null,
        ?SolicitacaoFluxoDecisao $decisao = null
    ): array {
        return DB::transaction(function () use ($solicitacao, $execucao, $etapaAtual, $matriculaUsuario, $observacao, $decisao) {

            // Buscar o departamento do solicitante original
            $solicitante = \App\Models\Funcionario::where('matricula', $solicitacao->usuario_solicitante)
                ->select('matricula', 'nome', 'areaatuacao')
                ->first();

            if (! $solicitante || ! $solicitante->areaatuacao) {
                return [
                    'sucesso' => false,
                    'mensagem' => 'Não foi possível identificar o departamento do solicitante',
                    'etapa_nova' => null,
                ];
            }

            $departamentoAnterior = $solicitacao->departamento_responsavel;
            $departamentoSolicitante = $solicitante->areaatuacao;

            // Usar a etapa de retorno configurada na decisão, ou fallback para a primeira etapa ativa
            $etapaDestino = null;
            if ($decisao && $decisao->etapa_destino_id) {
                $etapaDestino = SolicitacaoFluxoEtapa::where('id', $decisao->etapa_destino_id)
                    ->where('ativo', 'S')
                    ->first();
            }
            if (! $etapaDestino) {
                $etapaDestino = SolicitacaoFluxoEtapa::where('fluxo_id', $execucao->fluxo_id)
                    ->where('ativo', 'S')
                    ->orderBy('ordem')
                    ->first() ?? $etapaAtual;
            }

            // Atualizar execução — volta para a primeira etapa e marca como aguardando solicitante
            $execucao->update([
                'etapa_atual_id' => $etapaDestino->id,
                'status' => 'aguardando_solicitante',
                'usuario_alteracao' => $matriculaUsuario,
            ]);

            // Registrar histórico
            SolicitacaoFluxoHistorico::create([
                'solicitacao_id' => $solicitacao->id,
                'fluxo_id' => $execucao->fluxo_id,
                'etapa_anterior_id' => $etapaAtual->id,
                'etapa_nova_id' => $etapaDestino->id,
                'decisao_id' => $decisao?->id,
                'decisao_label' => $decisao?->label,
                'usuario_alteracao' => $matriculaUsuario,
                'observacao' => $observacao ?? 'Devolvido ao solicitante para complemento',
            ]);

            // Transferir para o departamento do solicitante e mudar status
            $solicitacao->departamento_responsavel = $departamentoSolicitante;
            $solicitacao->usuario_responsavel = $solicitacao->usuario_solicitante;
            $solicitacao->status = 'retorno solicitante';
            $solicitacao->save();

            // Comentário automático
            $textoComentario = 'Solicitação devolvida ao solicitante via fluxo'
                . PHP_EOL . 'De: ' . $departamentoAnterior . ' -> Para: ' . $departamentoSolicitante
                . PHP_EOL . 'Etapa: ' . $etapaAtual->nome;

            if ($decisao) {
                $textoComentario .= PHP_EOL . 'Decisão: ' . $decisao->label;
            }
            if ($observacao) {
                $textoComentario .= PHP_EOL . 'Observação: ' . $observacao;
            }

            $solicitacao->comentarios()->create([
                'usuario' => $matriculaUsuario,
                'comentario' => $textoComentario,
            ]);

            // Movimentação
            $descricao = 'Solicitação devolvida ao solicitante (' . ($solicitante->nome ?? $solicitante->matricula) . ')'
                . ' — Departamento: ' . $departamentoSolicitante;
            if ($decisao) {
                $descricao .= ' (Decisão: ' . $decisao->label . ')';
            }
            if ($observacao) {
                $descricao .= ' — Motivo: ' . $observacao;
            }

            $this->registrarMovimentacao($solicitacao, $matriculaUsuario, 'Devolvido ao solicitante', $descricao, $etapaDestino);

            // Atualizar etapa de andamento (decisão tem prioridade)
            $andamentoId = ($decisao && $decisao->etapa_andamento_id)
                ? $decisao->etapa_andamento_id
                : null;
            if ($andamentoId) {
                $this->atualizarEtapaAndamento($solicitacao, $andamentoId, $matriculaUsuario);
            }

            // Notificar via Reverb
            $this->notificarTransicao($solicitacao, $departamentoAnterior, $departamentoSolicitante, true);

            Log::info('Workflow: Solicitação devolvida ao solicitante', [
                'solicitacao_id' => $solicitacao->id,
                'etapa_anterior' => $etapaAtual->nome,
                'etapa_destino' => $etapaDestino->nome,
                'departamento_solicitante' => $departamentoSolicitante,
                'decisao' => $decisao?->label,
            ]);

            return [
                'sucesso' => true,
                'mensagem' => 'Solicitação devolvida ao solicitante (' . ($solicitante->nome ?? '') . ') no departamento ' . $departamentoSolicitante,
                'etapa_nova' => $etapaDestino,
            ];
        });
    }

    // ─── ABRIR SOLICITAÇÃO VINCULADA ──────────────────────────────

    /**
     * Cria uma solicitação filha vinculada à atual e avança o fluxo.
     * A nova solicitação é aberta no assunto configurado na decisão.
     */
    private function abrirSolicitacaoVinculada(
        Solicitacao $solicitacao,
        SolicitacaoFluxoExecucao $execucao,
        SolicitacaoFluxoEtapa $etapaAtual,
        SolicitacaoFluxoDecisao $decisao,
        int $matriculaUsuario,
        ?string $observacao = null
    ): array {
        if (! $decisao->abrir_solicitacao_assunto_id) {
            return ['sucesso' => false, 'mensagem' => 'Decisão não tem assunto configurado para abrir solicitação', 'etapa_nova' => null];
        }

        $assuntoFilha = SolicitacaoAssunto::find($decisao->abrir_solicitacao_assunto_id);
        if (! $assuntoFilha) {
            return ['sucesso' => false, 'mensagem' => 'Assunto da solicitação vinculada não encontrado', 'etapa_nova' => null];
        }

        return DB::transaction(function () use ($solicitacao, $execucao, $etapaAtual, $decisao, $assuntoFilha, $matriculaUsuario, $observacao) {
            // 1) Criar a solicitação filha
            $novaSolicitacao = Solicitacao::create([
                'titulo' => '[Vinculada #' . $solicitacao->id . '] ' . $solicitacao->titulo,
                'descricao' => 'Solicitação criada automaticamente via fluxo de workflow a partir da solicitação #' . $solicitacao->id
                    . ($observacao ? PHP_EOL . 'Observação: ' . $observacao : ''),
                'departamento_responsavel' => $assuntoFilha->departamento,
                'prioridade' => $solicitacao->prioridade,
                'usuario_solicitante' => $matriculaUsuario,
                'filial_id' => $solicitacao->filial_id,
                'assunto_id' => $assuntoFilha->id,
                'status' => 'pendente',
                'solicitacao_pai_id' => $solicitacao->id,
            ]);

            // Movimentação na filha
            $novaSolicitacao->movimentacoes()->create([
                'tipo_movimentacao' => 'Solicitação criada',
                'descricao' => 'Solicitação criada automaticamente via fluxo de workflow — vinculada à solicitação #' . $solicitacao->id,
                'usuario_movimentacao' => $matriculaUsuario,
            ]);

            // Iniciar fluxo na filha (se o assunto da filha possuir fluxo ativo)
            $execucaoFilha = $this->iniciarFluxo($novaSolicitacao, $matriculaUsuario);
            if ($execucaoFilha) {
                $execucaoFilha->update(['solicitacao_pai_id' => $solicitacao->id]);
            }

            // 2) Avançar o fluxo da solicitação pai
            $etapaDestino = $decisao->etapaDestino;

            // Se a decisão tem etapa destino, mover para ela
            if ($etapaDestino) {
                $resultado = $this->moverParaEtapa($solicitacao, $execucao, $etapaAtual, $etapaDestino, $matriculaUsuario, $observacao, $decisao);
            } else {
                // Se não tem destino, avança para a próxima etapa sequencialmente
                $proximaEtapa = $etapaAtual->proximaEtapa();
                if ($proximaEtapa) {
                    $resultado = $this->moverParaEtapa($solicitacao, $execucao, $etapaAtual, $proximaEtapa, $matriculaUsuario, $observacao, $decisao);
                } else {
                    $resultado = $this->finalizarFluxo($solicitacao, $execucao, $etapaAtual, $matriculaUsuario, $observacao, $decisao);
                }
            }

            // 3) Registrar comentário sobre a solicitação vinculada na pai
            $solicitacao->comentarios()->create([
                'usuario' => $matriculaUsuario,
                'comentario' => 'Solicitação vinculada #' . $novaSolicitacao->id . ' criada automaticamente via fluxo'
                    . PHP_EOL . 'Assunto: ' . $assuntoFilha->assunto
                    . PHP_EOL . 'Departamento: ' . ($assuntoFilha->departamento ?? '?')
                    . ($observacao ? PHP_EOL . 'Observação: ' . $observacao : ''),
            ]);

            $this->registrarMovimentacao($solicitacao, $matriculaUsuario, 'Solicitação vinculada criada', 'Solicitação #' . $novaSolicitacao->id . ' criada via fluxo — Assunto: ' . $assuntoFilha->assunto, $etapaAtual);

            Log::info('Workflow: Solicitação vinculada criada', [
                'solicitacao_pai_id' => $solicitacao->id,
                'solicitacao_filha_id' => $novaSolicitacao->id,
                'assunto_filha' => $assuntoFilha->assunto,
                'decisao' => $decisao->label,
            ]);

            $resultado['mensagem'] .= ' — Solicitação #' . $novaSolicitacao->id . ' criada';
            $resultado['solicitacao_filha_id'] = $novaSolicitacao->id;

            return $resultado;
        });
    }

    // ─── RETORNAR DO SOLICITANTE ──────────────────────────────────

    /**
     * Devolve a solicitação ao fluxo após o solicitante complementar/corrigir.
     * Transfere de volta para o departamento da etapa atual do fluxo.
     */
    public function retornarDoSolicitante(Solicitacao $solicitacao, int $matriculaUsuario, ?string $observacao = null): array
    {
        $execucao = SolicitacaoFluxoExecucao::where('solicitacao_id', $solicitacao->id)
            ->with('etapaAtual')
            ->first();

        if (! $execucao || ! $execucao->isAguardandoSolicitante()) {
            return ['sucesso' => false, 'mensagem' => 'Solicitação não está aguardando retorno do solicitante', 'etapa_nova' => null];
        }

        // Buscar no histórico a etapa de origem (onde o responsável tomou a decisão de devolver)
        $historicoRetorno = SolicitacaoFluxoHistorico::where('solicitacao_id', $solicitacao->id)
            ->where('fluxo_id', $execucao->fluxo_id)
            ->whereNotNull('decisao_id')
            ->orderBy('created_at', 'desc')
            ->first();

        $etapaOrigem = null;
        if ($historicoRetorno && $historicoRetorno->etapa_anterior_id) {
            $etapaOrigem = SolicitacaoFluxoEtapa::where('id', $historicoRetorno->etapa_anterior_id)
                ->where('ativo', 'S')
                ->first();
        }

        // Retorna para a etapa de origem (Teste3), fallback para etapa atual
        $etapaDestino = $etapaOrigem ?? $execucao->etapaAtual;

        return DB::transaction(function () use ($solicitacao, $execucao, $etapaDestino, $matriculaUsuario, $observacao) {

            $departamentoAnterior = $solicitacao->departamento_responsavel;
            $departamentoEtapa = $etapaDestino->departamento;

            // Restaurar status da execução para o estado da etapa de destino
            $statusNovo = $etapaDestino->temDecisoes() ? 'aguardando_decisao' : 'em_andamento';

            $execucao->update([
                'etapa_atual_id' => $etapaDestino->id,
                'status' => $statusNovo,
                'usuario_alteracao' => $matriculaUsuario,
            ]);

            // Registrar histórico
            SolicitacaoFluxoHistorico::create([
                'solicitacao_id' => $solicitacao->id,
                'fluxo_id' => $execucao->fluxo_id,
                'etapa_anterior_id' => $execucao->etapa_atual_id,
                'etapa_nova_id' => $etapaDestino->id,
                'usuario_alteracao' => $matriculaUsuario,
                'observacao' => $observacao ?? 'Solicitante devolveu ao fluxo',
            ]);

            // Transferir de volta para o departamento da etapa do fluxo
            $solicitacao->departamento_responsavel = $departamentoEtapa;
            $solicitacao->status = 'em atendimento';

            // Restaurar responsável da etapa (se configurado)
            if ($etapaDestino->responsavel_padrao) {
                $solicitacao->usuario_responsavel = $etapaDestino->responsavel_padrao;
            } else {
                // Tentar recuperar quem era o responsável antes do "voltar solicitante"
                $historicoResponsavel = SolicitacaoFluxoHistorico::where('solicitacao_id', $solicitacao->id)
                    ->where('fluxo_id', $execucao->fluxo_id)
                    ->where('etapa_anterior_id', $etapaDestino->id)
                    ->whereNotNull('usuario_alteracao')
                    ->orderBy('created_at', 'desc')
                    ->first();

                $solicitacao->usuario_responsavel = $historicoResponsavel?->usuario_alteracao;
            }

            $solicitacao->save();

            // Comentário automático
            $textoComentario = 'Solicitação devolvida ao fluxo pelo solicitante'
                . PHP_EOL . 'De: ' . $departamentoAnterior . ' -> Para: ' . $departamentoEtapa
                . PHP_EOL . 'Etapa: ' . $etapaDestino->nome;

            if ($observacao) {
                $textoComentario .= PHP_EOL . 'Observação: ' . $observacao;
            }

            $solicitacao->comentarios()->create([
                'usuario' => $matriculaUsuario,
                'comentario' => $textoComentario,
            ]);

            // Movimentação
            $this->registrarMovimentacao($solicitacao, $matriculaUsuario, 'Retorno do solicitante', 'Solicitante devolveu ao fluxo — Etapa: ' . $etapaDestino->nome, $etapaDestino);

            // Atualizar etapa de andamento automaticamente (se configurada na etapa de destino)
            if ($etapaDestino->etapa_andamento_id) {
                $this->atualizarEtapaAndamento($solicitacao, $etapaDestino->etapa_andamento_id, $matriculaUsuario);
            }

            // Notificar via Reverb
            $this->notificarTransicao($solicitacao, $departamentoAnterior, $departamentoEtapa, true);

            Log::info('Workflow: Solicitação devolvida ao fluxo pelo solicitante', [
                'solicitacao_id' => $solicitacao->id,
                'etapa_destino' => $etapaDestino->nome,
                'departamento_novo' => $departamentoEtapa,
            ]);

            return [
                'sucesso' => true,
                'mensagem' => 'Solicitação devolvida ao fluxo — Etapa: ' . $etapaDestino->nome . ' (' . $departamentoEtapa . ')',
                'etapa_nova' => $etapaDestino,
            ];
        });
    }

    // ─── CONSULTAS ────────────────────────────────────────────────

    /**
     * Retorna dados completos do fluxo de uma solicitação para exibição no frontend.
     *
     * Quando concluido ou cancelado, a timeline e a etapa_atual sao reconstruidas
     * a partir do historico — assim ficam imunes a edicoes/exclusoes no fluxo.
     */
    public function obterDadosFluxo(Solicitacao $solicitacao): ?array
    {
        $execucao = SolicitacaoFluxoExecucao::where('solicitacao_id', $solicitacao->id)
            ->with(['fluxo', 'etapaAtual.decisoes'])
            ->first();

        if (! $execucao) {
            return null;
        }

        $isConcluido = $execucao->isConcluido();
        $isCancelado = $execucao->isCancelado();
        $isEncerrado = $isConcluido || $isCancelado;

        // Historico de transicoes (sempre carregado)
        $historico = SolicitacaoFluxoHistorico::where('solicitacao_id', $solicitacao->id)
            ->with(['etapaAnterior', 'etapaNova', 'usuarioAlteracao'])
            ->orderBy('created_at', 'asc')
            ->get();

        if ($isEncerrado) {
            // ── Fluxo encerrado: reconstruir tudo pelo historico ──────────────
            // Coletar etapas unicas na ordem em que apareceram (snapshot historico)
            $etapasSnapshot = collect();
            $idsVistos = [];

            foreach ($historico as $mov) {
                if ($mov->etapaAnterior && ! in_array($mov->etapaAnterior->id, $idsVistos)) {
                    $idsVistos[] = $mov->etapaAnterior->id;
                    $etapasSnapshot->push($mov->etapaAnterior);
                }
                if ($mov->etapaNova && ! in_array($mov->etapaNova->id, $idsVistos)) {
                    $idsVistos[] = $mov->etapaNova->id;
                    $etapasSnapshot->push($mov->etapaNova);
                }
            }

            // Se o historico nao tiver etapas suficientes, tenta a etapa_atual ainda existente
            $etapaAtualSnapshot = null;
            if ($historico->isNotEmpty()) {
                $ultimoMov = $historico->last();
                // A ultima etapa visitada e a anterior do ultimo registro (pois etapa_nova e null no fim)
                $etapaAtualSnapshot = $ultimoMov->etapaAnterior ?? $execucao->etapaAtual;
            } else {
                $etapaAtualSnapshot = $execucao->etapaAtual;
            }

            // Garantir que a etapa final aparece na lista mesmo se tiver sido excluida depois
            if ($etapaAtualSnapshot && ! in_array($etapaAtualSnapshot->id, $idsVistos)) {
                $etapasSnapshot->push($etapaAtualSnapshot);
            }

            // Montar mapa de quem atendeu cada etapa
            $atendentes = $this->montarMapaAtendentes($historico);

            // Carregar campos preenchidos de todas as etapas
            $camposPreenchidos = $this->carregarCamposPreenchidosPorEtapa($execucao);

            // Valor bruto do campo permitir_solicitante_avancar na etapa final (pode ser null se etapa excluída)
            $permitirRawEncerrado = $etapaAtualSnapshot->permitir_solicitante_avancar ?? 'N';

            return [
                'fluxo' => $execucao->fluxo,
                'etapa_atual' => $etapaAtualSnapshot,
                'status' => $execucao->status,
                'decisoes' => [],
                'etapas' => $etapasSnapshot->values(),
                'historico' => $historico,
                'atendentes' => $atendentes,
                'is_concluido' => $isConcluido,
                'is_cancelado' => $isCancelado,
                'campos_preenchidos_por_etapa' => $camposPreenchidos,
                // Contrato do Modo Exclusivo do Solicitante (uniforme com o ramo em andamento)
                'permitir_solicitante_avancar_raw' => $permitirRawEncerrado,
                'permitir_solicitante_avancar' => in_array($permitirRawEncerrado, ['S', 'E'], true),
                'is_modo_exclusivo' => $permitirRawEncerrado === 'E',
            ];
        }

        // ── Fluxo em andamento: buscar etapas ativas do fluxo normalmente ──
        $etapas = SolicitacaoFluxoEtapa::where('fluxo_id', $execucao->fluxo_id)
            ->where('ativo', 'S')
            ->orderBy('ordem')
            ->with(['decisoes'])
            ->get();

        // Carrega responsáveis permitidos (tabela pode não existir antes da migration)
        try {
            $etapas->load('responsaveisPermitidos');
        } catch (\Exception $e) {
            // Tabela ainda não criada — ignora
        }

        // Montar mapa de quem atendeu cada etapa (etapa_anterior_id → usuário que moveu)
        $atendentes = $this->montarMapaAtendentes($historico);

        // Carregar campos da etapa atual e valores já preenchidos
        $camposEtapa = [];
        $valoresCampos = [];
        if ($execucao->etapaAtual) {
            $camposEtapa = SolicitacaoFluxoEtapaCampo::where('etapa_fluxo_id', $execucao->etapa_atual_id)
                ->orderBy('ordem')
                ->get();

            $valoresCampos = SolicitacaoFluxoEtapaCampoValor::where('execucao_id', $execucao->id)
                ->whereIn('etapa_campo_id', $camposEtapa->pluck('id'))
                ->get()
                ->keyBy('etapa_campo_id');
        }

        // Carregar campos preenchidos de todas as etapas (passadas)
        $camposPreenchidos = $this->carregarCamposPreenchidosPorEtapa($execucao);

        // Carregar campos configuráveis do assunto (selects) se a etapa tem a flag exibir_campos_assunto
        $camposAssunto = [];
        if ($execucao->etapaAtual && $execucao->etapaAtual->exibir_campos_assunto === 'S' && $solicitacao->assunto_id) {
            $camposAssunto = \App\Models\SolicitacaoSelecao::where('assunto_id', $solicitacao->assunto_id)
                ->where('exibir_atendimento', 'S')
                ->orderBy('ordem')
                ->with('itens')
                ->get()
                ->map(function ($select) {
                    return [
                        'id' => $select->id,
                        'label' => $select->label,
                        'tipo' => $select->tipo,
                        'tipo_data' => $select->tipo_data,
                        'obrigatorio' => $select->obrigatorio,
                        'multiplo' => $select->multiplo,
                        'dias_minimos' => $select->dias_minimos,
                        'placeholder' => $select->observacao,
                        // #12173 - Campos condicionais
                        'campo_pai_id' => $select->campo_pai_id,
                        'valor_condicional' => $select->valor_condicional,
                        'valores' => $select->itens->map(fn($i) => [
                            'code' => $i->id,
                            'label' => $i->valor,
                        ])->toArray(),
                    ];
                })
                ->toArray();
        }

        // Calcular informações de SLA da etapa atual
        $slaInfo = null;
        if ($execucao->etapaAtual && $execucao->etapaAtual->prazo_horas && $execucao->prazo_inicio) {
            $prazoLimite = $execucao->prazo_inicio->copy()->addHours($execucao->etapaAtual->prazo_horas);
            $agora = Carbon::now();
            $slaInfo = [
                'prazo_horas' => $execucao->etapaAtual->prazo_horas,
                'prazo_inicio' => $execucao->prazo_inicio->toIso8601String(),
                'prazo_limite' => $prazoLimite->toIso8601String(),
                'horas_restantes' => max(0, $agora->diffInHours($prazoLimite, false)),
                'atrasado' => $agora->greaterThan($prazoLimite),
            ];
        }

        // Carregar solicitações filhas vinculadas
        $solicitacoesFilhas = SolicitacaoFluxoExecucao::where('solicitacao_pai_id', $solicitacao->id)
            ->with('solicitacao:id,titulo,status')
            ->get()
            ->map(fn($e) => $e->solicitacao)
            ->filter()
            ->values();

        // Carregar solicitação pai (se esta é uma filha)
        $solicitacaoPai = null;
        if ($execucao->solicitacao_pai_id) {
            $solicitacaoPai = Solicitacao::select('id', 'titulo', 'status')->find($execucao->solicitacao_pai_id);
        }

        // Valor bruto do campo permitir_solicitante_avancar na etapa atual (null-safe)
        $permitirRawAtivo = $execucao->etapaAtual->permitir_solicitante_avancar ?? 'N';

        return [
            'fluxo' => $execucao->fluxo,
            'etapa_atual' => $execucao->etapaAtual,
            'status' => $execucao->status,
            'decisoes' => $execucao->etapaAtual->decisoes ?? [],
            'etapas' => $etapas,
            'historico' => $historico,
            'atendentes' => $atendentes,
            'is_concluido' => false,
            'is_cancelado' => false,
            'is_aguardando_solicitante' => $execucao->isAguardandoSolicitante(),
            'motivo_retorno_solicitante' => $execucao->isAguardandoSolicitante()
                ? $historico->last()?->observacao
                : null,
            'campos_etapa' => $camposEtapa,
            'valores_campos' => $valoresCampos,
            'campos_preenchidos_por_etapa' => $camposPreenchidos,
            'campos_assunto' => $camposAssunto,
            'exibir_campos_assunto' => $execucao->etapaAtual?->exibir_campos_assunto === 'S',
            'sla' => $slaInfo,
            'instrucoes' => $execucao->etapaAtual->instrucoes ?? null,
            'permitir_responsavel_externo' => $execucao->etapaAtual->permitir_responsavel_externo === 'S',
            // Contrato do Modo Exclusivo do Solicitante:
            // - permitir_solicitante_avancar (boolean) mantém semântica "o solicitante pode agir" — true para 'S' e 'E'
            // - permitir_solicitante_avancar_raw expõe o valor literal ('N' | 'S' | 'E')
            // - is_modo_exclusivo sinaliza o modo 'E' exclusivo do solicitante
            'permitir_solicitante_avancar' => in_array($permitirRawAtivo, ['S', 'E'], true),
            'permitir_solicitante_avancar_raw' => $permitirRawAtivo,
            'is_modo_exclusivo' => $permitirRawAtivo === 'E',
            'responsaveis_permitidos' => $execucao->etapaAtual
                ? $execucao->etapaAtual->responsaveisPermitidos()
                ->get()
                ->map(function ($r) {
                    $func = \App\Models\Funcionario::where('matricula', $r->matricula)->first(['matricula', 'nome']);
                    return [
                        'matricula' => $r->matricula,
                        'nome' => $func ? mb_convert_encoding($func->nome, 'UTF-8', 'auto') : "Matrícula {$r->matricula}",
                    ];
                })
                : [],
            'solicitacoes_filhas' => $solicitacoesFilhas,
            'solicitacao_pai' => $solicitacaoPai,
        ];
    }

    // ─── HELPERS PRIVADOS ─────────────────────────────────────────

    /**
     * Determina o status da execução a partir do modo da etapa-alvo.
     *
     * - 'aguardando_solicitante' quando a etapa está em Modo Exclusivo do Solicitante ('E').
     * - 'aguardando_decisao'     quando a etapa ('N' ou 'S') possui decisões configuradas.
     * - 'em_andamento'           caso contrário.
     */
    private function statusParaEtapa(SolicitacaoFluxoEtapa $etapa): string
    {
        if ($etapa->permitir_solicitante_avancar === 'E') {
            return 'aguardando_solicitante';
        }

        return $etapa->temDecisoes() ? 'aguardando_decisao' : 'em_andamento';
    }

    /**
     * Rejeita a operação quando a etapa atual é exclusiva do solicitante ('E')
     * e o usuário chamador não é o solicitante da solicitação.
     *
     * Deve ser invocado antes de qualquer escrita (fora da transação) para
     * garantir zero efeito colateral no caso de retorno 403.
     *
     * @return array{sucesso: bool, mensagem: string, etapa_nova: null, http_status: int}|null
     *         null quando autorizado; array de erro quando rejeitado.
     */
    private function guardSolicitante(Solicitacao $solicitacao, SolicitacaoFluxoEtapa $etapaAtual, int $matriculaUsuario): ?array
    {
        if ($etapaAtual->permitir_solicitante_avancar !== 'E') {
            return null;
        }

        if ((int) $solicitacao->usuario_solicitante === $matriculaUsuario) {
            return null;
        }

        return [
            'sucesso' => false,
            'mensagem' => 'Etapa exclusiva do solicitante — apenas o solicitante pode avançar.',
            'etapa_nova' => null,
            'http_status' => 403,
        ];
    }

    /**
     * Carrega todos os campos preenchidos de todas as etapas para uma execução.
     * Retorna array indexado por etapa_fluxo_id: [ etapa_id => [ { label, tipo, valor } ] ]
     */
    private function carregarCamposPreenchidosPorEtapa(SolicitacaoFluxoExecucao $execucao): array
    {
        // Buscar todos os valores preenchidos nesta execução
        $valores = SolicitacaoFluxoEtapaCampoValor::where('execucao_id', $execucao->id)
            ->with('campo')
            ->get();

        $resultado = [];
        foreach ($valores as $valor) {
            if (! $valor->campo) {
                continue;
            }

            $etapaId = $valor->campo->etapa_fluxo_id;

            if (! isset($resultado[$etapaId])) {
                $resultado[$etapaId] = [];
            }

            $resultado[$etapaId][] = [
                'label' => $valor->campo->label,
                'tipo' => $valor->campo->tipo,
                'valor' => $valor->valor,
                'ordem' => $valor->campo->ordem,
            ];
        }

        // Ordenar campos dentro de cada etapa pela ordem
        foreach ($resultado as &$campos) {
            usort($campos, fn($a, $b) => ($a['ordem'] ?? 0) - ($b['ordem'] ?? 0));
        }

        return $resultado;
    }

    /**
     * Valida se todos os campos obrigatórios da etapa foram preenchidos.
     * Retorna ['valido' => true] ou ['valido' => false, 'mensagem' => '...'].
     */
    private function validarCamposObrigatorios(SolicitacaoFluxoExecucao $execucao, SolicitacaoFluxoEtapa $etapa, ?int $decisaoId = null): array
    {
        $query = SolicitacaoFluxoEtapaCampo::where('etapa_fluxo_id', $etapa->id)
            ->where('obrigatorio', 'S');

        // Campos globais (sem decisao_id) + campos da decisão específica
        $query->where(function ($q) use ($decisaoId) {
            $q->whereNull('decisao_id');
            if ($decisaoId) {
                $q->orWhere('decisao_id', $decisaoId);
            }
        });

        $camposObrigatorios = $query->get();

        if ($camposObrigatorios->isEmpty()) {
            return ['valido' => true];
        }

        $valoresPreenchidos = SolicitacaoFluxoEtapaCampoValor::where('execucao_id', $execucao->id)
            ->whereIn('etapa_campo_id', $camposObrigatorios->pluck('id'))
            ->get()
            ->keyBy('etapa_campo_id');

        $camposFaltando = [];
        foreach ($camposObrigatorios as $campo) {
            $valor = $valoresPreenchidos->get($campo->id);
            if (! $valor || $valor->valor === null || trim($valor->valor) === '') {
                $camposFaltando[] = $campo->label;
            }
        }

        if (! empty($camposFaltando)) {
            $lista = implode(', ', $camposFaltando);

            return [
                'valido' => false,
                'mensagem' => 'Preencha os campos obrigatórios antes de avançar: ' . $lista,
            ];
        }

        return ['valido' => true];
    }

    /**
     * Monta mapa de quem atendeu cada etapa do fluxo.
     * Retorna array indexado por etapa_id com dados do atendente (nome, matricula).
     * A lógica é: quem moveu DE uma etapa (etapa_anterior_id) é quem a atendeu.
     */
    private function montarMapaAtendentes($historico): array
    {
        $atendentes = [];

        foreach ($historico as $mov) {
            if ($mov->etapa_anterior_id && $mov->usuarioAlteracao) {
                $usuario = $mov->usuarioAlteracao;
                $nomeCompleto = $usuario->nome ?? 'Usuário';
                $partes = explode(' ', trim($nomeCompleto));
                $nomeCurto = $partes[0];
                if (count($partes) > 1) {
                    $nomeCurto .= ' ' . end($partes);
                }

                $atendentes[$mov->etapa_anterior_id] = [
                    'matricula' => $usuario->matricula,
                    'nome' => $nomeCompleto,
                    'nome_curto' => $nomeCurto,
                ];
            }
        }

        return $atendentes;
    }

    private function registrarMovimentacao(
        Solicitacao $solicitacao,
        int $matriculaUsuario,
        string $tipo,
        string $descricao,
        ?SolicitacaoFluxoEtapa $etapa = null
    ): void {
        $dados_extras = null;
        if ($etapa) {
            $dados_extras = [
                'fluxo_etapa_id' => $etapa->id,
                'fluxo_etapa_nome' => $etapa->nome,
                'fluxo_etapa_cor' => $etapa->cor,
                'fluxo_etapa_icone' => $etapa->icone,
                'fluxo_departamento' => $etapa->departamento,
            ];
        }

        SolicitacaoMov::create([
            'solicitacao_id' => $solicitacao->id,
            'tipo_movimentacao' => $tipo,
            'descricao' => $descricao,
            'usuario_movimentacao' => $matriculaUsuario,
            'dados_extras' => $dados_extras ? json_encode($dados_extras) : null,
        ]);
    }

    /**
     * Atualiza a etapa de andamento da solicitação automaticamente
     * quando o workflow avança para uma etapa que tem etapa_andamento_id configurada.
     */
    private function atualizarEtapaAndamento(
        Solicitacao $solicitacao,
        int $etapaAndamentoId,
        int $matriculaUsuario
    ): void {
        $etapaAtualAnterior = \App\Models\SolicitacaoEtapaAtual::where('solicitacao_id', $solicitacao->id)->first();
        $etapaAnteriorId = $etapaAtualAnterior?->etapa_id;

        // Não atualizar se já está na mesma etapa
        if ($etapaAnteriorId == $etapaAndamentoId) {
            return;
        }

        \App\Models\SolicitacaoEtapaAtual::updateOrCreate(
            ['solicitacao_id' => $solicitacao->id],
            [
                'etapa_id' => $etapaAndamentoId,
                'usuario_alteracao' => $matriculaUsuario,
                'data_alteracao' => now(),
            ]
        );

        \App\Models\SolicitacaoEtapaHistorico::create([
            'solicitacao_id' => $solicitacao->id,
            'etapa_anterior_id' => $etapaAnteriorId,
            'etapa_nova_id' => $etapaAndamentoId,
            'usuario_alteracao' => $matriculaUsuario,
            'observacao' => 'Atualização automática via workflow',
        ]);

        // Registrar movimentação na timeline
        $etapaNova = \App\Models\SolicitacaoEtapa::find($etapaAndamentoId);
        $etapaAnteriorNome = $etapaAnteriorId
            ? (\App\Models\SolicitacaoEtapa::find($etapaAnteriorId)?->nome ?? 'Não definida')
            : 'Não definida';

        SolicitacaoMov::create([
            'solicitacao_id' => $solicitacao->id,
            'tipo_movimentacao' => 'Etapa alterada',
            'descricao' => 'Etapa de andamento alterada automaticamente de "' . $etapaAnteriorNome . '" para "' . ($etapaNova->nome ?? '?') . '" (via workflow)',
            'usuario_movimentacao' => $matriculaUsuario,
            'dados_extras' => json_encode([
                'etapa_id' => $etapaAndamentoId,
                'etapa_nome' => $etapaNova->nome ?? null,
                'etapa_cor' => $etapaNova->cor ?? null,
                'etapa_icone' => $etapaNova->icone ?? null,
            ]),
        ]);

        Log::info('Workflow: Etapa de andamento atualizada automaticamente', [
            'solicitacao_id' => $solicitacao->id,
            'etapa_andamento_id' => $etapaAndamentoId,
            'etapa_anterior_id' => $etapaAnteriorId,
        ]);
    }

    private function notificarTransicao(
        Solicitacao $solicitacao,
        ?string $departamentoAnterior,
        ?string $departamentoNovo,
        bool $mudouDepartamento
    ): void {
        try {
            $this->reverbService->notificarAtualizacao(
                $solicitacao->toArray(),
                $solicitacao->departamento_responsavel,
                'fluxo_workflow'
            );

            // Notificar saída no departamento anterior
            if ($mudouDepartamento && $departamentoAnterior && $departamentoAnterior !== $departamentoNovo) {
                $this->reverbService->notificarAtualizacao(
                    $solicitacao->toArray(),
                    $departamentoAnterior,
                    'transferencia_saida'
                );
            }

            // Notificar o solicitante
            if ($solicitacao->usuario_solicitante) {
                $this->reverbService->notificarUsuario(
                    (string) $solicitacao->usuario_solicitante,
                    'fluxo_workflow',
                    'Fluxo atualizado',
                    'A solicitação #' . $solicitacao->id . ' teve seu fluxo atualizado',
                    ['solicitacao_id' => $solicitacao->id]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Workflow: Falha ao notificar via Reverb', [
                'solicitacao_id' => $solicitacao->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
