<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, ref, computed, watch } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import * as GestaoJs from "../gestao-contratos.js"
import { formatarCnpj } from "@/utils/globalFunctions.js"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import InputMask from "primevue/inputmask"
import Textarea from "primevue/textarea"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO LOCAL                           ║
// ╚══════════════════════════════════════════════════════════════╝
const page = usePage()
const contratoId = computed(() => page.props.id || null)
const isEdit = computed(() => !!contratoId.value)
const isReadonly = computed(
  () => !!GestaoJs.contratoAtual.value?.id_solicitacao_compras
)
const loading = ref(false)
const saving = ref(false)
const activeTab = ref("dados")
const showAnexoModal = ref(false)

const form = ref({
  tipo: "SERVICO",
  filial_id: null,
  nome_fornecedor: "",
  cnpj_fornecedor: "",
  descricao_servico: "",
  valor_mensal: null,
  data_inicio: null,
  data_fim: null,
  status: "ATIVO",
  observacoes: ""
})

const statusOptions = [
  { label: "Ativo", value: "ATIVO" },
  { label: "Pendente", value: "PENDENTE" },
  { label: "Em Renovação", value: "EM_RENOVACAO" },
  { label: "Encerrado", value: "ENCERRADO" }
]

const filialOptions = computed(() =>
  (GestaoJs.filiais.value || []).map((f) => ({
    label: `${f.codfilial} - ${f.filial}`,
    value: f.codfilial
  }))
)

function parseDate(val) {
  if (!val) return null
  const d = new Date(val)
  return isNaN(d.getTime()) ? null : d
}

function formatDateForApi(val) {
  if (!val) return null
  const d = new Date(val)
  if (isNaN(d.getTime())) return null
  return d.toISOString().split("T")[0]
}

const anexoForm = ref({
  tipo: "",
  arquivo: null,
  descricao: ""
})

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)
  await GestaoJs.getFiliais()

  if (isEdit.value) {
    await carregarContrato()
  }
})

async function carregarContrato() {
  loading.value = true
  try {
    const response = await axios.get(
      `/v2/gestao-contratos/contratos/${contratoId.value}`
    )
    const contrato = response.data.dados

    form.value = {
      tipo: contrato.tipo,
      filial_id: contrato.filial_id || null,
      nome_fornecedor: contrato.nome_locador || "",
      cnpj_fornecedor:
        formatarCnpj(contrato.documento_locador) ||
        contrato.documento_locador ||
        "",
      descricao_servico: contrato.descricao_servico || "",
      valor_mensal: contrato.valor_mensal
        ? Number(contrato.valor_mensal)
        : null,
      data_inicio: parseDate(contrato.data_inicio),
      data_fim: parseDate(contrato.data_fim),
      status: contrato.status || "ATIVO",
      observacoes: contrato.observacoes || ""
    }

    GestaoJs.contratoAtual.value = contrato
    GestaoJs.anexos.value = contrato.anexos || []
  } catch (error) {
    console.error("Erro ao carregar contrato:", error)
    GestaoJs.showToast("Erro ao carregar contrato", "error")
  } finally {
    loading.value = false
  }
}

async function salvar() {
  saving.value = true
  try {
    // Mapear campos do form para campos do banco
    const dados = {
      ...form.value,
      nome_locador: form.value.nome_fornecedor,
      documento_locador: form.value.cnpj_fornecedor?.replace(/\D/g, "") || "",
      data_inicio: formatDateForApi(form.value.data_inicio),
      data_fim: formatDateForApi(form.value.data_fim)
    }
    delete dados.nome_fornecedor
    delete dados.cnpj_fornecedor

    if (isEdit.value) {
      await axios.put(
        `/v2/gestao-contratos/contratos/${contratoId.value}`,
        dados
      )
      GestaoJs.showToast("Contrato atualizado com sucesso!", "success")
    } else {
      await axios.post("/v2/gestao-contratos/contratos", dados)
      GestaoJs.showToast("Contrato cadastrado com sucesso!", "success")
    }
    router.visit("/pagina/gestao-contratos/servicos")
  } catch (error) {
    console.error("Erro ao salvar contrato:", error)
    GestaoJs.showToast(
      error.response?.data?.message || "Erro ao salvar contrato",
      "error"
    )
  } finally {
    saving.value = false
  }
}

