<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoAssunto extends Model
{
    protected $table = 'intranet_solicitacao_assuntos';

    protected $fillable = [
        'departamento',
        'assunto',
        'responsavel',
        'prioridade',
        'ativo',
        'qtd_min_anexos',
        'instrucoes',
        'redirect',
        'redirect_mensagem',
        'redirect_mensagem_sim',
        'redirect_nao',
        'redirect_mensagem_nao',
        'redirect_departamento',
        'redirect_assunto_id',
    ];

    protected $casts = [
        'redirect' => 'boolean',
        'redirect_nao' => 'boolean',
        'redirect_assunto_id' => 'integer'
    ];

    public function responsavel()
    {
        return $this->belongsTo(Funcionario::class, 'responsavel');
    }

    public function modelos()
    {
        return $this->hasMany(SolicitacaoAssuntoModelo::class);
    }

    public function arquivosModelo()
    {
        return $this->hasManyThrough(File::class, SolicitacaoAssuntoModelo::class, 'solicitacao_assunto_id', 'id', 'id', 'file_id');
    }

    public function liberacoes()
    {
        return $this->hasMany(SolicitacaoAssuntoLiberacao::class, 'assunto_id');
    }

    /**
     * Responsáveis exclusivos do assunto
     * 
     * #22263 - Permissão por Assunto em Solicitações
     * Define quais usuários podem VER e ATENDER solicitações deste assunto.
     */
    public function responsaveis()
    {
        return $this->hasMany(SolicitacaoAssuntoResponsavel::class, 'assunto_id');
    }

    /**
     * Usuários responsáveis (através da tabela pivot)
     */
    public function usuariosResponsaveis()
    {
        return $this->hasManyThrough(
            Funcionario::class,
            SolicitacaoAssuntoResponsavel::class,
            'assunto_id',     // FK em solicitacao_assunto_responsaveis
            'matricula',      // FK em funcionarios
            'id',             // Local key em solicitacao_assuntos
            'matricula'       // Local key em solicitacao_assunto_responsaveis
        );
    }

    /**
     * Etapas configuráveis do assunto
     * 
     * Permite configurar etapas de andamento como "Triagem", "Entrevista RH", etc.
     */
    public function etapas()
    {
        return $this->hasMany(SolicitacaoEtapa::class, 'assunto_id')->orderBy('ordem');
    }

    /**
     * Etapas ativas do assunto
     */
    public function etapasAtivas()
    {
        return $this->hasMany(SolicitacaoEtapa::class, 'assunto_id')
            ->where('ativo', 'S')
            ->orderBy('ordem');
    }

    // ─── Workflow/Fluxo ────────────────────────────────────────────

    /**
     * Fluxos de workflow vinculados ao assunto
     */
    public function fluxos()
    {
        return $this->hasMany(SolicitacaoFluxo::class, 'assunto_id');
    }

    /**
     * Fluxo ativo do assunto (deve ter apenas 1 ativo por vez)
     */
    public function fluxoAtivo()
    {
        return $this->hasOne(SolicitacaoFluxo::class, 'assunto_id')->where('ativo', 'S');
    }
}
