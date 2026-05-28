# 5 Estrelas — Plataforma de Gestão

Sistema de gestão completa para o cliente **5 Estrelas**, desenvolvido em parceria entre **BS Tech Solutions** e **Easy Tech**.

Cobre: gestão financeira, compras, suprimentos, contratos, operação de campo (fiscalização), ponto com geolocalização, escala de brigadistas, auditoria e dashboards gerenciais.

## Stack

| Camada | Tecnologia |
|--------|-----------|
| Backend | Laravel 13 (PHP 8.3+) |
| Frontend Web | Vue 3 + Inertia.js + PrimeVue 4 |
| Estilização | Tailwind CSS 4 + PrimeVue CSS variables (white-label) |
| Auth | Laravel session (Sanctum pro mobile no futuro) |
| Database | PostgreSQL 16 |
| Cache/Queue | Redis (em produção) |
| Realtime | Laravel Reverb (WebSockets) |
| Storage | Local (dev) / S3-compatível Backblaze B2 (futuro) |
| App Mobile | Flutter com WebView |
| CI/CD | GitHub Actions |

## Estrutura do repositório

```
5estrelas/
├── app/                    # Backend Laravel + Frontend Vue (Inertia)
│   ├── app/
│   ├── routes/
│   ├── resources/js/       # Vue + PrimeVue + Tailwind
│   └── database/
├── mobile/                 # App Flutter (WebView wrapper)
├── docs/                   # Documentação do projeto
├── .kiro/steering/         # Regras e guias do projeto
├── docker-compose.yml      # Postgres local
└── README.md
```

## Setup local

### Pré-requisitos

- PHP 8.3+ com extensões: `pgsql`, `pdo_pgsql`, `mbstring`, `xml`, `zip`
- Composer 2+
- Node 20+ / npm
- Docker + Docker Compose (pra subir Postgres)
- `pg_dump` / `psql` (`apt install postgresql-client`)
- Flutter 3.11+ (só pra trabalhar no app mobile)

No Ubuntu/Debian:

```bash
sudo apt install -y php8.3 php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-zip composer postgresql-client
```

### 1. Clonar e instalar

```bash
git clone git@github.com:bstechsolutions-livt/5estrelas.git
cd 5estrelas

# Postgres local
docker compose up -d

# Backend
cd app
cp .env.example .env
composer install
php artisan key:generate

# Frontend
npm install
```

### 2. Migrar e popular dados

```bash
# Cria tabelas + admins (Bruno + Admin)
php artisan migrate:fresh --seed

# Popula massa de teste (20 users, posts, comentários, etc)
php artisan db:seed --class=DemoSeeder
```

### 3. Subir os serviços

São 3 processos. Abra 3 terminais:

```bash
# Terminal 1 — Servidor web (porta 8090)
php artisan serve --host=0.0.0.0 --port=8090

# Terminal 2 — Reverb (WebSocket pra notificações em tempo real)
php artisan reverb:start --host=0.0.0.0 --port=8080

# Terminal 3 — Build do frontend (modo watch)
npm run dev
```

Acesse `http://localhost:8090`.

### 4. Login

| Usuário | E-mail | Senha | ID |
|---------|--------|-------|-----|
| Admin | `admin@5estrelas.com.br` | `password` | 1 |
| Bruno | `bruno@bstechsolutions.com` | `123456789` | 2 |

(login aceita e-mail OU ID numérico)

## Comandos úteis

```bash
# Disparar uma notificação manual (testar push em tempo real)
php artisan notify:send --email=bruno@bstechsolutions.com --title="Teste" --type=info

# Backup do banco (gera ZIP em storage/app/backups/)
php artisan backup:run --only-db

# Limpar backups antigos (retenção 7 dias)
php artisan backup:clean

# Resetar tudo (CUIDADO: apaga dados)
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
```

## App Mobile (Flutter)

```bash
cd mobile
flutter pub get
flutter run --dart-define=BACKEND_URL=http://<seu-ip>:8090 -d <device-id>
```

Para conectar do celular ao servidor local, recomendamos **Tailscale** (rede VPN). O IP da máquina dev fica acessível pelo celular via Tailscale.

## Documentação

- [`.kiro/steering/projeto.md`](.kiro/steering/projeto.md) — Visão geral do projeto, escopo, cronograma
- [`.kiro/steering/regras.md`](.kiro/steering/regras.md) — Regras de trabalho, convenções
- [`.kiro/steering/stack.md`](.kiro/steering/stack.md) — Decisões técnicas e por quê
- [`.kiro/steering/auditoria.md`](.kiro/steering/auditoria.md) — Padrão de auditoria
- [`.kiro/steering/mobile-ux.md`](.kiro/steering/mobile-ux.md) — Padrões UX mobile
- [`.kiro/steering/mobile-build.md`](.kiro/steering/mobile-build.md) — Build, push e Shorebird
- [`docs/`](docs/) — PDFs do contrato e infra

## Repositório

- GitHub: https://github.com/bstechsolutions-livt/5estrelas
- Branch principal: `main`

## Status

Estado atual do projeto rastreado em [`.kiro/steering/projeto.md`](.kiro/steering/projeto.md).
