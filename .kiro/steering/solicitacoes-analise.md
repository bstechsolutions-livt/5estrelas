---
inclusion: manual
---

# Análise Técnica — Módulo de SOLICITAÇÕES (Biglar → 5 Estrelas)

> Documento de **planejamento de port**. Não contém código de implementação.
> Origem: `Hub/clientes/biglar/sistemas/ct-intranet/app` (Laravel 11 + Inertia + Vue 3 + PrimeVue 4, Oracle).
> Destino: `5estrelas/app` (Laravel 12 + Inertia + Vue 3 + PrimeVue 4, PostgreSQL).
> Estratégia de referência já validada: `.kiro/steering/contratos.md` (shims de layout, `Filial`→`Branch`, `session('auth')->matricula`→`auth()->id()`, SQL Oracle→PG).

---

## 1. Propósito do módulo

"Solicitação" na Biglar é uma **central genérica de tickets/demandas internas com workflow configurável**. Um colaborador abre uma solicitação escolhendo um **Assunto** (categoria), que pertence a um **Departamento responsável**. A solicitação entra num ciclo de atendimento (pendente → em atendimento → resolvida/finalizada) e pode percorrer um **fluxo de etapas entre departamentos** (workflow) com **decisões** (aprovar/reprovar/devolver), **campos dinâmicos** por etapa e **aprovações por alçada**.

É, na prática, o **"motor de workflow" genérico** citado no cronograma do 5 Estrelas (Módulo 2 — "Core operacional e motor de workflows"). Cobre o ciclo do escopo: **Solicitação → Classificação → Aprovação → Execução → Registro → Dashboard**.

### Tipos de solicitação
Não há "tipos" rígidos no schema — a tipagem vem do **Assunto** e de **payloads especializados** opcionais ligados a certos assuntos:
- **Genérica**: título, descrição, anexos, campos dinâmicos do assunto.
- **Acesso a sistema / liberações**: rotinas Winthor (`c_rot`) e acessos (`c_acessos`) — *fortemente acoplado ao ERP*.
- **Equipamentos / Destinos / Vendas**: (`c_equip`, `c_dest`, `c_vendas`) — payloads de negócio Biglar.

### Conexão com o módulo de Compras
**Existe um módulo de Compras SEPARADO e paralelo** (`ComprasController` 9211 linhas, telas `Compras/Solicitacoes`, `Compras/Solicitacao`, `Compras/DashboardSolicitante`) que tem **suas próprias** "solicitações de compra" (tabela `intranet_compras_solicitacao`). É **outro sistema**, acoplado a Winthor/Oracle, com cotação, contratos recorrentes, bloqueios automáticos etc. A `CentralAprovacoes` é um **agregador transversal** que junta abas de: Contas a Pagar, Compras (autorização/alteração) e Solicitações.

> **Decisão de escopo:** o módulo a portar é o **genérico de Solicitações** (`SolicitacoesController`). O cluster **Compras** (e suas abas na Central de Aprovações) **não deve ser portado agora** — não temos módulo de Compras nem ERP Winthor, exatamente como já decidido em `contratos.md`.

---

## 2. Modelo de dados

### 2.1 Núcleo (mínimo viável)
| Tabela | Model | Papel |
|---|---|---|
| `intranet_solicitacao` | `Solicitacao` | Entidade central: título, descrição, status, prioridade, solicitante, responsável, departamento, filial, assunto, previsão de entrega, `solicitacao_pai_id`, `hash_duplicata`, `data_conclusao`. |
| `intranet_solicitacao_assuntos` | `SolicitacaoAssunto` | Categoria/assunto. Liga a departamento + responsável padrão, prioridade, qtd mín. de anexos, instruções e regras de **redirecionamento**. É o "tipo" da solicitação. |
| `intranet_solicitacao_mov` | `SolicitacaoMov` | Linha do tempo de movimentações (auditoria do ticket). |
| `intranet_solicitacao_com` | `SolicitacaoCom` | Comentários (com `deleted_at`, CLOB no Oracle, comentário privado, "isSistema"). |
| `intranet_solicitacao_com_arq` | `SolicitacaoComArq` | Anexos de comentários. |
| `intranet_solicitacao_arq` | `SolicitacaoArq` | Anexos da solicitação (liga a `File`). |

