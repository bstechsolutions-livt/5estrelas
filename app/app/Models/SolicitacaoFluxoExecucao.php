<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Estado atual de uma solicitação dentro de um fluxo.
 *
 * Cada solicitação pode ter no máximo 1 registro de execução
 * (constraint unique em solicitacao_id).
 */
class SolicitacaoFluxoExecucao extends Model
{
    protected $table = 'intranet_solicitacao_fluxo_execucao';

    protected $fillable = [
        'solicitacao_id',
        'fluxo_id',
        'etapa_atual_id',
        'status',
        'prazo_inicio',
        'solicitacao_pai_id',
        'usuario_alteracao',
    ];

    protected $casts = [
        'solicitacao_id' => 'integer',
        'fluxo_id' => 'integer',
        'etapa_atual_id' => 'integer',
        'solicitacao_pai_id' => 'integer',
        'usuario_alteracao' => 'integer',
        'prazo_inicio' => 'datetime',
    ];

    // ─── Relacionamentos ───────────────────────────────────────

    public function solicitacao()
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_id');
    }

    public function fluxo()
    {
        return $this->belongsTo(SolicitacaoFluxo::class, 'fluxo_id');
    }

    public function etapaAtual()
    {
        return $this->belongsTo(SolicitacaoFluxoEtapa::class, 'etapa_atual_id');
    }

    public function usuarioAlteracao()
    {
        return $this->belongsTo(Funcionario::class, 'usuario_alteracao');
    }

    public function solicitacaoPai()
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_pai_id');
    }

    public function solicitacoesFilhas()
    {
        return $this->hasMany(self::class, 'solicitacao_pai_id', 'solicitacao_id');
    }

    // ─── Scopes ────────────────────────────────────────────────

    public function scopeEmAndamento($query)
    {
        return $query->where('status', 'em_andamento');
    }

    public function scopeAguardandoDecisao($query)
    {
        return $query->where('status', 'aguardando_decisao');
    }

    // ─── Helpers ───────────────────────────────────────────────

    public function isConcluido(): bool
    {
        return $this->status === 'concluido';
    }

    public function isCancelado(): bool
    {
        return $this->status === 'cancelado';
    }

    public function isAtivo(): bool
    {
        return in_array($this->status, ['em_andamento', 'aguardando_decisao', 'aguardando_solicitante']);
    }

    public function isAguardandoSolicitante(): bool
    {
        return $this->status === 'aguardando_solicitante';
    }
}
