<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    public const TYPE_CONTA_FINANCEIRA = 'conta_financeira';
    public const TYPE_CENTRO_CUSTO = 'centro_custo';

    public const TYPE_LABELS = [
        self::TYPE_CONTA_FINANCEIRA => 'Conta financeira',
        self::TYPE_CENTRO_CUSTO => 'Centro de custo',
    ];

    protected $fillable = [
        'code', 'description', 'account_type', 'codemp', 'source', 'senior_raw', 'synced_at',
    ];

    protected $casts = [
        'codemp' => 'integer',
        'senior_raw' => 'array',
        'synced_at' => 'datetime',
    ];

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->account_type] ?? $this->account_type;
    }
}
