<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha de rateio importada via planilha (conciliação bancária).
 * Distinto de PayableRateio (espelho Senior).
 */
class PayableAllocationLine extends Model
{
    protected $fillable = [
        'payable_id',
        'line_order',
        'person_name',
        'payment_date',
        'document_id',
        'role_label',
        'pix_key',
        'quantity',
        'unit_amount',
        'amount',
        'matched_bank_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'quantity' => 'decimal:2',
            'unit_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }

    public function matchedBankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'matched_bank_transaction_id');
    }
}
