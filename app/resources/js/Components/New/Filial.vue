<script setup>
import { MultiSelect } from "primevue"
import { ref, watch } from "vue"

const props = defineProps({
  modelValue: [String, Object],
  label: String,
  error: String,
  disabled: Boolean,
  inputClass: String,
  retornaObjeto: Boolean,
  multiSelect: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(["update:modelValue"])

const filiais = ref([])
const selectedCodigo = ref(props.multiSelect ? [] : "")

// Atualiza o código selecionado baseado no modelValue
watch(
  () => props.modelValue,
  (val) => {
    if (props.retornaObjeto && val && typeof val === "object") {
      selectedCodigo.value = val.codigo
    } else {
      selectedCodigo.value = val || ""
    }
  },
  { immediate: true }
)

watch(filiais, () => {
  if (props.modelValue && props.retornaObjeto) {
    const existe = filiais.value.find((f) => f.codigo == selectedCodigo.value)
    if (existe) {
      selectedCodigo.value = existe.codigo
    }
  }
})

const handleSelect = (value) => {
  if (props.multiSelect) {
    if (props.retornaObjeto) {
      const filiaisSelecionadas = filiais.value.filter((f) =>
        value.includes(f.codigo)
      )
      emit("update:modelValue", filiaisSelecionadas)
    } else {
      emit("update:modelValue", value)
    }
  } else {
    const codigo = value?.target ? value.target.value : value
    if (props.retornaObjeto) {
      const filial = filiais.value.find((f) => f.codigo == codigo)
      emit("update:modelValue", filial)
    } else {
      emit("update:modelValue", codigo)
    }
  }
}

// Fetch
const fetchFiliais = async () => {
  try {
    const response = await fetch("/util/filiais-usuario")
    const data = await response.json()
    filiais.value = data
  } catch (e) {
    console.error("Erro ao buscar filiais", e)
  }
}

const labelFilial = (filial) => {
  return `${filial.codigo} - ${filial.fantasia}`
}

fetchFiliais()
</script>
<template>
  <div class="flex flex-col gap-2">
    <MultiSelect
      v-if="props.multiSelect"
      v-model="selectedCodigo"
      @update:modelValue="handleSelect"
      :disabled="disabled"
      id="selectFiliais"
      name="selectFiliais"
      class="w-full px-2 py-0 border border-gray-300 rounded-md text-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-200 disabled:cursor-not-allowed"
      :class="inputClass"
      :options="filiais"
      :option-label="labelFilial"
      :option-value="'codigo'"
      max-selected-labels="1"
      filter
      :selected-items-label="`${selectedCodigo.length} Selecionadas`"
    />

    <select
      v-else
      v-model="selectedCodigo"
      @change="handleSelect"
      :disabled="disabled"
      id="selectFiliais"
      name="selectFiliais"
      class="w-full px-2 py-0 border border-gray-300 rounded-md text-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-200 disabled:cursor-not-allowed"
      :class="inputClass"
    >
      <option
        disabled
        value=""
      >
        {{ placeholder }}
      </option>
      <option
        v-for="filial in filiais"
        :key="filial.id"
        :value="filial.codigo"
      >
        {{ filial.codigo }} - {{ filial.fantasia }}
      </option>
    </select>
    <span
      v-if="error"
      class="text-sm text-red-500"
    >
      {{ error }}
    </span>
  </div>
</template>
