<?php

namespace App\Models\v2;

use App\Models\Filial;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BsGestaoAlvara extends Model
{
    use SoftDeletes;

    protected $table = 'bs_gestao_alvaras';

    protected $fillable = [
        'filial_id',
        'tipo_alvara_id',
        'numero_documento',
        'descricao',
        'orgao_emissor',
        'data_emissao',
        'data_validade',
        'status',
        'responsavel_renovacao',
        'responsavel_email',
        'responsavel_telefone',
        'custo_renovacao',
        'requisitos_renovacao',
        'observacoes',
        'arquivo_path',
        'arquivo_nome',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data_emissao' => 'date',
        'data_validade' => 'date',
        'custo_renovacao' => 'decimal:2',
    ];

    protected $appends = ['dias_para_vencimento'];

    // Relationships
    public function filial()
    {
        return $this->belongsTo(Filial::class, 'filial_id');
    }

    public function tipoAlvara()
    {
        return $this->belongsTo(BsGestaoTipoAlvara::class, 'tipo_alvara_id');
    }

    // Accessors
    public function getDiasParaVencimentoAttribute()
    {
        if (! $this->data_validade) {
            return null;
        }

        return Carbon::now()->startOfDay()->diffInDays($this->data_validade, false);
    }

    // Scopes
    public function scopeVigentes($query)
    {
        return $query->where('status', 'VIGENTE');
    }

    public function scopeVencendoEm($query, int $dias)
    {
        return $query->where('data_validade', '<=', Carbon::now()->addDays($dias))
            ->where('data_validade', '>=', Carbon::now());
    }

    public function scopeVencidos($query)
    {
        return $query->where('data_validade', '<', Carbon::now());
    }
}
