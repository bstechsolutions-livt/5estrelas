# Implementation Plan

Ordem incremental: dados → service → backend da alçada → testes backend da alçada → backend do pagamento → testes backend do pagamento → frontend → seeder → Dusk → suíte inteira. Cada bloco termina com algo testável. Regra inegociável: **nada é "concluído" sem teste verde dos dois lados** (`testes.md`).

- [x] 1. Fundação de dados (migrations + models)
  - [x] 1.1 Criar migration `add_payment_fields_to_payables`
    - Colunas em `payables`: `paid_at` (date, nullable), `payment_method` (string, nullable), `paid_by` (foreignId nullable → `users.id`, `nullOnDelete`).
    - O enum de `status` já tem `pago`; não mexer nele.
    - _Requirements: 5.1, 5.3, 5.6_
  - [x] 1.2 Criar migration `create_payable_roles_table`
    - Colunas: `id`, `role` (string, index), `user_id` (foreignId → `users.id`, `cascadeOnDelete`), timestamps.
    - Unique composto (`role`, `user_id`) para impedir duplicata.
    - _Requirements: 2.4_
  - [x] 1.3 Ajustar o model `Payable`
    - Adicionar `paid_at`, `payment_method`, `paid_by` ao `$fillable` e ao `WORKFLOW_FIELDS` (sync Senior não sobrescreve).
    - Cast `paid_at => date`. Relação `paidBy(): BelongsTo` (`paid_by`).
    - Constante `PAYMENT_METHODS = ['PIX'=>'PIX','TED'=>'TED','Boleto'=>'Boleto','Dinheiro'=>'Dinheiro','Outro'=>'Outro']`.
    - _Requirements: 5.3, 5.6_
  - [x] 1.4 Criar model `App\Models\PayableRole`
    - `$fillable = ['role','user_id']`; const `ROLES` e `ROLE_DESCRIPTIONS` (pagador/conciliador/assinante); relação `user()`.
    - _Requirements: 1.3, 2.1_

- [x] 2. Permissão de gestão da alçada
  - Adicionar `['key' => 'financeiro.contas_pagar.alcada_gerenciar', 'label' => 'Gerenciar alçada do contas a pagar', 'module' => 'financeiro']` ao `PermissionsSeeder` e rodar o seeder de permissões.
  - _Requirements: 3.1_

- [x] 3. Service `PayableAlcadaService`
  - `eligibleUsers(role)` e `isAssigned(user, role)` filtrando `users.is_active = true` (ignora inativo — R2.5).
  - `hasRole(role)` (há pagador configurado? — R4.4).
  - `map()` para a tela: cada papel com label, descrição e usuários associados (inclui inativos com flag `is_active` para o admin remover — R1.1/R1.2).
  - `assign(role, userId, actor)` idempotente (`firstOrCreate`) + auditoria `contas_pagar.alcada_atribuido`.
  - `unassign(role, userId, actor)` + auditoria `contas_pagar.alcada_removido`.
  - _Requirements: 1.1, 1.2, 2.3, 2.4, 2.5, 3.3, 3.4, 4.1, 4.4_

- [x] 4. Backend da gestão de alçada (controller + rotas + menu)
  - [x] 4.1 Criar `PayableAlcadaController` (`index`, `store`, `destroy`)
    - `index`: `Inertia::render('Payables/Alcada', ['roles' => $svc->map(), 'availableUsers' => <ativos id,name,email>])`.
    - `store`: valida `role` ∈ chaves de `PayableRole::ROLES` e `user_id` `exists:users,id`; `$svc->assign()`; `back()->with('success')`.
    - `destroy(role, userId)`: `$svc->unassign()`; `back()->with('success')`.
    - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 3.3, 3.4_
  - [x] 4.2 Registrar rotas `payables.alcada.*`
    - Grupo `financeiro/contas-pagar/alcada` sob `middleware('permission:financeiro.contas_pagar.alcada_gerenciar')`; `destroy` com `whereNumber('userId')`.
    - Registrar antes do grupo `financeiro/contas-pagar` e/ou garantir `whereNumber('id')` no `payables.show` (ver task 6.3) para não colidir.
    - _Requirements: 1.4, 3.1, 3.2_
  - [x] 4.3 Adicionar item de menu em `MenuCatalog::all()` (grupo Financeiro)
    - `contas_pagar_alcada` → `/financeiro/contas-pagar/alcada`, permissão `financeiro.contas_pagar.alcada_gerenciar` (aparece no Ctrl+K via `availableTo`).
    - _Requirements: 8.1_

