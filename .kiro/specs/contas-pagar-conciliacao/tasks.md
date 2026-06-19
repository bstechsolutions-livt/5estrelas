# Implementation Plan: Conciliação Bancária

## Overview

Ordem incremental: migration → model → controller → testes backend → frontend → seeder → Dusk → suíte inteira. Cada bloco termina com algo testável. Regra inegociável: **nada é "concluído" sem teste verde dos dois lados** (`testes.md`).

## Tasks

- [x] 1. Fundação de dados (migration + ajustes no model)
  - [x] 1.1 Criar migration `add_conciliation_fields_to_payables`
    - Colunas em `payables`: `conciliated_at` (date, nullable), `conciliated_by` (foreignId nullable → `users.id`, `nullOnDelete`), `conciliation_notes` (text, nullable), `divergence_reason` (text, nullable).
    - _Requirements: 2.4, 3.3, 6.4_
  - [x] 1.2 Ajustar o model `Payable`
    - Adicionar `conciliated_at`, `conciliated_by`, `conciliation_notes`, `divergence_reason` ao `$fillable` e ao `WORKFLOW_FIELDS`.
    - Adicionar novos status em `STATUS_LABELS`: `'conciliado' => 'Conciliado'`, `'divergente' => 'Divergente'`.
    - Adicionar novos status em `STATUS_COLORS`: `'conciliado' => 'success'`, `'divergente' => 'danger'`.
    - Cast `conciliated_at => date`.
    - Relação `conciliator(): BelongsTo` (`conciliated_by`).
    - _Requirements: 6.1, 6.2, 6.3, 6.4_

- [x] 2. Backend — métodos de conciliação e divergência
  - [x] 2.1 Implementar `PayableController@conciliate`
    - Injetar `PayableAlcadaService`; `abort(403)` se `!isAssigned(user, 'conciliador')` (nem `*` fura — R1.2).
    - `validate`: `notes` `nullable|string|max:1000`.
    - `DB::transaction` + `lockForUpdate` + recheck `status==='pago'`; senão `back('error')`.
    - Update `status=conciliado, conciliated_at=today, conciliated_by=user, conciliation_notes=notes`.
    - `PayableComment` `type='conciliation'`.
    - `AuditLogger::log('contas_pagar.conciliado', old/new)`.
    - _Requirements: 1.1, 1.2, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 4.1, 4.2, 4.3, 5.1, 5.3, 5.5_
  - [x] 2.2 Implementar `PayableController@diverge`
    - Mesmo padrão: `abort(403)` se não conciliador; `validate`: `reason` `required|string|min:10|max:1000`.
    - `DB::transaction` + `lockForUpdate` + recheck `status==='pago'`.
    - Update `status=divergente, conciliated_at=today, conciliated_by=user, divergence_reason=reason`.
    - `PayableComment` `type='divergence'`.
    - `AuditLogger::log('contas_pagar.divergente', old/new)`.
    - _Requirements: 1.1, 1.2, 3.1, 3.2, 3.3, 3.5, 4.1, 4.2, 4.3, 5.2, 5.4, 5.5_
  - [x] 2.3 Ajustar `PayableController@show`
    - Eager-load `conciliator:id,name`.
    - Props: `canConciliate` = `isAssigned(user, 'conciliador') && status === 'pago'`; `conciliadorConfigured` = `hasRole('conciliador')`.
    - _Requirements: 1.3, 7.1_
  - [x] 2.4 Registrar rotas
    - `POST /{id}/conciliar` → `conciliate`, name `payables.conciliate`, `whereNumber('id')`.
    - `POST /{id}/divergencia` → `diverge`, name `payables.diverge`, `whereNumber('id')`.
    - Dentro do grupo existente `financeiro/contas-pagar`.
    - _Requirements: 2.1, 3.1_

- [x] 3. Feature tests da conciliação e rodar
  - Criar `tests/Feature/PayableConciliacaoTest.php` (`RefreshDatabase`, setup como `PayablePagamentoTest`):
    - Conciliador concilia `pago` → `assertDatabaseHas` status `conciliado`, `conciliated_by`, comment `type=conciliation`, audit `contas_pagar.conciliado`.
    - Conciliador com observação → `conciliation_notes` persistida.
    - Conciliador sem observação → funciona.
    - **Não-conciliador com `*` → 403** (caso-chave de segregação).
    - Conciliador **inativo** → 403.
    - Status ≠ `pago` (ex.: `pendente`, `aprovado`, `conciliado`) → recusa, status preservado.
    - Idempotência: conciliar 2x → 2ª recusada, 1 só efeito.
    - Divergência: conciliador registra → status `divergente`, `divergence_reason`, comment `type=divergence`, audit `contas_pagar.divergente`.
    - Divergência sem motivo → **422** (`assertJsonValidationErrors(['reason'])`).
    - Divergência motivo < 10 chars → **422**.
    - Divergência motivo > 1000 chars → **422**.
    - Sem conciliador configurado → 403 para todos.
    - Filtro status index: `?status=conciliado` e `?status=divergente` retornam títulos corretos.
  - Rodar `php artisan test --filter=PayableConciliacaoTest` (tem que passar).
  - _Requirements: 1.1, 1.2, 1.4, 2.1-2.6, 3.1-3.5, 4.1-4.3, 5.1-5.5, 6.3_

