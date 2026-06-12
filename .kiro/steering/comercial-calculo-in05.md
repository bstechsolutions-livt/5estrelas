---
inclusion: manual
---

# Motor de Cálculo de Custos — IN 05 e Modelo 5 Estrelas (Documentação Literal para Port PHP/Laravel)

> Fonte da verdade: protótipo `gestao360_5estrelas (2).html` (12.693 linhas).
> Esta documentação transcreve **literalmente** as expressões JavaScript do motor de
> cálculo para que o Service PHP (Laravel) seja portado **1:1**, sem omissões nem erros.
> É cálculo financeiro: cada arredondamento, fallback e ordem de operação importa.

---

## 1. Visão geral — as duas variantes de cálculo

O protótipo tem **dois modelos de precificação independentes**, selecionados por
`selecionarModelo(modelo)`:

| Modelo | Função principal | Quando roda | Característica |
|--------|------------------|-------------|---------------|
| `5estrelas` | `calcular()` → `calcularM2()` → `calcularM3()` | `selecionarModelo('5estrelas')` ou `calcular()` | Calculadora **simplificada** diurno/noturno. Encargos como um único % agregado. |
| `in05` | `calcIN()` | `selecionarModelo('in05')` | **Planilha IN 05 detalhada** por submódulos (1 a 6, com 2.1/2.2/2.3 e 4.1). **FONTE PRINCIPAL DA VERDADE.** |

```js
function selecionarModelo(modelo) {
  ...
  if (modelo === 'in05') calcIN();
  else calcular();
}
```

**Diferença essencial:**

- **`calcular()` (Modelo 5 Estrelas)**: separa colaboradores em turno **diurno** e **noturno**,
  cada turno com qtd e salário próprios. Aplica periculosidade, adicional noturno e
  intrajornada por turno, soma um único percentual de encargos (`#encargos`), adiciona
  benefícios (Módulo 2 simplificado) e aplica adm/lucro/impostos em cascata.
- **`calcIN()` (Modelo IN 05)**: segue a estrutura oficial da Instrução Normativa nº 05
  (Gov. Federal): **um único salário base**, decomposto em Módulos 1 a 6 com submódulos.
  Usa arredondamentos específicos (4 casas para percentuais, 2 casas para valores) e a
  fórmula de preço por empregado com **gross-up de tributos** (`(1+Adm)(1+Lucro)/(1−Trib)`).

> **Qual é a oficial/final?** Para contratos públicos / formação de preço formal, a **IN 05
> (`calcIN`)** é a planilha de referência. O Modelo 5 Estrelas é uma calculadora rápida
> interna. **Portar `calcIN` com prioridade máxima e fidelidade absoluta.** O Modelo
> 5 Estrelas também deve ser portado, pois é usado para cotação rápida.

---

## 2. Inputs — todos os campos de entrada e suas origens

### 2.1 Inputs do Modelo IN 05 (`calcIN`)

Lidos via `gIN(id)` = `parseFloat(document.getElementById(id)?.value || 0)`.
Defaults abaixo são os atributos `value=""` no HTML do protótipo.

| Campo (id) | Rótulo | Default | Unidade | Origem |
|------------|--------|---------|---------|--------|
| `in-sal` | Salário Base | `1850.00` | R$ | Input usuário (pode vir da CCT/categoria) |
| `in-peric-pct` | Adicional Periculosidade | `0` | % | Input usuário |
| `in-insal-pct` | Adicional Insalubridade | `0` | % | Input usuário |
| `in-an-pct` | Adicional Noturno | `0` | % | Input usuário |
| `in-hnr-pct` | Adicional Hora Noturna Reduzida | `0` | % | Input usuário |
| `in-outros1-pct` | Outros (Módulo 1) | `0` | % | Input usuário |
| `in-inss-pct` | INSS | `20` | % | Input usuário |
| `in-saledu-pct` | Salário Educação | `2.5` | % | Input usuário |
| `in-sat-pct` | SAT (RAT × FAP) | `3.28` | % | Input usuário |
| `in-sesc-pct` | SESC/SESI | `1.5` | % | Input usuário |
| `in-senai-pct` | SENAI/SENAC | `1` | % | Input usuário |
| `in-sebrae-pct` | SEBRAE | `0.6` | % | Input usuário |
| `in-incra-pct` | INCRA | `0.2` | % | Input usuário |
| `in-fgts-pct` | FGTS | `8` | % | Input usuário (reusado no Módulo 3) |
| `in-vt-dia` | Vale-Transporte (R$/dia) | `10.40` | R$/dia | Input usuário |
| `in-va-dia` | Vale-Alimentação/Refeição (R$/dia) | `30.00` | R$/dia | Input usuário |
| `in-medico` | Assistência Médica | `0` | R$ | Input usuário |
| `in-odonto` | Assistência Odontológica | `0` | R$ | Input usuário |
| `in-cesta` | Cesta Básica | `0` | R$ | Input usuário |
| `in-seguro` | Seguro de Vida | `14.20` | R$ | Input usuário |
| `in-pmq` | PMQ — Qualificação Profissional | `0` | R$ | Input usuário |
| `in-outros23` | Outros / Contribuição Patronal | `0` | R$ | Input usuário |
| `in-avisoind-pct` | Aviso Prévio Indenizado | `1` | % | Input usuário |
| `in-avistrab-pct` | Aviso Prévio Trabalhado | `0.59` | % | Input usuário |
| `in-ausleg-pct` | Substituto — Ausências Legais | `0.1` | % | Input usuário |
| `in-paterni-pct` | Substituto — Licença Paternidade | `0.02` | % | Input usuário |
| `in-acident-pct` | Substituto — Acidente Trabalho | `0.1` | % | Input usuário |
| `in-matern-pct` | Substituto — Afast. Maternidade | `0.02` | % | Input usuário |
| `in-intrajornada` | Substituto — Intervalo Repouso/Alimentação | `0` | R$ | Input usuário |
| `in-uniforme` | Uniformes (Cláusula 52ª) | `89.50` | R$ | Input usuário |
| `in-materiais` | Materiais | `0` | R$ | Input usuário |
| `in-ferramental` | Ferramental | `0` | R$ | Input usuário |
| `in-epi` | EPIs | `0` | R$ | Input usuário |
| `in-treinamento` | Outros (treinamento/reciclagem) | `0` | R$ | Input usuário |
| `in-sso` | SSO — Saúde e Seg. Ocupacional (Cláusula 55ª) | `18.00` | R$ | Input usuário |
| `in-custoind-pct` | Custos Indiretos | `5` | % | Input usuário |
| `in-lucro-pct` | Lucro | `3` | % | Input usuário |
| `in-iss-pct` | Tributos — ISS | `5` | % | Input usuário |
| `in-pis-pct` | PIS | `1.65` | % | Input usuário |
| `in-cofins-pct` | COFINS | `7.6` | % | Input usuário |

