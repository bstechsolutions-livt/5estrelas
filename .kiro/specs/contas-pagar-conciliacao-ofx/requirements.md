# Requirements Document

## Introduction

Esta é a **Spec 3** do épico **Fluxo de Aprovação e Pagamento**. Ela implementa a **Importação de Extrato OFX + Conciliação Assistida** — um passo acima da conciliação manual da Spec 2: o conciliador importa o extrato bancário em formato OFX e o sistema sugere automaticamente quais títulos pagos correspondem a cada transação do banco.

A Spec 2 (`contas-pagar-conciliacao`) já criou:
- Campos `conciliated_at`, `conciliated_by`, `conciliation_notes`, `divergence_reason` no `payables`
- Transições `pago` → `conciliado` e `pago` → `divergente`
- Métodos `PayableController@conciliate` e `PayableController@diverge`
- Validação de elegibilidade via `PayableAlcadaService.isAssigned('conciliador')`

Nesta spec, o conciliador faz **upload de um arquivo OFX**, o sistema extrai as transações bancárias, e para cada transação de débito tenta encontrar um título `pago` correspondente (matching por valor e data). O conciliador revisa as sugestões e pode aceitar ou rejeitar cada match, ou vincular manualmente. Ao final, executa a conciliação em lote dos matches aceitos.

**Bancos reais do cliente** que definem as variações do parser:
- Banco do Brasil (001) — XML-like com closing tags, FITID com pontos, dot decimal
- Santander (033) — SGML sem closing tags, COMMA decimal (17964,26)
- BRB (070) — XML-like com closing tags, explicit + sign (+19992.60)
- Banrisul (041) — SGML sem closing tags, lista de transações pode ser vazia

## Glossary

- **OFX (Open Financial Exchange)**: formato de extrato bancário (variante SGML) exportado pelos bancos brasileiros. Contém header texto + corpo XML/SGML com transações.
- **OFX_Parser**: service class (`App\Services\OfxParserService`) responsável por transformar o conteúdo de um arquivo OFX em estrutura de dados padronizada.
- **Bank_Statement_Import**: registro de uma importação de extrato OFX — metadados do arquivo, banco, conta, período.
- **Bank_Transaction**: uma transação individual extraída do OFX — tipo, data, valor, descrição.
- **Match_Status**: estado do vínculo entre uma Bank_Transaction e um Payable: `pending` (aguardando revisão), `accepted` (conciliador aceitou), `rejected` (conciliador rejeitou), `manual` (conciliador vinculou manualmente), `unmatched` (sem correspondência).
- **Matching_Algorithm**: lógica que compara Bank_Transactions com Payables `pago` para sugerir correspondências.
- **Conciliação_Assistida_Page**: página `/financeiro/contas-pagar/conciliacao` — workspace de conciliação com upload, histórico e revisão.
- **Batch_Conciliation**: ação de conciliar em lote todos os matches aceitos de uma importação.
- **Payable**: título a pagar na tabela `payables`.
- **Conciliador**: usuário na alçada com papel `conciliador` — único que pode importar e conciliar.
- **PayableAlcadaService**: service que verifica elegibilidade por papel na alçada.
- **useDevice()**: composable que detecta mobile (app Flutter ou tela < 1024px).
- **DemoSeeder**: seeder de massa de teste local.

## Requirements

### Requirement 1: Upload e parsing de arquivo OFX

**User Story:** Como conciliador, quero importar um arquivo OFX do extrato bancário para que o sistema extraia automaticamente as transações e inicie o processo de conciliação assistida.

#### Acceptance Criteria