### 2.2 Campos dinâmicos & seleções (configuração por assunto)
| Tabela | Model | Papel |
|---|---|---|
| `intranet_solicitacao_campos` | `SolicitacaoCampos` | Campos extras do assunto (`texto`/`selecao`), com `opcoes_titulo` (JSON). |
| `intranet_solicitacao_sel` | `SolicitacaoSelecao` | Campos de **seleção** configuráveis: tipo, múltiplo, condicional (`campo_pai_id`/`valor_condicional`), exibir na criação/atendimento, datas, `dias_minimos`. |
| `intranet_solicitacao_s_itens` | `SolicitacaoSelecaoItem` | Itens/opções de um select. |
| `intranet_solicitacao_s_resp` | `SolicitacaoSelecaoResposta` | Respostas preenchidas (com `valor_winthor` — acoplamento ERP). |

### 2.3 MOTOR DE WORKFLOW / FLUXO (o coração do módulo) ⭐
Adicionado depois (tem migrations Laravel limpas, **PostgreSQL-compatível**). Modelagem madura e bem documentada nos próprios models:

| Tabela | Model | Papel |
|---|---|---|
| `intranet_solicitacao_fluxos` | `SolicitacaoFluxo` | Definição do workflow vinculado a um **assunto**. Versionável (`versao`), `ativo` S/N. Helpers: `primeiraEtapa()`, `temExecucoesAtivas()`. |
| `intranet_solicitacao_fluxo_etapas` | `SolicitacaoFluxoEtapa` | Cada **etapa** pertence a um **departamento**. Campos: responsável padrão, prazo (horas), instruções, cor/ícone, ordem, tipo (Início/Etapa/Fim), `permitir_solicitante_avancar` (`N`/`S`/`E`=modo exclusivo), `manter_responsavel`, `exibir_campos_assunto`. |
| `intranet_solicitacao_fluxo_decisoes` | `SolicitacaoFluxoDecisao` | **Decisões** numa etapa (ex.: "Aprovado"/"Reprovado"). Cada uma roteia para `etapa_destino_id` e tem `acao`: `avancar`, `finalizar`, `resolver`, `voltar_solicitante`, `abrir_solicitacao` (cria solicitação filha). |
| `intranet_solicitacao_fluxo_execucao` | `SolicitacaoFluxoExecucao` | **Estado atual** da solicitação no fluxo (1 por solicitação): etapa atual, status (`em_andamento`/`aguardando_decisao`/`aguardando_solicitante`/`concluido`/`cancelado`), prazo, `solicitacao_pai_id`. |
| `intranet_solicitacao_fluxo_historico` | `SolicitacaoFluxoHistorico` | Histórico de transições do fluxo. |
| `intranet_sol_fluxo_etapa_campos` | `SolicitacaoFluxoEtapaCampo` | **Campos dinâmicos por etapa** (tipos: texto, textarea, numero, data, selecao, checkbox, arquivo). Pode estar ligado a uma decisão. Tem ~16 **campos predefinidos** (templates reutilizáveis: justificativa, valor_estimado, centro_custo, parecer, comprovante, etc.). |
| `intranet_sol_fluxo_campo_valores` | `SolicitacaoFluxoEtapaCampoValor` | Valores preenchidos dos campos da etapa por execução. |
| `intranet_sol_fluxo_etapa_responsaveis` | `SolicitacaoFluxoEtapaResponsavel` | Responsáveis permitidos por etapa. |

### 2.4 Etapas "legado" (modelo antigo, anterior ao fluxo)
| Tabela | Model | Papel |
|---|---|---|
| `intranet_solicitacao_etapas` | `SolicitacaoEtapa` | Etapas simples por assunto (kanban-like), anterior ao motor de fluxo. |
| `intranet_solicitacao_etapa_atual` | `SolicitacaoEtapaAtual` | Etapa atual (modelo antigo). |
| `intranet_solicitacao_etapa_historico` | `SolicitacaoEtapaHistorico` | Histórico (modelo antigo). |

> Há **dois mecanismos sobrepostos**: o "etapas" simples (antigo) e o "fluxo/execução" (novo motor). **Portar só o motor de fluxo** e descartar o legado de etapas, salvo se telas dependerem dele (verificar na fase de implementação).

