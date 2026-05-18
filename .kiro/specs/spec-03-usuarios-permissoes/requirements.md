# Spec 03 - Usuários e Permissões granulares

## Objetivo
Permitir gerenciar usuários e atribuir permissões diretas (sem roles), com tela admin e middleware de proteção. Permissões organizadas por módulo (ex: `usuarios.criar`, `aparencia.editar`).

## Requisitos

### R1: Tabela e modelo `permissions`
- Migration `permissions` (id, key, label, module, description, created_at, updated_at)
- Pivot `user_permission` (user_id, permission_id, primary key composta)
- Model `Permission` com relacionamento `users()`
- User `belongsToMany(Permission)` via `permissions()`
- Helper no User: `$user->can('chave.permissao')` (override do Gate padrão do Laravel)
- Cache das permissões do usuário por requisição

### R2: Seeder de permissões
- Catálogo inicial de permissões agrupadas por módulo:
  - **usuarios**: listar, criar, editar, excluir, gerenciar_permissoes
  - **aparencia**: visualizar, editar
- Admin recebe TODAS as permissões automaticamente no seeder

### R3: Tela de listagem de usuários (`/usuarios`)
- DataTable do PrimeVue com paginação server-side
- Colunas: Nome, E-mail, Status (Ativo/Inativo), Última atividade, Ações
- Botão "Novo usuário" no topo
- Filtro de busca por nome ou e-mail
- Ações por linha: Editar, Permissões, Ativar/Inativar
- Coluna `is_active` (boolean) na tabela `users`

### R4: Criar/Editar usuário (`/usuarios/criar`, `/usuarios/{id}/editar`)
- Formulário com: nome, e-mail, senha (apenas no criar/redefinir)
- Validação: e-mail único, senha mínima de 8 chars
- Toggle "Ativo"
- Toast de sucesso

### R5: Tela de permissões do usuário (`/usuarios/{id}/permissoes`)
- Lista todas as permissões agrupadas por módulo
- Checkbox em cada permissão (já marcado se o usuário tem)
- Botão "Marcar todas do módulo" / "Desmarcar todas"
- Salvar atualiza a pivot

### R6: Middleware `permission`
- `Route::middleware('permission:usuarios.criar')` protege rotas
- Retorna 403 se usuário não tiver a permissão
- Admin com permissão `*` (curinga) bypassa todas

### R7: Compartilhar permissões via Inertia
- `auth.user.permissions` como array de chaves no shared props
- Frontend usa `useAuth().can('chave.permissao')` para esconder/mostrar elementos

### R8: Atualizar menu lateral
- Itens do menu condicionais baseado em permissões do usuário
- Adicionar item "Usuários" (visível só para quem tem `usuarios.listar`)
- "Aparência" só pra quem tem `aparencia.editar`

## Entregável
- Logado como admin, ver no menu "Usuários"
- Acessar `/usuarios`, ver tabela com o admin
- Criar novo usuário com nome/email/senha
- Atribuir permissões granulares pra ele
- Logar com esse usuário (em outra aba)
- Ver que ele NÃO vê "Aparência" se não tiver a permissão
- Tentar acessar `/settings/aparencia` direto → 403

## Fora do escopo
- Recuperação de senha (próxima spec se necessário)
- Convite por e-mail
- Logs de auditoria detalhados (futura spec)
- Histórico de mudanças de permissão
