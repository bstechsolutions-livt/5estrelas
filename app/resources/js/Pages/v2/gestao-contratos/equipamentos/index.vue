<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, ref, computed } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import * as EquipJs from "../gestao-equipamentos.js"
import Panel from "primevue/panel"
import Button from "primevue/button"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Tag from "primevue/tag"
import Dialog from "primevue/dialog"
import EquipamentoForm from "./form.vue"
import { useTour } from "@/composables/useTour"
import "@/../css/tour.css"
import Swal from "sweetalert2"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO LOCAL                           ║
// ╚══════════════════════════════════════════════════════════════╝
const showConfirmDelete = ref(false)
const equipParaExcluir = ref(null)
const loadingInicial = ref(true)
const loadingExport = ref(false)

// Controle de visualização: 'lista' ou 'form'
const viewMode = ref("lista")
const editId = ref(null)

// Props da página (para quando acessar via URL direta com id)
const page = usePage()

// Tour Guiado
const { startTour, autoStart } = useTour("gestao-equipamentos")

const tourSteps = [
  {
    popover: {
      title: "Controle de Equipamentos",
      description: `
        Bem-vindo ao módulo de <strong>Controle de Validade de Equipamentos</strong>!<br><br>
        Aqui você gerencia:<br>
        • <strong>Extintores</strong>, mangueiras, hidrantes e outros equipamentos<br>
        • <strong>Datas de validade</strong> com alertas automáticos<br>
        • <strong>Ocorrências</strong> (despressurização, dano, etc.)<br>
        • <strong>Fotos</strong> para auditoria<br>
        • <strong>Tratativas</strong> — ações tomadas<br><br>
        Vamos conhecer a tela!
      `,
      side: "over",
      align: "center"
    }
  },
  {
    element: "#tour-equip-filtros",
    popover: {
      title: "Filtros",
      description: `
        Use os filtros para encontrar equipamentos rapidamente:<br><br>
        • <strong>Buscar</strong> — por identificação, carga ou localização<br>
        • <strong>Filial</strong> — filtrar por loja<br>
        • <strong>Tipo</strong> — extintor, mangueira, etc.<br>
        • <strong>Status</strong> — vigente, vencendo, vencido ou em manutenção
      `,
      side: "bottom",
      align: "center"
    }
  },
  {
    element: "#tour-equip-tabela",
    popover: {
      title: "Tabela de Equipamentos",
      description: `
        A tabela mostra todos os equipamentos cadastrados com:<br><br>
        • <strong>Filial</strong> e localidade<br>
        • <strong>Tipo</strong> e especificações (carga, qtd projeto)<br>
        • <strong>Status</strong> — calculado automaticamente pela data de validade<br>
        • <strong>Vencimento</strong> — com contagem de dias<br>
        • <strong>Tratativa</strong> — última ação registrada<br>
        • <strong>Fotos</strong> — clique na miniatura para ver a galeria<br><br>
        <em>Linhas em vermelho = equipamento vencido</em>
      `,
      side: "top",
      align: "center"
    }
  },
  {
    element: "#tour-equip-btn-novo",
    popover: {
      title: "Novo Equipamento",
      description: `
        Clique aqui para cadastrar um novo equipamento.<br><br>
        O formulário abre na mesma página (sem recarregar).<br>
        Preencha os dados obrigatórios: <strong>Filial, Tipo, Data de Validade e Status</strong>.
      `,
      side: "bottom",
      align: "end"
    }
  },
  {
    element: "#tour-equip-btn-exportar",
    popover: {
      title: "Exportar Excel",
      description: `
        Exporta todos os equipamentos filtrados para um arquivo <strong>.xlsx</strong>.<br><br>
        Os filtros aplicados são respeitados na exportação.
      `,
      side: "bottom",
      align: "end"
    }
  },
  {
    popover: {
      title: "Status Automático",
      description: `
        O sistema calcula o status automaticamente:<br><br>
        🟢 <strong>Vigente</strong> — validade > 10 dias<br>
        🟡 <strong>Vencendo</strong> — validade ≤ 10 dias<br>
        🔴 <strong>Vencido</strong> — data já passou<br>
        🔵 <strong>Em Manutenção</strong> — definido manualmente<br><br>
        No formulário, você só escolhe <strong>Vigente</strong> ou <strong>Em Manutenção</strong>. O resto é automático!
      `,
      side: "over",
      align: "center"
    }
  },
  {
    element: "#tour-btn-ajuda",
    popover: {
      title: "Precisa de ajuda?",
      description: `
        Sempre que tiver dúvidas, clique neste botão <strong>(?)</strong> para iniciar o tour guiado novamente.
      `,
      side: "bottom",
      align: "end"
    }
  }
]

