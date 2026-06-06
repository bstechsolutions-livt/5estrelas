<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { Head, router, usePage } from "@inertiajs/vue3"
import { onMounted, ref, computed, watch } from "vue"
import { useUserPreferences } from "@/composables/useUserPreferences"
import Select from "primevue/select"
import MultiSelect from "primevue/multiselect"
import DatePicker from "primevue/datepicker"
import Button from "primevue/button"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Tag from "primevue/tag"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import Checkbox from "primevue/checkbox"
import Panel from "primevue/panel"
import Dialog from "primevue/dialog"
import { useToast } from "primevue/usetoast"
import { FilterMatchMode } from "@primevue/core/api"
import { swalConfirm } from "@/utils/globalFunctions"
import Filial from "@/Components/New/Filial2.vue"
import Solicitacao from "@/Pages/Solicitacoes/Solicitação.vue"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       CONFIGURAÇÃO                           ║
// ╚══════════════════════════════════════════════════════════════╝

// Marcar como página nova
layout.paginaNova.value = true

const page = usePage()
const toast = useToast()
const userPreferences = useUserPreferences()

// Chaves de preferências
const PREF_KEYS = {
  departamento: "solicitacoes.relatorios.departamento",
  visualizacaoTabela: "solicitacoes.relatorios.visualizacaoTabela"
}

const props = defineProps([
  "departamentos",
  "permiteVerTodos",
  "statusList",
  "prioridadeList"
])

// Estados
const loading = ref(false)
const loadingExport = ref(false)
const loadingExportFluxo = ref(false)
const loadingInicial = ref(true)
const solicitacoes = ref([])
const totalRegistros = ref(0)
const erroMensagem = ref(null)
const buscaRealizada = ref(false)
const dialogSolicitacao = ref(false)
const solicitacaoSelecionada = ref(null)
const dialogConfig = ref(false)

// Fluxos detectados na última busca (controla o botão "Exportar Fluxo")
const fluxosDetectados = ref([])
// Qtd de assuntos usada na última busca (o fluxo só é exportável com 1 assunto)
const assuntosBuscados = ref(0)
const temFluxo = computed(
  () => assuntosBuscados.value === 1 && fluxosDetectados.value.length > 0
)

// Colunas padrão para a tabela
const colunasDefault = [
  { coluna: "id", ativarColuna: true },
  { coluna: "titulo", ativarColuna: true },
  { coluna: "descricao", ativarColuna: false },
  { coluna: "prioridade", ativarColuna: true },
  { coluna: "status", ativarColuna: true },
  { coluna: "etapa_atual", ativarColuna: true },
  { coluna: "assunto", ativarColuna: true },
  { coluna: "departamento", ativarColuna: true },
  { coluna: "created_at", ativarColuna: true },
  { coluna: "updated_at", ativarColuna: false },
  { coluna: "solicitante", ativarColuna: true },
  { coluna: "responsavel", ativarColuna: false },
  { coluna: "usuario_origem", ativarColuna: false },
  { coluna: "usuarios_destino", ativarColuna: false },
  { coluna: "filial", ativarColuna: false },
  { coluna: "previsao_entrega", ativarColuna: true },
  { coluna: "solicitacao_pai_id", ativarColuna: false },
  { coluna: "dias_aberta", ativarColuna: true },
  { coluna: "dias_atraso", ativarColuna: true },
  { coluna: "data_conclusao", ativarColuna: false }
]
const colunas = ref([...colunasDefault])
const colunasCarregadas = ref(false)

// Filtros
const filtro = ref({
  id: null,
  departamento: null,
  assuntos: [],
  filial: [],
  responsavel: null,
  solicitante: "",
  status: [],
  prioridade: [],
  dataInicio: null,
  dataFim: null,
  atrasadas: false
})

// Filtro global da tabela
const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS }
})

// Computed para verificar se busca por ID está ativa (desabilita outros filtros)
const buscaPorIdAtiva = computed(() => {
  return (
    filtro.value.id !== null &&
    filtro.value.id !== "" &&
    filtro.value.id !== undefined
  )
})

// Opções derivadas do departamento selecionado
const assuntosOptions = computed(() => {
  if (!filtro.value.departamento) return []
  const depto = props.departamentos.find(
    (d) => d.condicao1 === filtro.value.departamento
  )
  return depto?.assuntos || []
})

const responsaveisOptions = computed(() => {
  if (!filtro.value.departamento) return []
  const depto = props.departamentos.find(
    (d) => d.condicao1 === filtro.value.departamento
  )
  return depto?.responsaveis || []
})

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTATÍSTICAS                           ║
// ╚══════════════════════════════════════════════════════════════╝

const estatisticas = computed(() => {
  if (solicitacoes.value.length === 0) {
    return [
      { title: "Pendentes", value: 0, icon: "pi pi-clock", color: "yellow" },
      {
        title: "Em Atendimento",
        value: 0,
        icon: "pi pi-comment",
        color: "blue"
      },
      {
        title: "Finalizadas",
        value: 0,
        icon: "pi pi-check-circle",
        color: "green"
      },
      {
        title: "Atrasadas",
        value: 0,
        icon: "pi pi-exclamation-triangle",
        color: "red"
      }
    ]
  }

  const pendentes = solicitacoes.value.filter(
    (s) => s.status === "pendente"
  ).length
  const emAtendimento = solicitacoes.value.filter(
    (s) => s.status === "em atendimento"
  ).length
  const finalizadas = solicitacoes.value.filter((s) =>
    ["resolvida", "finalizada"].includes(s.status)
  ).length
  const atrasadas = solicitacoes.value.filter((s) => s.dias_atraso > 0).length

  return [
    {
      title: "Pendentes",
      value: pendentes,
      icon: "pi pi-clock",
      color: "yellow"
    },
    {
      title: "Em Atendimento",
      value: emAtendimento,
      icon: "pi pi-comment",
      color: "blue"
    },
    {
      title: "Finalizadas",
      value: finalizadas,
      icon: "pi pi-check-circle",
      color: "green"
    },
    {
      title: "Atrasadas",
      value: atrasadas,
      icon: "pi pi-exclamation-triangle",
      color: "red"
    }
  ]
})

