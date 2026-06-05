<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║  Entrada de Nota – Tela dedicada (Alexandre)                ║
// ║  Filtra medições na etapa ENVIADA e ENTRADA_NOTA            ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, ref, computed } from "vue"
import * as Recorrentes from "../contratos-recorrentes.js"
import { swalConfirm, swalInput } from "@/utils/globalFunctions.js"
import Panel from "primevue/panel"
import Button from "primevue/button"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Tag from "primevue/tag"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO LOCAL                           ║
// ╚══════════════════════════════════════════════════════════════╝
const etapaFiltro = ref("")
const etapasOptions = [
  { label: "Todas (Entrada)", value: "" },
  { label: "NF/Boleto Enviado", value: "ENVIADA" },
  { label: "Entrada de Nota", value: "ENTRADA_NOTA" }
]

const medicoesFiltradas = computed(() => {
  const data = Recorrentes.medicoes.value.data || []
  return data.filter((m) => {
    if (etapaFiltro.value) return m.etapa === etapaFiltro.value
    return m.etapa === "ENVIADA" || m.etapa === "ENTRADA_NOTA"
  })
})

const contratosFormatados = computed(() => {
  return [
    { id: "", label: "Todos" },
    ...Recorrentes.contratosRecorrentes.value.map((c) => ({
      ...c,
      label: `#${c.id} - ${c.razao_social_loja || c.nome_locador || "Contrato"}`
    }))
  ]
})

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)
  // Carrega todas as medições (filtro livre) e contratos dropdown
  await Promise.all([Recorrentes.getContratosRecorrentes(), buscar()])
})

async function buscar() {
  // Busca medições sem filtro de etapa fixo — filtramos no computed
  Recorrentes.medicoes.value.filtros.etapa = ""
  await Recorrentes.getMedicoes(1)
}

function limparFiltros() {
  etapaFiltro.value = ""
  Recorrentes.medicoes.value.filtros = {
    contrato_id: "",
    competencia: "",
    etapa: "",
    alerta: ""
  }
  buscar()
}

async function aceitarNota(medicao) {
  const result = await swalConfirm(
    "Confirmar Entrada de Nota",
    `Confirmar entrada de nota da medição #${medicao.id} (${medicao.competencia_formatada || medicao.competencia})?`,
    "Confirmar Entrada",
    "Cancelar",
    { icon: "question" }
  )
  if (!result.isConfirmed) return
  await Recorrentes.movimentarMedicao(medicao.id, "entrada_nota")
  buscar()
}

async function devolverNota(medicao) {
  const result = await swalInput(
    "Devolver para Fornecedor",
    `Informe o motivo da devolução da medição #${medicao.id}:`,
    "Ex: NF com dados incorretos...",
    "Devolver",
    "Cancelar",
    {
      icon: "warning",
      danger: true,
      inputType: "textarea",
      required: true,
      requiredMessage: "O comentário é obrigatório para devoluções."
    }
  )
  if (!result.isConfirmed || !result.value) return
  await Recorrentes.movimentarMedicao(
    medicao.id,
    "voltar_enviada",
    result.value
  )
  buscar()
}

async function encaminharFinanceiro(medicao) {
  const result = await swalConfirm(
    "Encaminhar ao Financeiro",
    `Encaminhar medição #${medicao.id} para o Financeiro?`,
    "Encaminhar",
    "Cancelar",
    { icon: "question" }
  )
  if (!result.isConfirmed) return
  await Recorrentes.movimentarMedicao(medicao.id, "financeiro")
  buscar()
}

