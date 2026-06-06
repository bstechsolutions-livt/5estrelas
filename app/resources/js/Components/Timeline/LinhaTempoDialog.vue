<script setup>
import { computed } from "vue"
import LinhaTempo from "./LinhaTempo.vue"

const props = defineProps({
  visible: { type: Boolean, default: false },
  // Props do LinhaTempo (Modo Compras)
  movimentacoes: { type: [Array, Object], default: () => [] },
  solicitacao: { type: Object, default: null },
  // Props do LinhaTempo (Modo Genérico — Bordero, Solicitações)
  eventosExternos: { type: Array, default: null },
  labelInicio: { type: String, default: "" },
  idInicio: { type: [String, Number], default: "" },
  // Loading externo (lazy loading do Relatório/Card)
  loading: { type: Boolean, default: false }
})

const emit = defineEmits(["update:visible"])

const dialogVisible = computed({
  get: () => props.visible,
  set: (val) => emit("update:visible", val)
})

const qtdEventos = computed(() => {
  if (props.eventosExternos?.length) return props.eventosExternos.length
  return (props.movimentacoes?.length || 0) + 1
})
</script>

<template>
  <Dialog
    v-model:visible="dialogVisible"
    modal
    position="right"
    :closeOnEscape="true"
    :pt="{
      mask: { class: 'backdrop-blur-sm' },
      root: {
        class:
          '!m-0 !rounded-none !shadow-2xl !border-0 !w-screen !h-[100dvh] sm:!w-[460px] lg:!w-[520px] sm:!max-w-[90vw] !max-h-[100dvh]'
      }
    }"
  >
    <template #container="{ closeCallback }">
      <div
        class="flex flex-col w-full h-[100dvh] bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-700"
      >
        <!-- Header -->
        <div
          class="flex justify-between items-center px-4 py-3 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div
              class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center shrink-0"
            >
              <i
                class="pi pi-history text-indigo-600 dark:text-indigo-400"
                aria-hidden="true"
              ></i>
            </div>
            <div class="flex flex-col leading-tight min-w-0">
              <h3
                class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate"
              >
                Linha do Tempo
              </h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">
                {{ qtdEventos }}
                {{ qtdEventos === 1 ? "evento" : "eventos" }}
              </p>
            </div>
          </div>
          <button
            @click="closeCallback"
            class="w-8 h-8 rounded-md flex items-center justify-center text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200 transition-colors shrink-0"
            aria-label="Fechar"
          >
            <i
              class="pi pi-times text-sm"
              aria-hidden="true"
            ></i>
          </button>
        </div>

        <!-- Content -->
        <div
          class="overflow-auto flex-1 min-h-0 p-3 sm:p-5 bg-slate-50/60 dark:bg-slate-900"
        >
          <div
            v-if="loading"
            class="flex items-center justify-center py-12 text-slate-500 dark:text-slate-400"
          >
            <i
              class="pi pi-spin pi-spinner text-xl"
              aria-hidden="true"
            ></i>
            <span class="ml-2 text-sm">Carregando…</span>
          </div>
          <LinhaTempo
            v-else
            :movimentacoes="movimentacoes"
            :solicitacao="solicitacao"
            :eventosExternos="eventosExternos"
            :labelInicio="labelInicio"
            :idInicio="idInicio"
          />
        </div>
      </div>
    </template>
  </Dialog>
</template>
