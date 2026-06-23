# Exploração Senior ERP — 23/06/2026 (deadlock quebrado)

Exploração autônoma READ-ONLY a partir do servidor de produção `G5E-SVM080`
(VPN + SSH `easytech@192.168.254.80`, origem `179.185.83.131`). Só operações
`Consultar*` do serviço `sapiens_Synccom_senior_g5_co_mfi_cpa_titulos`.

## Resultado principal
Conseguimos puxar **títulos de Contas a Pagar reais** sem precisar da sigla
`identificadorSistema` nem do Matheus, varrendo `codFor` no
`ConsultarTitulosAbertosCP`. Detalhes completos no steering
`.kiro/steering/integracao-senior.md` (seção "Exploração autônoma — 23/06/2026").

## Empresas operacionais
- `codEmp=1`: sem títulos (provável holding).
- `codEmp=2` e `codEmp=3`: têm títulos em aberto reais (`codFil=1`).

## Arquivos
- `senior-hit-3-1.xml` — emp 3, codFor 1 (2 títulos reais).
- `senior-hit-2-200.xml` — emp 2, codFor 200 (abastecimento).
- `senior-hit-2-2000.xml` — emp 2, codFor 2000 (R$ 23.560,21).
- `suppliers-with-open-titles.csv` — fornecedores com títulos em aberto
  (codFor 1–150, janela 2018–2027), por empresa, com nº de títulos e soma do saldo.
- `sweep-multiemp.log` — log da varredura codEmp 1–12 × amostra de codFor.
- `enum.log` — log da enumeração codFor 1–150 em emp 2 e 3.

## Pendências (Matheus)
- Sigla `identificadorSistema` registrada no Senior → destrava `cad_fornecedor`
  (nome/CNPJ dos fornecedores) e `cad_filial` (nomes das filiais). Não bloqueia o
  sync de títulos, mas o torna eficiente e completa os dados.

## Atenção
Tudo read-only. NUNCA `Gravar*`/`Baixar*`/`Excluir*` sem autorização explícita.
