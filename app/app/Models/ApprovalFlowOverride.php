<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalFlowOverride extends Model
{
    protected $fillable = [
        'area',
        'step_order',
        'label',
        'codccu',
        'title_patterns',
        'approver_user_id',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'codccu' => 'array',
        'title_patterns' => 'array',
        'step_order' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public static function findMatch(string $area, int $stepOrder, Payable $payable): ?self
    {
        return static::query()
            ->with('approver:id,name')
            ->where('area', $area)
            ->where('step_order', $stepOrder)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->first(fn (self $rule) => $rule->matchesPayable($payable));
    }

    public function matchesPayable(Payable $payable): bool
    {
        $codccus = $this->codccu ?? [];
        $patterns = $this->title_patterns ?? [];

        if ($codccus === [] && $patterns === []) {
            return false;
        }

        $ccMatch = false;
        if ($codccus !== []) {
            $payableCcs = self::codccusForPayable($payable);
            $allowed = collect($codccus)
                ->map(fn ($v) => self::normalizeCodccu($v))
                ->filter()
                ->all();
            $ccMatch = $payableCcs !== []
                && collect($payableCcs)->contains(fn (string $cc) => in_array($cc, $allowed, true));
        }

        $titleMatch = false;
        if ($patterns !== []) {
            $haystack = mb_strtoupper(trim(
                ($payable->title_number ?? '') . ' ' . ($payable->description ?? '')
            ));
            foreach ($patterns as $pattern) {
                $needle = mb_strtoupper(trim((string) $pattern, " \t\n\r\0\x0B%"));
                if ($needle !== '' && str_contains($haystack, $needle)) {
                    $titleMatch = true;
                    break;
                }
            }
        }

        if ($codccus !== [] && $patterns !== []) {
            return $ccMatch || $titleMatch;
        }

        return $codccus !== [] ? $ccMatch : $titleMatch;
    }

    /** @return string[] */
    public static function codccusForPayable(Payable $payable): array
    {
        $ccs = [];

        $header = self::normalizeCodccu($payable->codccu);
        if ($header !== null) {
            $ccs[] = $header;
        }

        if (! $payable->relationLoaded('rateios')) {
            $payable->load('rateios:payable_id,codccu');
        }

        foreach ($payable->rateios as $rateio) {
            $cc = self::normalizeCodccu($rateio->codccu);
            if ($cc !== null) {
                $ccs[] = $cc;
            }
        }

        return array_values(array_unique($ccs));
    }

    public static function normalizeCodccu(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $s = trim((string) $value);
        if ($s === '' || $s === '0') {
            return null;
        }

        if (ctype_digit($s)) {
            $s = ltrim($s, '0') ?: '0';
        }

        return $s;
    }

    public static function formatCodccuLines(?array $codccu): string
    {
        return collect($codccu ?? [])->implode("\n");
    }

    public static function formatTitlePatternLines(?array $patterns): string
    {
        return PayableDepartmentRule::formatLines($patterns ?? []);
    }
}
