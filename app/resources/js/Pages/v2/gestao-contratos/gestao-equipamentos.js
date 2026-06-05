// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import { useToast } from "vue-toastification"
import { ref } from "vue"
import axios from "axios"

const toast = useToast()

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO GLOBAL                          ║
// ╚══════════════════════════════════════════════════════════════╝

export const loading = ref(false)

export const equipamentos = ref({
  loading: false,
  data: [],
  pagination: {
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0
  },
  filtros: {
    status: "",
    filial_id: "",
    tipo_equipamento_id: "",
    busca: ""
  }
})

export const equipamentoAtual = ref({
  loading: false,
  data: null
})

export const tiposEquipamento = ref([])
export const filiais = ref([])
export const dashboardData = ref({
  loading: false,
  data: null
})

// ╔══════════════════════════════════════════════════════════════╗
// ║                    CRUD EQUIPAMENTOS                         ║
// ╚══════════════════════════════════════════════════════════════╝

export async function getEquipamentos(page = 1) {
  equipamentos.value.loading = true
  try {
    const params = {
      page,
      per_page: equipamentos.value.pagination.per_page,
      ...equipamentos.value.filtros
    }
    const resp = await axios.get("/v2/gestao-contratos/equipamentos", { params })
    if (resp.data.sucesso) {
      equipamentos.value.data = resp.data.dados.data
      equipamentos.value.pagination = {
        current_page: resp.data.dados.current_page,
        last_page: resp.data.dados.last_page,
        per_page: resp.data.dados.per_page,
        total: resp.data.dados.total
      }
    }
  } catch (error) {
    toast.error("Erro ao carregar equipamentos")
    console.error(error)
  } finally {
    equipamentos.value.loading = false
  }
}

export async function getEquipamento(id) {
  equipamentoAtual.value.loading = true
  try {
    const resp = await axios.get(`/v2/gestao-contratos/equipamentos/${id}`)
    if (resp.data.sucesso) {
      equipamentoAtual.value.data = resp.data.dados
    }
  } catch (error) {
    toast.error("Erro ao carregar equipamento")
    console.error(error)
  } finally {
    equipamentoAtual.value.loading = false
  }
}