**Inputs derivados de ESCALAS (não de campos):**

```js
const esc     = ESCALAS[escSel] || ESCALAS['24h'];  // escSel default = '24h'
const diasMes = esc.dias_mes || 15.5;
const horMes  = esc.horas_mes || 220;
```

- `escSel` é variável global (`let escSel = '24h'`), definida pela escala selecionada na UI.
- `diasMes` e `horMes` **vêm exclusivamente da escala** no IN 05 (não da CCT como no Modelo 5 Estrelas).

### 2.2 Inputs do Modelo 5 Estrelas (`calcular`)

Lidos via `get(id)` = `parseFloat(document.getElementById(id)?.value || 0)`.

| Campo (id) | Rótulo | Default | Origem |
|------------|--------|---------|--------|
| `qtd-diurno` | Qtd colaboradores diurno | — | Input usuário |
| `sal-diurno` | Salário diurno | — | Input usuário / CCT |
| `qtd-noturno` | Qtd colaboradores noturno | — | Input usuário |
| `sal-noturno` | Salário noturno | — | Input usuário / CCT |
| `an-diurno` | Flag adicional noturno diurno | `0` (`parseInt(... || 0)`) | Input usuário |
| `an-noturno` | Flag adicional noturno noturno | `1` (`parseInt(... || 1)`) | Input usuário |
| `encargos` | % encargos agregado | `72.11` | Input usuário / `recalcEncargos()` |
| `pct-adm` | % administração | `5.00` | Input usuário |
| `pct-lucro` | % lucro | `3.00` | Input usuário |
| `pct-impostos` | % impostos | `8.65` | Input usuário |
| `b-uniforme` | Uniforme (unit.) | `89.50` | Input / aba Insumos |
| `b-saude` | Plano saúde (unit.) | `242.00` | Input |
| `b-fundo` | Fundo (unit.) | `31.50` | Input |
| `b-sst` | SST (unit.) | `18.00` | Input |
| `b-cna` | CNA (unit.) | `22.00` | Input |
| `b-seguro` | Seguro (unit.) | `14.20` | Input |
| `b-gta` | GTA (unit.) | `47.00` | Input / aba Insumos |
| `b-cofre` | Cofre (unit.) | `55.00` | Input / aba Insumos |
| `b-arma` | Arma (unit.) | `126.00` | Input / aba Insumos |
| `b-reciclag` | Reciclagem (unit.) | `32.00` | Input / aba Insumos |
| `b-vt` | Vale-transporte (unit./dia) | `10.40` | Input |
| `b-va` | Vale-alimentação (unit./dia) | `30.00` | Input |
| `gt5-meses` | Meses p/ valor anual | `12` (`parseInt(... || 12)`) | Input usuário |

**Parâmetros via `getCCT(k)` (ver §11):** `dias_mes`, `horas_mes`, `peric`, `intra_h`, `desc_vt`.
Adicional noturno fixo: `anPct = 20/100` (20% sobre a hora). Horas-mês fallback: `220`.

---

## 3. Estrutura de Módulos IN 05 — fórmulas exatas (`calcIN`)

> Toda a função `calcIN()` está transcrita por módulo. Variáveis `gIN(id)` leem inputs;
> `sIN(id,v)` escreve valores R$ formatados; `pIN(id,v)` escreve percentuais com 4 casas + '%'.

### Preâmbulo

```js
const sal     = gIN('in-sal');
const esc     = ESCALAS[escSel] || ESCALAS['24h'];
const diasMes = esc.dias_mes || 15.5;
const horMes  = esc.horas_mes || 220;
```

### MÓDULO 1 — Composição da Remuneração

```js
const peric   = sal * (gIN('in-peric-pct') / 100);
const insal   = sal * (gIN('in-insal-pct') / 100);
const anVal   = Math.round((sal + peric) / horMes * (gIN('in-an-pct') / 100) * 8 * diasMes * 100) / 100;
const hnrVal  = sal * (gIN('in-hnr-pct') / 100);
const out1    = sal * (gIN('in-outros1-pct') / 100);
const m1      = sal + peric + insal + anVal + hnrVal + out1;
```

| Item | Fórmula matemática | Notas |
|------|--------------------|-------|
| Periculosidade (`peric`) | `peric = sal × (peric% / 100)` | — |
| Insalubridade (`insal`) | `insal = sal × (insal% / 100)` | — |
| Adicional Noturno (`anVal`) | `anVal = round₂( (sal+peric) / horMes × (an%/100) × 8 × diasMes )` | round a 2 casas (×100/100). Base = sal+peric (NÃO inclui insal) |
| Hora Noturna Reduzida (`hnrVal`) | `hnrVal = sal × (hnr%/100)` | — |
| Outros (`out1`) | `out1 = sal × (outros1%/100)` | — |
| **TOTAL M1 (`m1`)** | `m1 = sal + peric + insal + anVal + hnrVal + out1` | **Base de quase todos os módulos** |

> ⚠️ `m1` **não é arredondado** (só `anVal` é arredondado internamente). Em PHP usar
> `bcmath`/float com cuidado; replicar `round()` somente onde o JS chama `Math.round`.

### MÓDULO 2 — Encargos e Benefícios

`m2 = sub21 + sub22 + sub23` (note a ordem de CÁLCULO no código: 2.2 antes de 2.1).

#### Submódulo 2.2 — GPS/FGTS (Encargos previdenciários) — base = m1

```js
const inss   = Math.round(m1 * (gIN('in-inss-pct')   / 100) * 10000) / 10000;
const saledu = Math.round(m1 * (gIN('in-saledu-pct') / 100) * 10000) / 10000;
const sat    = Math.round(m1 * (gIN('in-sat-pct')    / 100) * 10000) / 10000;
const sesc   = Math.round(m1 * (gIN('in-sesc-pct')   / 100) * 10000) / 10000;
const senai  = Math.round(m1 * (gIN('in-senai-pct')  / 100) * 10000) / 10000;
const sebrae = Math.round(m1 * (gIN('in-sebrae-pct') / 100) * 10000) / 10000;
const incra  = Math.round(m1 * (gIN('in-incra-pct')  / 100) * 10000) / 10000;
const fgts   = Math.round(m1 * (gIN('in-fgts-pct')   / 100) * 10000) / 10000;
const sub22  = inss + saledu + sat + sesc + senai + sebrae + incra + fgts;
const pct22  = sub22 / m1;  // total % encargos sobre m1
```

