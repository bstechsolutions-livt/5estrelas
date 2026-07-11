<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BorderoAutoSetting extends Model
{
    protected $fillable = [
        'cron_enabled',
        'last_cron_at',
        'last_cron_count',
    ];

    protected $casts = [
        'cron_enabled' => 'boolean',
        'last_cron_at' => 'datetime',
        'last_cron_count' => 'integer',
    ];

    public static function instance(): self
    {
        return static::query()->firstOrCreate([], ['cron_enabled' => true]);
    }
}