// ╔══════════════════════════════════════════════════════════════╗
// ║                       PREFERÊNCIAS                           ║
// ╚══════════════════════════════════════════════════════════════╝

// Carregar preferências do usuário
async function carregarPreferencias() {
  try {
    const preferencias = await userPreferences.getMany([
      PREF_KEYS.departamento,
      PREF_KEYS.visualizacaoTabela
    ])

    // Verificar se o departamento salvo ainda existe
    if (preferencias[PREF_KEYS.departamento]) {
      const deptoExiste = props.departamentos.some(
        (d) => d.condicao1 === preferencias[PREF_KEYS.departamento]
      )
      if (deptoExiste) {
        filtro.value.departamento = preferencias[PREF_KEYS.departamento]
      }
    }

    // Se não tiver departamento salvo, usar o primeiro disponível
    if (!filtro.value.departamento && props.departamentos.length > 0) {
      filtro.value.departamento = props.departamentos[0].condicao1
    }

    // Carregar preferências de colunas
    if (!colunasCarregadas.value) {
      const colunasCache = preferencias[PREF_KEYS.visualizacaoTabela]
      colunas.value = mesclarColunasComCache(colunasCache)
      colunasCarregadas.value = true
    }
  } catch (e) {
    console.error("Erro ao carregar preferências:", e)
    // Usar primeiro departamento como fallback
    if (props.departamentos.length > 0) {
      filtro.value.departamento = props.departamentos[0].condicao1
    }
  }
}

// Salvar preferência de departamento
async function salvarDepartamento() {
  await userPreferences.set(PREF_KEYS.departamento, filtro.value.departamento)
  // Limpar assuntos e responsável ao mudar departamento
  filtro.value.assuntos = []
  filtro.value.responsavel = null
}

// Buscar dados do relatório
async function buscarDados() {
  // Se não tem ID, exige departamento
  if (!filtro.value.id && !filtro.value.departamento) {
    toast.add({
      severity: "warn",
      summary: "Atenção",
      detail: "Selecione um departamento ou informe um ID",
      life: 3000
    })
    return
  }

  loading.value = true
  erroMensagem.value = null
  buscaRealizada.value = true

  try {
    const params = {
      id: filtro.value.id,
      departamento: filtro.value.departamento,
      codfiliais: filtro.value.filial?.map((f) => f.codigo) || [],
      assuntos: filtro.value.assuntos,
      responsavel: filtro.value.responsavel,
      solicitante: filtro.value.solicitante,
      status: filtro.value.status,
      prioridade: filtro.value.prioridade,
      dataInicio: filtro.value.dataInicio
        ? formatarDataParaAPI(filtro.value.dataInicio)
        : null,
      dataFim: filtro.value.dataFim
        ? formatarDataParaAPI(filtro.value.dataFim)
        : null,
      atrasadas: filtro.value.atrasadas
    }

    const response = await axios.post("/solicitacoes/relatorios/buscar", params)
    solicitacoes.value = response.data.solicitacoes
    totalRegistros.value = response.data.total
    fluxosDetectados.value = response.data.fluxos || []
    // Guarda quantos assuntos foram usados nesta busca (snapshot)
    assuntosBuscados.value = (filtro.value.assuntos || []).length
  } catch (err) {
    console.error(err)
    erroMensagem.value = "Erro ao buscar dados do relatório"
    fluxosDetectados.value = []
    assuntosBuscados.value = 0
    toast.add({
      severity: "error",
      summary: "Erro",
      detail: "Erro ao buscar dados do relatório",
      life: 5000
    })
  } finally {
    loading.value = false
  }
}

// Exportar para Excel
async function exportarExcel() {
  if (!filtro.value.departamento) {
    toast.add({
      severity: "warn",
      summary: "Atenção",
      detail: "Selecione um departamento",
      life: 3000
    })
    return
  }

  loadingExport.value = true

  try {
    const params = {
      departamento: filtro.value.departamento,
      codfiliais: filtro.value.filial?.map((f) => f.codigo) || [],
      assuntos: filtro.value.assuntos,
      responsavel: filtro.value.responsavel,
      solicitante: filtro.value.solicitante,
      status: filtro.value.status,
      prioridade: filtro.value.prioridade,
      dataInicio: filtro.value.dataInicio
        ? formatarDataParaAPI(filtro.value.dataInicio)
        : null,
      dataFim: filtro.value.dataFim
        ? formatarDataParaAPI(filtro.value.dataFim)
        : null,
      atrasadas: filtro.value.atrasadas
    }

    const response = await axios.post(
      "/solicitacoes/relatorios/exportar",
      params,
      { responseType: "blob" }
    )

    // Criar link para download
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement("a")
    link.href = url
    link.setAttribute(
      "download",
      `relatorio_solicitacoes_${new Date().toISOString().slice(0, 10)}.xlsx`
    )
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)

    toast.add({
      severity: "success",
      summary: "Sucesso",
      detail: "Relatório exportado com sucesso",
      life: 3000
    })
  } catch (err) {
    console.error(err)
    toast.add({
      severity: "error",
      summary: "Erro",
      detail: "Erro ao exportar relatório",
      life: 5000
    })
  } finally {
    loadingExport.value = false
  }
}

