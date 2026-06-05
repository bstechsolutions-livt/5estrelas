<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import { onMounted, ref, computed, defineProps, defineEmits } from "vue"
import * as EquipJs from "../gestao-equipamentos.js"
import Button from "primevue/button"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import Panel from "primevue/panel"
import DatePicker from "primevue/datepicker"
import Textarea from "primevue/textarea"
import TabView from "primevue/tabview"
import TabPanel from "primevue/tabpanel"
import Tag from "primevue/tag"
import Dialog from "primevue/dialog"
import { useTour } from "@/composables/useTour"
import "@/../css/tour.css"
import Swal from "sweetalert2"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       PROPS & EMITS                          ║
// ╚══════════════════════════════════════════════════════════════╝
const props = defineProps({
  equipamentoId: { type: [Number, String, null], default: null }
})
const emit = defineEmits(["voltar"])

const isEdit = computed(() => !!props.equipamentoId)
const loadingPage = ref(false)
const saving = ref(false)
const erros = ref({})

// Dados do formulário principal
const form = ref({
  filial_id: "",
  tipo_equipamento_id: "",
  data_validade: null,
  status: "VIGENTE",
  carga: "",
  peso_kg: "",
  qtd_projeto: "",
  numero_identificacao: "",
  localizacao: "",
  observacoes: ""
})

// Fotos
const fotos = ref([])
const fotosParaUpload = ref([]) // Fotos selecionadas no modo novo (antes de salvar)
const uploadingFoto = ref(false)
const fotoModal = ref(false)
const fotoSelecionada = ref(null)
const showConfirmDeleteFoto = ref(false)
const fotoParaExcluir = ref(null)

// Ocorrências
const ocorrencias = ref([])
const loadingOcorrencias = ref(false)
const novaOcorrencia = ref({
  tipo_ocorrencia: "",
  tipo_ocorrencia_descricao: "",
  descricao: "",
  data_ocorrencia: null
})

// Tratativas
const tratativas = ref([])
const loadingTratativas = ref(false)
const novaTratativa = ref({
  descricao: "",
  data_registro: null
})

const filiaisFormatadas = computed(() => {
  return EquipJs.filiais.value.map((filial) => ({
    ...filial,
    label: `${filial.codfilial} - ${filial.fantasia}`
  }))
})

const statusOptions = [
  { label: "Vigente", value: "VIGENTE" },
  { label: "Em Manutenção", value: "EM_MANUTENCAO" }
]

// Verifica se os campos obrigatórios estão preenchidos para habilitar aba de Fotos
const dadosPreenchidos = computed(() => {
  return !!(form.value.filial_id && form.value.tipo_equipamento_id && form.value.data_validade && form.value.status)
})

const tiposOcorrencia = [
  { label: "Despressurização", value: "DESPRESSURIZACAO" },
  { label: "Dano Físico", value: "DANO_FISICO" },
  { label: "Uso em Emergência", value: "USO_EMERGENCIA" },
  { label: "Vencimento Antecipado", value: "VENCIMENTO_ANTECIPADO" },
  { label: "Outro", value: "OUTRO" }
]

// ╔══════════════════════════════════════════════════════════════╗
// ║                       TOUR GUIADO                            ║
// ╚══════════════════════════════════════════════════════════════╝
const { startTour, autoStart, hasSeenTour, checkIfSeen } = useTour("gestao-equipamentos-form")

