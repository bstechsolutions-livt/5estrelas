<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoAssuntoModelo extends Model
{
    use HasFactory;

    protected $table = 'solicitacao_assunto_modelos';

    protected $fillable = [
        'solicitacao_assunto_id',
        'file_id'
    ];

    public function assunto()
    {
        return $this->belongsTo(SolicitacaoAssunto::class, 'solicitacao_assunto_id');
    }

    public function arquivo()
    {
        return $this->belongsTo(File::class, 'file_id');
    }
}