### 2.5 Aprovações (alçada)
| Tabela | Model | Papel |
|---|---|---|
| `intranet_solicitacao_aprovacoes` | `SolicitacaoAprovacao` | Pedidos de aprovação dentro de uma solicitação: solicitante, aprovador, status (pendente/aprovada/rejeitada), observações, respondido_por/em. Scopes por matrícula; helpers `podeAprovar/podeEditar/podeCancelar`. **Chaveado por matrícula.** |

### 2.6 Acessórios / agendamento / responsáveis
| Tabela | Model | Papel |
|---|---|---|
| `intranet_solicitacao_agend` + `intranet_solicitacao_ag_sol` | `SolicitacaoAgendamento` / `SolicitacaoAgendSol` | Agendamentos/lembretes vinculados (pivot). Tem tela de agenda própria. |
| `solicitacao_assunto_responsaveis` | `SolicitacaoAssuntoResponsavel` | Responsáveis (matrículas) por assunto. |
| `solicitacao_assunto_modelos` | `SolicitacaoAssuntoModelo` | Arquivos-modelo anexáveis ao assunto. |
| `solicitacao_assunto_liberacoes` | `SolicitacaoAssuntoLiberacao` | Liberações configuráveis por assunto. |
| `intranet_solicitacao_equip` | `SolicitacaoEquipamentos` | Equipamentos cadastrados. |

### 2.7 Payloads especializados (acoplados a negócio Biglar / Winthor) ⚠️
`SolicitacaoCRot` (`c_rot` — rotinas Winthor), `SolicitacaoCAcessos` (`c_acessos`), `SolicitacaoCDest` (`c_dest`), `SolicitacaoCEquip` (`c_equip`), `SolicitacaoCVendas` (`c_vendas`). São dados estruturados para assuntos específicos (pedido de acesso a sistema, etc.). **Não trazer** — dependem de rotinas/cadastros do ERP que não temos.

### 2.8 NÃO faz parte deste módulo (ruído)
`ScomSolicitacao`/`ScomServer`/`ScomComunicacao` (sistema de **assinatura digital/comunicação** — `intranet_scom_*`) e `BsInv*` (inventário) **não pertencem** às Solicitações. Os "matches" de `Scom` no controller eram falsos-positivos (`colunasComStatus`, `dadosComentario`). Ignorar.

---

## 3. Controller — `SolicitacoesController` (7548 linhas, ~90 métodos)

É um **God Controller**. Responsabilidades agrupadas:

| Grupo | Métodos (amostra) | Observação |
|---|---|---|
| **Páginas (Inertia::render)** | `indexConfiguracoes`, `indexNova`, `indexLista`, `indexMinhas`, `indexAgendamento`, `indexDashboard`, `indexRelatorios` | 7 telas renderizadas via Inertia. |
| **CRUD solicitação** | `criarSolicitacao`, `getSolicitacoes`, `getSolicitacao`, `mudarPrioridade`, `mudarResponsavel`, `alterarDepto`, `alterarSolicitante`, `retornoSolicitante`, `atualizarPrevisaoEntrega` | `criarSolicitacao` (~350 linhas) tem hash anti-duplicata, movimentações, anexos, payloads C*. |
| **Ciclo de atendimento** | `iniciarAtendimento`, `pausarAtendimento`, `resolverAtendimento`, `recusarAtendimento`, `cancelarAtendimento`, `finalizarAtendimento` | Máquina de estados do ticket. |
| **Comentários** | `comentar`, `excluirComentario` | |
| **Configuração de assuntos** | `getAssuntos`, `salvarAssuntos`, `duplicarAssunto`, `toggleAtivoAssunto`, `salvarModelos`, `getDepartamentos`, `storeDepartamentos` | |
| **MOTOR DE FLUXO** | `getFluxo`, `salvarFluxo` (~270 linhas), `getFluxoSolicitacao`, `voltarFluxo`, `avancarFluxo`, `decidirFluxo`, `devolverAoFluxo`, `salvarCamposFluxo`, `getCamposPredefinidos` | Núcleo do workflow. |
| **Etapas (legado)** | `getEtapas`, `salvarEtapas`, `clonarEtapas`, `alterarEtapa` | |
| **Aprovações** | `listarAprovacoes`, `criarAprovacao`, `responderAprovacao`, `editarAprovacao`, `cancelarAprovacao`, `buscarAprovacoesUsuario` | Chaveadas por matrícula. |
| **Responsáveis/Liberações** | `getResponsaveis`, `salvarResponsaveis`, `getLiberacoes`, `salvarLiberacoes`, `getResponsaveisAdicionais`, `adicionar/removerResponsavelAdicional` | |
| **Agendamentos** | `criarAgendamento`, `criarLembrete`, `editar/cancelarLembrete`, `getAgendamentos`, `iniciar/finalizarAgendamento`, `buscaAgendamentoPorData` | Módulo de agenda completo. |
| **Notificações/Reverb** | `getCanaisNotif`, `saveNotificacoes`, `criaNotificacao`, `notificarReverbAtualizacao` | Realtime via Reverb. |
| **Dashboard/Relatórios** | `indexDashboard`, `getDadosDashboard`, `getEvolucaoPorData`, `buscarRelatorio`, `exportarRelatorio*` (Filtros/Fluxo) | Gráficos + exportação. |
| **Acoplamento Winthor** ⚠️ | `getDepartamentosCompras`, `getDepartamentosFuncionario`, `getFiliaisWinthor`, `getFuncoesWinthor`, `getRegionais`, `getEnderecoFilial`, `getFiliaisLideranca`, `storeFiliaisLideranca` | Consultam PCEMPR/PCFILIAL no Oracle. |
| **Importação** | `prepararImportacao`, `importar` | Importação em massa. |

