# Requirements Document

## Introduction

Esta é a **Spec 1** do épico **Fluxo de Aprovação e Pagamento** (ver steering `fluxo-aprovacao-pagamento.md` e PDF `docs/fluxo-aprovacao-compras-financeiro.pdf`). Ela cobre **duas peças** da Fase 2 (Pagamento):

1. **Cadastro de Alçada do Contas a Pagar** — uma configuração própria do módulo onde se define **quem ocupa cada papel** do fluxo de pagamento (quem paga, quem concilia, quem assina), editável em tempo real, sem deploy. É a **fonte de verdade** de quem pode executar cada ação do fluxo.
2. **Registrar pagamento** — a ação que leva um título de `aprovado` para `pago`, executável apenas por quem está na alçada como pagador.

A abordagem é **incremental sobre o `Payable` existente** (nada é apagado): aproveita model, controller, status, comentários, anexos, auditoria e telas atuais, e adiciona o cadastro de alçada e a ação de pagamento. O status `pago` já existe em `Payable::STATUS_LABELS`; esta spec cria a **ação** que faz a transição (hoje inexistente).

Nesta spec, o cadastro de alçada já contempla os três papéis da Fase 2 (pagador, conciliador, assinante) para a configuração nascer coesa, mas **apenas o papel pagador é consumido** por uma ação aqui. Conciliador e assinante passam a ser consumidos nas Specs 2 e 3.

## Glossary

- **Payable**: registro da tabela `payables` que representa um título a pagar (módulo Contas a Pagar).
- **Alcada_CP**: o cadastro de alçada do Contas a Pagar — conjunto de associações entre um **Papel** e um ou mais **Usuários** responsáveis.
- **Papel**: função no fluxo de pagamento. Nesta spec: `pagador`, `conciliador`, `assinante`.
- **Pagador**: usuário associado ao papel `pagador` na Alcada_CP; é quem pode registrar pagamentos.
- **Alcada_Admin**: a tela administrativa de gestão da Alcada_CP (configurar responsáveis por papel).
- **Registrar_Pagamento**: a ação que transiciona um Payable de `aprovado` para `pago`.
- **Pagamento_Form**: o formulário (dialog no desktop, bottom sheet no mobile) de registro de pagamento.
- **Comprovante**: arquivo anexado ao registro de pagamento (reaproveita a estrutura de documentos do Payable).
- **Forma_Pagamento**: meio do pagamento, escolhido de uma lista fixa (PIX, TED, Boleto, Dinheiro, Outro).
- **useDevice()**: composable que determina se o cliente é mobile (app Flutter ou tela < 1024px) — ver `mobile-ux.md`.
- **DemoSeeder**: seeder de massa de teste local (`database/seeders/DemoSeeder.php`).
- **Permissão curinga (`*`)**: permissão de administrador que dá acesso a todas as rotas protegidas por permissão (ver spec-03).

## Requirements

### Requirement 1: Visualizar a configuração de alçada

**User Story:** Como administrador financeiro, quero ver quem está configurado em cada papel do fluxo de pagamento, para conferir e auditar a alçada vigente.

#### Acceptance Criteria

1. WHEN um usuário com a permissão de gestão de alçada acessa a Alcada_Admin, THE Alcada_Admin SHALL exibir os papéis `pagador`, `conciliador` e `assinante`, cada um com a lista de usuários atualmente associados (nome e e-mail).
2. WHERE um papel não possui nenhum usuário associado, THE Alcada_Admin SHALL exibir esse papel com indicação explícita de que está sem responsável definido.
3. THE Alcada_Admin SHALL apresentar, para cada papel, uma descrição curta do que aquele papel faz no fluxo (ex.: "Pagador — registra o pagamento dos títulos aprovados").
4. IF o usuário não possui a permissão de gestão de alçada, THEN THE sistema SHALL retornar 403 ao acessar a Alcada_Admin.

### Requirement 2: Gerenciar responsáveis por papel (tempo real)

**User Story:** Como administrador financeiro, quero adicionar e remover pessoas de cada papel a qualquer momento, para refletir mudanças de responsável sem precisar de deploy.

#### Acceptance Criteria

1. THE Alcada_Admin SHALL permitir associar um ou mais usuários ativos a cada papel.
2. THE Alcada_Admin SHALL permitir remover um usuário de um papel.
3. WHEN o administrador salva uma alteração na Alcada_CP, THE sistema SHALL aplicar a alteração imediatamente, de modo que a próxima verificação de elegibilidade (ex.: registrar pagamento) já considere a configuração atualizada.
4. THE Alcada_CP SHALL impedir associar o mesmo usuário duas vezes ao mesmo papel (sem duplicatas).
5. WHERE um usuário associado a um papel é posteriormente inativado no cadastro de usuários, THE sistema SHALL desconsiderá-lo na verificação de elegibilidade do papel, sem exigir remoção manual da alçada.
6. THE Alcada_CP SHALL permitir que o mesmo usuário ocupe mais de um papel simultaneamente.

