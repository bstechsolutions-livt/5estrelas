# Implementation Plan: Importação de Extrato OFX + Conciliação Assistida

## Overview

Implementação incremental: primeiro o parser OFX (com testes de propriedade), depois o matching, depois o controller/rotas, frontend e por fim batch conciliation. Cada passo é testável isoladamente.

## Tasks

- [x] 1. Migrations e Models
  - [x] 1.1 Criar migration `create_bank_statement_imports_table` com todos os campos definidos no design (user_id FK, bank_name, bank_id, account_number, branch_number, file_name, file_path, period_start, period_end, balance, status, transaction_count, matched_count, error_message, timestamps, indexes)
    - _Requirements: 2.1_
  - [x] 1.2 Criar migration `create_bank_transactions_table` com todos os campos (import_id FK cascade, fitid, date, amount, type, description, memo, check_number, matched_payable_id FK nullable, match_status, match_confidence, raw_data jsonb, timestamps, indexes)
    - _Requirements: 2.2_
  - [x] 1.3 Criar model `App\Models\BankStatementImport` com fillable, casts, relações (user, transactions), trait Auditable
    - _Requirements: 2.1_
  - [x] 1.4 Criar model `App\Models\BankTransaction` com fillable, casts, relações (import, matchedPayable)
    - _Requirements: 2.2_
  - [x] 1.5 Rodar `php artisan migrate` e verificar tabelas criadas
    - _Requirements: 2.1, 2.2_

- [x] 2. OFX Parser Service
  - [x] 2.1 Criar DTOs: `App\Services\Ofx\OfxParseResult`, `App\Services\Ofx\OfxMeta`, `App\Services\Ofx\OfxTransaction` e `App\Services\Ofx\OfxParseException`
    - Classes simples com propriedades públicas conforme design
    - _Requirements: 1.1_
  - [x] 2.2 Implementar `App\Services\OfxParserService` com os métodos: `parse()`, `parseHeader()`, `extractTagValue()`, `parseAmount()`, `parseDate()`, `parseAccountInfo()`, `parseTransactions()`, `parseBalance()`
    - Deve lidar com: tags com/sem closing tags (XML vs SGML), decimal ponto/vírgula, sinal +, timezone em datas, FITID vazio, TRNAMT=0.00 ignorado, lista vazia de transações
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 1.10, 1.11, 1.12, 1.13, 1.14_
  - [x] 2.3 Copiar os 4 arquivos OFX reais para `tests/fixtures/ofx/` como fixtures de teste (bb.ofx, santander.ofx, brb.ofx, banrisul.ofx)
    - _Requirements: 1.1_
  - [ ]* 2.4 Escrever property tests para o parser (data provider com 100+ combinações)
    - **Property 1: Amount parsing round-trip** — para qualquer valor monetário gerado (positivo/negativo, com ponto/vírgula, com/sem +), parseAmount produz float correto
    - **Property 2: Date parsing preserves date component** — para qualquer data válida com qualquer sufixo de timezone, parseDate extrai a mesma data
    - **Property 3: Tag value extraction consistency** — para qualquer tag/valor, extração funciona em ambos os formatos (XML e SGML)
    - **Validates: Requirements 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8**
  - [x] 2.5 Escrever feature tests do parser com os 4 arquivos reais (BB, Santander, BRB, Banrisul)
    - Verificar: quantidade de transações extraídas, valores corretos, metadados da conta, saldo
    - Testar arquivo inválido → OfxParseException
    - _Requirements: 1.1-1.15_

- [x] 3. Checkpoint — Parser testado isoladamente
  - Rodar `php artisan test --filter=OfxParser` — todos devem passar
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Matching Service
  - [x] 4.1 Implementar `App\Services\BankMatchingService` com: `run(importId)`, `findCandidates(BankTransaction)`, `calculateConfidence(txDate, paidAt)`
    - Algoritmo: para cada transação debit, busca Payables status=pago com valor próximo (±R$0.01), classifica confidence por diferença de dias (≤2=high, 3-5=medium, >5=low), seleciona melhor candidato
    - Credits/Other → unmatched direto
    - Detecta duplicidade (mesmo Payable sugerido para múltiplas transações)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10_
  - [ ]* 4.2 Escrever property test para confidence classification (data provider 100+ combinações)
    - **Property 4: Matching confidence classification** — para qualquer par (transaction.date, payable.paid_at), a confidence é determinada pela diferença absoluta em dias
    - **Property 5: Matching excludes non-pago payables** — para qualquer Payable com status ≠ pago, nunca aparece como candidato
    - **Validates: Requirements 3.2, 3.3, 3.4, 3.9**
  - [x] 4.3 Escrever feature tests do matching: match high/medium/low, no match, crédito unmatched, payable já conciliado excluído, múltiplos candidatos ordenados, duplicidade detectada
    - _Requirements: 3.1-3.10_

- [x] 5. Batch Conciliation Service
  - [x] 5.1 Implementar `App\Services\BatchConciliationService` com: `execute(importId, user)`
    - Verifica elegibilidade uma vez, itera transações accepted/manual, executa transição pago→conciliado com lockForUpdate, cria PayableComment, registra auditoria consolidada, atualiza matched_count na import
    - Se payable não está pago → skip com motivo
    - Se nenhuma transação aceita → retorna erro
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8_
  - [ ]* 5.2 Escrever property test para batch conciliation invariant
    - **Property 6: Batch conciliation invariant** — para qualquer conjunto de transações aceitas com payables pago, após batch todos viram conciliado e count bate
    - **Validates: Requirements 5.1, 5.4**
  - [x] 5.3 Escrever feature tests do batch: lote completo, payable que mudou status (skip), sem aceitos (erro), audit log criado, comments criados, matched_count atualizado
    - _Requirements: 5.1-5.8_

