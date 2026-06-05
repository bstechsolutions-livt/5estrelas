<?php

namespace App\Models\v2;

use App\Models\Filial;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BsGestaoEquipamento extends Model
{
    use SoftDeletes;


    protected $table = 'bs_gestao_equipamentos';

    protected $fillable = [
        'filial_id',
        'tipo_equipamento_id',
        'numero_identificacao',
        'carga',
        'peso_kg',
        'qtd_projeto',
        'localizacao',
        'data_validade',
        'status',
        'observacoes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data_validade' => 'date',
        'peso_kg' => 'decimal:2',
        'qtd_projeto' => 'integer',
    ];

    protected $appends = ['status_computado', 'dias_para_vencimento', 'ultima_tratativa'];

    // Relationships
    public function filial()
    {
        return $this->belongsTo(Filial::class, 'filial_id');
    }

    public function tipoEquipamento()
    {
        return $this->belongsTo(BsGestaoTipoEquipamento::class, 'tipo_equipamento_id');
    }

    public function ocorrencias()
    {
        return $this->hasMany(BsGestaoEquipamentoOcorrencia::class, 'equipamento_id');
    }

    public function tratativas()
    {
        return $this->hasMany(BsGestaoEquipamentoTratativa::class, 'equipamento_id');
    }

    public function fotos()
    {
        return $this->morphMany(BsGestaoEquipamentoFoto::class, 'fotoable');
    }

    // Accessors
    public function getStatusComputadoAttribute(): string
    {
        if ($this->status === 'EM_MANUTENCAO') {
            return 'EM_MANUTENCAO';
        }

        if (! $this->data_validade) {
            return $this->status;
        }

        $hoje = Carbon::now()->startOfDay();
        $validade = Carbon::parse($this->data_validade)->startOfDay();

        if ($validade->lt($hoje)) {
            return 'VENCIDO';
        }

        $diasRestantes = $hoje->diffInDays($validade);

        if ($diasRestantes <= 10) {
            return 'VENCENDO';
        }

        return 'VIGENTE';
    }

    public function getDiasParaVencimentoAttribute()
    {
        if (! $this->data_validade) {
            return null;
        }

        return Carbon::now()->startOfDay()->diffInDays($this->data_validade, false);
    }

    public function getUltimaTratativaAttribute()
    {
        return $this->tratativas()->orderBy('data_registro', 'desc')->first()?->descricao;
    }

    // Scopes
    public function scopeVigentes($query)
    {
        return $query->where('status', '!=', 'EM_MANUTENCAO')
            ->where('data_validade', '>', Carbon::now()->addDays(10));
    }

    public function scopeVencendo($query, $dias = 10)
    {
        return $query->whereBetween('data_validade', [Carbon::now(), Carbon::now()->addDays($dias)])
            ->where('status', '!=', 'EM_MANUTENCAO');
    }

    public function scopeVencidos($query)
    {
        return $query->where('data_validade', '<', Carbon::now())
            ->where('status', '!=', 'EM_MANUTENCAO');
    }

    public function scopeEmManutencao($query)
    {
        return $query->where('status', 'EM_MANUTENCAO');
    }
}
