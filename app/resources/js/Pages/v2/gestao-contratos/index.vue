<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, ref } from "vue"
import { router } from "@inertiajs/vue3"
import * as GestaoJs from "./gestao-contratos.js"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(() => {
  layoutJs.setPaginaNova(true)
  GestaoJs.getDashboard()
})

function irPara(rota) {
  router.visit(rota)
}

// Função para obter classe de cor baseado nos dias para vencimento
function getCorDiasVencimento(dias) {
  if (dias <= 30) return "text-red-600 dark:text-red-400"
  if (dias <= 60) return "text-orange-600 dark:text-orange-400"
  if (dias <= 90) return "text-yellow-600 dark:text-yellow-400"
  return "text-gray-600 dark:text-gray-400"
}

function getBgCorDiasVencimento(dias) {
  if (dias <= 30) return "bg-red-50 dark:bg-red-900/20"
  if (dias <= 60) return "bg-orange-50 dark:bg-orange-900/20"
  if (dias <= 90) return "bg-yellow-50 dark:bg-yellow-900/20"
  return "bg-gray-50 dark:bg-gray-800"
}

function getIconBgCorDiasVencimento(dias) {
  if (dias <= 30) return "bg-red-100 dark:bg-red-900/30"
  if (dias <= 60) return "bg-orange-100 dark:bg-orange-900/30"
  if (dias <= 90) return "bg-yellow-100 dark:bg-yellow-900/30"
  return "bg-gray-100 dark:bg-gray-700"
}

