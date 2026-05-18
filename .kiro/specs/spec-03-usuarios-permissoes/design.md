# Spec 03 - Design

## Modelo de dados

### Adicionar coluna `is_active` em `users`
```sql
ALTER TABLE users ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE;
```

### Tabela `permissions`
```sql
CREATE TABLE permissions (
    id            BIGSERIAL PRIMARY KEY,
    key           VARCHAR(100) UNIQUE NOT NULL,  -- ex: 'usuarios.criar'
    label         VARCHAR(150) NOT NULL,         -- ex: 'Criar usuários'
    module        VARCHAR(50) NOT NULL,          -- ex: 'usuarios'
    description   TEXT NULL,
    created_at    TIMESTAMP,
    updated_at    TIMESTAMP
);
CREATE INDEX permissions_module_idx ON permissions(module);
```

### Pivot `user_permission`
```sql
CREATE TABLE user_permission (
    user_id        BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    permission_id  BIGINT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, permission_id)
);
```

## Backend

### `app/Models/Permission.php`
```php
class Permission extends Model {
    protected $fillable = ['key', 'label', 'module', 'description'];
    public function users() { return $this->belongsToMany(User::class, 'user_permission'); }
}
```

### `app/Models/User.php` - adicionar
```php
public function permissions() {
    return $this->belongsToMany(Permission::class, 'user_permission');
}

public function hasPermission(string $key): bool {
    if ($this->cachedPermissions === null) {
        $this->cachedPermissions = $this->permissions()->pluck('key')->toArray();
    }
    return in_array('*', $this->cachedPermissions) || in_array($key, $this->cachedPermissions);
}

protected $cachedPermissions = null;
```

### Catálogo de permissões (PermissionsSeeder)
```php
$permissions = [
    // Curinga (admin)
    ['key' => '*', 'label' => 'Acesso total (admin)', 'module' => 'sistema'],

    // Usuários
    ['key' => 'usuarios.listar', 'label' => 'Listar usuários', 'module' => 'usuarios'],
    ['key' => 'usuarios.criar', 'label' => 'Criar usuários', 'module' => 'usuarios'],
    ['key' => 'usuarios.editar', 'label' => 'Editar usuários', 'module' => 'usuarios'],
    ['key' => 'usuarios.excluir', 'label' => 'Excluir usuários', 'module' => 'usuarios'],
    ['key' => 'usuarios.gerenciar_permissoes', 'label' => 'Gerenciar permissões', 'module' => 'usuarios'],

    // Aparência
    ['key' => 'aparencia.visualizar', 'label' => 'Ver aparência', 'module' => 'aparencia'],
    ['key' => 'aparencia.editar', 'label' => 'Editar aparência', 'module' => 'aparencia'],
];
```

### `DatabaseSeeder` - admin recebe `*`
```php
$admin = User::firstOrCreate(...);
$wildcard = Permission::where('key', '*')->first();
$admin->permissions()->syncWithoutDetaching([$wildcard->id]);
```

### Middleware `EnsurePermission`
```php
class EnsurePermission {
    public function handle($request, Closure $next, string $permission) {
        if (!$request->user() || !$request->user()->hasPermission($permission)) {
            abort(403, 'Você não tem permissão para acessar este recurso.');
        }
        return $next($request);
    }
}
```
Registrar em `bootstrap/app.php`:
```php
$middleware->alias(['permission' => \App\Http\Middleware\EnsurePermission::class]);
```

### Controllers

**`UserController`**:
- `index()` - lista paginada com filtro
- `create()` - form de criação
- `store(Request)` - cria
- `edit($id)` - form de edição
- `update(Request, $id)` - atualiza
- `toggleActive($id)` - ativa/inativa
- `destroy($id)` - exclui (soft? não — hard por enquanto, sem soft delete)

**`UserPermissionController`**:
- `edit($id)` - mostra todas as permissões agrupadas + as do usuário
- `update(Request, $id)` - sync das permissões

### Rotas
```php
Route::middleware('auth')->group(function () {
    // Usuários
    Route::middleware('permission:usuarios.listar')->group(function () {
        Route::get('/usuarios', [UserController::class, 'index']);
    });
    Route::middleware('permission:usuarios.criar')->group(function () {
        Route::get('/usuarios/criar', [UserController::class, 'create']);
        Route::post('/usuarios', [UserController::class, 'store']);
    });
    Route::middleware('permission:usuarios.editar')->group(function () {
        Route::get('/usuarios/{id}/editar', [UserController::class, 'edit']);
        Route::put('/usuarios/{id}', [UserController::class, 'update']);
        Route::post('/usuarios/{id}/toggle-active', [UserController::class, 'toggleActive']);
    });
    Route::middleware('permission:usuarios.excluir')->group(function () {
        Route::delete('/usuarios/{id}', [UserController::class, 'destroy']);
    });
    Route::middleware('permission:usuarios.gerenciar_permissoes')->group(function () {
        Route::get('/usuarios/{id}/permissoes', [UserPermissionController::class, 'edit']);
        Route::put('/usuarios/{id}/permissoes', [UserPermissionController::class, 'update']);
    });

    // Aparência (já existia, agora protegida)
    Route::middleware('permission:aparencia.editar')->group(function () {
        Route::get('/settings/aparencia', [SettingsController::class, 'appearance']);
        Route::post('/settings/aparencia', [SettingsController::class, 'updateAppearance']);
    });
});
```

### Shared props
```php
'auth' => [
    'user' => $request->user() ? [
        'id' => $request->user()->id,
        'name' => $request->user()->name,
        'email' => $request->user()->email,
        'permissions' => $request->user()->permissions()->pluck('key')->toArray(),
    ] : null,
],
```

## Frontend

### Composable `useAuth()`
```js
export function useAuth() {
    const page = usePage()
    const user = computed(() => page.props.auth?.user)
    const permissions = computed(() => user.value?.permissions || [])

    function can(permission) {
        return permissions.value.includes('*') || permissions.value.includes(permission)
    }
    function canAny(...keys) {
        return keys.some(k => can(k))
    }
    return { user, permissions, can, canAny }
}
```

### Páginas
- `Pages/Users/Index.vue` - DataTable com paginação, filtro, ações
- `Pages/Users/Form.vue` - form de criar/editar (mesmo componente, prop `mode`)
- `Pages/Users/Permissions.vue` - lista agrupada por módulo com checkboxes

### AppLayout - menu condicional
```js
const menuItems = computed(() => [
    { label: 'Dashboard', icon: 'pi pi-home', href: '/dashboard' },
    can('usuarios.listar') && { label: 'Usuários', icon: 'pi pi-users', href: '/usuarios' },
    can('aparencia.editar') && { label: 'Aparência', icon: 'pi pi-palette', href: '/settings/aparencia' },
].filter(Boolean))
```

## Validações
- Email único na tabela users
- Senha mínima 8 chars no criar (opcional no editar)
- Não permitir excluir o próprio usuário logado
- Não permitir remover todas as permissões do último admin (`*`)
