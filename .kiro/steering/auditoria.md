---
inclusion: always
---

# Auditoria - Regra do Projeto

Toda funcionalidade que altera dados do sistema OU executa ações sensíveis DEVE registrar log de auditoria.

## Estrutura

Todos os logs ficam em uma única tabela `audit_logs`:

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | bigint | PK |
| user_id | bigint nullable | Quem executou (null se for sistema/anônimo) |
| user_name | string nullable | Snapshot do nome do usuário no momento (não some se o user for excluído) |
| event | string | Identificador da ação (ex: `users.created`, `auth.login.success`) |
| module | string | Módulo (ex: `usuarios`, `aparencia`, `auth`) |
| description | string nullable | Descrição amigável (ex: "Usuário João criado") |
| auditable_type | string nullable | Classe do model afetado (ex: `App\Models\User`) |
| auditable_id | bigint nullable | ID do registro afetado |
| old_values | json nullable | Dados anteriores (mudanças apenas) |
| new_values | json nullable | Dados novos (mudanças apenas) |
| metadata | json nullable | Dados extras contextuais |
| ip_address | string nullable | IP de origem |
| user_agent | string nullable | User agent do navegador |
| created_at | timestamp | Quando aconteceu |

## Como registrar

### Forma 1: Helper estático (recomendado para eventos pontuais)
```php
use App\Services\AuditLogger;

AuditLogger::log(
    event: 'users.created',
    module: 'usuarios',
    description: "Usuário {$user->name} criado",
    auditable: $user,
    newValues: $user->toArray(),
);
```

### Forma 2: Trait `Auditable` (recomendado para CRUD em models)
```php
use App\Traits\Auditable;

class User extends Authenticatable {
    use Auditable;

    protected array $auditableEvents = ['created', 'updated', 'deleted'];
    protected array $auditableExcept = ['password', 'remember_token']; // não logar
}
```
A trait faz o registro automático dos eventos `created`, `updated`, `deleted` com `old_values` e `new_values`.

## Eventos obrigatórios

Toda spec/funcionalidade futura deve registrar pelo menos:

### Auth
- `auth.login.success` - login feito
- `auth.login.failed` - tentativa de login com senha errada
- `auth.logout` - logout
- `auth.password.reset_requested` - solicitou link
- `auth.password.reset_completed` - redefiniu senha

### CRUD em models de negócio
- `<modulo>.<entidade>.created`
- `<modulo>.<entidade>.updated` (com diff em old/new values)
- `<modulo>.<entidade>.deleted`

### Ações sensíveis
- Mudança de status (ativar/inativar/aprovar/reprovar)
- Mudança de permissões
- Alteração de configurações sensíveis (settings, integrações)
- Acesso negado a recurso restrito (opcional, útil para detectar tentativas)

## Convenções de nomenclatura

- `event`: snake_case com módulo + entidade + ação. Ex: `usuarios.users.created`, `auth.login.success`
- `module`: nome do módulo (mesmo do menu/rotas). Ex: `usuarios`, `aparencia`, `auth`, `financeiro.contas_pagar`
- `description`: frase em português, curta, com nome do alvo. Ex: "Aprovou pagamento PG-1234 (R$ 5.000,00)"

## Visualização e acesso

- Permissão: `auditoria.visualizar`
- Tela: `/auditoria` com DataTable paginada e filtros (período, módulo, evento, usuário, busca)
- Detalhes: clicar abre Dialog mostrando old_values vs new_values em diff visual
- Apenas leitura. Logs NUNCA podem ser editados ou excluídos pela aplicação.

## Não registrar

- Páginas/leituras (GET) por padrão. Pode registrar acessos sensíveis pontuais via `AuditLogger::log` se necessário.
- Senhas, tokens, segredos. Sempre filtrar via `auditableExcept`.
- Auto-edits silenciosos do framework (timestamps, etc).

## Performance

- A escrita pode ser síncrona por enquanto. Quando passar de ~milhares por dia, mover para queue (Laravel Reverb/queue worker).
- Indexes em `user_id`, `event`, `module`, `auditable_type+auditable_id`, `created_at`.
- Reter por 1 ano (job de limpeza opcional no futuro).
