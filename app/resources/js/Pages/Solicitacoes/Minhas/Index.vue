<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import Solicitacao from "@/Pages/Solicitacoes/Ticket.vue"
import {
  formatarData,
  formatarDataSemHoras,
  swalErro,
  swalObservacao,
  swalConfirm,
  toastSuccess,
  toastError,
  exportarDadosParaExcel
} from "@/utils/globalFunctions"
import { Head, usePage, router } from "@inertiajs/vue3"
import {
  DatePicker,
  InputText,
  Select,
  MultiSelect,
  Button,
  Dialog,
  Panel,
  DataTable,
  Column,
  Tag,
  Checkbox
} from "primevue"
import { FilterMatchMode } from "@primevue/core/api"
import { onMounted, onUnmounted, ref, computed, Transition } from "vue"
import { useUserPreferences } from "@/composables/useUserPreferences"
import { useSolicitacoesEcho } from "@/composables/useSolicitacoesEcho"
import * as layout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"

// ✅ Marcar como página nova IMEDIATAMENTE (antes de qualquer requisição)
layout.paginaNova.value = true

const page = usePage()
const userPreferences = useUserPreferences()

// Chaves de preferências
const PREF_KEYS = {
  filtroMinhas: "solicitacoes.minhas.filtro",
  situacoesTodas: "solicitacoes.minhas.situacoesTodas",
  visualizacaoTabela: "solicitacoes.minhas.visualizacaoTabela"
}

const props = defineProps([
  "filiais",
  "usuarioLogado",
  "auth",
  "permissoes",
  "modoLideranca"
])

const loadingInicial = ref(true)
const tickets = ref(null)
const contagemCards = ref(null)
const filtrandoPorCard = ref(false)
const dialogSolicitacao = ref(false)
const solicitacaoSelecionada = ref(null)

// ✅ Reverb
const { escutarUsuario, desconectarTodos } = useSolicitacoesEcho()
let canalUsuario = null
const showFiltros = ref(false)
const dialogConfig = ref(false)
const colunasTemp = ref([]) // Cópia temporária para o dialog
const colunas = ref([])
const optionsFiliais = ref([])
const loadingExport = ref(false)
const cardsExpandidos = ref(new Set()) // Controla quais cards mobile estão expandidos

// Função para toggle do card expandido
function toggleCardExpandido(id) {
  if (cardsExpandidos.value.has(id)) {
    cardsExpandidos.value.delete(id)
  } else {
    cardsExpandidos.value.add(id)
  }
  // Força reatividade
  cardsExpandidos.value = new Set(cardsExpandidos.value)
}

// Filtro global da tabela
const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS }
})

// Colunas disponíveis para a tabela
const colunasDisponiveis = ref([
  { field: "id", header: "ID", visible: true },
  { field: "titulo", header: "Título", visible: true },
  { field: "situacao", header: "Situação", visible: true },
  { field: "etapa_atual", header: "Etapa Andamento", visible: true },
  { field: "prioridade", header: "Prioridade", visible: true },
  { field: "solicitante", header: "Solicitante", visible: false },
  { field: "responsavel", header: "Responsável", visible: false },
  { field: "assunto", header: "Assunto", visible: true },
  { field: "departamento", header: "Departamento", visible: true },
  { field: "filial", header: "Filial", visible: false },
  { field: "descricao", header: "Descrição", visible: false },
  { field: "dias_aberto", header: "Dias Aberto", visible: false },
  { field: "previsao_entrega", header: "Previsão de Entrega", visible: false },
  { field: "created_at", header: "Criado em", visible: true },
  { field: "updated_at", header: "Última Atualização", visible: true }
])

// Colunas visíveis (computada)
const colunasVisiveis = computed(() => {
  return colunasDisponiveis.value.filter((col) => col.visible)
})

const filtro = ref({
  id: null,
  prioridades: [],
  filiais: [],
  situacoes: [],
  dataIni: null,
  dataFim: null,
  dataAltIni: null,
  dataAltFim: null,
  porPagina: 10,
  pagina: 1,
  ordenacao: [
    {
      field: "id",
      order: -1
    }
  ]
})

// Situações disponíveis
const optionsSituacoes = [
  { name: "Pendente", value: "pendente" },
  { name: "Em Atendimento", value: "em atendimento" },
  { name: "Pausado", value: "atendimento pausado" },
  { name: "Agendado", value: "agendado" },
  { name: "Resolvida", value: "resolvida" },
  { name: "Recusada", value: "resolução recusada" },
  { name: "Finalizada", value: "finalizada" },
  { name: "Cancelada", value: "cancelada" },
  { name: "Retorno Solicitante", value: "retorno solicitante" },
  { name: "Atrasadas", value: "atrasadas" }
]

// Computed para verificar se busca por ID está ativa
const buscaPorIdAtiva = computed(() => {
  return (
    filtro.value.id !== null &&
    filtro.value.id !== "" &&
    filtro.value.id !== undefined
  )
})

onMounted(async () => {
  // Buscar filtro salvo
  const filtroSalvo = await userPreferences.get(PREF_KEYS.filtroMinhas)

  if (filtroSalvo) {
    filtro.value = { ...filtro.value, ...filtroSalvo }
  }

  const query = new URLSearchParams(window.location.search)
  if (query.has("solicitacao")) {
    solicitacaoSelecionada.value = { id: query.get("solicitacao") }
    dialogSolicitacao.value = true
  }

  // Buscar filiais, colunas e tickets em paralelo
  await Promise.all([
    buscarFiliais(),
    carregarConfigColunas(),
    getSolicitacoes()
  ])

  // ✅ Iniciar listener Reverb para atualizações em tempo real
  iniciarReverbListener()

  loadingInicial.value = false
})

// ✅ Cleanup ao desmontar componente
onUnmounted(() => {
  desconectarTodos()
})

// ✅ Iniciar listener do Reverb para o usuário atual
function iniciarReverbListener() {
  const matricula =
    props.auth?.user?.matricula || props.usuarioLogado?.matricula
  if (!matricula) {
    console.warn("[Minhas] Matrícula não encontrada para listener Reverb")
    return
  }

  console.log("[Minhas] Iniciando listener Reverb para matrícula:", matricula)

  canalUsuario = escutarUsuario(matricula, (data) => {
    // Notificação recebida - recarregar lista silenciosamente
    console.log("[Minhas] Notificação recebida via Reverb:", data)
    getSolicitacoes()
  })
}

function alterarPorPagina() {
  getSolicitacoes()
}

// Handler para mudança de página do DataTable
function onPageChange(event) {
  filtro.value.pagina = event.page + 1 // DataTable usa 0-based, backend usa 1-based
  filtro.value.porPagina = event.rows
  getSolicitacoes()
}

// Handler para ordenação do DataTable
function aoOrdenar(event) {
  filtro.value.ordenacao = event.multiSortMeta
  getSolicitacoes()
}

// Verifica se a coluna é ordenável
function isColumnSortable(field) {
  // Colunas que não são sortable
  const notSortable = ["descricao", "etapa_atual"]
  return !notSortable.includes(field)
}

const solicitacoesFiltered = computed(() => {
  if (!tickets.value) return []
  return tickets.value.filter((i) => !i.ocultarSolicitacao)
})

