<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue"
import BsButton from "./BsButton.vue"

// Definindo as props com tipos
const props = defineProps({
  visible: {
    type: Boolean,
    required: false,
    default: false
  },
  modelValue: {
    type: String,
    required: true
  },
  label: {
    type: String,
    required: false
  },
  invalid: {
    type: Boolean,
    required: false,
    default: false
  },
  showCalendar: {
    type: Boolean,
    required: false,
    default: true
  },
  showHours: {
    type: Boolean,
    required: false,
    default: false
  }
})

// Eventos que o componente emite
const emit = defineEmits(["update:visible", "update:modelValue"])

// Propriedade reativa para controlar o v-model
const internalValue = computed({
  get: () => props.modelValue,
  set: (novoValor) => emit("update:modelValue", novoValor)
})

// Controla a visibilidade do calendário
const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit("update:visible", value)
})

// Estados reativos do calendário
const currentMonth = ref(new Date().getMonth())
const currentYear = ref(new Date().getFullYear())
const currentDay = ref(new Date().getDay())

// Referência para a div do calendário
const calendarRef = ref(null)

// Função para detectar cliques fora do componente
const handleClickOutside = (event) => {
  if (
    calendarRef.value &&
    !calendarRef.value.contains(event.target) &&
    !mesClicado.value
  ) {
    updateTime()
    isVisible.value = false // Fecha o calendário
  }
}

const monthNames = [
  "Janeiro",
  "Fevereiro",
  "Março",
  "Abril",
  "Maio",
  "Junho",
  "Julho",
  "Agosto",
  "Setembro",
  "Outubro",
  "Novembro",
  "Dezembro"
]
const daysOfWeek = ["D", "S", "T", "Q", "Q", "S", "S"]

// Funções auxiliares
const getDaysInMonth = (month, year) => new Date(year, month + 1, 0).getDate()

const calculateBlankDays = (month, year) => new Date(year, month, 1).getDay()

const daysInMonth = ref(getDaysInMonth(currentMonth.value, currentYear.value))
const blankDays = ref(calculateBlankDays(currentMonth.value, currentYear.value))

const calendarDays = ref(null)
// Atualizar calendário
const updateCalendar = () => {
  // Dias no mês atual
  daysInMonth.value = getDaysInMonth(currentMonth.value, currentYear.value)
  // Dias em branco antes do primeiro dia do mês atual
  blankDays.value = calculateBlankDays(currentMonth.value, currentYear.value)

  // Dias do mês anterior
  const prevMonthDays = getDaysInMonth(
    currentMonth.value === 0 ? 11 : currentMonth.value - 1,
    currentMonth.value === 0 ? currentYear.value - 1 : currentYear.value
  )

  const daysFromPrevMonth = Array.from(
    { length: blankDays.value },
    (_, i) => prevMonthDays - blankDays.value + 1 + i
  )

  // Dias do próximo mês (para preencher 42 dias no total)
  const remainingDays = 42 - (daysFromPrevMonth.length + daysInMonth.value)
  const nextMonthDays = Array.from({ length: remainingDays }, (_, i) => i + 1)

  // Combinar todos os dias
  calendarDays.value = [
    ...daysFromPrevMonth.map((day) => ({
      day,
      currentMonth: false,
      isNextMonth: false
    })),
    ...Array.from({ length: daysInMonth.value }, (_, i) => ({
      day: i + 1,
      currentMonth: true,
      isNextMonth: false
    })),
    ...nextMonthDays.map((day) => ({
      day,
      currentMonth: false,
      isNextMonth: true
    }))
  ]
}

// Navegar entre os meses
const prevMonth = () => {
  if (currentMonth.value === 0) {
    currentMonth.value = 11
    currentYear.value--
  } else {
    currentMonth.value--
  }
  updateCalendar()
}

const nextMonth = () => {
  if (currentMonth.value === 11) {
    currentMonth.value = 0
    currentYear.value++
  } else {
    currentMonth.value++
  }
  updateCalendar()
}

