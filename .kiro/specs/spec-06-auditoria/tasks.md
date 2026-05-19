# Spec 06 - Tasks

- [x] 1. Migration `audit_logs` com índices
- [x] 2. Model `AuditLog` (read-only, casts JSON, relacionamento user)
- [x] 3. Service `App\Services\AuditLogger` com método estático `log()`
- [x] 4. Trait `App\Traits\Auditable` para Models
- [x] 5. Adicionar permissão `auditoria.visualizar` no PermissionsSeeder
- [x] 6. Controller `AuditLogController` com `index` e filtros
- [x] 7. Rota `/auditoria` protegida por permissão
- [x] 8. `Pages/Audit/Index.vue` com DataTable + filtros + Dialog de detalhes
- [x] 9. Item de menu "Auditoria" condicional
- [x] 10. Instrumentar LoginController (success/failed/logout)
- [x] 11. Instrumentar PasswordResetLinkController + NewPasswordController
- [x] 12. Instrumentar SettingsController (aparencia.updated com diff)
- [x] 13. Instrumentar UserController (toggle_active) + trait Auditable no User model
- [x] 14. Instrumentar UserPermissionController (permissions_updated)
- [x] 15. Instrumentar ProfileController (updated/password_changed/avatar_removed)
- [x] 16. Validar local: fazer várias ações, ver na tela de auditoria
- [x] 17. Título da aba e favicon agora seguem as settings do sistema

## Status: ✅ Concluída
