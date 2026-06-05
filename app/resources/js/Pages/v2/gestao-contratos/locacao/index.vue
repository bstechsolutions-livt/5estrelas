<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import * as GestaoJs from "../gestao-contratos.js"
import Panel from "primevue/panel"
import Button from "primevue/button"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Tag from "primevue/tag"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO LOCAL                           ║
// ╚══════════════════════════════════════════════════════════════╝
const showConfirmDelete = ref(false)
const contratoParaExcluir = ref(null)
const loadingInicial = ref(true)
const loadingExport = ref(false)

// Computed para formatar filiais com código + nome
const filiaisFormatadas = computed(() => {
  return [
    { codfilial: "", label: "Todas" },
    ...GestaoJs.filiais.value.map((filial) => ({
      ...filial,
      label: `${filial.codfilial} - ${filial.filial}`
    }))
  ]
})

// Status options para o select
const statusOptions = [
  { label: "Todos", value: "" },
  { label: "Ativo", value: "ATIVO" },
  { label: "Pendente", value: "PENDENTE" },
  { label: "Em Renovação", value: "EM_RENOVACAO" },
  { label: "Encerrado", value: "ENCERRADO" }
]

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)
  GestaoJs.contratos.value.filtros.tipo = "LOCACAO"
  await Promise.all([
    GestaoJs.getContratos(),
    GestaoJs.getFiliais(),
    GestaoJs.getTiposIndice()
  ])
  loadingInicial.value = false
})

function novoContrato() {
  router.visit("/pagina/gestao-contratos/locacao/novo")
}

function editarContrato(id) {
  router.visit(`/pagina/gestao-contratos/locacao/${id}`)
}

function confirmarExclusao(contrato) {
  contratoParaExcluir.value = contrato
  showConfirmDelete.value = true
}

async function excluirContrato() {
  if (contratoParaExcluir.value) {
    await GestaoJs.excluirContrato(contratoParaExcluir.value.id)
    showConfirmDelete.value = false
    contratoParaExcluir.value = null
  }
}

function buscar() {
  GestaoJs.getContratos(1)
}

function limparFiltros() {
  GestaoJs.contratos.value.filtros = {
    tipo: "LOCACAO",
    status: "",
    filial_id: "",
    busca: ""
  }
  GestaoJs.getContratos(1)
}

function mudarPagina(page) {
  GestaoJs.getContratos(page)
}

async function exportar() {
  loadingExport.value = true
  try {
    await GestaoJs.exportarContratosLocacao()
  } finally {
    loadingExport.value = false
  }
}

