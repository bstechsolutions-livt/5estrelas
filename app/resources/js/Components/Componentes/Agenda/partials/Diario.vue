<script setup>
import { tratarNome } from "@/utils/globalFunctions"
import { ref, watch, computed } from "vue"

const props = defineProps({
  dados: { required: true },
  dataBase: { required: true }
})

const emits = defineEmits(["detalhar"])

const base = ref("")
const cellsDay = ref([])
const startHour = 0
const endHour = 23
const rowHeight = 60

const hoursOfDay = computed(() => {
  return Array.from(
    { length: endHour - startHour + 1 },
    (_, i) => i + startHour
  )
})

function getBase() {
  if (props.dataBase) {
    const data = props.dataBase.split("/")
    return new Date(data[2], data[1] - 1, data[0])
  } else {
    return new Date()
  }
}

function getAgendamentosByDay(dayDate) {
  return (
    props.dados.map((ag) => ({
      ...ag,
      horario_inicial: extractTime(ag.data_agendamento)
    })) || []
  )
}

function buildDailyCells() {
  const cellsForDay = Array(24)
    .fill(null)
    .map(() => ({ appointments: [] }))
  const appointments = getAgendamentosByDay(base.value)

  appointments.forEach((app) => {
    const startM = timeStringToMinutes(app.horario_inicial)
    const startH = Math.floor(startM / 60)
    const span = getRowSpan(app)

    cellsForDay[startH].appointments.push({
      appointment: app,
      rowSpan: span
    })
  })

  cellsForDay.forEach((cell) => {
    if (cell.appointments.length > 0) {
      cell.appointments.sort((a, b) => {
        const timeA = new Date(a.appointment.data_agendamento).getTime()
        const timeB = new Date(b.appointment.data_agendamento).getTime()

        if (timeA === timeB) {
          const horaA = timeStringToMinutes(a.appointment.horario_inicial)
          const horaB = timeStringToMinutes(b.appointment.horario_inicial)
          return horaA - horaB
        }

        return timeA - timeB
      })
    }
  })

  return cellsForDay
}

watch(
  () => props.dataBase,
  (newVal) => {
    if (newVal) {
      base.value = getBase()
      cellsDay.value = buildDailyCells()
    }
  },
  { immediate: true }
)

watch(
  () => props.dados,
  (newVal) => {
    if (newVal) {
      cellsDay.value = buildDailyCells()
    }
  },
  { deep: true }
)

function timeStringToMinutes(timeStr) {
  if (!timeStr) return 0
  const [hh, mm] = timeStr.split(":")
  return parseInt(hh) * 60 + parseInt(mm)
}

function extractTime(datetime) {
  if (!datetime) return "--:--"
  const dateObj = new Date(datetime)
  const hours = String(dateObj.getHours()).padStart(2, "0")
  const minutes = String(dateObj.getMinutes()).padStart(2, "0")
  return `${hours}:${minutes}`
}

function getRowSpan(agendamento) {
  const startMinutes = timeStringToMinutes(agendamento.horario_inicial)
  const endMinutes = timeStringToMinutes(agendamento.horario_final)
  return Math.ceil((endMinutes - startMinutes) / 60)
}

function abrirDetalhes(comp) {
  emits("detalhar", comp)
}

