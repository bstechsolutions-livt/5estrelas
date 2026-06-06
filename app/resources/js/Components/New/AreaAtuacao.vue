<template>
  <MultiSelect
    v-if="props.multiSelect"
    v-model="valorSelecionado"
    :options="opcoes"
    optionLabel="nome"
    max-selected-labels="2"
    selected-items-label="{id} Areas selecionadas"
    filter
    :disabled="props.disabled"
    :multiple="props.multiSelect"
    class="flex items-center h-10"
  />
  <Select
    v-else
    v-model="valorSelecionado"
    :options="opcoes"
    optionLabel="nome"
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
  disabled: Boolean
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
        nome: item.nome
      }))
    )
  } else {
    emit("update:modelValue", {
      nome: val.nome
    })
  }
})

// Carrega as opções de departamentos ao montar o componente
onMounted(async () => {
  await carregarDepartamentos()
})

// Função para carregar todos os departamentos
async function carregarDepartamentos() {
  try {
    const res = await axios.get("/util/area-atuacao")
    opcoes.value = res.data
  } catch (e) {
    console.error("Erro ao carregar departamentos:", e)
  }
}
</script>
