# Parametrização/Ativação de Web Services para Sistema Integrado — Senior G5 ERP 5.10.4

> Pesquisa **só de documentação/internet** (sem VPN, sem SOAP, sem servidor de produção).
> Feita a partir da doc pública `https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/`
> via `curl + sed` (o site é JS-rendered e bloqueia o web_fetch).
> Objetivo: descobrir o procedimento EXATO para parametrizar/ativar um web service de um
> "Sistema Integrado" e resolver os erros **"Web service não está parametrizado para ser utilizado"**
> e **"Integração do tipo de informação 'N' está inativada"**.
> Data: pesquisa documental. Conteúdo das fontes parafraseado/resumido para conformidade de licença.

---

## 0. TL;DR — a resposta direta

O cadastro do Sistema Integrado (sigla **EASYTECH** na tela F000SIS) é só o **1º de 3 passos**. Falta o
passo do meio, que é o que está bloqueando tudo:

| Passo | Tela | O que faz | Status no 5estrelas |
|-------|------|-----------|---------------------|
| 1 | **F000SIS** (NH...SIS) — Cadastro de Sistemas Integrados | Registra a sigla/sistema terceiro (EASYTECH, Tipo 99, Ativo) | ✅ feito |
| 2 | **F000SXT** (NH...SXT) — **Configuração de Tipos de Informação** | **Ativa, por filial, CADA "tipo de informação" (cadastro) que o sistema poderá consumir** (Fornecedor, Filial, etc.). Situação `A-Ativo` + **Processar** | ❌ **FALTANDO — é o passo que resolve "tipo de informação inativada"** |
| 3 | **F000CWS** (NH000CWS) — Configuração de web services | Define quais **campos** de cada web service/porta saem, por empresa/filial/sistema. **Processar** | ⚠️ feito, mas precisa bater empresa/filial/porta da chamada |

- A tela **F000SXT** é aberta pelo **botão "Informações" dentro da F000SIS**, ou pelo menu
  `Cadastros > Integrações > Configuração de Tipos de Informação`.
- **F000CWI "Consulta de Web Services de integrações" NÃO é passo de ativação** — é só um
  **monitor/visualizador** de registros de integração por data. Não habilita nada.
- **"Processar" na F000CWS** = **salva/efetiva** a configuração daquele web service (não há "salvar"
  separado). Depois de processado, dá pra **Duplicar** para outras filiais.
- **Pré-requisito de licença**: `cad.fornecedor` e `cad.filial` na porta `ConsultarGeral` exigem o
  **módulo IPSI** na Proprietária. Conferir em `Ajuda > Informações da Proprietária (F000IPR)`.

> Sobre os códigos de tela: as telas têm prefixo **F000xxx** (cliente desktop) e o **mesmo** conteúdo
> no HTML5/Senior X aparece como **NH000xxx**. F000CWS ≡ NH000CWS, F000CWI ≡ NH000CWI, etc.
> "Implantação de Módulos de Integração" e "Integrações 1" são **pastas de menu** que agrupam essas
> telas — não existe uma rotina única separada com esse nome.

---

## 1. As telas e o que cada uma faz (fonte: doc oficial por tela)

### F000SIS — Cadastro de Sistemas Integrados
Fonte: [menu_cadastros/f000sis.htm](https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/menu_cadastros/f000sis.htm)

- Cadastra o sistema terceiro que vai integrar com o ERP. Campos: **Código, Descrição, Sigla, Tipo,
  Situação**. (No 5estrelas: Sigla=`EASYTECH`, Tipo=`99 - Outros`, Situação=`A-Ativo`.)
- Tipos padrão incluem: 1-Varejo Megasul, 2-Varejo Senior, 5-Força de venda, 8-GAtec, 17-ERP Senior X,
  … **99-Outros**.
- **Botão "Informações"**: abre a tela **F000SXT** para definir os **tipos de configuração/informação a
  serem integradas**. ← é por aqui que se chega no passo 2.
- Observação da própria Senior: se houver mais de um sistema integrado **do mesmo Tipo**, pode haver
  cruzamento de dados; o Tipo `99-Outros` **não** sofre esse efeito (mais um motivo para EASYTECH ser 99).

### F000SXT — Configuração de Tipos de Informação  ← **O PASSO QUE FALTA**
Fonte: [menu_cadastros/f000sxt.htm](https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/menu_cadastros/f000sxt.htm)

