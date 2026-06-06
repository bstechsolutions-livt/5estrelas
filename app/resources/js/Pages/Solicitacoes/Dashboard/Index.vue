<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import Loader from "@/Components/Loader.vue"
import * as layout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { Head, usePage } from "@inertiajs/vue3"
import { onMounted, onUnmounted, ref, computed, watch } from "vue"
import { useDebounceFn } from "@vueuse/core"
import { useUserPreferences } from "@/composables/useUserPreferences"
import { formatarData } from "@/utils/globalFunctions"
import Chart from "primevue/chart"
import Select from "primevue/select"
import MultiSelect from "primevue/multiselect"
import DatePicker from "primevue/datepicker"
import Button from "primevue/button"
import Panel from "primevue/panel"
import Dialog from "primevue/dialog"
import Solicitacao from "@/Pages/Solicitacoes/Solicitação.vue"

// Marcar como página nova
layout.paginaNova.value = true

const page = usePage()
const userPreferences = useUserPreferences()

// Estados para modal de solicitação
const dialogSolicitacao = ref(false)
const solicitacaoSelecionada = ref(null)

// Chaves de preferências
const PREF_KEYS = {
  departamento: "solicitacoes.dashboard.departamento",
  assuntos: "solicitacoes.dashboard.assuntos",
  responsavel: "solicitacoes.dashboard.responsavel"
}

const props = defineProps(["departamentos", "permiteVerTodos", "permissoes"])

// Estados
const loading = ref(false)
const loadingInicial = ref(true)
const dados = ref(null)
const erroMensagem = ref(null)

// Detecção de modo escuro
const isDark = ref(document.documentElement.classList.contains("dark"))
const observerDark = new MutationObserver(() => {
  isDark.value = document.documentElement.classList.contains("dark")
})
observerDark.observe(document.documentElement, {
  attributes: true,
  attributeFilter: ["class"]
})

// Filtros
const filtro = ref({
  departamento: null,
  assuntos: [],
  responsavel: null,
  dataInicio: null,
  dataFim: null
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

// Dados para o gráfico de evolução
const chartEvolucaoData = computed(() => {
  if (!dados.value?.evolucao) return null
  return {
    labels: dados.value.evolucao.map((d) => d.data),
    datasets: [
      {
        label: "Criadas",
        data: dados.value.evolucao.map((d) => d.criadas),
        fill: false,
        borderColor: "#3B82F6",
        backgroundColor: "#3B82F6",
        tension: 0.4
      },
      {
        label: "Finalizadas",
        data: dados.value.evolucao.map((d) => d.finalizadas),
        fill: false,
        borderColor: "#10B981",
        backgroundColor: "#10B981",
        tension: 0.4
      }
    ]
  }
})

// Título dinâmico do gráfico de evolução
const tituloEvolucao = computed(() => {
  if (!dados.value?.tipoAgrupamento) return "Evolução"
  const tipos = {
    dia: "Evolução Diária",
    semana: "Evolução Semanal",
    mes: "Evolução Mensal"
  }
  return tipos[dados.value.tipoAgrupamento] || "Evolução"
})

const chartEvolucaoOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: "top",
      labels: {
        color: isDark.value ? "#f1f5f9" : "#374151"
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        stepSize: 1,
        color: isDark.value ? "#94a3b8" : "#6b7280"
      },
      grid: {
        color: isDark.value ? "#334155" : "#e5e7eb"
      }
    },
    x: {
      ticks: {
        color: isDark.value ? "#94a3b8" : "#6b7280"
      },
      grid: {
        color: isDark.value ? "#334155" : "#e5e7eb"
      }
    }
  }
}))

// Dados para o gráfico de status (donut)
const chartStatusData = computed(() => {
  if (!dados.value?.porStatus) return null

  const statusLabels = {
    pendente: "Pendente",
    "em atendimento": "Em Atendimento",
    "atendimento pausado": "Pausado",
    agendado: "Agendado",
    "retorno solicitante": "Retorno Solicitante",
    finalizada: "Finalizada",
    cancelada: "Cancelada"
  }

  const statusColors = {
    pendente: "#F59E0B",
    "em atendimento": "#3B82F6",
    "atendimento pausado": "#8B5CF6",
    agendado: "#06B6D4",
    "retorno solicitante": "#EC4899",
    finalizada: "#10B981",
    cancelada: "#EF4444"
  }

  const labels = Object.keys(dados.value.porStatus).map(
    (s) => statusLabels[s] || s
  )
  const dataValues = Object.values(dados.value.porStatus)
  const colors = Object.keys(dados.value.porStatus).map(
    (s) => statusColors[s] || "#6B7280"
  )

  return {
    labels,
    datasets: [
      {
        data: dataValues,
        backgroundColor: colors,
        hoverBackgroundColor: colors
      }
    ]
  }
})

const chartStatusOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: "right",
      labels: {
        color: isDark.value ? "#f1f5f9" : "#374151"
      }
    }
  }
}))

// Dados para o gráfico de prioridade (bar)
const chartPrioridadeData = computed(() => {
  if (!dados.value?.porPrioridade) return null

  const prioridadeLabels = {
    urgente: "Urgente",
    alta: "Alta",
    media: "Média",
    baixa: "Baixa"
  }

  const prioridadeColors = {
    urgente: "#EF4444",
    alta: "#F59E0B",
    media: "#3B82F6",
    baixa: "#10B981"
  }

  // Ordenar por prioridade
  const ordem = ["urgente", "alta", "media", "baixa"]
  const sortedKeys = ordem.filter((k) => dados.value.porPrioridade[k])

  return {
    labels: sortedKeys.map((k) => prioridadeLabels[k] || k),
    datasets: [
      {
        label: "Quantidade",
        data: sortedKeys.map((k) => dados.value.porPrioridade[k]),
        backgroundColor: sortedKeys.map((k) => prioridadeColors[k] || "#6B7280")
      }
    ]
  }
})

const chartPrioridadeOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        stepSize: 1,
        color: isDark.value ? "#94a3b8" : "#6b7280"
      },
      grid: {
        color: isDark.value ? "#334155" : "#e5e7eb"
      }
    },
    x: {
      ticks: {
        color: isDark.value ? "#94a3b8" : "#6b7280"
      },
      grid: {
        color: isDark.value ? "#334155" : "#e5e7eb"
      }
    }
  }
}))

// Dados para o gráfico de Entregues no Prazo vs Atrasadas (donut)
const chartPrazoData = computed(() => {
  if (!dados.value) return null
  const noPrazo = dados.value.finalizadasNoPrazo || 0
  const comAtraso = dados.value.finalizadasComAtraso || 0

  if (noPrazo === 0 && comAtraso === 0) return null

  return {
    labels: ["No Prazo", "Com Atraso"],
    datasets: [
      {
        data: [noPrazo, comAtraso],
        backgroundColor: ["#10B981", "#EF4444"],
        hoverBackgroundColor: ["#059669", "#DC2626"]
      }
    ]
  }
})

const chartPrazoOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: "bottom",
      labels: {
        color: isDark.value ? "#f1f5f9" : "#374151"
      }
    }
  }
}))

// Dados para o gráfico de Top Assuntos (barras horizontais)
const chartTopAssuntosData = computed(() => {
  if (!dados.value?.topAssuntos?.length) return null

  return {
    labels: dados.value.topAssuntos.map((item) =>
      item.assunto.length > 25
        ? item.assunto.substring(0, 25) + "..."
        : item.assunto
    ),
    datasets: [
      {
        label: "Quantidade",
        data: dados.value.topAssuntos.map((item) => item.total),
        backgroundColor: [
          "#3B82F6",
          "#8B5CF6",
          "#EC4899",
          "#F59E0B",
          "#10B981"
        ],
        borderRadius: 4
      }
    ]
  }
})

