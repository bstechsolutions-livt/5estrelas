<?php

namespace App\Models\v2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BsGestaoEquipamentoFoto extends Model
{

    protected $table = 'bs_gestao_equipamento_fotos';

    protected $fillable = [
        'fotoable_type',
        'fotoable_id',
        'arquivo_path',
        'arquivo_nome',
        'arquivo_tamanho',
        'arquivo_mime',
        'created_by',
    ];

    protected $casts = [
        'arquivo_tamanho' => 'integer',
    ];

    // Relationships
    public function fotoable()
    {
        return $this->morphTo('fotoable');
    }

    // Accessors
    public function getUrlAttribute()
    {
        return Storage::disk('public')->url($this->arquivo_path);
    }
}
