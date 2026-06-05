<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bordero extends Model
{
    use Auditable;

    protected $fillable = [
        'number', 'description', 'status', 'total_amount', 'items_count',
        'created_by', 'approved_by', 'sent_for_approval_at', 'approved_at', 'rejection_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'sent_for_approval_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected string $auditableModule = 'financeiro.contas_pagar';
    protected string $auditableEventPrefix = 'bordero';
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    public const STATUS_LABELS = [
        'rascunho' => 'Rascunho',
        'aguardando_aprovacao' => 'Aguardando Aprovação',
        'aprovado' => 'Aprovado',
        'reprovado' => 'Reprovado',
        'pago' => 'Pago',
    ];

    public const STATUS_COLORS = [
        'rascunho' => 'secondary',
        'aguardando_aprovacao' => 'warn',
        'aprovado' => 'success',
        'reprovado' => 'danger',
        'pago' => 'success',
    ];

    public function auditDescription(string $action): ?string
    {
        return match ($action) {
            'created' => "Borderô {$this->number} criado",
            'updated' => "Borderô {$this->number} atualizado",
            'deleted' => "Borderô {$this->number} excluído",
            default => null,
        };
    }

    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function recalculate(): void
    {
        $this->total_amount = $this->payables()->sum('amount');
        $this->items_count = $this->payables()->count();
        $this->save();
    }

    public static function generateNumber(): string
    {
        $last = static::orderByDesc('id')->first();
        $next = $last ? ((int) str_replace('BORD-', '', $last->number)) + 1 : 1;
        return 'BORD-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
