<?php

namespace App\Services\Notifications\Support;

use Illuminate\Support\Facades\DB;

/**
 * Resolve e formata o telefone de um colaborador para envio via WhatsApp (Z-API).
 *
 * Replica a lógica já validada na Roleta do Aniversariante
 * (RoletaAniversarianteController::buscarTelefoneColaborador / formatarTelefoneWhatsapp),
 * que busca o número em 3 fontes em ordem de prioridade. O WhatsappChannel antigo
 * buscava apenas em pcempr.celular, o que fazia falhar para quem tinha o número
 * cadastrado só no perfil da intranet ou no campo fone.
 */
trait ResolveTelefoneWhatsapp
{
    /**
     * Busca o telefone do colaborador com prioridade:
     *   1. intranet_usuario.telefone (telefone do perfil da intranet)
     *   2. pcempr.celular (cadastro Oracle/Winthor)
     *   3. pcempr.fone (fallback — mesmo campo usado na tela de perfil)
     */
    protected function buscarTelefoneColaborador(string $matricula): ?string
    {
        // 1. Telefone do perfil da intranet
        $telefone = DB::table('intranet_usuario')
            ->where('matricula', $matricula)
            ->value('telefone');
        if (! empty($telefone)) {
            return $telefone;
        }

        // 2. Celular do cadastro Oracle
        $celular = DB::table('pcempr')
            ->where('matricula', $matricula)
            ->value('celular');
        if (! empty($celular)) {
            return $celular;
        }

        // 3. Fone do cadastro Oracle (fallback)
        $fone = DB::table('pcempr')
            ->where('matricula', $matricula)
            ->where('situacao', 'A')
            ->value('fone');
        if (! empty($fone)) {
            return $fone;
        }

        return null;
    }

    /**
     * Formata o telefone para o padrão esperado pela Z-API.
     * Remove caracteres especiais e adiciona DDI 55 se necessário.
     * Retorna null se o telefone for inválido.
     */
    protected function formatarTelefoneWhatsapp(string $telefone): ?string
    {
        $numeros = preg_replace('/\D/', '', $telefone);

        if (empty($numeros)) {
            return null;
        }

        // Já tem DDI 55 e tamanho válido
        if (str_starts_with($numeros, '55') && strlen($numeros) >= 12 && strlen($numeros) <= 13) {
            return $numeros;
        }

        // DDD + número (10-11 dígitos) → adiciona DDI 55
        if (strlen($numeros) >= 10 && strlen($numeros) <= 11) {
            return '55' . $numeros;
        }

        // Menos que 10 dígitos = sem DDD = inválido
        if (strlen($numeros) < 10) {
            return null;
        }

        return $numeros;
    }
}
