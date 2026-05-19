# Spec 07 - Tasks

## Backend
- [x] 1. Migrations: posts, post_likes, post_comments, user_shortcuts
- [x] 2. Models: Post (com Auditable), PostComment (com Auditable), UserShortcut
- [x] 3. Permissão `noticias.gerenciar` no PermissionsSeeder
- [x] 4. `MenuCatalog` em `app/Support/`
- [x] 5. `DashboardController` refatorado: passa highlights, news, shortcuts e menuOptions
- [x] 6. `PostController` (admin CRUD)
- [x] 7. `PostInteractionController` (like/comments/feed paginado)
- [x] 8. `UserShortcutController` (update sync)
- [x] 9. Rotas

## Frontend
- [x] 10. `Components/Dashboard/HighlightStories.vue` — stories estilo Instagram com progress bar, painel lateral de comentários, like, navegação
- [x] 11. `Components/Dashboard/ShortcutsCard.vue` (com modal de configurar)
- [x] 12. `Components/Dashboard/NewsFeed.vue` — infinite scroll via AJAX
- [x] 13. `Components/Dashboard/NewsCard.vue` — like + comentários (mostra só último, "ver todos")
- [x] 14. `Pages/Dashboard.vue` — feed com efeito de centralizar suavemente ao rolar
- [x] 15. `Pages/Posts/Index.vue` (admin com tabs e DataTable)
- [x] 16. `Pages/Posts/Form.vue` (create/edit com upload responsivo ao tipo)
- [x] 17. Item "Notícias" no menu lateral (condicional `noticias.gerenciar`)
- [x] 18. DemoSeeder com 20 usuários, 14 destaques, 13 notícias, 140+ likes, 96+ comentários

## Validação
- [x] 19. Validar local: criar destaque, ver no dashboard, abrir modal, criar notícia, like, comentar, configurar atalhos

## Status: ✅ Concluída