// Selecionar data e atualizar o v-model
const selectDay = (day) => {
  let selectedMonth = currentMonth.value
  let selectedYear = currentYear.value
  let hour = selectedHour.value
  let minute = selectedMinute.value
  currentDay.value = day.day

  if (!day.currentMonth) {
    if (day.isNextMonth) {
      // Se for do próximo mês
      if (currentMonth.value === 11) {
        selectedMonth = 0
        selectedYear++
      } else {
        selectedMonth++
      }
    } else {
      // Se for do mês anterior
      if (currentMonth.value === 0) {
        selectedMonth = 11
        selectedYear--
      } else {
        selectedMonth--
      }
    }
  }

  const pad = (n) => (n < 10 ? `0${n}` : n) // Formatar dia/mês com 2 dígitos

  internalValue.value = props.showHours
    ? `${selectedYear}-${pad(selectedMonth + 1)}-${pad(day.day)} ${pad(hour) + ":" + pad(minute)}`
    : `${selectedYear}-${pad(selectedMonth + 1)}-${pad(day.day)}`
}

const isSelectedDay = (day, currentMonth, currentYear) => {
  // Destaca o dia selecionado corretamente
  if (!internalValue.value) return false

  const [selectedDate] = internalValue.value.split(" ")
  const [selectedYear, selectedMonth, selectedDay] = selectedDate
    .split("-")
    .map(Number)

  return (
    day === selectedDay &&
    currentMonth + 1 === selectedMonth && // currentMonth é baseado em zero, então somamos 1
    currentYear === selectedYear
  )
}

// Verifica se é o dia atual
const isToday = (day) => {
  const today = new Date()
  return (
    day === today.getDate() &&
    currentMonth.value === today.getMonth() &&
    currentYear.value === today.getFullYear()
  )
}

onMounted(() => {
  const pad = (n) => (n < 10 ? `0${n}` : n)

  // Adiciona o listener de clique fora com um pequeno delay
  setTimeout(() => {
    if (isVisible.value) {
      document.addEventListener("click", handleClickOutside)
    }
  }, 30)

  // Se internalValue estiver vazio, inicializa com a data/hora atuais
  if (!internalValue.value || internalValue.value.trim() === "") {
    resetToToday()
  } else {
    // Caso o modelValue já possua valor, você pode fazer a extração e atribuição dos estados
    if (props.showHours && !props.showCalendar) {
      // Apenas hora (formato "HH:mm")
      const [hour, minute] = internalValue.value.split(":").map(Number)
      selectedHour.value = hour
      selectedMinute.value = minute
      // Para a parte de data, pode-se definir a data atual, se necessário
      const hoje = new Date()
      currentYear.value = hoje.getFullYear()
      currentMonth.value = hoje.getMonth()
      currentDay.value = hoje.getDate()
    } else if (!props.showHours) {
      // Apenas data (formato "YYYY-MM-DD")
      const [year, month, day] = internalValue.value.split("-").map(Number)
      currentYear.value = year
      currentMonth.value = month - 1
      currentDay.value = day
    } else {
      // Data e hora (formato "YYYY-MM-DD HH:mm")
      const [datePart, timePart] = internalValue.value.split(" ")
      const [year, month, day] = datePart.split("-").map(Number)
      currentYear.value = year
      currentMonth.value = month - 1
      currentDay.value = day
      if (timePart) {
        const [hour, minute] = timePart.split(":").map(Number)
        selectedHour.value = hour
        selectedMinute.value = minute
      } else {
        initializeCurrentTime()
      }
    }
  }
  updateCalendar()
})

onUnmounted(() => {
  document.removeEventListener("click", handleClickOutside)
})

