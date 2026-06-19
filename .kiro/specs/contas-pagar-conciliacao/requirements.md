# Requirements Document

## Introduction

Esta é a **Spec 2** do épico **Fluxo de Aprovação e Pagamento**. Ela implementa a **Conciliação Bancária** — a etapa que vem logo após o pagamento, onde o conciliador verifica se o pagamento registrado no sistema confere com o extrato bancário.

A Spec 1 (`contas-pagar-alcada-pagamento`) já criou:
- A tabela `payable_roles` com os papéis `pagador`, `conciliador` e `assinante`
- O serviço `PayableAlcadaService` com `isAssigned(user, role)`
- A transição `aprovado` → `pago`

Nesta spec, o papel `conciliador` (já existente na Alcada_CP) passa a ser **consumido** por uma ação: o conciliador verifica títulos `pago` e os marca como `conciliado` (pagamento confere com o banco) ou `divergente` (pagamento não confere). É uma verificação **manual** — não há importação automática de extrato (OFX) nesta spec.

**Fluxo de status ampliado**: `aprovado` → `pago` → `conciliado` (ou `divergente`).

## Glossary

- **Payable**: registro da tabela `payables` que representa um título a pagar (módulo Contas a Pagar).
- **Conciliador**: usuário associado ao papel `conciliador` na Alcada_CP; é quem pode conciliar pagamentos.
- **Conciliar**: ação de verificar que o pagamento registrado confere com o extrato bancário e marcar o título como `conciliado`.
- **Divergência**: situação em que o pagamento registrado no sistema não confere com o extrato bancário. O título é marcado como `divergente`.
- **Alcada_CP**: cadastro de alçada do Contas a Pagar — conjunto de associações entre papéis e usuários (criado na Spec 1).
- **Conciliacao_Form**: formulário (dialog no desktop, bottom sheet no mobile) para registrar a conciliação ou divergência.
- **PayableAlcadaService**: service existente que fornece `isAssigned(user, role)` — será reutilizado para o conciliador.
- **useDevice()**: composable que determina se o cliente é mobile (app Flutter ou tela < 1024px).
- **DemoSeeder**: seeder de massa de teste local.
- **Permissão curinga (`*`)**: permissão de administrador que dá acesso a todas as rotas protegidas por permissão.

## Requirements

### Requirement 1: Elegibilidade para conciliar (governada pela alçada)

**User Story:** Como responsável pelo controle financeiro, quero que apenas quem está na alçada como conciliador consiga conciliar pagamentos, para preservar a segregação de funções.

#### Acceptance Criteria

1. WHERE o usuário autenticado está associado ao papel `conciliador` na Alcada_CP e está ativo, THE sistema SHALL permitir que ele execute a ação de conciliação.
2. IF o usuário autenticado NÃO está associado ao papel `conciliador` na Alcada_CP, THEN THE sistema SHALL negar a conciliação com retorno 403, mesmo que o usuário possua a permissão curinga (`*`).
3. WHILE a viewport e a permissão permitem visualizar o título, THE sistema SHALL exibir a ação de conciliar somente para usuários elegíveis pelo critério 1.1, ocultando-a dos demais.
4. IF nenhum usuário está associado ao papel `conciliador` na Alcada_CP, THEN THE sistema SHALL negar a conciliação para todos e indicar que a alçada de conciliação não está configurada.

### Requirement 2: Conciliar um título pago (caminho feliz)

**User Story:** Como conciliador, quero marcar um título pago como conciliado após verificar que o pagamento confere com o extrato bancário, para fechar a etapa de conciliação do fluxo.

#### Acceptance Criteria

1. WHERE o Payable está no status `pago`, THE sistema SHALL permitir a ação de conciliação (sujeito à elegibilidade do Requirement 1).
2. WHEN um Conciliador confirma a conciliação de um Payable, THE Conciliacao_Form SHALL capturar uma observação opcional (texto livre, máximo 1000 caracteres).
3. WHEN a conciliação é registrada com sucesso, THE sistema SHALL alterar o status do Payable para `conciliado` e registrar quem conciliou e quando.
4. THE sistema SHALL persistir a data da conciliação (`conciliated_at`) e o responsável (`conciliated_by`) no Payable.
5. IF o Payable está em qualquer status diferente de `pago`, THEN THE sistema SHALL recusar a conciliação e informar que o título não está apto a ser conciliado.
6. WHEN dois pedidos de conciliação concorrentes são tentados sobre o mesmo Payable, THE sistema SHALL efetivar no máximo um, mantendo a transição de status idempotente.

