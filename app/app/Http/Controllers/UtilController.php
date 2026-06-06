<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;

/**
 * Adaptador (5 Estrelas) do UtilController da Biglar.
 * O módulo de Solicitações usa estes helpers estáticos para resolver
 * nome de usuário/filial e as filiais do usuário logado.
 * Aqui mapeamos para o nosso modelo (users / branches).
 */
class UtilController extends Controller
{
    /** Nome do funcionário (Biglar: por matrícula). No 5E: users.id → name. */
    public static function nomeFuncionario($id)
    {
        if (!$id) {
            return null;
        }
        return User::find($id)?->name;
    }

    /** Nome/fantasia da filial. No 5E: branches.id → name. */
    public static function nomeFilial($id)
    {
        if (!$id) {
            return null;
        }
        return Branch::find($id)?->name;
    }

    /**
     * Filiais do usuário logado (Biglar: lista de filiais liberadas, como objetos).
     * No 5E: branches vinculadas ao usuário (pivot branch_user) como models Filial
     * (têm accessors codigo/razaosocial/fantasia e aceitam atributos dinâmicos).
     * Sem vínculo → todas as filiais ativas.
     */
    public static function filiaisUsuarioStatic()
    {
        $user = auth()->user();
        if (!$user) {
            return collect();
        }

        $ids = method_exists($user, 'branches') ? $user->branches()->pluck('branches.id')->all() : [];
        $query = \App\Models\Filial::query();
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->where('is_active', true);
        }
        return $query->orderBy('name')->get();
    }
}
