<script setup>
import { formatarData, getHoras } from "@/utils/globalFunctions"
import { computed } from "vue"

const props = defineProps({
  dados: {
    required: true
  },
  dataBase: {
    required: true
  }
})

const emits = defineEmits(["detalhar"])

const base = computed(() => {
  if (props.dataBase) {
    const data = props.dataBase.split("/")
    const dataFormat = new Date(data[2], data[1] - 1, data[0])
    return dataFormat
  } else {
    return new Date()
  }
})

const anoAtual = computed(() => base.value.getFullYear())
const mesAtual = computed(() => base.value.getMonth())

const totalDias = computed(() =>
  new Date(anoAtual.value, mesAtual.value + 1, 0).getDate()
)

const indicePrimeiroDia = computed(() =>
  new Date(anoAtual.value, mesAtual.value, 1).getDay()
)

const calendarDays = computed(() => {
  const dias = []

  for (let i = 0; i < indicePrimeiroDia.value; i++) {
    dias.push(null)
  }

  for (let d = 1; d <= totalDias.value; d++) {
    const compromissoDia = (props.dados || []).filter((element) => {
      const dataSplit = element.data_agendamento.split(" ")
      const [ano, mes, dia] = dataSplit[0].split("-")
      return d === Number(dia)
    })

    dias.push({
      day: d,
      compromisso: compromissoDia
    })
  }

  return dias
})

const daysOfWeek = [
  { short: "Dom", full: "Domingo" },
  { short: "Seg", full: "Segunda" },
  { short: "Ter", full: "Terça" },
  { short: "Qua", full: "Quarta" },
  { short: "Qui", full: "Quinta" },
  { short: "Sex", full: "Sexta" },
  { short: "Sáb", full: "Sábado" }
]

// Verifica se é o dia atual
const hoje = new Date()
function isToday(day) {
  return (
    day === hoje.getDate() &&
    mesAtual.value === hoje.getMonth() &&
    anoAtual.value === hoje.getFullYear()
  )
}

function getStatusConfig(status, tipo = null) {
  // Se for lembrete, usa cores âmbar/laranja específicas
  if (tipo === "lembrete") {
    const lembreteConfigs = {
      aguardando: {
        bg: "bg-gradient-to-r from-amber-400 to-orange-500",
        hover: "hover:from-amber-500 hover:to-orange-600",
        ring: "ring-amber-300",
        pulse: false,
        icon: "pi pi-bookmark"
      },
      cancelado: {
        bg: "bg-red-500",
        hover: "hover:bg-red-400",
        ring: "ring-red-300",
        pulse: false,
        icon: "pi pi-bookmark"
      },
      finalizado: {
        bg: "bg-emerald-500",
        hover: "hover:bg-emerald-400",
        ring: "ring-emerald-300",
        pulse: false,
        icon: "pi pi-bookmark"
      }
    }
    return lembreteConfigs[status] || lembreteConfigs.aguardando
  }

  // Configurações para visitas (padrão)
  const configs = {
    aguardando: {
      bg: "bg-amber-500",
      hover: "hover:bg-amber-400",
      ring: "ring-amber-300",
      pulse: false,
      icon: "pi pi-car"
    },
    "em atendimento": {
      bg: "bg-blue-500",
      hover: "hover:bg-blue-400",
      ring: "ring-blue-300",
      pulse: true,
      icon: "pi pi-car"
    },
    cancelado: {
      bg: "bg-red-500",
      hover: "hover:bg-red-400",
      ring: "ring-red-300",
      pulse: false,
      icon: "pi pi-car"
    },
    finalizado: {
      bg: "bg-emerald-500",
      hover: "hover:bg-emerald-400",
      ring: "ring-emerald-300",
      pulse: false,
      icon: "pi pi-car"
    }
  }
  return configs[status] || configs.aguardando
}

function abrirDetalhes(comp) {
  emits("detalhar", comp)
}
</script>

