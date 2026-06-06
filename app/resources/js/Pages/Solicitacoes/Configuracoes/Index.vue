<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import Loader from "@/Components/Loader.vue"
import {
  toastSuccess,
  toastError,
  toastInfo,
  uploadFile,
  swalConfirm
} from "@/utils/globalFunctions"
import { Head } from "@inertiajs/vue3"
import {
  Button,
  Divider,
  InputText,
  Select,
  Checkbox,
  InputNumber
} from "primevue"
import PickList from "primevue/picklist"
import ToggleSwitch from "primevue/toggleswitch"
import Editor from "primevue/editor"
import Dialog from "primevue/dialog"
import ConfirmDialog from "primevue/confirmdialog"
import { useConfirm } from "primevue/useconfirm"
import { onMounted, ref, watch, computed } from "vue"
import BsButton from "@/Components/Componentes/BsButton.vue"
import BsIcone from "@/Components/Componentes/BsIcone.vue"
import BsFile2 from "@/Components/Componentes/BsFile2.vue"
import ViewFiles from "@/Components/Componentes/ViewFiles.vue"
import Funcionario from "@/Components/Componentes/Funcionario.vue"
import LiberacaoDialog from "./Components/LiberacaoDialog.vue"
import ResponsaveisDialog from "./Components/ResponsaveisDialog.vue"
import RedirecionamentoDialog from "./Components/RedirecionamentoDialog.vue"
import EtapasDialog from "./Components/EtapasDialog.vue"
import FluxoDialog from "./Components/FluxoDialog.vue"
import AcoesAssuntoMenu from "./Components/AcoesAssuntoMenu.vue"

const props = defineProps(["departamentos"])
const loading = ref(false)
const loadingButton = ref(false)
const loadingAssuntos = ref(false)
const departamentos = ref([[], []])
const salvandoDep = ref(false)
const salvandoAssuntos = ref(false)
const assuntos = ref([])
const departamentoAssunto = ref(null)
const responsaveis = ref([])
const dialogCampos = ref(false)
const dialogSelecao = ref(false)
const assuntoSelecionado = ref(null)
const camposPadrao = ref([])
const isMobile = ref(window.innerWidth < 768 || window.innerHeight < 768)
const estadoOptions = [
  { label: "ATIVO", value: true },
  { label: "INATIVO", value: false }
]
const obrigatorioOptions = [
  { label: "OBRIGATÓRIO", value: true },
  { label: "OPCIONAL", value: false }
]
const tipoTituloOptions = [
  { label: "Texto Livre", value: "texto" },
  { label: "Seleção", value: "selecao" }
]
const tipoCampoOptions = [
  { label: "Seleção", value: "selecao" },
  { label: "Colaborador", value: "colaborador" },
  { label: "Data", value: "data" },
  { label: "Texto", value: "texto" },
  { label: "Arquivo", value: "arquivo" },
  { label: "Número", value: "numero" },
  { label: "CNPJ", value: "cnpj" },
  { label: "Depto de Compras (Winthor)", value: "depto_compras" },
  { label: "Depto de Funcionário (Winthor)", value: "depto_funcionario" },
  { label: "Filial (Winthor)", value: "filial_winthor" },
  { label: "Função (Winthor)", value: "funcao" },
  { label: "Regional", value: "regional" }
]
const tipoDataOptions = [
  { label: "Única", value: "single" },
  { label: "Período", value: "range" }
]

const listaSelect = ref([
  {
    id: null,
    label: "",
    ativo: true,
    obrigatorio: false,
    observacao: "",
    valores: [],
    tipo: "selecao",
    tipo_data: "range",
    dias_minimos: null,
    multiplo: false,
    errors: {
      erroLabel: "",
      erroOptions: ""
    }
  }
])
const canaisNotif = ref({})
const sucessoNotif = ref(false)
const equipamentos = ref([])
const carregado = ref(false)
const prazoResolucao = ref("")

// Variáveis para aba Filiais (Visibilidade Liderança)
const filiaisDesconfiguradas = ref([])
const filiaisConfiguradas = ref([])
const filialDesconfiguradaSelecionada = ref(null)
const filialConfiguradaSelecionada = ref(null)
const filtroFilialDesconfigurada = ref("")
const filtroFilialConfigurada = ref("")
const salvandoFiliais = ref(false)

// Computed para filiais desconfiguradas (filtradas)
const filiaisDesconfiguradasFiltradas = computed(() => {
  let lista = filiaisDesconfiguradas.value.filter(
    (f) => f && f.fantasia && f.fantasia.trim() !== ""
  )
  if (filtroFilialDesconfigurada.value) {
    const filtro = filtroFilialDesconfigurada.value.toLowerCase()
    lista = lista.filter(
      (f) =>
        f.fantasia.toLowerCase().includes(filtro) ||
        String(f.codigo).includes(filtro) ||
        (f.cidade && f.cidade.toLowerCase().includes(filtro))
    )
  }
  return lista
})

// Computed para filiais configuradas (filtradas)
const filiaisConfiguradasFiltradas = computed(() => {
  let lista = filiaisConfiguradas.value.filter(
    (f) => f && f.fantasia && f.fantasia.trim() !== ""
  )
  if (filtroFilialConfigurada.value) {
    const filtro = filtroFilialConfigurada.value.toLowerCase()
    lista = lista.filter(
      (f) =>
        f.fantasia.toLowerCase().includes(filtro) ||
        String(f.codigo).includes(filtro) ||
        (f.cidade && f.cidade.toLowerCase().includes(filtro))
    )
  }
  return lista
})

function selecionarFilialDesconfigurada(filial) {
  filialDesconfiguradaSelecionada.value = filial
  filialConfiguradaSelecionada.value = null
}

function selecionarFilialConfigurada(filial) {
  filialConfiguradaSelecionada.value = filial
  filialDesconfiguradaSelecionada.value = null
}

function habilitarFilial() {
  if (filialDesconfiguradaSelecionada.value) {
    const index = filiaisDesconfiguradas.value.findIndex(
      (f) => f.codigo === filialDesconfiguradaSelecionada.value.codigo
    )
    if (index !== -1) {
      const [filial] = filiaisDesconfiguradas.value.splice(index, 1)
      filiaisConfiguradas.value.push(filial)
      filiaisConfiguradas.value.sort((a, b) =>
        a.fantasia.localeCompare(b.fantasia)
      )
      filialDesconfiguradaSelecionada.value = null
    }
  }
}

function desabilitarFilial() {
  if (filialConfiguradaSelecionada.value) {
    const index = filiaisConfiguradas.value.findIndex(
      (f) => f.codigo === filialConfiguradaSelecionada.value.codigo
    )
    if (index !== -1) {
      const [filial] = filiaisConfiguradas.value.splice(index, 1)
      filiaisDesconfiguradas.value.push(filial)
      filiaisDesconfiguradas.value.sort((a, b) =>
        a.fantasia.localeCompare(b.fantasia)
      )
      filialConfiguradaSelecionada.value = null
    }
  }
}

async function getFiliaisLideranca() {
  try {
    const { data } = await axios.get(
      "/solicitacoes/configuracoes/filiais-lideranca"
    )
    const configuradas = []
    const desconfiguradas = []
    data.forEach((f) => {
      if (f.configurada) {
        configuradas.push(f)
      } else {
        desconfiguradas.push(f)
      }
    })
    filiaisConfiguradas.value = configuradas.sort((a, b) =>
      a.fantasia.localeCompare(b.fantasia)
    )
    filiaisDesconfiguradas.value = desconfiguradas.sort((a, b) =>
      a.fantasia.localeCompare(b.fantasia)
    )
  } catch (err) {
    toastError("Erro ao carregar filiais")
  }
}

async function salvarFiliaisLideranca() {
  salvandoFiliais.value = true
  try {
    await axios.post("/solicitacoes/configuracoes/filiais-lideranca", {
      ativos: filiaisConfiguradas.value.map((f) => f.codigo),
      inativos: filiaisDesconfiguradas.value.map((f) => f.codigo)
    })
    toastSuccess("Filiais salvas com sucesso!")
  } catch (err) {
    toastError("Erro ao salvar filiais")
  }
  salvandoFiliais.value = false
}
const aba = ref("departamentos")
const dialogInstrucoes = ref(false)
const assuntoParaInstrucoes = ref(null)
const dialogModelos = ref(false)
const assuntoParaModelos = ref(null)
const modelosTemporarios = ref([])
const visualizarModelos = ref(false)
const modelosParaVisualizar = ref([])
const filtroAtivo = ref("todos")
const abrirDialogRedirecionar = ref(false)

// Variáveis para o PickList customizado de departamentos
const depDesabilitadoSelecionado = ref(null)
const depHabilitadoSelecionado = ref(null)
const filtroDepDesabilitado = ref("")
const filtroDepHabilitado = ref("")

// Computed para departamentos desabilitados (filtrados)
const depsDesabilitadosFiltrados = computed(() => {
  if (!departamentos.value[0]) return []
  // Filtrar itens com label válido (não null, não vazio)
  let lista = departamentos.value[0].filter(
    (dep) => dep && dep.label && dep.label.trim() !== ""
  )
  if (filtroDepDesabilitado.value) {
    lista = lista.filter((dep) =>
      dep.label
        .toLowerCase()
        .includes(filtroDepDesabilitado.value.toLowerCase())
    )
  }
  return lista
})

// Computed para departamentos habilitados (filtrados)
const depsHabilitadosFiltrados = computed(() => {
  if (!departamentos.value[1]) return []
  // Filtrar itens com label válido (não null, não vazio)
  let lista = departamentos.value[1].filter(
    (dep) => dep && dep.label && dep.label.trim() !== ""
  )
  if (filtroDepHabilitado.value) {
    lista = lista.filter((dep) =>
      dep.label.toLowerCase().includes(filtroDepHabilitado.value.toLowerCase())
    )
  }
  return lista
})

// Funções para selecionar departamento
function selecionarDepDesabilitado(dep) {
  depDesabilitadoSelecionado.value = dep
  // Remover seleção do outro lado
  depHabilitadoSelecionado.value = null
}

function selecionarDepHabilitado(dep) {
  depHabilitadoSelecionado.value = dep
  // Remover seleção do outro lado
  depDesabilitadoSelecionado.value = null
}

// Funções para mover departamentos
function habilitarDepartamento() {
  if (depDesabilitadoSelecionado.value) {
    const index = departamentos.value[0].findIndex(
      (d) => d.value === depDesabilitadoSelecionado.value.value
    )
    if (index !== -1) {
      const [dep] = departamentos.value[0].splice(index, 1)
      departamentos.value[1].push(dep)
      departamentos.value[1].sort((a, b) => a.label.localeCompare(b.label))
      depDesabilitadoSelecionado.value = null
    }
  }
}

function desabilitarDepartamento() {
  if (depHabilitadoSelecionado.value) {
    const index = departamentos.value[1].findIndex(
      (d) => d.value === depHabilitadoSelecionado.value.value
    )
    if (index !== -1) {
      const [dep] = departamentos.value[1].splice(index, 1)
      departamentos.value[0].push(dep)
      departamentos.value[0].sort((a, b) => a.label.localeCompare(b.label))
      depHabilitadoSelecionado.value = null
    }
  }
}

// #12173 - Variável para campos de exportação
const camposExportacao = ref([
  { coluna: "id", label: "ID" },
  { coluna: "titulo", label: "Título" },
  { coluna: "prioridade", label: "Prioridade" },
  { coluna: "filial_id", label: "Filial" },
  { coluna: "assunto_id", label: "Assunto" },
  { coluna: "usuario_responsavel", label: "Responsável" },
  { coluna: "usuario_solicitante", label: "Solicitante" },
  { coluna: "created_at", label: "Data Criação" }
])

// Variáveis para liberação
const dialogLiberacao = ref(false)
const assuntoParaLiberacao = ref(null)

// Variáveis para responsáveis do assunto (permissão exclusiva)
const dialogResponsaveisAssunto = ref(false)
const assuntoParaResponsaveis = ref(null)

// Variáveis para etapas de andamento do assunto
const dialogEtapas = ref(false)
const assuntoParaEtapas = ref(null)

// Variáveis para fluxo/workflow do assunto
const dialogFluxo = ref(false)
const assuntoParaFluxo = ref(null)

// Variáveis para dialog de alteração de tipo de campo
const dialogAlteracaoTipo = ref(false)
const campoAlteracaoTipo = ref(null)
const tipoAntigoAlteracao = ref(null)
const tipoNovoAlteracao = ref(null)
const qtdRespostasAlteracao = ref(0)

// Variáveis para responsáveis adicionais
const dialogResponsaveisAdicionais = ref(false)
const responsaveisAdicionais = ref([])
const novoResponsavelAdicional = ref(null)
const loadingResponsaveis = ref(false)
const departamentoResponsaveis = ref(null)

// Variável para nova opção de título (campo de seleção)
const novaOpcaoTitulo = ref("")

// Função para adicionar opção ao campo titulo
function adicionarOpcaoTitulo(campo) {
  if (!novaOpcaoTitulo.value.trim()) return
  if (!campo.opcoes_titulo) {
    campo.opcoes_titulo = []
  }
  // Evitar duplicatas
  if (!campo.opcoes_titulo.includes(novaOpcaoTitulo.value.trim())) {
    campo.opcoes_titulo.push(novaOpcaoTitulo.value.trim())
  }
  novaOpcaoTitulo.value = ""
}

// Função para remover opção do campo titulo
function removerOpcaoTitulo(campo, index) {
  if (campo.opcoes_titulo) {
    campo.opcoes_titulo.splice(index, 1)
  }
}

// Computed para filtrar campos
const camposFiltrados = computed(() => {
  if (!assuntoSelecionado.value?.campos) return []

  switch (filtroAtivo.value) {
    case "ativos":
      return assuntoSelecionado.value.campos.filter((c) => c.ativo)
    case "inativos":
      return assuntoSelecionado.value.campos.filter((c) => !c.ativo)
    default:
      return assuntoSelecionado.value.campos
  }
})

// Computed para estatísticas dos campos
const estatisticasCampos = computed(() => {
  if (!assuntoSelecionado.value?.campos)
    return {
      ativos: 0,
      inativos: 0,
      obrigatorios: 0,
      opcionais: 0
    }

  const campos = assuntoSelecionado.value.campos
  return {
    ativos: campos.filter((c) => c.ativo).length,
    inativos: campos.filter((c) => !c.ativo).length,
    obrigatorios: campos.filter((c) => c.ativo && c.obrigatorio).length,
    opcionais: campos.filter((c) => c.ativo && !c.obrigatorio).length
  }
})

// Funções helper para Avatar
function obterIniciais(nome) {
  if (!nome) return "?"
  const partes = nome
    .trim()
    .split(" ")
    .filter((p) => p.length > 0)
  if (partes.length === 0) return "?"
  if (partes.length === 1) return partes[0].charAt(0).toUpperCase()
  return (
    partes[0].charAt(0) + partes[partes.length - 1].charAt(0)
  ).toUpperCase()
}

function obterNomeSobrenome(nome) {
  if (!nome) return ""
  const partes = nome
    .trim()
    .split(" ")
    .filter((p) => p.length > 0)
  if (partes.length === 0) return ""
  if (partes.length === 1) return partes[0]
  return `${partes[0]} ${partes[partes.length - 1]}`
}

// Inicializar o confirm popup
const confirm = useConfirm()

onMounted(async () => {
  layoutJs.setPaginaNova(true)
  getCanaisNotif()
  loading.value = true

  await getDepartamentos()
  await getEquipamentos()
  await getFiliaisLideranca()
  carregado.value = true
  loading.value = false
})

watch(
  () => [window.innerWidth, window.innerHeight],
  () => {
    isMobile.value = window.innerWidth < 768 || window.innerHeight < 768
  }
)
window.addEventListener("resize", () => {
  isMobile.value = window.innerWidth < 768 || window.innerHeight < 768
})

function showDialogCampos(assunto) {
  assuntoSelecionado.value = assunto
  filtroAtivo.value = "todos" // Reset do filtro ao abrir
  dialogCampos.value = true
}

function salvarEFecharCampos() {
  dialogCampos.value = false
  salvarAssuntos()
}

