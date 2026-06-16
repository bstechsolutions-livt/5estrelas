<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rateio (cost-center split) de um título a pagar, espelhado da Senior
 * (ConsultarTitulosAbertosCP v3 — Apêndice A.3). Relação um-para-muitos com Payable.
 */
class PayableRateio extends Model
{
    /**
     * Campos do rateio na Senior (Apêndice A.3). Chave = código Senior (camelCase),
     * valor = tipo lógico (int|string|money|rate|date). Coluna = código em minúsculas.
     */
    public const SENIOR_FIELDS = [
        'abrFpj' => 'string', 'abrPrj' => 'string', 'codCcu' => 'string', 'codFpj' => 'int',
        'codTns' => 'string', 'criRat' => 'int', 'ctaFin' => 'int', 'ctaRed' => 'int',
        'datBas' => 'date', 'mesAno' => 'string', 'numPrj' => 'int', 'obsRat' => 'string',
        'perCta' => 'rate', 'perRat' => 'rate', 'seqMov' => 'int', 'seqRat' => 'int',
        'somSub' => 'int', 'tipOri' => 'string', 'vlrCta' => 'money', 'vlrRat' => 'money',
    ];

    /** Coluna no banco para um código Senior (perRat -> perrat). */
    public static function seniorColumn(string $code): string
    {
        return strtolower($code);
    }

    /** Lista de colunas (lower) de origem Senior. */
    public static function seniorColumns(): array
    {
        return array_map(fn ($c) => self::seniorColumn($c), array_keys(self::SENIOR_FIELDS));
    }

    public function getFillable()
    {
        return array_values(array_unique(array_merge(['payable_id'], self::seniorColumns())));
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

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }
}