- Cada item: `valor = round₄( m1 × (pct/100) )` — **arredondado a 4 casas** (`×10000/10000`).
- `sub22` = soma dos 8 itens.
- **`pct22 = sub22 / m1`** — fração (não %) usada como base de incidência nos Módulos 2.1, 3 e 4. **Crítico.**

#### Submódulo 2.1 — 13º Salário e Férias — base = m1

```js
const p13        = 0.0833;
const pFer       = Math.round(p13 / 3 * 10000) / 10000;          // = 0.0278
const pInc21     = Math.round((p13 + pFer) * pct22 * 10000) / 10000;
const pMultaFgts = Math.round(pFer * 0.032 * 10000) / 10000;
const v13        = m1 * p13;
const vFer       = m1 * pFer;
const vInc21     = m1 * pInc21;
const vMultaFgts = m1 * pMultaFgts;
const sub21      = v13 + vFer + vInc21 + vMultaFgts;
```

| Item | Percentual | Valor |
|------|-----------|-------|
| 13º Salário | `p13 = 0.0833` (constante fixa) | `v13 = m1 × 0.0833` |
| Férias | `pFer = round₄(0.0833 / 3)` ≈ `0.0278` | `vFer = m1 × pFer` |
| Incidência encargos s/ 13º+Férias | `pInc21 = round₄((p13+pFer) × pct22)` | `vInc21 = m1 × pInc21` |
| Multa FGTS s/ Férias | `pMultaFgts = round₄(pFer × 0.032)` | `vMultaFgts = m1 × pMultaFgts` |
| **sub21** | — | `v13 + vFer + vInc21 + vMultaFgts` |

> `p13 = 0.0833` é **hardcoded** (≈ 1/12). `0.032` (3,2%) também é hardcoded. **Documentar como constantes.**

#### Submódulo 2.3 — Benefícios

```js
const vtBruto = gIN('in-vt-dia') * diasMes;
const vtDesc  = sal * 0.06;
const vtLiq   = Math.round(Math.max(vtBruto - vtDesc, 0) * 100) / 100;
const vaVal   = Math.round(gIN('in-va-dia') * diasMes * 100) / 100;
const medico  = gIN('in-medico');
const odonto  = gIN('in-odonto');
const cesta   = gIN('in-cesta');
const seguro  = gIN('in-seguro');
const pmq     = gIN('in-pmq');
const out23   = gIN('in-outros23');
const sub23   = vtLiq + vaVal + medico + odonto + cesta + seguro + pmq + out23;
```

| Item | Fórmula |
|------|---------|
| VT bruto | `vtBruto = vt_dia × diasMes` |
| VT desconto | `vtDesc = sal × 0.06` (6% do salário, hardcoded) |
| **VT líquido** | `vtLiq = round₂( max(vtBruto − vtDesc, 0) )` — **nunca negativo** |
| VA | `vaVal = round₂( va_dia × diasMes )` |
| Médico/Odonto/Cesta/Seguro/PMQ/Outros | valores diretos dos inputs (sem transformação) |
| **sub23** | soma de todos acima |

```js
const m2 = sub21 + sub22 + sub23;
```

### MÓDULO 3 — Provisão para Rescisão — base = m1

```js
const pAvisoInd  = gIN('in-avisoind-pct') / 100;
const pFgtsPct   = gIN('in-fgts-pct') / 100;                         // reusa FGTS do 2.2
const pFgtsAviso = Math.round(pAvisoInd * pFgtsPct * 10000) / 10000;
const pAvisTrab  = gIN('in-avistrab-pct') / 100;
const pMultaInd  = Math.round(pAvisoInd * 0.40 * 10000) / 10000;     // Multa FGTS s/ Aviso Indenizado
const pMultaResc = Math.round(pFgtsPct  * 0.40 * 10000) / 10000;     // Multa FGTS rescisão
const pIncGPS    = Math.round(pAvisTrab * pct22 * 10000) / 10000;    // Incid. GPS/FGTS s/ Aviso Trabalhado

const vAvisoInd  = Math.round(m1 * pAvisoInd  * 100) / 100;
const vFgtsAviso = Math.round(m1 * pFgtsAviso * 100) / 100;
const vAvisTrab  = Math.round(m1 * pAvisTrab  * 100) / 100;
const vMultaInd  = Math.round(m1 * pMultaInd  * 100) / 100;
const vMultaResc = Math.round(m1 * pMultaResc * 100) / 100;
const vIncGPS    = Math.round(m1 * pIncGPS    * 100) / 100;
const m3 = vAvisoInd + vFgtsAviso + vAvisTrab + vMultaInd + vMultaResc + vIncGPS;
```

| Item | Percentual (round₄) | Valor (round₂ sobre m1) |
|------|---------------------|--------------------------|
| Aviso Prévio Indenizado | `pAvisoInd = avisoind%/100` (direto) | `vAvisoInd = round₂(m1 × pAvisoInd)` |
| FGTS s/ Aviso Indenizado | `pFgtsAviso = round₄(pAvisoInd × pFgtsPct)` | `vFgtsAviso = round₂(m1 × pFgtsAviso)` |
| Aviso Prévio Trabalhado | `pAvisTrab = avistrab%/100` (direto) | `vAvisTrab = round₂(m1 × pAvisTrab)` |
| Multa FGTS s/ Aviso Indenizado | `pMultaInd = round₄(pAvisoInd × 0.40)` | `vMultaInd = round₂(m1 × pMultaInd)` |
| Multa FGTS Rescisão | `pMultaResc = round₄(pFgtsPct × 0.40)` | `vMultaResc = round₂(m1 × pMultaResc)` |
| Incid. GPS/FGTS s/ Aviso Trabalhado | `pIncGPS = round₄(pAvisTrab × pct22)` | `vIncGPS = round₂(m1 × pIncGPS)` |
| **TOTAL M3** | — | soma dos 6 valores |

> `0.40` (40% multa FGTS) é hardcoded. `pFgtsPct` reusa **o mesmo input** `in-fgts-pct` do Módulo 2.2.

### MÓDULO 4 — Reposição do Profissional Ausente — base = m1

