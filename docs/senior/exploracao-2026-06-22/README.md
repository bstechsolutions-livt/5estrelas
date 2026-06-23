# Exploração Senior ERP — 22/06/2026 (dados reais)

Respostas SOAP cruas capturadas a partir do servidor de produção `G5E-SVM080`
(origem `179.185.83.131`, IP whitelistado). Todas READ-ONLY (`Consultar*`/`ConsultarGeral`).

Contexto e conclusões completas em `.kiro/steering/integracao-senior.md`
(seção "Exploração de dados reais — 22/06/2026").

## Arquivos

| Arquivo | Operação | O que mostra |
|---------|----------|--------------|
| `cp_for_0.xml` | `ConsultarTitulosAbertosCP` (codEmp=1, codFor=0, retRat=N, vct 22/06/2025→20/09/2026) | Contrato OK: `tipoRetorno=1`, "Processado com sucesso", **sem títulos** (codFor chutado não tem títulos em aberto). |
| `filial_emp_1.xml` | `cad_filial.ConsultarGeral` (codEmp=1) | Bloqueio: "Valores de parâmetros obrigatórios não foram informados" (falta `identificadorSistema`). |
| `param_geral.xml` | `cad_parametrosintegracao.ConsultarGeral` (codEmp=1, codFil=0) | Erros encadeados: "Sistema integrado não informado" + "Código da filial não cadastrado ... 1/0". |
| `p_1_1.xml` | `cad_parametrosintegracao.ConsultarGeral` (codEmp=1, codFil=1) | Empresa/filial VÁLIDAS: sobra só "Sistema integrado não informado". |
| `p_99_1.xml` | idem (codEmp=99, codFil=1) | Empresa INEXISTENTE: "Código da empresa não cadastrado ... 99". |

## TL;DR

- **codEmp 1–12 existem; cada uma tem codFil=1; empresa 1 só tem a filial 1.**
- **Bloqueio para listar filiais/fornecedores:** os serviços de cadastro exigem a
  sigla de "Sistema integrado" (`identificadorSistema`) registrada no Senior — não temos.
- **CP de títulos funciona** mas precisa de `codFor` real (a lista de fornecedores
  depende da sigla acima). Pedir ao Matheus a sigla e/ou `codFor` reais.
