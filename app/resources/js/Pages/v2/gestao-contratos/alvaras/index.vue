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
const alvaraParaExcluir = ref(null)
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
  { label: "Vigente", value: "VIGENTE" },
  { label: "Vencido", value: "VENCIDO" },
  { label: "Em Renovação", value: "EM_RENOVACAO" },
  { label: "Cancelado", value: "CANCELADO" }
]

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)
  await Promise.all([
    GestaoJs.getAlvaras(),
    GestaoJs.getFiliais(),
    GestaoJs.getTiposAlvara()
  ])
  loadingInicial.value = false
})

function novoAlvara() {
  router.visit("/pagina/gestao-contratos/alvaras/novo")
}

function editarAlvara(id) {
  router.visit(`/pagina/gestao-contratos/alvaras/${id}`)
}

function confirmarExclusao(alvara) {
  alvaraParaExcluir.value = alvara
  showConfirmDelete.value = true
}

async function excluirAlvara() {
  if (alvaraParaExcluir.value) {
    await GestaoJs.excluirAlvara(alvaraParaExcluir.value.id)
    showConfirmDelete.value = false
    alvaraParaExcluir.value = null
  }
}

function buscar() {
  GestaoJs.getAlvaras(1)
}

function limparFiltros() {
  GestaoJs.alvaras.value.filtros = {
    tipo_alvara_id: "",
    status: "",
    filial_id: "",
    busca: ""
  }
  GestaoJs.getAlvaras(1)
}

function mudarPagina(page) {
  GestaoJs.getAlvaras(page)
}

async function exportar() {
  loadingExport.value = true
  try {
    await GestaoJs.exportarAlvaras()
  } finally {
    loadingExport.value = false
  }
}

// Funções para status
function getStatusSeverity(status) {
  const severities = {
    VIGENTE: "success",
    VENCIDO: "danger",
    EM_RENOVACAO: "warn",
    CANCELADO: "secondary"
  }
  return severities[status] || "secondary"
}