---

## 4. Telas Vue

### 4.1 Módulo genérico de Solicitações (`Pages/Solicitacoes`) — ~37.8k linhas
| Tela | Linhas | O que faz |
|---|---:|---|
| `Solicitação.vue` | **9811** | Tela de **detalhe** do ticket (a maior do sistema): timeline, comentários, anexos, aprovações, fluxo, agendamentos, ações de atendimento. |
| `Configuracoes/Index.vue` | 4349 | Admin: assuntos, departamentos, responsáveis, modelos, liberações, importação. |
| `Nova/Index.vue` | 3777 | Criar solicitação (campos dinâmicos, anexos, filial/depto). |
| `Lista/Index.vue` | 3729 | Lista/fila de solicitações (filtros, colunas dinâmicas por fluxo). |
| `Dashboard/Index.vue` | 2512 | Dashboard com gráficos e indicadores. |
| `Configuracoes/Components/FluxoDialog.vue` | **2550** | **Construtor visual de workflow** (drag-and-drop de etapas/decisões — usa `vuedraggable`). |
| `Minhas/Index.vue` | 2476 | "Minhas solicitações". |
| `Relatorios/Index.vue` | 1932 | Relatórios + exportação. |
| `Agendamentos/partials/Detalhes.vue` | 1427 | Detalhe de agendamento. |
| `Aprovacao.vue` | 1240 | Tela de aprovação. |
| `Agendamentos/Index.vue` | 731 | Agenda. |
| `Configuracoes/Components/EtapasDialog.vue` | 657 | Editor de etapas (legado). |
| `Agendamento.vue` / `AgendamentoLembrete.vue` | 655 / 271 | Agendar / lembrete. |
| `Configuracoes/Components/{Liberacao,Responsaveis,Redirecionamento,AcoesAssuntoMenu}.vue` + `ImportacaoComponent` + `Lista/Components/CardAprovacao` | 175–434 | Dialogs auxiliares de config. |

### 4.2 Compras (NÃO portar) — `Compras/*` ~7.9k linhas
`Compras/Solicitacoes/Solicitacoes.vue` (2695), `Compras/Solicitacao/Solicitacao.vue` (2091), `DashboardSolicitante` (~1.6k). Acoplados a Winthor/cotação/contratos recorrentes.

### 4.3 Central de Aprovações — `CentralAprovacoes/*` ~2.2k linhas
Agregador com abas: **ContaPagar**, **Compras (autorização/alteração)**, **Solicitações (autorização)**. Só a aba de **Solicitações** é relevante; as demais dependem de Compras/Contas a Pagar.

---

## 5. Migrations / Schema

### 5.1 Situação
- O **núcleo legado** (`intranet_solicitacao`, `_assuntos`, `_campos`, `_mov`, `_com`, `_arq`, `_sel`, `_s_itens`, `_s_resp`, `_agend`, `_aprovacoes`, `_etapas`…) **não tem migration Laravel** — são tabelas **legado Oracle** criadas direto no banco. Precisarão ser **reconstruídas a partir dos models + uso no controller** (mesmo caso da tabela base de Contratos).
- O **motor de fluxo** (todas as `*_fluxo_*` e `*_fluxo_etapa_campos`) **tem migrations Laravel limpas** usando `Blueprint` padrão (`$table->id()`, `unsignedBigInteger`, `char(1)`, `foreign()...onDelete`). **PostgreSQL-compatível, portável quase como está.**