const tourSteps = computed(() => [
  {
    popover: {
      title: isEdit.value ? "Edição de Equipamento" : "Novo Equipamento",
      description: isEdit.value
        ? `
          Este formulário permite editar o equipamento.<br><br>
          Ele é dividido em abas:<br>
          • <strong>Dados</strong> — informações principais<br>
          • <strong>Fotos</strong> — imagens de auditoria<br>
          • <strong>Ocorrências</strong> — eventos registrados<br>
          • <strong>Tratativas</strong> — ações tomadas
        `
        : `
          Este formulário permite cadastrar um novo equipamento.<br><br>
          Ele é dividido em abas:<br>
          • <strong>Dados</strong> — informações principais e especificações<br>
          • <strong>Fotos</strong> — imagens de auditoria (habilitada após preencher os dados)
        `,
      side: "over",
      align: "center"
    }
  },
  {
    element: "#tour-form-dados",
    popover: {
      title: "Aba Dados",
      description: `
        Preencha os campos obrigatórios: <strong>Filial</strong>, <strong>Tipo de Equipamento</strong>, <strong>Data de Validade</strong> e <strong>Status</strong>.<br><br>
        Campos opcionais: Carga, Peso, Qtd Projeto, Nº Identificação, Localização e Observações.
      `,
      side: "bottom",
      align: "center"
    }
  },
  {
    element: "#tour-form-fotos-tab",
    popover: {
      title: "Aba Fotos",
      description: `
        Faça upload de até <strong>5 fotos</strong> de auditoria do equipamento.<br><br>
        Esta aba só é habilitada após preencher os <strong>campos obrigatórios</strong> na aba Dados.
      `,
      side: "bottom",
      align: "center"
    }
  },
  ...(isEdit.value ? [
    {
      element: "#tour-form-ocorrencias-tab",
      popover: {
        title: "Aba Ocorrências",
        description: `
          Registre eventos relevantes do equipamento, como:<br>
          • <strong>Despressurização</strong><br>
          • <strong>Dano físico</strong><br>
          • <strong>Uso em emergência</strong><br>
          • <strong>Vencimento antecipado</strong>
        `,
        side: "bottom",
        align: "center"
      }
    },
    {
      element: "#tour-form-tratativas-tab",
      popover: {
        title: "Aba Tratativas",
        description: `
          Registre as ações tomadas para o equipamento, como:<br>
          • <strong>Recarga</strong><br>
          • <strong>Substituição</strong><br>
          • <strong>Manutenção preventiva</strong><br>
          • Outras tratativas realizadas
        `,
        side: "bottom",
        align: "center"
      }
    }
  ] : []),
  {
    element: "#tour-btn-ajuda",
    popover: {
      title: "Precisa de ajuda?",
      description: `
        Sempre que tiver dúvidas, clique neste botão <strong>(?)</strong> para iniciar o tour guiado novamente.
      `,
      side: "bottom",
      align: "end"
    }
  }
])

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

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  const promises = [EquipJs.getFiliais(), EquipJs.getTiposEquipamento()]
  if (isEdit.value) {
    promises.push(carregarEquipamento())
  }
  await Promise.all(promises)

  // Tour Guiado — só na primeira vez que abrir o form nesta sessão
  const seen = await checkIfSeen()
  if (!seen) {
    setTimeout(() => {
      autoStart(tourSteps.value, Swal.fire.bind(Swal), 800)
    }, 500)
  }
})

async function carregarEquipamento() {
  loadingPage.value = true
  try {
    await EquipJs.getEquipamento(props.equipamentoId)
    const equip = EquipJs.equipamentoAtual.value.data
    if (equip) {
      form.value = {
        filial_id: equip.filial_id || "",
        tipo_equipamento_id: equip.tipo_equipamento_id ? Number(equip.tipo_equipamento_id) : "",
        data_validade: parseDate(equip.data_validade),
        status: equip.status || "VIGENTE",
        carga: equip.carga || "",
        peso_kg: equip.peso_kg || "",
        qtd_projeto: equip.qtd_projeto || "",
        numero_identificacao: equip.numero_identificacao || "",
        localizacao: equip.localizacao || "",
        observacoes: equip.observacoes || ""
      }
      fotos.value = equip.fotos || []
      ocorrencias.value = equip.ocorrencias || []
      tratativas.value = equip.tratativas || []
    }
  } catch (error) {
    console.error("Erro ao carregar equipamento:", error)
    EquipJs.showToast("Erro ao carregar equipamento", "error")
  } finally {
    loadingPage.value = false
  }
}

