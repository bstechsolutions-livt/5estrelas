<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, ref } from "vue"
import { router } from "@inertiajs/vue3"
import * as Recorrentes from "../contratos-recorrentes.js"
import Panel from "primevue/panel"
import Button from "primevue/button"
import Select from "primevue/select"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Tag from "primevue/tag"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO LOCAL                           ║
// ╚══════════════════════════════════════════════════════════════╝
const activeTab = ref("para-renovar")
const diasFiltro = ref(60)

const diasOptions = [
  { label: "30 dias", value: 30 },
  { label: "60 dias", value: 60 },
  { label: "90 dias", value: 90 },
  { label: "120 dias", value: 120 },
  { label: "180 dias", value: 180 }
]

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)
  await Promise.all([
    Recorrentes.getContratosParaRenovar(diasFiltro.value),
    Recorrentes.getRenovacoes()
  ])
})

function irParaRenovacao(contratoId) {
  router.visit(`/pagina/gestao-contratos/renovacao/${contratoId}`)
}

function buscarParaRenovar() {
  Recorrentes.getContratosParaRenovar(diasFiltro.value)
}

function getStatusSeverity(status) {
  const severities = {
    APROVADA: "success",
    PENDENTE_COMPRAS: "warn",
    REJEITADA: "danger"
  }
  return severities[status] || "secondary"
}

