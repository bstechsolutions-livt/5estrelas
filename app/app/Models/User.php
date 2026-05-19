<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Traits\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_active', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable, Auditable;

    protected ?array $cachedPermissions = null;

    protected string $auditableModule = 'usuarios';
    protected string $auditableEventPrefix = 'users';
    protected array $auditableEvents = ['created', 'updated', 'deleted'];
    protected array $auditableExcept = ['avatar_path']; // muda muito, vamos logar via Profile

    public function auditDescription(string $action): ?string
    {
        return match ($action) {
            'created' => "Usuário {$this->name} criado",
            'updated' => "Usuário {$this->name} atualizado",
            'deleted' => "Usuário {$this->name} excluído",
            default => null,
        };
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permission');
    }

    public function permissionKeys(): array
    {
        if ($this->cachedPermissions === null) {
            $this->cachedPermissions = $this->permissions()->pluck('key')->toArray();
        }
        return $this->cachedPermissions;
    }

    public function hasPermission(string $key): bool
    {
        $keys = $this->permissionKeys();
        return in_array('*', $keys, true) || in_array($key, $keys, true);
    }

    public function hasAnyPermission(array $keys): bool
    {
        $userKeys = $this->permissionKeys();
        if (in_array('*', $userKeys, true)) {
            return true;
        }
        foreach ($keys as $k) {
            if (in_array($k, $userKeys, true)) {
                return true;
            }
        }
        return false;
    }

    public function flushPermissionCache(): void
    {
        $this->cachedPermissions = null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