const resetToToday = () => {
  const hoje = new Date()
  const pad = (n) => (n < 10 ? `0${n}` : n)

  if (props.showHours && !props.showCalendar) {
    // Apenas hora
    selectedHour.value = hoje.getHours()
    selectedMinute.value = hoje.getMinutes()
    internalValue.value = `${pad(hoje.getHours())}:${pad(hoje.getMinutes())}`
    // Opcional: se precisar, também atualize a data
    currentYear.value = hoje.getFullYear()
    currentMonth.value = hoje.getMonth()
    currentDay.value = hoje.getDate()
  } else if (!props.showHours) {
    // Apenas data
    currentYear.value = hoje.getFullYear()
    currentMonth.value = hoje.getMonth()
    currentDay.value = hoje.getDate()
    internalValue.value = `${hoje.getFullYear()}-${pad(hoje.getMonth() + 1)}-${pad(hoje.getDate())}`
  } else {
    // Data e hora
    currentYear.value = hoje.getFullYear()
    currentMonth.value = hoje.getMonth()
    currentDay.value = hoje.getDate()
    selectedHour.value = hoje.getHours()
    selectedMinute.value = hoje.getMinutes()
    internalValue.value = `${hoje.getFullYear()}-${pad(hoje.getMonth() + 1)}-${pad(hoje.getDate())} ${pad(hoje.getHours())}:${pad(hoje.getMinutes())}`
  }

  updateCalendar()
}

// Variável para controlar a visibilidade do dropdown de meses
const isMonthDropdownVisible = ref(false)

// Função para alternar a visibilidade do dropdown de meses
const toggleMonthDropdown = () => {
  isMonthDropdownVisible.value = !isMonthDropdownVisible.value
}

const mesClicado = ref(false)

// Função para selecionar o mês
const selectMonth = (monthIndex) => {
  mesClicado.value = true
  currentMonth.value = monthIndex
  isMonthDropdownVisible.value = false // Fecha o dropdown
  updateCalendar() // Atualiza o calendário com o novo mês
  setTimeout(() => {
    if (isVisible.value) {
      // Garantir que o calendário só será fechado se já estiver visível
      mesClicado.value = false
    }
  }, 30) // Espera 1 segundo (1000 ms)
}

let intervalId = null
function nextYear() {
  var year = parseInt(currentYear.value)
  year++
  currentYear.value = year
}

function prevYear() {
  var year = parseInt(currentYear.value)
  year--
  currentYear.value = year
}

// Estados reativos para horas e minutos
const selectedHour = ref(new Date().getHours())
const selectedMinute = ref(new Date().getMinutes())

const initializeCurrentTime = () => {
  const now = new Date()
  selectedHour.value = now.getHours()
  selectedMinute.value = now.getMinutes()
  updateTime() // Atualiza o valor interno
}

const updateTime = () => {
  const pad = (n) => (n < 10 ? `0${n}` : n)

  // Se não há um valor interno definido ainda, usamos os valores atuais dos estados
  if (!internalValue.value) {
    if (props.showHours && !props.showCalendar) {
      // Apenas hora
      internalValue.value = `${pad(selectedHour.value)}:${pad(selectedMinute.value)}`
    } else if (!props.showHours) {
      // Apenas data
      internalValue.value = `${currentYear.value}-${pad(currentMonth.value + 1)}-${pad(currentDay.value)}`
    } else {
      // Ambos verdadeiros: data e hora
      internalValue.value = `${currentYear.value}-${pad(currentMonth.value + 1)}-${pad(currentDay.value)} ${pad(selectedHour.value)}:${pad(selectedMinute.value)}`
    }
  } else {
    // Quando já existe um valor, atualizamos-o conforme o cenário
    if (props.showHours && !props.showCalendar) {
      internalValue.value = `${pad(formattedHour.value)}:${pad(formattedMinutes.value)}`
    } else if (!props.showHours) {
      internalValue.value = `${currentYear.value}-${pad(currentMonth.value + 1)}-${pad(currentDay.value)}`
    } else {
      // Para o caso em que ambos são true, preserva a parte da data (seja extraída do valor atual ou dos estados)
      // Aqui pode ser interessante garantir que a data exibida seja a dos estados atuais
      internalValue.value = `${currentYear.value}-${pad(currentMonth.value + 1)}-${pad(currentDay.value)} ${pad(selectedHour.value)}:${pad(selectedMinute.value)}`
    }
  }
}

