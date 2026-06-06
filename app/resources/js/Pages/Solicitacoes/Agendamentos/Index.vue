<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from "vue"
import { Head, usePage } from "@inertiajs/vue3"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import Loader from "@/Components/Loader.vue"
import { swalErro, tratarNome } from "@/utils/globalFunctions"
import axios from "axios"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import Panel from "primevue/panel"
import Button from "primevue/button"
import Detalhes from "./partials/Detalhes.vue"
import BsAgenda from "@/Components/Componentes/Agenda/BsAgenda.vue"
import Agendamento from "../Agendamento.vue"
import Filial2 from "@/Components/New/Filial2.vue"
import Solicitacao from "@/Pages/Solicitacoes/Solicitação.vue"

const page = usePage()

// Marcar como página nova
layout.paginaNova.value = true

const props = defineProps({
  solicitacoes: {
    type: Array,
    required: true
  },
  usuariosDepto: {
    type: Array,
    required: false
  },
  usuarioLogado: {
    type: Array,
    required: false
  },
  permissoes: {},
  auth: {}
})

// ╔══════════════════════════════════════════════════════════════╗
// ║                        ESTADOS                               ║
// ╚══════════════════════════════════════════════════════════════╝

const usuarioSelecionado = ref(
  props.usuariosDepto !== null ? "" : props.usuarioLogado.matricula
)
const dados = ref([...props.solicitacoes])
const usuarios = ref(props.usuariosDepto)
const dialogDetalhes = ref(false)
const agendamento = ref([])
const statusSelect = ref(null)
const filialSelecionada = ref(null)
const loading = ref(false)
const verTodos = ref(false)
const dialogNovoAgend = ref(false)
const dialogSolicitacao = ref(false)
const solicitacaoLembreteId = ref(null)

// Detecção de modo escuro
const isDark = ref(document.documentElement.classList.contains("dark"))
const observerDark = new MutationObserver(() => {
  isDark.value = document.documentElement.classList.contains("dark")
})
observerDark.observe(document.documentElement, {
  attributes: true,
  attributeFilter: ["class"]
})

const statusOptions = [
  {
    label: "Aguardando",
    value: "aguardando",
    icon: "pi pi-clock",
    color: "amber"
  },
  {
    label: "Em Atendimento",
    value: "em atendimento",
    icon: "pi pi-spin pi-cog",
    color: "blue"
  },
  {
    label: "Finalizado",
    value: "finalizado",
    icon: "pi pi-check-circle",
    color: "green"
  },
  {
    label: "Cancelado",
    value: "cancelado",
    icon: "pi pi-times-circle",
    color: "red"
  }
]

// ╔══════════════════════════════════════════════════════════════╗
// ║                      COMPUTEDS                               ║
// ╚══════════════════════════════════════════════════════════════╝

watch(
  () => props.solicitacoes,
  (newVal) => {
    dados.value = [...newVal]
  }
)

watch(
  () => props.usuariosDepto,
  (newVal) => {
    usuarios.value = [...newVal]
  }
)

const usuariosComputed = computed(() => {
  if (!usuarios.value) return []
  return [...usuarios.value].sort((a, b) =>
    a.nome.localeCompare(b.nome, "pt-BR", { sensitivity: "base" })
  )
})

// Estatísticas dos agendamentos
const estatisticas = computed(() => {
  if (!dadosEnvio.value?.dados)
    return {
      aguardando: 0,
      atendimento: 0,
      finalizado: 0,
      cancelado: 0,
      total: 0
    }

  const dados = dadosEnvio.value.dados
  return {
    aguardando: dados.filter((d) => d.status === "aguardando").length,
    atendimento: dados.filter((d) => d.status === "em atendimento").length,
    finalizado: dados.filter((d) => d.status === "finalizado").length,
    cancelado: dados.filter((d) => d.status === "cancelado").length,
    total: dados.length
  }
})

// ╔══════════════════════════════════════════════════════════════╗
// ║                      LIFECYCLE                               ║
// ╚══════════════════════════════════════════════════════════════╝