### 5.2 DDL Oracle a reconstruir / sinais de alerta
| Sinal | Onde | Ação |
|---|---|---|
| `CLOB` | `alter_comentario_to_clob_on_intranet_solicitacao_com` | Vira `text` no PG. |
| Tabelas sem migration | núcleo legado | Reconstruir schema em migration PG nova. |
| `char(1)` flags `'S'/'N'` | fluxo etapas/decisões | Manter como `char(1)` ou converter para boolean (decisão de padronização). |
| Consultas Oracle no controller | `TO_NUMBER`, `TO_CHAR`, `NVL`, `SYSDATE`, `PCEMPR`, `PCFILIAL`, `DB::connection('oracle')` (16 ocorrências) | Reescrever/remover — ver §6. |

> Migrations apenas-ALTER específicas de Compras (`add_*_to_intranet_compras_solicitacao`, contrato recorrente, bloqueado automático) **não se aplicam**.

---

## 6. Pontos de acoplamento (CRÍTICO)

| # | Acoplamento | Como aparece na Biglar | Proposta de adaptação no 5 Estrelas |
|---|---|---|---|
| 1 | **Usuário / matrícula** | `session('auth')->matricula` (184 ocorrências), todas as FKs de pessoa usam `matricula` (`usuario_solicitante`, `usuario_responsavel`, `aprovador_matricula`, `usuario_movimentacao`…) ligadas ao model `Funcionario`. | Trocar para `auth()->id()` e FKs `users.id`. Substituir `Funcionario` por `User`. **Impacto alto** — toca quase todos os métodos e ~10 relações de model. Os componentes de seleção de pessoa (`Funcionario.vue`) precisam apontar para `users`. |
| 2 | **Filial** | `filial_id` referencia `Filial.codigo` (não id). `getEnderecoFilial`, `getFiliaisWinthor` via Oracle `PCFILIAL`. | Já existe `App\Models\Filial` mapeando `branches`. Padronizar `filial_id = branches.id` (como em Contratos). Substituir consultas `PCFILIAL` por `branches`. |
| 3 | **Departamento / setor** | Departamento é **string livre** (`departamento`, `departamento_responsavel`, `areaatuacao` do funcionário no Winthor). `getDepartamentos`/`getDepartamentosFuncionario` consultam o ERP. | Usar nossa tabela `departments` (FK `department_id`). Decidir: manter string (compat rápido) ou normalizar para `department_id`. Recomendo **normalizar para FK `departments`** já que temos a entidade. Etapas do fluxo (`departamento`) passam a referenciar `departments`. |
| 4 | **Compras / Oracle / ERP (Winthor)** | `DB::connection('oracle')`, `PCEMPR`, `PCFILIAL`, tipos de campo "Winthor" (`depto_compras`, `filial_winthor`, `funcao`, `regional`), `valor_winthor` nas respostas, payloads `c_rot`/`c_acessos`. | **Remover por completo** (sem ERP/Compras, igual Contratos). Os "tipos de campo Winthor" caem; mantém-se apenas `selecao` normal alimentada por dados locais. Remover métodos `get*Winthor`, `getRegionais`, `getDepartamentosCompras`. |
| 5 | **Integrações externas** | Notificações via `criaNotificacao` + **Reverb** (`notificarReverbAtualizacao`, composable `useSolicitacoesEcho` com canais por departamento/solicitação/usuário). Sem WhatsApp neste módulo (existe em outros). | Temos **Reverb** e sistema de **notificações** próprio (`NotificationService`, sino). Mapear `criaNotificacao` → nosso `NotificationService`; canais Echo → nossos canais. **Baixo/médio esforço**, infra já existe. |
| 6 | **Auth/permissões** | Rotas sem `permission:` no bloco (auth implícita Biglar). | Adicionar middleware `permission:solicitacoes.*` e `$user->hasPermission()`. Criar permissões novas (ver §8). |
| 7 | **Armazenamento de arquivos** | Model `File` + `SolicitacaoArq`/`ComArq`/modelos. | Mapear para nosso storage (Backblaze B2 / `storage`), reaproveitando `BsFile`/padrão de anexos já trazido em Contratos. |

