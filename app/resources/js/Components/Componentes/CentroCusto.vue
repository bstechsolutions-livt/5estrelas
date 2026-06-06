<template>
  <AutoComplete
    v-model="localValue"
    :suggestions="sugestoes"
    @complete="buscarCentroCustos"
    @item-select="aoSelecionarCentroCusto"
    :optionLabel="formatarLabel"
    :optionValue="(opcao) => opcao"
    placeholder="Buscar centro de custo..."
    :fluid="fluid"
    :class="fluid ? 'w-full' : ''"
    :multiple="multiSelect"
  />
</template>

<script setup>
import { ref, computed, watch } from "vue"
import AutoComplete from "primevue/autocomplete"
import axios from "axios"

const props = defineProps({
  modelValue: [String, Number, Array, Object, null],
  multiSelect: Boolean,
  retornaObjeto: Boolean,
  fluid: Boolean
})
const emit = defineEmits(["update:modelValue"])

const sugestoes = ref([])

// Use um computed para refletir sempre o modelValue
const localValue = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val)
})

async function aoSelecionarCentroCusto(event) {
  const centroCusto = event.value

  try {
    if (centroCusto.tipo === "grupo") {
      const res = await axios.get(
        `/util/centros-por-grupo/${centroCusto.codigocentrocusto}`
      )
      const centrosDoGrupo = res.data

      if (props.multiSelect) {
        // Adicionar todos os centros do grupo ao array
        let novos = Array.isArray(localValue.value) ? [...localValue.value] : []
        centrosDoGrupo.forEach((centro) => {
          if (
            !novos.some(
              (item) => item.codigocentrocusto === centro.codigocentrocusto
            )
          ) {
            novos.push(centro)
          }
        })
        // Remover o grupo da seleção após adicionar os centros
        const idx = novos.findIndex(
          (item) =>
            item.codigocentrocusto === centroCusto.codigocentrocusto &&
            item.tipo === "grupo"
        )
        if (idx !== -1) {
          novos.splice(idx, 1)
        }
        emit("update:modelValue", novos)
      } else {
        emit("update:modelValue", centrosDoGrupo[0] || null)
      }
    } else {
      // Se for um centro de custo individual
      if (props.multiSelect) {
        let novos = Array.isArray(localValue.value) ? [...localValue.value] : []
        if (
          !novos.some(
            (item) => item.codigocentrocusto === centroCusto.codigocentrocusto
          )
        ) {
          novos.push(centroCusto)
        }
        emit("update:modelValue", novos)
      } else {
        emit("update:modelValue", centroCusto)
      }
    }
  } catch (e) {
    console.error(
      "Erro ao selecionar centro de custo:",
      e.response?.data || e.message
    )
  }
}

async function buscarCentroCustos(event) {
  const termo = event.query?.toLowerCase()
  if (!termo) {
    sugestoes.value = []
    return
  }

  try {
    const res = await axios.get("/util/centro-custos2", {
      params: { termo }
    })
    sugestoes.value = res.data.map((item) => ({
      codigocentrocusto: item.codigocentrocusto,
      descricao: item.descricao,
      tipo: item.tipo // Identifica se é grupo ou centro
    }))
  } catch (e) {
    console.error(
      "Erro ao buscar centros de custo:",
      e.response?.data || e.message
    )
  }
}

function formatarLabel(opcao) {
  return opcao.tipo === "grupo"
    ? `Grupo: ${opcao.descricao}`
    : `${opcao.codigocentrocusto} - ${opcao.descricao}`
}
</script>