const formattedHour = computed({
  get: () =>
    selectedHour.value < 10 ? "0" + selectedHour.value : selectedHour.value,
  set: (newValue) => {
    selectedHour.value = parseInt(newValue, 10) || 0
  }
})

const formattedMinutes = computed({
  get: () =>
    selectedMinute.value < 10
      ? "0" + selectedMinute.value
      : selectedMinute.value,
  set: (newValue) => {
    selectedMinute.value = parseInt(newValue, 10) || 0
  }
})

// Incrementar e decrementar horas
const incrementHour = () => {
  selectedHour.value = (selectedHour.value + 1) % 24
  updateTime()
}

const decrementHour = () => {
  selectedHour.value = (selectedHour.value - 1 + 24) % 24
  updateTime()
}

const incrementMinute = () => {
  selectedMinute.value = (selectedMinute.value + 1) % 60
  updateTime()
}

const decrementMinute = () => {
  selectedMinute.value = (selectedMinute.value - 1 + 60) % 60
  updateTime()
}

function saveFields() {
  updateTime()
  isVisible.value = false
}
</script>

<template>
  <div ref="calendarRef">
    <!-- Calendário flutuante modernizado -->
    <div
      v-if="isVisible"
      class="bg-white dark:bg-slate-800 shadow-2xl rounded-2xl w-80 z-50 relative overflow-hidden"
    >
      <!-- Header com gradiente -->
      <div class="bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-3">
        <div class="flex items-center justify-between">
          <!-- Botão anterior -->
          <button
            v-if="showCalendar"
            @click="prevMonth"
            class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all duration-200"
          >
            <i class="pi pi-chevron-left text-white text-sm"></i>
          </button>
          <div
            v-else
            class="w-8"
          ></div>

          <!-- Mês e Ano -->
          <button
            v-if="showCalendar"
            @click="toggleMonthDropdown"
            class="text-white font-semibold text-lg hover:bg-white/10 px-3 py-1 rounded-lg transition-all duration-200 flex items-center gap-2"
          >
            <span>{{ monthNames[currentMonth] }}</span>
            <span>{{ currentYear }}</span>
            <i class="pi pi-chevron-down text-xs opacity-70"></i>
          </button>
          <span
            v-else
            class="text-white font-semibold text-lg"
          >
            Selecionar Hora
          </span>

          <!-- Botão próximo -->
          <button
            v-if="showCalendar"
            @click="nextMonth"
            class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all duration-200"
          >
            <i class="pi pi-chevron-right text-white text-sm"></i>
          </button>
          <div
            v-else
            class="w-8"
          ></div>
        </div>
      </div>

      <!-- Dropdown de seleção do mês -->
      <div
        v-if="isMonthDropdownVisible && showCalendar"
        class="absolute top-0 left-0 right-0 bottom-0 bg-white dark:bg-slate-800 z-20 rounded-2xl overflow-hidden"
      >
        <!-- Header do dropdown -->
        <div class="bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-3">
          <div class="flex items-center justify-between">
            <button
              @click="prevYear()"
              class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all duration-200"
            >
              <i class="pi pi-chevron-left text-white text-sm"></i>
            </button>

            <input
              type="text"
              v-model="currentYear"
              class="bg-transparent border-0 text-white rounded-lg text-xl font-bold text-center w-24 focus:ring-0 focus:outline-none"
            />

            <button
              @click="nextYear()"
              class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all duration-200"
            >
              <i class="pi pi-chevron-right text-white text-sm"></i>
            </button>
          </div>
        </div>

        <!-- Grid de meses -->
        <div class="p-4">
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="(monthName, index) in monthNames"
              :key="index"
              @click="selectMonth(index)"
              class="py-3 px-2 rounded-xl text-sm font-medium transition-all duration-200"
              :class="{
                'bg-gradient-to-r from-cyan-500 to-blue-600 text-white shadow-lg':
                  index === currentMonth,
                'bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-600':
                  index !== currentMonth
              }"
            >
              {{ monthName.substring(0, 3) }}
            </button>
          </div>
        </div>
      </div>

      <!-- Conteúdo do calendário -->
      <div class="p-4">
        <!-- Grade do calendário -->
        <div v-if="showCalendar">
          <!-- Dias da semana -->
          <div class="grid grid-cols-7 gap-1 mb-2">
            <div
              v-for="day in daysOfWeek"
              :key="day"
              class="text-center text-xs font-semibold text-gray-400 dark:text-gray-500 py-2"
            >
              {{ day }}
            </div>
          </div>

          <!-- Dias do calendário -->
          <div class="grid grid-cols-7 gap-1">
            <button
              v-for="(item, index) in calendarDays"
              :key="index"
              class="aspect-square rounded-xl text-sm font-medium transition-all duration-200 flex items-center justify-center"
              :class="{
                'text-gray-300 dark:text-gray-600': !item.currentMonth,
                'bg-gradient-to-r from-rose-500 to-rose-600 text-white shadow-lg scale-105':
                  isSelectedDay(item.day, currentMonth, currentYear) &&
                  item.currentMonth,
                'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700':
                  item.currentMonth &&
                  !isSelectedDay(item.day, currentMonth, currentYear),
                'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-600 dark:text-cyan-400 font-bold':
                  isToday(item.day) &&
                  item.currentMonth &&
                  !isSelectedDay(item.day, currentMonth, currentYear)
              }"
              @click="selectDay(item)"
            >
              {{ item.day }}
            </button>
          </div>
        </div>

        <!-- Seletor de hora -->
        <div
          v-if="showHours"
          class="flex items-center justify-center gap-4 py-4"
          :class="{
            'border-t border-gray-200 dark:border-slate-700 mt-4': showCalendar
          }"
        >
          <!-- Controle de horas -->
          <div class="flex flex-col items-center">
            <button
              @click="incrementHour"
              class="w-10 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-600 transition-all duration-200 flex items-center justify-center"
            >
              <i class="pi pi-chevron-up text-xs"></i>
            </button>
            <input
              v-model="formattedHour"
              type="text"
              class="w-14 h-12 text-center text-xl font-bold border-2 border-gray-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-700 text-gray-800 dark:text-white focus:border-cyan-500 dark:focus:border-cyan-400 focus:ring-0 my-1"
            />
            <button
              @click="decrementHour"
              class="w-10 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-600 transition-all duration-200 flex items-center justify-center"
            >
              <i class="pi pi-chevron-down text-xs"></i>
            </button>
          </div>

          <!-- Separador -->
          <div class="text-2xl font-bold text-gray-400 dark:text-gray-500">
            :
          </div>

          <!-- Controle de minutos -->
          <div class="flex flex-col items-center">
            <button
              @click="incrementMinute"
              class="w-10 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-600 transition-all duration-200 flex items-center justify-center"
            >
              <i class="pi pi-chevron-up text-xs"></i>
            </button>
            <input
              v-model="formattedMinutes"
              type="text"
              class="w-14 h-12 text-center text-xl font-bold border-2 border-gray-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-700 text-gray-800 dark:text-white focus:border-cyan-500 dark:focus:border-cyan-400 focus:ring-0 my-1"
            />
            <button
              @click="decrementMinute"
              class="w-10 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-600 transition-all duration-200 flex items-center justify-center"
            >
              <i class="pi pi-chevron-down text-xs"></i>
            </button>
          </div>
        </div>

        <!-- Botões de ação -->
        <div class="flex gap-2 mt-4">
          <button
            @click="saveFields()"
            class="flex-1 py-2.5 px-4 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white font-semibold shadow-lg transition-all duration-200"
          >
            Salvar
          </button>
          <button
            v-if="showCalendar"
            @click="resetToToday"
            class="py-2.5 px-4 rounded-xl bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 font-semibold hover:bg-gray-200 dark:hover:bg-slate-600 transition-all duration-200"
          >
            Hoje
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
