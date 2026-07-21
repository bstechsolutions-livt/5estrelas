<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementImport extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id', 'bank_account_id', 'conciliation_session_id', 'bank_name', 'bank_id', 'account_number', 'branch_number',
        'file_name', 'file_path', 'period_start', 'period_end', 'balance',
        'status', 'transaction_count', 'matched_count', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'balance' => 'decimal:2',
        ];
    }

    protected string $auditableModule = 'financeiro.contas_pagar';
    protected string $auditableEventPrefix = 'contas_pagar';
    protected array $auditableEvents = ['created', 'deleted'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function conciliationSession(): BelongsTo
    {
        return $this->belongsTo(ConciliationSession::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'import_id');
    }
}
