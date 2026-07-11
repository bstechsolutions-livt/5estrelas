<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayableDepartmentRule extends Model
{
    protected $fillable = [
        'department_id',
        'codccu',
        'description_patterns',
    ];

    protected $casts = [
        'codccu' => 'array',
        'description_patterns' => 'array',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return string[] */
    public static function parseLines(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        return collect(preg_split('/[\r\n,;]+/', $text) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @param string[] $patterns */
    public static function formatLines(array $patterns): string
    {
        return collect($patterns)
            ->map(fn ($p) => trim((string) $p, '%'))
            ->filter()
            ->implode("\n");
    }

    /** Garante padrão ILIKE com curingas (%GFD%). */
    public static function normalizePattern(string $pattern): string
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return '';
        }

        if (!str_starts_with($pattern, '%')) {
            $pattern = '%' . $pattern;
        }
        if (!str_ends_with($pattern, '%')) {
            $pattern .= '%';
        }

        return $pattern;
    }

    /** @param string[] $patterns */
    public static function normalizePatterns(array $patterns): array
    {
        return collect($patterns)
            ->map(fn ($p) => self::normalizePattern((string) $p))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