### Requirement 3: Registrar divergência

**User Story:** Como conciliador, quero sinalizar que um pagamento não confere com o extrato bancário, para que a equipe financeira investigue o problema.

#### Acceptance Criteria

1. WHEN um Conciliador registra uma divergência em um Payable com status `pago`, THE sistema SHALL alterar o status para `divergente`.
2. WHEN um Conciliador registra uma divergência, THE Conciliacao_Form SHALL exigir uma observação obrigatória descrevendo o motivo da divergência (mínimo 10 caracteres, máximo 1000 caracteres).
3. THE sistema SHALL persistir a data da divergência e o responsável no Payable.
4. THE status `divergente` SHALL ser exibido com severidade `danger` (cor vermelha) nas interfaces de lista e detalhe.
5. IF o Payable está em qualquer status diferente de `pago`, THEN THE sistema SHALL recusar o registro de divergência e informar que o título não está apto.

### Requirement 4: Validação de estado de origem

**User Story:** Como operador, quero que só títulos pagos possam ser conciliados ou marcados como divergentes, para que o fluxo não pule etapas.

#### Acceptance Criteria

1. WHERE o Payable está no status `pago`, THE sistema SHALL permitir as ações de conciliar e registrar divergência.
2. IF o Payable está em qualquer status diferente de `pago` (ex.: `pendente`, `aprovado`, `conciliado`, `divergente`), THEN THE sistema SHALL recusar ambas as ações e informar que o título não está apto.
3. WHEN dois pedidos concorrentes (conciliação ou divergência) são tentados sobre o mesmo Payable, THE sistema SHALL efetivar no máximo um, mantendo a transição idempotente (o segundo encontra o título fora de `pago` e é recusado conforme 4.2).

### Requirement 5: Auditoria e histórico da conciliação

**User Story:** Como auditor, quero que cada conciliação e divergência fique registrada na linha do tempo do título e na auditoria, para rastreabilidade.

#### Acceptance Criteria

1. WHEN uma conciliação é registrada, THE sistema SHALL registrar um log de auditoria no módulo `financeiro.contas_pagar` com evento `contas_pagar.conciliado`, identificando o título, quem conciliou, quando e a observação (se houver).
2. WHEN uma divergência é registrada, THE sistema SHALL registrar um log de auditoria no módulo `financeiro.contas_pagar` com evento `contas_pagar.divergente`, identificando o título, quem registrou, quando e o motivo da divergência.
3. WHEN uma conciliação é registrada, THE sistema SHALL adicionar uma entrada na linha do tempo (comentário tipo `conciliation`) do Payable indicando que foi conciliado, por quem e quando.
4. WHEN uma divergência é registrada, THE sistema SHALL adicionar uma entrada na linha do tempo (comentário tipo `divergence`) do Payable indicando a divergência, por quem, quando e o motivo.
5. THE registros de auditoria SHALL preservar os dados anteriores e novos relevantes (status anterior `pago` → novo `conciliado` ou `divergente`).

### Requirement 6: Novos status no modelo

**User Story:** Como desenvolvedor, quero que os novos status `conciliado` e `divergente` existam no modelo Payable com labels e cores corretos, para que toda a interface os reconheça.

#### Acceptance Criteria

1. THE modelo Payable SHALL incluir o status `conciliado` com label "Conciliado" e cor `success` (verde).
2. THE modelo Payable SHALL incluir o status `divergente` com label "Divergente" e cor `danger` (vermelho).
3. WHEN a tela de lista (`Index`) exibe títulos, THE sistema SHALL permitir filtrar pelos novos status `conciliado` e `divergente`.
4. THE novos campos `conciliated_at`, `conciliated_by`, `conciliation_notes` e `divergence_reason` SHALL ser adicionados ao `WORKFLOW_FIELDS` para que a sincronização com a Senior não os sobrescreva.

### Requirement 7: Apresentação no desktop