function salvarEFecharInstrucoes() {
  dialogInstrucoes.value = false
  salvarAssuntos()
}

function toggleTodosCampos(ativo) {
  if (assuntoSelecionado.value?.campos) {
    assuntoSelecionado.value.campos.forEach((campo) => {
      campo.ativo = ativo
    })
  }
}

function showDialogInstrucoes(assunto) {
  assuntoParaInstrucoes.value = assunto
  if (!assunto.instrucoes) {
    assunto.instrucoes = ""
  }
  dialogInstrucoes.value = true
}

function openRedirect(assunto) {
  if (!assunto.id) {
    toastError(
      "É necessário salvar o assunto antes de configurar redirecionamentos"
    )
    return
  }
  assuntoSelecionado.value = assunto
  abrirDialogRedirecionar.value = true
}

// Função para duplicar um assunto
async function duplicarAssunto(assunto) {
  if (!assunto.id) {
    toastError("É necessário salvar o assunto antes de duplicar")
    return
  }

  confirm.require({
    message: `Deseja duplicar o assunto "${assunto.assunto}" com todos os seus campos e configurações?`,
    header: "Confirmar Duplicação",
    icon: "pi pi-copy",
    rejectClass: "p-button-secondary p-button-outlined",
    rejectLabel: "Cancelar",
    acceptLabel: "Duplicar",
    accept: async () => {
      loading.value = true
      try {
        const response = await axios.post(
          "/solicitacoes/configuracoes/duplicar-assunto",
          { assunto_id: assunto.id }
        )

        if (response.data.sucesso) {
          toastSuccess(
            response.data.mensagem || "Assunto duplicado com sucesso!"
          )
          // Recarregar a lista de assuntos
          await buscarAssuntos()
        } else {
          toastError(response.data.mensagem || "Erro ao duplicar assunto")
        }
      } catch (error) {
        console.error(error)
        toastError(error.response?.data?.mensagem || "Erro ao duplicar assunto")
      } finally {
        loading.value = false
      }
    }
  })
}

// Função para ativar/desativar um assunto
async function toggleAtivoAssunto(assunto) {
  if (!assunto.id) {
    toastError("É necessário salvar o assunto antes de alterar o status")
    return
  }

  const estaAtivo = assunto.ativo === "S" || assunto.ativo === true
  const acao = estaAtivo ? "desativar" : "ativar"
  const acaoCapitalizada = estaAtivo ? "Desativar" : "Ativar"
  const mensagem = estaAtivo
    ? "O assunto não aparecerá mais para os usuários criarem novas solicitações."
    : "O assunto voltará a aparecer para os usuários."

  const { isConfirmed } = await swalConfirm(
    `${acaoCapitalizada} assunto?`,
    `Deseja ${acao} o assunto "${assunto.assunto}"? ${mensagem}`,
    acaoCapitalizada,
    "Cancelar"
  )

  if (!isConfirmed) return

  loading.value = true

  try {
    const response = await axios.post(
      "/solicitacoes/configuracoes/toggle-ativo-assunto",
      { assunto_id: assunto.id }
    )

    if (response.data.sucesso) {
      // Atualizar o status local do assunto
      assunto.ativo = response.data.ativo
      toastSuccess(response.data.mensagem)
    } else {
      toastError(response.data.mensagem || `Erro ao ${acao} assunto`)
    }
  } catch (error) {
    console.error(error)
    toastError(error.response?.data?.mensagem || `Erro ao ${acao} assunto`)
  } finally {
    loading.value = false
  }
}

function showDialogModelos(assunto) {
  // Verificar se o assunto já foi salvo (tem ID)
  if (!assunto.id) {
    toastError("É necessário salvar o assunto antes de adicionar modelos")
    return
  }

  assuntoParaModelos.value = assunto
  if (!assunto.modelos) {
    assunto.modelos = []
  }
  // Limpar lista temporária ao abrir - só para novos uploads
  modelosTemporarios.value = []

  dialogModelos.value = true
}

function atualizarListaModelos(arquivos) {
  modelosTemporarios.value = arquivos.map((arquivo) => ({
    file_id: null, // Será preenchido após upload
    arquivo: arquivo,
    nome: arquivo.nome || arquivo.name
  }))
}

function deletarModeloTemporario() {
  // Reset da lista temporária
  modelosTemporarios.value = []
}

async function salvarModelos() {
  try {
    // Validar tamanho dos arquivos antes de fazer upload (limite: 50MB)
    const TAMANHO_MAXIMO = 50 * 1024 * 1024 // 50MB em bytes
    const arquivosGrandes = []

    for (let modelo of modelosTemporarios.value) {
      if (modelo.arquivo && modelo.arquivo.file && !modelo.file_id) {
        if (modelo.arquivo.file.size > TAMANHO_MAXIMO) {
          const tamanhoMB = (modelo.arquivo.file.size / (1024 * 1024)).toFixed(
            2
          )
          arquivosGrandes.push(
            `"${modelo.arquivo.nome || modelo.arquivo.name}" (${tamanhoMB} MB)`
          )
        }
      }
    }

    if (arquivosGrandes.length > 0) {
      toastError(
        `Arquivo muito grande! Máximo 50 MB. Arquivos: ${arquivosGrandes.join(", ")}`
      )
      return
    }

    // Upload dos novos arquivos
    for (let modelo of modelosTemporarios.value) {
      if (modelo.arquivo && modelo.arquivo.file && !modelo.file_id) {
        const response = await uploadFile(
          modelo.arquivo.file,
          "intranet", // application sempre "intranet"
          "solicitacoes/modelos", // folder especifica o caminho
          modelo.arquivo.nome || modelo.arquivo.name,
          { user_id: 1 } // Usando ID numérico em vez de string
        )
        if (response && response.data) {
          // Tentar diferentes estruturas de resposta
          if (response.data.file && response.data.file.id) {
            modelo.file_id = response.data.file.id
          } else if (response.data.id) {
            modelo.file_id = response.data.id
          }
        } else {
          toastError(
            `Não foi possível enviar o arquivo "${modelo.arquivo.nome || modelo.arquivo.name || "desconhecido"}". Verifique o tamanho e tente novamente.`
          )
          return
        }
      }
    }

    // Filtrar apenas modelos que tiveram upload com sucesso
    const modelosComUpload = modelosTemporarios.value.filter((m) => m.file_id)

    // Criar estrutura para enviar ao backend
    const modelosParaSalvar = [
      // Manter modelos existentes
      ...(assuntoParaModelos.value.modelos || []).map((m) => ({
        file_id: m.file_id || m.arquivo?.id,
        nome: m.nome || m.arquivo?.original_name
      })),
      // Adicionar novos modelos
      ...modelosComUpload.map((modelo) => ({
        file_id: modelo.file_id,
        nome: modelo.nome || modelo.arquivo.nome || modelo.arquivo.name
      }))
    ]

    // Enviar para o backend
    const response = await axios.post(
      "/solicitacoes/configuracoes/salvar-modelos",
      {
        assunto_id: assuntoParaModelos.value.id,
        modelos: modelosParaSalvar
      }
    )

    if (response.data.success) {
      // Atualizar o assunto com os dados retornados do backend
      assuntoParaModelos.value.modelos = response.data.assunto.modelos

      // Atualizar também na lista de assuntos
      const assuntoIndex = assuntos.value.findIndex(
        (a) => a.id === assuntoParaModelos.value.id
      )
      if (assuntoIndex !== -1) {
        assuntos.value[assuntoIndex].modelos = response.data.assunto.modelos
      }

      // Limpar lista temporária
      modelosTemporarios.value = []

      dialogModelos.value = false
      toastSuccess("Modelos salvos com sucesso!")
    } else {
      throw new Error(response.data.message || "Erro ao salvar modelos")
    }
  } catch (error) {
    console.error("Erro ao salvar modelos:", error)
    toastError(
      "Erro ao salvar modelos: " +
        (error.response?.data?.message || error.message)
    )
  }
}

function removerModelo(index) {
  confirm.require({
    message: "Tem certeza que deseja remover este modelo?",
    header: "Confirmação",
    icon: "pi pi-exclamation-triangle",
    rejectClass: "p-button-secondary p-button-outlined",
    rejectLabel: "Cancelar",
    acceptLabel: "Remover",
    accept: () => {
      assuntoParaModelos.value.modelos.splice(index, 1)
    }
  })
}

function visualizarModelosAssunto(assunto) {
  if (assunto.modelos && assunto.modelos.length > 0) {
    modelosParaVisualizar.value = assunto.modelos.map((m) => m.arquivo.id)
    visualizarModelos.value = true
  } else {
    toastInfo("Este assunto não possui modelos cadastrados.")
  }
}

function showDialogLiberacao(assunto) {
  // Verificar se o assunto já foi salvo (tem ID)
  if (!assunto.id) {
    console.error("Assunto sem ID:", assunto)
    toastError("É necessário salvar o assunto antes de configurar liberações")
    return
  }

  assuntoParaLiberacao.value = assunto
  dialogLiberacao.value = true
}

function abrirDialogLiberacao() {
  // Esta função pode ser expandida no futuro para liberações por departamento
  toastInfo(
    "Configure as liberações individualmente para cada assunto usando o botão 'Liberação' de cada assunto."
  )
}

// ========== FUNÇÕES PARA RESPONSÁVEIS DO ASSUNTO ==========

function showDialogResponsaveisAssunto(assunto) {
  // Verificar se o assunto já foi salvo (tem ID)
  if (!assunto.id) {
    console.error("Assunto sem ID:", assunto)
    toastError("É necessário salvar o assunto antes de configurar responsáveis")
    return
  }

  assuntoParaResponsaveis.value = assunto
  dialogResponsaveisAssunto.value = true
}

// ========== FUNÇÕES PARA ETAPAS DE ANDAMENTO ==========

function showDialogEtapas(assunto) {
  // Verificar se o assunto já foi salvo (tem ID)
  if (!assunto.id) {
    console.error("Assunto sem ID:", assunto)
    toastError("É necessário salvar o assunto antes de configurar etapas")
    return
  }

  assuntoParaEtapas.value = assunto
  dialogEtapas.value = true
}

// ========== FUNÇÕES PARA FLUXO/WORKFLOW ==========

function showDialogFluxo(assunto) {
  if (!assunto.id) {
    toastError("É necessário salvar o assunto antes de configurar o fluxo")
    return
  }
  assuntoParaFluxo.value = assunto
  dialogFluxo.value = true
}

// ========== FUNÇÕES PARA RESPONSÁVEIS ADICIONAIS ==========

function abrirDialogResponsaveisAdicionais() {
  if (!departamentoAssunto.value) {
    toastError("Selecione um departamento primeiro")
    return
  }

  departamentoResponsaveis.value = departamentoAssunto.value
  buscarResponsaveisAdicionais()
  dialogResponsaveisAdicionais.value = true
}

async function buscarResponsaveisAdicionais() {
  if (!departamentoResponsaveis.value) return

  try {
    loadingResponsaveis.value = true
    const { data } = await axios.get(
      `/solicitacoes/configuracoes/responsaveis-adicionais/${departamentoResponsaveis.value}`
    )
    responsaveisAdicionais.value = data
  } catch (error) {
    console.error("Erro ao buscar responsáveis adicionais:", error)
    toastError("Erro ao carregar responsáveis adicionais")
  } finally {
    loadingResponsaveis.value = false
  }
}

async function adicionarResponsavelAdicional() {
  if (!novoResponsavelAdicional.value?.matricula) {
    toastError("Selecione um funcionário")
    return
  }

  try {
    loadingResponsaveis.value = true
    const { data } = await axios.post(
      "/solicitacoes/configuracoes/adicionar-responsavel-adicional",
      {
        departamento: departamentoResponsaveis.value,
        matricula: novoResponsavelAdicional.value.matricula
      }
    )

    toastSuccess(data.message)
    novoResponsavelAdicional.value = null
    await buscarResponsaveisAdicionais()
  } catch (error) {
    console.error("Erro ao adicionar responsável:", error)
    toastError(
      error.response?.data?.error || "Erro ao adicionar responsável adicional"
    )
  } finally {
    loadingResponsaveis.value = false
  }
}

function removerResponsavelAdicional(responsavel) {
  confirm.require({
    message: `Tem certeza que deseja remover ${responsavel.nome} como responsável adicional?`,
    header: "Confirmação",
    icon: "pi pi-exclamation-triangle",
    rejectClass: "p-button-secondary p-button-outlined",
    rejectLabel: "Cancelar",
    acceptLabel: "Remover",
    accept: async () => {
      try {
        loadingResponsaveis.value = true
        const { data } = await axios.delete(
          "/solicitacoes/configuracoes/remover-responsavel-adicional",
          {
            data: { matricula: responsavel.matricula }
          }
        )

        toastSuccess(data.message)
        await buscarResponsaveisAdicionais()
      } catch (error) {
        console.error("Erro ao remover responsável:", error)
        toastError(
          error.response?.data?.error || "Erro ao remover responsável adicional"
        )
      } finally {
        loadingResponsaveis.value = false
      }
    }
  })
}

async function getDepartamentos() {
  try {
    const { data } = await axios.get(
      "/solicitacoes/configuracoes/departamentos"
    )
    if (data && data.ativos && data.inativos) {
      departamentos.value = [data.inativos, data.ativos]
    } else {
      toastError("Formato de dados inválido")
    }
  } catch (err) {
    toastError("Erro ao carregar departamentos")
  }
}

// Função para ordenar as listas de departamentos
function ordenarDepartamentos() {
  departamentos.value[0].sort((a, b) => a.label.localeCompare(b.label)) // Ordena a lista 'source'
  departamentos.value[1].sort((a, b) => a.label.localeCompare(b.label)) // Ordena a lista 'target'
}

// Watcher para garantir que as listas sejam ordenadas após alterações
watch(
  () => departamentos.value[0], // Observando a lista 'source'
  () => ordenarDepartamentos(),
  { deep: true } // Isso permite monitorar alterações internas na lista
)

watch(
  () => departamentos.value[1], // Observando a lista 'target'
  () => ordenarDepartamentos(),
  { deep: true } // Isso permite monitorar alterações internas na lista
)

watch(
  () => departamentoAssunto.value,
  () => buscarAssuntos()
)

async function buscarAssuntos() {
  assuntos.value = []
  loadingAssuntos.value = true
  await axios
    .post("/solicitacoes/configuracoes/assuntos", {
      departamento: departamentoAssunto.value,
      incluir_inativos: true // Incluir assuntos inativos na configuração
    })
    .then(async (res) => {
      responsaveis.value = res.data.responsaveis
      assuntos.value = res.data.assuntos
      prazoResolucao.value = res.data.prazoResolucao
      camposPadrao.value = res.data.campos

      for (const item of assuntos.value) {
        item.selects = item.selects || []

        item.selects.forEach((s) => {
          s.editando = s.id == null
          s.errors = s.errors || { erroLabel: "", erroOptions: "" }
          s.valorDigitado = ""
          // Converter 'S'/'N' para booleanos
          s.exibir_nova = s.exibir_nova === "S" || s.exibir_nova === true
          s.exibir_atendimento =
            s.exibir_atendimento === "S" || s.exibir_atendimento === true
          s.obrigatorio = s.obrigatorio === "S" || s.obrigatorio === true
          s.multiplo = s.multiplo === "S" || s.multiplo === true
          // #12173 - Converter campo_pai_id para número (vem como string do banco)
          s.campo_pai_id = s.campo_pai_id ? Number(s.campo_pai_id) : null
        })
      }
    })
    .catch((err) => {
      console.error(err)
      toastError("Erro ao buscar assuntos")
    })
    .finally(() => {
      loadingAssuntos.value = false
    })
}

async function salvarDep(feedback = false) {
  salvandoDep.value = true

  await axios
    .post("/solicitacoes/configuracoes/departamentos", departamentos.value)
    .then((res) => {
      if (feedback) toastSuccess("Departamentos salvos com sucesso!")
    })
    .catch((err) => {
      toastError("Erro ao salvar departamentos")
    })

  salvandoDep.value = false
}

function adicionarAssunto() {
  // Adiciona um novo assunto vazio
  assuntos.value.push({
    assunto: null,
    responsavel: null,
    campos: JSON.parse(JSON.stringify(camposPadrao.value)),
    prioridade: null
  })
}

