<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, ref, computed } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import * as Recorrentes from "../contratos-recorrentes.js"
import Panel from "primevue/panel"
import Button from "primevue/button"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Tag from "primevue/tag"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO LOCAL                           ║
// ╚══════════════════════════════════════════════════════════════╝
const page = usePage()
const medicaoId = computed(() => {
  const url = window.location.pathname
  const parts = url.split("/")
  return parts[parts.length - 1]
})

const valorReal = ref("")
const numeroNf = ref("")
const numeroBoleto = ref("")
const observacao = ref("")
const arquivos = ref([])
const uploading = ref(false)

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)
  await Recorrentes.getMedicao(medicaoId.value)
})

const medicao = computed(() => Recorrentes.medicaoAtual.value.data)
const contrato = computed(() => medicao.value?.contrato)

const divergencia = computed(() => {
  if (!medicao.value || !valorReal.value) return null
  const previsto = parseFloat(medicao.value.valor_previsto)
  const real = parseFloat(valorReal.value)
  if (!previsto || !real) return null
  const diff = ((real - previsto) / previsto) * 100
  return {
    percentual: diff.toFixed(2),
    acima: real > previsto,
    valorDiferenca: real - previsto
  }
})

function onFileChange(event) {
  const files = Array.from(event.target.files)
  arquivos.value.push(...files)
}

function removerArquivo(index) {
  arquivos.value.splice(index, 1)
}

async function enviar() {
  if (!valorReal.value || !numeroNf.value?.trim()) return

  uploading.value = true
  try {
    const dados = {
      valor_real: parseFloat(valorReal.value),
      numero_nf: numeroNf.value,
      numero_boleto: numeroBoleto.value,
      observacao: observacao.value
    }

    const result = await Recorrentes.enviarNfBoleto(medicaoId.value, dados)
    if (!result) {
      uploading.value = false
      return
    }

    // Upload de anexos
    for (const arquivo of arquivos.value) {
      const formData = new FormData()
      formData.append("arquivo", arquivo)

      const nomeArquivo = arquivo.name.toLowerCase()
      let tipo = "OUTROS"
      if (nomeArquivo.includes("nf") || nomeArquivo.includes("nota"))
        tipo = "NOTA_FISCAL"
      else if (nomeArquivo.includes("boleto")) tipo = "BOLETO"
      else if (
        nomeArquivo.includes("comprov") ||
        nomeArquivo.includes("recibo")
      )
        tipo = "COMPROVANTE"

      formData.append("tipo", tipo)
      formData.append("descricao", arquivo.name)

      await Recorrentes.uploadAnexoMedicao(medicaoId.value, formData, true)
    }

    router.visit("/pagina/gestao-contratos/medicoes")
  } catch (error) {
    console.error("Erro ao enviar:", error)
  } finally {
    uploading.value = false
  }
}

