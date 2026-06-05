<?php

namespace App\Models\v2;

use App\Models\Filial;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BsGestaoContrato extends Model
{
    use SoftDeletes;

    protected $table = 'bs_gestao_contratos';

    protected $fillable = [
        'tipo',
        'filial_id',
        'razao_social_loja',
        'cnpj_loja',
        'contrato_em_nome_de',
        'tipo_pessoa',
        'nome_locador',
        'documento_locador',
        'email_locador',
        'telefone_locador',
        'imobiliaria',
        'endereco_imovel',
        'cidade',
        'estado',
        'cep',
        'banco',
        'agencia',
        'conta',
        'tipo_conta',
        'pix',
        'data_inicio',
        'data_fim',
        'renovacao_automatica',
        'pagamento_antecipado',
        'dia_vencimento',
        'valor_mensal',
        'valor_condominio',
        'valor_iptu',
        'tipo_indice_id',
        'data_proximo_reajuste',
        'percentual_reajuste_fixo',
        'tipo_servico',
        'descricao_servico',
        'numero_contrato',
        'negociador',
        'responsavel_interno',
        'retencao_irrf',
        'percentual_irrf',
        'observacoes',
        'status',
        'created_by',
        'updated_by',
        // Campos de vínculo com Compras / recorrência
        'id_solicitacao_compras',
        'provisao_mensal',
        'percentual_divergencia',
        'dia_envio_nf',
        'dias_alerta_antes',
        // Campos adicionais solicitados
        'locadores_adicionais',
        'periodo_apuracao_inicio',
        'periodo_apuracao_fim',
        'iptu_inscricoes',
        'indices_adicionais',
        'valor_proposto_locador',
        // Melhorias locação (mai/2026)
        'telefone_imobiliaria',
        'iptu_pago_carne',
        'dia_apuracao',
        'dia_apuracao_fim',
        'valor_anterior',
        'tem_condominio',
        'prazo_contrato_meses',
        'mes_base_reajuste',
        'data_vencimento_reajuste',
        'historico_anual',
        'reajuste_fixo_contrato',
        'valor_reajuste_fixo',
    ];

    protected $casts = [
        'renovacao_automatica' => 'boolean',
        'pagamento_antecipado' => 'boolean',
        'retencao_irrf' => 'boolean',
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'data_proximo_reajuste' => 'date',
        'valor_mensal' => 'decimal:2',
        'valor_condominio' => 'decimal:2',
        'valor_iptu' => 'decimal:2',
        'percentual_reajuste_fixo' => 'decimal:4',
        'percentual_irrf' => 'decimal:2',
        'documento_locador' => 'string',
        'provisao_mensal' => 'decimal:2',
        'percentual_divergencia' => 'decimal:2',
        // Novos campos
        'locadores_adicionais' => 'array',
        'periodo_apuracao_inicio' => 'date',
        'periodo_apuracao_fim' => 'date',
        'iptu_inscricoes' => 'array',
        'indices_adicionais' => 'array',
        'valor_proposto_locador' => 'decimal:2',
        // Melhorias locação (mai/2026)
        'iptu_pago_carne' => 'boolean',
        'dia_apuracao' => 'integer',
        'dia_apuracao_fim' => 'integer',
        'valor_anterior' => 'decimal:2',
        'tem_condominio' => 'boolean',
        'prazo_contrato_meses' => 'integer',
        'mes_base_reajuste' => 'integer',
        'data_vencimento_reajuste' => 'date',
        'historico_anual' => 'array',
        'reajuste_fixo_contrato' => 'boolean',
        'valor_reajuste_fixo' => 'decimal:2',
    ];

    protected $appends = ['dias_para_vencimento', 'valor_total_mensal'];

    // Relationships
    public function filial()
    {
        return $this->belongsTo(Filial::class, 'filial_id');
    }

    public function tipoIndice()
    {
        return $this->belongsTo(BsGestaoTipoIndice::class, 'tipo_indice_id');
    }

    public function reajustes()
    {
        return $this->hasMany(BsGestaoContratoReajuste::class, 'contrato_id')->orderBy('data_reajuste', 'desc');
    }

    public function anexos()
    {
        return $this->hasMany(BsGestaoContratoAnexo::class, 'contrato_id');
    }

    // Accessors
    public function getDocumentoLocadorAttribute($value)
    {
        if (!$value) return null;
        // Se contém apenas dígitos, retorna direto (evita notação científica)
        if (preg_match('/^\d+$/', $value)) {
            return ltrim($value, '0') ?: '0';
        }
        // Valor já formatado ou com caracteres especiais, retorna como está
        return $value;
    }
    public function getDiasParaVencimentoAttribute()
    {
        if (! $this->data_fim) {
            return null;
        }

        return Carbon::now()->startOfDay()->diffInDays($this->data_fim, false);
    }

    public function getValorTotalMensalAttribute()
    {
        return ($this->valor_mensal ?? 0) + ($this->valor_condominio ?? 0);
    }

    // Scopes
    public function scopeAtivos($query)
    {
        return $query->where('status', 'ATIVO');
    }

    public function scopeLocacao($query)
    {
        return $query->where('tipo', 'LOCACAO');
    }

    public function scopeServico($query)
    {
        return $query->where('tipo', 'SERVICO');
    }

    public function scopeVencendoEm($query, int $dias)
    {
        return $query->where('data_fim', '<=', Carbon::now()->addDays($dias))
            ->where('data_fim', '>=', Carbon::now());
    }

    public function scopeVencidos($query)
    {
        return $query->where('data_fim', '<', Carbon::now());
    }

    public function scopeComRecorrencia($query)
    {
        return $query->whereNotNull('id_solicitacao_compras');
    }

    public function scopePrecisaEnvioNf($query)
    {
        return $query->ativos()
            ->comRecorrencia()
            ->whereNotNull('dia_envio_nf');
    }
}
