<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, computed } from "vue"
import { router } from "@inertiajs/vue3"
import * as EquipJs from "../gestao-equipamentos.js"
import Panel from "primevue/panel"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Tag from "primevue/tag"
import Button from "primevue/button"
import { useTour } from "@/composables/useTour"
import "@/../css/tour.css"
import Swal from "sweetalert2"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)
  await EquipJs.getDashboardEquipamentos()

  // Tour Guiado
  setTimeout(() => {
    autoStart(tourSteps, Swal.fire.bind(Swal), 800)
  }, 500)
})

const dashboard = computed(() => EquipJs.dashboardData.value.data)
const isLoading = computed(() => EquipJs.dashboardData.value.loading)

// ╔══════════════════════════════════════════════════════════════╗
// ║                       TOUR GUIADO                            ║
// ╚══════════════════════════════════════════════════════════════╝
const { startTour, autoStart } = useTour("gestao-equipamentos-dashboard")

const tourSteps = [
  {
    popover: {
      title: "Dashboard de Equipamentos",
      description: `
        Bem-vindo ao <strong>Dashboard de Equipamentos</strong>!<br><br>
        Aqui você tem uma visão consolidada de:<br>
        • <strong>Vencimentos</strong> e prazos<br>
        • <strong>Status</strong> dos equipamentos<br>
        • <strong>Conformidade</strong> por filial
      `,
      side: "over",
      align: "center"
    }
  },
  {
    element: "#tour-dash-cards",
    popover: {
      title: "Indicadores",
      description: `
        Cards com os principais indicadores:<br>
        • <strong>Total</strong> de equipamentos cadastrados<br>
        • <strong>Vencidos</strong> — precisam de atenção imediata<br>
        • <strong>Vencendo</strong> — nos próximos 10 dias<br>
        • <strong>Em Manutenção</strong> — em processo<br><br>
        Clique em qualquer card para <strong>filtrar a lista</strong> de equipamentos.
      `,
      side: "bottom",
      align: "center"
    }
  },
  {
    element: "#tour-dash-proximos",
    popover: {
      title: "Próximos a Vencer",
      description: `
        Lista os <strong>10 equipamentos</strong> com a data de vencimento mais próxima.<br><br>
        Clique em qualquer item para acessar os detalhes do equipamento.
      `,
      side: "top",
      align: "center"
    }
  },
  {
    element: "#tour-dash-resumo",
    popover: {
      title: "Resumo por Filial",
      description: `
        Tabela com a <strong>distribuição de equipamentos por filial</strong>.<br><br>
        Mostra a quantidade de vigentes, vencendo e vencidos em cada filial.
      `,
      side: "top",
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

function navegarComFiltro(status) {
  router.visit(`/pagina/gestao-contratos/equipamentos?status=${status}`)
}

function navegarEquipamento(id) {
  router.visit(`/pagina/gestao-contratos/equipamentos/${id}`)
}
</script>

<template>
  <AuthenticatedLayout>
    <!-- Breadcrumb -->
    <div class="w-full flex flex-wrap items-center bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-xl mb-4 sm:mb-6 border border-gray-200 dark:border-slate-700">
      <div class="flex flex-wrap items-center gap-1 sm:gap-2 text-sm sm:text-base text-gray-600 dark:text-gray-300 font-medium w-full">
        <div class="flex items-center gap-1 sm:gap-2">
          <i class="pi pi-home"></i>
          <span>Home</span>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <a href="/pagina/gestao-contratos" class="hover:text-purple-600 dark:hover:text-purple-400">Gestão de Contratos</a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">Dashboard Equipamentos</span>
        </div>
      </div>
    </div>

    <!-- Page Header -->
    <div class="space-y-2 mt-4 mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3">
            <div class="w-1 h-8 bg-gradient-to-b from-purple-500 to-purple-700 rounded-full"></div>
            Dashboard — Controle de Equipamentos
          </h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 pl-4">
            Visão geral de vencimentos, status e conformidade dos equipamentos.
          </p>
        </div>
        <div class="flex gap-2">
          <Button id="tour-btn-ajuda" icon="pi pi-question-circle" severity="secondary" text size="small" @click="startTour(tourSteps)" v-tooltip.top="'Tour Guiado'" />
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center py-16">
      <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-full shadow-md text-sm">
        <i class="pi pi-spinner pi-spin text-xs"></i>
        <span class="font-medium">Carregando dashboard...</span>
      </div>
    </div>

    <div v-else-if="dashboard" class="space-y-6">
      <!-- Cards Indicadores -->
      <div id="tour-dash-cards" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <!-- Total -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          @click="navegarComFiltro('')"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white bg-purple-500">
              <i class="pi pi-box !text-[11px] flex-shrink-0"></i>
              Total
            </span>
          </div>
          <div class="text-gray-950 dark:text-white text-2xl font-bold">
            {{ dashboard.total || 0 }}
          </div>
          <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">equipamentos cadastrados</div>
        </div>

        <!-- Vencidos -->
        <div
          class="bg-white dark:bg-gray-900 border border-red-300 dark:border-red-800 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          @click="navegarComFiltro('VENCIDO')"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white bg-red-500">
              <i class="pi pi-times-circle !text-[11px] flex-shrink-0"></i>
              Vencidos
            </span>
          </div>
          <div class="text-red-600 dark:text-red-400 text-2xl font-bold">
            {{ dashboard.vencidos || 0 }}
          </div>
          <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">precisam de atenção</div>
        </div>

        <!-- Vencendo -->
        <div
          class="bg-white dark:bg-gray-900 border border-yellow-300 dark:border-yellow-800 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          @click="navegarComFiltro('VENCENDO')"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white bg-yellow-500">
              <i class="pi pi-clock !text-[11px] flex-shrink-0"></i>
              Vencendo
            </span>
          </div>
          <div class="text-yellow-600 dark:text-yellow-400 text-2xl font-bold">
            {{ dashboard.vencendo || 0 }}
          </div>
          <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">nos próximos 10 dias</div>
        </div>

        <!-- Em Manutenção -->
        <div
          class="bg-white dark:bg-gray-900 border border-blue-300 dark:border-blue-800 rounded-xl p-4 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          @click="navegarComFiltro('EM_MANUTENCAO')"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white bg-blue-500">
              <i class="pi pi-wrench !text-[11px] flex-shrink-0"></i>
              Em Manutenção
            </span>
          </div>
          <div class="text-blue-600 dark:text-blue-400 text-2xl font-bold">
            {{ dashboard.em_manutencao || 0 }}
          </div>
          <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">em processo</div>
        </div>
      </div>

      <!-- Próximos a Vencer + Resumo por Filial -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Próximos a Vencer -->
        <Panel id="tour-dash-proximos" class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden">
          <template #header>
            <div class="flex items-center gap-2 mb-2">
              <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-200 dark:bg-red-900 shadow-lg flex-shrink-0">
                <i class="pi pi-calendar-times text-red-700 dark:text-red-300 !text-xl"></i>
              </span>
              <div>
                <h3 class="text-2xl font-extrabold text-black-800 dark:text-white">Próximos a Vencer</h3>
                <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">10 equipamentos com vencimento mais próximo</div>
              </div>
            </div>
          </template>

          <div v-if="dashboard.proximos_a_vencer && dashboard.proximos_a_vencer.length > 0" class="space-y-2">
            <div
              v-for="equip in dashboard.proximos_a_vencer"
              :key="equip.id"
              class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition cursor-pointer"
              @click="navegarEquipamento(equip.id)"
            >
              <div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                  {{ equip.filial ? equip.filial_id + ' - ' + (equip.filial.fantasia || equip.filial.razaosocial) : equip.filial_id }} — {{ equip.tipo_equipamento?.nome || "Equipamento" }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ equip.numero_identificacao || "S/N" }} • {{ equip.carga || "-" }}
                </p>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ EquipJs.formatarData(equip.data_validade) }}</span>
                <Tag
                  :value="equip.dias_para_vencimento + 'd'"
                  :severity="equip.dias_para_vencimento <= 0 ? 'danger' : equip.dias_para_vencimento <= 15 ? 'warn' : 'info'"
                  class="font-bold"
                />
              </div>
            </div>
          </div>
          <div v-else class="flex flex-col items-center py-6">
            <i class="pi pi-check-circle text-3xl text-green-300 dark:text-green-600 mb-2"></i>
            <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum equipamento próximo ao vencimento.</p>
          </div>
        </Panel>

        <!-- Resumo por Filial -->
        <Panel id="tour-dash-resumo" class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden">
          <template #header>
            <div class="flex items-center gap-2 mb-2">
              <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-purple-200 dark:bg-purple-900 shadow-lg flex-shrink-0">
                <i class="pi pi-building text-purple-700 dark:text-purple-300 !text-xl"></i>
              </span>
              <div>
                <h3 class="text-2xl font-extrabold text-black-800 dark:text-white">Resumo por Filial</h3>
                <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Distribuição de equipamentos por filial</div>
              </div>
            </div>
          </template>

          <DataTable
            :value="dashboard.resumo_por_filial || []"
            :paginator="false"
            stripedRows
            scrollable
            scrollHeight="350px"
            class="p-datatable-sm"
            :pt="{ root: { class: 'rounded-xl overflow-hidden' } }"
          >
            <template #empty>
              <div class="flex flex-col items-center justify-center py-6">
                <i class="pi pi-building text-4xl text-gray-300 dark:text-gray-600 mb-2"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400">Sem dados por filial.</p>
              </div>
            </template>

            <Column field="filial_nome" header="Filial">
              <template #body="{ data }">
                <span class="font-medium text-gray-900 dark:text-white text-sm">{{ data.filial_nome }}</span>
              </template>
            </Column>
            <Column field="vigentes" header="Vigentes" class="text-center">
              <template #body="{ data }">
                <span class="font-bold text-green-600 dark:text-green-400">{{ data.vigentes || 0 }}</span>
              </template>
            </Column>
            <Column field="vencendo" header="Vencendo" class="text-center">
              <template #body="{ data }">
                <span class="font-bold text-yellow-600 dark:text-yellow-400">{{ data.vencendo || 0 }}</span>
              </template>
            </Column>
            <Column field="vencidos" header="Vencidos" class="text-center">
              <template #body="{ data }">
                <span class="font-bold text-red-600 dark:text-red-400">{{ data.vencidos || 0 }}</span>
              </template>
            </Column>
          </DataTable>
        </Panel>
      </div>
    </div>

    <!-- Sem dados -->
    <div v-else class="text-center py-16">
      <i class="pi pi-chart-bar text-5xl text-gray-300 dark:text-gray-600 mb-4"></i>
      <p class="text-gray-500 dark:text-gray-400">Não foi possível carregar os dados do dashboard.</p>
    </div>
  </AuthenticatedLayout>
</template>
