<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoAssuntoLiberacao extends Model
{
  use HasFactory;

  protected $table = 'solicitacao_assunto_liberacoes';

  protected $fillable = [
    'assunto_id',
    'tipo',
    'valor'
  ];

  public function assunto()
  {
    return $this->belongsTo(SolicitacaoAssunto::class, 'assunto_id');
  }
}
