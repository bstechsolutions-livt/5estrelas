# Spec 06 - Auditoria base

## Objetivo
Implementar a estrutura de auditoria descrita no steering (`auditoria.md`) e instrumentar todos os pontos atuais do sistema para registrar logs. Criar tela de visualização com filtros.

## Requisitos

### R1: Tabela `audit_logs`
- Migration conforme estrutura do steering
- Indexes em `user_id`, `event`, `module`, `auditable_type+auditable_id`, `created_at`

### R2: Service `AuditLogger`
- Método estático `log()` que recebe event, module, description, auditable (model), oldValues, newValues, metadata
- Captura automática de `user_id`, `user_name`, `ip_address`, `user_agent` do request atual
- Funciona mesmo sem usuário logado (eventos de auth.login.failed por exemplo)

### R3: Trait `Auditable` para Models
- Listeners automáticos para `created`, `updated`, `deleted`
- Configurável: `$auditableEvents`, `$auditableExcept`, `$auditableModule`, `$auditableEventPrefix`
- Calcula diff (old/new values) automaticamente em updates
- Filtra campos sensíveis

### R4: Permissão `auditoria.visualizar`
- Adicionar à seeder de permissões
- Admin já pega via `*`

### R5: Tela `/auditoria`
- DataTable do PrimeVue com paginação server-side
- Colunas: Data/Hora, Usuário, Módulo, Evento, Descrição, Ações
- Filtros: período (data inicial/final), módulo (select), evento (select), usuário (select), busca
- Botão "Detalhes" em cada linha → abre Dialog mostrando old/new values formatados
- Item de menu "Auditoria" (visível só com permissão)

### R6: Instrumentar pontos existentes

**Auth (LoginController + Reset):**
- `auth.login.success` - sucesso
- `auth.login.failed` - falha
- `auth.logout`
- `auth.password.reset_requested`
- `auth.password.reset_completed`

**Usuários (UserController + UserPermissionController):**
- Trait `Auditable` no model `User` (created/updated/deleted)
- `usuarios.toggle_active` - ativação/desativação
- `usuarios.permissions_updated` - sync de permissões (com diff de keys)

**Settings (SettingsController):**
- `aparencia.updated` - alterações nas settings (com diff campo a campo)

**Profile (ProfileController):**
- `perfil.updated` - dados pessoais
- `perfil.password_changed` - troca de senha (sem logar a senha!)
- `perfil.avatar_removed`

## Entregável
- Logado, fazer várias ações: login, alterar perfil, criar usuário, mudar permissão, alterar aparência
- Acessar `/auditoria` (admin tem acesso)
- Ver lista cronológica de tudo
- Filtrar por módulo "auth" → ver só logins
- Clicar num log de "users.updated" → ver diff antes/depois
- Tentar acessar `/auditoria` com usuário sem permissão → 403

## Fora do escopo
- Auditoria de leituras (GET)
- Job de limpeza/retenção
- Exportação para CSV/Excel
- Gráficos/dashboards de auditoria
- Alertas em tempo real