- [x] 5. Feature tests da alçada e rodar
  - Criar `tests/Feature/PayableAlcadaTest.php` (`RefreshDatabase`, setup como `ComercialClienteTest`):
    - `index` 200 com a permissão; **403** sem; 200 com `*`.
    - `store` associa → `assertDatabaseHas('payable_roles', ...)` + `assertDatabaseHas('audit_logs', ['event'=>'contas_pagar.alcada_atribuido'])`.
    - `store` com `role`/`user_id` inválidos → **422** (`assertJsonValidationErrors`).
    - `store` duplicado → continua 1 linha (R2.4).
    - `destroy` → `assertDatabaseMissing` + audit `contas_pagar.alcada_removido`.
    - `store`/`destroy` **403** sem permissão.
  - Rodar `php artisan test --filter=PayableAlcadaTest` (tem que passar).
  - _Requirements: 1.4, 2.1, 2.2, 2.4, 3.1, 3.2, 3.3, 3.4_

- [x] 6. Backend de registrar pagamento (controller + rota)
  - [x] 6.1 Implementar `PayableController@pay`
    - Injetar `PayableAlcadaService`; `abort(403)` se `!isAssigned(user,'pagador')` (nem `*` fura — R4.2).
    - `validate`: `paid_at` `required|date|before_or_equal:today`; `payment_method` `nullable|Rule::in(PAYMENT_METHODS)`; `file` `nullable|file|max:10240`.
    - `DB::transaction` + `lockForUpdate` + recheck `status==='aprovado'` (idempotência R6.3); senão `back('error')`.
    - Update `status=pago, paid_at, payment_method, paid_by`; gravar comprovante via `PayableDocument`; `PayableComment` `type='payment'`; `AuditLogger::log('contas_pagar.pago', old/new)`.
    - _Requirements: 4.1, 4.2, 4.4, 5.1, 5.3, 5.4, 5.5, 5.6, 5.7, 6.1, 6.2, 6.3, 7.1, 7.2, 7.3_
  - [x] 6.2 Ajustar `PayableController@show`
    - Eager-load `paidBy:id,name`; injetar `PayableAlcadaService`.
    - Passar props `canPay` (isAssigned pagador && status `aprovado`), `paymentMethods`, `pagadorConfigured`.
    - _Requirements: 4.3, 8.2_
  - [x] 6.3 Rotas
    - Adicionar `POST /{id}/registrar-pagamento` → `pay`, name `payables.pay`, `whereNumber('id')`.
    - Adicionar `whereNumber('id')` ao `payables.show`.
    - _Requirements: 5.1_

- [x] 7. Feature tests do pagamento e rodar
  - Criar `tests/Feature/PayablePagamentoTest.php`:
    - pagador paga `aprovado` → `assertDatabaseHas('payables', ['status'=>'pago','paid_by'=>...])`; comentário `type=payment`; audit `contas_pagar.pago`.
    - **não-pagador com `*` → 403** (caso-chave de segregação).
    - pagador **inativo** → 403.
    - status ≠ `aprovado` → recusa, status preservado.
    - `paid_at` futura → **422**; `payment_method` fora da lista → **422**.
    - comprovante (`Storage::fake('public')` + `UploadedFile::fake()`) → `assertDatabaseHas('payable_documents', ...)`.
    - idempotência: pagar 2x → 2ª recusada, 1 só efeito.
    - sem pagador configurado → 403 para todos.
  - Rodar `php artisan test --filter=PayablePagamentoTest`.
  - _Requirements: 4.1, 4.2, 4.4, 5.1, 5.2, 5.5, 5.6, 5.7, 6.1, 6.2, 6.3, 7.1, 7.2, 7.3_