const chartTopAssuntosOptions = computed(() => ({
  indexAxis: "y",
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    x: {
      beginAtZero: true,
      ticks: {
        stepSize: 1,
        color: isDark.value ? "#94a3b8" : "#6b7280"
      },
      grid: {
        color: isDark.value ? "#334155" : "#e5e7eb"
      }
    },
    y: {
      ticks: {
        color: isDark.value ? "#94a3b8" : "#6b7280"
      },
      grid: {
        color: isDark.value ? "#334155" : "#e5e7eb"
      }
    }
  }
}))

// Dados para o gráfico de Atrasadas por Faixa (donut)
const chartAtrasadasFaixaData = computed(() => {
  if (!dados.value?.atrasadasPorFaixa) return null

  const { ate3dias, ate7dias, mais7dias } = dados.value.atrasadasPorFaixa
  if (ate3dias === 0 && ate7dias === 0 && mais7dias === 0) return null

  return {
    labels: ["Até 3 dias", "4-7 dias", "Mais de 7 dias"],
    datasets: [
      {
        data: [ate3dias, ate7dias, mais7dias],
        backgroundColor: ["#F59E0B", "#F97316", "#EF4444"],
        hoverBackgroundColor: ["#D97706", "#EA580C", "#DC2626"]
      }
    ]
  }
})

// Definir período inicial (semana atual: segunda a domingo)
function getInicioSemana() {
  const hoje = new Date()
  const dia = hoje.getDay()
  const diff = dia === 0 ? -6 : 1 - dia // Se domingo, volta 6 dias; senão, vai até segunda
  const segunda = new Date(hoje)
  segunda.setDate(hoje.getDate() + diff)
  segunda.setHours(0, 0, 0, 0)
  return segunda
}

function getFimSemana() {
  const inicio = getInicioSemana()
  const domingo = new Date(inicio)
  domingo.setDate(inicio.getDate() + 6)
  domingo.setHours(23, 59, 59, 999)
  return domingo
}

