<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitacaoAprovacao extends Model
{
    protected $table = 'intranet_solicitacao_aprovacoes';

    protected $fillable = [
        'solicitacao_id',
        'solicitante_matricula',
        'aprovador_matricula',
        'observacoes',
        'status',
        'resposta_observacoes',
        'respondido_por',
        'respondido_em'
    ];

    protected $casts = [
        'respondido_em' => 'datetime',
    ];

    /**
     * Relacionamento com a solicitação
     */
    public function solicitacao()
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_id', 'id');
    }

    /**
     * Relacionamento com o funcionário solicitante
     */
    public function solicitante()
    {
        return $this->belongsTo(Funcionario::class, 'solicitante_matricula')
            ->select(['matricula', 'nome', 'email', 'areaatuacao', 'fone']);
    }

    /**
     * Relacionamento com o funcionário aprovador
     */
    public function aprovador()
    {
        return $this->belongsTo(Funcionario::class, 'aprovador_matricula')
            ->select(['matricula', 'nome', 'email', 'areaatuacao', 'fone']);
    }

    /**
     * Relacionamento com quem respondeu a aprovação
     */
    public function respondidoPor()
    {
        return $this->belongsTo(Funcionario::class, 'respondido_por')
            ->select(['matricula', 'nome', 'email', 'areaatuacao', 'fone']);
    }

    /**
     * Scope para aprovações pendentes
     */
    public function scopePendentes($query)
    {
        return $query->where('status', 'pendente');
    }

    /**
     * Scope para aprovações respondidas
     */
    public function scopeRespondidas($query)
    {
        return $query->whereIn('status', ['aprovada', 'rejeitada']);
    }

    /**
     * Scope para aprovações de um aprovador específico
     */
    public function scopeParaAprovador($query, $matricula)
    {
        return $query->where('aprovador_matricula', $matricula);
    }

    /**
     * Scope para aprovações solicitadas por um usuário específico
     */
    public function scopeSolicitadaPor($query, $matricula)
    {
        return $query->where('solicitante_matricula', $matricula);
    }

    /**
     * Verifica se a aprovação pode ser cancelada
     */
    public function podeCancelar($matriculaUsuario)
    {
        return $this->status === 'pendente' && $this->solicitante_matricula == $matriculaUsuario;
    }

    /**
     * Verifica se a aprovação pode ser editada
     */
    public function podeEditar($matriculaUsuario)
    {
        return $this->status === 'pendente' && $this->solicitante_matricula == $matriculaUsuario;
    }

    /**
     * Verifica se pode aprovar/rejeitar
     */
    public function podeAprovar($matriculaUsuario)
    {
        return $this->status === 'pendente' && $this->aprovador_matricula == $matriculaUsuario;
    }
}