- [x] 8. Frontend — tela de Alçada (`Pages/Payables/Alcada.vue`)
  - Layout `<component :is="isMobile ? AppLayoutMobile : AppLayout">`; `<Toast />` + `useToast()`.
  - Card por papel (desktop e mobile = cards verticais, sem tabela): título, descrição (R1.3), lista de associados (nome + e-mail), estado vazio "Sem responsável definido" (R1.2).
  - Adicionar: `Select` filtrável de usuários ativos + botão Adicionar → `useForm({role,user_id}).post(payables.alcada.store)`.
  - Remover: botão por usuário → `router.delete(payables.alcada.destroy)`.
  - `onSuccess` → toast + lista atualiza (Inertia recarrega `roles`).
  - Atributos `dusk`: `alcada-page`, `alcada-role-{role}`, `alcada-select-{role}`, `alcada-add-{role}`, `alcada-remove-{role}-{userId}`.
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 8.1, 9.1_

- [x] 9. Frontend — registrar pagamento em `Pages/Payables/Show.vue`
  - Adicionar `<Toast />` + `useToast()`.
  - Bloco "Pagamento" na sidebar de ações visível quando `canPay`: botão "Registrar pagamento" (`dusk="open-payment"`).
  - Desktop: PrimeVue `Dialog`; Mobile (`isMobile`): `Components/Mobile/BottomSheet.vue` (campos full-width, confirmar fixo no rodapé).
  - Campos: `DatePicker` (`dusk="payment-date"`, default hoje, `:maxDate` hoje), `Select` forma (`dusk="payment-method"`, opcional), `FileUpload` comprovante (`dusk="payment-file"`, opcional). Confirmar = `dusk="confirm-payment"`.
  - Submit `useForm(...).post('/financeiro/contas-pagar/${id}/registrar-pagamento', { forceFormData:true, preserveScroll:true, onSuccess })` → fecha + toast; `Tag` vira "Pago" sem reload manual.
  - Bloco read-only de pagamento quando `status==='pago'` (`paid_at`, `payment_method`, `paidBy.name`).
  - Hint opcional "Alçada de pagamento não configurada" quando `aprovado && !pagadorConfigured`.
  - _Requirements: 4.3, 5.1, 5.2, 5.5, 5.6, 8.2, 8.3, 8.4, 9.2, 9.3, 9.4_

- [x] 10. DemoSeeder (massa local)
  - Estender `PayableSeeder` e/ou criar `PayableAlcadaSeeder` (chamado pelo `DemoSeeder` após `PayableSeeder`):
    - Associar ≥1 usuário a cada papel; garantir `bruno@bstechsolutions.com` entre os pagadores; conceder `financeiro.contas_pagar.visualizar` aos pagadores demo sem `*`.
    - Garantir ≥3 títulos `aprovado` e ≥3 `pago` (com `paid_at`/`payment_method`/`paid_by` + 1 `PayableDocument` "comprovante").
  - Rodar `php artisan migrate:fresh --seed` (ou `db:seed --class=DemoSeeder`) e conferir as telas preenchidas.
  - _Requirements: 10.1, 10.2, 10.3_

- [x] 11. Dusk (browser real) e rodar
  - [x] 11.1 `tests/Browser/PayableAlcadaTest.php`
    - Login `bruno@bstechsolutions.com`; tela renderiza (`@alcada-page`, 3 cards, descrições).
    - Adicionar usuário a um papel (select + `@alcada-add-*`) → aparece na lista + toast.
    - Remover usuário (`@alcada-remove-*`) → some + toast.
    - _Requirements: 1.1, 1.2, 2.1, 2.2_
  - [x] 11.2 `tests/Browser/PayablePagamentoTest.php`
    - Setup: cria `Payable` `aprovado` + associa bruno como `pagador`.
    - Desktop: detalhe mostra `@open-payment`; abre Dialog (data default hoje); `@confirm-payment` → toast + `Tag` "PAGO" (uppercase) + bloco pagamento; `assertDatabaseHas('payables', ['status'=>'pago'])`.
    - Mobile (`resize(375,800)`): form abre como bottom sheet, conclui pagamento.
    - Título não-`aprovado` não mostra o botão.
    - _Requirements: 4.3, 5.1, 5.2, 8.2, 8.3, 9.2, 9.3, 9.4_
  - Subir `php artisan serve --port=8778` (env `.env.dusk.local`) e rodar `php artisan dusk --filter=PayableAlcadaTest` e `--filter=PayablePagamentoTest`.

- [x] 12. Suíte inteira verde
  - Rodar `php artisan test` (toda a suíte) e `php artisan dusk` (todos), corrigir qualquer regressão. Reportar o resultado (X passed). Deploy só com verde.
  - _Requirements: todos_