- Define **quais tipos de informação serão integrados** entre o ERP e o sistema selecionado (EASYTECH).
- Passo a passo documentado:
  1. Campo **Sistema**: selecionar a aplicação cadastrada na F000SIS (EASYTECH).
  2. Campo **Filial**: informar a filial que está sendo configurada (ex.: 1).
  3. Campo **Informação**: escolher o tipo, **ou deixar em branco e clicar "Mostrar"** para listar todos
     os tipos disponíveis.
  4. Na grade, **mudar a coluna "Situação" para `A - Ativo`**, **marcar** o(s) registro(s) e **Processar**.
  5. Ao mudar a situação, aparece confirmação com opção de aplicar a *Item Atual / Todos os Itens /
     Itens Acima / Itens Abaixo*.
- Efeito: ativar um tipo faz o ERP **monitorar** aquele cadastro (inclusão/alteração/exclusão geram
  pendência de integração). Por isso só se ativa o que realmente vai ser consumido.
- **É exatamente esta tela que resolve o erro `Integração do tipo de informação "1" está inativada`** —
  o "tipo 1" (no caso do `cad_filial`) está inativo porque nunca foi ativado aqui.
- Para replicar de uma filial para outra: botão **Duplicar** → tela **F000DCI** (Replicação).
- Nota lateral (Varejo): se a empresa (F070EMP) usar Módulo Varejo, às vezes é preciso ligar o parâmetro
  "Integração Varejo" (F070VAR) para gerar pendências — provavelmente **não** se aplica ao EASYTECH (99-Outros),
  mas fica o registro.

### F000CWS — Configuração de web services
Fonte: [menu_cadastros/f000cws.htm](https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/menu_cadastros/f000cws.htm)

- Define **quais informações (campos) de um web service** podem ser consultadas/retornadas numa integração.
- **Só aparecem no campo "Web service" os serviços que permitem configuração** — e a disponibilidade
  considera os **módulos da Proprietária** (licença). Se o serviço aparece pra você selecionar, em tese a
  licença permite; se não aparece, é trava de Proprietária.
- Passo a passo documentado:
  1. **Código**: número da configuração (cada WS recebe um código distinto; Tab gera automático). **Cuidado:
     reusar o mesmo código sobrescreve registros.**
  2. Informar **Empresa**, **Filial** e **Sistema** (o Sistema = EASYTECH da F000SIS).
  3. Selecionar **Web service** e a **Porta**.
  4. No painel **"Campos exportados pelo web service"**, marcar os campos. **PASSO OBRIGATÓRIO** — sem marcar
     campo nenhum, o serviço não retorna nada.
  5. (Opcional) Painel **"Definições das regras de filtro…"**: regra + cláusula `Where` SQL para restringir
     registros.
  6. **Processar**.
- **Lógica de resolução de PORTA (importante p/ o nosso caso):** ao executar uma porta, o sistema procura a
  config **daquela** porta; se não achar, tenta a **versão imediatamente inferior** e assim por diante até a
  porta base. **Se não achar configuração nenhuma, as requisições àquela porta são BLOQUEADAS.**
  (Exceção: web services do Varejo/Gestão de Lojas, que mantêm comportamento nativo.)
- Depois de processado, o botão **Duplicar** (tela **F000DWS**) replica a config para outras filiais.

> **Tradução prática:** a F000CWS é o "filtro de campos + liberação por empresa/filial/sistema/porta". Se a
> chamada SOAP não casar com uma configuração existente (empresa/filial/sistema/porta), o ERP trata como
> **não parametrizado** → bloqueia.

### F000CWI — Consulta de Web Services de integrações  (≡ NH000CWI)
Fonte: [menu_cadastros/f000cwi.htm](https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/menu_cadastros/f000cwi.htm)

- **É só um monitor/consulta.** Informa-se uma **Data de consulta**, clica **Mostrar**, e vê-se, por guias,
  os registros de integração de web services específicos (ex.: requisição eletrônica, movimento de estoque),
  com status, filtros por empresa/filial/sistema e a mensagem de retorno do WS.
- Botões como "Consultar registro integrado" abrem o XML (tela F000RWS).
- **Não ativa nem parametriza nada.** Apesar de estar na pasta de menu "Implantação de Módulos de Integração",
  o papel dela é auditar/diagnosticar o que já trafegou — **não** é o passo de ativação que estávamos procurando.

