<?php

namespace App\Models\v2;

use Illuminate\Database\Eloquent\Model;

class BsGestaoEquipamentoOcorrencia extends Model
{

    protected $table = 'bs_gestao_equipamento_ocorrencias';

    protected $fillable = [
        'equipamento_id',
        'tipo_ocorrencia',
        'tipo_ocorrencia_descricao',
        'descricao',
        'data_ocorrencia',
        'created_by',
    ];

    protected $casts = [
        'data_ocorrencia' => 'date',
    ];

    // Constants
    const TIPO_DESPRESSURIZACAO = 'DESPRESSURIZACAO';
    const TIPO_DANO_FISICO = 'DANO_FISICO';
    const TIPO_USO_EMERGENCIA = 'USO_EMERGENCIA';
    const TIPO_VENCIMENTO_ANTECIPADO = 'VENCIMENTO_ANTECIPADO';
    const TIPO_OUTRO = 'OUTRO';

    const TIPOS = [
        self::TIPO_DESPRESSURIZACAO => 'Despressurização',
        self::TIPO_DANO_FISICO => 'Dano Físico',
        self::TIPO_USO_EMERGENCIA => 'Uso em Emergência',
        self::TIPO_VENCIMENTO_ANTECIPADO => 'Vencimento Antecipado',
        self::TIPO_OUTRO => 'Outro',
    ];

    // Relationships
    public function equipamento()
    {
        return $this->belongsTo(BsGestaoEquipamento::class, 'equipamento_id');
    }

    public function fotos()
    {
        return $this->morphMany(BsGestaoEquipamentoFoto::class, 'fotoable');
    }
}