function voltar() {
  router.visit("/pagina/gestao-contratos/medicoes")
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
            class="hover:text-blue-600 dark:hover:text-blue-400"
          >
            Gestão de Contratos
          </a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <a
            href="/pagina/gestao-contratos/medicoes"
            class="hover:text-blue-600 dark:hover:text-blue-400"
          >
            Medições
          </a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">
            Enviar NF/Boleto
          </span>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div
      v-if="Recorrentes.medicaoAtual.value.loading"
      class="flex items-center justify-center py-16"
    >
      <div
        class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-full shadow-md text-sm"
      >
        <i class="pi pi-spinner pi-spin text-xs"></i>
        <span class="font-medium">Carregando...</span>
      </div>
    </div>

    <div
      v-else-if="medicao"
      class="space-y-6"
    >
      <!-- Cabeçalho da Página -->
      <div class="space-y-2 mt-4">
        <div
          class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
        >
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
                  class="w-1 h-8 bg-gradient-to-b from-blue-500 to-blue-700 rounded-full"
                ></div>
                Enviar NF/Boleto - Medição #{{ medicao.id }}
              </h2>
            </div>
          </div>
        </div>
      </div>

      <!-- Info do Contrato -->
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
                Informações do Contrato
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Dados do contrato vinculado a esta medição.
              </div>
            </div>
          </div>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div>
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Contrato
            </label>
            <p class="text-sm font-medium text-gray-900 dark:text-white mt-1">
              #{{ medicao.contrato_id }}
            </p>
          </div>
          <div>
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Fornecedor
            </label>
            <p class="text-sm font-medium text-gray-900 dark:text-white mt-1">
              {{ contrato?.razao_social_loja || contrato?.nome_locador || "-" }}
            </p>
          </div>
          <div>
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Competência
            </label>
            <p class="text-sm font-medium text-gray-900 dark:text-white mt-1">
              {{ medicao.competencia_formatada || medicao.competencia }}
            </p>
          </div>
          <div>
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Valor Previsto
            </label>
            <p class="text-lg font-bold text-blue-600 dark:text-blue-400 mt-1">
              {{ Recorrentes.formatarMoeda(medicao.valor_previsto) }}
            </p>
          </div>
        </div>
      </Panel>

      <!-- Formulário -->
      <Panel
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
      >
        <template #header>
          <div class="flex items-center gap-2 mb-2">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-200 dark:bg-blue-900 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-upload text-blue-700 dark:text-blue-300 !text-xl"
              ></i>
            </span>
            <div>
              <h3
                class="text-2xl font-extrabold text-black-800 dark:text-white"
              >
                Dados da Nota Fiscal / Boleto
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Informe os dados da NF e boleto para envio da medição.
              </div>
            </div>
          </div>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
          <!-- Valor Real -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Valor Real
              <span class="text-red-500">*</span>
            </label>
            <InputNumber
              v-model="valorReal"
              mode="currency"
              currency="BRL"
              locale="pt-BR"
              :showButtons="false"
              :min="0"
              placeholder="0,00"
              fluid
              class="w-full"
            />
          </div>

          <!-- Número NF -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Número da Nota Fiscal
              <span class="text-red-500">*</span>
            </label>
            <InputText
              v-model="numeroNf"
              placeholder="Ex: 123456"
              class="w-full h-10 px-3"
            />
          </div>

          <!-- Número Boleto -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Número do Boleto
            </label>
            <InputText
              v-model="numeroBoleto"
              placeholder="Ex: 789012"
              class="w-full h-10 px-3"
            />
          </div>

          <!-- Observação -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Observação
            </label>
            <InputText
              v-model="observacao"
              placeholder="Observação opcional"
              class="w-full h-10 px-3"
            />
          </div>
        </div>

        <!-- Alerta Divergência -->
        <div
          v-if="divergencia && divergencia.acima"
          class="mb-4 p-3 rounded-lg"
          :class="
            divergencia.percentual > 10
              ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'
              : 'bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800'
          "
        >
          <div class="flex items-center gap-2">
            <i
              class="pi pi-exclamation-triangle"
              :class="
                divergencia.percentual > 10 ? 'text-red-600' : 'text-yellow-600'
              "
            ></i>
            <span
              class="text-sm font-medium"
              :class="
                divergencia.percentual > 10
                  ? 'text-red-700 dark:text-red-400'
                  : 'text-yellow-700 dark:text-yellow-400'
              "
            >
              Valor {{ divergencia.percentual }}% acima do previsto ({{
                Recorrentes.formatarMoeda(divergencia.valorDiferenca)
              }}
              a mais)
            </span>
          </div>
        </div>

        <!-- Upload de Arquivos -->
        <div class="mb-4">
          <label
            class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 block"
          >
            Anexos (NF, Boleto, Comprovantes)
          </label>
          <div
            class="border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg p-4 text-center cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors"
            @click="$refs.fileInput.click()"
          >
            <i
              class="pi pi-cloud-upload text-3xl text-gray-400 dark:text-gray-500 mb-2"
            ></i>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Clique para selecionar arquivos
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
              PDF, JPG, PNG (máx. 10MB cada)
            </p>
          </div>
          <input
            ref="fileInput"
            type="file"
            multiple
            accept=".pdf,.jpg,.jpeg,.png"
            class="hidden"
            @change="onFileChange"
          />

          <div
            v-if="arquivos.length > 0"
            class="mt-3 space-y-2"
          >
            <div
              v-for="(arq, index) in arquivos"
              :key="index"
              class="flex items-center justify-between p-2 bg-gray-50 dark:bg-slate-700/50 rounded-lg"
            >
              <div class="flex items-center gap-2">
                <i class="pi pi-file text-gray-500 dark:text-gray-400"></i>
                <span class="text-sm text-gray-700 dark:text-gray-300">
                  {{ arq.name }}
                </span>
                <span class="text-xs text-gray-400">
                  ({{ (arq.size / 1024).toFixed(0) }}KB)
                </span>
              </div>
              <Button
                icon="pi pi-times"
                severity="danger"
                text
                rounded
                size="small"
                @click="removerArquivo(index)"
              />
            </div>
          </div>
        </div>

        <!-- Botões -->
        <div
          class="flex items-center gap-3 justify-end pt-4 border-t border-gray-200 dark:border-slate-700"
        >
          <Button
            label="Cancelar"
            icon="pi pi-times"
            severity="secondary"
            outlined
            @click="voltar"
          />
          <Button
            label="Enviar NF/Boleto"
            icon="pi pi-send"
            severity="info"
            @click="enviar"
            :disabled="!valorReal || !numeroNf?.trim()"
            :loading="uploading"
          />
        </div>
      </Panel>

      <!-- Anexos existentes -->
      <Panel
        v-if="medicao.anexos && medicao.anexos.length > 0"
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
      >
        <template #header>
          <div class="flex items-center gap-2 mb-2">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-200 dark:bg-green-900 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-paperclip text-green-700 dark:text-green-300 !text-xl"
              ></i>
            </span>
            <div>
              <h3
                class="text-2xl font-extrabold text-black-800 dark:text-white"
              >
                Anexos Enviados
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Documentos já vinculados a esta medição.
              </div>
            </div>
          </div>
        </template>

        <div class="space-y-2">
          <div
            v-for="anexo in medicao.anexos"
            :key="anexo.id"
            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg"
          >
            <div class="flex items-center gap-3">
              <i
                class="pi pi-file-pdf text-red-500"
                v-if="anexo.nome_arquivo?.endsWith('.pdf')"
              ></i>
              <i
                class="pi pi-image text-blue-500"
                v-else
              ></i>
              <div>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                  {{ anexo.descricao || anexo.nome_arquivo }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ anexo.tipo }} |
                  {{ Recorrentes.formatarData(anexo.created_at) }}
                </p>
              </div>
            </div>
            <Button
              icon="pi pi-download"
              severity="info"
              text
              rounded
              as="a"
              :href="'/storage/' + anexo.caminho_arquivo"
              target="_blank"
            />
          </div>
        </div>
      </Panel>
    </div>
  </AuthenticatedLayout>
</template>