function getStatusConfig(status, tipo = "visita") {
  // Se for lembrete, usar estilo especial de lembrete
  if (tipo === "lembrete") {
    const lembreteConfigs = {
      aguardando: {
        bg: "bg-gradient-to-r from-amber-400 to-orange-500",
        bgLight: "bg-amber-50 dark:bg-amber-900/20",
        border: "border-amber-300 dark:border-amber-700",
        text: "text-amber-700 dark:text-amber-300",
        icon: "pi-bookmark",
        shadow: "shadow-amber-200 dark:shadow-amber-900/50",
        pulse: false
      },
      cancelado: {
        bg: "bg-gradient-to-r from-red-400 to-red-500",
        bgLight: "bg-red-50 dark:bg-red-900/20",
        border: "border-red-300 dark:border-red-700",
        text: "text-red-700 dark:text-red-300",
        icon: "pi-bookmark",
        shadow: "shadow-red-200 dark:shadow-red-900/50",
        pulse: false
      },
      finalizado: {
        bg: "bg-gradient-to-r from-emerald-400 to-emerald-500",
        bgLight: "bg-emerald-50 dark:bg-emerald-900/20",
        border: "border-emerald-300 dark:border-emerald-700",
        text: "text-emerald-700 dark:text-emerald-300",
        icon: "pi-bookmark-fill",
        shadow: "shadow-emerald-200 dark:shadow-emerald-900/50",
        pulse: false
      }
    }
    return lembreteConfigs[status] || lembreteConfigs.aguardando
  }

  // Configurações para visitas
  const configs = {
    aguardando: {
      bg: "bg-gradient-to-r from-indigo-400 to-indigo-500",
      bgLight: "bg-indigo-50 dark:bg-indigo-900/20",
      border: "border-indigo-300 dark:border-indigo-700",
      text: "text-indigo-700 dark:text-indigo-300",
      icon: "pi-car",
      shadow: "shadow-indigo-200 dark:shadow-indigo-900/50",
      pulse: false
    },
    "em atendimento": {
      bg: "bg-gradient-to-r from-blue-400 to-blue-500",
      bgLight: "bg-blue-50 dark:bg-blue-900/20",
      border: "border-blue-300 dark:border-blue-700",
      text: "text-blue-700 dark:text-blue-300",
      icon: "pi-spinner",
      shadow: "shadow-blue-200 dark:shadow-blue-900/50",
      pulse: true
    },
    cancelado: {
      bg: "bg-gradient-to-r from-red-400 to-red-500",
      bgLight: "bg-red-50 dark:bg-red-900/20",
      border: "border-red-300 dark:border-red-700",
      text: "text-red-700 dark:text-red-300",
      icon: "pi-times-circle",
      shadow: "shadow-red-200 dark:shadow-red-900/50",
      pulse: false
    },
    finalizado: {
      bg: "bg-gradient-to-r from-emerald-400 to-emerald-500",
      bgLight: "bg-emerald-50 dark:bg-emerald-900/20",
      border: "border-emerald-300 dark:border-emerald-700",
      text: "text-emerald-700 dark:text-emerald-300",
      icon: "pi-check-circle",
      shadow: "shadow-emerald-200 dark:shadow-emerald-900/50",
      pulse: false
    }
  }
  return configs[status] || configs.aguardando
}

// Verifica se a hora é a hora atual
const hoje = new Date()
function isCurrentHour(hour) {
  const baseDate = getBase()
  const isToday = baseDate.toDateString() === hoje.toDateString()
  return isToday && hoje.getHours() === hour
}

// Data formatada
const formattedDate = computed(() => {
  if (!props.dataBase) return ""
  const data = props.dataBase.split("/")
  const date = new Date(data[2], data[1] - 1, data[0])
  const options = {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric"
  }
  return date.toLocaleDateString("pt-BR", options)
})
</script>

