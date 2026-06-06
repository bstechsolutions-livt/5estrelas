<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Adaptador: o módulo de Solicitações (portado da Biglar) referencia "Funcionario"
 * (que lá vinha do ERP por matrícula). No 5 Estrelas a entidade equivalente é o User.
 * Este model mapeia a tabela `users` expondo os atributos que o código da Biglar espera
 * (matricula, nome, etc.), evitando reescrever as telas/controllers.
 *
 * As relações dos models de solicitação foram ajustadas para usar a PK `id`
 * (as colunas de pessoa guardam o id do usuário).
 */
class Funcionario extends Model
{
    protected $table = 'users';

    protected $appends = ['matricula', 'nome', 'avatar_url'];

    public function getMatriculaAttribute()
    {
        return (string) $this->id;
    }

    public function getNomeAttribute()
    {
        return $this->name;
    }

    public function getAvatarUrlAttribute()
    {
        return $this->avatar_path ? Storage::url($this->avatar_path) : null;
    }
}
