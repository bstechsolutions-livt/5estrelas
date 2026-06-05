<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import * as Recorrentes from "../contratos-recorrentes.js"
import Panel from "primevue/panel"
import Button from "primevue/button"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import DatePicker from "primevue/datepicker"
import Tag from "primevue/tag"
import { swalConfirm } from "@/utils/globalFunctions.js"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO LOCAL                           ║
// ╚══════════════════════════════════════════════════════════════╝
const contratoId = computed(() => {
  const url = window.location.pathname
  const parts = url.split("/")
  return parts[parts.length - 1]
})

const valorNovo = ref("")
const dataInicioNova = ref("")
const dataFimNova = ref("")
const observacao = ref("")
const resultadoRenovacao = ref(null)

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)
  await Recorrentes.prepararRenovacao(contratoId.value)
})

const contrato = computed(() => Recorrentes.renovacaoPrep.value.contrato)
const regra = computed(() => Recorrentes.renovacaoPrep.value.regra_divergencia)
const historico = computed(
  () => Recorrentes.renovacaoPrep.value.historico_renovacoes
)

const simulacao = computed(() => {
  if (!contrato.value || !valorNovo.value || !regra.value) return null
  const anterior = parseFloat(contrato.value.valor_mensal)
  const novo = parseFloat(valorNovo.value)
  if (!anterior || !novo) return null

  const variacao = ((novo - anterior) / anterior) * 100
  const limite = parseFloat(regra.value.percentual_divergencia) || 10
  const limiteValor = anterior * (1 + limite / 100)

  return {
    variacao: variacao.toFixed(2),
    dentro: novo <= limiteValor,
    limiteValor,
    limitePercentual: limite,
    diferenca: novo - anterior
  }
})

async function abrirConfirmacao() {
  if (!valorNovo.value || !dataInicioNova.value || !dataFimNova.value) return

  const anterior = contrato.value ? parseFloat(contrato.value.valor_mensal) : 0
  const novo = parseFloat(valorNovo.value)
  const variacaoStr = simulacao.value?.variacao ?? "0.00"
  const variacaoColor = parseFloat(variacaoStr) > 0 ? "#dc2626" : "#16a34a"
  const sinal = parseFloat(variacaoStr) > 0 ? "+" : ""
  const tipoAprovacao = simulacao.value?.dentro
    ? "Aprovação automática"
    : "Será enviado para aprovação via Compras"

  const html = `
    <div style="text-align:center;font-size:0.85rem;color:#64748b;margin-bottom:0.75rem;">${tipoAprovacao}</div>
    <div style="display:flex;flex-direction:column;gap:0.35rem;font-size:0.9rem;">
      <p>Valor atual: <strong>${Recorrentes.formatarMoeda(anterior)}</strong></p>
      <p>Novo valor: <strong>${Recorrentes.formatarMoeda(novo)}</strong></p>
      <p>Variação: <strong style="color:${variacaoColor}">${sinal}${variacaoStr}%</strong></p>
    </div>
  `

  const result = await swalConfirm(
    "Confirmar Renovação",
    html,
    "Confirmar",
    "Cancelar",
    { icon: simulacao.value?.dentro ? "question" : "warning" }
  )

  if (result.isConfirmed) {
    await confirmarRenovacao()
  }
}

async function confirmarRenovacao() {
  const formatDate = (d) => {
    if (!d) return null
    const dt = d instanceof Date ? d : new Date(d)
    const y = dt.getFullYear()
    const m = String(dt.getMonth() + 1).padStart(2, "0")
    const day = String(dt.getDate()).padStart(2, "0")
    return `${y}-${m}-${day}`
  }

  const dados = {
    valor_novo: parseFloat(valorNovo.value),
    nova_data_inicio: formatDate(dataInicioNova.value),
    nova_data_fim: formatDate(dataFimNova.value),
    observacao: observacao.value
  }

  const result = await Recorrentes.renovarContrato(contratoId.value, dados)
  if (result) {
    resultadoRenovacao.value = result
    if (!result.necessita_compras) {
      setTimeout(() => {
        router.visit("/pagina/gestao-contratos/renovacao")
      }, 2000)
    }
  }
}

