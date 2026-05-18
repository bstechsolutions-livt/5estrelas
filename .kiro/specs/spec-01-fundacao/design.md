# Spec 01 - Design

## Estrutura de pastas criada

```
5estrelas/
├── app/                              # Projeto Laravel completo
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Auth/LoginController.php
│   │   │   │   └── DashboardController.php
│   │   │   └── Middleware/HandleInertiaRequests.php
│   │   └── Models/User.php
│   ├── bootstrap/app.php             # Middleware Inertia registrado
│   ├── database/
│   │   ├── database.sqlite
│   │   ├── migrations/               # users, cache, jobs (default Laravel)
│   │   └── seeders/DatabaseSeeder.php
│   ├── resources/
│   │   ├── css/app.css               # Tailwind + PrimeIcons + CSS variables
│   │   ├── js/
│   │   │   ├── app.js                # Vue + Inertia + PrimeVue (Aura theme)
│   │   │   ├── Layouts/AppLayout.vue # Sidebar + Header
│   │   │   └── Pages/
│   │   │       ├── Auth/Login.vue
│   │   │       └── Dashboard.vue
│   │   └── views/app.blade.php       # Root template Inertia
│   ├── routes/web.php                # Rotas guest/auth
│   └── vite.config.js                # Vite + Vue + Tailwind + alias @
├── docs/                             # PDFs do cliente
└── .kiro/                            # Steering + specs
```

## Decisões técnicas

- **Banco**: SQLite por enquanto (PostgreSQL fica para quando instalar o Postgres local)
- **Cache/Session**: database driver (Redis fica para depois)
- **Tema PrimeVue**: Aura preset, com possibilidade de override via CSS variables
- **Auth**: session-based via Laravel padrão (Sanctum entra na próxima spec quando precisar de API)

## Layout base

### Sidebar (esquerda, dark `#1e1e2d`)
- Largura fixa 260px
- Header com logo "5E" + nome "5 Estrelas" + botão de colapsar
- Campo de busca
- Menu de navegação (Dashboard, Configurações)
- Animação suave de abrir/fechar via wrapper com largura animada (300ms)

### Header (topo, dark `#1e1e2d`)
- Botão hamburger (aparece quando sidebar fechada)
- Lado direito: avatar + nome do usuário | sininho + botão sair

### Conteúdo principal
- Fundo claro (`bg-gray-50`)
- Padding interno

## Stack instalada

- Laravel 13.9.0
- Inertia 3.1
- Vue 3
- PrimeVue 4 + @primevue/themes (Aura)
- Tailwind CSS 4 + @tailwindcss/vite
- PrimeIcons
