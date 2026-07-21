<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BorderoDocument extends Model
{
    protected $fillable = [
        'bordero_id', 'uploaded_by', 'name', 'path', 'mime_type', 'size',
    ];

    protected $appends = ['url'];

    public function bordero(): BelongsTo
    {
        return $this->belongsTo(Bordero::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
