<template>
  <MultiSelect
    v-if="props.multiSelect"
    v-model="valorSelecionado"
    :options="opcoes"
    :placeholder="props.placeholder"
    :optionLabel="formatarLabel"
    :optionValue="codigo"
    max-selected-labels="2"
    selected-items-label="{id} Filiais selecionadas"
    filter
    :disabled="props.disabled"
    :multiple="props.multiSelect"
    class="flex items-center h-10 w-full"
    :show-clear="props.showClear"
  />
  <Select
    v-else
    v-model="valorSelecionado"
    :placeholder="props.placeholder"
    :options="opcoes"
    :optionLabel="formatarLabel"
    :optionValue="codigo"
    filter
    :auto-filter-focus="true"
    :disabled="props.disabled"
    class="flex items-center h-10 w-full"
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
    emit(
      "update:modelValue",
      val.map((item) => ({
        codigo: item.codigo,
        fantasia: item.fantasia
      }))
    )
  } else {
    emit("update:modelValue", {
      codigo: val.codigo,
      fantasia: val.fantasia
    })
  }
})

// Carrega as opções de filiais ao montar o componente
onMounted(async () => {
  await carregarFiliais()
  if (props.preSelected && props.preSelected.length) {
    // Extrai os códigos das filiais do preSelected
    var codigosPreSelected = props.preSelected.map((item) => item.filial.codigo)
    if (props.multiSelect) {
      valorSelecionado.value = opcoes.value.filter((opcao) =>
        codigosPreSelected.includes(Number(opcao.codigo))
      )
    } else {
      valorSelecionado.value =
        opcoes.value.find((opcao) =>
          codigosPreSelected.includes(Number(opcao.codigo))
        ) || null
    }
  }
})

// Função para carregar todas as filiais
async function carregarFiliais() {
  try {
    const res = await axios.get("/util/filiais-usuario")
    opcoes.value = res.data
  } catch (e) {
    console.error("Erro ao carregar filiais:", e)
  }
}

// Formata o rótulo das opções
function formatarLabel(opcao) {
  return `${opcao.codigo} - ${opcao.fantasia}`
}

// Função para limpar a seleção (chamada externamente, se necessário)
function limparSelecao() {
  valorSelecionado.value = props.multiSelect ? [] : null
}
</script>
