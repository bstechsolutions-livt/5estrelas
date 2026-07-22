<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankAccount extends Model
{
    use Auditable;

    public string $auditableModule = 'financeiro.bancos';

    public string $auditableEventPrefix = 'conta';

    protected $fillable = [
        'name',
        'is_active',
        'senior_codemp',
        'senior_codfil',
        'senior_num_cco',
        'senior_descricao',
        'bank_code',
        'bank_name',
        'agency',
        'account_number',
        'account_digit',
        'opening_balance',
        'opening_balance_date',
        'imported_from_senior_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'senior_codemp' => 'integer',
            'senior_codfil' => 'integer',
            'opening_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
            'imported_from_senior_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statementImports(): HasMany
    {
        return $this->hasMany(BankStatementImport::class);
    }

    public function latestStatementImport(): HasOne
    {
        return $this->hasOne(BankStatementImport::class)
            ->ofMany(
                ['period_end' => 'max', 'id' => 'max'],
                fn (Builder $query) => $query
                    ->where('status', 'done')
                    ->whereNotNull('balance')
                    ->whereNotNull('period_end'),
            );
    }

    public function isFromSenior(): bool
    {
        return $this->senior_codemp !== null && filled($this->senior_num_cco);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Normaliza número de conta para comparação com OFX (só dígitos).
     */
    public static function normalizeAccountDigits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    public function accountDigitsForMatch(): string
    {
        $base = self::normalizeAccountDigits($this->account_number);
        $digit = self::normalizeAccountDigits($this->account_digit);

        return $digit !== '' ? $base.$digit : $base;
    }

    /** @return array<string, mixed> */
    public function toListArray(): array
    {
        $statement = $this->relationLoaded('latestStatementImport')
            ? $this->latestStatementImport
            : null;
        $hasStatementBalance = $statement?->balance !== null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'senior_codemp' => $this->senior_codemp,
            'senior_codfil' => $this->senior_codfil,
            'senior_num_cco' => $this->senior_num_cco,
            'senior_descricao' => $this->senior_descricao,
            'bank_code' => $this->bank_code,
            'bank_name' => $this->bank_name,
            'agency' => $this->agency,
            'account_number' => $this->account_number,
            'account_digit' => $this->account_digit,
            'opening_balance' => $this->opening_balance !== null ? (float) $this->opening_balance : null,
            'opening_balance_date' => $this->opening_balance_date?->toDateString(),
            'current_balance' => $hasStatementBalance
                ? (float) $statement->balance
                : ($this->opening_balance !== null ? (float) $this->opening_balance : null),
            'balance_date' => $hasStatementBalance
                ? $statement->period_end?->toDateString()
                : $this->opening_balance_date?->toDateString(),
            'balance_source' => $hasStatementBalance ? 'ofx' : ($this->opening_balance !== null ? 'initial' : null),
            'imported_from_senior_at' => $this->imported_from_senior_at?->toIso8601String(),
            'from_senior' => $this->isFromSenior(),
        ];
    }

    public function auditDescription(string $action): string
    {
        return match ($action) {
            'created' => "Conta bancária criada: {$this->name}",
            'updated' => "Conta bancária atualizada: {$this->name}",
            'deleted' => "Conta bancária excluída: {$this->name}",
            default => "Conta bancária ({$action}): {$this->name}",
        };
    }
}