<template>
  <div class="bg-white dark:bg-slate-800 rounded-xl overflow-hidden">
    <!-- Header com data -->
    <div class="bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-3">
      <div class="flex items-center gap-3">
        <div
          class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center"
        >
          <i class="pi pi-calendar text-white text-lg"></i>
        </div>
        <div>
          <h3 class="text-white font-semibold capitalize">
            {{ formattedDate }}
          </h3>
          <p class="text-white/70 text-sm">Visualização diária</p>
        </div>
      </div>
    </div>

    <!-- Tabela de horários -->
    <div
      class="overflow-y-auto max-h-[600px] scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-slate-600"
    >
      <table class="w-full border-collapse">
        <thead class="sticky top-0 z-10">
          <tr class="bg-gray-50 dark:bg-slate-700">
            <th
              class="w-20 py-2 px-3 text-center text-gray-600 dark:text-gray-300 font-medium text-sm border-b border-gray-200 dark:border-slate-600"
            >
              <i class="pi pi-clock"></i>
            </th>
            <th
              class="py-2 px-3 text-left text-gray-600 dark:text-gray-300 font-medium text-sm border-b border-gray-200 dark:border-slate-600"
            >
              Agendamentos
            </th>
          </tr>
        </thead>
        <tbody class="text-sm">
          <tr
            v-for="(hour, hIndex) in hoursOfDay"
            :key="'daily-hour-tr-' + hIndex"
            :class="[
              'border-b border-gray-100 dark:border-slate-700 transition-colors duration-200',
              isCurrentHour(hour)
                ? 'bg-cyan-50/50 dark:bg-cyan-900/20'
                : 'hover:bg-gray-50/50 dark:hover:bg-slate-700/30'
            ]"
          >
            <!-- Coluna da hora -->
            <td
              :class="[
                'py-3 px-3 text-center font-medium text-xs border-r border-gray-200 dark:border-slate-600',
                isCurrentHour(hour)
                  ? 'bg-gradient-to-r from-cyan-500 to-blue-600 text-white'
                  : 'bg-gray-50 dark:bg-slate-700/50 text-gray-600 dark:text-gray-300'
              ]"
            >
              <div class="flex flex-col items-center">
                <span class="text-sm font-semibold">
                  {{ String(hour).padStart(2, "0") }}:00
                </span>
                <span
                  v-if="isCurrentHour(hour)"
                  class="text-[10px] opacity-80"
                >
                  Agora
                </span>
              </div>
            </td>

            <!-- Coluna dos agendamentos -->
            <td class="py-2 px-3 align-top min-h-[60px]">
              <div
                v-if="
                  cellsDay[hIndex] && cellsDay[hIndex].appointments.length > 0
                "
                class="flex flex-wrap gap-2"
              >
                <div
                  v-for="(appointmentData, appIndex) in cellsDay[hIndex]
                    .appointments"
                  :key="'app-' + hIndex + '-' + appIndex"
                  @click="abrirDetalhes(appointmentData.appointment)"
                  v-tooltip.top="
                    `${appointmentData.appointment.tipo === 'lembrete' ? '📌 Lembrete' : '🚗 Visita'}: ${appointmentData.appointment.descricao_completa} - ${tratarNome(appointmentData.appointment.nomeResponsavel)}`
                  "
                  :class="[
                    'group cursor-pointer rounded-xl p-3 text-white transition-all duration-200 hover:scale-[1.02] hover:shadow-lg flex-1 min-w-[280px] max-w-[400px]',
                    getStatusConfig(
                      appointmentData.appointment.status,
                      appointmentData.appointment.tipo
                    ).bg,
                    getStatusConfig(
                      appointmentData.appointment.status,
                      appointmentData.appointment.tipo
                    ).shadow,
                    getStatusConfig(
                      appointmentData.appointment.status,
                      appointmentData.appointment.tipo
                    ).pulse
                      ? 'animate-pulse'
                      : ''
                  ]"
                >
                  <div class="flex items-start gap-3">
                    <!-- Ícone do tipo (visita/lembrete) -->
                    <div
                      class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0"
                    >
                      <i
                        :class="[
                          appointmentData.appointment.tipo === 'lembrete'
                            ? 'pi pi-bookmark'
                            : 'pi pi-car',
                          'text-lg'
                        ]"
                      ></i>
                    </div>

                    <!-- Conteúdo -->
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center justify-between gap-2 mb-1">
                        <div class="flex items-center gap-2">
                          <span
                            :class="[
                              'text-[10px] font-bold uppercase px-1.5 py-0.5 rounded',
                              appointmentData.appointment.tipo === 'lembrete'
                                ? 'bg-orange-200/30 text-white'
                                : 'bg-white/20 text-white'
                            ]"
                          >
                            {{
                              appointmentData.appointment.tipo === "lembrete"
                                ? "Lembrete"
                                : "Visita"
                            }}
                          </span>
                          <span class="font-bold text-sm truncate">
                            {{ appointmentData.appointment.descricao }}
                          </span>
                        </div>
                        <span
                          class="text-xs bg-white/20 px-2 py-0.5 rounded-full font-medium flex-shrink-0"
                        >
                          {{ appointmentData.appointment.horario_inicial }}
                        </span>
                      </div>

                      <div class="flex items-center gap-2 text-xs opacity-90">
                        <i class="pi pi-user text-[10px]"></i>
                        <span class="truncate">
                          {{ appointmentData.appointment.mat_responsavel }} -
                          {{
                            tratarNome(
                              appointmentData.appointment.nomeResponsavel
                            )
                          }}
                        </span>
                      </div>

                      <div
                        v-if="appointmentData.appointment.descricao_completa"
                        class="mt-1.5 text-xs opacity-80 line-clamp-2"
                      >
                        {{ appointmentData.appointment.descricao_completa }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Estado vazio para a hora -->
              <div
                v-else
                class="h-[40px] flex items-center"
              >
                <span class="text-gray-300 dark:text-slate-600 text-xs">—</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