```js
const pCobFer = Math.round(((p13 + pFer) / 12 + p13) * 10000) / 10000;   // Cobertura Férias
const pAusleg = gIN('in-ausleg-pct') / 100;
const pPatern = gIN('in-paterni-pct') / 100;
const pAcident= gIN('in-acident-pct') / 100;
const pMatern = gIN('in-matern-pct')  / 100;
const subtot41pct = pCobFer + pAusleg + pPatern + pAcident + pMatern;

// Incidência (2.2 + 2.1) sobre ausências
const pct21total = (p13 + pFer) * pct22 + (p13 + pFer);  // encargos sobre 13º e férias
const pIncAus = Math.round(subtot41pct * (pct22 + pct21total) * 10000) / 10000;

const vCobFer = Math.round(m1 * pCobFer  * 100) / 100;
const vAusleg = Math.round(m1 * pAusleg  * 100) / 100;
const vPatern = Math.round(m1 * pPatern  * 100) / 100;
const vAcident= Math.round(m1 * pAcident * 100) / 100;
const vMatern = Math.round(m1 * pMatern  * 100) / 100;
const sub41   = vCobFer + vAusleg + vPatern + vAcident + vMatern;
const vIncAus = Math.round(m1 * pIncAus  * 100) / 100;
const tot41   = sub41 + vIncAus;
const m4intra = gIN('in-intrajornada');
const m4      = tot41 + m4intra;
```

#### Submódulo 4.1 — Substitutos (percentuais sobre m1)

| Item | Percentual | Valor |
|------|-----------|-------|
| Cobertura Férias | `pCobFer = round₄( (p13+pFer)/12 + p13 )` | `vCobFer = round₂(m1 × pCobFer)` |
| Ausências Legais | `pAusleg = ausleg%/100` | `vAusleg = round₂(m1 × pAusleg)` |
| Licença Paternidade | `pPatern = paterni%/100` | `vPatern = round₂(m1 × pPatern)` |
| Acidente Trabalho | `pAcident = acident%/100` | `vAcident = round₂(m1 × pAcident)` |
| Afast. Maternidade | `pMatern = matern%/100` | `vMatern = round₂(m1 × pMatern)` |
| **sub41** | — | soma dos 5 valores |

#### Incidência sobre ausências (item F)

- `subtot41pct = pCobFer + pAusleg + pPatern + pAcident + pMatern`
- `pct21total = (p13+pFer) × pct22 + (p13+pFer)` — fração de encargos sobre 13º+férias.
- `pIncAus = round₄( subtot41pct × (pct22 + pct21total) )`
- `vIncAus = round₂(m1 × pIncAus)`
- `tot41 = sub41 + vIncAus`

#### Submódulo 4.2 — Intrajornada (valor R$ direto)

- `m4intra = gIN('in-intrajornada')` (input em R$, sem transformação)
- **TOTAL M4: `m4 = tot41 + m4intra`**

### MÓDULO 5 — Insumos

```js
const m5 = gIN('in-uniforme') + gIN('in-materiais') + gIN('in-ferramental') +
           gIN('in-epi') + gIN('in-treinamento') + gIN('in-sso');
```

- Soma direta de 6 inputs em R$. **Sem percentuais, sem arredondamento adicional.**

### MÓDULO 6 — Custos Indiretos, Tributos e Lucro

```js
const subtotal = m1 + m2 + m3 + m4 + m5;
const pCind    = gIN('in-custoind-pct') / 100;
const pLucro   = gIN('in-lucro-pct')   / 100;
const pISS     = gIN('in-iss-pct')     / 100;
const pPIS     = gIN('in-pis-pct')     / 100;
const pCOFINS  = gIN('in-cofins-pct')  / 100;
const pTrib    = pISS + pPIS + pCOFINS;

// Preço por empregado com gross-up de tributos
const d140     = (1 + pCind) * (1 + pLucro) / (1 - pTrib) - 1;
const precoEmp = Math.round(subtotal * (1 + d140) * 100) / 100;

// Valores individuais (apenas para exibição)
const vCind    = Math.round(subtotal * pCind  * 100) / 100;
const vLucro   = Math.round((subtotal + vCind) * pLucro * 100) / 100;
const vISS     = Math.round(precoEmp * pISS   * 100) / 100;
const vPIS     = Math.round(precoEmp * pPIS   * 100) / 100;
const vCOFINS  = Math.round(precoEmp * pCOFINS * 100) / 100;
const m6       = vCind + vLucro + vISS + vPIS + vCOFINS;
```

| Item | Fórmula |
|------|---------|
| Subtotal (M1..M5) | `subtotal = m1 + m2 + m3 + m4 + m5` |
| Fator gross-up | `d140 = (1+Adm)·(1+Lucro)/(1−Trib) − 1` |
| **Preço por empregado** | `precoEmp = round₂( subtotal × (1 + d140) )` |
| Custos Indiretos (exib.) | `vCind = round₂(subtotal × Adm)` |
| Lucro (exib.) | `vLucro = round₂( (subtotal + vCind) × Lucro )` — incide sobre subtotal+Adm |
| ISS (exib.) | `vISS = round₂(precoEmp × ISS)` — incide sobre o PREÇO final |
| PIS (exib.) | `vPIS = round₂(precoEmp × PIS)` |
| COFINS (exib.) | `vCOFINS = round₂(precoEmp × COFINS)` |
| M6 (exib.) | `vCind + vLucro + vISS + vPIS + vCOFINS` |

> ⚠️ **Importante:** o valor final do posto é `precoEmp` (calculado via `d140`), **não** `subtotal + m6`.
> O `m6` exibido é apenas a decomposição visual e pode divergir levemente de `precoEmp − subtotal`
> por arredondamentos. **O número oficial é `precoEmp`.** `pTrib` deve ser < 1 (senão divisão por zero/negativo).

---

## 4. Encargos A/B/C/D (`recalcEncargos`) — Modelo 5 Estrelas

> Este detalhamento alimenta o **campo único `#encargos`** do Modelo 5 Estrelas (calculadora
> simplificada). **Não é usado pelo IN 05** (que detalha encargos nos Módulos 2/3/4).

```js
function recalcEncargos() {
  const g   = id => parseFloat(document.getElementById(id)?.value || 0);
  const totA = g('enc-a01')+g('enc-a02')+...+g('enc-a08');   // 8 itens
  const totB = g('enc-b01')+g('enc-b02')+...+g('enc-b08');   // 8 itens
  const totC = g('enc-c01')+g('enc-c02')+g('enc-c03');       // 3 itens
  const totD = g('enc-d01');                                  // 1 item
  const tot  = totA+totB+totC+totD;
  ...
  const encEl = document.getElementById('encargos');
  if (encEl) encEl.value = tot.toFixed(2);
  return tot;
}
```