export async function salvarEquipamento(dados) {
  loading.value = true
  try {
    let resp
    if (dados.id) {
      resp = await axios.put(`/v2/gestao-contratos/equipamentos/${dados.id}`, dados)
    } else {
      resp = await axios.post("/v2/gestao-contratos/equipamentos", dados)
    }
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      return resp.data.dados
    }
  } catch (error) {
    toast.error(error.response?.data?.mensagem || "Erro ao salvar equipamento")
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

export async function excluirEquipamento(id) {
  loading.value = true
  try {
    const resp = await axios.delete(`/v2/gestao-contratos/equipamentos/${id}`)
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      await getEquipamentos(equipamentos.value.pagination.current_page)
    }
  } catch (error) {
    toast.error("Erro ao excluir equipamento")
    console.error(error)
  } finally {
    loading.value = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                   TIPOS DE EQUIPAMENTO                       ║
// ╚══════════════════════════════════════════════════════════════╝

export async function getTiposEquipamento() {
  try {
    const resp = await axios.get("/v2/gestao-contratos/tipos-equipamento")
    if (resp.data.sucesso) {
      tiposEquipamento.value = resp.data.dados.map(t => ({ ...t, id: Number(t.id) }))
    }
  } catch (error) {
    console.error("Erro ao carregar tipos de equipamento", error)
  }
}

export async function salvarTipoEquipamento(dados) {
  loading.value = true
  try {
    let resp
    if (dados.id) {
      resp = await axios.put(`/v2/gestao-contratos/tipos-equipamento/${dados.id}`, dados)
    } else {
      resp = await axios.post("/v2/gestao-contratos/tipos-equipamento", dados)
    }
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      await getTiposEquipamento()
      return resp.data.dados
    }
  } catch (error) {
    toast.error(error.response?.data?.mensagem || "Erro ao salvar tipo de equipamento")
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

export async function excluirTipoEquipamento(id) {
  loading.value = true
  try {
    const resp = await axios.delete(`/v2/gestao-contratos/tipos-equipamento/${id}`)
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      await getTiposEquipamento()
    }
  } catch (error) {
    toast.error(error.response?.data?.mensagem || "Erro ao excluir tipo de equipamento")
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                OCORRÊNCIAS E TRATATIVAS                      ║
// ╚══════════════════════════════════════════════════════════════╝

export async function getOcorrencias(equipamentoId) {
  try {
    const resp = await axios.get(`/v2/gestao-contratos/equipamentos/${equipamentoId}/ocorrencias`)
    if (resp.data.sucesso) {
      return resp.data.dados
    }
    return []
  } catch (error) {
    toast.error("Erro ao carregar ocorrências")
    console.error(error)
    return []
  }
}

export async function registrarOcorrencia(equipamentoId, dados) {
  loading.value = true
  try {
    const resp = await axios.post(
      `/v2/gestao-contratos/equipamentos/${equipamentoId}/ocorrencias`,
      dados
    )
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      return resp.data.dados
    }
  } catch (error) {
    toast.error(error.response?.data?.mensagem || "Erro ao registrar ocorrência")
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

export async function getTratativas(equipamentoId) {
  try {
    const resp = await axios.get(`/v2/gestao-contratos/equipamentos/${equipamentoId}/tratativas`)
    if (resp.data.sucesso) {
      return resp.data.dados
    }
    return []
  } catch (error) {
    toast.error("Erro ao carregar tratativas")
    console.error(error)
    return []
  }
}

export async function registrarTratativa(equipamentoId, dados) {
  loading.value = true
  try {
    const resp = await axios.post(
      `/v2/gestao-contratos/equipamentos/${equipamentoId}/tratativas`,
      dados
    )
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem)
      return resp.data.dados
    }
  } catch (error) {
    toast.error(error.response?.data?.mensagem || "Erro ao registrar tratativa")
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                          FOTOS                               ║
// ╚══════════════════════════════════════════════════════════════╝

export async function uploadFotoEquipamento(equipamentoId, file) {
  loading.value = true
  try {
    const formData = new FormData()
    formData.append("arquivo", file)

    const resp = await axios.post(
      `/v2/gestao-contratos/equipamentos/${equipamentoId}/fotos`,
      formData,
      { headers: { "Content-Type": "multipart/form-data" } }
    )
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem || "Foto enviada com sucesso")
      return resp.data.dados
    }
  } catch (error) {
    toast.error(error.response?.data?.mensagem || "Erro ao enviar foto")
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

export async function uploadFotoOcorrencia(ocorrenciaId, file) {
  loading.value = true
  try {
    const formData = new FormData()
    formData.append("arquivo", file)

    const resp = await axios.post(
      `/v2/gestao-contratos/ocorrencias/${ocorrenciaId}/fotos`,
      formData,
      { headers: { "Content-Type": "multipart/form-data" } }
    )
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem || "Foto enviada com sucesso")
      return resp.data.dados
    }
  } catch (error) {
    toast.error(error.response?.data?.mensagem || "Erro ao enviar foto")
    console.error(error)
    throw error
  } finally {
    loading.value = false
  }
}

export async function excluirFoto(fotoId) {
  loading.value = true
  try {
    const resp = await axios.delete(`/v2/gestao-contratos/equipamento-fotos/${fotoId}`)
    if (resp.data.sucesso) {
      toast.success(resp.data.mensagem || "Foto excluída com sucesso")
    }
  } catch (error) {
    toast.error("Erro ao excluir foto")
    console.error(error)
  } finally {
    loading.value = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                        DASHBOARD                             ║
// ╚══════════════════════════════════════════════════════════════╝

export async function getDashboardEquipamentos() {
  dashboardData.value.loading = true
  try {
    const resp = await axios.get("/v2/gestao-contratos/equipamentos/dashboard")
    if (resp.data.sucesso) {
      dashboardData.value.data = resp.data.dados
    }
  } catch (error) {
    toast.error("Erro ao carregar dashboard de equipamentos")
    console.error(error)
  } finally {
    dashboardData.value.loading = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                       EXPORTAÇÃO                             ║
// ╚══════════════════════════════════════════════════════════════╝

export async function exportarEquipamentos() {
  try {
    const response = await axios.get("/v2/gestao-contratos/equipamentos/exportar", {
      params: { ...equipamentos.value.filtros },
      responseType: "blob"
    })
    downloadFile(response.data, "equipamentos.xlsx")
    toast.success("Exportação realizada com sucesso!")
  } catch (error) {
    toast.error("Erro ao exportar equipamentos")
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
// ║                         FILIAIS                              ║
// ╚══════════════════════════════════════════════════════════════╝

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

export function getStatusEquipamentoClass(status) {
  const classes = {
    VIGENTE: "bg-green-100 text-green-700",
    VENCENDO: "bg-yellow-100 text-yellow-700",
    VENCIDO: "bg-red-100 text-red-700",
    EM_MANUTENCAO: "bg-blue-100 text-blue-700"
  }
  return classes[status] || "bg-gray-100 text-gray-700"
}

export function getStatusEquipamentoLabel(status) {
  const labels = {
    VIGENTE: "Vigente",
    VENCENDO: "Vencendo",
    VENCIDO: "Vencido",
    EM_MANUTENCAO: "Em Manutenção"
  }
  return labels[status] || status
}

export function getStatusEquipamentoSeverity(status) {
  const severities = {
    VIGENTE: "success",
    VENCENDO: "warn",
    VENCIDO: "danger",
    EM_MANUTENCAO: "info"
  }
  return severities[status] || "secondary"
}

export function getTipoOcorrenciaLabel(tipo) {
  const labels = {
    DESPRESSURIZACAO: "Despressurização",
    DANO_FISICO: "Dano Físico",
    USO_EMERGENCIA: "Uso em Emergência",
    VENCIMENTO_ANTECIPADO: "Vencimento Antecipado",
    OUTRO: "Outro"
  }
  return labels[tipo] || tipo
}

export function formatarData(data) {
  if (!data) return "-"
  return new Date(data).toLocaleDateString("pt-BR")
}

export function formatarDataInput(data) {
  if (!data) return ""
  if (/^\d{4}-\d{2}-\d{2}$/.test(data)) return data
  const d = new Date(data)
  if (isNaN(d.getTime())) return ""
  return d.toISOString().split("T")[0]
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
