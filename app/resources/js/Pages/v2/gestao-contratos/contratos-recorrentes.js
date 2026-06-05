// ╔══════════════════════════════════════════════════════════════╗
// ║        Contratos Recorrentes - State e API                   ║
// ║   Medições, Renovações, Dashboard, Avisos, Relatórios        ║
// ╚══════════════════════════════════════════════════════════════╝
import { useToast } from "vue-toastification"
import { ref, computed } from "vue"

const toast = useToast()

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO GLOBAL                          ║
// ╚══════════════════════════════════════════════════════════════╝

export const loading = ref(false)

// ╔══════════════════════════════════════════════════════════════╗
// ║                       MEDIÇÕES                               ║
// ╚══════════════════════════════════════════════════════════════╝

export const medicoes = ref({
  loading: false,
  data: [],
  pagination: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
  filtros: { contrato_id: "", competencia: "", etapa: "", alerta: "" }
})

export const medicaoAtual = ref({ loading: false, data: null })

export async function getMedicoes(page = 1) {
  medicoes.value.loading = true
  try {
    const params = {
      page,
      per_page: medicoes.value.pagination.per_page,
      ...medicoes.value.filtros
    }
    const resp = await axios.get("/v2/gestao-contratos/medicoes", { params })
    if (resp.data.sucesso) {
      medicoes.value.data = resp.data.dados.data
      medicoes.value.pagination = {
        current_page: resp.data.dados.current_page,
        last_page: resp.data.dados.last_page,
        per_page: resp.data.dados.per_page,
        total: resp.data.dados.total
      }
    }
  } catch (error) {
    toast.error("Erro ao carregar medições")
    console.error(error)
  } finally {
    medicoes.value.loading = false
  }
}

export async function getMedicao(id) {
  medicaoAtual.value.loading = true
  try {
    const resp = await axios.get(`/v2/gestao-contratos/medicoes/${id}`)
    if (resp.data.sucesso) {
      medicaoAtual.value.data = resp.data.dados
    }
  } catch (error) {
    toast.error("Erro ao carregar medição")
    console.error(error)
  } finally {
    medicaoAtual.value.loading = false
  }
}

export async function getMedicoesPorContrato(contratoId) {
  medicoes.value.loading = true
  try {
    const resp = await axios.get(
      `/v2/gestao-contratos/medicoes/contrato/${contratoId}`
    )
    if (resp.data.sucesso) {
      return resp.data.dados
    }
  } catch (error) {
    toast.error("Erro ao carregar medições do contrato")
    console.error(error)
  } finally {
    medicoes.value.loading = false
  }
}