async function getSolicitacoes() {
  const filtroTmp = {
    ...filtro.value,
    aba: "minhas"
  }

  try {
    const response = await axios.post(
      "/solicitacoes/lista/buscar-solicitacoes",
      filtroTmp
    )

    // Salvar filtros
    const filtroSanitizado = userPreferences.sanitizeFiltro(filtro.value)
    userPreferences.set(PREF_KEYS.filtroMinhas, filtroSanitizado)
    if (filtro.value.situacoes.length != 1) {
      userPreferences.set(PREF_KEYS.situacoesTodas, filtro.value.situacoes)
    }

    filtro.value.porPagina = response.data.paginacao.porPagina
    tickets.value = response.data["solicitacoes"].data
    tickets.value.paginacao = response.data["paginacao"]
    tickets.value.contagem = response.data["contagem"]

    if (!filtrandoPorCard.value) {
      contagemCards.value = response.data["contagem"]
    }

    // Mesclar colunas do servidor com cache
    const colunasServidor = response.data["colunas"]
    const colunasCache = await userPreferences.get(
      PREF_KEYS.visualizacaoTabela,
      []
    )

    if (!Array.isArray(colunas.value) || colunas.value.length == 0) {
      colunas.value = mesclarColunasComCache(colunasServidor, colunasCache)
    }

    tickets.value.forEach((item) => {
      if (
        item.status == "cancelada" ||
        item.status == "finalizada" ||
        item.status == "resolvida" ||
        item.status == "em atendimento" ||
        item.status == "agendado"
      ) {
        item.desabilitaCheckbox = true
      }
      item.checked = false
    })

    tickets.value.totalAbertos = tickets.value.filter(
      (i) =>
        i.status != "finalizada" &&
        i.status != "cancelada" &&
        i.status != "resolvida"
    ).length
  } catch (error) {
    console.error(error)
    swalErro()
  }
}

function mesclarColunasComCache(colunasServidor, colunasCache) {
  if (!colunasCache || colunasCache.length === 0) {
    return colunasServidor
  }

  return colunasServidor.map((colServidor) => {
    const colCache = colunasCache.find((c) => c.field === colServidor.field)
    return colCache
      ? { ...colServidor, visible: colCache.visible ?? colServidor.visible }
      : colServidor
  })
}

function getClassPrioridade(prioridade) {
  switch (prioridade) {
    case "baixa":
      return "text-gray-500 dark:text-gray-400"
    case "media":
      return "text-blue-700 dark:text-blue-400"
    case "alta":
      return "text-yellow-600 dark:text-yellow-400"
    case "urgente":
      return "text-red-600 dark:text-red-400"
    default:
      return "text-gray-500 dark:text-gray-400"
  }
}

function getIconePrioridade(prioridade) {
  switch (prioridade) {
    case "baixa":
      return "fa-solid fa-angles-down"
    case "media":
      return "fa-solid fa-minus"
    case "alta":
      return "fa-solid fa-angle-up"
    case "urgente":
      return "fa-solid fa-angles-up"
    default:
      return "fa-solid fa-minus"
  }
}

function corStatus(status) {
  switch (status) {
    case "pendente":
      return "bg-yellow-400 dark:bg-yellow-500"
    case "em atendimento":
      return "bg-blue-500 dark:bg-blue-600"
    case "atendimento pausado":
      return "bg-orange-400 dark:bg-orange-500"
    case "agendado":
      return "bg-purple-500 dark:bg-purple-600"
    case "resolvida":
      return "bg-green-500 dark:bg-green-600"
    case "resolução recusada":
      return "bg-red-400 dark:bg-red-500"
    case "finalizada":
      return "bg-green-700 dark:bg-green-800"
    case "cancelada":
      return "bg-gray-500 dark:bg-gray-600"
    case "retorno solicitante":
      return "bg-cyan-500 dark:bg-cyan-600"
    default:
      return "bg-gray-400 dark:bg-gray-500"
  }
}

function textoStatus(status) {
  switch (status) {
    case "pendente":
      return "Pendente"
    case "em atendimento":
      return "Atendimento"
    case "atendimento pausado":
      return "Pausado"
    case "agendado":
      return "Agendado"
    case "resolvida":
      return "Resolvida"
    case "resolução recusada":
      return "Recusada"
    case "finalizada":
      return "Finalizada"
    case "cancelada":
      return "Cancelada"
    case "retorno solicitante":
      return "Retorno"
    default:
      return status
  }
}

// Severity do status para Tag do PrimeVue
function getStatusSeverity(status) {
  const severities = {
    pendente: "warn",
    "em atendimento": "info",
    "atendimento pausado": "secondary",
    agendado: "help",
    resolvida: "success",
    "resolução recusada": "danger",
    finalizada: "success",
    cancelada: "secondary",
    "retorno solicitante": "info"
  }
  return severities[status] || "secondary"
}

// Verificar se a ticket está atrasada (previsão de entrega vencida)
function estaAtrasada(solicitacao) {
  if (!solicitacao.previsao_entrega) return false

  // Status que podem estar atrasados
  const statusAtrasaveis = [
    "pendente",
    "em atendimento",
    "atendimento pausado",
    "agendado"
  ]
  if (!statusAtrasaveis.includes(solicitacao.status)) return false

  const hoje = new Date()
  hoje.setHours(0, 0, 0, 0)
  const previsao = new Date(solicitacao.previsao_entrega)
  previsao.setHours(0, 0, 0, 0)

  return previsao < hoje
}

// Calcular dias de atraso
function diasDeAtraso(solicitacao) {
  if (!estaAtrasada(solicitacao)) return 0

  const hoje = new Date()
  hoje.setHours(0, 0, 0, 0)
  const previsao = new Date(solicitacao.previsao_entrega)
  previsao.setHours(0, 0, 0, 0)

  const diffTime = hoje - previsao
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  return diffDays
}

// Severity da prioridade para Tag do PrimeVue
function getPrioridadeSeverity(prioridade) {
  const severities = {
    urgente: "danger",
    alta: "warn",
    media: "info",
    baixa: "secondary"
  }
  return severities[prioridade] || "secondary"
}

// Label da prioridade formatado
function getPrioridadeLabel(prioridade) {
  const labels = {
    urgente: "Urgente",
    alta: "Alta",
    media: "Média",
    baixa: "Baixa"
  }
  return labels[prioridade] || prioridade
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
  if (!nome) return "Não atribuído"
  const partes = nome
    .trim()
    .split(" ")
    .filter((p) => p.length > 0)
  if (partes.length === 0) return "Não atribuído"
  if (partes.length === 1) return partes[0]
  return `${partes[0]} ${partes[partes.length - 1]}`
}

// Visual do status para cards mobile
function getStatusVisual(status) {
  switch (status) {
    case "pendente":
      return { icon: "far fa-clock", color: "#eab308" }
    case "em atendimento":
      return { icon: "fas fa-spinner", color: "#60a5fa" }
    case "atendimento pausado":
      return { icon: "fas fa-pause", color: "#f0e258" }
    case "agendado":
      return { icon: "fas fa-calendar-check", color: "#06b6d4" }
    case "resolvida":
      return { icon: "fas fa-check", color: "#34d399" }
    case "resolução recusada":
      return { icon: "fas fa-ban", color: "#f87171" }
    case "finalizada":
      return { icon: "fas fa-check-double", color: "#10b981" }
    case "cancelada":
      return { icon: "fas fa-times", color: "#9ca3af" }
    case "retorno solicitante":
      return { icon: "fas fa-undo", color: "#FFA500" }
    case "atrasadas":
      return { icon: "fas fa-clock", color: "#dc2626" }
    default:
      return { icon: "fas fa-question", color: "#d1d5db" }
  }
}