async function salvar() {
  erros.value = {}
  saving.value = true
  try {
    const dados = {
      ...form.value,
      data_validade: formatDateForApi(form.value.data_validade)
    }
    if (isEdit.value) {
      dados.id = props.equipamentoId
    }
    const resultado = await EquipJs.salvarEquipamento(dados)

    // Se é novo e tem fotos pendentes, faz upload após criar
    if (!isEdit.value && fotosParaUpload.value.length > 0 && resultado?.id) {
      for (const file of fotosParaUpload.value) {
        try {
          await EquipJs.uploadFotoEquipamento(resultado.id, file)
        } catch (e) { /* toast handled in composable */ }
      }
      fotosParaUpload.value = []
    }

    emit("voltar")
  } catch (error) {
    if (error.response?.data?.erros) {
      erros.value = error.response.data.erros
    }
  } finally {
    saving.value = false
  }
}

function voltar() {
  emit("voltar")
}

// ── Fotos ──
async function onUploadFoto(event) {
  const file = event.target.files[0]
  if (!file) return

  // Modo edição: upload direto
  if (isEdit.value) {
    uploadingFoto.value = true
    try {
      const novaFoto = await EquipJs.uploadFotoEquipamento(props.equipamentoId, file)
      if (novaFoto) fotos.value.push(novaFoto)
    } catch (e) { /* toast handled in composable */ }
    finally {
      uploadingFoto.value = false
      event.target.value = ""
    }
  } else {
    // Modo novo: armazena localmente para upload após salvar
    if (fotosParaUpload.value.length >= 5) {
      EquipJs.showToast("Limite de 5 fotos atingido", "error")
      event.target.value = ""
      return
    }
    fotosParaUpload.value.push(file)
    // Criar preview local
    const preview = {
      id: `temp-${Date.now()}`,
      arquivo_nome: file.name,
      arquivo_path: URL.createObjectURL(file),
      _isLocal: true
    }
    fotos.value.push(preview)
    event.target.value = ""
  }
}

function abrirFoto(foto) {
  fotoSelecionada.value = foto
  fotoModal.value = true
}

function confirmarExcluirFoto(foto) {
  fotoParaExcluir.value = foto
  showConfirmDeleteFoto.value = true
}

async function excluirFotoConfirmada() {
  if (!fotoParaExcluir.value) return
  await EquipJs.excluirFoto(fotoParaExcluir.value.id)
  fotos.value = fotos.value.filter((f) => f.id !== fotoParaExcluir.value.id)
  showConfirmDeleteFoto.value = false
  fotoParaExcluir.value = null
}

function downloadFoto(foto) {
  window.open(`/v2/gestao-contratos/equipamento-fotos/${foto.id}/download`, "_blank")
}

// ── Ocorrências ──
async function salvarOcorrencia() {
  if (!props.equipamentoId) return
  try {
    const dados = {
      ...novaOcorrencia.value,
      data_ocorrencia: formatDateForApi(novaOcorrencia.value.data_ocorrencia)
    }
    const result = await EquipJs.registrarOcorrencia(props.equipamentoId, dados)
    if (result) {
      ocorrencias.value.unshift(result)
      novaOcorrencia.value = { tipo_ocorrencia: "", tipo_ocorrencia_descricao: "", descricao: "", data_ocorrencia: null }
    }
  } catch (e) { /* toast handled in composable */ }
}

// ── Tratativas ──
async function salvarTratativa() {
  if (!props.equipamentoId) return
  try {
    const dados = {
      ...novaTratativa.value,
      data_registro: formatDateForApi(novaTratativa.value.data_registro)
    }
    const result = await EquipJs.registrarTratativa(props.equipamentoId, dados)
    if (result) {
      tratativas.value.unshift(result)
      novaTratativa.value = { descricao: "", data_registro: null }
    }
  } catch (e) { /* toast handled in composable */ }
}
</script>

