<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $table = 'intranet_files';  // Define a tabela utilizada

    protected $fillable = [
        'original_name',
        'stored_name',
        'extension',
        'upload_date',
        'path',
        'external_link',
        'application',
        'folder',
        'user_id',
        'delted_at',
        'deleted_at',
        'deleted_perm_at'
    ];

    public $timestamps = true; // Laravel gerencia automaticamente os campos created_at e updated_at

    protected $primaryKey = 'id';  // Chave primária
    public $incrementing = true;   // O campo ID será preenchido pela sequência
    protected $keyType = 'int';    // O tipo da chave é inteiro
    public $sequence = 'INTRANET_FILES_SEQ'; // Sequence correta no Oracle

    protected static function booted(): void
    {
        static::addGlobalScope('ativos', function ($query) {
            $query->whereNull('deleted_at');
        });


        // Exemplo ignorando o filtro permanente:
        // File::withoutGlobalScope('ativos')->get();

    }
}
