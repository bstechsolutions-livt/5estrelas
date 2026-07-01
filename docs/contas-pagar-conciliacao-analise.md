# Contas a Pagar + Conciliação — Análise do feedback do cliente

**Data:** 01/07/2026
**Origem:** documento "Informações na tela principal" enviado pelo cliente (5 Estrelas)
**Objetivo:** responder ponto a ponto, propor solução técnica e separar o que é **ajuste pra fazer** do que é **decisão/pergunta que precisa de resposta** antes de virar código.

> Leitura rápida: o documento mistura duas coisas. (1) Ajustes concretos e pequenos no Contas a Pagar — dá pra atacar já. (2) Um bloco grande de **conciliação bancária + integração Senior** que na verdade são perguntas de processo, precisam de decisão antes de codar. Este doc trata das duas e termina com um roteiro de specs.

---

## Legenda de status

- ✅ **Já existe** no sistema hoje
- 🔧 **Ajuste** — mudança concreta, escopo claro, dá pra fatiar em spec
- ❓ **Decisão** — pergunta do cliente / precisa alinhamento antes de codar
- 🧱 **Grande** — feature nova de peso, merece spec dedicada e desenho

---

## 1. Tela principal (listagem do Contas a Pagar)

**Pedido:** exibir Empresa (pode abreviar), Fornecedor (nome), Descrição/observação, Valor, Vencimento.

| Item | Status | Observação |
|------|--------|------------|
| Fornecedor (nome) | ✅ | `supplier_name` já na listagem |
| Descrição/observação | ✅ | `description` já na listagem |
| Valor | ✅ | `amount` já na listagem |
| Vencimento | ✅ | `due_date` já na listagem |
| **Empresa** (abreviada) | 🔧❓ | Hoje a listagem mostra **filial** (`branch`). O documento fala em **"Empresa"**. |

**❓ Decisão necessária:** "Empresa" = empresa do grupo (os `codEmp` da Senior: 5 Estrelas Segurança, Serv. Apoio Administrativo, etc. — as 12 empresas) ou a **filial**? Isso define o que exibir e como abreviar (ex.: sigla/fantasia). Proposta: exibir a **empresa do grupo** (com fantasia/sigla curta) e manter filial como informação secundária no detalhe.

---

## 2. Documentos obrigatórios para aprovar

**Pedido:** todo lançamento precisa ter documento (NF, boleto, relatório, comprovação). Nem todo tem tudo, mas **não pode ser aprovado sem nenhum**.

- Status: 🔧 (anexo já existe; a **trava** não).
- **Proposta:** bloquear o avanço do título (envio para aprovação e/ou a própria aprovação) enquanto não houver **pelo menos 1 documento** anexado. Mensagem clara de erro. Registrar em auditoria a tentativa bloqueada (opcional).
- Não vamos exigir "um de cada tipo" (ele mesmo diz que nem todo lançamento tem tudo) — só exigir **≥ 1 anexo**.

---

## 3. Histórico / quem criou (pergunta do cliente)

**Pergunta:** "histórico de lançamentos, quem criou e os demais já é padrão, correto?"

- **Resposta: sim.** ✅ Já temos: timeline de comentários/eventos por título, quem preparou (`prepared_by`), quem aprovou/pagou/conciliou, e **log de auditoria** completo (quem, o quê, quando, valores antes/depois). Pode confirmar pra ele sem ressalvas.

---

## 4. Integração Senior — travas, agrupamento e baixa

### 4.1 Trava de vencimento (+72h automático)
**Pedido:** lançou hoje (ex.: 29/06) → vencimento automático de 72h (ex.: 02/07). **Só o financeiro** pode alterar.

- Status: 🔧
- **Proposta:** ao criar o lançamento, calcular vencimento default = data do lançamento + 72h (definir se conta **dias corridos** ou **dias úteis** — ver decisão). Campo de vencimento **bloqueado para edição**, liberado só para quem tiver a permissão do financeiro (ex.: `financeiro.contas_pagar.editar_vencimento`).
- **❓ Decisão:** 72h = 3 dias corridos ou 3 dias úteis? (feriado/fim de semana empurra?)

### 4.2 Lançamento agrupado (vários títulos ERP → 1 lançamento no gestor)
**Pedido:** no ERP as notas entram individuais (ex.: cartão de crédito, fundo fixo); no gestor vira **um lançamento único com todos os documentos**.

