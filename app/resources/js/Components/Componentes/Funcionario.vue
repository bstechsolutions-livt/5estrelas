<template>
  <AutoComplete
    v-model="valorSelecionado"
    :suggestions="sugestoes"
    @complete="buscarFuncionario"
    @item-select="aoSelecionarFuncionario"
    :optionLabel="formatarLabel"
    :optionValue="valorMatricula"
    placeholder="Buscar funcionário..."
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
  retornaObjeto: [Boolean],
  apenasClts: [Boolean] // Nova prop para filtrar apenas CLTs
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

    // Se já for um objeto com .matricula, pode atribuir direto
    if (props.retornaObjeto && typeof val === "object" && val?.matricula) {
      valorSelecionado.value = val
    } else {
      // Senão, tenta carregar o funcionário pela matrícula
      await carregarFuncionario(val)
    }
  },
  { immediate: true }
)

function aoSelecionarFuncionario(event) {
  const funcionario = event.value

  isAtualizando.value = true
  if (props.multiSelect) {
    emit("update:modelValue", valorSelecionado.value)
  } else {
    if (props.retornaObjeto) {
      emit("update:modelValue", funcionario)
    } else {
      emit("update:modelValue", funcionario?.matricula || null)
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
      await carregarFuncionario(val)
    }
  },
  { immediate: true }
)

async function carregarFuncionario(matricula) {
  try {
    const res = await axios.get("/util/funcionarios2", {
      params: { busca: matricula }
    })

    const funcionario = res.data.find(
      (f) => `${f.matricula}` === `${matricula}`
    )
    if (funcionario) {
      valorSelecionado.value = funcionario
    }
  } catch (e) {
    console.error("Erro ao carregar funcionário:", e)
  }
}

function buscarFuncionario(event) {
  const termo = event.query?.toLowerCase()
  if (!termo || termo.length < 0) {
    sugestoes.value = []
    return
  }

  axios
    .get("/util/funcionarios2", {
      params: { busca: termo }
    })
    .then((res) => {
      let funcionarios = res.data

      // Se apenasClts for true, filtra apenas funcionários com tipo 'F'
      if (props.apenasClts) {
        funcionarios = funcionarios.filter((f) => f.tipo === "F")
      }

      sugestoes.value = funcionarios.sort((a, b) => {
        // Ordena pelo campo 'matricula' do menor para o maior
        return Number(a.matricula) - Number(b.matricula)
      })
    })
}

function formatarLabel(opcao) {
  return `${opcao.matricula} - ${opcao.nome}`
}
</script>
