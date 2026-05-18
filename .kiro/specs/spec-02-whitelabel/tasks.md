# Spec 02 - Tasks

- [x] 1. Criar migration `settings`
  - Tabela com colunas key, value, type, timestamps

- [x] 2. Criar Model `Setting` com helpers
  - `get($key, $default)`, `set($key, $value, $type)`, `allKeyed()`
  - Cache via `Cache::rememberForever`

- [x] 3. Criar `SettingsSeeder` com valores padrão
  - app_name, primary_color, secondary_color, logo_path, favicon_path, login_bg_path
  - Registrado no `DatabaseSeeder`

- [x] 4. Compartilhar settings no `HandleInertiaRequests`
  - Bloco `theme` no `share()`
  - URLs de logo/favicon/login_bg via `Storage::url()`
  - Flash messages compartilhadas

- [x] 5. Criar `SettingsController`
  - `appearance()` → renderiza `Pages/Settings/Appearance.vue`
  - `updateAppearance()` → valida e salva
  - Upload das imagens em `storage/app/public/branding/`

- [x] 6. Criar rotas
  - GET `/settings/aparencia`
  - POST `/settings/aparencia`

- [x] 7. Rodar `php artisan storage:link`

- [x] 8. Criar composable `useTheme()`
  - Injeta CSS variables no `:root`
  - Atualiza favicon e title dinâmico
  - Reage a mudanças nos shared props via `watchEffect`

- [x] 9. Aplicar `useTheme()` globalmente
  - Invocado em `AppLayout.vue` e `Login.vue`

- [x] 10. Atualizar `AppLayout.vue`
  - Usa `theme.app_name`, `theme.logo_url`
  - Cores primária/secundária aplicadas via style binding
  - Item de menu "Aparência" adicionado

- [x] 11. Atualizar `Pages/Auth/Login.vue`
  - Usa `theme.app_name`, `theme.logo_url`, `theme.login_bg_url`
  - Cor primária aplicada no botão
  - Fallbacks com gradient e iniciais

- [x] 12. Criar `Pages/Settings/Appearance.vue`
  - Form com InputText, ColorPicker, FileUpload customizado
  - Preview de imagens
  - Submit + Toast de sucesso

- [x] 13. Atualizar menu lateral
  - Item "Aparência" leva para `/settings/aparencia`

- [x] 14. Validar local
  - Migrations rodaram com sucesso
  - Storage link criado
  - Build do frontend passa sem erros (npm run build)
  - Servidores rodando (php artisan serve + npm run dev)
  - Bug do upload (403) corrigido — `Storage::disk('public')->putFileAs()`
  - Limites aumentados: logo 20MB, favicon 5MB, bg 30MB
  - Mensagens de validação traduzidas pra pt_BR
  - Logo mobile e bg mobile adicionados
  - Login com logo: esconde nome/subtexto e amplia logo

## Status: ✅ Concluída e testada pelo usuário
