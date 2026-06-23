# Pesquisa: `identificadorSistema` (Sigla do Sistema de Integração) — Senior G5 ERP 5.10.4

> Pesquisa **só de documentação/internet** (sem chamadas SOAP ao cliente). Feita a partir da doc pública
> `https://documentacao.senior.com.br/gestaoempresarialerp/5.10.4/` via `curl` (o site bloqueia web_fetch por ser JS-rendered).
> Objetivo: descobrir se existe sigla padrão, convenção, e se há WS para LISTAR sistemas sem já ter a sigla.

## 1. O que a doc oficial diz sobre o campo

Da página `com_senior_g5_co_cad_parametrosintegracao.htm` (única que renderizou conteúdo inline):

- **`identificadorSistema`** = **`(Obrigatório) - String(15) - Sigla do Sistema de Integração`**.
- Aparece **em TODAS as operações** do serviço, como primeiro parâmetro de entrada.
- **Não há valor default documentado.** A doc não cita nenhum exemplo de sigla (nem "G5", nem "SENIOR", nem nada). É um campo livre de até 15 caracteres que precisa corresponder a um "Sistema de Integração" **previamente cadastrado no Senior**.
- Tipo de retorno do serviço: `0 = Processado`, `-1 = Erro na Solicitação`. (O erro real visto em produção, `Sigla de sistema não cadastrada` com `tipoRetorno=-1`, bate com "sigla não existe no cadastro".)

## 2. Existe WS para LISTAR os sistemas registrados sem já ter a sigla? — **NÃO**

`com_senior_g5_co_cad_parametrosintegracao` tem **4 operações**, e **todas exigem `identificadorSistema` na entrada**:

| Operação | Função | Exige sigla? |
|----------|--------|--------------|
| `ConsultarGeral`   | Consulta parâmetros de configuração da integração | **Sim** |
| `ConsultarGeral_2` | Idem (overload) | **Sim** |
| `Exportar`         | Exporta parâmetros (Tipo T/A/E) | **Sim** |
| `Gravar`           | **Grava** par chave/valor de config (grupo, chave, valor, codEmp, codFil) | **Sim** |

**Importante:** este serviço **não é o cadastro dos sistemas em si** — ele lê/grava *parâmetros de configuração* (grupo/subgrupo/chave/valor) **de um sistema que já existe**. O `Gravar` aqui **não registra uma sigla nova**; ele só grava config para uma sigla existente (e também exige a sigla).

Varri os 446 serviços do índice (`/tmp/all-services.txt`). Candidatos por nome (`sistema|integ|param|cadsis|sis`):
- `cad_parametrosintegracao` (analisado acima)
- `cad_familiaparametros`, `int_varejo_retornointegracao`, `int_varejo_integracoespendentes`, `mct_ctb_integracao`, etc. — **nenhum** é um "listar sistemas de integração". São integrações de domínios específicos (varejo, contábil), todos presumindo a sigla/contexto.

➡️ **Chicken-and-egg confirmado pela doc:** não há operação read-only que devolva a lista de siglas cadastradas. A sigla é criada/gerida por uma **tela do ERP** (Cadastro de Sistema de Integração), do lado Senior/cliente — não exposta como consulta WS sem já ter uma sigla.

## 3. Convenção do que a sigla "parece"

- Campo **String(15)** → curto, tipicamente um mnemônico em maiúsculas (ex.: nome do sistema externo/parceiro).
- A doc **não** define convenção nem lista valores seed. Não há "sigla de fábrica" garantida.
- Pelo histórico do projeto, a integração que **já funciona** (libera execução de WS) é a do parceiro **Geo Brasil**. É plausível que a(s) sigla(s) cadastrada(s) hoje sejam variações ligadas a esse parceiro — então variações de "Geo Brasil" são os palpites de maior probabilidade.

## 4. Siglas candidatas NOVAS para testar (ainda não testadas)

> Já testadas (todas → "Sigla de sistema não cadastrada"): SAPIENS, SENIOR, G5, ERP, INTEGRACAO, RUBI, WS, PADRAO, 5ESTRELAS, BSTECH, EASYTECH, GEOBRASIL, HCM, SENIORX, GESTAO, GEO, CBDS, RM, VETORH, WEBSERVICE.

Sugeridas (em ordem de probabilidade), respeitando o limite de 15 chars:

**Linha "Geo Brasil" (parceiro que já funciona — mais promissor):**
- `GEOB`, `GB`, `GEOBRAS`, `GEOBRASILRH`, `GEOBRASILERP`, `GEOBR`, `GEO_BRASIL`, `GEOBRASA`

**Linha "G5/Sapiens/integrador genérico":**
- `G5SS` (mencionado no contexto como ligado a CBDS), `G5SENIOR`, `GEMP`, `GESTAOEMP`, `SAPIENSG5`, `INTEGRADOR`, `INTEGRA`, `INTEG`

**Linha "externo/terceiros/api":**
- `EXTERNO`, `TERCEIROS`, `TERCEIRO`, `API`, `REST`, `SOAP`, `DEFAULT`, `PORTAL`

**Linha "5 Estrelas / nosso usuário de integração `5estrelas.integracao`":**
- `5ESTRELASINT`, `INT5E`, `5E`, `5ESTRELASERP`, `MODERNIZACAO`

> Observação: a chance de acerto por chute é baixa (campo livre cadastrado manualmente). Listei para um run autorizado separado; **não testei nada aqui**.

## 5. Bottom line

1. **Existe sigla default documentada?** **Não.** A doc define `identificadorSistema` como `String(15) - Sigla do Sistema de Integração` obrigatório, **sem** valor padrão nem exemplo. Não há "sigla de fábrica".
2. **Existe WS para listar/descobrir os sistemas registrados sem já ter a sigla?** **Não.** As 4 operações de `cad_parametrosintegracao` (ConsultarGeral, ConsultarGeral_2, Exportar, Gravar) **todas** exigem a sigla. Nenhum outro dos 446 serviços lista os sistemas de integração. É chicken-and-egg.
3. **A sigla pode ser descoberta pela doc?** **Não.** É **config do lado Senior/cliente** (tela "Cadastro de Sistema de Integração"), só obtida com quem administra o ERP (**Matheus/Luan**) — ou registrando uma nova via a tela/serviço de escrita (decisão do Bruno, fora do read-only).
4. **Caminho recomendado (sem depender da sigla):** seguir com o sync de Contas a Pagar via `ConsultarTitulosAbertosCP` (que **NÃO** exige `identificadorSistema`), varrendo `codFor` por `codEmp` — já validado em produção (empresas operacionais codEmp 2 e 3). A sigla só é necessária para enriquecer com **nome/CNPJ de fornecedores e filiais** (`cad_fornecedor` / `cad_filial`).

### Ação concreta para o Matheus
Pedir **uma das duas**:
- (a) A **sigla do "Sistema de Integração"** já cadastrada (valor exato de `identificadorSistema`) — de preferência a que a Geo Brasil usa, ou uma nova registrada para `5estrelas.integracao`; **ou**
- (b) Autorização para **registrar nossa própria sigla** (operação de escrita no cadastro de sistemas — decisão do Bruno por ser write).
