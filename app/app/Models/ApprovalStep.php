<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    protected $fillable = [
        'payable_id', 'order', 'level_name', 'status',
        'assigned_to', 'resolved_by', 'resolved_at', 'comment',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function payable(): BelongsTo { return $this->belongsTo(Payable::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }

    public function isPending(): bool { return $this->status === 'pendente'; }
    public function isApproved(): bool { return $this->status === 'aprovado'; }

    public const LEVEL_LABELS = [
        'departamento' => 'Departamento',
        'gerencia' => 'Gerência / Head',
        'diretoria' => 'Diretoria',
        'financeiro' => 'Financeiro',
        'presidencia' => 'Presidência',
        'presidencia_2' => 'Presidência (2ª assinatura)',
    ];
}
