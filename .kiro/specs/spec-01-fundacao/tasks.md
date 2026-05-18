# Spec 01 - Tasks

- [x] 1. Criar projeto Laravel 12 e organizar workspace
  - Criar projeto Laravel via composer
  - Mover tudo para pasta `app/` mantendo `docs/` e `.kiro/` na raiz

- [x] 2. Instalar dependências de frontend
  - Inertia (server e client)
  - Vue 3 + @vitejs/plugin-vue
  - PrimeVue 4 + @primevue/themes + primeicons
  - Tailwind CSS 4 + @tailwindcss/vite

- [x] 3. Configurar Vite, Inertia e PrimeVue
  - vite.config.js com Vue, Tailwind e alias `@`
  - app.js com createInertiaApp + PrimeVue Aura theme
  - app.blade.php como root template
  - HandleInertiaRequests middleware (auth.user shared)
  - bootstrap/app.php registrando o middleware

- [x] 4. Configurar autenticação
  - LoginController (show/store/destroy)
  - DashboardController
  - Rotas guest (login) e auth (logout, dashboard)
  - DatabaseSeeder com admin@5estrelas.com.br / password

- [x] 5. Criar tela de login
  - Pages/Auth/Login.vue com PrimeVue InputText, Password, Button, Checkbox
  - Background gradient escuro
  - Validação de erros

- [x] 6. Criar layout base (AppLayout.vue)
  - Sidebar dark com logo + busca + menu + animação suave
  - Header dark com hamburger + usuário + sininho + sair
  - Sidebar mobile com overlay e Teleport
  - Transições CSS para abrir/fechar

- [x] 7. Criar página Dashboard placeholder
  - Apenas saudação "Olá, [nome] 👋"
  - Subtexto "Bem-vindo ao painel de gestão"

- [x] 8. Validar local
  - php artisan migrate:fresh --seed funcionando
  - npm run build passando
  - Login funcional com admin@5estrelas.com.br / password
  - Layout responsivo (desktop e mobile)
  - Animação de sidebar suave

## Status: ✅ Concluída