// Carregar preferências do usuário
async function carregarPreferencias() {
  try {
    const preferencias = await userPreferences.getMany([
      PREF_KEYS.departamento,
      PREF_KEYS.assuntos,
      PREF_KEYS.responsavel
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

    // Carregar assuntos e responsável após definir departamento
    if (preferencias[PREF_KEYS.assuntos]) {
      filtro.value.assuntos = preferencias[PREF_KEYS.assuntos]
    }
    if (preferencias[PREF_KEYS.responsavel]) {
      filtro.value.responsavel = preferencias[PREF_KEYS.responsavel]
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
  buscarDados()
}

// Buscar dados do dashboard
async function buscarDados() {
  loading.value = true
  erroMensagem.value = null

  try {
    const params = {
      departamento: filtro.value.departamento,
      assuntos: filtro.value.assuntos,
      responsavel: filtro.value.responsavel,
      dataInicio: filtro.value.dataInicio
        ? formatarDataParaAPI(filtro.value.dataInicio)
        : null,
      dataFim: filtro.value.dataFim
        ? formatarDataParaAPI(filtro.value.dataFim)
        : null
    }

    const response = await axios.post("/solicitacoes/dashboard/dados", params)
    dados.value = response.data
  } catch (err) {
    console.error(err)
    erroMensagem.value = "Erro ao carregar dados do dashboard"
  } finally {
    loading.value = false
  }
}

function formatarDataParaAPI(data) {
  if (!data) return null
  const d = new Date(data)
  return d.toISOString().split("T")[0]
}

// Função de busca com debounce para evitar múltiplas chamadas
const buscarDadosDebounced = useDebounceFn(() => {
  if (!loadingInicial.value) {
    buscarDados()
  }
}, 500)

// Watch para mudanças nos filtros (exceto departamento que tem handler próprio)
watch(
  () => [
    filtro.value.assuntos,
    filtro.value.responsavel,
    filtro.value.dataInicio,
    filtro.value.dataFim
  ],
  () => {
    buscarDadosDebounced()
  },
  { deep: true }
)

// Inicialização
onMounted(async () => {
  // Definir período padrão (semana atual)
  filtro.value.dataInicio = getInicioSemana()
  filtro.value.dataFim = getFimSemana()

  // Carregar preferências
  await carregarPreferencias()

  // Buscar dados iniciais
  await buscarDados()

  loadingInicial.value = false
})

// Cleanup
onUnmounted(() => {
  observerDark.disconnect()
})

// Funções auxiliares
function getStatusClass(status) {
  const classes = {
    pendente:
      "bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-400 border-yellow-300 dark:border-yellow-700",
    "em atendimento":
      "bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400 border-blue-300 dark:border-blue-700",
    "atendimento pausado":
      "bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-400 border-purple-300 dark:border-purple-700",
    agendado:
      "bg-cyan-100 dark:bg-cyan-900/30 text-cyan-800 dark:text-cyan-400 border-cyan-300 dark:border-cyan-700",
    "retorno solicitante":
      "bg-pink-100 dark:bg-pink-900/30 text-pink-800 dark:text-pink-400 border-pink-300 dark:border-pink-700",
    finalizada:
      "bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400 border-green-300 dark:border-green-700",
    cancelada:
      "bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-400 border-red-300 dark:border-red-700"
  }
  return (
    classes[status] ||
    "bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300 border-gray-300 dark:border-gray-600"
  )
}

// Função para exibir título da solicitação (prioriza título, depois assunto)
function getTituloSolicitacao(sol) {
  if (sol.titulo) return sol.titulo
  if (sol.assunto) return sol.assunto
  return "Sem título"
}

// Função para montar tooltip completo
function getTooltipSolicitacao(sol) {
  let tooltip = ""
  if (sol.titulo) tooltip += `Título: ${sol.titulo}\n`
  if (sol.assunto) tooltip += `Assunto: ${sol.assunto}\n`
  if (sol.descricao) tooltip += `\nDescrição: ${sol.descricao}`
  if (!tooltip) tooltip = "Sem informações"
  return tooltip.trim()
}

// Função para formatar tempo de espera (segundos -> formato legível)
function formatarTempoEspera(segundos) {
  if (segundos < 60) {
    return { valor: Math.floor(segundos), unidade: "s" }
  } else if (segundos < 3600) {
    return { valor: Math.floor(segundos / 60), unidade: "min" }
  } else if (segundos < 86400) {
    return { valor: Math.floor(segundos / 3600), unidade: "h" }
  } else {
    return { valor: Math.floor(segundos / 86400), unidade: "d" }
  }
}

// Função para obter classe de cor baseado no tempo em segundos
function getCorTempoEspera(segundos) {
  const dias = segundos / 86400
  if (dias > 7)
    return "bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400"
  if (dias > 3)
    return "bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400"
  if (dias > 1)
    return "bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400"
  return "bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-400"
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

// Função para gerar cor do avatar baseado no nome
function getAvatarColor(nome) {
  if (!nome) return "bg-gray-400"
  const colors = [
    "bg-gradient-to-br from-blue-400 to-blue-600",
    "bg-gradient-to-br from-emerald-400 to-emerald-600",
    "bg-gradient-to-br from-violet-400 to-violet-600",
    "bg-gradient-to-br from-rose-400 to-rose-600",
    "bg-gradient-to-br from-amber-400 to-amber-600",
    "bg-gradient-to-br from-cyan-400 to-cyan-600",
    "bg-gradient-to-br from-pink-400 to-pink-600",
    "bg-gradient-to-br from-indigo-400 to-indigo-600",
    "bg-gradient-to-br from-teal-400 to-teal-600",
    "bg-gradient-to-br from-fuchsia-400 to-fuchsia-600"
  ]
  let hash = 0
  for (let i = 0; i < nome.length; i++) {
    hash = nome.charCodeAt(i) + ((hash << 5) - hash)
  }
  return colors[Math.abs(hash) % colors.length]
}

// Função para obter URL da foto do perfil
function getFotoPerfil(matricula) {
  if (!matricula) return null
  return `/api/funcionarios/${matricula}/foto`
}

// Função para tratar erro de imagem
function onImageError(event) {
  event.target.style.display = "none"
  if (event.target.nextElementSibling) {
    event.target.nextElementSibling.style.display = "flex"
  }
}

// Abrir solicitação em modal
function abrirSolicitacao(sol) {
  solicitacaoSelecionada.value = sol
  dialogSolicitacao.value = true
}
</script>

<template>
  <Head title="Dashboard Solicitações" />
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
            Dashboard
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
          Dashboard de Solicitações
        </h2>
      </div>
      <span
        class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-bold pl-2 pr-2 sm:pr-0 break-words whitespace-normal"
      >
        Visualize métricas, gráficos e indicadores de desempenho das
        solicitações do seu departamento.
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
            <h3 class="text-2xl font-extrabold text-gray-800 dark:text-white">
              Filtros
            </h3>
            <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
              Utilize os filtros abaixo para refinar os dados do dashboard.
            </div>
          </div>
        </div>
      </template>

      <div class="flex flex-col gap-4 w-full">
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 items-end gap-4 w-full"
        >
          <!-- Departamento -->
          <div class="flex flex-col gap-1">
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
            />
          </div>

          <!-- Assuntos -->
          <div class="flex flex-col gap-1">
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
              :disabled="!filtro.departamento"
              filter
            />
          </div>

          <!-- Atendente -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Atendente
            </label>
            <Select
              v-model="filtro.responsavel"
              :options="responsaveisOptions"
              :optionLabel="
                (item) =>
                  Array.isArray(item)
                    ? item.nome || item.NOME
                    : item?.nome || item?.NOME
              "
              :optionValue="
                (item) =>
                  Array.isArray(item)
                    ? item.matricula || item.MATRICULA
                    : item?.matricula || item?.MATRICULA
              "
              placeholder="Todos"
              class="w-full h-10"
              showClear
              filter
              :disabled="!filtro.departamento"
            />
          </div>

          <!-- Data Início -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Data Início
            </label>
            <DatePicker
              v-model="filtro.dataInicio"
              dateFormat="dd/mm/yy"
              placeholder="dd/mm/aaaa"
              class="w-full"
              showIcon
              showButtonBar
              fluid
            />
          </div>

          <!-- Data Fim -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Data Fim
            </label>
            <DatePicker
              v-model="filtro.dataFim"
              dateFormat="dd/mm/yy"
              placeholder="dd/mm/aaaa"
              class="w-full"
              showIcon
              showButtonBar
              fluid
            />
          </div>
        </div>
      </div>
    </Panel>

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

    <!-- Conteúdo do Dashboard -->
    <div
      v-if="dados"
      class="space-y-6"
    >
      <!-- Cards de Resumo -->
      <div
        class="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-5 px-1 sm:px-0"
      >
        <!-- Criadas no Período -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 group"
        >
          <div class="flex items-center justify-between mb-2">
            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-blue-500"
            >
              <i class="pi pi-inbox !text-[11px] flex-shrink-0"></i>
              Criadas
              <span class="text-[9px] opacity-80">(período)</span>
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
          >
            <span>{{ dados.totalPeriodo }}</span>
          </div>
        </div>

        <!-- Abertas (Total Atual) -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 group"
        >
          <div class="flex items-center justify-between mb-2">
            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-yellow-400"
            >
              <i class="pi pi-folder-open !text-[11px] flex-shrink-0"></i>
              Em Aberto
              <span class="text-[9px] opacity-80">(total)</span>
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
          >
            <span>{{ dados.abertas }}</span>
          </div>
        </div>

        <!-- Atrasadas (Total Atual) -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 group"
        >
          <div class="flex items-center justify-between mb-2">
            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-red-600"
            >
              <i class="pi pi-clock !text-[11px] flex-shrink-0"></i>
              Atrasadas
              <span class="text-[9px] opacity-80">(total)</span>
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
          >
            <span>{{ dados.atrasadas }}</span>
          </div>
        </div>

        <!-- Finalizadas no Período -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 group flex flex-col"
        >
          <div class="flex items-center justify-between mb-2">
            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-emerald-500"
            >
              <i class="pi pi-check-circle !text-[11px] flex-shrink-0"></i>
              Finalizadas
              <span class="text-[9px] opacity-80">(período)</span>
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
          >
            <span>{{ dados.finalizadasPeriodo || 0 }}</span>
          </div>
        </div>

        <!-- Tempo Médio -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 group flex flex-col"
        >
          <div class="flex items-center justify-between mb-2">
            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-purple-500"
            >
              <i class="pi pi-stopwatch !text-[11px] flex-shrink-0"></i>
              Tempo Médio
              <span class="text-[9px] opacity-80">(período)</span>
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold items-baseline gap-1"
          >
            <span>{{ dados.tempoMedioResolucao }}</span>
            <span class="text-sm font-normal text-gray-400 dark:text-gray-500">
              dias
            </span>
          </div>
        </div>
      </div>

      <!-- Gráficos -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Gráfico de Evolução -->
        <div
          class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
        >
          <h3
            class="mb-4 font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
          >
            <span
              class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30"
            >
              <i class="pi pi-chart-line text-blue-600 dark:text-blue-400"></i>
            </span>
            {{ tituloEvolucao }}
            <span class="text-xs font-normal text-gray-400 dark:text-gray-500">
              (período)
            </span>
          </h3>
          <div class="h-64">
            <Chart
              v-if="chartEvolucaoData"
              type="line"
              :data="chartEvolucaoData"
              :options="chartEvolucaoOptions"
              class="h-full"
            />
            <div
              v-else
              class="flex h-full items-center justify-center text-gray-400 dark:text-gray-500"
            >
              <div class="text-center">
                <i class="pi pi-chart-line mb-2 text-3xl opacity-50"></i>
                <p>Sem dados para exibir</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Gráfico de Status -->
        <div
          class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
        >
          <h3
            class="mb-4 font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
          >
            <span
              class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30"
            >
              <i
                class="pi pi-chart-pie text-purple-600 dark:text-purple-400"
              ></i>
            </span>
            Distribuição por Status
            <span class="text-xs font-normal text-gray-400 dark:text-gray-500">
              (total)
            </span>
          </h3>
          <div class="h-64">
            <Chart
              v-if="chartStatusData"
              type="doughnut"
              :data="chartStatusData"
              :options="chartStatusOptions"
              class="h-full"
            />
            <div
              v-else
              class="flex h-full items-center justify-center text-gray-400 dark:text-gray-500"
            >
              <div class="text-center">
                <i class="pi pi-chart-pie mb-2 text-3xl opacity-50"></i>
                <p>Sem dados para exibir</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Segunda linha de gráficos/tabelas -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Gráfico de Prioridade -->
        <div
          class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
        >
          <h3
            class="mb-4 font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
          >
            <span
              class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-orange-100 dark:bg-orange-900/30"
            >
              <i
                class="pi pi-chart-bar text-orange-600 dark:text-orange-400"
              ></i>
            </span>
            Distribuição por Prioridade
            <span class="text-xs font-normal text-gray-400 dark:text-gray-500">
              (período)
            </span>
          </h3>
          <div class="h-64">
            <Chart
              v-if="chartPrioridadeData"
              type="bar"
              :data="chartPrioridadeData"
              :options="chartPrioridadeOptions"
              class="h-full"
            />
            <div
              v-else
              class="flex h-full items-center justify-center text-gray-400 dark:text-gray-500"
            >
              <div class="text-center">
                <i class="pi pi-chart-bar mb-2 text-3xl opacity-50"></i>
                <p>Sem dados para exibir</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Top Assuntos - Gráfico de Barras Horizontais -->
        <div
          class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
        >
          <h3
            class="mb-4 font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
          >
            <span
              class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-yellow-100 dark:bg-yellow-900/30"
            >
              <i class="pi pi-star text-yellow-600 dark:text-yellow-400"></i>
            </span>
            Top 5 Assuntos
            <span class="text-xs font-normal text-gray-400 dark:text-gray-500">
              (período)
            </span>
          </h3>
          <div class="h-64">
            <Chart
              v-if="chartTopAssuntosData"
              type="bar"
              :data="chartTopAssuntosData"
              :options="chartTopAssuntosOptions"
              class="h-full"
            />
            <div
              v-else
              class="flex h-full items-center justify-center text-gray-400 dark:text-gray-500"
            >
              <div class="text-center">
                <i class="pi pi-star mb-2 text-3xl opacity-50"></i>
                <p>Sem dados para exibir</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Terceira linha de gráficos -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Gráfico Entregues no Prazo vs Atrasadas -->
        <div
          class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
        >
          <h3
            class="mb-4 font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
          >
            <span
              class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-100 dark:bg-green-900/30"
            >
              <i class="pi pi-clock text-green-600 dark:text-green-400"></i>
            </span>
            Entregas no Prazo vs Atrasadas
            <span class="text-xs font-normal text-gray-400 dark:text-gray-500">
              (finalizadas no período)
            </span>
          </h3>
          <div class="h-64">
            <Chart
              v-if="chartPrazoData"
              type="doughnut"
              :data="chartPrazoData"
              :options="chartPrazoOptions"
              class="h-full"
            />
            <div
              v-else
              class="flex h-full items-center justify-center text-gray-400 dark:text-gray-500"
            >
              <div class="text-center">
                <i class="pi pi-check-circle mb-2 text-3xl text-green-400"></i>
                <p>Nenhuma finalizada no período</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Gráfico de Atrasadas por Faixa -->
        <div
          class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
        >
          <h3
            class="mb-4 font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
          >
            <span
              class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30"
            >
              <i
                class="pi pi-exclamation-triangle text-red-600 dark:text-red-400"
              ></i>
            </span>
            Atrasadas por Tempo de Atraso
            <span class="text-xs font-normal text-gray-400 dark:text-gray-500">
              (total)
            </span>
          </h3>
          <div class="h-64">
            <Chart
              v-if="chartAtrasadasFaixaData"
              type="doughnut"
              :data="chartAtrasadasFaixaData"
              :options="chartPrazoOptions"
              class="h-full"
            />
            <div
              v-else
              class="flex h-full items-center justify-center text-gray-400 dark:text-gray-500"
            >
              <div class="text-center">
                <i class="pi pi-check-circle mb-2 text-3xl text-green-400"></i>
                <p>Nenhuma solicitação atrasada!</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Ranking de Atendentes -->
      <div
        class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
      >
        <h3
          class="mb-4 font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
        >
          <span
            class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/30"
          >
            <i class="pi pi-trophy text-indigo-600 dark:text-indigo-400"></i>
          </span>
          Ranking de Atendentes
          <span class="text-xs font-normal text-gray-400 dark:text-gray-500">
            (finalizações no período)
          </span>
        </h3>
        <div
          v-if="dados.rankingAtendentes?.length"
          class="space-y-3"
        >
          <!-- Card de cada atendente -->
          <div
            v-for="(atendente, index) in dados.rankingAtendentes"
            :key="atendente.matricula"
            :class="[
              'flex items-center gap-4 p-3 rounded-xl transition-all duration-300 hover:scale-[1.01]',
              index === 0
                ? 'bg-gradient-to-r from-yellow-50 to-amber-50 dark:from-yellow-900/20 dark:to-amber-900/20 border border-yellow-200 dark:border-yellow-800/50 shadow-sm'
                : index === 1
                  ? 'bg-gradient-to-r from-gray-50 to-slate-100 dark:from-gray-800/30 dark:to-slate-800/30 border border-gray-200 dark:border-gray-700/50'
                  : index === 2
                    ? 'bg-gradient-to-r from-orange-50 to-amber-50 dark:from-orange-900/20 dark:to-amber-900/20 border border-orange-200 dark:border-orange-800/50'
                    : 'bg-gray-50 dark:bg-slate-700/30 border border-gray-100 dark:border-slate-700'
            ]"
          >
            <!-- Posição -->
            <div class="flex-shrink-0">
              <span
                :class="[
                  'flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold shadow-md',
                  index === 0
                    ? 'bg-gradient-to-br from-yellow-300 to-yellow-500 text-yellow-900'
                    : index === 1
                      ? 'bg-gradient-to-br from-gray-300 to-gray-500 text-gray-800'
                      : index === 2
                        ? 'bg-gradient-to-br from-orange-300 to-orange-500 text-orange-900'
                        : 'bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400'
                ]"
              >
                <i
                  v-if="index < 3"
                  class="pi pi-star-fill text-xs"
                ></i>
                <span v-else>{{ index + 1 }}</span>
              </span>
            </div>

            <!-- Avatar + Nome -->
            <div class="flex items-center gap-3 flex-1 min-w-0">
              <!-- Avatar com foto ou iniciais -->
              <div class="relative flex-shrink-0">
                <!-- Foto de perfil -->
                <img
                  v-if="atendente.foto"
                  :src="atendente.foto"
                  :alt="atendente.nome"
                  class="h-11 w-11 rounded-full object-cover ring-2 ring-white dark:ring-slate-700 shadow-md"
                />
                <!-- Avatar com iniciais (fallback) -->
                <div
                  v-else
                  :class="[
                    'h-11 w-11 rounded-full flex items-center justify-center text-white font-bold text-sm ring-2 ring-white dark:ring-slate-700 shadow-md',
                    getAvatarColor(atendente.nome)
                  ]"
                >
                  {{ obterIniciais(atendente.nome) }}
                </div>
                <!-- Badge de posição para top 3 -->
                <span
                  v-if="index < 3"
                  :class="[
                    'absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold shadow-sm border-2 border-white dark:border-slate-700',
                    index === 0
                      ? 'bg-yellow-400 text-yellow-900'
                      : index === 1
                        ? 'bg-gray-400 text-gray-900'
                        : 'bg-orange-400 text-orange-900'
                  ]"
                >
                  {{ index + 1 }}º
                </span>
              </div>

              <!-- Nome com tooltip -->
              <div class="min-w-0">
                <p
                  v-tooltip.right="atendente.nome"
                  class="font-semibold text-gray-800 dark:text-gray-100 truncate cursor-default"
                >
                  {{ obterNomeSobrenome(atendente.nome) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ atendente.matricula || "Atendente" }}
                </p>
              </div>
            </div>

            <!-- Total de Finalizações -->
            <div class="flex-shrink-0 text-right">
              <div
                :class="[
                  'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full font-bold',
                  index === 0
                    ? 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300'
                    : index === 1
                      ? 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200'
                      : index === 2
                        ? 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300'
                        : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                ]"
              >
                <i class="pi pi-check-circle text-xs"></i>
                <span>{{ atendente.total }}</span>
              </div>
            </div>
          </div>
        </div>
        <div
          v-else
          class="flex h-32 items-center justify-center text-gray-400 dark:text-gray-500"
        >
          <div class="text-center">
            <i class="pi pi-trophy mb-2 text-3xl opacity-50"></i>
            <p>Sem finalizações no período selecionado</p>
          </div>
        </div>
      </div>

      <!-- ========== NOVAS MÉTRICAS ========== -->

      <!-- Cards de Métricas Extras -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Resolvidas Aguardando Feedback -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 flex flex-col"
        >
          <div class="flex items-center justify-between mb-2">
            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-orange-500"
            >
              <i class="pi pi-hourglass !text-[11px] flex-shrink-0"></i>
              Aguardando Feedback
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
          >
            <span>{{ dados.resolvidasAguardando || 0 }}</span>
          </div>
          <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
            Resolvidas, aguardando solicitante finalizar
          </p>
        </div>

        <!-- Taxa de Resolução -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 flex flex-col"
        >
          <div class="flex items-center justify-between mb-2">
            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-emerald-500"
            >
              <i class="pi pi-percentage !text-[11px] flex-shrink-0"></i>
              Taxa de Resolução
              <span class="text-[9px] opacity-80">(período)</span>
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold items-baseline gap-1"
          >
            <span>{{ dados.taxaResolucao }}</span>
            <span class="text-lg">%</span>
          </div>
          <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
            {{ dados.finalizadasPeriodo }} de {{ dados.totalPeriodo }}
            finalizadas
          </p>
        </div>

        <!-- Taxa No Prazo -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 flex flex-col"
        >
          <div class="flex items-center justify-between mb-2">
            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-cyan-500"
            >
              <i class="pi pi-calendar-clock !text-[11px] flex-shrink-0"></i>
              Entregues no Prazo
              <span class="text-[9px] opacity-80">(período)</span>
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold items-baseline gap-1"
          >
            <span>{{ dados.taxaNoPrazo }}</span>
            <span class="text-lg">%</span>
          </div>
          <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
            {{ dados.finalizadasNoPrazo }} no prazo,
            {{ dados.finalizadasComAtraso }} com atraso
          </p>
        </div>

        <!-- Taxa de Cancelamento -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 flex flex-col"
        >
          <div class="flex items-center justify-between mb-2">
            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-gray-500"
            >
              <i class="pi pi-times-circle !text-[11px] flex-shrink-0"></i>
              Canceladas
              <span class="text-[9px] opacity-80">(período)</span>
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold items-baseline gap-1"
          >
            <span>{{ dados.taxaCancelamento }}</span>
            <span class="text-lg">%</span>
          </div>
          <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
            Taxa de cancelamento no período
          </p>
        </div>
      </div>

      <!-- Atrasadas por Faixa -->
      <div
        v-if="dados.atrasadas > 0"
        class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
      >
        <h3
          class="mb-6 font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
        >
          <span
            class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30"
          >
            <i
              class="pi pi-exclamation-triangle text-red-600 dark:text-red-400"
            ></i>
          </span>
          Detalhamento de Atrasos
          <span class="text-xs font-normal text-gray-400 dark:text-gray-500">
            (total atual)
          </span>
        </h3>

        <!-- Cards de Atraso com Design Moderno -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
          <!-- Até 3 dias - Atenção -->
          <div
            class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-yellow-400 via-amber-400 to-orange-400 p-1 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02]"
          >
            <div
              class="relative h-full rounded-xl bg-white dark:bg-slate-900 p-5"
            >
              <!-- Ícone decorativo de fundo -->
              <div class="absolute -right-4 -top-4 opacity-10 dark:opacity-5">
                <i class="pi pi-clock text-[80px] text-yellow-500"></i>
              </div>

              <!-- Conteúdo -->
              <div class="relative z-10">
                <div class="flex items-center gap-2 mb-3">
                  <span
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-yellow-400 to-amber-500 shadow-md"
                  >
                    <i class="pi pi-clock text-white"></i>
                  </span>
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300"
                  >
                    Atenção
                  </span>
                </div>

                <p
                  class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1"
                >
                  Até 3 dias de atraso
                </p>

                <div class="flex items-baseline gap-2">
                  <span
                    class="text-4xl font-extrabold bg-gradient-to-r from-yellow-500 to-amber-500 bg-clip-text text-transparent"
                  >
                    {{ dados.atrasadasPorFaixa?.ate3dias || 0 }}
                  </span>
                  <span class="text-sm text-gray-400 dark:text-gray-500">
                    solicitações
                  </span>
                </div>

                <!-- Barra de progresso visual -->
                <div
                  class="mt-4 h-1.5 w-full rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden"
                >
                  <div
                    class="h-full rounded-full bg-gradient-to-r from-yellow-400 to-amber-500 transition-all duration-500"
                    :style="{
                      width:
                        dados.atrasadas > 0
                          ? Math.min(
                              ((dados.atrasadasPorFaixa?.ate3dias || 0) /
                                dados.atrasadas) *
                                100,
                              100
                            ) + '%'
                          : '0%'
                    }"
                  ></div>
                </div>
              </div>
            </div>
          </div>

          <!-- 4 a 7 dias - Alerta -->
          <div
            class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-orange-400 via-orange-500 to-red-400 p-1 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02]"
          >
            <div
              class="relative h-full rounded-xl bg-white dark:bg-slate-900 p-5"
            >
              <!-- Ícone decorativo de fundo -->
              <div class="absolute -right-4 -top-4 opacity-10 dark:opacity-5">
                <i
                  class="pi pi-exclamation-circle text-[80px] text-orange-500"
                ></i>
              </div>

              <!-- Conteúdo -->
              <div class="relative z-10">
                <div class="flex items-center gap-2 mb-3">
                  <span
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-orange-400 to-orange-600 shadow-md"
                  >
                    <i class="pi pi-exclamation-circle text-white"></i>
                  </span>
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300"
                  >
                    Alerta
                  </span>
                </div>

                <p
                  class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1"
                >
                  4 a 7 dias de atraso
                </p>

                <div class="flex items-baseline gap-2">
                  <span
                    class="text-4xl font-extrabold bg-gradient-to-r from-orange-500 to-red-500 bg-clip-text text-transparent"
                  >
                    {{ dados.atrasadasPorFaixa?.ate7dias || 0 }}
                  </span>
                  <span class="text-sm text-gray-400 dark:text-gray-500">
                    solicitações
                  </span>
                </div>

                <!-- Barra de progresso visual -->
                <div
                  class="mt-4 h-1.5 w-full rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden"
                >
                  <div
                    class="h-full rounded-full bg-gradient-to-r from-orange-400 to-orange-600 transition-all duration-500"
                    :style="{
                      width:
                        dados.atrasadas > 0
                          ? Math.min(
                              ((dados.atrasadasPorFaixa?.ate7dias || 0) /
                                dados.atrasadas) *
                                100,
                              100
                            ) + '%'
                          : '0%'
                    }"
                  ></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Mais de 7 dias - Crítico -->
          <div
            class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-red-400 via-red-500 to-rose-600 p-1 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02]"
          >
            <div
              class="relative h-full rounded-xl bg-white dark:bg-slate-900 p-5"
            >
              <!-- Ícone decorativo de fundo -->
              <div class="absolute -right-4 -top-4 opacity-10 dark:opacity-5">
                <i
                  class="pi pi-exclamation-triangle text-[80px] text-red-500"
                ></i>
              </div>

              <!-- Pulse animation para crítico -->
              <div
                v-if="(dados.atrasadasPorFaixa?.mais7dias || 0) > 0"
                class="absolute top-3 right-3 flex h-3 w-3"
              >
                <span
                  class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"
                ></span>
                <span
                  class="relative inline-flex rounded-full h-3 w-3 bg-red-500"
                ></span>
              </div>

              <!-- Conteúdo -->
              <div class="relative z-10">
                <div class="flex items-center gap-2 mb-3">
                  <span
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-red-500 to-rose-600 shadow-md"
                  >
                    <i class="pi pi-exclamation-triangle text-white"></i>
                  </span>
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 animate-pulse"
                  >
                    Crítico
                  </span>
                </div>

                <p
                  class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1"
                >
                  Mais de 7 dias de atraso
                </p>

                <div class="flex items-baseline gap-2">
                  <span
                    class="text-4xl font-extrabold bg-gradient-to-r from-red-500 to-rose-600 bg-clip-text text-transparent"
                  >
                    {{ dados.atrasadasPorFaixa?.mais7dias || 0 }}
                  </span>
                  <span class="text-sm text-gray-400 dark:text-gray-500">
                    solicitações
                  </span>
                </div>

                <!-- Barra de progresso visual -->
                <div
                  class="mt-4 h-1.5 w-full rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden"
                >
                  <div
                    class="h-full rounded-full bg-gradient-to-r from-red-500 to-rose-600 transition-all duration-500"
                    :style="{
                      width:
                        dados.atrasadas > 0
                          ? Math.min(
                              ((dados.atrasadasPorFaixa?.mais7dias || 0) /
                                dados.atrasadas) *
                                100,
                              100
                            ) + '%'
                          : '0%'
                    }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Resumo Total -->
        <div
          class="mt-5 flex items-center justify-center gap-1 p-3 rounded-xl bg-gray-50 dark:bg-slate-700/50"
        >
          <div class="flex items-center gap-2">
            <i class="pi pi-info-circle text-gray-400 dark:text-gray-500"></i>
            <span class="text-sm text-gray-500 dark:text-gray-400">
              Total de solicitações atrasadas:
            </span>
          </div>
          <span class="text-lg font-bold text-red-600 dark:text-red-400">
            {{
              (dados.atrasadasPorFaixa?.ate3dias || 0) +
              (dados.atrasadasPorFaixa?.ate7dias || 0) +
              (dados.atrasadasPorFaixa?.mais7dias || 0)
            }}
          </span>
        </div>
      </div>

      <!-- Listas de Atenção -->
      <div class="space-y-6">
        <!-- Pendentes sem movimentação -->
        <div
          class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
        >
          <div class="flex items-center justify-between mb-5">
            <h3
              class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
            >
              <span
                class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-yellow-400 to-amber-500 shadow-md"
              >
                <i class="pi pi-exclamation-circle text-white"></i>
              </span>
              <div>
                <span>Pendentes sem Movimentação</span>
                <p class="text-xs font-normal text-gray-400 dark:text-gray-500">
                  +3 dias sem iniciar atendimento
                </p>
              </div>
            </h3>
            <span
              v-if="dados.pendentesSemMovimentacao?.length"
              class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-sm font-bold"
            >
              <i class="pi pi-list text-xs"></i>
              {{ dados.pendentesSemMovimentacao.length }}
            </span>
          </div>

          <div v-if="dados.pendentesSemMovimentacao?.length">
            <!-- Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
              <div
                v-for="sol in dados.pendentesSemMovimentacao"
                :key="sol.id"
                class="group relative overflow-hidden rounded-xl border border-yellow-200 dark:border-yellow-800/50 bg-gradient-to-br from-yellow-50 to-amber-50 dark:from-yellow-900/10 dark:to-amber-900/10 p-4 hover:shadow-md hover:border-yellow-300 dark:hover:border-yellow-700 transition-all duration-300"
              >
                <!-- Badge de dias no canto -->
                <div class="absolute top-3 right-3">
                  <span
                    :class="[
                      'inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold shadow-sm',
                      Math.floor(sol.dias) > 30
                        ? 'bg-red-500 text-white'
                        : Math.floor(sol.dias) > 14
                          ? 'bg-orange-500 text-white'
                          : 'bg-yellow-500 text-white'
                    ]"
                  >
                    <i class="pi pi-clock text-[10px]"></i>
                    {{ Math.floor(sol.dias) }}d
                  </span>
                </div>

                <!-- Conteúdo -->
                <div class="pr-16">
                  <span
                    @click="abrirSolicitacao(sol)"
                    class="inline-flex items-center gap-1.5 text-blue-600 dark:text-blue-400 font-bold hover:text-blue-700 dark:hover:text-blue-300 transition-colors cursor-pointer"
                  >
                    <span class="text-lg">#{{ sol.id }}</span>
                    <i
                      class="pi pi-eye text-xs opacity-0 group-hover:opacity-100 transition-opacity"
                    ></i>
                  </span>

                  <p
                    class="mt-1 text-sm text-gray-700 dark:text-gray-300 line-clamp-2"
                    :title="getTituloSolicitacao(sol)"
                  >
                    {{ getTituloSolicitacao(sol) }}
                  </p>
                </div>

                <!-- Barra de urgência -->
                <div
                  class="mt-3 h-1 w-full rounded-full bg-yellow-200 dark:bg-yellow-900/30 overflow-hidden"
                >
                  <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="[
                      Math.floor(sol.dias) > 30
                        ? 'bg-red-500'
                        : Math.floor(sol.dias) > 14
                          ? 'bg-orange-500'
                          : 'bg-yellow-500'
                    ]"
                    :style="{
                      width: Math.min((sol.dias / 60) * 100, 100) + '%'
                    }"
                  ></div>
                </div>
              </div>
            </div>
          </div>

          <div
            v-else
            class="flex h-32 items-center justify-center"
          >
            <div class="text-center">
              <div
                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 mb-3"
              >
                <i
                  class="pi pi-check-circle text-3xl text-green-500 dark:text-green-400"
                ></i>
              </div>
              <p class="text-gray-500 dark:text-gray-400 font-medium">
                Nenhuma pendente parada!
              </p>
              <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                Todas as solicitações foram iniciadas
              </p>
            </div>
          </div>
        </div>

        <!-- Em atendimento há muito tempo -->
        <div
          class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
        >
          <div class="flex items-center justify-between mb-5">
            <h3
              class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
            >
              <span
                class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-orange-400 to-red-500 shadow-md"
              >
                <i class="pi pi-clock text-white"></i>
              </span>
              <div>
                <span>Em Atendimento há Muito Tempo</span>
                <p class="text-xs font-normal text-gray-400 dark:text-gray-500">
                  +7 dias sem movimentação
                </p>
              </div>
            </h3>
            <span
              v-if="dados.emAtendimentoMuitoTempo?.length"
              class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 text-sm font-bold"
            >
              <i class="pi pi-list text-xs"></i>
              {{ dados.emAtendimentoMuitoTempo.length }}
            </span>
          </div>

          <div v-if="dados.emAtendimentoMuitoTempo?.length">
            <!-- Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
              <div
                v-for="sol in dados.emAtendimentoMuitoTempo"
                :key="sol.id"
                class="group relative overflow-hidden rounded-xl border border-orange-200 dark:border-orange-800/50 bg-gradient-to-br from-orange-50 to-red-50 dark:from-orange-900/10 dark:to-red-900/10 p-4 hover:shadow-md hover:border-orange-300 dark:hover:border-orange-700 transition-all duration-300"
              >
                <!-- Badge de dias e status -->
                <div
                  class="absolute top-3 right-3 flex flex-col items-end gap-1"
                >
                  <span
                    :class="[
                      'inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold shadow-sm',
                      Math.floor(sol.dias) > 30
                        ? 'bg-red-500 text-white'
                        : Math.floor(sol.dias) > 14
                          ? 'bg-orange-500 text-white'
                          : 'bg-amber-500 text-white'
                    ]"
                  >
                    <i class="pi pi-clock text-[10px]"></i>
                    {{ Math.floor(sol.dias) }}d
                  </span>
                </div>

                <!-- Conteúdo -->
                <div class="pr-16">
                  <div class="flex items-center gap-2 mb-1">
                    <span
                      @click="abrirSolicitacao(sol)"
                      class="inline-flex items-center gap-1.5 text-blue-600 dark:text-blue-400 font-bold hover:text-blue-700 dark:hover:text-blue-300 transition-colors cursor-pointer"
                    >
                      <span class="text-lg">#{{ sol.id }}</span>
                      <i
                        class="pi pi-eye text-xs opacity-0 group-hover:opacity-100 transition-opacity"
                      ></i>
                    </span>
                    <span
                      :class="[
                        'inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold',
                        sol.status === 'em atendimento'
                          ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400'
                          : 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-400'
                      ]"
                    >
                      {{
                        sol.status === "em atendimento"
                          ? "Em Atendimento"
                          : "Pausado"
                      }}
                    </span>
                  </div>

                  <p
                    class="text-sm text-gray-700 dark:text-gray-300 line-clamp-2"
                    :title="getTituloSolicitacao(sol)"
                  >
                    {{ getTituloSolicitacao(sol) }}
                  </p>
                </div>

                <!-- Barra de urgência -->
                <div
                  class="mt-3 h-1 w-full rounded-full bg-orange-200 dark:bg-orange-900/30 overflow-hidden"
                >
                  <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="[
                      Math.floor(sol.dias) > 30
                        ? 'bg-red-500'
                        : Math.floor(sol.dias) > 14
                          ? 'bg-orange-500'
                          : 'bg-amber-500'
                    ]"
                    :style="{
                      width: Math.min((sol.dias / 60) * 100, 100) + '%'
                    }"
                  ></div>
                </div>
              </div>
            </div>
          </div>

          <div
            v-else
            class="flex h-32 items-center justify-center"
          >
            <div class="text-center">
              <div
                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 mb-3"
              >
                <i
                  class="pi pi-check-circle text-3xl text-green-500 dark:text-green-400"
                ></i>
              </div>
              <p class="text-gray-500 dark:text-gray-400 font-medium">
                Nenhum atendimento parado!
              </p>
              <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                Todos os atendimentos estão em dia
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Solicitações mais antigas abertas -->
      <div
        class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
      >
        <div class="flex items-center justify-between mb-5">
          <h3
            class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
          >
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-slate-400 to-slate-600 shadow-md"
            >
              <i class="pi pi-history text-white"></i>
            </span>
            <div>
              <span>Solicitações Mais Antigas em Aberto</span>
              <p class="text-xs font-normal text-gray-400 dark:text-gray-500">
                Backlog mais antigo - requer atenção
              </p>
            </div>
          </h3>
          <span
            v-if="dados.maisAntigasAbertas?.length"
            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-bold"
          >
            <i class="pi pi-list text-xs"></i>
            {{ dados.maisAntigasAbertas.length }}
          </span>
        </div>

        <div v-if="dados.maisAntigasAbertas?.length">
          <!-- Cards Grid -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div
              v-for="sol in dados.maisAntigasAbertas"
              :key="sol.id"
              @click="abrirSolicitacao(sol)"
              class="group relative overflow-hidden rounded-xl border border-gray-200 dark:border-slate-700 bg-gradient-to-br from-white to-gray-50 dark:from-slate-800 dark:to-slate-800/50 p-4 hover:shadow-lg hover:border-blue-300 dark:hover:border-blue-700 transition-all duration-300 hover:scale-[1.01] cursor-pointer"
            >
              <!-- Header com ID e Dias -->
              <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2">
                  <span
                    class="text-xl font-bold text-blue-600 dark:text-blue-400 group-hover:text-blue-700 dark:group-hover:text-blue-300 transition-colors"
                  >
                    #{{ sol.id }}
                  </span>
                  <i
                    class="pi pi-eye text-xs text-blue-400 opacity-0 group-hover:opacity-100 transition-opacity"
                  ></i>
                </div>
                <span
                  :class="[
                    'inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold  shadow-sm',
                    sol.dias > 60
                      ? 'bg-gradient-to-r from-red-500 to-rose-600 text-white'
                      : sol.dias > 30
                        ? 'bg-gradient-to-r from-orange-500 to-red-500 text-white'
                        : 'bg-gradient-to-r from-yellow-400 to-amber-500 text-white'
                  ]"
                >
                  <i class="pi pi-clock text-[10px]"></i>
                  {{ Math.floor(sol.dias) }}d
                </span>
              </div>

              <!-- Título -->
              <p
                class="text-sm font-medium text-gray-800 dark:text-gray-200 line-clamp-2 mb-3"
              >
                {{ getTituloSolicitacao(sol) }}
              </p>

              <!-- Info Grid -->
              <div class="grid grid-cols-3 gap-2 text-xs">
                <!-- Solicitante -->
                <div class="flex flex-col">
                  <span class="text-gray-400 dark:text-gray-500 mb-0.5">
                    Solicitante
                  </span>
                  <span
                    v-tooltip.top="sol.solicitante"
                    class="text-gray-600 dark:text-gray-400 font-medium truncate"
                  >
                    {{
                      sol.solicitante?.split(" ").slice(0, 2).join(" ") || "N/A"
                    }}
                  </span>
                </div>

                <!-- Responsável -->
                <div class="flex flex-col">
                  <span class="text-gray-400 dark:text-gray-500 mb-0.5">
                    Responsável
                  </span>
                  <span
                    v-tooltip.top="sol.responsavel || 'Não atribuído'"
                    :class="[
                      'font-medium truncate',
                      sol.responsavel
                        ? 'text-gray-600 dark:text-gray-400'
                        : 'text-orange-500 dark:text-orange-400 italic'
                    ]"
                  >
                    {{
                      sol.responsavel?.split(" ").slice(0, 2).join(" ") ||
                      "Não atribuído"
                    }}
                  </span>
                </div>

                <!-- Status -->
                <div class="flex flex-col items-end">
                  <span class="text-gray-400 dark:text-gray-500 mb-0.5">
                    Status
                  </span>
                  <span
                    :class="[
                      'inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold whitespace-nowrap',
                      getStatusClass(sol.status)
                    ]"
                  >
                    {{ sol.status }}
                  </span>
                </div>
              </div>

              <!-- Barra de urgência -->
              <div
                class="mt-3 h-1 w-full rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden"
              >
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="[
                    sol.dias > 60
                      ? 'bg-gradient-to-r from-red-500 to-rose-600'
                      : sol.dias > 30
                        ? 'bg-gradient-to-r from-orange-500 to-red-500'
                        : 'bg-gradient-to-r from-yellow-400 to-amber-500'
                  ]"
                  :style="{
                    width: Math.min((sol.dias / 100) * 100, 100) + '%'
                  }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <div
          v-else
          class="flex h-32 items-center justify-center"
        >
          <div class="text-center">
            <div
              class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 mb-3"
            >
              <i
                class="pi pi-check-circle text-3xl text-green-500 dark:text-green-400"
              ></i>
            </div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
              Nenhuma solicitação em aberto!
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
              Todas as solicitações foram finalizadas
            </p>
          </div>
        </div>
      </div>

      <!-- Solicitações Atrasadas -->
      <div
        class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
      >
        <div class="flex items-center justify-between mb-5">
          <h3
            class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
          >
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-red-500 to-rose-600 shadow-md"
            >
              <i class="pi pi-exclamation-triangle text-white"></i>
            </span>
            <div>
              <span>Solicitações Atrasadas</span>
              <p class="text-xs font-normal text-gray-400 dark:text-gray-500">
                Prazo de entrega vencido
              </p>
            </div>
          </h3>
          <span
            v-if="dados.listaAtrasadas?.length"
            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-sm font-bold"
          >
            <i class="pi pi-exclamation-circle text-xs"></i>
            {{ dados.listaAtrasadas.length }}
          </span>
        </div>

        <div v-if="dados.listaAtrasadas?.length">
          <!-- Cards Grid -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div
              v-for="sol in dados.listaAtrasadas"
              :key="sol.id"
              @click="abrirSolicitacao(sol)"
              class="group relative overflow-hidden rounded-xl border-2 border-red-200 dark:border-red-800/50 bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/10 dark:to-rose-900/10 p-4 hover:shadow-lg hover:border-red-300 dark:hover:border-red-700 transition-all duration-300 hover:scale-[1.01] cursor-pointer"
            >
              <!-- Indicador de urgência pulsante -->
              <div
                v-if="sol.diasAtraso > 7"
                class="absolute top-3 left-3 flex h-2 w-2"
              >
                <span
                  class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"
                ></span>
                <span
                  class="relative inline-flex rounded-full h-2 w-2 bg-red-500"
                ></span>
              </div>

              <!-- Header com ID e Dias de Atraso -->
              <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2">
                  <span
                    class="text-xl font-bold text-blue-600 dark:text-blue-400 group-hover:text-blue-700 dark:group-hover:text-blue-300 transition-colors"
                  >
                    #{{ sol.id }}
                  </span>
                  <i
                    class="pi pi-eye text-xs text-blue-400 opacity-0 group-hover:opacity-100 transition-opacity"
                  ></i>
                </div>
                <span
                  :class="[
                    'inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold shadow-sm',
                    sol.diasAtraso > 7
                      ? 'bg-gradient-to-r from-red-600 to-rose-700 text-white animate-pulse'
                      : sol.diasAtraso > 3
                        ? 'bg-gradient-to-r from-red-500 to-rose-600 text-white'
                        : 'bg-gradient-to-r from-orange-500 to-red-500 text-white'
                  ]"
                >
                  <i class="pi pi-exclamation-triangle text-[10px]"></i>
                  {{ Math.floor(sol.diasAtraso) }}d atraso
                </span>
              </div>

              <!-- Título -->
              <p
                class="text-sm font-medium text-gray-800 dark:text-gray-200 line-clamp-2 mb-3"
              >
                {{ getTituloSolicitacao(sol) }}
              </p>

              <!-- Info Grid -->
              <div class="grid grid-cols-3 gap-2 text-xs">
                <!-- Responsável -->
                <div class="flex flex-col">
                  <span class="text-gray-400 dark:text-gray-500 mb-0.5">
                    Responsável
                  </span>
                  <span
                    v-tooltip.top="sol.responsavel || 'Não atribuído'"
                    :class="[
                      'font-medium truncate',
                      sol.responsavel
                        ? 'text-gray-600 dark:text-gray-400'
                        : 'text-red-500 dark:text-red-400 italic'
                    ]"
                  >
                    {{
                      sol.responsavel?.split(" ").slice(0, 2).join(" ") ||
                      "Não atribuído"
                    }}
                  </span>
                </div>

                <!-- Previsão -->
                <div class="flex flex-col">
                  <span class="text-gray-400 dark:text-gray-500 mb-0.5">
                    Previsão
                  </span>
                  <span class="font-medium text-red-600 dark:text-red-400">
                    {{ sol.previsao }}
                  </span>
                </div>

                <!-- Status -->
                <div class="flex flex-col items-end">
                  <span class="text-gray-400 dark:text-gray-500 mb-0.5">
                    Status
                  </span>
                  <span
                    :class="[
                      'inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold whitespace-nowrap',
                      getStatusClass(sol.status)
                    ]"
                  >
                    {{ sol.status }}
                  </span>
                </div>
              </div>

              <!-- Barra de atraso -->
              <div
                class="mt-3 h-1.5 w-full rounded-full bg-red-100 dark:bg-red-900/30 overflow-hidden"
              >
                <div
                  class="h-full rounded-full bg-gradient-to-r from-red-500 to-rose-600 transition-all duration-500"
                  :style="{
                    width: Math.min((sol.diasAtraso / 30) * 100, 100) + '%'
                  }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <div
          v-else
          class="flex h-32 items-center justify-center"
        >
          <div class="text-center">
            <div
              class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 mb-3"
            >
              <i
                class="pi pi-check-circle text-3xl text-green-500 dark:text-green-400"
              ></i>
            </div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
              Nenhuma solicitação atrasada!
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
              Todas as entregas estão no prazo
            </p>
          </div>
        </div>
      </div>

      <!-- Aguardando Feedback do Solicitante -->
      <div
        class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
      >
        <div class="flex items-center justify-between mb-5">
          <h3
            class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
          >
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-400 to-teal-500 shadow-md"
            >
              <i class="pi pi-comments text-white"></i>
            </span>
            <div>
              <span>Aguardando Feedback do Solicitante</span>
              <p class="text-xs font-normal text-gray-400 dark:text-gray-500">
                Resolvidas aguardando confirmação
              </p>
            </div>
          </h3>
          <span
            v-if="dados.listaAguardandoFeedback?.length"
            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-cyan-100 dark:bg-cyan-900/30 text-cyan-700 dark:text-cyan-400 text-sm font-bold"
          >
            <i class="pi pi-hourglass text-xs"></i>
            {{ dados.listaAguardandoFeedback.length }}
          </span>
        </div>

        <div v-if="dados.listaAguardandoFeedback?.length">
          <!-- Cards Grid -->
          <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-3">
            <div
              v-for="sol in dados.listaAguardandoFeedback"
              :key="sol.id"
              @click="abrirSolicitacao(sol)"
              class="group relative overflow-hidden rounded-xl border border-cyan-200 dark:border-cyan-800/50 bg-gradient-to-br from-cyan-50 to-teal-50 dark:from-cyan-900/10 dark:to-teal-900/10 p-4 hover:shadow-lg hover:border-cyan-300 dark:hover:border-cyan-700 transition-all duration-300 hover:scale-[1.01] cursor-pointer"
            >
              <!-- Header com ID e Tempo de Espera -->
              <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2">
                  <span
                    class="text-xl font-bold text-blue-600 dark:text-blue-400 group-hover:text-blue-700 dark:group-hover:text-blue-300 transition-colors"
                  >
                    #{{ sol.id }}
                  </span>
                  <i
                    class="pi pi-eye text-xs text-blue-400 opacity-0 group-hover:opacity-100 transition-opacity"
                  ></i>
                </div>
                <span
                  :class="[
                    'inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold shadow-sm',
                    getCorTempoEspera(sol.segundosAguardando)
                  ]"
                >
                  <i class="pi pi-hourglass text-[10px]"></i>
                  {{ formatarTempoEspera(sol.segundosAguardando).valor
                  }}{{ formatarTempoEspera(sol.segundosAguardando).unidade }}
                </span>
              </div>

              <!-- Título -->
              <p
                class="text-sm font-medium text-gray-800 dark:text-gray-200 line-clamp-2 mb-3"
              >
                {{ getTituloSolicitacao(sol) }}
              </p>

              <!-- Info Grid -->
              <div class="grid grid-cols-2 gap-2 text-xs">
                <!-- Solicitante -->
                <div class="flex flex-col">
                  <span class="text-gray-400 dark:text-gray-500 mb-0.5">
                    Solicitante
                  </span>
                  <span
                    v-tooltip.top="sol.solicitante"
                    class="text-gray-600 dark:text-gray-400 font-medium truncate"
                  >
                    {{
                      sol.solicitante?.split(" ").slice(0, 2).join(" ") || "N/A"
                    }}
                  </span>
                </div>

                <!-- Resolvida em -->
                <div class="flex flex-col items-end">
                  <span class="text-gray-400 dark:text-gray-500 mb-0.5">
                    Resolvida em
                  </span>
                  <span class="font-medium text-green-600 dark:text-green-400">
                    {{ sol.updated_at }}
                  </span>
                </div>
              </div>

              <!-- Status badge -->
              <div class="mt-3 flex items-center justify-center">
                <span
                  class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-semibold"
                >
                  <i class="pi pi-check-circle text-[10px]"></i>
                  Resolvida - Aguardando finalização
                </span>
              </div>
            </div>
          </div>
        </div>

        <div
          v-else
          class="flex h-32 items-center justify-center"
        >
          <div class="text-center">
            <div
              class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 mb-3"
            >
              <i
                class="pi pi-check-circle text-3xl text-green-500 dark:text-green-400"
              ></i>
            </div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
              Nenhuma solicitação aguardando feedback!
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
              Todas as resolvidas já foram finalizadas
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Estado vazio -->
    <div
      v-else-if="!loading && !loadingInicial"
      class="bg-white dark:bg-slate-800 rounded-3xl p-12 text-center shadow-sm"
    >
      <span
        class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 dark:bg-slate-700 mb-6"
      >
        <i
          class="pi pi-chart-bar !text-2xl text-gray-400 dark:text-gray-500"
        ></i>
      </span>
      <h3 class="text-xl font-bold text-gray-700 dark:text-gray-200 mb-2">
        Selecione os filtros
      </h3>
      <p class="text-gray-500 dark:text-gray-400">
        Selecione um departamento e período para visualizar o dashboard.
      </p>
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
  </AuthenticatedLayout>
</template>
