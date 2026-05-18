# Spec 01 - Fundação: Projeto Laravel + Auth + Layout Base

## Objetivo
Criar o projeto Laravel com autenticação funcionando e o layout base (sidebar escura + header + área de conteúdo) inspirado na EasyIntra. Ao final, o Bruno deve conseguir rodar local, fazer login e ver o dashboard vazio com o layout correto.

## Requisitos

### R1: Projeto Laravel criado na raiz do workspace
- Laravel 12 com PHP 8.3+
- Vue 3 + Inertia.js configurados
- PrimeVue 4 instalado e configurado
- Tailwind CSS 4 configurado
- PostgreSQL como banco (configurável via .env)
- Redis configurado para cache/session (configurável via .env)

### R2: Autenticação básica
- Tela de login funcional (email + senha)
- Registro desabilitado (usuários criados via seeder/admin)
- Logout funcionando
- Middleware de auth protegendo rotas internas
- Seeder com usuário admin padrão (admin@5estrelas.com.br / password)

### R3: Layout base inspirado na EasyIntra
- Sidebar escura (dark) à esquerda, colapsável
- Menu com ícones + texto (por enquanto: Dashboard, Configurações)
- Header superior com: logo/nome do sistema à esquerda, nome do usuário + avatar à direita
- Área de conteúdo principal com fundo claro
- Responsivo (sidebar vira drawer no mobile)
- Saudação "Olá, [NOME] 👋" no dashboard

### R4: Dashboard placeholder
- Página inicial após login
- Mostra saudação com nome do usuário
- Cards de "Acesso Rápido" vazios (placeholder)
- Área de "Resumo" vazia (placeholder)
- Sem funcionalidade real ainda — só o layout

## Entregável
- `php artisan serve` rodando local
- `npm run dev` rodando o Vite
- Acessar http://localhost:8000, ver tela de login
- Logar com admin@5estrelas.com.br / password
- Ver dashboard com layout sidebar dark + header + saudação

## Fora do escopo desta spec
- White-label/configurações de tema (próxima spec)
- Permissões (próxima spec)
- Qualquer módulo funcional
- App mobile
