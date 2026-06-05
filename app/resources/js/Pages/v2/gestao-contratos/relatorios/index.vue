<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, ref, computed } from "vue"
import * as Recorrentes from "../contratos-recorrentes.js"
import Panel from "primevue/panel"
import Button from "primevue/button"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Tag from "primevue/tag"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO LOCAL                           ║
// ╚══════════════════════════════════════════════════════════════╝
const abaAtiva = ref("medicoes")

// Filtros Medições
const filtroMedContrato = ref(null)
const filtroMedEtapa = ref(null)
const filtroMedCompInicio = ref("")
const filtroMedCompFim = ref("")

// Filtros Renovações
const filtroRenContrato = ref(null)
const filtroRenStatus = ref(null)
const filtroRenDataInicio = ref("")
const filtroRenDataFim = ref("")

// Filtros Provisão x Realizado
const filtroPrvCompInicio = ref("")
const filtroPrvCompFim = ref("")

// Filtros Contratos a Vencer
const filtroVencerDias = ref(90)

// Filtros SLA
const filtroSlaCompInicio = ref("")
const filtroSlaCompFim = ref("")

// Opções
const opcoesEtapa = ref([
  { label: "Todas", value: "" },
  { label: "Pendente Envio", value: "PENDENTE_ENVIO" },
  { label: "Enviada", value: "ENVIADA" },
  { label: "Conferida", value: "CONFERIDA" },
  { label: "Aprovada", value: "APROVADA" },
  { label: "Divergência", value: "DIVERGENCIA" }
])

const opcoesStatusRen = ref([
  { label: "Todos", value: "" },
  { label: "Aprovada", value: "APROVADA" },
  { label: "Pendente Compras", value: "PENDENTE_COMPRAS" },
  { label: "Rejeitada", value: "REJEITADA" }
])

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)
  await Recorrentes.getContratosRecorrentes()
})

const opcoesContratos = computed(() => {
  const contratos = Recorrentes.contratosRecorrentes.value || []
  return [
    { label: "Todos", value: "" },
    ...contratos.map((c) => ({
      label: c.razao_social_loja || c.nome_locador || `Contrato #${c.id}`,
      value: c.id
    }))
  ]
})

const relMedicoes = computed(() => Recorrentes.relatorioMedicoes.value?.data)
const relRenovacoes = computed(
  () => Recorrentes.relatorioRenovacoes.value?.data
)
const relContratosStatus = computed(
  () => Recorrentes.relatorioContratosStatus.value
)
const relProvisaoRealizado = computed(
  () => Recorrentes.relatorioProvisaoRealizado.value
)
const relContratosVencer = computed(
  () => Recorrentes.relatorioContratosVencer.value
)
const relSla = computed(() => Recorrentes.relatorioSla.value)

async function buscarRelatorioMedicoes() {
  const filtros = {}
  if (filtroMedContrato.value) filtros.contrato_id = filtroMedContrato.value
  if (filtroMedEtapa.value) filtros.etapa = filtroMedEtapa.value
  if (filtroMedCompInicio.value)
    filtros.competencia_inicio = filtroMedCompInicio.value
  if (filtroMedCompFim.value) filtros.competencia_fim = filtroMedCompFim.value
  await Recorrentes.getRelatorioMedicoes(filtros)
}

async function buscarRelatorioRenovacoes() {
  const filtros = {}
  if (filtroRenContrato.value) filtros.contrato_id = filtroRenContrato.value
  if (filtroRenStatus.value) filtros.status = filtroRenStatus.value
  if (filtroRenDataInicio.value) filtros.data_inicio = filtroRenDataInicio.value
  if (filtroRenDataFim.value) filtros.data_fim = filtroRenDataFim.value
  await Recorrentes.getRelatorioRenovacoes(filtros)
}

function limparFiltrosMedicoes() {
  filtroMedContrato.value = null
  filtroMedEtapa.value = null
  filtroMedCompInicio.value = ""
  filtroMedCompFim.value = ""
}

function limparFiltrosRenovacoes() {
  filtroRenContrato.value = null
  filtroRenStatus.value = null
  filtroRenDataInicio.value = ""
  filtroRenDataFim.value = ""
}

async function buscarContratosStatus() {
  await Recorrentes.getRelatorioContratosStatus()
}

async function buscarProvisaoRealizado() {
  const filtros = {}
  if (filtroPrvCompInicio.value)
    filtros.competencia_inicio = filtroPrvCompInicio.value
  if (filtroPrvCompFim.value) filtros.competencia_fim = filtroPrvCompFim.value
  await Recorrentes.getRelatorioProvisaoRealizado(filtros)
}

async function buscarContratosVencer() {
  await Recorrentes.getRelatorioContratosVencer({
    dias: filtroVencerDias.value
  })
}

async function buscarSla() {
  const filtros = {}
  if (filtroSlaCompInicio.value)
    filtros.competencia_inicio = filtroSlaCompInicio.value
  if (filtroSlaCompFim.value) filtros.competencia_fim = filtroSlaCompFim.value
  await Recorrentes.getRelatorioSla(filtros)
}

