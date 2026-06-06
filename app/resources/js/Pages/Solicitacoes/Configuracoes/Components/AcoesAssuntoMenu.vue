<script setup>
import { ref, computed } from "vue"
import Ripple from "primevue/ripple"

const props = defineProps({
  assunto: {
    type: Object,
    required: true
  },
  quantidadeModelos: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits([
  "campos-configuraveis",
  "campos-predefinidos",
  "instrucoes",
  "modelos",
  "ver-modelos",
  "liberacao",
  "responsaveis",
  "redirecionar",
  "duplicar",
  "etapas",
  "fluxo",
  "toggle-ativo"
])

const menuAberto = ref(false)
const executando = ref(false)

// Configuração das ações
const acoes = computed(() => [
  {
    id: "campos-configuraveis",
    label: "Campos Configuráveis",
    icon: "pi pi-list",
    color: "slate",
    bgColor: "bg-slate-900",
    hoverBg: "hover:bg-slate-800",
    textColor: "text-white",
    borderColor: "border-slate-900",
    disabled: false,
    action: () => emit("campos-configuraveis", props.assunto)
  },
  {
    id: "campos-predefinidos",
    label: "Campos Predefinidos",
    icon: "pi pi-cog",
    color: "slate",
    bgColor: "bg-slate-700",
    hoverBg: "hover:bg-slate-600",
    textColor: "text-white",
    borderColor: "border-slate-700",
    disabled: false,
    action: () => emit("campos-predefinidos", props.assunto)
  },
  {
    id: "instrucoes",
    label: "Instruções",
    icon: "pi pi-info-circle",
    color: "gray",
    bgColor: "bg-slate-500",
    hoverBg: "hover:bg-slate-600",
    textColor: "text-white",
    borderColor: "border-slate-500",
    hasBorder: false,
    disabled: false,
    action: () => emit("instrucoes", props.assunto)
  },
  {
    id: "modelos",
    label: props.assunto.id ? "Modelos" : "Salve primeiro",
    icon: "pi pi-file",
    color: "blue",
    bgColor: props.assunto.id ? "bg-blue-500" : "bg-gray-300",
    hoverBg: props.assunto.id ? "hover:bg-blue-600" : "",
    textColor: "text-white",
    borderColor: props.assunto.id ? "border-blue-500" : "border-gray-300",
    disabled: !props.assunto.id,
    action: () => emit("modelos", props.assunto)
  },
  {
    id: "ver-modelos",
    label: `Ver Modelos (${props.quantidadeModelos})`,
    icon: "pi pi-eye",
    color: "purple",
    bgColor: "bg-purple-500",
    hoverBg: "hover:bg-purple-600",
    textColor: "text-white",
    borderColor: "border-purple-500",
    disabled: false,
    visible: props.quantidadeModelos > 0,
    action: () => emit("ver-modelos", props.assunto)
  },
  {
    id: "liberacao",
    label: props.assunto.id ? "Liberação" : "Salve primeiro",
    icon: "pi pi-shield",
    color: "amber",
    bgColor: props.assunto.id ? "bg-amber-500" : "bg-gray-300",
    hoverBg: props.assunto.id ? "hover:bg-amber-600" : "",
    textColor: "text-white",
    borderColor: props.assunto.id ? "border-amber-500" : "border-gray-300",
    disabled: !props.assunto.id,
    action: () => emit("liberacao", props.assunto)
  },
  {
    id: "responsaveis",
    label: props.assunto.id ? "Responsáveis" : "Salve primeiro",
    icon: "pi pi-users",
    color: "indigo",
    bgColor: props.assunto.id ? "bg-indigo-500" : "bg-gray-300",
    hoverBg: props.assunto.id ? "hover:bg-indigo-600" : "",
    textColor: "text-white",
    borderColor: props.assunto.id ? "border-indigo-500" : "border-gray-300",
    disabled: !props.assunto.id,
    action: () => emit("responsaveis", props.assunto)
  },
  {
    id: "etapas",
    label: props.assunto.id ? "Etapas de Andamento" : "Salve primeiro",
    icon: "pi pi-sitemap",
    color: "violet",
    bgColor: props.assunto.id ? "bg-violet-500" : "bg-gray-300",
    hoverBg: props.assunto.id ? "hover:bg-violet-600" : "",
    textColor: "text-white",
    borderColor: props.assunto.id ? "border-violet-500" : "border-gray-300",
    disabled: !props.assunto.id,
    action: () => emit("etapas", props.assunto)
  },
  {
    id: "fluxo",
    label: props.assunto.id ? "Fluxo/Workflow" : "Salve primeiro",
    icon: "pi pi-directions",
    color: "teal",
    bgColor: props.assunto.id ? "bg-teal-500" : "bg-gray-300",
    hoverBg: props.assunto.id ? "hover:bg-teal-600" : "",
    textColor: "text-white",
    borderColor: props.assunto.id ? "border-teal-500" : "border-gray-300",
    disabled: !props.assunto.id,
    action: () => emit("fluxo", props.assunto)
  },
  {
    id: "redirecionar",
    label: "Redirecionar",
    icon: "pi pi-arrow-right",
    color: "blue",
    bgColor: "bg-blue-500",
    hoverBg: "hover:bg-blue-600",
    textColor: "text-white",
    borderColor: "border-blue-500",
    disabled: !props.assunto.id,
    action: () => emit("redirecionar", props.assunto)
  },
  {
    id: "duplicar",
    label: props.assunto.id ? "Duplicar" : "Salve primeiro",
    icon: "pi pi-copy",
    color: "green",
    bgColor: props.assunto.id ? "bg-green-500" : "bg-gray-300",
    hoverBg: props.assunto.id ? "hover:bg-green-600" : "",
    textColor: "text-white",
    borderColor: props.assunto.id ? "border-green-500" : "border-gray-300",
    disabled: !props.assunto.id,
    action: () => emit("duplicar", props.assunto)
  },
  {
    id: "toggle-ativo",
    label: props.assunto.id
      ? props.assunto.ativo === "S" || props.assunto.ativo === true
        ? "Desativar Assunto"
        : "Ativar Assunto"
      : "Salve primeiro",
    icon:
      props.assunto.ativo === "S" || props.assunto.ativo === true
        ? "pi pi-ban"
        : "pi pi-check-circle",
    color:
      props.assunto.ativo === "S" || props.assunto.ativo === true
        ? "red"
        : "emerald",
    bgColor: !props.assunto.id
      ? "bg-gray-300"
      : props.assunto.ativo === "S" || props.assunto.ativo === true
        ? "bg-red-500"
        : "bg-emerald-500",
    hoverBg: !props.assunto.id
      ? ""
      : props.assunto.ativo === "S" || props.assunto.ativo === true
        ? "hover:bg-red-600"
        : "hover:bg-emerald-600",
    textColor: "text-white",
    borderColor: !props.assunto.id
      ? "border-gray-300"
      : props.assunto.ativo === "S" || props.assunto.ativo === true
        ? "border-red-500"
        : "border-emerald-500",
    disabled: !props.assunto.id,
    action: () => emit("toggle-ativo", props.assunto)
  }
])

// Ações visíveis (filtra as não visíveis)
const acoesVisiveis = computed(() =>
  acoes.value.filter((a) => a.visible !== false)
)

function toggleMenu() {
  menuAberto.value = !menuAberto.value
}

function executarAcao(acao, event) {
  if (event) {
    event.stopPropagation()
    event.preventDefault()
  }

  // Evita múltiplas execuções
  if (executando.value || acao.disabled) {
    return
  }

  executando.value = true
  menuAberto.value = false

  // Executa a ação após um pequeno delay para garantir que o menu fechou
  setTimeout(() => {
    acao.action()
    executando.value = false
  }, 50)
}
</script>

<template>
  <!-- Container principal -->
  <div class="w-full">
    <!-- Menu dropdown elegante para todas as telas -->
    <div class="relative">
      <!-- Botão principal do menu -->
      <button
        @click="toggleMenu"
        class="w-full flex items-center justify-between gap-3 px-4 py-3 bg-gradient-to-r from-slate-800 to-slate-700 hover:from-slate-700 hover:to-slate-600 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-[1.01] active:scale-[0.99]"
        v-ripple
      >
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center backdrop-blur-sm"
          >
            <i class="pi pi-cog text-white text-lg"></i>
          </div>
          <div class="text-left">
            <span class="block text-sm font-semibold">Ações do Assunto</span>
            <span class="block text-xs text-slate-300">
              {{ acoesVisiveis.length }} opções disponíveis
            </span>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span
            class="text-xs bg-white/20 px-2.5 py-1 rounded-full font-medium"
          >
            {{ acoesVisiveis.filter((a) => !a.disabled).length }} ativas
          </span>
          <i
            :class="[
              'pi pi-chevron-down text-sm transition-transform duration-300',
              menuAberto ? 'rotate-180' : ''
            ]"
          ></i>
        </div>
      </button>

      <!-- Lista de ações expandível -->
      <transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 max-h-0"
        enter-to-class="opacity-100 max-h-[600px]"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 max-h-[600px]"
        leave-to-class="opacity-0 max-h-0"
      >
        <div
          v-if="menuAberto"
          class="mt-2 bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-gray-100 dark:border-slate-700 overflow-hidden"
        >
          <div class="py-2">
            <button
              v-for="acao in acoesVisiveis"
              :key="acao.id"
              @click.stop.prevent="executarAcao(acao, $event)"
              :disabled="acao.disabled"
              class="w-full flex items-center gap-4 px-4 py-3.5 hover:bg-gray-50 dark:hover:bg-slate-700 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-transparent dark:disabled:hover:bg-transparent"
              v-ripple
            >
              <div
                class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-sm"
                :class="
                  acao.disabled ? 'bg-gray-200 dark:bg-slate-600' : acao.bgColor
                "
              >
                <i
                  :class="[
                    acao.icon,
                    'text-base',
                    acao.disabled
                      ? 'text-gray-400 dark:text-gray-500'
                      : 'text-white'
                  ]"
                ></i>
              </div>
              <span
                class="text-sm font-medium flex-1 text-left text-gray-700 dark:text-gray-200"
              >
                {{ acao.label }}
              </span>
              <i
                class="pi pi-chevron-right text-xs text-gray-300 dark:text-gray-500"
              ></i>
            </button>
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<style scoped>
/* Animações suaves */
button {
  -webkit-tap-highlight-color: transparent;
}

/* Efeito de ripple customizado */
.p-ripple {
  overflow: hidden;
  position: relative;
}

/* Transições do menu */
:deep(.p-menu) {
  animation: slideDown 0.2s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Hover states mais suaves */
button:not(:disabled):hover {
  filter: brightness(1.05);
}

button:not(:disabled):active {
  filter: brightness(0.95);
}

/* Focus states acessíveis */
button:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
}

/* Scrollbar customizada para listas longas */
::-webkit-scrollbar {
  width: 4px;
}

::-webkit-scrollbar-track {
  background: transparent;
}

::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 2px;
}

::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