| Grupo | Itens | Defaults (%) | Total default |
|-------|-------|--------------|---------------|
| **A** — Encargos Sociais Básicos | a01 INSS `20.00`, a02 FGTS `8.00`, a03 Sal.Educ `2.50`, a04 SESI `1.50`, a05 SENAI `1.00`, a06 INCRA `0.20`, a07 SAT (RAT 3%×FAP 1,00) `2.11`, a08 SEBRAE `0.60` | — | ~35.91 |
| **B** — 13º, Férias e Afastamentos | b01 13º `8.93`, b02 Férias `9.09`, b03 Abono Férias 1/3 `3.03`, b04 Aux.Doença `2.85`, b05 Pat/Maternidade `0.85`, b06 Faltas Legais `2.38`, b07 Acid.Trab 15d `0.75`, b08 Aviso Prévio Indenizado `2.37` | — | ~30.25 |
| **C** — Rescisão | c01 Aviso Prévio Trabalhado `1.85`, c02 Indeniz. Adicional `0.22`, c03 Multa FGTS rescisão (40%+10%) `4.00` | — | ~6.07 |
| **D** — Incidências Cumulativas | d01 "Incidência do Grupo A sobre o Grupo B" `9.77` | — | 9.77 |
| **TOTAL** | `tot = A + B + C + D` | — | ~82.00 |

> ⚠️ **Grupo D NÃO é auto-calculado** no protótipo: `enc-d01` é um **input manual** (default
> `9.77`), apesar do rótulo dizer "Incidência do Grupo A sobre o Grupo B". A soma é
> simplesmente `totA + totB + totC + totD`. Não há fórmula de produto A×B no código.
> O total é gravado em `localStorage('gestao360_encargos')` e aplicado em `#encargos` via
> `aplicarEncargosNaCotacao()` (`el.value = tot.toFixed(2)`).
> `getEncargosTotal()` retorna a soma do localStorage ou **fallback `82.00`**.

---

## 5. Ordem de cálculo e cascata

### 5.1 IN 05 (`calcIN`) — sequência exata

```
1. sal, esc, diasMes, horMes
2. MÓDULO 1: peric, insal, anVal, hnrVal, out1 → m1
3. MÓDULO 2.2 (GPS/FGTS): inss..fgts → sub22 ; pct22 = sub22/m1
4. MÓDULO 2.1 (13º/Férias): usa pct22 → sub21
5. MÓDULO 2.3 (Benefícios): vtLiq, vaVal, ... → sub23
6. m2 = sub21 + sub22 + sub23
7. MÓDULO 3 (Rescisão): usa pct22 e pFgtsPct → m3
8. MÓDULO 4 (Ausências): usa pct22, p13, pFer → m4 = tot41 + intrajornada
9. MÓDULO 5 (Insumos): m5
10. MÓDULO 6: subtotal = m1+m2+m3+m4+m5
              d140 = (1+Adm)(1+Lucro)/(1−Trib) − 1
              precoEmp = round₂(subtotal × (1+d140))   ← VALOR OFICIAL DO POSTO
11. QUADRO RESUMO: m1..m6, subtotal, precoEmp
12. BLOCO TOTAL GERAL: mensal = precoEmp ; anual = precoEmp × mesesIN
```

> **Dependências críticas de ordem:** `pct22` (passo 3) é usado nos passos 4, 7 e 8.
> Calcular 2.2 **antes** de 2.1. `pFer`/`p13` (passo 4) são usados no passo 8.

### 5.2 Modelo 5 Estrelas — cascata (`calcular` → `calcularM2` → `calcularM3`)

```js
// calcular()
const remTotal = totD + totN;                 // soma turnos
const encVal   = remTotal * (encargos/100);
const m1       = remTotal + encVal;            // M1 = remuneração + encargos
window._m1 = m1; ... calcularM2();

// calcularM2()  → benefícios
let m2 = Σ (b-<id> × mult);  // mult: VT/VA = diasMes×totalFunc ; gta/cofre/arma = 1 ; demais = totalFunc
m2 -= descVT;                // desconto VT (ver §6)
m2 += getM2ExtrasTotal();    // itens extras (valor × totalFunc)
window._m2 = m2; calcularM3();

// calcularM3()
const base   = m1 + m2;
const vAdm   = base * pAdm;
const vLucro = (base + vAdm) * pLucro;         // lucro incide sobre base+adm
const total3 = base + vAdm + vLucro;
const vImp   = total3 * pImp;                  // impostos sobre total3
const grandTotal = total3 + vImp;
const valorPessoa = totalFunc > 0 ? grandTotal / totalFunc : 0;
// mensal = grandTotal ; anual = grandTotal × meses (gt5-meses || 12)
```

| Etapa | Fórmula |
|-------|---------|
| Remuneração total | `remTotal = totD + totN` |
| Encargos | `encVal = remTotal × (encargos/100)` |
| **M1** | `m1 = remTotal + encVal` |
| **M2** | `Σ(unit × mult) − descVT + extras` |
| Base | `base = m1 + m2` |
| Adm | `vAdm = base × pAdm` |
| Lucro | `vLucro = (base + vAdm) × pLucro` |
| total3 | `total3 = base + vAdm + vLucro` |
| Impostos | `vImp = total3 × pImp` |
| **Grand Total (mensal)** | `grandTotal = total3 + vImp` |
| Valor por pessoa | `grandTotal / totalFunc` (0 se totalFunc=0) |
| Anual | `grandTotal × meses` |

> **Diferença-chave vs IN 05:** no Modelo 5 Estrelas os impostos são aplicados por
> **multiplicação direta** (`total3 × pImp`), **não** por gross-up `/(1−Trib)`. São
> motores distintos — portar ambos como estão.

---

## 6. Fórmulas de periculosidade, adicional noturno, intrajornada e desconto VT

### 6.1 Periculosidade

- **IN 05:** `peric = sal × (in-peric-pct / 100)`
- **5 Estrelas:** `pericD = salD × pericPct` ; `pericN = salN × pericPct`, onde `pericPct = (getCCT('peric') || 0)/100`

### 6.2 Adicional Noturno

- **IN 05:** `anVal = round₂( (sal + peric) / horMes × (in-an-pct/100) × 8 × diasMes )`
  - Base: `sal + peric`. Fator `8` (horas) e `diasMes`. Arredonda a 2 casas.