// Exportar relatório orientado a FLUXO (quando os filtros batem em um fluxo).
// Se houver mais de um fluxo detectado, exporta um arquivo por fluxo.
async function exportarFluxo() {
  if (!temFluxo.value) {
    toast.add({
      severity: "warn",
      summary: "Atenção",
      detail: "Os dados filtrados não possuem fluxo configurado",
      life: 3000
    })
    return
  }

  loadingExportFluxo.value = true

  try {
    const baseParams = {
      id: filtro.value.id,
      departamento: filtro.value.departamento,
      codfiliais: filtro.value.filial?.map((f) => f.codigo) || [],
      assuntos: filtro.value.assuntos,
      responsavel: filtro.value.responsavel,
      solicitante: filtro.value.solicitante,
      status: filtro.value.status,
      prioridade: filtro.value.prioridade,
      dataInicio: filtro.value.dataInicio
        ? formatarDataParaAPI(filtro.value.dataInicio)
        : null,
      dataFim: filtro.value.dataFim
        ? formatarDataParaAPI(filtro.value.dataFim)
        : null,
      atrasadas: filtro.value.atrasadas
    }

    // Um arquivo por fluxo detectado (cada fluxo tem suas próprias etapas)
    for (const fluxo of fluxosDetectados.value) {
      const response = await axios.post(
        "/solicitacoes/relatorios/exportar-fluxo",
        { ...baseParams, fluxo_id: fluxo.id },
        { responseType: "blob" }
      )

      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement("a")
      link.href = url
      const nomeFluxo = (fluxo.nome || "fluxo").replace(/[^A-Za-z0-9_-]+/g, "_")
      link.setAttribute(
        "download",
        `relatorio_fluxo_${nomeFluxo}_${new Date().toISOString().slice(0, 10)}.xlsx`
      )
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    }

    toast.add({
      severity: "success",
      summary: "Sucesso",
      detail:
        fluxosDetectados.value.length > 1
          ? "Relatórios de fluxo exportados com sucesso"
          : "Relatório de fluxo exportado com sucesso",
      life: 3000
    })
  } catch (err) {
    console.error(err)
    toast.add({
      severity: "error",
      summary: "Erro",
      detail: "Erro ao exportar relatório de fluxo",
      life: 5000
    })
  } finally {
    loadingExportFluxo.value = false
  }
}

function formatarDataParaAPI(data) {
  if (!data) return null
  const d = new Date(data)
  return d.toISOString().split("T")[0]
}

// Limpar filtros
function limparFiltros() {
  filtro.value.id = null
  filtro.value.filial = []
  filtro.value.assuntos = []
  filtro.value.responsavel = null
  filtro.value.solicitante = ""
  filtro.value.status = []
  filtro.value.prioridade = []
  filtro.value.dataInicio = null
  filtro.value.dataFim = null
  filtro.value.atrasadas = false
  solicitacoes.value = []
  totalRegistros.value = 0
  buscaRealizada.value = false
  fluxosDetectados.value = []
  assuntosBuscados.value = 0
}

// Definir período inicial (últimos 30 dias)
function getDataInicioPadrao() {
  const data = new Date()
  data.setDate(data.getDate() - 30)
  data.setHours(0, 0, 0, 0)
  return data
}

function getDataFimPadrao() {
  const data = new Date()
  data.setHours(23, 59, 59, 999)
  return data
}

// Funções auxiliares para status
function getStatusSeverity(status) {
  const severities = {
    pendente: "warn",
    "em atendimento": "info",
    "atendimento pausado": "secondary",
    agendado: "info",
    "retorno solicitante": "warn",
    resolvida: "success",
    finalizada: "success",
    cancelada: "danger"
  }
  return severities[status] || "secondary"
}

function getStatusLabel(status) {
  const labels = {
    pendente: "Pendente",
    "em atendimento": "Em Atendimento",
    "atendimento pausado": "Pausado",
    agendado: "Agendado",
    "retorno solicitante": "Retorno",
    resolvida: "Resolvida",
    finalizada: "Finalizada",
    cancelada: "Cancelada"
  }
  return labels[status] || status
}

// Funções auxiliares para prioridade
function getPrioridadeSeverity(prioridade) {
  const severities = {
    urgente: "danger",
    alta: "warn",
    media: "info",
    baixa: "success"
  }
  return severities[prioridade] || "secondary"
}

function getPrioridadeLabel(prioridade) {
  const labels = {
    urgente: "Urgente",
    alta: "Alta",
    media: "Média",
    baixa: "Baixa"
  }
  return labels[prioridade] || prioridade
}

