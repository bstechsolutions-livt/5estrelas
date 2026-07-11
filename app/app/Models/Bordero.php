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
        'created_by', 'auto_rule_id', 'approved_by', 'sent_for_approval_at', 'approved_at', 'rejection_reason',
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

    /** Mantém o status do borderô alinhado ao estado dos títulos (workflow multinível). */
    public function syncStatusFromPayables(): void
    {
        $payables = $this->payables()->get(['id', 'status']);
        if ($payables->isEmpty()) {
            return;
        }

        if ($payables->every(fn ($p) => $p->status === 'aprovado')) {
            $this->update([
                'status' => 'aprovado',
                'approved_at' => $this->approved_at ?? now(),
            ]);

            return;
        }

        if ($payables->contains(fn ($p) => $p->status === 'aguardando_aprovacao')) {
            $this->update(['status' => 'aguardando_aprovacao']);

            return;
        }

        if ($payables->every(fn ($p) => in_array($p->status, ['pendente', 'em_preparacao'], true))) {
            $this->update([
                'status' => 'rascunho',
                'sent_for_approval_at' => null,
                'approved_at' => null,
            ]);
        }
    }

    public function wasRejectedBack(): bool
    {
        return $this->status === 'rascunho' && filled($this->rejection_reason);
    }

    public static function generateNumber(): string
    {
        $last = static::orderByDesc('id')->first();
        $next = $last ? ((int) str_replace('BORD-', '', $last->number)) + 1 : 1;
        return 'BORD-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
