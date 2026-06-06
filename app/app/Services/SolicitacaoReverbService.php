<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service para enviar notificações em tempo real via Reverb
 * 
 * Utiliza o helper reverbSend() seguindo o padrão do projeto
 * 
 * Canais:
 * - public.intranet.solicitacoes.departamento.{departamento} - eventos do departamento
 * - public.intranet.solicitacoes.item.{id} - eventos de uma solicitação específica
 * - public.intranet.solicitacoes.usuario.{matricula} - notificações pessoais
 */
class SolicitacaoReverbService
{
  /**
   * Sanitiza nome do departamento para usar como canal Reverb
   * Remove espaços, acentos e caracteres especiais
   */
  protected function sanitizarNomeCanal(string $nome): string
  {
    // Converter para minúsculas e remover acentos
    $nome = Str::ascii(strtolower($nome));
    // Substituir espaços e caracteres especiais por underscore
    $nome = preg_replace('/[^a-z0-9]+/', '_', $nome);
    // Remover underscores duplicados e das pontas
    return trim(preg_replace('/_+/', '_', $nome), '_');
  }

  /**
   * Envia notificação de nova solicitação criada
   *
   * @param array $solicitacao Dados da solicitação
   * @param string $departamento Nome do departamento
   */
  public function notificarCriacao(array $solicitacao, string $departamento): bool
  {
    $departamentoSanitizado = $this->sanitizarNomeCanal($departamento);
    $canal = "public.intranet.solicitacoes.departamento.{$departamentoSanitizado}";

    $dados = [
      'solicitacao' => $solicitacao,
      'departamento' => $departamento,
      'timestamp' => now()->toISOString(),
    ];

    $resultado = reverbSend($canal, 'criada', $dados);

    if ($resultado) {
      Log::info('Reverb: Solicitação criada', [
        'solicitacao_id' => $solicitacao['id'] ?? null,
        'departamento' => $departamento,
        'canal' => $canal,
      ]);
    } else {
      Log::error('Reverb: Erro ao notificar criação', [
        'solicitacao_id' => $solicitacao['id'] ?? null,
        'departamento' => $departamento,
      ]);
    }

    return $resultado;
  }

  /**
   * Envia notificação de atualização de solicitação
   *
   * @param array $solicitacao Dados da solicitação
   * @param string $departamento Nome do departamento
   * @param string $tipoAtualizacao Tipo: status, prioridade, responsavel, etc
   * @param string|null $matriculaSolicitante Matrícula do solicitante para notificar também
   */
  public function notificarAtualizacao(array $solicitacao, string $departamento, string $tipoAtualizacao = 'geral', ?string $matriculaSolicitante = null): bool
  {
    $departamentoSanitizado = $this->sanitizarNomeCanal($departamento);

    $dados = [
      'solicitacao' => $solicitacao,
      'departamento' => $departamento,
      'tipo_atualizacao' => $tipoAtualizacao,
      'timestamp' => now()->toISOString(),
    ];

    // Envia para o canal do departamento
    $canalDepartamento = "public.intranet.solicitacoes.departamento.{$departamentoSanitizado}";
    $resultado1 = reverbSend($canalDepartamento, 'atualizada', $dados);

    // Envia para o canal da solicitação específica
    $solicitacaoId = $solicitacao['id'] ?? null;
    $resultado2 = false;
    if ($solicitacaoId) {
      $canalItem = "public.intranet.solicitacoes.item.{$solicitacaoId}";
      $resultado2 = reverbSend($canalItem, 'atualizada', $dados);
    }

    // ✅ Envia também para o canal do solicitante (Minhas Solicitações)
    $resultado3 = false;
    if ($matriculaSolicitante) {
      $canalUsuario = "public.intranet.solicitacoes.usuario.{$matriculaSolicitante}";
      $resultado3 = reverbSend($canalUsuario, 'atualizada', $dados);
    }

    $sucesso = $resultado1 || $resultado2 || $resultado3;

    if ($sucesso) {
      Log::info('Reverb: Solicitação atualizada', [
        'solicitacao_id' => $solicitacaoId,
        'departamento' => $departamento,
        'tipo' => $tipoAtualizacao,
        'solicitante' => $matriculaSolicitante,
      ]);
    } else {
      Log::error('Reverb: Erro ao notificar atualização', [
        'solicitacao_id' => $solicitacaoId,
        'departamento' => $departamento,
      ]);
    }

    return $sucesso;
  }

