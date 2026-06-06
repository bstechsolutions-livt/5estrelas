<template>
  <div class="flex flex-col items-center justify-center w-full ipad:flex-row">
    <div class="flex items-center justify-between w-full p-2 space-x-4">
      <!-- Botão "Anterior" -->
      <button
        :class="paginaLocal === 1 ? 'invisible' : 'visible'"
        class="items-center justify-center hidden w-20 h-8 p-2 text-sm font-medium transition-all rounded-lg ipad:flex text-slate-700 hover:border hover:shadow-md hover:drop-shadow-md"
        :disabled="paginaLocal === 1"
        @click="goToPage(paginaLocal - 1)"
      >
        Anterior
      </button>

      <BsIcone
        icone="arrow-left"
        severity="contrast"
        class="flex p-2 ipad:hidden"
        :class="paginaLocal === 1 ? 'invisible' : 'visible'"
        :disabled="paginaLocal === 1"
        @click="goToPage(paginaLocal - 1)"
      />

      <!-- Exibição de página atual e total de páginas -->
      <div
        class="text-[12px] ipad:text-[13px] space-x-2 font-medium text-slate-700 flex flex-row items-center justify-center text-nowrap"
      >
        <span>Página</span>
        <InputNumber
          v-model="paginaLocal"
          input-class="!text-[12px] ipad:!text-[13px] w-[44px] ipad:w-[46px] h-7 ipad:h-9 text-center"
          size="small"
          :min="1"
          :max="paginasLocal"
          @update:modelValue="(val) => goToPage(val)"
        />

        <span>de {{ paginasLocal }}</span>

        <select
          @change="goToPage(1)"
          v-model="porPaginaLocal"
          class="w-18 h-[34px] ipad:h-9 text-center rounded-md border border-gray-300 focus:ring-0 focus:border-green-300 text-[12px] ipad:text-[13px] font-medium text-slate-700"
        >
          <option
            v-for="qtd in qtdPorPagina"
            :key="qtd"
            :value="qtd"
          >
            {{ qtd }}
          </option>
        </select>
      </div>

      <BsIcone
        icone="arrow-right"
        severity="contrast"
        class="flex p-2 ipad:hidden"
        :class="paginaLocal == paginasLocal ? 'invisible' : 'visible'"
        :disabled="paginaLocal === paginasLocal"
        @click="goToPage(paginaLocal + 1)"
      />
      <!-- Botão "Próxima" -->
      <button
        :class="paginaLocal == paginasLocal ? 'invisible' : 'visible'"
        class="items-center justify-center hidden w-20 h-8 p-2 text-sm font-medium transition-all rounded-lg ipad:flex text-slate-700 hover:border hover:shadow-md hover:drop-shadow-md"
        :disabled="paginaLocal === paginasLocal"
        @click="goToPage(paginaLocal + 1)"
      >
        Próxima
      </button>
    </div>
  </div>
</template>

<script setup>
// IMPORTAÇÕES
import { InputNumber } from "primevue"
import { ref, watch, computed } from "vue"
import BsIcone from "./BsIcone.vue"

// PROPS
const props = defineProps({
  pagina: {
    type: Number,
    default: 1,
    required: true
  },
  paginas: {
    type: Number,
    default: 1,
    required: true
  },
  alteraPorPagina: {
    type: Boolean,
    required: false,
    default: false
  },
  porPagina: {
    type: Number,
    required: false,
    default: 10
  }
})

// EMITS
const emits = defineEmits([
  "update:pagina",
  "update:paginas",
  "update:porPagina",
  "alterouPagina"
])

// COMPUTED
const paginaLocal = computed({
  get: () => props.pagina,
  set: (novoValor) => emits("update:pagina", novoValor)
})

const paginasLocal = computed({
  get: () => props.paginas,
  set: (novoValor) => emits("update:paginas", novoValor)
})

const porPaginaLocal = computed({
  get: () => props.porPagina,
  set: (novoValor) => emits("update:porPagina", novoValor)
})

// VARIÁVEIS
const qtdPorPagina = [5, 10, 20, 50]

// FUNÇÕES
function goToPage(novaPagina) {
  paginaLocal.value = novaPagina
  emits("alterouPagina")
}
</script>
