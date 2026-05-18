---
inclusion: always
---

# Projeto 5 Estrelas - Notas e Alinhamentos

## Visão Geral

Plataforma de gestão completa para o cliente **5 Estrelas**, desenvolvida em parceria entre **BS Tech Solutions** e **Easy Tech**. O sistema cobre gestão financeira, compras, suprimentos, contratos, operação de campo (fiscalização), ponto com geolocalização, escala de brigadistas, auditoria com IA e dashboards gerenciais.

## Repositório

- GitHub: https://github.com/bstechsolutions-livt/5estrelas
- Branch principal: `main`
- Repositório antigo arquivado como `5estrelas-bkp`

## Estrutura do Projeto

- `docs/` - Documentação do projeto (contrato, infra, plano de implantação)

## Documentos de Referência

- `docs/5 estrelas - escopo.pdf` - Anexo Técnico de Escopo (contrato vinculante)
- `docs/descritivo_infraestrutura_5_estrelas_cliente_v4 (1).pdf` - Descritivo de infraestrutura
- `docs/plano_implantacao_5_estrelas_6_meses_bstech (2) (1).pdf` - Plano de implantação em 6 meses

---

## Módulos do Sistema

### 1. Financeiro
- **Contas a Pagar**: solicitação, aprovação por alçada, execução, baixa automática, conciliação bancária
- **Contas a Receber e Faturamento**: ciclo de faturamento, NFs, baixa automática, notificações D-10/D-5 via chatbot
- **Gestão de Despesas**: classificação por categoria/CC/projeto, workflow de aprovação
- **Fundo Fixo e Folha**: prestação de contas, integração com HCM Senior

### 2. Compras, Suprimentos e Contratos
- Demanda de compra com classificação orçamentária
- Workflow de aprovação por alçada
- Requisições de suprimentos com rastreabilidade
- Gestão de contratos (alertas, versionamento, histórico)

### 3. Operação Ponta a Ponta
- Ciclo: Solicitação → Classificação → Aprovação → Execução → Registro → Dashboard

### 4. Integração Senior Sistemas (ERP/HCM)
- API REST bidirecional (INPUT e OUTPUT)
- INPUT: movimentações validadas, aprovações, dados de ponto → Senior
- OUTPUT: folha processada, registros de ponto, dados HCM → sistema centralizador
- Logs de auditoria por transação
- Validação técnica pelo analista Daniel

### 5. App de Fiscalização em Campo
- Movimentações diárias (faltas, coberturas, trocas, ocorrências)
- Passagem de turno com e-mail automático
- Evidências fotográficas (substitui WhatsApp)
- Checklist de postos
- Validação pela mesa operacional

### 6. Ponto e Geolocalização
- Postos com latitude/longitude
- Validação por proximidade do celular
- Painel de inconsistências

### 7. Escala de Brigadistas
- Escala de folgões
- Ferramenta de apoio para criticar/auditar escala

### 8. Auditoria e IA
- Logs completos (quem, o quê, quando)
- Versionamento de registros
- Controle granular de permissões
- IA para auditoria de pagamentos (duplicidades, anomalias, fora de contrato)
- Fila de revisão humana
- Relatórios periódicos automáticos

### 9. Dashboards Gerenciais
- Diretoria e Presidência
- App mobile
- Indicadores financeiros, operacionais e RH
- Filtros por unidade, período, tipo de despesa

---

## SLA Contratual

| Parâmetro | Compromisso |
|-----------|-------------|
| Disponibilidade | 95% mínimo |
| Tempo de resposta | Até 2 horas |
| Suporte | 7x12 |
| Plantão emergencial | Casos críticos |

---

## Infraestrutura

### Arquitetura
- **VPS/Servidor**: aplicação web, APIs, banco de dados, autenticação, integrações, dashboards
- **Storage externo**: fotos, evidências, anexos, backups (referência: Backblaze B2, API S3-compatible)

### Ambientes

| Fase | Configuração | Referência |
|------|-------------|------------|
| Homologação | 2 vCPU, 8 GB RAM, 100 GB NVMe | KVM 2 (~R$44-78/mês) |
| Produção inicial | 4 vCPU, 16 GB RAM, 200 GB NVMe | KVM 4 (~R$60-150/mês) |
| Expansão futura | 8 vCPU, 32 GB RAM, 400 GB NVMe | KVM 8 (~R$120-260/mês) |

### Requisitos
- Linux Server
- SSD/NVMe
- HTTPS/SSL
- Firewall
- Backup automático + cópia externa
- Domínio/subdomínio

---

## Cronograma de Implantação (26 semanas / 6 meses)

| Módulo | Foco | Semanas |
|--------|------|---------|
| 1 | Fundação técnica, segurança, auditoria base, setup | 1-2 |
| 2 | Core operacional e motor de workflows | 3-4 |
| 3 | Financeiro: contas a pagar, despesas, fundo fixo, folha | 5-8 |
| 4 | Contas a receber, faturamento, NF, notificações | 9-11 |
| 5 | Compras, suprimentos e contratos | 12-14 |
| 6 | Integração Senior ERP/HCM | 15-18 |
| 7 | App fiscalização, passagem de turno, ponto, geolocalização | 19-22 |
| 8 | Escala de brigadistas, gaps operacionais | 23-24 |
| 9 | IA, dashboards, SLA, estabilização, entrega final | 25-26 |

### Prazo contratual
- **180 dias corridos** a partir da assinatura
- Pagamento da 7ª parcela suspenso se não concluído no prazo

---

## Dependências Críticas

- Documentação e acesso à API Senior (analista Daniel)
- Definição dos bancos integrados
- Canal do chatbot
- Validação semanal do cliente
- Regras de aprovação e alçadas formalizadas
- Massa de dados e cenários reais para testes
- Definição de perfis e permissões

---

## Itens Incluídos Sem Custo Adicional

- Validação e correção de gaps para Diretora Ana Paula
- Ferramenta para auditar escala de evidências
- Escala de folgões dos brigadistas
- App dos fiscais completo
- Módulos de Contas a Receber e Contas a Pagar
- Faturamento e controle de NFs com integração Senior

---

## Decisões e Alinhamentos

- Parceria BS Tech + Easy Tech
- Entregas semanais demonstráveis, aceite formal por módulo/fase
- Auditoria de pagamentos via regras programáticas (sem IA por enquanto)
- Ajustes operacionais durante implantação não geram custo adicional
- Novas funcionalidades fora do escopo = novo alinhamento

## Stack Definida

- **Backend**: Laravel 12 (PHP 8.3+)
- **Frontend Web**: Vue 3 + Inertia.js + PrimeVue 4
- **Estilização**: Tailwind CSS 4 + PrimeVue CSS variables (tema dinâmico/white-label)
- **Auth**: Laravel Sanctum (session)
- **Database**: PostgreSQL 16
- **Cache/Queue**: Redis
- **Storage**: S3-compatible (Backblaze B2)
- **App Mobile**: Flutter com WebView (wrapper do sistema web)
- **Realtime**: Laravel Reverb (WebSocket)
- **CI/CD**: GitHub Actions
- **Infra**: VPS Linux (Hostinger KVM ou equivalente)

### White-label
- Não é multi-tenant. Single-tenant, mas altamente configurável
- Cada deploy futuro = um cliente, um banco
- Cores, logos, favicon, nome da empresa, background de login = configuráveis via painel admin
- Implementado via CSS variables injetadas em runtime a partir de tabela `settings`
