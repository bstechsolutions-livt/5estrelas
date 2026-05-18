# Spec 03 - Tasks

- [x] 1. Migration: adicionar `is_active` em `users`
- [x] 2. Migration: criar tabela `permissions`
- [x] 3. Migration: criar pivot `user_permission`
- [x] 4. Model `Permission`
- [x] 5. Atualizar Model `User` com relacionamento e helpers (`hasPermission`, etc)
- [x] 6. `PermissionsSeeder` com catálogo inicial
- [x] 7. Atualizar `DatabaseSeeder` para dar `*` ao admin
- [x] 8. Middleware `EnsurePermission` + alias `permission`
- [x] 9. `UserController` (CRUD básico + toggle)
- [x] 10. `UserPermissionController` (edit + update + proteção último admin)
- [x] 11. Rotas com middlewares de permissão
- [x] 12. Atualizar `HandleInertiaRequests` para incluir `permissions` em `auth.user`
- [x] 13. Composable `useAuth()` com `can()` / `canAny()` / `canAll()`
- [x] 14. Pages/Users/Index.vue - DataTable com paginação server-side, filtro, ações
- [x] 15. Pages/Users/Form.vue - criar/editar com toggle de status
- [x] 16. Pages/Users/Permissions.vue - matriz por módulo + destaque do curinga `*`
- [x] 17. Atualizar `AppLayout.vue` com menu condicional por permissão
- [x] 18. Validar local: criar usuário, atribuir permissões, testar bloqueio 403

## Status: ✅ Concluída