---

## 7. Dependências de libs

### Frontend
| Lib | Já temos? | Observação |
|---|---|---|
| PrimeVue 4 (Button, Dialog, Select, DataTable, Datepicker, Chart, Editor, Paginator, MultiSelect, Timeline…) | ✅ | Base do projeto. `primevue/editor` (Quill) e `primevue/chart` (Chart.js) confirmar registro. |
| axios, @inertiajs/vue3, vue | ✅ | |
| `@vueuse/core` | ✅ (usado em `useDevice`) | |
| `vuedraggable` | ❌ **a adicionar** | Drag-and-drop do **construtor de fluxo** (`FluxoDialog`). |
| `maska` / `primevue/inputmask` | ⚠️ verificar | Máscaras de input. |
| `heic2any` | ❌ **a adicionar (opcional)** | Converte fotos HEIC (iPhone) no upload. Opcional. |
| `sweetalert2`, `vue-toastification` | ✅ (trazidos em Contratos via `globalFunctions`) | As telas usam `swal*`/`toast*`. |
| Shims: `AuthenticatedLayout`, `globalFunctions`, `BsFile` | ✅ (Contratos) | Reaproveitar. Faltam: `Funcionario.vue` (picker pessoa→users), `Filial2`, `ViewFiles`, `BsData`, `BsIcone`, `LinhaTempo`, `BsAgenda`, `useSolicitacoesEcho`, `useUserPreferences`. |

### Backend
- Nenhuma lib nova obrigatória. Exportação de relatório segue padrão **CSV** (como Contratos) até spec dedicada de xlsx.
- Reverb e NotificationService já existem.

---

## 8. Avaliação de esforço e faseamento

> **Complexidade global: ALTA.** É o maior módulo analisado até aqui (controller 7548 linhas + tela de detalhe 9811 linhas + construtor de fluxo 2550). É o "motor de workflow" que outros módulos (Financeiro, Compras, Despesas) vão reutilizar — vale investir em portá-lo bem e de forma **incremental** (specs minúsculas, conforme `regras.md`).

### Fase 0 — Fundação de dados (núcleo mínimo)
Migrations PG do núcleo (`solicitacoes`, `solicitacao_assuntos`, `solicitacao_campos`, `mov`, `com`, `arq`) + models adaptados (matrícula→user_id, filial→branch_id, departamento→department_id). Permissões `solicitacoes.visualizar` / `solicitacoes.criar` / `solicitacoes.gerenciar` / `solicitacoes.configurar` / `solicitacoes.aprovar`. **Complexidade: média.**

### Fase 1 — MVP do ciclo simples (sem fluxo)
Criar solicitação (`Nova`), listar (`Lista`/`Minhas`), detalhe (`Solicitação.vue` enxuta), comentários, anexos, movimentações, ciclo de atendimento (iniciar/pausar/resolver/recusar/cancelar/finalizar), notificações + Reverb. **Entregável testável.** **Complexidade: média-alta** (tela de detalhe é grande — vale quebrar em partes).

### Fase 2 — Configuração de assuntos
`Configuracoes/Index` (assuntos, departamentos, responsáveis, modelos, campos dinâmicos do assunto, seleções). **Complexidade: média.**

### Fase 3 — MOTOR DE WORKFLOW ⭐ (o diferencial)
Migrations de fluxo (quase iguais — só FKs para `departments`/`users`), models de fluxo (vêm "praticamente iguais", são limpos), engine (`avancarFluxo`/`decidirFluxo`/`voltarFluxo`/`devolverAoFluxo`/`salvarCamposFluxo`), campos predefinidos, execução/histórico, e o **construtor visual `FluxoDialog`** (+`vuedraggable`). **Complexidade: ALTA.** É o núcleo de valor e o mais sensível.

### Fase 4 — Aprovações por alçada
`SolicitacaoAprovacao` + telas `Aprovacao.vue` e aba de Solicitações da `CentralAprovacoes`. Rechavear matrícula→user_id. **Complexidade: média.**

### Fase 5 — Dashboard & Relatórios
`Dashboard/Index` (gráficos) + `Relatorios` + exportação CSV. **Complexidade: média.**

### Fase 6 (opcional) — Agendamentos/Lembretes
Agenda (`BsAgenda`), lembretes, notificações agendadas. **Complexidade: média.** Avaliar se o 5 Estrelas precisa.