function irParaCompras() {
  router.visit("/pagina/compras")
}

function voltar() {
  router.visit("/pagina/gestao-contratos/renovacao")
}

function getStatusSeverity(status) {
  const severities = {
    APROVADA: "success",
    PENDENTE_COMPRAS: "warn",
    REJEITADA: "danger"
  }
  return severities[status] || "secondary"
}
</script>

<template>
  <AuthenticatedLayout>
    <!-- Breadcrumb -->
    <div
      class="w-full flex flex-wrap items-center bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-xl mb-4 sm:mb-6 border border-gray-200 dark:border-slate-700"
    >
      <div
        class="flex flex-wrap items-center gap-1 sm:gap-2 text-sm sm:text-base text-gray-600 dark:text-gray-300 font-medium w-full"
      >
        <div class="flex items-center gap-1 sm:gap-2">
          <i class="pi pi-home"></i>
          <span>Home</span>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <a
            href="/pagina/gestao-contratos"
            class="hover:text-green-600 dark:hover:text-green-400"
          >
            Gestão de Contratos
          </a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <a
            href="/pagina/gestao-contratos/renovacao"
            class="hover:text-green-600 dark:hover:text-green-400"
          >
            Renovações
          </a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">
            Renovar Contrato
          </span>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div
      v-if="Recorrentes.renovacaoPrep.value.loading"
      class="flex items-center justify-center py-16"
    >
      <div
        class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-full shadow-md text-sm"
      >
        <i class="pi pi-spinner pi-spin text-xs"></i>
        <span class="font-medium">Carregando dados do contrato...</span>
      </div>
    </div>

    <!-- Resultado da Renovação -->
    <div
      v-else-if="resultadoRenovacao"
      class="max-w-2xl mx-auto"
    >
      <!-- Sucesso - Dentro da divergência -->
      <div
        v-if="!resultadoRenovacao.necessita_compras"
        class="bg-white dark:bg-slate-800 rounded-3xl p-8 border border-gray-200 dark:border-slate-700 shadow-md text-center"
      >
        <div
          class="w-20 h-20 mx-auto mb-4 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center"
        >
          <i
            class="pi pi-check-circle text-4xl text-green-600 dark:text-green-400"
          ></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
          Renovação Aprovada!
        </h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">
          O valor está dentro da margem de divergência permitida. O contrato foi
          renovado automaticamente.
        </p>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Redirecionando...
        </p>
      </div>

      <!-- Fora da divergência -->
      <div
        v-else
        class="bg-white dark:bg-slate-800 rounded-3xl p-8 border border-gray-200 dark:border-slate-700 shadow-md text-center"
      >
        <div
          class="w-20 h-20 mx-auto mb-4 rounded-full bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center"
        >
          <i
            class="pi pi-exclamation-triangle text-4xl text-yellow-600 dark:text-yellow-400"
          ></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
          Necessita Aprovação via Compras
        </h3>
        <p class="text-gray-600 dark:text-gray-400 mb-2">
          O valor novo ultrapassa a margem de divergência de
          <span class="font-bold">
            {{ resultadoRenovacao.percentual_limite }}%
          </span>
          .
        </p>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
          É necessário criar uma nova solicitação de compras para aprovação do
          novo valor.
        </p>
        <div class="flex items-center gap-3 justify-center">
          <Button
            label="Voltar"
            icon="pi pi-arrow-left"
            severity="secondary"
            outlined
            @click="voltar"
          />
          <Button
            label="Ir para Compras"
            icon="pi pi-shopping-cart"
            severity="info"
            @click="irParaCompras"
          />
        </div>
      </div>
    </div>

    <!-- Formulário de Renovação -->
    <div
      v-else-if="contrato"
      class="space-y-6"
    >
      <!-- Cabeçalho -->
      <div class="space-y-2 mt-4">
        <div class="flex items-center gap-3">
          <Button
            icon="pi pi-arrow-left"
            severity="secondary"
            outlined
            @click="voltar"
            class="!w-10 !h-10"
          />
          <div>
            <h2
              class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
            >
              <div
                class="w-1 h-8 bg-gradient-to-b from-green-500 to-green-700 rounded-full"
              ></div>
              Renovar Contrato #{{ contrato.id }}
            </h2>
          </div>
        </div>
      </div>

      <!-- Info Contrato Atual -->
      <Panel
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
      >
        <template #header>
          <div class="flex items-center gap-2 mb-2">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-purple-200 dark:bg-purple-900 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-file text-purple-700 dark:text-purple-300 !text-xl"
              ></i>
            </span>
            <div>
              <h3
                class="text-2xl font-extrabold text-black-800 dark:text-white"
              >
                Contrato Atual
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Informações do contrato que será renovado.
              </div>
            </div>
          </div>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Fornecedor
            </label>
            <p class="text-sm font-medium text-gray-900 dark:text-white mt-1">
              {{ contrato.razao_social_loja || contrato.nome_locador || "-" }}
            </p>
          </div>
          <div>
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Valor Mensal Atual
            </label>
            <p class="text-lg font-bold text-blue-600 dark:text-blue-400 mt-1">
              {{ Recorrentes.formatarMoeda(contrato.valor_mensal) }}
            </p>
          </div>
          <div>
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Vigência
            </label>
            <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
              {{ Recorrentes.formatarData(contrato.data_inicio) }} a
              {{ Recorrentes.formatarData(contrato.data_fim) }}
            </p>
          </div>
          <div>
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Divergência Máxima
            </label>
            <p
              class="text-sm font-bold text-orange-600 dark:text-orange-400 mt-1"
            >
              {{ regra?.percentual_divergencia || "-" }}%
            </p>
          </div>
        </div>
      </Panel>

      <!-- Regra de Divergência -->
      <div
        class="bg-blue-50 dark:bg-blue-900/20 rounded-xl px-4 py-3 border border-blue-200 dark:border-blue-800"
      >
        <p
          class="text-sm text-blue-700 dark:text-blue-400 flex items-center gap-2 flex-wrap"
        >
          <i class="pi pi-info-circle text-blue-600 dark:text-blue-400"></i>
          <strong class="text-blue-800 dark:text-blue-300">
            Regra de Divergência
          </strong>
          <span>
            Se o novo valor estiver dentro de
            <strong>{{ regra?.percentual_divergencia || 10 }}%</strong>
            do valor atual (até
            <strong>
              {{ Recorrentes.formatarMoeda(regra?.valor_maximo_sem_aprovacao) }}
            </strong>
            ), a renovação será aprovada automaticamente. Caso contrário, será
            necessário abrir uma nova solicitação de compras.
          </span>
        </p>
      </div>

      <!-- Formulário -->
      <Panel
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
      >
        <template #header>
          <div class="flex items-center gap-2 mb-2">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-200 dark:bg-green-900 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-refresh text-green-700 dark:text-green-300 !text-xl"
              ></i>
            </span>
            <div>
              <h3
                class="text-2xl font-extrabold text-black-800 dark:text-white"
              >
                Dados da Renovação
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Informe os novos valores e datas para a renovação.
              </div>
            </div>
          </div>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Novo Valor Mensal
              <span class="text-red-500">*</span>
            </label>
            <InputNumber
              v-model="valorNovo"
              mode="currency"
              currency="BRL"
              locale="pt-BR"
              :showButtons="false"
              :min="0"
              :placeholder="String(contrato.valor_mensal)"
              fluid
              class="w-full"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Nova Data Início
              <span class="text-red-500">*</span>
            </label>
            <DatePicker
              v-model="dataInicioNova"
              dateFormat="dd/mm/yy"
              placeholder="dd/mm/aaaa"
              class="w-full"
              fluid
              showIcon
              iconDisplay="input"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Nova Data Fim
              <span class="text-red-500">*</span>
            </label>
            <DatePicker
              v-model="dataFimNova"
              dateFormat="dd/mm/yy"
              placeholder="dd/mm/aaaa"
              class="w-full"
              fluid
              showIcon
              iconDisplay="input"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Observação
            </label>
            <InputText
              v-model="observacao"
              placeholder="Motivo da renovação"
              class="w-full h-10 px-3"
            />
          </div>
        </div>

        <!-- Simulação -->
        <div
          v-if="simulacao"
          class="mt-4 p-4 rounded-lg"
          :class="
            simulacao.dentro
              ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800'
              : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'
          "
        >
          <div class="flex items-center gap-2 mb-2">
            <i
              class="pi"
              :class="
                simulacao.dentro
                  ? 'pi-check-circle text-green-600'
                  : 'pi-exclamation-triangle text-red-600'
              "
            ></i>
            <span
              class="font-medium text-sm"
              :class="
                simulacao.dentro
                  ? 'text-green-700 dark:text-green-400'
                  : 'text-red-700 dark:text-red-400'
              "
            >
              {{
                simulacao.dentro
                  ? "Dentro da margem - Aprovação automática"
                  : "Fora da margem - Necessita aprovação via Compras"
              }}
            </span>
          </div>
          <div class="grid grid-cols-3 gap-4 mt-2">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Variação</p>
              <p
                class="font-bold"
                :class="
                  simulacao.variacao > 0 ? 'text-red-600' : 'text-green-600'
                "
              >
                {{ simulacao.variacao > 0 ? "+" : "" }}{{ simulacao.variacao }}%
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">Diferença</p>
              <p
                class="font-bold"
                :class="
                  simulacao.diferenca > 0 ? 'text-red-600' : 'text-green-600'
                "
              >
                {{ Recorrentes.formatarMoeda(simulacao.diferenca) }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Limite ({{ simulacao.limitePercentual }}%)
              </p>
              <p class="font-bold text-gray-700 dark:text-gray-300">
                {{ Recorrentes.formatarMoeda(simulacao.limiteValor) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Botões -->
        <div
          class="flex items-center gap-3 justify-end pt-4 mt-4 border-t border-gray-200 dark:border-slate-700"
        >
          <Button
            label="Cancelar"
            icon="pi pi-times"
            severity="secondary"
            outlined
            @click="voltar"
          />
          <Button
            label="Renovar Contrato"
            icon="pi pi-refresh"
            severity="success"
            @click="abrirConfirmacao"
            :disabled="
              !valorNovo ||
              !dataInicioNova ||
              !dataFimNova ||
              Recorrentes.loading.value
            "
          />
        </div>
      </Panel>

      <!-- Histórico -->
      <Panel
        v-if="historico && historico.length > 0"
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
      >
        <template #header>
          <div class="flex items-center gap-2 mb-2">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-200 dark:bg-gray-800 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-history text-gray-700 dark:text-gray-300 !text-xl"
              ></i>
            </span>
            <div>
              <h3
                class="text-2xl font-extrabold text-black-800 dark:text-white"
              >
                Histórico de Renovações
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Renovações anteriores deste contrato.
              </div>
            </div>
          </div>
        </template>

        <div class="space-y-2">
          <div
            v-for="r in historico"
            :key="r.id"
            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg"
          >
            <div>
              <p class="text-sm text-gray-700 dark:text-gray-300">
                {{ Recorrentes.formatarMoeda(r.valor_anterior) }} →
                {{ Recorrentes.formatarMoeda(r.valor_novo) }}
                <span
                  class="text-xs ml-2"
                  :class="
                    r.percentual_variacao > 0
                      ? 'text-red-600'
                      : 'text-green-600'
                  "
                >
                  ({{ r.percentual_variacao > 0 ? "+" : ""
                  }}{{ parseFloat(r.percentual_variacao).toFixed(2) }}%)
                </span>
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ Recorrentes.formatarData(r.created_at) }}
              </p>
            </div>
            <Tag
              :value="r.status"
              :severity="getStatusSeverity(r.status)"
              class="font-medium"
            />
          </div>
        </div>
      </Panel>
    </div>
  </AuthenticatedLayout>
</template>
