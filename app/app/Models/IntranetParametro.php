<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntranetParametro extends Model
{
    // Definir o nome da tabela
    protected $table = 'intranet_parametros';

    // Se a tabela não tiver a coluna 'id' como chave primária
    protected $primaryKey = null;
    public $incrementing = false;

    // Se a tabela não possuir timestamps (created_at, updated_at)
    public $timestamps = false;

    // Definir os campos que podem ser preenchidos (mass assignment)
    protected $fillable = [
        'MENU',
        'SUBMENU',
        'VALOR',
        'VALOR_CLOB',
        'OBSERVACAO',
        'PARAMETRO',
        'CONDICAO1',
        'CONDICAO2',
        'CONDICAO3',
    ];

    // Se necessário, você pode adicionar casts aqui para o campo CLOB
    protected $casts = [
        'VALOR_CLOB' => 'string', // Como o valor é um CLOB, tratamos como string
    ];
}
