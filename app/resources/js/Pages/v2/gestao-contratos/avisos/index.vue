<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted } from "vue"
import { router } from "@inertiajs/vue3"
import * as Recorrentes from "../contratos-recorrentes.js"
import Panel from "primevue/panel"
import Button from "primevue/button"
import Tag from "primevue/tag"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(() => {
  layoutJs.setPaginaNova(true)
  Recorrentes.getAvisos()
})

function irParaMedicao(id) {
  router.visit(`/pagina/gestao-contratos/medicoes/enviar/${id}`)
}

function irParaRenovacao(id) {
  router.visit(`/pagina/gestao-contratos/renovacao/${id}`)
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
            class="hover:text-orange-600 dark:hover:text-orange-400"
          >
            Gestão de Contratos
          </a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">Avisos</span>
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
                class="w-1 h-8 bg-gradient-to-b from-orange-500 to-red-600 rounded-full"
              ></div>
              Avisos - Contratos Recorrentes
            </h2>
          </div>
          <span
            class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-bold pl-4 mt-1"
          >
            Alertas de pendências, divergências e contratos próximos do
            vencimento.
          </span>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div
      v-if="Recorrentes.avisos.value.loading"
      class="flex items-center justify-center py-16"
    >
      <div
        class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-orange-500 to-red-600 text-white rounded-full shadow-md text-sm"
      >
        <i class="pi pi-spinner pi-spin text-xs"></i>
        <span class="font-medium">Carregando avisos...</span>
      </div>
    </div>

    <div v-else>
      <!-- Cards Resumo -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-yellow-400"
            >
              <i class="pi pi-clock !text-[11px] flex-shrink-0"></i>
              Pendentes Envio
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ Recorrentes.avisos.value.resumo.total_pendentes }}</span>
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
              Divergência
            </span>
          </div>
          <div
            class="flex w-full justify-start text-lg font-bold"
            :class="
              Recorrentes.avisos.value.resumo.total_alertas > 0
                ? 'text-red-600'
                : 'text-gray-950 dark:text-white'
            "
          >
            <span>{{ Recorrentes.avisos.value.resumo.total_alertas }}</span>
          </div>
        </div>
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-orange-500"
            >
              <i class="pi pi-calendar-times !text-[11px] flex-shrink-0"></i>
              Vencendo 90d
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ Recorrentes.avisos.value.resumo.total_vencendo }}</span>
          </div>
        </div>
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-gray-500"
            >
              <i class="pi pi-ban !text-[11px] flex-shrink-0"></i>
              Vencidos
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ Recorrentes.avisos.value.resumo.total_vencidos }}</span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Pendentes de Envio -->
        <Panel
          class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
        >
          <template #header>
            <div class="flex items-center gap-2 mb-2">
              <span
                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-yellow-200 dark:bg-yellow-900 shadow-lg flex-shrink-0"
              >
                <i
                  class="pi pi-clock text-yellow-700 dark:text-yellow-300 !text-xl"
                ></i>
              </span>
              <div>
                <h3
                  class="text-2xl font-extrabold text-black-800 dark:text-white"
                >
                  Pendentes de Envio NF/Boleto
                </h3>
                <div
                  class="text-xs text-gray-500 dark:text-gray-400 font-medium"
                >
                  Medições aguardando envio de NF e boleto.
                </div>
              </div>
            </div>
          </template>

          <div
            v-if="Recorrentes.avisos.value.pendentes_envio.length === 0"
            class="text-center py-6 text-gray-500 dark:text-gray-400"
          >
            <i class="pi pi-check-circle text-3xl text-green-500 mb-2"></i>
            <p class="text-sm font-medium">Nenhuma pendência!</p>
          </div>
          <div
            v-else
            class="space-y-2 max-h-80 overflow-y-auto"
          >
            <div
              v-for="m in Recorrentes.avisos.value.pendentes_envio"
              :key="m.id"
              class="flex items-center justify-between p-3 rounded-xl border-l-4 border-l-yellow-500 bg-yellow-50/50 dark:bg-yellow-900/10 hover:shadow-sm transition-all cursor-pointer"
              @click="irParaMedicao(m.id)"
            >
              <div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                  Contrato #{{ m.contrato_id }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ m.competencia_formatada || m.competencia }} |
                  {{ Recorrentes.formatarMoeda(m.valor_previsto) }}
                </p>
              </div>
              <Button
                icon="pi pi-chevron-right"
                severity="secondary"
                text
                rounded
                size="small"
              />
            </div>
          </div>
        </Panel>

        <!-- Com Alerta de Divergência -->
        <Panel
          class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
        >
          <template #header>
            <div class="flex items-center gap-2 mb-2">
              <span
                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-200 dark:bg-red-900 shadow-lg flex-shrink-0"
              >
                <i
                  class="pi pi-exclamation-triangle text-red-700 dark:text-red-300 !text-xl"
                ></i>
              </span>
              <div>
                <h3
                  class="text-2xl font-extrabold text-black-800 dark:text-white"
                >
                  Divergências Detectadas
                </h3>
                <div
                  class="text-xs text-gray-500 dark:text-gray-400 font-medium"
                >
                  Medições com valor real acima do limite permitido.
                </div>
              </div>
            </div>
          </template>

          <div
            v-if="Recorrentes.avisos.value.com_alerta.length === 0"
            class="text-center py-6 text-gray-500 dark:text-gray-400"
          >
            <i class="pi pi-check-circle text-3xl text-green-500 mb-2"></i>
            <p class="text-sm font-medium">Nenhuma divergência!</p>
          </div>
          <div
            v-else
            class="space-y-2 max-h-80 overflow-y-auto"
          >
            <div
              v-for="m in Recorrentes.avisos.value.com_alerta"
              :key="m.id"
              class="flex items-center justify-between p-3 rounded-xl border-l-4 border-l-red-500 bg-red-50/50 dark:bg-red-900/10 hover:shadow-sm transition-all"
            >
              <div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                  Contrato #{{ m.contrato_id }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ m.competencia_formatada || m.competencia }} | Previsto:
                  {{ Recorrentes.formatarMoeda(m.valor_previsto) }} → Real:
                  <span class="text-red-600 font-bold">
                    {{ Recorrentes.formatarMoeda(m.valor_real) }}
                  </span>
                </p>
              </div>
              <Tag
                :value="Recorrentes.getEtapaLabel(m.etapa)"
                :severity="m.etapa === 'PAGO' ? 'success' : 'info'"
                class="font-medium"
              />
            </div>
          </div>
        </Panel>

        <!-- Contratos Vencendo -->
        <Panel
          class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
        >
          <template #header>
            <div class="flex items-center gap-2 mb-2">
              <span
                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-orange-200 dark:bg-orange-900 shadow-lg flex-shrink-0"
              >
                <i
                  class="pi pi-calendar-times text-orange-700 dark:text-orange-300 !text-xl"
                ></i>
              </span>
              <div>
                <h3
                  class="text-2xl font-extrabold text-black-800 dark:text-white"
                >
                  Contratos Vencendo (90 dias)
                </h3>
                <div
                  class="text-xs text-gray-500 dark:text-gray-400 font-medium"
                >
                  Contratos próximos do vencimento que precisam de atenção.
                </div>
              </div>
            </div>
          </template>

          <div
            v-if="Recorrentes.avisos.value.contratos_vencendo.length === 0"
            class="text-center py-6 text-gray-500 dark:text-gray-400"
          >
            <i class="pi pi-check-circle text-3xl text-green-500 mb-2"></i>
            <p class="text-sm font-medium">Nenhum contrato vencendo!</p>
          </div>
          <div
            v-else
            class="space-y-2 max-h-80 overflow-y-auto"
          >
            <div
              v-for="c in Recorrentes.avisos.value.contratos_vencendo"
              :key="c.id"
              class="flex items-center justify-between p-3 rounded-xl border-l-4 hover:shadow-sm transition-all cursor-pointer"
              :class="
                c.dias_para_vencimento <= 30
                  ? 'border-l-red-500 bg-red-50/50 dark:bg-red-900/10'
                  : 'border-l-orange-500 bg-orange-50/50 dark:bg-orange-900/10'
              "
              @click="irParaRenovacao(c.id)"
            >
              <div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                  {{
                    c.razao_social_loja || c.nome_locador || "Contrato #" + c.id
                  }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  Vencimento: {{ Recorrentes.formatarData(c.data_fim) }}
                </p>
              </div>
              <div class="text-right flex items-center gap-2">
                <Tag
                  :value="c.dias_para_vencimento + ' dias'"
                  :severity="c.dias_para_vencimento <= 30 ? 'danger' : 'warn'"
                  class="font-medium"
                />
                <Button
                  icon="pi pi-chevron-right"
                  severity="secondary"
                  text
                  rounded
                  size="small"
                />
              </div>
            </div>
          </div>
        </Panel>

        <!-- Contratos Vencidos -->
        <Panel
          class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
        >
          <template #header>
            <div class="flex items-center gap-2 mb-2">
              <span
                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-200 dark:bg-gray-800 shadow-lg flex-shrink-0"
              >
                <i
                  class="pi pi-ban text-gray-700 dark:text-gray-300 !text-xl"
                ></i>
              </span>
              <div>
                <h3
                  class="text-2xl font-extrabold text-black-800 dark:text-white"
                >
                  Contratos Vencidos
                </h3>
                <div
                  class="text-xs text-gray-500 dark:text-gray-400 font-medium"
                >
                  Contratos que já passaram da data de vencimento.
                </div>
              </div>
            </div>
          </template>

          <div
            v-if="Recorrentes.avisos.value.contratos_vencidos.length === 0"
            class="text-center py-6 text-gray-500 dark:text-gray-400"
          >
            <i class="pi pi-check-circle text-3xl text-green-500 mb-2"></i>
            <p class="text-sm font-medium">Nenhum contrato vencido!</p>
          </div>
          <div
            v-else
            class="space-y-2 max-h-80 overflow-y-auto"
          >
            <div
              v-for="c in Recorrentes.avisos.value.contratos_vencidos"
              :key="c.id"
              class="flex items-center justify-between p-3 rounded-xl border-l-4 border-l-red-500 bg-red-50/50 dark:bg-red-900/10 hover:shadow-sm transition-all cursor-pointer"
              @click="irParaRenovacao(c.id)"
            >
              <div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                  {{
                    c.razao_social_loja || c.nome_locador || "Contrato #" + c.id
                  }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  Venceu em: {{ Recorrentes.formatarData(c.data_fim) }}
                </p>
              </div>
              <Tag
                value="Vencido"
                severity="danger"
                icon="pi pi-exclamation-circle"
                class="font-medium"
              />
            </div>
          </div>
        </Panel>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
