<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserShortcut extends Model
{
    protected $fillable = ['user_id', 'menu_key', 'position'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
