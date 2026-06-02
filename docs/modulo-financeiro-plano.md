# Módulo Financeiro
## Plataforma 5 Estrelas — Plano de Implementação

**Data**: 28/05/2026 | **Versão**: 2.0

---

## O que é

O módulo financeiro permite controlar todo o ciclo de pagamentos e recebimentos da empresa: desde a solicitação de um pagamento até a conciliação bancária, passando por aprovações configuráveis.

---

## Como funciona (visão do usuário)

### Fluxo de Contas a Pagar

1. **Alguém solicita** um pagamento (preenche formulário com fornecedor, valor, centro de custo)
2. **O sistema envia** para aprovação automática (baseado em regras de alçada por valor)
3. **O aprovador** recebe notificação e aprova ou reprova
4. **Após aprovação**, o financeiro agenda e executa o pagamento
5. **O sistema dá baixa** automaticamente quando o banco confirma
6. **Conciliação**: o sistema cruza os pagamentos com o extrato bancário

### Fluxo de Contas a Receber

1. **Registra a NF** emitida (cliente, valor, vencimento)
2. **O sistema monitora** os vencimentos e avisa em D-10 e D-5
3. **Quando o cliente paga**, dá baixa (manual ou automática via extrato)
4. **Dashboard** mostra em tempo real: quanto tem a receber, quanto está vencido

---

## Telas que serão criadas

### Cadastros (cada um com tela própria)

| Tela | O que cadastra | Exemplo |
|------|---------------|---------|
| Centros de Custo | Áreas/setores da empresa | "Operações", "Administrativo", "TI" |
| Categorias de Despesa | Tipos de gasto | "Serviços", "Material", "Aluguel" |
| Fornecedores | Quem a empresa paga | "ABC Ltda", "XYZ Serviços" |
| Contas Bancárias | Contas da empresa | "Itaú AG 1234 CC 56789" |
| Projetos | Projetos em andamento | "Obra Centro", "Expansão Norte" |

Cada cadastro tem: listagem com busca, botão de criar, editar e excluir.

### Financeiro — Contas a Pagar

| Tela | Função |
|------|--------|
| Solicitações | Lista todas as solicitações de pagamento com filtros |
| Nova Solicitação | Formulário para pedir um pagamento |
| Detalhes | Mostra histórico de aprovações, anexos, timeline |
| Execução | Agendar e confirmar pagamentos aprovados |
| Conciliação | Cruzar pagamentos com extrato bancário |

### Financeiro — Contas a Receber

| Tela | Função |
|------|--------|
| Notas Fiscais | Lista NFs emitidas com status (aberto/pago/vencido) |
| Registrar NF | Formulário para registrar nova NF |
| Dar Baixa | Confirmar recebimento de pagamento |

### Financeiro — Despesas e Fundo Fixo

| Tela | Função |
|------|--------|
| Despesas | Lista despesas com classificação e status de aprovação |
| Nova Despesa | Formulário (categoria + centro de custo + projeto) |
| Fundo Fixo | Prestação de contas de valores adiantados |

### Financeiro — Dashboard

Painel com indicadores visuais:
- Total a pagar no mês
- Total a receber no mês
- Valores vencidos (alerta)
- Gráfico de despesas por categoria
- Fluxo de caixa (entradas vs saídas)

### Aprovações

| Tela | Função |
|------|--------|
| Minhas Pendências | O que preciso aprovar (com badge no menu) |
| Configurar Fluxos | Admin define regras de alçada (quem aprova o quê) |

---

## Permissões (cada tela com controle próprio)

### Cadastros
- `financeiro.centros_custo.gerenciar`
- `financeiro.categorias.gerenciar`
- `financeiro.fornecedores.gerenciar`
- `financeiro.contas_bancarias.gerenciar`
- `financeiro.projetos.gerenciar`

### Contas a Pagar
- `financeiro.contas_pagar.visualizar`
- `financeiro.contas_pagar.solicitar`
- `financeiro.contas_pagar.aprovar`
- `financeiro.contas_pagar.executar`
- `financeiro.contas_pagar.conciliar`

### Contas a Receber
- `financeiro.contas_receber.visualizar`
- `financeiro.contas_receber.registrar`
- `financeiro.contas_receber.baixar`

### Despesas
- `financeiro.despesas.visualizar`
- `financeiro.despesas.registrar`
- `financeiro.despesas.aprovar`

### Fundo Fixo
- `financeiro.fundo_fixo.visualizar`
- `financeiro.fundo_fixo.prestar_contas`

### Dashboard e Workflows
- `financeiro.dashboard.visualizar`
- `financeiro.workflows.configurar`

---

## Integração com Senior Sistemas

O Senior já tem módulos de Contas a Pagar/Receber. Nossa plataforma **não substitui** — ela é a camada de **workflow e aprovação** que alimenta o Senior.

### Na prática

- Usuário solicita pagamento **aqui** → passa por aprovação **aqui**
- Quando aprovado → sistema envia pro Senior registrar contabilmente
- Senior confirma pagamento → sistema dá baixa automaticamente
- Extrato bancário vem do Senior → sistema faz conciliação

### O que preparamos desde o início

Toda tabela financeira já nasce com campos de integração:
- ID do registro no Senior (pra vincular)
- Status de sincronização (pendente/sincronizado/erro)
- Data da última sincronização

Isso permite o sistema funcionar **100% sozinho** agora, e quando a integração Senior estiver pronta (semanas 15-18), é só "ligar".

---

## Fases de entrega (uma por vez, testável)

| # | Fase | Prazo | Entregável |
|---|------|-------|-----------|
| 1 | Cadastros Base | 1-2 dias | Telas de CC, Categorias, Fornecedores, Bancos, Projetos |
| 2 | Motor de Workflows | 2-3 dias | Configurar fluxos, alçadas, tela de pendências |
| 3 | Solicitação de Pagamento | 1-2 dias | Formulário + lista + envio pra aprovação |
| 4 | Aprovação | 1-2 dias | Aprovar/reprovar + notificação + histórico |
| 5 | Execução e Baixa | 2-3 dias | Agendar pagamento, confirmar, baixa |
| 6 | Notas Fiscais | 2-3 dias | Registrar NF, acompanhar, dar baixa |
| 7 | Notificações de Vencimento | 1 dia | Alertas D-10 e D-5 automáticos |
| 8 | Despesas e Fundo Fixo | 2-3 dias | Registrar, aprovar, prestar contas |
| 9 | Dashboard Financeiro | 1-2 dias | Cards + gráficos + indicadores |

**Total estimado**: 15-20 dias úteis

Cada fase termina com algo visível no browser pra testar.

---

## Dependências do cliente

Para avançar além da Fase 2, precisamos que o cliente defina:

1. **Regras de alçada** — quem aprova o quê, por qual valor
2. **Bancos utilizados** — quais contas bancárias integrar
3. **Plano de contas** — classificação contábil usada

Sem isso, construímos a estrutura mas não configuramos os fluxos reais.

---

## Menu lateral (como vai ficar)

Atual:
- Dashboard
- Usuários
- Notícias
- Aparência
- Auditoria
- Backups

Novo (adicionado):
- **Financeiro** (expansível)
  - Dashboard Financeiro
  - Contas a Pagar
  - Contas a Receber
  - Despesas
  - Fundo Fixo
- **Cadastros** (expansível)
  - Centros de Custo
  - Categorias
  - Fornecedores
  - Contas Bancárias
  - Projetos
- **Aprovações**
  - Minhas Pendências
  - Configurar Fluxos

---

*BS Tech Solutions — Maio/2026*
