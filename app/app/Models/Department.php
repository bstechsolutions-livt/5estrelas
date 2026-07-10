<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Department extends Model
{
    use Auditable;

    protected $fillable = ['name', 'slug', 'is_active', 'area_key', 'manager_id', 'director_id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected string $auditableModule = 'departamentos';
    protected string $auditableEventPrefix = 'departamentos';
    protected array $auditableEvents = ['created', 'updated', 'deleted'];

    public function auditDescription(string $action): ?string
    {
        return match ($action) {
            'created' => "Departamento {$this->name} criado",
            'updated' => "Departamento {$this->name} atualizado",
            'deleted' => "Departamento {$this->name} excluído",
            default => null,
        };
    }

    protected static function booted(): void
    {
        static::creating(function ($dept) {
            if (empty($dept->slug)) {
                $dept->slug = Str::slug($dept->name);
            }
        });

        static::updating(function ($dept) {
            if ($dept->isDirty('name') && !$dept->isDirty('slug')) {
                $dept->slug = Str::slug($dept->name);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function manager(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function director(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }
}
