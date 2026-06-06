<template>
  <MultiSelect
    v-if="props.multiSelect"
    v-model="valorSelecionado"
    :options="opcoes"
    :placeholder="props.placeholder"
    max-selected-labels="2"
    selected-items-label="{id} Filiais selecionadas"
    filter
    :disabled="props.disabled"
    :multiple="props.multiSelect"
    class="flex items-center h-10"
    :show-clear="props.showClear"
  />
  <Select
    v-else
    v-model="valorSelecionado"
    :placeholder="props.placeholder"
    :options="opcoes"
    filter
    :disabled="props.disabled"
    class="flex items-center h-10"
    :show-clear="props.showClear"
  />
</template>

<script setup>
import { ref, watch, onMounted } from "vue"
import MultiSelect from "primevue/multiselect"
import axios from "axios"
import { Select } from "primevue"

// Props e v-model
const props = defineProps({
  modelValue: [String, Array, Object],
  multiSelect: [Boolean],
  retornaObjeto: [Boolean],
  disabled: Boolean,
  preSelected: Array,
  placeholder: String,
  showClear: { type: Boolean, default: false }
})
const emit = defineEmits(["update:modelValue"])

const valorSelecionado = ref(props.multiSelect ? [] : null)
const opcoes = ref([])

// Atualiza modelValue quando seleciona um item
watch(valorSelecionado, (val) => {
  if (!val || (Array.isArray(val) && val.length === 0)) {
    emit("update:modelValue", props.multiSelect ? [] : null)
    return
  }
  if (props.multiSelect) {
    emit("update:modelValue", valorSelecionado.value)
  } else {
    emit("update:modelValue", valorSelecionado.value)
  }
})

// Carrega as opções de departamentos ao montar o componente
onMounted(async () => {
  await carregarDpto()
})

// Função para carregar todas as departamentos
async function carregarDpto() {
  try {
    const res = await axios.get("/util/depto-usuarios")
    opcoes.value = res.data.dados
  } catch (e) {
    console.error("Erro ao carregar departamentos:", e)
  }
}

// Função para limpar a seleção (chamada externamente, se necessário)
function limparSelecao() {
  valorSelecionado.value = props.multiSelect ? [] : null
}
</script>
