<?php

namespace App\Services\Senior;

use App\Models\Payable;
use App\Services\AuditLogger;

/**
 * Status_Mapper (requirement 8): traduz a situação do título na Senior (`sitTit`)
 * para um dos status internos de Payable::STATUS_LABELS.
 *
 * Pura e sem chamadas externas (req 8.6). O mapa abaixo é PROVISÓRIO até validarmos
 * os códigos reais de `sitTit` numa chamada real à Senior; o que não casar cai em
 * `pendente` (req 8.4) e o valor original é preservado em senior_situacao_original.
 */
class StatusMapper
{
    /** Mapa sitTit (Senior) → status interno. Chaves em maiúsculas. */
    public const MAP = [
        'NOR' => 'pendente',     // normal / em aberto
        'ABE' => 'pendente',     // aberto
        'PEN' => 'pendente',     // pendente
        'LIB' => 'aprovado',     // liberado
        'APR' => 'aprovado',     // aprovado
        'PAG' => 'pago',         // pago
        'LIQ' => 'pago',         // liquidado
        'BAI' => 'pago',         // baixado
        'REP' => 'reprovado',    // reprovado
        'CAN' => 'reprovado',    // cancelado
    ];

    /**
     * Traduz a situação da Senior para o status interno.
     * Para sitTit ausente/vazio/nulo: 'pendente' + log de auditoria (req 8.5).
     */
    public function map(?string $sitTit): string
    {
        if ($sitTit === null || trim($sitTit) === '') {
            AuditLogger::log(
                event: 'contas_pagar.sync.situacao_indefinida',
                module: 'financeiro.contas_pagar',
                description: 'Título sincronizado sem situação (sitTit) definida — status interno definido como pendente',
            );

            return 'pendente';
        }

        $key = strtoupper(trim($sitTit));
        $status = self::MAP[$key] ?? 'pendente';

        // Garante que o status resultante é válido (defensivo).
        return array_key_exists($status, Payable::STATUS_LABELS) ? $status : 'pendente';
    }
}
