<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableRateio extends Model
{
    public const SENIOR_FIELDS = [
        'codCcu' => 'string', 'codFpj' => 'int', 'codTns' => 'string', 'criRat' => 'int',
        'ctaFin' => 'int', 'ctaRed' => 'int', 'datBas' => 'date', 'mesAno' => 'string',
        'numPrj' => 'int', 'obsRat' => 'string', 'perCta' => 'rate', 'perRat' => 'rate',
        'seqMov' => 'int', 'seqRat' => 'int', 'somSub' => 'int', 'tipOri' => 'string',
        'vlrCta' => 'money', 'vlrRat' => 'money',
    ];

    public static function seniorColumn(string $code): string
    {
        return strtolower($code);
    }

    public static function seniorColumns(): array
    {
        return array_map(fn ($c) => self::seniorColumn($c), array_keys(self::SENIOR_FIELDS));
    }

    public function getFillable()
    {
        return array_values(array_unique(array_merge(['receivable_id'], self::seniorColumns())));
    }

    protected function casts(): array
    {
        $casts = [];
        foreach (self::SENIOR_FIELDS as $code => $type) {
            $casts[self::seniorColumn($code)] = match ($type) {
                'money' => 'decimal:2',
                'rate' => 'decimal:6',
                'date' => 'date',
                'int' => 'integer',
                default => 'string',
            };
        }

        return $casts;
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }
}
