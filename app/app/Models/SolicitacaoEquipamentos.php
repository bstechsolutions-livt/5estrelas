<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoEquipamentos extends Model
{
    // use HasFactory;
    protected $table = 'intranet_solicitacao_equip';
    
    protected $fillable = [
        'equipamento'
    ];
    
    public $timestamps = false;
}
