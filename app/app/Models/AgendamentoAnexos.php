<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendamentoAnexos extends Model
{
    // use HasFactory;

    protected $table = 'intranet_agend_anexos';

    protected $fillable = [
        'nome_arquivo',
        'id_caminho',
        'id_agendamento',
        'tipo_arquivo',
        'user_cria'
    ];

    public $timestamps = true;
    protected $primaryKey = 'id';

}
