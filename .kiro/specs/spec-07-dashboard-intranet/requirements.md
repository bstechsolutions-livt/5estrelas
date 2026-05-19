# Spec 07 - Dashboard Intranet (atalhos + destaques + notícias)

## Objetivo
Transformar o dashboard em uma "intranet" estilo EasyIntra/feed corporativo: saudação, atalhos configuráveis pelo usuário, destaques (stories tipo Instagram), feed de notícias (posts com like e comentário) e tela admin pra gerenciar.

## Visão geral do layout

```
┌────────────────────────────────────────────────────────────────┐
│  Olá, Bruno 👋                                                  │
│  Bem-vindo ao painel                                           │
├────────────────────────────────────────────────────────────────┤
│  DESTAQUES (stories)                                           │
│  ⭕  ⭕  ⭕  ⭕  ⭕  →                                            │
├──────────────────────────────────┬─────────────────────────────┤
│  ATALHOS RÁPIDOS         [⚙]    │   FEED DE NOTÍCIAS          │
│  ┌────┐ ┌────┐ ┌────┐ ┌────┐    │   ┌──────────────────────┐  │
│  │ 📅 │ │ 📊 │ │ ⚙  │ │ +  │    │   │  [Imagem]            │  │
│  │Tit │ │Tit │ │Tit │ │Add │    │   │  Título              │  │
│  └────┘ └────┘ └────┘ └────┘    │   │  Conteúdo...         │  │
│                                  │   │  ❤ 12  💬 3          │  │
│                                  │   └──────────────────────┘  │
│                                  │   ┌──────────────────────┐  │
│                                  │   │ ...                  │  │
└──────────────────────────────────┴─────────────────────────────┘
```

## Requisitos

### R1: Tabela `posts` (destaques + notícias)
- type: enum('highlight', 'news')
- title, content (texto longo)
- image_path (obrigatória pra destaques, opcional pra notícias)
- published_at (data inicial de exibição)
- expires_at (data de validade, nullable — se null, não expira)
- is_active (boolean)
- created_by (user_id)

### R2: Tabela `post_likes`
- post_id, user_id (PK composta)
- created_at

### R3: Tabela `post_comments`
- id, post_id, user_id, content, created_at, updated_at

### R4: Tabela `user_shortcuts`
- id, user_id, menu_key (string ex: `usuarios`, `aparencia`), position (int para ordenação)
- timestamps

### R5: Permissão `noticias.gerenciar`
- Adicionar à seeder
- Quem tem essa permissão pode criar/editar/excluir destaques e notícias

### R6: Dashboard (`/dashboard`) - Novo layout

**6.1 Cabeçalho:** "Olá, [primeiro nome] 👋" + subtitulo (mantém)

**6.2 Destaques (stories):**
- Linha horizontal de círculos com a imagem do destaque (60x60 ou 70x70px, com borda colorida da cor primária)
- Título embaixo (max 2 linhas, truncado)
- Scroll horizontal se passar da largura
- Clicar em um destaque abre dialog/modal "estilo story": imagem grande, título e conteúdo
- Apenas destaques ativos (is_active=true) e dentro do período (published_at <= now <= expires_at)

**6.3 Atalhos:**
- Card com header "ACESSO RÁPIDO" + ícone de engrenagem (abre modal de configuração)
- Grade de cards (4 colunas no desktop, 2 mobile) com ícone + nome
- Cards baseados nos `user_shortcuts` do usuário
- Card vazio "+" para adicionar (abre o mesmo modal)
- Modal de configuração: lista todos os menus/submenus disponíveis (que o usuário tem permissão), com checkbox; salvar
- Por enquanto, fonte = mesmo array do menu lateral (Dashboard, Usuários, Aparência, Auditoria), filtrado pelas permissões. Suporta submenus quando existirem.

**6.4 Feed de notícias:**
- Coluna direita com cards de notícias ordenados por data desc
- Cada card:
  - Imagem (se houver) — aspect ratio 4:5 ou similar
  - Título (font-bold)
  - Conteúdo (limitado a 4-5 linhas, "ver mais" expande)
  - Footer: ❤ contador de likes (clicável) | 💬 contador de comentários (clicável abre seção)
  - Data publicação no canto
- Clicar em comentários expande lista de comentários abaixo + textarea pra novo
- Apenas notícias ativas e dentro do período

### R7: Tela admin de gerenciamento (`/noticias`)

**7.1 Lista:** DataTable com tabs ou filtro por tipo (Destaques | Notícias)
- Colunas: Imagem (thumb), Título, Tipo (badge), Status (Ativo/Expirado/Inativo), Likes, Comentários, Ações

**7.2 Form de criar/editar:**
- Toggle entre "Destaque" e "Notícia"
- Título
- Conteúdo (textarea/rich text simples — por enquanto textarea)
- Upload imagem (com placeholder mostrando proporção e resolução recomendada conforme tipo):
  - Destaque: 1:1 (quadrado), 512x512px ou 1080x1080px, máx 5MB
  - Notícia: 4:5 (vertical), 1080x1350px, máx 10MB (ou opcional)
- Período de exibição: published_at + expires_at (DatePicker)
- Toggle ativo
- Salvar

### R8: Interações no feed
- Like: clicar no coração marca/desmarca (toggle)
- Comentários: lista visível ao clicar, textarea pra postar, próprio usuário pode excluir o próprio comentário

### R9: Auditoria
Conforme regra do steering, registrar:
- `noticias.posts.created` (via trait Auditable)
- `noticias.posts.updated`
- `noticias.posts.deleted`
- `noticias.post.liked` (manualmente, com auditable post)
- `noticias.post.unliked`
- `noticias.comment.created` (via trait Auditable)
- `noticias.comment.deleted`

### R10: Item de menu
- "Notícias" no menu lateral, condicional `noticias.gerenciar`

## Entregável
- Logado, ver dashboard novo: saudação + stories vazios + atalhos vazios + feed vazio
- Ir em "Notícias" (admin)
- Criar destaque com imagem, título, conteúdo
- Criar notícia com imagem, título, conteúdo
- Voltar pro dashboard, ver story (círculo) e card de notícia
- Clicar no story → abre modal com a imagem grande
- Clicar no coração da notícia → like marcado, contador subiu
- Comentar na notícia → comentário aparece
- Configurar atalhos: clicar engrenagem, marcar "Aparência" e "Usuários", salvar → atalhos aparecem no dashboard
- Tudo registrado em auditoria

## Fora do escopo
- Comentários aninhados (replies)
- Notificação real-time (Reverb) quando alguém comenta — futura
- Editor rich-text com imagens inline (por enquanto textarea simples)
- Compartilhar / repostar
- Destaques agrupados por categoria