function removerAssunto(index) {
  confirm.require({
    message: "Tem certeza que deseja remover este assunto?",
    header: "Confirmação",
    icon: "pi pi-exclamation-triangle",
    rejectClass: "p-button-secondary p-button-outlined",
    rejectLabel: "Cancelar",
    acceptLabel: "Remover",
    accept: () => {
      assuntos.value.splice(index, 1)
    }
  })
}

async function salvarAssuntos() {
  // Verifica se todos os assuntos possuem descrição
  for (const assunto of assuntos.value) {
    if (!assunto.assunto) {
      toastError("Todos os assuntos devem ter uma descrição")
      return
    }
    assunto.id = assunto.id || null
    assunto.prioridade == assunto.prioridade || null

    for (const campo of assunto.campos) {
      campo.id = campo.id || null
      campo.observacao = campo.observacao || null
    }
  }

  await salvarDep()

  await axios
    .post("/solicitacoes/configuracoes/salvar-assuntos", {
      assuntos: assuntos.value,
      departamento: departamentoAssunto.value,
      prazoResolucao: prazoResolucao.value
    })
    .then(async (res) => {
      buscarAssuntos()
      toastSuccess("Assuntos salvos com sucesso!")
    })
    .catch((err) => {
      toastError("Erro ao salvar assuntos")
    })
}
async function getCanaisNotif() {
  await axios.get("configuracoes/canais-notif").then((res) => {
    canaisNotif.value = res.data
  })
}

async function salvarNotificacoes(canais) {
  var params = {
    canais: canais
  }

  await axios
    .post("configuracoes/salvar-notif", params)
    .then(async (res) => {
      // msg.value = res.data;
      sucessoNotif.value = true
      await new Promise((resolve) => setTimeout(resolve, 5000))
      sucessoNotif.value = false
    })
    .catch((e) => {
      console.error(e)
    })
}

async function getEquipamentos() {
  loading.value = true
  axios
    .get("/solicitacoes/configuracoes/buscar-equipamentos")
    .then(async (res) => {
      equipamentos.value = await res.data
    })
    .catch((err) => {
      console.error(err)
    })
  loading.value = false
}

async function adicionarEquipamento() {
  equipamentos.value.push({
    equipamento: ""
  })
}

async function saveEquipamentos() {
  loading.value = true
  var params = {
    equipamentos: equipamentos.value
  }

  await axios
    .post("/solicitacoes/configuracoes/salvar-equipamento", params)
    .then(async (res) => {
      toastSuccess("Equipamentos salvos com sucesso!")
      await getEquipamentos()
    })
    .catch((err) => {
      toastError("Erro ao salvar equipamentos")
      console.error(err)
    })
  loading.value = false
}

async function removeEquipamento(idEquipamento) {
  confirm.require({
    message: "Tem certeza que deseja remover este equipamento?",
    header: "Confirmar remoção",
    icon: "pi pi-exclamation-triangle",
    rejectClass: "p-button-secondary p-button-outlined p-button-sm",
    acceptClass: "p-button-danger p-button-sm",
    rejectLabel: "Cancelar",
    acceptLabel: "Remover",
    accept: async () => {
      if (!idEquipamento) {
        equipamentos.value.pop()
        return
      }

      loading.value = true
      var params = {
        idEquipamento: idEquipamento
      }

      await axios
        .post("/solicitacoes/configuracoes/remover-equipamento", params)
        .then(async (res) => {
          toastSuccess("Equipamento removido com sucesso!")
          await getEquipamentos()
        })
        .catch((err) => {
          toastError("Erro ao remover equipamento")
        })
      loading.value = false
    }
  })
}

// ========== FUNÇÕES PARA CAMPOS DE EXPORTAÇÃO ==========

function carregarCamposExportacao() {
  const camposSalvos = localStorage.getItem("solicitacoes.camposExportacao")
  if (camposSalvos) {
    try {
      camposExportacao.value = JSON.parse(camposSalvos)
    } catch (error) {
      console.error("Erro ao carregar campos:", error)
    }
  }
}

function salvarCamposExportacao() {
  localStorage.setItem(
    "solicitacoes.camposExportacao",
    JSON.stringify(camposExportacao.value)
  )
  toastSuccess("Campos de exportação salvos com sucesso!")
}

function resetarCamposExportacao() {
  confirm.require({
    message: "Resetar campos para o padrão?",
    header: "Confirmar",
    icon: "pi pi-exclamation-triangle",
    rejectClass: "p-button-secondary p-button-outlined",
    rejectLabel: "Cancelar",
    acceptLabel: "Resetar",
    accept: () => {
      camposExportacao.value = [
        { coluna: "id", label: "ID" },
        { coluna: "titulo", label: "Título" },
        { coluna: "prioridade", label: "Prioridade" },
        { coluna: "filial_id", label: "Filial" },
        { coluna: "assunto_id", label: "Assunto" },
        { coluna: "usuario_responsavel", label: "Responsável" },
        { coluna: "usuario_solicitante", label: "Solicitante" },
        { coluna: "created_at", label: "Data Criação" }
      ]
      salvarCamposExportacao()
    }
  })
}

function showDialogSelecao(assunto) {
  assuntoSelecionado.value = assunto

  // Garante que os selects do assunto estejam prontos para edição
  if (!assunto.selects) {
    assunto.selects = []
  }

  assunto.selects.forEach((s) => {
    s.editando = s.id == null
    s.errors = s.errors || { erroLabel: "", erroOptions: "" }
    s.valorDigitado = ""
    // Guardar tipo original para verificar alterações
    s._tipoOriginal = s.tipo
    // Converter 'S'/'N' para booleanos (caso não tenha sido convertido ainda)
    s.exibir_nova = s.exibir_nova === "S" || s.exibir_nova === true
    s.exibir_atendimento =
      s.exibir_atendimento === "S" || s.exibir_atendimento === true
    s.obrigatorio = s.obrigatorio === "S" || s.obrigatorio === true
    s.multiplo = s.multiplo === "S" || s.multiplo === true
  })

  dialogSelecao.value = true

  // Garante que todos os selects tenham a ordem correta baseada na posição
  atualizarOrdem()
}

function adicionarSelecao() {
  assuntoSelecionado.value.selects.unshift(novoItemSelect())
  // Atualiza ordem após adicionar novo item
  atualizarOrdem()
}

function adicionarChip(item) {
  const valor = item.valorDigitado?.trim()
  if (valor && !item.valores.includes(valor)) {
    item.valores.push(valor)
  }
  item.valorDigitado = ""
}

function removerSelect(index) {
  assuntoSelecionado.value.selects.splice(index, 1)
  // Atualiza ordem após remover item
  atualizarOrdem()
}

/**
 * Verifica se o campo tem respostas antes de permitir alteração de tipo
 * @param {Object} campo - O campo sendo editado
 * @param {String} novoTipo - O novo tipo selecionado
 * @param {String} tipoAntigo - O tipo anterior do campo
 */
async function verificarAlteracaoTipo(campo, novoTipo, tipoAntigo) {
  // Se é um campo novo (sem id), não precisa verificar
  if (!campo.id) {
    return
  }

  // Se o tipo não mudou, não precisa verificar
  if (novoTipo === tipoAntigo) {
    return
  }

  try {
    const response = await axios.get(
      `/solicitacoes/configuracoes/verificar-respostas-campo/${campo.id}`
    )

    if (response.data.existe_respostas) {
      // Guardar dados para o dialog
      campoAlteracaoTipo.value = campo
      tipoAntigoAlteracao.value = tipoAntigo
      tipoNovoAlteracao.value = novoTipo
      qtdRespostasAlteracao.value = response.data.qtd_respostas

      // Mostrar dialog de confirmação
      dialogAlteracaoTipo.value = true
    }
  } catch (error) {
    console.error("Erro ao verificar respostas do campo:", error)
  }
}

/**
 * Confirma a alteração de tipo - o campo antigo será desativado e um novo será criado
 */
function confirmarAlteracaoTipo() {
  dialogAlteracaoTipo.value = false
}

/**
 * Cancela a alteração de tipo - volta ao tipo anterior
 */
function cancelarAlteracaoTipo() {
  if (campoAlteracaoTipo.value && tipoAntigoAlteracao.value) {
    campoAlteracaoTipo.value.tipo = tipoAntigoAlteracao.value
  }
  dialogAlteracaoTipo.value = false
}

async function criarSelects() {
  try {
    loadingButton.value = true
    const isValid = await validaForm()
    if (!isValid) {
      loadingButton.value = false
      return
    }

    assuntoSelecionado.value.selects = JSON.parse(
      JSON.stringify(assuntoSelecionado.value.selects)
    )

    loadingButton.value = false
    dialogSelecao.value = false
    await salvarAssuntos()
  } catch (error) {
    console.error(error)
    loadingButton.value = false
  }
}

async function validaForm() {
  let isValid = true

  // Tipos que não precisam de opções manuais
  const tiposSemOpcoes = [
    "data",
    "texto",
    "arquivo",
    "numero",
    "filial_winthor",
    "funcao",
    "regional",
    "depto_compras",
    "cnpj",
    "colaborador",
    "depto_funcionario"
  ]

  for (const item of assuntoSelecionado.value.selects) {
    // Garante que 'errors' exista
    if (!item.errors) {
      item.errors = {
        erroLabel: "",
        erroOptions: ""
      }
    }

    // Limpa os erros anteriores
    item.errors.erroLabel = ""
    item.errors.erroOptions = ""

    if (!item.label || item.label.trim().length < 2) {
      item.errors.erroLabel = "Insira um título válido"
      isValid = false
    }

    // Tipos que não precisam de opções manuais (buscam do backend ou são campos de entrada)
    const tiposSemOpcoes = [
      "data",
      "texto",
      "arquivo",
      "numero",
      "cnpj",
      "colaborador",
      "depto_compras",
      "depto_funcionario",
      "filial_winthor",
      "funcao",
      "regional"
    ]

    if (
      !tiposSemOpcoes.includes(item.tipo) &&
      (!item.valores || item.valores.length < 2)
    ) {
      item.errors.erroOptions = "Crie ao menos 2 opções para a seleção"
      isValid = false
    }
  }

  return isValid
}

function novoItemSelect() {
  return {
    id: null,
    label: "",
    ativo: true,
    obrigatorio: false,
    observacao: "",
    valores: [],
    valorDigitado: "",
    tipo: "selecao", //selecao, data, texto, cnpj, depto_compras, depto_funcionario, filial_winthor, funcao, regional
    tipo_data: "range",
    dias_minimos: null,
    multiplo: false,
    exibir_nova: true,
    exibir_atendimento: false,
    ordem: 0,
    // #12173 - Campos condicionais
    campo_pai_id: null,
    valor_condicional: null,
    errors: {
      erroLabel: "",
      erroOptions: ""
    },
    editando: true
  }
}

// #12173 - Computed para obter campos disponíveis como campo pai
function getCamposPaiDisponiveis(currentIndex) {
  if (!assuntoSelecionado.value?.selects) return []

  // Retorna apenas campos do tipo 'selecao' que não sejam o campo atual
  // e que tenham valores definidos (opções)
  return assuntoSelecionado.value.selects
    .filter((select, idx) => {
      // Não pode ser o próprio campo
      if (idx === currentIndex) return false
      // Apenas campos do tipo seleção com opções
      if (select.tipo !== "selecao") return false
      // Deve ter pelo menos uma opção
      if (!select.valores || select.valores.length === 0) return false
      return true
    })
    .map((select) => ({
      id: select.id
        ? Number(select.id)
        : `temp_${assuntoSelecionado.value.selects.indexOf(select)}`,
      label: select.label || "(Sem título)",
      valores: select.valores
    }))
}

// #12173 - Obter as opções do campo pai selecionado
function getOpcoesCampoPai(campoPaiId) {
  if (!campoPaiId || !assuntoSelecionado.value?.selects) return []

  const campoPai = assuntoSelecionado.value.selects.find(
    (s) =>
      Number(s.id) === Number(campoPaiId) ||
      `temp_${assuntoSelecionado.value.selects.indexOf(s)}` === campoPaiId
  )

  return campoPai?.valores || []
}

function atualizarOrdem() {
  // Atualiza a ordem de cada item baseado na posição no array
  assuntoSelecionado.value.selects.forEach((item, index) => {
    item.ordem = index
  })
}

function moverCampo(index, direcao) {
  const selects = assuntoSelecionado.value.selects
  const novoIndex = index + direcao

  // Verifica limites
  if (novoIndex < 0 || novoIndex >= selects.length) return

  // Troca posições
  const temp = selects[index]
  selects[index] = selects[novoIndex]
  selects[novoIndex] = temp

  // Atualiza ordem
  atualizarOrdem()
}

// Mover campo para posição específica por número
function moverParaPosicao(indexAtual, novaPosicao) {
  const selects = assuntoSelecionado.value.selects

  // Converte para número e ajusta para índice (0-based)
  let novoIndex = parseInt(novaPosicao) - 1

  // Valida limites
  if (isNaN(novoIndex) || novoIndex < 0) novoIndex = 0
  if (novoIndex >= selects.length) novoIndex = selects.length - 1

  // Se é a mesma posição, não faz nada
  if (novoIndex === indexAtual) return

  // Remove o item da posição atual
  const [item] = selects.splice(indexAtual, 1)

  // Insere na nova posição
  selects.splice(novoIndex, 0, item)

  // Atualiza ordem
  atualizarOrdem()
}

function toggleEdicao(item) {
  item.editando = !item.editando
}
</script>

