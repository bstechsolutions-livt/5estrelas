# Spec 02 - White-label: Configuração visual dinâmica

## Objetivo
Permitir que o nome da empresa, cores, logo, favicon e background da tela de login sejam configurados via tela administrativa, sem rebuild. Tudo aplicado em runtime via CSS variables e shared props do Inertia.

## Requisitos

### R1: Tabela e modelo `settings`
- Migration criando tabela `settings` (chave/valor para flexibilidade futura)
- Model `Setting` com helper `Setting::get('chave', 'default')` e `Setting::set('chave', 'valor')`
- Cache simples (lembrar de invalidar ao salvar)
- Seeder com valores padrão:
  - `app_name` = "5 Estrelas"
  - `primary_color` = "#3b82f6"
  - `secondary_color` = "#1e1e2d"
  - `logo_path` = null
  - `favicon_path` = null
  - `login_bg_path` = null

### R2: Shared props no Inertia
- Middleware `HandleInertiaRequests` deve compartilhar todas as settings em `theme`
- Disponível em todas as páginas via `usePage().props.theme`

### R3: Composable `useTheme()` no frontend
- Injeta CSS variables no `:root` baseado nas settings recebidas
- Aplica favicon dinâmico no `<head>`
- Aplica nome da empresa no `<title>` e onde aparecer no layout

### R4: Aplicar nas telas existentes
- Sidebar e Login passam a usar as cores/nome/logo das settings
- Logo (se houver) substitui o quadradinho "5E"
- Background da tela de login (se houver) substitui o gradient atual

### R5: Tela admin de aparência
- Rota `/settings/aparencia` (autenticada)
- Item de menu "Configurações" agora leva para essa tela
- Formulário com:
  - Input de texto para nome da empresa
  - PrimeVue ColorPicker para cor primária e secundária
  - PrimeVue FileUpload para logo, favicon e bg de login
  - Botão "Salvar" — após salvar, aplica imediatamente
- Preview dos uploads antes de salvar
- Mensagem de sucesso (Toast)

### R6: Upload de arquivos
- Storage local (`storage/app/public/branding/`)
- Link simbólico do storage (`php artisan storage:link`)
- Validação: imagens (jpg, png, webp, svg, ico para favicon) e tamanho máximo 2MB

## Entregável
- Logado, ir em "Configurações" no menu
- Mudar nome para "Cliente Teste", cor primária para vermelho
- Salvar
- Ver imediatamente o nome mudando no logo, sidebar, título da aba
- Cor primária aplicada nos botões e ativos do menu
- Subir um logo PNG → ver substituindo o "5E"
- Fazer logout → ver o background customizado na tela de login (se subido)

## Fora do escopo
- Multi-tenant (continua single)
- Tema escuro/claro (apenas dark sidebar como já está)
- Tipografia customizável
- Permissões granulares (próxima spec)