- **5 Estrelas (diurno):** `adnD = anD ? (salD + pericD) / horasMes × anPct × 8 × diasMes : 0`
- **5 Estrelas (noturno):** `adnNVal = anN ? (salN + pericN) / horasMes × anPct × 8 × diasMes : 0`
  - `anPct = 20/100` **fixo (20% sobre a hora)**. `anD`/`anN` são flags (0/1) que ligam/desligam.

### 6.3 Intrajornada

- **5 Estrelas (diurno):** `intraD = (salD + pericD + adnD) / horasMes × intraH × diasMes`
- **5 Estrelas (noturno):** `intraN = (salN + pericN + adnNVal) / horasMes × intraH × diasMes`
  - `intraH = getCCT('intra_h') || 1.5` (horas de intervalo, default 1,5h).
  - Base: salário + periculosidade + adicional noturno.
- **IN 05:** intrajornada é **valor R$ direto** no input `in-intrajornada` (Módulo 4.2), sem fórmula.

### 6.4 Desconto VT

- **IN 05 (2.3-A):** `vtDesc = sal × 0.06` ; `vtLiq = round₂( max(vt_dia×diasMes − vtDesc, 0) )` — **6% hardcoded, nunca negativo**.
- **5 Estrelas (`calcularM2`):**
  ```js
  const descVTPct = (getCCT('desc_vt') || 6) / 100;   // default 6%
  const descVT    = descVTPct * salBase * totalFunc;  // % × salário × nº func
  m2 -= descVT;
  ```
  Onde `salBase = window._salBase || sal-diurno || sal-noturno`.

---

## 7. VA / VT — multiplicadores

### 7.1 Modelo 5 Estrelas (`calcularM2`)

Lista de benefícios e multiplicadores:

```js
const bens = [
  { id:'uniforme', mult: totalFunc }, { id:'saude', mult: totalFunc },
  { id:'fundo',    mult: totalFunc }, { id:'sst',   mult: totalFunc },
  { id:'cna',      mult: totalFunc }, { id:'seguro',mult: totalFunc },
  { id:'gta',      mult: 1 },         { id:'cofre', mult: 1 },
  { id:'arma',     mult: 1 },         { id:'reciclag', mult: totalFunc },
  { id:'vt',       mult: diasMes * totalFunc },
  { id:'va',       mult: diasMes * totalFunc },
];
// tot = unit × mult ; m2 = Σ tot
```

| Benefício | Multiplicador |
|-----------|---------------|
| uniforme, saude, fundo, sst, cna, seguro, reciclag | `× totalFunc` |
| gta, cofre, arma | `× 1` (por posto, não por funcionário) |
| **vt** | `× (diasMes × totalFunc)` |
| **va** | `× (diasMes × totalFunc)` |

- `totalFunc = window._totalFunc || 4` ; `diasMes = window._diasMes || 15.5`.
- VA exibido no quadro: `vaTotal = b-va × (diasMes || 15.5) × totalFunc`.

### 7.2 IN 05

- **VT:** `vtBruto = vt_dia × diasMes`, depois desconto 6% (§6.4). **Por empregado** (a planilha IN é por empregado; multiplicação por nº de postos é feita fora do motor).
- **VA:** `vaVal = round₂( va_dia × diasMes )`. **Por empregado.**

> ⚠️ **No IN 05 o motor calcula custo POR EMPREGADO.** A multiplicação pelo número de
> postos/funcionários é responsabilidade da camada superior, não de `calcIN`.

---

## 8. Saídas

### 8.1 IN 05

| Saída | Variável | Onde aparece |
|-------|----------|--------------|
| Total Módulo 1 | `m1` | `in-tot-m1`, `in-tot-m1-box`, `in-res-m1`, `gtin-m1` |
| Total Módulo 2 | `m2` | `in-tot-m2`, `in-res-m2`, `gtin-m2` |
| Total Módulo 3 | `m3` | `in-tot-m3`, `in-res-m3`, `gtin-m3` |
| Total Módulo 4 | `m4` | `in-tot-m4`, `in-res-m4`, `gtin-m4` |
| Total Módulo 5 | `m5` | `in-tot-m5`, `in-res-m5`, `gtin-m5` |
| Total Módulo 6 | `m6` | `in-tot-m6`, `in-res-m6`, `gtin-m6` |
| Subtotal (M1..M5) | `subtotal` | `in-subtotal`, `gtin-subtotal` |
| **Valor por empregado (posto)** | `precoEmp` | `in-valor-total-emp`, `in-grand-total`, `in-preco-emp`, `gtin-mensal` |
| Mensal | `precoEmp` | `gtin-mensal` |
| **Anual** | `precoEmp × mesesIN` | `gtin-anual` (`mesesIN = gtin-meses || 12`) |
| VA (resumo) | `vaVal` | `gtin-va` |
| Globais | `window._grandTotal = precoEmp` ; `window._vaTotal = vaVal` | usados pelo sistema de itens/propostas |

### 8.2 Modelo 5 Estrelas

| Saída | Variável |
|-------|----------|
| Total M1 | `m1` (`total-m1`, `total-m1-box`, `gt5-m1`) |
| Total M2 | `m2` (`total-m2`, `gt5-m2`) |
| Total M3 (adm+lucro+imp) | `vAdm + vLucro + vImp` (`total-m3`, `gt5-m3`) |
| **Mensal** | `grandTotal` (`gt5-mensal`) |
| **Anual** | `grandTotal × meses` (`gt5-anual`) |
| VA total | `vaTotal` (`gt5-va`) |
| Valor por func. | `grandTotal / totalFunc` (`gt5-func-info`) |
| Globais | `window._grandTotal = grandTotal` ; `window._vaTotal = vaTotal` |

---

## 9. Casos especiais, arredondamentos e fallbacks

### 9.1 Arredondamentos

| Padrão JS | Significado | Onde |
|-----------|-------------|------|
| `Math.round(x * 10000) / 10000` | **4 casas decimais** (percentuais/frações) | Todos os `pct` e itens de 2.2 |
| `Math.round(x * 100) / 100` | **2 casas decimais** (valores R$) | `anVal`, valores de M3/M4, `vtLiq`, `vaVal`, `precoEmp`, `vCind`, etc. |
| `toFixed(2)` | string com 2 casas | `#encargos` em `recalcEncargos` (`tot.toFixed(2)`) |
| `toFixed(4) + '%'` | `pIN()` exibe percentual com 4 casas | campos `*-pct` readonly |
| sem arredondamento | float puro | `m1`, `sub21`, `sub22`, `sub23`, `m2`, `m5`, `subtotal`, somas de totais |

