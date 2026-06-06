<template>
  <div
    class="group bg-white dark:bg-slate-800 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-slate-100 dark:border-slate-700 overflow-hidden hover:-translate-y-1"
  >
    <!-- Header do Card com Gradiente -->
    <div
      class="relative p-4 bg-gradient-to-r from-slate-50 to-slate-100 dark:from-slate-800 dark:to-slate-700 border-b border-slate-100 dark:border-slate-700"
    >
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
          <!-- ID Badge -->
          <div
            class="flex items-center justify-center min-w-[3rem] h-12 px-3 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white font-bold text-sm shadow-lg shadow-indigo-500/25"
          >
            #{{ aprovacao.solicitacao_id }}
          </div>
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wide shadow-sm"
                :class="getClassPrioridadeModern(aprovacao.prioridade)"
              >
                <i class="pi pi-flag text-[10px]"></i>
                {{ getLabelPrioridade(aprovacao.prioridade) }}
              </span>
            </div>
            <h3
              class="text-sm font-bold text-slate-800 dark:text-white line-clamp-2 leading-snug"
            >
              {{ aprovacao.titulo }}
            </h3>
          </div>
        </div>

        <!-- Status Badge com animação -->
        <div class="flex items-center gap-2">
          <span class="relative flex h-3 w-3">
            <span
              class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"
            ></span>
            <span
              class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"
            ></span>
          </span>
        </div>
      </div>
    </div>

    <!-- Conteúdo do Card -->
    <div class="p-4">
      <!-- Informações em Grid -->
      <div class="grid grid-cols-2 gap-2 mb-4">
        <div
          class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/50 px-3 py-2 rounded-lg"
        >
          <i class="pi pi-building text-indigo-500"></i>
          <span class="truncate">{{ aprovacao.departamento }}</span>
        </div>
        <div
          class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/50 px-3 py-2 rounded-lg"
        >
          <i class="pi pi-tag text-purple-500"></i>
          <span class="truncate">{{ aprovacao.assunto }}</span>
        </div>
        <div
          class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/50 px-3 py-2 rounded-lg"
        >
          <i class="pi pi-map-marker text-rose-500"></i>
          <span class="truncate">{{ aprovacao.filial }}</span>
        </div>
        <div
          class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/50 px-3 py-2 rounded-lg"
        >
          <i class="pi pi-user text-emerald-500"></i>
          <span class="truncate">{{ aprovacao.solicitante.nome }}</span>
        </div>
      </div>

      <!-- Responsável -->
      <div
        v-if="aprovacao.responsavel"
        class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400 mb-4"
      >
        <img
          v-if="aprovacao.responsavel.foto_perfil"
          :src="aprovacao.responsavel.foto_perfil"
          :alt="aprovacao.responsavel.nome"
          class="w-6 h-6 rounded-full object-cover"
        />
        <div
          v-else
          class="w-6 h-6 rounded-full bg-gradient-to-br from-blue-400 to-cyan-500 flex items-center justify-center text-white text-[10px] font-bold"
        >
          {{ aprovacao.responsavel.nome.charAt(0) }}
        </div>
        <span>
          Responsável:
          <strong class="text-slate-700 dark:text-slate-300">
            {{ aprovacao.responsavel.nome }}
          </strong>
        </span>
      </div>

      <!-- Motivo da Aprovação -->
      <div
        v-if="aprovacao.aprovacao.motivo"
        class="mb-4"
      >
        <h4
          class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2 flex items-center gap-1"
        >
          <i class="pi pi-info-circle text-amber-500"></i>
          Motivo da Solicitação
        </h4>
        <p
          class="text-xs text-slate-600 dark:text-slate-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800/30 p-3 rounded-xl line-clamp-3"
        >
          {{ aprovacao.aprovacao.motivo }}
        </p>
      </div>

      <!-- Observações -->
      <div
        v-if="aprovacao.aprovacao.observacoes"
        class="mb-4"
      >
        <h4
          class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2 flex items-center gap-1"
        >
          <i class="pi pi-comment text-blue-500"></i>
          Observações
        </h4>
        <p
          class="text-xs text-slate-600 dark:text-slate-400 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/30 p-3 rounded-xl line-clamp-2"
        >
          {{ aprovacao.aprovacao.observacoes }}
        </p>
      </div>

      <!-- Data e Tempo -->
      <div
        class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 pt-3 border-t border-slate-100 dark:border-slate-700"
      >
        <div class="flex items-center gap-1">
          <i class="pi pi-clock text-slate-400"></i>
          <span>{{ parseInt(aprovacao.dias_aberto) }} dia(s)</span>
        </div>
        <span class="text-slate-400">
          {{ formatarData(aprovacao.created_at) }}
        </span>
      </div>
    </div>

    <!-- Footer com Ações -->
    <div
      class="px-4 py-3 bg-gradient-to-r from-slate-50 to-slate-100 dark:from-slate-800 dark:to-slate-750 border-t border-slate-100 dark:border-slate-700"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <!-- Botões de ação rápida -->
        <div class="flex gap-2">
          <Button
            outlined
            @click="$emit('aprovar-rapido', aprovacao)"
            class="flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-gradient-to-r from-emerald-500 to-green-600 rounded-xl hover:from-emerald-600 hover:to-green-700 transition-all shadow-md shadow-emerald-500/25 hover:shadow-lg hover:shadow-emerald-500/30"
          >
            <i class="pi pi-check"></i>
            Aprovar
          </Button>
          <Button
            outlined
            severity="danger"
            @click="$emit('rejeitar-rapido', aprovacao)"
            class="flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-gradient-to-r from-rose-500 to-red-600 rounded-xl hover:from-rose-600 hover:to-red-700 transition-all shadow-md shadow-rose-500/25 hover:shadow-lg hover:shadow-rose-500/30"
          >
            <i class="pi pi-times"></i>
            Rejeitar
          </button>
        </div>

        <!-- Botão para abrir solicitação -->
        <button
          @click="$emit('abrir-solicitacao', aprovacao)"
          class="flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800 rounded-xl hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-all"
          title="Ver detalhes da solicitação"
        >
          <i class="pi pi-eye"></i>
          Ver Detalhes
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatarData } from "@/utils/globalFunctions"

const props = defineProps({
  aprovacao: {
    type: Object,
    required: true
  }
})

const emits = defineEmits([
  "aprovar-rapido",
  "rejeitar-rapido",
  "abrir-solicitacao"
])

function getClassPrioridadeModern(prioridade) {
  switch (prioridade) {
    case "baixa":
      return "bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600"
    case "media":
      return "bg-gradient-to-r from-blue-50 to-indigo-50 text-blue-700 dark:from-blue-900/40 dark:to-indigo-900/40 dark:text-blue-300 border border-blue-200 dark:border-blue-700"
    case "alta":
      return "bg-gradient-to-r from-amber-50 to-orange-50 text-amber-700 dark:from-amber-900/40 dark:to-orange-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-700"
    case "urgente":
      return "bg-gradient-to-r from-red-100 to-rose-100 text-red-700 dark:from-red-900/40 dark:to-rose-900/40 dark:text-red-300 border border-red-200 dark:border-red-700 animate-pulse"
    default:
      return "bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600"
  }
}

function getLabelPrioridade(prioridade) {
  switch (prioridade) {
    case "baixa":
      return "Baixa"
    case "media":
      return "Média"
    case "alta":
      return "Alta"
    case "urgente":
      return "Urgente"
    default:
      return prioridade
  }
}
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
