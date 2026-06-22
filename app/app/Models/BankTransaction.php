<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $fillable = [
        'import_id', 'fitid', 'date', 'amount', 'type', 'description',
        'memo', 'check_number', 'matched_payable_id', 'match_status',
        'match_confidence', 'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'import_id');
    }

    public function matchedPayable(): BelongsTo
    {
        return $this->belongsTo(Payable::class, 'matched_payable_id');
    }
}
