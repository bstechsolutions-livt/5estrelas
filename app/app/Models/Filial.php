<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Adaptador: o módulo de contratos portado da Biglar referencia "Filial".
 * No 5 Estrelas a entidade equivalente é "Branch" (tabela `branches`).
 * Este model mapeia branches expondo os atributos que o código da Biglar espera
 * (codigo, razaosocial, fantasia, cgc), para evitar reescrever as telas/controllers.
 */
class Filial extends Model
{
    protected $table = 'branches';

    protected $appends = ['codigo', 'razaosocial', 'fantasia', 'cgc'];

    public function getCodigoAttribute()
    {
        return $this->code ?? (string) $this->id;
    }

    public function getRazaosocialAttribute()
    {
        return $this->name;
    }

    public function getFantasiaAttribute()
    {
        return $this->name;
    }

    public function getCgcAttribute()
    {
        return $this->cnpj;
    }
}