- Status: 🧱 (é o item mais pesado do documento)
- **Proposta (a validar):** criar o conceito de "lançamento agrupado" — um título-guarda-chuva no gestor que referencia N títulos da Senior, consolidando valor e reunindo todos os anexos/documentos. A aprovação/pagamento/conciliação acontece no agrupado, mas mantemos a rastreabilidade item a item.
- Precisa de desenho próprio (modelo de dados + UX de agrupar). Não entra nos ajustes rápidos.

### 4.3 A baixa integra ou é dobrada? (pergunta do cliente)
**Pergunta:** "a integração também funcionará na hora das baixas? Ou teremos que fazer nos dois sistemas?"

- Status: ❓ (depende do que a Senior libera)
- **Situação real:** hoje estamos **read-only** com a Senior (só `Consultar*` validado; `Baixar*`/`Gravar*` nunca foram autorizados). Escrever baixa na Senior via API exige autorização formal e testes em homologação.
- **Resposta honesta pro cliente:** por ora a baixa é registrada **no gestor** (nosso sistema); a baixa no ERP Senior continua no processo atual até validarmos a escrita na API com eles. Quando liberarem, automatizamos a baixa bidirecional.

---

## 5. Alçada do Contas a Pagar

**Pedido:** incluir **Jéssica e Leyla como pagador**; incluir **todo o financeiro como conciliador** (acessos vêm depois).

- Status: ✅ (config, não código). A alçada já é gerenciável por tela (papéis pagador / conciliador / assinante).
- **Ação:** quando o cliente mandar os usuários, é só cadastrar na tela de Alçada. Sem desenvolvimento.

---

## 6. Visualizar anexo na própria página

**Pedido:** ao abrir um arquivo, **não abrir outra aba** — abrir na mesma tela, com botão de voltar.

- Status: 🔧 (front)
- **Proposta:** visualizador inline (preview em painel/modal na própria tela do título) para PDF e imagem, com botão "Voltar". Sem abrir nova aba do navegador.

---

## 7. Conciliação (bloco de perguntas + features novas)

> Aqui está a maior parte das **perguntas** do cliente. Já existe uma base de conciliação bancária começada no projeto (`BankConciliationController`, `BankStatementImport`, `BankTransaction`), então não partimos do zero.

### 7.1 Onde os arquivos ficam salvos / como acessar? (pergunta)
- Status: ❓ → **Proposta:** armazenamento em nuvem no **Backblaze B2** (S3, já é o padrão do projeto), com acesso controlado pelo próprio sistema (o usuário baixa/visualiza pelo gestor, com permissão — não é link solto de Drive). Hoje está em storage local; migrar os anexos financeiros pro B2.

### 7.2 Envio para contabilidade separado por conta + empresa
- Status: 🧱🔧 → **Proposta:** função de "gerar pacote de conciliação" por **conta bancária** e por **empresa**, com a identificação da empresa em cada pacote. Exporta um conjunto organizado (ZIP/PDF único) pronto pra mandar pra contabilidade.

### 7.3 Extrato mensal organizado (o Drive atual está bagunçado — ele pede sugestão)
- Status: 🧱 → **Proposta (nossa sugestão pra ele):** o próprio gestor monta o "dossiê" mensal por conta, **na ordem exigida** (ver 7.5), gerando um PDF consolidado + índice. Substitui o link do Google Drive por algo organizado, versionado e com acesso por permissão.

### 7.4 Itens sem NF/pagamento que precisam prestar contas
**Ex.:** transferência entre contas (despesa/receita), recebimento de notas de clientes, tarifas bancárias, etc.

- Status: 🧱 → Hoje a conciliação está presa ao **título de contas a pagar**. Isso exige o conceito de **conciliação bancária de verdade**, onde cada linha do extrato é conciliada (com título, ou como transferência, tarifa, receita etc.), inclusive itens que não geram pagamento com NF. A base já existe (`BankTransaction`) e precisa ser estendida.

### 7.5 Ordem fixa do dossiê de conciliação
**Ordem exigida:** extrato → relatório de conciliação (Senior) → receitas (NF + comprovantes) → transferências → pagamentos.

- Status: 🔧 (regra de montagem do pacote) → incorporar essa ordem na geração do dossiê (7.3).