### F000IPR — Informações da Proprietária (verificação de licença)
Fonte: [menu_ajuda/f000ipr.htm](https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/menu_ajuda/f000ipr.htm)
e [pre-requisito-de-proprietaria.htm](https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/webservices/pre-requisito-de-proprietaria.htm)

- `Ajuda > Informações da Proprietária (F000IPR)` → navegar nas guias **Processos** e **Integrações** para
  ver os **módulos liberados** na licença.
- Licença pode ser **por área** (1 caractere: M-Mercado, S-Suprimentos, B-BackOffice, F-Finanças) ou
  **por processo** (4 caracteres: RFNF, **IPSI**, etc.).
- Para descobrir quais WS estão disponíveis no ambiente: `Recursos > Implementações > Web Services > Editar`,
  selecionar o provedor **Interno** → lista os web services/portas liberados. **Se o WS não está aí, ele não
  pode ser usado de jeito nenhum** (nem regra LSP, nem tela SGI, nem deploy GlassFish p/ terceiros).

---

## 2. O fluxo documentado end-to-end (fonte: manual oficial de integração Maxxsoft)

A melhor "receita" pública é o **manual da integração Maxxsoft – MaxxSFA+**, que mostra a sequência completa
de parametrização ERP↔sistema terceiro com as mesmas telas.
Fonte: [integracao-maxxsoft/inicio-integracao-maxxsoft.htm](https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/manuais_processos/agronegocio/integracao-maxxsoft/inicio-integracao-maxxsoft.htm)

**Pré-requisitos (item 2.1):**
- ERP em versão compatível; **Glassfish** instalado/habilitado; **ambiente middleware instalado e
  configurado**; **deploy dos web services efetuado**;
- **Proprietária com os módulos exigidos, incluindo o Módulo de Integração `IPSI`**;
- Banco Oracle/SQL Server suportado.

**Parametrizações (item 2.2) — a ordem oficial:**
1. **Cadastrar sistema integrado (F000SIS)**: Código, Descrição, **Sigla**, **Tipo**, **Situação=Ativo**.
2. **Configurar tipos de informação (F000SXT)**: informar Código/Sistema, **Filial**, escolher a Informação
   (ou "Mostrar" pra listar), **marcar** os registros com **Situação `A-Ativo`** e **Processar**.
3. **Configurar web services (F000CWS)**: selecionar WS + **Porta** (a porta tem que ser a **versão** usada
   pelo integrador), e — citando o manual — **por padrão marcar TODOS os campos** em "Campos exportados pelo
   web service" (tirar só o que o parceiro pedir pra não exportar). Criar filtros se necessário.

> **Conclusão do fluxo:** o cliente fez **(1)** e **(3)**, mas **pulou o (2) — F000SXT**. É o elo que falta.
> Para serviços de cadastro consumidos por um sistema integrado, **a ativação do "tipo de informação" na
> F000SXT é parte obrigatória da parametrização**, não opcional.

---

## 3. O que "Processar" faz, tela por tela

- **F000CWS → Processar**: **salva/efetiva** a configuração do web service (campos liberados + filtros) para
  aquela combinação **empresa/filial/sistema/web service/porta**. Não existe um "Salvar" à parte — *Processar
  é o gravar*. Após processar, habilita o **Duplicar** (F000DWS) p/ replicar a config a outras filiais.
- **F000SXT → Processar**: **grava a ativação** dos tipos de informação marcados (Situação `A-Ativo`) para o
  Sistema+Filial. É o que "liga" o monitoramento/integração daquele cadastro.
- **F000DWS / F000DCI → Processar**: confirmam a **replicação/duplicação** da configuração de uma filial para
  outras.

---

## 4. Diagnóstico dos dois erros

### 4.1. `Integração do tipo de informação "1" está inativada.` (visto no `cad_filial`)
- **Causa**: o "tipo de informação" correspondente (no caso, o cadastro de **Filial** = tipo 1) **não foi
  ativado na F000SXT** para o sistema EASYTECH naquela filial.
- **Correção**: F000SXT → Sistema=EASYTECH → Filial=1 → **Mostrar** → achar a linha do cadastro (Filial /
  Fornecedor / etc.) → **Situação `A-Ativo`** → **marcar** → **Processar**. Repetir/Duplicar para as demais
  filiais/empresas que forem consumir.

