<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, computed } from "vue"
import { router } from "@inertiajs/vue3"
import * as Recorrentes from "../contratos-recorrentes.js"
import Panel from "primevue/panel"
import Button from "primevue/button"
import Tag from "primevue/tag"
import DataTable from "primevue/datatable"
import Column from "primevue/column"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)
  await Recorrentes.getDashboardRecorrente()
})

const dashboard = computed(() => Recorrentes.dashboardRecorrente.value)

function navegar(rota) {
  router.visit(rota)
}

function getAlertaSeverity(tipo) {
  const map = {
    pendentes_envio: "warn",
    divergencias: "danger",
    vencendo_90d: "info",
    vencidos: "danger"
  }
  return map[tipo] || "secondary"
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
            class="hover:text-indigo-600 dark:hover:text-indigo-400"
          >
            Gestão de Contratos
          </a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">
            Dashboard Recorrentes
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
          class="w-1 h-8 bg-gradient-to-b from-indigo-500 to-indigo-700 rounded-full"
        ></div>
        Dashboard - Contratos Recorrentes
      </h2>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Visão geral de medições, renovações e alertas dos contratos recorrentes.
      </p>
    </div>

    <!-- Loading -->
    <div
      v-if="!dashboard || dashboard.loading"
      class="flex items-center justify-center py-16"
    >
      <div
        class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-full shadow-md text-sm"
      >
        <i class="pi pi-spinner pi-spin text-xs"></i>
        <span class="font-medium">Carregando dashboard...</span>
      </div>
    </div>

    <div
      v-else
      class="space-y-6"
    >
      <!-- Cards Totais -->
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-blue-500"
            >
              <i class="pi pi-file !text-[11px] flex-shrink-0"></i>
              Contratos Ativos
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ dashboard.totais?.contratos_ativos || 0 }}</span>
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
              Valor Mensal
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>
              {{
                Recorrentes.formatarMoeda(dashboard.totais?.valor_mensal_total)
              }}
            </span>
          </div>
        </div>
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-purple-500"
            >
              <i class="pi pi-calculator !text-[11px] flex-shrink-0"></i>
              Provisão Mensal
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>
              {{ Recorrentes.formatarMoeda(dashboard.totais?.provisao_mensal) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Pipeline + Alertas (2 colunas) -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Pipeline Medições -->
        <Panel
          class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
        >
          <template #header>
            <div class="flex items-center gap-2 mb-2">
              <span
                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-200 dark:bg-blue-900 shadow-lg flex-shrink-0"
              >
                <i
                  class="pi pi-chart-bar text-blue-700 dark:text-blue-300 !text-xl"
                ></i>
              </span>
              <div>
                <h3
                  class="text-2xl font-extrabold text-black-800 dark:text-white"
                >
                  Pipeline de Medições
                </h3>
                <div
                  class="text-xs text-gray-500 dark:text-gray-400 font-medium"
                >
                  Distribuição por etapa no mês atual.
                </div>
              </div>
            </div>
          </template>

          <div
            v-if="dashboard.pipeline_medicoes"
            class="space-y-3"
          >
            <div
              v-for="(etapa, index) in dashboard.pipeline_medicoes"
              :key="index"
              class="flex items-center gap-3"
            >
              <div
                class="w-32 text-right text-sm font-medium text-gray-600 dark:text-gray-400"
              >
                {{ etapa.etapa || etapa.nome }}
              </div>
              <div
                class="flex-1 h-6 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden"
              >
                <div
                  class="h-full rounded-full transition-all duration-300"
                  :class="[
                    index === 0
                      ? 'bg-blue-500'
                      : index === 1
                        ? 'bg-yellow-500'
                        : index === 2
                          ? 'bg-green-500'
                          : index === 3
                            ? 'bg-purple-500'
                            : 'bg-gray-400'
                  ]"
                  :style="{ width: (etapa.percentual || 0) + '%' }"
                ></div>
              </div>
              <span
                class="w-8 text-sm font-bold text-gray-700 dark:text-gray-300"
              >
                {{ etapa.total || 0 }}
              </span>
            </div>
          </div>
          <p
            v-else
            class="text-sm text-gray-500 dark:text-gray-400 text-center py-4"
          >
            Sem dados de pipeline.
          </p>
        </Panel>

        <!-- Alertas -->
        <Panel
          class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
        >
          <template #header>
            <div class="flex items-center gap-2 mb-2">
              <span
                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-orange-200 dark:bg-orange-900 shadow-lg flex-shrink-0"
              >
                <i
                  class="pi pi-bell text-orange-700 dark:text-orange-300 !text-xl"
                ></i>
              </span>
              <div>
                <h3
                  class="text-2xl font-extrabold text-black-800 dark:text-white"
                >
                  Alertas
                </h3>
                <div
                  class="text-xs text-gray-500 dark:text-gray-400 font-medium"
                >
                  Itens que precisam de atenção imediata.
                </div>
              </div>
            </div>
          </template>

          <div
            v-if="dashboard.alertas"
            class="space-y-2"
          >
            <div
              class="flex items-center justify-between p-3 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/10 transition cursor-pointer"
              @click="navegar('/pagina/gestao-contratos/avisos')"
            >
              <div class="flex items-center gap-3">
                <i class="pi pi-send text-yellow-600 dark:text-yellow-400"></i>
                <span class="text-sm text-gray-700 dark:text-gray-300">
                  Pendentes Envio NF/Boleto
                </span>
              </div>
              <Tag
                :value="String(dashboard.alertas.pendentes_envio || 0)"
                severity="warn"
                class="font-bold"
              />
            </div>
            <div
              class="flex items-center justify-between p-3 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/10 transition cursor-pointer"
              @click="navegar('/pagina/gestao-contratos/avisos')"
            >
              <div class="flex items-center gap-3">
                <i
                  class="pi pi-exclamation-triangle text-red-600 dark:text-red-400"
                ></i>
                <span class="text-sm text-gray-700 dark:text-gray-300">
                  Divergências
                </span>
              </div>
              <Tag
                :value="String(dashboard.alertas.divergencias || 0)"
                severity="danger"
                class="font-bold"
              />
            </div>
            <div
              class="flex items-center justify-between p-3 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/10 transition cursor-pointer"
              @click="navegar('/pagina/gestao-contratos/renovacao')"
            >
              <div class="flex items-center gap-3">
                <i class="pi pi-clock text-blue-600 dark:text-blue-400"></i>
                <span class="text-sm text-gray-700 dark:text-gray-300">
                  Vencendo em 90 dias
                </span>
              </div>
              <Tag
                :value="String(dashboard.alertas.vencendo_90d || 0)"
                severity="info"
                class="font-bold"
              />
            </div>
            <div
              class="flex items-center justify-between p-3 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/10 transition cursor-pointer"
              @click="navegar('/pagina/gestao-contratos/renovacao')"
            >
              <div class="flex items-center gap-3">
                <i
                  class="pi pi-times-circle text-red-600 dark:text-red-400"
                ></i>
                <span class="text-sm text-gray-700 dark:text-gray-300">
                  Contratos Vencidos
                </span>
              </div>
              <Tag
                :value="String(dashboard.alertas.vencidos || 0)"
                severity="danger"
                class="font-bold"
              />
            </div>
          </div>
          <p
            v-else
            class="text-sm text-gray-500 dark:text-gray-400 text-center py-4"
          >
            Nenhum alerta.
          </p>
        </Panel>
      </div>

      <!-- Evolução Mensal -->
      <Panel
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
      >
        <template #header>
          <div class="flex items-center gap-2 mb-2">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-200 dark:bg-indigo-900 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-chart-line text-indigo-700 dark:text-indigo-300 !text-xl"
              ></i>
            </span>
            <div>
              <h3
                class="text-2xl font-extrabold text-black-800 dark:text-white"
              >
                Evolução Mensal
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Histórico dos últimos 6 meses de medições e valores.
              </div>
            </div>
          </div>
        </template>

        <DataTable
          :value="dashboard.evolucao_mensal || []"
          :paginator="false"
          stripedRows
          class="p-datatable-sm"
          :pt="{ root: { class: 'rounded-xl overflow-hidden' } }"
        >
          <template #empty>
            <div class="flex flex-col items-center justify-center py-6">
              <i
                class="pi pi-chart-line text-4xl text-gray-300 dark:text-gray-600 mb-2"
              ></i>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                Sem dados de evolução.
              </p>
            </div>
          </template>
          <Column
            field="competencia"
            header="Competência"
          >
            <template #body="{ data }">
              <span class="font-medium text-gray-900 dark:text-white">
                {{ data.competencia }}
              </span>
            </template>
          </Column>
          <Column
            field="total_medicoes"
            header="Total Medições"
            class="text-center"
          >
            <template #body="{ data }">
              <span class="font-bold text-blue-600 dark:text-blue-400">
                {{ data.total_medicoes || 0 }}
              </span>
            </template>
          </Column>
          <Column
            field="valor_previsto"
            header="Valor Previsto"
          >
            <template #body="{ data }">
              <span class="text-gray-700 dark:text-gray-300">
                {{ Recorrentes.formatarMoeda(data.valor_previsto) }}
              </span>
            </template>
          </Column>
          <Column
            field="valor_real"
            header="Valor Real"
          >
            <template #body="{ data }">
              <span class="font-bold text-green-600 dark:text-green-400">
                {{ Recorrentes.formatarMoeda(data.valor_real) }}
              </span>
            </template>
          </Column>
          <Column
            field="divergencia"
            header="Divergência"
          >
            <template #body="{ data }">
              <Tag
                v-if="data.divergencia"
                :value="
                  data.divergencia > 0
                    ? '+' + data.divergencia + '%'
                    : data.divergencia + '%'
                "
                :severity="
                  Math.abs(data.divergencia) > 10 ? 'danger' : 'success'
                "
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
        </DataTable>
      </Panel>

      <!-- Próximos Vencimentos + Medições Urgentes -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Próximos Vencimentos -->
        <Panel
          class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
        >
          <template #header>
            <div class="flex items-center gap-2 mb-2">
              <span
                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-200 dark:bg-red-900 shadow-lg flex-shrink-0"
              >
                <i
                  class="pi pi-calendar-times text-red-700 dark:text-red-300 !text-xl"
                ></i>
              </span>
              <div>
                <h3
                  class="text-2xl font-extrabold text-black-800 dark:text-white"
                >
                  Próximos Vencimentos
                </h3>
                <div
                  class="text-xs text-gray-500 dark:text-gray-400 font-medium"
                >
                  Contratos que vencem nos próximos dias.
                </div>
              </div>
            </div>
          </template>

          <div
            v-if="
              dashboard.proximos_vencimentos &&
              dashboard.proximos_vencimentos.length > 0
            "
            class="space-y-2"
          >
            <div
              v-for="c in dashboard.proximos_vencimentos"
              :key="c.id"
              class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition cursor-pointer"
              @click="
                navegar('/pagina/gestao-contratos/renovacao/form/' + c.id)
              "
            >
              <div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                  {{
                    c.razao_social_loja || c.nome_locador || "Contrato #" + c.id
                  }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  Vence em {{ Recorrentes.formatarData(c.data_fim) }}
                </p>
              </div>
              <div class="flex items-center gap-2">
                <span
                  class="text-sm font-bold text-gray-700 dark:text-gray-300"
                >
                  {{ Recorrentes.formatarMoeda(c.valor_mensal) }}
                </span>
                <Tag
                  :value="c.dias_para_vencer + 'd'"
                  :severity="
                    c.dias_para_vencer <= 30
                      ? 'danger'
                      : c.dias_para_vencer <= 60
                        ? 'warn'
                        : 'info'
                  "
                  class="font-bold"
                />
              </div>
            </div>
          </div>
          <div
            v-else
            class="flex flex-col items-center py-6"
          >
            <i
              class="pi pi-check-circle text-3xl text-green-300 dark:text-green-600 mb-2"
            ></i>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Nenhum vencimento próximo.
            </p>
          </div>
        </Panel>

        <!-- Medições Urgentes -->
        <Panel
          class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
        >
          <template #header>
            <div class="flex items-center gap-2 mb-2">
              <span
                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-yellow-200 dark:bg-yellow-900 shadow-lg flex-shrink-0"
              >
                <i
                  class="pi pi-exclamation-circle text-yellow-700 dark:text-yellow-300 !text-xl"
                ></i>
              </span>
              <div>
                <h3
                  class="text-2xl font-extrabold text-black-800 dark:text-white"
                >
                  Medições Urgentes
                </h3>
                <div
                  class="text-xs text-gray-500 dark:text-gray-400 font-medium"
                >
                  Medições que precisam de ação imediata.
                </div>
              </div>
            </div>
          </template>

          <div
            v-if="
              dashboard.medicoes_urgentes &&
              dashboard.medicoes_urgentes.length > 0
            "
            class="space-y-2"
          >
            <div
              v-for="m in dashboard.medicoes_urgentes"
              :key="m.id"
              class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition cursor-pointer"
              @click="
                navegar('/pagina/gestao-contratos/medicoes/enviar/' + m.id)
              "
            >
              <div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                  {{
                    m.razao_social_loja || m.nome_locador || "Medição #" + m.id
                  }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ m.competencia }} •
                  {{ Recorrentes.formatarMoeda(m.valor_previsto) }}
                </p>
              </div>
              <Tag
                :value="m.etapa"
                :severity="
                  m.etapa === 'PENDENTE_ENVIO'
                    ? 'warn'
                    : m.etapa === 'DIVERGENCIA'
                      ? 'danger'
                      : 'info'
                "
                class="font-medium"
              />
            </div>
          </div>
          <div
            v-else
            class="flex flex-col items-center py-6"
          >
            <i
              class="pi pi-check-circle text-3xl text-green-300 dark:text-green-600 mb-2"
            ></i>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Nenhuma medição urgente.
            </p>
          </div>
        </Panel>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
