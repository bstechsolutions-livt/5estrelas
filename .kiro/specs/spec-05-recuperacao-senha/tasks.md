# Spec 05 - Tasks

- [x] 1. Verificar `password_reset_tokens` table (já criada pelo Laravel default)
- [x] 2. Criar `Auth\PasswordResetLinkController` (show + store)
- [x] 3. Criar `Auth\NewPasswordController` (show + store)
- [x] 4. Criar `App\Notifications\ResetPasswordNotification` customizada (em pt_BR + nome do sistema)
- [x] 5. Sobrescrever `sendPasswordResetNotification` no model `User`
- [x] 6. Adicionar rotas `/esqueci-senha` e `/redefinir-senha/{token}`
- [x] 7. `Pages/Auth/ForgotPassword.vue`
- [x] 8. `Pages/Auth/ResetPassword.vue`
- [x] 9. Adicionar link "Esqueci minha senha" na tela de Login
- [x] 10. Validar local: solicitar reset, ler e-mail no log, redefinir senha, logar

## Status: ✅ Concluída