> Observação: a **ordenação de substitutos** (titular + suplentes do assinante) NÃO faz parte desta spec — será tratada na Spec 3 (2ª assinatura), onde a semântica de substituição do presidente é relevante.

### Requirement 3: Permissão e auditoria da gestão de alçada

**User Story:** Como auditor, quero que só pessoas autorizadas mexam na alçada e que toda mudança fique registrada, para garantir a integridade da segregação de funções.

#### Acceptance Criteria

1. THE sistema SHALL proteger as rotas de gestão da Alcada_CP por uma permissão dedicada (`contas_pagar.alcada.gerenciar`), retornando 403 a quem não a possui.
2. WHERE o usuário possui a permissão curinga (`*`), THE sistema SHALL conceder acesso à gestão da Alcada_CP.
3. WHEN um usuário associa um responsável a um papel, THE sistema SHALL registrar um log de auditoria no módulo `financeiro.contas_pagar` identificando o papel, o usuário associado e quem executou a ação.
4. WHEN um usuário remove um responsável de um papel, THE sistema SHALL registrar um log de auditoria no módulo `financeiro.contas_pagar` identificando o papel, o usuário removido e quem executou a ação.

### Requirement 4: Elegibilidade para registrar pagamento (governada pela alçada)

**User Story:** Como responsável pelo controle financeiro, quero que apenas quem está na alçada como pagador consiga registrar pagamentos, para preservar a segregação de funções.

#### Acceptance Criteria

1. WHERE o usuário autenticado está associado ao papel `pagador` na Alcada_CP e está ativo, THE sistema SHALL permitir que ele execute Registrar_Pagamento.
2. IF o usuário autenticado NÃO está associado ao papel `pagador` na Alcada_CP, THEN THE sistema SHALL negar Registrar_Pagamento com retorno 403, mesmo que o usuário possua a permissão curinga (`*`).
3. WHILE a viewport e a permissão permitem visualizar o título, THE sistema SHALL exibir a ação de registrar pagamento somente para usuários elegíveis pelo critério 4.1, ocultando-a dos demais.
4. IF nenhum usuário está associado ao papel `pagador` na Alcada_CP, THEN THE sistema SHALL negar Registrar_Pagamento para todos e indicar que a alçada de pagamento não está configurada.

### Requirement 5: Registrar pagamento de um título aprovado

**User Story:** Como pagador, quero registrar o pagamento de um título aprovado informando data, forma e comprovante, para fechar a etapa de pagamento do fluxo.

#### Acceptance Criteria

1. WHEN um Pagador confirma Registrar_Pagamento de um Payable, THE Pagamento_Form SHALL capturar a data do pagamento (com valor padrão igual à data atual), a Forma_Pagamento (opcional) e um Comprovante (anexo opcional).
2. THE Pagamento_Form SHALL recusar data de pagamento posterior à data atual.
3. WHEN o pagamento é registrado com sucesso, THE sistema SHALL alterar o status do Payable para `pago` e registrar quem pagou e quando.
4. THE Registrar_Pagamento SHALL tratar o pagamento como **total** do valor em aberto do título; valor parcial, juros e desconto estão fora desta spec.
5. WHEN um Comprovante é anexado, THE sistema SHALL armazená-lo associado ao Payable, reaproveitando a estrutura de documentos existente, e mantê-lo acessível na tela de detalhe.
6. WHEN o pagamento é registrado, THE Forma_Pagamento informada SHALL ser persistida no Payable e exibida na tela de detalhe.
7. WHEN o registro de pagamento falha por erro de validação ou de persistência, THE sistema SHALL preservar o status anterior do Payable e não criar registro de pagamento parcial.

### Requirement 6: Validação de estado de origem

**User Story:** Como operador, quero que só títulos aprovados possam ser pagos, para que o fluxo não pule etapas.

#### Acceptance Criteria

1. WHERE o Payable está no status `aprovado`, THE sistema SHALL permitir Registrar_Pagamento (sujeito à elegibilidade do Requirement 4).
2. IF o Payable está em qualquer status diferente de `aprovado` (ex.: `pendente`, `em_preparacao`, `aguardando_aprovacao`, `reprovado`, `pago`), THEN THE sistema SHALL recusar Registrar_Pagamento e informar que o título não está apto a ser pago.
3. WHEN dois registros de pagamento concorrentes são tentados sobre o mesmo Payable, THE sistema SHALL efetivar no máximo um, mantendo a transição de status idempotente (o segundo encontra o título já `pago` e é recusado conforme 6.2).

### Requirement 7: Auditoria e histórico do pagamento

**User Story:** Como auditor, quero que cada pagamento fique registrado na linha do tempo do título e na auditoria, para rastreabilidade.

