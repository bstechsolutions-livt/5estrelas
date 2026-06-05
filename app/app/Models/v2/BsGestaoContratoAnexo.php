<?php

namespace App\Models\v2;

use Illuminate\Database\Eloquent\Model;

class BsGestaoContratoAnexo extends Model
{
    protected $table = 'bs_gestao_contratos_anexos';

    protected $fillable = [
        'contrato_id',
        'tipo',
        'nome_arquivo',
        'caminho',
        'tamanho',
        'mime_type',
        'descricao',
        'created_by',
    ];

    public function contrato()
    {
        return $this->belongsTo(BsGestaoContrato::class, 'contrato_id');
    }
}
