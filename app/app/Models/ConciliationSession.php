<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConciliationSession extends Model
{
    protected $fillable = [
        'bank_account_id',
        'reference_date',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reference_date' => 'date',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(BankStatementImport::class);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function periodBounds(): array
    {
        $day = $this->reference_date->copy()->startOfDay();

        return [$day, $day->copy()->endOfDay()];
    }

    public function periodLabel(): string
    {
        return $this->reference_date->locale('pt_BR')->translatedFormat('d/m/Y');
    }
}