// Abrir solicitação
function abrirSolicitacao(sol) {
  solicitacaoSelecionada.value = sol
  dialogSolicitacao.value = true
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES AVATAR                         ║
// ╚══════════════════════════════════════════════════════════════╝

// Função para obter iniciais (nome e sobrenome)
function obterIniciais(nome) {
  if (!nome) return "?"
  const partes = nome
    .trim()
    .split(" ")
    .filter((p) => p.length > 0)
  if (partes.length === 0) return "?"
  if (partes.length === 1) return partes[0].charAt(0).toUpperCase()
  return (
    partes[0].charAt(0) + partes[partes.length - 1].charAt(0)
  ).toUpperCase()
}

// Função para obter nome e sobrenome
function obterNomeSobrenome(nome) {
  if (!nome) return "N/A"
  const partes = nome
    .trim()
    .split(" ")
    .filter((p) => p.length > 0)
  if (partes.length === 0) return "N/A"
  if (partes.length === 1) return partes[0]
  return `${partes[0]} ${partes[partes.length - 1]}`
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                   CONFIGURAR TABELA                          ║
// ╚══════════════════════════════════════════════════════════════╝

// Função para verificar se uma coluna está visível
function colunaVisivel(pColuna) {
  return (
    Array.isArray(colunas.value) &&
    colunas.value.some((c) => c.coluna === pColuna && c.ativarColuna)
  )
}

// Mapeamento de nomes das colunas
function deParaColunas(coluna) {
  const mapa = {
    id: "ID",
    titulo: "Título",
    descricao: "Descrição",
    prioridade: "Prioridade",
    status: "Situação",
    etapa_atual: "Etapa Andamento",
    assunto: "Assunto",
    departamento: "Departamento",
    created_at: "Criado em",
    updated_at: "Última Atualização",
    solicitante: "Solicitante",
    responsavel: "Responsável",
    usuario_origem: "Usuário Origem",
    usuarios_destino: "Usuários Destino",
    filial: "Filial",
    previsao_entrega: "Previsão de Entrega",
    solicitacao_pai_id: "Solicitação Pai",
    dias_aberta: "Dias Aberto",
    dias_atraso: "Atraso",
    data_conclusao: "Data de Conclusão"
  }
  return mapa[coluna] || coluna
}

// Função para mesclar colunas com cache local
function mesclarColunasComCache(colunasCache) {
  if (!Array.isArray(colunasCache) || colunasCache.length === 0) {
    return colunasDefault.map((col) => ({ ...col }))
  }

  // Criar um mapa das colunas default para fácil acesso
  const defaultMap = colunasDefault.reduce((map, col) => {
    map[col.coluna] = col
    return map
  }, {})

  // Resultado: primeiro as colunas do cache na ordem salva
  const resultado = []

  // Adicionar colunas na ordem do cache
  colunasCache.forEach((colCache) => {
    if (defaultMap[colCache.coluna]) {
      resultado.push({
        coluna: colCache.coluna,
        ativarColuna: colCache.ativarColuna
      })
    }
  })

  // Adicionar colunas novas do default que não existiam no cache
  colunasDefault.forEach((colDefault) => {
    if (!resultado.find((c) => c.coluna === colDefault.coluna)) {
      resultado.push({ ...colDefault })
    }
  })

  return resultado
}

// Abrir dialog de configuração
function abrirDialogConfig() {
  dialogConfig.value = true
}

// Salvar configuração de colunas
async function salvarVisualizacao() {
  await userPreferences.set(PREF_KEYS.visualizacaoTabela, colunas.value)
  dialogConfig.value = false
}

// Limpar configuração de colunas (resetar para padrão)
async function limparVisualizacao() {
  const confirmacao = await swalConfirm(
    "Confirmar Reset",
    "Deseja realmente resetar as configurações de colunas para o padrão?",
    "Sim, resetar",
    "Cancelar"
  )

  if (!confirmacao.isConfirmed) return

  await userPreferences.remove(PREF_KEYS.visualizacaoTabela)
  colunas.value = colunasDefault.map((col) => ({ ...col }))
  dialogConfig.value = false
}

// Inicialização
onMounted(async () => {
  // Carregar preferências
  await carregarPreferencias()

  loadingInicial.value = false
})
</script>

<template>
  <Head title="Relatórios - Solicitações" />
  <AuthenticatedLayout>
    <!-- Loading Inicial -->
    <div
      v-if="loadingInicial"
      class="fixed inset-0 z-50 flex items-center justify-center bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm"
    >
      <div class="flex flex-col items-center gap-4">
        <i class="pi pi-spin pi-spinner text-5xl text-blue-600"></i>
        <span class="text-lg font-medium text-gray-600 dark:text-gray-300">
          Carregando...
        </span>
      </div>
    </div>

    <!-- Breadcrumb Página -->
    <div
      class="w-full flex flex-wrap items-center bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-xl mb-4 sm:mb-6 border border-gray-200 dark:border-slate-700"
    >
      <div
        class="flex flex-wrap items-center gap-1 sm:gap-2 text-sm sm:text-base text-gray-600 dark:text-gray-300 font-medium w-full"
      >
        <div class="flex items-center gap-1 sm:gap-2">
          <i class="pi pi-home"></i>
          <span>Home</span>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span>Solicitações</span>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span
            class="text-gray-950 dark:text-white font-bold truncate max-w-[120px] sm:max-w-none"
          >
            Relatórios
          </span>
        </div>
      </div>
    </div>

    <!-- Cabeçalho da Página -->
    <div class="space-y-2 mb-6 mt-4">
      <div class="flex items-center gap-3">
        <h2
          class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
        >
          <div
            class="w-1 h-8 bg-gradient-to-b from-blue-500 to-blue-700 rounded-full"
          ></div>
          Relatórios de Solicitações
        </h2>
      </div>
      <span
        class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-bold pl-2 pr-2 sm:pr-0 break-words whitespace-normal"
      >
        Gere relatórios detalhados das solicitações com filtros avançados e
        exportação para Excel.
      </span>
    </div>

    <!-- Filtros com Panel -->
    <Panel
      header="Filtros"
      toggleable
      :collapsed="false"
      class="mb-6 bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
    >
      <template #header>
        <div class="flex items-center gap-2 mb-2">
          <span
            class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-200 dark:bg-blue-900 shadow-lg flex-shrink-0"
          >
            <i
              class="pi pi-filter text-blue-700 dark:text-blue-300 !text-xl"
            ></i>
          </span>
          <div>
            <h3 class="text-2xl font-extrabold text-black-800 dark:text-white">
              Filtros
            </h3>
            <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
              Utilize os filtros abaixo para refinar o relatório de
              solicitações.
            </div>
          </div>
        </div>
      </template>

      <div class="flex flex-col gap-4 w-full">
        <!-- Grid de filtros - Linha 1 -->
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 items-end gap-4 w-full"
        >
          <!-- ID -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              ID
            </label>
            <InputText
              v-model="filtro.id"
              placeholder="Ex: 12345"
              class="w-full h-10 px-3"
            />
          </div>

          <!-- Departamento -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Departamento *
            </label>
            <Select
              v-model="filtro.departamento"
              :options="departamentos"
              optionLabel="condicao1"
              optionValue="condicao1"
              placeholder="Selecione"
              class="w-full h-10"
              @change="salvarDepartamento"
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Assuntos -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Assuntos
            </label>
            <MultiSelect
              v-model="filtro.assuntos"
              :options="assuntosOptions"
              optionLabel="assunto"
              optionValue="id"
              placeholder="Todos"
              :maxSelectedLabels="2"
              class="w-full h-10"
              :disabled="!filtro.departamento || buscaPorIdAtiva"
              filter
            />
          </div>

          <!-- Responsável -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Responsável
            </label>
            <Select
              v-model="filtro.responsavel"
              :options="responsaveisOptions"
              :optionLabel="(item) => item?.nome || item?.NOME"
              :optionValue="(item) => item?.matricula || item?.MATRICULA"
              placeholder="Todos"
              class="w-full h-10"
              showClear
              filter
              :disabled="!filtro.departamento || buscaPorIdAtiva"
            />
          </div>

          <!-- Solicitante -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Solicitante (matrícula)
            </label>
            <InputText
              v-model="filtro.solicitante"
              placeholder="Matrícula"
              class="w-full h-10 px-3"
              :disabled="buscaPorIdAtiva"
            />
          </div>
        </div>

        <!-- Grid de filtros - Linha 2 -->
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 items-end gap-4 w-full"
        >
          <!-- Filial -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Filial
            </label>
            <Filial
              v-model="filtro.filial"
              :multiSelect="true"
              class="w-full"
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Status -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Status
            </label>
            <MultiSelect
              v-model="filtro.status"
              :options="statusList"
              optionLabel="label"
              optionValue="value"
              placeholder="Todos"
              :maxSelectedLabels="2"
              class="w-full h-10"
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Prioridade -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Prioridade
            </label>
            <MultiSelect
              v-model="filtro.prioridade"
              :options="prioridadeList"
              optionLabel="label"
              optionValue="value"
              placeholder="Todas"
              :maxSelectedLabels="2"
              class="w-full h-10"
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Data Início -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Data Início
            </label>
            <DatePicker
              v-model="filtro.dataInicio"
              dateFormat="dd/mm/yy"
              placeholder="dd/mm/aaaa"
              showIcon
              showButtonBar
              fluid
              class="w-full"
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Data Fim -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Data Fim
            </label>
            <DatePicker
              v-model="filtro.dataFim"
              dateFormat="dd/mm/yy"
              placeholder="dd/mm/aaaa"
              showIcon
              showButtonBar
              fluid
              class="w-full"
              :disabled="buscaPorIdAtiva"
            />
          </div>
        </div>

        <!-- Checkbox Atrasadas + Botões -->
        <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
          <div
            class="flex items-center gap-2"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <Checkbox
              v-model="filtro.atrasadas"
              :binary="true"
              inputId="atrasadas"
              :disabled="buscaPorIdAtiva"
            />
            <label
              for="atrasadas"
              class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300"
            >
              Apenas atrasadas
            </label>
          </div>

          <div class="flex gap-2 w-full justify-end">
            <Button
              label="Aplicar Filtros"
              icon="pi pi-search"
              severity="info"
              outlined
              @click="buscarDados"
              :loading="loading"
            />
            <Button
              label="Limpar"
              icon="pi pi-times"
              severity="secondary"
              outlined
              @click="limparFiltros"
            />
          </div>
        </div>
      </div>
    </Panel>

    <!-- Cards de Estatísticas -->
    <div
      v-if="buscaRealizada && !erroMensagem"
      class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 px-1 sm:px-0"
    >
      <!-- Card Pendentes -->
      <div
        class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300"
      >
        <div class="flex items-center justify-between mb-1">
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-yellow-400"
          >
            <i class="pi pi-clock !text-[11px] flex-shrink-0"></i>
            {{ estatisticas[0].title }}
          </span>
        </div>
        <div
          class="flex w-full justify-start text-gray-950 dark:text-white text-xl font-bold"
        >
          <span>{{ estatisticas[0].value }}</span>
        </div>
      </div>

      <!-- Card Em Atendimento -->
      <div
        class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300"
      >
        <div class="flex items-center justify-between mb-1">
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-blue-500"
          >
            <i class="pi pi-comments !text-[11px] flex-shrink-0"></i>
            {{ estatisticas[1].title }}
          </span>
        </div>
        <div
          class="flex w-full justify-start text-gray-950 dark:text-white text-xl font-bold"
        >
          <span>{{ estatisticas[1].value }}</span>
        </div>
      </div>

      <!-- Card Finalizadas -->
      <div
        class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300"
      >
        <div class="flex items-center justify-between mb-1">
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-emerald-500"
          >
            <i class="pi pi-check-circle !text-[11px] flex-shrink-0"></i>
            {{ estatisticas[2].title }}
          </span>
        </div>
        <div
          class="flex w-full justify-start text-gray-950 dark:text-white text-xl font-bold"
        >
          <span>{{ estatisticas[2].value }}</span>
        </div>
      </div>

      <!-- Card Atrasadas -->
      <div
        class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300"
      >
        <div class="flex items-center justify-between mb-1">
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-red-600"
          >
            <i
              class="pi pi-exclamation-triangle !text-[11px] flex-shrink-0"
            ></i>
            {{ estatisticas[3].title }}
          </span>
        </div>
        <div
          class="flex w-full justify-start text-gray-950 dark:text-white text-xl font-bold"
        >
          <span>{{ estatisticas[3].value }}</span>
        </div>
      </div>
    </div>

    <!-- Erro -->
    <div
      v-if="erroMensagem && !loading"
      class="mb-6 rounded-2xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-6"
    >
      <div class="flex items-center gap-3">
        <span
          class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex-shrink-0"
        >
          <i
            class="pi pi-exclamation-circle text-red-600 dark:text-red-400 text-xl"
          ></i>
        </span>
        <div>
          <h4 class="font-bold text-red-800 dark:text-red-300">
            Erro ao carregar dados
          </h4>
          <span class="text-red-600 dark:text-red-400 text-sm">
            {{ erroMensagem }}
          </span>
        </div>
      </div>
      <Button
        label="Tentar novamente"
        icon="pi pi-refresh"
        severity="danger"
        outlined
        class="mt-4"
        @click="buscarDados"
      />
    </div>

    <!-- Mensagem quando ainda não buscou -->
    <div
      v-if="!buscaRealizada && !loading"
      class="bg-white dark:bg-slate-800 rounded-3xl p-12 text-center shadow-sm"
    >
      <span
        class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 dark:bg-slate-700 mb-6"
      >
        <i class="pi pi-search !text-2xl text-gray-400 dark:text-gray-500"></i>
      </span>
      <h3 class="text-xl font-bold text-gray-700 dark:text-gray-200 mb-2">
        Gere seu relatório
      </h3>
      <p class="text-gray-500 dark:text-gray-400">
        Selecione os filtros desejados e clique em
        <strong class="text-blue-600 dark:text-blue-400">
          Aplicar Filtros
        </strong>
        para visualizar os dados.
      </p>
    </div>

    <!-- Tabela de Resultados -->
    <div
      v-if="buscaRealizada && !erroMensagem"
      class="bg-white dark:bg-slate-800 rounded-3xl p-4 sm:p-6 relative overflow-hidden"
    >
      <!-- Cabeçalho da Tabela -->
      <div
        class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6"
      >
        <div class="flex items-center gap-3 flex-1 min-w-0">
          <span
            class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-200 dark:bg-indigo-900/30 shadow-lg flex-shrink-0"
          >
            <i
              class="pi pi-list !text-xl text-indigo-700 dark:text-indigo-400"
            ></i>
          </span>
          <div>
            <h2
              class="text-xl sm:text-xl md:text-2xl font-extrabold text-black-800 dark:text-white drop-shadow truncate"
            >
              Resultados
              <span
                class="text-base font-normal text-gray-500 dark:text-gray-400 ml-2"
              >
                ({{ totalRegistros }} registro{{
                  totalRegistros !== 1 ? "s" : ""
                }})
              </span>
            </h2>
            <div
              class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 font-medium mt-1"
            >
              Clique em uma linha para abrir a solicitação
            </div>
          </div>
        </div>

        <!-- Busca, Configurar e Exportar -->
        <div
          class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3"
        >
          <IconField class="w-full sm:w-auto sm:flex-none">
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="filters.global.value"
              placeholder="Filtrar na tabela..."
              class="w-full sm:w-64 h-10"
            />
          </IconField>
          <div class="flex gap-2 w-full sm:w-auto">
            <Button
              icon="pi pi-cog"
              label="Configurar"
              severity="secondary"
              outlined
              v-tooltip.top="'Configurar Tabela'"
              @click="abrirDialogConfig"
              class="flex-1 sm:flex-none"
            />
            <Button
              label="Exportar"
              icon="pi pi-file-excel"
              severity="success"
              outlined
              @click="exportarExcel"
              :loading="loadingExport"
              :disabled="solicitacoes.length === 0"
              class="flex-1 sm:flex-none"
            />
            <Button
              v-if="temFluxo"
              label="Exportar Fluxo"
              icon="pi pi-sitemap"
              severity="help"
              outlined
              @click="exportarFluxo"
              :loading="loadingExportFluxo"
              :disabled="solicitacoes.length === 0"
              v-tooltip.top="'Exportar tramitação por etapa do fluxo'"
              class="flex-1 sm:flex-none"
            />
          </div>
        </div>
      </div>

      <!-- DataTable -->
      <div
        class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden"
      >
        <DataTable
          :value="solicitacoes"
          v-model:filters="filters"
          :globalFilterFields="[
            'id',
            'titulo',
            'assunto',
            'solicitante',
            'responsavel',
            'status',
            'prioridade'
          ]"
          :loading="loading"
          paginator
          :rows="20"
          :rowsPerPageOptions="[10, 20, 50, 100]"
          paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
          currentPageReportTemplate="Mostrando {first} a {last} de {totalRecords}"
          sortMode="multiple"
          removableSort
          stripedRows
          showGridlines
          class="text-sm"
          tableStyle="table-layout: auto; width: 100%"
          rowHover
          @rowClick="abrirSolicitacao($event.data)"
          :rowClass="() => 'cursor-pointer hover:bg-blue-50 transition-colors'"
        >
          <template #loading>
            <div
              class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-full shadow-md text-sm"
            >
              <i class="pi pi-spinner pi-spin text-xs"></i>
              <span class="font-medium">Carregando...</span>
            </div>
          </template>

          <template #empty>
            <div class="py-12 text-center">
              <span
                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-700 mb-4"
              >
                <i
                  class="pi pi-inbox text-3xl text-gray-400 dark:text-gray-500"
                ></i>
              </span>
              <p class="text-gray-500 dark:text-gray-400 font-medium">
                Nenhuma solicitação encontrada
              </p>
              <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">
                Tente ajustar os filtros
              </p>
            </div>
          </template>

          <Column
            v-if="colunaVisivel('id')"
            field="id"
            header="ID"
            sortable
            style="min-width: 90px"
          >
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <i class="pi pi-hashtag text-blue-500"></i>
                <span
                  class="font-mono text-sm text-gray-800 dark:text-gray-200 bg-blue-50 dark:bg-blue-900/30 px-2 py-1 rounded-lg shadow-sm"
                >
                  {{ data.id }}
                </span>
              </div>
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('titulo')"
            field="titulo"
            header="Título"
            sortable
            style="min-width: 250px"
          >
            <template #body="{ data }">
              <div
                class="truncate font-medium text-gray-800 dark:text-gray-200"
              >
                <i class="pi pi-file text-blue-400"></i>
                {{ data.titulo || "-" }}
              </div>
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('assunto')"
            field="assunto"
            header="Assunto"
            sortable
            style="min-width: 150px"
          >
            <template #body="{ data }">
              <div class="truncate text-gray-600 dark:text-gray-300">
                <i class="pi pi-tags text-blue-400"></i>
                {{ data.assunto || "-" }}
              </div>
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('departamento')"
            field="departamento"
            header="Departamento"
            sortable
            style="min-width: 130px"
          >
            <template #body="{ data }">
              <div class="truncate text-gray-600 dark:text-gray-300">
                <i class="pi pi-sitemap text-blue-400"></i>
                {{ data.departamento || "-" }}
              </div>
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('filial')"
            field="filial"
            header="Filial"
            sortable
            style="min-width: 100px"
          >
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <i class="pi pi-building text-blue-400"></i>
                <span
                  class="font-medium text-gray-800 truncate dark:text-gray-200"
                >
                  {{ data.filial || "-" }}
                </span>
              </div>
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('solicitante')"
            field="solicitante"
            header="Solicitante"
            sortable
            style="min-width: 150px"
          >
            <template #body="{ data }">
              <div
                class="flex items-center gap-2"
                v-tooltip.top="data.solicitante"
              >
                <!-- Avatar com foto ou iniciais -->
                <div class="relative flex-shrink-0">
                  <img
                    v-if="data.solicitante_foto"
                    :src="data.solicitante_foto"
                    :alt="data.solicitante"
                    class="w-7 h-7 rounded-full object-cover ring-2 ring-purple-100"
                  />
                  <span
                    v-else
                    class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 text-purple-600 text-xs font-bold"
                  >
                    {{ obterIniciais(data.solicitante) }}
                  </span>
                </div>
                <span
                  class="max-w-[120px] truncate text-gray-700 dark:text-gray-300 font-medium"
                >
                  {{ obterNomeSobrenome(data.solicitante) }}
                </span>
              </div>
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('responsavel')"
            field="responsavel"
            header="Responsável"
            sortable
            style="min-width: 150px"
          >
            <template #body="{ data }">
              <div
                class="flex items-center gap-2"
                v-tooltip.top="data.responsavel"
              >
                <!-- Avatar com foto ou iniciais -->
                <div class="relative flex-shrink-0">
                  <img
                    v-if="data.responsavel_foto"
                    :src="data.responsavel_foto"
                    :alt="data.responsavel"
                    class="w-7 h-7 rounded-full object-cover ring-2 ring-indigo-100"
                  />
                  <span
                    v-else
                    class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold"
                  >
                    {{ obterIniciais(data.responsavel) }}
                  </span>
                </div>
                <span
                  class="max-w-[120px] truncate text-gray-700 dark:text-gray-300 font-medium"
                >
                  {{ obterNomeSobrenome(data.responsavel) }}
                </span>
              </div>
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('status')"
            field="status"
            header="Status"
            sortable
            style="min-width: 140px"
          >
            <template #body="{ data }">
              <Tag
                :value="getStatusLabel(data.status)"
                :severity="getStatusSeverity(data.status)"
                class="font-medium truncate"
              />
            </template>
          </Column>

          <!-- Etapa de Andamento (Timeline Style) -->
          <Column
            v-if="colunaVisivel('etapa_atual')"
            field="etapa_atual"
            header="Etapa Andamento"
            style="min-width: 160px"
          >
            <template #body="{ data }">
              <div class="flex items-center">
                <div
                  v-if="data.etapa_atual?.etapa"
                  class="flex items-center gap-2"
                  v-tooltip.top="'Etapa atual: ' + data.etapa_atual.etapa.nome"
                >
                  <!-- Indicador visual tipo timeline -->
                  <div class="relative flex items-center">
                    <div
                      class="w-2.5 h-2.5 rounded-full ring-2 ring-offset-1 ring-offset-white dark:ring-offset-slate-800 animate-pulse"
                      :style="{
                        backgroundColor:
                          data.etapa_atual.etapa.cor || '#10b981',
                        boxShadow:
                          '0 0 8px ' +
                          (data.etapa_atual.etapa.cor || '#10b981') +
                          '60'
                      }"
                    ></div>
                  </div>
                  <!-- Badge com nome da etapa -->
                  <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold shadow-sm transition-all duration-200"
                    :style="{
                      backgroundColor:
                        (data.etapa_atual.etapa.cor || '#10b981') + '18',
                      color: data.etapa_atual.etapa.cor || '#10b981',
                      border:
                        '1px solid ' +
                        (data.etapa_atual.etapa.cor || '#10b981') +
                        '35'
                    }"
                  >
                    <i
                      v-if="data.etapa_atual.etapa.icone"
                      :class="data.etapa_atual.etapa.icone"
                      class="text-[10px]"
                    ></i>
                    <i
                      v-else
                      class="fas fa-sitemap text-[10px]"
                    ></i>
                    <span class="truncate max-w-[100px]">
                      {{ data.etapa_atual.etapa.nome }}
                    </span>
                  </span>
                </div>
                <span
                  v-else
                  class="text-gray-400 dark:text-gray-500 text-xs italic"
                >
                  -
                </span>
              </div>
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('prioridade')"
            field="prioridade"
            header="Prioridade"
            sortable
            style="min-width: 110px"
          >
            <template #body="{ data }">
              <Tag
                :value="getPrioridadeLabel(data.prioridade)"
                :severity="getPrioridadeSeverity(data.prioridade)"
                class="font-medium"
              />
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('created_at')"
            field="created_at"
            header="Criação"
            sortable
            style="min-width: 120px"
          >
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <i class="pi pi-calendar text-blue-400 text-xs"></i>
                <span class="text-gray-600 dark:text-gray-300 truncate">
                  {{ data.created_at }}
                </span>
              </div>
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('updated_at')"
            field="updated_at"
            header="Última Atualização"
            sortable
            class="whitespace-nowrap"
            style="min-width: 140px"
          >
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <i class="pi pi-calendar text-green-500 text-xs"></i>
                <span class="text-gray-600 dark:text-gray-300 truncate">
                  {{ data.updated_at }}
                </span>
              </div>
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('data_conclusao')"
            field="data_conclusao"
            header="Data de Conclusão"
            sortable
            class="whitespace-nowrap"
            style="min-width: 160px"
          >
            <template #body="{ data }">
              <div v-if="data.data_conclusao" class="flex items-center gap-2">
                <i class="pi pi-check-circle text-green-500 text-xs"></i>
                <span class="text-gray-600 dark:text-gray-300 truncate">
                  {{ data.data_conclusao }}
                </span>
              </div>
              <span
                v-else
                class="text-gray-400 dark:text-gray-500"
              >
                -
              </span>
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('previsao_entrega')"
            field="previsao_entrega"
            header="Previsão"
            sortable
            style="min-width: 110px"
          >
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <i class="pi pi-calendar-plus text-red-600 text-xs"></i>
                <span
                  :class="{
                    'text-red-600 font-semibold': data.dias_atraso,
                    'text-gray-600 dark:text-gray-300': !data.dias_atraso
                  }"
                >
                  {{ data.previsao_entrega || "-" }}
                </span>
              </div>
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('dias_aberta')"
            field="dias_aberta"
            header="Dias"
            sortable
            style="min-width: 80px"
          >
            <template #body="{ data }">
              <span
                class="inline-flex items-center truncate gap-1 bg-blue-50 dark:bg-blue-900/30 text-gray-700 dark:text-gray-300 text-xs font-medium px-2 py-1 rounded-full"
              >
                <i class="pi pi-clock text-blue-400 text-xs"></i>
                {{ parseInt(data.dias_aberta) }} Dias
              </span>
            </template>
          </Column>

          <Column
            v-if="colunaVisivel('dias_atraso')"
            field="dias_atraso"
            header="Atraso"
            sortable
            style="min-width: 90px"
          >
            <template #body="{ data }">
              <span
                v-if="data.dias_atraso"
                class="inline-flex truncate items-center gap-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-xs font-bold px-2 py-1 rounded-full"
              >
                <i class="pi pi-exclamation-triangle text-xs"></i>
                {{ parseInt(data.dias_atraso) }} Dias
              </span>
              <span
                v-else
                class="text-gray-400 dark:text-gray-500"
              >
                -
              </span>
            </template>
          </Column>

          <!-- Descrição -->
          <Column
            v-if="colunaVisivel('descricao')"
            field="descricao"
            header="Descrição"
            style="min-width: 200px; max-width: 300px"
          >
            <template #body="{ data }">
              <div
                class="truncate text-gray-600 dark:text-gray-300"
                v-tooltip.top="data.descricao"
              >
                {{ data.descricao || "-" }}
              </div>
            </template>
          </Column>

          <!-- Usuário Origem -->
          <Column
            v-if="colunaVisivel('usuario_origem')"
            field="usuario_origem"
            header="Usuário Origem"
            sortable
            class="whitespace-nowrap"
            style="min-width: 150px"
          >
            <template #body="{ data }">
              <div
                v-if="data.usuario_origem"
                class="flex items-center gap-2"
                v-tooltip.top="data.usuario_origem"
              >
                <span
                  class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gradient-to-br from-purple-500 to-purple-600 text-white text-xs font-semibold shadow-sm"
                >
                  {{ obterIniciais(data.usuario_origem) }}
                </span>
                <span class="truncate text-gray-700 dark:text-gray-200">
                  {{ obterNomeSobrenome(data.usuario_origem) }}
                </span>
              </div>
              <span
                v-else
                class="text-gray-400 dark:text-gray-500"
              >
                -
              </span>
            </template>
          </Column>

          <!-- Usuários Destino -->
          <Column
            v-if="colunaVisivel('usuarios_destino')"
            field="usuarios_destino"
            header="Usuários Destino"
            style="min-width: 150px"
          >
            <template #body="{ data }">
              <div
                v-if="data.usuarios_destino && data.usuarios_destino.length > 0"
                class="truncate text-gray-600 dark:text-gray-300"
                v-tooltip.top="data.usuarios_destino.join(', ')"
              >
                {{ data.usuarios_destino.join(", ") }}
              </div>
              <span
                v-else
                class="text-gray-400 dark:text-gray-500"
              >
                -
              </span>
            </template>
          </Column>

          <!-- Solicitação Pai -->
          <Column
            v-if="colunaVisivel('solicitacao_pai_id')"
            field="solicitacao_pai_id"
            header="Solicitação Pai"
            class="whitespace-nowrap"
            sortable
            style="min-width: 100px"
          >
            <template #body="{ data }">
              <Tag
                v-if="data.solicitacao_pai_id"
                :value="'#' + data.solicitacao_pai_id"
                severity="info"
                class="text-xs font-mono"
              />
              <span
                v-else
                class="text-gray-400 dark:text-gray-500"
              >
                -
              </span>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- Dialog de Visualização da Solicitação -->
    <Dialog
      v-model:visible="dialogSolicitacao"
      modal
      class="!bg-transparent !border-0 !shadow-none"
    >
      <template #container>
        <Solicitacao
          v-if="solicitacaoSelecionada"
          :solicitacao_id="solicitacaoSelecionada.id"
          :permissoes="page.props.permissoes"
          :auth="page.props.auth"
          @fecharDialogo="dialogSolicitacao = false"
        />
      </template>
    </Dialog>

    <!-- Dialog Configurar Tabela -->
    <Dialog
      v-model:visible="dialogConfig"
      modal
      position="top"
      :closable="false"
      :pt="{
        root: {
          class:
            '!border-0 !rounded-2xl overflow-hidden w-[95vw] sm:w-[380px] !bg-transparent !shadow-none'
        },
        header: { class: 'hidden' },
        content: { class: '!p-0' },
        mask: { class: 'backdrop-blur-sm' }
      }"
    >
      <div
        class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden shadow-2xl"
      >
        <!-- Header Personalizado -->
        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-5 py-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
              >
                <i class="pi pi-table text-white text-lg"></i>
              </div>
              <div>
                <h3 class="text-lg font-bold text-white">Configurar Tabela</h3>
                <p class="text-white/80 text-xs">
                  Escolha quais colunas exibir
                </p>
              </div>
            </div>
            <Button
              @click="dialogConfig = false"
              icon="pi pi-times"
              rounded
              outlined
              severity="secondary"
              class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
            />
          </div>
        </div>

        <!-- Conteúdo -->
        <div class="p-5">
          <div
            class="max-h-[50vh] overflow-y-auto space-y-2.5 pr-1 custom-scrollbar"
          >
            <template
              v-for="(col, index) in colunas"
              :key="index"
            >
              <div
                class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer group"
                @click="col.ativarColuna = !col.ativarColuna"
              >
                <Checkbox
                  v-model="col.ativarColuna"
                  :binary="true"
                  :inputId="'col-' + index"
                  class="pointer-events-none"
                />
                <label
                  :for="'col-' + index"
                  class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors flex-1"
                >
                  {{ deParaColunas(col.coluna) }}
                </label>
                <i
                  class="pi text-xs transition-all"
                  :class="
                    col.ativarColuna
                      ? 'pi-eye text-blue-500'
                      : 'pi-eye-slash text-gray-400'
                  "
                ></i>
              </div>
            </template>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-5 pb-5 flex gap-3">
          <Button
            label="Resetar"
            icon="pi pi-refresh"
            severity="secondary"
            outlined
            class="!rounded-xl flex-1"
            @click="limparVisualizacao"
          />
          <Button
            label="Salvar"
            icon="pi pi-check"
            severity="success"
            class="!rounded-xl flex-1 !bg-gradient-to-r !from-blue-500 !to-indigo-600 !border-0"
            @click="salvarVisualizacao"
          />
        </div>
      </div>
    </Dialog>
  </AuthenticatedLayout>
</template>