> ⚠️ **Em PHP**: replicar `Math.round` (round half away from zero/half-up no JS para
> positivos) com `round($x, 2)` / `round($x, 4)`. Atenção: JS `Math.round` arredonda
> .5 **para cima** (toward +∞), e `round()` do PHP usa half-up por padrão — equivalente
> para valores positivos. Validar negativos (não devem ocorrer aqui).

### 9.2 Fallbacks e defaults

| Expressão | Fallback |
|-----------|----------|
| `ESCALAS[escSel] || ESCALAS['24h']` (calcIN) | escala 24h se `escSel` inválido |
| `ESCALAS[escSel] || ESCALAS['12d']` (calcular) | escala 12d (diurno) |
| `esc.dias_mes || 15.5` (calcIN) | 15.5 dias |
| `esc.horas_mes || 220` (calcIN) | 220 horas |
| `getCCT('dias_mes') || escAtiva.dias_mes || 15.5` (calcular) | cascata CCT→escala→15.5 |
| `getCCT('horas_mes') || 220` | 220 |
| `getCCT('peric') || 0` | 0 |
| `getCCT('intra_h') || 1.5` | 1.5h |
| `getCCT('desc_vt') || 6` | 6% |
| `window._totalFunc || 4` (calcularM2) | 4 funcionários |
| `window._totalFunc || 1` (calcularM3) | 1 |
| `window._diasMes || 15.5` | 15.5 |
| `getM2ExtrasTotal`: `window._totalFunc || 1` | 1 |
| `getEncargosTotal()` | **82.00** se nada salvo |
| `parseInt(gt5-meses || 12)` / `parseInt(gtin-meses || 12)` | 12 meses |
| `parseInt(an-diurno || 0)` | 0 (diurno sem AN por padrão) |
| `parseInt(an-noturno || 1)` | 1 (noturno COM AN por padrão) |
| `gIN(id)` = `parseFloat(... || 0)` | 0 para qualquer input vazio |
| `get(id)` = `parseFloat(... || 0)` | 0 |
| `getIdx('adm')` → `t.adm ?? 5` | 5 |
| `getIdx('lucro')` → `t.lucro ?? 3` | 3 |
| `getIdx('impostos')` → `(t.iss ?? 5)+(t.pis ?? 0.65)+(t.cofins ?? 3)` | 8.65 |

### 9.3 Constantes hardcoded (NÃO são inputs)

| Constante | Valor | Onde |
|-----------|-------|------|
| 13º (`p13`) | `0.0833` | Módulo 2.1 e 4 |
| Multa FGTS s/ Férias | `0.032` (3,2%) | Módulo 2.1 (`pMultaFgts`) |
| Multa FGTS rescisão/aviso | `0.40` (40%) | Módulo 3 (`pMultaInd`, `pMultaResc`) |
| Desconto VT (IN 05) | `0.06` (6%) | Módulo 2.3 |
| Adicional noturno (5 Estrelas) | `20/100` (20%) | `calcular` (`anPct`) |
| Fator horas AN | `8` | AN em ambos os modelos |
| Férias = 13º/3 | `p13/3` ≈ `0.0278` | Módulo 2.1 (`pFer`) |

### 9.4 ESCALAS (dias_mes / horas_mes por escala)

| Chave | label | dias_mes | horas_mes | qtdD | qtdN | func/posto |
|-------|-------|----------|-----------|------|------|-----------|
| `12d` | 12x36 Diurno | 15.5 | 220 | 2 | 0 | 2 |
| `12n` | 12x36 Noturno | 15.5 | 220 | 0 | 2 | 2 |
| `24h` | 24 Horas (12x36 D+N) | 15.5 | 220 | 2 | 2 | 4 |
| `44h-5x2` | 44h 5×2 | 22 | 220 | 1 | 0 | 1 |
| `44h-6x1` | 44h 6×1 | 26 | 220 | 1 | 0 | 1 |

> `escSel` default global = `'24h'`. `calcIN` usa fallback `'24h'`; `calcular` usa `'12d'`.

---

## 10. Tabela de mapeamento campo → origem → fórmula (IN 05, conferência)

