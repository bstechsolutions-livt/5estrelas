<script setup>
import { computed, onMounted, ref } from "vue"

// Definindo as props com tipos
const props = defineProps({
  modelValue: {
    type: [String, Number, Object],
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

const filiais = ref([])

// Propriedade reativa para controlar o v-model
const internalValue = computed({
  get: () => props.modelValue,
  set: (novoValor) => emit("update:modelValue", novoValor)
})

// Verifica se tem valor
const hasValue = computed(
  () =>
    props.modelValue !== null &&
    props.modelValue !== undefined &&
    props.modelValue !== ""
)

// Limpar valor
function clearValue() {
  emit("update:modelValue", null)
}

onMounted(async () => {
  await getFiliais()
})

async function getFiliais() {
  await axios
    .get("/util/get-filiais")
    .then((res) => {
      filiais.value = res.data
    })
    .catch((err) => {
      console.error(err)
    })
}
</script>

<template>
  <div class="relative w-full">
    <!-- Label flutuante -->
    <label
      v-if="label"
      for="select-filial"
      class="absolute -top-2 left-3 z-10 px-1.5 select-none text-xs font-medium rounded bg-white dark:bg-slate-700 transition-colors duration-200"
      :class="{
        'text-gray-600 dark:text-gray-300': !invalid,
        'text-red-600 dark:text-red-400': invalid
      }"
    >
      {{ label }}
    </label>

    <!-- Select modernizado -->
    <div class="relative">
      <select
        :disabled="disabled"
        id="select-filial"
        name="select-filial"
        v-model="internalValue"
        class="w-full h-[42px] px-3 appearance-none border rounded-xl bg-white dark:bg-slate-700 text-gray-800 dark:text-white transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
        :class="{
          'border-gray-300 dark:border-slate-600 focus:border-cyan-500 focus:ring-cyan-500/20 dark:focus:border-cyan-400 dark:focus:ring-cyan-400/20 hover:border-gray-400 dark:hover:border-slate-500':
            !invalid,
          'border-red-500 dark:border-red-400 focus:border-red-500 focus:ring-red-500/20':
            invalid,
          'pr-20': showClear && hasValue,
          'pr-10': !showClear || !hasValue
        }"
      >
        <option
          value=""
          disabled
          class="text-gray-400"
        >
          Selecione uma filial
        </option>
        <option
          v-for="option in filiais"
          :key="option.codigo"
          :value="option.codigo"
          class="bg-white dark:bg-slate-700 text-gray-800 dark:text-white py-2"
        >
          {{ option.codigo }} - {{ option.fantasia }}
        </option>
      </select>

      <!-- Ícones à direita -->
      <div
        class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1 pointer-events-none"
      >
        <!-- Botão Limpar -->
        <button
          v-if="showClear && hasValue && !disabled"
          @click.stop="clearValue"
          type="button"
          class="pointer-events-auto w-7 h-7 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-600 transition-all duration-200"
          title="Limpar"
        >
          <i class="pi pi-times text-xs"></i>
        </button>

        <!-- Ícone de seta -->
        <div
          class="w-7 h-7 rounded-lg flex items-center justify-center bg-gradient-to-r from-cyan-500 to-blue-600 text-white"
        >
          <i class="pi pi-chevron-down text-xs"></i>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Remove a seta padrão do select em diferentes navegadores */
select {
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  background-image: none;
}

select::-ms-expand {
  display: none;
}

/* Estilo para as opções do select em dark mode */
@media (prefers-color-scheme: dark) {
  select option {
    background-color: #334155;
    color: #f1f5f9;
  }

  /* Remove o seletor global que força o fundo escuro nas opções do select */
  select option {
    background-color: transparent; /* or any other default color */
    color: inherit; /* or any other default color */
  }
}

.dark select option {
  background-color: #334155;
  color: #f1f5f9;
}
</style>
