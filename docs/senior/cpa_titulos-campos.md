# Senior CP — Estrutura REAL verificada (ConsultarTitulosAbertosCP v3)

> Extraído do WSDL/XSD vivo de PRODUÇÃO em 14/06/2026 via `?xsd`. Serviço `com_senior_g5_co_mfi_cpa_titulos`. PRD respondeu HTTP 200; HML estava 503.

> Arquivos crus: `docs/senior/cpa_titulos.wsdl` e `docs/senior/cpa_titulos.xsd`.


## Entrada — parâmetros de consulta (v3 In) — 26 campos

| Campo | Tipo | Lista |
|---|---|---|
| `codEmp` | int |  |
| `codFil` | int |  |
| `codFor` | string |  |
| `filCtr` | int |  |
| `filNfc` | int |  |
| `filNff` | int |  |
| `filNfv` | int |  |
| `filOcp` | int |  |
| `flowInstanceID` | string |  |
| `flowName` | string |  |
| `forNfc` | int |  |
| `forNff` | int |  |
| `numCtr` | int |  |
| `numNfc` | int |  |
| `numNff` | int |  |
| `numNfv` | int |  |
| `numOcp` | int |  |
| `ocpFre` | int |  |
| `ocpNre` | int |  |
| `retRat` | string |  |
| `seqImo` | int |  |
| `snfNfc` | string |  |
| `snfNfv` | string |  |
| `tipTit` | string |  |
| `vctFim` | string |  |
| `vctIni` | string |  |

## Saída — Título (cabeçalho) (v3 OutTitulos) — 73 campos

| Campo | Tipo | Lista |
|---|---|---|
| `antDsc` | string |  |
| `codCcu` | string |  |
| `codCrt` | string |  |
| `codDfs` | int |  |
| `codEmp` | int |  |
| `codFil` | int |  |
| `codFor` | int |  |
| `codFpg` | int |  |
| `codFpj` | int |  |
| `codFrj` | string |  |
| `codMoe` | string |  |
| `codMpt` | string |  |
| `codNtg` | int |  |
| `codPor` | string |  |
| `codTns` | string |  |
| `codTpt` | string |  |
| `cpgSub` | string |  |
| `ctaFin` | int |  |
| `ctaRed` | int |  |
| `ctrFre` | int |  |
| `ctrNre` | int |  |
| `datDsc` | string |  |
| `datEmi` | string |  |
| `datEnt` | string |  |
| `datNeg` | string |  |
| `datPpt` | string |  |
| `docIdeFav` | string |  |
| `dscNeg` | double |  |
| `filCcr` | int |  |
| `filCtr` | int |  |
| `filNfc` | int |  |
| `filNff` | int |  |
| `filNfv` | int |  |
| `filOcp` | int |  |
| `forNfc` | int |  |
| `forNff` | int |  |
| `gerTep` | string |  |
| `jrsDia` | double |  |
| `jrsNeg` | double |  |
| `mulNeg` | double |  |
| `numCcr` | int |  |
| `numCtr` | int |  |
| `numNfc` | int |  |
| `numNff` | int |  |
| `numNfv` | int |  |
| `numOcp` | int |  |
| `numPrj` | int |  |
| `numTit` | string |  |
| `obsTcp` | string |  |
| `ocpFre` | int |  |
| `ocpNre` | int |  |
| `outNeg` | double |  |
| `perDsc` | int |  |
| `perJrs` | int |  |
| `perMul` | int |  |
| `proJrs` | string |  |
| `rateios` | tns:titulosConsultarTitulosAbertosCP3OutTitulosRateios | sim |
| `seqCgt` | int |  |
| `seqImo` | int |  |
| `sitTit` | string |  |
| `snfNfc` | string |  |
| `snfNfv` | string |  |
| `tipEfe` | string |  |
| `tipJrs` | string |  |
| `tolDsc` | int |  |
| `tolJrs` | int |  |
| `tolMul` | int |  |
| `ultPgt` | string |  |
| `vctOri` | string |  |
| `vctPro` | string |  |
| `vlrAbe` | double |  |
| `vlrDsc` | double |  |
| `vlrOri` | double |  |

## Saída — Rateio (v3 OutTitulosRateios) — 20 campos

| Campo | Tipo | Lista |
|---|---|---|
| `abrFpj` | string |  |
| `abrPrj` | string |  |
| `codCcu` | string |  |
| `codFpj` | int |  |
| `codTns` | string |  |
| `criRat` | int |  |
| `ctaFin` | int |  |
| `ctaRed` | int |  |
| `datBas` | string |  |
| `mesAno` | string |  |
| `numPrj` | int |  |
| `obsRat` | string |  |
| `perCta` | double |  |
| `perRat` | double |  |
| `seqMov` | int |  |
| `seqRat` | int |  |
| `somSub` | int |  |
| `tipOri` | string |  |
| `vlrCta` | double |  |
| `vlrRat` | double |  |