function getTipoColor(tipo) {
  const cores = {
    FUNCIONAMENTO: "info",
    BOMBEIROS: "danger",
    SANITARIO: "success",
    AMBIENTAL: "success",
    OUTROS: "secondary"
  }
  return cores[tipo] || "secondary"
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
            Alvarás e Licenças
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
              Alvarás e Licenças
            </h2>
          </div>
          <span
            class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-bold pl-4 mt-1"
          >
            Gerencie alvarás de funcionamento, bombeiros, sanitários e outras
            licenças.
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
              Utilize os filtros abaixo para refinar a busca de alvarás.
            </div>
          </div>
        </div>
      </template>

      <div class="flex flex-col gap-4 w-full">
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 items-end gap-4 w-full"
        >
          <!-- Busca -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Buscar
            </label>
            <InputText
              v-model="GestaoJs.alvaras.value.filtros.busca"
              placeholder="Número, descrição, órgão..."
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
              v-model="GestaoJs.alvaras.value.filtros.filial_id"
              :options="filiaisFormatadas"
              optionLabel="label"
              optionValue="codfilial"
              placeholder="Todas"
              class="w-full h-10"
            />
          </div>

          <!-- Tipo -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Tipo
            </label>
            <Select
              v-model="GestaoJs.alvaras.value.filtros.tipo_alvara_id"
              :options="[
                { id: '', descricao: 'Todos' },
                ...GestaoJs.tiposAlvara.value
              ]"
              optionLabel="descricao"
              optionValue="id"
              placeholder="Todos"
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
              v-model="GestaoJs.alvaras.value.filtros.status"
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
              severity="help"
              outlined
              @click="buscar"
              :loading="GestaoJs.alvaras.value.loading"
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
            class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-purple-200 dark:bg-purple-900/30 shadow-lg flex-shrink-0"
          >
            <i
              class="pi pi-id-card !text-xl text-purple-700 dark:text-purple-400"
            ></i>
          </span>
          <div>
            <h2
              class="text-xl sm:text-xl md:text-2xl font-extrabold text-black-800 dark:text-white drop-shadow truncate"
            >
              Alvarás
              <span
                class="text-base font-normal text-gray-500 dark:text-gray-400 ml-2"
              >
                ({{ GestaoJs.alvaras.value.pagination.total }} registro{{
                  GestaoJs.alvaras.value.pagination.total !== 1 ? "s" : ""
                }})
              </span>
            </h2>
            <div
              class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 font-medium mt-1"
            >
              Lista de alvarás e licenças cadastrados
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
            :disabled="GestaoJs.alvaras.value.data.length === 0"
          />
          <Button
            label="Novo Alvará"
            icon="pi pi-plus"
            severity="help"
            outlined
            @click="novoAlvara"
          />
        </div>
      </div>

      <!-- DataTable -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm">
        <DataTable
          :value="GestaoJs.alvaras.value.data"
          :loading="GestaoJs.alvaras.value.loading"
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
              class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-full shadow-md text-sm"
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
                Nenhum alvará encontrado
              </p>
              <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">
                Tente ajustar os filtros ou adicione um novo alvará
              </p>
            </div>
          </template>

          <Column
            field="filial.razaosocial"
            header="Filial"
            sortable
            style="min-width: 200px"
          >
            <template #body="{ data }">
              <div>
                <div
                  class="flex truncate items-center gap-1.5 font-medium text-gray-900 dark:text-white"
                >
                  <i class="pi pi-building text-purple-500"></i>
                  {{
                    data.filial
                      ? data.filial.codigo + " - " + data.filial.razaosocial
                      : "-"
                  }}
                </div>
              </div>
            </template>
          </Column>

          <Column
            field="tipo_alvara.descricao"
            header="Tipo"
            sortable
            style="min-width: 150px"
          >
            <template #body="{ data }">
              <Tag
                :value="data.tipo_alvara?.descricao || '-'"
                :severity="getTipoColor(data.tipo_alvara?.codigo)"
                class="font-medium truncate max-w-xs"
              />
            </template>
          </Column>

          <Column
            field="numero_documento"
            header="Número"
            sortable
            style="min-width: 140px"
          >
            <template #body="{ data }">
              <div>
                <div
                  class="flex items-center gap-1.5 font-medium text-gray-900 dark:text-gray-200"
                >
                  <i class="pi pi-file text-blue-500"></i>
                  {{ data.numero_documento || "-" }}
                </div>
                <div
                  v-if="data.descricao"
                  class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs"
                >
                  {{ data.descricao }}
                </div>
              </div>
            </template>
          </Column>

          <Column
            field="orgao_emissor"
            header="Órgão"
            sortable
            style="min-width: 150px"
          >
            <template #body="{ data }">
              <div
                class="flex items-center gap-1.5 truncate text-sm text-gray-700 dark:text-gray-300"
              >
                <i class="pi pi-briefcase text-gray-400"></i>
                {{ data.orgao_emissor || "-" }}
              </div>
            </template>
          </Column>

          <Column
            field="data_validade"
            header="Validade"
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
                      : data.dias_para_vencimento < 0
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-gray-900 dark:text-gray-200'
                  ]"
                >
                  <i
                    class="pi pi-calendar"
                    :class="
                      data.dias_para_vencimento <= 30
                        ? 'text-red-500'
                        : 'text-orange-500'
                    "
                  ></i>
                  {{ GestaoJs.formatarData(data.data_validade) }}
                </div>
                <div
                  class="flex items-center gap-1.5 text-xs"
                  :class="
                    data.dias_para_vencimento < 0
                      ? 'text-red-500 dark:text-red-400 font-medium'
                      : data.dias_para_vencimento <= 30
                        ? 'text-orange-500 dark:text-orange-400'
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
                  <span v-if="data.dias_para_vencimento > 0">
                    {{ data.dias_para_vencimento }} dias restantes
                  </span>
                  <span v-else-if="data.dias_para_vencimento === 0">
                    Vence hoje!
                  </span>
                  <span v-else>
                    Vencido há {{ Math.abs(data.dias_para_vencimento) }} dias
                  </span>
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
            style="min-width: 140px"
            :exportable="false"
          >
            <template #body="{ data }">
              <div class="flex items-center justify-center gap-1">
                <Button
                  v-if="data.arquivo_path"
                  icon="pi pi-download"
                  severity="success"
                  text
                  rounded
                  @click="GestaoJs.downloadAnexoAlvara(data.id)"
                  v-tooltip.top="'Baixar Anexo'"
                />
                <Button
                  icon="pi pi-pencil"
                  severity="help"
                  text
                  rounded
                  @click="editarAlvara(data.id)"
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
            Tem certeza que deseja excluir o alvará
            <strong class="text-gray-900 dark:text-white">
              {{ alvaraParaExcluir?.numero_documento }}
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
              @click="excluirAlvara"
              label="Excluir"
              severity="danger"
            />
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