#### Acceptance Criteria

1. WHEN um pagamento é registrado, THE sistema SHALL registrar um log de auditoria no módulo `financeiro.contas_pagar` com evento `contas_pagar.pago`, identificando o título, o valor, a data do pagamento e quem pagou.
2. WHEN um pagamento é registrado, THE sistema SHALL adicionar uma entrada na linha do tempo (comentário do tipo mudança de status) do Payable indicando que o título foi pago, por quem e quando.
3. THE registro de auditoria do pagamento SHALL preservar os dados anteriores e novos relevantes (status anterior `aprovado` → novo `pago`).

### Requirement 8: Apresentação no desktop

**User Story:** Como usuário no desktop, quero acessar a configuração de alçada e a ação de pagamento de forma clara dentro do módulo.

#### Acceptance Criteria

1. THE Alcada_Admin SHALL ser acessível por um item de navegação visível apenas para usuários com a permissão de gestão de alçada.
2. WHILE a viewport for maior ou igual a 1024px e o título estiver `aprovado` e o usuário for elegível, THE tela de detalhe do Payable SHALL exibir a ação "Registrar pagamento" que abre o Pagamento_Form como dialog.
3. WHEN o pagamento é concluído com sucesso, THE sistema SHALL exibir feedback de sucesso (toast) e refletir o novo status `pago` na tela sem recarregamento manual.
4. THE telas desta spec que usam toast SHALL renderizar o componente `<Toast />` do PrimeVue no template (gotcha conhecido — ver `mobile-ux.md`).

### Requirement 9: Apresentação mobile dedicada

**User Story:** Como usuário no app, quero configurar a alçada e registrar pagamento numa interface que pareça nativa, não um site espremido.

#### Acceptance Criteria

1. WHILE o dispositivo é detectado como mobile por `useDevice()`, THE Alcada_Admin SHALL apresentar os papéis e responsáveis em lista de cards verticais com rolagem nativa, sem tabela e sem scroll horizontal na faixa de 360px a 414px.
2. WHILE o dispositivo é detectado como mobile por `useDevice()`, THE Pagamento_Form SHALL ser apresentado como bottom sheet (sobe do fundo), fechável por gesto de arrastar para baixo, com os campos em largura total e o botão de confirmar fixo na parte inferior.
3. WHEN o pagamento é concluído no mobile, THE sistema SHALL exibir feedback de sucesso e atualizar o status do título exibido sem recarregamento manual.
4. THE versão mobile SHALL oferecer as mesmas regras de elegibilidade e validação dos Requirements 4, 5 e 6.

### Requirement 10: Massa de teste local (DemoSeeder)

**User Story:** Como desenvolvedor, quero rodar o sistema localmente com alçada e títulos de exemplo, para validar a interface e os fluxos sem depender de dados reais.

#### Acceptance Criteria

1. THE DemoSeeder SHALL popular a Alcada_CP com pelo menos um usuário em cada papel (`pagador`, `conciliador`, `assinante`), garantindo que `bruno@bstechsolutions.com` esteja entre os pagadores (para os testes Dusk).
2. THE DemoSeeder SHALL garantir a existência de títulos no status `aprovado` (para serem pagos) e de títulos já `pago` (para validar a apresentação pós-pagamento).
3. THE DemoSeeder SHALL ser idempotente ou documentar a necessidade de `migrate:fresh`, conforme `regras.md`.

## Fora do escopo desta spec

- Conciliação bancária via extrato (Spec 2).
- 2ª assinatura / encerramento e a ordenação de substitutos do presidente (Spec 3).
- Cadeia de aprovação multi-nível da Fase 1 — gerência → diretoria → financeiro → 1ª assinatura (specs próprias).
- Baixa do título na Senior (`BaixarTitulosCP`) — spec futura; aqui o pagamento é registro **interno**.
- Estorno / desfazer pagamento — spec futura.
- Pagamento parcial, juros, desconto e multa.

## Decisões adotadas (para sua revisão)

Pontos que decidi para destravar a spec — me diga se concorda ou quer ajustar:

1. **Alçada manda, inclusive sobre o admin.** Registrar pagamento exige estar na alçada como `pagador`; nem a permissão curinga (`*`) fura isso (segregação de função). O `*` continua valendo para **gerenciar** a alçada.
2. **Três papéis já no cadastro** (pagador, conciliador, assinante), mas só o `pagador` é usado por uma ação nesta spec. Conciliador/assinante entram em uso nas Specs 2/3.
3. **Comprovante e forma de pagamento são opcionais**; data do pagamento default = hoje e não pode ser futura.
4. **Pagamento sempre total** do valor em aberto (sem parcial/juros/desconto nesta spec).
5. **Substitutos do presidente ficam para a Spec 3** (não cadastramos ordenação de suplentes agora).