### 4.2. `Web service não está parametrizado para ser utilizado.` (visto no `cad_fornecedor` e no `cad_filial` com codFil)
- **O que o erro significa** (com base na doc da F000CWS): **não existe configuração efetiva** que case com a
  chamada executada. A F000CWS diz, com todas as letras, que **se nenhuma configuração for encontrada para a
  porta, as requisições são bloqueadas**. Ou seja, o ERP considera aquele WS **não parametrizado** para o
  contexto (empresa/filial/sistema/porta) da chamada.
- **Causas prováveis (em ordem), já que o cliente diz ter configurado emp 3/fil 1/ConsultarGeral):**
  1. **Empresa/Filial da chamada ≠ da configuração.** Só foi parametrizado **Empresa 3, Filial 1**. As provas
     anteriores chamaram `codEmp` 1, 2 e 3 — **1 e 2 não têm config** → erro garantido. **Re-testar exatamente
     em codEmp=3, codFil=1.**
  2. **Tipo de informação não ativado (F000SXT).** Para serviço de cadastro consumido por sistema integrado, a
     ativação na F000SXT faz parte da parametrização. Sem ela, o WS continua "não parametrizado" para o sistema.
  3. **Porta divergente / versão.** Configurou-se `ConsultarGeral` (porta base) — bom, porque a resolução de
     porta desce da versão chamada até a base. Mas se a config tivesse sido feita só numa porta **versionada**
     (ex.: `ConsultarGeral_8`) e a chamada for na base `ConsultarGeral`, **não casa** (a busca só desce, não
     sobe). Garantir config na **porta base `ConsultarGeral`** (que cobre as versões superiores por descida).
  4. **Sistema selecionado na F000CWS ≠ EASYTECH.** O campo **Sistema** da F000CWS tem que ser o **mesmo**
     código/sigla informado em `identificadorSistema=EASYTECH`.
  5. **Campos não marcados / Processar sem efeito.** Marcar campos é **obrigatório**; sem isso a config não
     "vale". Reabrir a config e confirmar que há campos marcados e que foi processada.
  6. **Proprietária sem o módulo `IPSI`.** `cad.fornecedor` e `cad.filial` em `ConsultarGeral` exigem
     **"Contém todos na proprietária: IPSI"**. Se a licença não tiver IPSI, o WS nem deveria aparecer na
     F000CWS / no Editor de Web Services — e aí é trava de **contrato/Proprietária** (resolver com o Comercial
     Senior). **Conferir no F000IPR.**

---

## 5. Pré-requisito de licença (Proprietária) — não esquecer
Fonte: [pre-requisito-de-proprietaria.htm](https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/webservices/pre-requisito-de-proprietaria.htm)

Da tabela oficial de pré-requisitos:
- `com.senior.g5.co.cad.fornecedor` — porta `ConsultarGeral` → **Contém todos na proprietária: `IPSI`**.
- `com.senior.g5.co.cad.filial` — porta `ConsultarGeral` → **Contém todos: `IPSI`** (e "Contém algum: IPSI, VMAIS").
- `IPSI` = **Módulo de Integração**. É o mesmo módulo citado como pré-requisito no manual Maxxsoft.

**Como conferir:** `Ajuda > Informações da Proprietária (F000IPR)` → guias **Processos/Integrações** → procurar
`IPSI`. Alternativa: `Recursos > Implementações > Web Services > Editar` → provedor **Interno** → ver se
`cad.fornecedor`/`cad.filial` e as portas aparecem. Se **não** aparecem, é trava de Proprietária (contrato).

---

## 6. Sequência concreta de cliques recomendada (próximo teste)

> Tudo no **ERP Gestão Empresarial** (login `bruno.easy`), na **Empresa onde se vai consultar** (começar pela
> **Empresa 3 / Filial 1**, que foi onde a F000CWS já foi configurada — ou pela Empresa 2). Read-only do nosso
> lado; aqui é config no ERP do cliente.

1. **Conferir a licença (1x):**
   `Ajuda > Informações da Proprietária (F000IPR)` → ver se o módulo **`IPSI`** está liberado.
   - Se **não** estiver → parar: é contrato/Proprietária (acionar Matheus/Comercial Senior). Sem IPSI o resto
     não resolve.

2. **Ativar os tipos de informação (F000SXT) — o passo que faltava:**
   `Cadastros > Integrações > Configuração de Tipos de Informação (F000SXT)`
   (ou abrir **F000SIS** → registro **EASYTECH** → botão **Informações**).
   - **Sistema** = EASYTECH; **Filial** = 1; deixar **Informação** em branco → **Mostrar**.
   - Localizar **Fornecedor** e **Filial** (e outros cadastros que vamos consumir) → coluna **Situação =
     `A - Ativo`** → **marcar** → **Processar**.
   - Repetir para a(s) empresa(s)/filial(is) alvo, ou usar **Duplicar** (F000DCI).