onMounted(async () => {
  await getByData()

  if (usuarios.value == null) {
    usuarios.value = [
      {
        matricula:
          props.solicitacoes.length > 0
            ? props.solicitacoes[0].agendamentos[0].mat_responsavel
            : props.usuarioLogado.matricula,
        nome:
          props.solicitacoes.length > 0
            ? props.solicitacoes[0].agendamentos[0].nomeResponsavel
            : props.usuarioLogado.nome
      }
    ]
  }

  // Deep-link vindo da notificação: ?agendamento=ID → abre o detalhe direto
  await abrirAgendamentoDaUrl()
})

/**
 * Lê o parâmetro ?agendamento=ID da URL (clique na notificação) e abre
 * o detalhe do agendamento correspondente.
 *
 * Procura primeiro nos dados já carregados do calendário (mês atual).
 * Se não achar (agendamento em outro mês), busca no backend pela matrícula
 * do usuário logado e abre.
 */
async function abrirAgendamentoDaUrl() {
  const params = new URLSearchParams(window.location.search)
  const id = params.get("agendamento")
  if (!id) return

  // 1) Procura nos agendamentos do calendário já carregados (mês atual)
  let alvo = (dadosEnvio.value.dados || []).find(
    (a) => String(a.id) === String(id)
  )

  // 2) Não achou: busca no backend pelos agendamentos do usuário logado
  if (!alvo) {
    try {
      const mat = props.usuarioLogado?.matricula
      const { data } = await axios.get(
        "/solicitacoes/agendamento/agendamentos/" + mat
      )
      // data = array de solicitações, cada uma com .agendamentos[]
      for (const sol of data) {
        const ag = (sol.agendamentos || []).find(
          (a) => String(a.id) === String(id)
        )
        if (ag) {
          alvo = ag
          break
        }
      }
    } catch (e) {
      console.error("Erro ao buscar agendamento da notificação:", e)
    }
  }

  if (alvo) {
    abrirDetalhes(alvo)
    // Limpa o parâmetro só depois de abrir, pra não reabrir no F5
    const url = new URL(window.location.href)
    url.searchParams.delete("agendamento")
    window.history.replaceState({}, "", url)
  }
}

onUnmounted(() => {
  observerDark.disconnect()
})

// ╔══════════════════════════════════════════════════════════════╗
// ║                       WATCHERS                               ║
// ╚══════════════════════════════════════════════════════════════╝

watch(usuarioSelecionado, async (newVal) => {
  if (newVal != null) {
    verTodos.value = false
    await getByData()
    await getAgendamentosByUser(newVal)
  } else {
    await getByData()
    verTodos.value = true
  }
})

watch(filialSelecionada, async () => {
  await getByData()
})

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝

async function getAgendamentosByUser(mat_usuario) {
  await axios
    .get("/solicitacoes/agendamento/agendamentos/" + mat_usuario)
    .then(async (res) => {
      dados.value = res.data
    })
    .catch((err) => {
      console.error(err)
    })
}

function abrirDetalhes(ag) {
  // Se for lembrete, abre a solicitação no dialog
  if (ag.tipo === "lembrete" && ag.solicitacao_id) {
    solicitacaoLembreteId.value = ag.solicitacao_id
    dialogSolicitacao.value = true
    return
  }

  // Se for visita, abre o modal de detalhes
  if (ag.status == "aguardando") {
    ag.statusDet = "ativo"
  }
  agendamento.value = ag
  dialogDetalhes.value = true
}

function atualizaAgendamento(vAgendamento) {
  props.solicitacoes.map((sol) => {
    if (sol.agendamentos[0].id == vAgendamento.id) {
      sol.agendamentos[0] = vAgendamento
    }
  })

  dadosEnvio.value.dados.forEach((item) => {
    if (item.id == vAgendamento.id) {
      item.status = vAgendamento.status
    }
  })

  agendamento.value = vAgendamento
}

const dadosEnvio = ref({
  dados: [],
  dataIni: "",
  dataFim: ""
})

