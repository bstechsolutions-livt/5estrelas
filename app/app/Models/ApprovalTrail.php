<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalTrail extends Model
{
    protected $fillable = ['area', 'order', 'level_name', 'role_label', 'default_user_id'];

    public function defaultUser(): BelongsTo { return $this->belongsTo(User::class, 'default_user_id'); }

    /**
     * Retorna os níveis de aprovação para uma área, em ordem.
     */
    public static function trailFor(string $area): \Illuminate\Support\Collection
    {
        return static::where('area', $area)->orderBy('order')->get();
    }

    /**
     * Áreas disponíveis (conforme documento v3.0).
     */
    public const AREAS = [
        'matriz' => 'Matriz / Filiais / Compras / Modernização',
        'comercial' => 'Comercial / Faturamento / Marketing',
        'licitacao' => 'Licitação',
        'dp_rh' => 'DP / RH',
        'juridico' => 'Jurídico',
    ];
}
