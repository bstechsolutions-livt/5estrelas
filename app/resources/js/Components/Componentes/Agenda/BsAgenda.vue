<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from "vue"
import Button from "primevue/button"
import SelectButton from "primevue/selectbutton"
import Agenda from "./partials/Agenda.vue"
import Mensal from "./partials/Mensal.vue"
import Semanal from "./partials/Semanal.vue"
import Diario from "./partials/Diario.vue"

const props = defineProps({
  modelValue: {
    type: Object,
    required: true
  },
  mes: {
    required: false,
    default: true
  },
  agenda: {
    type: Boolean,
    required: false,
    default: true
  },
  semana: {
    type: Boolean,
    required: false,
    default: true
  },
  dia: {
    type: Boolean,
    required: false,
    default: true
  },
  verTodos: {
    type: Boolean,
    required: false,
    default: false
  }
})

const emits = defineEmits(["busca-por-data", "detalhar", "update-data"])

const tipo = ref(
  props.mes
    ? "mensal"
    : props.agenda
      ? "agenda"
      : props.semana
        ? "semanal"
        : props.dia
          ? "diario"
          : ""
)
const isMobile = ref(false)
const dataIni = ref("")
const dataFim = ref("")
const baseDate = ref(new Date())

// Opções para o SelectButton de tipo de visualização
const tipoOptions = computed(() => {
  const options = []
  if (props.agenda)
    options.push({ label: "Agenda", value: "agenda", icon: "pi pi-list" })
  if (props.mes)
    options.push({ label: "Mês", value: "mensal", icon: "pi pi-calendar" })
  if (props.semana && !props.verTodos)
    options.push({ label: "Semana", value: "semanal", icon: "pi pi-th-large" })
  if (props.dia && !props.verTodos)
    options.push({ label: "Dia", value: "diario", icon: "pi pi-clock" })
  return options
})

// Legenda de status - organizada por tipo e depois status
const statusLegend = [
  {
    label: "Visita",
    color: "bg-amber-500",
    pulse: false,
    icon: "pi pi-car",
    description: "Visita agendada"
  },
  {
    label: "Lembrete",
    color: "bg-gradient-to-r from-amber-400 to-orange-500",
    pulse: false,
    icon: "pi pi-bookmark",
    description: "Lembrete na agenda"
  },
  {
    label: "Pendente",
    color: "bg-yellow-400",
    pulse: false,
    icon: "pi pi-clock",
    description: "Aguardando atendimento"
  },
  {
    label: "Atendimento",
    color: "bg-blue-500",
    pulse: true,
    icon: "pi pi-spin pi-cog",
    description: "Em atendimento"
  },
  {
    label: "Finalizado",
    color: "bg-emerald-500",
    pulse: false,
    icon: "pi pi-check",
    description: "Concluído"
  },
  {
    label: "Cancelado",
    color: "bg-red-500",
    pulse: false,
    icon: "pi pi-times",
    description: "Cancelado"
  }
]

function checkIsMobile() {
  isMobile.value = window.innerWidth < 768
}

onMounted(() => {
  checkIsMobile()
  window.addEventListener("resize", checkIsMobile)
  emits("update-data")
})

onUnmounted(() => {
  window.removeEventListener("resize", checkIsMobile)
})

watch([dataIni, dataFim], ([newDataIni, newDataFim]) => {
  emits("update-data", { dataIni: newDataIni, dataFim: newDataFim })
})

watch(tipo, () => {
  baseDate.value = new Date()
  atualizaDatasFormat()
})

watch(
  () => props.verTodos,
  () => {
    tipo.value = "mensal"
  }
)

watch(props.modelValue, (newVal) => {
  if (newVal) {
    props.modelValue.dados.forEach((element) => {
      if (element.status == "ativo") {
        element.status = "aguardando"
      }
    })
  }
})

function atualizaDatasFormat() {
  const { start, end } = getDateRange(tipo.value, baseDate.value)
  const options = { day: "2-digit", month: "2-digit", year: "numeric" }
  dataIni.value = start.toLocaleDateString("pt-BR", options)
  dataFim.value = end.toLocaleDateString("pt-BR", options)
}

function getDateRange(tipo, vBaseDate = new Date()) {
  let start, end

  if (tipo === "mensal" || tipo === "agenda") {
    start = new Date(vBaseDate.getFullYear(), vBaseDate.getMonth(), 1)
    end = new Date(vBaseDate.getFullYear(), vBaseDate.getMonth() + 1, 0)
  } else if (tipo === "semanal") {
    const dayOfWeek = vBaseDate.getDay()
    start = new Date(vBaseDate)
    start.setDate(vBaseDate.getDate() - dayOfWeek)
    end = new Date(vBaseDate)
    end.setDate(vBaseDate.getDate() + (6 - dayOfWeek))
  } else if (tipo === "diario") {
    start = vBaseDate
    end = vBaseDate
  }

  return { start, end }
}

const mesExtenso = computed(() => {
  const { start } = getDateRange(tipo.value, baseDate.value)
  return start.toLocaleString("pt-BR", { month: "long" })
})

const anoExtenso = computed(() => {
  const { start } = getDateRange(tipo.value, baseDate.value)
  return start.getFullYear()
})

function previous() {
  if (tipo.value === "mensal" || tipo.value === "agenda") {
    baseDate.value = new Date(
      baseDate.value.getFullYear(),
      baseDate.value.getMonth() - 1,
      1
    )
  } else if (tipo.value === "semanal") {
    baseDate.value = new Date(
      baseDate.value.getFullYear(),
      baseDate.value.getMonth(),
      baseDate.value.getDate() - 7
    )
  } else if (tipo.value === "diario") {
    baseDate.value = new Date(
      baseDate.value.getFullYear(),
      baseDate.value.getMonth(),
      baseDate.value.getDate() - 1
    )
  }
  atualizaDatasFormat()
}

