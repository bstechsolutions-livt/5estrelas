---
inclusion: always
---

# Integração Senior ERP (G5 / Gestão Empresarial) — 5estrelas

> Descoberto e validado em 10/06/2026. Conexão testada, credenciais funcionando, WSDL baixado com sucesso. Esta é a base do **Módulo 6 do 5estrelas** (Integração Senior ERP/HCM).

## Contexto

- Cliente: **5 ESTRELAS SISTEMA DE SEGURANCA** / código Senior **24935**
- Ambiente Senior: **Sirius**
- IDs de ambiente: `I250623084614_P` (Produção) e `I250623084614_H` (Homologação)
- Servidores Senior: VMAPLP21SEN (PRD), VMAPLH18SEN (HML)
- Contato cliente: Matheus Xavier (matheus.xavier@grupo5estrelas.com.br), Luan, Dionei
- **Banco a banco NÃO disponível** (exige banco dedicado contratado — não é o caso). Só webservices SOAP ou SFTP.
- A Senior empurra contratar consultoria; **não vamos perguntar pra eles**, descobrimos por conta.

## Endpoints (ERP Gestão Empresarial — Webservices SOAP)

- **Produção (PRD):** `https://webp27.seniorcloud.com.br:30361`
- **Homologação (HML):** `https://webh17.seniorcloud.com.br:30661`
- Caminho base dos serviços: `/g5-senior-services/`
- Liberar outbound HTTPS para `*.seniorcloud.com.br`. Senior pode pedir IP público pra whitelist.

## Credenciais de integração (usuário técnico)

- **Usuário:** `5estrelas.integracao`
- **Senha:** `WsxCdeRfvTgb@`
- Criado pelo Matheus, serve pra CBDS e consumo dos webservices.
- A senha vai **DENTRO do envelope SOAP** (campos `user`, `password`, `encryption`), não é só HTTP Basic Auth. (Basic Auth no curl funciona pra BAIXAR o WSDL; a chamada de operação usa os campos no body.)

## Formato do endpoint WSDL (DESCOBERTA-CHAVE)

O nome do servlet WSDL é: **`sapiens_Sync` + nome_do_servico**

```
https://webp27.seniorcloud.com.br:30361/g5-senior-services/sapiens_Synccom_senior_g5_co_<area>_<entidade>?wsdl
```

3 variantes de execução por serviço:
- `sapiens_Sync...`      → síncrono (tempo real) — usar este pra consulta
- `sapiens_Async...`     → assíncrono
- `sapiens_Scheduled...` → agendado

> A doc oficial mostra a URL como `http://example.com/g5-senior-services/sapiens_Synccom_senior_g5_co_...?wsdl`. Trocar `example.com` pelo host real (webp27... PRD ou webh17... HML).

## Como descobrir os nomes dos serviços

Sem perguntar pra Senior: baixar o índice de WS e extrair os identificadores.

```bash
curl -s -L "https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/webservices/indice-web-services.htm" \
  | grep -oiE "com_senior_g5_co_[a-z0-9_]+" | sort -u
```

São **446 serviços** no total. Áreas (prefixo após `co_`):
- `int` (155) — Integrações
- `mct` (91) — Contábil
- `mfi` (65) — **Financeiro** (contas pagar/receber, tesouraria)
- `mcm` (54) — Comercial/Compras
- `ger` (37) — Geral (cadastros base, aprovações, centro custo)
- `cad` (24) — Cadastros (cidades, clientes, depósito)
- `mpr` (8) — Produção · `prj` (3) — Projetos · outros

A doc de cada serviço fica em:
`https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/webservices/<nome_servico>.htm`
(mostra a URL literal do WSDL e os campos)

## Serviços financeiros mapeados (mfi)

### Contas a Pagar — `com_senior_g5_co_mfi_cpa_titulos`

