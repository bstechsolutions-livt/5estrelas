<?php

namespace App\Models;

use App\Services\AuditLogger;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenFinanceAccountConnection extends Model
{
    use Auditable;

    public const STATUS_PENDING_DATA = 'pending_data';

    public const STATUS_PAYER_READY = 'payer_ready';

    public const STATUS_ACCOUNT_READY = 'account_ready';

    public const STATUS_AWAITING_AUTHORIZATION = 'aguardando_autorizacao';

    public const STATUS_CONNECTED = 'conectada';

    public const STATUS_ERROR = 'erro';

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public string $auditableModule = 'open_finance';

    public string $auditableEventPrefix = 'conexao';

    /** Nunca auditar o link de consentimento (mesmo criptografado). */
    public array $auditableExcept = ['openfinance_link'];

    protected $fillable = [
        'bank_account_id',
        'open_finance_payer_id',
        'environment',
        'provider_account_hash',
        'openfinance_link',
        'openfinance_id',
        'status',
        'statement_activated',
        'last_error_kind',
        'last_error_message',
        'provider_synced_at',
        'authorized_at',
    ];

    protected $hidden = [
        'openfinance_link',
    ];

    protected function casts(): array
    {
        return [
            'openfinance_link' => 'encrypted',
            'statement_activated' => 'boolean',
            'provider_synced_at' => 'datetime',
            'authorized_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(OpenFinancePayer::class, 'open_finance_payer_id');
    }

    public function hasAuthorizationLink(): bool
    {
        return filled($this->openfinance_link);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED && filled($this->openfinance_id);
    }

    public function auditDescription(string $action): string
    {
        $account = $this->relationLoaded('bankAccount')
            ? $this->bankAccount?->name
            : null;
        $label = $account ?: ('#'.$this->bank_account_id);

        return match ($action) {
            'created' => "Conexão Open Finance criada para {$label}",
            'updated' => "Conexão Open Finance atualizada para {$label} ({$this->status})",
            'deleted' => "Conexão Open Finance removida para {$label}",
            default => "Conexão Open Finance ({$action}) para {$label}",
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_NOT_CONFIGURED => 'Não configurada',
            self::STATUS_PENDING_DATA => 'Dados pendentes',
            self::STATUS_PAYER_READY => 'Pagador pronto',
            self::STATUS_ACCOUNT_READY => 'Conta pronta',
            self::STATUS_AWAITING_AUTHORIZATION => 'Aguardando autorização',
            self::STATUS_CONNECTED => 'Conectada',
            self::STATUS_ERROR => 'Erro',
            default => 'Não configurada',
        };
    }

    public static function recordSafeAudit(self $connection, string $event, string $description): void
    {
        AuditLogger::log(
            event: $event,
            module: 'open_finance',
            description: $description,
            auditable: $connection,
            newValues: [
                'status' => $connection->status,
                'environment' => $connection->environment,
                'has_openfinance_id' => filled($connection->openfinance_id),
            ],
        );
    }
}