<template>
  <div>
    <!-- Breadcrumb -->
    <div class="w-full flex flex-wrap items-center bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-xl mb-4 sm:mb-6 border border-gray-200 dark:border-slate-700">
      <div class="flex flex-wrap items-center gap-1 sm:gap-2 text-sm sm:text-base text-gray-600 dark:text-gray-300 font-medium w-full">
        <div class="flex items-center gap-1 sm:gap-2">
          <i class="pi pi-home"></i>
          <span>Home</span>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <a href="/pagina/gestao-contratos" class="hover:text-purple-600 dark:hover:text-purple-400">Gestão de Contratos</a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <a href="/pagina/gestao-contratos/equipamentos" class="hover:text-purple-600 dark:hover:text-purple-400">Equipamentos</a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">{{ isEdit ? "Editar" : "Novo" }} Equipamento</span>
        </div>
      </div>
    </div>

    <!-- Cabeçalho -->
    <div class="space-y-2 mb-6 mt-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3">
              <div class="w-1 h-8 bg-gradient-to-b from-purple-500 to-purple-700 rounded-full"></div>
              {{ isEdit ? "Editar" : "Novo" }} Equipamento
            </h2>
          </div>
        </div>
        <div class="flex gap-2">
          <Button id="tour-btn-ajuda" icon="pi pi-question-circle" severity="secondary" text size="small" @click="startTour(tourSteps.value)" v-tooltip.top="'Tour Guiado'" />
          <Button @click="voltar" label="Voltar" icon="pi pi-arrow-left" severity="secondary" outlined />
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
    <div v-if="loadingPage" class="flex justify-center py-20">
      <i class="pi pi-spin pi-spinner text-4xl text-purple-500"></i>
    </div>

    <!-- Conteúdo com Abas -->
    <div v-else>
      <TabView>
        <!-- ═══════════ ABA DADOS ═══════════ -->
        <TabPanel>
          <template #header>
            <span id="tour-form-dados">Dados</span>
          </template>
          <Panel class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden">
            <template #header>
              <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-purple-200 dark:bg-purple-900 shadow-lg flex-shrink-0">
                  <i class="pi pi-box text-purple-700 dark:text-purple-300 !text-xl"></i>
                </span>
                <div>
                  <h3 class="text-2xl font-extrabold text-black-800 dark:text-white">Dados do Equipamento</h3>
                  <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Informações principais e especificações técnicas</div>
                </div>
              </div>
            </template>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Filial *</label>
                <Select
                  v-model="form.filial_id"
                  :options="filiaisFormatadas"
                  optionLabel="label"
                  optionValue="codfilial"
                  placeholder="Selecione..."
                  class="w-full"
                  :class="{ 'p-invalid': erros.filial_id }"
                />
                <small v-if="erros.filial_id" class="text-red-500">{{ erros.filial_id[0] }}</small>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Tipo de Equipamento *</label>
                <Select
                  v-model="form.tipo_equipamento_id"
                  :options="EquipJs.tiposEquipamento.value"
                  optionLabel="nome"
                  optionValue="id"
                  placeholder="Selecione..."
                  class="w-full"
                  :class="{ 'p-invalid': erros.tipo_equipamento_id }"
                />
                <small v-if="erros.tipo_equipamento_id" class="text-red-500">{{ erros.tipo_equipamento_id[0] }}</small>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Data de Validade *</label>
                <DatePicker
                  v-model="form.data_validade"
                  dateFormat="dd/mm/yy"
                  showIcon
                  placeholder="dd/mm/aaaa"
                  class="w-full"
                  :class="{ 'p-invalid': erros.data_validade }"
                />
                <small v-if="erros.data_validade" class="text-red-500">{{ erros.data_validade[0] }}</small>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Status *</label>
                <Select
                  v-model="form.status"
                  :options="statusOptions"
                  optionLabel="label"
                  optionValue="value"
                  class="w-full"
                  :class="{ 'p-invalid': erros.status }"
                />
                <small v-if="erros.status" class="text-red-500">{{ erros.status[0] }}</small>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Carga</label>
                <InputText v-model="form.carga" placeholder="Ex: PÓ ABC, CO2, AP" class="w-full" />
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Peso (kg)</label>
                <InputText v-model="form.peso_kg" type="number" step="0.01" placeholder="0,00" class="w-full" />
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Qtd Projeto</label>
                <InputText v-model="form.qtd_projeto" type="number" placeholder="0" class="w-full" />
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Nº Identificação</label>
                <InputText v-model="form.numero_identificacao" placeholder="Patrimônio / ID" class="w-full" />
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Localização</label>
                <InputText v-model="form.localizacao" placeholder="Localização na filial" class="w-full" />
              </div>

              <div class="md:col-span-3 flex flex-col gap-1">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Observações</label>
                <Textarea v-model="form.observacoes" rows="3" autoResize placeholder="Informações adicionais..." class="w-full" />
              </div>
            </div>
          </Panel>
        </TabPanel>

        <!-- ═══════════ ABA FOTOS ═══════════ -->
        <TabPanel :disabled="!dadosPreenchidos">
          <template #header>
            <span id="tour-form-fotos-tab">Fotos</span>
          </template>
          <Panel class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden">
            <template #header>
              <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-200 dark:bg-green-900 shadow-lg flex-shrink-0">
                  <i class="pi pi-images text-green-700 dark:text-green-300 !text-xl"></i>
                </span>
                <div>
                  <h3 class="text-2xl font-extrabold text-black-800 dark:text-white">Fotos de Auditoria</h3>
                  <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Até 5 fotos por equipamento (jpg, jpeg, png — máx. 10 MB)</div>
                </div>
              </div>
            </template>

            <!-- Upload -->
            <div v-if="fotos.length < 5" class="mb-4">
              <label
                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-lg cursor-pointer hover:bg-purple-200 dark:hover:bg-purple-900/50 transition font-medium text-sm"
              >
                <i :class="uploadingFoto ? 'pi pi-spin pi-spinner' : 'pi pi-upload'"></i>
                {{ uploadingFoto ? "Enviando..." : "Enviar Foto" }}
                <input type="file" accept="image/jpeg,image/png" class="hidden" @change="onUploadFoto" :disabled="uploadingFoto" />
              </label>
            </div>
            <p v-else class="text-sm text-orange-600 dark:text-orange-400 mb-4">
              <i class="pi pi-info-circle mr-1"></i> Limite de 5 fotos atingido.
            </p>

            <!-- Galeria -->
            <div v-if="fotos.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
              <div v-for="foto in fotos" :key="foto.id" class="relative group rounded-lg overflow-hidden border border-gray-200 dark:border-slate-700">
                <img
                  :src="foto._isLocal ? foto.arquivo_path : '/storage/' + foto.arquivo_path"
                  :alt="foto.arquivo_nome"
                  class="w-full h-32 object-cover cursor-pointer"
                  @click="abrirFoto(foto)"
                />
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                  <Button icon="pi pi-eye" severity="info" text rounded size="small" @click="abrirFoto(foto)" />
                  <Button icon="pi pi-download" severity="success" text rounded size="small" @click="downloadFoto(foto)" />
                  <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="confirmarExcluirFoto(foto)" />
                </div>
              </div>
            </div>
            <div v-else class="text-center py-8 text-gray-400 dark:text-gray-500">
              <i class="pi pi-image text-4xl mb-2"></i>
              <p>Nenhuma foto cadastrada</p>
            </div>
          </Panel>

          <!-- Modal Visualizar Foto -->
          <Dialog v-model:visible="fotoModal" modal :header="fotoSelecionada?.arquivo_nome || 'Foto'" class="w-full max-w-3xl">
            <div class="flex justify-center">
              <img v-if="fotoSelecionada" :src="fotoSelecionada._isLocal ? fotoSelecionada.arquivo_path : '/storage/' + fotoSelecionada.arquivo_path" :alt="fotoSelecionada.arquivo_nome" class="max-w-full max-h-[70vh] object-contain rounded" />
            </div>
            <template #footer>
              <Button label="Download" icon="pi pi-download" severity="success" outlined @click="downloadFoto(fotoSelecionada)" />
              <Button label="Fechar" icon="pi pi-times" severity="secondary" outlined @click="fotoModal = false" />
            </template>
          </Dialog>

          <!-- Confirmar Exclusão Foto -->
          <Dialog v-model:visible="showConfirmDeleteFoto" modal header="Confirmar Exclusão" class="w-full max-w-sm">
            <p class="text-gray-600 dark:text-gray-400">Tem certeza que deseja excluir esta foto?</p>
            <template #footer>
              <Button label="Cancelar" severity="secondary" outlined @click="showConfirmDeleteFoto = false" />
              <Button label="Excluir" severity="danger" @click="excluirFotoConfirmada" />
            </template>
          </Dialog>
        </TabPanel>

        <!-- ═══════════ ABA OCORRÊNCIAS ═══════════ -->
        <TabPanel v-if="isEdit">
          <template #header>
            <span id="tour-form-ocorrencias-tab">Ocorrências</span>
          </template>
          <Panel class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden mb-4">
            <template #header>
              <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-orange-200 dark:bg-orange-900 shadow-lg flex-shrink-0">
                  <i class="pi pi-exclamation-triangle text-orange-700 dark:text-orange-300 !text-xl"></i>
                </span>
                <div>
                  <h3 class="text-2xl font-extrabold text-black-800 dark:text-white">Registrar Ocorrência</h3>
                  <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Registre eventos relevantes do equipamento</div>
                </div>
              </div>
            </template>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Tipo de Ocorrência *</label>
                <Select
                  v-model="novaOcorrencia.tipo_ocorrencia"
                  :options="tiposOcorrencia"
                  optionLabel="label"
                  optionValue="value"
                  placeholder="Selecione..."
                  class="w-full"
                />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Data da Ocorrência *</label>
                <DatePicker v-model="novaOcorrencia.data_ocorrencia" dateFormat="dd/mm/yy" showIcon placeholder="dd/mm/aaaa" class="w-full" />
              </div>
              <div v-if="novaOcorrencia.tipo_ocorrencia === 'OUTRO'" class="flex flex-col gap-1 md:col-span-2">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Descrição do Tipo *</label>
                <InputText v-model="novaOcorrencia.tipo_ocorrencia_descricao" placeholder="Descreva o tipo de ocorrência" class="w-full" />
              </div>
              <div class="flex flex-col gap-1 md:col-span-2">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Descrição *</label>
                <Textarea v-model="novaOcorrencia.descricao" rows="3" autoResize placeholder="Descreva a ocorrência..." class="w-full" />
              </div>
              <div class="md:col-span-2 flex justify-end">
                <Button label="Registrar Ocorrência" icon="pi pi-plus" severity="help" outlined @click="salvarOcorrencia" :loading="EquipJs.loading.value" />
              </div>
            </div>
          </Panel>

          <!-- Lista de Ocorrências -->
          <Panel class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden">
            <template #header>
              <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-orange-200 dark:bg-orange-900 shadow-lg flex-shrink-0">
                  <i class="pi pi-list text-orange-700 dark:text-orange-300 !text-xl"></i>
                </span>
                <div>
                  <h3 class="text-2xl font-extrabold text-black-800 dark:text-white">Histórico de Ocorrências</h3>
                  <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ ocorrencias.length }} registro(s)</div>
                </div>
              </div>
            </template>

            <div v-if="ocorrencias.length > 0" class="space-y-3">
              <div v-for="oc in ocorrencias" :key="oc.id" class="p-4 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-200 dark:border-slate-600">
                <div class="flex items-center justify-between mb-2">
                  <Tag :value="EquipJs.getTipoOcorrenciaLabel(oc.tipo_ocorrencia)" severity="warn" class="font-medium" />
                  <span class="text-xs text-gray-500 dark:text-gray-400">{{ EquipJs.formatarData(oc.data_ocorrencia) }}</span>
                </div>
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ oc.descricao }}</p>
                <p v-if="oc.tipo_ocorrencia === 'OUTRO' && oc.tipo_ocorrencia_descricao" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  Tipo: {{ oc.tipo_ocorrencia_descricao }}
                </p>
              </div>
            </div>
            <div v-else class="text-center py-8 text-gray-400 dark:text-gray-500">
              <i class="pi pi-info-circle text-3xl mb-2"></i>
              <p>Nenhuma ocorrência registrada</p>
            </div>
          </Panel>
        </TabPanel>

        <!-- ═══════════ ABA TRATATIVAS ═══════════ -->
        <TabPanel v-if="isEdit">
          <template #header>
            <span id="tour-form-tratativas-tab">Tratativas</span>
          </template>
          <Panel class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden mb-4">
            <template #header>
              <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-200 dark:bg-blue-900 shadow-lg flex-shrink-0">
                  <i class="pi pi-check-circle text-blue-700 dark:text-blue-300 !text-xl"></i>
                </span>
                <div>
                  <h3 class="text-2xl font-extrabold text-black-800 dark:text-white">Registrar Tratativa</h3>
                  <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Registre ações tomadas para o equipamento</div>
                </div>
              </div>
            </template>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="flex flex-col gap-1 md:col-span-2">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Descrição *</label>
                <Textarea v-model="novaTratativa.descricao" rows="3" autoResize placeholder="Descreva a tratativa..." class="w-full" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Data do Registro *</label>
                <DatePicker v-model="novaTratativa.data_registro" dateFormat="dd/mm/yy" showIcon placeholder="dd/mm/aaaa" class="w-full" />
              </div>
              <div class="flex items-end justify-end">
                <Button label="Registrar Tratativa" icon="pi pi-plus" severity="help" outlined @click="salvarTratativa" :loading="EquipJs.loading.value" />
              </div>
            </div>
          </Panel>

          <!-- Lista de Tratativas -->
          <Panel class="bg-white dark:bg-slate-800 rounded-3xl p-4 relative overflow-hidden">
            <template #header>
              <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-200 dark:bg-blue-900 shadow-lg flex-shrink-0">
                  <i class="pi pi-list text-blue-700 dark:text-blue-300 !text-xl"></i>
                </span>
                <div>
                  <h3 class="text-2xl font-extrabold text-black-800 dark:text-white">Histórico de Tratativas</h3>
                  <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ tratativas.length }} registro(s)</div>
                </div>
              </div>
            </template>

            <div v-if="tratativas.length > 0" class="space-y-3">
              <div v-for="trat in tratativas" :key="trat.id" class="p-4 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-200 dark:border-slate-600">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-sm font-medium text-gray-900 dark:text-white">{{ trat.created_by_nome || "Usuário" }}</span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">{{ EquipJs.formatarData(trat.data_registro) }}</span>
                </div>
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ trat.descricao }}</p>
              </div>
            </div>
            <div v-else class="text-center py-8 text-gray-400 dark:text-gray-500">
              <i class="pi pi-info-circle text-3xl mb-2"></i>
              <p>Nenhuma tratativa registrada</p>
            </div>
          </Panel>
        </TabPanel>
      </TabView>
    </div>
  </div>
</template>
