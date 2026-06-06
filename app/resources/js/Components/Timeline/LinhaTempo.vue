<script setup>
import { watch, ref, computed } from "vue"
import { formatarData } from "@/utils/globalFunctions"

const props = defineProps({
  // Modo Compras: passa movimentações brutas + solicitação (componente monta os events)
  movimentacoes: { type: [Array, Object], default: () => [] },
  solicitacao: { type: Object, default: null },
  // Modo Genérico (Bordero etc): passa events já montados [{status, data, nome, icon, color}]
  eventosExternos: { type: Array, default: null },
  // Texto do rodapé "Início da Jornada" — customizável
  labelInicio: { type: String, default: "" },
  idInicio: { type: [String, Number], default: "" }
})

const events = ref([])
const carregado = ref(false)

// Reage a mudanças nas props (lazy loading do Relatório/Card)
watch(
  () => [props.movimentacoes, props.solicitacao, props.eventosExternos],
  () => {
    if (props.eventosExternos && props.eventosExternos.length) {
      // Modo genérico: usa events externos diretamente (já invertidos se necessário)
      events.value = [...props.eventosExternos].reverse()
      carregado.value = true
    } else if (props.solicitacao) {
      // Modo Compras: monta events a partir de movimentações
      posicionarLinhaTempo()
      carregado.value = true
    }
  },
  { immediate: true, deep: true }
)

const corPadrao = "#6366f1"

// Label / ID do rodapé
const labelInicioTexto = computed(
  () => props.labelInicio || "Início da Jornada"
)
const idInicioTexto = computed(() => {
  if (props.idInicio) return props.idInicio
  if (props.solicitacao?.id) return `Solicitação #${props.solicitacao.id}`
  return ""
})

function posicionarLinhaTempo() {
  const lista = []

  // Criação (sempre o primeiro evento cronológico)
  lista.push({
    status: "Criado",
    nome: props.solicitacao.nomeSolicitante,
    data: formatarData(props.solicitacao.data_cria),
    color: "#3b82f6",
    icon: "pi pi-file-plus"
  })

  if (props.movimentacoes && props.movimentacoes.length) {
    props.movimentacoes.forEach((mov) => {
      lista.push({
        status: getStatus(mov),
        data: mov.processado == "N" ? "" : formatarData(mov.data_mov),
        nome: mov.processado == "N" ? "" : mov.nome_mov,
        color: getCor(mov),
        icon: getIcone(mov)
      })
    })
  }

  // Mais recente no topo
  events.value = lista.reverse()
}