<template>
  <Head title="Config Solicitações" />

  <AuthenticatedLayout>
    <!-- Loader personalizado -->
    <Loader :loading="loading"></Loader>

    <!-- Breadcrumb Página -->
    <div
      class="w-full flex flex-wrap items-center bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-xl mb-4 sm:mb-6 border border-gray-200 dark:border-slate-700"
    >
      <div
        class="flex flex-wrap items-center gap-1 sm:gap-2 text-sm sm:text-base text-gray-600 dark:text-gray-300 font-medium w-full"
      >
        <div class="flex items-center gap-1 sm:gap-2">
          <i class="pi pi-home"></i>
          <span>Home</span>
        </div>
        <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
        <span>Solicitações</span>
        <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
        <span
          class="text-gray-950 dark:text-white font-bold truncate max-w-[120px] sm:max-w-none"
        >
          {{
            aba === "departamentos"
              ? "Departamentos"
              : aba === "assuntos"
                ? "Assuntos"
                : aba === "equipamentos"
                  ? "Equipamentos"
                  : aba === "notificacoes"
                    ? "Notificações"
                    : aba === "filiais"
                      ? "Filiais"
                      : aba === "importacao"
                        ? "Importação"
                        : "Configurações"
          }}
        </span>
      </div>
    </div>
    <!-- Fim Breadcrumb Página -->

    <!-- Cabeçalho da Página Departamentos -->
    <div
      v-if="aba === 'departamentos'"
      class="space-y-2 mb-2 mt-4"
    >
      <div class="flex items-center gap-3">
        <h2
          class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
        >
          <div
            class="w-1 h-8 bg-gradient-to-b from-blue-500 to-blue-700 rounded-full"
          ></div>
          Departamentos
        </h2>
      </div>
      <span class="text-xs text-gray-500 dark:text-gray-400 font-bold pl-2">
        Gerencie os departamentos habilitados para solicitações
      </span>
    </div>

    <!-- Cabeçalho da Página Assuntos -->
    <div
      v-if="aba === 'assuntos'"
      class="space-y-2 mb-2 mt-4"
    >
      <div class="flex items-center gap-3">
        <h2
          class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
        >
          <div
            class="w-1 h-8 bg-gradient-to-b from-blue-500 to-blue-700 rounded-full"
          ></div>
          Assuntos
        </h2>
      </div>
      <span class="text-xs text-gray-500 dark:text-gray-400 font-bold pl-2">
        Configure os assuntos disponíveis para cada departamento
      </span>
    </div>

    <!-- Cabeçalho da Página Equipamentos -->
    <div
      v-if="aba === 'equipamentos'"
      class="space-y-2 mb-2 mt-4"
    >
      <div class="flex items-center gap-3">
        <h2
          class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
        >
          <div
            class="w-1 h-8 bg-gradient-to-b from-blue-500 to-blue-700 rounded-full"
          ></div>
          Equipamentos
        </h2>
      </div>
      <span class="text-xs text-gray-500 dark:text-gray-400 font-bold pl-2">
        Gerencie os equipamentos disponíveis para solicitações
      </span>
    </div>

    <!-- Cabeçalho da Página Notificações -->
    <div
      v-if="aba === 'notificacoes'"
      class="space-y-2 mb-2 mt-4"
    >
      <div class="flex items-center gap-3">
        <h2
          class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
        >
          <div
            class="w-1 h-8 bg-gradient-to-b from-blue-500 to-blue-700 rounded-full"
          ></div>
          Notificações
        </h2>
      </div>
      <span class="text-xs text-gray-500 dark:text-gray-400 font-bold pl-2">
        Configure as notificações do sistema de solicitações
      </span>
    </div>

    <!-- Cabeçalho da Página Filiais -->
    <div
      v-if="aba === 'filiais'"
      class="space-y-2 mb-2 mt-4"
    >
      <div class="flex items-center gap-3">
        <h2
          class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
        >
          <div
            class="w-1 h-8 bg-gradient-to-b from-blue-500 to-blue-700 rounded-full"
          ></div>
          Filiais
        </h2>
      </div>
      <span class="text-xs text-gray-500 dark:text-gray-400 font-bold pl-2">
        Configure quais filiais permitem visibilidade de chamados entre colegas
      </span>
    </div>

    <!-- Menu de Abas -->
    <div
      class="w-full mb-4 mt-8 pb-2 border-b border-gray-200 dark:border-slate-700"
    >
      <div
        class="flex flex-col lg:flex-row items-stretch lg:items-stretch justify-start gap-2"
      >
        <!-- Aba Departamentos -->
        <div
          class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg cursor-pointer select-none flex-1 lg:flex-none lg:min-w-[140px] text-sm"
          :class="
            aba === 'departamentos'
              ? 'bg-blue-50 dark:bg-blue-900/30 border text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800 shadow-sm'
              : 'border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white'
          "
          @click="aba = 'departamentos'"
        >
          <i class="pi pi-building !text-lg" />
          <span class="font-medium tracking-wide">Departamentos</span>
        </div>

        <!-- Divisor -->
        <div
          class="hidden lg:block border-l border-gray-200 dark:border-slate-600 h-8 bg-gradient-to-b from-gray-200 via-blue-300/40 to-gray-200 dark:from-slate-600 dark:via-blue-500/40 dark:to-slate-600 rounded-full self-center mx-1"
        ></div>

        <!-- Aba Assuntos -->
        <div
          class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg cursor-pointer select-none flex-1 lg:flex-none lg:min-w-[140px] text-sm"
          :class="
            aba === 'assuntos'
              ? 'bg-blue-50 dark:bg-blue-900/30 border text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800 shadow-sm'
              : 'border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white'
          "
          @click="aba = 'assuntos'"
        >
          <i class="pi pi-list !text-lg" />
          <span class="font-medium tracking-wide">Assuntos</span>
        </div>

        <!-- Divisor -->
        <div
          class="hidden lg:block border-l border-gray-200 dark:border-slate-600 h-8 bg-gradient-to-b from-gray-200 via-blue-300/40 to-gray-200 dark:from-slate-600 dark:via-blue-500/40 dark:to-slate-600 rounded-full self-center mx-1"
        ></div>

        <!-- Aba Equipamentos -->
        <div
          class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg cursor-pointer select-none flex-1 lg:flex-none lg:min-w-[140px] text-sm"
          :class="
            aba === 'equipamentos'
              ? 'bg-blue-50 dark:bg-blue-900/30 border text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800 shadow-sm'
              : 'border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white'
          "
          @click="aba = 'equipamentos'"
        >
          <i class="pi pi-desktop !text-lg" />
          <span class="font-medium tracking-wide">Equipamentos</span>
        </div>

        <!-- Divisor -->
        <div
          class="hidden lg:block border-l border-gray-200 dark:border-slate-600 h-8 bg-gradient-to-b from-gray-200 via-blue-300/40 to-gray-200 dark:from-slate-600 dark:via-blue-500/40 dark:to-slate-600 rounded-full self-center mx-1"
        ></div>

        <!-- Aba Notificações -->
        <div
          class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg cursor-pointer select-none flex-1 lg:flex-none lg:min-w-[140px] text-sm"
          :class="
            aba === 'notificacoes'
              ? 'bg-blue-50 dark:bg-blue-900/30 border text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800 shadow-sm'
              : 'border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white'
          "
          @click="aba = 'notificacoes'"
        >
          <i class="pi pi-bell !text-lg" />
          <span class="font-medium tracking-wide">Notificações</span>
        </div>

        <!-- Divisor -->
        <div
          class="hidden lg:block border-l border-gray-200 dark:border-slate-600 h-8 bg-gradient-to-b from-gray-200 via-blue-300/40 to-gray-200 dark:from-slate-600 dark:via-blue-500/40 dark:to-slate-600 rounded-full self-center mx-1"
        ></div>

        <!-- Aba Filiais -->
        <div
          class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg cursor-pointer select-none flex-1 lg:flex-none lg:min-w-[140px] text-sm"
          :class="
            aba === 'filiais'
              ? 'bg-blue-50 dark:bg-blue-900/30 border text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800 shadow-sm'
              : 'border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white'
          "
          @click="aba = 'filiais'"
        >
          <i class="pi pi-warehouse !text-lg" />
          <span class="font-medium tracking-wide">Filiais</span>
        </div>
      </div>
    </div>
    <!-- Fim Menu de Abas -->

    <!-- Conteúdo das Abas -->
    <div class="w-full">
      <!-- Aba Departamentos -->
      <div
        v-if="aba == 'departamentos'"
        class="w-full"
      >
        <div
          class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden"
        >
          <!-- Header do Card -->
          <div
            class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 px-6 py-4 border-b border-gray-100 dark:border-slate-700"
          >
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 bg-blue-100 dark:bg-blue-900/50 shrink-0 rounded-xl flex items-center justify-center"
              >
                <i
                  class="pi pi-arrows-h text-blue-600 dark:text-blue-400 text-lg shrink-0"
                ></i>
              </div>
              <div>
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">
                  Selecionar Departamentos
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Mova os departamentos entre as listas para habilitar ou
                  desabilitar
                </p>
              </div>
            </div>
          </div>

          <!-- Conteúdo - PickList Customizado -->
          <div class="p-4 md:p-6">
            <div
              class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 items-start"
            >
              <!-- Card: Departamentos Desabilitados -->
              <div
                class="bg-white dark:bg-slate-900 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 border-l-4 border-l-red-400 dark:border-l-red-500 border-y border-r border-gray-200 dark:border-slate-700"
              >
                <div class="p-4 border-b border-gray-200 dark:border-slate-700">
                  <h4
                    class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-3 flex items-center"
                  >
                    <i class="pi pi-times-circle mr-2 text-red-500"></i>
                    Departamentos Desabilitados
                    <span
                      class="ml-auto px-2 py-0.5 bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-400 text-xs rounded-full font-medium"
                    >
                      {{ departamentos[0]?.length || 0 }}
                    </span>
                  </h4>
                  <div class="relative">
                    <i
                      class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                    ></i>
                    <input
                      v-model="filtroDepDesabilitado"
                      type="text"
                      placeholder="Buscar departamentos..."
                      class="w-full pl-10 pr-4 py-2.5 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all"
                    />
                  </div>
                </div>

                <div class="overflow-y-auto h-80 p-2">
                  <div
                    v-for="(dep, index) in depsDesabilitadosFiltrados"
                    :key="dep.value"
                    @click="selecionarDepDesabilitado(dep)"
                    class="p-4 mb-1 rounded-lg cursor-pointer transition-all duration-200 border border-transparent"
                    :class="{
                      'bg-red-50 dark:bg-red-900/30 border-red-400 dark:border-red-600 shadow-sm':
                        depDesabilitadoSelecionado?.value === dep.value,
                      'bg-gray-50 dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 hover:border-red-300 dark:hover:border-red-700 hover:shadow-sm':
                        depDesabilitadoSelecionado?.value !== dep.value
                    }"
                  >
                    <div class="flex items-center">
                      <span
                        class="font-semibold text-red-600 dark:text-red-400 mr-3 min-w-[28px] text-center"
                      >
                        {{ index + 1 }}
                      </span>
                      <span
                        class="text-gray-700 dark:text-gray-200 text-sm font-medium uppercase"
                      >
                        {{ dep.label }}
                      </span>
                    </div>
                  </div>

                  <div
                    v-if="depsDesabilitadosFiltrados.length === 0"
                    class="text-center py-12 text-gray-400 dark:text-gray-500"
                  >
                    <i class="pi pi-inbox text-4xl mb-3 block"></i>
                    <p class="font-medium">Nenhum departamento encontrado</p>
                  </div>
                </div>
              </div>

              <!-- Botões Centrais -->
              <div
                class="flex md:flex-col justify-center items-center gap-4 px-2 py-4 md:py-8"
              >
                <Button
                  :icon="isMobile ? 'pi pi-arrow-down' : 'pi pi-arrow-right'"
                  class="!w-14 !h-14 !rounded-full shadow-xl hover:scale-110 transition-transform duration-200"
                  severity="success"
                  @click="habilitarDepartamento"
                  :disabled="!depDesabilitadoSelecionado"
                  v-tooltip.top="'Habilitar departamento'"
                />
                <Button
                  :icon="isMobile ? 'pi pi-arrow-up' : 'pi pi-arrow-left'"
                  class="!w-14 !h-14 !rounded-full shadow-xl hover:scale-110 transition-transform duration-200"
                  severity="danger"
                  @click="desabilitarDepartamento"
                  :disabled="!depHabilitadoSelecionado"
                  v-tooltip.top="'Desabilitar departamento'"
                />
              </div>

              <!-- Card: Departamentos Habilitados -->
              <div
                class="bg-white dark:bg-slate-900 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 border-l-4 border-l-green-400 dark:border-l-green-500 border-y border-r border-gray-200 dark:border-slate-700"
              >
                <div class="p-4 border-b border-gray-200 dark:border-slate-700">
                  <h4
                    class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-3 flex items-center"
                  >
                    <i class="pi pi-check-circle mr-2 text-green-500"></i>
                    Departamentos Habilitados
                    <span
                      class="ml-auto px-2 py-0.5 bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400 text-xs rounded-full font-medium"
                    >
                      {{ departamentos[1]?.length || 0 }}
                    </span>
                  </h4>
                  <div class="relative">
                    <i
                      class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                    ></i>
                    <input
                      v-model="filtroDepHabilitado"
                      type="text"
                      placeholder="Buscar departamentos..."
                      class="w-full pl-10 pr-4 py-2.5 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all"
                    />
                  </div>
                </div>

                <div class="overflow-y-auto h-80 p-2">
                  <div
                    v-for="(dep, index) in depsHabilitadosFiltrados"
                    :key="dep.value"
                    @click="selecionarDepHabilitado(dep)"
                    class="p-4 mb-1 rounded-lg cursor-pointer transition-all duration-200 border border-transparent"
                    :class="{
                      'bg-green-50 dark:bg-green-900/30 border-green-400 dark:border-green-600 shadow-sm':
                        depHabilitadoSelecionado?.value === dep.value,
                      'bg-gray-50 dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 hover:border-green-300 dark:hover:border-green-700 hover:shadow-sm':
                        depHabilitadoSelecionado?.value !== dep.value
                    }"
                  >
                    <div class="flex items-center">
                      <span
                        class="font-semibold text-green-600 dark:text-green-400 mr-3 min-w-[28px] text-center"
                      >
                        {{ index + 1 }}
                      </span>
                      <span
                        class="text-gray-700 dark:text-gray-200 text-sm font-medium uppercase"
                      >
                        {{ dep.label }}
                      </span>
                    </div>
                  </div>

                  <div
                    v-if="depsHabilitadosFiltrados.length === 0"
                    class="text-center py-12 text-gray-400 dark:text-gray-500"
                  >
                    <i class="pi pi-inbox text-4xl mb-3 block"></i>
                    <p class="font-medium">Nenhum departamento encontrado</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div
            class="px-6 py-4 bg-gray-50 dark:bg-slate-700 border-t border-gray-100 dark:border-slate-600 flex justify-end"
          >
            <Button
              @click="salvarDep(true)"
              label="Salvar Alterações"
              icon="pi pi-check"
              outlined
              severity="success"
              class="px-6 w-full sm:w-auto"
            />
          </div>
        </div>
      </div>

      <div
        v-if="aba == 'assuntos'"
        class="w-full"
      >
        <!-- Card de seleção de departamento -->
        <div
          class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden mb-6"
        >
          <div
            class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 px-6 py-4 border-b border-gray-100 dark:border-slate-700"
          >
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 bg-blue-100 dark:bg-blue-900/50 shrink-0 rounded-xl flex items-center justify-center"
              >
                <i
                  class="pi pi-filter text-blue-600 dark:text-blue-400 text-lg"
                ></i>
              </div>
              <div>
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">
                  Selecionar Departamento
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Escolha um departamento para configurar seus assuntos
                </p>
              </div>
            </div>
          </div>
          <div class="p-6 w-full">
            <div class="flex flex-wrap items-center gap-4">
              <Select
                v-model="departamentoAssunto"
                placeholder="Escolha um departamento"
                :options="departamentos[1]"
                option-label="label"
                option-value="value"
                class="w-full md:w-72"
              />
              <div
                v-if="departamentoAssunto"
                class="relative flex items-center gap-2"
              >
                <div class="flex flex-col 2xl:-mt-5">
                  <label class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                    Prazo de resolução (dias)
                  </label>
                  <input
                    v-model="prazoResolucao"
                    type="number"
                    class="w-full border border-gray-200 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Ex: 30"
                  />
                </div>
                <div
                  @click="
                    toastInfo(
                      'Prazo de resolução (em dias) para uma solicitação ser resolvida automaticamente.'
                    )
                  "
                  class="w-5 h-5 mt-5 2xl:mt-0 bg-blue-500 rounded-full flex items-center justify-center cursor-help"
                  v-tooltip.top="
                    'Prazo de resolução (em dias) para uma solicitação ser resolvida automaticamente.'
                  "
                >
                  <i class="pi pi-info text-white !text-xs"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Seção de configurações do departamento -->
        <div
          v-if="departamentoAssunto"
          class="mb-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden"
        >
          <div
            class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/30 dark:to-emerald-900/30 px-6 py-4 border-b border-gray-100 dark:border-slate-700"
          >
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 bg-green-100 dark:bg-green-900/50 rounded-xl flex items-center justify-center"
              >
                <i
                  class="pi pi-cog text-green-600 dark:text-green-400 text-lg"
                ></i>
              </div>
              <div>
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">
                  Configurações do Departamento
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Configurações que se aplicam a todo o departamento
                  <strong class="text-green-600 dark:text-green-400">
                    {{ departamentoAssunto }}
                  </strong>
                </p>
              </div>
            </div>
          </div>
          <div class="p-6">
            <div class="flex flex-wrap gap-3">
              <Button
                outlined
                severity="info"
                icon="pi pi-users"
                label="Responsáveis Adicionais"
                @click="abrirDialogResponsaveisAdicionais"
              />
            </div>
          </div>
        </div>

        <div v-if="departamentoAssunto">
          <!-- Loading de assuntos -->
          <div
            v-if="loadingAssuntos"
            class="flex flex-col items-center justify-center py-16 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl shadow-sm"
          >
            <i class="pi pi-spin pi-spinner text-4xl text-blue-500 mb-4"></i>
            <p class="text-gray-600 dark:text-gray-400 font-medium">
              Carregando assuntos...
            </p>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
              Aguarde um momento
            </p>
          </div>

          <div
            v-else-if="assuntos.length"
            class="space-y-6"
          >
            <div
              v-for="(assunto, index) in assuntos"
              :key="index"
              :class="[
                'p-6 border rounded-xl shadow-sm hover:shadow-md transition-all duration-200',
                assunto.ativo === 'N' || assunto.ativo === false
                  ? 'bg-gray-50 dark:bg-slate-900 border-gray-300 dark:border-slate-600 opacity-75'
                  : 'bg-white dark:bg-slate-800 border-gray-200 dark:border-slate-700'
              ]"
            >
              <!-- Cabeçalho do assunto -->
              <div class="flex items-start justify-between gap-3 mb-6">
                <div class="flex items-start gap-3 min-w-0 flex-1">
                  <div
                    class="w-8 h-8 bg-blue-100 dark:bg-blue-900/50 rounded-lg flex items-center justify-center shrink-0"
                  >
                    <span
                      class="text-blue-600 dark:text-blue-400 font-semibold text-sm"
                    >
                      {{ index + 1 }}
                    </span>
                  </div>
                  <div class="flex flex-col gap-1">
                    <h4
                      class="text-base sm:text-lg font-semibold text-gray-800 dark:text-gray-100 break-words leading-tight"
                    >
                      {{ assunto.assunto || "Novo Assunto" }}
                    </h4>
                    <!-- Badge de status ativo/inativo -->
                    <span
                      v-if="assunto.id"
                      :class="[
                        'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium w-fit',
                        assunto.ativo === 'S' || assunto.ativo === true
                          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400'
                          : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-400'
                      ]"
                    >
                      <i
                        :class="[
                          'text-[10px]',
                          assunto.ativo === 'S' || assunto.ativo === true
                            ? 'pi pi-check-circle'
                            : 'pi pi-ban'
                        ]"
                      ></i>
                      {{
                        assunto.ativo === "S" || assunto.ativo === true
                          ? "Ativo"
                          : "Inativo"
                      }}
                    </span>
                  </div>
                </div>
                <!-- Toggle Ativar/Inativar -->
                <div
                  v-if="assunto.id"
                  class="flex items-center gap-2"
                  v-tooltip.left="
                    assunto.ativo === 'S' || assunto.ativo === true
                      ? 'Clique para desativar'
                      : 'Clique para ativar'
                  "
                >
                  <ToggleSwitch
                    :modelValue="
                      assunto.ativo === 'S' || assunto.ativo === true
                    "
                    @update:modelValue="toggleAtivoAssunto(assunto)"
                  />
                </div>
              </div>

              <!-- Campos principais em grid -->
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Campo para o Assunto -->
                <div class="space-y-2">
                  <label
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    Nome do Assunto *
                  </label>
                  <InputText
                    v-model="assunto.assunto"
                    class="w-full"
                    placeholder="Digite o assunto"
                  />
                </div>

                <!-- Campo para o Responsável -->
                <div class="space-y-2">
                  <label
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    Responsável
                  </label>
                  <Select
                    show-clear
                    v-model="assunto.responsavel"
                    :options="responsaveis"
                    placeholder="Nenhum responsável atribuído"
                    option-label="nome"
                    option-value="matricula"
                    class="w-full"
                  />
                </div>

                <!-- Campo para a prioridade -->
                <div class="space-y-2">
                  <label
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    Prioridade Padrão
                  </label>
                  <Select
                    show-clear
                    v-model="assunto.prioridade"
                    :options="['baixa', 'media', 'alta', 'urgente']"
                    placeholder="Definida pelo usuário"
                    class="w-full"
                  />
                </div>

                <!-- Quantidade mínima de anexos -->
                <div class="space-y-2">
                  <label
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    Anexos Obrigatórios
                  </label>
                  <input
                    type="number"
                    class="w-full border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-gray-100 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Quantidade mínima"
                    v-model="assunto.qtd_min_anexos"
                    min="0"
                  />
                </div>
              </div>

              <!-- Botões de configuração - Componente responsivo -->
              <div class="pt-4 border-t border-gray-100 dark:border-slate-700">
                <AcoesAssuntoMenu
                  :assunto="assunto"
                  :quantidade-modelos="assunto.modelos?.length || 0"
                  @campos-configuraveis="showDialogSelecao"
                  @campos-predefinidos="showDialogCampos"
                  @instrucoes="showDialogInstrucoes"
                  @modelos="showDialogModelos"
                  @ver-modelos="visualizarModelosAssunto"
                  @liberacao="showDialogLiberacao"
                  @responsaveis="showDialogResponsaveisAssunto"
                  @etapas="showDialogEtapas"
                  @fluxo="showDialogFluxo"
                  @redirecionar="openRedirect"
                  @duplicar="duplicarAssunto"
                  @toggle-ativo="toggleAtivoAssunto"
                />
              </div>
            </div>
          </div>

          <div
            v-else
            class="text-center py-12"
          >
            <div
              class="w-16 h-16 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4"
            >
              <i
                class="pi pi-plus text-gray-400 dark:text-gray-500 text-xl"
              ></i>
            </div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
              Nenhum assunto adicionado ao departamento
            </p>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
              Clique em "Adicionar Assunto" para começar
            </p>
          </div>

          <!-- Footer fixo com botões de ação -->
          <div class="mt-6 p-4">
            <div class="flex justify-end items-end gap-3">
              <Button
                outlined
                @click="adicionarAssunto()"
                icon="pi pi-plus"
                label="Adicionar Assunto"
                class="shrink-0"
                severity="info"
              />
              <Button
                outlined
                @click="salvarAssuntos()"
                label="Salvar Alterações"
                class="shrink-0 2xl:mr-8"
                severity="success"
                icon="pi pi-check"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Aba Equipamentos -->
      <div
        v-if="aba == 'equipamentos'"
        class="w-full"
      >
        <div
          class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden"
        >
          <!-- Header do Card -->
          <div
            class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 px-6 py-4 border-b border-gray-100 dark:border-slate-700"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 bg-blue-100 dark:bg-blue-900/50 shrink-0 rounded-xl flex items-center justify-center"
                >
                  <i
                    class="pi pi-desktop text-blue-600 dark:text-blue-400 text-lg"
                  ></i>
                </div>
                <div>
                  <h3 class="font-semibold text-gray-800 dark:text-gray-100">
                    Lista de Equipamentos
                  </h3>
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    Gerencie os equipamentos disponíveis para solicitações
                  </p>
                </div>
              </div>
              <Button
                @click="adicionarEquipamento()"
                icon="pi pi-plus"
                outlined
                label="Adicionar"
                class="shrink-0"
                severity="info"
                size="small"
              />
            </div>
          </div>
          <!-- Conteúdo -->
          <div class="p-6">
            <div
              class="space-y-3"
              v-if="equipamentos.length > 0 && carregado"
            >
              <div
                v-for="(equip, index) in equipamentos"
                :key="index"
                class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-700 rounded-xl border border-gray-100 dark:border-slate-600 hover:border-blue-200 dark:hover:border-blue-800 transition-colors"
              >
                <div
                  class="w-8 h-8 bg-blue-100 dark:bg-blue-900/50 rounded-lg flex items-center justify-center flex-shrink-0"
                >
                  <span
                    class="text-blue-600 dark:text-blue-400 font-semibold text-sm"
                  >
                    {{ index + 1 }}
                  </span>
                </div>
                <input
                  v-model="equip.equipamento"
                  type="text"
                  class="flex-1 border border-gray-200 dark:border-slate-600 dark:bg-slate-800 dark:text-gray-100 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                  placeholder="Digite o nome do equipamento"
                />
                <Button
                  @click="removeEquipamento(equip.id)"
                  icon="pi pi-trash"
                  severity="danger"
                  outlined
                  text
                  rounded
                  v-tooltip.top="'Remover equipamento'"
                />
              </div>
            </div>

            <div
              v-else-if="equipamentos.length == 0 && carregado"
              class="text-center py-12"
            >
              <div
                class="w-16 h-16 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4"
              >
                <i
                  class="pi pi-desktop text-gray-400 dark:text-gray-500 text-2xl"
                ></i>
              </div>
              <p class="text-gray-500 dark:text-gray-400 font-medium">
                Nenhum equipamento cadastrado
              </p>
              <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
                Clique em "Adicionar" para começar
              </p>
            </div>
          </div>
          <!-- Footer -->
          <div
            class="px-6 py-4 bg-gray-50 dark:bg-slate-700 border-t border-gray-100 dark:border-slate-600 flex justify-end"
          >
            <Button
              @click="saveEquipamentos()"
              label="Salvar Alterações"
              icon="pi pi-check"
              outlined
              severity="success"
              class="px-6 w-full sm:w-auto mr-6"
            />
          </div>
        </div>
      </div>

      <!-- Aba Notificações -->
      <div
        v-if="aba == 'notificacoes'"
        class="w-full"
      >
        <div
          class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden"
        >
          <!-- Header do Card -->
          <div
            class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 px-6 py-4 border-b border-gray-100 dark:border-slate-700"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 bg-blue-100 dark:bg-blue-900/50 shrink-0 rounded-xl flex items-center justify-center"
                >
                  <i
                    class="pi pi-bell text-blue-600 dark:text-blue-400 text-lg"
                  ></i>
                </div>
                <div>
                  <h3 class="font-semibold text-gray-800 dark:text-gray-100">
                    Canais de Notificação
                  </h3>
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    Ative ou desative os canais de notificação do sistema
                  </p>
                </div>
              </div>
              <div
                class="flex items-center gap-2 px-3 py-1.5 bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400 rounded-full text-sm font-medium transition-all duration-500"
                :class="{
                  'opacity-0': !sucessoNotif,
                  'opacity-100': sucessoNotif
                }"
              >
                <i class="pi pi-check-circle"></i>
                <span>Salvo com sucesso!</span>
              </div>
            </div>
          </div>
          <!-- Conteúdo -->
          <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div
                v-for="canal in canaisNotif"
                :key="canal.canal"
                class="flex flex-col items-center p-6 bg-gray-50 dark:bg-slate-700 rounded-xl border border-gray-100 dark:border-slate-600 hover:border-blue-200 dark:hover:border-blue-800 transition-all"
              >
                <div
                  class="w-12 h-12 bg-white dark:bg-slate-800 rounded-xl flex items-center justify-center mb-3 shadow-sm"
                >
                  <i
                    :class="[
                      'text-xl',
                      canal.canal === 'push'
                        ? 'pi pi-send text-blue-500'
                        : canal.canal === 'in-app'
                          ? 'pi pi-inbox text-purple-500'
                          : canal.canal === 'email'
                            ? 'pi pi-envelope text-green-500'
                            : canal.canal === 'flutter'
                              ? 'pi pi-mobile text-orange-500'
                              : 'pi pi-bell text-gray-500'
                    ]"
                  ></i>
                </div>
                <label
                  class="font-semibold text-gray-700 dark:text-gray-200 mb-3 capitalize"
                >
                  {{ canal.canal }}
                </label>
                <ToggleSwitch
                  v-model="canal.notificacao"
                  true-value="1"
                  false-value="0"
                />
              </div>
            </div>
          </div>
          <!-- Footer -->
          <div
            class="px-6 py-4 bg-gray-50 dark:bg-slate-700 border-t border-gray-100 dark:border-slate-600 flex justify-end"
          >
            <Button
              @click="salvarNotificacoes(canaisNotif)"
              label="Salvar Alterações"
              outlined
              icon="pi pi-check"
              severity="success"
              class="px-6"
            />
          </div>
        </div>
      </div>

      <!-- Aba Filiais (Visibilidade Liderança) -->
      <div
        v-if="aba == 'filiais'"
        class="w-full"
      >
        <div
          class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl shadow-sm overflow-hidden"
        >
          <!-- Header do Card -->
          <div
            class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 px-6 py-4 border-b border-gray-100 dark:border-slate-700"
          >
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 bg-blue-100 dark:bg-blue-900/50 shrink-0 rounded-xl flex items-center justify-center"
              >
                <i
                  class="pi pi-arrows-h text-blue-600 dark:text-blue-400 text-lg shrink-0"
                ></i>
              </div>
              <div>
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">
                  Visibilidade por Filial
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Filiais à direita permitem que todos os seus usuários vejam e
                  interajam com todos os chamados abertos pela filial
                </p>
              </div>
            </div>
          </div>

          <!-- Conteúdo - PickList Customizado -->
          <div class="p-4 md:p-6">
            <div
              class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 items-start"
            >
              <!-- Card: Filiais Padrão (sem visibilidade) -->
              <div
                class="bg-white dark:bg-slate-900 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 border-l-4 border-l-gray-400 dark:border-l-gray-500 border-y border-r border-gray-200 dark:border-slate-700"
              >
                <div class="p-4 border-b border-gray-200 dark:border-slate-700">
                  <h4
                    class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-3 flex items-center"
                  >
                    <i class="pi pi-eye-slash mr-2 text-gray-500"></i>
                    Padrão (só os próprios)
                    <span
                      class="ml-auto px-2 py-0.5 bg-gray-100 dark:bg-gray-900/50 text-gray-700 dark:text-gray-400 text-xs rounded-full font-medium"
                    >
                      {{ filiaisDesconfiguradasFiltradas.length }}
                    </span>
                  </h4>
                  <div class="relative">
                    <i
                      class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                    ></i>
                    <input
                      v-model="filtroFilialDesconfigurada"
                      type="text"
                      placeholder="Buscar filiais..."
                      class="w-full pl-10 pr-4 py-2.5 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-gray-500 focus:border-gray-500 transition-all"
                    />
                  </div>
                </div>

                <div class="overflow-y-auto h-80 p-2">
                  <div
                    v-for="(filial, index) in filiaisDesconfiguradasFiltradas"
                    :key="filial.codigo"
                    @click="selecionarFilialDesconfigurada(filial)"
                    class="p-4 mb-1 rounded-lg cursor-pointer transition-all duration-200 border border-transparent"
                    :class="{
                      'bg-blue-50 dark:bg-blue-900/30 border-blue-400 dark:border-blue-600 shadow-sm':
                        filialDesconfiguradaSelecionada?.codigo ===
                        filial.codigo,
                      'bg-gray-50 dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 hover:border-gray-300 dark:hover:border-gray-700 hover:shadow-sm':
                        filialDesconfiguradaSelecionada?.codigo !==
                        filial.codigo
                    }"
                  >
                    <div class="flex items-center">
                      <span
                        class="font-semibold text-gray-600 dark:text-gray-400 mr-3 min-w-[28px] text-center"
                      >
                        {{ filial.codigo }}
                      </span>
                      <div class="flex flex-col">
                        <span
                          class="text-gray-700 dark:text-gray-200 text-sm font-medium uppercase"
                        >
                          {{ filial.fantasia }}
                        </span>
                        <span
                          v-if="filial.cidade"
                          class="text-gray-400 dark:text-gray-500 text-xs"
                        >
                          {{ filial.cidade }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <div
                    v-if="filiaisDesconfiguradasFiltradas.length === 0"
                    class="text-center py-12 text-gray-400 dark:text-gray-500"
                  >
                    <i class="pi pi-inbox text-4xl mb-3 block"></i>
                    <p class="font-medium">Nenhuma filial encontrada</p>
                  </div>
                </div>
              </div>

              <!-- Botões Centrais -->
              <div
                class="flex md:flex-col justify-center items-center gap-4 px-2 py-4 md:py-8"
              >
                <Button
                  :icon="isMobile ? 'pi pi-arrow-down' : 'pi pi-arrow-right'"
                  class="!w-14 !h-14 !rounded-full shadow-xl hover:scale-110 transition-transform duration-200"
                  severity="success"
                  @click="habilitarFilial"
                  :disabled="!filialDesconfiguradaSelecionada"
                  v-tooltip.top="'Ativar visibilidade'"
                />
                <Button
                  :icon="isMobile ? 'pi pi-arrow-up' : 'pi pi-arrow-left'"
                  class="!w-14 !h-14 !rounded-full shadow-xl hover:scale-110 transition-transform duration-200"
                  severity="danger"
                  @click="desabilitarFilial"
                  :disabled="!filialConfiguradaSelecionada"
                  v-tooltip.top="'Desativar visibilidade'"
                />
              </div>

              <!-- Card: Filiais com Visibilidade Ativa -->
              <div
                class="bg-white dark:bg-slate-900 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 border-l-4 border-l-green-400 dark:border-l-green-500 border-y border-r border-gray-200 dark:border-slate-700"
              >
                <div class="p-4 border-b border-gray-200 dark:border-slate-700">
                  <h4
                    class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-3 flex items-center"
                  >
                    <i class="pi pi-eye mr-2 text-green-500"></i>
                    Visibilidade Filial (todos veem)
                    <span
                      class="ml-auto px-2 py-0.5 bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400 text-xs rounded-full font-medium"
                    >
                      {{ filiaisConfiguradasFiltradas.length }}
                    </span>
                  </h4>
                  <div class="relative">
                    <i
                      class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                    ></i>
                    <input
                      v-model="filtroFilialConfigurada"
                      type="text"
                      placeholder="Buscar filiais..."
                      class="w-full pl-10 pr-4 py-2.5 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all"
                    />
                  </div>
                </div>

                <div class="overflow-y-auto h-80 p-2">
                  <div
                    v-for="(filial, index) in filiaisConfiguradasFiltradas"
                    :key="filial.codigo"
                    @click="selecionarFilialConfigurada(filial)"
                    class="p-4 mb-1 rounded-lg cursor-pointer transition-all duration-200 border border-transparent"
                    :class="{
                      'bg-green-50 dark:bg-green-900/30 border-green-400 dark:border-green-600 shadow-sm':
                        filialConfiguradaSelecionada?.codigo === filial.codigo,
                      'bg-gray-50 dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 hover:border-green-300 dark:hover:border-green-700 hover:shadow-sm':
                        filialConfiguradaSelecionada?.codigo !== filial.codigo
                    }"
                  >
                    <div class="flex items-center">
                      <span
                        class="font-semibold text-green-600 dark:text-green-400 mr-3 min-w-[28px] text-center"
                      >
                        {{ filial.codigo }}
                      </span>
                      <div class="flex flex-col">
                        <span
                          class="text-gray-700 dark:text-gray-200 text-sm font-medium uppercase"
                        >
                          {{ filial.fantasia }}
                        </span>
                        <span
                          v-if="filial.cidade"
                          class="text-gray-400 dark:text-gray-500 text-xs"
                        >
                          {{ filial.cidade }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <div
                    v-if="filiaisConfiguradasFiltradas.length === 0"
                    class="text-center py-12 text-gray-400 dark:text-gray-500"
                  >
                    <i class="pi pi-inbox text-4xl mb-3 block"></i>
                    <p class="font-medium">Nenhuma filial encontrada</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div
            class="px-6 py-4 bg-gray-50 dark:bg-slate-700 border-t border-gray-100 dark:border-slate-600 flex justify-end"
          >
            <Button
              @click="salvarFiliaisLideranca()"
              label="Salvar Alterações"
              icon="pi pi-check"
              outlined
              severity="success"
              class="px-6 w-full sm:w-auto"
              :loading="salvandoFiliais"
            />
          </div>
        </div>
      </div>
    </div>

    <Dialog
      v-model:visible="dialogCampos"
      modal
      position="top"
      :style="{ width: '60rem', maxHeight: '90vh' }"
      :breakpoints="{ '1199px': '90vw', '767px': '95vw', '575px': '100vw' }"
      :pt="{
        root: { class: 'overflow-hidden' },
        header: {
          class: 'border-b border-gray-100 dark:border-slate-700 flex-shrink-0'
        },
        content: { class: 'p-0 overflow-hidden flex-1' },
        footer: { class: 'flex-shrink-0' }
      }"
    >
      <template #header>
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 bg-blue-100 dark:bg-blue-900/50 rounded-xl flex items-center justify-center"
          >
            <i class="pi pi-cog text-blue-600 dark:text-blue-400 text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-gray-800 dark:text-gray-100">
              Configurar Campos do Assunto
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Defina quais campos aparecerão no formulário
            </p>
          </div>
        </div>
      </template>

      <div
        class="p-4 sm:p-6 space-y-4 sm:space-y-6 max-h-[60vh] overflow-y-auto overflow-x-hidden"
      >
        <!-- Cabeçalho informativo -->
        <div
          class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-xl p-3 sm:p-4"
        >
          <div class="flex items-start gap-3">
            <div
              class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0"
            >
              <i class="pi pi-info-circle text-blue-600"></i>
            </div>
            <div>
              <h4 class="text-sm font-semibold text-blue-800 mb-1">
                Campos Predefinidos do Sistema
              </h4>
              <p class="text-xs sm:text-sm text-blue-700">
                Configure quais campos aparecerão no formulário de criação de
                solicitações e suas respectivas configurações. Campos inativos
                não aparecerão para o usuário.
              </p>
            </div>
          </div>
        </div>

        <!-- Filtros e ações rápidas -->
        <div
          class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl p-3 sm:p-4"
        >
          <div
            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
          >
            <div class="flex flex-wrap items-center gap-2">
              <div class="flex items-center gap-2">
                <i class="pi pi-filter text-gray-500 dark:text-gray-400"></i>
                <span
                  class="text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                  Filtros:
                </span>
              </div>
              <div class="flex gap-1">
                <Button
                  label="Todos"
                  outlined
                  size="small"
                  :severity="filtroAtivo === 'todos' ? 'primary' : 'secondary'"
                  @click="filtroAtivo = 'todos'"
                  class="!text-xs !px-2 sm:!px-3"
                />
                <Button
                  label="Ativos"
                  outlined
                  size="small"
                  :severity="filtroAtivo === 'ativos' ? 'success' : 'secondary'"
                  @click="filtroAtivo = 'ativos'"
                  class="!text-xs !px-2 sm:!px-3"
                />
                <Button
                  outlined
                  label="Inativos"
                  size="small"
                  :severity="
                    filtroAtivo === 'inativos' ? 'danger' : 'secondary'
                  "
                  @click="filtroAtivo = 'inativos'"
                  class="!text-xs !px-2 sm:!px-3"
                />
              </div>
            </div>

            <div class="flex gap-1 sm:gap-2">
              <Button
                icon="pi pi-check-circle"
                size="small"
                severity="success"
                @click="toggleTodosCampos(true)"
                class="!text-xs !px-2 sm:!px-3"
                outlined
                v-tooltip.top="'Ativar Todos'"
              />
              <Button
                icon="pi pi-times-circle"
                size="small"
                severity="danger"
                @click="toggleTodosCampos(false)"
                class="!text-xs !px-2 sm:!px-3"
                outlined
                v-tooltip.top="'Desativar Todos'"
              />
            </div>
          </div>
        </div>

        <!-- Lista de campos com design melhorado -->
        <div class="space-y-4">
          <!-- Mensagem quando não há campos no filtro atual -->
          <div
            v-if="camposFiltrados.length === 0"
            class="text-center py-12"
          >
            <div
              class="w-16 h-16 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4"
            >
              <i
                class="pi pi-filter text-gray-400 dark:text-gray-500 text-xl"
              ></i>
            </div>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
              Nenhum campo
              {{
                filtroAtivo === "ativos"
                  ? "ativo"
                  : filtroAtivo === "inativos"
                    ? "inativo"
                    : ""
              }}
              encontrado
            </p>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
              Ajuste os filtros ou configure os campos conforme necessário
            </p>
          </div>

          <div
            v-for="(campo, index) in camposFiltrados"
            :key="index"
            class="p-3 sm:p-5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl hover:shadow-md transition-all duration-200"
            :class="{
              'opacity-60 bg-gray-50 dark:bg-slate-700/50': !campo.ativo
            }"
          >
            <!-- Cabeçalho do campo -->
            <div
              class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4"
            >
              <div class="flex items-center gap-3">
                <div
                  class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg flex items-center justify-center text-sm font-semibold flex-shrink-0"
                  :class="
                    campo.ativo
                      ? 'bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400'
                      : 'bg-gray-100 dark:bg-slate-600 text-gray-500 dark:text-gray-400'
                  "
                >
                  <i
                    class="pi"
                    :class="campo.ativo ? 'pi-check' : 'pi-minus'"
                  ></i>
                </div>
                <div>
                  <h5
                    class="text-sm sm:text-base font-semibold text-gray-800 dark:text-gray-100 uppercase"
                  >
                    {{ campo.descricao }}
                  </h5>
                  <p
                    class="text-xs sm:text-sm text-gray-500 dark:text-gray-400"
                  >
                    Campo {{ campo.ativo ? "ativo" : "inativo" }} no sistema
                  </p>
                </div>
              </div>

              <!-- Toggle de status -->
              <div class="flex items-center gap-2 ml-12 sm:ml-0">
                <span class="text-xs sm:text-sm text-gray-600">Inativo</span>
                <ToggleSwitch v-model="campo.ativo" />
                <span class="text-xs sm:text-sm text-gray-600">Ativo</span>
              </div>
            </div>

            <!-- Configurações do campo em grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
              <!-- Configuração de obrigatoriedade -->
              <div class="space-y-1">
                <label
                  class="block text-xs sm:text-sm font-medium text-gray-700"
                >
                  <i class="pi pi-exclamation-triangle text-amber-500 mr-1"></i>
                  Obrigatoriedade
                </label>
                <Select
                  v-model="campo.obrigatorio"
                  :options="obrigatorioOptions"
                  option-value="value"
                  option-label="label"
                  :disabled="!campo.ativo"
                  class="w-full"
                  placeholder="Selecione a obrigatoriedade"
                />
                <small class="text-xs text-gray-500 hidden sm:block">
                  Define se o campo deve ser preenchido obrigatoriamente
                </small>
              </div>

              <!-- Campo de observação -->
              <div class="space-y-1">
                <label
                  class="block text-xs sm:text-sm font-medium text-gray-700"
                >
                  <i class="pi pi-comment text-blue-500 mr-1"></i>
                  Observação/Dica
                </label>
                <InputText
                  v-model="campo.observacao"
                  :disabled="!campo.ativo"
                  class="w-full"
                  placeholder="Digite uma observação ou dica para o usuário"
                />
                <small class="text-xs text-gray-500 hidden sm:block">
                  Texto de ajuda que aparecerá para orientar o usuário
                </small>
              </div>
            </div>

            <!-- Configuração de tipo para campo TITULO -->
            <div
              v-if="campo.descricao === 'titulo'"
              class="mt-4 p-4 bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 border border-purple-200 dark:border-purple-700 rounded-xl"
            >
              <div class="flex items-start gap-3 mb-3">
                <div
                  class="w-8 h-8 bg-purple-100 dark:bg-purple-900/50 rounded-lg flex items-center justify-center flex-shrink-0"
                >
                  <i
                    class="pi pi-sliders-h text-purple-600 dark:text-purple-400"
                  ></i>
                </div>
                <div>
                  <h4
                    class="text-sm font-semibold text-purple-800 dark:text-purple-300 mb-1"
                  >
                    Tipo de Campo
                  </h4>
                  <p class="text-xs text-purple-700 dark:text-purple-400">
                    Defina se o título será digitado livremente ou selecionado
                    de uma lista de opções pré-definidas.
                  </p>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Seleção do tipo -->
                <div class="space-y-1">
                  <label
                    class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    <i class="pi pi-list text-purple-500 mr-1"></i>
                    Tipo de Entrada
                  </label>
                  <Select
                    v-model="campo.tipo"
                    :options="tipoTituloOptions"
                    option-value="value"
                    option-label="label"
                    :disabled="!campo.ativo"
                    class="w-full"
                    placeholder="Selecione o tipo"
                  />
                </div>

                <!-- Área de opções (visível apenas quando tipo = selecao) -->
                <div
                  v-if="campo.tipo === 'selecao'"
                  class="space-y-1"
                >
                  <label
                    class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    <i class="pi pi-plus-circle text-purple-500 mr-1"></i>
                    Adicionar Opção
                  </label>
                  <InputText
                    v-model="novaOpcaoTitulo"
                    :disabled="!campo.ativo"
                    class="w-full"
                    placeholder="Digite e pressione Enter"
                    @keyup.enter="adicionarOpcaoTitulo(campo)"
                  />
                </div>
              </div>

              <!-- Lista de opções cadastradas -->
              <div
                v-if="
                  campo.tipo === 'selecao' &&
                  campo.opcoes_titulo &&
                  campo.opcoes_titulo.length > 0
                "
                class="mt-4"
              >
                <label
                  class="block text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                >
                  <i class="pi pi-tags text-purple-500 mr-1"></i>
                  Opções Cadastradas ({{ campo.opcoes_titulo.length }})
                </label>
                <div class="flex flex-wrap gap-2">
                  <div
                    v-for="(opcao, opcIndex) in campo.opcoes_titulo"
                    :key="opcIndex"
                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-white dark:bg-slate-700 border border-purple-200 dark:border-purple-600 rounded-full text-sm text-gray-700 dark:text-gray-300 shadow-sm"
                  >
                    <span>{{ opcao }}</span>
                    <button
                      @click="removerOpcaoTitulo(campo, opcIndex)"
                      :disabled="!campo.ativo"
                      class="w-5 h-5 rounded-full bg-red-100 dark:bg-red-900/50 hover:bg-red-200 dark:hover:bg-red-800 text-red-600 dark:text-red-400 flex items-center justify-center transition-colors"
                      v-tooltip.top="'Remover opção'"
                    >
                      <i class="pi pi-times text-xs"></i>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Mensagem quando tipo seleção mas sem opções -->
              <div
                v-if="
                  campo.tipo === 'selecao' &&
                  (!campo.opcoes_titulo || campo.opcoes_titulo.length === 0)
                "
                class="mt-4"
              >
                <div
                  class="flex items-center gap-2 p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-lg"
                >
                  <i class="pi pi-exclamation-triangle text-amber-500"></i>
                  <span class="text-xs text-amber-700 dark:text-amber-300">
                    Adicione pelo menos uma opção para que o usuário possa
                    selecionar.
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <!-- Resumo das configurações sempre visível -->
        <div
          class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
        >
          <div class="grid grid-cols-4 gap-2 sm:gap-4 text-center">
            <div>
              <div class="text-base sm:text-lg font-semibold text-green-600">
                {{ estatisticasCampos.ativos }}
              </div>
              <div class="text-xs text-gray-600">Ativos</div>
            </div>
            <div>
              <div class="text-base sm:text-lg font-semibold text-gray-500">
                {{ estatisticasCampos.inativos }}
              </div>
              <div class="text-xs text-gray-600">Inativos</div>
            </div>
            <div>
              <div class="text-base sm:text-lg font-semibold text-amber-600">
                {{ estatisticasCampos.obrigatorios }}
              </div>
              <div class="text-xs text-gray-600">Obrigatórios</div>
            </div>
            <div>
              <div class="text-base sm:text-lg font-semibold text-blue-600">
                {{ estatisticasCampos.opcionais }}
              </div>
              <div class="text-xs text-gray-600">Opcionais</div>
            </div>
          </div>

          <!-- Botões de ação -->
          <div class="flex justify-end gap-2">
            <Button
              label="Cancelar"
              outlined
              severity="secondary"
              icon="pi pi-times"
              @click="dialogCampos = false"
              size="small"
            />
            <Button
              label="Salvar"
              icon="pi pi-check"
              @click="salvarEFecharCampos"
              severity="success"
              outlined
              size="small"
            />
          </div>
        </div>
      </template>
    </Dialog>

    <Dialog
      v-model:visible="dialogSelecao"
      modal
      position="top"
      :style="{ width: '60rem' }"
      :breakpoints="{ '1199px': '90vw', '767px': '95vw', '575px': '100vw' }"
      :pt="{
        root: { class: 'overflow-hidden' },
        header: { class: 'border-b border-gray-100 dark:border-slate-700' },
        content: { class: 'p-0 overflow-x-hidden' }
      }"
    >
      <template #header>
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 bg-teal-100 dark:bg-teal-900/50 rounded-xl flex items-center justify-center"
          >
            <i class="pi pi-list text-teal-600 dark:text-teal-400 text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-gray-800 dark:text-gray-100">
              Configurar Seleções
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Campos personalizados do assunto
            </p>
          </div>
        </div>
      </template>

      <template #default>
        <div
          class="p-3 sm:p-6 space-y-4 max-h-[70vh] overflow-y-auto overflow-x-hidden"
        >
          <div class="flex w-full justify-end">
            <Button
              label="Adicionar Seleção"
              icon="pi pi-plus"
              severity="contrast"
              outlined
              size="small"
              @click="adicionarSelecao"
            ></Button>
          </div>

          <div
            class="flex flex-col items-center justify-center w-full h-full"
            v-if="assuntoSelecionado.selects.length == 0"
          >
            <i class="pi pi-list !text-3xl mb-3"></i>
            <p class="text-lg font-medium">Nenhuma seleção configurada ainda</p>
            <p class="text-sm">
              Clique em
              <strong>“Adicionar Seleção”</strong>
              para começar
            </p>
          </div>

          <div class="space-y-3">
            <div
              v-for="(novoSelect, idx) in assuntoSelecionado.selects"
              :key="novoSelect.id || idx"
              class="p-3 sm:p-4 rounded-xl border transition-all duration-300"
              :class="
                !novoSelect.editando
                  ? 'bg-gray-50 border-gray-200'
                  : 'bg-white border-blue-200 shadow-md'
              "
            >
              <div class="space-y-3">
                <!-- Linha 1: Controles e Tipo de Campo -->
                <div class="flex flex-wrap items-start gap-2">
                  <!-- Campo de ordenação por número -->
                  <div class="flex items-center gap-1">
                    <input
                      type="number"
                      :value="idx + 1"
                      @change="moverParaPosicao(idx, $event.target.value)"
                      @blur="$event.target.value = idx + 1"
                      min="1"
                      :max="assuntoSelecionado.selects.length"
                      class="w-12 h-8 text-center text-sm font-bold text-blue-600 bg-blue-50 border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 hover:bg-blue-100 transition-colors [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                      v-tooltip.top="
                        `Ordem: digite 1 a ${assuntoSelecionado.selects.length}`
                      "
                    />
                  </div>

                  <!-- Tipo de Campo -->
                  <div class="flex flex-col flex-1 min-w-[140px]">
                    <label class="text-xs text-gray-600 font-medium mb-1">
                      Tipo de Campo
                    </label>
                    <Select
                      class="w-full"
                      v-model="novoSelect.tipo"
                      placeholder="Tipo"
                      :options="tipoCampoOptions"
                      option-value="value"
                      option-label="label"
                      :disabled="!novoSelect.editando"
                      @change="
                        verificarAlteracaoTipo(
                          novoSelect,
                          novoSelect.tipo,
                          novoSelect._tipoOriginal
                        )
                      "
                    ></Select>
                  </div>

                  <!-- Tipo de Data (condicional) -->
                  <div
                    v-if="novoSelect.tipo == 'data'"
                    class="flex flex-col flex-1 min-w-[120px]"
                  >
                    <label class="text-xs text-gray-600 font-medium mb-1">
                      Tipo de Data
                    </label>
                    <Select
                      class="w-full"
                      v-model="novoSelect.tipo_data"
                      placeholder="Tipo"
                      :options="tipoDataOptions"
                      option-value="value"
                      option-label="label"
                      :disabled="!novoSelect.editando"
                    ></Select>
                  </div>

                  <!-- Prazo Mínimo em Dias (condicional - apenas para tipo data) -->
                  <div
                    v-if="novoSelect.tipo == 'data'"
                    class="flex flex-col flex-1 min-w-[120px]"
                  >
                    <label class="text-xs text-gray-600 font-medium mb-1">
                      Dias Mínimos
                    </label>
                    <InputNumber
                      class="w-full"
                      v-model="novoSelect.dias_minimos"
                      placeholder="Ex: 5"
                      :min="0"
                      :disabled="!novoSelect.editando"
                      v-tooltip.top="
                        'Prazo mínimo de antecedência em dias. Ex: 5 = só permite datas a partir de 5 dias a partir de hoje.'
                      "
                    />
                  </div>
                </div>

                <!-- Linha 2: Título, Obrigatório, Exibir em, Observação -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div class="flex flex-col">
                    <div
                      class="flex flex-row gap-2 w-full justify-between pr-2"
                    >
                      <label class="text-xs text-gray-600 font-medium mb-1">
                        Título
                      </label>
                      <small
                        v-if="novoSelect.errors?.erroLabel"
                        class="p-error text-xs text-red-500 text-end font-bold"
                      >
                        {{ novoSelect.errors.erroLabel }}
                      </small>
                    </div>

                    <InputText
                      class="w-full h-10 !text-sm"
                      v-model="novoSelect.label"
                      :invalid="!!novoSelect.errors?.erroLabel"
                      :disabled="!novoSelect.editando"
                    />
                  </div>

                  <div class="flex flex-col">
                    <label class="text-xs text-gray-600 font-medium mb-1">
                      Obrigatório
                    </label>
                    <Select
                      class="w-full h-10 !text-sm"
                      :options="obrigatorioOptions"
                      v-model="novoSelect.obrigatorio"
                      option-value="value"
                      option-label="label"
                      :disabled="!novoSelect.editando"
                    />
                  </div>

                  <div class="flex flex-col">
                    <label class="text-xs text-gray-600 font-medium mb-1">
                      Exibir em
                    </label>
                    <div class="flex gap-3 h-10 items-center">
                      <div class="flex items-center gap-1">
                        <Checkbox
                          v-model="novoSelect.exibir_nova"
                          :disabled="!novoSelect.editando"
                          :binary="true"
                          :input-id="`exibir_nova_${idx}`"
                        />
                        <label
                          :for="`exibir_nova_${idx}`"
                          class="text-xs text-gray-600"
                        >
                          Nova
                        </label>
                      </div>
                      <div class="flex items-center gap-1">
                        <Checkbox
                          v-model="novoSelect.exibir_atendimento"
                          :disabled="!novoSelect.editando"
                          :binary="true"
                          :input-id="`exibir_atendimento_${idx}`"
                        />
                        <label
                          :for="`exibir_atendimento_${idx}`"
                          class="text-xs text-gray-600"
                        >
                          Atend.
                        </label>
                      </div>
                    </div>
                  </div>

                  <div class="flex flex-col sm:col-span-2">
                    <label class="text-xs text-gray-600 font-medium mb-1">
                      Observação
                    </label>
                    <InputText
                      class="w-full h-10 !text-sm"
                      v-model="novoSelect.observacao"
                      :disabled="!novoSelect.editando"
                    />
                  </div>
                </div>

                <!-- #12173 - Seção para Campos Condicionais -->
                <div
                  v-if="getCamposPaiDisponiveis(idx).length > 0"
                  class="grid grid-cols-1 sm:grid-cols-2 gap-3 border-t border-gray-200 pt-3 mt-2"
                >
                  <div class="flex flex-col">
                    <label class="text-xs text-gray-600 font-medium mb-1">
                      <i class="pi pi-link text-xs mr-1"></i>
                      Exibir somente se
                    </label>
                    <Select
                      class="w-full h-10 !text-sm"
                      v-model="novoSelect.campo_pai_id"
                      :options="getCamposPaiDisponiveis(idx)"
                      option-value="id"
                      option-label="label"
                      placeholder="Sempre exibir"
                      :disabled="!novoSelect.editando"
                      showClear
                    />
                  </div>

                  <div
                    v-if="novoSelect.campo_pai_id"
                    class="flex flex-col"
                  >
                    <label class="text-xs text-gray-600 font-medium mb-1">
                      Valor igual a
                    </label>
                    <Select
                      class="w-full h-10 !text-sm"
                      v-model="novoSelect.valor_condicional"
                      :options="getOpcoesCampoPai(novoSelect.campo_pai_id)"
                      placeholder="Selecione o valor"
                      :disabled="!novoSelect.editando"
                    />
                  </div>

                  <div
                    v-if="
                      novoSelect.campo_pai_id && novoSelect.valor_condicional
                    "
                    class="sm:col-span-2 flex items-center px-3 py-2 bg-amber-50 border border-amber-200 rounded-md"
                  >
                    <i
                      class="pi pi-info-circle text-amber-600 mr-2 flex-shrink-0"
                    ></i>
                    <span class="text-xs text-amber-700">
                      Este campo só aparecerá quando o campo pai tiver o valor
                      selecionado
                    </span>
                  </div>
                </div>

                <!-- Linha 3: Multi-seleção e botões de ação -->
                <div
                  class="flex flex-wrap gap-3 items-center justify-between border-t border-gray-100 pt-3"
                >
                  <!-- Toggle para múltipla seleção (apenas para tipos que permitem) -->
                  <div
                    v-if="
                      [
                        'selecao',
                        'depto_compras',
                        'depto_funcionario',
                        'filial_winthor',
                        'funcao',
                        'regional'
                      ].includes(novoSelect.tipo)
                    "
                    class="flex items-center gap-2"
                  >
                    <label class="text-xs text-gray-600 font-medium">
                      Multi-seleção
                    </label>
                    <ToggleSwitch
                      v-model="novoSelect.multiplo"
                      :disabled="!novoSelect.editando"
                      :pt="{
                        root: {
                          class:
                            novoSelect.multiplo && !novoSelect.editando
                              ? 'opacity-100'
                              : ''
                        },
                        slider: {
                          class:
                            novoSelect.multiplo && !novoSelect.editando
                              ? '!bg-green-500'
                              : ''
                        },
                        handle: {
                          class:
                            novoSelect.multiplo && !novoSelect.editando
                              ? '!bg-white'
                              : ''
                        }
                      }"
                    />
                    <span
                      class="text-xs font-medium"
                      :class="
                        novoSelect.multiplo ? 'text-green-500' : 'text-gray-500'
                      "
                    >
                      {{ novoSelect.multiplo ? "Sim" : "Não" }}
                    </span>
                  </div>

                  <div class="flex items-center gap-2 ml-auto">
                    <Button
                      v-if="novoSelect.id != null"
                      icon="pi pi-pencil"
                      size="small"
                      severity="info"
                      @click="toggleEdicao(novoSelect)"
                      outlined
                      v-tooltip.bottom="'Editar campo'"
                    />
                    <Button
                      icon="pi pi-trash"
                      size="small"
                      severity="danger"
                      outlined
                      v-tooltip.bottom="'Remover campo'"
                      @click="removerSelect(idx)"
                    />
                  </div>
                </div>

                <!-- Opções da seleção -->
                <div
                  v-if="novoSelect.tipo == 'selecao'"
                  class="border p-3 rounded-lg border-gray-200 transition-all duration-300"
                  :class="[
                    !novoSelect.editando ? 'bg-gray-50' : 'bg-white shadow-sm'
                  ]"
                >
                  <p class="text-xs font-medium mb-2 text-gray-700">
                    Opções da seleção:
                  </p>

                  <div class="space-y-2">
                    <div class="flex flex-wrap gap-1.5">
                      <div
                        v-for="(val, i) in novoSelect.valores"
                        :key="i"
                        class="flex items-center bg-gray-200 px-2 py-0.5 rounded-full text-xs"
                      >
                        <span>{{ val }}</span>
                        <i
                          v-if="novoSelect.editando"
                          class="pi pi-times ml-1 cursor-pointer text-red-600 text-xs"
                          @click="novoSelect.valores.splice(i, 1)"
                        ></i>
                      </div>

                      <small
                        v-if="novoSelect.errors?.erroOptions"
                        class="p-error text-xs mt-1 text-red-500 font-bold"
                      >
                        {{ novoSelect.errors.erroOptions }}
                      </small>
                    </div>

                    <InputText
                      v-if="novoSelect.editando"
                      v-model="novoSelect.valorDigitado"
                      :disabled="!novoSelect.editando"
                      placeholder="Digite e pressione Enter"
                      class="w-full"
                      @keydown.enter.prevent="adicionarChip(novoSelect)"
                      @keydown.tab.prevent="adicionarChip(novoSelect)"
                    />
                  </div>
                </div>

                <!-- Seção para campos de texto -->
                <div
                  v-if="novoSelect.tipo == 'texto'"
                  class="border p-3 rounded-lg border-gray-200 bg-gray-50"
                >
                  <p class="text-xs font-medium text-gray-700">
                    Campo de texto livre
                  </p>
                  <p class="text-xs text-gray-500 mt-1">
                    O usuário poderá digitar qualquer texto neste campo.
                  </p>
                </div>

                <!-- Seção para CNPJ -->
                <div
                  v-if="novoSelect.tipo == 'cnpj'"
                  class="border p-3 rounded-lg border-blue-200 bg-blue-50"
                >
                  <p class="text-sm font-medium mb-2 text-blue-800">
                    <i class="pi pi-id-card mr-2"></i>
                    Campo de CNPJ:
                  </p>
                  <p class="text-xs text-blue-600">
                    Campo com máscara de CNPJ (##.###.###/####-##).
                  </p>
                </div>

                <!-- Seção para Depto de Compras -->
                <div
                  v-if="novoSelect.tipo == 'depto_compras'"
                  class="border p-2 rounded border-green-200 bg-green-50 transition-all duration-300"
                >
                  <p class="text-sm font-medium mb-2 text-green-800">
                    <i class="pi pi-box mr-2"></i>
                    Departamento de Compras (Winthor):
                  </p>
                  <p class="text-xs text-green-600">
                    Busca os departamentos cadastrados na tabela PCDEPTO do
                    Winthor.
                  </p>
                </div>

                <!-- Seção para Depto de Funcionário -->
                <div
                  v-if="novoSelect.tipo == 'depto_funcionario'"
                  class="border p-2 rounded border-green-200 bg-green-50 transition-all duration-300"
                >
                  <p class="text-sm font-medium mb-2 text-green-800">
                    <i class="pi pi-users mr-2"></i>
                    Departamento de Funcionário (Winthor):
                  </p>
                  <p class="text-xs text-green-600">
                    Busca as áreas de atuação dos funcionários ativos
                    (AREAATUACAO de PCEMPR).
                  </p>
                </div>

                <!-- Seção para Filial Winthor -->
                <div
                  v-if="novoSelect.tipo == 'filial_winthor'"
                  class="border p-2 rounded border-green-200 bg-green-50 transition-all duration-300"
                >
                  <p class="text-sm font-medium mb-2 text-green-800">
                    <i class="pi pi-building mr-2"></i>
                    Filial (Winthor):
                  </p>
                  <p class="text-xs text-green-600">
                    Busca as filiais cadastradas na tabela PCFILIAL do Winthor.
                  </p>
                </div>

                <!-- Seção para Função -->
                <div
                  v-if="novoSelect.tipo == 'funcao'"
                  class="border p-2 rounded border-green-200 bg-green-50 transition-all duration-300"
                >
                  <p class="text-sm font-medium mb-2 text-green-800">
                    <i class="pi pi-briefcase mr-2"></i>
                    Função (Winthor):
                  </p>
                  <p class="text-xs text-green-600">
                    Busca as funções dos funcionários ativos (FUNCAO de PCEMPR).
                  </p>
                </div>

                <!-- Seção para Regional -->
                <div
                  v-if="novoSelect.tipo == 'regional'"
                  class="border p-2 rounded border-purple-200 bg-purple-50 transition-all duration-300"
                >
                  <p class="text-sm font-medium mb-2 text-purple-800">
                    <i class="pi pi-map mr-2"></i>
                    Regional:
                  </p>
                  <p class="text-xs text-purple-600">
                    Busca as regionais cadastradas no sistema.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button
            label="Cancelar"
            icon="pi pi-times"
            severity="secondary"
            outlined
            size="small"
            @click="dialogSelecao = false"
          />
          <Button
            label="Salvar"
            icon="pi pi-check"
            severity="success"
            outlined
            size="small"
            :loading="loadingButton"
            @click="criarSelects"
          />
        </div>
      </template>
    </Dialog>

    <!-- Dialog de Instruções -->
    <Dialog
      v-model:visible="dialogInstrucoes"
      modal
      :style="{ width: '50rem' }"
      :breakpoints="{ '1199px': '75vw', '767px': '90vw', '575px': '95vw' }"
      :pt="{
        root: { class: 'overflow-hidden' },
        header: { class: 'border-b border-gray-100 dark:border-slate-700' },
        content: { class: 'p-0' }
      }"
    >
      <template #header>
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 bg-purple-100 dark:bg-purple-900/50 rounded-xl flex items-center justify-center"
          >
            <i
              class="pi pi-book text-purple-600 dark:text-purple-400 text-lg"
            ></i>
          </div>
          <div>
            <h3 class="font-semibold text-gray-800 dark:text-gray-100">
              Configurar Instruções
            </h3>
            <p
              class="text-sm text-gray-500 dark:text-gray-400"
              v-if="assuntoParaInstrucoes"
            >
              Assunto:
              <strong class="text-purple-600 dark:text-purple-400">
                {{ assuntoParaInstrucoes.assunto }}
              </strong>
            </p>
          </div>
        </div>
      </template>

      <div
        class="p-6 space-y-6"
        v-if="assuntoParaInstrucoes"
      >
        <!-- Card informativo -->
        <div
          class="bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-100 rounded-xl p-4"
        >
          <div class="flex items-start gap-3">
            <div
              class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0"
            >
              <i class="pi pi-info-circle text-purple-600"></i>
            </div>
            <p class="text-sm text-purple-700">
              Configure instruções que aparecerão na tela de criação de
              solicitações. Use formatação para destacar informações
              importantes.
            </p>
          </div>
        </div>

        <!-- Editor -->
        <div
          class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl overflow-hidden"
        >
          <Editor
            v-model="assuntoParaInstrucoes.instrucoes"
            placeholder="Digite as instruções para este assunto..."
            editorStyle="height: 250px"
          >
            <template v-slot:toolbar>
              <span class="ql-formats">
                <button
                  title="Negrito"
                  class="ql-bold"
                ></button>
                <button
                  title="Itálico"
                  class="ql-italic"
                ></button>
                <button
                  title="Sublinhado"
                  class="ql-underline"
                ></button>
                <select
                  title="Cor do texto"
                  class="ql-color"
                ></select>
                <select
                  title="Cor de fundo"
                  class="ql-background"
                ></select>
                <button
                  title="Lista ordenada"
                  class="ql-list"
                  value="ordered"
                ></button>
                <button
                  title="Lista com marcadores"
                  class="ql-list"
                  value="bullet"
                ></button>
                <button
                  title="Limpar Formatação"
                  class="ql-clean"
                ></button>
              </span>
            </template>
          </Editor>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-3">
          <Button
            label="Cancelar"
            outlined
            severity="secondary"
            icon="pi pi-times"
            @click="dialogInstrucoes = false"
          />
          <Button
            label="Salvar Instruções"
            icon="pi pi-check"
            outlined
            severity="success"
            @click="salvarEFecharInstrucoes"
          />
        </div>
      </template>
    </Dialog>

    <!-- Dialog para Modelos -->
    <Dialog
      v-model:visible="dialogModelos"
      modal
      :style="{ width: '50rem' }"
      :breakpoints="{ '1199px': '75vw', '767px': '90vw', '575px': '95vw' }"
      :pt="{
        root: { class: 'overflow-hidden' },
        header: { class: 'border-b border-gray-100 dark:border-slate-700' },
        content: { class: 'p-0' }
      }"
    >
      <template #header>
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 bg-orange-100 dark:bg-orange-900/50 rounded-xl flex items-center justify-center"
          >
            <i
              class="pi pi-file text-orange-600 dark:text-orange-400 text-lg"
            ></i>
          </div>
          <div>
            <h3 class="font-semibold text-gray-800 dark:text-gray-100">
              Gerenciar Arquivos Modelo
            </h3>
            <p
              class="text-sm text-gray-500 dark:text-gray-400"
              v-if="assuntoParaModelos"
            >
              Assunto:
              <strong class="text-orange-600 dark:text-orange-400">
                {{ assuntoParaModelos.assunto }}
              </strong>
            </p>
          </div>
        </div>
      </template>

      <div
        class="p-6 space-y-6"
        v-if="assuntoParaModelos"
      >
        <!-- Card informativo -->
        <div
          class="bg-gradient-to-r from-orange-50 to-amber-50 border border-orange-100 rounded-xl p-4"
        >
          <div class="flex items-start gap-3">
            <div
              class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0"
            >
              <i class="pi pi-info-circle text-orange-600"></i>
            </div>
            <p class="text-sm text-orange-700">
              Configure arquivos modelo que ficarão disponíveis para download na
              criação de solicitações deste assunto.
            </p>
          </div>
        </div>

        <!-- Lista de modelos existentes -->
        <div
          v-if="
            assuntoParaModelos.modelos && assuntoParaModelos.modelos.length > 0
          "
        >
          <h5
            class="font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2"
          >
            <i class="pi pi-folder text-blue-600 dark:text-blue-400"></i>
            Modelos Atuais
            <span
              class="ml-auto px-2 py-0.5 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400 text-xs rounded-full font-medium"
            >
              {{ assuntoParaModelos.modelos.length }}
            </span>
          </h5>
          <div class="space-y-2 max-h-48 overflow-y-auto">
            <div
              v-for="(modelo, index) in assuntoParaModelos.modelos"
              :key="index"
              class="flex items-center justify-between p-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl hover:border-blue-200 dark:hover:border-blue-800 transition-all"
            >
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 bg-blue-50 dark:bg-blue-900/50 shrink-0 rounded-xl flex items-center justify-center"
                >
                  <i class="pi pi-file text-blue-600 dark:text-blue-400"></i>
                </div>
                <div>
                  <span
                    class="font-medium text-gray-800 dark:text-gray-100 text-sm"
                  >
                    {{
                      modelo.arquivo?.original_name || modelo.nome || "Arquivo"
                    }}
                  </span>
                  <div class="text-xs text-gray-500 dark:text-gray-400">
                    Arquivo modelo disponível
                  </div>
                </div>
              </div>
              <Button
                icon="pi pi-trash"
                severity="danger"
                outlined
                rounded
                @click="removerModelo(index)"
                v-tooltip.top="'Remover modelo'"
              />
            </div>
          </div>
        </div>

        <!-- Seção para adicionar novos modelos -->
        <div
          class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-2 border-dashed border-green-300 dark:border-green-700 rounded-xl p-4 sm:p-6"
        >
          <h5
            class="font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2"
          >
            <div
              class="w-8 h-8 bg-green-100 dark:bg-green-900/50 rounded-lg flex items-center justify-center"
            >
              <i
                class="pi pi-cloud-upload text-green-600 dark:text-green-400"
              ></i>
            </div>
            <span>
              {{
                assuntoParaModelos.modelos?.length > 0
                  ? "Adicionar Novos Modelos"
                  : "Adicionar Modelos"
              }}
            </span>
          </h5>
          <BsFile2
            @atualizar-lista="atualizarListaModelos"
            @deletar-arquivo="deletarModeloTemporario"
            :aceitar-um-arquivo="false"
            :is-vertical="true"
          />
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-3">
          <Button
            label="Cancelar"
            severity="secondary"
            outlined
            icon="pi pi-times"
            @click="dialogModelos = false"
          />
          <Button
            label="Salvar Modelos"
            outlined
            icon="pi pi-check"
            severity="success"
            @click="salvarModelos"
          />
        </div>
      </template>
    </Dialog>

    <!-- ViewFiles para visualizar modelos -->
    <ViewFiles
      v-model:visible="visualizarModelos"
      :lista-arquivos-id="modelosParaVisualizar"
    />

    <!-- Dialog de Liberação -->
    <LiberacaoDialog
      v-model="dialogLiberacao"
      :assunto="assuntoParaLiberacao"
      @liberacoes-salvas="buscarAssuntos"
    />

    <!-- Dialog de Responsáveis do Assunto (Permissão Exclusiva) -->
    <ResponsaveisDialog
      v-model="dialogResponsaveisAssunto"
      :assunto="assuntoParaResponsaveis"
      @responsaveis-salvos="buscarAssuntos"
    />

    <!-- Dialog de Etapas de Andamento -->
    <EtapasDialog
      v-model="dialogEtapas"
      :assunto="assuntoParaEtapas"
      :assuntos="assuntos"
      @etapas-salvas="buscarAssuntos"
    />

    <!-- Dialog de Fluxo/Workflow -->
    <FluxoDialog
      v-model="dialogFluxo"
      :assunto="assuntoParaFluxo"
      :departamentos="departamentos[1] || []"
      :assuntos="assuntos"
      @fluxo-salvo="buscarAssuntos"
    />

    <!-- Dialog de Responsáveis Adicionais -->
    <Dialog
      v-model:visible="dialogResponsaveisAdicionais"
      modal
      :style="{ width: '50rem' }"
      :breakpoints="{ '1199px': '75vw', '767px': '90vw', '575px': '95vw' }"
      :pt="{
        root: { class: 'overflow-hidden' },
        header: { class: 'border-b border-gray-100 dark:border-slate-700' },
        content: { class: 'p-0' }
      }"
    >
      <template #header>
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 bg-blue-100 dark:bg-blue-900/50 rounded-xl flex items-center justify-center"
          >
            <i class="pi pi-users text-blue-600 dark:text-blue-400 text-lg"></i>
          </div>
          <div>
            <h3 class="font-semibold text-gray-800 dark:text-gray-100">
              Responsáveis Adicionais
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Departamento:
              <strong class="text-blue-600 dark:text-blue-400">
                {{ departamentoResponsaveis }}
              </strong>
            </p>
          </div>
        </div>
      </template>

      <div class="p-6 space-y-6">
        <!-- Card informativo -->
        <div
          class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-xl p-4"
        >
          <div class="flex items-start gap-3">
            <div
              class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0"
            >
              <i class="pi pi-info-circle text-blue-600"></i>
            </div>
            <p class="text-sm text-blue-700">
              Adicione funcionários de outros departamentos que também podem
              responder chamados. Eles aparecerão como opção de responsáveis
              para todos os assuntos deste departamento.
            </p>
          </div>
        </div>

        <!-- Formulário para adicionar novo responsável -->
        <div
          class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl p-4"
        >
          <h5
            class="font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2"
          >
            <i class="pi pi-user-plus text-green-600 dark:text-green-400"></i>
            Adicionar Novo Responsável
          </h5>
          <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
              <Funcionario
                v-model="novoResponsavelAdicional"
                :retorna-objeto="true"
                placeholder="Buscar funcionário..."
              />
            </div>
            <Button
              outlined
              label="Adicionar"
              icon="pi pi-plus"
              @click="adicionarResponsavelAdicional"
              :loading="loadingResponsaveis"
              :disabled="!novoResponsavelAdicional"
              severity="success"
              class="w-full sm:w-auto"
            />
          </div>
        </div>

        <!-- Lista de responsáveis adicionais existentes -->
        <div v-if="responsaveisAdicionais.length > 0">
          <h5
            class="font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2"
          >
            <i class="pi pi-users text-blue-600 dark:text-blue-400"></i>
            Responsáveis Configurados
            <span
              class="ml-auto px-2 py-0.5 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400 text-xs rounded-full font-medium"
            >
              {{ responsaveisAdicionais.length }}
            </span>
          </h5>
          <div class="space-y-3 max-h-80 overflow-y-auto">
            <div
              v-for="responsavel in responsaveisAdicionais"
              :key="responsavel.id"
              class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 sm:p-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl hover:border-blue-200 dark:hover:border-blue-800 hover:shadow-sm transition-all"
            >
              <div class="flex items-center gap-3 sm:gap-4">
                <!-- Avatar com foto ou iniciais -->
                <div
                  v-if="responsavel.foto"
                  class="w-10 h-10 sm:w-12 sm:h-12 rounded-full overflow-hidden ring-2 ring-offset-2 ring-green-400 flex-shrink-0"
                  v-tooltip.top="responsavel.nome"
                >
                  <img
                    :src="responsavel.foto"
                    :alt="responsavel.nome"
                    class="w-full h-full object-cover"
                  />
                </div>
                <div
                  v-else
                  class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center ring-2 ring-offset-2 ring-green-400 flex-shrink-0"
                  v-tooltip.top="responsavel.nome"
                >
                  <span class="text-white font-bold text-xs sm:text-sm">
                    {{ obterIniciais(responsavel.nome) }}
                  </span>
                </div>
                <div class="min-w-0">
                  <div
                    class="font-semibold text-gray-800 dark:text-gray-100 text-sm sm:text-base truncate"
                  >
                    {{ obterNomeSobrenome(responsavel.nome) }}
                  </div>
                  <div
                    class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 flex flex-wrap items-center gap-1 sm:gap-2"
                  >
                    <span class="flex items-center gap-1">
                      <i class="pi pi-id-card text-xs"></i>
                      {{ responsavel.matricula }}
                    </span>
                    <span
                      class="text-gray-300 dark:text-gray-600 hidden sm:inline"
                    >
                      •
                    </span>
                    <span class="flex items-center gap-1">
                      <i class="pi pi-building text-xs"></i>
                      <span class="truncate max-w-[120px] sm:max-w-none">
                        {{ responsavel.departamento_original }}
                      </span>
                    </span>
                  </div>
                </div>
              </div>
              <Button
                icon="pi pi-trash"
                severity="danger"
                outlined
                rounded
                size="small"
                @click="removerResponsavelAdicional(responsavel)"
                :loading="loadingResponsaveis"
                v-tooltip.top="'Remover responsável'"
                class="self-end sm:self-auto"
              />
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <div
          v-else-if="!loadingResponsaveis"
          class="text-center py-12"
        >
          <div
            class="w-16 h-16 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-4"
          >
            <i
              class="pi pi-users text-gray-400 dark:text-gray-500 text-2xl"
            ></i>
          </div>
          <p class="text-gray-500 dark:text-gray-400 font-medium">
            Nenhum responsável adicional configurado
          </p>
          <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
            Use o formulário acima para adicionar
          </p>
        </div>

        <!-- Loading state -->
        <div
          v-if="loadingResponsaveis"
          class="text-center py-12"
        >
          <div
            class="inline-flex items-center gap-3 px-4 py-2 bg-blue-50 dark:bg-blue-900/30 rounded-full"
          >
            <i
              class="pi pi-spin pi-spinner text-blue-500 dark:text-blue-400"
            ></i>
            <span class="text-blue-600 dark:text-blue-400 font-medium">
              Carregando...
            </span>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end">
          <Button
            label="Fechar"
            outlined
            severity="secondary"
            icon="pi pi-times"
            @click="dialogResponsaveisAdicionais = false"
          />
        </div>
      </template>
    </Dialog>

    <!-- ConfirmDialog para confirmações -->
    <ConfirmDialog />

    <!-- Dialog de alteração de tipo de campo -->
    <Dialog
      v-model:visible="dialogAlteracaoTipo"
      modal
      :closable="false"
      header="Atenção: Alteração de Tipo de Campo"
      class="!w-[500px]"
    >
      <div class="flex flex-col space-y-4">
        <div
          class="flex items-center space-x-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg"
        >
          <i class="pi pi-exclamation-triangle text-yellow-600 text-2xl"></i>
          <div>
            <p class="font-semibold text-yellow-800">
              Este campo já possui respostas!
            </p>
            <p class="text-sm text-yellow-700">
              {{ qtdRespostasAlteracao }}
              {{ qtdRespostasAlteracao <= 1 ? "solicitação" : "solicitações" }}
              já responderam este campo.
            </p>
          </div>
        </div>

        <div class="text-gray-700">
          <p class="mb-2">Ao alterar o tipo do campo:</p>
          <ul class="list-disc list-inside space-y-1 text-sm ml-2">
            <li>
              Um
              <strong>novo campo</strong>
              será criado com o novo tipo
            </li>
            <li>
              O campo antigo será
              <strong>desativado</strong>
              para novas solicitações
            </li>
            <li>
              As respostas antigas
              <strong>continuarão visíveis</strong>
              nas solicitações existentes
            </li>
          </ul>
        </div>

        <div class="text-sm text-gray-500 bg-gray-50 p-2 rounded">
          <strong>Tipo atual:</strong>
          {{
            tipoCampoOptions.find((t) => t.value === tipoAntigoAlteracao)
              ?.label || tipoAntigoAlteracao
          }}
          <br />
          <strong>Novo tipo:</strong>
          {{
            tipoCampoOptions.find((t) => t.value === tipoNovoAlteracao)
              ?.label || tipoNovoAlteracao
          }}
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end space-x-2">
          <Button
            label="Cancelar"
            severity="secondary"
            icon="pi pi-times"
            @click="cancelarAlteracaoTipo"
          />
          <Button
            label="Confirmar Alteração"
            severity="warning"
            icon="pi pi-check"
            @click="confirmarAlteracaoTipo"
          />
        </div>
      </template>
    </Dialog>

    <RedirecionamentoDialog
      v-model="abrirDialogRedirecionar"
      v-model:assunto="assuntoSelecionado"
      @save="salvarAssuntos"
      :departamentos="props.departamentos"
    />
  </AuthenticatedLayout>
</template>