  /**
   * Envia notificação de novo comentário
   *
   * @param int $solicitacaoId ID da solicitação
   * @param array $comentario Dados do comentário
   * @param string $departamento Nome do departamento
   */
  public function notificarComentario(int $solicitacaoId, array $comentario, string $departamento): bool
  {
    $canal = "public.intranet.solicitacoes.item.{$solicitacaoId}";

    $dados = [
      'solicitacao_id' => $solicitacaoId,
      'comentario' => $comentario,
      'departamento' => $departamento,
      'timestamp' => now()->toISOString(),
    ];

    $resultado = reverbSend($canal, 'comentario', $dados);

    if ($resultado) {
      Log::info('Reverb: Comentário adicionado', [
        'solicitacao_id' => $solicitacaoId,
        'departamento' => $departamento,
      ]);
    } else {
      Log::error('Reverb: Erro ao notificar comentário', [
        'solicitacao_id' => $solicitacaoId,
      ]);
    }

    return $resultado;
  }

  /**
   * Envia notificação de comentário excluído
   *
   * @param int $solicitacaoId ID da solicitação
   * @param int $comentarioId ID do comentário excluído
   * @param string $departamento Nome do departamento
   */
  public function notificarComentarioExcluido(int $solicitacaoId, int $comentarioId, string $departamento): bool
  {
    $canal = "public.intranet.solicitacoes.item.{$solicitacaoId}";

    $dados = [
      'solicitacao_id' => $solicitacaoId,
      'comentario_id' => $comentarioId,
      'departamento' => $departamento,
      'timestamp' => now()->toISOString(),
    ];

    $resultado = reverbSend($canal, 'comentario_excluido', $dados);

    if ($resultado) {
      Log::info('Reverb: Comentário excluído', [
        'solicitacao_id' => $solicitacaoId,
        'comentario_id' => $comentarioId,
      ]);
    } else {
      Log::error('Reverb: Erro ao notificar exclusão de comentário', [
        'solicitacao_id' => $solicitacaoId,
        'comentario_id' => $comentarioId,
      ]);
    }

    return $resultado;
  }

  /**
   * Envia notificação pessoal para um usuário
   *
   * @param string $matricula Matrícula do usuário
   * @param string $tipo Tipo: nova_solicitacao, atribuicao, comentario, aprovacao, status
   * @param string $titulo Título da notificação
   * @param string $mensagem Mensagem
   * @param array|null $dados Dados extras
   */
  public function notificarUsuario(
    string $matricula,
    string $tipo,
    string $titulo,
    string $mensagem,
    ?array $dados = null
  ): bool {
    $canal = "public.intranet.solicitacoes.usuario.{$matricula}";

    $payload = [
      'tipo' => $tipo,
      'titulo' => $titulo,
      'mensagem' => $mensagem,
      'dados' => $dados,
      'timestamp' => now()->toISOString(),
    ];

    $resultado = reverbSend($canal, 'notificacao', $payload);

    if ($resultado) {
      Log::info('Reverb: Notificação para usuário', [
        'matricula' => $matricula,
        'tipo' => $tipo,
      ]);
    } else {
      Log::error('Reverb: Erro ao notificar usuário', [
        'matricula' => $matricula,
        'tipo' => $tipo,
      ]);
    }

    return $resultado;
  }

  /**
   * Notifica responsável sobre nova atribuição
   */
  public function notificarAtribuicao(array $solicitacao, string $matriculaResponsavel): bool
  {
    return $this->notificarUsuario(
      $matriculaResponsavel,
      'atribuicao',
      'Nova Solicitação Atribuída',
      "A solicitação #{$solicitacao['id']} foi atribuída a você.",
      [
        'solicitacao_id' => $solicitacao['id'],
        'titulo' => $solicitacao['titulo'] ?? '',
        'departamento' => $solicitacao['departamento_responsavel'] ?? '',
      ]
    );
  }

  /**
   * Notifica solicitante sobre mudança de status
   */
  public function notificarMudancaStatus(array $solicitacao, string $matriculaSolicitante, string $novoStatus): bool
  {
    $statusFormatado = ucfirst(str_replace('_', ' ', $novoStatus));

    return $this->notificarUsuario(
      $matriculaSolicitante,
      'status',
      'Status Atualizado',
      "Sua solicitação #{$solicitacao['id']} está agora: {$statusFormatado}",
      [
        'solicitacao_id' => $solicitacao['id'],
        'status' => $novoStatus,
      ]
    );
  }

  /**
   * Notifica sobre nova aprovação pendente
   */
  public function notificarAprovacaoPendente(array $solicitacao, string $matriculaAprovador): bool
  {
    return $this->notificarUsuario(
      $matriculaAprovador,
      'aprovacao',
      'Aprovação Solicitada',
      "Você tem uma nova aprovação pendente para a solicitação #{$solicitacao['id']}.",
      [
        'solicitacao_id' => $solicitacao['id'],
        'titulo' => $solicitacao['titulo'] ?? '',
      ]
    );
  }
}
