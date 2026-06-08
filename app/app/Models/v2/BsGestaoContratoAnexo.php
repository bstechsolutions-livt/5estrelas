<?php

namespace App\Models\v2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    protected $appends = ['url'];

    /**
     * URL pública do anexo (via symlink storage:link).
     * O `caminho` guarda o path relativo no disco public (ex.: gestao/contratos/53/arquivo.pdf).
     */
    public function getUrlAttribute(): ?string
    {
        return $this->caminho ? Storage::url($this->caminho) : null;
    }

    public function contrato()
    {
        return $this->belongsTo(BsGestaoContrato::class, 'contrato_id');
    }
}
