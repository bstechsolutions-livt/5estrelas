<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BsFilialDeptoSelect extends Model
{
    protected $table = 'intranet_solicitacao_filial_depto_select';

    protected $fillable = [
        'solicitacao_id',
        'filial',
        'departamento',
    ];
}
