# Spec 06 - Design

## Migration `audit_logs`
```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('user_name')->nullable();
    $table->string('event', 100);
    $table->string('module', 50);
    $table->text('description')->nullable();
    $table->string('auditable_type')->nullable();
    $table->unsignedBigInteger('auditable_id')->nullable();
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->json('metadata')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamp('created_at')->useCurrent();

    $table->index('user_id');
    $table->index('event');
    $table->index('module');
    $table->index(['auditable_type', 'auditable_id']);
    $table->index('created_at');
});
```

## Model `AuditLog`
- Read-only (sem updated_at, sem update/delete pela app)
- `casts`: `old_values`, `new_values`, `metadata` => array
- Relacionamento `user()` opcional
- Scopes para filtros: `forUser`, `forModule`, `forEvent`, `between`

## Service `AuditLogger`
```php
class AuditLogger {
    public static function log(
        string $event,
        string $module,
        ?string $description = null,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): AuditLog {
        $request = request();
        $user = auth()->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'event' => $event,
            'module' => $module,
            'description' => $description,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
```

## Trait `Auditable`
```php
trait Auditable {
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => self::auditEvent($m, 'created'));
        static::updated(fn ($m) => self::auditEvent($m, 'updated'));
        static::deleted(fn ($m) => self::auditEvent($m, 'deleted'));
    }

    protected static function auditEvent(Model $model, string $action): void
    {
        $events = $model->auditableEvents ?? ['created', 'updated', 'deleted'];
        if (!in_array($action, $events, true)) return;

        $module = $model->auditableModule ?? 'sistema';
        $prefix = $model->auditableEventPrefix ?? class_basename($model);
        $event = strtolower("{$module}.{$prefix}.{$action}");

        $except = $model->auditableExcept ?? [];
        $hidden = ['password', 'remember_token', ...$except];

        $old = $action === 'updated' ? Arr::except($model->getOriginal(), $hidden) : null;
        $new = match($action) {
            'created' => Arr::except($model->getAttributes(), $hidden),
            'updated' => Arr::except($model->getChanges(), $hidden),
            'deleted' => Arr::except($model->getOriginal(), $hidden),
        };

        // Diff em update: só campos que realmente mudaram
        if ($action === 'updated') {
            $old = Arr::only($old, array_keys($new));
            if (empty($new)) return; // nada mudou
        }

        AuditLogger::log(
            event: $event,
            module: $module,
            description: $model->auditDescription($action) ?? null,
            auditable: $model,
            oldValues: $old,
            newValues: $new,
        );
    }

    protected function auditDescription(string $action): ?string
    {
        return null;
    }
}
```

## AuditLogController
- `index(Request)` - lista paginada com filtros
- Autorizado por `permission:auditoria.visualizar`

## Frontend

### `Pages/Audit/Index.vue`
- Header: filtros (DatePicker, Select módulo, Select evento, MultiSelect usuário, busca)
- DataTable com colunas:
  - Data/Hora (formatada)
  - Usuário (nome + IP em pequeno embaixo)
  - Módulo (badge)
  - Evento (código em mono)
  - Descrição
  - Ações (botão "Detalhes")
- Dialog de detalhes:
  - Header com event/módulo/data
  - Seção old vs new (lado a lado em colunas, destacando diferenças)
  - Metadata (se houver)
  - User agent

### Helpers de filtros
- Endpoint `/auditoria/filtros` ou enviar nas props as listas (módulos distintos, eventos distintos)

## Pontos de instrumentação

### LoginController
- `store()` quando Auth::attempt OK → log `auth.login.success`
- `store()` quando Auth::attempt FAIL → log `auth.login.failed` (sem user)
- `destroy()` → log `auth.logout` antes do logout

### PasswordResetLinkController
- `store()` → log `auth.password.reset_requested` (mesmo se email não existe — útil pra detectar tentativa)

### NewPasswordController
- após Password::reset OK → log `auth.password.reset_completed`

### SettingsController
- `updateAppearance()` → comparar antes/depois e logar `aparencia.updated` com diff

### UserPermissionController
- `update()` → comparar antes/depois e logar `usuarios.permissions_updated` com keys old/new

### UserController
- `toggleActive()` → log `usuarios.toggle_active`
- (criar/editar/excluir já cobertos pela trait Auditable no model)

### ProfileController
- `updateProfile()` → log `perfil.updated` com diff (nome, email, avatar)
- `updatePassword()` → log `perfil.password_changed` (sem logar senha)
- `removeAvatar()` → log `perfil.avatar_removed`

## Permissão e menu
- Adicionar `auditoria.visualizar` no PermissionsSeeder (módulo `auditoria`)
- Item "Auditoria" no menu lateral do AppLayout, condicionado ao `can('auditoria.visualizar')`

## Storage
- Tabela única, sem soft delete
- `created_at` único timestamp (sem updated_at)
