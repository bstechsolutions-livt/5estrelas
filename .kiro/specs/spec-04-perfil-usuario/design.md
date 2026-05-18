# Spec 04 - Design

## Banco

### Migration
```php
Schema::table('users', function (Blueprint $table) {
    $table->string('avatar_path')->nullable()->after('is_active');
});
```

## Backend

### `ProfileController`
- `show()` → renderiza `Pages/Profile/Index.vue` com dados do user atual
- `updateProfile(Request)` → atualiza nome, email, avatar
- `updatePassword(Request)` → valida senha atual e troca
- `removeAvatar()` → remove foto

### Rotas (todas autenticadas, sem permissão extra)
```php
Route::middleware('auth')->group(function () {
    Route::get('/perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/perfil', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/perfil/senha', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/perfil/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
});
```

### Validações
**updateProfile**:
- name: required, string, max:150
- email: required, email, unique:users (ignore self)
- avatar: nullable, image, mimes:jpg,jpeg,png,webp, max:5120

**updatePassword**:
- current_password: required, current_password (rule do Laravel)
- password: required, string, min:8, confirmed, different:current_password
- password_confirmation: required

### Shared props
Adicionar `avatar_url` no `auth.user`:
```php
'avatar_url' => $user->avatar_path ? Storage::url($user->avatar_path) : null,
```

## Frontend

### `Pages/Profile/Index.vue`
3 cards/seções:

1. **Card "Informações pessoais"**
   - Avatar circular com botão "Trocar foto" / "Remover"
   - Input nome
   - Input e-mail
   - Botão "Salvar"

2. **Card "Trocar senha"**
   - Password "Senha atual"
   - Password "Nova senha"
   - Password "Confirmar nova senha"
   - Botão "Atualizar senha"

3. **Card "Conta"**
   - E-mail (read-only display)
   - Status (Ativo/Inativo)
   - Permissões resumidas (badges)
   - Botão "Sair da conta" (vermelho)

### `AppLayout.vue` - mudanças
- "Ver perfil" vira link clicável → `/perfil`
- Avatar usa `auth.user.avatar_url` quando houver
- Mesmo no menu mobile

### Composable `useAuth()` - sem mudanças (avatar_url vem nas shared props)

## Storage
- Pasta: `storage/app/public/avatars/`
- Filename: `avatars/{userId}_{timestamp}.{ext}`
- Ao subir nova foto, **deletar a antiga** se existir

## UX
- Avatar grande na tela de perfil (96x96 ou 128x128)
- Preview imediato ao selecionar arquivo (FileReader)
- Toast de sucesso após cada salvar
- Loading no botão durante o submit
- Limpar form de senha após sucesso
