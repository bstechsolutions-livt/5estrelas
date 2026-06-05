<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import BsFile from "@/ComponentsV2/BsFile.vue"
import { onMounted, ref, computed } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import * as GestaoJs from "../gestao-contratos.js"
import Button from "primevue/button"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import Panel from "primevue/panel"
import DatePicker from "primevue/datepicker"
import Textarea from "primevue/textarea"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO LOCAL                           ║
// ╚══════════════════════════════════════════════════════════════╝
const page = usePage()
const alvaraId = computed(() => page.props.id || null)
const isEdit = computed(() => !!alvaraId.value)
const loading = ref(false)
const saving = ref(false)
const uploadingAnexo = ref(false)

// Dados do anexo atual
const anexoAtual = ref(null)
const arquivoSelecionado = ref(null)

// Computed para formatar filiais com código + nome
const filiaisFormatadas = computed(() => {
  return GestaoJs.filiais.value.map((filial) => ({
    ...filial,
    label: `${filial.codfilial} - ${filial.filial}`
  }))
})

const form = ref({
  filial_id: "",
  tipo_alvara_id: "",
  numero_alvara: "",
  descricao: "",
  orgao_emissor: "",
  data_emissao: null,
  data_validade: null,
  valor_taxa: "",
  status: "VIGENTE",
  observacoes: "",
  responsavel_renovacao: "",
  email_responsavel: "",
  telefone_responsavel: ""
})

// Helpers de data
function parseDate(val) {
  if (!val) return null
  if (val instanceof Date) return val
  const str = String(val)
  if (str.includes("T") || str.includes("-")) {
    const d = new Date(str)
    return isNaN(d.getTime()) ? null : d
  }
  return null
}

function formatDateForApi(val) {
  if (!val || !(val instanceof Date)) return null
  const y = val.getFullYear()
  const m = String(val.getMonth() + 1).padStart(2, "0")
  const d = String(val.getDate()).padStart(2, "0")
  return `${y}-${m}-${d}`
}

const statusOptions = [
  { label: "Vigente", value: "VIGENTE" },
  { label: "Vencido", value: "VENCIDO" },
  { label: "Em Renovação", value: "EM_RENOVACAO" },
  { label: "Cancelado", value: "CANCELADO" }
]

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)

  // Carregar dados em paralelo para melhor performance
  const promises = [GestaoJs.getFiliais(), GestaoJs.getTiposAlvara()]

  if (isEdit.value) {
    promises.push(carregarAlvara())
  }

  await Promise.all(promises)
})

async function carregarAlvara() {
  loading.value = true
  try {
    const response = await axios.get(
      `/v2/gestao-contratos/alvaras/${alvaraId.value}`
    )
    const alvara = response.data.dados

    form.value = {
      filial_id: alvara.filial_id || "",
      tipo_alvara_id: alvara.tipo_alvara_id || "",
      numero_alvara: alvara.numero_documento || "",
      descricao: alvara.descricao || "",
      orgao_emissor: alvara.orgao_emissor || "",
      data_emissao: parseDate(alvara.data_emissao),
      data_validade: parseDate(alvara.data_validade),
      valor_taxa: alvara.custo_renovacao || "",
      status: alvara.status || "VIGENTE",
      observacoes: alvara.observacoes || "",
      responsavel_renovacao: alvara.responsavel_renovacao || "",
      email_responsavel: alvara.responsavel_email || "",
      telefone_responsavel: alvara.responsavel_telefone || ""
    }

    // Carregar anexo se existir
    if (alvara.arquivo_path) {
      anexoAtual.value = {
        path: alvara.arquivo_path,
        nome: alvara.arquivo_nome || "Arquivo anexado"
      }
    }
  } catch (error) {
    console.error("Erro ao carregar alvará:", error)
    GestaoJs.showToast("Erro ao carregar alvará", "error")
  } finally {
    loading.value = false
  }
}

async function salvar() {
  saving.value = true
  try {
    // Mapear campos do form para campos do banco
    const dados = {
      filial_id: form.value.filial_id,
      tipo_alvara_id: form.value.tipo_alvara_id,
      numero_documento: form.value.numero_alvara,
      descricao: form.value.descricao,
      orgao_emissor: form.value.orgao_emissor,
      data_emissao: formatDateForApi(form.value.data_emissao),
      data_validade: formatDateForApi(form.value.data_validade),
      custo_renovacao: form.value.valor_taxa,
      status: form.value.status,
      observacoes: form.value.observacoes,
      responsavel_renovacao: form.value.responsavel_renovacao,
      responsavel_email: form.value.email_responsavel,
      responsavel_telefone: form.value.telefone_responsavel
    }

    let idAlvara = alvaraId.value

    if (isEdit.value) {
      await axios.put(`/v2/gestao-contratos/alvaras/${alvaraId.value}`, dados)
    } else {
      const response = await axios.post("/v2/gestao-contratos/alvaras", dados)
      idAlvara = response.data.dados.id
    }

    // Upload do anexo se houver arquivo selecionado
    if (arquivoSelecionado.value && idAlvara) {
      await GestaoJs.uploadAnexoAlvara(idAlvara, arquivoSelecionado.value)
    }

    GestaoJs.showToast(
      isEdit.value
        ? "Alvará atualizado com sucesso!"
        : "Alvará cadastrado com sucesso!",
      "success"
    )
    router.visit("/pagina/gestao-contratos/alvaras")
  } catch (error) {
    console.error("Erro ao salvar alvará:", error)
    GestaoJs.showToast(
      error.response?.data?.message || "Erro ao salvar alvará",
      "error"
    )
  } finally {
    saving.value = false
  }
}