function getStatusLabel(status) {
  const labels = {
    APROVADA: "Aprovada",
    PENDENTE_COMPRAS: "Pendente Compras",
    REJEITADA: "Rejeitada"
  }
  return labels[status] || status
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
            class="hover:text-green-600 dark:hover:text-green-400"
          >
            Gestão de Contratos
          </a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">
            Renovações
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
                class="w-1 h-8 bg-gradient-to-b from-green-500 to-green-700 rounded-full"
              ></div>
              Renovações de Contratos
            </h2>
          </div>
          <span
            class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-bold pl-4 mt-1"
          >
            Gerencie renovações de contratos recorrentes com regra de
            divergência automática.
          </span>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div
      class="flex items-center gap-1 mb-6 bg-white dark:bg-slate-800 rounded-xl p-1 border border-gray-200 dark:border-slate-700 w-fit"
    >
      <Button
        :label="'Para Renovar'"
        icon="pi pi-refresh"
        :severity="activeTab === 'para-renovar' ? 'success' : 'secondary'"
        :outlined="activeTab !== 'para-renovar'"
        @click="activeTab = 'para-renovar'"
        size="small"
      />
      <Button
        :label="'Histórico'"
        icon="pi pi-history"
        :severity="activeTab === 'historico' ? 'success' : 'secondary'"
        :outlined="activeTab !== 'historico'"
        @click="activeTab = 'historico'"
        size="small"
      />
    </div>

    <!-- Tab: Para Renovar -->
    <div v-if="activeTab === 'para-renovar'">
      <!-- Filtro dias -->
      <Panel
        class="mb-6 bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
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
                Filtros
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Filtre os contratos por período de vencimento.
              </div>
            </div>
          </div>
        </template>

        <div class="flex flex-col gap-4 w-full">
          <div class="grid grid-cols-1 sm:grid-cols-3 items-end gap-4 w-full">
            <div class="flex flex-col gap-1">
              <label
                class="text-sm font-semibold text-gray-700 dark:text-gray-300"
              >
                Contratos vencendo nos próximos:
              </label>
              <Select
                v-model="diasFiltro"
                :options="diasOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="60 dias"
                class="w-full h-10"
              />
            </div>
            <div class="flex gap-2 items-end">
              <Button
                label="Buscar"
                icon="pi pi-search"
                severity="success"
                outlined
                @click="buscarParaRenovar"
                :loading="Recorrentes.contratosParaRenovar.value.loading"
              />
            </div>
          </div>
        </div>
      </Panel>

      <!-- Tabela de Contratos para Renovar -->
      <div
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 sm:p-6 relative overflow-hidden"
      >
        <div
          class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6"
        >
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-200 dark:bg-green-900/30 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-refresh !text-xl text-green-700 dark:text-green-400"
              ></i>
            </span>
            <div>
              <h2
                class="text-xl sm:text-xl md:text-2xl font-extrabold text-black-800 dark:text-white drop-shadow truncate"
              >
                Contratos para Renovar
                <span
                  class="text-base font-normal text-gray-500 dark:text-gray-400 ml-2"
                >
                  ({{
                    Recorrentes.contratosParaRenovar.value.data.length
                  }}
                  registro{{
                    Recorrentes.contratosParaRenovar.value.data.length !== 1
                      ? "s"
                      : ""
                  }})
                </span>
              </h2>
              <div
                class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 font-medium mt-1"
              >
                Contratos próximos do vencimento que precisam de renovação
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm">
          <DataTable
            :value="Recorrentes.contratosParaRenovar.value.data"
            :loading="Recorrentes.contratosParaRenovar.value.loading"
            paginator
            :rows="20"
            :rowsPerPageOptions="[10, 20, 50]"
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
                data.dias_para_vencimento <= 30 &&
                data.dias_para_vencimento >= 0
                  ? 'bg-red-50 dark:bg-red-900/20'
                  : ''
            "
          >
            <template #loading>
              <div
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-full shadow-md text-sm"
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
                  <i class="pi pi-check-circle text-3xl text-green-500"></i>
                </span>
                <p class="text-gray-500 dark:text-gray-400 font-medium">
                  Nenhum contrato para renovar neste período
                </p>
              </div>
            </template>

            <Column
              header="Fornecedor"
              sortable
              style="min-width: 220px"
            >
              <template #body="{ data }">
                <div>
                  <div
                    class="flex items-center gap-1.5 font-medium text-gray-900 dark:text-white"
                  >
                    <i class="pi pi-user text-green-500"></i>
                    {{
                      data.razao_social_loja ||
                      data.nome_locador ||
                      "Contrato #" + data.id
                    }}
                  </div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">
                    Contrato #{{ data.id }}
                  </div>
                </div>
              </template>
            </Column>

            <Column
              field="valor_mensal"
              header="Valor Mensal"
              sortable
              style="min-width: 140px"
            >
              <template #body="{ data }">
                <div
                  class="flex items-center gap-1.5 font-semibold text-green-600 dark:text-green-400"
                >
                  <i class="pi pi-dollar"></i>
                  {{ Recorrentes.formatarMoeda(data.valor_mensal) }}
                </div>
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
                    :class="
                      data.dias_para_vencimento <= 30
                        ? 'text-red-600 dark:text-red-400'
                        : 'text-gray-900 dark:text-gray-200'
                    "
                  >
                    <i
                      class="pi pi-calendar"
                      :class="
                        data.dias_para_vencimento <= 30
                          ? 'text-red-500'
                          : 'text-orange-500'
                      "
                    ></i>
                    {{ Recorrentes.formatarData(data.data_fim) }}
                  </div>
                </div>
              </template>
            </Column>

            <Column
              field="dias_para_vencimento"
              header="Dias"
              sortable
              style="min-width: 100px"
            >
              <template #body="{ data }">
                <Tag
                  :value="data.dias_para_vencimento + ' dias'"
                  :severity="
                    data.dias_para_vencimento <= 30 ? 'danger' : 'warn'
                  "
                  class="font-medium"
                />
              </template>
            </Column>

            <Column
              field="percentual_divergencia"
              header="Divergência"
              style="min-width: 120px"
            >
              <template #body="{ data }">
                <span
                  v-if="data.percentual_divergencia"
                  class="text-sm font-bold text-orange-600"
                >
                  {{ data.percentual_divergencia }}%
                </span>
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
              style="min-width: 120px"
              :exportable="false"
            >
              <template #body="{ data }">
                <div class="flex items-center gap-1">
                  <Button
                    icon="pi pi-refresh"
                    severity="success"
                    text
                    rounded
                    @click="irParaRenovacao(data.id)"
                    v-tooltip.top="'Renovar'"
                  />
                </div>
              </template>
            </Column>
          </DataTable>
        </div>
      </div>
    </div>

    <!-- Tab: Histórico -->
    <div v-if="activeTab === 'historico'">
      <div
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 sm:p-6 relative overflow-hidden"
      >
        <div
          class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6"
        >
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-200 dark:bg-green-900/30 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-history !text-xl text-green-700 dark:text-green-400"
              ></i>
            </span>
            <div>
              <h2
                class="text-xl sm:text-xl md:text-2xl font-extrabold text-black-800 dark:text-white drop-shadow truncate"
              >
                Histórico de Renovações
                <span
                  class="text-base font-normal text-gray-500 dark:text-gray-400 ml-2"
                >
                  ({{
                    Recorrentes.renovacoes.value.pagination.total
                  }}
                  registro{{
                    Recorrentes.renovacoes.value.pagination.total !== 1
                      ? "s"
                      : ""
                  }})
                </span>
              </h2>
              <div
                class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 font-medium mt-1"
              >
                Todas as renovações já realizadas
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm">
          <DataTable
            :value="Recorrentes.renovacoes.value.data"
            :loading="Recorrentes.renovacoes.value.loading"
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
          >
            <template #loading>
              <div
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-full shadow-md text-sm"
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
                    class="pi pi-history text-3xl text-gray-400 dark:text-gray-500"
                  ></i>
                </span>
                <p class="text-gray-500 dark:text-gray-400 font-medium">
                  Nenhuma renovação registrada
                </p>
              </div>
            </template>

            <Column
              field="contrato_id"
              header="Contrato"
              sortable
              style="min-width: 100px"
            >
              <template #body="{ data }">
                <div
                  class="flex items-center gap-1.5 font-medium text-gray-900 dark:text-white"
                >
                  <i class="pi pi-file-edit text-green-500"></i>
                  #{{ data.contrato_id }}
                </div>
              </template>
            </Column>

            <Column
              field="valor_anterior"
              header="Valor Anterior"
              sortable
              style="min-width: 130px"
            >
              <template #body="{ data }">
                <span class="font-mono text-gray-700 dark:text-gray-300">
                  {{ Recorrentes.formatarMoeda(data.valor_anterior) }}
                </span>
              </template>
            </Column>

            <Column
              field="valor_novo"
              header="Valor Novo"
              sortable
              style="min-width: 130px"
            >
              <template #body="{ data }">
                <span
                  class="font-mono font-medium text-gray-900 dark:text-white"
                >
                  {{ Recorrentes.formatarMoeda(data.valor_novo) }}
                </span>
              </template>
            </Column>

            <Column
              field="percentual_variacao"
              header="Variação"
              sortable
              style="min-width: 110px"
            >
              <template #body="{ data }">
                <span
                  :class="
                    data.percentual_variacao > 0
                      ? 'text-red-600'
                      : 'text-green-600'
                  "
                  class="font-medium"
                >
                  {{ data.percentual_variacao > 0 ? "+" : ""
                  }}{{ parseFloat(data.percentual_variacao).toFixed(2) }}%
                </span>
              </template>
            </Column>

            <Column
              field="dentro_divergencia"
              header="Divergência"
              style="min-width: 110px"
            >
              <template #body="{ data }">
                <Tag
                  v-if="data.dentro_divergencia"
                  value="Dentro"
                  severity="success"
                  icon="pi pi-check-circle"
                  class="font-medium"
                />
                <Tag
                  v-else
                  value="Fora"
                  severity="danger"
                  icon="pi pi-exclamation-triangle"
                  class="font-medium"
                />
              </template>
            </Column>

            <Column
              field="status"
              header="Status"
              sortable
              style="min-width: 140px"
            >
              <template #body="{ data }">
                <Tag
                  :value="getStatusLabel(data.status)"
                  :severity="getStatusSeverity(data.status)"
                  class="font-medium"
                />
              </template>
            </Column>

            <Column
              field="created_at"
              header="Data"
              sortable
              style="min-width: 110px"
            >
              <template #body="{ data }">
                <span class="text-gray-600 dark:text-gray-400">
                  {{ Recorrentes.formatarData(data.created_at) }}
                </span>
              </template>
            </Column>
          </DataTable>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