// Galeria de fotos
const showGaleria = ref(false)
const galeriaFotos = ref([])
const galeriaIndex = ref(0)
const galeriaEquipNome = ref("")

const filiaisFormatadas = computed(() => {
  return [
    { codfilial: "", label: "Todas" },
    ...EquipJs.filiais.value.map((filial) => ({
      ...filial,
      label: `${filial.codfilial} - ${filial.fantasia}`
    }))
  ]
})

const tiposFormatados = computed(() => {
  return [
    { id: "", nome: "Todos" },
    ...EquipJs.tiposEquipamento.value
  ]
})

const statusOptions = [
  { label: "Todos", value: "" },
  { label: "Vigente", value: "VIGENTE" },
  { label: "Vencendo", value: "VENCENDO" },
  { label: "Vencido", value: "VENCIDO" },
  { label: "Em Manutenção", value: "EM_MANUTENCAO" }
]

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)
  await Promise.all([
    EquipJs.getEquipamentos(),
    EquipJs.getFiliais(),
    EquipJs.getTiposEquipamento()
  ])
  loadingInicial.value = false

  // Se acessou via URL com id (ex: /equipamentos/5), abrir form
  if (page.props.id) {
    editId.value = page.props.id
    viewMode.value = "form"
  }

  // Tour guiado — auto-start na primeira visita
  if (viewMode.value === "lista") {
    setTimeout(() => {
      autoStart(tourSteps, Swal.fire.bind(Swal), 800)
    }, 500)
  }
})

function novoEquipamento() {
  editId.value = null
  viewMode.value = "form"
}

function editarEquipamento(id) {
  editId.value = id
  viewMode.value = "form"
}

function voltarParaLista() {
  viewMode.value = "lista"
  editId.value = null
  EquipJs.getEquipamentos(EquipJs.equipamentos.value.pagination.current_page)
}

function confirmarExclusao(equip) {
  equipParaExcluir.value = equip
  showConfirmDelete.value = true
}

async function excluirEquipamento() {
  if (equipParaExcluir.value) {
    await EquipJs.excluirEquipamento(equipParaExcluir.value.id)
    showConfirmDelete.value = false
    equipParaExcluir.value = null
  }
}

function buscar() {
  EquipJs.getEquipamentos(1)
}

function limparFiltros() {
  EquipJs.equipamentos.value.filtros = {
    status: "",
    filial_id: "",
    tipo_equipamento_id: "",
    busca: ""
  }
  EquipJs.getEquipamentos(1)
}

async function exportar() {
  loadingExport.value = true
  try {
    await EquipJs.exportarEquipamentos()
  } finally {
    loadingExport.value = false
  }
}

function rowClass(data) {
  return data.status_computado === "VENCIDO" ? "bg-red-50 dark:bg-red-900/20" : ""
}

// Galeria de fotos
function abrirGaleria(equip) {
  galeriaFotos.value = equip.fotos || []
  galeriaIndex.value = 0
  galeriaEquipNome.value = (equip.tipo_equipamento?.nome || "Equipamento") + (equip.numero_identificacao ? " — " + equip.numero_identificacao : "")
  showGaleria.value = true
}

function galeriaAnterior() {
  if (galeriaIndex.value > 0) galeriaIndex.value--
}

function galeriaProxima() {
  if (galeriaIndex.value < galeriaFotos.value.length - 1) galeriaIndex.value++
}

function formatarCnpj(valor) {
  if (!valor) return "-"
  const num = String(valor).replace(/\D/g, "")
  if (num.length === 14) {
    return num.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, "$1.$2.$3/$4-$5")
  }
  if (num.length === 11) {
    return num.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, "$1.$2.$3-$4")
  }
  return valor
}
</script>