1. WHEN o conciliador faz upload de um arquivo com extensão `.ofx`, THE OFX_Parser SHALL extrair o header OFX (OFXHEADER, DATA, VERSION, ENCODING) e validar que é um arquivo OFX válido.
2. WHEN o OFX contém tags com closing tags (estilo XML, ex.: `<CODE>0</CODE>`), THE OFX_Parser SHALL extrair o valor entre as tags de abertura e fechamento.
3. WHEN o OFX contém tags sem closing tags (estilo SGML, ex.: `<CODE>0`), THE OFX_Parser SHALL extrair o valor como texto até a próxima tag de abertura ou fim de linha.
4. WHEN o campo TRNAMT usa ponto como separador decimal (ex.: `-93.10`, `+19992.60`), THE OFX_Parser SHALL interpretar o ponto como separador decimal.
5. WHEN o campo TRNAMT usa vírgula como separador decimal (ex.: `17964,26`, `-17964,26`), THE OFX_Parser SHALL interpretar a vírgula como separador decimal.
6. WHEN o campo TRNAMT contém sinal positivo explícito (ex.: `+19992.60`), THE OFX_Parser SHALL interpretar como valor positivo (crédito).
7. WHEN o campo DTPOSTED contém timezone (ex.: `20260618000000[-3:GMT]` ou `[-03:BRT]`), THE OFX_Parser SHALL extrair apenas a data (YYYYMMDD) ignorando hora e timezone para fins de conciliação.
8. WHEN o campo DTPOSTED não contém timezone (ex.: `20260608111231`), THE OFX_Parser SHALL extrair os primeiros 8 caracteres como a data (YYYYMMDD).
9. WHEN uma transação possui TRNAMT igual a 0.00 (ex.: "Saldo Anterior", "Saldo do dia"), THE OFX_Parser SHALL ignorar a transação e não incluí-la na lista de resultados.
10. WHEN uma transação possui FITID vazio, THE OFX_Parser SHALL aceitar a transação normalmente, gerando um identificador interno baseado em import_id + posição sequencial.
11. WHEN o bloco BANKTRANLIST não contém nenhuma tag STMTTRN (lista vazia, como no Banrisul), THE OFX_Parser SHALL retornar uma lista vazia de transações sem erro.
12. THE OFX_Parser SHALL extrair os metadados da conta bancária (BANKID, ACCTID, BRANCHID opcional, ACCTTYPE) e da instituição (ORG, FID) do bloco BANKACCTFROM e SIGNONMSGSRSV1.
13. THE OFX_Parser SHALL extrair o saldo (LEDGERBAL → BALAMT) e a data do saldo (DTASOF) quando presentes.
14. IF o arquivo enviado não é um OFX válido (sem header OFXHEADER ou sem bloco OFX), THEN THE sistema SHALL rejeitar o upload com mensagem de erro descritiva e não criar registros.
15. WHEN o parsing é concluído com sucesso, THE sistema SHALL criar um registro Bank_Statement_Import e um registro Bank_Transaction para cada transação válida extraída.

### Requirement 2: Armazenamento de importações e transações

**User Story:** Como conciliador, quero que as importações e transações fiquem persistidas para que eu possa revisitar importações anteriores e manter o histórico.

#### Acceptance Criteria

1. THE sistema SHALL persistir cada importação na tabela `bank_statement_imports` com: id, user_id (quem importou), bank_name, bank_id (código banco), account_number, branch_number (opcional), file_name, file_path, period_start, period_end, balance, status (processing/done/error), transaction_count, matched_count, created_at.
2. THE sistema SHALL persistir cada transação na tabela `bank_transactions` com: id, import_id (FK), fitid, date, amount (decimal 2 casas, sempre positivo com coluna type separada), type (credit/debit/other), description (NAME ou MEMO), memo, check_number (opcional), matched_payable_id (FK nullable), match_status (pending/accepted/rejected/manual/unmatched), match_confidence (high/medium/low/none), raw_data (JSON completo da transação original), created_at.
3. WHEN uma importação é duplicada (mesmo arquivo ou mesmo FITID+bank_id+account+date), THE sistema SHALL alertar o conciliador sobre possível duplicidade mas permitir a importação.
4. THE sistema SHALL garantir que excluir uma importação exclui em cascata todas as Bank_Transactions associadas (desde que não estejam no status `accepted` ou conciliadas).

### Requirement 3: Algoritmo de matching

**User Story:** Como conciliador, quero que o sistema sugira automaticamente quais títulos pagos correspondem a cada transação do banco, para agilizar a conciliação.