function getIconCorDiasVencimento(dias) {
  if (dias <= 30) return "text-red-600 dark:text-red-400"
  if (dias <= 60) return "text-orange-600 dark:text-orange-400"
  if (dias <= 90) return "text-yellow-600 dark:text-yellow-400"
  return "text-gray-600 dark:text-gray-400"
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
          <span>Gestão de Contratos</span>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">Dashboard</span>
        </div>
      </div>
    </div>

    <!-- Cabeçalho -->
    <div class="space-y-2 mb-6">
      <div class="flex items-center gap-3">
        <h2
          class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
        >
          <div
            class="w-1 h-8 bg-gradient-to-b from-purple-500 to-purple-700 rounded-full"
          ></div>
          Dashboard - Gestão de Contratos e Alvarás
        </h2>
      </div>
      <span
        class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-bold pl-4"
      >
        Visão geral dos contratos de locação, serviço e alvarás da empresa.
      </span>
    </div>

    <!-- Loading (apenas na primeira carga; inline, não cobre sidebar/header) -->
    <div
      v-if="GestaoJs.dashboard.value.loading && !GestaoJs.dashboard.value.loaded"
      class="flex items-center justify-center py-24"
    >
      <div class="flex flex-col items-center gap-3">
        <i class="pi pi-spin pi-spinner text-4xl" :style="{ color: 'var(--app-primary)' }"></i>
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
          Carregando...
        </span>
      </div>
    </div>

    <!-- Conteúdo -->
    <div v-else>
      <!-- Cards de Resumo -->
      <div
        class="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-4 px-1 sm:px-0 mb-6"
      >
        <!-- Contratos Locação -->
        <div
          class="bg-white dark:bg-slate-800 border-l-4 border-l-blue-500 rounded-xl p-4 border border-gray-100 dark:border-slate-700 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer group"
          @click="irPara('/pagina/gestao-contratos/locacao')"
        >
          <div class="flex items-center justify-between mb-2">
            <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">
              Contratos de Locação
            </p>
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex-shrink-0 group-hover:scale-110 transition-transform"
            >
              <i class="pi pi-home text-blue-600 dark:text-blue-400"></i>
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
          >
            <span>{{ GestaoJs.dashboard.value.contratos.total_locacao }}</span>
          </div>
          <p class="text-sm text-blue-600 dark:text-blue-400 mt-1 font-medium">
            {{
              GestaoJs.formatarMoeda(
                GestaoJs.dashboard.value.contratos.valor_total_locacao
              )
            }}/mês
          </p>
        </div>

        <!-- Contratos Serviço -->
        <div
          class="bg-white dark:bg-slate-800 border-l-4 border-l-green-500 rounded-xl p-4 border border-gray-100 dark:border-slate-700 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer group"
          @click="irPara('/pagina/gestao-contratos/servicos')"
        >
          <div class="flex items-center justify-between mb-2">
            <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">
              Contratos de Serviço
            </p>
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex-shrink-0 group-hover:scale-110 transition-transform"
            >
              <i class="pi pi-briefcase text-green-600 dark:text-green-400"></i>
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
          >
            <span>{{ GestaoJs.dashboard.value.contratos.total_servico }}</span>
          </div>
          <p
            class="text-sm text-green-600 dark:text-green-400 mt-1 font-medium"
          >
            {{
              GestaoJs.formatarMoeda(
                GestaoJs.dashboard.value.contratos.valor_total_servico
              )
            }}/mês
          </p>
        </div>

        <!-- Alvarás -->
        <div
          class="bg-white dark:bg-slate-800 border-l-4 border-l-purple-500 rounded-xl p-4 border border-gray-100 dark:border-slate-700 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer group"
          @click="irPara('/pagina/gestao-contratos/alvaras')"
        >
          <div class="flex items-center justify-between mb-2">
            <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">
              Alvarás e Licenças
            </p>
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900/30 flex-shrink-0 group-hover:scale-110 transition-transform"
            >
              <i
                class="pi pi-file-edit text-purple-600 dark:text-purple-400"
              ></i>
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
          >
            <span>{{ GestaoJs.dashboard.value.alvaras.total }}</span>
          </div>
          <p
            v-if="GestaoJs.dashboard.value.alvaras.vencidos > 0"
            class="text-sm text-red-600 dark:text-red-400 mt-1 font-medium flex items-center gap-1"
          >
            <i class="pi pi-exclamation-circle text-xs"></i>
            {{ GestaoJs.dashboard.value.alvaras.vencidos }} vencido(s)
          </p>
          <p
            v-else
            class="text-sm text-green-600 dark:text-green-400 mt-1 font-medium flex items-center gap-1"
          >
            <i class="pi pi-check-circle text-xs"></i>
            Todos em dia
          </p>
        </div>

        <!-- Equipamentos -->
        <div
          class="bg-white dark:bg-slate-800 border-l-4 border-l-amber-500 rounded-xl p-4 border border-gray-100 dark:border-slate-700 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer group"
          @click="irPara('/pagina/gestao-contratos/equipamentos')"
        >
          <div class="flex items-center justify-between mb-2">
            <p class="text-gray-500 dark:text-gray-400 text-sm font-medium">
              Equipamentos
            </p>
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex-shrink-0 group-hover:scale-110 transition-transform"
            >
              <i class="pi pi-box text-amber-600 dark:text-amber-400"></i>
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-2xl font-bold"
          >
            <span>{{ GestaoJs.dashboard.value.equipamentos.total }}</span>
          </div>
          <p
            v-if="GestaoJs.dashboard.value.equipamentos.vencidos > 0"
            class="text-sm text-red-600 dark:text-red-400 mt-1 font-medium flex items-center gap-1"
          >
            <i class="pi pi-exclamation-circle text-xs"></i>
            {{ GestaoJs.dashboard.value.equipamentos.vencidos }} vencido(s)
          </p>
          <p
            v-else
            class="text-sm text-green-600 dark:text-green-400 mt-1 font-medium flex items-center gap-1"
          >
            <i class="pi pi-check-circle text-xs"></i>
            Todos em dia
          </p>
        </div>
      </div>

      <!-- Faixa de indicadores -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-slate-700 shadow-sm flex items-center gap-3">
          <span class="inline-flex items-center justify-center w-11 h-11 rounded-lg" :style="{ backgroundColor: 'color-mix(in srgb, var(--app-primary) 15%, white)' }">
            <i class="pi pi-dollar text-lg" :style="{ color: 'var(--app-primary)' }"></i>
          </span>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Comprometido / mês</p>
            <p dusk="kpi-comprometido-mes" class="text-lg font-bold text-gray-900 dark:text-white">
              {{ GestaoJs.formatarMoeda(GestaoJs.dashboard.value.contratos.valor_comprometido_mes) }}
            </p>
          </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-slate-700 shadow-sm flex items-center gap-3">
          <span class="inline-flex items-center justify-center w-11 h-11 rounded-lg bg-orange-100 dark:bg-orange-900/30">
            <i class="pi pi-clock text-orange-600 dark:text-orange-400 text-lg"></i>
          </span>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Vencendo em 30 dias</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white">
              {{ GestaoJs.dashboard.value.contratos.vencendo_30_dias + GestaoJs.dashboard.value.alvaras.vencendo_30_dias + GestaoJs.dashboard.value.equipamentos.vencendo_30_dias }}
              <span class="text-xs font-normal text-gray-400">itens</span>
            </p>
          </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-gray-100 dark:border-slate-700 shadow-sm flex items-center gap-3">
          <span class="inline-flex items-center justify-center w-11 h-11 rounded-lg bg-red-100 dark:bg-red-900/30">
            <i class="pi pi-exclamation-triangle text-red-600 dark:text-red-400 text-lg"></i>
          </span>
          <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Vencidos</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white">
              {{ (GestaoJs.dashboard.value.contratos.vencidos || 0) + GestaoJs.dashboard.value.alvaras.vencidos + GestaoJs.dashboard.value.equipamentos.vencidos }}
              <span class="text-xs font-normal text-gray-400">itens</span>
            </p>
          </div>
        </div>
      </div>

      <!-- Próximos Vencimentos -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Contratos -->
        <div
          class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
        >
          <div class="flex items-center justify-between mb-4">
            <h3
              class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
            >
              <span
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30"
              >
                <i class="pi pi-home text-blue-600 dark:text-blue-400"></i>
              </span>
              Próximos Vencimentos - Contratos
            </h3>
            <button
              @click="irPara('/pagina/gestao-contratos/locacao')"
              class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium transition-colors"
            >
              Ver todos →
            </button>
          </div>

          <div
            v-if="GestaoJs.dashboard.value.proximos_vencimentos.length === 0"
            class="text-center py-8 text-gray-500 dark:text-gray-400"
          >
            <div
              class="w-16 h-16 mx-auto mb-3 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center"
            >
              <i
                class="pi pi-check-circle text-3xl text-green-500 dark:text-green-400"
              ></i>
            </div>
            <p class="font-medium">Nenhum contrato próximo do vencimento</p>
          </div>

          <div
            v-else
            class="space-y-3 max-h-80 overflow-y-auto"
          >
            <div
              v-for="contrato in GestaoJs.dashboard.value.proximos_vencimentos"
              :key="contrato.id"
              class="flex items-center gap-3 p-3 rounded-xl transition-all duration-200 hover:shadow-sm"
              :class="getBgCorDiasVencimento(contrato.dias_para_vencimento)"
            >
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                :class="
                  getIconBgCorDiasVencimento(contrato.dias_para_vencimento)
                "
              >
                <i
                  class="pi"
                  :class="[
                    contrato.tipo === 'LOCACAO' ? 'pi-home' : 'pi-briefcase',
                    getIconCorDiasVencimento(contrato.dias_para_vencimento)
                  ]"
                ></i>
              </div>
              <div class="flex-1 min-w-0">
                <p
                  class="text-sm font-medium text-gray-900 dark:text-white truncate"
                >
                  {{
                    contrato.razao_social_loja ||
                    contrato.nome_locador ||
                    "Contrato #" + contrato.id
                  }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                  {{
                    contrato.filial
                      ? contrato.filial.codigo +
                        " - " +
                        contrato.filial.razaosocial
                      : "Filial não informada"
                  }}
                </p>
              </div>
              <div class="text-right flex-shrink-0">
                <p
                  class="text-sm font-bold"
                  :class="getCorDiasVencimento(contrato.dias_para_vencimento)"
                >
                  {{ contrato.dias_para_vencimento }} dias
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ GestaoJs.formatarData(contrato.data_fim) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Alvarás -->
        <div
          class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
        >
          <div class="flex items-center justify-between mb-4">
            <h3
              class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2"
            >
              <span
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30"
              >
                <i
                  class="pi pi-file-edit text-purple-600 dark:text-purple-400"
                ></i>
              </span>
              Próximos Vencimentos - Alvarás
            </h3>
            <button
              @click="irPara('/pagina/gestao-contratos/alvaras')"
              class="text-sm text-purple-600 dark:text-purple-400 hover:text-purple-800 dark:hover:text-purple-300 font-medium transition-colors"
            >
              Ver todos →
            </button>
          </div>

          <div
            v-if="GestaoJs.dashboard.value.proximos_alvaras_vencer.length === 0"
            class="text-center py-8 text-gray-500 dark:text-gray-400"
          >
            <div
              class="w-16 h-16 mx-auto mb-3 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center"
            >
              <i
                class="pi pi-check-circle text-3xl text-green-500 dark:text-green-400"
              ></i>
            </div>
            <p class="font-medium">Nenhum alvará próximo do vencimento</p>
          </div>

          <div
            v-else
            class="space-y-3 max-h-80 overflow-y-auto"
          >
            <div
              v-for="alvara in GestaoJs.dashboard.value.proximos_alvaras_vencer"
              :key="alvara.id"
              class="flex items-center gap-3 p-3 rounded-xl transition-all duration-200 hover:shadow-sm"
              :class="getBgCorDiasVencimento(alvara.dias_para_vencimento)"
            >
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                :class="getIconBgCorDiasVencimento(alvara.dias_para_vencimento)"
              >
                <i
                  class="pi pi-file-edit"
                  :class="getIconCorDiasVencimento(alvara.dias_para_vencimento)"
                ></i>
              </div>
              <div class="flex-1 min-w-0">
                <p
                  class="text-sm font-medium text-gray-900 dark:text-white truncate"
                >
                  {{
                    alvara.tipo_alvara?.descricao ||
                    alvara.tipo_alvara?.nome ||
                    "Alvará"
                  }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                  {{
                    alvara.filial
                      ? alvara.filial.codigo + " - " + alvara.filial.razaosocial
                      : "Filial não informada"
                  }}
                </p>
              </div>
              <div class="text-right flex-shrink-0">
                <p
                  class="text-sm font-bold"
                  :class="getCorDiasVencimento(alvara.dias_para_vencimento)"
                >
                  {{ alvara.dias_para_vencimento }} dias
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ GestaoJs.formatarData(alvara.data_validade) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Equipamentos -->
        <div
          class="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md border border-gray-100 dark:border-slate-700"
        >
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
              <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30">
                <i class="pi pi-box text-amber-600 dark:text-amber-400"></i>
              </span>
              Equipamentos a Vencer
            </h3>
            <button
              @click="irPara('/pagina/gestao-contratos/equipamentos')"
              class="text-sm font-medium transition-colors"
              :style="{ color: 'var(--app-primary)' }"
            >
              Ver todos →
            </button>
          </div>

          <div
            v-if="GestaoJs.dashboard.value.proximos_equipamentos_vencer.length === 0"
            class="text-center py-8 text-gray-500 dark:text-gray-400"
          >
            <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
              <i class="pi pi-check-circle text-3xl text-green-500 dark:text-green-400"></i>
            </div>
            <p class="font-medium">Nenhum equipamento próximo do vencimento</p>
          </div>

          <div v-else class="space-y-3 max-h-80 overflow-y-auto">
            <div
              v-for="equip in GestaoJs.dashboard.value.proximos_equipamentos_vencer"
              :key="equip.id"
              class="flex items-center gap-3 p-3 rounded-xl transition-all duration-200 hover:shadow-sm"
              :class="getBgCorDiasVencimento(equip.dias_para_vencimento)"
            >
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                :class="getIconBgCorDiasVencimento(equip.dias_para_vencimento)"
              >
                <i class="pi pi-box" :class="getIconCorDiasVencimento(equip.dias_para_vencimento)"></i>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                  {{ equip.numero_identificacao || (equip.tipo_equipamento?.nome) || ("Equip. #" + equip.id) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                  {{ equip.filial ? equip.filial.codigo + " - " + equip.filial.razaosocial : (equip.localizacao || "Sem filial") }}
                </p>
              </div>
              <div class="text-right flex-shrink-0">
                <p class="text-sm font-bold" :class="getCorDiasVencimento(equip.dias_para_vencimento)">
                  {{ equip.dias_para_vencimento }} dias
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ GestaoJs.formatarData(equip.data_validade) }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