**User Story:** Como usuário no desktop, quero ver a ação de conciliar e o resultado da conciliação de forma clara na tela de detalhe do título.

#### Acceptance Criteria

1. WHILE a viewport for maior ou igual a 1024px e o título estiver `pago` e o usuário for elegível (conciliador), THE tela de detalhe do Payable SHALL exibir a ação "Conciliar" que abre o Conciliacao_Form como dialog.
2. THE Conciliacao_Form SHALL apresentar duas opções: "Conciliar" (pagamento confere) e "Registrar divergência" (pagamento não confere).
3. WHEN o usuário seleciona "Conciliar", THE formulário SHALL exibir um campo de observação opcional e o botão de confirmação.
4. WHEN o usuário seleciona "Registrar divergência", THE formulário SHALL exibir um campo de motivo obrigatório e o botão de confirmação com severidade `danger`.
5. WHEN a conciliação ou divergência é concluída com sucesso, THE sistema SHALL exibir feedback de sucesso (toast) e refletir o novo status na tela sem recarregamento manual.
6. WHEN o título está no status `conciliado`, THE tela de detalhe SHALL exibir um bloco read-only com data da conciliação, nome do conciliador e observação (se houver).
7. WHEN o título está no status `divergente`, THE tela de detalhe SHALL exibir um bloco read-only com data, nome do responsável e motivo da divergência, estilizado com severidade `danger`.
8. THE telas desta spec que usam toast SHALL renderizar o componente `<Toast />` do PrimeVue no template.

### Requirement 8: Apresentação mobile dedicada

**User Story:** Como usuário no app, quero conciliar pagamentos numa interface que pareça nativa.

#### Acceptance Criteria

1. WHILE o dispositivo é detectado como mobile por `useDevice()`, THE Conciliacao_Form SHALL ser apresentado como bottom sheet (sobe do fundo), fechável por gesto de arrastar para baixo, com os campos em largura total e o botão de confirmar fixo na parte inferior.
2. WHEN a conciliação ou divergência é concluída no mobile, THE sistema SHALL exibir feedback de sucesso e atualizar o status do título exibido sem recarregamento manual.
3. THE versão mobile SHALL oferecer as mesmas regras de elegibilidade e validação dos Requirements 1, 2, 3 e 4.

### Requirement 9: Massa de teste local (DemoSeeder)

**User Story:** Como desenvolvedor, quero rodar o sistema localmente com títulos em diferentes estados de conciliação, para validar a interface e os fluxos.

#### Acceptance Criteria

1. THE DemoSeeder SHALL garantir que `bruno@bstechsolutions.com` esteja associado ao papel `conciliador` na Alcada_CP (para testes Dusk).
2. THE DemoSeeder SHALL garantir a existência de títulos no status `pago` (para serem conciliados), títulos `conciliado` (com dados de conciliação preenchidos) e pelo menos um título `divergente` (com motivo preenchido).
3. THE DemoSeeder SHALL ser idempotente ou documentar a necessidade de `migrate:fresh`.

## Fora do escopo desta spec

- Importação automática de extrato bancário (OFX/CNAB) — spec futura.
- Conciliação em lote (conciliar vários títulos de uma vez) — spec futura.
- Estorno / desfazer conciliação — spec futura.
- Resolver divergência (transição `divergente` → algum status) — spec futura; títulos divergentes ficam nesse status até tratamento manual.
- 2ª assinatura / encerramento (Spec 3).
- Integração com Senior para status de conciliação.

## Decisões adotadas

1. **Alçada manda, igual ao pagamento.** Conciliar exige estar na alçada como `conciliador`; nem `*` fura (segregação de função). O `*` continua valendo para gerenciar a alçada.
2. **Dois status novos**: `conciliado` (feliz) e `divergente` (problema detectado). Ambos nascem a partir de `pago`.
3. **Divergência não volta ao pagador automaticamente** — fica no status `divergente` para investigação. O fluxo de "resolver divergência" é spec futura.
4. **Observação opcional na conciliação, obrigatória na divergência** — para explicar o que não conferiu.
5. **Mesmo padrão da Spec 1**: reusa `PayableAlcadaService.isAssigned('conciliador')`, `PayableComment`, `AuditLogger`, `lockForUpdate` para concorrência, `Dialog`/`BottomSheet` para mobile, etc.