// Exportar para Excel - exporta apenas colunas visíveis
async function exportarExcel() {
  if (!tickets.value || tickets.value.length === 0) {
    toastError("Nenhuma ticket para exportar")
    return
  }

  loadingExport.value = true
  try {
    // Obter apenas as colunas visíveis
    const colunasVisiveis = colunasDisponiveis.value.filter((c) => c.visible)

    // Mapear os dados de acordo com as colunas visíveis
    const dados = solicitacoesFiltered.value.map((s) => {
      const linha = {}
      colunasVisiveis.forEach((col) => {
        switch (col.field) {
          case "id":
            linha["ID"] = s.id
            break
          case "titulo":
            linha["Título"] = s.titulo || "-"
            break
          case "situacao":
            linha["Situação"] = textoStatus(s.status)
            break
          case "prioridade":
            linha["Prioridade"] = getPrioridadeLabel(s.prioridade)
            break
          case "solicitante":
            linha["Solicitante"] = s.usuario_solicitante?.nome || "-"
            break
          case "responsavel":
            linha["Responsável"] = s.usuario_responsavel?.nome || "-"
            break
          case "assunto":
            linha["Assunto"] = s.assunto?.assunto || "-"
            break
          case "departamento":
            linha["Departamento"] = s.departamento_responsavel || "-"
            break
          case "filial":
            linha["Filial"] = s.filial
              ? `${s.filial.codigo} - ${s.filial.fantasia}`
              : "-"
            break
          case "descricao":
            linha["Descrição"] = s.descricao
              ? s.descricao
                  .replace(/<[^>]*>/g, "")
                  .replace(/&nbsp;/g, " ")
                  .trim()
                  .substring(0, 200)
              : "-"
            break
          case "dias_aberto":
            linha["Dias Aberto"] = Math.floor(Number(s.dias_aberto) || 0)
            break
          case "previsao_entrega":
            linha["Previsão de Entrega"] = s.previsao_entrega
              ? formatarData(s.previsao_entrega)
              : "-"
            break
          case "created_at":
            linha["Criado em"] = s.created_at ? formatarData(s.created_at) : "-"
            break
          case "updated_at":
            linha["Última Atualização"] = s.updated_at
              ? formatarData(s.updated_at)
              : "-"
            break
          case "data_conclusao":
            linha["Data de Conclusão"] = s.data_conclusao
              ? formatarData(s.data_conclusao)
              : "-"
            break
          case "etapa_atual":
            linha["Etapa Andamento"] = s.etapa_atual?.etapa?.nome || "-"
            break
        }
      })
      return linha
    })

    exportarDadosParaExcel(dados, "minhas_solicitacoes.xlsx")
  } catch (error) {
    console.error(error)
    toastError("Erro ao exportar dados")
  } finally {
    loadingExport.value = false
  }
}

// Salvar configuração de colunas
async function salvarConfigColunas() {
  colunasDisponiveis.value = colunasTemp.value.map((col) => ({ ...col }))
  await userPreferences.set(
    PREF_KEYS.visualizacaoTabela,
    colunasDisponiveis.value
  )
  dialogConfig.value = false
}

function abrirDialogConfig() {
  colunasTemp.value = colunasDisponiveis.value.map((col) => ({ ...col }))
  dialogConfig.value = true
}

// Resetar configuração de colunas
async function resetarColunas() {
  const confirmacao = await swalConfirm(
    "Confirmar Reset",
    "Deseja realmente resetar as configurações de colunas para o padrão?",
    "Sim, resetar",
    "Cancelar"
  )

  if (!confirmacao.isConfirmed) return

  colunasDisponiveis.value = [
    { field: "id", header: "ID", visible: true },
    { field: "titulo", header: "Título", visible: true },
    { field: "situacao", header: "Situação", visible: true },
    { field: "etapa_atual", header: "Etapa Andamento", visible: true },
    { field: "prioridade", header: "Prioridade", visible: true },
    { field: "solicitante", header: "Solicitante", visible: false },
    { field: "responsavel", header: "Responsável", visible: false },
    { field: "assunto", header: "Assunto", visible: true },
    { field: "departamento", header: "Departamento", visible: true },
    { field: "filial", header: "Filial", visible: false },
    { field: "descricao", header: "Descrição", visible: false },
    { field: "dias_aberto", header: "Dias Aberto", visible: false },
    {
      field: "previsao_entrega",
      header: "Previsão de Entrega",
      visible: false
    },
    { field: "created_at", header: "Criado em", visible: true },
    { field: "updated_at", header: "Última Atualização", visible: true }
  ]
  // Salvar reset
  userPreferences.set(PREF_KEYS.visualizacaoTabela, colunasDisponiveis.value)
  dialogConfig.value = false
}

// Salvar ordem das colunas quando arrastar no DataTable
function onColumnReorder(event) {
  // No PrimeVue 4, o evento contém dragIndex e dropIndex
  // Precisamos reordenar manualmente o array colunasDisponiveis
  const { dragIndex, dropIndex } = event

  // Pegar apenas colunas visíveis para reordenar
  const visiveisAtual = colunasDisponiveis.value.filter((c) => c.visible)
  const colunasOcultas = colunasDisponiveis.value.filter((c) => !c.visible)

  // Reordenar as colunas visíveis
  const [movedColumn] = visiveisAtual.splice(dragIndex, 1)
  visiveisAtual.splice(dropIndex, 0, movedColumn)

  // Reconstruir array: visíveis reordenadas + ocultas no final
  colunasDisponiveis.value = [...visiveisAtual, ...colunasOcultas]

  // Salvar automaticamente
  userPreferences.set(PREF_KEYS.visualizacaoTabela, colunasDisponiveis.value)
}

// Helpers para colunas dinâmicas
function getColumnField(field) {
  const fieldMap = {
    assunto: "assunto.assunto",
    departamento: "departamento_responsavel",
    situacao: "status",
    solicitante: "usuario_solicitante.nome",
    responsavel: "usuario_responsavel.nome",
    filial: "filial.fantasia"
  }
  return fieldMap[field] || field
}

function getColumnStyle(field) {
  const styleMap = {
    id: "min-width: 90px",
    titulo: "min-width: 200px; max-width: 300px",
    descricao: "min-width: 200px; max-width: 300px",
    assunto: "min-width: 150px",
    departamento: "min-width: 130px",
    prioridade: "min-width: 110px",
    situacao: "min-width: 130px",
    etapa_atual: "min-width: 160px",
    solicitante: "min-width: 170px",
    responsavel: "min-width: 170px",
    filial: "min-width: 120px",
    dias_aberto: "min-width: 110px",
    previsao_entrega: "min-width: 150px",
    created_at: "min-width: 140px",

    updated_at: "min-width: 160px"
  }
  return styleMap[field] || ""
}

function getColumnClass(field) {
  const classMap = {
    dias_aberto: "whitespace-nowrap",
    previsao_entrega: "whitespace-nowrap",

    updated_at: "whitespace-nowrap"
  }
  return classMap[field] || ""
}

// Carregar configuração de colunas salva
async function carregarConfigColunas() {
  const configSalva = await userPreferences.get(PREF_KEYS.visualizacaoTabela)

  if (configSalva && Array.isArray(configSalva)) {
    // Mesclar colunas salvas com colunas disponíveis (para incluir novas colunas)
    const colunasDefault = [
      { field: "id", header: "ID", visible: true },
      { field: "titulo", header: "Título", visible: true },
      { field: "situacao", header: "Situação", visible: true },
      { field: "etapa_atual", header: "Etapa Andamento", visible: true },
      { field: "prioridade", header: "Prioridade", visible: true },
      { field: "solicitante", header: "Solicitante", visible: false },
      { field: "responsavel", header: "Responsável", visible: false },
      { field: "assunto", header: "Assunto", visible: true },
      { field: "departamento", header: "Departamento", visible: true },
      { field: "filial", header: "Filial", visible: false },
      { field: "descricao", header: "Descrição", visible: false },
      { field: "dias_aberto", header: "Dias Aberto", visible: false },
      {
        field: "previsao_entrega",
        header: "Previsão de Entrega",
        visible: false
      },
      { field: "created_at", header: "Criado em", visible: true },
      { field: "updated_at", header: "Última Atualização", visible: true }
    ]

    // Mesclar respeitando a ORDEM salva
    const colunasOrdenadas = []

    // Primeiro, adicionar colunas na ordem salva
    configSalva.forEach((colSalva) => {
      const colDefault = colunasDefault.find((c) => c.field === colSalva.field)
      if (colDefault) {
        colunasOrdenadas.push({ ...colDefault, visible: colSalva.visible })
      }
    })

    // Depois, adicionar colunas novas que não existiam no save (no final)
    colunasDefault.forEach((colDefault) => {
      if (!colunasOrdenadas.find((c) => c.field === colDefault.field)) {
        colunasOrdenadas.push(colDefault)
      }
    })

    colunasDisponiveis.value = colunasOrdenadas
  }
}

function abrirSolicitacao(solicitacao) {
  solicitacaoSelecionada.value = solicitacao
  dialogSolicitacao.value = true
}

