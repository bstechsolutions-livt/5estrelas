<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Regional extends Model
{
  protected $table = 'bs_regionais';

  protected $fillable = [
    'cod_filial',
    'filial',
    'nome',
    'gerente',
    'telefone1',
    'telefone2',
    'email',
    'ativo',
  ];

  /**
   * Retorna apenas regionais ativas
   */
  public function scopeAtivas($query)
  {
    return $query->where('ativo', 'S');
  }
}
