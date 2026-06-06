<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoCVendas extends Model
{
    use HasFactory;

    protected $table = 'intranet_solicitacao_c_vendas';
    protected $guarded = [];

     // Desativa incremento automático (sem coluna `id`)
     public $incrementing = false;

     // Define que não há chave primária
     protected $primaryKey = null;
 
     // Desativa timestamps (sem `created_at` e `updated_at`)
     public $timestamps = false;
}