// Funções para status
function getStatusSeverity(status) {
  const severities = {
    ATIVO: "success",
    PENDENTE: "warn",
    EM_RENOVACAO: "info",
    ENCERRADO: "danger"
  }
  return severities[status] || "secondary"
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
            Contratos de Locação
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
                class="w-1 h-8 bg-gradient-to-b from-blue-500 to-blue-700 rounded-full"
              ></div>
              Contratos de Locação
            </h2>
          </div>
          <span
            class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-bold pl-4 mt-1"
          >
            Gerencie os contratos de locação de imóveis da empresa.
          </span>
        </div>
      </div>
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
              Utilize os filtros abaixo para refinar a busca de contratos.
            </div>
          </div>
        </div>
      </template>

      <div class="flex flex-col gap-4 w-full">
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 items-end gap-4 w-full"
        >
          <!-- Busca -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Buscar
            </label>
            <InputText
              v-model="GestaoJs.contratos.value.filtros.busca"
              placeholder="Razão social, locador, CNPJ..."
              class="w-full h-10 px-3"
              @keyup.enter="buscar"
            />
          </div>

          <!-- Filial -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Filial
            </label>
            <Select
              v-model="GestaoJs.contratos.value.filtros.filial_id"
              :options="filiaisFormatadas"
              optionLabel="label"
              optionValue="codfilial"
              placeholder="Todas"
              class="w-full h-10"
            />
          </div>

          <!-- Status -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Status
            </label>
            <Select
              v-model="GestaoJs.contratos.value.filtros.status"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Todos"
              class="w-full h-10"
            />
          </div>

          <!-- Botões -->
          <div
            class="flex gap-2 w-full sm:w-auto justify-end items-end sm:col-span-2 lg:col-span-1"
          >
            <Button
              label="Buscar"
              icon="pi pi-search"
              severity="info"
              outlined
              @click="buscar"
              :loading="GestaoJs.contratos.value.loading"
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

    <!-- Tabela de Resultados com DataTable -->
    <div
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
              Contratos
              <span
                class="text-base font-normal text-gray-500 dark:text-gray-400 ml-2"
              >
                ({{ GestaoJs.contratos.value.pagination.total }} registro{{
                  GestaoJs.contratos.value.pagination.total !== 1 ? "s" : ""
                }})
              </span>
            </h2>
            <div
              class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 font-medium mt-1"
            >
              Lista de contratos de locação cadastrados
            </div>
          </div>
        </div>

        <!-- Botões -->
        <div class="flex items-center gap-3">
          <Button
            label="Exportar"
            icon="pi pi-file-excel"
            severity="success"
            outlined
            @click="exportar"
            :loading="loadingExport"
            :disabled="GestaoJs.contratos.value.data.length === 0"
          />
          <Button
            label="Novo Contrato"
            icon="pi pi-plus"
            severity="info"
            outlined
            @click="novoContrato"
          />
        </div>
      </div>

      <!-- DataTable -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm">
        <DataTable
          :value="GestaoJs.contratos.value.data"
          :loading="GestaoJs.contratos.value.loading"
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
              data.dias_para_vencimento <= 30 && data.dias_para_vencimento >= 0
                ? 'bg-red-50 dark:bg-red-900/20'
                : ''
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
                Nenhum contrato encontrado
              </p>
              <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">
                Tente ajustar os filtros ou adicione um novo contrato
              </p>
            </div>
          </template>

          <Column
            field="razao_social_loja"
            header="Filial / Loja"
            sortable
            style="min-width: 200px"
          >
            <template #body="{ data }">
              <div>
                <div
                  class="flex items-center gap-1.5 font-medium text-gray-900 dark:text-white"
                >
                  <i class="pi pi-building text-blue-500"></i>
                  {{
                    data.razao_social_loja ||
                    (data.filial
                      ? data.filial.codigo + " - " + data.filial.razaosocial
                      : "-")
                  }}
                </div>
                <div
                  class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"
                >
                  <i class="pi pi-id-card text-gray-400"></i>
                  {{ data.cnpj_loja || "-" }}
                </div>
              </div>
            </template>
          </Column>

          <Column
            field="nome_locador"
            header="Locador"
            sortable
            style="min-width: 150px"
          >
            <template #body="{ data }">
              <div>
                <div
                  class="flex items-center gap-1.5 text-sm font-medium text-gray-900 dark:text-gray-200"
                >
                  <i class="pi pi-user text-purple-500"></i>
                  {{ data.nome_locador || "-" }}
                </div>
                <div
                  v-if="data.imobiliaria"
                  class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"
                >
                  <i class="pi pi-home text-gray-400"></i>
                  {{ data.imobiliaria }}
                </div>
              </div>
            </template>
          </Column>

          <Column
            field="valor_mensal"
            header="Valor"
            sortable
            style="min-width: 130px"
          >
            <template #body="{ data }">
              <div>
                <div
                  class="flex items-center gap-1.5 font-semibold text-green-600 dark:text-green-400"
                >
                  <i class="pi pi-dollar"></i>
                  {{ GestaoJs.formatarMoeda(data.valor_mensal) }}
                </div>
                <div
                  v-if="data.valor_condominio"
                  class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"
                >
                  <i class="pi pi-plus text-gray-400"></i>
                  {{ GestaoJs.formatarMoeda(data.valor_condominio) }} cond.
                </div>
              </div>
            </template>
          </Column>

          <Column
            field="tipo_indice.codigo"
            header="Índice"
            sortable
            style="min-width: 100px"
          >
            <template #body="{ data }">
              <span
                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded-lg"
              >
                <i class="pi pi-chart-line text-xs"></i>
                {{ data.tipo_indice?.codigo || "-" }}
              </span>
            </template>
          </Column>

          <Column
            field="data_fim"
            header="Vencimento"
            sortable
            style="min-width: 140px"
          >
            <template #body="{ data }">
              <div>
                <div
                  class="flex items-center gap-1.5 font-medium"
                  :class="[
                    data.dias_para_vencimento <= 30 &&
                    data.dias_para_vencimento >= 0
                      ? 'text-red-600 dark:text-red-400'
                      : 'text-gray-900 dark:text-gray-200'
                  ]"
                >
                  <i
                    class="pi pi-calendar"
                    :class="
                      data.dias_para_vencimento <= 30 &&
                      data.dias_para_vencimento >= 0
                        ? 'text-red-500'
                        : 'text-orange-500'
                    "
                  ></i>
                  {{ GestaoJs.formatarData(data.data_fim) }}
                </div>
                <div
                  class="flex items-center gap-1.5 text-xs"
                  :class="
                    data.dias_para_vencimento < 0
                      ? 'text-red-500 dark:text-red-400 font-medium'
                      : 'text-gray-500 dark:text-gray-400'
                  "
                >
                  <i
                    class="pi pi-clock"
                    :class="
                      data.dias_para_vencimento < 0
                        ? 'text-red-400'
                        : 'text-gray-400'
                    "
                  ></i>
                  {{
                    data.dias_para_vencimento >= 0
                      ? data.dias_para_vencimento + " dias"
                      : "Vencido há " +
                        Math.abs(data.dias_para_vencimento) +
                        " dias"
                  }}
                </div>
              </div>
            </template>
          </Column>

          <Column
            field="status"
            header="Status"
            sortable
            style="min-width: 120px"
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
            header="Ações"
            style="min-width: 120px"
            :exportable="false"
          >
            <template #body="{ data }">
              <div class="flex items-center justify-center gap-1">
                <Button
                  icon="pi pi-pencil"
                  severity="info"
                  text
                  rounded
                  @click="editarContrato(data.id)"
                  v-tooltip.top="'Editar'"
                />
                <Button
                  icon="pi pi-trash"
                  severity="danger"
                  text
                  rounded
                  @click="confirmarExclusao(data)"
                  v-tooltip.top="'Excluir'"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- Modal Confirmar Exclusão -->
    <div
      v-if="showConfirmDelete"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
      <div
        class="bg-white dark:bg-slate-800 rounded-xl p-6 max-w-md w-full mx-4 shadow-xl"
      >
        <div class="text-center">
          <i
            class="pi pi-exclamation-triangle text-5xl text-yellow-500 mb-4"
          ></i>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            Confirmar Exclusão
          </h3>
          <p class="text-gray-600 dark:text-gray-400 mb-6">
            Tem certeza que deseja excluir o contrato de
            <strong class="text-gray-900 dark:text-white">
              {{
                contratoParaExcluir?.razao_social_loja ||
                contratoParaExcluir?.nome_locador
              }}
            </strong>
            ?
          </p>
          <div class="flex gap-3 justify-center">
            <Button
              @click="showConfirmDelete = false"
              label="Cancelar"
              severity="secondary"
              outlined
            />
            <Button
              @click="excluirContrato"
              label="Excluir"
              severity="danger"
            />
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
