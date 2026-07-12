<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bordero extends Model
{
    use Auditable;

    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_EM_PREPARACAO = 'em_preparacao';
    public const STATUS_AGUARDANDO_APROVACAO = 'aguardando_aprovacao';
    public const STATUS_APROVADO = 'aprovado';
    public const STATUS_PAGO = 'pago';

    /** @deprecated use STATUS_PENDENTE — alias para compatibilidade temporária */
    public const STATUS_RASCUNHO = 'pendente';

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
        self::STATUS_PENDENTE => 'Pendente',
        self::STATUS_EM_PREPARACAO => 'Em Preparação',
        self::STATUS_AGUARDANDO_APROVACAO => 'Aguardando Aprovação',
        self::STATUS_APROVADO => 'Aprovado',
        self::STATUS_PAGO => 'Pago',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDENTE => 'secondary',
        self::STATUS_EM_PREPARACAO => 'info',
        self::STATUS_AGUARDANDO_APROVACAO => 'warn',
        self::STATUS_APROVADO => 'success',
        self::STATUS_PAGO => 'success',
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

    /** Mantém o status do borderô alinhado ao estado dos títulos. */
    public function syncStatusFromPayables(): void
    {
        $payables = $this->payables()->get(['id', 'status']);
        if ($payables->isEmpty()) {
            return;
        }

        if ($payables->every(fn ($p) => $p->status === 'aprovado')) {
            $this->update([
                'status' => self::STATUS_APROVADO,
                'approved_at' => $this->approved_at ?? now(),
            ]);

            return;
        }

        if ($payables->contains(fn ($p) => $p->status === 'aguardando_aprovacao')) {
            $this->update(['status' => self::STATUS_AGUARDANDO_APROVACAO]);

            return;
        }

        if ($payables->contains(fn ($p) => $p->status === 'em_preparacao')) {
            $this->update(['status' => self::STATUS_EM_PREPARACAO]);

            return;
        }

        if ($payables->every(fn ($p) => $p->status === 'pendente')) {
            $this->update([
                'status' => self::STATUS_PENDENTE,
                'sent_for_approval_at' => null,
                'approved_at' => null,
            ]);
        }
    }

    public function wasRejectedBack(): bool
    {
        return $this->status === self::STATUS_PENDENTE && filled($this->rejection_reason);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDENTE, self::STATUS_EM_PREPARACAO], true);
    }

    public static function generateNumber(): string
    {
        $last = static::orderByDesc('id')->first();
        $next = $last ? ((int) str_replace('BORD-', '', $last->number)) + 1 : 1;

        return 'BORD-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
