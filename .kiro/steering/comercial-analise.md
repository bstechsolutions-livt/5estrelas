---
inclusion: manual
---

# Análise — Módulo Comercial ("Gestão 360º")

Fonte: protótipo `~/Downloads/gestao360_5estrelas (2).html` (build 20260513_181659).
Protótipo 100% client-side (HTML/JS puro + Tailwind-like CSS inline + lib `xlsx` para import/export Excel). Persistência só em `localStorage` (`gestao360_v2`). Não há backend.

## O que é o módulo

Ferramenta **comercial** para empresa de **mão de obra terceirizada / vigilância e segurança**. O coração é a **montagem de planilha de custos no modelo IN 05** (Instrução Normativa de terceirização do serviço público) para **precificar postos de trabalho** e gerar **propostas**, virar **contrato**, faturar e reajustar.

## Telas (views) — 8

| view (id) | Nome | Função |
|-----------|------|--------|
| `view-dashboard` | Dashboard | KPIs, maiores contratos, funil de propostas, distribuição por estado |
| `view-cotacao` | Nova Cotação de Custos | **núcleo** — planilha IN 05 por posto (Módulos 1 a 6) |
| `view-saude` | Saúde Contratual | composição financeira real x planejado, evolução mensal, metas, índices |
| `view-clientes` / `view-cliente-detalhe` | Contratos / Detalhe | clientes, contratos, postos/escalas ativas, propostas vinculadas |
| `view-faturamento` | Faturamento | controle de entradas de proposta/faturamento por ano/mês |
| `view-propostas` | Controle de Propostas | funil/CRUD de propostas (Nº, cliente, valor, envio, status) |
| `view-reajuste` / `-detalhe` | Reajuste de Contratos | reajuste por índice/CCT, itens, histórico de status |
| `view-cct` | Valores (CCT) | cadastro de CCT, encargos, taxas, insumos, categorias |

## Núcleo: planilha de custos (IN 05)

Composição do **custo por empregado/posto**, organizada em módulos (padrão da planilha de terceirização):

- **Módulo 1 — Composição da Remuneração**: salário base, adicional de periculosidade, insalubridade, adicional noturno, intrajornada.
- **Módulo 2 — Encargos e Benefícios** (anuais/mensais/diários):
  - 2.1 — 13º, férias e adicional de férias
  - 2.2 — GPS/INSS, FGTS e outras contribuições (encargos sociais %)
  - 2.3 — Benefícios mensais e diários (VA, VT, plano de saúde, seguro de vida, SSO, fundo social/odonto, uniforme/materiais)
- **Módulo 3 — Provisão para Rescisão**
- **Módulo 4 — Custo de Reposição do Profissional Ausente** (4.1 ausências legais, 4.2 intrajornada)
- **Módulo 5 — Insumos Diversos** (uniforme, armamento, colete, cofre, reciclagem, etc.)
- **Módulo 6 — Custos Indiretos, Tributos e Lucro**: administração (%), lucro (%), tributos (ISS, PIS, COFINS), inadimplência.
- **Quadro-Resumo do Custo por Empregado** → valor do posto → x nº colaboradores → valor mensal do contrato.

Parâmetros que alimentam o cálculo:
- **CCT vigente** (convenção coletiva): ano/dissídio, data-base, sindicato, salário base por categoria.
- **Categoria profissional** (cargo/CBO) com periculosidade, intrajornada, desconto VT.
- **Escala** (ex.: 12x36, 24h) → dias/mês e horas/mês.
- **Estado praticado / município-UF** (varia CCT e tributos).
- **Índices** globais editáveis: encargos %, administração %, lucro %, impostos %.

## Entidades de dados (a modelar no banco)

1. **Cliente** — nome, contato principal/envio, cidade/UF, situação; agrega valor mensal, colaboradores, postos, propostas.
2. **Proposta** — Nº, cliente, responsável, data proposta/envio/aprovação, valor, valor aprovado, status (funil), revisão, modelo de planilha, observações.
3. **Cotação / Planilha de custos** — vinculada à proposta; cabeçalho (empresa/CNPJ, cliente, competência, estado) + postos.
4. **Posto** — tipo/descrição, categoria, escala, nº colaboradores, e toda a composição IN 05 (módulos 1–6).
5. **Contrato** — gerado da proposta aprovada; valor mensal, início, meses de execução, nº postos, postos/escalas ativas, próximo reajuste, tipo de índice. **⚠️ já existe módulo Contratos no sistema (gestao-contratos) — precisa reconciliar/integrar, não duplicar.**
6. **Faturamento** — entradas por ano/mês, valor mensal contratado, faturamento real, inadimplência.
7. **Saúde Contratual** — real x planejado: folha, benefícios, uniforme/materiais; metas (margem alvo/mínima, % máx folha/faturamento); evolução mensal.
8. **Reajuste** — cliente/contrato, tipo de índice, config, itens para reajuste, resumo, histórico de status.
9. **CCT** — nome/vigência, valores por categoria, encargos, taxas, insumos (com duplicar/importar planilha).
10. **Categoria profissional** — nome, CBO, periculosidade, intrajornada, desconto VT, valores base.

