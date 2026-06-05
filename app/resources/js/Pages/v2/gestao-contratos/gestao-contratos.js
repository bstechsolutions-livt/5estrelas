// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import { useToast } from "vue-toastification"
import { ref, computed } from "vue"

const toast = useToast()

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO GLOBAL                          ║
// ╚══════════════════════════════════════════════════════════════╝

export const loading = ref(false)
export const activeTab = ref("dashboard")

// ╔══════════════════════════════════════════════════════════════╗
// ║                         DASHBOARD                            ║
// ╚══════════════════════════════════════════════════════════════╝

export const dashboard = ref({
  loading: false,
  contratos: {
    total_locacao: 0,
    total_servico: 0,
    vencendo_30_dias: 0,
    vencendo_90_dias: 0,
    valor_total_locacao: 0,
    valor_total_servico: 0
  },
  alvaras: {
    total: 0,
    vencendo_30_dias: 0,
    vencidos: 0
  },
  proximos_vencimentos: [],
  proximos_alvaras_vencer: []
})

export async function getDashboard() {
  dashboard.value.loading = true
  try {
    const resp = await axios.get("/v2/gestao-contratos/dashboard")
    if (resp.data.sucesso) {
      dashboard.value.contratos = resp.data.dados.contratos
      dashboard.value.alvaras = resp.data.dados.alvaras
      dashboard.value.proximos_vencimentos =
        resp.data.dados.proximos_vencimentos
      dashboard.value.proximos_alvaras_vencer =
        resp.data.dados.proximos_alvaras_vencer
    }
  } catch (error) {
    toast.error("Erro ao carregar dashboard")
    console.error(error)
  } finally {
    dashboard.value.loading = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                         CONTRATOS                            ║
// ╚══════════════════════════════════════════════════════════════╝

export const contratos = ref({
  loading: false,
  data: [],
  pagination: {
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0
  },
  filtros: {
    tipo: "",
    status: "",
    filial_id: "",
    busca: ""
  }
})

export const contratoAtual = ref({
  loading: false,
  data: null,
  reajustes: [],
  anexos: []
})

export async function getContratos(page = 1) {
  contratos.value.loading = true
  try {
    const params = {
      page,
      per_page: contratos.value.pagination.per_page,
      ...contratos.value.filtros
    }
    const resp = await axios.get("/v2/gestao-contratos/contratos", { params })
    if (resp.data.sucesso) {
      contratos.value.data = resp.data.dados.data
      contratos.value.pagination = {
        current_page: resp.data.dados.current_page,
        last_page: resp.data.dados.last_page,
        per_page: resp.data.dados.per_page,
        total: resp.data.dados.total
      }
    }
  } catch (error) {
    toast.error("Erro ao carregar contratos")
    console.error(error)
  } finally {
    contratos.value.loading = false
  }
}

export async function getContrato(id) {
  contratoAtual.value.loading = true
  try {
    const resp = await axios.get(`/v2/gestao-contratos/contratos/${id}`)
    if (resp.data.sucesso) {
      contratoAtual.value.data = resp.data.dados
      contratoAtual.value.reajustes = resp.data.dados.reajustes || []
      contratoAtual.value.anexos = resp.data.dados.anexos || []
    }
  } catch (error) {
    toast.error("Erro ao carregar contrato")
    console.error(error)
  } finally {
    contratoAtual.value.loading = false
  }
}

export async function salvarContrato(dados) {
  loading.value = true
  try {
    let resp
    if (dados.id) {
      resp = await axios.put(
        `/v2/gestao-contratos/contratos/${dados.id}`,
        dados
      )
    } else {
      resp = await axios.post("/v2/gestao-contratos/contratos", dados)
    }
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      return resp.data.dados
    }
  } catch (error) {
    toast.error("Erro ao salvar contrato")
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

export async function excluirContrato(id) {
  loading.value = true
  try {
    const resp = await axios.delete(`/v2/gestao-contratos/contratos/${id}`)
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      await getContratos(contratos.value.pagination.current_page)
    }
  } catch (error) {
    toast.error("Erro ao excluir contrato")
    console.error(error)
  } finally {
    loading.value = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                         REAJUSTES                            ║
// ╚══════════════════════════════════════════════════════════════╝

export async function salvarReajuste(contratoId, dados) {
  loading.value = true
  try {
    const resp = await axios.post(
      `/v2/gestao-contratos/contratos/${contratoId}/reajustes`,
      dados
    )
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      await getContrato(contratoId)
      return resp.data.dados
    }
  } catch (error) {
    toast.error("Erro ao salvar reajuste")
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                          ANEXOS                              ║
// ╚══════════════════════════════════════════════════════════════╝

export async function uploadAnexo(contratoId, formData) {
  loading.value = true
  try {
    const resp = await axios.post(
      `/v2/gestao-contratos/contratos/${contratoId}/anexos`,
      formData,
      {
        headers: { "Content-Type": "multipart/form-data" }
      }
    )
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      await getContrato(contratoId)
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

export async function excluirAnexo(id, contratoId) {
  loading.value = true
  try {
    const resp = await axios.delete(`/v2/gestao-contratos/anexos/${id}`)
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      await getContrato(contratoId)
    }
  } catch (error) {
    toast.error("Erro ao excluir anexo")
    console.error(error)
  } finally {
    loading.value = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                         ALVARÁS                              ║
// ╚══════════════════════════════════════════════════════════════╝

export const alvaras = ref({
  loading: false,
  data: [],
  pagination: {
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0
  },
  filtros: {
    status: "",
    filial_id: "",
    tipo_alvara_id: "",
    busca: ""
  }
})

export const alvaraAtual = ref({
  loading: false,
  data: null
})

export async function getAlvaras(page = 1) {
  alvaras.value.loading = true
  try {
    const params = {
      page,
      per_page: alvaras.value.pagination.per_page,
      ...alvaras.value.filtros
    }
    const resp = await axios.get("/v2/gestao-contratos/alvaras", { params })
    if (resp.data.sucesso) {
      alvaras.value.data = resp.data.dados.data
      alvaras.value.pagination = {
        current_page: resp.data.dados.current_page,
        last_page: resp.data.dados.last_page,
        per_page: resp.data.dados.per_page,
        total: resp.data.dados.total
      }
    }
  } catch (error) {
    toast.error("Erro ao carregar alvarás")
    console.error(error)
  } finally {
    alvaras.value.loading = false
  }
}

export async function getAlvara(id) {
  alvaraAtual.value.loading = true
  try {
    const resp = await axios.get(`/v2/gestao-contratos/alvaras/${id}`)
    if (resp.data.sucesso) {
      alvaraAtual.value.data = resp.data.dados
    }
  } catch (error) {
    toast.error("Erro ao carregar alvará")
    console.error(error)
  } finally {
    alvaraAtual.value.loading = false
  }
}

export async function salvarAlvara(dados) {
  loading.value = true
  try {
    let resp
    if (dados.id) {
      resp = await axios.put(`/v2/gestao-contratos/alvaras/${dados.id}`, dados)
    } else {
      resp = await axios.post("/v2/gestao-contratos/alvaras", dados)
    }
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      return resp.data.dados
    }
  } catch (error) {
    toast.error("Erro ao salvar alvará")
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

export async function excluirAlvara(id) {
  loading.value = true
  try {
    const resp = await axios.delete(`/v2/gestao-contratos/alvaras/${id}`)
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      await getAlvaras(alvaras.value.pagination.current_page)
    }
  } catch (error) {
    toast.error("Erro ao excluir alvará")
    console.error(error)
  } finally {
    loading.value = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                     DADOS AUXILIARES                         ║
// ╚══════════════════════════════════════════════════════════════╝

export const tiposIndice = ref([])
export const tiposAlvara = ref([])
export const filiais = ref([])

export async function getTiposIndice() {
  try {
    const resp = await axios.get("/v2/gestao-contratos/tipos-indice")
    if (resp.data.sucesso) {
      tiposIndice.value = resp.data.dados
    }
  } catch (error) {
    console.error("Erro ao carregar tipos de índice", error)
  }
}

export async function getTiposAlvara() {
  try {
    const resp = await axios.get("/v2/gestao-contratos/tipos-alvara")
    if (resp.data.sucesso) {
      tiposAlvara.value = resp.data.dados
    }
  } catch (error) {
    console.error("Erro ao carregar tipos de alvará", error)
  }
}

export async function getFiliais() {
  try {
    const resp = await axios.get("/v2/gestao-contratos/filiais")
    if (resp.data.sucesso) {
      filiais.value = resp.data.dados
    }
  } catch (error) {
    console.error("Erro ao carregar filiais", error)
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                         HELPERS                              ║
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

export function formatarDataInput(data) {
  if (!data) return ""
  // Se já estiver no formato YYYY-MM-DD, retorna
  if (/^\d{4}-\d{2}-\d{2}$/.test(data)) return data
  // Converte de ISO para YYYY-MM-DD
  const d = new Date(data)
  if (isNaN(d.getTime())) return ""
  return d.toISOString().split("T")[0]
}

export function getStatusClass(status) {
  const classes = {
    ATIVO: "bg-green-100 text-green-700",
    VIGENTE: "bg-green-100 text-green-700",
    ENCERRADO: "bg-gray-100 text-gray-700",
    VENCIDO: "bg-red-100 text-red-700",
    PENDENTE: "bg-yellow-100 text-yellow-700",
    EM_RENOVACAO: "bg-blue-100 text-blue-700"
  }
  return classes[status] || "bg-gray-100 text-gray-700"
}

export function getDiasVencimentoClass(dias) {
  if (dias < 0) return "text-red-600 font-bold"
  if (dias <= 30) return "text-red-500"
  if (dias <= 60) return "text-orange-500"
  if (dias <= 90) return "text-yellow-600"
  return "text-gray-600"
}

export function getStatusAlvaraClass(status) {
  const classes = {
    VIGENTE: "bg-green-100 text-green-700",
    VENCIDO: "bg-red-100 text-red-700",
    EM_RENOVACAO: "bg-blue-100 text-blue-700",
    CANCELADO: "bg-gray-100 text-gray-700"
  }
  return classes[status] || "bg-gray-100 text-gray-700"
}

export function showToast(message, type = "success") {
  if (type === "success") {
    toast.success(message)
  } else if (type === "error") {
    toast.error(message)
  } else {
    toast.info(message)
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                      EXPORTAÇÃO EXCEL                        ║
// ╚══════════════════════════════════════════════════════════════╝

export async function exportarContratosLocacao() {
  try {
    const response = await axios.get(
      "/v2/gestao-contratos/contratos/exportar",
      {
        params: { tipo: "LOCACAO" },
        responseType: "blob"
      }
    )
    downloadFile(response.data, "contratos_locacao.xlsx")
    toast.success("Exportação realizada com sucesso!")
  } catch (error) {
    toast.error("Erro ao exportar contratos")
    console.error(error)
  }
}

export async function exportarContratosServico() {
  try {
    const response = await axios.get(
      "/v2/gestao-contratos/contratos/exportar",
      {
        params: { tipo: "SERVICO" },
        responseType: "blob"
      }
    )
    downloadFile(response.data, "contratos_servico.xlsx")
    toast.success("Exportação realizada com sucesso!")
  } catch (error) {
    toast.error("Erro ao exportar contratos")
    console.error(error)
  }
}

export async function exportarAlvaras() {
  try {
    const response = await axios.get("/v2/gestao-contratos/alvaras/exportar", {
      responseType: "blob"
    })
    downloadFile(response.data, "alvaras.xlsx")
    toast.success("Exportação realizada com sucesso!")
  } catch (error) {
    toast.error("Erro ao exportar alvarás")
    console.error(error)
  }
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

// ╔══════════════════════════════════════════════════════════════╗
// ║                  ANEXOS DE ALVARÁS                           ║
// ╚══════════════════════════════════════════════════════════════╝

export async function uploadAnexoAlvara(alvaraId, arquivo) {
  loading.value = true
  try {
    const formData = new FormData()
    formData.append("arquivo", arquivo.file)

    const resp = await axios.post(
      `/v2/gestao-contratos/alvaras/${alvaraId}/anexo`,
      formData,
      {
        headers: { "Content-Type": "multipart/form-data" }
      }
    )
    if (resp.data.sucesso) {
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

export async function deleteAnexoAlvara(alvaraId) {
  loading.value = true
  try {
    await axios.delete(`/v2/gestao-contratos/alvaras/${alvaraId}/anexo`)
  } catch (error) {
    toast.error("Erro ao remover anexo")
    console.error(error)
  } finally {
    loading.value = false
  }
}

export function downloadAnexoAlvara(alvaraId) {
  window.open(
    `/v2/gestao-contratos/alvaras/${alvaraId}/anexo/download`,
    "_blank"
  )
}

// Refs adicionais para formulários
export const anexos = ref([])
export const reajustes = ref([])
