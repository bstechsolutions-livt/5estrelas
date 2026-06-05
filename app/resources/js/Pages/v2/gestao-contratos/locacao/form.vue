<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, ref, computed, watch } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import * as GestaoJs from "../gestao-contratos.js"
import { formatarCnpj, formatarCpf } from "@/utils/globalFunctions.js"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import RadioButton from "primevue/radiobutton"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO LOCAL                           ║
// ╚══════════════════════════════════════════════════════════════╝
const page = usePage()
const contratoId = computed(() => page.props.id || null)
const isEdit = computed(() => !!contratoId.value)
const loading = ref(false)
const saving = ref(false)
const activeTab = ref("dados")
const showAnexoModal = ref(false)
const showReajusteModal = ref(false)

const form = ref({
  tipo: "LOCACAO",
  filial_id: null,
  razao_social_loja: "",
  cnpj_loja: "",
  // Locador principal
  tipo_pessoa: "PJ",
  nome_locador: "",
  documento_locador: "",
  telefone_locador: "",
  // Imobiliária
  imobiliaria: "",
  telefone_imobiliaria: "",
  // Locadores adicionais
  locadores_adicionais: [],
  // Endereço
  endereco_imovel: "",
  cidade: "",
  estado: null,
  cep: "",
  // Período de apuração
  dia_apuracao: null,
  dia_apuracao_fim: null,
  // IPTU
  valor_iptu: null,
  iptu_pago_carne: null,
  iptu_inscricoes: [],
  // Valores, Vigência e Condições
  valor_anterior: null,
  valor_mensal: null,
  valor_proposto_locador: null,
  tem_condominio: null,
  valor_condominio: null,
  data_inicio: null,
  data_fim: null,
  prazo_contrato_meses: null,
  pagamento_antecipado: false,
  dia_vencimento: null,
  renovacao_automatica: false,
  negociador: "",
  status: "ATIVO",
  // Índice de Reajuste
  tipo_indice_id: null,
  percentual_reajuste_fixo: null,
  valor_reajuste_fixo: null,
  mes_base_reajuste: null,
  data_vencimento_reajuste: null,
  indices_adicionais: [],
  // Histórico anual
  historico_anual: [],
  // Observações
  observacoes: ""
})

// ╔══════════════════════════════════════════════════════════════╗
// ║                       OPÇÕES DE SELECT                       ║
// ╚══════════════════════════════════════════════════════════════╝
const statusOptions = [
  { label: "Ativo", value: "ATIVO" },
  { label: "Pendente", value: "PENDENTE" },
  { label: "Em Renovação", value: "EM_RENOVACAO" },
  { label: "Encerrado", value: "ENCERRADO" }
]

const simNaoOptions = [
  { label: "Sim", value: true },
  { label: "Não", value: false }
]

const diaOptions = Array.from({ length: 31 }, (_, i) => ({
  label: String(i + 1).padStart(2, "0"),
  value: i + 1
}))

const mesOptions = [
  { label: "Janeiro", value: 1 },
  { label: "Fevereiro", value: 2 },
  { label: "Março", value: 3 },
  { label: "Abril", value: 4 },
  { label: "Maio", value: 5 },
  { label: "Junho", value: 6 },
  { label: "Julho", value: 7 },
  { label: "Agosto", value: 8 },
  { label: "Setembro", value: 9 },
  { label: "Outubro", value: 10 },
  { label: "Novembro", value: 11 },
  { label: "Dezembro", value: 12 }
]

const filialOptions = computed(() =>
  (GestaoJs.filiais.value || []).map((f) => ({
    label: `${f.codfilial} - ${f.fantasia}`,
    value: f.codfilial,
    razaosocial: f.filial,
    cgc: f.cgc
  }))
)

// Auto-preencher razão social e CNPJ ao selecionar filial
watch(() => form.value.filial_id, (novaFilial) => {
  if (!novaFilial) return
  const filial = filialOptions.value.find(f => f.value === novaFilial)
  if (filial) {
    form.value.razao_social_loja = filial.razaosocial || ""
    form.value.cnpj_loja = filial.cgc || ""
  }
})

const indiceOptions = computed(() =>
  (GestaoJs.tiposIndice.value || []).map((i) => ({
    label: `${i.codigo} - ${i.descricao}`,
    value: i.id
  }))
)

const estadoOptions = [
  "AC","AL","AP","AM","BA","CE","DF","ES","GO","MA","MT","MS","MG",
  "PA","PB","PR","PE","PI","RJ","RN","RS","RO","RR","SC","SP","SE","TO"
].map((uf) => ({ label: uf, value: uf }))

