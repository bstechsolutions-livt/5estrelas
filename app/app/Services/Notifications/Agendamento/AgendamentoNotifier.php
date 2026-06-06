<?php

namespace App\Services\Notifications\Agendamento;

use App\Http\Controllers\UtilController;
use App\Models\SolicitacaoAgendamento;
use App\Services\Notifications\NotificationCenter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dispara notificações de agendamento pelo NotificationCenter.
 *
 * Regra de negócio:
 * - Destinatário é SEMPRE o técnico do agendamento (mat_responsavel).
 * - Uma única notificação consolidada por agendamento (não uma por solicitação).
 * - Em troca de técnico (atualização), avisa o anterior (saiu) e o novo (entrou).
 * - Quem dispara a ação (user_cria) não é notificado.
 *
 * A cascata (in-app/push imediato → WhatsApp → e-mail) e o respeito às
 * preferências do usuário são responsabilidade do NotificationCenter.
 */
class AgendamentoNotifier
{
    public function __construct(private NotificationCenter $center) {}

    /**
     * Matrícula de quem disparou a ação (usuário logado).
     * Usada para não notificar a própria pessoa que fez a alteração.
     */
    private function atorMatricula(): ?string
    {
        try {
            return session('auth')->matricula ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Remove o ator (quem fez a ação) da lista de destinatários.
     */
    private function semOAtor(array $userIds): array
    {
        $ator = $this->atorMatricula();
        if (empty($ator)) {
            return $userIds;
        }
        return array_values(array_filter(
            $userIds,
            fn ($id) => (string) $id !== (string) $ator
        ));
    }

    /**
     * Agendamento criado → notifica o técnico.
     */
    public function notificarCriacao(SolicitacaoAgendamento $agendamento): void
    {
        $tecnico = $agendamento->mat_responsavel;
        if (empty($tecnico)) {
            return;
        }

        // Não notifica se quem criou é o próprio técnico
        $destinatarios = $this->semOAtor([$tecnico]);
        if (empty($destinatarios)) {
            return;
        }

        $ctx = $this->montarContexto($agendamento);

        $this->disparar(
            'agendamento.criado',
            $destinatarios,
            'Novo agendamento',
            "Você tem um novo agendamento.\n\n" . $this->blocoDetalhes($ctx),
            $ctx
        );
    }

    /**
     * Agendamento atualizado → notifica o técnico atual.
     * Se o técnico mudou, avisa o anterior (saiu) e o novo (entrou).
     *
     * @param int|string|null $tecnicoAnterior matrícula do técnico antes da atualização
     * @param array $dadosAnteriores ['filial', 'data_agendamento', 'observacao'] antes da edição
     */
    public function notificarAtualizacao(SolicitacaoAgendamento $agendamento, $tecnicoAnterior = null, array $dadosAnteriores = []): void
    {
        $tecnicoAtual = $agendamento->mat_responsavel;
        $ctx = $this->montarContexto($agendamento);

        $trocouTecnico = $tecnicoAnterior && (string) $tecnicoAnterior !== (string) $tecnicoAtual;

        if ($trocouTecnico) {
            // Técnico anterior saiu: cancela os avisos pendentes dele deste agendamento
            $this->cancelarPendentesDoAgendamento($agendamento->id, (string) $tecnicoAnterior);

            // Nome do novo responsável (para informar quem assumiu)
            $nomeNovoResponsavel = ! empty($tecnicoAtual)
                ? UtilController::nomeFuncionario($tecnicoAtual)
                : null;

            $corpoReatribuido = "Um agendamento foi reatribuído para outro responsável.";
            if ($nomeNovoResponsavel) {
                $corpoReatribuido = "Seu agendamento foi reatribuído para {$nomeNovoResponsavel}.";
            }

            // Técnico anterior: saiu do agendamento (não notifica se foi ele quem fez)
            $destAnterior = $this->semOAtor([(string) $tecnicoAnterior]);
            if (! empty($destAnterior)) {
                $this->disparar(
                    'agendamento.cancelado',
                    $destAnterior,
                    'Agendamento reatribuído',
                    $corpoReatribuido . "\n\n" . $this->blocoDetalhes($ctx),
                    $ctx
                );
            }

            // Técnico novo: entrou no agendamento (não notifica se foi ele quem fez)
            if (! empty($tecnicoAtual)) {
                $this->cancelarPendentesDoAgendamento($agendamento->id, (string) $tecnicoAtual);
                $destNovo = $this->semOAtor([(string) $tecnicoAtual]);
                if (! empty($destNovo)) {
                    $this->disparar(
                        'agendamento.criado',
                        $destNovo,
                        'Novo agendamento',
                        "Você tem um novo agendamento.\n\n" . $this->blocoDetalhes($ctx),
                        $ctx
                    );
                }
            }
            return;
        }

        // Mesmo técnico: apenas atualização de dados.
        // Monta o texto destacando o que mudou (filial, data/hora, observação).
        if (! empty($tecnicoAtual)) {
            // Cancela avisos pendentes anteriores deste agendamento (ex: "novo
            // agendamento" ainda na fila) — a atualização substitui o que estava pendente.
            $this->cancelarPendentesDoAgendamento($agendamento->id, (string) $tecnicoAtual);

            // Não notifica se quem atualizou é o próprio técnico
            $destAtual = $this->semOAtor([(string) $tecnicoAtual]);
            if (empty($destAtual)) {
                return;
            }

            $mudancas = $this->descreverMudancas($agendamento, $dadosAnteriores);

            $corpo = $mudancas !== ''
                ? "Seu agendamento foi atualizado.\n\n{$mudancas}"
                : "Seu agendamento em {$ctx['filial']} foi atualizado para {$ctx['data']} às {$ctx['hora']}.{$ctx['sufixoSolic']}";

            $this->disparar(
                'agendamento.atualizado',
                $destAtual,
                'Agendamento atualizado',
                $corpo,
                $ctx
            );
        }
    }

    /**
     * Agendamento cancelado → notifica o técnico.
     */
    public function notificarCancelamento(SolicitacaoAgendamento $agendamento): void
    {
        $tecnico = $agendamento->mat_responsavel;
        if (empty($tecnico)) {
            return;
        }

        // Cancela avisos pendentes anteriores deste agendamento (ex: o "novo
        // agendamento" via WhatsApp/e-mail que ainda não disparou) — sempre,
        // mesmo que o próprio técnico tenha cancelado.
        $this->cancelarPendentesDoAgendamento($agendamento->id, (string) $tecnico);

        // Não notifica se quem cancelou é o próprio técnico
        $destinatarios = $this->semOAtor([(string) $tecnico]);
        if (empty($destinatarios)) {
            return;
        }

        $ctx = $this->montarContexto($agendamento);

        $this->disparar(
            'agendamento.cancelado',
            $destinatarios,
            'Agendamento cancelado',
            "Seu agendamento foi cancelado.\n\n" . $this->blocoDetalhes($ctx),
            $ctx
        );
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * Cancela (expira) as entregas ainda PENDENTES de notificações anteriores
     * do mesmo agendamento. Usado quando o agendamento muda de estado
     * (cancelado / reatribuído / atualizado) para o técnico não receber, via
     * WhatsApp/e-mail com atraso, um aviso de algo que já mudou.
     *
     * Só mexe em entregas 'pending' (ainda na fila). As já enviadas (in-app no
     * sininho) permanecem como histórico.
     */
    private function cancelarPendentesDoAgendamento($agendamentoId, ?string $userId = null): void
    {
        if (empty($agendamentoId)) {
            return;
        }

        try {
            // Notificações que referenciam este agendamento (via JSON data.agendamento_id)
            $notifIds = DB::table('notifications')
                ->whereRaw("JSON_VALUE(data, '$.agendamento_id') = ?", [(string) $agendamentoId])
                ->pluck('id');

            if ($notifIds->isEmpty()) {
                return;
            }

            $query = DB::table('notification_deliveries')
                ->whereIn('notification_id', $notifIds)
                ->where('status', 'pending');

            if (! empty($userId)) {
                $query->where('user_id', (string) $userId);
            }

            $query->update(['status' => 'expired', 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('AgendamentoNotifier: falha ao cancelar pendentes do agendamento', [
                'agendamento_id' => $agendamentoId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Monta um bloco de detalhes padronizado (filial, data/hora, solicitações)
     * com quebras de linha, para deixar a notificação organizada.
     */
    private function blocoDetalhes(array $ctx): string
    {
        $linhas = [
            "Filial: {$ctx['filial']}",
            "Data e hora: {$ctx['data']} às {$ctx['hora']}",
        ];

        if (! empty($ctx['qtd_solic']) && $ctx['qtd_solic'] > 0) {
            $linhas[] = $ctx['qtd_solic'] . ' ' . ($ctx['qtd_solic'] === 1 ? 'solicitação' : 'solicitações');
        }

        return implode("\n", $linhas);
    }

    /**
     * Compara os dados anteriores com os atuais e descreve em texto o que mudou.
     * Ex: "Filial alterada para 91 - BIGLAR PIO XII. Data/hora alterada para 04/06/2026 às 15:30. Observação atualizada."
     */
    private function descreverMudancas(SolicitacaoAgendamento $agendamento, array $dadosAnteriores): string
    {
        if (empty($dadosAnteriores)) {
            return '';
        }

        $partes = [];

        // Filial
        if (array_key_exists('filial', $dadosAnteriores)
            && (string) $dadosAnteriores['filial'] !== (string) $agendamento->filial) {
            $filialNova = $agendamento->filial
                ? ($agendamento->filial . ' - ' . UtilController::nomeFilial($agendamento->filial))
                : 'não informada';
            $filialAntiga = $dadosAnteriores['filial']
                ? ($dadosAnteriores['filial'] . ' - ' . UtilController::nomeFilial($dadosAnteriores['filial']))
                : 'não informada';
            $partes[] = "Filial:\n• Antes: {$filialAntiga}\n• Agora: {$filialNova}";
        }

        // Data / hora
        if (array_key_exists('data_agendamento', $dadosAnteriores)) {
            $antesData = $dadosAnteriores['data_agendamento']
                ? Carbon::parse($dadosAnteriores['data_agendamento'])->format('d/m/Y \à\s H:i')
                : null;
            $depoisData = $agendamento->data_agendamento
                ? Carbon::parse($agendamento->data_agendamento)->format('d/m/Y \à\s H:i')
                : null;

            if ($antesData !== $depoisData && $depoisData) {
                $partes[] = $antesData
                    ? "Data e hora:\n• Antes: {$antesData}\n• Agora: {$depoisData}"
                    : "Data e hora:\n• Agora: {$depoisData}";
            }
        }

        // Observação
        if (array_key_exists('observacao', $dadosAnteriores)
            && trim((string) $dadosAnteriores['observacao']) !== trim((string) $agendamento->observacao)) {
            $partes[] = empty(trim((string) $agendamento->observacao))
                ? 'Observação: removida.'
                : 'Observação: atualizada.';
        }

        return implode("\n\n", $partes);
    }

    /**
     * Monta os dados de contexto do agendamento (filial, data, hora, qtd solicitações).
     */
    private function montarContexto(SolicitacaoAgendamento $agendamento): array
    {
        $nomeFilial = $agendamento->filial
            ? ($agendamento->filial . ' - ' . UtilController::nomeFilial($agendamento->filial))
            : 'filial não informada';

        $dataAg = $agendamento->data_agendamento ? Carbon::parse($agendamento->data_agendamento) : null;
        $data = $dataAg ? $dataAg->format('d/m/Y') : 'data a definir';
        $hora = $dataAg ? $dataAg->format('H:i') : '';

        // Quantidade de solicitações vinculadas (sem o scope que filtra por departamento)
        $qtdSolic = DB::table('intranet_solicitacao_ag_sol')
            ->where('agendamento_id', $agendamento->id)
            ->count();

        $sufixoSolic = $qtdSolic > 0
            ? ' (' . $qtdSolic . ' ' . ($qtdSolic === 1 ? 'solicitação' : 'solicitações') . ')'
            : '';

        return [
            'filial'       => $nomeFilial,
            'data'         => $data,
            'hora'         => $hora,
            'qtd_solic'    => $qtdSolic,
            'sufixoSolic'  => $sufixoSolic,
            'agendamento_id' => $agendamento->id,
            'link'         => '/solicitacoes/agendamento?agendamento=' . $agendamento->id,
        ];
    }

    /**
     * Chama o NotificationCenter com tratamento de erro (notificação nunca quebra o fluxo).
     */
    private function disparar(string $eventSlug, array $userIds, string $titulo, string $corpo, array $ctx): void
    {
        try {
            $this->center->dispatch(
                $eventSlug,
                [
                    'title' => $titulo,
                    'body'  => $corpo,
                    'data'  => [
                        'link'           => $ctx['link'],
                        'type'           => 'agendamento',
                        'agendamento_id' => $ctx['agendamento_id'],
                        'filial'         => $ctx['filial'],
                        'data'           => $ctx['data'],
                        'hora'           => $ctx['hora'],
                        'qtd_solic'      => $ctx['qtd_solic'],
                    ],
                ],
                $userIds,
                [
                    'priority' => 'normal',
                    'origin'   => 'AgendamentoNotifier',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('AgendamentoNotifier: falha ao disparar notificação', [
                'event'    => $eventSlug,
                'user_ids' => $userIds,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