function getEtapaSeverity(etapa) {
  const severities = {
    PENDENTE: "warn",
    ENVIADA: "info",
    ENTRADA_NOTA: "warn",
    FINANCEIRO: "info",
    PAGO: "success"
  }
  return severities[etapa] || "secondary"
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
            class="hover:text-blue-600 dark:hover:text-blue-400"
          >
            Gestão de Contratos
          </a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">
            Entrada de Nota
          </span>
        </div>
      </div>
    </div>

    <!-- Cabeçalho da Página -->
    <div class="space-y-2 mb-6 mt-4">
      <div
        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
      >
        <div>
          <div class="flex items-center gap-3">
            <h2
              class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
            >
              <div
                class="w-1 h-8 bg-gradient-to-b from-purple-500 to-purple-700 rounded-full"
              ></div>
              Entrada de Nota – Medições
            </h2>
          </div>
          <span
            class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-bold pl-4 mt-1"
          >
            Receba e valide as NFs/Boletos enviados pelos fornecedores. Aceite
            ou devolva para correção.
          </span>
        </div>
      </div>
    </div>

    <!-- Filtros -->
    <Panel
      header="Filtros"
      toggleable
      :collapsed="false"
      class="mb-6 bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
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
            <h3 class="text-2xl font-extrabold text-black-800 dark:text-white">
              Filtros
            </h3>
            <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
              Filtre as medições aguardando entrada de nota.
            </div>
          </div>
        </div>
      </template>

      <div class="flex flex-col gap-4 w-full">
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 items-end gap-4 w-full"
        >
          <!-- Contrato -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Contrato
            </label>
            <Select
              v-model="Recorrentes.medicoes.value.filtros.contrato_id"
              :options="contratosFormatados"
              optionLabel="label"
              optionValue="id"
              placeholder="Todos"
              class="w-full h-10"
            />
          </div>

          <!-- Competência -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Competência
            </label>
            <DatePicker
              v-model="Recorrentes.medicoes.value.filtros.competencia"
              view="month"
              dateFormat="mm/yy"
              placeholder="Selecione"
              class="w-full"
              fluid
              showIcon
              iconDisplay="input"
            />
          </div>

          <!-- Sub-Etapa -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Status
            </label>
            <Select
              v-model="etapaFiltro"
              :options="etapasOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Todas"
              class="w-full h-10"
            />
          </div>

          <!-- Botões -->
          <div class="flex gap-2 w-full sm:w-auto justify-end items-end">
            <Button
              label="Buscar"
              icon="pi pi-search"
              severity="info"
              outlined
              @click="buscar"
              :loading="Recorrentes.medicoes.value.loading"
              class="flex-1 sm:flex-none"
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

    <!-- Tabela -->
    <div
      class="bg-white dark:bg-slate-800 rounded-3xl p-4 sm:p-6 relative overflow-hidden"
    >
      <div
        class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6"
      >
        <div class="flex items-center gap-3 flex-1 min-w-0">
          <span
            class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-purple-200 dark:bg-purple-900/30 shadow-lg flex-shrink-0"
          >
            <i
              class="pi pi-file-import !text-xl text-purple-700 dark:text-purple-400"
            ></i>
          </span>
          <div>
            <h2
              class="text-xl sm:text-xl md:text-2xl font-extrabold text-black-800 dark:text-white drop-shadow truncate"
            >
              Notas a Processar
              <span
                class="text-base font-normal text-gray-500 dark:text-gray-400 ml-2"
              >
                ({{ medicoesFiltradas.length }} registro{{
                  medicoesFiltradas.length !== 1 ? "s" : ""
                }})
              </span>
            </h2>
            <div
              class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 font-medium mt-1"
            >
              Medições com NF enviada aguardando entrada / processamento
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm">
        <DataTable
          :value="medicoesFiltradas"
          :loading="Recorrentes.medicoes.value.loading"
          paginator
          :rows="20"
          :rowsPerPageOptions="[10, 20, 50, 100]"
          paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
          currentPageReportTemplate="Mostrando {first} a {last} de {totalRecords}"
          sortMode="multiple"
          removableSort
          stripedRows
          showGridlines
          class="min-w-full text-sm"
          rowHover
          :rowClass="
            (data) =>
              data.alerta_divergencia ? 'bg-red-50 dark:bg-red-900/20' : ''
          "
        >
          <template #loading>
            <div
              class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-full shadow-md text-sm"
            >
              <i class="pi pi-spinner pi-spin text-xs"></i>
              <span class="font-medium">Carregando...</span>
            </div>
          </template>

          <template #empty>
            <div class="py-12 text-center">
              <span
                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 mb-4"
              >
                <i
                  class="pi pi-check-circle text-3xl text-green-500 dark:text-green-400"
                ></i>
              </span>
              <p class="text-gray-500 dark:text-gray-400 font-medium">
                Nenhuma nota pendente de processamento
              </p>
              <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">
                Todas as notas foram processadas!
              </p>
            </div>
          </template>

          <Column
            field="contrato_id"
            header="Contrato"
            sortable
            style="min-width: 180px"
          >
            <template #body="{ data }">
              <div>
                <div
                  class="flex items-center gap-1.5 font-medium text-gray-900 dark:text-white"
                >
                  <i class="pi pi-file-edit text-blue-500"></i>
                  #{{ data.contrato_id }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  {{
                    data.contrato?.razao_social_loja ||
                    data.contrato?.nome_locador ||
                    ""
                  }}
                </div>
              </div>
            </template>
          </Column>

          <Column
            field="competencia"
            header="Competência"
            sortable
            style="min-width: 130px"
          >
            <template #body="{ data }">
              <div
                class="flex items-center gap-1.5 text-gray-700 dark:text-gray-300"
              >
                <i class="pi pi-calendar text-blue-500"></i>
                {{ data.competencia_formatada || data.competencia }}
              </div>
            </template>
          </Column>

          <Column
            field="valor_previsto"
            header="Previsto"
            sortable
            style="min-width: 120px"
          >
            <template #body="{ data }">
              <span class="font-semibold text-blue-600 dark:text-blue-400">
                {{ Recorrentes.formatarMoeda(data.valor_previsto) }}
              </span>
            </template>
          </Column>

          <Column
            field="valor_real"
            header="Valor NF"
            sortable
            style="min-width: 120px"
          >
            <template #body="{ data }">
              <span
                class="font-semibold"
                :class="
                  data.alerta_divergencia
                    ? 'text-red-600 dark:text-red-400'
                    : 'text-green-600 dark:text-green-400'
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
            header="Status"
            sortable
            style="min-width: 140px"
          >
            <template #body="{ data }">
              <Tag
                :value="Recorrentes.getEtapaLabel(data.etapa)"
                :severity="getEtapaSeverity(data.etapa)"
                class="font-medium"
              />
            </template>
          </Column>

          <Column
            field="alerta_divergencia"
            header="Alerta"
            style="min-width: 100px"
          >
            <template #body="{ data }">
              <Tag
                v-if="data.alerta_divergencia"
                value="Divergência"
                severity="danger"
                icon="pi pi-exclamation-triangle"
                class="font-medium"
              />
              <span
                v-else
                class="text-gray-400"
              >
                -
              </span>
            </template>
          </Column>

          <Column
            field="observacoes"
            header="Observações"
            style="min-width: 200px"
          >
            <template #body="{ data }">
              <div
                v-if="data.observacoes"
                class="flex items-start gap-1.5 text-sm text-gray-700 dark:text-gray-300"
              >
                <i
                  class="pi pi-comment text-amber-500 mt-0.5 flex-shrink-0"
                ></i>
                <span
                  class="line-clamp-2"
                  :title="data.observacoes"
                >
                  {{ data.observacoes }}
                </span>
              </div>
              <span
                v-else
                class="text-gray-400"
              >
                -
              </span>
            </template>
          </Column>

          <Column
            header="Ações"
            style="min-width: 240px"
            :exportable="false"
          >
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <!-- ENVIADA: aceitar NF (entrada de nota) -->
                <template v-if="data.etapa === 'ENVIADA'">
                  <Button
                    label="Aceitar NF"
                    icon="pi pi-check"
                    severity="success"
                    size="small"
                    rounded
                    outlined
                    @click="aceitarNota(data)"
                    :disabled="Recorrentes.loading.value"
                    v-tooltip.top="'Aceitar e registrar entrada da NF'"
                  />
                </template>
                <!-- ENTRADA_NOTA: encaminhar financeiro ou devolver -->
                <template v-else-if="data.etapa === 'ENTRADA_NOTA'">
                  <Button
                    label="Financeiro"
                    icon="pi pi-send"
                    severity="info"
                    size="small"
                    rounded
                    outlined
                    @click="encaminharFinanceiro(data)"
                    :disabled="Recorrentes.loading.value"
                    v-tooltip.top="'Encaminhar para o Financeiro'"
                  />
                  <Button
                    label="Devolver"
                    icon="pi pi-replay"
                    severity="danger"
                    size="small"
                    rounded
                    outlined
                    @click="devolverNota(data)"
                    :disabled="Recorrentes.loading.value"
                    v-tooltip.top="'Devolver NF para o gestor reenviar'"
                  />
                </template>
              </div>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
