<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PayableDocument extends Model
{
    protected $fillable = ['payable_id', 'uploaded_by', 'name', 'doc_type', 'path', 'mime_type', 'size'];

    protected $appends = ['url'];

    /**
     * Tipos de documento do contas a pagar (feedback do cliente).
     * Nem todo título tem todos; a trava de aprovação exige apenas ≥ 1 no total.
     */
    public const TYPES = [
        'nota_fiscal' => 'Nota Fiscal',
        'boleto' => 'Boleto',
        'relatorio' => 'Relatório',
        'comprovacao' => 'Comprovação',
        'outro' => 'Outro',
    ];

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
