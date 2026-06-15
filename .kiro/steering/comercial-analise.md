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


---

## REGRA OBRIGATÓRIA (Bruno) — Fidelidade total ao protótipo

**NADA do HTML `gestao360_5estrelas` pode ficar pra trás.** Cada tela/campo/botão/funcionalidade
do protótipo DEVE ser portado. Não simplificar, não "cobrir ~40%". Antes de considerar qualquer
spec do Comercial concluída, fazer conferência campo-a-campo contra a view correspondente do HTML
e listar o que falta. Testar back (unit) + front (Dusk).

### Status de fidelidade por tela (atualizar sempre) — atualizado 12/06/2026

- **Config/Valores: 100% ✅ e em produção.** Tela portada 1:1 do HTML (CSS `.g360`), abas Convenções Coletivas / Taxas / Insumos, cards CCT por estado (20 CCTs), painel de detalhe editável, Encargos A/B/C/D, Taxas (adm/lucro/tributos), Insumos (12). Cores em white-label (`--app-primary`). 4 Dusk + dados semeados.
- **Cotação: motor 100% ✅ / tela 100% ✅ (portada 1:1).** Motor IN05 + 5E verificado ao centavo (7 unit). Tela **reportada no padrão `.g360`** (igual à Valores), fiel à `view-cotacao`: cabeçalho + botões, Identificação da Proposta, Configurar Posto (categorias/escalas do backend, banner, preview), detalhamento colapsável dos dois modelos (5 Estrelas com turnos Diurno/Noturno + IN 05 com Módulos 1–6 e quadro-resumo), Total Geral por modelo, coluna direita Resumo dos Postos + mini-KPIs. Recálculo reativo (debounce) chama o backend (`/calcular` e `/calcular-5e`); o número que vira posto usa o total do backend. Cores white-label. 3 Dusk (cotação) + 2 (Valores) passando. **Stubs (visual 1:1, handler "Em breve")**: Salvar Proposta, Gerar PDF, Importar/Exportar XLSX e "Adicionar item ao Módulo 02" — dependem da Spec 3 (Propostas), ainda não feita.
- **Demais views** (Dashboard, Propostas, Clientes, Faturamento, Saúde, Reajuste): ❌ não iniciadas.

---

## PONTO DE RETOMADA (handoff) — ler ao voltar pra esta demanda

### Como o módulo está montado
- **Tabelas**: `bs_comercial_ccts` (20, uf×serviço, com valores), `_categorias` (5), `_escalas` (5, com qtdD/qtdN/func/AN/jornada), `_indices` (adm/lucro/iss/pis/cofins/encargos), `_encargos` (20, grupos A/B/C/D), `_insumos` (12).
- **Models**: `App\Models\Comercial\{Cct,Categoria,Escala,Indice,Encargo,Insumo}`.
- **Controllers**: `App\Http\Controllers\Comercial\ComercialConfigController` (Valores) e `ComercialCotacaoController` (Cotação).
- **Motor de cálculo**: `App\Services\Comercial\ComposicaoCustoService` — `calcularIN05()` e `calcular5Estrelas()`. **Fonte da verdade. Verificado ao centavo contra o protótipo** (7 testes em `tests/Unit/ComposicaoCustoServiceTest.php`). Fórmulas literais em `comercial-calculo-in05.md`.
- **Rotas**: prefixo `/comercial/*` (web.php), middleware `permission:comercial.visualizar`.
- **Permissões**: `comercial.visualizar/cotar/aprovar/configurar` (PermissionsSeeder).
- **Menu**: grupo "Comercial" (Nova Cotação, Valores) no `MenuCatalog`.
- **Seeder**: `ComercialConfigSeeder` (idempotente, valores exatos do protótipo). Rodar: `php artisan db:seed --class=ComercialConfigSeeder --force`.
- **Telas**: `resources/js/Pages/Comercial/Configuracoes/Index.vue` (Valores, portada 1:1) e `Cotacao/Index.vue` (a refazer no padrão g360).

### REGRAS/APRENDIZADOS (valem pra todo o módulo Comercial)
1. **Fidelidade total**: portar HTML+CSS do protótipo 1:1, mudando só a casca (nosso AppLayout/menu). NÃO recriar com PrimeVue (fica diferente — a Valores teve que ser refeita por isso).
2. **CSS do protótipo**: já extraído e escopado sob `.g360` em `resources/css/comercial-g360.css` (importar com `import "@/../css/comercial-g360.css"`). Envolver o conteúdo em `<div class="g360"><div class="view active">...`. **Reutilizável pra todas as views.**
3. **Cores white-label**: dentro do `.g360`, `--brand-gold` aponta pra `var(--app-primary)`; tons rgba viram `color-mix(... var(--app-primary) ...)`. Manter cores semânticas (verde/azul/vermelho). NÃO usar o dourado fixo do protótipo.
4. **Cálculo sempre no backend** (ComposicaoCustoService), nunca só no front — o número é auditável.
5. **Constantes do cálculo** (13º 0.0833, multa FGTS 40%, desc VT 6%, AN 20%) hoje fixas no Service; decisão: podem virar parâmetros no banco se o cliente pedir.
6. **Fonte do protótipo**: `~/Downloads/gestao360_5estrelas (2).html` (restaurado da lixeira). Linhas-chave: CSS 15–1106; ESCALAS 4470–4578; calcIN 5891–6147; calcular/M2/M3 5667–5785; view-cct (Valores) 3568–4073; view-cotacao 1650–2383.

### TESTES (back + front)
- **Unit**: `php artisan test --filter=ComposicaoCustoServiceTest` (7 testes, motor ao centavo).
- **Browser (Dusk)**: instalado. Rodar: subir `php artisan serve --port=8778` e `php artisan dusk --filter=ComercialCotacaoTest`. Config em `.env.dusk.local` (NÃO commitado, tem segredos; já no .gitignore). 
  - **Gotcha Dusk**: `assertSee/getText` aplica `text-transform` — textos com classe `uppercase` (ex.: `.module-title`) aparecem MAIÚSCULOS pro Selenium. Asserções devem casar com o texto exibido (ex.: assertSee('ENCARGOS SOCIAIS')).
  - **ChromeDriver**: tem que casar com a versão do Chrome. Se atualizar: `php artisan dusk:chrome-driver --detect`.
- **Toda tela nova do Comercial**: criar teste Dusk no padrão de `tests/Browser/ComercialCotacaoTest.php`.

### PRÓXIMOS PASSOS (ordem)
1. ~~Reportar a Cotação no padrão g360~~ ✅ FEITO (tela 1:1, 5E + IN05, recálculo via backend).
2. Spec 3 — Propostas: ligar os stubs da Cotação (Salvar Proposta, Gerar PDF, Importar/Exportar XLSX, itens extras do Módulo 02) ao backend real.
3. Demais views: Propostas (funil), Clientes, Faturamento, Saúde Contratual, Reajuste, Dashboard.
4. Adicionar Comercial no SearchController (Ctrl+K) quando houver entidades pesquisáveis (propostas/clientes).
