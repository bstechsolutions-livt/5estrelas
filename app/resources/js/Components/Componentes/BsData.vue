<script setup>
import { computed, ref } from "vue"
import BsCalendario from "./BsCalendario.vue"

// Definindo as props com tipos
const props = defineProps({
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
  },
  disabled: {
    type: Boolean,
    required: false,
    default: false
  },
  showClear: {
    type: Boolean,
    required: false,
    default: true
  }
})

// Eventos que o componente emite
const emit = defineEmits(["update:modelValue"])

// Propriedade reativa para controlar o v-model
const internalValue = computed({
  get: () => props.modelValue,
  set: (novoValor) => emit("update:modelValue", novoValor)
})

// Variáveis
const showCalendario = ref(false)

// Limpar valor
function clearValue() {
  emit("update:modelValue", "")
}

// Verifica se tem valor para mostrar o botão de limpar
const hasValue = computed(() => props.modelValue && props.modelValue.length > 0)
</script>

<template>
  <div class="relative">
    <!-- Exibe a label se for fornecida -->
    <label
      v-if="label"
      for="date-input"
      class="absolute -top-2 left-2 z-10 px-1.5 select-none text-xs font-medium rounded bg-white dark:bg-slate-700 transition-colors duration-200"
      :class="{
        'text-gray-600 dark:text-gray-300': !invalid,
        'text-red-600 dark:text-red-400': invalid
      }"
    >
      {{ label }}
    </label>

    <!-- Input de data -->
    <input
      id="date-input"
      :type="
        showHours && !showCalendar
          ? 'time'
          : showHours && showCalendar
            ? 'datetime-local'
            : 'date'
      "
      v-model="internalValue"
      :disabled="disabled"
      class="w-full h-[42px] px-3 border rounded-xl bg-white dark:bg-slate-700 text-gray-800 dark:text-white transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0 disabled:opacity-50 disabled:cursor-not-allowed"
      :class="{
        'border-gray-300 dark:border-slate-600 focus:border-cyan-500 focus:ring-cyan-500/20 dark:focus:border-cyan-400 dark:focus:ring-cyan-400/20':
          !invalid,
        'border-red-500 dark:border-red-400 focus:border-red-500 focus:ring-red-500/20':
          invalid,
        'pr-20': showClear && hasValue,
        'pr-12': !showClear || !hasValue
      }"
    />

    <!-- Botões de ação -->
    <div
      class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1"
    >
      <!-- Botão Limpar -->
      <button
        v-if="showClear && hasValue && !disabled"
        @click.stop="clearValue"
        type="button"
        class="w-7 h-7 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-600 transition-all duration-200"
        title="Limpar"
      >
        <i class="pi pi-times text-xs"></i>
      </button>

      <!-- Botão Calendário -->
      <button
        @click.stop="showCalendario = true"
        type="button"
        :disabled="disabled"
        class="w-8 h-8 rounded-lg flex items-center justify-center bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-600 hover:to-blue-700 text-white shadow-sm transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
        title="Abrir calendário"
      >
        <i class="pi pi-calendar text-sm"></i>
      </button>
    </div>

    <!-- Dialog do Calendário -->
    <Dialog
      v-model:visible="showCalendario"
      modal
      :pt="{
        root: { class: '!rounded-2xl !overflow-hidden !border-0' },
        mask: { class: 'backdrop-blur-sm' },
        content: { class: '!p-0' }
      }"
    >
      <template #container="{ closeCallback }">
        <BsCalendario
          v-model="internalValue"
          v-model:visible="showCalendario"
          :showCalendar="showCalendar"
          :showHours="showHours"
        ></BsCalendario>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
@reference "tailwindcss";
input[type="date"]::-webkit-calendar-picker-indicator {
  display: none !important;
}
input[type="datetime-local"]::-webkit-calendar-picker-indicator {
  display: none !important;
}
input[type="time"]::-webkit-calendar-picker-indicator {
  display: none !important;
}

/* Estilo para placeholder em dark mode */
input::placeholder {
  @apply text-gray-400 dark:text-gray-500;
}

/* Remove o spinner de números em inputs de data */
input::-webkit-inner-spin-button,
input::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
</style>
