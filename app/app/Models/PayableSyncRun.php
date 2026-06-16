<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de uma execução do Payables_Sync (requirement 9 — observabilidade).
 * Guarda horários, ambiente, modo, gatilho, contagens e resultado.
 */
class PayableSyncRun extends Model
{
    public const STATUS_RUNNING = 'em_andamento';
    public const STATUS_SUCCESS = 'sucesso';
    public const STATUS_FAILED = 'falha';
    public const STATUS_SKIPPED = 'ignorado'; // desabilitado por config / sobreposição

    public const MODE_INCREMENTAL = 'incremental';
    public const MODE_FULL = 'full';

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_SCHEDULED = 'agendado';

    protected $fillable = [
        'environment', 'mode', 'trigger', 'status',
        'started_at', 'finished_at',
        'inserted_count', 'updated_count', 'missing_count',
        'window_start', 'window_end', 'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'inserted_count' => 'integer',
        'updated_count' => 'integer',
        'missing_count' => 'integer',
    ];

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }
}
