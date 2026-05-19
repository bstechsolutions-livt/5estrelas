<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    use Auditable;

    public const TYPE_HIGHLIGHT = 'highlight';
    public const TYPE_NEWS = 'news';

    protected $fillable = [
        'type', 'title', 'content', 'image_path',
        'published_at', 'expires_at', 'is_active', 'created_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected string $auditableModule = 'noticias';
    protected string $auditableEventPrefix = 'posts';

    public function auditDescription(string $action): ?string
    {
        $typeName = $this->type === self::TYPE_HIGHLIGHT ? 'Destaque' : 'Notícia';
        return match ($action) {
            'created' => "{$typeName} \"{$this->title}\" criado",
            'updated' => "{$typeName} \"{$this->title}\" atualizado",
            'deleted' => "{$typeName} \"{$this->title}\" excluído",
            default => null,
        };
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_likes')
            ->withPivot('created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        $now = now();
        return $q->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            });
    }

    public function scopeHighlights(Builder $q): Builder
    {
        return $q->where('type', self::TYPE_HIGHLIGHT);
    }

    public function scopeNews(Builder $q): Builder
    {
        return $q->where('type', self::TYPE_NEWS);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }
}