<template>
  <div class="flex flex-col w-full">
    <!-- Cabeçalho: Dias da semana -->
    <div
      class="grid grid-cols-7 bg-gradient-to-r from-cyan-500 to-blue-600 rounded-t-xl overflow-hidden"
    >
      <div
        v-for="(dia, index) in daysOfWeek"
        :key="index"
        :class="[
          'py-3 text-center text-white font-semibold text-sm',
          index === 0 || index === 6 ? 'bg-black/10' : ''
        ]"
      >
        <span class="hidden sm:inline">{{ dia.full }}</span>
        <span class="sm:hidden">{{ dia.short }}</span>
      </div>
    </div>

    <!-- Corpo: Dias do mês -->
    <div
      class="grid grid-flow-row grid-cols-7 border-l border-gray-200 dark:border-slate-600"
    >
      <div
        v-for="(celula, index) in calendarDays"
        :key="index"
        :class="[
          'min-h-[100px] sm:min-h-[140px] border-b border-r border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 transition-colors',
          !celula ? 'bg-gray-50 dark:bg-slate-700/50' : '',
          celula && isToday(celula.day) ? 'bg-cyan-50 dark:bg-cyan-900/20' : ''
        ]"
      >
        <div
          v-if="celula"
          class="relative flex flex-col h-full p-1 sm:p-2"
        >
          <!-- Número do dia -->
          <div class="flex items-center justify-between mb-1">
            <span
              :class="[
                'w-7 h-7 flex items-center justify-center rounded-full text-sm font-semibold transition-colors',
                isToday(celula.day)
                  ? 'bg-cyan-500 text-white shadow-lg'
                  : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-600'
              ]"
            >
              {{ celula.day }}
            </span>
            <span
              v-if="celula.compromisso.length > 0"
              class="text-[10px] font-medium text-gray-400 dark:text-gray-500"
            >
              {{ celula.compromisso.length }}
            </span>
          </div>

          <!-- Compromissos -->
          <div
            class="flex flex-col gap-1 overflow-y-auto flex-1 pr-1 scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-slate-600"
          >
            <div
              v-for="(comp, idx) in celula.compromisso.sort(
                (a, b) =>
                  new Date(a.data_agendamento) - new Date(b.data_agendamento)
              )"
              :key="idx"
              @click="abrirDetalhes(comp)"
              v-tooltip.top="
                `${comp.tipo === 'lembrete' ? '📌 Lembrete' : '🚗 Visita'}: ${getHoras(comp.data_agendamento)} - ${comp.descricao_completa}`
              "
              :class="[
                'group flex items-center gap-1 px-1.5 py-1 rounded-md cursor-pointer transition-all text-white text-[10px] sm:text-xs',
                getStatusConfig(comp.status, comp.tipo).bg,
                getStatusConfig(comp.status, comp.tipo).hover,
                getStatusConfig(comp.status, comp.tipo).pulse
                  ? 'animate-pulse'
                  : ''
              ]"
            >
              <!-- Ícone indicando tipo: bookmark para lembrete, car para visita -->
              <i
                :class="[
                  comp.tipo === 'lembrete' ? 'pi pi-bookmark' : 'pi pi-car',
                  'text-[8px] sm:text-[10px] flex-shrink-0 opacity-90'
                ]"
              ></i>
              <span class="truncate font-medium">
                {{ getHoras(comp.data_agendamento) }}
                <span class="hidden sm:inline">
                  - {{ comp.descricao_completa }}
                </span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.scrollbar-thin {
  scrollbar-width: thin;
}
.scrollbar-thumb-gray-300::-webkit-scrollbar {
  width: 4px;
}
.scrollbar-thumb-gray-300::-webkit-scrollbar-thumb {
  background-color: #d1d5db;
  border-radius: 2px;
}
.dark .scrollbar-thumb-slate-600::-webkit-scrollbar-thumb {
  background-color: #475569;
}
</style>