<template>
  <AuthenticatedLayout>
    <!-- MODO FORMULÁRIO -->
    <EquipamentoForm
      v-if="viewMode === 'form'"
      :equipamento-id="editId"
      @voltar="voltarParaLista"
    />

    <!-- MODO LISTA -->
    <template v-else>
    <!-- Breadcrumb -->
    <div class="w-full flex flex-wrap items-center bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-xl mb-4 sm:mb-6 border border-gray-200 dark:border-slate-700">
      <div class="flex flex-wrap items-center gap-1 sm:gap-2 text-sm sm:text-base text-gray-600 dark:text-gray-300 font-medium w-full">
        <div class="flex items-center gap-1 sm:gap-2">
          <i class="pi pi-home"></i>
          <span>Home</span>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <a href="/pagina/gestao-contratos" class="hover:text-purple-600 dark:hover:text-purple-400">Gestão de Contratos</a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">Equipamentos</span>
        </div>
      </div>
    </div>

    <!-- Cabeçalho -->
    <div class="space-y-2 mb-6 mt-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3">
            <div class="w-1 h-8 bg-gradient-to-b from-purple-500 to-purple-700 rounded-full"></div>
            Controle de Equipamentos
          </h2>
          <span class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium pl-4 mt-1">
            Gerencie a validade e conformidade dos equipamentos por filial.
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Button
            id="tour-btn-ajuda"
            icon="pi pi-question-circle"
            severity="secondary"
            text
            size="small"
            @click="startTour(tourSteps)"
            v-tooltip.top="'Tour Guiado'"
          />
          <Button
            id="tour-equip-btn-exportar"
            icon="pi pi-file-excel"
            severity="success"
            label="Exportar"
            outlined
            @click="exportar"
            :loading="loadingExport"
            :disabled="EquipJs.equipamentos.value.data.length === 0"
            v-tooltip.top="'Exportar Excel'"
            class="!px-3"
          />
          <Button id="tour-equip-btn-novo" label="Novo" icon="pi pi-plus" severity="info" @click="novoEquipamento" />
        </div>
      </div>
    </div>

    <!-- Filtros -->
    <div id="tour-equip-filtros" class="bg-white dark:bg-slate-800 rounded-2xl p-4 mb-4 border border-gray-100 dark:border-slate-700">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Buscar</label>
          <InputText
            v-model="EquipJs.equipamentos.value.filtros.busca"
            placeholder="ID, carga, localização..."
            class="w-full"
            size="small"
            @keyup.enter="buscar"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Filial</label>
          <Select
            v-model="EquipJs.equipamentos.value.filtros.filial_id"
            :options="filiaisFormatadas"
            optionLabel="label"
            optionValue="codfilial"
            placeholder="Todas"
            class="w-full"
            size="small"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Tipo</label>
          <Select
            v-model="EquipJs.equipamentos.value.filtros.tipo_equipamento_id"
            :options="tiposFormatados"
            optionLabel="nome"
            optionValue="id"
            placeholder="Todos"
            class="w-full"
            size="small"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Status</label>
          <Select
            v-model="EquipJs.equipamentos.value.filtros.status"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Todos"
            class="w-full"
            size="small"
          />
        </div>
        <div class="flex gap-2 items-end">
          <Button icon="pi pi-search"  severity="info" size="small" @click="buscar" :loading="EquipJs.equipamentos.value.loading" v-tooltip.top="'Buscar'" />
          <Button icon="pi pi-filter-slash" severity="secondary" size="small" outlined @click="limparFiltros" v-tooltip.top="'Limpar filtros'" />
        </div>
      </div>
    </div>

    <!-- Tabela -->
    <div id="tour-equip-tabela" class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 overflow-hidden">
      <DataTable
        :value="EquipJs.equipamentos.value.data"
        :loading="EquipJs.equipamentos.value.loading"
        paginator
        :rows="20"
        :rowsPerPageOptions="[10, 20, 50]"
        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
        currentPageReportTemplate="{first}-{last} de {totalRecords}"
        sortMode="multiple"
        removableSort
        stripedRows
        rowHover
        scrollable
        scrollHeight="flex"
        :rowClass="rowClass"
        class="text-sm"
        :pt="{ root: { class: 'border-0' }, paginator: { class: 'border-t border-gray-100 dark:border-slate-700' } }"
      >
        <template #loading>
          <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-purple-500 text-white rounded-full shadow text-xs">
            <i class="pi pi-spinner pi-spin"></i>
            <span>Carregando...</span>
          </div>
        </template>

        <template #empty>
          <div class="py-16 text-center">
            <i class="pi pi-inbox text-4xl text-gray-300 dark:text-gray-600 mb-3"></i>
            <p class="text-gray-500 dark:text-gray-400 font-medium">Nenhum equipamento encontrado</p>
            <p class="text-gray-400 dark:text-gray-500 text-xs mt-1">Ajuste os filtros ou cadastre um novo equipamento</p>
          </div>
        </template>

        <!-- FILIAL -->
        <Column field="filial.fantasia" header="Filial" sortable style="min-width: 160px">
          <template #body="{ data }">
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 dark:bg-purple-900/30 flex-shrink-0">
                <i class="pi pi-building text-purple-600 dark:text-purple-400 !text-xs"></i>
              </span>
              <div class="min-w-0">
                <div class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ data.filial?.fantasia || "-" }}</div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400">{{ data.filial?.cidade || "" }}{{ data.filial?.cidade && data.filial?.uf ? ' - ' + data.filial.uf : '' }}</div>
              </div>
            </div>
          </template>
        </Column>

        <!-- TIPO -->
        <Column field="tipo_equipamento.nome" header="Tipo" sortable style="min-width: 110px">
          <template #body="{ data }">
            <Tag :value="data.tipo_equipamento?.nome || '-'" severity="secondary" class="text-xs" />
          </template>
        </Column>

        <!-- CNPJ -->
        <Column field="filial.cgc" header="CNPJ" sortable style="min-width: 160px">
          <template #body="{ data }">
            <span class="text-xs text-gray-600 dark:text-gray-400 font-mono whitespace-nowrap">{{ formatarCnpj(data.filial?.cgc) }}</span>
          </template>
        </Column>

        <!-- QTD PROJETO -->
        <Column field="qtd_projeto" header="Qtd" sortable style="min-width: 60px" class="text-center">
          <template #body="{ data }">
            <span class="text-sm font-semibold text-gray-900 dark:text-gray-200">{{ data.qtd_projeto || "-" }}</span>
          </template>
        </Column>

        <!-- CARGA -->
        <Column field="carga" header="Carga" sortable style="min-width: 90px">
          <template #body="{ data }">
            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ data.carga || "-" }}</span>
          </template>
        </Column>

        <!-- STATUS -->
        <Column field="status_computado" header="Status" sortable style="min-width: 130px">
          <template #body="{ data }">
            <Tag
              :value="EquipJs.getStatusEquipamentoLabel(data.status_computado)"
              :severity="EquipJs.getStatusEquipamentoSeverity(data.status_computado)"
              class="text-xs font-semibold whitespace-nowrap"
            />
          </template>
        </Column>

        <!-- VENCIMENTO (após status) -->
        <Column field="data_validade" header="Vencimento" sortable style="min-width: 130px">
          <template #body="{ data }">
            <div>
              <div
                class="text-sm font-medium whitespace-nowrap"
                :class="data.dias_para_vencimento < 0 ? 'text-red-600 dark:text-red-400' : data.dias_para_vencimento <= 10 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-900 dark:text-gray-200'"
              >
                {{ EquipJs.formatarData(data.data_validade) }}
              </div>
              <div
                class="text-[11px] whitespace-nowrap"
                :class="data.dias_para_vencimento < 0 ? 'text-red-500 font-medium' : data.dias_para_vencimento <= 10 ? 'text-orange-500' : 'text-gray-400'"
              >
                <span v-if="data.dias_para_vencimento > 0">{{ data.dias_para_vencimento }}d restantes</span>
                <span v-else-if="data.dias_para_vencimento === 0">Vence hoje</span>
                <span v-else>{{ Math.abs(data.dias_para_vencimento) }}d vencido</span>
              </div>
            </div>
          </template>
        </Column>

        <!-- TRATATIVA -->
        <Column field="ultima_tratativa" header="Tratativa" style="min-width: 160px">
          <template #body="{ data }">
            <div v-if="data.ultima_tratativa" class="max-w-[180px]">
              <div class="text-xs font-medium text-gray-900 dark:text-gray-200 truncate">{{ data.ultima_tratativa }}</div>
            </div>
            <span v-else class="text-xs text-gray-300 dark:text-gray-600 italic">Nenhuma</span>
          </template>
        </Column>

        <!-- FOTOS -->
        <Column header="Fotos" style="min-width: 90px" class="text-center">
          <template #body="{ data }">
            <div v-if="data.fotos && data.fotos.length > 0" class="flex items-center justify-center gap-1">
              <img
                :src="'/storage/' + data.fotos[0].arquivo_path"
                :alt="data.fotos[0].arquivo_nome"
                class="w-9 h-9 rounded-lg object-cover border border-gray-200 dark:border-slate-600 cursor-pointer hover:scale-110 transition-transform shadow-sm"
                @click="abrirGaleria(data)"
              />
              <span v-if="data.fotos.length > 1" class="text-[10px] text-gray-500 dark:text-gray-400 font-bold bg-gray-100 dark:bg-slate-700 rounded-full px-1.5 py-0.5 cursor-pointer" @click="abrirGaleria(data)">
                +{{ data.fotos.length - 1 }}
              </span>
            </div>
            <span v-else class="text-gray-300 dark:text-gray-600"><i class="pi pi-image"></i></span>
          </template>
        </Column>

        <!-- AÇÕES -->
        <Column header="" style="min-width: 90px; max-width: 90px" :exportable="false" frozen alignFrozen="right">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-0.5">
              <Button icon="pi pi-pencil" severity="help" text rounded size="small" @click="editarEquipamento(data.id)" v-tooltip.top="'Editar'" />
              <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmarExclusao(data)" v-tooltip.top="'Excluir'" />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Dialog Galeria de Fotos -->
    <Dialog
      v-model:visible="showGaleria"
      modal
      :header="'Fotos — ' + galeriaEquipNome"
      :style="{ width: '700px', maxWidth: '95vw' }"
      :closable="true"
    >
      <div v-if="galeriaFotos.length > 0" class="flex flex-col items-center gap-4">
        <!-- Imagem principal -->
        <div class="relative w-full flex items-center justify-center bg-gray-50 dark:bg-slate-900 rounded-xl p-2 min-h-[300px] max-h-[60vh]">
          <img
            :src="'/storage/' + galeriaFotos[galeriaIndex].arquivo_path"
            :alt="galeriaFotos[galeriaIndex].arquivo_nome"
            class="max-w-full max-h-[55vh] object-contain rounded-lg shadow"
          />
          <!-- Setas de navegação -->
          <button
            v-if="galeriaIndex > 0"
            @click="galeriaAnterior"
            class="absolute left-2 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 dark:bg-slate-700/80 shadow flex items-center justify-center hover:bg-white dark:hover:bg-slate-600 transition"
          >
            <i class="pi pi-chevron-left text-gray-700 dark:text-gray-200"></i>
          </button>
          <button
            v-if="galeriaIndex < galeriaFotos.length - 1"
            @click="galeriaProxima"
            class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/80 dark:bg-slate-700/80 shadow flex items-center justify-center hover:bg-white dark:hover:bg-slate-600 transition"
          >
            <i class="pi pi-chevron-right text-gray-700 dark:text-gray-200"></i>
          </button>
        </div>

        <!-- Contador -->
        <div class="text-sm text-gray-500 dark:text-gray-400">
          {{ galeriaIndex + 1 }} / {{ galeriaFotos.length }}
          <span class="ml-2 text-xs text-gray-400">{{ galeriaFotos[galeriaIndex].arquivo_nome }}</span>
        </div>

        <!-- Thumbnails -->
        <div v-if="galeriaFotos.length > 1" class="flex items-center gap-2 overflow-x-auto pb-2">
          <img
            v-for="(foto, idx) in galeriaFotos"
            :key="foto.id"
            :src="'/storage/' + foto.arquivo_path"
            :alt="foto.arquivo_nome"
            class="w-14 h-14 rounded-lg object-cover cursor-pointer border-2 transition-all flex-shrink-0"
            :class="idx === galeriaIndex ? 'border-purple-500 shadow-md scale-105' : 'border-gray-200 dark:border-slate-600 opacity-60 hover:opacity-100'"
            @click="galeriaIndex = idx"
          />
        </div>
      </div>
      <template #footer>
        <div class="flex justify-between items-center w-full">
          <a
            :href="'/v2/gestao-contratos/equipamento-fotos/' + galeriaFotos[galeriaIndex]?.id + '/download'"
            target="_blank"
            class="inline-flex items-center gap-1 text-sm text-purple-600 dark:text-purple-400 hover:underline"
          >
            <i class="pi pi-download text-xs"></i> Download
          </a>
          <Button label="Fechar" severity="secondary" outlined size="small" @click="showGaleria = false" />
        </div>
      </template>
    </Dialog>

    <!-- Dialog Confirmar Exclusão -->
    <Dialog
      v-model:visible="showConfirmDelete"
      modal
      header="Excluir Equipamento"
      :style="{ width: '400px' }"
      :closable="true"
    >
      <div class="flex flex-col items-center gap-4 py-4">
        <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/30">
          <i class="pi pi-trash text-3xl text-red-500"></i>
        </span>
        <p class="text-center text-gray-600 dark:text-gray-400">
          Tem certeza que deseja excluir o equipamento
          <strong class="text-gray-900 dark:text-white">{{ equipParaExcluir?.numero_identificacao || '#' + equipParaExcluir?.id }}</strong>?
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500 text-center">Esta ação não pode ser desfeita.</p>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <Button label="Cancelar" severity="secondary" outlined size="small" @click="showConfirmDelete = false" />
          <Button label="Excluir" severity="danger" size="small" icon="pi pi-trash" @click="excluirEquipamento" :loading="EquipJs.loading.value" />
        </div>
      </template>
    </Dialog>
    </template>
  </AuthenticatedLayout>
</template>