3. **Revisar a configuração do web service (F000CWS):**
   `Cadastros > Integrações > Configuração de web services (F000CWS)`
   - Conferir/!criar config com **Empresa** = (a que será chamada, ex. 3), **Filial** = 1, **Sistema** =
     EASYTECH, **Web service** = `com.senior.g5.co.cad.fornecedor`, **Porta** = `ConsultarGeral` (porta base).
   - **Marcar os campos** desejados em "Campos exportados pelo web service" (na dúvida, **marcar todos**).
   - **Processar**. Usar **Duplicar** para replicar p/ as demais filiais.
   - Repetir para `com.senior.g5.co.cad.filial` / `ConsultarGeral`.

4. **Re-testar a chamada SOAP** (do servidor de produção, origem liberada) com `identificadorSistema=EASYTECH`,
   mirando **exatamente** a empresa/filial configuradas (ex.: `codEmp=3`, `codFil=1`), porta `ConsultarGeral`.

5. **Se ainda falhar**, usar **F000CWI** (Consulta de Web Services de integrações) e/ou o XML de retorno
   (F000RWS) para ver a mensagem exata, e validar item a item a seção 4.2 (empresa/filial, porta, sistema,
   campos marcados, IPSI).

---

## 7. O que pedir ao Matheus/Luan (se não tiverem acesso às telas)

1. Confirmar **IPSI** na Proprietária (F000IPR). Se faltar, ajustar contrato/Proprietária com a Senior.
2. Na **F000SXT** (botão "Informações" da F000SIS, registro EASYTECH): **ativar** (`A-Ativo` + Processar) os
   tipos de informação **Fornecedor** e **Filial** (e demais que formos consumir), para a(s) empresa(s)/filial(is)
   operacionais (Empresa 2 e 3, Filial 1).
3. Na **F000CWS**: garantir config de `cad.fornecedor`/`cad.filial` na porta **`ConsultarGeral`**, **Sistema =
   EASYTECH**, **campos marcados**, **Processar**, e **Duplicar** para as filiais necessárias.

---

## 8. Conexão com o resto da integração 5estrelas

- **Contas a Pagar continua não dependendo disso.** `ConsultarTitulosAbertosCP` **não** usa
  `identificadorSistema` e já puxa títulos reais por varredura de `codFor` (Empresas 2 e 3). EASYTECH+F000SXT+F000CWS
  só destrava o **enriquecimento** com **nome/CNPJ de fornecedor e filial** (`cad_fornecedor`/`cad_filial`).
- Manter **read-only** (`Consultar*`/`ConsultarGeral`). Nunca `Gravar*`/`Exportar` que escreva, nem `Baixar*`,
  sem autorização explícita do Bruno.

---

## 9. Fontes (doc oficial Senior — Gestão Empresarial | ERP 5.10.4)

- F000CWS — Configuração de web services: `…/menu_cadastros/f000cws.htm`
- F000SXT — Configuração de Tipos de Informação: `…/menu_cadastros/f000sxt.htm`
- F000SIS — Sistemas Integrados: `…/menu_cadastros/f000sis.htm`
- F000CWI — Consulta de Web Services de integrações: `…/menu_cadastros/f000cwi.htm`
- F000DWS — Duplicação de configuração de web service: `…/menu_cadastros/f000dws.htm`
- F000IPR — Informações da Proprietária: `…/menu_ajuda/f000ipr.htm`
- Pré-requisitos de Proprietária p/ habilitar integração via WS: `…/webservices/pre-requisito-de-proprietaria.htm`
- Boas práticas para o Web Services: `…/boas-praticas-ws.htm`
- Manual de integração Maxxsoft – MaxxSFA+ (fluxo end-to-end): `…/manuais_processos/agronegocio/integracao-maxxsoft/inicio-integracao-maxxsoft.htm`
- Índice de Web Services: `…/webservices/indice-web-services.htm`
- WS Fornecedor (campos/portas): `…/webservices/com_senior_g5_co_cad_fornecedor.htm`

> (Prefixo comum das URLs: `https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/`.)
> Conteúdo das páginas foi parafraseado/resumido para conformidade com as restrições de licenciamento.