const tipoPessoaOptions = [
  { label: "Pessoa Jurídica", value: "PJ" },
  { label: "Pessoa Física", value: "PF" }
]

const tiposAnexo = [
  { value: "CONTRATO", label: "Contrato" },
  { value: "ADITIVO", label: "Aditivo" },
  { value: "COMPROVANTE", label: "Comprovante" },
  { value: "OUTROS", label: "Outros" }
]

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES AUXILIARES                      ║
// ╚══════════════════════════════════════════════════════════════╝
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

function formatarDocumentoLocador(val) {
  if (!val) return ""
  const digits = val.toString().replace(/\D/g, "")
  if (digits.length === 14) return formatarCnpj(digits)
  if (digits.length === 11) return formatarCpf(digits)
  return val
}

// Watchers
watch(() => form.value.tem_condominio, (val) => {
  if (val === false) form.value.valor_condominio = null
})

// Computed: detecta se o índice selecionado é "Fixo conforme contrato"
const isIndiceFix = computed(() => {
  if (!form.value.tipo_indice_id) return false
  const indice = (GestaoJs.tiposIndice.value || []).find(i => i.id === form.value.tipo_indice_id)
  return indice && indice.codigo === 'FIXO'
})

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES CRUD                            ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)
  await GestaoJs.getFiliais()
  await GestaoJs.getTiposIndice()
  if (isEdit.value) await carregarContrato()
})

async function carregarContrato() {
  loading.value = true
  try {
    const response = await axios.get(`/v2/gestao-contratos/contratos/${contratoId.value}`)
    const c = response.data.dados

    form.value = {
      tipo: c.tipo,
      filial_id: c.filial_id || null,
      razao_social_loja: c.razao_social_loja || "",
      cnpj_loja: c.cnpj_loja || "",
      tipo_pessoa: c.tipo_pessoa || "PJ",
      nome_locador: c.nome_locador || "",
      documento_locador: formatarDocumentoLocador(c.documento_locador) || "",
      telefone_locador: c.telefone_locador || "",
      imobiliaria: c.imobiliaria || "",
      telefone_imobiliaria: c.telefone_imobiliaria || "",
      locadores_adicionais: c.locadores_adicionais || [],
      endereco_imovel: c.endereco_imovel || "",
      cidade: c.cidade || "",
      estado: c.estado || null,
      cep: c.cep || "",
      dia_apuracao: c.dia_apuracao || null,
      dia_apuracao_fim: c.dia_apuracao_fim || null,
      valor_iptu: c.valor_iptu ? Number(c.valor_iptu) : null,
      iptu_pago_carne: c.iptu_pago_carne ?? null,
      iptu_inscricoes: c.iptu_inscricoes || [],
      valor_anterior: c.valor_anterior ? Number(c.valor_anterior) : null,
      valor_mensal: c.valor_mensal ? Number(c.valor_mensal) : null,
      valor_proposto_locador: c.valor_proposto_locador ? Number(c.valor_proposto_locador) : null,
      tem_condominio: c.tem_condominio ?? null,
      valor_condominio: c.valor_condominio ? Number(c.valor_condominio) : null,
      data_inicio: parseDate(c.data_inicio),
      data_fim: parseDate(c.data_fim),
      prazo_contrato_meses: c.prazo_contrato_meses || null,
      pagamento_antecipado: c.pagamento_antecipado || false,
      dia_vencimento: c.dia_vencimento || null,
      renovacao_automatica: c.renovacao_automatica || false,
      negociador: c.negociador || "",
      status: c.status || "ATIVO",
      tipo_indice_id: c.tipo_indice_id || null,
      percentual_reajuste_fixo: c.percentual_reajuste_fixo ? Number(c.percentual_reajuste_fixo) : null,
      valor_reajuste_fixo: c.valor_reajuste_fixo ? Number(c.valor_reajuste_fixo) : null,
      mes_base_reajuste: c.mes_base_reajuste || null,
      data_vencimento_reajuste: parseDate(c.data_vencimento_reajuste),
      indices_adicionais: c.indices_adicionais || [],
      historico_anual: c.historico_anual || [],
      observacoes: c.observacoes || ""
    }

    GestaoJs.contratoAtual.value = c
    GestaoJs.anexos.value = c.anexos || []
    GestaoJs.reajustes.value = c.reajustes || []
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
    const dados = {
      ...form.value,
      documento_locador: form.value.documento_locador?.replace(/\D/g, "") || "",
      data_inicio: formatDateForApi(form.value.data_inicio),
      data_fim: formatDateForApi(form.value.data_fim),
      data_vencimento_reajuste: formatDateForApi(form.value.data_vencimento_reajuste),
    }
    if (isEdit.value) {
      await axios.put(`/v2/gestao-contratos/contratos/${contratoId.value}`, dados)
      GestaoJs.showToast("Contrato atualizado com sucesso!", "success")
    } else {
      await axios.post("/v2/gestao-contratos/contratos", dados)
      GestaoJs.showToast("Contrato cadastrado com sucesso!", "success")
    }
    router.visit("/pagina/gestao-contratos/locacao")
  } catch (error) {
    console.error("Erro ao salvar contrato:", error)
    GestaoJs.showToast(error.response?.data?.message || "Erro ao salvar contrato", "error")
  } finally {
    saving.value = false
  }
}