function voltar() {
  router.visit("/pagina/gestao-contratos/alvaras")
}

// Funções de anexo
function onArquivoSelecionado(arquivo) {
  arquivoSelecionado.value = arquivo
}

function onArquivoRemovido() {
  arquivoSelecionado.value = null
}

async function uploadAnexo() {
  if (!arquivoSelecionado.value || !alvaraId.value) return

  uploadingAnexo.value = true
  try {
    const resultado = await GestaoJs.uploadAnexoAlvara(
      alvaraId.value,
      arquivoSelecionado.value
    )
    anexoAtual.value = {
      path: resultado.arquivo_path,
      nome: resultado.arquivo_nome
    }
    arquivoSelecionado.value = null
  } catch (error) {
    console.error("Erro ao fazer upload:", error)
  } finally {
    uploadingAnexo.value = false
  }
}

async function removerAnexo() {
  if (!alvaraId.value) return

  uploadingAnexo.value = true
  try {
    await GestaoJs.deleteAnexoAlvara(alvaraId.value)
    anexoAtual.value = null
  } catch (error) {
    console.error("Erro ao remover anexo:", error)
  } finally {
    uploadingAnexo.value = false
  }
}

function downloadAnexo() {
  if (alvaraId.value) {
    GestaoJs.downloadAnexoAlvara(alvaraId.value)
  }
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
            class="hover:text-purple-600 dark:hover:text-purple-400"
          >
            Gestão de Contratos
          </a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <a
            href="/pagina/gestao-contratos/alvaras"
            class="hover:text-purple-600 dark:hover:text-purple-400"
          >
            Alvarás
          </a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">
            {{ isEdit ? "Editar" : "Novo" }} Alvará
          </span>
        </div>
      </div>
    </div>

    <!-- Cabeçalho -->
    <div class="space-y-2 mb-6 mt-4">
      <div
        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
      >
        <div>
          <div class="flex items-center gap-3">
            <h2
              class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
            >
              <div
                class="w-1 h-8 bg-gradient-to-b from-purple-500 to-purple-700 rounded-full"
              ></div>
              {{ isEdit ? "Editar" : "Novo" }} Alvará
            </h2>
          </div>
        </div>
        <div class="flex gap-2">
          <Button
            @click="voltar"
            label="Voltar"
            icon="pi pi-arrow-left"
            severity="secondary"
            outlined
          />
          <Button
            @click="salvar"
            :disabled="saving"
            :label="saving ? 'Salvando...' : 'Salvar'"
            :icon="saving ? 'pi pi-spin pi-spinner' : 'pi pi-check'"
            severity="help"
            outlined
          />
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div
      v-if="loading"
      class="flex justify-center py-20"
    >
      <i class="pi pi-spin pi-spinner text-4xl text-purple-500"></i>
    </div>

    <!-- Formulário -->
    <div
      v-else
      class="space-y-6"
    >
      <!-- Dados do Alvará -->
      <Panel
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
      >
        <template #header>
          <div class="flex items-center gap-2 mb-2">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-purple-200 dark:bg-purple-900 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-id-card text-purple-700 dark:text-purple-300 !text-xl"
              ></i>
            </span>
            <div>
              <h3
                class="text-2xl font-extrabold text-black-800 dark:text-white"
              >
                Dados do Alvará
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Informações principais do documento
              </div>
            </div>
          </div>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Filial *
            </label>
            <Select
              v-model="form.filial_id"
              :options="filiaisFormatadas"
              optionLabel="label"
              optionValue="codfilial"
              placeholder="Selecione..."
              class="w-full"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Tipo de Alvará *
            </label>
            <Select
              v-model="form.tipo_alvara_id"
              :options="GestaoJs.tiposAlvara.value"
              optionLabel="descricao"
              optionValue="id"
              placeholder="Selecione..."
              class="w-full"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Número do Alvará *
            </label>
            <InputText
              v-model="form.numero_alvara"
              placeholder="Número/Protocolo"
              class="w-full"
            />
          </div>
          <div class="md:col-span-2 flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Descrição
            </label>
            <InputText
              v-model="form.descricao"
              placeholder="Descrição do alvará"
              class="w-full"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Órgão Emissor *
            </label>
            <InputText
              v-model="form.orgao_emissor"
              placeholder="Ex: Prefeitura, Corpo de Bombeiros"
              class="w-full"
            />
          </div>
        </div>
      </Panel>

      <!-- Validade e Valores -->
      <Panel
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
      >
        <template #header>
          <div class="flex items-center gap-2 mb-2">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-orange-200 dark:bg-orange-900 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-calendar text-orange-700 dark:text-orange-300 !text-xl"
              ></i>
            </span>
            <div>
              <h3
                class="text-2xl font-extrabold text-black-800 dark:text-white"
              >
                Validade e Valores
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Datas e custos relacionados
              </div>
            </div>
          </div>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Data de Emissão
            </label>
            <DatePicker
              v-model="form.data_emissao"
              dateFormat="dd/mm/yy"
              showIcon
              placeholder="dd/mm/aaaa"
              class="w-full"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Data de Validade *
            </label>
            <DatePicker
              v-model="form.data_validade"
              dateFormat="dd/mm/yy"
              showIcon
              placeholder="dd/mm/aaaa"
              class="w-full"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Valor da Taxa
            </label>
            <InputText
              v-model="form.valor_taxa"
              type="number"
              step="0.01"
              placeholder="0,00"
              class="w-full"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Status
            </label>
            <Select
              v-model="form.status"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              class="w-full"
            />
          </div>
        </div>
      </Panel>

      <!-- Responsável pela Renovação -->
      <Panel
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
      >
        <template #header>
          <div class="flex items-center gap-2 mb-2">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-200 dark:bg-blue-900 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-user text-blue-700 dark:text-blue-300 !text-xl"
              ></i>
            </span>
            <div>
              <h3
                class="text-2xl font-extrabold text-black-800 dark:text-white"
              >
                Responsável pela Renovação
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Contato do responsável
              </div>
            </div>
          </div>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Nome do Responsável
            </label>
            <InputText
              v-model="form.responsavel_renovacao"
              placeholder="Nome do responsável"
              class="w-full"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              E-mail
            </label>
            <InputText
              v-model="form.email_responsavel"
              type="email"
              placeholder="email@exemplo.com"
              class="w-full"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Telefone
            </label>
            <InputText
              v-model="form.telefone_responsavel"
              placeholder="(00) 00000-0000"
              class="w-full"
            />
          </div>
        </div>
      </Panel>

      <!-- Anexo do Alvará -->
      <Panel
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
                Anexo do Alvará
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                PDF do alvará para afixar no mural
              </div>
            </div>
          </div>
        </template>

        <!-- Anexo existente -->
        <div
          v-if="anexoAtual"
          class="flex items-center gap-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg mb-4"
        >
          <i
            class="pi pi-file-pdf text-3xl text-green-600 dark:text-green-400"
          ></i>
          <div class="flex-1">
            <p class="font-medium text-green-800 dark:text-green-300">
              {{ anexoAtual.nome }}
            </p>
            <p class="text-sm text-green-600 dark:text-green-400">
              Arquivo anexado
            </p>
          </div>
          <div class="flex gap-2">
            <Button
              @click="downloadAnexo"
              icon="pi pi-download"
              label="Baixar"
              severity="success"
              outlined
            />
            <Button
              @click="removerAnexo"
              :disabled="uploadingAnexo"
              :icon="uploadingAnexo ? 'pi pi-spin pi-spinner' : 'pi pi-trash'"
              label="Remover"
              severity="danger"
              outlined
            />
          </div>
        </div>

        <!-- Upload de novo anexo -->
        <div v-else>
          <BsFile
            :aceitar-um-arquivo="true"
            :extensoes-permitidas="['pdf', 'jpg', 'jpeg', 'png']"
            :max-file-size="10"
            @atualizar-lista="onArquivoSelecionado"
            @deletar-arquivo="onArquivoRemovido"
          />

          <!-- Aviso de que será enviado ao salvar -->
          <p
            v-if="arquivoSelecionado"
            class="mt-4 text-sm text-purple-600 dark:text-purple-400 flex items-center gap-2"
          >
            <i class="pi pi-info-circle"></i>
            O anexo será enviado ao clicar em "Salvar"
          </p>
        </div>
      </Panel>

      <!-- Observações -->
      <Panel
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden"
      >
        <template #header>
          <div class="flex items-center gap-2 mb-2">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-200 dark:bg-gray-700 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-comment text-gray-700 dark:text-gray-300 !text-xl"
              ></i>
            </span>
            <div>
              <h3
                class="text-2xl font-extrabold text-black-800 dark:text-white"
              >
                Observações
              </h3>
              <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                Informações adicionais
              </div>
            </div>
          </div>
        </template>

        <Textarea
          v-model="form.observacoes"
          rows="4"
          autoResize
          placeholder="Informações adicionais sobre o alvará..."
          class="w-full"
        />
      </Panel>
    </div>
  </AuthenticatedLayout>
</template>
