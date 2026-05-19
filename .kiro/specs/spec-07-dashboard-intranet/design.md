# Spec 07 - Design

## Migrations

### `posts`
```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('type', 20); // 'highlight' | 'news'
    $table->string('title', 200);
    $table->text('content')->nullable();
    $table->string('image_path')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['type', 'is_active', 'published_at']);
    $table->index('expires_at');
});
```

### `post_likes`
```php
Schema::create('post_likes', function (Blueprint $table) {
    $table->foreignId('post_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamp('created_at')->useCurrent();
    $table->primary(['post_id', 'user_id']);
});
```

### `post_comments`
```php
Schema::create('post_comments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->text('content');
    $table->timestamps();
    $table->index(['post_id', 'created_at']);
});
```

### `user_shortcuts`
```php
Schema::create('user_shortcuts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('menu_key', 100);
    $table->integer('position')->default(0);
    $table->timestamps();
    $table->unique(['user_id', 'menu_key']);
});
```

## Models

### `Post`
- Trait `Auditable` (module=noticias, prefix=posts)
- Casts: published_at, expires_at, is_active
- Scopes:
  - `scopeActive` - is_active=true e dentro do período
  - `scopeHighlights` - type=highlight
  - `scopeNews` - type=news
- Relations: likes(), comments(), creator()
- Accessor `image_url` (Storage::url ou null)
- Accessor `is_liked_by_current_user` (computed via withCount/condicional)

### `PostComment`
- Trait Auditable (module=noticias, prefix=comments)
- Relations: post(), user()

### `UserShortcut`
- Sem trait Auditable (preferência pessoal não é auditoria de negócio)
- Relations: user()

## Catálogo de Menus para atalhos

Por enquanto fixo no backend. Futuro: tabela `menu_items` para suportar submenus dinâmicos.

```php
// app/Support/MenuCatalog.php
class MenuCatalog {
    public static function all(): array {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'pi pi-home', 'href' => '/dashboard', 'permission' => null],
            ['key' => 'usuarios', 'label' => 'Usuários', 'icon' => 'pi pi-users', 'href' => '/usuarios', 'permission' => 'usuarios.listar'],
            ['key' => 'aparencia', 'label' => 'Aparência', 'icon' => 'pi pi-palette', 'href' => '/settings/aparencia', 'permission' => 'aparencia.editar'],
            ['key' => 'noticias', 'label' => 'Notícias', 'icon' => 'pi pi-megaphone', 'href' => '/noticias', 'permission' => 'noticias.gerenciar'],
            ['key' => 'auditoria', 'label' => 'Auditoria', 'icon' => 'pi pi-history', 'href' => '/auditoria', 'permission' => 'auditoria.visualizar'],
            ['key' => 'perfil', 'label' => 'Meu perfil', 'icon' => 'pi pi-user', 'href' => '/perfil', 'permission' => null],
        ];
    }

    public static function availableTo(User $user): array {
        return collect(self::all())->filter(fn($i) => !$i['permission'] || $user->hasPermission($i['permission']))->values()->all();
    }
}
```

O `AppLayout` passa a usar isso. Sidebar e atalhos ficam em sincronia.

## Controllers

### `DashboardController` (refatorar)
- `index()` carrega para a Vue:
  - greeting
  - highlights (Posts ativos do tipo highlight, ordenados por published_at desc, limit 12)
  - news (Posts do tipo news, paginação 5 por scroll/load more — primeira página apenas no carregamento inicial)
  - shortcuts (UserShortcut do usuário com menu resolvido a partir do MenuCatalog)
  - menuOptions (catálogo disponível para o usuário)

### `PostController` (admin /noticias)
- index() - lista paginada com filtro por tipo
- create()
- store()
- edit($id)
- update()
- destroy()
- toggleActive($id)

### `PostInteractionController`
- toggleLike($postId)
- comments($postId) - lista paginada de comentários
- storeComment($postId)
- destroyComment($postId, $commentId)

### `UserShortcutController`
- update(Request) - recebe array de menu_keys, faz sync (delete tudo e cria de novo na ordem)

## Rotas

