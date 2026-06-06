<script setup>
import { computed } from "vue"
import { formatarData } from "@/utils/globalFunctions"

const props = defineProps({
  dados: {
    required: true
  }
})

const emits = defineEmits(["detalhar"])

const diasCompromisso = computed(() => {
  const agrupados = props.dados.reduce((acc, item) => {
    const [data] = item.data_agendamento.split(" ")
    const dia = data.split("-")[2]

    if (!acc[dia]) {
      acc[dia] = {
        dia,
        compromissos: []
      }
    }

    acc[dia].compromissos.push(item)
    return acc
  }, {})

  return agrupados
})

function getStatusConfig(status, tipo = "visita") {
  // Se for lembrete, usar estilo especial de lembrete
  if (tipo === "lembrete") {
    const lembreteConfigs = {
      aguardando: {
        bg: "bg-gradient-to-r from-amber-400 to-orange-500",
        bgLight: "bg-amber-50 dark:bg-amber-900/20",
        border: "border-amber-300 dark:border-amber-700",
        text: "text-amber-700 dark:text-amber-300",
        icon: "pi pi-bookmark",
        label: "Lembrete",
        pulse: false
      },
      cancelado: {
        bg: "bg-red-500",
        bgLight: "bg-red-50 dark:bg-red-900/20",
        border: "border-red-200 dark:border-red-800",
        text: "text-red-700 dark:text-red-300",
        icon: "pi pi-bookmark",
        label: "Lembrete Cancelado",
        pulse: false
      },
      finalizado: {
        bg: "bg-emerald-500",
        bgLight: "bg-emerald-50 dark:bg-emerald-900/20",
        border: "border-emerald-200 dark:border-emerald-800",
        text: "text-emerald-700 dark:text-emerald-300",
        icon: "pi pi-bookmark-fill",
        label: "Lembrete Finalizado",
        pulse: false
      }
    }
    return lembreteConfigs[status] || lembreteConfigs.aguardando
  }

  // Configurações para visitas
  const configs = {
    aguardando: {
      bg: "bg-indigo-500",
      bgLight: "bg-indigo-50 dark:bg-indigo-900/20",
      border: "border-indigo-200 dark:border-indigo-800",
      text: "text-indigo-700 dark:text-indigo-300",
      icon: "pi pi-car",
      label: "Visita",
      pulse: false
    },
    "em atendimento": {
      bg: "bg-blue-500",
      bgLight: "bg-blue-50 dark:bg-blue-900/20",
      border: "border-blue-200 dark:border-blue-800",
      text: "text-blue-700 dark:text-blue-300",
      icon: "pi pi-cog",
      label: "Em Atendimento",
      pulse: true
    },
    cancelado: {
      bg: "bg-red-500",
      bgLight: "bg-red-50 dark:bg-red-900/20",
      border: "border-red-200 dark:border-red-800",
      text: "text-red-700 dark:text-red-300",
      icon: "pi pi-times-circle",
      label: "Cancelado",
      pulse: false
    },
    finalizado: {
      bg: "bg-emerald-500",
      bgLight: "bg-emerald-50 dark:bg-emerald-900/20",
      border: "border-emerald-200 dark:border-emerald-800",
      text: "text-emerald-700 dark:text-emerald-300",
      icon: "pi pi-check-circle",
      label: "Finalizado",
      pulse: false
    }
  }
  return configs[status] || configs.aguardando
}

function abrirDetalhes(comp) {
  emits("detalhar", comp)
}
</script>

<template>
  <div class="flex flex-col w-full h-full p-4">
    <!-- Lista de compromissos por dia -->
    <div
      v-if="Object.keys(diasCompromisso).length != 0"
      class="space-y-6"
    >
      <div
        v-for="dia in Object.values(diasCompromisso).sort(
          (a, b) => b.dia - a.dia
        )"
        :key="dia.dia"
        class="space-y-3"
      >
        <!-- Header do dia -->
        <div
          class="flex items-center gap-3 pb-2 border-b border-gray-200 dark:border-slate-600"
        >
          <div
            class="w-12 h-12 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg"
          >
            <span class="text-xl font-bold text-white">{{ dia.dia }}</span>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
              {{ dia.compromissos.length }}
              {{
                dia.compromissos.length === 1 ? "agendamento" : "agendamentos"
              }}
            </p>
          </div>
        </div>

        <!-- Compromissos do dia -->
        <div class="grid gap-3">
          <div
            v-for="compromisso in dia.compromissos.sort(
              (a, b) =>
                new Date(a.data_agendamento) - new Date(b.data_agendamento)
            )"
            :key="compromisso.id"
            @click="abrirDetalhes(compromisso)"
            :class="[
              'group relative p-4 rounded-xl border cursor-pointer transition-all duration-300',
              'hover:shadow-lg hover:scale-[1.01] hover:-translate-y-0.5',
              getStatusConfig(compromisso.status, compromisso.tipo).bgLight,
              getStatusConfig(compromisso.status, compromisso.tipo).border
            ]"
          >
            <div class="flex items-start gap-4">
              <!-- Indicador de tipo com ícone -->
              <div
                :class="[
                  'w-10 h-10 rounded-xl flex-shrink-0 shadow-sm flex items-center justify-center',
                  getStatusConfig(compromisso.status, compromisso.tipo).bg,
                  getStatusConfig(compromisso.status, compromisso.tipo).pulse
                    ? 'animate-pulse'
                    : ''
                ]"
              >
                <i
                  :class="[
                    compromisso.tipo === 'lembrete'
                      ? 'pi pi-bookmark'
                      : 'pi pi-car',
                    'text-white text-sm'
                  ]"
                ></i>
              </div>

              <!-- Conteúdo -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <span
                    :class="[
                      'text-[10px] font-bold uppercase px-2 py-0.5 rounded',
                      compromisso.tipo === 'lembrete'
                        ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300'
                        : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                    ]"
                  >
                    {{
                      compromisso.tipo === "lembrete" ? "Lembrete" : "Visita"
                    }}
                  </span>
                </div>
                <h4
                  class="font-semibold text-gray-800 dark:text-white text-sm sm:text-base truncate"
                >
                  {{ compromisso.descricao_completa }}
                </h4>

                <div class="flex flex-wrap items-center gap-3 mt-2">
                  <!-- Horário -->
                  <span
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 dark:text-gray-400"
                  >
                    <i class="pi pi-clock"></i>
                    {{ formatarData(compromisso.data_agendamento) }}
                  </span>

                  <!-- Status badge -->
                  <span
                    :class="[
                      'inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold',
                      getStatusConfig(compromisso.status, compromisso.tipo).bg,
                      'text-white'
                    ]"
                  >
                    <i
                      :class="
                        getStatusConfig(compromisso.status, compromisso.tipo)
                          .icon
                      "
                      class="text-[10px]"
                    ></i>
                    {{
                      getStatusConfig(compromisso.status, compromisso.tipo)
                        .label
                    }}
                  </span>
                </div>
              </div>

              <!-- Seta indicadora -->
              <div
                class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity"
              >
                <i
                  class="pi pi-chevron-right text-gray-400 dark:text-gray-500"
                ></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Estado vazio -->
    <div
      v-else
      class="flex flex-col items-center justify-center w-full py-16 space-y-4"
    >
      <div
        class="w-24 h-24 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center"
      >
        <i
          class="pi pi-calendar-times text-4xl text-gray-400 dark:text-gray-500"
        ></i>
      </div>
      <div class="text-center">
        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300">
          Nenhum agendamento
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          Não há compromissos para o período selecionado
        </p>
      </div>
    </div>
  </div>
</template>