- [x] 6. Checkpoint — Services testados
  - Rodar `php artisan test --filter=BankMatching` e `php artisan test --filter=BatchConciliation` — todos devem passar
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Controller e Rotas
  - [x] 7.1 Criar `App\Http\Controllers\BankConciliationController` com todos os métodos: `index`, `show`, `upload`, `accept`, `reject`, `link`, `batchConciliate`, `destroy`, `searchPayables`
    - Upload: valida arquivo .ofx (max 10MB), chama OfxParserService, cria BankStatementImport + BankTransactions, chama BankMatchingService, registra auditoria
    - Accept/Reject/Link: verificam isAssigned('conciliador'), atualizam match_status
    - BatchConciliate: chama BatchConciliationService
    - Destroy: verifica que não há transações conciliadas, deleta em cascata
    - SearchPayables: retorna Payables status=pago filtrando por query (número, fornecedor, valor)
    - _Requirements: 1.15, 4.1-4.7, 5.1, 6.1-6.4, 9.1-9.3_
  - [x] 7.2 Registrar rotas em `routes/web.php` dentro do grupo middleware `financeiro.contas_pagar.visualizar`
    - Prefixo: `financeiro/contas-pagar/conciliacao`
    - Nomes: `bank-conciliation.index`, `.show`, `.upload`, `.accept`, `.reject`, `.link`, `.batch`, `.destroy`, `.search-payables`
    - _Requirements: 7.1_
  - [x] 7.3 Adicionar item "Conciliação Bancária" no menu sidebar (grupo Financeiro)
    - Permissão: `financeiro.contas_pagar.visualizar`
    - Ícone: `pi pi-file-import` ou similar
    - _Requirements: 7.1_
  - [x] 7.4 Escrever feature tests do controller: upload OK, upload 403, upload inválido, accept, reject, link, batch, delete, search-payables, visualização sem conciliador (read-only)
    - _Requirements: 6.1-6.4, 4.1-4.7, 5.1-5.8_

- [x] 8. Checkpoint — Backend completo
  - Rodar `php artisan test --filter=BankConciliation` — todos devem passar
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 9. Frontend — Página de Conciliação Bancária
  - [~] 9.1 Criar `resources/js/Pages/BankConciliation/Index.vue` com versões desktop e mobile
    - Desktop: header, FileUpload (drag&drop, .ofx only), DataTable de importações (data, banco, conta, período, transações, matches, status), link para show
    - Mobile: cards de importações, FAB de upload
    - Props: imports (paginado), isConciliador
    - Upload visível apenas se isConciliador
    - Atributos dusk conforme design
    - `<Toast />` renderizado
    - _Requirements: 7.1-7.3, 7.9, 7.10, 8.1, 8.2_
  - [~] 9.2 Criar `resources/js/Pages/BankConciliation/Show.vue` com versões desktop e mobile
    - Desktop: resumo (banco, conta, período, contadores), DataTable de transações (data, valor, descrição, match sugerido com link, badge confidence, ações), filtros por match_status, botão batch no rodapé, Dialog de busca manual
    - Mobile: cards de transações, bottom sheet de ações, bottom sheet de busca, bottom sheet de confirmação batch
    - Props: import, transactions (paginado), counters, isConciliador, filters
    - Ações inline (aceitar/rejeitar via Inertia preserveScroll), dialog de vincular
    - Atributos dusk conforme design
    - _Requirements: 7.4-7.8, 8.3-8.5, 4.1-4.7_
  - [~] 9.3 Adicionar busca global (`SearchController`) para `BankStatementImport` — pesquisar por banco ou conta
    - _Requirements: 7.1_

- [ ] 10. DemoSeeder
  - [~] 10.1 Estender `DemoSeeder` (ou criar `BankConciliationSeeder` chamado pelo Demo) com:
    - 2 importações de bancos diferentes (BB e Santander)
    - 8-12 transações por importação com mix de: matched (high confidence), matched (medium), unmatched, credit
    - Vincular 3-4 transações a Payables existentes como aceitas/conciliadas
    - Garantir bruno@bstechsolutions.com como conciliador (se não já estiver)
    - _Requirements: 10.1-10.4_

- [~] 11. Checkpoint — Frontend + Seeder
  - Rodar DemoSeeder: `php artisan db:seed --class=DemoSeeder`
  - Verificar que a tela carrega preenchida com volume realista
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 12. Testes Dusk
  - [~] 12.1 Criar `tests/Browser/BankConciliationTest.php` com cenários:
    - Página carrega com título e área de upload
    - Upload de OFX → redirect para show com transações listadas
    - Aceitar match → badge verde "Aceito"
    - Rejeitar match → badge vermelho "Rejeitado"
    - Vincular manual → abre dialog, busca payable, vincula
    - Batch conciliar → toast sucesso, contadores atualizados
    - Mobile: upload via FAB/card
    - Mobile: ações via bottom sheet
    - Read-only para não-conciliador
    - _Requirements: 7.1-7.10, 8.1-8.5_

- [~] 13. Checkpoint final — Suíte completa
  - Rodar `php artisan test` (suíte inteira) — garantir que nada quebrou
  - Rodar `php artisan dusk --filter=BankConciliation` — testes de browser passando
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marcadas com `*` são property tests opcionais e podem ser puladas para MVP mais rápido
- Os 4 arquivos OFX reais em `~/Downloads/` devem ser copiados como fixtures de teste
- O parser é a parte mais crítica: deve funcionar com TODOS os 4 bancos sem exceção
- A conciliação em lote reusa a mesma transição de status da Spec 2 (não reinventa)
- O controller é separado do PayableController para manter responsabilidade única
- A busca global (`SearchController`) deve ser estendida com a nova entidade