#### Acceptance Criteria

1. WHEN uma importação é concluída, THE Matching_Algorithm SHALL processar cada Bank_Transaction de tipo `debit` e buscar Payables com status `pago` que correspondam.
2. WHEN o valor absoluto da Bank_Transaction for igual ao valor do Payable (tolerância de R$ 0.01) E a data da transação estiver dentro de ±2 dias da data de pagamento (`paid_at`), THE Matching_Algorithm SHALL classificar como match de confiança `high`.
3. WHEN o valor absoluto da Bank_Transaction for igual ao valor do Payable (tolerância de R$ 0.01) E a data da transação estiver entre 3 e 5 dias de diferença da data de pagamento, THE Matching_Algorithm SHALL classificar como match de confiança `medium`.
4. WHEN o valor absoluto da Bank_Transaction for igual ao valor do Payable (tolerância de R$ 0.01) mas a data estiver fora da janela de 5 dias, THE Matching_Algorithm SHALL classificar como match de confiança `low`.
5. WHEN múltiplos Payables correspondem a uma mesma Bank_Transaction, THE Matching_Algorithm SHALL listar todos os candidatos ordenados por confiança (high > medium > low) e sugerir o de maior confiança.
6. WHEN nenhum Payable corresponde a uma Bank_Transaction (nenhum match de valor), THE Matching_Algorithm SHALL marcar a transação como `unmatched` com confiança `none`.
7. WHEN uma Bank_Transaction é de tipo `credit` ou `other`, THE Matching_Algorithm SHALL marcar como `unmatched` (créditos e outros não são pagamentos saindo).
8. THE Matching_Algorithm SHALL atualizar os campos `matched_payable_id`, `match_status` e `match_confidence` de cada Bank_Transaction ao final do processamento.
9. THE Matching_Algorithm SHALL considerar somente Payables que ainda NÃO foram conciliados (status `pago`, não `conciliado` nem `divergente`) para evitar matches duplos.
10. WHEN o mesmo Payable é sugerido para múltiplas Bank_Transactions, THE Matching_Algorithm SHALL alertar sobre possível duplicidade de pagamento.

### Requirement 4: Revisão e ações do conciliador

**User Story:** Como conciliador, quero revisar as sugestões do sistema e aceitar, rejeitar ou vincular manualmente cada transação para garantir que a conciliação está correta.

#### Acceptance Criteria

1. WHEN o conciliador visualiza uma importação, THE Conciliação_Assistida_Page SHALL exibir a lista de transações com: data, valor, descrição/memo, status do match, título sugerido (se houver) e confiança.
2. WHEN o conciliador aceita um match sugerido, THE sistema SHALL alterar o match_status da Bank_Transaction para `accepted`.
3. WHEN o conciliador rejeita um match sugerido, THE sistema SHALL alterar o match_status para `rejected` e limpar o matched_payable_id.
4. WHEN o conciliador busca manualmente um Payable para vincular a uma transação sem match, THE Conciliação_Assistida_Page SHALL oferecer busca por número do título, fornecedor ou valor, filtrando apenas Payables com status `pago`.
5. WHEN o conciliador vincula manualmente um Payable a uma Bank_Transaction, THE sistema SHALL alterar o match_status para `manual` e registrar o matched_payable_id.
6. THE Conciliação_Assistida_Page SHALL exibir contadores resumo: total de transações débito, matched (aceitos + manuais), pendentes, rejeitados, sem correspondência.
7. WHEN o conciliador altera a decisão (ex.: aceitar um que havia rejeitado), THE sistema SHALL permitir a alteração enquanto a conciliação em lote não foi executada.

### Requirement 5: Conciliação em lote (batch)

**User Story:** Como conciliador, quero conciliar em lote todos os matches aceitos de uma importação para efetuar a conciliação de forma eficiente.

#### Acceptance Criteria