function exportarMedicoes() {
  const filtros = {}
  if (filtroMedContrato.value) filtros.contrato_id = filtroMedContrato.value
  if (filtroMedEtapa.value) filtros.etapa = filtroMedEtapa.value
  if (filtroMedCompInicio.value)
    filtros.competencia_inicio = filtroMedCompInicio.value
  if (filtroMedCompFim.value) filtros.competencia_fim = filtroMedCompFim.value
  Recorrentes.exportarMedicoes(filtros)
}

function formatarEtapa(etapa) {
  const labels = {
    PENDENTE_ENVIO: "Pendente Envio",
    ENVIADA: "Enviada",
    CONFERIDA: "Conferida",
    APROVADA: "Aprovada",
    DIVERGENCIA: "Divergência",
    ENTRADA_NOTA: "Entrada Nota",
    PENDENTE: "Pendente",
    PAGO: "Pago",
    CANCELADA: "Cancelada"
  }
  return labels[etapa] || (etapa ? etapa.replace(/_/g, " ") : "-")
}

function getEtapaSeverity(etapa) {
  const map = {
    PENDENTE: "warn",
    PENDENTE_ENVIO: "warn",
    ENVIADA: "info",
    ENTRADA_NOTA: "info",
    FINANCEIRO: "info",
    CONFERIDA: "secondary",
    APROVADA: "success",
    PAGO: "success",
    DIVERGENCIA: "danger",
    CANCELADA: "danger"
  }
  return map[etapa] || "secondary"
}

function getStatusSeverity(status) {
  const map = {
    APROVADA: "success",
    PENDENTE_COMPRAS: "warn",
    REJEITADA: "danger"
  }
  return map[status] || "secondary"
}
</script>