- [x] 4. Checkpoint — Backend verde
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Frontend — conciliação em `Pages/Payables/Show.vue`
  - `<Toast />` já existe (Spec 1 adicionou).
  - Bloco "Conciliação" na sidebar de ações visível quando `canConciliate`: botão "Conciliar" (`dusk="open-conciliation"`).
  - **Desktop**: PrimeVue `Dialog` com:
    - Dois botões de opção: "Conciliar" (`dusk="action-conciliate"`) e "Registrar divergência" (`dusk="action-diverge"`).
    - Ao escolher "Conciliar": `Textarea` observação opcional (`dusk="conciliation-notes"`) + botão Confirmar (`dusk="confirm-conciliation"`).
    - Ao escolher "Registrar divergência": `Textarea` motivo obrigatório (`dusk="divergence-reason"`) + botão Confirmar severity danger (`dusk="confirm-divergence"`).
  - **Mobile** (`isMobile`): `BottomSheet.vue` (`dusk="conciliation-sheet"`) com mesmo conteúdo, campos full-width, confirmar fixo no rodapé.
  - Submit conciliação: `useForm({ notes }).post('/financeiro/contas-pagar/${id}/conciliar', { preserveScroll, onSuccess })`.
  - Submit divergência: `useForm({ reason }).post('/financeiro/contas-pagar/${id}/divergencia', { preserveScroll, onSuccess })`.
  - `onSuccess`: fecha dialog/sheet + toast; Inertia recarrega `payable`, Tag atualiza sem reload manual.
  - Bloco **read-only "Conciliação"** quando `status==='conciliado'`: data, conciliador, observação (se houver). Ícone check, cor success.
  - Bloco **read-only "Divergência"** quando `status==='divergente'`: data, responsável, motivo. Borda/cor danger.
  - Hint: se `status==='pago' && !conciliadorConfigured`, exibir aviso "Alçada de conciliação não configurada".
  - _Requirements: 1.3, 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 8.1, 8.2, 8.3_

- [x] 6. DemoSeeder (massa local)
  - Estender `PayableSeeder` (ou `PayableAlcadaSeeder`):
    - Associar `bruno@bstechsolutions.com` ao papel `conciliador` na Alcada_CP (`PayableRole`).
    - Garantir ≥3 títulos `pago` (para conciliar).
    - Garantir ≥3 títulos `conciliado` (com `conciliated_at`, `conciliated_by`, `conciliation_notes` preenchidos).
    - Garantir ≥1 título `divergente` (com `divergence_reason` preenchido).
  - Rodar `php artisan migrate:fresh --seed` e conferir as telas preenchidas.
  - _Requirements: 9.1, 9.2, 9.3_

- [x] 7. Dusk (browser real) e rodar
  - [x] 7.1 `tests/Browser/PayableConciliacaoTest.php`
    - Login `bruno@bstechsolutions.com`; setup: cria `Payable` `pago` + associa bruno como `conciliador`.
    - **Desktop — Conciliar**: detalhe mostra `@open-conciliation`; abre Dialog; clica `@action-conciliate`; campo notes aparece; `@confirm-conciliation` → toast + Tag "CONCILIADO" (uppercase) + bloco read-only. `assertDatabaseHas('payables', ['status'=>'conciliado'])`.
    - **Desktop — Divergência**: abre Dialog; clica `@action-diverge`; campo reason aparece; preenche motivo ≥10 chars; `@confirm-divergence` → toast + Tag "DIVERGENTE" + bloco read-only (vermelho).
    - **Mobile** (`resize(375,800)`): form abre como bottom sheet (`@conciliation-sheet`); conclui conciliação.
    - **Título não-pago**: não mostra o botão `@open-conciliation`.
    - **Read-only conciliado**: título `conciliado` mostra bloco com data + conciliador.
    - **Read-only divergente**: título `divergente` mostra bloco com motivo em vermelho.
  - Subir `php artisan serve --port=8778` (env `.env.dusk.local`) e rodar `php artisan dusk --filter=PayableConciliacaoTest`.
  - _Requirements: 1.3, 7.1-7.8, 8.1-8.3_

- [x] 8. Suíte inteira verde
  - Rodar `php artisan test` (toda a suíte) e `php artisan dusk` (todos), corrigir qualquer regressão. Reportar o resultado (X passed). Deploy só com verde.
  - _Requirements: todos_

## Notes

- Cada task referencia os requirements específicos que cobre.
- O padrão segue exatamente o da Spec 1 (migration → model → controller → feature test → frontend → seeder → Dusk → suíte).
- Checkpoints garantem validação incremental.
- As rotas novas vivem no mesmo grupo já existente de `financeiro/contas-pagar`.
- Não é necessário criar nova permissão — a ação é governada pela alçada.