1. WHEN o conciliador aciona "Conciliar aceitos", THE sistema SHALL processar todas as Bank_Transactions da importação com match_status `accepted` ou `manual` e, para cada uma, executar a conciliação do Payable vinculado (transição `pago` → `conciliado`).
2. THE Batch_Conciliation SHALL verificar a elegibilidade do conciliador (via PayableAlcadaService) uma única vez no início do lote.
3. WHEN um Payable do lote já não está no status `pago` (concorrência ou mudança), THE sistema SHALL pular esse item, marcar a Bank_Transaction como `rejected` com motivo, e continuar o restante do lote.
4. THE Batch_Conciliation SHALL persistir `conciliated_at`, `conciliated_by` e `conciliation_notes` (gerada automaticamente referenciando a importação OFX) em cada Payable conciliado.
5. WHEN a conciliação em lote é concluída, THE sistema SHALL registrar um log de auditoria consolidado com evento `contas_pagar.conciliacao_lote` indicando quantos títulos foram conciliados, o ID da importação e quem executou.
6. WHEN a conciliação em lote é concluída, THE sistema SHALL adicionar um comentário (type `conciliation`) na timeline de cada Payable conciliado referenciando a importação OFX.
7. WHEN a conciliação em lote é concluída com sucesso, THE sistema SHALL atualizar o status da Bank_Statement_Import para refletir o progresso (matched_count atualizado).
8. IF nenhuma Bank_Transaction está com match_status `accepted` ou `manual`, THEN THE sistema SHALL informar que não há transações para conciliar.

### Requirement 6: Controle de acesso

**User Story:** Como responsável financeiro, quero que apenas conciliadores da alçada possam importar extratos e executar a conciliação assistida.

#### Acceptance Criteria

1. WHERE o usuário está associado ao papel `conciliador` na Alcada_CP e está ativo, THE sistema SHALL permitir upload de OFX, revisão de matches e execução de conciliação em lote.
2. IF o usuário NÃO está associado ao papel `conciliador` na Alcada_CP, THEN THE sistema SHALL negar o acesso às ações de importação e conciliação com retorno 403.
3. THE acesso de visualização (ver a página e histórico de importações) SHALL ser governado pela permissão `financeiro.contas_pagar.visualizar` (qualquer usuário com acesso ao módulo pode ver, mas só conciliador executa).
4. WHILE o usuário tem apenas permissão de visualização (mas não é conciliador), THE Conciliação_Assistida_Page SHALL exibir o histórico e detalhes em modo read-only, sem botões de ação.

### Requirement 7: Apresentação desktop

**User Story:** Como usuário no desktop, quero uma página dedicada de conciliação bancária com upload, histórico e tabela de revisão.

#### Acceptance Criteria

1. THE sistema SHALL disponibilizar a rota `/financeiro/contas-pagar/conciliacao` como página de conciliação bancária com menu "Conciliação Bancária" no grupo Financeiro.
2. THE Conciliação_Assistida_Page SHALL conter uma área de upload (drag & drop ou seleção de arquivo, aceita apenas `.ofx`) visível apenas para conciliadores.
3. THE Conciliação_Assistida_Page SHALL exibir a lista de importações anteriores (DataTable) com: data, banco, conta, período, total de transações, matches aceitos, status.
4. WHEN o conciliador seleciona uma importação, THE sistema SHALL exibir a tabela de transações com colunas: data, valor, descrição, match (título sugerido com link), confiança (badge colorido), ação.
5. THE tabela de transações SHALL ter filtros por match_status (pendente, aceito, rejeitado, sem match) e ordenação por data e valor.
6. WHEN o conciliador clica "Aceitar" em uma transação com match sugerido, THE sistema SHALL atualizar o status inline sem recarregar a página.
7. WHEN o conciliador clica "Vincular" em uma transação sem match, THE sistema SHALL abrir um Dialog de busca de Payable com campo de pesquisa e resultados em tabela.
8. THE sistema SHALL exibir o botão "Conciliar Aceitos" (com contagem) fixo no rodapé da tabela, habilitado somente quando há ao menos 1 transação aceita/manual.
9. WHEN o processamento de matching está em andamento (arquivo grande), THE sistema SHALL exibir feedback de progresso.
10. THE página SHALL renderizar o componente `<Toast />` do PrimeVue para feedback de ações.