<template>
  <AuthenticatedLayout>
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
          <a
            href="/pagina/gestao-contratos"
            class="hover:text-purple-600 dark:hover:text-purple-400"
          >
            Gestão de Contratos
          </a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">
            Relatórios
          </span>
        </div>
      </div>
    </div>

    <!-- Page Header -->
    <div class="space-y-2 mt-4 mb-6">
      <h2
        class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
      >
        <div
          class="w-1 h-8 bg-gradient-to-b from-purple-500 to-purple-700 rounded-full"
        ></div>
        Relatórios - Contratos Recorrentes
      </h2>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Relatórios analíticos de medições e renovações dos contratos
        recorrentes.
      </p>
    </div>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2 mb-6">
      <Button
        label="Medições"
        icon="pi pi-list"
        :severity="abaAtiva === 'medicoes' ? 'info' : 'secondary'"
        :outlined="abaAtiva !== 'medicoes'"
        @click="abaAtiva = 'medicoes'"
      />
      <Button
        label="Renovações"
        icon="pi pi-refresh"
        :severity="abaAtiva === 'renovacoes' ? 'success' : 'secondary'"
        :outlined="abaAtiva !== 'renovacoes'"
        @click="abaAtiva = 'renovacoes'"
      />
      <Button
        label="Contratos por Status"
        icon="pi pi-chart-bar"
        :severity="abaAtiva === 'contratos_status' ? 'warn' : 'secondary'"
        :outlined="abaAtiva !== 'contratos_status'"
        @click="abaAtiva = 'contratos_status'"
      />
      <Button
        label="Provisão × Realizado"
        icon="pi pi-chart-line"
        :severity="abaAtiva === 'provisao_realizado' ? 'help' : 'secondary'"
        :outlined="abaAtiva !== 'provisao_realizado'"
        @click="abaAtiva = 'provisao_realizado'"
      />
      <Button
        label="Contratos a Vencer"
        icon="pi pi-clock"
        :severity="abaAtiva === 'contratos_vencer' ? 'danger' : 'secondary'"
        :outlined="abaAtiva !== 'contratos_vencer'"
        @click="abaAtiva = 'contratos_vencer'"
      />
      <Button
        label="SLA Processamento"
        icon="pi pi-stopwatch"
        :severity="abaAtiva === 'sla' ? 'contrast' : 'secondary'"
        :outlined="abaAtiva !== 'sla'"
        @click="abaAtiva = 'sla'"
      />
    </div>

    <!-- ═══════ ABA MEDIÇÕES ═══════ -->
    <div
      v-if="abaAtiva === 'medicoes'"
      class="space-y-6"
    >
      <!-- Filtros -->
      <Panel
        toggleable
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
      >
        <template #header>
          <div class="flex items-center gap-2 mb-2">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-purple-200 dark:bg-purple-900 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-filter text-purple-700 dark:text-purple-300 !text-xl"
              ></i>
            </span>
            <div>
              <h3
                class="text-2xl font-extrabold text-black-800 dark:text-white"
              >
                Filtros - Medições
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Defina os critérios para gerar o relatório de medições.
              </div>
            </div>
          </div>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Contrato
            </label>
            <Select
              v-model="filtroMedContrato"
              :options="opcoesContratos"
              optionLabel="label"
              optionValue="value"
              placeholder="Todos"
              class="w-full h-10"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Etapa
            </label>
            <Select
              v-model="filtroMedEtapa"
              :options="opcoesEtapa"
              optionLabel="label"
              optionValue="value"
              placeholder="Todas"
              class="w-full h-10"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Competência Início
            </label>
            <DatePicker
              v-model="filtroMedCompInicio"
              view="month"
              dateFormat="mm/yy"
              placeholder="Selecione"
              class="w-full"
              fluid
              showIcon
              iconDisplay="input"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Competência Fim
            </label>
            <DatePicker
              v-model="filtroMedCompFim"
              view="month"
              dateFormat="mm/yy"
              placeholder="Selecione"
              class="w-full"
              fluid
              showIcon
              iconDisplay="input"
            />
          </div>
        </div>

        <div class="flex items-center gap-3 justify-end pt-2">
          <Button
            label="Limpar"
            icon="pi pi-times"
            severity="secondary"
            outlined
            @click="limparFiltrosMedicoes"
          />
          <Button
            label="Buscar"
            icon="pi pi-search"
            severity="info"
            @click="buscarRelatorioMedicoes"
            :loading="Recorrentes.loading.value"
          />
        </div>
      </Panel>

      <!-- Resumo -->
      <div
        v-if="relMedicoes && relMedicoes.resumo"
        class="grid grid-cols-2 sm:grid-cols-4 gap-3"
      >
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-blue-500"
            >
              <i class="pi pi-list !text-[11px] flex-shrink-0"></i>
              Total Medições
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ relMedicoes.resumo.total || 0 }}</span>
          </div>
        </div>
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-indigo-500"
            >
              <i class="pi pi-dollar !text-[11px] flex-shrink-0"></i>
              Valor Previsto
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>
              {{ Recorrentes.formatarMoeda(relMedicoes.resumo.valor_previsto) }}
            </span>
          </div>
        </div>
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-green-500"
            >
              <i class="pi pi-dollar !text-[11px] flex-shrink-0"></i>
              Valor Real
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>
              {{ Recorrentes.formatarMoeda(relMedicoes.resumo.valor_real) }}
            </span>
          </div>
        </div>
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-red-600"
            >
              <i
                class="pi pi-exclamation-triangle !text-[11px] flex-shrink-0"
              ></i>
              Divergências
            </span>
          </div>
          <div
            class="flex w-full justify-start text-lg font-bold"
            :class="
              (relMedicoes.resumo.divergencias || 0) > 0
                ? 'text-red-600'
                : 'text-gray-950 dark:text-white'
            "
          >
            <span>{{ relMedicoes.resumo.divergencias || 0 }}</span>
          </div>
        </div>
      </div>

      <!-- Tabela Medições -->
      <div
        class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-200 dark:border-slate-700 shadow-md overflow-hidden"
      >
        <div
          class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-slate-700"
        >
          <div class="flex items-center gap-3">
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-200 dark:bg-blue-900 shadow flex-shrink-0"
            >
              <i class="pi pi-list text-blue-700 dark:text-blue-300"></i>
            </span>
            <div>
              <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                Resultado
              </h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ relMedicoes?.dados?.length || 0 }} registro(s) encontrado(s)
              </p>
            </div>
          </div>
          <Button
            label="Exportar Excel"
            icon="pi pi-file-excel"
            severity="success"
            outlined
            @click="exportarMedicoes"
            :disabled="
              !relMedicoes ||
              !relMedicoes.dados ||
              relMedicoes.dados.length === 0
            "
          />
        </div>

        <DataTable
          :value="relMedicoes?.dados || []"
          :paginator="true"
          :rows="15"
          :rowsPerPageOptions="[10, 15, 25, 50]"
          stripedRows
          class="p-datatable-sm"
          :loading="Recorrentes.loading.value"
          :pt="{ root: { class: 'rounded-xl overflow-hidden' } }"
        >
          <template #loading>
            <div class="flex items-center gap-2 justify-center py-6">
              <i
                class="pi pi-spinner pi-spin text-blue-600 dark:text-blue-400"
              ></i>
              <span class="text-sm text-gray-500 dark:text-gray-400">
                Buscando relatório...
              </span>
            </div>
          </template>
          <template #empty>
            <div class="flex flex-col items-center justify-center py-8">
              <i
                class="pi pi-search !text-2xl text-gray-300 dark:text-gray-600 mb-2"
              ></i>
              <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">
                Aplique os filtros e clique em "Buscar" para gerar o relatório.
              </p>
            </div>
          </template>
          <Column
            field="contrato"
            header="Contrato"
          >
            <template #body="{ data }">
              <span class="font-medium text-gray-900 dark:text-white">
                {{
                  data.razao_social_loja ||
                  data.nome_locador ||
                  "#" + data.contrato_id
                }}
              </span>
            </template>
          </Column>
          <Column
            field="competencia"
            header="Competência"
          >
            <template #body="{ data }">
              <span class="text-gray-700 dark:text-gray-300">
                {{ data.competencia }}
              </span>
            </template>
          </Column>
          <Column
            field="valor_previsto"
            header="Previsto"
          >
            <template #body="{ data }">
              <span class="text-gray-700 dark:text-gray-300">
                {{ Recorrentes.formatarMoeda(data.valor_previsto) }}
              </span>
            </template>
          </Column>
          <Column
            field="valor_real"
            header="Real"
          >
            <template #body="{ data }">
              <span
                class="font-bold"
                :class="
                  data.valor_real
                    ? 'text-green-600 dark:text-green-400'
                    : 'text-gray-400'
                "
              >
                {{
                  data.valor_real
                    ? Recorrentes.formatarMoeda(data.valor_real)
                    : "-"
                }}
              </span>
            </template>
          </Column>
          <Column
            field="etapa"
            header="Etapa"
          >
            <template #body="{ data }">
              <Tag
                :value="formatarEtapa(data.etapa)"
                :severity="getEtapaSeverity(data.etapa)"
                class="font-medium"
              />
            </template>
          </Column>
          <Column
            field="divergencia"
            header="Divergência"
          >
            <template #body="{ data }">
              <Tag
                v-if="data.tem_divergencia"
                value="Sim"
                severity="danger"
                class="font-medium"
              />
              <span
                v-else
                class="text-gray-400 text-sm"
              >
                Não
              </span>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- ═══════ ABA RENOVAÇÕES ═══════ -->
    <div
      v-if="abaAtiva === 'renovacoes'"
      class="space-y-6"
    >
      <!-- Filtros -->
      <Panel
        toggleable
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
      >
        <template #header>
          <div class="flex items-center gap-2 mb-2">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-200 dark:bg-green-900 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-filter text-green-700 dark:text-green-300 !text-xl"
              ></i>
            </span>
            <div>
              <h3
                class="text-2xl font-extrabold text-black-800 dark:text-white"
              >
                Filtros - Renovações
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Defina os critérios para gerar o relatório de renovações.
              </div>
            </div>
          </div>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Contrato
            </label>
            <Select
              v-model="filtroRenContrato"
              :options="opcoesContratos"
              optionLabel="label"
              optionValue="value"
              placeholder="Todos"
              class="w-full h-10"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Status
            </label>
            <Select
              v-model="filtroRenStatus"
              :options="opcoesStatusRen"
              optionLabel="label"
              optionValue="value"
              placeholder="Todos"
              class="w-full h-10"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Data Início
            </label>
            <DatePicker
              v-model="filtroRenDataInicio"
              dateFormat="dd/mm/yy"
              placeholder="dd/mm/aaaa"
              class="w-full"
              fluid
              showIcon
              iconDisplay="input"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Data Fim
            </label>
            <DatePicker
              v-model="filtroRenDataFim"
              dateFormat="dd/mm/yy"
              placeholder="dd/mm/aaaa"
              class="w-full"
              fluid
              showIcon
              iconDisplay="input"
            />
          </div>
        </div>

        <div class="flex items-center gap-3 justify-end pt-2">
          <Button
            label="Limpar"
            icon="pi pi-times"
            severity="secondary"
            outlined
            @click="limparFiltrosRenovacoes"
          />
          <Button
            label="Buscar"
            icon="pi pi-search"
            severity="success"
            @click="buscarRelatorioRenovacoes"
            :loading="Recorrentes.loading.value"
          />
        </div>
      </Panel>

      <!-- Resumo -->
      <div
        v-if="relRenovacoes && relRenovacoes.resumo"
        class="grid grid-cols-2 sm:grid-cols-4 gap-3"
      >
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-green-500"
            >
              <i class="pi pi-refresh !text-[11px] flex-shrink-0"></i>
              Total Renovações
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ relRenovacoes.resumo.total || 0 }}</span>
          </div>
        </div>
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-emerald-500"
            >
              <i class="pi pi-check-circle !text-[11px] flex-shrink-0"></i>
              Aprovadas
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ relRenovacoes.resumo.aprovadas || 0 }}</span>
          </div>
        </div>
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-yellow-400"
            >
              <i class="pi pi-clock !text-[11px] flex-shrink-0"></i>
              Pend. Compras
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ relRenovacoes.resumo.pendentes_compras || 0 }}</span>
          </div>
        </div>
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-purple-500"
            >
              <i class="pi pi-percentage !text-[11px] flex-shrink-0"></i>
              Variação Média
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ relRenovacoes.resumo.variacao_media || "0.00" }}%</span>
          </div>
        </div>
      </div>

      <!-- Tabela Renovações -->
      <div
        class="bg-white dark:bg-slate-800 rounded-3xl border border-gray-200 dark:border-slate-700 shadow-md overflow-hidden"
      >
        <div
          class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-slate-700"
        >
          <div class="flex items-center gap-3">
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-green-200 dark:bg-green-900 shadow flex-shrink-0"
            >
              <i class="pi pi-refresh text-green-700 dark:text-green-300"></i>
            </span>
            <div>
              <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                Resultado
              </h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ relRenovacoes?.dados?.length || 0 }} registro(s)
                encontrado(s)
              </p>
            </div>
          </div>
        </div>

        <DataTable
          :value="relRenovacoes?.dados || []"
          :paginator="true"
          :rows="15"
          :rowsPerPageOptions="[10, 15, 25, 50]"
          stripedRows
          class="p-datatable-sm"
          :loading="Recorrentes.loading.value"
          :pt="{ root: { class: 'rounded-xl overflow-hidden' } }"
        >
          <template #loading>
            <div class="flex items-center gap-2 justify-center py-6">
              <i
                class="pi pi-spinner pi-spin text-green-600 dark:text-green-400"
              ></i>
              <span class="text-sm text-gray-500 dark:text-gray-400">
                Buscando relatório...
              </span>
            </div>
          </template>
          <template #empty>
            <div class="flex flex-col items-center justify-center py-8">
              <i
                class="pi pi-search !text-2xl text-gray-300 dark:text-gray-600 mb-2"
              ></i>
              <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">
                Aplique os filtros e clique em "Buscar" para gerar o relatório.
              </p>
            </div>
          </template>
          <Column
            field="contrato"
            header="Contrato"
          >
            <template #body="{ data }">
              <span class="font-medium text-gray-900 dark:text-white">
                {{
                  data.razao_social_loja ||
                  data.nome_locador ||
                  "#" + data.contrato_id
                }}
              </span>
            </template>
          </Column>
          <Column
            field="valor_anterior"
            header="Valor Anterior"
          >
            <template #body="{ data }">
              <span class="text-gray-700 dark:text-gray-300">
                {{ Recorrentes.formatarMoeda(data.valor_anterior) }}
              </span>
            </template>
          </Column>
          <Column
            field="valor_novo"
            header="Valor Novo"
          >
            <template #body="{ data }">
              <span class="font-bold text-blue-600 dark:text-blue-400">
                {{ Recorrentes.formatarMoeda(data.valor_novo) }}
              </span>
            </template>
          </Column>
          <Column
            field="percentual_variacao"
            header="Variação"
          >
            <template #body="{ data }">
              <span
                class="font-bold"
                :class="
                  parseFloat(data.percentual_variacao) > 0
                    ? 'text-red-600'
                    : 'text-green-600'
                "
              >
                {{ parseFloat(data.percentual_variacao) > 0 ? "+" : ""
                }}{{ parseFloat(data.percentual_variacao).toFixed(2) }}%
              </span>
            </template>
          </Column>
          <Column
            field="teve_divergencia"
            header="Divergência"
          >
            <template #body="{ data }">
              <Tag
                v-if="data.teve_divergencia"
                value="Sim"
                severity="danger"
                class="font-medium"
              />
              <span
                v-else
                class="text-gray-400 text-sm"
              >
                Não
              </span>
            </template>
          </Column>
          <Column
            field="status"
            header="Status"
          >
            <template #body="{ data }">
              <Tag
                :value="data.status"
                :severity="getStatusSeverity(data.status)"
                class="font-medium"
              />
            </template>
          </Column>
          <Column
            field="created_at"
            header="Data"
          >
            <template #body="{ data }">
              <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ Recorrentes.formatarData(data.created_at) }}
              </span>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- ═══════ ABA CONTRATOS POR STATUS ═══════ -->
    <div
      v-if="abaAtiva === 'contratos_status'"
      class="space-y-6"
    >
      <div class="flex items-center gap-2 mb-4">
        <Button
          label="Carregar Relatório"
          icon="pi pi-chart-bar"
          severity="warn"
          outlined
          @click="buscarContratosStatus"
          :loading="relContratosStatus.loading"
        />
      </div>

      <!-- Cards de Totais -->
      <div
        v-if="relContratosStatus.dados"
        class="grid grid-cols-2 md:grid-cols-4 gap-4"
      >
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4"
        >
          <span class="text-xs text-gray-500 block">Total</span>
          <span class="text-2xl font-bold text-gray-900 dark:text-white">
            {{ relContratosStatus.dados.totais.total }}
          </span>
        </div>
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-green-200 dark:border-green-800 p-4"
        >
          <span class="text-xs text-green-600 block">Ativos</span>
          <span class="text-2xl font-bold text-green-600">
            {{ relContratosStatus.dados.totais.ativos }}
          </span>
        </div>
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4"
        >
          <span class="text-xs text-gray-500 block">Inativos</span>
          <span class="text-2xl font-bold text-gray-500">
            {{ relContratosStatus.dados.totais.inativos }}
          </span>
        </div>
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-blue-200 dark:border-blue-800 p-4"
        >
          <span class="text-xs text-blue-600 block">Valor Mensal Total</span>
          <span class="text-xl font-bold text-blue-600">
            {{
              Recorrentes.formatarMoeda(
                relContratosStatus.dados.totais.valor_mensal_total
              )
            }}
          </span>
        </div>
      </div>

      <!-- Tabela por Status -->
      <div
        v-if="relContratosStatus.dados"
        v-for="grupo in relContratosStatus.dados.por_status"
        :key="grupo.status"
        class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-4"
      >
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <Tag
              :value="grupo.status"
              :severity="
                grupo.status === 'ATIVO'
                  ? 'success'
                  : grupo.status === 'INATIVO'
                    ? 'danger'
                    : 'secondary'
              "
              class="font-medium"
            />
            <span class="text-sm text-gray-500">
              ({{ grupo.quantidade }} contratos)
            </span>
          </div>
          <span class="text-sm font-bold text-blue-600">
            {{ Recorrentes.formatarMoeda(grupo.valor_mensal_total) }}/mês
          </span>
        </div>
        <DataTable
          :value="grupo.contratos"
          stripedRows
          showGridlines
          class="text-sm"
          rowHover
        >
          <Column
            field="id"
            header="#"
            style="width: 60px"
          />
          <Column
            field="nome_locador"
            header="Fornecedor"
          />
          <Column
            field="descricao_servico"
            header="Descrição"
          />
          <Column
            field="filial"
            header="Filial"
          />
          <Column
            field="valor_mensal"
            header="Valor Mensal"
          >
            <template #body="{ data }">
              <span class="font-semibold text-green-600">
                {{ Recorrentes.formatarMoeda(data.valor_mensal) }}
              </span>
            </template>
          </Column>
          <Column
            field="data_inicio"
            header="Início"
          />
          <Column
            field="data_fim"
            header="Fim"
          />
        </DataTable>
      </div>

      <div
        v-if="!relContratosStatus.dados && !relContratosStatus.loading"
        class="text-center py-12 text-gray-400"
      >
        Clique em "Carregar Relatório" para visualizar os dados.
      </div>
    </div>

    <!-- ═══════ ABA PROVISÃO × REALIZADO ═══════ -->
    <div
      v-if="abaAtiva === 'provisao_realizado'"
      class="space-y-6"
    >
      <!-- Filtros -->
      <div
        class="bg-white dark:bg-slate-800 rounded-xl p-4 flex flex-wrap items-end gap-4"
      >
        <div class="flex flex-col gap-1">
          <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Competência Início
          </label>
          <DatePicker
            v-model="filtroPrvCompInicio"
            view="month"
            dateFormat="mm/yy"
            placeholder="Início"
            class="w-48"
            fluid
            showIcon
            iconDisplay="input"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Competência Fim
          </label>
          <DatePicker
            v-model="filtroPrvCompFim"
            view="month"
            dateFormat="mm/yy"
            placeholder="Fim"
            class="w-48"
            fluid
            showIcon
            iconDisplay="input"
          />
        </div>
        <Button
          label="Buscar"
          icon="pi pi-search"
          severity="help"
          outlined
          @click="buscarProvisaoRealizado"
          :loading="relProvisaoRealizado.loading"
        />
      </div>

      <!-- Totais -->
      <div
        v-if="relProvisaoRealizado.dados"
        class="grid grid-cols-1 md:grid-cols-3 gap-4"
      >
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-blue-200 dark:border-blue-800 p-4"
        >
          <span class="text-xs text-blue-600 block">Total Previsto</span>
          <span class="text-xl font-bold text-blue-600">
            {{
              Recorrentes.formatarMoeda(
                relProvisaoRealizado.dados.totais.valor_previsto
              )
            }}
          </span>
        </div>
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-green-200 dark:border-green-800 p-4"
        >
          <span class="text-xs text-green-600 block">Total Realizado</span>
          <span class="text-xl font-bold text-green-600">
            {{
              Recorrentes.formatarMoeda(
                relProvisaoRealizado.dados.totais.valor_real
              )
            }}
          </span>
        </div>
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border p-4"
          :class="
            relProvisaoRealizado.dados.totais.variacao_percentual > 0
              ? 'border-red-200 dark:border-red-800'
              : 'border-green-200 dark:border-green-800'
          "
        >
          <span
            class="text-xs block"
            :class="
              relProvisaoRealizado.dados.totais.variacao_percentual > 0
                ? 'text-red-600'
                : 'text-green-600'
            "
          >
            Variação Global
          </span>
          <span
            class="text-xl font-bold"
            :class="
              relProvisaoRealizado.dados.totais.variacao_percentual > 0
                ? 'text-red-600'
                : 'text-green-600'
            "
          >
            {{
              relProvisaoRealizado.dados.totais.variacao_percentual > 0
                ? "+"
                : ""
            }}{{ relProvisaoRealizado.dados.totais.variacao_percentual }}%
          </span>
        </div>
      </div>

      <!-- Tabela por Mês -->
      <div
        v-if="relProvisaoRealizado.dados"
        class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-4"
      >
        <DataTable
          :value="relProvisaoRealizado.dados.por_mes"
          stripedRows
          showGridlines
          class="text-sm"
          rowHover
        >
          <Column
            field="competencia"
            header="Competência"
            sortable
          />
          <Column
            field="valor_previsto"
            header="Previsto"
          >
            <template #body="{ data }">
              <span class="font-semibold text-blue-600">
                {{ Recorrentes.formatarMoeda(data.valor_previsto) }}
              </span>
            </template>
          </Column>
          <Column
            field="valor_real"
            header="Realizado"
          >
            <template #body="{ data }">
              <span class="font-semibold text-green-600">
                {{ Recorrentes.formatarMoeda(data.valor_real) }}
              </span>
            </template>
          </Column>
          <Column
            field="variacao_percentual"
            header="Variação"
          >
            <template #body="{ data }">
              <span
                class="font-bold"
                :class="
                  data.variacao_percentual > 0
                    ? 'text-red-600'
                    : 'text-green-600'
                "
              >
                {{ data.variacao_percentual > 0 ? "+" : ""
                }}{{ data.variacao_percentual }}%
              </span>
            </template>
          </Column>
          <Column
            field="total_medicoes"
            header="Medições"
          />
          <Column
            field="pagas"
            header="Pagas"
          />
        </DataTable>
      </div>

      <div
        v-if="!relProvisaoRealizado.dados && !relProvisaoRealizado.loading"
        class="text-center py-12 text-gray-400"
      >
        Selecione o período e clique em "Buscar".
      </div>
    </div>

    <!-- ═══════ ABA CONTRATOS A VENCER ═══════ -->
    <div
      v-if="abaAtiva === 'contratos_vencer'"
      class="space-y-6"
    >
      <!-- Filtro -->
      <div
        class="bg-white dark:bg-slate-800 rounded-xl p-4 flex flex-wrap items-end gap-4"
      >
        <div class="flex flex-col gap-1">
          <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Período (dias)
          </label>
          <Select
            v-model="filtroVencerDias"
            :options="[
              { label: '30 dias', value: 30 },
              { label: '60 dias', value: 60 },
              { label: '90 dias', value: 90 },
              { label: '180 dias', value: 180 },
              { label: '365 dias', value: 365 }
            ]"
            optionLabel="label"
            optionValue="value"
            class="w-48"
          />
        </div>
        <Button
          label="Buscar"
          icon="pi pi-search"
          severity="danger"
          outlined
          @click="buscarContratosVencer"
          :loading="relContratosVencer.loading"
        />
      </div>

      <!-- Totais -->
      <div
        v-if="relContratosVencer.dados"
        class="grid grid-cols-2 md:grid-cols-4 gap-4"
      >
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-amber-200 dark:border-amber-800 p-4"
        >
          <span class="text-xs text-amber-600 block">Vencendo</span>
          <span class="text-2xl font-bold text-amber-600">
            {{ relContratosVencer.dados.totais.vencendo }}
          </span>
        </div>
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-red-200 dark:border-red-800 p-4"
        >
          <span class="text-xs text-red-600 block">Já Vencidos</span>
          <span class="text-2xl font-bold text-red-600">
            {{ relContratosVencer.dados.totais.vencidos }}
          </span>
        </div>
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-orange-200 dark:border-orange-800 p-4"
        >
          <span class="text-xs text-orange-600 block">
            Urgentes (&lt; 30 dias)
          </span>
          <span class="text-2xl font-bold text-orange-600">
            {{ relContratosVencer.dados.totais.urgentes }}
          </span>
        </div>
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-blue-200 dark:border-blue-800 p-4"
        >
          <span class="text-xs text-blue-600 block">Valor Mensal</span>
          <span class="text-xl font-bold text-blue-600">
            {{
              Recorrentes.formatarMoeda(
                relContratosVencer.dados.totais.valor_mensal_vencendo
              )
            }}
          </span>
        </div>
      </div>

      <!-- Vencidos -->
      <div
        v-if="
          relContratosVencer.dados &&
          relContratosVencer.dados.vencidos.length > 0
        "
        class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-4"
      >
        <h3
          class="text-base font-bold text-red-600 mb-3 flex items-center gap-2"
        >
          <i class="pi pi-exclamation-triangle"></i>
          Contratos Vencidos
        </h3>
        <DataTable
          :value="relContratosVencer.dados.vencidos"
          stripedRows
          showGridlines
          class="text-sm"
          rowHover
        >
          <Column
            field="id"
            header="#"
            style="width: 60px"
          />
          <Column
            field="nome_locador"
            header="Fornecedor"
          />
          <Column
            field="filial"
            header="Filial"
          />
          <Column
            field="valor_mensal"
            header="Valor Mensal"
          >
            <template #body="{ data }">
              <span class="font-semibold text-green-600">
                {{ Recorrentes.formatarMoeda(data.valor_mensal) }}
              </span>
            </template>
          </Column>
          <Column
            field="data_fim"
            header="Vencimento"
          />
          <Column
            field="dias_restantes"
            header="Dias"
          >
            <template #body="{ data }">
              <Tag
                :value="`${Math.abs(data.dias_restantes)} dias atrás`"
                severity="danger"
                class="font-medium"
              />
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- Vencendo -->
      <div
        v-if="
          relContratosVencer.dados &&
          relContratosVencer.dados.vencendo.length > 0
        "
        class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-4"
      >
        <h3
          class="text-base font-bold text-amber-600 mb-3 flex items-center gap-2"
        >
          <i class="pi pi-clock"></i>
          Contratos a Vencer
        </h3>
        <DataTable
          :value="relContratosVencer.dados.vencendo"
          stripedRows
          showGridlines
          class="text-sm"
          rowHover
        >
          <Column
            field="id"
            header="#"
            style="width: 60px"
          />
          <Column
            field="nome_locador"
            header="Fornecedor"
          />
          <Column
            field="filial"
            header="Filial"
          />
          <Column
            field="valor_mensal"
            header="Valor Mensal"
          >
            <template #body="{ data }">
              <span class="font-semibold text-green-600">
                {{ Recorrentes.formatarMoeda(data.valor_mensal) }}
              </span>
            </template>
          </Column>
          <Column
            field="data_fim"
            header="Vencimento"
          />
          <Column
            field="dias_restantes"
            header="Dias Restantes"
          >
            <template #body="{ data }">
              <Tag
                :value="`${data.dias_restantes} dias`"
                :severity="
                  data.faixa === 'URGENTE'
                    ? 'danger'
                    : data.faixa === 'ATENCAO'
                      ? 'warn'
                      : 'success'
                "
                class="font-medium"
              />
            </template>
          </Column>
        </DataTable>
      </div>

      <div
        v-if="!relContratosVencer.dados && !relContratosVencer.loading"
        class="text-center py-12 text-gray-400"
      >
        Clique em "Buscar" para visualizar os contratos a vencer.
      </div>
    </div>

    <!-- ═══════ ABA SLA DE PROCESSAMENTO ═══════ -->
    <div
      v-if="abaAtiva === 'sla'"
      class="space-y-6"
    >
      <!-- Filtros -->
      <div
        class="bg-white dark:bg-slate-800 rounded-xl p-4 flex flex-wrap items-end gap-4"
      >
        <div class="flex flex-col gap-1">
          <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Competência Início
          </label>
          <DatePicker
            v-model="filtroSlaCompInicio"
            view="month"
            dateFormat="mm/yy"
            placeholder="Início"
            class="w-48"
            fluid
            showIcon
            iconDisplay="input"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Competência Fim
          </label>
          <DatePicker
            v-model="filtroSlaCompFim"
            view="month"
            dateFormat="mm/yy"
            placeholder="Fim"
            class="w-48"
            fluid
            showIcon
            iconDisplay="input"
          />
        </div>
        <Button
          label="Buscar"
          icon="pi pi-search"
          severity="contrast"
          outlined
          @click="buscarSla"
          :loading="relSla.loading"
        />
      </div>

      <!-- Cards de Médias -->
      <div
        v-if="relSla.dados"
        class="grid grid-cols-2 md:grid-cols-4 gap-4"
      >
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-blue-200 dark:border-blue-800 p-4"
        >
          <span class="text-xs text-blue-600 block">Envio → Entrada Nota</span>
          <span class="text-2xl font-bold text-blue-600">
            {{ relSla.dados.medias.envio_entrada ?? "-" }}
            <span class="text-sm font-normal">dias</span>
          </span>
        </div>
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-purple-200 dark:border-purple-800 p-4"
        >
          <span class="text-xs text-purple-600 block">
            Entrada Nota → Financeiro
          </span>
          <span class="text-2xl font-bold text-purple-600">
            {{ relSla.dados.medias.entrada_financeiro ?? "-" }}
            <span class="text-sm font-normal">dias</span>
          </span>
        </div>
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-emerald-200 dark:border-emerald-800 p-4"
        >
          <span class="text-xs text-emerald-600 block">
            Financeiro → Pagamento
          </span>
          <span class="text-2xl font-bold text-emerald-600">
            {{ relSla.dados.medias.financeiro_pagamento ?? "-" }}
            <span class="text-sm font-normal">dias</span>
          </span>
        </div>
        <div
          class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4"
        >
          <span class="text-xs text-gray-600 block">Tempo Total Médio</span>
          <span class="text-2xl font-bold text-gray-900 dark:text-white">
            {{ relSla.dados.medias.total ?? "-" }}
            <span class="text-sm font-normal">dias</span>
          </span>
        </div>
      </div>

      <!-- Tabela SLA -->
      <div
        v-if="relSla.dados"
        class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-4"
      >
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm text-gray-500">
            {{ relSla.dados.total_analisadas }} medições analisadas ·
            {{ relSla.dados.total_completas }} fluxo completo
          </span>
        </div>
        <DataTable
          :value="relSla.dados.medicoes"
          stripedRows
          showGridlines
          class="text-sm"
          rowHover
          paginator
          :rows="20"
        >
          <Column
            field="id"
            header="#"
            style="width: 60px"
            sortable
          />
          <Column
            field="contrato_nome"
            header="Fornecedor"
          />
          <Column
            field="competencia"
            header="Competência"
            sortable
          />
          <Column
            field="etapa"
            header="Etapa Atual"
          >
            <template #body="{ data }">
              <Tag
                :value="formatarEtapa(data.etapa)"
                :severity="getEtapaSeverity(data.etapa)"
                class="font-medium"
              />
            </template>
          </Column>
          <Column
            field="dias_envio_entrada"
            header="Envio→Entrada"
            sortable
          >
            <template #body="{ data }">
              <span
                :class="
                  data.dias_envio_entrada > 5
                    ? 'text-red-600 font-bold'
                    : 'text-gray-700 dark:text-gray-300'
                "
              >
                {{ data.dias_envio_entrada ?? "-" }}
              </span>
            </template>
          </Column>
          <Column
            field="dias_entrada_financeiro"
            header="Entrada→Financ."
            sortable
          >
            <template #body="{ data }">
              <span
                :class="
                  data.dias_entrada_financeiro > 5
                    ? 'text-red-600 font-bold'
                    : 'text-gray-700 dark:text-gray-300'
                "
              >
                {{ data.dias_entrada_financeiro ?? "-" }}
              </span>
            </template>
          </Column>
          <Column
            field="dias_financeiro_pagamento"
            header="Financ.→Pago"
            sortable
          >
            <template #body="{ data }">
              <span
                :class="
                  data.dias_financeiro_pagamento > 10
                    ? 'text-red-600 font-bold'
                    : 'text-gray-700 dark:text-gray-300'
                "
              >
                {{ data.dias_financeiro_pagamento ?? "-" }}
              </span>
            </template>
          </Column>
          <Column
            field="dias_total"
            header="Total"
            sortable
          >
            <template #body="{ data }">
              <span
                class="font-bold"
                :class="
                  data.dias_total > 20 ? 'text-red-600' : 'text-green-600'
                "
              >
                {{ data.dias_total ?? "-" }}
              </span>
            </template>
          </Column>
        </DataTable>
      </div>

      <div
        v-if="!relSla.dados && !relSla.loading"
        class="text-center py-12 text-gray-400"
      >
        Selecione o período e clique em "Buscar".
      </div>
    </div>
  </AuthenticatedLayout>
</template>