### 7.6 Validação do Leonardo (ou substituto) de TODOS os extratos
**Pedido:** todos os extratos passam pelo Leonardo, **mesmo sem movimento/tarifa**. Precisam ser vistos e validados. Como subir os PDFs pra eles verem?

- Status: 🧱 → **Proposta:** passo de "validação de extrato por conta" — cada conta bancária, com ou sem movimento no período, entra numa fila de validação; o validador (Leonardo, com substituto configurável — reaproveita a ideia do papel "assinante" da alçada) vê o PDF do extrato no sistema e aprova. Só fecha o mês quando todas as contas foram validadas.

### 7.7 Conciliação nos dois sistemas ou integra? (pergunta)
- Status: ❓ → mesma resposta do 4.3: enquanto a escrita na Senior não estiver liberada, a conciliação vive **no gestor**; o relatório de conciliação da Senior entra como **anexo** no dossiê. Integração automática fica para quando a API de escrita for validada.

---

## Decisões travadas (Bruno, 01/07/2026)

1. **"Empresa" na tela principal** — regra geral do sistema: **NUNCA exibir código, sempre nome.** Onde hoje apareceria um código Senior (empresa/fornecedor/etc.), mostrar o **nome**. Na tela principal, exibir o nome da empresa (abreviado quando couber). Mapeamento codEmp → nome já documentado no steering `integracao-senior` (as 12 empresas).
2. **Trava de vencimento** — decidido pela BStech: **+72h corridas (3 dias) a partir do lançamento; se cair em fim de semana, empurra para o próximo dia útil.** (Feriados: opcional/futuro, via calendário configurável.) Bate com o exemplo do cliente (29/06 → 02/07). Edição só pelo financeiro.
3. **Baixa e conciliação integradas com a Senior** — **fica pra depois** (Senior segue read-only por ora). Precisamos fazer, mas não é agora. Confirmado.
4. **Usuários de pagador/conciliador** — **parametrizado** (tela de Alçada). Não importa agora; cadastra quando tiver os acessos.
5. **Envio pra contabilidade** — o cliente **não definiu formato**. Nós **propomos** um modelo (dossiê organizado por conta/empresa) e ele pede ajuste se quiser. Ou seja: liberdade de design nossa.

---

## Roteiro proposto de specs (fatiado, do mais fácil pro mais pesado)

Seguindo a regra do projeto (specs minúsculas, cada uma testável), sugiro esta ordem. Cada spec entra com testes (Feature + Dusk) e passa antes de deploy.

**Bloco A — ajustes rápidos no Contas a Pagar (✅ CONCLUÍDO 01/07/2026, com testes verdes):**
- **Spec A1:** ✅ documento obrigatório para aprovar (trava ≥ 1 anexo no envio e na aprovação). — item 2
- **Spec A2:** ✅ visualizador de anexo inline (Dialog na própria tela, sem nova aba, com "Voltar"). — item 6
- **Spec A3:** ✅ coluna "Empresa" por NOME (nunca código) na listagem (desktop + mobile) e no detalhe; nome resolvido de `bs_comercial_filiais` pelo codEmp. Também adicionada coluna Descrição. — item 1
- **Spec A4:** ✅ vencimento automático +72h (3 dias corridos, rola p/ dia útil) em lançamento manual sem vencimento + edição de vencimento restrita ao financeiro (permissão `financeiro.contas_pagar.editar_vencimento`). — item 4.1

> Cobertura Bloco A: suíte Feature completa 371 passed / 0 falhas; Dusk de contas a pagar todos verdes. Decisões 1 e 2 aplicadas conforme travado acima.

**Bloco B — conciliação (precisa das decisões acima):**
- **Spec B1:** migrar anexos financeiros pro Backblaze B2 + acesso por permissão. — item 7.1
- **Spec B2:** conciliação bancária estendida (linhas de extrato: título / transferência / tarifa / receita). — item 7.4
- **Spec B3:** validação de extrato por conta (fila do Leonardo + substituto), inclusive contas sem movimento. — item 7.6
- **Spec B4:** dossiê mensal de conciliação (ordem fixa 7.5) + envio pra contabilidade por conta/empresa. — itens 7.2, 7.3, 7.5

**Bloco C — pesado (spec dedicada, desenho antes):**
- **Spec C1:** lançamento agrupado (N títulos Senior → 1 lançamento com todos os documentos). — item 4.2

---

*BS Tech Solutions — 01/07/2026. Documento para validação interna com o Bruno antes de responder o cliente.*