### Requirement 8: Apresentação mobile dedicada

**User Story:** Como usuário no app, quero conciliar extratos numa interface que pareça nativa.

#### Acceptance Criteria

1. WHILE o dispositivo é detectado como mobile por `useDevice()`, THE Conciliação_Assistida_Page SHALL apresentar a lista de importações como cards verticais em vez de DataTable.
2. WHILE mobile, THE área de upload SHALL ser um botão de ação flutuante (FAB) ou card de upload simplificado em vez de drag & drop.
3. WHILE mobile, THE tabela de transações SHALL ser apresentada como lista de cards com valor, descrição e badge de status, com ação principal por toque.
4. WHEN o conciliador toca em uma transação no mobile, THE sistema SHALL abrir um bottom sheet com detalhes e ações (aceitar, rejeitar, vincular).
5. WHEN a conciliação em lote é acionada no mobile, THE sistema SHALL abrir bottom sheet de confirmação com resumo (X transações a conciliar).

### Requirement 9: Auditoria

**User Story:** Como auditor, quero que importações e conciliações em lote fiquem registradas para rastreabilidade.

#### Acceptance Criteria

1. WHEN uma importação OFX é realizada com sucesso, THE sistema SHALL registrar log de auditoria com evento `contas_pagar.ofx_importado`, módulo `financeiro.contas_pagar`, indicando banco, conta, período, quantidade de transações extraídas.
2. WHEN uma conciliação em lote é executada, THE sistema SHALL registrar log de auditoria com evento `contas_pagar.conciliacao_lote`, módulo `financeiro.contas_pagar`, indicando quantidade conciliada, ID da importação, quem executou.
3. WHEN uma importação é excluída, THE sistema SHALL registrar log de auditoria com evento `contas_pagar.ofx_excluido`.
4. THE registros de auditoria SHALL preservar old_values e new_values relevantes.

### Requirement 10: Massa de teste (DemoSeeder)

**User Story:** Como desenvolvedor, quero rodar localmente com importações e transações de exemplo para validar a interface com volume real.

#### Acceptance Criteria

1. THE DemoSeeder SHALL criar ao menos 2 Bank_Statement_Imports (bancos diferentes) com transações variadas: matched, unmatched, pendentes.
2. THE DemoSeeder SHALL vincular algumas Bank_Transactions a Payables existentes (simulando matches aceitos e conciliados).
3. THE DemoSeeder SHALL garantir que `bruno@bstechsolutions.com` está associado ao papel `conciliador` (se não já estiver da Spec 2).
4. THE DemoSeeder SHALL ser idempotente ou documentar a necessidade de `migrate:fresh`.

## Fora do escopo desta spec

- Importação automática via API bancária (Open Banking) — spec futura.
- Parsing de arquivos CNAB/retorno bancário — spec futura.
- Conciliação de contas a receber — spec futura.
- Estorno de conciliação em lote — spec futura.
- Notificações automáticas sobre divergências — spec futura.
- Integração com Senior para comunicar status de conciliação.

## Decisões adotadas

1. **Parser interno sem dependência externa**: o formato OFX/SGML é simples o suficiente para um parser customizado. Evita dependência de pacotes desatualizados.
2. **Foco em transações de débito**: para o Contas a Pagar, só os débitos (saídas) são relevantes para matching com títulos pagos. Créditos ficam como `unmatched` para referência.
3. **Tolerância de R$ 0.01 no valor**: cobre arredondamentos bancários mínimos.
4. **Janela de data ±2 dias (high) / ±5 dias (medium)**: compensação de TED/DOC pode levar 1-2 dias úteis; boletos podem ter D+3.
5. **Conciliação em lote reusa a mesma lógica da Spec 2** (transição `pago` → `conciliado`), mas em batch com auditoria consolidada.
6. **Visualização aberta, execução restrita**: qualquer usuário com permissão do módulo pode ver as importações, mas só conciliador executa ações.
7. **Não bloqueia duplicidade de importação**: alerta mas permite (o mesmo extrato pode ser re-importado para correção).
