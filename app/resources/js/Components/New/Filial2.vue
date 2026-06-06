<template>
  <MultiSelect
    v-if="props.multiSelect"
    v-model="valorSelecionado"
    :options="opcoes"
    :optionLabel="formatarLabel"
    :optionValue="valorCodigo"
    max-selected-labels="2"
    selected-items-label="{id} Filiais selecionadas"
    filter
    :disabled="props.disabled"
    :multiple="props.multiSelect"
    class="flex items-center h-10"
  />
  <Select
    v-else
    v-model="valorSelecionado"
    :options="opcoes"
    :optionLabel="formatarLabel"
    :optionValue="valorCodigo"
    filter
    :disabled="props.disabled"
    class="flex items-center h-10"
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
  // Nova prop: se true, lê filiais da URL e pré-seleciona
  carregarDaUrl: {
    type: Boolean,
    default: false
  }
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

  // Se a prop carregarDaUrl estiver ativa, verificar se há filiais na URL para pré-selecionar
  if (props.carregarDaUrl && props.multiSelect) {
    const query = new URLSearchParams(window.location.search)
    const filiaisParam = query.get("filiais")

    if (filiaisParam) {
      const codigosUrl = filiaisParam.split(",").map(decodeURIComponent)

      // Encontrar os objetos completos das filiais pelos códigos
      const filiaisSelecionadas = opcoes.value.filter((f) =>
        codigosUrl.includes(f.codigo)
      )

      if (filiaisSelecionadas.length > 0) {
        valorSelecionado.value = filiaisSelecionadas
      }
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
