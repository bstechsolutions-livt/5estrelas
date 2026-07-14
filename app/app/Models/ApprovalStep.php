<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    protected $fillable = [
        'payable_id', 'order', 'level_name', 'role_label', 'approver_type', 'approver_department_id',
        'status', 'assigned_to', 'delegated_to', 'delegated_by', 'delegated_at', 'delegation_expires_at', 'delegation_reason',
        'resolved_by', 'resolved_at', 'comment',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'delegated_at' => 'datetime',
            'delegation_expires_at' => 'datetime',
        ];
    }

    public function payable(): BelongsTo { return $this->belongsTo(Payable::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function delegatee(): BelongsTo { return $this->belongsTo(User::class, 'delegated_to'); }
    public function delegationSetBy(): BelongsTo { return $this->belongsTo(User::class, 'delegated_by'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }

    public function isPending(): bool { return $this->status === 'pendente'; }
    public function isApproved(): bool { return $this->status === 'aprovado'; }

    public const LEVEL_LABELS = [
        'departamento' => 'Departamento',
        'gerencia' => 'Gerência / Head',
        'diretoria' => 'Diretoria',
        'financeiro' => 'Financeiro',
        'presidencia' => 'Presidência',
        'presid_ncia' => 'Presidência',
        'presidencia_2' => 'Presidência (2ª assinatura)',
    ];

    /** @return list<string> */
    public static function presidencyLevelNames(): array
    {
        return ['presidencia', 'presidencia_2', 'presid_ncia'];
    }

    public static function isPresidencyLevel(?string $levelName): bool
    {
        if (! $levelName) {
            return false;
        }

        return in_array($levelName, self::presidencyLevelNames(), true)
            || str_starts_with($levelName, 'presid');
    }

    public function levelLabel(): string
    {
        return self::LEVEL_LABELS[$this->level_name]
            ?? $this->role_label
            ?? $this->level_name;
    }
}
