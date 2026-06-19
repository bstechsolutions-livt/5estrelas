---
inclusion: manual
---

# Fluxo de Aprovação e Pagamento — Compras / Contas a Pagar (ÉPICO)

> **Mapa de navegação deste épico (multi-spec).** Decisões de negócio travadas, estado do código,
> roadmap de specs e pendências. **Puxar este steering (#) no início de CADA spec do módulo** pra não perder o fio.
> Detalhe visual / passo-a-passo do fluxo: `docs/fluxo-aprovacao-compras-financeiro.pdf`.

---

## 1. Visão geral

O cliente (5 Estrelas) pediu um fluxo de **aprovação de documentos + pagamento** para o "sistema de Compras". Um documento que gera pagamento (compra, contrato, nota) percorre **duas fases**:

- **Fase 1 — Aprovação:** sobe a cadeia `Departamento → Gerência/Head → Diretoria → Financeiro → Presidência`. A 1ª assinatura do presidente **libera o pagamento**.
- **Fase 2 — Pagamento:** `Pagamento (Karen) → Conciliação → Presidência (2ª assinatura, encerra)`.

> **Referência histórica:** na intranet Biglar isso existia via "Central de Aprovações" (agregava Contas a Pagar + Compras + Solicitações). No 5E o **módulo de Compras NÃO foi portado** (ver `solicitacoes-analise.md` e `contratos.md`). Vamos **construir no mundo novo**.

---

## 2. Regras de ouro do fluxo (consolidadas — fonte: PDF)

- **Dupla aprovação sempre:** responsável da área (o diretor; ou o head, onde não há diretoria) **+** o presidente Leonardo Prudente. Sem as duas, não anda.
- **O presidente assina 2x:** 1ª libera o pagamento (fim da Fase 1), 2ª valida a conciliação depois de pago (fim da Fase 2).
- **Substituição do presidente:** 1º Ana Paula, 2º Luiz Farias. O substituto **nunca** pode ser quem já assinou aquele documento como diretor.
- **Trilhas (Fase 1):** Dionei (Matriz/Filiais/Compras/Modernização), Ana Paula (Comercial/Faturamento/Marketing), Luiz Farias (Licitação). **Sem diretoria:** DP-RH (Silene) e Jurídico (Dra. Alexyxandra) vão direto ao Financeiro. **Especiais:** Multi/Star (pré-aprovação Luiz Farias → entra no fluxo do Compras), Baluarte (caminho duplo: Matriz + Comercial).

---

## 3. Estado atual do código (ponto de partida)

| Peça | O que é | Implicação |
|---|---|---|
| **`Payable` + `PayableController`** (Contas a Pagar) | Mundo **novo** (User/Branch, integra Senior). Status linear: `pendente → em_preparacao → aguardando_aprovacao → aprovado/reprovado → pago`. | Aprovação de **1 nível só** (um `approved_by`). `pago` é **só rótulo** — nenhuma ação faz a transição. **Não tem** conciliação nem 2ª assinatura. |
| **`WorkflowService` + `Solicitacao`** | Motor de workflow robusto (etapas, decisões, transferência entre depts, histórico, Reverb). | **Acoplado ao legado** (Funcionario/matrícula, Filial, `intranet_solicitacao`). |

> **Decisão de arquitetura:** **NÃO** reaproveitar o `WorkflowService` para o Contas a Pagar — generalizar o motor legado (ou acoplar o Payable ao mundo `Funcionario`/`Filial`) é mais risco/retrabalho do que ajuda, e mexe num sistema que já roda em produção (chamados). Construímos a cadeia **no mundo novo, em cima do `Payable`**.
>
> **Abordagem:** **incremental — nada é apagado.** Aproveita o que já existe no Payable (model, controller, status, approve/reject, anexos, comentários, auditoria, integração Senior, telas) e **adiciona** o que falta. O `approve` atual vira um passo dentro da cadeia maior; não é substituído.

---

## 4. Decisões de negócio travadas

1. **Alçada própria e configurável** (não permissão fixa, não "responsável do departamento"): cadastro do Contas a Pagar que mapeia **papel → pessoa(s)**, editável em tempo real (trocar quem aprova sem deploy). É a **fonte de verdade** de quem faz o quê. Papéis da Fase 2: `pagador`, `conciliador`, `assinante` (presidente) + substitutos. Na Fase 1 esse mesmo cadastro **cresce** com os níveis gerência/diretoria por trilha.
2. **Pagamento:** registra **data** (default hoje), **comprovante** (anexo opcional) e **forma** (opcional). v1 só pagamento **total** — parcial/juros/desconto fora.
3. **Conciliação:** bancária **AUTOMÁTICA via extrato** (matching lançamento × título). **Não** é manual. É **spec própria** (peça grande). _Pendência:_ origem do extrato (OFX/CNAB upload vs API/Open Finance).
4. **2ª assinatura:** o presidente valida a conciliação e **encerra**. A regra de substituição (Ana Paula → Luiz Farias) **vale aqui também**.
5. **Senior:** na v1, marcar `pago`/`conciliado` é **só registro interno**. A baixa real (`BaixarTitulosCP`) é **spec futura** — escrita na Senior ainda bloqueada por whitelist de IP (ver `integracao-senior.md`).
6. **Estorno / desfazer pagamento:** **fora da v1** (spec futura).

---

## 5. Roadmap de specs (ordem)

1. **➡️ Alçada + Registrar pagamento** — cadastro de alçada (tela de config) **+** ação `aprovado → pago` (data, comprovante, forma; total). Consome o papel `pagador`. _[PRÓXIMA]_
2. **Conciliação bancária automática via extrato** — `pago → conciliado`. (definir origem do extrato)
3. **2ª assinatura / encerramento** — `conciliado → encerrado`. Consome `assinante` + substitutos.
4. **Cadeia de aprovação (Fase 1)** — multi-nível, dupla aprovação, substituição, roteamento por trilhas, casos especiais. Provavelmente **2-3 specs**. Expande o cadastro de alçada.
5. **Baixa na Senior** — ao pagar, baixar o título via `BaixarTitulosCP` (depende da whitelist liberada).

---

## 6. Decisões em aberto (resolver na spec indicada)

- **Onde nasce o documento de compra?** No próprio `Payable`, ou num módulo de Compras/Solicitação que **gera** o título ao ser aprovado? → spec da **Fase 1**.
- **Relação com a Senior:** a aprovação roda sobre títulos **já sincronizados**, ou sobre um **documento interno** que vira título depois? → spec da **Fase 1**.
- **Origem do extrato bancário** (OFX/CNAB upload vs API/Open Finance). → **spec 2**.

---

## 7. Convenções deste módulo (além das regras globais)

- Ações do fluxo são governadas pela **alçada configurável**, não por permissão fixa. Permissão só para: **acessar** o módulo e **gerenciar** a alçada.
- Toda transição de status registra **auditoria** (`audit_logs`) — ver `auditoria.md`. Eventos: `contas_pagar.pago`, `.conciliado`, `.assinado`, etc.
- **Testes 100%, inegociável** (ver `testes.md`): cobertura total dos **dois lados**.
  - **Backend** — cada rota/endpoint do controller coberta por **feature test** (PHPUnit), incluindo caminho feliz (confirma no banco), **validação (422)** e **permissão/alçada negada (403)**.
  - **Frontend** — cada tela e **cada ação/botão** coberta por **Dusk** (browser real, clicando de verdade: configurar alçada, pagar, conciliar, assinar, filtrar, abrir modal/bottom sheet...).
  - Nenhuma tela/feature é "concluída" sem teste **verde**. Rodar `php artisan test --filter=X` + `php artisan dusk --filter=X` + suíte inteira. **Deploy só com a suíte inteira verde.**
- **Mobile dedicado** por tela — ver `mobile-ux.md`. `<Toast />` no template das telas com toast.
- **DemoSeeder**: alçada configurada + títulos em cada status + comprovantes + extrato de exemplo.
- **Ctrl+K**: registrar novas entidades no `SearchController`.
- Toda transição **valida o estado de origem** (como já fazem `approve`/`reject` hoje).
