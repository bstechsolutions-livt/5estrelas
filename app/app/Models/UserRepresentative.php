<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRepresentative extends Model
{
    public const SCOPE_FINANCEIRO_APROVACAO = 'financeiro.aprovacao';

    protected $fillable = [
        'user_id',
        'representative_id',
        'starts_at',
        'ends_at',
        'scopes',
        'reason',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'scopes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function coversScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [self::SCOPE_FINANCEIRO_APROVACAO];

        return in_array($scope, $scopes, true) || in_array('*', $scopes, true);
    }

    public function isCurrentlyActive(?\Carbon\CarbonInterface $on = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $on = ($on ?? now())->startOfDay();
        $starts = $this->starts_at?->startOfDay();
        $ends = $this->ends_at?->endOfDay();

        if ($starts && $on->lt($starts)) {
            return false;
        }

        if ($ends && $on->gt($ends)) {
            return false;
        }

        return true;
    }
}
