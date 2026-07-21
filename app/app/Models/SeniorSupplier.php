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

        $row = static::where('cod_emp', $codEmp)
            ->where('cod_for', (int) $codFor)
            ->first(['name', 'senior_raw']);

        if ($row === null || static::isUnresolvedRaw($row->senior_raw)) {
            return null;
        }

        return $row->name;
    }

    /** Stub gravado quando Exportar não achou o codFor (não é cadastro real). */
    public static function isUnresolvedRaw(mixed $seniorRaw): bool
    {
        if (! is_array($seniorRaw)) {
            return false;
        }

        return (bool) ($seniorRaw['unresolved'] ?? false);
    }

    public function isUnresolved(): bool
    {
        return static::isUnresolvedRaw($this->senior_raw);
    }
}
