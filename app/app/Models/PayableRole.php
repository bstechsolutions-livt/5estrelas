<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Associação papel -> usuário da Alçada do Contas a Pagar (Alcada_CP).
 *
 * Cada linha = um usuário responsável por um papel do fluxo de pagamento.
 * É a fonte de verdade de quem executa cada ação (quem paga/concilia/assina),
 * editável em tempo real pela tela administrativa, sem deploy.
 *
 * Obs.: "papel" aqui é um conceito de NEGÓCIO do fluxo financeiro, não um papel
 * de autenticação/autorização (o sistema usa permissões diretas, sem roles de auth).
 */
class PayableRole extends Model
{
    protected $fillable = ['role', 'user_id'];

    /** Papéis do fluxo de pagamento (slug => rótulo). */
    public const ROLES = [
        'pagador' => 'Pagador',
        'conciliador' => 'Conciliador',
        'assinante' => 'Assinante',
    ];

    /** Descrição curta do que cada papel faz (exibida na tela de alçada). */
    public const ROLE_DESCRIPTIONS = [
        'pagador' => 'Registra o pagamento dos títulos aprovados.',
        'conciliador' => 'Concilia os pagamentos com o extrato bancário (Spec 2).',
        'assinante' => 'Assina e encerra a conciliação — 2ª assinatura (Spec 3).',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
