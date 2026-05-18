# Spec 05 - Design

## Backend

### Controllers

**`Auth\PasswordResetLinkController`**
- `show()` → tela `Auth/ForgotPassword.vue`
- `store(Request)` → valida e-mail, envia link via `Password::sendResetLink()`. Sempre retorna sucesso (não revela existência do e-mail).

**`Auth\NewPasswordController`**
- `show($token)` → tela `Auth/ResetPassword.vue` com email e token na query
- `store(Request)` → valida e usa `Password::reset()` para atualizar a senha; loga o usuário e redireciona

### Rotas (guest)
```php
Route::middleware('guest')->group(function () {
    Route::get('/login', ...);
    Route::post('/login', ...);

    // Recuperação de senha
    Route::get('/esqueci-senha', [PasswordResetLinkController::class, 'show'])->name('password.request');
    Route::post('/esqueci-senha', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/redefinir-senha/{token}', [NewPasswordController::class, 'show'])->name('password.reset');
    Route::post('/redefinir-senha', [NewPasswordController::class, 'store'])->name('password.update');
});
```

### Notification customizada
Criar `App\Notifications\ResetPasswordNotification` que estende a do Laravel mas com:
- Subject usando nome do sistema (Setting::get('app_name'))
- Texto em português
- Botão com cor primária do sistema
- (futuro) Logo do sistema

### Configurações
**`.env` atualizar:**
```
MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@5estrelas.com.br"
MAIL_FROM_NAME="${APP_NAME}"
```

Por padrão Laravel já fica em `log`, mas garantir.

### Validações

**Solicitar reset:**
- email: required, email

**Reset:**
- token: required
- email: required, email
- password: required, string, min:8, confirmed

## Frontend

### `Pages/Auth/ForgotPassword.vue`
- Mesmo visual do Login (background com tema, logo, etc)
- Card branco com:
  - Título "Esqueci minha senha"
  - Texto explicativo
  - Input e-mail (PrimeVue)
  - Botão "Enviar link"
  - Link "Voltar ao login"
- Após sucesso: mostrar mensagem "Se o e-mail estiver cadastrado, você receberá um link..."

### `Pages/Auth/ResetPassword.vue`
- Mesmo visual
- Card com:
  - Título "Redefinir senha"
  - Subtítulo com o e-mail (read-only)
  - Hidden: token, email
  - Input password (com força)
  - Input confirmação
  - Botão "Redefinir e entrar"
  - Link "Voltar ao login"

### `Pages/Auth/Login.vue` - adicionar link
Abaixo do checkbox "Lembrar-me", à direita:
```vue
<Link href="/esqueci-senha" class="text-sm text-[primary] hover:underline">
  Esqueci minha senha
</Link>
```

## Dependências Laravel
- `Illuminate\Support\Facades\Password` (já vem)
- `App\Notifications\ResetPasswordNotification` (criar)
- Notification gatilhada via `User::sendPasswordResetNotification($token)` (sobrescrever no Model User)

## Tabela
- `password_reset_tokens` já existe (migration default do Laravel) — só validar
