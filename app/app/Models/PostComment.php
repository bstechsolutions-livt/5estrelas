<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostComment extends Model
{
    use Auditable;

    protected $fillable = ['post_id', 'user_id', 'content'];

    protected string $auditableModule = 'noticias';
    protected string $auditableEventPrefix = 'comments';
    protected array $auditableEvents = ['created', 'deleted'];

    public function auditDescription(string $action): ?string
    {
        return match ($action) {
            'created' => 'Comentou em uma postagem',
            'deleted' => 'Excluiu um comentário',
            default => null,
        };
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