```php
// /dashboard (já existe, refatorar)

Route::middleware('auth')->group(function () {
    // Posts admin
    Route::middleware('permission:noticias.gerenciar')->group(function () {
        Route::get('/noticias', [PostController::class, 'index'])->name('posts.index');
        Route::get('/noticias/criar', [PostController::class, 'create'])->name('posts.create');
        Route::post('/noticias', [PostController::class, 'store'])->name('posts.store');
        Route::get('/noticias/{id}/editar', [PostController::class, 'edit'])->name('posts.edit');
        Route::put('/noticias/{id}', [PostController::class, 'update'])->name('posts.update');
        Route::post('/noticias/{id}/toggle-active', [PostController::class, 'toggleActive']);
        Route::delete('/noticias/{id}', [PostController::class, 'destroy'])->name('posts.destroy');
    });

    // Interações (qualquer autenticado)
    Route::post('/posts/{id}/like', [PostInteractionController::class, 'toggleLike']);
    Route::get('/posts/{id}/comentarios', [PostInteractionController::class, 'comments']);
    Route::post('/posts/{id}/comentarios', [PostInteractionController::class, 'storeComment']);
    Route::delete('/posts/{postId}/comentarios/{commentId}', [PostInteractionController::class, 'destroyComment']);

    // Atalhos do usuário
    Route::put('/perfil/atalhos', [UserShortcutController::class, 'update']);
});
```

## Frontend

### `Pages/Dashboard.vue` (refatorar grande)
Estrutura:
- Header saudação
- Section "Destaques" → componente `HighlightStories.vue`
- Grid 2 colunas (lg): atalhos à esquerda (componente `ShortcutsCard.vue`) e feed à direita (componente `NewsFeed.vue`)

### `Components/Dashboard/HighlightStories.vue`
- Lista horizontal scrollável de círculos
- Clicar abre `Dialog` com a imagem em destaque, título e conteúdo
- Setas para navegar entre destaques no dialog

### `Components/Dashboard/ShortcutsCard.vue`
- Header com botão de engrenagem (abre dialog de configuração)
- Grid de cards (4 colunas md+, 2 mobile)
- Cada card: ícone colorido com cor primária + label
- Card "+" no fim se tiver menos que o catálogo todo
- Dialog de configuração:
  - Lista do catálogo disponível com checkboxes
  - Drag-handle para reordenar (futuro — primeira versão sem reorder, ordem do catálogo)
  - Botão Salvar

### `Components/Dashboard/NewsFeed.vue`
- Lista de `NewsCard.vue`
- Botão "Carregar mais" no final (paginação simples)

### `Components/Dashboard/NewsCard.vue`
- Imagem (rounded-t-xl)
- Título grande
- Conteúdo (truncado em 5 linhas, "ver mais")
- Footer com:
  - Botão like (coração outline / preenchido) + contador
  - Botão comentários + contador
  - Data formatada
- Seção de comentários expansível abaixo do card

### `Pages/Posts/Index.vue` (admin)
- Tabs: Destaques | Notícias
- DataTable com ações

### `Pages/Posts/Form.vue`
- Mode: create | edit
- Toggle de tipo (Destaque | Notícia)
- Campos conforme tipo (placeholder de upload muda proporção)

## Storage
- `posts/highlight_{id}_{timestamp}.{ext}` para destaques
- `posts/news_{id}_{timestamp}.{ext}` para notícias
- Ao trocar imagem, deletar a antiga

## Comportamento de likes/comentários
- Like é otimista no frontend (atualiza contador antes do retorno; reverte se erro)
- Comentário é POST normal, recarrega lista
- Comentários listados em ordem cronológica desc (mais recente primeiro), limite inicial 10, "ver mais antigos"

## Auditoria
- Trait Auditable em Post e PostComment
- AuditLogger manual em like/unlike (não dá pra usar trait pq pivot não tem evento Eloquent direto; usar attach/detach + log explícito)

## Performance
- Posts ativos: index composto em (type, is_active, published_at)
- Likes contados via withCount
- Comentários carregados sob demanda (não vêm com a lista de posts, só ao expandir)
