<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import * as Recorrentes from "../contratos-recorrentes.js"
import { swalConfirm } from "@/utils/globalFunctions.js"
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
const etapasOptions = [
  { label: "Todas", value: "" },
  { label: "Pendente Envio", value: "PENDENTE" },
  { label: "NF/Boleto Enviado", value: "ENVIADA" },
  { label: "Entrada de Nota", value: "ENTRADA_NOTA" },
  { label: "Financeiro", value: "FINANCEIRO" },
  { label: "Pago", value: "PAGO" }
]

const alertaOptions = [
  { label: "Todos", value: "" },
  { label: "Com Alerta", value: "1" },
  { label: "Sem Alerta", value: "0" }
]

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
  await Promise.all([
    Recorrentes.getContratosRecorrentes(),
    Recorrentes.getMedicoes()
  ])
})

function buscar() {
  Recorrentes.getMedicoes(1)
}

function limparFiltros() {
  Recorrentes.medicoes.value.filtros = {
    contrato_id: "",
    competencia: "",
    etapa: "",
    alerta: ""
  }
  Recorrentes.getMedicoes(1)
}

function irParaEnvio(id) {
  router.visit(`/pagina/gestao-contratos/medicoes/enviar/${id}`)
}

async function confirmarGerarMedicoes() {
  const result = await swalConfirm(
    "Gerar Medições Mensais",
    "Isso criará uma medição pendente para cada contrato recorrente ativo que ainda não tenha medição neste mês. Deseja continuar?",
    "Gerar Medições",
    "Cancelar",
    { icon: "question" }
  )
  if (!result.isConfirmed) return
  await Recorrentes.gerarMedicoesMensais()
  Recorrentes.getMedicoes(1)
}

function getAcaoDisponivel(medicao) {
  // Gestor só pode enviar NF quando PENDENTE.
  // As demais transições (entrada_nota, financeiro, pagar)
  // são feitas nas telas específicas por Alexandre/Daniela.
  if (medicao.etapa === "PENDENTE") {
    return {
      label: "Enviar NF",
      icon: "pi pi-upload",
      action: () => irParaEnvio(medicao.id)
    }
  }
  return null
}

async function movimentar(id, acao) {
  await Recorrentes.movimentarMedicao(id, acao)
  Recorrentes.getMedicoes(Recorrentes.medicoes.value.pagination.current_page)
}

function getEtapaSeverity(etapa) {
  const severities = {
    PENDENTE: "warn",
    ENVIADA: "info",
    ENTRADA_NOTA: "info",
    FINANCEIRO: "info",
    PAGO: "success",
    DIVERGENCIA: "danger",
    CANCELADA: "danger"
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
          <span class="text-gray-950 dark:text-white font-bold">Medições</span>
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
              Medições - Contratos Recorrentes
            </h2>
          </div>
          <span
            class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-bold pl-4 mt-1"
          >
            Acompanhe o envio de NF/Boleto e o fluxo de pagamento das medições
            mensais.
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
              Utilize os filtros abaixo para refinar a busca de medições.
            </div>
          </div>
        </div>
      </template>

      <div class="flex flex-col gap-4 w-full">
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 items-end gap-4 w-full"
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

          <!-- Etapa -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Etapa
            </label>
            <Select
              v-model="Recorrentes.medicoes.value.filtros.etapa"
              :options="etapasOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Todas"
              class="w-full h-10"
            />
          </div>

          <!-- Alertas -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Alertas
            </label>
            <Select
              v-model="Recorrentes.medicoes.value.filtros.alerta"
              :options="alertaOptions"
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
            class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-200 dark:bg-blue-900/30 shadow-lg flex-shrink-0"
          >
            <i class="pi pi-list !text-xl text-blue-700 dark:text-blue-400"></i>
          </span>
          <div>
            <h2
              class="text-xl sm:text-xl md:text-2xl font-extrabold text-black-800 dark:text-white drop-shadow truncate"
            >
              Medições
              <span
                class="text-base font-normal text-gray-500 dark:text-gray-400 ml-2"
              >
                ({{ Recorrentes.medicoes.value.pagination.total }} registro{{
                  Recorrentes.medicoes.value.pagination.total !== 1 ? "s" : ""
                }})
              </span>
            </h2>
            <div
              class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 font-medium mt-1"
            >
              Lista de medições dos contratos recorrentes
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <Button
            label="Gerar Medições do Mês"
            icon="pi pi-plus-circle"
            severity="info"
            outlined
            @click="confirmarGerarMedicoes"
          />
        </div>
      </div>

      <!-- DataTable -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm">
        <DataTable
          :value="Recorrentes.medicoes.value.data"
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
                Nenhuma medição encontrada
              </p>
              <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">
                Clique em "Gerar Medições do Mês" para criar as medições
                pendentes
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
            style="min-width: 130px"
          >
            <template #body="{ data }">
              <div
                class="flex items-center gap-1.5 font-semibold text-blue-600 dark:text-blue-400"
              >
                <i class="pi pi-dollar"></i>
                {{ Recorrentes.formatarMoeda(data.valor_previsto) }}
              </div>
            </template>
          </Column>

          <Column
            field="valor_real"
            header="Real"
            sortable
            style="min-width: 130px"
          >
            <template #body="{ data }">
              <div
                class="flex items-center gap-1.5 font-semibold"
                :class="
                  data.alerta_divergencia
                    ? 'text-red-600 dark:text-red-400'
                    : 'text-green-600 dark:text-green-400'
                "
              >
                <i class="pi pi-dollar"></i>
                {{
                  data.valor_real
                    ? Recorrentes.formatarMoeda(data.valor_real)
                    : "-"
                }}
              </div>
            </template>
          </Column>

          <Column
            field="etapa"
            header="Etapa"
            sortable
            style="min-width: 140px"
          >
            <template #body="{ data }">
              <Tag
                :value="Recorrentes.getEtapaLabel(data.etapa)"
                :severity="getEtapaSeverity(data.etapa)"
                class="font-medium truncate"
              />
            </template>
          </Column>

          <Column
            field="alerta_divergencia"
            header="Alerta"
            style="min-width: 120px"
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
            style="min-width: 160px"
            :exportable="false"
          >
            <template #body="{ data }">
              <div class="flex items-center gap-1 truncate">
                <template v-if="getAcaoDisponivel(data)">
                  <Button
                    :label="getAcaoDisponivel(data).label"
                    :icon="getAcaoDisponivel(data).icon"
                    severity="info"
                    text
                    size="small"
                    @click="getAcaoDisponivel(data).action()"
                    :disabled="Recorrentes.loading.value"
                  />
                </template>
                <template v-else-if="data.etapa === 'PAGO'">
                  <Tag
                    value="Concluído"
                    severity="success"
                    icon="pi pi-check-circle"
                    class="font-medium"
                  />
                </template>
                <template v-else>
                  <Tag
                    value="Em processamento"
                    severity="info"
                    icon="pi pi-spin pi-spinner"
                    class="font-medium"
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