async function limparFiltros() {
  filtro.value = {
    id: null,
    prioridades: [],
    filiais: [],
    situacoes: [],
    dataIni: null,
    dataFim: null,
    dataAltIni: null,
    dataAltFim: null,
    porPagina: filtro.value.porPagina,
    pagina: 1,
    ordenacao: [
      {
        field: "id",
        order: -1
      }
    ]
  }

  // Limpar cache salvo no servidor
  await userPreferences.remove(PREF_KEYS.filtroMinhas)
  await userPreferences.remove(PREF_KEYS.situacoesTodas)

  getSolicitacoes()
}

async function filtrarPorLegenda(situacao) {
  const situacoesAtuais = await userPreferences.get(
    PREF_KEYS.situacoesTodas,
    []
  )
  if (filtro.value.situacoes == situacao) {
    filtrandoPorCard.value = false
    filtro.value.situacoes = situacoesAtuais
  } else {
    filtrandoPorCard.value = true
    filtro.value.situacoes = situacao
  }
  getSolicitacoes()
}

async function filtrarTodos() {
  const situacoesTodas = await userPreferences.get(PREF_KEYS.situacoesTodas, [])
  filtrandoPorCard.value = false
  filtro.value.situacoes = situacoesTodas
  getSolicitacoes()
}

async function buscarFiliais() {
  const filiaisMap = props.filiais?.map((filial) => ({
    name: filial.codigo + " - " + filial.fantasia,
    code: filial.codigo
  }))
  optionsFiliais.value = filiaisMap || []
}

function redirecionarNovaSolicitacao() {
  router.visit("/solicitacoes/nova")
}

function pageChanged(pg) {
  tickets.value.paginacao.pagina = pg
  getSolicitacoes()
}
</script>