function voltar() {
  router.visit("/pagina/gestao-contratos/servicos")
}

// Funções de Anexo
function abrirModalAnexo() {
  anexoForm.value = { tipo: "", arquivo: null, descricao: "" }
  showAnexoModal.value = true
}

function handleFileUpload(event) {
  anexoForm.value.arquivo = event.target.files[0]
}

async function salvarAnexo() {
  if (!anexoForm.value.arquivo || !anexoForm.value.tipo) {
    GestaoJs.showToast("Preencha todos os campos", "error")
    return
  }

  const formData = new FormData()
  formData.append("arquivo", anexoForm.value.arquivo)
  formData.append("tipo", anexoForm.value.tipo)
  formData.append("descricao", anexoForm.value.descricao)

  try {
    const response = await axios.post(
      `/v2/gestao-contratos/contratos/${contratoId.value}/anexos`,
      formData,
      {
        headers: { "Content-Type": "multipart/form-data" }
      }
    )
    GestaoJs.anexos.value.push(response.data.dados)
    showAnexoModal.value = false
    GestaoJs.showToast("Anexo adicionado com sucesso!", "success")
  } catch (error) {
    GestaoJs.showToast("Erro ao enviar anexo", "error")
  }
}

async function excluirAnexo(anexoId) {
  if (!confirm("Deseja excluir este anexo?")) return

  try {
    await axios.delete(`/v2/gestao-contratos/anexos/${anexoId}`)
    GestaoJs.anexos.value = GestaoJs.anexos.value.filter(
      (a) => a.id !== anexoId
    )
    GestaoJs.showToast("Anexo excluído!", "success")
  } catch (error) {
    GestaoJs.showToast("Erro ao excluir anexo", "error")
  }
}