### O que vem "praticamente igual"
- Models e migrations do **motor de fluxo** (só trocar FKs de departamento/usuário).
- Estrutura de **campos dinâmicos por etapa** e campos predefinidos.
- Lógica de transição de estados do fluxo (decisões/ações).

### O que precisa ser reescrito/adaptado
- Toda referência a **matrícula → `auth()->id()`/users** (~184 pontos).
- **Filial.codigo → Branch.id**, **departamento string → `departments`**.
- Notificações (`criaNotificacao` → `NotificationService`) e canais Reverb.
- Schema do núcleo legado (sem migration original).

### O que NÃO faz sentido trazer
- **Módulo Compras** (`ComprasController`, telas `Compras/*`, abas de Compras/ContaPagar na CentralAprovacoes) — sem ERP/Compras.
- Tudo **Winthor/Oracle** (`PCEMPR`, `PCFILIAL`, tipos de campo "Winthor", `valor_winthor`, `get*Winthor`, `getRegionais`).
- Payloads **`c_rot`/`c_acessos`/`c_dest`/`c_equip`/`c_vendas`** (acesso a sistema/negócio Biglar).
- **Etapas legado** (`solicitacao_etapas`/`etapa_atual`/`etapa_historico`) — substituído pelo motor de fluxo (confirmar que nenhuma tela depende).
- `Scom*` / `BsInv*` (não pertencem ao módulo).

---

## 9. Riscos e decisões em aberto (validar com o Bruno)

1. **Profundidade do motor de fluxo no MVP.** Portar o workflow completo (decisões, campos por etapa, construtor visual, solicitação-filha) é o maior esforço. Pergunta: MVP entra **sem fluxo** (ciclo simples) e o motor vem numa fase dedicada? (Recomendado.)
2. **Departamento: string vs FK.** Normalizar para `departments` (mais correto) custa mais que manter string. Decidir padrão — afeta etapas do fluxo e filtros.
3. **Modelo de "etapas" legado.** Confirmar que pode ser descartado em favor do motor de fluxo (verificar dependência das telas `Lista`/`Solicitação`).
4. **Flags `S`/`N` (char) vs boolean.** Padronizar no PG? O motor usa `char(1)` extensivamente; converter para boolean dá retrabalho mas alinha ao nosso padrão (`is_active`).
5. **Aprovações: este módulo vs Financeiro.** O escopo do 5 Estrelas tem "aprovação por alçada" no Financeiro/Compras. Definir se a **aprovação** vive no motor de Solicitações (genérico) e é reutilizada, evitando duplicar lógica.
6. **CentralAprovacoes.** Trazer só a aba de Solicitações agora e montar as abas de Compras/Contas a Pagar quando esses módulos existirem? (Recomendado.)
7. **Agendamentos/Lembretes.** Faz parte do escopo do 5 Estrelas ou é específico Biglar? Pode virar módulo à parte.
8. **Anti-duplicata por hash + janela de tempo.** Manter a heurística (`hash_duplicata` + 2 min) ou trocar por constraint/idempotência? 
9. **Tela de detalhe (9811 linhas).** Precisa ser **refatorada/quebrada** ao portar (manutenção). Aceitar o custo de reescrever em componentes menores?
10. **Mobile.** Por `mobile-ux.md`, telas novas exigem versão mobile dedicada (lista→cards, modais→bottom sheet, filtros→bottom sheet). A Biglar não tem isso pronto — é trabalho adicional por tela.

---

## Apêndice — Inventário de arquivos (origem Biglar)
- Controller: `app/Http/Controllers/SolicitacoesController.php` (7548 linhas) — **portar (adaptado, fatiado)**.
- Controllers correlatos: `ComprasController.php` (9211) e `OrdemComprasController.php` (502) — **não portar**.
- Models (~35): núcleo + fluxo + aprovações + agendamento + acessórios (lista nas §2). C* e Scom/BsInv **fora**.
- Telas: `Pages/Solicitacoes/*` (~37.8k linhas) **portar**; `Pages/Compras/*` (~7.9k) **não**; `CentralAprovacoes/*` (~2.2k) **parcial (só aba Solicitações)**.
- Migrations: fluxo (Laravel/PG, **reusar**); núcleo (legado Oracle, **reconstruir**).
