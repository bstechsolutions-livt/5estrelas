<?php

namespace App\Models\v2;

use Illuminate\Database\Eloquent\Model;

class BsGestaoTipoEquipamento extends Model
{

    protected $table = 'bs_gestao_tipos_equipamento';

    protected $fillable = [
        'nome',
        'descricao',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    // Relationships
    public function equipamentos()
    {
        return $this->hasMany(BsGestaoEquipamento::class, 'tipo_equipamento_id');
    }

    // Scopes
    public function scopeAtivos($query)
    {
        return $query->where('ativo', true)->orderBy('nome');
    }
}
