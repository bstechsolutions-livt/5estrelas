<script setup>
import { tratarNome } from "@/utils/globalFunctions"
import { ref, watch, computed, onMounted } from "vue"

const props = defineProps({
  dados: {
    required: true
  },
  dataBase: {
    required: true
  }
})

const emits = defineEmits(["detalhar"])

const DAYS_COUNT = 7

const cells = ref([])

watch(
  () => props.dados,
  (newVal) => {
    if (newVal.length > 0 && newVal[0].id != null) {
      cells.value = calcularCells()
    }
  },
  { deep: true }
)

const base = ref("")

function getBase() {
  if (props.dataBase) {
    const data = props.dataBase.split("/")
    const dataFormat = new Date(data[2], data[1] - 1, data[0])
    return dataFormat
  } else {
    return new Date()
  }
}

const currentWeekStart = ref("")

function getStartOfWeek(date) {
  const d = new Date(date)
  d.setHours(0, 0, 0, 0)
  const day = d.getDay()
  d.setDate(d.getDate() - day)
  return d
}

const daysOfWeek = computed(() => {
  return Array.from({ length: DAYS_COUNT }, (_, i) => {
    const d = new Date(currentWeekStart.value)
    d.setDate(d.getDate() + i)
    return d
  })
})

const dayNames = ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"]

watch(
  () => props.dataBase,
  (newVal) => {
    if (newVal) {
      base.value = getBase()
      currentWeekStart.value = getStartOfWeek(base.value)
      cells.value = calcularCells()
    }
  },
  { immediate: true }
)

onMounted(() => {
  base.value = getBase()
  currentWeekStart.value = getStartOfWeek(base.value)
})

const startHour = 0
const endHour = 23
const hoursOfDay = computed(() => {
  return Array.from(
    { length: endHour - startHour + 1 },
    (_, i) => i + startHour
  )
})

function extractTime(datetime) {
  if (!datetime) return "--:--"
  const dateObj = new Date(datetime)
  const hours = String(dateObj.getHours()).padStart(2, "0")
  const minutes = String(dateObj.getMinutes()).padStart(2, "0")
  return `${hours}:${minutes}`
}

function getAgendamentosByDay(dayDate) {
  return (
    props.dados
      .filter(
        (agend) =>
          new Date(agend.data_agendamento).toDateString() ==
          dayDate.toDateString()
      )
      .map((ag) => ({
        ...ag,
        horario_inicial: extractTime(ag.data_agendamento),
        horario_final: extractTime(ag.data_fim_agendamento)
      })) || []
  )
}

function calcularCells() {
  const dayCells = daysOfWeek.value.map((day) => {
    const cellsForDay = Array(24)
      .fill(null)
      .map(() => ({ appointments: [] }))
    const appointments = getAgendamentosByDay(day)

    appointments.forEach((app) => {
      const startH = parseInt(app.horario_inicial.split(":")[0])
      cellsForDay[startH].appointments.push({
        appointment: app,
        rowSpan: 1
      })
    })

    cellsForDay.forEach((cell) => {
      if (cell.appointments.length > 0) {
        cell.appointments.sort((a, b) => {
          const timeA = new Date(a.appointment.data_agendamento).getTime()
          const timeB = new Date(b.appointment.data_agendamento).getTime()
          if (timeA === timeB) {
            const horaA =
              parseInt(a.appointment.horario_inicial.split(":")[0]) * 60 +
              parseInt(a.appointment.horario_inicial.split(":")[1])
            const horaB =
              parseInt(b.appointment.horario_inicial.split(":")[0]) * 60 +
              parseInt(b.appointment.horario_inicial.split(":")[1])
            return horaA - horaB
          }
          return timeA - timeB
        })
      }
    })

    return cellsForDay
  })

  const tableRows = []
  for (let h = 0; h < 24; h++) {
    const row = dayCells.map((cellsForDay) => cellsForDay[h])
    tableRows.push(row)
  }
  return tableRows
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
        shadow: "shadow-amber-200 dark:shadow-amber-900/50",
        pulse: false
      },
      cancelado: {
        bg: "bg-gradient-to-r from-red-400 to-red-500",
        shadow: "shadow-red-200 dark:shadow-red-900/50",
        pulse: false
      },
      finalizado: {
        bg: "bg-gradient-to-r from-emerald-400 to-emerald-500",
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
      shadow: "shadow-indigo-200 dark:shadow-indigo-900/50",
      pulse: false
    },
    "em atendimento": {
      bg: "bg-gradient-to-r from-blue-400 to-blue-500",
      shadow: "shadow-blue-200 dark:shadow-blue-900/50",
      pulse: true
    },
    cancelado: {
      bg: "bg-gradient-to-r from-red-400 to-red-500",
      shadow: "shadow-red-200 dark:shadow-red-900/50",
      pulse: false
    },
    finalizado: {
      bg: "bg-gradient-to-r from-emerald-400 to-emerald-500",
      shadow: "shadow-emerald-200 dark:shadow-emerald-900/50",
      pulse: false
    }
  }
  return configs[status] || configs.aguardando
}

