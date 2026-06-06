<template>
  <AutoComplete
    v-model="valorSelecionado"
    :suggestions="sugestoes"
    @complete="buscarFornecedor"
    @item-select="aoSelecionarFornecedor"
    :optionLabel="formatarLabel"
    :optionValue="valorCodfornec"
    placeholder="Buscar fornecedor..."
    fluid
    :multiple="multiSelect"
  />
</template>

<script setup>
import { ref, watch } from "vue"
import AutoComplete from "primevue/autocomplete"
import axios from "axios"

// Props e v-model
const props = defineProps({
  modelValue: [String, Number],
  multiSelect: [Boolean],
  retornaObjeto: [Boolean]
})
const emit = defineEmits(["update:modelValue"])

const valorSelecionado = ref(null)
const sugestoes = ref([])
const isAtualizando = ref(false)
// Atualiza modelValue quando seleciona um item
watch(valorSelecionado, (val) => {
  if (!val) {
    emit("update:modelValue", null)
  }
})

watch(
  () => props.modelValue,
  async (val) => {
    if (!val) {
      valorSelecionado.value = null
      return
    }

    // Se já for um objeto com .codfornec, pode atribuir direto
    if (props.retornaObjeto && typeof val === "object" && val?.codfornec) {
      valorSelecionado.value = val
    } else {
      // Senão, tenta carregar o fornecedor pelo código
      await carregarFornecedor(val)
    }
  },
  { immediate: true }
)

function aoSelecionarFornecedor(event) {
  const fornecedor = event.value

  isAtualizando.value = true
  if (props.multiSelect) {
    emit("update:modelValue", valorSelecionado.value)
  } else {
    if (props.retornaObjeto) {
      emit("update:modelValue", fornecedor)
    } else {
      emit("update:modelValue", fornecedor?.codfornec || null)
    }
  }
}

// Quando modelValue muda externamente (ex: já vem preenchido), busca os dados
watch(
  () => props.modelValue,
  async (val) => {
    if (isAtualizando.value) {
      isAtualizando.value = false
      return
    }
    if (!val) {
      valorSelecionado.value = null
      return
    }

    if (props.retornaObjeto) {
      valorSelecionado.value = val
    } else {
      await carregarFornecedor(val)
    }
  },
  { immediate: true }
)

function buscarFornecedor(event) {
  axios
    .get(route("documentos.assinatura-digital.buscar-fornecedores"), {
      params: {
        query: event.query
      }
    })
    .then((response) => {
      sugestoes.value = response.data
    })
    .catch(() => {
      sugestoes.value = []
    })
}

function formatarLabel(opcao) {
  return `${opcao.codfornec} - ${opcao.fornecedor}`
}

function valorCodfornec(opcao) {
  return opcao.codfornec
}
</script>