## Funcionalidades relevantes (≈ funções JS já mapeadas)

- Import/export **Excel** (`xlsx`) de planilha de cotação e CCT (`importAnalisarPlanilha`, `exportarExcel`).
- **Modo admin** com senha (gate para editar CCT/índices) — `ativarModoAdmin`, `confirmarAdmin`.
- Funil de propostas com badges/contadores e KPIs do dashboard.
- Duplicar CCT, aplicar CCT/encargos/insumos na cotação, recálculo reativo (`calcIN`, `calcular`, `atualizarKPIs`).
- Reajuste: criar para cliente, alterar status, calcular, histórico.
- Vincular/desvincular proposta a cliente; gerar contrato; postos ativos.

## Plano de portabilidade (Laravel 12 + Vue 3 + Inertia + PrimeVue)

1. **Reconciliar com o módulo Contratos existente** (`gestao-contratos`). Decidir:
   - Comercial é a "esteira" (cotação → proposta → aprovação) que **alimenta** o contrato existente; ou
   - Vira submódulo dentro de Contratos. Recomendado: módulo próprio **Comercial** que, ao aprovar proposta, **cria o contrato** no módulo de Contratos (reuso de `bs_gestao_contratos`).
2. **Tabelas** (prefixo sugerido `bs_comercial_`): `clientes` (avaliar reuso de `branches`/cliente existente), `propostas`, `cotacoes`, `cotacao_postos`, `ccts`, `cct_categorias`, `cct_valores`, `indices`, `faturamentos`, `reajustes`, `reajuste_itens`, `reajuste_historico`, `saude_contratual` (ou cálculo on-the-fly).
3. **Motor de cálculo IN 05 no backend** (Service `ComposicaoCustoService`) — não confiar só no front; o cálculo é fonte de verdade para proposta/contrato.
4. **Telas Vue** espelhando as 8 views; PrimeVue DataTable para propostas/clientes/faturamento; formulário multi-módulo para a cotação (stepper/accordion).
5. **Import/export Excel**: manter `xlsx` (já usado no projeto) ou `exceljs`/`maatwebsite` no backend.
6. **Permissões**: `comercial.visualizar`, `comercial.cotar`, `comercial.aprovar`, `comercial.configurar` (CCT/índices = só admin).
7. **Menu**: novo grupo "Comercial" (Dashboard, Cotação, Propostas, Clientes, Faturamento, Reajustes, CCT/Valores) — respeitar ordem alfabética dos grupos.
8. **Ctrl+K (SearchController)**: indexar propostas, clientes/contratos, CCTs.
9. **DemoSeeder**: massa realista (clientes, propostas em vários status do funil, CCTs por estado, contratos, faturamento mensal).
10. **Mobile**: versão dedicada (listas viram cards; planilha de cotação é desktop-first, mobile só consulta/aprovação).

## Pontos a confirmar com o Bruno (antes de implementar)

- **Cliente** do comercial = a mesma entidade dos contratos? (hoje contratos usa filial/branch; comercial parece ter "cliente" próprio — empresa contratante).
- A planilha IN 05 do protótipo é a **versão final** dos módulos/fórmulas? Há regras de CCT por estado a importar?
- O **contrato** gerado pelo comercial deve usar o `bs_gestao_contratos` existente ou tabela nova?
- Faturamento aqui se conecta com o **Financeiro/Contas a Receber** (que está no escopo do projeto) ou é só controle comercial?
- Modo admin/senha → migrar para o sistema de **permissões** (sem senha separada).

## Observações

- Protótipo é a melhor referência visual/fluxo; o cálculo IN 05 já está implementado em JS e pode ser portado quase 1:1 para um Service PHP (testável).
- É um módulo **grande**. Quebrar em specs pequenas: (1) CCT/Valores + Categorias, (2) Cotação/planilha IN 05, (3) Propostas/funil, (4) Clientes/Contratos + vínculo, (5) Faturamento, (6) Saúde Contratual, (7) Reajustes, (8) Dashboard.
