<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeniorSupplier extends Model
{
    protected $fillable = [
        'cod_emp', 'cod_for', 'name', 'trade_name', 'cnpj', 'senior_raw', 'senior_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'senior_raw' => 'array',
            'senior_synced_at' => 'datetime',
        ];
    }

    public static function resolveName(int $codEmp, int|string|null $codFor): ?string
    {
        if ($codFor === null || $codFor === '') {
            return null;
        }

        return static::where('cod_emp', $codEmp)
            ->where('cod_for', (int) $codFor)
            ->value('name');
    }
}
