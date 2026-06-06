<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Adaptador: o código portado da Biglar (contratos + solicitacoes) referencia
 * "Filial". No 5 Estrelas a entidade equivalente é "Branch" (tabela `branches`).
 *
 * Para que queries que fazem SELECT de colunas específicas e ORDER BY nos nomes
 * legados (codigo, razaosocial, fantasia, cgc) funcionem, este model aponta para
 * a view `vw_filiais`, que expõe as colunas reais de branches MAIS esses aliases
 * como colunas de verdade. Read-only (a view não é gravável; branches é a fonte).
 */
class Filial extends Model
{
    protected $table = 'vw_filiais';

    public $timestamps = false;

    protected $guarded = [];
}
