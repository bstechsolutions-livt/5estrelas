<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayableComment extends Model
{
    protected $fillable = ['payable_id', 'user_id', 'body', 'type', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
