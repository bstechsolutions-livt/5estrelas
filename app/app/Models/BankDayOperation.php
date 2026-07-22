<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankDayOperation extends Model
{
    public const CATEGORY_TARIFA = 'tarifa';

    public const CATEGORY_APLICACAO = 'aplicacao';

    public const CATEGORY_RESGATE = 'resgate';

    protected $fillable = [
        'reference_date',
        'category',
        'description',
        'amount',
        'transaction_date',
        'bank_transaction_id',
        'import_id',
        'bank_account_id',
        'fitid',
        'ofx_file_name',
        'ofx_file_path',
        'conciliated_by',
        'conciliated_at',
    ];

    protected function casts(): array
    {
        return [
            'reference_date' => 'date',
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'conciliated_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'import_id');
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function conciliatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conciliated_by');
    }
}