// Hex → rgba com alpha para fundo suave do marcador / pill
function tintHex(hex, alpha = 0.12) {
  if (!hex) return `rgba(99, 102, 241, ${alpha})`
  let h = hex.replace("#", "")
  if (h.length === 3) {
    h = h
      .split("")
      .map((c) => c + c)
      .join("")
  }
  const r = parseInt(h.substring(0, 2), 16)
  const g = parseInt(h.substring(2, 4), 16)
  const b = parseInt(h.substring(4, 6), 16)
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

function getCor(mov) {
  if (mov.processado == "N") return "#9ca3af"
  if (mov.processado == "V") return "#f59e0b"

  switch (mov.etapa) {
    case "NOVA":
      return "#64748b"
    case "EM COTACAO":
      return "#3b82f6"
    case "AUTORIZACAO":
      return "#eab308"
    case "FATURAMENTO":
      return "#f97316"
    case "RECEBIMENTO":
      return "#8b5cf6"
    case "ENTRADA DE NOTA":
      return "#06b6d4"
    case "FINANCEIRO":
      return "#6366f1"
    case "FINALIZADA":
      return "#10b981"
    case "NEGADA":
      return "#ef4444"
    case "CANCELADA":
      return "#dc2626"
    case "RETORNO_SOLICITANTE":
      return "#f59e0b"
    default:
      return corPadrao
  }
}

function getIcone(mov) {
  if (mov.processado == "N") return "pi pi-spin pi-spinner"

  switch (mov.etapa) {
    case "NOVA":
      return "pi pi-inbox"
    case "EM COTACAO":
      return "pi pi-search"
    case "AUTORIZACAO":
      return "pi pi-check-circle"
    case "FATURAMENTO":
      return "pi pi-file-edit"
    case "RECEBIMENTO":
      return "pi pi-box"
    case "ENTRADA DE NOTA":
      return "pi pi-file-import"
    case "FINANCEIRO":
      return "pi pi-wallet"
    case "FINALIZADA":
      return "pi pi-verified"
    case "NEGADA":
      return "pi pi-times-circle"
    case "CANCELADA":
      return "pi pi-ban"
    case "RETORNO_SOLICITANTE":
      return "pi pi-undo"
    default:
      return "pi pi-circle"
  }
}

function getStatus(mov) {
  switch (mov.etapa) {
    case "NOVA":
      return mov.processado == "S"
        ? "Iniciou Cotação"
        : props.solicitacao?.origem == "CONTRATO_RECORRENTE"
          ? "Aguardando Aprovação"
          : "Aguardando Cotação"
    case "EM COTACAO":
      return mov.processado == "S" ? "Cotação Realizada" : "Em Cotação"
    case "AUTORIZACAO":
      return mov.processado == "S"
        ? "Autorização Finalizada"
        : "Aguardando Autorização"
    case "FATURAMENTO":
      return mov.processado == "S"
        ? "Faturamento Realizado"
        : "Aguardando Faturamento"
    case "ENTRADA DE NOTA":
      if (mov.processado == "V") return "Retornou à etapa de recebimento"
      return mov.processado == "S" ? "Nota Recebida" : "Aguardando Entrada Nota"
    case "FINANCEIRO":
      if (mov.processado == "V") return "Retornou à etapa de entrada de nota"
      return mov.processado == "S"
        ? "Autorizado Financeiro"
        : "Aguardando Financeiro"
    case "RECEBIMENTO":
      return mov.processado == "S"
        ? "Recebimento Realizado"
        : "Aguardando Recebimento"
    case "FINALIZADA":
      return "Finalizada"
    case "CANCELADA":
      return "Solicitação Cancelada"
    case "NEGADA":
      return "Negada"
    case "RETORNO_SOLICITANTE":
      return mov.processado == "S"
        ? "Retornado ao Solicitante"
        : "Aguardando Retorno"
  }
}

function getFirstAndLastName(fullName) {
  if (!fullName) return ""
  const words = fullName.trim().split(" ")
  if (words.length === 0) return ""
  if (words.length === 1) return words[0]
  return words[0] + " " + words[words.length - 1]
}
</script>

<template>
  <div
    v-if="carregado"
    class="relative"
  >
    <!-- Trilho vertical único (desktop e mobile) -->
    <div
      class="absolute left-[17px] sm:left-[19px] top-3 bottom-3 w-px bg-slate-200 dark:bg-slate-700"
      aria-hidden="true"
    ></div>

    <!-- Lista de eventos -->
    <ul class="flex flex-col gap-2.5 sm:gap-3">
      <li
        v-for="(event, index) in events"
        :key="index"
        class="relative pl-11 sm:pl-14 group"
      >
        <!-- Marcador -->
        <span
          class="absolute left-0 top-2.5 w-[34px] h-[34px] sm:w-[38px] sm:h-[38px] rounded-full flex items-center justify-center transition-transform duration-200 group-hover:scale-105"
          :style="{
            backgroundColor: tintHex(event.color, 0.14),
            color: event.color || corPadrao
          }"
        >
          <i
            :class="[event.icon || 'pi pi-circle', 'text-sm']"
            aria-hidden="true"
          ></i>
        </span>

        <!-- Card -->
        <div
          class="relative rounded-xl border border-slate-200 dark:border-slate-700/70 bg-white dark:bg-slate-800/60 p-3 sm:p-3.5 transition-colors duration-200 hover:border-slate-300 dark:hover:border-slate-600"
        >
          <!-- Header: status + data -->
          <div class="flex items-center justify-between gap-2 flex-nowrap">
            <span
              class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] sm:text-[11px] font-semibold uppercase tracking-wide bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 truncate min-w-0"
            >
              {{ event.status }}
            </span>

            <span
              v-if="event.data"
              class="inline-flex items-center gap-1 text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400 leading-none whitespace-nowrap shrink-0"
            >
              <span>{{ event.data }}</span>
              <template v-if="event.hora">
                <span class="opacity-60">·</span>
                <span>{{ event.hora }}</span>
              </template>
            </span>
          </div>

          <!-- Conteúdo -->
          <div
            v-if="
              event.descricao ||
              event.nome ||
              event.status === 'Autorização Finalizada' ||
              !event.data
            "
            class="mt-1.5"
          >
            <p
              v-if="event.descricao"
              class="text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed first-letter:uppercase line-clamp-2"
              :title="event.descricao"
            >
              {{ event.descricao }}
            </p>

            <p
              v-else-if="
                event.nome && event.status !== 'Autorização Finalizada'
              "
              class="text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed first-letter:uppercase line-clamp-2"
              :title="event.nome"
            >
              {{ event.nome }}
            </p>

            <!-- Autorizações múltiplas -->
            <ul
              v-else-if="event.status === 'Autorização Finalizada'"
              class="flex flex-col gap-0.5 mt-0.5"
            >
              <li
                v-for="autorizacao in solicitacao?.autorizacoes || []"
                :key="autorizacao.id"
                class="text-[10px] sm:text-[13px] leading-snug truncate"
                :title="`${autorizacao.descricao} — ${getFirstAndLastName(autorizacao.autorizador)} · ${formatarData(autorizacao.data_aprovacao)}`"
              >
                <span
                  class="font-medium text-emerald-700 dark:text-emerald-300"
                >
                  {{ autorizacao.descricao }}
                </span>
                <span class="text-emerald-700/80 dark:text-emerald-400">
                  &nbsp;{{ getFirstAndLastName(autorizacao.autorizador) }}
                </span>
                <span class="text-emerald-600/70 dark:text-emerald-500">
                  &nbsp;· {{ formatarData(autorizacao.data_aprovacao) }}
                </span>
              </li>
            </ul>

            <p
              v-else
              class="text-xs sm:text-sm italic text-slate-400 dark:text-slate-500"
            >
              Aguardando…
            </p>
          </div>
        </div>
      </li>

      <!-- Início da jornada -->
      <li class="relative pl-11 sm:pl-14 mt-1">
        <span
          class="absolute left-0 top-1.5 w-[34px] h-[34px] sm:w-[38px] sm:h-[38px] rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center"
        >
          <i
            class="pi pi-flag text-slate-500 dark:text-slate-300 text-sm"
            aria-hidden="true"
          ></i>
        </span>
        <div class="py-1.5">
          <div
            class="text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-200"
          >
            {{ labelInicioTexto }}
          </div>
          <div
            v-if="idInicioTexto"
            class="text-[10px] sm:text-[11px] text-slate-500 dark:text-slate-400"
          >
            {{ idInicioTexto }}
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>