async function getByData(datas) {
  loading.value = true
  let params = {
    dataIni: datas ? datas.dataIni : "",
    dataFim: datas ? datas.dataFim : "",
    responsavel: usuarioSelecionado.value ?? ""
  }

  await axios
    .post("/solicitacoes/agendamento/buscar-por-data", params)
    .then((res) => {
      dadosEnvio.value.dados = res.data
      dadosEnvio.value.dataIni = datas ? datas.dataIni : ""
      dadosEnvio.value.dataFim = datas ? datas.dataFim : ""

      if (statusSelect.value != null) {
        dadosEnvio.value.dados = dadosEnvio.value.dados.filter(
          (item) => item.status == statusSelect.value
        )
      }

      if (filialSelecionada.value != null) {
        dadosEnvio.value.dados = dadosEnvio.value.dados.filter(
          (item) => item.filial == filialSelecionada.value.codigo
        )
      }
    })
    .catch((e) => {
      console.error(e)
      swalErro()
    })
    .finally(() => {
      loading.value = false
    })
}

function fecharDialog() {
  if (agendamento.value.status == "ativo") {
    agendamento.value.status = "aguardando"
  }
  dialogDetalhes.value = false
}

async function atualizarAgenda() {
  await getByData()
  dialogNovoAgend.value = false
}

function validaPermissao(perm) {
  return props.permissoes.includes(perm)
}

async function filtrarStatus() {
  await getByData()
}
</script>