// Verifica se é hoje
const hoje = new Date()
function isToday(date) {
  return date.toDateString() === hoje.toDateString()
}
</script>

<template>
  <div class="bg-white dark:bg-slate-800 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full border-collapse min-w-[800px]">
        <!-- Header -->
        <thead class="sticky top-0 z-10">
          <tr class="bg-gradient-to-r from-cyan-500 to-blue-600">
            <th
              class="w-16 py-3 px-2 text-center text-white font-semibold text-sm border-r border-white/20"
            >
              <i class="pi pi-clock"></i>
            </th>
            <th
              v-for="(day, dIndex) in daysOfWeek"
              :key="'day-th-' + dIndex"
              :class="[
                'py-3 px-2 text-center text-white font-semibold text-sm border-r border-white/20 last:border-r-0',
                isToday(day) ? 'bg-white/20' : ''
              ]"
            >
              <div class="flex flex-col items-center gap-1">
                <span class="text-xs opacity-80">{{ dayNames[dIndex] }}</span>
                <span
                  :class="[
                    'w-8 h-8 flex items-center justify-center rounded-full text-sm',
                    isToday(day) ? 'bg-white text-cyan-600 font-bold' : ''
                  ]"
                >
                  {{ day.getDate() }}
                </span>
              </div>
            </th>
          </tr>
        </thead>

        <tbody class="text-sm">
          <tr
            v-for="(hour, hIndex) in hoursOfDay"
            :key="'hour-' + hIndex"
            class="border-b border-gray-100 dark:border-slate-700 hover:bg-gray-50/50 dark:hover:bg-slate-700/30"
          >
            <!-- Hora -->
            <td
              class="py-2 px-2 text-center bg-gray-50 dark:bg-slate-700/50 border-r border-gray-200 dark:border-slate-600 font-medium text-gray-600 dark:text-gray-300 text-xs"
            >
              {{ String(hour).padStart(2, "0") }}:00
            </td>

            <!-- Células dos dias -->
            <template
              v-if="cells"
              v-for="(cell, dayIdx) in cells[hIndex]"
              :key="'cell-' + hIndex + '-' + dayIdx"
            >
              <td
                :class="[
                  'py-1 px-1 border-r border-gray-100 dark:border-slate-700 last:border-r-0 align-top min-h-[50px]',
                  isToday(daysOfWeek[dayIdx])
                    ? 'bg-cyan-50/30 dark:bg-cyan-900/10'
                    : ''
                ]"
              >
                <div
                  v-if="cell && cell.appointments.length > 0"
                  class="space-y-1"
                >
                  <div
                    v-for="(appointmentData, appIndex) in cell.appointments"
                    :key="'app-' + appIndex"
                    @click="abrirDetalhes(appointmentData.appointment)"
                    v-tooltip.top="
                      `${appointmentData.appointment.tipo === 'lembrete' ? '📌 Lembrete' : '🚗 Visita'}: ${appointmentData.appointment.descricao_completa} - ${tratarNome(appointmentData.appointment.nomeResponsavel)}`
                    "
                    :class="[
                      'group cursor-pointer rounded-lg px-2 py-1.5 text-white transition-all duration-200 hover:scale-[1.02] hover:shadow-lg',
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
                    <div class="flex flex-col gap-0.5">
                      <div class="flex items-center gap-1">
                        <i
                          :class="[
                            appointmentData.appointment.tipo === 'lembrete'
                              ? 'pi pi-bookmark'
                              : 'pi pi-car',
                            'text-[9px] opacity-90'
                          ]"
                        ></i>
                        <span class="text-[10px] font-bold truncate">
                          {{ appointmentData.appointment.descricao }}
                        </span>
                      </div>
                      <div
                        class="flex items-center gap-1 text-[9px] opacity-90"
                      >
                        <i class="pi pi-user text-[8px]"></i>
                        <span class="truncate">
                          {{
                            tratarNome(
                              appointmentData.appointment.nomeResponsavel
                            )
                          }}
                        </span>
                        <span class="ml-auto font-medium">
                          {{ appointmentData.appointment.horario_inicial }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </td>
            </template>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
