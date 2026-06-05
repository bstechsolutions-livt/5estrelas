<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payable extends Model
{
    use Auditable;

    protected $fillable = [
        'title_number', 'supplier_name', 'supplier_cnpj', 'amount',
        'due_date', 'issue_date', 'description', 'category', 'status',
        'branch_id', 'prepared_by', 'approved_by', 'sent_for_approval_at',
        'approved_at', 'rejection_reason', 'bordero_id', 'senior_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'issue_date' => 'date',
        'sent_for_approval_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected string $auditableModule = 'financeiro.contas_pagar';
    protected string $auditableEventPrefix = 'contas_pagar';
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    public function auditDescription(string $action): ?string
    {
        return match ($action) {
            'created' => "Título {$this->title_number} criado - {$this->supplier_name} R$ {$this->amount}",
            'updated' => "Título {$this->title_number} atualizado",
            'deleted' => "Título {$this->title_number} excluído",
            default => null,
        };
    }

    // Status labels
    public const STATUS_LABELS = [
        'pendente' => 'Pendente',
        'em_preparacao' => 'Em Preparação',
        'aguardando_aprovacao' => 'Aguardando Aprovação',
        'aprovado' => 'Aprovado',
        'reprovado' => 'Reprovado',
        'pago' => 'Pago',
    ];

    public const STATUS_COLORS = [
        'pendente' => 'warn',
        'em_preparacao' => 'info',
        'aguardando_aprovacao' => 'warn',
        'aprovado' => 'success',
        'reprovado' => 'danger',
        'pago' => 'success',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function bordero(): BelongsTo
    {
        return $this->belongsTo(Bordero::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PayableDocument::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PayableComment::class)->orderBy('created_at');
    }
}