<template>
  <Head title="Meus Tickets" />

  <AuthenticatedLayout>
    <!-- Loading Inicial -->
    <div
      v-if="loadingInicial"
      dusk="tickets-loading"
      class="flex items-center justify-center py-32"
    >
      <div class="flex flex-col items-center gap-6">
        <!-- Spinner animado -->
        <div class="relative">
          <div
            class="w-20 h-20 rounded-full border-4 border-blue-100 dark:border-slate-700"
          ></div>
          <div
            class="absolute top-0 left-0 w-20 h-20 rounded-full border-4 border-transparent border-t-blue-500 dark:border-t-blue-400 animate-spin"
          ></div>
          <div
            class="absolute top-2 left-2 w-16 h-16 rounded-full border-4 border-transparent border-t-indigo-400 dark:border-t-indigo-500 animate-spin"
            style="animation-duration: 1.5s; animation-direction: reverse"
          ></div>
        </div>
        <!-- Texto -->
        <div class="text-center">
          <p class="text-xl font-semibold text-gray-700 dark:text-gray-200">
            Carregando Tickets
          </p>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Aguarde um momento...
          </p>
        </div>
      </div>
    </div>

    <!-- Breadcrumb -->
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
          <span>Tickets</span>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span
            class="text-gray-950 dark:text-white font-bold truncate max-w-[120px] sm:max-w-none"
          >
            Minhas
          </span>
        </div>
      </div>
    </div>

    <!-- Cabeçalho da Página -->
    <div
      class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6"
    >
      <div class="space-y-2">
        <div class="flex items-center gap-3">
          <h2
            class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
          >
            <div
              class="w-1 h-8 bg-gradient-to-b from-blue-500 to-blue-700 rounded-full"
            ></div>
            Meus Tickets
          </h2>
        </div>
        <span
          class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-bold pl-4 pr-2 sm:pr-0 break-words whitespace-normal"
        >
          Acompanhe suas tickets e veja o status de cada uma.
        </span>
      </div>
    </div>

    <!-- Badge modo liderança -->
    <div
      v-if="props.modoLideranca"
      class="flex items-center gap-2 rounded-lg bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 px-4 py-2 text-blue-700 dark:text-blue-400 text-sm font-medium mb-4"
    >
      <i class="pi pi-users"></i>
      Você está visualizando
      <strong>todos os chamados da sua filial</strong>
    </div>

    <!-- Filtros com Panel -->
    <Panel
      header="Filtros"
      toggleable
      :collapsed="!showFiltros"
      @toggle="showFiltros = !showFiltros"
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
            <h3 class="text-2xl font-extrabold text-gray-800 dark:text-white">
              Filtros
            </h3>
            <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
              Utilize os filtros abaixo para refinar sua busca.
            </div>
          </div>
        </div>
      </template>

      <div class="flex flex-col gap-4 w-full">
        <!-- Grid de filtros - Linha 1 -->
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 items-end gap-4 w-full"
        >
          <!-- Filtro por ID -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              ID
              <span
                @click="
                  swalObservacao(
                    'ID é um número único que identifica sua ticket no sistema.'
                  )
                "
                v-tooltip.top="
                  'ID é um número único que identifica sua ticket no sistema.'
                "
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <InputText
              v-model="filtro.id"
              placeholder="Ex: 12345"
              class="w-full h-10 px-3"
            />
          </div>

          <!-- Filtro por prioridade -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Prioridade
              <span
                @click="
                  swalObservacao(
                    'Filtre as tickets por prioridade (urgente, alta, média ou baixa).'
                  )
                "
                v-tooltip.top="
                  'Filtre as tickets por prioridade (urgente, alta, média ou baixa).'
                "
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <MultiSelect
              :options="[
                { label: 'Baixa', value: 'baixa' },
                { label: 'Média', value: 'media' },
                { label: 'Alta', value: 'alta' },
                { label: 'Urgente', value: 'urgente' }
              ]"
              optionLabel="label"
              optionValue="value"
              v-model="filtro.prioridades"
              placeholder="Todas"
              class="w-full h-10"
              :maxSelectedLabels="2"
              selectedItemsLabel="{0} selecionadas"
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Filtro por situação -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Situação
              <span
                @click="
                  swalObservacao(
                    'Filtre as tickets pelo status atual (pendente, em atendimento, finalizada, etc).'
                  )
                "
                v-tooltip.top="
                  'Filtre as tickets pelo status atual (pendente, em atendimento, finalizada, etc).'
                "
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <MultiSelect
              :options="optionsSituacoes"
              option-label="name"
              option-value="value"
              v-model="filtro.situacoes"
              placeholder="Todas"
              class="w-full h-10"
              :maxSelectedLabels="2"
              selectedItemsLabel="{0} selecionadas"
              :disabled="buscaPorIdAtiva"
            />
          </div>
        </div>

        <!-- Grid de filtros - Linha 2 -->
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 items-end gap-4 w-full"
        >
          <!-- Filtro por Filial -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Filial
              <span
                @click="
                  swalObservacao(
                    'Filtre as tickets pela filial de origem.'
                  )
                "
                v-tooltip.top="'Filtre as tickets pela filial de origem.'"
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <MultiSelect
              v-model="filtro.filiais"
              :options="optionsFiliais"
              option-label="name"
              option-value="code"
              placeholder="Todas"
              class="w-full h-10"
              :maxSelectedLabels="1"
              selectedItemsLabel="{0} selecionadas"
              filter
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Filtro por data de criação início -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Criação Início
              <span
                @click="
                  swalObservacao(
                    'Buscar tickets criadas a partir desta data.'
                  )
                "
                v-tooltip.top="
                  'Buscar tickets criadas a partir desta data.'
                "
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <DatePicker
              v-model="filtro.dataIni"
              dateFormat="dd/mm/yy"
              placeholder="dd/mm/aaaa"
              showIcon
              showButtonBar
              fluid
              class="w-full"
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Filtro por data de criação fim -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Criação Fim
              <span
                @click="
                  swalObservacao('Buscar tickets criadas até esta data.')
                "
                v-tooltip.top="'Buscar tickets criadas até esta data.'"
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
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

        <!-- Botões -->
        <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
          <Button
            label="Aplicar Filtros"
            icon="pi pi-search"
            severity="info"
            outlined
            @click="getSolicitacoes()"
          />
          <Button
            label="Limpar"
            icon="pi pi-times"
            severity="secondary"
            outlined
            @click="limparFiltros()"
          />
        </div>
      </div>
    </Panel>

    <div class="relative w-full mx-auto">
      <!-- Cards de Estatísticas -->
      <div
        v-if="!buscaPorIdAtiva && tickets"
        class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-5 2xl:grid-cols-10 gap-3 mt-6 px-1 sm:px-0"
      >
        <!-- Card Total -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-indigo-500 scale-[1.02]':
              typeof filtro.situacoes !== 'string'
          }"
          @click="filtrarTodos()"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-indigo-600"
            >
              <i class="pi pi-list !text-[11px] flex-shrink-0"></i>
              Total
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.total ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Pendentes -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-yellow-400 scale-[1.02]':
              filtro.situacoes === 'pendente'
          }"
          @click="filtrarPorLegenda('pendente')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-yellow-400"
            >
              <i class="pi pi-clock !text-[11px] flex-shrink-0"></i>
              Pendentes
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.pendentes ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Em Atendimento -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-blue-500 scale-[1.02]':
              filtro.situacoes === 'em atendimento'
          }"
          @click="filtrarPorLegenda('em atendimento')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-blue-500"
            >
              <i class="pi pi-comments !text-[11px] flex-shrink-0"></i>
              Atendimento
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.em_atendimento ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Agendado -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-purple-500 scale-[1.02]':
              filtro.situacoes === 'agendado'
          }"
          @click="filtrarPorLegenda('agendado')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-purple-500"
            >
              <i class="pi pi-calendar !text-[11px] flex-shrink-0"></i>
              Agendado
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.agendado ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Pausado -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-gray-500 scale-[1.02]':
              filtro.situacoes === 'atendimento pausado'
          }"
          @click="filtrarPorLegenda('atendimento pausado')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-gray-500"
            >
              <i class="pi pi-pause !text-[11px] flex-shrink-0"></i>
              Pausado
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.atendimento_pausado ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Atrasadas -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-red-500 scale-[1.02]': filtro.situacoes === 'atrasadas'
          }"
          @click="filtrarPorLegenda('atrasadas')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-red-600"
            >
              <i
                class="pi pi-exclamation-triangle !text-[11px] flex-shrink-0"
              ></i>
              Atrasadas
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.atrasadas ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Resolvidas -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-teal-500 scale-[1.02]':
              filtro.situacoes === 'resolvida'
          }"
          @click="filtrarPorLegenda('resolvida')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-teal-500"
            >
              <i class="pi pi-check !text-[11px] flex-shrink-0"></i>
              Resolvidas
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.resolvida ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Recusada -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-orange-500 scale-[1.02]':
              filtro.situacoes === 'resolução recusada'
          }"
          @click="filtrarPorLegenda('resolução recusada')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-orange-500"
            >
              <i class="pi pi-times-circle !text-[11px] flex-shrink-0"></i>
              Recusada
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.resolucao_recusada ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Retorno -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-cyan-500 scale-[1.02]':
              filtro.situacoes === 'retorno solicitante'
          }"
          @click="filtrarPorLegenda('retorno solicitante')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-cyan-500"
            >
              <i class="pi pi-reply !text-[11px] flex-shrink-0"></i>
              Retorno
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.retorno_solicitante ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Finalizadas -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-emerald-500 scale-[1.02]':
              filtro.situacoes === 'finalizada'
          }"
          @click="filtrarPorLegenda('finalizada')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-emerald-500"
            >
              <i class="pi pi-check-circle !text-[11px] flex-shrink-0"></i>
              Finalizadas
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.finalizada ?? 0 }}</span>
          </div>
        </div>
      </div>

      <!-- Tabela de Tickets (Desktop/iPad) -->
      <div
        v-if="tickets && tickets.length > 0"
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 sm:p-6 mt-6 relative overflow-hidden hidden ipad:block"
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
                class="text-xl sm:text-xl md:text-2xl font-extrabold text-gray-800 dark:text-white drop-shadow truncate"
              >
                Resultados
                <span
                  class="text-base font-normal text-gray-500 dark:text-gray-400 ml-2"
                >
                  ({{ tickets?.contagem?.total ?? 0 }} registro{{
                    (tickets?.contagem?.total ?? 0) !== 1 ? "s" : ""
                  }})
                </span>
              </h2>
              <div
                class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 font-medium mt-1"
              >
                Clique em uma linha para abrir a ticket
              </div>
            </div>
          </div>

          <!-- Busca, Configurar e Exportar -->
          <div class="flex flex-wrap items-center gap-1 sm:gap-2">
            <Button
              @click="redirecionarNovaSolicitacao()"
              label="Solicitar"
              severity="info"
              outlined
              class="h-10"
              v-tooltip.top="'Novo Ticket'"
              raised
              icon="pi pi-plus-circle"
            />
            <Button
              icon="pi pi-cog"
              label="Configurar"
              severity="secondary"
              outlined
              v-tooltip.top="'Configurar Tabela'"
              @click="abrirDialogConfig"
            />
            <Button
              label="Exportar"
              icon="pi pi-file-excel"
              severity="success"
              outlined
              v-tooltip.top="'Exportar para Excel'"
              @click="exportarExcel"
              :loading="loadingExport"
              :disabled="tickets.length === 0"
            />
          </div>
        </div>

        <!-- DataTable -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm">
          <DataTable
            :value="solicitacoesFiltered"
            v-model:filters="filters"
            :globalFilterFields="[
              'id',
              'titulo',
              'assunto.assunto',
              'departamento',
              'status',
              'prioridade'
            ]"
            lazy
            paginator
            :rows="filtro.porPagina"
            :totalRecords="tickets?.contagem?.total ?? 0"
            :rowsPerPageOptions="[10, 25, 50, 100]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Mostrando {first} a {last} de {totalRecords}"
            @page="onPageChange"
            @sort="aoOrdenar"
            sortMode="multiple"
            :multiSortMeta="filtro.ordenacao"
            removableSort
            @column-reorder="onColumnReorder"
            reorderableColumns
            stripedRows
            showGridlines
            scrollable
            class="min-w-full text-sm"
            rowHover
            @rowClick="abrirSolicitacao($event.data)"
            :rowClass="
              () =>
                'cursor-pointer hover:bg-blue-50 dark:hover:bg-slate-700 transition-colors'
            "
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
                  Nenhuma ticket encontrada
                </p>
                <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">
                  Tente ajustar os filtros
                </p>
              </div>
            </template>

            <!-- Colunas dinâmicas baseadas na ordem de colunasVisiveis -->
            <Column
              v-for="coluna in colunasVisiveis"
              :key="coluna.field"
              :field="getColumnField(coluna.field)"
              :header="coluna.header"
              :style="getColumnStyle(coluna.field)"
              :class="getColumnClass(coluna.field)"
              :sortable="isColumnSortable(coluna.field)"
            >
              <template #body="{ data }">
                <!-- ID -->
                <div
                  v-if="coluna.field === 'id'"
                  class="flex items-center gap-1"
                >
                  <i class="pi pi-hashtag text-blue-500"></i>
                  <span
                    class="font-mono text-sm text-gray-800 dark:text-gray-200 bg-blue-50 dark:bg-blue-900/30 px-2 py-1 rounded-lg shadow-sm"
                  >
                    {{ data.id }}
                  </span>
                </div>

                <!-- Título -->
                <div
                  v-else-if="coluna.field === 'titulo'"
                  class="truncate font-medium text-gray-800 dark:text-gray-200"
                >
                  <i class="pi pi-file text-blue-400 mr-1"></i>
                  {{ data.titulo || "-" }}
                </div>

                <!-- Descrição -->
                <div
                  v-else-if="coluna.field === 'descricao'"
                  class="truncate text-gray-600 dark:text-gray-300"
                >
                  {{ data.descricao || "-" }}
                </div>

                <!-- Assunto -->
                <div
                  v-else-if="coluna.field === 'assunto'"
                  class="truncate text-gray-600 dark:text-gray-300"
                  :title="data.assunto?.assunto"
                >
                  <i class="pi pi-tags text-blue-400 mr-1"></i>
                  {{ data.assunto?.assunto || "-" }}
                </div>

                <!-- Departamento -->
                <div
                  v-else-if="coluna.field === 'departamento'"
                  class="truncate text-gray-600 dark:text-gray-300"
                  :title="data.departamento_responsavel"
                >
                  {{ data.departamento_responsavel || "-" }}
                </div>

                <!-- Prioridade -->
                <Tag
                  v-else-if="coluna.field === 'prioridade'"
                  :value="getPrioridadeLabel(data.prioridade)"
                  :severity="getPrioridadeSeverity(data.prioridade)"
                  class="font-medium"
                />

                <!-- Situação/Status -->
                <div
                  v-else-if="coluna.field === 'situacao'"
                  class="flex items-center gap-1.5"
                >
                  <Tag
                    :value="textoStatus(data.status)"
                    :severity="getStatusSeverity(data.status)"
                    class="font-medium"
                  />
                  <!-- Indicador de Atraso -->
                  <i
                    v-if="estaAtrasada(data)"
                    v-tooltip.top="
                      'Atrasada há ' + diasDeAtraso(data) + ' dia(s)'
                    "
                    class="pi pi-clock text-red-500 text-sm animate-pulse"
                  ></i>
                </div>

                <!-- Etapa de Andamento (Timeline Style) -->
                <div
                  v-else-if="coluna.field === 'etapa_atual'"
                  class="flex items-center"
                >
                  <div
                    v-if="data.etapa_atual?.etapa"
                    class="flex items-center gap-2"
                    v-tooltip.top="
                      'Etapa atual: ' + data.etapa_atual.etapa.nome
                    "
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

                <!-- Solicitante -->
                <div
                  v-else-if="coluna.field === 'solicitante'"
                  class="flex items-center gap-2"
                  v-tooltip.top="data.usuario_solicitante?.nome"
                >
                  <div class="relative flex-shrink-0">
                    <img
                      v-if="data.solicitante_foto"
                      :src="data.solicitante_foto"
                      :alt="data.usuario_solicitante?.nome"
                      class="w-7 h-7 rounded-full object-cover ring-2 ring-purple-100"
                    />
                    <span
                      v-else
                      class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 text-purple-600 text-xs font-bold"
                    >
                      {{ obterIniciais(data.usuario_solicitante?.nome) }}
                    </span>
                  </div>
                  <span
                    class="max-w-[120px] truncate text-gray-700 dark:text-gray-300 font-medium"
                  >
                    {{ obterNomeSobrenome(data.usuario_solicitante?.nome) }}
                  </span>
                </div>

                <!-- Responsável -->
                <div
                  v-else-if="coluna.field === 'responsavel'"
                  class="flex items-center gap-2"
                  v-tooltip.top="
                    data.usuario_responsavel?.nome || 'Não atribuído'
                  "
                >
                  <div class="relative flex-shrink-0">
                    <img
                      v-if="data.responsavel_foto"
                      :src="data.responsavel_foto"
                      :alt="data.usuario_responsavel?.nome"
                      class="w-7 h-7 rounded-full object-cover ring-2 ring-indigo-100"
                    />
                    <span
                      v-else
                      class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold"
                    >
                      {{
                        data.usuario_responsavel
                          ? obterIniciais(data.usuario_responsavel.nome)
                          : "NA"
                      }}
                    </span>
                  </div>
                  <span
                    class="max-w-[120px] truncate text-gray-700 dark:text-gray-300 font-medium"
                  >
                    {{ obterNomeSobrenome(data.usuario_responsavel?.nome) }}
                  </span>
                </div>

                <!-- Filial -->
                <div
                  v-else-if="coluna.field === 'filial'"
                  class="truncate text-gray-600 dark:text-gray-300"
                >
                  {{ data.filial?.codigo }} - {{ data.filial?.fantasia || "-" }}
                </div>

                <!-- Dias Aberto -->
                <span
                  v-else-if="coluna.field === 'dias_aberto'"
                  class="inline-flex items-center gap-1 bg-blue-50 dark:bg-blue-900/30 text-gray-700 dark:text-gray-300 text-xs font-medium px-2 py-1 rounded-full"
                >
                  <i class="pi pi-clock text-blue-400 text-xs"></i>
                  {{ parseInt(data.dias_aberto) || 0 }} dias
                </span>

                <!-- Previsão de Entrega -->
                <div
                  v-else-if="coluna.field === 'previsao_entrega'"
                  class="flex items-center gap-2"
                >
                  <i class="pi pi-calendar-plus text-orange-400 text-xs"></i>
                  <span class="text-gray-600 dark:text-gray-300">
                    {{
                      data.previsao_entrega
                        ? formatarDataSemHoras(data.previsao_entrega)
                        : "-"
                    }}
                  </span>
                </div>

                <!-- Criado em -->
                <div
                  v-else-if="coluna.field === 'created_at'"
                  class="flex items-center gap-2"
                >
                  <i class="pi pi-calendar text-blue-400 text-xs"></i>
                  <span class="text-gray-600 dark:text-gray-300 truncate">
                    {{ formatarData(data.created_at) }}
                  </span>
                </div>

                <!-- Última Atualização -->
                <div
                  v-else-if="coluna.field === 'updated_at'"
                  class="flex items-center gap-2"
                >
                  <i class="pi pi-clock text-green-400 text-xs"></i>
                  <span class="text-gray-600 dark:text-gray-300 truncate">
                    {{ formatarData(data.updated_at) }}
                  </span>
                </div>


              </template>
            </Column>
          </DataTable>
        </div>
      </div>

      <!-- Cards de Tickets Mobile -->
      <div
        v-if="tickets && tickets.length > 0"
        class="block mt-5 space-y-4 ipad:hidden px-2"
      >
        <!-- Placeholder mobile quando não encontrar -->
        <div
          v-if="solicitacoesFiltered.length === 0"
          class="flex flex-col items-center justify-center py-12 text-gray-500 bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-gray-200 dark:border-slate-700"
        >
          <div
            class="w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-4"
          >
            <i
              class="pi pi-search text-3xl text-gray-400 dark:text-gray-500"
            ></i>
          </div>
          <template v-if="buscaPorIdAtiva">
            <p class="text-lg font-bold text-gray-700 dark:text-gray-200">
              Ticket #{{ filtro.id }} não encontrada
            </p>
            <p
              class="text-sm mt-2 text-gray-500 dark:text-gray-400 text-center px-4"
            >
              A ticket não existe ou você não tem permissão para
              visualizá-la.
            </p>
          </template>
          <template v-else>
            <p class="text-lg font-bold text-gray-700 dark:text-gray-200">
              Nenhuma ticket encontrada
            </p>
            <p class="text-sm mt-2 text-gray-500 dark:text-gray-400">
              Tente ajustar os filtros.
            </p>
          </template>
        </div>

        <!-- Cards Mobile -->
        <div
          v-for="(item, index) in solicitacoesFiltered"
          :key="index"
          class="mb-4"
        >
          <div
            class="bg-white dark:bg-slate-800 rounded-2xl shadow-md overflow-hidden border-l-4 transition-all duration-200"
            :class="{
              'border-yellow-400 dark:border-yellow-500':
                item.status === 'pendente',
              'border-blue-400 dark:border-blue-500':
                item.status === 'em atendimento',
              'border-gray-400 dark:border-gray-500':
                item.status === 'atendimento pausado',
              'border-cyan-400 dark:border-cyan-500':
                item.status === 'agendado',
              'border-green-400 dark:border-green-500':
                item.status === 'resolvida' || item.status === 'finalizada',
              'border-red-400 dark:border-red-500':
                item.status === 'resolução recusada' ||
                item.status === 'cancelada',
              'border-orange-400 dark:border-orange-500':
                item.status === 'retorno solicitante'
            }"
          >
            <!-- Header do Card - SEMPRE VISÍVEL -->
            <div
              class="bg-gradient-to-r from-slate-200 to-slate-300 dark:from-slate-600 dark:to-slate-700 p-3 border-b-2 border-gray-300 dark:border-slate-500"
            >
              <div class="flex items-center justify-between">
                <!-- ID e Badge Prioridade -->
                <div class="flex items-center gap-2">
                  <span
                    @click="abrirSolicitacao(item)"
                    class="inline-flex items-center justify-center px-3 py-1.5 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 font-bold text-sm border border-indigo-200 dark:border-indigo-700 cursor-pointer hover:bg-indigo-200 dark:hover:bg-indigo-800/40 active:scale-95 transition-all"
                  >
                    #{{ item.id }}
                  </span>
                  <span
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold border"
                    :class="{
                      'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 border-red-300 dark:border-red-700':
                        item.prioridade === 'urgente',
                      'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300 border-yellow-300 dark:border-yellow-700':
                        item.prioridade === 'alta',
                      'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-700':
                        item.prioridade === 'media',
                      'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600':
                        item.prioridade === 'baixa'
                    }"
                  >
                    <i class="pi pi-flag !text-[10px]"></i>
                    {{ item.prioridade?.toUpperCase() }}
                  </span>
                </div>

                <!-- Botão expandir/colapsar -->
                <div
                  @click.stop="toggleCardExpandido(item.id)"
                  class="flex items-center justify-center w-8 h-8 bg-white dark:bg-slate-600 rounded-lg border border-gray-200 dark:border-slate-500 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-500 transition-colors"
                >
                  <i
                    class="pi text-gray-500 dark:text-gray-400 transition-transform duration-300"
                    :class="
                      cardsExpandidos.has(item.id)
                        ? 'pi-chevron-up'
                        : 'pi-chevron-down'
                    "
                  ></i>
                </div>
              </div>
            </div>

            <!-- Solicitante - SEMPRE VISÍVEL -->
            <div
              class="bg-white dark:bg-slate-800 px-4 py-2.5 cursor-pointer border-b border-gray-200 dark:border-slate-600"
              @click="abrirSolicitacao(item)"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <!-- Avatar Solicitante -->
                  <div class="relative flex-shrink-0">
                    <img
                      v-if="item.solicitante_foto"
                      :src="item.solicitante_foto"
                      :alt="item.usuario_solicitante?.nome"
                      class="w-8 h-8 rounded-full object-cover ring-2 ring-emerald-400 dark:ring-emerald-500 shadow-sm"
                    />
                    <div
                      v-else
                      class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center border-2 border-emerald-300 dark:border-emerald-500 shadow-sm"
                    >
                      <span class="text-white text-[10px] font-bold">
                        {{ obterIniciais(item.usuario_solicitante?.nome) }}
                      </span>
                    </div>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p
                      class="text-[9px] uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-semibold"
                    >
                      Solicitante
                    </p>
                    <p
                      class="text-sm font-bold text-gray-800 dark:text-white truncate"
                    >
                      {{ obterNomeSobrenome(item.usuario_solicitante?.nome) }}
                    </p>
                  </div>
                </div>
                <!-- Dias aberto -->
                <div
                  class="flex items-center gap-1.5 px-2 py-1 bg-gray-100 dark:bg-slate-700 rounded-lg"
                >
                  <i
                    class="pi pi-clock !text-xs text-gray-500 dark:text-gray-400"
                  ></i>
                  <span
                    class="text-xs font-bold text-gray-600 dark:text-gray-300"
                  >
                    {{ parseInt(item.dias_aberto) || 0 }} dias
                  </span>
                </div>
              </div>
            </div>

            <!-- Conteúdo Colapsável -->
            <Transition name="collapse">
              <div
                v-show="cardsExpandidos.has(item.id)"
                class="overflow-hidden"
              >
                <!-- Filial -->
                <div
                  class="px-4 py-2.5 border-b border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800"
                >
                  <div class="flex items-center gap-2">
                    <div
                      class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"
                    >
                      <i
                        class="pi pi-building text-blue-600 dark:text-blue-400 !text-xs"
                      ></i>
                    </div>
                    <div class="min-w-0 flex-1">
                      <p
                        class="text-[9px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold"
                      >
                        Filial
                      </p>
                      <p
                        class="text-sm font-bold text-gray-800 dark:text-white truncate"
                      >
                        {{ item.filial?.codigo }} - {{ item.filial?.fantasia }}
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Departamento e Assunto -->
                <div
                  class="px-4 py-2.5 border-b border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800"
                >
                  <div class="flex items-start gap-3">
                    <div
                      class="w-8 h-8 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center flex-shrink-0 border border-orange-200 dark:border-orange-700"
                    >
                      <i
                        class="pi pi-folder text-orange-600 dark:text-orange-400 !text-xs"
                      ></i>
                    </div>
                    <div class="min-w-0 flex-1">
                      <p
                        class="text-[9px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold"
                      >
                        {{ item.departamento_responsavel }}
                      </p>
                      <p
                        class="text-sm font-bold text-gray-800 dark:text-white truncate mt-0.5"
                      >
                        {{
                          item.assunto != null
                            ? item.assunto.assunto
                            : "Transferido"
                        }}
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Responsável -->
                <div
                  class="px-4 py-2.5 border-b border-gray-200 dark:border-slate-600 bg-blue-50/50 dark:bg-blue-900/10"
                >
                  <div class="flex items-center gap-2">
                    <!-- Avatar com foto ou iniciais - Responsável -->
                    <div class="relative flex-shrink-0">
                      <img
                        v-if="item.responsavel_foto"
                        :src="item.responsavel_foto"
                        :alt="item.usuario_responsavel?.nome"
                        class="w-8 h-8 rounded-full object-cover ring-2 ring-blue-400 dark:ring-blue-500 shadow-md"
                      />
                      <div
                        v-else
                        class="w-8 h-8 rounded-full flex items-center justify-center shadow-md border-2"
                        :class="
                          item.usuario_responsavel
                            ? 'bg-gradient-to-br from-blue-400 to-blue-600 border-blue-300 dark:border-blue-500'
                            : 'bg-gray-200 dark:bg-slate-600 border-gray-300 dark:border-slate-500'
                        "
                      >
                        <span class="text-white text-[10px] font-bold">
                          {{
                            item.usuario_responsavel
                              ? obterIniciais(item.usuario_responsavel?.nome)
                              : "?"
                          }}
                        </span>
                      </div>
                    </div>
                    <div class="min-w-0 flex-1">
                      <p
                        class="text-[9px] uppercase tracking-wider text-blue-600 dark:text-blue-400 font-bold"
                      >
                        Responsável
                      </p>
                      <p
                        class="text-sm font-bold truncate"
                        :class="
                          item.usuario_responsavel
                            ? 'text-gray-800 dark:text-white'
                            : 'text-gray-400 dark:text-gray-500'
                        "
                      >
                        {{
                          item.usuario_responsavel
                            ? obterNomeSobrenome(item.usuario_responsavel?.nome)
                            : "Não atribuído"
                        }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </Transition>

            <!-- Footer - Status - SEMPRE VISÍVEL -->
            <div
              @click="abrirSolicitacao(item)"
              class="px-4 py-3 flex items-center justify-between border-t-2 cursor-pointer hover:brightness-95 active:scale-[0.99] transition-all"
              :class="{
                'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-300 dark:border-yellow-700':
                  item.status === 'pendente',
                'bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-700':
                  item.status === 'em atendimento',
                'bg-gray-100 dark:bg-gray-700/30 border-gray-300 dark:border-gray-600':
                  item.status === 'atendimento pausado',
                'bg-cyan-50 dark:bg-cyan-900/20 border-cyan-300 dark:border-cyan-700':
                  item.status === 'agendado',
                'bg-green-50 dark:bg-green-900/20 border-green-300 dark:border-green-700':
                  item.status === 'resolvida' || item.status === 'finalizada',
                'bg-red-50 dark:bg-red-900/20 border-red-300 dark:border-red-700':
                  item.status === 'resolução recusada' ||
                  item.status === 'cancelada',
                'bg-orange-50 dark:bg-orange-900/20 border-orange-300 dark:border-orange-700':
                  item.status === 'retorno solicitante'
              }"
            >
              <div class="flex items-center gap-2">
                <div
                  class="w-8 h-8 rounded-xl flex items-center justify-center border-2"
                  :class="{
                    'bg-yellow-100 dark:bg-yellow-800/40 border-yellow-400 dark:border-yellow-600':
                      item.status === 'pendente',
                    'bg-blue-100 dark:bg-blue-800/40 border-blue-400 dark:border-blue-600':
                      item.status === 'em atendimento',
                    'bg-gray-200 dark:bg-gray-600/40 border-gray-400 dark:border-gray-500':
                      item.status === 'atendimento pausado',
                    'bg-cyan-100 dark:bg-cyan-800/40 border-cyan-400 dark:border-cyan-600':
                      item.status === 'agendado',
                    'bg-green-100 dark:bg-green-800/40 border-green-400 dark:border-green-600':
                      item.status === 'resolvida' ||
                      item.status === 'finalizada',
                    'bg-red-100 dark:bg-red-800/40 border-red-400 dark:border-red-600':
                      item.status === 'resolução recusada' ||
                      item.status === 'cancelada',
                    'bg-orange-100 dark:bg-orange-800/40 border-orange-400 dark:border-orange-600':
                      item.status === 'retorno solicitante'
                  }"
                >
                  <i
                    :class="getStatusVisual(item.status).icon"
                    :style="{ color: getStatusVisual(item.status).color }"
                    class="text-sm"
                  ></i>
                </div>
                <span
                  class="text-sm font-bold uppercase tracking-wide"
                  :class="{
                    'text-yellow-700 dark:text-yellow-300':
                      item.status === 'pendente',
                    'text-blue-700 dark:text-blue-300':
                      item.status === 'em atendimento',
                    'text-gray-600 dark:text-gray-300':
                      item.status === 'atendimento pausado',
                    'text-cyan-700 dark:text-cyan-300':
                      item.status === 'agendado',
                    'text-green-700 dark:text-green-300':
                      item.status === 'resolvida' ||
                      item.status === 'finalizada',
                    'text-red-700 dark:text-red-300':
                      item.status === 'resolução recusada' ||
                      item.status === 'cancelada',
                    'text-orange-700 dark:text-orange-300':
                      item.status === 'retorno solicitante'
                  }"
                >
                  {{ textoStatus(item.status) }}
                </span>
                <!-- Indicador de Atraso (Mobile) -->
                <i
                  v-if="estaAtrasada(item)"
                  v-tooltip.top="
                    'Atrasada há ' + diasDeAtraso(item) + ' dia(s)'
                  "
                  class="pi pi-clock text-red-500 text-sm animate-pulse ml-1"
                ></i>
              </div>

              <!-- Data de criação -->
              <span
                class="text-xs text-gray-500 dark:text-gray-400 font-semibold bg-white dark:bg-slate-700 px-2 py-1 rounded-lg border border-gray-200 dark:border-slate-600"
              >
                {{ formatarDataSemHoras(item.created_at) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Paginação Mobile -->
        <div
          class="mt-6 bg-white dark:bg-slate-800 rounded-2xl shadow-lg border-2 border-gray-200 dark:border-slate-600 p-4"
        >
          <div class="flex items-center justify-between gap-3">
            <!-- Botão Anterior -->
            <button
              @click="filtro.pagina > 1 && (filtro.pagina--, getSolicitacoes())"
              :disabled="filtro.pagina <= 1"
              class="flex items-center justify-center w-10 h-10 rounded-xl border-2 transition-all duration-200"
              :class="
                filtro.pagina <= 1
                  ? 'bg-gray-100 dark:bg-slate-700 border-gray-200 dark:border-slate-600 text-gray-400 dark:text-gray-500 cursor-not-allowed'
                  : 'bg-white dark:bg-slate-700 border-blue-500 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 active:scale-95'
              "
            >
              <i class="pi pi-chevron-left font-bold"></i>
            </button>

            <!-- Info de Página -->
            <div class="flex items-center gap-2 flex-1 justify-center">
              <span
                class="text-sm text-gray-600 dark:text-gray-400 font-medium"
              >
                Página
              </span>
              <span
                class="inline-flex items-center justify-center min-w-[40px] h-9 px-3 bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 font-bold text-sm rounded-xl border border-indigo-200 dark:border-indigo-700"
              >
                {{ filtro.pagina }}
              </span>
              <span
                class="text-sm text-gray-600 dark:text-gray-400 font-medium"
              >
                de
              </span>
              <span class="text-sm text-gray-800 dark:text-gray-200 font-bold">
                {{ tickets?.paginacao?.paginas || 1 }}
              </span>
            </div>

            <!-- Botão Próximo -->
            <button
              @click="
                filtro.pagina < (tickets?.paginacao?.paginas || 1) &&
                (filtro.pagina++, getSolicitacoes())
              "
              :disabled="
                filtro.pagina >= (tickets?.paginacao?.paginas || 1)
              "
              class="flex items-center justify-center w-10 h-10 rounded-xl border-2 transition-all duration-200"
              :class="
                filtro.pagina >= (tickets?.paginacao?.paginas || 1)
                  ? 'bg-gray-100 dark:bg-slate-700 border-gray-200 dark:border-slate-600 text-gray-400 dark:text-gray-500 cursor-not-allowed'
                  : 'bg-white dark:bg-slate-700 border-blue-500 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 active:scale-95'
              "
            >
              <i class="pi pi-chevron-right font-bold"></i>
            </button>
          </div>

          <!-- Info total de registros -->
          <div
            class="flex items-center justify-center gap-2 mt-3 pt-3 border-t border-gray-200 dark:border-slate-600"
          >
            <span class="text-xs text-gray-500 dark:text-gray-400">
              Mostrando 1 a {{ tickets?.paginacao?.porPagina || 10 }} de
              {{ tickets?.contagem?.total || 0 }}
            </span>
          </div>
        </div>
      </div>

      <!-- Mensagem quando não há tickets -->
      <div
        v-else-if="!loadingInicial"
        class="flex flex-col items-center justify-center py-16 text-gray-500 dark:text-gray-400"
      >
        <i class="pi pi-inbox text-6xl mb-4"></i>
        <p class="text-lg font-medium">Nenhuma ticket encontrada</p>
        <p class="text-sm">Crie uma nova ticket para começar</p>
        <Button
          @click="redirecionarNovaSolicitacao()"
          label="Novo Ticket"
          severity="contrast"
          class="mt-4"
          icon="fas fa-plus"
        />
      </div>
    </div>

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
            <div
              v-for="coluna in colunasTemp"
              :key="coluna.field"
              class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer group"
              @click="coluna.visible = !coluna.visible"
            >
              <Checkbox
                v-model="coluna.visible"
                :binary="true"
                :inputId="coluna.field"
                class="pointer-events-none"
              />
              <label
                :for="coluna.field"
                class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors flex-1"
              >
                {{ coluna.header }}
              </label>
              <i
                class="pi text-xs transition-all"
                :class="
                  coluna.visible
                    ? 'pi-eye text-blue-500'
                    : 'pi-eye-slash text-gray-400'
                "
              ></i>
            </div>
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
            @click="resetarColunas"
          />
          <Button
            label="Salvar"
            icon="pi pi-check"
            severity="success"
            class="!rounded-xl flex-1 !bg-gradient-to-r !from-blue-500 !to-indigo-600 !border-0"
            @click="salvarConfigColunas"
          />
        </div>
      </div>
    </Dialog>

    <!-- Dialog Ticket -->
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
  </AuthenticatedLayout>
</template>

<style scoped>
.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Transição Collapse para cards mobile */
.collapse-enter-active,
.collapse-leave-active {
  transition: all 0.3s ease;
  max-height: 500px;
  opacity: 1;
}

.collapse-enter-from,
.collapse-leave-to {
  max-height: 0;
  opacity: 0;
}
</style>
