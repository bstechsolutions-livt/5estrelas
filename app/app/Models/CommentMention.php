<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mention em um comentário de payable.
 * Quando alguém digita @fulano, cria-se um CommentMention que:
 * - Gera notificação pro mencionado
 * - Dá visibilidade do título ao mencionado
 */
class CommentMention extends Model
{
    protected $fillable = ['payable_comment_id', 'mentioned_user_id', 'read'];

    protected function casts(): array
    {
        return ['read' => 'boolean'];
    }

    public function comment(): BelongsTo { return $this->belongsTo(PayableComment::class, 'payable_comment_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'mentioned_user_id'); }
}
