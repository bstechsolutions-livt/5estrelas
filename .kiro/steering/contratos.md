---
inclusion: manual
---

# Módulo Gestão de Contratos (portado da intranet Biglar)

Origem: `Hub/clientes/biglar/sistemas/ct-intranet` (Laravel 11 + Inertia + Vue 3 + PrimeVue 4, banco Oracle).
Estratégia: **lift-and-shift + adaptar encanamento + testar rota por rota** (não reescrever).

## Como as telas funcionam
- As telas usam **axios** batendo em rotas de API que devolvem JSON (`{ sucesso, dados }`), com estado em arquivos `.js` (ex: `gestao-contratos.js`). NÃO usam props do Inertia.
- Caminhos hardcoded nas telas: páginas em `/pagina/gestao-contratos/...`, API em `/v2/gestao-contratos/...`. As rotas no 5E batem exatamente nesses caminhos pra não editar as telas.

## Adaptações feitas (shims)
- `resources/js/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue` + `.js`: adaptadores que embrulham o `AppLayout` do 5E. `layoutJs.setPaginaNova` é no-op.
- `resources/js/utils/globalFunctions.js`: helpers usados pelas telas (`swalConfirm`, `swalInput`, `formatarCnpj`, `formatarCpf`, `swalErro`, `toastError/Warning/Success`, `getDevice`).
- `resources/js/composables/useTour.js`: stub no-op (tour/driver.js não trazido).
- `resources/js/ComponentsV2/BsFile.vue`: componente de arquivo (usado no form de alvarás).
- `app/Models/Filial.php`: mapeia a entidade "Filial" da Biglar para a tabela `branches` (accessors `codigo`/`razaosocial`/`fantasia`/`cgc`). `contratos.filial_id` = `branches.id`.
- `app/Services/UtilService.php`: `exportToExcel` gera **CSV** (sem dep nova). Trocar por xlsx (maatwebsite/excel) numa spec dedicada se precisar.
- Controller: `session('auth')->matricula` → `auth()->id()`; SQL Oracle (`TO_NUMBER`/`TO_CHAR`) → padrão PostgreSQL.

## Libs adicionadas
- Front: `sweetalert2`, `vue-toastification` (registrada no `app.js`).
- Back: nenhuma nova (export em CSV).

## Tabelas (PostgreSQL) — migration `2026_06_05_120000_create_gestao_contratos_tables`
`bs_gestao_contratos`, `bs_gestao_contratos_reajustes`, `bs_gestao_contratos_anexos`, `bs_gestao_alvaras`, `bs_gestao_tipos_alvara`, `bs_gestao_tipos_indice`.
(A tabela base era legado Oracle — reconstruída a partir do model.)

## Permissões
- `contratos.visualizar`, `contratos.gerenciar` (PermissionsSeeder). Menu = grupo "Contratos" (Painel, Locação, Serviços, Alvarás).

## Status (o que está PRONTO e testado)
- ✅ Dashboard, lista de contratos (locação/serviço), detalhe, criar/editar/excluir, reajustes, anexos, alvarás (via `GestaoContratosController`).
- Seeders: `GestaoContratosTiposSeeder` (índices/tipos) + `GestaoContratosDemoSeeder` (22 contratos, 8 alvarás).

## PENDENTE (próximas fatias)
- Controllers ainda NÃO portados: `GestaoContratosMedicaoController`, `GestaoContratosRenovacaoController`, `GestaoContratosDashboardController`, `GestaoEquipamentosController`. As telas existem (renovação, medições, equipamentos, dashboard-recorrentes, entrada-nota, financeiro, relatórios) mas as APIs delas dão 404 até serem portadas.
- **Acoplado a Compras + Oracle** (não funciona sem o módulo de Compras): contratos recorrentes, entrada-nota, financeiro, parte das medições.
- Ctrl+K: adicionar contratos no `SearchController` (regra do projeto).
- Mobile dedicado: as telas são responsivas (rodam dentro do AppLayout), mas não têm versão `.mobile.vue` própria ainda.
- Cores: telas usam roxo/`dark:` hardcoded (Tailwind). Ajustar pra white-label depois.