> **Verificado em 14/06/2026 (re-teste de conectividade):** o WSDL/XSD de **PRD** (`webp27...:30361`) respondeu **HTTP 200** desta máquina (sem VPN). O **HML** (`webh17...:30661`) respondeu **503** (provavelmente fora da whitelist/rede no momento). O XSD real foi baixado e a estrutura de `ConsultarTitulosAbertosCP` **v3** extraída e salva em:
> - `docs/senior/cpa_titulos.wsdl`, `docs/senior/cpa_titulos.xsd` (artefatos crus)
> - `docs/senior/cpa_titulos-campos.md` (tabela de campos legível)
>
> Estrutura real da v3 (a usar como fonte de verdade no Design, no lugar do que foi inferido só da doc):
> - **Entrada (26 params):** `codEmp`, `codFil`, `codFor`, `vctIni`, `vctFim` (janela de vencimento — base do sync incremental), `retRat` (S = retornar rateios), `tipTit`, e filtros de origem fiscal (`filNfc`/`numNfc`/`forNfc`/`snfNfc`, `filCtr`/`numCtr`, `filNff`/`numNff`/`forNff`, `filNfv`/`numNfv`/`snfNfv`, `filOcp`/`numOcp`/`ocpFre`/`ocpNre`), `seqImo`, `flowInstanceID`, `flowName`.
> - **Saída título (73 campos):** confirma os campos da doc e ainda traz extras não documentados: `codDfs`, `codFrj`, `cpgSub`, `ctrFre`/`ctrNre`, `filCcr`/`numCcr`, `gerTep`, `proJrs`, `seqCgt`, `seqImo`, `tipEfe`. Rateios vêm aninhados em `rateios` (lista, maxOccurs=unbounded).
> - **Saída rateio (20 campos):** `abrFpj`, `abrPrj`, `codCcu`, `codFpj`, `codTns`, `criRat`, `ctaFin`, `ctaRed`, `datBas`, `mesAno`, `numPrj`, `obsRat`, `perCta`, `perRat`, `seqMov`, `seqRat`, `somSub`, `tipOri`, `vlrCta`, `vlrRat`.
>
> ✅ **RESOLVIDO — chamada real de operação FUNCIONOU a partir do servidor de produção (22/06/2026).** O cliente liberou o acesso (whitelist Senior + firewall outbound). Teste executado a partir da VM `G5E-SVM080` (via VPN + SSH `easytech@192.168.254.80`):
> - **IP público de saída do servidor = `179.185.83.131`** (confirmado, bate com o IP que estava na whitelist).
> - **TCP 30361 (PRD) e 30661 (HML) = ABERTAS** (antes 30361 dava timeout pelo firewall do cliente — bloqueio #1 resolvido).
> - **WSDL PRD** (`webp27...:30361`) = **HTTP 200**, 34.532 bytes. **WSDL HML** (`webh17...:30661`) = **HTTP 503** (ambiente HML indisponível no F5 "sp4-sao"; não é problema nosso).
> - **Operação `ConsultarTitulosAbertosCP` em PRD EXECUTA** (não trava mais!): HTTP 200, resposta SOAP de aplicação real em 1-27s. A camada de execução respondeu — confirmando que o gating por IP foi removido.
> - A operação valida os campos em sequência. Refinando os parâmetros chegamos a **`Processado com sucesso.` (`tipoRetorno=1`, `erroExecucao` nil)**.
>
> **Parâmetros mínimos validados para `ConsultarTitulosAbertosCP` (PRD):**
> - `codEmp` (obrigatório)
> - `codFor` (obrigatório — sem ele: "É necessário informar o código do fornecedor.")
> - `retRat` (obrigatório — só aceita `N` ou `S`)
> - `vctIni` / `vctFim` — **datas no formato `dd/MM/yyyy`** (ISO `yyyy-MM-dd` é rejeitado: "A data deve estar no formato dia/mês/ano")
> - Senha vai DENTRO do envelope (`user`/`password`/`encryption=0`).
> - SOAPAction vazio, `Content-Type: text/xml;charset=UTF-8`, `-k` (TLS), namespace `http://services.senior.com.br`.
> - `tipoRetorno`: `1` = sucesso, `2` = erro de validação de negócio. Mensagem humana em `mensagemRetorno`.
>
> **Conclusão:** conectividade, whitelist, firewall, credenciais e contrato SOAP estão **100% funcionais a partir do servidor de produção**. Os DOIS bloqueios anteriores (firewall outbound do cliente + whitelist Senior do IP `179.185.83.131`) estão resolvidos. Toda chamada real à Senior DEVE rodar a partir do servidor (origem `179.185.83.131`); do IP de dev do Bruno (dinâmico) continua gated.
>
> **Próximos passos:** (1) descobrir os `codFor` (códigos de fornecedor) e `codEmp`/`codFil` reais do 5estrelas para puxar títulos de verdade; (2) implementar o sync usando dd/MM/yyyy e os 3 campos obrigatórios; (3) re-testar HML quando a Senior normalizar o ambiente (hoje 503). NUNCA rodar operações de escrita (`GravarTitulosCP`, `BaixarTitulosCP`) — só `Consultar*` read-only até validação formal.

Operações úteis:
- **Consulta:** `ConsultarTitulosCP`, `ConsultarTitulosAbertosCP`, `buscarPendentesCP`, `ConsultarCP`
- **Gravar/baixar:** `GravarTitulosCP`, `BaixarTitulosCP`, `EntradaTitulosLoteCP`, `GerarBaixaPorLoteCP`, `GerarBaixaAproveitamentoCP`
- **Aprovação:** `aprovarCP`, `reprovarCP`
- **Exportar baixas:** `ExportarBaixaTitulosPagarIntegracao`
- **Outros:** `EstornoBaixaTitulosCP`, `ExcluirTitulosCP`, `SubstituirTitulosCP`

### Contas a Receber — `com_senior_g5_co_mfi_cre_titulos`
- **Consulta:** `ConsultarTitulosCR`, `ConsultarTitulosAbertosCR`
- **Gravar/baixar:** `GravarTitulosCR`, `BaixarTitulosCR`, `GerarBaixaPorLoteCR`, `GerarBaixaAproveitamentoCR`
- **Exportar baixas:** `ExportarBaixaTitulosReceberIntegracao`, `ExportarBaixaTitulosReceberVenda`
- **Outros:** `AlteracaoParcialTitulosCR`, `EstornoBaixaTitulosCR`, `ExcluirTitulosCR`, `SubstituirTitulosCR`

### Tesouraria — `com_senior_g5_co_mfi_tes_movimentotesouraria`
- `Exportar`

> Sufixos `_2`, `_3` nas operações = versões/overloads do mesmo método com assinaturas diferentes.

## Estrutura da chamada SOAP (cada operação)

Toda operação recebe:
- `user` — usuário de integração
- `password` — senha
- `encryption` — tipo de criptografia da senha (geralmente `0` = sem criptografia)
- `parameters` — os dados específicos da operação

## Responsabilidades (definido pela Senior)

- **Senior Cloud/Tecnologia:** conexão, conectividade, liberação de endpoints/whitelist. (já OK)
- **Suporte Produto / funcional ERP:** regras de negócio, quais campos usar, estrutura de tabelas. (NÃO temos — descobrir via doc + WSDL)

## Documentação oficial

- Índice WS: `https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/webservices/indice-web-services.htm`
- Versão do ERP: **5.10.4**
- A doc bloqueia extração via web_fetch (JS). Usar `curl -s -L ... | grep` pra extrair conteúdo cru.

## Procedimento pra explorar um serviço novo

```bash
BASE="https://webp27.seniorcloud.com.br:30361/g5-senior-services"
U="5estrelas.integracao"; P='WsxCdeRfvTgb@'
SVC="sapiens_Synccom_senior_g5_co_mfi_cpa_titulos"  # ajustar
# Baixar WSDL e listar operações:
curl -s -k -u "$U:$P" "${BASE}/${SVC}?wsdl" | grep -oE 'operation name="[^"]*"'
```

## Cuidados

- **PRD é produção real do cliente.** Operações de gravar/baixar/excluir alteram dados financeiros reais. Testar SEMPRE em HML primeiro (`webh17...:30661`).
- Consultas (`Consultar*`) são read-only, seguras.
- Evitar caracteres especiais nos dados (quebram o parser XML da Senior — erro "Read Stream Error").


## Usuários e Filiais (explorado 10/06/2026)

### Usuário — `com_senior_g5_co_ger_cad_usuario` (RASO, pouco útil)
Operações: `ExportarAbrangencia`, `ExportarAbrangencia_2`, `obterParametrosVendas`.
**NÃO traz cadastro pessoal** (sem nome, email, CPF, cargo). Só expõe vínculo de acesso:
- `codUsu` (código usuário ERP), `usuPlt` (login plataforma)
- `codEmp`/`numEmp` (empresa), `codFil` (filial que acessa = "abrangência")
- `codFor` (vínculo fornecedor), `codRep` (vínculo representante)
- `obterParametrosVendas` → parâmetros de venda (venAsp, venCpd, venLpd, venRpd, obsMob/Mol/Mor)

> Cadastro rico de usuário (nome, email, perfil, permissões) NÃO está no ERP. Fica na **Gestão de Acesso da plataforma Senior X** (outro endpoint, fora do g5-senior-services).

### Filial — `com_senior_g5_co_cad_filial` (RICO, ~319 campos)
Operações: `ConsultarGeral`, `ConsultarGeral_2`, `ConsultarCadastro`, `ConsultarFiscal`, `Exportar`, `Exportar_2`.

**Entrada de `ConsultarGeral`** (`filialConsultarGeralIn`): `codEmp` (int), `codFil` (int), `numCgc`, `sigUfs`, `tipEmp`, `indicePagina`, `limitePagina`, `identificadorSistema`. (filtros opcionais; paginar com indice/limite)

**Saída** (`filialConsultarGeralOutFilial`) — campos úteis pra espelhar nas nossas filiais:
- **Identificação:** `codEmp`, `codFil`, `nomFil` (nome filial), `nenFil` (nome empresarial), `sigFil` (sigla), `numCgc` (CNPJ), `insEst` (inscrição estadual), `insMun` (inscrição municipal), `tipEmp`
- **Endereço filial:** `endFil`, `baiFil`, `cidFil`, `cepFil`, `eenFil` (nº), `cplEnd` (complemento), `sigUfs` (UF)
- **Endereço cobrança:** `endCob`, `baiCob`, `cidCob`, `cepCob`, `eenCob`, `cplCob`, `estCob`
- **Endereço entrega:** `endEnt`, `baiEnt`, `cidEnt`, `cepEnt`, `eenEnt`, `cplEnt`, `estEnt`
- **Contato:** `numFon` (telefone), `numFax`, `intNet` (site/internet), `cxaPst` (caixa postal)
- Prefixos de outras áreas (não precisa pra cadastro básico): `ctb*` (contábil), `pag*` (pagamento), `rec*` (recebimento), `ven*` (vendas), `est*` (estoque), `prd*` (produção), `efi*`/`cpr*`/`cxb*` (fiscal/caixa).

> Os campos seguem padrão Senior de 3 letras + 3 letras (ex: `nomFil` = NOMe FILial, `numCgc` = NUMero CGC/CNPJ, `insEst` = INScrição ESTadual).

### Próximo passo (quando retomar 5estrelas)
- Fazer chamada SOAP real de `ConsultarGeral` (ou `Exportar`) de Filial pra trazer as filiais reais do 5estrelas e popular o cadastro do nosso sistema.
- **Testar em HML primeiro** (`webh17...:30661`). Consulta é read-only, segura.
- Precisa montar o envelope SOAP com user/password/encryption + parameters (codEmp).

## Estratégia de ambiente para a integração (decidido 14/06/2026)

- **IP público de saída da VM de produção `G5E-SVM080` = `179.185.83.131`** (confirmado em 14/06/2026 via `curl ipify`/`ifconfig.me` de dentro do servidor; sai pela `eth0` via gateway `192.168.254.254`). É ESTE o IP a liberar na whitelist da Senior (não o IP de dev do Bruno, que é dinâmico; a Senior só libera IP estático/FIXO).

### DOIS bloqueios distintos a resolver (confirmado 14/06/2026)
1. **Firewall do CLIENTE (saída) — responsabilidade da TI 5 Estrelas:** o servidor `192.168.254.80` (público `179.185.83.131`) **NÃO tem saída** para `webp27.seniorcloud.com.br:30361`. Teste do servidor: internet geral OK (Google 204), DNS resolve `webp27` → `159.60.137.78`, mas **TCP 30361 = BLOQUEADO (timeout)**. Precisa liberar OUTBOUND HTTPS do servidor para `*.seniorcloud.com.br` nas portas **30361 (PRD)** e **30661 (HML)**.
2. **Whitelist da SENIOR (entrada) — abrir chamado:** adicionar `179.185.83.131` na whitelist dos webservices (form da Jessica, ticket #1269856). Do IP de dev (`189.63.13.100`) o WSDL abre mas a execução de operação trava → indício de que a execução é gated por IP liberado (hoje só o do parceiro Geo Brasil).

> A evidência de TELNET origem→destino:porta que a Senior pede só vai passar DEPOIS do bloqueio #1 (firewall do cliente) ser liberado. Provavelmente fazer os dois pedidos juntos.
- **Desenvolvimento é local**, mas **toda chamada real à Senior é testada no servidor** (a partir da VM de produção, cuja origem será a liberada).
- Localmente a integração roda em modo desabilitado/mock (ver requirement 12 da spec `senior-contas-pagar-sync`): sem acesso à Senior, o agendador conclui sem erro e o DemoSeeder popula a massa.
- Formulário de whitelist da Senior (modelo no e-mail da Jessica Lewandovski, ticket #1269856) precisa de: tipo=Integração web services; URL cloud=`https://webp27.seniorcloud.com.br:30361`; IP de origem (FIXO) da VM; evidência de IP (meuip.com.br); evidência de TELNET origem→`webp27.seniorcloud.com.br` porta `30361`; comunicação pela internet; IP do Brasil.

## Exploração de dados reais — 22/06/2026 (codEmp/codFil + bloqueio identificadorSistema)

> Executado a partir do servidor de produção `G5E-SVM080` (VPN + SSH `easytech@192.168.254.80`, origem `179.185.83.131`). Tudo READ-ONLY (`Consultar*`/`ConsultarGeral`). Artefatos crus salvos em `docs/senior/exploracao-2026-06-22/`.

### 1. CP `ConsultarTitulosAbertosCP` — contrato 100% funcional, mas retornou vazio
- Janela testada: `vctIni=22/06/2025`, `vctFim=20/09/2026` (formato `dd/MM/yyyy`).
- `codEmp=1`, `retRat=N`, `codFor` ∈ {0,1,2,3,10,100} → **todos HTTP 200, `tipoRetorno=1`, `mensagemRetorno=Processado com sucesso.`** mas **SEM bloco `<titulos>`** (zero títulos). Ou seja: a operação executa e responde certo, mas esses `codFor` chutados não têm títulos em aberto (ou não são fornecedores reais). Amostra: `docs/senior/exploracao-2026-06-22/cp_for_0.xml`.
- **CP NÃO exige `identificadorSistema`** (diferente dos serviços de cadastro). Os 3 obrigatórios continuam: `codEmp`, `codFor`, `retRat` (+ janela `vctIni/vctFim`).

### 2. BLOQUEIO PRINCIPAL: serviços de cadastro exigem "Sistema integrado" (identificadorSistema) registrado
Testados `cad_filial`, `cad_fornecedor`, `cad_parametrosintegracao` com `ConsultarGeral`/`Exportar`:
- Sem `identificadorSistema` → `tipoRetorno=0`, `erroExecucao="...Valores de parâmetros obrigatórios não foram informados."`
- Com `identificadorSistema=5ESTRELAS` (chute) → erro específico **`Sigla de sistema não cadastrada`** (`tipoRetorno=-1`).
- Conclusão: existe um **"Sistema integrado" (sigla)** que precisa estar **cadastrado no Senior** (tela de Cadastro de Sistemas / Parâmetros de Integração) e informado em `identificadorSistema` para LISTAR filiais/fornecedores. **Não temos essa sigla.** É config do lado Senior (pedir ao Matheus a sigla registrada, ou pedir pra registrar uma pra nossa integração `5estrelas.integracao`).
- Amostra do erro: `docs/senior/exploracao-2026-06-22/filial_emp_1.xml` e `param_geral.xml`.

### 3. Oráculo de validação: descobrimos codEmp/codFil reais SEM precisar da sigla
O `cad_parametrosintegracao` (`ConsultarGeral`) valida `codEmp`/`codFil` ANTES de exigir a sigla, e ecoa mensagens distintas. Usando isso como oráculo (presença/ausência de "não cadastrado"):

- **Empresas válidas: `codEmp` 1 a 12** (todas só reclamam "Sistema integrado não informado"). 
- **`codEmp` 15, 20, 99, 500 = inexistentes** → `Código da empresa não cadastrado. Código de empresa informado: N`. (13 e 14 não testados — o grupo tem ~12 empresas.)
- **Empresa 1 tem UMA única filial: `codFil=1`.** `codFil` ∈ {2,3,4,5,6,8,10} → `Código da filial não cadastrado`. `codFil=0` → inválido.
- Cada empresa testada (1..12) aceita `codFil=1`.
- Padrão de mensagens (oráculo):
  - filial válida → resposta só com `Sistema integrado não informado`.
  - filial inválida → `+ Código da filial não cadastrado. Código da empresa/filial informados: E/F`.
  - empresa inválida → `+ Código da empresa não cadastrado. Código de empresa informado: E`.
- Amostras: `p_1_1.xml` (emp1/fil1 válido) e `p_99_1.xml` (empresa inexistente).

### 4. Resumo / próximos passos concretos
- ✅ Mapa de empresas/filiais (parcial, sem nomes): **codEmp 1–12, cada uma com codFil=1; empresa 1 só tem a filial 1.**
- ❌ **Não conseguimos nomes/CNPJ das filiais nem a lista de fornecedores** porque `ConsultarGeral`/`Exportar` de cadastro exigem a **sigla de "Sistema integrado"** registrada no Senior.
- ❌ **Não puxamos títulos reais** porque CP exige `codFor` e não temos lista de fornecedores (chutes 0–100 vieram vazios).
- **AÇÃO (pedir ao Matheus):** (a) a **sigla do "Sistema integrado"** já cadastrada para a integração (valor de `identificadorSistema`), ou registrar uma nova; e/ou (b) alguns **`codFor` reais** com títulos em aberto + a empresa/filial correspondente (provável `codEmp` da matriz + `codFil=1`). Com a sigla, `cad_fornecedor.ConsultarGeral` lista os `codFor` e aí o CP retorna títulos de verdade.
- Reforço: continuar **só read-only** (`Consultar*`) até validação formal; nunca `Gravar*`/`Baixar*`/`Excluir*`.

## Exploração autônoma — 23/06/2026 (DEADLOCK QUEBRADO: títulos reais sem precisar do Matheus)

> Executado a partir do servidor de produção `G5E-SVM080` (VPN + SSH `easytech@192.168.254.80`, origem `179.185.83.131`). Tudo READ-ONLY (`Consultar*`). Artefatos crus salvos em `docs/senior/exploracao-2026-06-23/`.
> Objetivo: quebrar o deadlock codFor↔sigla SEM depender do Matheus. **Conseguimos puxar títulos reais.**

### 1. ✅ ACHADO PRINCIPAL — títulos de Contas a Pagar REAIS puxados (sem sigla)
A sweep de `codFor` no `ConsultarTitulosAbertosCP` (que NÃO exige `identificadorSistema`) encontrou empresas com movimento. **codEmp=1 não tem nenhum título** (provável holding/sem AP); as empresas operacionais são outras:

- **codEmp=2** e **codEmp=3** têm títulos em aberto reais.
- Exemplos confirmados (amostras salvas):
  - `senior-hit-3-1.xml` — emp 3, codFor 1: 2 títulos. Ex.: `numTit=48388378`, emi 01/11/2024, vct 20/11/2024, `vlrAbe=34.685,36`, obs "ABASTECIMENTO DOS VEICULOS... 2ª QUINZENA 2024"; outro `numTit=5080 05/05`, vct 22/09/2026, `vlrAbe=6.236,60`, "PARCELA 05/05 - ORÇAMENTO 1483 / uniforme operacional".
  - `senior-hit-2-200.xml` — emp 2, codFor 200: títulos de abastecimento (`vlrAbe` 50,00 / 80,00), `codCcu=2363`.
  - `senior-hit-2-2000.xml` — emp 2, codFor 2000: `numTit=07003481`, vct 14/05/2025, `vlrAbe=23.560,21`, "PAGAMENTO EXECUÇÃO PROCESSO PONTÃO - FURTO MOTO", `codCcu=4714`.

**Campos do título de saída (sitTit=AB = aberto), confirmados em produção:** `codEmp`, `codFil`, `codFor`, `numTit`, `datEmi`, `datEnt`, `datPpt`, `vctOri`/`vctPro` (vencimento), `vlrOri` (original), `vlrAbe` (saldo aberto), `obsTcp` (descrição/histórico), `ctaFin` (conta financeira), `codCcu` (centro de custo), `codTns` (transação, ex. 90500), `codMoe=01`, `codTpt=01`. Com `retRat=S` viriam os rateios aninhados.
> **IMPORTANTE:** o título traz só o `codFor` (código), **NÃO o nome do fornecedor**. Para enriquecer com nome/CNPJ ainda precisamos do `cad_fornecedor` (gated pela sigla — ver item 4).

### 2. Enumeração de fornecedores com títulos em aberto (codFor 1–150, janela 2018–2027)
Salvo em `docs/senior/exploracao-2026-06-23/suppliers-with-open-titles.csv`:
- **codEmp=2:** codFor **41, 75, 94, 108, 114, 118, 141, 149** (na faixa 1–150) + **200, 2000** (achados na amostragem ampla). Somas de saldo variam de R$ 44 a R$ 25.867.
- **codEmp=3:** codFor **1, 49, 75, 90, 114**. codFor 1 sozinho soma R$ 40.921,96.
- Fornecedores são **esparsos** e os códigos vão **além de 150** (ex. emp2 tem 200 e 2000). Uma enumeração completa exige varrer faixa maior (ex. 1–9999) por empresa, OU obter a lista oficial via `cad_fornecedor` (precisa da sigla).

### 3. Operações CP testadas quanto a exigir `codFor`
- `ConsultarTitulosCP` e `ConsultarCP` → **exigem codFor** ("É necessário informar o código do fornecedor.").
- `buscarPendentesCP` → **assinatura diferente**: recebe `usuario` (codUsu int), `indicePagina`, `limitePagina` (sem codFor). Saída rica (`titulosbuscarPendentesCPOutTitulo`): inclui `codigoFornecedor`, **`nomeFornecedor`**, `nomeEmpresa`, `nomeFilial`, `numero`, `valorOriginal`, `valorLiquido`, datas. PORÉM a **fila de aprovação está vazia**: varri `usuario` 1–60 e todos retornaram só "Processado com sucesso" sem títulos. É a fila de aprovação de CP (workflow `aprovarCP`), que o cliente aparentemente não usa. Se um dia usarem, esse método entrega o nome do fornecedor de brinde.

### 4. Sigla `identificadorSistema` — segue bloqueada (gate do lado Senior)
- TODAS as 25 operações de `cad_fornecedor` (`ConsultarGeral`/`ConsultarCadastro`/`ConsultarFiscal`/`Exportar`, incl. overloads `_2`.._10`) **exigem `identificadorSistema`** — confirmado lendo o XSD (504 KB). Nenhuma variante dispensa.
- ~20 siglas chutadas (SAPIENS, SENIOR, G5, ERP, INTEGRACAO, RUBI, WS, PADRAO, 5ESTRELAS, BSTECH, EASYTECH, GEOBRASIL, HCM, SENIORX, etc.) → todas **`Sigla de sistema não cadastrada`** (`tipoRetorno=-1`).
- **Chicken-and-egg confirmado:** `cad_parametrosintegracao` (o serviço que LISTA os "Sistemas Integrados") tem só 4 ops: `ConsultarGeral`, `ConsultarGeral_2`, `Exportar`, **`Gravar`**. Tanto `ConsultarGeral` quanto `Exportar` **também exigem a sigla pra consultar** ("Sistema integrado não informado") — ou seja, não dá pra LER a lista de siglas sem já ter uma sigla. O único jeito de criar uma é o `Gravar` (ESCRITA — **não usamos**, fora do escopo read-only).

### 5. BOTTOM LINE — dá pra puxar dado real sozinho? **SIM, parcialmente.**
- ✅ **Contas a Pagar (títulos em aberto): conseguimos puxar de forma autônoma**, sem o Matheus, varrendo `codFor` por empresa no `ConsultarTitulosAbertosCP`. Empresas operacionais = **codEmp 2 e 3** (codFil=1). Já temos amostra real validada.
- ⚠️ **Estratégia de sync sem a sigla:** iterar `codFor` de 1..N (ex. 1..9999) por `codEmp` (2,3 e demais com movimento), janela `vctIni/vctFim` ampla, `retRat=S` se quiser rateio. Funciona, mas é "varredura" (muitas chamadas) e o **nome do fornecedor não vem** — guardamos só `codFor`.
- ❌ **Ainda precisamos do Matheus para 2 coisas (não bloqueiam o sync de títulos, mas melhoram muito):**
  1. **A sigla `identificadorSistema`** (registrada no Senior) → destrava `cad_fornecedor` (lista oficial de fornecedores: codFor + nome + CNPJ) e `cad_filial` (nomes/CNPJ das filiais). Com ela o sync fica eficiente (lista direta em vez de varredura) e os títulos ganham o nome do fornecedor. Alternativa: autorizar a gente a usar `Gravar` em `cad_parametrosintegracao` pra registrar nossa própria sigla (decisão do Bruno — é escrita).
  2. (Opcional) Confirmar **quais codEmp são as empresas operacionais** e os nomes — já inferimos 2 e 3 pela presença de títulos.

### 6. Recomendação de implementação (spec `senior-contas-pagar-sync`)
- Implementar o sync de CP **já**, baseado em `ConsultarTitulosAbertosCP`, iterando `codEmp` ∈ {empresas com movimento} × `codFor` ∈ faixa configurável, janela de vencimento incremental (`dd/MM/yyyy`). Persistir `codFor` cru; deixar campo `fornecedor_nome` nullable pra enriquecer depois.
- Quando a sigla chegar, adicionar um passo de sync de `cad_fornecedor`/`cad_filial` pra popular nomes/CNPJ e trocar a varredura por lista oficial.
- Manter **read-only** (`Consultar*`). NUNCA `Gravar*`/`Baixar*`/`Excluir*` sem autorização explícita do Bruno.


## Sweep de siglas `identificadorSistema` — 22/06/2026 (rodada 2: TODAS falharam de novo)

> Executado a partir do servidor de produção `G5E-SVM080` (VPN + SSH `easytech@192.168.254.80`, origem `179.185.83.131`). READ-ONLY: só `ConsultarGeral` em `cad_fornecedor` (`codEmp=1`, `codFil=1`, `indicePagina=1`, `limitePagina=10`). Nada salvo (resultado 100% negativo).

Testada uma segunda batelada de **34 siglas candidatas** (harvest de screenshots do console cloud + pesquisa + variantes de HCM/Geo Brasil/Sirius). **Resultado: TODAS retornaram `Sigla de sistema não cadastrada`** (`tipoRetorno=-1`, erro aninhado em `<erros><mensagemErro>`; o `<mensagemRetorno>` de topo é só "Ocorreram erros.").

- Siglas testadas nesta rodada: SIRIUS, SIRIUSG5, VELPE, INTVELPE, INTEGRACAOVELPE, CONCENTRADOR, MIDDLEWARE, MENSAGERIA, MENSAGERIACLOUD, INTEGRADORHCM, HCMINT, INTHCM, GEOB, GEOBRAS, GB, GEOBRASA, G5SS, INTEGRADOR, INTEGRADORG5, EXTERNO, TERCEIROS, INSTALACAO, G5INT, INTG5, SENIORX5, PLATAFORMA, INFORMACOESINSTAL, SENIORXPLATFORM, SENIORX, SX, SXPLATFORM, RUBIWEB, CBDS, GEOBRASILTECNOLOGIA.
- Somadas à rodada 1 (~20 siglas: SAPIENS, SENIOR, G5, ERP, INTEGRACAO, RUBI, WS, PADRAO, 5ESTRELAS, BSTECH, EASYTECH, GEOBRASIL, HCM, SENIORX, GESTAO, GEO, CBDS, RM, VETORH, WEBSERVICE), já são **~50+ siglas chutadas, todas negativas**.

> **CONCLUSÃO FECHADA: chutar sigla não vai funcionar — desistir dessa abordagem.** A sigla do "Sistema integrado" é um valor cadastrado arbitrariamente no Senior (não é derivável). O caminho é obtê-la com **Matheus/Luan** (pedir a sigla já registrada para a integração `5estrelas.integracao`, ou pedir para registrarem uma), OU abrir o ERP/console Senior e ler a tela de Cadastro de Sistemas Integrados / Parâmetros de Integração. Detalhe técnico de classificação: o erro vem em `<erros><mensagemErro>Sigla de sistema não cadastrada</mensagemErro></erros>`, NÃO em `<mensagemRetorno>` (que fica "Ocorreram erros.") — grep tem que mirar `mensagemErro`.
> Lembrete: o sync de Contas a Pagar **não depende** dessa sigla (ver nota de 23/06/2026 — `ConsultarTitulosAbertosCP` puxa títulos reais por varredura de `codFor`). A sigla só destrava `cad_fornecedor`/`cad_filial` (nomes/CNPJ).

## Sigla `identificadorSistema=EASYTECH` REGISTRADA — teste 23/06/2026 (1º gate vencido, falta o 2º)

> **SIGLA OFICIAL DA INTEGRAÇÃO = `EASYTECH`** (registrada no G5, Empresa 0002, Código 1, Tipo 99-Outros, Situação A-Ativo). 1º gate vencido. Falta ativar os web services individuais dentro do Sistema Integrado EASYTECH (2º gate).

> Executado a partir do servidor de produção `G5E-SVM080` (VPN + SSH `easytech@192.168.254.80`, origem `179.185.83.131`). Tudo READ-ONLY (`ConsultarGeral`). Amostras cruas salvas em `docs/senior/exploracao-2026-06-23/` (`forn-easytech-emp{1,2,3}.xml`, `filial-easytech-emp2.xml`, `filial2-easytech-emp2.xml`).
> Contexto: o cliente registrou no G5 (Empresa 0002) um **"Sistema Integrado" com Sigla=EASYTECH** (Tipo 99-Outros, Situação A-Ativo). Fui testar se isso destrava `cad_fornecedor`/`cad_filial`.

### ✅ ACHADO: a sigla EASYTECH AGORA é reconhecida — o erro `Sigla de sistema não cadastrada` SUMIU
Antes (rodadas de chute), EASYTECH retornava `Sigla de sistema não cadastrada` (`tipoRetorno=-1`). **Agora não retorna mais isso em NENHUMA chamada.** O registro do Sistema Integrado no Senior funcionou — passamos o 1º gate (reconhecimento da sigla).

### ❌ MAS surgiu um 2º gate: o web service precisa ser "parametrizado/ativado" para o Sistema Integrado
Com `identificadorSistema=EASYTECH`:

- **`cad_fornecedor.ConsultarGeral`** (codEmp=1, 2 e 3, codFil=1) → os TRÊS retornam **HTTP 200, `tipoRetorno=0`**, com:
  `erroExecucao = "Ocorreu um erro ao executar o serviço \"Cadastro - Fornecedor - Consultar Geral\": Web service não está parametrizado para ser utilizado."` (sem lista de fornecedores).
- **`cad_filial.ConsultarGeral`**:
  - sem `codFil` (codEmp=2) → `tipoRetorno=-1`, erros: `Código da filial não informado.` **+ `Integração do tipo de informação "1" está inativada.`**
  - com `codFil=1` (codEmp=2) → mesmo padrão do fornecedor: `tipoRetorno=0`, `erroExecucao = "...\"Cadastro - Filial - Consultar Geral\": Web service não está parametrizado para ser utilizado."`

> Ou seja: existe um **2º passo de config do lado Senior** — dentro do Sistema Integrado EASYTECH é preciso **ativar/parametrizar cada web service (tipo de informação)** que queremos consumir (Cadastro Fornecedor, Cadastro Filial, etc.). Registrar a sigla sozinho não basta; cada serviço/“tipo de informação” fica inativo por padrão (mensagens "Web service não está parametrizado para ser utilizado" e "Integração do tipo de informação X está inativada").

### Respostas diretas
1. **EASYTECH funcionou?** Como sigla, SIM — o `Sigla de sistema não cadastrada` acabou. Como liberação de dados, AINDA NÃO.
2. **cad_fornecedor retornou dados?** Não em nenhuma empresa (1/2/3): todas dão "Web service não está parametrizado para ser utilizado".
3. **cad_filial retornou nomes?** Não: mesmo erro de parametrização (+ "tipo de informação 1 inativada" quando falta codFil).
4. **Bottom line:** enriquecimento de fornecedor/filial **NÃO está totalmente destravado ainda**. Falta o cliente/Matheus **ativar os web services (tipos de informação) dentro do Sistema Integrado EASYTECH** no Senior (Cadastro Fornecedor, Cadastro Filial e os demais que formos usar). Feito isso, `cad_fornecedor`/`cad_filial.ConsultarGeral` com `identificadorSistema=EASYTECH` devem passar a listar dados.

### AÇÃO (pedir ao Matheus/Luan)
- No cadastro do **Sistema Integrado EASYTECH** (Empresa 0002), **ativar/parametrizar os web services** desejados: pelo menos `Cadastro - Fornecedor - Consultar Geral` e `Cadastro - Filial - Consultar Geral` (e demais "tipos de informação" que formos consumir). Hoje estão inativos.
- Lembrete: o sync de Contas a Pagar **continua não dependendo disso** (ver nota de 23/06/2026 — `ConsultarTitulosAbertosCP` já puxa títulos reais por varredura de `codFor`, sem sigla). EASYTECH+ativação só destrava nomes/CNPJ de fornecedor/filial.
- Reforço: seguimos **read-only** (`Consultar*`). NUNCA `Gravar*`/`Baixar*`/`Excluir*` sem autorização explícita do Bruno.

## Acessos à interface Senior (UI) — fornecidos pelo Luan (23/06/2026)

> Repo privado — credenciais ficam só aqui (regra do projeto). Estes são acessos à INTERFACE GRÁFICA do Senior (web/HTML5), distintos do usuário técnico de webservices `5estrelas.integracao`.

### Plataforma Senior (Sirius S2 GW02)
- **URL:** https://sirius-s2.seniorcloud.com.br/
- **Login:** estre.estre.modern
- **Senha:** Senhateste1!

### ERP Gestão Empresarial (login interno do ERP)
- **Login:** bruno.easy
- **Senha inicial:** 123456 (TEMPORÁRIA — troca obrigatória no primeiro acesso)
- ⚠️ Quando a senha for trocada, atualizar aqui com a nova (sem expor em chat de grupo).

### Usuário do Luan (referência — máquina dele, acesso cedido durante a config)
- Login plataforma: estre.LUAN
- Servidor/instância exibido no login do ERP: VMTSPLUS0652P
- Base de produção: I250623084614_ERPP (I250623084614_p)
- Empresas operacionais confirmadas: codEmp 0002 = "5 ESTRELAS", codEmp 0003 (com títulos); matriz = filial 0001.

## Mapa de empresas (codEmp → nome) — confirmado 23/06/2026 (tela Seleção Empresa F000EMP)

Lista completa das 12 empresas do grupo 5 Estrelas no Senior (antes só tínhamos inferido codEmp 1–12 pelo oráculo de validação; agora com os nomes reais):

| codEmp | Razão social | Fantasia |
|--------|--------------|----------|
| 1 | Empresa Modelo - 1 | Modelo - 1 (empresa de teste/template — sem títulos) |
| 2 | 5 ESTRELAS SISTEMA DE SEGURANCA LTDA | 5 ESTRELAS (matriz do grupo; tem títulos) |
| 3 | 5 ESTRELAS SERVICOS DE APOIO ADMINISTRATIVO LTDA | SERV APOIO (tem títulos) |
| 4 | ARI CONSTRUTORA E ADMINISTRADORA LTDA | ARI ADM |
| 5 | 5 ESTRELAS REFEICOES COLETIVAS | REFEICOES |
| 6 | 5 ESTRELAS SERVICOS ESPECIALIZADOS | SRV ESPEC |
| 7 | BEST SERVICE - ADMINISTRACAO E EVENTOS EMPRESARIAIS LTDA | BEST |
| 8 | SS SERVICOS DE MANUTENCAO E LIMPEZA LTDA | SS SRV |
| 9 | BALUARTE VIGILANCIA PATRIMONIAL LTDA | BALUARTE |
| 10 | MULTI SEGURANCA ELETRONICA E PATRIMONIAL LTDA | MULTI |
| 11 | STAR SEGURANCA ELETRONICA LTDA | STAR |
| 12 | LSR INCORPORADORA, CONSTRUTORA E IMOBILIARIA EIRELI | LSR |

Notas:
- Confirmamos via SOAP (ConsultarTitulosAbertosCP) títulos em aberto em codEmp **2** e **3**. As demais empresas operacionais (4–12) provavelmente também têm — varrer quando implementar o sync multi-empresa.
- codEmp 1 (Modelo) é template, sem movimento — ignorar no sync.
- A parametrização de web services (F000SXT / F000CWS) é feita por empresa logada; para ativar Fornecedor/Filial nas empresas que vamos consumir, trocar a empresa ativa (tela Seleção Empresa) e repetir, ou usar Duplicar (F000DCI/F000DWS).
- Grupo tem ramos variados: segurança/vigilância (2, 9, 10, 11), serviços/apoio (3, 6, 8), refeições (5), construção/incorporação (4, 12), eventos (7).


## Re-teste pós-config do cliente (F000SXT + F000CWS) — 23/06/2026 — ❌ AINDA NÃO destravou Empresa 3

> Executado a partir do servidor de produção `G5E-SVM080` (VPN + SSH `easytech@192.168.254.80`, origem `179.185.83.131`). Tudo READ-ONLY (`ConsultarGeral`, `identificadorSistema=EASYTECH`, `codFil=1`, `indicePagina=1`, `limitePagina=10`). Amostras cruas salvas em `docs/senior/exploracao-2026-06-23/` (`forn-emp3-pos-config-2026-06-23.xml`, `forn-emp2-pos-config-2026-06-23.xml`, `filial-emp3-pos-config-2026-06-23.xml`).
> Contexto: cliente avisou que concluiu OS DOIS passos de config (ativar tipos de informação em F000SXT para Fornecedor + Empresa/Filial, e configurar o web service em F000CWS). Fui validar `cad_fornecedor.ConsultarGeral` para **Empresa 3 / Filial 1**.

### Resultado por empresa (cad_fornecedor.ConsultarGeral)
| codEmp/codFil | Serviço | HTTP / tempo | tipoRetorno | Mensagem | `<fornecedor>`? |
|---|---|---|---|---|---|
| **3 / 1** (ALVO) | cad_fornecedor | 200 / 11,6s | **0** | `erroExecucao = "...\"Cadastro - Fornecedor - Consultar Geral\": Web service não está parametrizado para ser utilizado."` | vazio `<fornecedor/>` |
| 3 / 1 | cad_filial | 200 / 12,7s | 0 | mesmo `Web service não está parametrizado para ser utilizado` (Filial) | vazio `<filial/>` |
| 2 / 1 | cad_fornecedor | 200 / 27,9s | **-1** | `<erros><mensagemErro>Integração do tipo de informação "52" está inativada.</mensagemErro></erros>` | sem dados |
| 1 / 1 | cad_fornecedor | 200 / 27,9s | 0 | `Web service não está parametrizado...` (Modelo — esperado) | vazio |

### Diagnóstico (a config caiu PARCIAL e NÃO na empresa certa)
- **Empresa 3 (a que precisamos): NADA mudou.** Continua exatamente igual ao teste anterior: tanto `cad_fornecedor` quanto `cad_filial` retornam `Web service não está parametrizado para ser utilizado` (`tipoRetorno=0`). Ou seja, em **Empresa 3 nem o passo F000CWS (parametrizar o web service) foi aplicado** — o serviço sequer está parametrizado.
- **Empresa 2: avançou um passo, mas ainda incompleto.** O erro MUDOU de "Web service não está parametrizado" para **`Integração do tipo de informação "52" está inativada`** (`tipoRetorno=-1`). Isso significa que em **Empresa 2 o web service JÁ foi parametrizado (F000CWS feito)**, mas o **"tipo de informação 52" (Fornecedor) continua INATIVO (F000SXT não ativado para o tipo 52)**.
- **DESCOBERTA-CHAVE: "tipo de informação 52" = Fornecedor (Cadastro Fornecedor).** (Para Filial, no teste anterior de 23/06, o código aparecia como "tipo 1".) Esse número é o que precisa ser **ativado** na tela F000SXT por empresa.
- **Confirma o comportamento per-empresa** (já anotado no Mapa de empresas): a parametrização F000SXT/F000CWS é **por empresa logada**. O cliente aplicou config (parcial) aparentemente só na **Empresa 0002** (onde a sigla EASYTECH foi registrada) e **não na Empresa 3**.

### Respostas diretas (ao pedido do teste)
1. **tipoRetorno / erros:** Empresa 3 → `tipoRetorno=0`, `erroExecucao = "Web service não está parametrizado para ser utilizado"` (sem `<erros>`, `mensagemRetorno` nil).
2. **Retornou `<fornecedor>`?** **NÃO.** `<fornecedor/>` vazio. Nenhum fornecedor (código/nome/CNPJ) veio.
3. **Ainda "não está parametrizado" / "tipo inativado"?** Empresa 3 = exatamente `Web service não está parametrizado para ser utilizado`. Empresa 2 = `Integração do tipo de informação "52" está inativada`.
4. **Bottom line:** enriquecimento de fornecedor **NÃO está funcionando para a Empresa 3.** A config do cliente não chegou na Empresa 3 (web service não parametrizado) e, na Empresa 2, foi parametrizado mas falta ativar o tipo 52 (Fornecedor).

### AÇÃO (pedir ao Matheus/Luan) — agora bem específica
Para CADA empresa que vamos consumir (no mínimo **Empresa 2 e Empresa 3**), dentro do Sistema Integrado **EASYTECH** (trocar a empresa ativa na Seleção de Empresa F000EMP e repetir, ou usar Duplicar F000DCI/F000DWS):
- **F000CWS** — parametrizar/associar o web service `Cadastro - Fornecedor - Consultar Geral` (e `Cadastro - Filial - Consultar Geral`). → resolve o "Web service não está parametrizado" (estado atual da Empresa 3).
- **F000SXT** — **ATIVAR o tipo de informação 52 (Fornecedor)** e o tipo de Filial. → resolve o "tipo de informação 52 está inativada" (estado atual da Empresa 2).
- Salvar e confirmar que a situação fica Ativa. Hoje a Empresa 3 está atrás da Empresa 2 (nem o web service parametrizado).

> Reforços: (a) o sync de **Contas a Pagar não depende disso** — `ConsultarTitulosAbertosCP` já puxa títulos reais por varredura de `codFor` (ver nota de 23/06). Esta config só destrava nomes/CNPJ de fornecedor/filial. (b) Seguimos **read-only** (`Consultar*`). NUNCA `Gravar*`/`Baixar*`/`Excluir*` sem autorização explícita do Bruno.
