<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PayableDocument extends Model
{
    protected $fillable = ['payable_id', 'uploaded_by', 'name', 'path', 'mime_type', 'size'];

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }
}