const tiposAnexo = [
  { value: "CONTRATO", label: "Contrato" },
  { value: "ADITIVO", label: "Aditivo" },
  { value: "COMPROVANTE", label: "Comprovante" },
  { value: "OUTROS", label: "Outros" }
]
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
            href="/pagina/gestao-contratos/servicos"
            class="hover:text-green-600 dark:hover:text-green-400"
          >
            Serviços
          </a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">
            {{ isEdit ? "Editar" : "Novo" }} Contrato
          </span>
        </div>
      </div>
    </div>

    <!-- Cabeçalho -->
    <div
      class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6"
    >
      <div class="space-y-1">
        <div class="flex items-center gap-3">
          <h2
            class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
          >
            <div
              class="w-1 h-8 bg-gradient-to-b from-green-500 to-green-700 rounded-full"
            ></div>
            {{ isReadonly ? "Visualizar" : isEdit ? "Editar" : "Novo" }}
            Contrato de Serviço
          </h2>
        </div>
      </div>
      <div class="flex gap-2">
        <button
          @click="voltar"
          class="flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-slate-600 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
        >
          <i class="pi pi-arrow-left"></i>
          <span>Voltar</span>
        </button>
        <button
          v-if="!isReadonly"
          @click="salvar"
          :disabled="saving"
          class="flex items-center gap-2 px-4 py-2 border border-green-600 text-green-600 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors disabled:opacity-50"
        >
          <i :class="saving ? 'pi pi-spin pi-spinner' : 'pi pi-check'"></i>
          <span>{{ saving ? "Salvando..." : "Salvar" }}</span>
        </button>
      </div>
    </div>

    <!-- Banner Somente Leitura -->
    <div
      v-if="isReadonly"
      class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-4 flex items-center gap-3"
    >
      <i class="pi pi-lock text-amber-500 text-lg"></i>
      <span class="text-sm text-amber-800 dark:text-amber-300">
        <strong>Contrato criado via Solicitação de Compras</strong>
        — A edição deve ser feita na solicitação de compras.
      </span>
    </div>

    <!-- Loading -->
    <div
      v-if="loading"
      class="flex justify-center py-20"
    >
      <i class="pi pi-spin pi-spinner text-4xl text-green-500"></i>
    </div>

    <!-- Formulário -->
    <div
      v-else
      class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700"
    >
      <!-- Tabs -->
      <div class="border-b border-gray-200 dark:border-slate-700">
        <nav
          class="flex space-x-8 px-6"
          aria-label="Tabs"
        >
          <button
            @click="activeTab = 'dados'"
            :class="[
              'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === 'dados'
                ? 'border-green-500 text-green-600 dark:text-green-400'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'
            ]"
          >
            <i class="pi pi-file mr-2"></i>
            Dados do Contrato
          </button>
          <button
            v-if="isEdit"
            @click="activeTab = 'anexos'"
            :class="[
              'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === 'anexos'
                ? 'border-green-500 text-green-600 dark:text-green-400'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'
            ]"
          >
            <i class="pi pi-paperclip mr-2"></i>
            Anexos ({{ GestaoJs.anexos.value.length }})
          </button>
        </nav>
      </div>

      <!-- Tab Dados -->
      <div
        v-show="activeTab === 'dados'"
        class="p-6"
      >
        <!-- Dados da Filial -->
        <div class="mb-8">
          <h3
            class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2"
          >
            <i class="pi pi-building text-green-500"></i>
            Filial
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >
                Filial *
              </label>
              <Select
                v-model="form.filial_id"
                :options="filialOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Selecione..."
                :disabled="isReadonly"
                class="w-full"
              />
            </div>
          </div>
        </div>

        <!-- Dados do Fornecedor -->
        <div class="mb-8">
          <h3
            class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2"
          >
            <i class="pi pi-truck text-green-500"></i>
            Dados do Fornecedor
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >
                Nome do Fornecedor *
              </label>
              <InputText
                v-model="form.nome_fornecedor"
                :disabled="isReadonly"
                placeholder="Razão social do fornecedor"
                class="w-full"
              />
            </div>
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >
                CNPJ do Fornecedor
              </label>
              <InputMask
                v-model="form.cnpj_fornecedor"
                mask="99.999.999/9999-99"
                :disabled="isReadonly"
                placeholder="00.000.000/0000-00"
                class="w-full"
              />
            </div>
          </div>
        </div>

        <!-- Descrição do Serviço -->
        <div class="mb-8">
          <h3
            class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2"
          >
            <i class="pi pi-cog text-green-500"></i>
            Descrição do Serviço
          </h3>
          <div>
            <label
              class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >
              Descrição *
            </label>
            <Textarea
              v-model="form.descricao_servico"
              rows="3"
              :disabled="isReadonly"
              placeholder="Descreva o serviço contratado"
              class="w-full"
              autoResize
            />
          </div>
        </div>

        <!-- Valores e Datas -->
        <div class="mb-8">
          <h3
            class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2"
          >
            <i class="pi pi-dollar text-green-500"></i>
            Valores e Vigência
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >
                Valor Mensal *
              </label>
              <InputNumber
                v-model="form.valor_mensal"
                :disabled="isReadonly"
                mode="currency"
                currency="BRL"
                locale="pt-BR"
                :showButtons="false"
                placeholder="0,00"
                class="w-full"
              />
            </div>
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >
                Data Início *
              </label>
              <DatePicker
                v-model="form.data_inicio"
                :disabled="isReadonly"
                dateFormat="dd/mm/yy"
                showIcon
                fluid
                placeholder="dd/mm/aaaa"
                class="w-full"
              />
            </div>
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >
                Data Fim *
              </label>
              <DatePicker
                v-model="form.data_fim"
                :disabled="isReadonly"
                dateFormat="dd/mm/yy"
                showIcon
                fluid
                placeholder="dd/mm/aaaa"
                class="w-full"
              />
            </div>
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
              >
                Status
              </label>
              <Select
                v-model="form.status"
                :options="statusOptions"
                optionLabel="label"
                optionValue="value"
                :disabled="isReadonly"
                placeholder="Selecione..."
                class="w-full"
              />
            </div>
          </div>
        </div>

        <!-- Observações -->
        <div>
          <h3
            class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2"
          >
            <i class="pi pi-comment text-green-500"></i>
            Observações
          </h3>
          <Textarea
            v-model="form.observacoes"
            rows="4"
            :disabled="isReadonly"
            placeholder="Informações adicionais sobre o contrato..."
            class="w-full"
            autoResize
          />
        </div>
      </div>

      <!-- Tab Anexos -->
      <div
        v-show="activeTab === 'anexos'"
        class="p-6"
      >
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Anexos do Contrato
          </h3>
          <button
            v-if="!isReadonly"
            @click="abrirModalAnexo"
            class="flex items-center gap-2 px-4 py-2 border border-green-600 text-green-600 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20"
          >
            <i class="pi pi-plus"></i>
            Adicionar Anexo
          </button>
        </div>

        <div
          v-if="GestaoJs.anexos.value.length === 0"
          class="text-center py-10 text-gray-500 dark:text-gray-400"
        >
          <i class="pi pi-folder-open text-4xl mb-2"></i>
          <p>Nenhum anexo cadastrado</p>
        </div>

        <div
          v-else
          class="grid gap-4"
        >
          <div
            v-for="anexo in GestaoJs.anexos.value"
            :key="anexo.id"
            class="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-700 rounded-lg"
          >
            <div class="flex items-center gap-4">
              <i class="pi pi-file text-2xl text-green-500"></i>
              <div>
                <p class="font-medium text-gray-900 dark:text-white">
                  {{ anexo.nome_arquivo }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  {{ anexo.tipo }} • {{ anexo.descricao || "Sem descrição" }}
                </p>
              </div>
            </div>
            <div class="flex gap-2">
              <a
                :href="anexo.url" :download="anexo.nome_arquivo"
                target="_blank"
                class="p-2 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg"
              >
                <i class="pi pi-download"></i>
              </a>
              <button
                v-if="!isReadonly"
                @click="excluirAnexo(anexo.id)"
                class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg"
              >
                <i class="pi pi-trash"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Anexo -->
    <div
      v-if="showAnexoModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
      <div
        class="bg-white dark:bg-slate-800 rounded-xl p-6 max-w-md w-full mx-4 shadow-xl"
      >
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
          Adicionar Anexo
        </h3>
        <div class="space-y-4">
          <div>
            <label
              class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >
              Tipo *
            </label>
            <Select
              v-model="anexoForm.tipo"
              :options="tiposAnexo"
              optionLabel="label"
              optionValue="value"
              placeholder="Selecione..."
              class="w-full"
            />
          </div>
          <div>
            <label
              class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >
              Arquivo *
            </label>
            <input
              type="file"
              @change="handleFileUpload"
              class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white"
            />
          </div>
          <div>
            <label
              class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
            >
              Descrição
            </label>
            <InputText
              v-model="anexoForm.descricao"
              placeholder="Descrição do anexo"
              class="w-full"
            />
          </div>
        </div>
        <div class="flex gap-3 mt-6">
          <button
            @click="showAnexoModal = false"
            class="flex-1 px-4 py-2 border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700"
          >
            Cancelar
          </button>
          <button
            @click="salvarAnexo"
            class="flex-1 px-4 py-2 border border-green-600 text-green-600 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20"
          >
            Salvar
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
