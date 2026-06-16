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
> ⚠️ **Chamada real de operação ainda NÃO funcionou (14/06/2026).** Testes exaustivos via HTTP POST cru (sem ext-soap, envelope rpc/literal, namespace `http://services.senior.com.br`, soapAction vazio) do nosso IP de dev **`189.63.13.100`**:
> - XML malformado → **SOAP Fault em 0,2s** (HTTP 500 "Unable to create envelope"). Ou seja, o servlet de front responde.
> - Qualquer operação **bem-formada** (`ConsultarTitulosAbertosCP`, `ConsultarCP`) → **trava, 0 bytes recebidos**, em 45/60/90/150s. Testado: janela de 10 anos / 1 mês / 1 dia (não é volume), data ISO `yyyy-MM-dd` e `dd/MM/yyyy`, com e sem Basic Auth, `codEmp=1`.
> - **Credenciais ERRADAS também travam igual** (sem fault de auth rápido). → a camada de execução da operação NÃO completa pra nossa origem.
>
> **Conclusão (causa raiz provável): nosso IP de origem NÃO está na whitelist da Senior para EXECUÇÃO de operação.** O e-mail da Senior (ticket #1269856, 05/06/2026) diz que os webservices "já estão liberados **para o parceiro Geo Brasil**" — ou seja, liberados para o IP da Geo Brasil, não o nosso. WSDL e o parser de XML são públicos, mas a execução da operação é gated por IP → trava silenciosamente para origens não liberadas. A Senior exige **IP estático (FIXO)** e preenchimento do formulário de liberação (modelo no e-mail da Jessica Lewandovski / Cloud Support).
>
> **O que descartamos:** não é `codEmp`, não é credencial, não é formato de data, não é volume, não é ext-soap. Envelope e contrato estão corretos.
>
> **Próximo passo real:** liberar via formulário Senior o **IP fixo do servidor onde a integração vai rodar** (provavelmente o público de saída da VM de produção `G5E-SVM080`, não o IP dinâmico de dev do Bruno). Depois rodar a consulta read-only de lá. Alternativa: descobrir/usar o IP já liberado (Geo Brasil) se a nossa app for rodar atrás dele. Confirmar com Matheus Xavier qual IP de saída a app usará em produção e abrir o pedido de whitelist.

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