function next() {
  if (tipo.value === "mensal" || tipo.value === "agenda") {
    baseDate.value = new Date(
      baseDate.value.getFullYear(),
      baseDate.value.getMonth() + 1,
      1
    )
  } else if (tipo.value === "semanal") {
    baseDate.value = new Date(
      baseDate.value.getFullYear(),
      baseDate.value.getMonth(),
      baseDate.value.getDate() + 7
    )
  } else if (tipo.value === "diario") {
    baseDate.value = new Date(
      baseDate.value.getFullYear(),
      baseDate.value.getMonth(),
      baseDate.value.getDate() + 1
    )
  }
  atualizaDatasFormat()
}

function abrirDetalhes(comp) {
  emits("detalhar", comp)
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <!-- Header com controles -->
    <div
      class="flex flex-col lg:flex-row items-center gap-4 p-4 bg-gray-50 dark:bg-slate-700/50 rounded-xl border border-gray-200 dark:border-slate-600"
    >
      <!-- Botões de tipo de visualização -->
      <div class="flex items-center gap-2">
        <SelectButton
          v-model="tipo"
          :options="tipoOptions"
          optionLabel="label"
          optionValue="value"
          :allowEmpty="false"
          :pt="{
            root: { class: '!rounded-xl overflow-hidden' },
            button: ({ context }) => ({
              class: [
                '!px-4 !py-2 !text-sm !font-medium transition-all duration-200',
                context.active
                  ? '!bg-gradient-to-r !from-cyan-500 !to-blue-600 !text-white !border-cyan-500'
                  : '!bg-white dark:!bg-slate-600 !text-gray-700 dark:!text-gray-200 !border-gray-300 dark:!border-slate-500 hover:!bg-gray-100 dark:hover:!bg-slate-500'
              ]
            })
          }"
        >
          <template #option="slotProps">
            <div class="flex items-center gap-2">
              <i :class="slotProps.option.icon"></i>
              <span class="hidden sm:inline">{{ slotProps.option.label }}</span>
            </div>
          </template>
        </SelectButton>
      </div>

      <!-- Legenda de Status -->
      <div
        class="flex flex-wrap items-center justify-center gap-3 px-4 py-2 bg-white dark:bg-slate-600 rounded-lg border border-gray-200 dark:border-slate-500"
      >
        <div
          v-for="status in statusLegend"
          :key="status.label"
          class="flex items-center gap-1.5"
          v-tooltip.top="status.description"
        >
          <div
            :class="[
              'w-4 h-4 rounded-full shadow-sm flex items-center justify-center',
              status.color,
              status.pulse ? 'animate-pulse' : ''
            ]"
          >
            <i
              v-if="status.icon"
              :class="[status.icon, 'text-[8px] text-white']"
            ></i>
          </div>
          <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
            {{ status.label }}
          </span>
        </div>
      </div>

      <!-- Navegação de período -->
      <div class="flex items-center gap-3 ml-auto">
        <!-- Exibição do período -->
        <div
          class="px-4 py-2 bg-white dark:bg-slate-600 rounded-lg border border-gray-200 dark:border-slate-500"
        >
          <div
            v-if="tipo == 'agenda' || tipo == 'mensal'"
            class="flex items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-gray-200"
          >
            <i class="pi pi-calendar text-cyan-500"></i>
            <span class="capitalize">{{ mesExtenso }}</span>
            <span class="text-gray-400 dark:text-gray-400">de</span>
            <span>{{ anoExtenso }}</span>
          </div>
          <div
            v-else
            class="flex items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-gray-200"
          >
            <i class="pi pi-calendar text-cyan-500"></i>
            <span>{{ dataIni }}</span>
            <span
              v-if="tipo == 'semanal'"
              class="text-gray-400 dark:text-gray-400"
            >
              até {{ dataFim }}
            </span>
          </div>
        </div>

        <!-- Botões de navegação -->
        <div class="flex items-center">
          <Button
            @click="previous"
            icon="pi pi-chevron-left"
            severity="secondary"
            text
            rounded
            class="!w-10 !h-10 hover:!bg-cyan-100 dark:hover:!bg-cyan-900/30"
            v-tooltip.top="'Anterior'"
          />
          <Button
            @click="next"
            icon="pi pi-chevron-right"
            severity="secondary"
            text
            rounded
            class="!w-10 !h-10 hover:!bg-cyan-100 dark:hover:!bg-cyan-900/30"
            v-tooltip.top="'Próximo'"
          />
        </div>
      </div>
    </div>

    <!-- Conteúdo do calendário -->
    <div
      class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden"
    >
      <Agenda
        v-if="tipo == 'agenda'"
        :dados="modelValue.dados"
        @detalhar="abrirDetalhes"
      />
      <Mensal
        v-if="tipo == 'mensal'"
        :dados="modelValue.dados"
        :dataBase="dataIni"
        @detalhar="abrirDetalhes"
      />
      <Semanal
        v-if="tipo == 'semanal'"
        :dados="modelValue.dados"
        :dataBase="dataIni"
        @detalhar="abrirDetalhes"
      />
      <Diario
        v-if="tipo == 'diario'"
        :dados="modelValue.dados"
        :dataBase="dataIni"
        @detalhar="abrirDetalhes"
      />
    </div>
  </div>
</template>
