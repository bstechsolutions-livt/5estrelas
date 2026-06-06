<?php

namespace App\Http\Controllers;

/**
 * Adaptador de compatibilidade para o módulo de Solicitações portado da Biglar.
 *
 * O código portado chama `NotificacaoController::enviarNotificacaoStatic(...)`.
 * Na origem (ct-intranet/producao) esse fluxo foi DESATIVADO em 14/05/2026
 * (refatoração do sistema de notificações — "sininho" desligado), retornando
 * imediatamente sem efeito. Aqui no 5 Estrelas a notificação in-app/realtime do
 * módulo de solicitações é feita via SolicitacaoReverbService (tempo real) e pelo
 * sistema próprio de notificações (NotificationService), então este adaptador é
 * intencionalmente um no-op, mantendo o comportamento idêntico ao da produção.
 */
class NotificacaoController extends Controller
{
    public static function enviarNotificacaoStatic(
        $titulo,
        $mensagem,
        $origem,
        $canais,
        $destinatarios,
        $menu = null,
        $submenu = null,
        $link = null,
        $layout = null,
        $id_caminho_img = null
    ) {
        // Notificações por este canal estão desativadas (paridade com produção).
        return null;
    }
}