function voltar() {
  router.visit("/pagina/gestao-contratos/locacao")
}

// Locadores adicionais
function adicionarLocador() {
  form.value.locadores_adicionais.push({ nome: "", documento: "", tipo_pessoa: "PJ" })
}
function removerLocador(index) {
  form.value.locadores_adicionais.splice(index, 1)
}

// IPTU inscrições
function adicionarIptu() {
  form.value.iptu_inscricoes.push({ inscricao: "", percentual_loja: null })
}
function removerIptu(index) {
  form.value.iptu_inscricoes.splice(index, 1)
}

// Índices adicionais
function adicionarIndice() {
  form.value.indices_adicionais.push({ descricao: "", percentual: null })
}
function removerIndice(index) {
  form.value.indices_adicionais.splice(index, 1)
}

// Histórico anual — gerado automaticamente com base nas datas do contrato
const anosContrato = computed(() => {
  if (!form.value.data_inicio || !form.value.data_fim) return []
  const inicio = new Date(form.value.data_inicio)
  const fim = new Date(form.value.data_fim)
  if (isNaN(inicio.getTime()) || isNaN(fim.getTime())) return []

  const anoInicio = inicio.getFullYear()
  const anoFim = fim.getFullYear()
  const anos = []
  for (let a = anoInicio; a <= anoFim; a++) {
    anos.push(a)
  }
  return anos
})

// Sincroniza historico_anual com os anos calculados (preserva dados já preenchidos)
watch(anosContrato, (novosAnos) => {
  const historicoAtual = form.value.historico_anual || []
  const novoHistorico = novosAnos.map(ano => {
    const existente = historicoAtual.find(h => h.ano === ano)
    return existente || { ano, valor_aluguel: null, indice: null, percentual: null, valor_reajuste_fixo: null, condominio: null, valor_proposto: null, valor_anterior: null, dia_vencimento: null, negociador: null }
  })
  form.value.historico_anual = novoHistorico
}, { immediate: false })

// Anexos
const anexoForm = ref({ tipo: "", arquivo: null, descricao: "" })
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
    const response = await axios.post(`/v2/gestao-contratos/contratos/${contratoId.value}/anexos`, formData, { headers: { "Content-Type": "multipart/form-data" } })
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
    GestaoJs.anexos.value = GestaoJs.anexos.value.filter((a) => a.id !== anexoId)
    GestaoJs.showToast("Anexo excluído!", "success")
  } catch (error) {
    GestaoJs.showToast("Erro ao excluir anexo", "error")
  }
}