<template>
  <Head title="Agendamentos" />

  <AuthenticatedLayout>
    <!-- Loading Overlay -->
    <div
      v-if="loading"
      class="fixed inset-0 z-50 flex items-center justify-center bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm"
    >
      <div class="flex flex-col items-center gap-4">
        <div class="relative">
          <div
            class="w-16 h-16 border-4 border-blue-200 dark:border-blue-900 rounded-full animate-spin border-t-blue-600 dark:border-t-blue-400"
          ></div>
          <div class="absolute inset-0 flex items-center justify-center">
            <i
              class="pi pi-calendar text-blue-600 dark:text-blue-400 text-xl"
            ></i>
          </div>
        </div>
        <span class="text-lg font-medium text-gray-600 dark:text-gray-300">
          Carregando agendamentos...
        </span>
      </div>
    </div>

    <!-- Breadcrumb -->
    <div
      class="w-full flex flex-wrap items-center bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-xl mb-4 sm:mb-6 border border-gray-200 dark:border-slate-700 shadow-sm"
    >
      <div
        class="flex flex-wrap items-center gap-1 sm:gap-2 text-sm sm:text-base text-gray-600 dark:text-gray-300 font-medium w-full"
      >
        <div class="flex items-center gap-1 sm:gap-2">
          <i class="pi pi-home text-gray-400 dark:text-gray-500"></i>
          <span>Home</span>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span>Solicitações</span>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span
            class="text-gray-950 dark:text-white font-bold truncate max-w-[120px] sm:max-w-none"
          >
            Agendamentos
          </span>
        </div>
      </div>
    </div>

    <!-- Cabeçalho da Página -->
    <div class="space-y-2 mb-6 mt-4">
      <div
        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
      >
        <div class="flex items-center gap-3">
          <h2
            class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
          >
            <div
              class="w-1 h-8 bg-gradient-to-b from-cyan-500 to-cyan-700 rounded-full"
            ></div>
            Agendamentos
          </h2>
        </div>

        <!-- Botão Novo Agendamento -->
        <div v-if="validaPermissao('solicitacoes.lista.criar-agendamento')">
          <Button
            @click="dialogNovoAgend = true"
            icon="pi pi-plus"
            label="Novo Agendamento"
            outlined
            class="w-full"
          />
        </div>
      </div>
      <span
        class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium pl-2 pr-2 sm:pr-0"
      >
        Gerencie os agendamentos de atendimento do seu departamento.
      </span>
    </div>

    <!-- Cards de Estatísticas -->
    <div
      class="grid grid-cols-2 sm:grid-cols-5 gap-3 sm:gap-4 mb-6 px-1 sm:px-0"
    >
      <!-- Aguardando -->
      <div
        class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300"
      >
        <div class="flex items-center justify-between mb-1">
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-yellow-400"
          >
            <i class="pi pi-clock !text-[11px] flex-shrink-0"></i>
            Aguardando
          </span>
        </div>
        <div
          class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
        >
          <span>{{ estatisticas.aguardando }}</span>
        </div>
      </div>

      <!-- Em Atendimento -->
      <div
        class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300"
      >
        <div class="flex items-center justify-between mb-1">
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-blue-500"
          >
            <i class="pi pi-cog !text-[11px] flex-shrink-0"></i>
            Em Atendimento
          </span>
        </div>
        <div
          class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
        >
          <span>{{ estatisticas.atendimento }}</span>
        </div>
      </div>

      <!-- Finalizados -->
      <div
        class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300"
      >
        <div class="flex items-center justify-between mb-1">
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-emerald-500"
          >
            <i class="pi pi-check-circle !text-[11px] flex-shrink-0"></i>
            Finalizados
          </span>
        </div>
        <div
          class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
        >
          <span>{{ estatisticas.finalizado }}</span>
        </div>
      </div>

      <!-- Cancelados -->
      <div
        class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300"
      >
        <div class="flex items-center justify-between mb-1">
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-red-600"
          >
            <i class="pi pi-times-circle !text-[11px] flex-shrink-0"></i>
            Cancelados
          </span>
        </div>
        <div
          class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
        >
          <span>{{ estatisticas.cancelado }}</span>
        </div>
      </div>

      <!-- Total -->
      <div
        class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300"
      >
        <div class="flex items-center justify-between mb-1">
          <span
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-purple-500"
          >
            <i class="pi pi-calendar !text-[11px] flex-shrink-0"></i>
            Total
          </span>
        </div>
        <div
          class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
        >
          <span>{{ estatisticas.total }}</span>
        </div>
      </div>
    </div>

    <!-- Filtros com Panel -->
    <Panel
      toggleable
      :collapsed="false"
      class="mb-6"
      :pt="{
        root: {
          class:
            '!bg-white dark:!bg-slate-800 !rounded-2xl !border !border-gray-200 dark:!border-slate-700 !shadow-sm overflow-hidden'
        },
        header: {
          class:
            '!bg-white dark:!bg-slate-800 !border-b !border-gray-200 dark:!border-slate-700 !rounded-t-2xl'
        },
        content: { class: '!bg-white dark:!bg-slate-800 !p-4' },
        toggleableContent: { class: '!bg-white dark:!bg-slate-800' },
        toggler: { class: 'dark:!text-white dark:hover:!bg-slate-700' }
      }"
    >
      <template #header>
        <div class="flex items-center gap-3">
          <span
            class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-cyan-100 dark:bg-cyan-900/30 shadow"
          >
            <i
              class="pi pi-filter text-cyan-600 dark:text-cyan-400 text-lg"
            ></i>
          </span>
          <div>
            <span class="font-bold text-gray-900 dark:text-white text-lg">
              Filtros
            </span>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              Refine sua busca de agendamentos
            </p>
          </div>
        </div>
      </template>

      <div class="flex flex-col gap-4 w-full">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <!-- Responsável -->
          <div class="flex flex-col gap-1.5 min-w-0">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
            >
              <i class="pi pi-user text-gray-400"></i>
              Responsável
            </label>
            <Select
              v-model="usuarioSelecionado"
              :options="usuariosComputed"
              optionLabel="nome"
              optionValue="matricula"
              placeholder="Selecione o responsável"
              :disabled="usuariosDepto == null"
              showClear
              filter
              filterPlaceholder="Buscar..."
              class="w-full overflow-hidden"
            />
          </div>

          <!-- Filial -->
          <div class="flex flex-col gap-1.5 min-w-0">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
            >
              <i class="pi pi-building text-gray-400"></i>
              Filial
            </label>
            <Filial2
              v-model="filialSelecionada"
              :multi-select="false"
              :retorna-objeto="false"
              :show-clear="true"
              placeholder="Selecione a filial"
              class="w-full overflow-hidden"
            />
          </div>

          <!-- Status -->
          <div class="flex flex-col gap-1.5 min-w-0">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
            >
              <i class="pi pi-tag text-gray-400"></i>
              Status
            </label>
            <Select
              v-model="statusSelect"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Selecione o status"
              showClear
              @change="filtrarStatus"
              class="w-full overflow-hidden"
            >
              <template #option="slotProps">
                <div class="flex items-center gap-2">
                  <i
                    :class="[
                      slotProps.option.icon,
                      `text-${slotProps.option.color}-500`
                    ]"
                  ></i>
                  <span>{{ slotProps.option.label }}</span>
                </div>
              </template>
            </Select>
          </div>
        </div>
      </div>
    </Panel>

    <!-- Agenda -->
    <div
      class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-sm overflow-hidden"
    >
      <div
        class="p-4 border-b border-gray-200 dark:border-slate-700 bg-gradient-to-r from-cyan-50 to-blue-50 dark:from-cyan-900/20 dark:to-blue-900/20"
      >
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 rounded-xl bg-white dark:bg-slate-700 shadow flex items-center justify-center"
          >
            <i
              class="pi pi-calendar text-cyan-600 dark:text-cyan-400 text-lg"
            ></i>
          </div>
          <div>
            <h3 class="font-bold text-gray-900 dark:text-white">
              Calendário de Agendamentos
            </h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              Clique em um agendamento para ver detalhes
            </p>
          </div>
        </div>
      </div>

      <div class="p-4">
        <BsAgenda
          v-model="dadosEnvio"
          @update-data="getByData"
          @detalhar="abrirDetalhes"
          :verTodos="verTodos"
        />
      </div>
    </div>

    <!-- Dialog Detalhes -->
    <Dialog
      v-model:visible="dialogDetalhes"
      modal
      position="top"
      :closable="false"
      :showHeader="false"
      class="!w-[100vw] !h-[100vh] !max-h-[100vh] sm:!w-[450px] sm:!h-[85vh] sm:!max-h-[85vh] !m-0 sm:!mt-4 !rounded-none sm:!rounded-2xl !top-0 !left-0 sm:!top-auto sm:!left-auto"
      :pt="{
        root: {
          class: '!rounded-none sm:!rounded-2xl !overflow-hidden !border-0'
        },
        mask: { class: 'backdrop-blur-sm' },
        content: {
          class: '!p-0 !h-full !overflow-hidden'
        }
      }"
    >
      <Detalhes
        :agendamento="agendamento"
        :usuarioLogado="usuarioLogado"
        :permissoes="props.permissoes"
        :auth="props.auth"
        @fechar="fecharDialog"
        @update:loading="(valor) => (loading = valor)"
        @acao:iniciar="atualizaAgendamento"
        @acao:finalizar="atualizaAgendamento"
        @acao:atualizar="atualizarAgenda"
      />
    </Dialog>

    <!-- Dialog Novo Agendamento -->
    <Dialog
      v-model:visible="dialogNovoAgend"
      modal
      position="top"
      :closable="false"
      :showHeader="false"
      class="!w-[95vw] sm:!w-[90vw] lg:!w-auto !max-w-4xl !max-h-[90vh]"
      :pt="{
        root: { class: '!rounded-2xl !overflow-hidden !border-0 !shadow-2xl' },
        mask: { class: 'backdrop-blur-sm' },
        content: { class: '!p-0 !bg-white dark:!bg-slate-800' }
      }"
    >
      <!-- Header Customizado -->
      <div class="bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div
              class="w-12 h-12 bg-white/20 shrink-0 rounded-xl flex items-center justify-center shadow-lg"
            >
              <i class="pi pi-calendar-plus text-white text-xl"></i>
            </div>
            <div>
              <h2 class="text-xl font-bold text-white">Novo Agendamento</h2>
              <p class="text-sm text-white/70">
                Crie um agendamento sem vínculo a solicitações
              </p>
            </div>
          </div>
          <button
            @click="dialogNovoAgend = false"
            class="w-10 h-10 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center transition-all duration-200 group"
          >
            <i
              class="pi pi-times text-white group-hover:scale-110 transition-transform"
            ></i>
          </button>
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="p-5 overflow-y-auto max-h-[calc(90vh-100px)]">
        <Agendamento
          :user="props.auth"
          @atualizar="atualizarAgenda"
        />
      </div>
    </Dialog>

    <!-- Dialog Solicitação do Lembrete -->
    <Dialog
      v-model:visible="dialogSolicitacao"
      modal
      class="!bg-transparent !border-0 !shadow-none"
    >
      <template #container>
        <Solicitacao
          v-if="solicitacaoLembreteId"
          :solicitacao_id="solicitacaoLembreteId"
          :permissoes="page.props.permissoes"
          :auth="page.props.auth"
          @fecharDialogo="dialogSolicitacao = false"
        />
      </template>
    </Dialog>
  </AuthenticatedLayout>
</template>