| # | Item | Input(id) | Origem | Fórmula literal |
|---|------|-----------|--------|-----------------|
| M1-A | Salário | `in-sal` | usuário/CCT | `sal` |
| M1-B | Periculosidade | `in-peric-pct` | usuário | `sal*(pct/100)` |
| M1-C | Insalubridade | `in-insal-pct` | usuário | `sal*(pct/100)` |
| M1-D | Adic. Noturno | `in-an-pct` | usuário | `round((sal+peric)/horMes*(pct/100)*8*diasMes,2)` |
| M1-E | Hora Not. Reduzida | `in-hnr-pct` | usuário | `sal*(pct/100)` |
| M1-G | Outros | `in-outros1-pct` | usuário | `sal*(pct/100)` |
| — | **TOTAL M1** | — | — | `sal+peric+insal+anVal+hnrVal+out1` |
| 2.2-A..H | INSS..FGTS | `in-inss-pct`..`in-fgts-pct` | usuário | `round(m1*(pct/100),4)` cada |
| — | sub22 / pct22 | — | — | `Σ itens` / `sub22/m1` |
| 2.1-A | 13º | const | `0.0833` | `m1*0.0833` |
| 2.1-B | Férias | const | `round(0.0833/3,4)` | `m1*pFer` |
| 2.1-C | Incid. s/ 13º+Férias | deriv. | `round((p13+pFer)*pct22,4)` | `m1*pInc21` |
| 2.1-D | Multa FGTS s/ Férias | const | `round(pFer*0.032,4)` | `m1*pMultaFgts` |
| 2.3-A | VT | `in-vt-dia` | usuário | `round(max(vt_dia*diasMes - sal*0.06,0),2)` |
| 2.3-B | VA | `in-va-dia` | usuário | `round(va_dia*diasMes,2)` |
| 2.3-C..F | Médico/Odonto/Cesta/Seguro/PMQ/Outros | `in-medico`..`in-outros23` | usuário | valor direto |
| — | **TOTAL M2** | — | — | `sub21+sub22+sub23` |
| M3-A | Aviso Ind. | `in-avisoind-pct` | usuário | `round(m1*pAvisoInd,2)` |
| M3-B | FGTS s/ Aviso Ind. | deriv. | `round(pAvisoInd*pFgtsPct,4)` | `round(m1*pFgtsAviso,2)` |
| M3-C | Aviso Trab. | `in-avistrab-pct` | usuário | `round(m1*pAvisTrab,2)` |
| M3-D | Multa FGTS s/ Aviso Ind. | const | `round(pAvisoInd*0.40,4)` | `round(m1*pMultaInd,2)` |
| M3-E | Multa FGTS Rescisão | const | `round(pFgtsPct*0.40,4)` | `round(m1*pMultaResc,2)` |
| M3-F | Incid. GPS/FGTS s/ Aviso Trab. | deriv. | `round(pAvisTrab*pct22,4)` | `round(m1*pIncGPS,2)` |
| — | **TOTAL M3** | — | — | `Σ 6 valores` |
| 4.1-A | Cobertura Férias | deriv. | `round((p13+pFer)/12 + p13,4)` | `round(m1*pCobFer,2)` |
| 4.1-B..E | Ausências/Patern/Acid/Matern | `in-ausleg-pct`.. | usuário | `round(m1*pX,2)` |
| 4.1-F | Incid. s/ ausências | deriv. | `round(subtot41pct*(pct22+pct21total),4)` | `round(m1*pIncAus,2)` |
| 4.2-A | Intrajornada | `in-intrajornada` | usuário | valor direto |
| — | **TOTAL M4** | — | — | `tot41 + intrajornada` |
| M5 | Insumos (6 itens) | `in-uniforme`..`in-sso` | usuário | `Σ inputs` |
| M6-A | Custos Indiretos | `in-custoind-pct` | usuário | `round(subtotal*Adm,2)` (exib.) |
| M6-B | Lucro | `in-lucro-pct` | usuário | `round((subtotal+vCind)*Lucro,2)` (exib.) |
| M6-C | ISS | `in-iss-pct` | usuário | `round(precoEmp*ISS,2)` (exib.) |
| M6 | PIS/COFINS | `in-pis-pct`/`in-cofins-pct` | usuário | `round(precoEmp*X,2)` (exib.) |
| — | **PREÇO/EMPREGADO** | — | — | `round(subtotal*(1+d140),2)`, `d140=(1+Adm)(1+Lucro)/(1−Trib)−1` |

---

## 11. `getCCT(k)` — resolução de parâmetros (Modelo 5 Estrelas)

```js
function getCCT(k) {
  const vals = getCCTValores(_cctKey);          // CCT ativa (localStorage ou CCT_DEFAULTS)
  if (k in vals) return vals[k];
  const esc = ESCALAS[escSel] || ESCALAS['24h'];
  const cat = getCatVals(catSel);               // categoria ativa (CATEGORIAS + overrides)
  const map = {
    dias_mes:  esc.dias_mes,
    horas_mes: esc.horas_mes,
    peric:     cat.peric   || 0,
    intra_h:   cat.intra   || 1.5,
    desc_vt:   cat.desc_vt || 6,
  };
  return map[k] !== undefined ? map[k] : (getIdx(k) || 0);
}
```

Ordem de resolução: **CCT ativa → ESCALA / CATEGORIA → getIdx/localStorage → 0**.

- `getCCTValores(key)`: `{ ...CCT_DEFAULTS[key||'df-vigilancia'], ...localStorage }`.
- `_cctKey` default = `'df-vigilancia'`.
- `CATEGORIAS`: vigilante, portaria, limpeza, bombeiro, supervisor (campos sal/peric/intra/benefícios).
- `getCatVals(catId)`: base `CATEGORIAS[catId]` + insumos do localStorage + overrides `customCats`.

---

## 12. DÚVIDAS / pontos ambíguos a confirmar com humano antes de portar

1. **`m6` vs `precoEmp`:** o valor oficial do posto é `precoEmp` (via gross-up `d140`), mas o
   quadro também soma `m6 = vCind+vLucro+vISS+vPIS+vCOFINS`. Por arredondamento, `subtotal+m6`
   **pode divergir** de `precoEmp`. Confirmar qual número vai para a proposta/contrato (assumo `precoEmp`).
2. **Grupo D dos encargos (5 Estrelas):** `enc-d01` (9.77%) é input manual, apesar do rótulo
   "Incidência do Grupo A sobre o Grupo B". Confirmar se no PHP deve ser **calculado**
   (ex.: produto/incidência de A sobre B) ou mantido como **valor parametrizável fixo** (como está).
3. **Reuso do input FGTS:** Módulo 3 usa `in-fgts-pct` (mesmo do Módulo 2.2). Confirmar que é
   intencional (um único campo FGTS para os dois módulos).
4. **`pct21total` (Módulo 4):** `(p13+pFer)*pct22 + (p13+pFer)`. A expressão mistura fração de
   encargos sobre 13º+férias com o próprio (p13+pFer). Confere com a planilha oficial IN 05?
   Vale validar contra a planilha de origem (parece somar a base + sua incidência).
5. **Constantes hardcoded** (`0.0833`, `0.032`, `0.40`, `0.06`, `8`, `20%`): devem virar
   **parâmetros configuráveis** no banco (tabela `comercial_indices`/`encargos`) ou ficar
   fixas no código? Para cálculo financeiro auditável, recomendo torná-las parâmetros versionados.
6. **`escSel`/`catSel`/`_cctKey`** são estado global de UI. No Service PHP precisam virar
   **parâmetros explícitos** do request (escala, categoria, cct). Definir contrato da API.
7. **Persistência localStorage** (`gestao360_*`): no protótipo encargos/taxas/insumos vêm do
   navegador. No Laravel isso deve vir de **tabelas** (`Cct`, `Categoria`, `Escala`, `Encargo`,
   `Indice` já existem em `app/Models/Comercial`). Mapear cada chave localStorage → coluna.
8. **Arredondamento half-up:** confirmar tolerância. JS `Math.round(2.5)=3`, `Math.round(-2.5)=-2`.
   PHP `round()` half-up difere para negativos — aqui não há negativos esperados, mas documentar.
9. **VA/VT por empregado vs por posto (IN 05):** `calcIN` produz custo por empregado; a
   multiplicação por nº de postos/funcionários é externa. Confirmar onde essa multiplicação
   ocorrerá no fluxo Laravel (proposta/itens).
10. **`in-an-pct` no IN 05 default 0** mas a fórmula usa `8` horas fixas: confirmar se o "8"
    representa as 8h/noite ou outro fator normativo da planilha.