// Reajustes
const reajusteForm = ref({ data_reajuste: new Date(), valor_anterior: null, valor_novo: null, percentual: null, indice_aplicado: "" })
function abrirModalReajuste() {
  reajusteForm.value = { data_reajuste: new Date(), valor_anterior: form.value.valor_mensal, valor_novo: null, percentual: null, indice_aplicado: "" }
  showReajusteModal.value = true
}
watch(() => reajusteForm.value.percentual, (novoPerc) => {
  if (novoPerc && reajusteForm.value.valor_anterior) {
    reajusteForm.value.valor_novo = (parseFloat(reajusteForm.value.valor_anterior) * (1 + parseFloat(novoPerc) / 100)).toFixed(2)
  }
})
async function salvarReajuste() {
  if (!reajusteForm.value.data_reajuste || !reajusteForm.value.valor_novo) {
    GestaoJs.showToast("Preencha a data e o novo valor", "error")
    return
  }
  try {
    const dados = { data_reajuste: formatDateForApi(reajusteForm.value.data_reajuste), valor_anterior: reajusteForm.value.valor_anterior, valor_reajustado: reajusteForm.value.valor_novo, percentual_aplicado: reajusteForm.value.percentual, indice_utilizado: reajusteForm.value.indice_aplicado }
    const response = await axios.post(`/v2/gestao-contratos/contratos/${contratoId.value}/reajustes`, dados)
    GestaoJs.reajustes.value.push(response.data.dados)
    form.value.valor_mensal = reajusteForm.value.valor_novo
    showReajusteModal.value = false
    GestaoJs.showToast("Reajuste registrado com sucesso!", "success")
  } catch (error) {
    GestaoJs.showToast("Erro ao registrar reajuste", "error")
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <!-- Breadcrumb -->
    <div class="w-full flex flex-wrap items-center bg-white p-2 sm:p-3 rounded-xl mb-4 sm:mb-6 border border-gray-200">
      <div class="flex flex-wrap items-center gap-1 sm:gap-2 text-sm sm:text-base text-gray-600 font-medium w-full">
        <div class="flex items-center gap-1 sm:gap-2">
          <i class="pi pi-home"></i>
          <span>Home</span>
          <span class="mx-1 sm:mx-2 text-gray-400">/</span>
          <a href="/pagina/gestao-contratos" class="hover:text-blue-600">Gestão de Contratos</a>
          <span class="mx-1 sm:mx-2 text-gray-400">/</span>
          <a href="/pagina/gestao-contratos/locacao" class="hover:text-blue-600">Locação</a>
          <span class="mx-1 sm:mx-2 text-gray-400">/</span>
          <span class="text-gray-950 font-bold">{{ isEdit ? "Editar" : "Novo" }} Contrato</span>
        </div>
      </div>
    </div>

    <!-- Cabeçalho -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div class="space-y-1">
        <h2 class="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-3">
          <div class="w-1 h-8 bg-gradient-to-b from-blue-500 to-blue-700 rounded-full"></div>
          {{ isEdit ? "Editar" : "Novo" }} Contrato de Locação
        </h2>
      </div>
      <div class="flex gap-2">
        <button @click="voltar" class="flex items-center gap-2 px-4 py-2 border border-gray-500 text-gray-600 rounded-lg hover:bg-gray-100 transition-colors">
          <i class="pi pi-arrow-left"></i><span>Voltar</span>
        </button>
        <button @click="salvar" :disabled="saving" class="flex items-center gap-2 px-4 py-2 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50 transition-colors disabled:opacity-50">
          <i :class="saving ? 'pi pi-spin pi-spinner' : 'pi pi-check'"></i>
          <span>{{ saving ? "Salvando..." : "Salvar" }}</span>
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-20">
      <i class="pi pi-spin pi-spinner text-4xl text-blue-500"></i>
    </div>

    <!-- Formulário -->
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100">
      <!-- Tabs -->
      <div class="border-b border-gray-200">
        <nav class="flex space-x-8 px-6" aria-label="Tabs">
          <button @click="activeTab = 'dados'" :class="['py-4 px-1 border-b-2 font-medium text-sm transition-colors', activeTab === 'dados' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
            <i class="pi pi-file mr-2"></i>Dados do Contrato
          </button>
          <button v-if="isEdit" @click="activeTab = 'anexos'" :class="['py-4 px-1 border-b-2 font-medium text-sm transition-colors', activeTab === 'anexos' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
            <i class="pi pi-paperclip mr-2"></i>Anexos ({{ GestaoJs.anexos.value.length }})
          </button>
          <button v-if="isEdit" @click="activeTab = 'reajustes'" :class="['py-4 px-1 border-b-2 font-medium text-sm transition-colors', activeTab === 'reajustes' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
            <i class="pi pi-chart-line mr-2"></i>Histórico de Reajustes ({{ GestaoJs.reajustes.value.length }})
          </button>
        </nav>
      </div>

      <!-- ═══════════════ TAB DADOS ═══════════════ -->
      <div v-show="activeTab === 'dados'" class="p-6">

        <!-- 1. Dados da Loja/Filial -->
        <div class="mb-8">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="pi pi-building text-blue-500"></i>Dados da Loja/Filial
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Filial *</label>
              <Select v-model="form.filial_id" :options="filialOptions" optionLabel="label" optionValue="value" placeholder="Selecione..." class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Razão Social *</label>
              <InputText v-model="form.razao_social_loja" placeholder="Razão social da loja" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">CNPJ</label>
              <InputText v-model="form.cnpj_loja" placeholder="00.000.000/0000-00" class="w-full" />
            </div>
          </div>
        </div>

        <!-- 2. Dados do Locador -->
        <div class="mb-8">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="pi pi-user text-blue-500"></i>Dados do Locador
          </h3>
          <div class="border border-gray-200 rounded-lg p-4 mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-3">Locador 1 (Principal)</p>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                <Select v-model="form.tipo_pessoa" :options="tipoPessoaOptions" optionLabel="label" optionValue="value" placeholder="Selecione..." class="w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Locador *</label>
                <InputText v-model="form.nome_locador" placeholder="Nome completo ou razão social" class="w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">CPF/CNPJ</label>
                <InputText v-model="form.documento_locador" placeholder="CPF ou CNPJ" class="w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                <InputText v-model="form.telefone_locador" placeholder="(00) 00000-0000" class="w-full" />
              </div>
            </div>
          </div>
          <!-- Locadores Adicionais -->
          <div v-for="(loc, idx) in form.locadores_adicionais" :key="idx" class="border border-blue-100 bg-blue-50 rounded-lg p-4 mb-3">
            <div class="flex justify-between items-center mb-3">
              <p class="text-xs font-semibold text-blue-600 uppercase">Locador {{ idx + 2 }}</p>
              <button @click="removerLocador(idx)" class="text-red-500 hover:text-red-700 text-sm flex items-center gap-1"><i class="pi pi-trash"></i> Remover</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div><label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label><Select v-model="loc.tipo_pessoa" :options="tipoPessoaOptions" optionLabel="label" optionValue="value" placeholder="Selecione..." class="w-full" /></div>
              <div><label class="block text-sm font-medium text-gray-700 mb-1">Nome</label><InputText v-model="loc.nome" placeholder="Nome do locador" class="w-full" /></div>
              <div><label class="block text-sm font-medium text-gray-700 mb-1">CPF/CNPJ</label><InputText v-model="loc.documento" placeholder="CPF ou CNPJ" class="w-full" /></div>
            </div>
          </div>
          <button @click="adicionarLocador" class="flex items-center gap-2 px-3 py-2 text-sm border border-blue-500 text-blue-600 rounded-lg hover:bg-blue-50 mt-1">
            <i class="pi pi-plus"></i> Adicionar Locador
          </button>
        </div>

        <!-- 3. Imobiliária -->
        <div class="mb-8">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="pi pi-briefcase text-blue-500"></i>Imobiliária
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nome da Imobiliária</label>
              <InputText v-model="form.imobiliaria" placeholder="Nome da imobiliária (se houver)" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Telefone da Imobiliária</label>
              <InputText v-model="form.telefone_imobiliaria" placeholder="(00) 00000-0000" class="w-full" />
            </div>
          </div>
        </div>

        <!-- 4. Endereço do Imóvel -->
        <div class="mb-8">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="pi pi-map-marker text-blue-500"></i>Endereço do Imóvel
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Endereço</label><InputText v-model="form.endereco_imovel" placeholder="Rua, número, complemento" class="w-full" /></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Cidade</label><InputText v-model="form.cidade" placeholder="Cidade" class="w-full" /></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Estado</label><Select v-model="form.estado" :options="estadoOptions" optionLabel="label" optionValue="value" placeholder="UF" class="w-full" /></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">CEP</label><InputText v-model="form.cep" placeholder="00000-000" class="w-full" /></div>
          </div>
        </div>

        <!-- 5. Período de Apuração -->
        <div class="mb-8">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="pi pi-calendar text-blue-500"></i>Período de Apuração
          </h3>
          <div class="flex items-center gap-3">
            <div class="w-32">
              <label class="block text-sm font-medium text-gray-700 mb-1">De</label>
              <Select v-model="form.dia_apuracao" :options="diaOptions" optionLabel="label" optionValue="value" placeholder="Dia" class="w-full" />
            </div>
            <span class="mt-6 text-gray-500 font-medium">até</span>
            <div class="w-32">
              <label class="block text-sm font-medium text-gray-700 mb-1">Até</label>
              <Select v-model="form.dia_apuracao_fim" :options="diaOptions" optionLabel="label" optionValue="value" placeholder="Dia" class="w-full" />
            </div>
          </div>
        </div>

        <!-- 6. IPTU e Inscrições -->
        <div class="mb-8">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="pi pi-receipt text-blue-500"></i>IPTU e Inscrições
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">IPTU pago no carnê</label>
              <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                  <RadioButton v-model="form.iptu_pago_carne" :value="true" inputId="iptu_sim" />
                  <label for="iptu_sim" class="text-sm text-gray-700 cursor-pointer">Sim</label>
                </div>
                <div class="flex items-center gap-2">
                  <RadioButton v-model="form.iptu_pago_carne" :value="false" inputId="iptu_nao" />
                  <label for="iptu_nao" class="text-sm text-gray-700 cursor-pointer">Não</label>
                </div>
              </div>
            </div>
          </div>
          <!-- Inscrições IPTU -->
          <div v-for="(item, idx) in form.iptu_inscricoes" :key="idx" class="border border-gray-200 rounded-lg p-4 mb-3 bg-gray-50">
            <div class="flex justify-between items-center mb-3">
              <p class="text-xs font-semibold text-gray-500 uppercase">Inscrição IPTU {{ idx + 1 }}</p>
              <button @click="removerIptu(idx)" class="text-red-500 hover:text-red-700 text-sm flex items-center gap-1"><i class="pi pi-trash"></i> Remover</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div><label class="block text-sm font-medium text-gray-700 mb-1">Inscrição</label><InputText v-model="item.inscricao" placeholder="Número da inscrição IPTU" class="w-full" /></div>
              <div><label class="block text-sm font-medium text-gray-700 mb-1">% IPTU Loja</label><InputNumber v-model="item.percentual_loja" :minFractionDigits="2" :maxFractionDigits="2" suffix="%" placeholder="Ex: 100" class="w-full" /></div>
            </div>
          </div>
          <button @click="adicionarIptu" class="flex items-center gap-2 px-3 py-2 text-sm border border-gray-400 text-gray-600 rounded-lg hover:bg-gray-100">
            <i class="pi pi-plus"></i> Adicionar Inscrição IPTU
          </button>
        </div>

        <!-- 7. Valores, Vigência e Condições -->
        <div class="mb-8">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="pi pi-dollar text-blue-500"></i>Valores, Vigência e Condições
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Valor Mensal *</label>
              <InputNumber v-model="form.valor_mensal" mode="currency" currency="BRL" locale="pt-BR" placeholder="0,00" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Valor Proposto pelo Locador</label>
              <InputNumber v-model="form.valor_proposto_locador" mode="currency" currency="BRL" locale="pt-BR" placeholder="0,00" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Valor Anterior</label>
              <InputNumber v-model="form.valor_anterior" mode="currency" currency="BRL" locale="pt-BR" placeholder="0,00" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Condomínio</label>
              <Select v-model="form.tem_condominio" :options="simNaoOptions" optionLabel="label" optionValue="value" placeholder="Selecione..." class="w-full" />
            </div>
            <div v-if="form.tem_condominio === true">
              <label class="block text-sm font-medium text-gray-700 mb-1">Valor Condomínio</label>
              <InputNumber v-model="form.valor_condominio" mode="currency" currency="BRL" locale="pt-BR" placeholder="0,00" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Início do contrato *</label>
              <DatePicker v-model="form.data_inicio" dateFormat="dd/mm/yy" showIcon placeholder="dd/mm/aaaa" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1"                                                                                                                                                                                      >Fim do contrato *</label>
              <DatePicker v-model="form.data_fim" dateFormat="dd/mm/yy" showIcon placeholder="dd/mm/aaaa" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Prazo do contrato (meses)</label>
              <InputNumber v-model="form.prazo_contrato_meses" :min="1" placeholder="Ex: 36" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Aluguel Antecipado</label>
              <Select v-model="form.pagamento_antecipado" :options="simNaoOptions" optionLabel="label" optionValue="value" placeholder="Selecione..." class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Dia do Vencimento</label>
              <InputNumber v-model="form.dia_vencimento" :min="1" :max="31" placeholder="Ex: 10" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Renovação Automática</label>
              <Select v-model="form.renovacao_automatica" :options="simNaoOptions" optionLabel="label" optionValue="value" placeholder="Selecione..." class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Negociador</label>
              <InputText v-model="form.negociador" placeholder="Nome do negociador" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Selecione..." class="w-full" />
            </div>
          </div>
        </div>

        <!-- 8. Índice de Reajuste -->
        <div class="mb-8">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="pi pi-chart-line text-blue-500"></i>Índice de Reajuste
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Índice Principal</label>
              <Select v-model="form.tipo_indice_id" :options="indiceOptions" optionLabel="label" optionValue="value" placeholder="Selecione..." class="w-full" />
            </div>
            <div v-if="!isIndiceFix">
              <label class="block text-sm font-medium text-gray-700 mb-1">Percentual (%)</label>
              <InputNumber v-model="form.percentual_reajuste_fixo" :minFractionDigits="2" :maxFractionDigits="4" suffix="%" placeholder="Ex: 5.50" class="w-full" />
            </div>
            <div v-if="isIndiceFix">
              <label class="block text-sm font-medium text-gray-700 mb-1">Valor do Reajuste (R$)</label>
              <InputNumber v-model="form.valor_reajuste_fixo" mode="currency" currency="BRL" locale="pt-BR" placeholder="0,00" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Mês base Reajuste</label>
              <Select v-model="form.mes_base_reajuste" :options="mesOptions" optionLabel="label" optionValue="value" placeholder="Selecione..." class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Data Vencimento com Reajuste</label>
              <DatePicker v-model="form.data_vencimento_reajuste" view="month" dateFormat="mm/yy" showIcon placeholder="mm/aaaa" class="w-full" />
            </div>
          </div>
          <!-- Índices adicionais -->
          <div v-for="(ind, idx) in form.indices_adicionais" :key="idx" class="border border-gray-200 rounded-lg p-4 mb-3 bg-gray-50">
            <div class="flex justify-between items-center mb-3">
              <p class="text-xs font-semibold text-gray-500 uppercase">Índice Adicional {{ idx + 1 }}</p>
              <button @click="removerIndice(idx)" class="text-red-500 hover:text-red-700 text-sm flex items-center gap-1"><i class="pi pi-trash"></i> Remover</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div><label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label><InputText v-model="ind.descricao" placeholder="Ex: IGPM, Fixo..." class="w-full" /></div>
              <div><label class="block text-sm font-medium text-gray-700 mb-1">Percentual (%)</label><InputNumber v-model="ind.percentual" :minFractionDigits="2" :maxFractionDigits="4" suffix="%" placeholder="Ex: 3.00" class="w-full" /></div>
            </div>
          </div>
          <button @click="adicionarIndice" class="flex items-center gap-2 px-3 py-2 text-sm border border-gray-400 text-gray-600 rounded-lg hover:bg-gray-100">
            <i class="pi pi-plus"></i> Adicionar Índice
          </button>
        </div>

        <!-- 9. Histórico Anual -->
        <div class="mb-8" v-if="anosContrato.length > 0">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="pi pi-history text-blue-500"></i>Histórico Anual
          </h3>
          <p class="text-sm text-gray-500 mb-4">Preencha os valores praticados em cada ano do contrato ({{ anosContrato.length }} anos).</p>
          <div v-for="(hist, idx) in form.historico_anual" :key="hist.ano" class="border border-gray-200 rounded-lg p-4 mb-3" :class="hist.valor_aluguel ? 'bg-green-50 border-green-200' : 'bg-gray-50'">
            <div class="flex items-center gap-2 mb-3">
              <span class="text-sm font-bold text-gray-700 bg-white border border-gray-300 rounded px-2 py-0.5">{{ hist.ano }}</span>
              <span v-if="hist.valor_aluguel" class="text-xs text-green-600 font-medium">✓ preenchido</span>
              <span v-else class="text-xs text-gray-400">pendente</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Valor do Aluguel</label>
                <InputNumber v-model="hist.valor_aluguel" mode="currency" currency="BRL" locale="pt-BR" placeholder="0,00" class="w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Valor Proposto pelo Locador</label>
                <InputNumber v-model="hist.valor_proposto" mode="currency" currency="BRL" locale="pt-BR" placeholder="0,00" class="w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Valor Anterior</label>
                <InputNumber v-model="hist.valor_anterior" mode="currency" currency="BRL" locale="pt-BR" placeholder="0,00" class="w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Condomínio</label>
                <InputNumber v-model="hist.condominio" mode="currency" currency="BRL" locale="pt-BR" placeholder="0,00" class="w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Índice de Reajuste</label>
                <Select v-model="hist.indice" :options="indiceOptions" optionLabel="label" optionValue="value" placeholder="Selecione..." class="w-full" />
              </div>
              <div v-if="hist.indice && (GestaoJs.tiposIndice.value || []).find(i => i.id === hist.indice)?.codigo !== 'FIXO'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Percentual (%)</label>
                <InputNumber v-model="hist.percentual" :minFractionDigits="2" :maxFractionDigits="2" suffix="%" placeholder="Ex: 5.50" class="w-full" />
              </div>
              <div v-if="hist.indice && (GestaoJs.tiposIndice.value || []).find(i => i.id === hist.indice)?.codigo === 'FIXO'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Valor do Reajuste (R$)</label>
                <InputNumber v-model="hist.valor_reajuste_fixo" mode="currency" currency="BRL" locale="pt-BR" placeholder="0,00" class="w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dia do Vencimento</label>
                <InputNumber v-model="hist.dia_vencimento" :min="1" :max="31" placeholder="Ex: 10" class="w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Negociador</label>
                <InputText v-model="hist.negociador" placeholder="Nome do negociador" class="w-full" />
              </div>
            </div>
          </div>
        </div>
        <div class="mb-8" v-else-if="!form.data_inicio || !form.data_fim">
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="pi pi-history text-blue-500"></i>Histórico Anual
          </h3>
          <p class="text-sm text-gray-400 italic">Preencha as datas de início e fim do contrato para gerar o histórico anual.</p>
        </div>

        <!-- 10. Observações -->
        <div>
          <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i class="pi pi-comment text-blue-500"></i>Observações
          </h3>
          <Textarea v-model="form.observacoes" rows="4" autoResize placeholder="Informações adicionais sobre o contrato..." class="w-full" />
        </div>
      </div>

      <!-- ═══════════════ TAB ANEXOS ═══════════════ -->
      <div v-show="activeTab === 'anexos'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900">Anexos do Contrato</h3>
          <button @click="abrirModalAnexo" class="flex items-center gap-2 px-4 py-2 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50">
            <i class="pi pi-plus"></i>Adicionar Anexo
          </button>
        </div>
        <div v-if="GestaoJs.anexos.value.length === 0" class="text-center py-10 text-gray-500">
          <i class="pi pi-folder-open text-4xl mb-2"></i><p>Nenhum anexo cadastrado</p>
        </div>
        <div v-else class="grid gap-4">
          <div v-for="anexo in GestaoJs.anexos.value" :key="anexo.id" class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center gap-4">
              <i class="pi pi-file text-2xl text-blue-500"></i>
              <div><p class="font-medium">{{ anexo.nome_arquivo }}</p><p class="text-sm text-gray-500">{{ anexo.tipo }} • {{ anexo.descricao || "Sem descrição" }}</p></div>
            </div>
            <div class="flex gap-2">
              <a :href="anexo.caminho" target="_blank" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg"><i class="pi pi-download"></i></a>
              <button @click="excluirAnexo(anexo.id)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg"><i class="pi pi-trash"></i></button>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════ TAB REAJUSTES ═══════════════ -->
      <div v-show="activeTab === 'reajustes'" class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold text-gray-900">Histórico de Reajustes</h3>
          <button @click="abrirModalReajuste" class="flex items-center gap-2 px-4 py-2 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50">
            <i class="pi pi-plus"></i>Registrar Reajuste
          </button>
        </div>
        <div v-if="GestaoJs.reajustes.value.length === 0" class="text-center py-10 text-gray-500">
          <i class="pi pi-chart-line text-4xl mb-2"></i><p>Nenhum reajuste registrado</p>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor Anterior</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor Novo</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Percentual</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Índice</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="reajuste in GestaoJs.reajustes.value" :key="reajuste.id">
                <td class="px-4 py-3">{{ GestaoJs.formatarData(reajuste.data_reajuste) }}</td>
                <td class="px-4 py-3">{{ GestaoJs.formatarMoeda(reajuste.valor_anterior) }}</td>
                <td class="px-4 py-3 font-medium text-green-600">{{ GestaoJs.formatarMoeda(reajuste.valor_reajustado) }}</td>
                <td class="px-4 py-3">{{ reajuste.percentual_aplicado || "-" }}%</td>
                <td class="px-4 py-3">{{ reajuste.indice_utilizado || "-" }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Anexo -->
    <div v-if="showAnexoModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-xl">
        <h3 class="text-lg font-semibold mb-4">Adicionar Anexo</h3>
        <div class="space-y-4">
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Tipo *</label><Select v-model="anexoForm.tipo" :options="tiposAnexo" optionLabel="label" optionValue="value" placeholder="Selecione..." class="w-full" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Arquivo *</label><input type="file" @change="handleFileUpload" class="w-full px-3 py-2 border border-gray-300 rounded-lg" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label><InputText v-model="anexoForm.descricao" placeholder="Descrição do anexo" class="w-full" /></div>
        </div>
        <div class="flex gap-3 mt-6">
          <button @click="showAnexoModal = false" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
          <button @click="salvarAnexo" class="flex-1 px-4 py-2 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50">Salvar</button>
        </div>
      </div>
    </div>

    <!-- Modal Reajuste -->
    <div v-if="showReajusteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-xl">
        <h3 class="text-lg font-semibold mb-4">Registrar Reajuste</h3>
        <div class="space-y-4">
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Data do Reajuste *</label><DatePicker v-model="reajusteForm.data_reajuste" dateFormat="dd/mm/yy" showIcon placeholder="dd/mm/aaaa" class="w-full" /></div>
          <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Valor Anterior</label><InputNumber v-model="reajusteForm.valor_anterior" mode="currency" currency="BRL" locale="pt-BR" disabled class="w-full" /></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Percentual (%)</label><InputNumber v-model="reajusteForm.percentual" :minFractionDigits="2" :maxFractionDigits="2" placeholder="Ex: 5.5" class="w-full" /></div>
          </div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Novo Valor *</label><InputNumber v-model="reajusteForm.valor_novo" mode="currency" currency="BRL" locale="pt-BR" class="w-full" /></div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1">Índice Aplicado</label><InputText v-model="reajusteForm.indice_aplicado" placeholder="Ex: IGPM Dez/2024" class="w-full" /></div>
        </div>
        <div class="flex gap-3 mt-6">
          <button @click="showReajusteModal = false" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
          <button @click="salvarReajuste" class="flex-1 px-4 py-2 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50">Salvar</button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