export async function enviarNfBoleto(id, dados) {
  loading.value = true
  try {
    const resp = await axios.post(
      `/v2/gestao-contratos/medicoes/${id}/enviar`,
      dados
    )
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      return resp.data.dados
    } else {
      toast.warning(resp.data.mensagem)
    }
  } catch (error) {
    const msg = error.response?.data?.mensagem || "Erro ao enviar NF/Boleto"
    toast.error(msg)
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

export async function movimentarMedicao(id, acao, observacoes = null) {
  loading.value = true
  try {
    const payload = { acao }
    if (observacoes) payload.observacoes = observacoes
    const resp = await axios.post(
      `/v2/gestao-contratos/medicoes/${id}/movimentar`,
      payload
    )
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      return resp.data.dados
    }
  } catch (error) {
    const msg = error.response?.data?.mensagem || "Erro ao movimentar medição"
    toast.error(msg)
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

export async function gerarMedicoesMensais(competencia = null) {
  loading.value = true
  try {
    const params = competencia ? { competencia } : {}
    const resp = await axios.get(
      "/v2/gestao-contratos/medicoes/gerar-mensais",
      { params }
    )
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      return resp.data.dados
    }
  } catch (error) {
    toast.error("Erro ao gerar medições mensais")
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                 ANEXOS DE MEDIÇÃO                            ║
// ╚══════════════════════════════════════════════════════════════╝

export async function uploadAnexoMedicao(medicaoId, formData, silent = false) {
  loading.value = true
  try {
    const resp = await axios.post(
      `/v2/gestao-contratos/medicoes/${medicaoId}/anexos`,
      formData,
      { headers: { "Content-Type": "multipart/form-data" } }
    )
    if (resp.data.sucesso) {
      if (!silent) toast.success(resp.data.mensagem)
      return resp.data.dados
    }
  } catch (error) {
    toast.error("Erro ao enviar anexo")
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

export async function excluirAnexoMedicao(id) {
  loading.value = true
  try {
    const resp = await axios.delete(
      `/v2/gestao-contratos/medicoes/anexos/${id}`
    )
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
    }
  } catch (error) {
    toast.error("Erro ao excluir anexo")
    console.error(error)
  } finally {
    loading.value = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                      AVISOS                                  ║
// ╚══════════════════════════════════════════════════════════════╝

export const avisos = ref({
  loading: false,
  pendentes_envio: [],
  com_alerta: [],
  contratos_vencendo: [],
  contratos_vencidos: [],
  resumo: {
    total_pendentes: 0,
    total_alertas: 0,
    total_vencendo: 0,
    total_vencidos: 0
  }
})

export async function getAvisos() {
  avisos.value.loading = true
  try {
    const resp = await axios.get("/v2/gestao-contratos/medicoes/avisos")
    if (resp.data.sucesso) {
      avisos.value = { loading: false, ...resp.data.dados }
    }
  } catch (error) {
    toast.error("Erro ao carregar avisos")
    console.error(error)
  } finally {
    avisos.value.loading = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                    RENOVAÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝

export const renovacoes = ref({
  loading: false,
  data: [],
  pagination: { current_page: 1, last_page: 1, per_page: 15, total: 0 }
})

export const renovacaoPrep = ref({
  loading: false,
  contrato: null,
  regra_divergencia: null,
  historico_renovacoes: []
})

export const contratosParaRenovar = ref({
  loading: false,
  data: []
})

export async function getRenovacoes(page = 1) {
  renovacoes.value.loading = true
  try {
    const params = { page, per_page: renovacoes.value.pagination.per_page }
    const resp = await axios.get("/v2/gestao-contratos/renovacoes", { params })
    if (resp.data.sucesso) {
      renovacoes.value.data = resp.data.dados.data
      renovacoes.value.pagination = {
        current_page: resp.data.dados.current_page,
        last_page: resp.data.dados.last_page,
        per_page: resp.data.dados.per_page,
        total: resp.data.dados.total
      }
    }
  } catch (error) {
    toast.error("Erro ao carregar renovações")
    console.error(error)
  } finally {
    renovacoes.value.loading = false
  }
}

export async function getContratosParaRenovar(dias = 60) {
  contratosParaRenovar.value.loading = true
  try {
    const resp = await axios.get(
      "/v2/gestao-contratos/renovacoes/para-renovar",
      { params: { dias } }
    )
    if (resp.data.sucesso) {
      contratosParaRenovar.value.data = resp.data.dados
    }
  } catch (error) {
    toast.error("Erro ao carregar contratos para renovar")
    console.error(error)
  } finally {
    contratosParaRenovar.value.loading = false
  }
}

export async function prepararRenovacao(contratoId) {
  renovacaoPrep.value.loading = true
  try {
    const resp = await axios.get(
      `/v2/gestao-contratos/renovacoes/contrato/${contratoId}`
    )
    if (resp.data.sucesso) {
      renovacaoPrep.value.contrato = resp.data.dados.contrato
      renovacaoPrep.value.regra_divergencia = resp.data.dados.regra_divergencia
      renovacaoPrep.value.historico_renovacoes =
        resp.data.dados.historico_renovacoes
    }
  } catch (error) {
    toast.error("Erro ao preparar renovação")
    console.error(error)
    throw error
  } finally {
    renovacaoPrep.value.loading = false
  }
}

export async function renovarContrato(contratoId, dados) {
  loading.value = true
  try {
    const resp = await axios.post(
      `/v2/gestao-contratos/renovacoes/contrato/${contratoId}`,
      dados
    )
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      return resp.data.dados
    }
  } catch (error) {
    if (error.response?.status === 422) {
      // Fora da divergência – precisa nova solicitação de compras
      const data = error.response.data
      toast.warning(data.mensagem)
      return { necessita_compras: true, ...data.dados }
    }
    toast.error(error.response?.data?.mensagem || "Erro ao renovar contrato")
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                 DASHBOARD RECORRENTES                        ║
// ╚══════════════════════════════════════════════════════════════╝

export const dashboardRecorrente = ref({
  loading: false,
  totais: {
    total_contratos_recorrentes: 0,
    valor_total_mensal: 0,
    valor_total_provisao: 0
  },
  medicoes_mes: {
    pendentes: 0,
    enviadas: 0,
    entrada_nota: 0,
    financeiro: 0,
    pagas: 0
  },
  alertas: {
    divergencia: 0,
    vencendo_30_dias: 0,
    vencendo_90_dias: 0,
    vencidos: 0
  },
  evolucao_mensal: [],
  proximos_vencimentos: [],
  medicoes_urgentes: [],
  competencia_atual: ""
})

export async function getDashboardRecorrente() {
  dashboardRecorrente.value.loading = true
  try {
    const resp = await axios.get("/v2/gestao-contratos/dashboard-recorrentes")
    if (resp.data.sucesso) {
      dashboardRecorrente.value = { loading: false, ...resp.data.dados }
    }
  } catch (error) {
    toast.error("Erro ao carregar dashboard")
    console.error(error)
  } finally {
    dashboardRecorrente.value.loading = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                    RELATÓRIOS                                ║
// ╚══════════════════════════════════════════════════════════════╝

export const relatorioMedicoes = ref({ loading: false, data: null })
export const relatorioRenovacoes = ref({ loading: false, data: null })
export const contratosRecorrentes = ref([])

export async function getRelatorioMedicoes(filtros = {}) {
  relatorioMedicoes.value.loading = true
  try {
    const resp = await axios.get(
      "/v2/gestao-contratos/dashboard-recorrentes/relatorio-medicoes",
      { params: filtros }
    )
    if (resp.data.sucesso) {
      relatorioMedicoes.value.data = resp.data.dados
    }
  } catch (error) {
    toast.error("Erro ao gerar relatório de medições")
    console.error(error)
  } finally {
    relatorioMedicoes.value.loading = false
  }
}

export async function getRelatorioRenovacoes(filtros = {}) {
  relatorioRenovacoes.value.loading = true
  try {
    const resp = await axios.get(
      "/v2/gestao-contratos/dashboard-recorrentes/relatorio-renovacoes",
      { params: filtros }
    )
    if (resp.data.sucesso) {
      relatorioRenovacoes.value.data = resp.data.dados
    }
  } catch (error) {
    toast.error("Erro ao gerar relatório de renovações")
    console.error(error)
  } finally {
    relatorioRenovacoes.value.loading = false
  }
}

export async function getContratosRecorrentes() {
  try {
    const resp = await axios.get(
      "/v2/gestao-contratos/dashboard-recorrentes/contratos-recorrentes"
    )
    if (resp.data.sucesso) {
      contratosRecorrentes.value = resp.data.dados
    }
  } catch (error) {
    console.error("Erro ao carregar contratos recorrentes", error)
  }
}

export async function exportarMedicoes(filtros = {}) {
  try {
    const response = await axios.get(
      "/v2/gestao-contratos/dashboard-recorrentes/exportar-medicoes",
      { params: filtros, responseType: "blob" }
    )
    downloadFile(response.data, "relatorio_medicoes_contratos.xlsx")
    toast.success("Exportação realizada com sucesso!")
  } catch (error) {
    toast.error("Erro ao exportar relatório")
    console.error(error)
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║            RELATÓRIOS ADICIONAIS (4 novos – PDF)             ║
// ╚══════════════════════════════════════════════════════════════╝

export const relatorioContratosStatus = ref({ loading: false, dados: null })
export const relatorioProvisaoRealizado = ref({ loading: false, dados: null })
export const relatorioContratosVencer = ref({ loading: false, dados: null })
export const relatorioSla = ref({ loading: false, dados: null })

export async function getRelatorioContratosStatus(filtros = {}) {
  relatorioContratosStatus.value.loading = true
  try {
    const response = await axios.get(
      "/v2/gestao-contratos/dashboard-recorrentes/relatorio-contratos-status",
      { params: filtros }
    )
    relatorioContratosStatus.value.dados = response.data.dados
  } catch (error) {
    toast.error("Erro ao carregar relatório de contratos por status")
    console.error(error)
  } finally {
    relatorioContratosStatus.value.loading = false
  }
}

export async function getRelatorioProvisaoRealizado(filtros = {}) {
  relatorioProvisaoRealizado.value.loading = true
  try {
    const response = await axios.get(
      "/v2/gestao-contratos/dashboard-recorrentes/relatorio-provisao-realizado",
      { params: filtros }
    )
    relatorioProvisaoRealizado.value.dados = response.data.dados
  } catch (error) {
    toast.error("Erro ao carregar relatório provisão × realizado")
    console.error(error)
  } finally {
    relatorioProvisaoRealizado.value.loading = false
  }
}

export async function getRelatorioContratosVencer(filtros = {}) {
  relatorioContratosVencer.value.loading = true
  try {
    const response = await axios.get(
      "/v2/gestao-contratos/dashboard-recorrentes/relatorio-contratos-vencer",
      { params: filtros }
    )
    relatorioContratosVencer.value.dados = response.data.dados
  } catch (error) {
    toast.error("Erro ao carregar relatório de contratos a vencer")
    console.error(error)
  } finally {
    relatorioContratosVencer.value.loading = false
  }
}

export async function getRelatorioSla(filtros = {}) {
  relatorioSla.value.loading = true
  try {
    const response = await axios.get(
      "/v2/gestao-contratos/dashboard-recorrentes/relatorio-sla",
      { params: filtros }
    )
    relatorioSla.value.dados = response.data.dados
  } catch (error) {
    toast.error("Erro ao carregar relatório de SLA")
    console.error(error)
  } finally {
    relatorioSla.value.loading = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                       HELPERS                                ║
// ╚══════════════════════════════════════════════════════════════╝

export function formatarMoeda(valor) {
  if (!valor && valor !== 0) return "-"
  return new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL"
  }).format(valor)
}

export function formatarData(data) {
  if (!data) return "-"
  return new Date(data).toLocaleDateString("pt-BR")
}

export function getEtapaClass(etapa) {
  const classes = {
    PENDENTE: "bg-yellow-100 text-yellow-700",
    ENVIADA: "bg-blue-100 text-blue-700",
    ENTRADA_NOTA: "bg-purple-100 text-purple-700",
    FINANCEIRO: "bg-orange-100 text-orange-700",
    PAGO: "bg-green-100 text-green-700"
  }
  return classes[etapa] || "bg-gray-100 text-gray-700"
}

export function getEtapaLabel(etapa) {
  const labels = {
    PENDENTE: "Pendente Envio",
    ENVIADA: "NF/Boleto Enviado",
    ENTRADA_NOTA: "Entrada de Nota",
    FINANCEIRO: "Financeiro",
    PAGO: "Pago"
  }
  return labels[etapa] || etapa
}

export function getAlertaClass(alerta) {
  return alerta ? "bg-red-100 text-red-700 font-bold" : ""
}

function downloadFile(data, filename) {
  const url = window.URL.createObjectURL(new Blob([data]))
  const link = document.createElement("a")
  link.href = url
  link.setAttribute("download", filename)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
