<script setup>
import {
  deleteFile,
  downloadFile,
  formatarData,
  formatarDataSemHoras,
  formatarParaReais,
  formatarTelefoneFixo,
  getUsuarioById,
  isImagem,
  swalConfirm,
  swalInput,
  swalObservacao,
  toastWarning,
  toastSuccess,
  tratarNome,
  uploadFile,
  toastError
} from "@/utils/globalFunctions"
import heic2any from "heic2any"
import { Image, Textarea } from "primevue"
import DatePicker from "primevue/datepicker"
import Button from "primevue/button"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import MultiSelect from "primevue/multiselect"
import InputMask from "primevue/inputmask"
import Select from "primevue/select"
import { onMounted, onUnmounted, ref, reactive, watch, computed } from "vue"
import { useSolicitacoesEcho } from "@/composables/useSolicitacoesEcho"
import FileInput2 from "@/Components/New/FileInput2.vue"
import Agendamento from "./Agendamento.vue"
import AgendamentoLembrete from "./AgendamentoLembrete.vue"
import Detalhes from "./Agendamentos/partials/Detalhes.vue"
import Loader from "@/Components/Loader.vue"
import Editor from "primevue/editor"
import ViewFiles from "@/Components/Componentes/ViewFiles.vue"
import Funcionario from "@/Components/Componentes/Funcionario.vue"
import Aprovacao from "./Aprovacao.vue"
import LinhaTempo from "@/Components/Timeline/LinhaTempo.vue"
import { Dialog } from "primevue"
import { usePage } from "@inertiajs/vue3"

const props = defineProps([
  "solicitacao_id",
  "permissoes",
  "auth",
  "abaInicial",
  "aprovacaoIdRejeitar"
])
const emits = defineEmits(["fecharDialogo", "atualizar"])

const aba = ref(props.abaInicial || "solicitacao")
const solicitacao = ref(null)
const loading = ref(false)
const dialogDadosLiberacao = ref(false)
const dadosLiberacaoSelecionado = ref(null)
const dialogMudarPrioridade = ref(false)

// ── Criar Branch (somente matrículas autorizadas) ──
const page = usePage()
const podeCriarBranch = computed(() => {
  const matriculasAutorizadas = page.props.matriculas_devtools || []
  return matriculasAutorizadas.includes(Number(props.auth?.matricula))
})
const criandoBranch = ref(false)
const branchJaExiste = ref(false)
const branchNomeExistente = ref("")
const dialogMudarDepartamento = ref(false)
const prioridadeSelecionada = ref("baixa")
const dialogTrocarResponsavel = ref(false)
const responsavelSelecionado = ref(null)
const comentario = ref("")
const comentarioDevolucao = ref("")
const comentarioAnexos = ref("")
const arquivosComentario = ref([])
const dialogAnexos = ref(false)
const dialogoCaixaVenda = ref(null)
const vendasPendentesSelecionada = ref(null)
const dialogResolver = ref(false)
const dialogRecusar = ref(false)
const dialogoCancelar = ref(false)
const dialogAgendamento = ref(false)
const dialogLembrete = ref(false) // Dialog para criar lembrete
const dialogDetalhesLembrete = ref(false) // Dialog para visualizar detalhes do lembrete
const lembreteEdicao = ref(null) // Armazena o lembrete para edição
const agendamentos = ref([])
const agendamentoSelecionado = ref("")
const editAgendamento = ref(false)
const dialogDetalhes = ref(false)
const existeAnexo = ref(false)
const dialogImagem = ref(false)
const imagemSelecionada = ref([])
const deptos = ref([])
const deptoSelecionado = ref(null)
const assuntoSelecionado = ref(null)
const comentarioMotivo = ref("")
const dialogMotivoDepto = ref(false)
const respostasSelects = ref([]) // Respostas dos campos customizados ao alterar assunto
const isFlutter = ref(typeof window.flutter_inappwebview !== "undefined") // Verifica se está no ambiente Flutter

// Etapa de andamento
const etapaSelecionada = ref(null)

// Fluxo/Workflow
const fluxoDados = ref(null)
const loadingFluxo = ref(false)
const dialogDecisaoFluxo = ref(false)
const observacaoFluxo = ref("")
const valoresCamposFluxo = ref({})
const salvandoCampos = ref(false)
const dialogAtribuirDev = ref(false)
const decisaoAtribuirDevId = ref(null)
const devSelecionado = ref(null)
const deptoAtribuirDev = ref(null)
const dialogCamposDecisao = ref(false)
const camposDecisaoAtual = ref([])
const respostasSelectsFluxo = ref([]) // Respostas dos campos do assunto ao avançar no fluxo
const decisaoPendenteId = ref(null)
const decisaoPendenteObs = ref(null)

// ✅ Reverb - Canal ativo para a ticket
const { escutarSolicitacao, sairDoCanal } = useSolicitacoesEcho()
let canalSolicitacao = null

// Dados dos campos pré-definidos Winthor para alteração de departamento
const dadosWinthor = ref({
  depto_compras: [],
  depto_funcionario: [],
  filial_winthor: [],
  funcao: [],
  regional: []
})

// Estado de loading dos campos Winthor
const loadingWinthor = ref({
  depto_compras: false,
  depto_funcionario: false,
  filial_winthor: false,
  funcao: false,
  regional: false
})

// Função para carregar dados Winthor
async function carregarDadosWinthor(tipo) {
  if (dadosWinthor.value[tipo].length > 0) return // Já carregado
  if (loadingWinthor.value[tipo]) return // Já está carregando

  loadingWinthor.value[tipo] = true

  const endpoints = {
    depto_compras: "/solicitacoes/configuracoes/dados/departamentos-compras",
    depto_funcionario:
      "/solicitacoes/configuracoes/dados/departamentos-funcionario",
    filial_winthor: "/solicitacoes/configuracoes/dados/filiais-winthor",
    funcao: "/solicitacoes/configuracoes/dados/funcoes",
    regional: "/solicitacoes/configuracoes/dados/regionais"
  }

  try {
    const response = await axios.get(endpoints[tipo])
    dadosWinthor.value[tipo] = response.data
  } catch (error) {
    console.error(`Erro ao carregar ${tipo}:`, error)
  } finally {
    loadingWinthor.value[tipo] = false
  }
}
const dialogRetorno = ref(false)
const showAtencaoRetorno = ref(false)
const privateComment = ref(false)
const privateType = ref(null)
const showPrivateMenu = ref(false)
const privateOptions = [
  {
    label: "Comentário público",
    value: null,
    icon: "pi pi-lock-open",
    color: "text-gray-600"
  },
  {
    label: "Privado (só eu)",
    value: "S",
    icon: "pi pi-lock",
    color: "text-violet-600"
  },
  {
    label: "Minha área de atuação",
    value: "A",
    icon: "pi pi-users",
    color: "text-blue-600"
  }
]
const previsaoEntrega = ref(null)
const editandoPrevisao = ref(false)
const fileId = ref(null)
const viewFileDialog = ref(false)
const listaIdsFile = ref([])
const trocaAssunto = ref(false)
const loadingHeic = ref(false)
const imagemConvertida = ref(null)

// Referência do componente de aprovação
const aprovacaoRef = ref(null)

// Dados das aprovações para uso global
const aprovacoes = ref([])
const aprovacaoStats = reactive({
  pendentes: 0,
  aprovadas: 0,
  rejeitadas: 0,
  canceladas: 0,
  total: 0
})

// Dialog para enviar arquivo para dossiê
const dialogEnviarDossie = ref(false)
const funcionarioSelecionadoDossie = ref(null)
const pastaSelecionadaDossie = ref(null)
const listaSelecionadaDossie = ref(null)
const loadingDossie = ref(false)
const configDossie = ref({ pastas: [], dossiesIndividuais: [] })
const arquivoParaDossie = ref(null)
// Dialog para criar novo dossiê
const dialogNovoDossie = ref(false)
const novoDossie = ref({ descricao: "" })

const maskTelefone = reactive({
  mask: [
    "(##) ####-####", // Formato sem 9º dígito
    "(##) #####-####", // Formato com 9º dígito
    "####-####" // Sem DDD
  ],
  eager: true
})

const comentarioTexto = computed(() => {
  // if (!comentario.value) return ""
  const html = comentario.value

  // Substitui <br> por \n e </p> por \n para preservar enters
  const normalizado = html
    .replace(/<br\s*\/?>/gi, "\n")
    .replace(/<\/p>/gi, "\n")
    .replace(/<[^>]+>/g, "") // remove as demais tags
    .replace(/&nbsp;/gi, " ") // remove &nbsp;
    .trim()

  return normalizado
})

const responsaveisComputedOption = computed(() => {
  const responsaveis = [...solicitacao.value.responsaveis_relacionados].sort(
    (a, b) => a.nome.localeCompare(b.nome, "pt-BR", { sensitivity: "base" })
  )
  return responsaveis
})

const arquivosComputed = computed(() => {
  var arquivosJuntos = []
  if (solicitacao.value.arquivos && solicitacao.value.arquivos.length > 0) {
    arquivosJuntos = arquivosJuntos.concat(solicitacao.value.arquivos)
  }
  if (
    solicitacao.value.respostas_selecao &&
    solicitacao.value.respostas_selecao.filter((r) => r.file_id).length > 0
  ) {
    // Concatenando respostas de seleção com arquivos
    arquivosJuntos = arquivosJuntos.concat(
      solicitacao.value.respostas_selecao.filter((r) => r.file_id)
    )
  }
  // Arquivos processados e prontos para uso
  return arquivosJuntos
})

// Computed que agrupa arquivos por categoria (predefinidos vs configuráveis)
const arquivosAgrupados = computed(() => {
  const grupos = []

  // 1. Arquivos predefinidos (sem selecao)
  const arquivosPredefinidos = arquivosComputed.value.filter(
    (arq) => !arq.selecao
  )
  if (arquivosPredefinidos.length > 0) {
    grupos.push({
      label: "Arquivos",
      arquivos: arquivosPredefinidos
    })
  }

  // 2. Arquivos de campos configuráveis (com selecao), agrupados por label
  const arquivosConfiguraveis = arquivosComputed.value.filter(
    (arq) => arq.selecao
  )

  // Agrupar por selecao.label
  const gruposPorLabel = {}
  arquivosConfiguraveis.forEach((arq) => {
    const label = arq.selecao.label
    if (!gruposPorLabel[label]) {
      gruposPorLabel[label] = []
    }
    gruposPorLabel[label].push(arq)
  })

  // Converter para array
  Object.keys(gruposPorLabel).forEach((label) => {
    grupos.push({
      label: label,
      arquivos: gruposPorLabel[label]
    })
  })

  return grupos
})

// Computed que agrupa respostas de seleção pelo selecao_id para evitar repetição de labels
const respostasSelecaoAgrupadas = computed(() => {
  if (!solicitacao.value.respostas_selecao) return []

  const mapa = new Map()

  solicitacao.value.respostas_selecao
    .filter((r) => r.selecao.tipo !== "arquivo")
    .forEach((resposta) => {
      const key = resposta.selecao_id
      if (!mapa.has(key)) {
        mapa.set(key, {
          id: key,
          selecao: resposta.selecao,
          respostas: []
        })
      }
      mapa.get(key).respostas.push(resposta)
    })

  return Array.from(mapa.values())
})

const isRecusada = computed(() => {
  const status = solicitacao.value.movimentacoes.find(
    (m) => m.tipo_movimentacao === "Resolução recusada"
  )
  return status ? true : false
})

onMounted(async () => {
  await buscarSolicitacao()
  verificarBranchExistente()

  // ✅ Iniciar listener Reverb para atualizações em tempo real
  iniciarReverbListener()
})

// ✅ Cleanup ao desmontar componente
onUnmounted(() => {
  if (canalSolicitacao) {
    sairDoCanal(`public.intranet.solicitacoes.item.${props.solicitacao_id}`)
  }
})

// ✅ Iniciar listener do Reverb para a ticket específica
function iniciarReverbListener() {
  if (!props.solicitacao_id) return

  console.log(
    "[Ticket] Iniciando listener Reverb para ticket:",
    props.solicitacao_id
  )

  canalSolicitacao = escutarSolicitacao(props.solicitacao_id, {
    onAtualizada: (data) => {
      // Atualização recebida - recarregar ticket silenciosamente
      console.log("[Ticket] Atualização recebida via Reverb:", data)
      buscarSolicitacaoSilenciosa()
    },
    onComentario: (data) => {
      // Novo comentário recebido
      console.log("[Ticket] Novo comentário via Reverb:", data)
      buscarSolicitacaoSilenciosa()
    },
    onComentarioExcluido: (data) => {
      // Comentário excluído em tempo real
      console.log("[Ticket] Comentário excluído via Reverb:", data)
      buscarSolicitacaoSilenciosa()
    }
  })
}

// Computed para determinar se a previsão pode ser editada
const podeEditarPrevisao = computed(() => {
  return (
    solicitacao.value &&
    solicitacao.value.isDepartamento &&
    solicitacao.value.status !== "cancelada" &&
    solicitacao.value.status !== "finalizada" &&
    solicitacao.value.status !== "resolvida" &&
    solicitacao.value.status !== "retorno solicitante"
  )
})

// Computed para determinar se a previsão deve ser mostrada
const mostrarPrevisao = computed(() => {
  return solicitacao.value && solicitacao.value.usuario_responsavel
})

// Computed para formatar a data da previsão
const previsaoFormatada = computed(() => {
  if (!solicitacao.value || !solicitacao.value.previsao_entrega) return null

  try {
    const data = new Date(solicitacao.value.previsao_entrega)
    return data.toLocaleDateString("pt-BR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric"
    })
  } catch (error) {
    return null
  }
})

// Computed para problemas de aprovação
const temProblemasAprovacao = computed(() => {
  return aprovacaoStats.pendentes > 0 || aprovacaoStats.rejeitadas > 0
})

// Computed para mensagem de alerta de aprovações
const mensagemAlerteAprovacao = computed(() => {
  const mensagens = []

  if (aprovacaoStats.pendentes > 0) {
    mensagens.push(
      `${aprovacaoStats.pendentes} aprovação${aprovacaoStats.pendentes > 1 ? "ões" : ""} pendente${aprovacaoStats.pendentes > 1 ? "s" : ""}`
    )
  }

  if (aprovacaoStats.rejeitadas > 0) {
    mensagens.push(
      `${aprovacaoStats.rejeitadas} aprovação${aprovacaoStats.rejeitadas > 1 ? "ões" : ""} rejeitada${aprovacaoStats.rejeitadas > 1 ? "s" : ""}`
    )
  }

  return mensagens.join(" e ")
})

// Computed para contador de aprovações pendentes
const aprovacaosPendentes = computed(() => {
  return aprovacaoStats.pendentes
})

// Computed para contador de aprovações rejeitadas
const aprovacoesRejeitadas = computed(() => {
  return aprovacaoStats.rejeitadas
})

// Computed para filtrar dossiês pela pasta selecionada
const dossiesDaPasta = computed(() => {
  if (!pastaSelecionadaDossie.value || !funcionarioSelecionadoDossie.value)
    return []

  // Filtra os dossiês do funcionário pela pasta selecionada
  return configDossie.value.dossiesIndividuais.filter(
    (dossie) => dossie.dossie_pasta_id == pastaSelecionadaDossie.value.id
  )
})

/**
 * Determina se o usuário logado pode agir na etapa atual do fluxo.
 *
 * Regra de visibilidade unificada (substitui as três expressões v-if duplicadas
 * sobre campos, campos do assunto e ações do fluxo):
 *
 *   modo 'E' (Exclusivo):  apenas o solicitante age
 *   modo 'S' (Permitir):   responsável e solicitante agem
 *   modo 'N' (Nenhum):     apenas o responsável age
 *
 * Retorna false quando o fluxo está pausado (aguardando_solicitante via
 * voltar_solicitante), concluído ou cancelado.
 */
const podeAgirNaEtapa = computed(() => {
  const fluxo = fluxoDados.value
  const sol = solicitacao.value
  if (!fluxo || !sol) return false

  if (fluxo.is_concluido || fluxo.is_cancelado) return false

  const matriculaLogada = props.auth?.matricula
  const matriculaSolicitante = sol.usuario_solicitante?.matricula
  const matriculaResponsavel = sol.usuario_responsavel?.matricula

  const ehSolicitante =
    matriculaSolicitante != null && matriculaSolicitante == matriculaLogada
  const ehResponsavel =
    !!sol.isDepartamento &&
    matriculaResponsavel != null &&
    matriculaResponsavel == matriculaLogada

  // Modo Exclusivo: apenas o solicitante pode agir.
  // Nesse modo, o próprio fluxo fica em status aguardando_solicitante,
  // mas diferente do mecanismo voltar_solicitante, a etapa é exclusiva
  // do solicitante por design e o fluxo avança para frente ao completar.
  if (fluxo.is_modo_exclusivo) {
    return ehSolicitante
  }

  // Para os modos 'N' e 'S', preservar a exclusão atual por aguardando_solicitante
  // (quando o fluxo foi devolvido ad-hoc pela ação voltar_solicitante).
  if (fluxo.is_aguardando_solicitante) return false

  // Modo 'S': permite responsável e solicitante.
  if (fluxo.permitir_solicitante_avancar) {
    return ehResponsavel || ehSolicitante
  }

  // Modo 'N': apenas o responsável.
  return ehResponsavel
})

// Função para atualizar a previsão de entrega
async function atualizarPrevisaoEntrega() {
  try {
    loading.value = true
    await axios.post("/solicitacoes/lista/atualizar-previsao-entrega", {
      solicitacao_id: props.solicitacao_id,
      previsao_entrega: previsaoEntrega.value || null
    })

    await buscarSolicitacao()
    editandoPrevisao.value = false
  } catch (error) {
    toastError(
      error.response?.data?.message || "Erro ao atualizar previsão de entrega"
    )
  } finally {
    loading.value = false
  }
}

// Função para iniciar a edição da previsão
function iniciarEdicaoPrevisao() {
  editandoPrevisao.value = true
  if (solicitacao.value.previsao_entrega) {
    previsaoEntrega.value = new Date(solicitacao.value.previsao_entrega)
  }
}

// Função para cancelar a edição da previsão
function cancelarEdicaoPrevisao() {
  editandoPrevisao.value = false
  previsaoEntrega.value = null
}

// Função para carregar dados das aprovações
async function carregarAprovacoes() {
  try {
    const response = await axios.get(
      `/solicitacoes/aprovacoes/${props.solicitacao_id}`
    )
    aprovacoes.value = response.data || []

    // Atualizar estatísticas
    aprovacaoStats.pendentes = 0
    aprovacaoStats.aprovadas = 0
    aprovacaoStats.rejeitadas = 0
    aprovacaoStats.canceladas = 0
    aprovacaoStats.total = aprovacoes.value.length

    aprovacoes.value.forEach((aprovacao) => {
      switch (aprovacao.status) {
        case "pendente":
          aprovacaoStats.pendentes++
          break
        case "aprovada":
          aprovacaoStats.aprovadas++
          break
        case "rejeitada":
          aprovacaoStats.rejeitadas++
          break
        case "cancelada":
          aprovacaoStats.canceladas++
          break
      }
    })
  } catch (error) {
    console.error("Erro ao carregar aprovações:", error)
    aprovacoes.value = []
    aprovacaoStats.pendentes = 0
    aprovacaoStats.aprovadas = 0
    aprovacaoStats.rejeitadas = 0
    aprovacaoStats.canceladas = 0
    aprovacaoStats.total = 0
  }
}

// Função para atualizar dados das aprovações quando o componente filho emitir
function atualizarAprovacoes(novasAprovacoes) {
  aprovacoes.value = novasAprovacoes || []

  // Atualizar estatísticas
  aprovacaoStats.pendentes = 0
  aprovacaoStats.aprovadas = 0
  aprovacaoStats.rejeitadas = 0
  aprovacaoStats.canceladas = 0
  aprovacaoStats.total = aprovacoes.value.length

  aprovacoes.value.forEach((aprovacao) => {
    switch (aprovacao.status) {
      case "pendente":
        aprovacaoStats.pendentes++
        break
      case "aprovada":
        aprovacaoStats.aprovadas++
        break
      case "rejeitada":
        aprovacaoStats.rejeitadas++
        break
      case "cancelada":
        aprovacaoStats.canceladas++
        break
    }
  })
}

function fechar() {
  emits("atualizar")
  emits("fecharDialogo")
}

async function buscarSolicitacao() {
  loading.value = true

  await axios
    .get("/solicitacoes/lista/solicitacao/" + props.solicitacao_id)
    .then((res) => {
      solicitacao.value = res.data

      // Atualizar a previsão de entrega se existir apenas na primeira vez
      if (res.data.previsao_entrega && !previsaoEntrega.value) {
        previsaoEntrega.value = new Date(res.data.previsao_entrega)
      }

      // Inicializar etapa selecionada
      etapaSelecionada.value = res.data.etapa_atual?.id || null

      // Carregar dados do fluxo/workflow
      carregarFluxo()
    })
    .catch((err) => {
      toastError()
    })

  loading.value = false
}

// ✅ Versão silenciosa para atualizações via Reverb (sem loading)
async function buscarSolicitacaoSilenciosa() {
  try {
    const res = await axios.get(
      "/solicitacoes/lista/solicitacao/" + props.solicitacao_id
    )
    solicitacao.value = res.data

    // Atualizar a previsão de entrega se existir apenas na primeira vez
    if (res.data.previsao_entrega && !previsaoEntrega.value) {
      previsaoEntrega.value = new Date(res.data.previsao_entrega)
    }

    // Atualizar etapa selecionada
    etapaSelecionada.value = res.data.etapa_atual?.id || null

    // Atualizar fluxo/workflow
    carregarFluxo()
  } catch (err) {
    console.error("[Ticket] Erro ao atualizar via Reverb:", err)
  }
}

// Watcher para verificar se o status da ticket mudou para "retorno solicitante"
watch(
  () => solicitacao.value && solicitacao.value.status,
  (novoStatus) => {
    if (novoStatus === "retorno solicitante" && solicitacao.value.isOwner) {
      aba.value = "acompanhar"
      showAtencaoRetorno.value = true
    }
  }
)

// Watcher para resetar a flag quando o dialog é fechado
watch(
  () => dialogMudarDepartamento.value,
  (novoValor) => {
    if (!novoValor) {
      // Dialog foi fechado, resetar a flag e limpar respostas
      trocaAssunto.value = false
      respostasSelects.value = []
    }
  }
)

// Limpar respostas dos selects quando o assunto mudar
watch(
  () => assuntoSelecionado.value,
  () => {
    respostasSelects.value = []
  }
)

async function getDeptosAtivo() {
  loading.value = true

  await axios
    .get("/solicitacoes/lista/buscar-departamentos")
    .then((res) => {
      deptos.value = res.data.departamentos
    })
    .catch((err) => {
      toastError(err.response.data.message)
    })
    .finally(() => {
      loading.value = false
    })
}

async function trocarAba(novaAba) {
  if (novaAba === "agendamento") {
    await buscarAgendamentos()
  } else if (novaAba === "aprovacoes") {
    await carregarAprovacoes()
  }

  aba.value = novaAba
}

function baixarArquivo(id) {
  downloadFile(id)
}

function getBorderPrioridade() {
  switch (solicitacao.value.prioridade) {
    case "baixa":
      return "border-gray-800 text-gray-800"
    case "media":
      return "border-blue-800 text-blue-800"
    case "alta":
      return "border-yellow-800 text-yellow-800"
    case "urgente":
      return "border-red-800 text-red-800"
    default:
      break
  }
}

function showDialogMudarPrioridae() {
  prioridadeSelecionada.value = solicitacao.value.prioridade
  dialogMudarPrioridade.value = true
}

async function mudarPrioridade() {
  const response = await swalConfirm(
    "",
    "Deseja realmente trocar a prioridade do chamado ?"
  )
  if (response.isConfirmed) {
    const params = {
      solicitacao: solicitacao.value,
      novaPrioridade: prioridadeSelecionada.value
    }

    await axios
      .post("/solicitacoes/lista/mudar-prioridade", params)
      .then((res) => {
        buscarSolicitacao()
        dialogMudarPrioridade.value = false
      })
      .catch((err) => {
        toastError("Erro ao alterar prioridade!")
      })
  }
}

// ========== FUNÇÕES PARA ETAPAS DE ANDAMENTO ==========

function obterCorEtapa(etapaId) {
  const etapa = solicitacao.value?.etapas_disponiveis?.find(
    (e) => e.id === etapaId
  )
  return etapa?.cor || "#3B82F6"
}

function obterNomeEtapa(etapaId) {
  const etapa = solicitacao.value?.etapas_disponiveis?.find(
    (e) => e.id === etapaId
  )
  return etapa?.nome || "Não definida"
}

async function alterarEtapa() {
  if (!etapaSelecionada.value) return

  const etapa = solicitacao.value?.etapas_disponiveis?.find(
    (e) => e.id === etapaSelecionada.value
  )
  if (!etapa) return

  try {
    loading.value = true
    const { data } = await axios.post("/solicitacoes/lista/alterar-etapa", {
      solicitacao_id: solicitacao.value.id,
      etapa_id: etapaSelecionada.value
    })

    if (data.success) {
      toastSuccess("Etapa alterada com sucesso!")
      await buscarSolicitacao()
    } else {
      toastError(data.message || "Erro ao alterar etapa")
    }
  } catch (error) {
    console.error("Erro ao alterar etapa:", error)
    toastError(error.response?.data?.message || "Erro ao alterar etapa")
  } finally {
    loading.value = false
  }
}

// ========== FUNÇÕES PARA FLUXO/WORKFLOW ==========

async function carregarFluxo() {
  if (!solicitacao.value?.id) return
  loadingFluxo.value = true
  try {
    const { data } = await axios.get(
      `/solicitacoes/lista/fluxo-solicitacao/${solicitacao.value.id}`
    )
    fluxoDados.value = data
    // Inicializar valores dos campos da etapa atual
    const vals = {}
    if (data?.campos_etapa) {
      data.campos_etapa.forEach((campo) => {
        const valorExistente = data.valores_campos?.[campo.id]
        vals[campo.id] = valorExistente?.valor ?? ""
      })
    }
    valoresCamposFluxo.value = vals
    // Limpar respostas dos selects do assunto ao recarregar fluxo
    respostasSelectsFluxo.value = []
  } catch (error) {
    console.error("Erro ao carregar fluxo:", error)
    fluxoDados.value = null
  } finally {
    loadingFluxo.value = false
  }
}

// Adiciona/atualiza resposta de um campo do assunto no contexto do fluxo
function addRespostaFluxo(select, valor) {
  const tiposWinthor = [
    "depto_compras",
    "depto_funcionario",
    "filial_winthor",
    "funcao",
    "regional"
  ]

  const idx = respostasSelectsFluxo.value.findIndex(
    (r) => r.selecao_id === select.id
  )

  let resposta
  if (select.tipo === "selecao" || tiposWinthor.includes(select.tipo)) {
    resposta = valor
  } else if (select.tipo === "data") {
    let datas
    if (select.tipo_data === "range") {
      datas = valor?.map((v) => (v ? v.toISOString().split("T")[0] : null))
    } else {
      datas = [valor ? valor.toISOString().split("T")[0] : null]
    }
    resposta = { datas: datas }
  } else if (
    select.tipo === "texto" ||
    select.tipo === "numero" ||
    select.tipo === "cnpj"
  ) {
    resposta = valor
  }

  const novaResposta = {
    assunto_id: solicitacao.value.assunto_id,
    selecao_id: select.id,
    tipo: select.tipo,
    resposta: resposta
  }

  if (idx !== -1) {
    respostasSelectsFluxo.value[idx] = novaResposta
  } else {
    respostasSelectsFluxo.value.push(novaResposta)
  }
}

// #12173 - Verifica se um campo condicional deve ser exibido (contexto: fluxo/workflow)
function campoDeveSerExibidoFluxo(select) {
  if (!select.campo_pai_id) return true

  const campoPaiId = Number(select.campo_pai_id)
  const campoPai = fluxoDados.value?.campos_assunto?.find(
    (s) => Number(s.id) === campoPaiId
  )

  const respostaPai = respostasSelectsFluxo.value.find(
    (r) => Number(r.selecao_id) === campoPaiId
  )

  if (!respostaPai) return false

  const valorResposta = respostaPai.resposta

  // Array de codes numéricos (MultiSelect) — resolver labels via opções do pai
  if (Array.isArray(valorResposta)) {
    if (campoPai?.valores) {
      return valorResposta.some((code) => {
        const opcao = campoPai.valores.find(
          (v) => v.code === code || Number(v.code) === Number(code)
        )
        return opcao?.label === select.valor_condicional
      })
    }
    return valorResposta.some(
      (v) => (typeof v === "string" ? v : v?.label) === select.valor_condicional
    )
  }

  // Objeto {code, label}
  if (typeof valorResposta === "object" && valorResposta !== null) {
    if (valorResposta.label) {
      return valorResposta.label === select.valor_condicional
    }
  }

  // Code numérico — resolver label
  if (campoPai?.valores && Array.isArray(campoPai.valores)) {
    const opcaoSelecionada = campoPai.valores.find(
      (v) =>
        v.code === valorResposta || Number(v.code) === Number(valorResposta)
    )
    if (opcaoSelecionada) {
      return opcaoSelecionada.label === select.valor_condicional
    }
  }

  return valorResposta === select.valor_condicional
}

async function avancarFluxo() {
  try {
    loading.value = true
    // Salvar campos antes de avançar
    const camposSalvos = await salvarCamposFluxo()
    if (!camposSalvos) {
      loading.value = false
      return
    }
    const { data } = await axios.post("/solicitacoes/lista/avancar-fluxo", {
      solicitacao_id: solicitacao.value.id,
      observacao: observacaoFluxo.value || null,
      respostas_selects: respostasSelectsFluxo.value
    })
    if (data.success) {
      observacaoFluxo.value = ""
      respostasSelectsFluxo.value = []
      await buscarSolicitacao()
      await carregarFluxo()
    } else {
      toastError(data.message)
    }
  } catch (error) {
    toastError(error.response?.data?.message || "Erro ao avançar fluxo")
  } finally {
    loading.value = false
  }
}

async function voltarFluxo() {
  try {
    loading.value = true
    const { data } = await axios.post("/solicitacoes/lista/voltar-fluxo", {
      solicitacao_id: solicitacao.value.id,
      observacao: observacaoFluxo.value || null
    })
    if (data.success) {
      observacaoFluxo.value = ""
      await buscarSolicitacao()
      await carregarFluxo()
    } else {
      toastError(data.message)
    }
  } catch (error) {
    toastError(error.response?.data?.message || "Erro ao voltar fluxo")
  } finally {
    loading.value = false
  }
}

async function salvarCamposFluxo() {
  if (!fluxoDados.value?.campos_etapa?.length) return true
  try {
    salvandoCampos.value = true
    const campos = fluxoDados.value.campos_etapa.map((c) => ({
      etapa_campo_id: c.id,
      valor: valoresCamposFluxo.value[c.id] ?? ""
    }))
    await axios.post("/solicitacoes/lista/salvar-campos-fluxo", {
      solicitacao_id: solicitacao.value.id,
      campos
    })
    return true
  } catch (error) {
    toastError("Erro ao salvar campos da etapa")
    return false
  } finally {
    salvandoCampos.value = false
  }
}

// ========== UPLOAD DE ARQUIVO PARA CAMPO TIPO ARQUIVO ==========
const uploadingCampoArquivo = ref({})
const CAMPO_ARQUIVO_MAX_MB = 10
const CAMPO_ARQUIVO_MAX_BYTES = CAMPO_ARQUIVO_MAX_MB * 1024 * 1024
const CAMPO_ARQUIVO_EXTENSOES = [
  "pdf",
  "doc",
  "docx",
  "xls",
  "xlsx",
  "ppt",
  "pptx",
  "txt",
  "csv",
  "jpg",
  "jpeg",
  "png",
  "gif",
  "webp"
]
const CAMPO_ARQUIVO_ACCEPT = CAMPO_ARQUIVO_EXTENSOES.map((e) => `.${e}`).join(
  ","
)

async function handleCampoArquivoUpload(event, campoId) {
  const file = event.target.files?.[0]
  if (!file) return

  const ext = file.name.split(".").pop()?.toLowerCase()
  if (!ext || !CAMPO_ARQUIVO_EXTENSOES.includes(ext)) {
    toastError(
      `Formato não permitido. Aceitos: ${CAMPO_ARQUIVO_EXTENSOES.join(", ")}`
    )
    event.target.value = ""
    return
  }

  if (file.size > CAMPO_ARQUIVO_MAX_BYTES) {
    toastError(`Arquivo excede o limite de ${CAMPO_ARQUIVO_MAX_MB}MB`)
    event.target.value = ""
    return
  }

  uploadingCampoArquivo.value[campoId] = true
  try {
    const result = await uploadFile(
      file,
      "solicitacoes",
      "campos-fluxo",
      null,
      props.auth
    )
    if (result.success) {
      valoresCamposFluxo.value[campoId] = JSON.stringify({
        file_id: result.data.file.id,
        file_name: file.name
      })
      toastSuccess("Arquivo enviado com sucesso")
    } else {
      toastError(result.message || "Erro ao enviar arquivo")
    }
  } catch (error) {
    toastError("Erro ao enviar arquivo")
  } finally {
    uploadingCampoArquivo.value[campoId] = false
    event.target.value = ""
  }
}

function removerCampoArquivo(campoId) {
  const valorAtual = valoresCamposFluxo.value[campoId]
  if (valorAtual) {
    try {
      const parsed = JSON.parse(valorAtual)
      if (parsed.file_id) {
        deleteFile(parsed.file_id).catch(() => {})
      }
    } catch {}
  }
  valoresCamposFluxo.value[campoId] = ""
}

function parseCampoArquivo(valor) {
  if (!valor) return null
  try {
    const parsed = JSON.parse(valor)
    if (parsed.file_id) return parsed
  } catch {}
  return null
}

function visualizarCampoArquivo(valor) {
  const parsed = parseCampoArquivo(valor)
  if (!parsed) return
  fileId.value = parsed.file_id
  listaIdsFile.value = [parsed.file_id]
  viewFileDialog.value = true
}

async function decidirFluxo(decisaoId) {
  try {
    // Se a decisão é voltar_solicitante, pedir motivo
    const decisao = fluxoDados.value?.decisoes?.find((d) => d.id === decisaoId)
    let observacao = null

    if (decisao?.acao === "voltar_solicitante") {
      const result = await swalInput(
        "Devolver ao Solicitante",
        "Informe o motivo do retorno para o solicitante",
        "Ex: Campo X está incorreto, favor corrigir...",
        "Enviar",
        "Cancelar",
        { inputType: "textarea", required: false }
      )
      if (!result.isConfirmed) return
      observacao = result.value || null
    } else if (decisao?.acao === "cancelar") {
      const result = await swalInput(
        "Cancelar Fluxo",
        "Informe o motivo do cancelamento",
        "Ex: Ticket duplicado, não procede...",
        "Confirmar",
        "Voltar",
        { inputType: "textarea", required: false }
      )
      if (!result.isConfirmed) return
      observacao = result.value || null
    } else if (decisao?.acao === "abrir_solicitacao") {
      const result = await swalConfirm(
        "Abrir Ticket Vinculada",
        "Será criada uma nova ticket vinculada a esta. Deseja continuar?"
      )
      if (!result.isConfirmed) return
    } else if (decisao?.acao === "atribuir_avancar") {
      decisaoAtribuirDevId.value = decisaoId
      devSelecionado.value = null
      deptoAtribuirDev.value =
        fluxoDados.value?.etapas?.find((e) => e.id === decisao.etapa_destino_id)
          ?.departamento || null
      dialogAtribuirDev.value = true
      return
    }

    // Verificar se a decisão tem campos vinculados
    const camposDecisao = fluxoDados.value?.campos_etapa?.filter(
      (c) => c.decisao_id === decisaoId
    )
    if (camposDecisao?.length) {
      camposDecisao.forEach((c) => {
        if (valoresCamposFluxo.value[c.id] === undefined) {
          valoresCamposFluxo.value[c.id] = ""
        }
      })
      camposDecisaoAtual.value = camposDecisao
      decisaoPendenteId.value = decisaoId
      decisaoPendenteObs.value = observacao
      dialogCamposDecisao.value = true
      return
    }

    loading.value = true
    // Salvar campos antes de decidir
    const camposSalvos = await salvarCamposFluxo()
    if (!camposSalvos) {
      loading.value = false
      return
    }
    const { data } = await axios.post("/solicitacoes/lista/decidir-fluxo", {
      solicitacao_id: solicitacao.value.id,
      decisao_id: decisaoId,
      observacao: observacao,
      respostas_selects: respostasSelectsFluxo.value
    })
    if (data.success) {
      observacaoFluxo.value = ""
      respostasSelectsFluxo.value = []
      dialogDecisaoFluxo.value = false
      await buscarSolicitacao()
      await carregarFluxo()
    } else {
      toastError(data.message)
    }
  } catch (error) {
    toastError(error.response?.data?.message || "Erro ao processar decisão")
  } finally {
    loading.value = false
  }
}

async function confirmarAtribuirDev() {
  if (!devSelecionado.value) {
    toastError("Selecione um responsável antes de continuar")
    return
  }

  try {
    loading.value = true
    dialogAtribuirDev.value = false

    const camposSalvos = await salvarCamposFluxo()
    if (!camposSalvos) {
      loading.value = false
      return
    }

    const matricula = devSelecionado.value?.matricula || devSelecionado.value
    const { data } = await axios.post("/solicitacoes/lista/decidir-fluxo", {
      solicitacao_id: solicitacao.value.id,
      decisao_id: decisaoAtribuirDevId.value,
      observacao: observacaoFluxo.value || null,
      responsavel_matricula: matricula,
      respostas_selects: respostasSelectsFluxo.value
    })

    if (data.success) {
      observacaoFluxo.value = ""
      respostasSelectsFluxo.value = []
      dialogDecisaoFluxo.value = false
      devSelecionado.value = null
      decisaoAtribuirDevId.value = null
      await buscarSolicitacao()
      await carregarFluxo()
    } else {
      toastError(data.message)
    }
  } catch (error) {
    toastError(error.response?.data?.message || "Erro ao atribuir responsável")
  } finally {
    loading.value = false
  }
}

async function confirmarCamposDecisao() {
  // Validar campos obrigatórios da decisão
  for (const campo of camposDecisaoAtual.value) {
    if (campo.obrigatorio === "S") {
      const val = valoresCamposFluxo.value[campo.id]
      if (val === undefined || val === null || val === "") {
        toastError(`Preencha o campo obrigatório: ${campo.label}`)
        return
      }
    }
  }

  try {
    loading.value = true
    dialogCamposDecisao.value = false

    const camposSalvos = await salvarCamposFluxo()
    if (!camposSalvos) {
      loading.value = false
      return
    }

    const { data } = await axios.post("/solicitacoes/lista/decidir-fluxo", {
      solicitacao_id: solicitacao.value.id,
      decisao_id: decisaoPendenteId.value,
      observacao: decisaoPendenteObs.value || null,
      respostas_selects: respostasSelectsFluxo.value
    })

    if (data.success) {
      observacaoFluxo.value = ""
      respostasSelectsFluxo.value = []
      dialogDecisaoFluxo.value = false
      camposDecisaoAtual.value = []
      decisaoPendenteId.value = null
      decisaoPendenteObs.value = null
      await buscarSolicitacao()
      await carregarFluxo()
    } else {
      toastError(data.message)
    }
  } catch (error) {
    toastError(error.response?.data?.message || "Erro ao processar decisão")
  } finally {
    loading.value = false
  }
}

async function devolverAoFluxo() {
  try {
    loading.value = true
    // Salvar campos antes de reenviar
    const camposSalvos = await salvarCamposFluxo()
    if (!camposSalvos) {
      loading.value = false
      return
    }
    const { data } = await axios.post("/solicitacoes/lista/devolver-ao-fluxo", {
      solicitacao_id: solicitacao.value.id,
      observacao: observacaoFluxo.value || null
    })
    if (data.success) {
      observacaoFluxo.value = ""
      await buscarSolicitacao()
      await carregarFluxo()
    } else {
      toastError(data.message)
    }
  } catch (error) {
    toastError(error.response?.data?.message || "Erro ao devolver ao fluxo")
  } finally {
    loading.value = false
  }
}

async function mudarResponsavel() {
  const response = await swalConfirm(
    "Deseja realmente mudar o responsavel pelo chamado ?",
    "Os agendamentos atrelados a essa ticket também terão seus responsáveis alterados."
  )

  if (response.isConfirmed) {
    const params = {
      solicitacao: solicitacao.value,
      responsavel: responsavelSelecionado.value
    }

    await axios
      .post("/solicitacoes/lista/mudar-responsavel", params)
      .then((res) => {
        dialogTrocarResponsavel.value = false
        buscarSolicitacao()
      })
      .catch((err) => {
        toastError("Erro ao alterar responsável!")
      })
  }
}

async function removerResponsavel() {
  const response = await swalConfirm(
    "",
    "Deseja realmente remover o responsavel pelo chamado ?"
  )
  if (response.isConfirmed) {
    const params = {
      solicitacao: solicitacao.value,
      responsavel: null
    }

    await axios
      .post("/solicitacoes/lista/mudar-responsavel", params)
      .then((res) => {
        dialogTrocarResponsavel.value = false
        buscarSolicitacao()

        pausarAtendimento()
      })
      .catch((err) => {
        toastError("Erro ao remover responsável!")
      })
  }
}

async function seAtribuir() {
  const response = await swalConfirm(
    "",
    "Deseja realmente se atribuir como responsavel pelo chamado ?"
  )
  if (response.isConfirmed) {
    const params = {
      solicitacao: solicitacao.value,
      responsavel: null,
      seAtribuir: true
    }

    await axios
      .post("/solicitacoes/lista/mudar-responsavel", params)
      .then((res) => {
        dialogTrocarResponsavel.value = false
        buscarSolicitacao()
      })
      .catch((err) => {
        toastError("Erro ao se atribuir como responsável!")
      })
  }
}

function showDialogTrocarResponsavel() {
  if (solicitacao.value.usuario_responsavel) {
    responsavelSelecionado.value =
      solicitacao.value.usuario_responsavel.matricula
  }

  dialogTrocarResponsavel.value = true
}

function abrirAgendamento() {
  dialogAgendamento.value = true
  editAgendamento.value = false
}

async function comentar(tipo) {
  if (enviandoComentario.value) return // Bloqueia se já está enviando
  enviandoComentario.value = true

  // Validar tamanho dos arquivos antes de fazer upload (limite: 50MB)
  const TAMANHO_MAXIMO = 50 * 1024 * 1024 // 50MB em bytes
  const arquivosGrandes = []

  for (let arquivo of arquivosComentario.value) {
    if (arquivo.file && arquivo.file.size > TAMANHO_MAXIMO) {
      const tamanhoMB = (arquivo.file.size / (1024 * 1024)).toFixed(2)
      arquivosGrandes.push(`"${arquivo.file.name}" (${tamanhoMB} MB)`)
    }
  }

  if (arquivosGrandes.length > 0) {
    toastError(
      `Arquivo muito grande! Máximo 50 MB. Arquivos: ${arquivosGrandes.join(", ")}`
    )
    enviandoComentario.value = false
    return
  }

  var responseFile = null

  for (let arquivo of arquivosComentario.value) {
    // Zerar variável para não pegar lixo
    responseFile = null

    // Criar um nome aleatório para não sobrescrever
    const sufixoAleatorio = Math.floor(Math.random() * 1000)
    const nome = "solicitacao_com_arq_" + sufixoAleatorio

    // Salvar arquivo
    responseFile = await uploadFile(
      arquivo.file,
      "intranet",
      "solicitacao-com-arquivos",
      nome
    )

    if (responseFile.success) {
      arquivo.fileTab = responseFile.data.file
    } else {
      toastError(
        `Não foi possível enviar o arquivo "${arquivo.file?.name || "desconhecido"}". Verifique o tamanho e tente novamente.`
      )
      enviandoComentario.value = false
      return
    }
  }

  if (
    solicitacao.value.usuario_solicitante.matricula == props.auth.matricula &&
    solicitacao.value.status == "retorno solicitante"
  ) {
    solicitacao.value.status = "atendimento pausado"
  }

  const params = {
    solicitacao: solicitacao.value,
    comentario: comentario.value,
    arquivos: arquivosComentario.value,
    private: privateComment.value,
    privateType: privateType.value
  }

  await axios
    .post("/solicitacoes/lista/comentar", params)
    .then((res) => {
      comentario.value = ""
      arquivosComentario.value = []
      dialogAnexos.value = false
      privateComment.value = false
      privateType.value = null
      buscarSolicitacao()
    })
    .catch((err) => {
      toastError("Erro ao comentar na ticket!")

      // Excluir arquivos se der algum erro, pra não ficar lixo no sistema
      for (let arquivo of arquivosComentario.value) {
        if (arquivo.fileTab) {
          deleteFile(arquivo.fileTab.id)
        }
      }
    })
    .finally(() => {
      enviandoComentario.value = false
    })
}

const excluindoComentario = ref(false)

async function excluirComentario(comentarioId) {
  const result = await swalConfirm(
    "Excluir comentário",
    "Tem certeza que deseja excluir este comentário? Esta ação não pode ser desfeita.",
    "Excluir",
    "Cancelar",
    { danger: true, icon: "trash" }
  )
  if (!result.isConfirmed) return

  excluindoComentario.value = true
  try {
    await axios.delete(`/solicitacoes/lista/comentario/${comentarioId}`)
    toastSuccess("Comentário excluído com sucesso!")
    buscarSolicitacaoSilenciosa()
  } catch (error) {
    const msg = error.response?.data?.message || "Erro ao excluir comentário."
    toastError(msg)
  } finally {
    excluindoComentario.value = false
  }
}

function showDialogAnexos() {
  arquivosComentario.value = []
  dialogAnexos.value = true
}

function atualizarAnexos(arquivos) {
  arquivosComentario.value = arquivos
}

function showDadosLiberacao(dadosLiberacao) {
  dadosLiberacaoSelecionado.value = dadosLiberacao
  dialogDadosLiberacao.value = true
}

function verCaixasPendentes(venda) {
  vendasPendentesSelecionada.value = venda
  dialogoCaixaVenda.value = true
}

async function iniciarAtendimento() {
  // Verificar se há problemas com aprovações antes de iniciar o atendimento
  if (temProblemasAprovacao.value) {
    const continuar = await swalConfirm(
      "Atenção!",
      `Existem aprovações pendentes ou rejeitadas nesta ticket.\nDeseja iniciar o atendimento mesmo assim?`,
      "Sim, iniciar",
      "Cancelar"
    )

    if (!continuar.isConfirmed) {
      return
    }
  }

  const params = {
    solicitacao: solicitacao.value
  }

  await axios
    .post("/solicitacoes/lista/iniciar-atendimento", params)
    .then((res) => {
      buscarSolicitacao()
    })
    .catch((err) => {
      toastError("Erro ao iniciar atendimento!")
    })
}

async function pausarAtendimento() {
  const params = {
    solicitacao: solicitacao.value
  }

  await axios
    .post("/solicitacoes/lista/pausar-atendimento", params)
    .then((res) => {
      buscarSolicitacao()
    })
    .catch((err) => {
      toastError("Erro ao pausar atendimento!")
    })
}

async function resolverAtendimento() {
  // Verificar se há aprovações pendentes ou rejeitadas
  if (temProblemasAprovacao.value) {
    const mensagemAlerta = `Esta ticket possui ${mensagemAlerteAprovacao.value}.\n\nDeseja resolver mesmo assim?`

    const resp = await swalConfirm(
      "Atenção: Aprovações Pendentes",
      mensagemAlerta
    )

    if (!resp.isConfirmed) {
      return
    }
  } else {
    const resp = await swalConfirm("Deseja resolver essa ticket ?")

    if (!resp.isConfirmed) {
      return
    }
  }

  const params = {
    solicitacao: solicitacao.value,
    comentario: comentario.value
  }

  await axios
    .post("/solicitacoes/lista/resolver-atendimento", params)
    .then((res) => {
      buscarSolicitacao()
      dialogResolver.value = false
      comentario.value = ""
    })
    .catch((err) => {
      toastError("Erro ao resolver ticket!")
    })
}

// function showDialogResolver() {
//     comentario.value = '';
//     dialogResolver.value = true;
// }

async function resolver() {
  if (isRecusada.value) {
    dialogResolver.value = true
  } else {
    resolverAtendimento()
  }
}
async function finalizar() {
  const params = {
    solicitacao: solicitacao.value
  }

  await axios
    .post("/solicitacoes/lista/finalizar-atendimento", params)
    .then((res) => {
      dialogResolver.value = false
      buscarSolicitacao()
    })
    .catch((err) => {
      toastError("Erro ao finalizar ticket!")
    })
}

function showDialogRecusar() {
  comentario.value = ""
  dialogRecusar.value = true
}

async function recusarAtendimento() {
  const params = {
    solicitacao: solicitacao.value,
    comentario: comentario.value
  }

  await axios
    .post("/solicitacoes/lista/recusar-atendimento", params)
    .then((res) => {
      buscarSolicitacao()
      dialogRecusar.value = false
      comentario.value = ""
    })
    .catch((err) => {
      toastError("Erro ao recusar ticket!")
    })
}

async function showDialogCancelar() {
  const resposta = await swalConfirm(
    "Você tem certeza?",
    "Os agendamentos atrelados a essa ticket também serão cancelados."
  )
  if (!resposta.isConfirmed) {
    return
  }

  comentario.value = ""
  dialogoCancelar.value = true
}

async function cancelarAtendimento() {
  const params = {
    solicitacao: solicitacao.value,
    comentario: comentario.value
  }

  await axios
    .post("/solicitacoes/lista/cancelar-atendimento", params)
    .then((res) => {
      buscarSolicitacao()
      buscarAgendamentos()
      dialogoCancelar.value = false
      comentario.value = ""
    })
    .catch((err) => {
      toastError("Erro ao cancelar ticket!")
    })
}

async function buscarAgendamentos() {
  loading.value = true
  await axios
    .get("/solicitacoes/agendamento/buscar-agendamento/" + props.solicitacao_id)
    .then((res) => {
      agendamentos.value = res.data
      existeAnexo.value = agendamentos.value.some(
        (ag) => ag.anexo && ag.anexo.length > 0
      )
      agendamentos.value.forEach(async (item) => {
        if (item.anexo) {
          item.anexo.forEach(async (anexo) => {
            anexo.userName = await getUsuarioById(anexo.user_id)
          })
        }
        if (item.imagem_assinatura) {
          item.imagem_assinatura.user_assinatura = await getUsuarioById(
            item.imagem_assinatura.user_id
          )
        }
      })
    })
    .catch((e) => {
      console.error(e)
      toastError()
    })
  loading.value = false
}

async function atualizaAgendamentos() {
  dialogAgendamento.value = false
  await buscarSolicitacao()
  await buscarAgendamentos()
}

async function atualizaLembrete() {
  dialogLembrete.value = false
  lembreteEdicao.value = null // Limpa o lembrete de edição
  dialogDetalhesLembrete.value = false // Fecha o dialog de detalhes se estiver aberto
  await buscarSolicitacao()
  await buscarAgendamentos()
}

function abrirLembrete() {
  lembreteEdicao.value = null // Limpa para criar novo
  dialogLembrete.value = true
}

function editarLembrete() {
  lembreteEdicao.value = agendamentoSelecionado.value
  dialogDetalhesLembrete.value = false
  dialogLembrete.value = true
}

function cancelarLembreteEFechar() {
  cancelarAgendamento(agendamentoSelecionado.value.id)
  dialogDetalhesLembrete.value = false
}

async function redirectMaps(rota) {
  if (isFlutter.value) {
    const rotas = await window.flutter_inappwebview.callHandler("Rotas", rota)
  } else {
    window.open(rota, "_blank")
  }
}

async function editarAgendamento() {
  editAgendamento.value = true
  dialogAgendamento.value = true
}

async function cancelarAgendamento(idAgendamento) {
  var params = {
    id: idAgendamento,
    solicitacao_id: props.solicitacao_id
  }

  await axios
    .post("/solicitacoes/agendamento/cancelar-agendamento", params)
    .then(async (res) => {
      await buscarSolicitacao()
      await buscarAgendamentos()
    })
    .catch((e) => {
      console.error(e)
      toastError("Erro ao cancelar agendamento!")
    })
}

function getClassPrioridade(prioridade) {
  switch (prioridade) {
    case "baixa":
      return "text-gray-500"
    case "media":
      return "text-blue-700"
    case "alta":
      return "text-yellow-600"
    case "urgente":
      return "text-red-600"
  }
}

function detalharAgendamento(agendamento) {
  agendamentoSelecionado.value = agendamento

  // Se for lembrete, abre dialog simples
  if (agendamento.tipo === "lembrete") {
    dialogDetalhesLembrete.value = true
  } else {
    // Se for visita, abre o dialog completo de detalhes
    dialogDetalhes.value = true
  }
}

async function atualizaAgendamento(vAgendamento) {
  if (solicitacao.value.agendamentos[0].id == vAgendamento.id) {
    solicitacao.value.agendamentos[0] = vAgendamento
  }

  agendamentoSelecionado.value = vAgendamento
}

async function updateDetalhes() {
  await buscarAgendamentos()
  await buscarSolicitacao()
}

function setBackground(status) {
  switch (status) {
    case "aguardando":
      return "bg-yellow-500 text-white"
    case "em atendimento":
      return "bg-blue-500 animate-pulse text-white"
    case "cancelado":
      return "bg-red-500/80 text-white"
    case "finalizado":
      return "bg-green-500/90 text-white"
  }
}

// Função para obter estilos da etapa baseados na cor configurada
function getEstilosEtapa(movimentacao) {
  const dadosExtras = movimentacao?.dados_extras
  if (!dadosExtras?.etapa_cor) {
    return null
  }

  const cor = dadosExtras.etapa_cor
  const icone = dadosExtras.etapa_icone || "pi pi-sitemap"

  return {
    icon: icone,
    cor: cor,
    badge: `border`,
    ribbon: "",
    customStyle: true
  }
}

// Computed: converte movimentações de Tickets para formato eventosExternos do componente LinhaTempo
// Backend retorna movimentações em id DESC (mais recente primeiro).
// LinhaTempo faz .reverse() internamente, então enviamos em ASC para que o resultado final seja DESC (mais recente no topo).
const eventosTimeline = computed(() => {
  if (!solicitacao.value?.movimentacoes?.length) return []
  return [...solicitacao.value.movimentacoes].reverse().map((mov) => {
    const info = getIconeMovimentacao(mov.tipo_movimentacao, mov)
    const corHex = info.customCor || corTailwindParaHex(info)
    const dataObj = mov.created_at ? new Date(mov.created_at) : null
    return {
      status: mov.tipo_movimentacao,
      data: dataObj ? formatarDataSemHoras(mov.created_at) : "",
      hora: dataObj
        ? dataObj.toLocaleTimeString("pt-BR", {
            hour: "2-digit",
            minute: "2-digit"
          })
        : "",
      descricao: mov.descricao || "",
      icon: info.icon || "pi pi-circle",
      color: corHex
    }
  })
})

function corTailwindParaHex(info) {
  const classe = info.dot || info.bgLine || ""
  const mapa = {
    emerald: "#10b981",
    blue: "#3b82f6",
    amber: "#f59e0b",
    red: "#ef4444",
    green: "#22c55e",
    teal: "#14b8a6",
    rose: "#f43f5e",
    purple: "#a855f7",
    indigo: "#6366f1",
    cyan: "#06b6d4",
    orange: "#f97316",
    violet: "#8b5cf6",
    fuchsia: "#d946ef",
    lime: "#84cc16",
    sky: "#0ea5e9",
    gray: "#6b7280"
  }
  for (const [nome, hex] of Object.entries(mapa)) {
    if (classe.includes(nome)) return hex
  }
  return "#6366f1"
}

// Função para obter ícone e cores da movimentação no histórico
function getIconeMovimentacao(tipo, movimentacao = null) {
  const tipoLower = tipo?.toLowerCase() || ""

  // Mapeamento de tipos para ícones e cores
  if (
    tipoLower.includes("criada") ||
    tipoLower.includes("criado") ||
    tipoLower.includes("ticket criada")
  ) {
    return {
      icon: "fas fa-plus",
      bg: "bg-gradient-to-br from-emerald-500 to-emerald-600",
      badge: "bg-emerald-50 text-emerald-700 border border-emerald-200",
      border: "border-emerald-500",
      dot: "bg-emerald-500",
      ribbon: "bg-gradient-to-r from-emerald-500 to-emerald-600",
      bgLine: "bg-emerald-500"
    }
  }
  if (
    tipoLower.includes("inicio") ||
    tipoLower.includes("início") ||
    tipoLower.includes("iniciado") ||
    tipoLower.includes("iniciar")
  ) {
    return {
      icon: "fas fa-play",
      bg: "bg-gradient-to-br from-blue-500 to-blue-600",
      badge: "bg-blue-50 text-blue-700 border border-blue-200",
      border: "border-blue-500",
      dot: "bg-blue-500",
      ribbon: "bg-gradient-to-r from-blue-500 to-blue-600",
      bgLine: "bg-blue-500"
    }
  }
  if (
    tipoLower.includes("pausado") ||
    tipoLower.includes("pausar") ||
    tipoLower.includes("pausa")
  ) {
    return {
      icon: "fas fa-pause",
      bg: "bg-gradient-to-br from-amber-500 to-amber-600",
      badge: "bg-amber-50 text-amber-700 border border-amber-200",
      border: "border-amber-500",
      dot: "bg-amber-500",
      ribbon: "bg-gradient-to-r from-amber-500 to-amber-600",
      bgLine: "bg-amber-500"
    }
  }
  if (
    tipoLower.includes("cancelado") ||
    tipoLower.includes("cancelar") ||
    tipoLower.includes("cancelamento")
  ) {
    return {
      icon: "fas fa-times",
      bg: "bg-gradient-to-br from-red-500 to-red-600",
      badge: "bg-red-50 text-red-700 border border-red-200",
      border: "border-red-500",
      dot: "bg-red-500",
      ribbon: "bg-gradient-to-r from-red-500 to-red-600",
      bgLine: "bg-red-500"
    }
  }
  if (
    tipoLower.includes("recusado") ||
    tipoLower.includes("recusada") ||
    tipoLower.includes("recusar") ||
    tipoLower.includes("rejeitado") ||
    tipoLower.includes("rejeitada")
  ) {
    return {
      icon: "fas fa-ban",
      bg: "bg-gradient-to-br from-rose-500 to-rose-600",
      badge: "bg-rose-50 text-rose-700 border border-rose-200",
      border: "border-rose-500",
      dot: "bg-rose-500",
      ribbon: "bg-gradient-to-r from-rose-500 to-rose-600",
      bgLine: "bg-rose-500"
    }
  }
  if (
    tipoLower.includes("finalizado") ||
    tipoLower.includes("finalizar") ||
    tipoLower.includes("concluído") ||
    tipoLower.includes("concluido")
  ) {
    return {
      icon: "fas fa-check",
      bg: "bg-gradient-to-br from-green-500 to-green-600",
      badge: "bg-green-50 text-green-700 border border-green-200",
      border: "border-green-500",
      dot: "bg-green-500",
      ribbon: "bg-gradient-to-r from-green-500 to-green-600",
      bgLine: "bg-green-500"
    }
  }
  if (
    tipoLower.includes("resolvido") ||
    tipoLower.includes("resolver") ||
    tipoLower.includes("resolução")
  ) {
    return {
      icon: "fas fa-check-double",
      bg: "bg-gradient-to-br from-teal-500 to-teal-600",
      badge: "bg-teal-50 text-teal-700 border border-teal-200",
      border: "border-teal-500",
      dot: "bg-teal-500",
      ribbon: "bg-gradient-to-r from-teal-500 to-teal-600",
      bgLine: "bg-teal-500"
    }
  }
  if (tipoLower.includes("agendamento")) {
    return {
      icon: "fas fa-calendar-alt",
      bg: "bg-gradient-to-br from-purple-500 to-purple-600",
      badge: "bg-purple-50 text-purple-700 border border-purple-200",
      border: "border-purple-500",
      dot: "bg-purple-500",
      ribbon: "bg-gradient-to-r from-purple-500 to-purple-600",
      bgLine: "bg-purple-500"
    }
  }
  if (tipoLower.includes("comentário") || tipoLower.includes("comentario")) {
    return {
      icon: "fas fa-comment",
      bg: "bg-gradient-to-br from-indigo-500 to-indigo-600",
      badge: "bg-indigo-50 text-indigo-700 border border-indigo-200",
      border: "border-indigo-500",
      dot: "bg-indigo-500",
      ribbon: "bg-gradient-to-r from-indigo-500 to-indigo-600",
      bgLine: "bg-indigo-500"
    }
  }
  if (
    tipoLower.includes("responsável") ||
    tipoLower.includes("responsavel") ||
    tipoLower.includes("atribuído") ||
    tipoLower.includes("atribuido")
  ) {
    return {
      icon: "fas fa-user-check",
      bg: "bg-gradient-to-br from-cyan-500 to-cyan-600",
      badge: "bg-cyan-50 text-cyan-700 border border-cyan-200",
      border: "border-cyan-500",
      dot: "bg-cyan-500",
      ribbon: "bg-gradient-to-r from-cyan-500 to-cyan-600",
      bgLine: "bg-cyan-500"
    }
  }
  if (tipoLower.includes("prioridade")) {
    return {
      icon: "fas fa-flag",
      bg: "bg-gradient-to-br from-orange-500 to-orange-600",
      badge: "bg-orange-50 text-orange-700 border border-orange-200",
      border: "border-orange-500",
      dot: "bg-orange-500",
      ribbon: "bg-gradient-to-r from-orange-500 to-orange-600",
      bgLine: "bg-orange-500"
    }
  }
  if (
    tipoLower.includes("departamento") ||
    tipoLower.includes("transferência") ||
    tipoLower.includes("transferencia")
  ) {
    return {
      icon: "fas fa-exchange-alt",
      bg: "bg-gradient-to-br from-violet-500 to-violet-600",
      badge: "bg-violet-50 text-violet-700 border border-violet-200",
      border: "border-violet-500",
      dot: "bg-violet-500",
      ribbon: "bg-gradient-to-r from-violet-500 to-violet-600",
      bgLine: "bg-violet-500"
    }
  }
  if (tipoLower.includes("retorno")) {
    return {
      icon: "fas fa-reply",
      bg: "bg-gradient-to-br from-sky-500 to-sky-600",
      badge: "bg-sky-50 text-sky-700 border border-sky-200",
      border: "border-sky-500",
      dot: "bg-sky-500",
      ribbon: "bg-gradient-to-r from-sky-500 to-sky-600",
      bgLine: "bg-sky-500"
    }
  }
  if (
    tipoLower.includes("aprovação") ||
    tipoLower.includes("aprovado") ||
    tipoLower.includes("aprovar")
  ) {
    return {
      icon: "fas fa-thumbs-up",
      bg: "bg-gradient-to-br from-lime-500 to-lime-600",
      badge: "bg-lime-50 text-lime-700 border border-lime-200",
      border: "border-lime-500",
      dot: "bg-lime-500",
      ribbon: "bg-gradient-to-r from-lime-500 to-lime-600",
      bgLine: "bg-lime-500"
    }
  }
  if (tipoLower.includes("retornou")) {
    // Se tiver dados extras com cor da etapa, usa ela
    if (movimentacao?.dados_extras?.etapa_cor) {
      const cor = movimentacao.dados_extras.etapa_cor
      const icone = movimentacao.dados_extras.etapa_icone || "pi pi-replay"
      return {
        icon: icone,
        customCor: cor,
        bg: "",
        badge: "",
        border: "",
        dot: "",
        ribbon: "",
        bgLine: ""
      }
    }
    return {
      icon: "pi pi-replay",
      bg: "bg-gradient-to-br from-amber-500 to-orange-600",
      badge: "bg-amber-50 text-amber-700 border border-amber-200",
      border: "border-amber-500",
      dot: "bg-amber-500",
      ribbon: "bg-gradient-to-r from-amber-500 to-orange-600",
      bgLine: "bg-amber-500"
    }
  }
  if (tipoLower.includes("etapa")) {
    // Se tiver movimentação com dados extras, usa a cor configurada
    if (movimentacao?.dados_extras?.etapa_cor) {
      const cor = movimentacao.dados_extras.etapa_cor
      const icone = movimentacao.dados_extras.etapa_icone || "pi pi-sitemap"
      return {
        icon: icone,
        customCor: cor,
        bg: "",
        badge: "",
        border: "",
        dot: "",
        ribbon: "",
        bgLine: ""
      }
    }
    // Fallback para cor padrão
    return {
      icon: "pi pi-sitemap",
      bg: "bg-gradient-to-br from-fuchsia-500 to-fuchsia-600",
      badge: "bg-fuchsia-50 text-fuchsia-700 border border-fuchsia-200",
      border: "border-fuchsia-500",
      dot: "bg-fuchsia-500",
      ribbon: "bg-gradient-to-r from-fuchsia-500 to-fuchsia-600",
      bgLine: "bg-fuchsia-500"
    }
  }

  // Padrão
  return {
    icon: "fas fa-info-circle",
    bg: "bg-gradient-to-br from-gray-500 to-gray-600",
    badge: "bg-gray-50 text-gray-700 border border-gray-200",
    border: "border-gray-400",
    dot: "bg-gray-400",
    ribbon: "bg-gradient-to-r from-gray-500 to-gray-600",
    bgLine: "bg-gray-400"
  }
}

function validaPermissao(perm) {
  return props.permissoes.includes(perm)
}

async function verificarBranchExistente() {
  if (!podeCriarBranch.value || !solicitacao.value) return

  const tituloSol = solicitacao.value.titulo || ""
  const descricaoSol = solicitacao.value.descricao || ""
  const titulo =
    tituloSol.length > 10
      ? tituloSol
      : descricaoSol || tituloSol || "solicitacao"

  try {
    const { data } = await axios.get("/v2/release-notes/check-branch", {
      params: { numero: props.solicitacao_id, titulo }
    })
    branchJaExiste.value = data.existe
    branchNomeExistente.value = data.branch || ""
  } catch {
    branchJaExiste.value = false
  }
}

async function criarBranch() {
  if (criandoBranch.value || !solicitacao.value) return

  // Se título for curto/genérico, prefere a descrição
  const tituloSol = solicitacao.value.titulo || ""
  const descricaoSol = solicitacao.value.descricao || ""
  const titulo =
    tituloSol.length > 10
      ? tituloSol
      : descricaoSol || tituloSol || "solicitacao"

  const resultado = await swalConfirm(
    `Criar branch para a ticket #${props.solicitacao_id}?`,
    `Branch baseada em producao com o nome: ${props.solicitacao_id}-${titulo.toLowerCase().substring(0, 40)}...`
  )
  if (!resultado.isConfirmed) return

  criandoBranch.value = true
  try {
    const { data } = await axios.post("/v2/release-notes/create-branch", {
      numero: props.solicitacao_id,
      titulo: titulo
    })
    if (data.sucesso) {
      branchJaExiste.value = true
      branchNomeExistente.value =
        data.dados?.branch || branchNomeExistente.value
      toastSuccess(data.mensagem)
    } else {
      toastError(data.mensagem)
    }
  } catch (error) {
    const msg = error.response?.data?.mensagem || "Erro ao criar branch"
    if (error.response?.status === 409) {
      toastWarning(msg)
    } else {
      toastError(msg)
    }
  } finally {
    criandoBranch.value = false
  }
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES AVATAR                         ║
// ╚══════════════════════════════════════════════════════════════╝

// Função para obter iniciais (nome e sobrenome)
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

// Função para obter nome e sobrenome formatado
function obterNomeSobrenome(nome) {
  if (!nome) return "Não informado"
  const partes = nome
    .trim()
    .split(" ")
    .filter((p) => p.length > 0)
  if (partes.length === 0) return "Não informado"
  if (partes.length === 1) return partes[0]
  return `${partes[0]} ${partes[partes.length - 1]}`
}

function getIcon(extensao) {
  switch (extensao) {
    case "pdf":
      return "fas fa-file-pdf"
    case "xlsx":
    case "xls":
      return "fas fa-file-excel"
    case "doc":
    case "docx":
      return "fas fa-file-word"
    case "jpg":
    case "jpeg":
    case "png":
      return "fas fa-file-image"
    default:
      return "fas fa-file"
  }
}

async function verArquivo(arquivo) {
  if (!arquivo) {
    return
  }

  // Verifica se é arquivo HEIC/HEIF - usando a estrutura real
  const isHeic =
    arquivo.extension &&
    (arquivo.extension.toLowerCase() === "heic" ||
      arquivo.extension.toLowerCase() === "heif" ||
      arquivo.original_name.toLowerCase().endsWith(".heic") ||
      arquivo.original_name.toLowerCase().endsWith(".heif"))

  if (isHeic) {
    // Limpa cache anterior antes de converter nova imagem
    limparCacheHeic()

    // Tenta converter HEIC para visualização
    const jpegUrl = await converterHeicParaJpeg(arquivo)

    if (jpegUrl) {
      // Se conversão foi bem-sucedida, abre no diálogo de imagem
      imagemSelecionada.value = imagemConvertida.value
      dialogImagem.value = true
    } else {
    }
    return
  }

  // Para outros tipos de arquivo
  if (
    arquivo.extension &&
    (arquivo.extension.toLowerCase() === "docx" ||
      arquivo.extension.toLowerCase() === "doc")
  ) {
    window.open(arquivo.external_link, "_blank")
    return
  }

  if (arquivo.extension && arquivo.extension.toLowerCase() === "pdf") {
    const options =
      "width=800,height=600,left=200,top=100,menubar=no,toolbar=no,location=no,status=no"
    window.open(arquivo.external_link, "VisualizarArquivo", options)
    return
  }

  const isImagemNormal =
    arquivo.original_name && isImagem(arquivo.original_name)

  if (isImagemNormal) {
    imagemSelecionada.value = arquivo
    dialogImagem.value = true
  } else {
  }
}

function verArquivo2(arquivo) {
  fileId.value = arquivo.id
  viewFileDialog.value = true
  listaIdsFile.value = arquivosComputed.value.map((item) => item.file.id)
}

function visualizarArquivoComentario(arquivo, arquivosComentario) {
  fileId.value = arquivo.id
  listaIdsFile.value = arquivosComentario.map((item) => item.file.id)
  viewFileDialog.value = true
}

async function downloadArquivo(file) {
  try {
    // Verifica se existe caminho do arquivo
    if (!file.external_link) {
      toastWarning("Arquivo não encontrado ou caminho inválido.")
      return
    }

    // Método 1: Tentar download direto via link
    const link = document.createElement("a")
    link.href = file.external_link
    link.download = file.original_name || "arquivo"
    link.target = "_blank"

    // Adiciona o link ao DOM temporariamente
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)

    // Download iniciado
  } catch (error) {
    console.error("Erro ao fazer download:", error)

    // Fallback: Tentar abrir em nova aba
    try {
      window.open(file.external_link, "_blank")
    } catch (fallbackError) {
      console.error("Erro no fallback:", fallbackError)
      toastWarning(
        "Não foi possível acessar o arquivo. Verifique se o link está funcionando."
      )
    }
  }
}

// Função para limpar cache de imagens HEIC
function limparCacheHeic() {
  if (
    imagemConvertida.value &&
    imagemConvertida.value.external_link &&
    imagemConvertida.value.external_link.startsWith("blob:")
  ) {
    URL.revokeObjectURL(imagemConvertida.value.external_link)
    imagemConvertida.value = null
  }
}

async function converterHeicParaJpeg(file) {
  try {
    loadingHeic.value = true

    // Tenta diferentes abordagens para buscar o arquivo
    let response
    let blob

    try {
      // Primeira tentativa: fetch com CORS
      response = await fetch(file.external_link, {
        mode: "cors",
        headers: {
          Accept: "image/*,*/*"
        }
      })
    } catch (corsError) {
      // Erro CORS, tentando sem mode
      // Segunda tentativa: fetch sem CORS
      try {
        response = await fetch(file.external_link, {
          headers: {
            Accept: "image/*,*/*"
          }
        })
      } catch (fetchError) {
        // Erro fetch simples, tentando proxy
        // Terceira tentativa: através do backend Laravel (se disponível)
        response = await fetch(
          `/api/proxy-image?url=${encodeURIComponent(file.external_link)}`
        )
      }
    }

    if (!response || !response.ok) {
      throw new Error(
        `Erro HTTP: ${response?.status || "N/A"} - ${response?.statusText || "Falha na requisição"}`
      )
    }

    blob = await response.blob()

    // Arquivo baixado com sucesso

    // Verifica se o blob é válido
    if (blob.size === 0) {
      throw new Error("Arquivo vazio ou inacessível")
    }

    // Verifica se é realmente um arquivo HEIC/HEIF
    const isValidHeic =
      blob.type.includes("heic") ||
      blob.type.includes("heif") ||
      blob.type === "application/octet-stream" ||
      file.original_name.toLowerCase().match(/\.(heic|heif)$/i)

    // Validação de arquivo HEIC

    // Iniciando conversão HEIC

    // Converte HEIC para JPEG com configurações mais robustas
    const convertedBlob = await heic2any({
      blob: blob,
      toType: "image/jpeg",
      quality: 0.8,
      multiple: false
    })

    // Conversão HEIC concluída com sucesso

    // Cria URL temporária para a imagem convertida
    const jpegUrl = URL.createObjectURL(convertedBlob)

    // Cria objeto de arquivo simulado para o diálogo
    imagemConvertida.value = {
      ...file,
      external_link: jpegUrl,
      extension: "jpeg",
      original_name: file.original_name.replace(/\.(heic|heif)$/i, ".jpeg")
    }

    // URL temporária criada para visualização

    return jpegUrl
  } catch (error) {
    console.error("Erro ao converter HEIC:", error.message)

    // Mensagem mais específica baseada no tipo de erro
    let mensagemErro = "Não foi possível converter o arquivo HEIC."

    if (error.message.includes("fetch") || error.message.includes("HTTP")) {
      mensagemErro += " Problema ao acessar o arquivo no servidor."
    } else if (
      error.message.includes("heic2any") ||
      error.message.includes("Failed to decode")
    ) {
      mensagemErro += " O arquivo pode estar corrompido ou não ser HEIC válido."
    } else if (error.name === "TypeError") {
      mensagemErro += " Problema de formato do arquivo."
    } else if (error.message.includes("vazio")) {
      mensagemErro += " O arquivo está vazio ou inacessível."
    } else if (error.message.includes("CORS")) {
      mensagemErro += " Problema de permissão de acesso."
    }

    toastWarning(mensagemErro + " Fazendo download automático...")

    // Em caso de erro, faz download automático
    downloadArquivo(file)
    return null
  } finally {
    loadingHeic.value = false
  }
}

async function abrirTrocaAssunto() {
  if (deptos.value.length === 0) {
    await getDeptosAtivo()
  }
  // Pré-selecionar o departamento atual
  const deptoAtual = deptos.value.find(
    (depto) => depto.condicao1 === solicitacao.value.departamento_responsavel
  )

  if (deptoAtual) {
    deptoSelecionado.value = deptoAtual
    // Limpar assunto selecionado para forçar nova seleção
    assuntoSelecionado.value = null
  }

  // Marcar que é apenas troca de assunto
  trocaAssunto.value = true
  dialogMudarDepartamento.value = true
}

async function abrirTrocaDepartamento() {
  await getDeptosAtivo()
  // Limpar seleções para permitir escolha livre
  deptoSelecionado.value = null
  assuntoSelecionado.value = null

  // Marcar que é troca completa (departamento + assunto)
  trocaAssunto.value = false
  dialogMudarDepartamento.value = true
}

async function alterarDepto() {
  // Validar se está tentando alterar para o mesmo departamento e assunto
  const mesmoDepto =
    deptoSelecionado.value?.condicao1 ===
    solicitacao.value.departamento_responsavel
  const mesmoAssunto =
    assuntoSelecionado.value === solicitacao.value.assunto?.id

  if (mesmoDepto && mesmoAssunto) {
    return
  }

  dialogMotivoDepto.value = false
  loading.value = true

  const params = {
    solicitacao_id: solicitacao.value.id,
    deptoSelecionado: deptoSelecionado.value.condicao1,
    assunto_id: assuntoSelecionado.value,
    comentario: comentarioMotivo.value,
    troca_assunto: trocaAssunto.value,
    selects: respostasSelects.value
  }

  await axios
    .post("/solicitacoes/lista/alterar-departamento", params)
    .then(async (res) => {
      dialogMudarDepartamento.value = false
      // Resetar a flag de troca de assunto
      trocaAssunto.value = false
      // Limpar respostas dos selects
      respostasSelects.value = []
      await buscarSolicitacao()
    })
    .catch((err) => {
      swalObservacao(err.response.data.message)
    })
    .finally(() => {
      loading.value = false
    })
}

// Calcula a data mínima permitida para campos de data com prazo mínimo configurado
function calcularDataMinima(select) {
  if (!select.dias_minimos) return null
  const data = new Date()
  data.setDate(data.getDate() + Number(select.dias_minimos))
  data.setHours(0, 0, 0, 0)
  return data
}

// Função para adicionar resposta dos selects na alteração de departamento/assunto
function addRespostaAlteracao(select, valor) {
  const tiposWinthor = [
    "depto_compras",
    "depto_funcionario",
    "filial_winthor",
    "funcao",
    "regional"
  ]

  const idx = respostasSelects.value.findIndex(
    (r) => r.selecao_id === select.id
  )

  let resposta

  if (select.tipo === "selecao" || tiposWinthor.includes(select.tipo)) {
    resposta = valor
  } else if (select.tipo === "data") {
    let datas
    if (select.tipo_data === "range") {
      datas = valor?.map((v) => (v ? v.toISOString().split("T")[0] : null))
    } else {
      datas = [valor ? valor.toISOString().split("T")[0] : null]
    }
    resposta = { datas: datas }
  } else if (
    select.tipo === "texto" ||
    select.tipo === "numero" ||
    select.tipo === "cnpj"
  ) {
    resposta = valor
  }

  const novaResposta = {
    assunto_id: assuntoSelecionado.value,
    selecao_id: select.id,
    tipo: select.tipo,
    resposta: resposta
  }

  if (idx !== -1) {
    respostasSelects.value[idx] = novaResposta
  } else {
    respostasSelects.value.push(novaResposta)
  }
}

// #12173 - Verifica se um campo condicional deve ser exibido (contexto: tela de atendimento)
function campoDeveSerExibidoAtendimento(select, assunto) {
  if (!select.campo_pai_id) return true

  const campoPaiId = Number(select.campo_pai_id)

  // Buscar o campo pai para obter suas opções
  const campoPai = assunto?.selects?.find((s) => Number(s.id) === campoPaiId)

  // Buscar resposta do campo pai nas respostas de alteração
  const respostaPai = respostasSelects.value.find(
    (r) => Number(r.selecao_id) === campoPaiId
  )

  if (!respostaPai) return false

  const valorResposta = respostaPai.resposta

  // Array de codes numéricos (MultiSelect) — resolver labels via opções do pai
  if (Array.isArray(valorResposta)) {
    if (campoPai?.valores) {
      return valorResposta.some((code) => {
        const opcao = campoPai.valores.find(
          (v) => v.code === code || Number(v.code) === Number(code)
        )
        return opcao?.label === select.valor_condicional
      })
    }
    return valorResposta.some(
      (v) => (typeof v === "string" ? v : v?.label) === select.valor_condicional
    )
  }

  // Objeto {code, label}
  if (typeof valorResposta === "object" && valorResposta !== null) {
    if (valorResposta.label) {
      return valorResposta.label === select.valor_condicional
    }
  }

  // Code numérico — resolver label
  if (campoPai?.valores && Array.isArray(campoPai.valores)) {
    const opcaoSelecionada = campoPai.valores.find(
      (v) =>
        v.code === valorResposta || Number(v.code) === Number(valorResposta)
    )
    if (opcaoSelecionada) {
      return opcaoSelecionada.label === select.valor_condicional
    }
  }

  return valorResposta === select.valor_condicional
}

// Computed para obter o assunto selecionado com seus selects
const assuntoComSelects = computed(() => {
  if (!deptoSelecionado.value || !assuntoSelecionado.value) return null
  return deptoSelecionado.value.assuntos.find(
    (a) => a.id === assuntoSelecionado.value
  )
})

// Computed para filtrar assuntos - remove o assunto atual quando for o mesmo departamento
const assuntosFiltrados = computed(() => {
  if (!deptoSelecionado.value?.assuntos) return []

  const mesmoDepto =
    deptoSelecionado.value?.condicao1 ===
    solicitacao.value?.departamento_responsavel

  if (mesmoDepto && solicitacao.value?.assunto?.id) {
    return deptoSelecionado.value.assuntos.filter(
      (a) => a.id !== solicitacao.value.assunto.id
    )
  }

  return deptoSelecionado.value.assuntos
})

async function RetornarSolicitacao() {
  const resposta = await swalConfirm(
    "Você tem certeza?",
    "A ticket será retornada ao Solicitante."
  )
  if (!resposta.isConfirmed) {
    return
  }

  const params = {
    solicitacao: solicitacao.value,
    comentario: comentarioDevolucao.value
  }

  await axios
    .post("/solicitacoes/lista/retorno-solicitante", params)
    .then((res) => {
      buscarSolicitacao()
      comentario.value = ""
      dialogRetorno.value = false
    })
    .catch((err) => {
      toastError("Erro ao retornar ticket!")
    })
}

function togglePrivado() {
  showPrivateMenu.value = !showPrivateMenu.value
}

function selecionarPrivacidade(option) {
  privateType.value = option.value
  privateComment.value = option.value !== null
  showPrivateMenu.value = false
}

// Funções para enviar arquivo para dossiê
async function enviarParaDossie(fileId) {
  arquivoParaDossie.value = fileId
  await buscarConfigDossie()
  dialogEnviarDossie.value = true
}

// Função chamada quando o funcionário é selecionado
const onFuncionarioChange = async () => {
  pastaSelecionadaDossie.value = null
  listaSelecionadaDossie.value = null
  if (funcionarioSelecionadoDossie.value) {
    await buscarDossiesDoFuncionario()
  }
}

async function buscarConfigDossie() {
  loadingDossie.value = true
  try {
    const response = await axios.get("/rh/dossie/dados-configuracao")

    // Pegar apenas as pastas, não precisamos das listas de templates
    configDossie.value = {
      pastas: response.data.pastas || [],
      dossiesIndividuais: []
    }

    // Se há funcionário selecionado, buscar também seus dossiês individuais
    if (funcionarioSelecionadoDossie.value) {
      await buscarDossiesDoFuncionario()
    }
  } catch (error) {
    console.error("Erro ao buscar configurações do dossiê:", error)
    toastError("Erro ao carregar configurações do dossiê")
  } finally {
    loadingDossie.value = false
  }
}

async function buscarDossiesDoFuncionario() {
  if (!funcionarioSelecionadoDossie.value) return

  try {
    const params = {
      funcionario: funcionarioSelecionadoDossie.value
    }
    const response = await axios.post("/rh/dossie/dossies-usuario", params)

    // A resposta agora vem com a estrutura { dossies: [...], pastas: [] }
    // dossies contém os dossiês individuais do funcionário
    configDossie.value.dossiesIndividuais = response.data.dossies || []
  } catch (error) {
    console.error("Erro ao buscar dossiês do funcionário:", error)
    configDossie.value.dossiesIndividuais = []
  }
}

// Função chamada quando a pasta é alterada
const onPastaChange = async () => {
  listaSelecionadaDossie.value = null
  if (funcionarioSelecionadoDossie.value) {
    await buscarDossiesDoFuncionario()
  }
}

async function confirmarEnvioDossie() {
  if (!funcionarioSelecionadoDossie.value) {
    toastError("Selecione um funcionário")
    return
  }

  if (!pastaSelecionadaDossie.value) {
    toastError("Selecione uma pasta")
    return
  }

  if (!listaSelecionadaDossie.value) {
    toastError("Selecione um dossiê")
    return
  }

  loadingDossie.value = true

  try {
    const params = {
      funcionario: funcionarioSelecionadoDossie.value,
      documento: {
        descricao: `Arquivo da ticket #${props.solicitacao_id}`,
        arquivos: [
          {
            file: { id: arquivoParaDossie.value }
          }
        ]
      },
      // Adicionar dossie_itens para sempre cair no else e buscar dossiê existente
      pastaSelecionada: {
        ...listaSelecionadaDossie.value,
        dossie_itens: [] // Força o key_exists a retornar true
      }
    }

    await axios.post("/rh/dossie/adicionar", params)

    fecharDialogDossie()
  } catch (error) {
    console.error("Erro ao enviar arquivo para dossiê:", error)
    const errorMessage =
      error.response?.data?.error || "Erro ao enviar arquivo para o dossiê"
    toastError(errorMessage)
  } finally {
    loadingDossie.value = false
  }
}

function fecharDialogDossie() {
  dialogEnviarDossie.value = false
  funcionarioSelecionadoDossie.value = null
  pastaSelecionadaDossie.value = null
  listaSelecionadaDossie.value = null
  arquivoParaDossie.value = null
  // Limpar também a configuração para forçar nova busca
  configDossie.value = { pastas: [], dossiesIndividuais: [] }
}

// Função para criar novo dossiê
async function criarNovoDossie() {
  if (!novoDossie.value.descricao) {
    toastError("Digite uma descrição para o dossiê")
    return
  }

  if (!pastaSelecionadaDossie.value) {
    toastError("Selecione uma pasta primeiro")
    return
  }

  loadingDossie.value = true

  try {
    const params = {
      funcionario: funcionarioSelecionadoDossie.value,
      documento: {
        descricao: novoDossie.value.descricao,
        arquivos: [] // Vazio pois estamos apenas criando o dossiê
      },
      pastaSelecionada: pastaSelecionadaDossie.value
    }

    const response = await axios.post("/rh/dossie/adicionar", params)
    await buscarConfigDossie()

    dialogNovoDossie.value = false
    novoDossie.value = { descricao: "" }
  } catch (error) {
    console.error("Erro ao criar dossiê:", error)
    toastError("Erro ao criar dossiê")
  } finally {
    loadingDossie.value = false
  }
}

const cancelarNovoDossie = () => {
  dialogNovoDossie.value = false
  novoDossie.value = { descricao: "" }
}

function fecharDialogImagem() {
  // Limpa cache HEIC e reseta imagemSelecionada
  limparCacheHeic()
  imagemSelecionada.value = []
  dialogImagem.value = false
}

// Variáveis para trocar solicitante
const dialogTrocarSolicitante = ref(false)
const novoSolicitanteSelecionado = ref(null)
const comentarioMotivoSolicitante = ref("")
const dialogMotivoSolicitante = ref(false)
const enviandoComentario = ref(false)

// Funções para trocar solicitante
function abrirTrocaSolicitante() {
  novoSolicitanteSelecionado.value = null
  comentarioMotivoSolicitante.value = ""
  dialogTrocarSolicitante.value = true
}

// Função para alterar o solicitante
async function alterarSolicitante() {
  dialogMotivoSolicitante.value = false
  loading.value = true

  // Parâmetros para a requisição
  const params = {
    solicitacao_id: solicitacao.value.id,
    novo_solicitante: novoSolicitanteSelecionado.value.matricula,
    comentario: comentarioMotivoSolicitante.value
  }

  // Requisição para alterar o solicitante
  await axios
    .post("/solicitacoes/lista/alterar-solicitante", params)
    .then(async (res) => {
      dialogTrocarSolicitante.value = false
      comentarioMotivoSolicitante.value = ""
      await buscarSolicitacao()
    })
    .catch((err) => {
      swalObservacao(
        err.response?.data?.message || "Erro ao alterar solicitante"
      )
    })
    .finally(() => {
      loading.value = false
    })
}

async function novoAgendamento() {
  await buscarAgendamentos()
  abrirAgendamento()
}
</script>

<template>
  <Loader :loading="loading"></Loader>

  <!-- Overlay de Loading HEIC -->
  <div
    v-if="loadingHeic"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
  >
    <div class="bg-white rounded-xl p-8 shadow-2xl max-w-sm mx-4 text-center">
      <div class="flex justify-center mb-4">
        <div class="relative">
          <div
            class="w-16 h-16 border-4 border-blue-200 rounded-full animate-spin border-t-blue-600"
          ></div>
          <div class="absolute inset-0 flex items-center justify-center">
            <i class="fas fa-image text-blue-600 text-xl"></i>
          </div>
        </div>
      </div>
      <h3 class="text-lg font-semibold text-gray-800 mb-2">
        Convertendo arquivo HEIC
      </h3>
      <p class="text-gray-600 text-sm mb-4">
        Preparando arquivo para visualização...
      </p>

      <p class="text-xs text-gray-500 mt-3">Isso pode levar alguns segundos</p>
    </div>
  </div>

  <div
    class="flex flex-col items-center justify-center w-screen h-[calc(100dvh-4rem)] select-none"
  >
    <div
      v-if="solicitacao"
      class="flex flex-col overflow-hidden rounded-2xl shadow-2xl shadow-slate-300/50 dark:shadow-slate-900/50 bg-white dark:bg-slate-800 backdrop-blur-xl h-full w-full max-w-[1600px] border border-slate-200/50 dark:border-slate-700/50 ring-1 ring-black/5 dark:ring-white/10"
    >
      <!-- Header Principal com Gradiente -->
      <div
        class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-slate-800 via-slate-700 to-slate-800 dark:from-slate-900 dark:via-slate-800 dark:to-slate-900"
      >
        <div class="flex items-center gap-4">
          <!-- ID do Ticket -->
          <div class="flex items-center gap-2">
            <div
              class="flex items-center justify-center w-8 h-8 bg-white/10 backdrop-blur-sm rounded-xl border border-white/20"
            >
              <i class="pi pi-hashtag text-white/90 text-xs"></i>
            </div>
            <span class="text-lg font-bold text-white tracking-wide">
              {{ solicitacao_id }}
            </span>
          </div>

          <!-- Separador -->
          <div class="hidden sm:block w-px h-6 bg-white/20"></div>

          <!-- Badge de Prioridade -->
          <div
            class="flex items-center gap-2 px-3 py-1.5 rounded-xl backdrop-blur-sm border transition-all cursor-default"
            :class="{
              'bg-gray-500/20 border-gray-400/30 text-gray-200':
                solicitacao.prioridade === 'baixa',
              'bg-blue-500/20 border-blue-400/30 text-blue-200':
                solicitacao.prioridade === 'media',
              'bg-amber-500/20 border-amber-400/30 text-amber-200':
                solicitacao.prioridade === 'alta',
              'bg-red-500/20 border-red-400/30 text-red-200 animate-pulse':
                solicitacao.prioridade === 'urgente'
            }"
          >
            <i
              class="pi pi-flag text-xs"
              :class="{
                'text-gray-300': solicitacao.prioridade === 'baixa',
                'text-blue-300': solicitacao.prioridade === 'media',
                'text-amber-300': solicitacao.prioridade === 'alta',
                'text-red-300': solicitacao.prioridade === 'urgente'
              }"
            ></i>
            <span class="text-xs font-bold uppercase tracking-wider">
              {{ solicitacao.prioridade }}
            </span>
            <button
              v-if="
                (solicitacao.status == 'pendente' ||
                  solicitacao.status == 'atendimento pausado' ||
                  solicitacao.status == 'resolução recusada') &&
                validaPermissao('solicitacoes.lista.alterar-prioridade') &&
                solicitacao.departamento_responsavel == props.auth.areaatuacao
              "
              @click="showDialogMudarPrioridae()"
              class="ml-1 w-5 h-5 flex items-center justify-center"
            >
              <i class="pi pi-pencil text-[10px] text-white/80"></i>
            </button>
          </div>
        </div>

        <!-- Status Badge Central -->
        <div
          class="hidden sm:flex items-center gap-2.5 px-5 py-2.5 rounded-full shadow-lg backdrop-blur-sm transition-all duration-300 hover:scale-[1.02]"
          :class="{
            'bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/40 dark:to-orange-900/40 border border-amber-200/80 dark:border-amber-700/50 shadow-amber-500/20':
              solicitacao.status === 'pendente',
            'bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/40 dark:to-indigo-900/40 border border-blue-200/80 dark:border-blue-700/50 shadow-blue-500/20':
              solicitacao.status === 'em atendimento',
            'bg-gradient-to-r from-orange-50 to-amber-50 dark:from-orange-900/40 dark:to-amber-900/40 border border-orange-200/80 dark:border-orange-700/50 shadow-orange-500/20':
              solicitacao.status === 'atendimento pausado',
            'bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/40 dark:to-green-900/40 border border-emerald-200/80 dark:border-emerald-700/50 shadow-emerald-500/20':
              solicitacao.status === 'resolvida' ||
              solicitacao.status === 'finalizada',
            'bg-gradient-to-r from-red-50 to-rose-50 dark:from-red-900/40 dark:to-rose-900/40 border border-red-200/80 dark:border-red-700/50 shadow-red-500/20':
              solicitacao.status === 'cancelada' ||
              solicitacao.status === 'resolução recusada',
            'bg-gradient-to-r from-purple-50 to-violet-50 dark:from-purple-900/40 dark:to-violet-900/40 border border-purple-200/80 dark:border-purple-700/50 shadow-purple-500/20':
              solicitacao.status === 'retorno solicitante',
            'bg-gradient-to-r from-cyan-50 to-teal-50 dark:from-cyan-900/40 dark:to-teal-900/40 border border-cyan-200/80 dark:border-cyan-700/50 shadow-cyan-500/20':
              solicitacao.status === 'agendado'
          }"
        >
          <!-- Ícone animado -->
          <div class="relative">
            <span
              class="absolute inset-0 rounded-full animate-ping opacity-40"
              :class="{
                'bg-amber-400': solicitacao.status === 'pendente',
                'bg-blue-400': solicitacao.status === 'em atendimento',
                'bg-orange-400': solicitacao.status === 'atendimento pausado',
                'bg-emerald-400':
                  solicitacao.status === 'resolvida' ||
                  solicitacao.status === 'finalizada',
                'bg-red-400':
                  solicitacao.status === 'cancelada' ||
                  solicitacao.status === 'resolução recusada',
                'bg-purple-400': solicitacao.status === 'retorno solicitante',
                'bg-cyan-400': solicitacao.status === 'agendado'
              }"
            ></span>
            <span
              class="relative flex h-2.5 w-2.5 rounded-full"
              :class="{
                'bg-amber-500': solicitacao.status === 'pendente',
                'bg-blue-500': solicitacao.status === 'em atendimento',
                'bg-orange-500': solicitacao.status === 'atendimento pausado',
                'bg-emerald-500':
                  solicitacao.status === 'resolvida' ||
                  solicitacao.status === 'finalizada',
                'bg-red-500':
                  solicitacao.status === 'cancelada' ||
                  solicitacao.status === 'resolução recusada',
                'bg-purple-500': solicitacao.status === 'retorno solicitante',
                'bg-cyan-500': solicitacao.status === 'agendado'
              }"
            ></span>
          </div>

          <!-- Texto do Status -->
          <span
            class="text-xs font-bold uppercase tracking-wider"
            :class="{
              'text-amber-700 dark:text-amber-300':
                solicitacao.status === 'pendente',
              'text-blue-700 dark:text-blue-300':
                solicitacao.status === 'em atendimento',
              'text-orange-700 dark:text-orange-300':
                solicitacao.status === 'atendimento pausado',
              'text-emerald-700 dark:text-emerald-300':
                solicitacao.status === 'resolvida' ||
                solicitacao.status === 'finalizada',
              'text-red-700 dark:text-red-300':
                solicitacao.status === 'cancelada' ||
                solicitacao.status === 'resolução recusada',
              'text-purple-700 dark:text-purple-300':
                solicitacao.status === 'retorno solicitante',
              'text-cyan-700 dark:text-cyan-300':
                solicitacao.status === 'agendado'
            }"
          >
            {{ solicitacao.status }}
          </span>

          <!-- Badge de Agendado (se houver) -->
          <div
            v-if="
              solicitacao.agendamentos.some(
                (ag) =>
                  ag.status != 'finalizado' &&
                  ag.status != 'cancelado' &&
                  ag.status != 'ativo'
              )
            "
            class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gradient-to-r from-cyan-500 to-teal-500 shadow-md shadow-cyan-500/30"
          >
            <i class="pi pi-calendar-clock text-[10px] text-white"></i>
            <span
              class="text-[10px] font-bold text-white uppercase tracking-wider"
            >
              Agendado
            </span>
          </div>
        </div>

        <!-- Botão Fechar -->
        <button
          @click="fechar()"
          class="flex items-center justify-center w-9 h-9 rounded-xl bg-white/10 hover:bg-red-500/80 border border-white/20 hover:border-red-400 text-white/80 hover:text-white transition-all duration-300 hover:scale-105 hover:shadow-lg hover:shadow-red-500/25"
        >
          <i class="pi pi-times text-sm"></i>
        </button>
      </div>

      <!-- Status Badge Mobile (visível apenas em mobile) -->
      <div
        class="sm:hidden flex items-center justify-center py-2.5 bg-gradient-to-r from-slate-50 via-white to-slate-50 dark:from-slate-800/80 dark:via-slate-800 dark:to-slate-800/80 border-b border-slate-200/50 dark:border-slate-700/50"
      >
        <div
          class="flex items-center gap-2 px-4 py-2 rounded-full shadow-md backdrop-blur-sm transition-all"
          :class="{
            'bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/40 dark:to-orange-900/40 border border-amber-200/80 dark:border-amber-700/50':
              solicitacao.status === 'pendente',
            'bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/40 dark:to-indigo-900/40 border border-blue-200/80 dark:border-blue-700/50':
              solicitacao.status === 'em atendimento',
            'bg-gradient-to-r from-orange-50 to-amber-50 dark:from-orange-900/40 dark:to-amber-900/40 border border-orange-200/80 dark:border-orange-700/50':
              solicitacao.status === 'atendimento pausado',
            'bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900/40 dark:to-green-900/40 border border-emerald-200/80 dark:border-emerald-700/50':
              solicitacao.status === 'resolvida' ||
              solicitacao.status === 'finalizada',
            'bg-gradient-to-r from-red-50 to-rose-50 dark:from-red-900/40 dark:to-rose-900/40 border border-red-200/80 dark:border-red-700/50':
              solicitacao.status === 'cancelada' ||
              solicitacao.status === 'resolução recusada',
            'bg-gradient-to-r from-purple-50 to-violet-50 dark:from-purple-900/40 dark:to-violet-900/40 border border-purple-200/80 dark:border-purple-700/50':
              solicitacao.status === 'retorno solicitante'
          }"
        >
          <!-- Ícone animado -->
          <div class="relative">
            <span
              class="absolute inset-0 rounded-full animate-ping opacity-40"
              :class="{
                'bg-amber-400': solicitacao.status === 'pendente',
                'bg-blue-400': solicitacao.status === 'em atendimento',
                'bg-orange-400': solicitacao.status === 'atendimento pausado',
                'bg-emerald-400':
                  solicitacao.status === 'resolvida' ||
                  solicitacao.status === 'finalizada',
                'bg-red-400':
                  solicitacao.status === 'cancelada' ||
                  solicitacao.status === 'resolução recusada',
                'bg-purple-400': solicitacao.status === 'retorno solicitante'
              }"
            ></span>
            <span
              class="relative flex h-2 w-2 rounded-full"
              :class="{
                'bg-amber-500': solicitacao.status === 'pendente',
                'bg-blue-500': solicitacao.status === 'em atendimento',
                'bg-orange-500': solicitacao.status === 'atendimento pausado',
                'bg-emerald-500':
                  solicitacao.status === 'resolvida' ||
                  solicitacao.status === 'finalizada',
                'bg-red-500':
                  solicitacao.status === 'cancelada' ||
                  solicitacao.status === 'resolução recusada',
                'bg-purple-500': solicitacao.status === 'retorno solicitante'
              }"
            ></span>
          </div>

          <!-- Texto do Status -->
          <span
            class="text-[11px] font-bold uppercase tracking-wider"
            :class="{
              'text-amber-700 dark:text-amber-300':
                solicitacao.status === 'pendente',
              'text-blue-700 dark:text-blue-300':
                solicitacao.status === 'em atendimento',
              'text-orange-700 dark:text-orange-300':
                solicitacao.status === 'atendimento pausado',
              'text-emerald-700 dark:text-emerald-300':
                solicitacao.status === 'resolvida' ||
                solicitacao.status === 'finalizada',
              'text-red-700 dark:text-red-300':
                solicitacao.status === 'cancelada' ||
                solicitacao.status === 'resolução recusada',
              'text-purple-700 dark:text-purple-300':
                solicitacao.status === 'retorno solicitante'
            }"
          >
            {{ solicitacao.status }}
          </span>

          <!-- Badge de Agendado (se houver) -->
          <div
            v-if="
              solicitacao.agendamentos.some(
                (ag) =>
                  ag.status != 'finalizado' &&
                  ag.status != 'cancelado' &&
                  ag.status != 'ativo'
              )
            "
            class="flex items-center gap-1 px-2 py-0.5 rounded-full bg-gradient-to-r from-cyan-500 to-teal-500 shadow-sm shadow-cyan-500/30"
          >
            <i class="pi pi-calendar-clock text-[9px] text-white"></i>
            <span
              class="text-[9px] font-bold text-white uppercase tracking-wider"
            >
              Agendado
            </span>
          </div>
        </div>
      </div>

      <!-- Navegação por Abas - Mobile -->
      <div
        class="ipad:hidden bg-gradient-to-r from-slate-50 via-white to-slate-50 dark:from-slate-800 dark:via-slate-750 dark:to-slate-800 border-b border-slate-200/80 dark:border-slate-600/50"
      >
        <div
          class="flex items-center overflow-x-auto scrollbar-hide px-2 py-2 gap-1"
        >
          <!-- Aba Detalhes -->
          <button
            @click="trocarAba('solicitacao')"
            class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-300"
            :class="
              aba == 'solicitacao'
                ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-lg shadow-indigo-500/30 scale-[1.02]'
                : 'bg-white/80 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600/50 border border-slate-200/50 dark:border-slate-600/50'
            "
          >
            <i
              class="pi pi-list text-[10px]"
              :class="aba == 'solicitacao' ? 'text-white' : 'text-indigo-500'"
            ></i>
            <span>Detalhes</span>
          </button>

          <!-- Aba Comentários -->
          <button
            @click="trocarAba('acompanhar')"
            class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-300"
            :class="
              aba == 'acompanhar'
                ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-lg shadow-indigo-500/30 scale-[1.02]'
                : 'bg-white/80 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600/50 border border-slate-200/50 dark:border-slate-600/50'
            "
          >
            <i
              class="pi pi-comments text-[10px]"
              :class="aba == 'acompanhar' ? 'text-white' : 'text-emerald-500'"
            ></i>
            <span>Comentários</span>
          </button>

          <!-- Aba Aprovações -->
          <button
            @click="trocarAba('aprovacoes')"
            class="relative flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-300"
            :class="
              aba == 'aprovacoes'
                ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-lg shadow-indigo-500/30 scale-[1.02]'
                : 'bg-white/80 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600/50 border border-slate-200/50 dark:border-slate-600/50'
            "
          >
            <i
              class="pi pi-check-circle text-[10px]"
              :class="aba == 'aprovacoes' ? 'text-white' : 'text-amber-500'"
            ></i>
            <span>Aprovações</span>
            <!-- Indicador de problemas nas aprovações -->
            <span
              v-if="temProblemasAprovacao"
              class="absolute -top-1 -right-1 flex h-3 w-3"
            >
              <span
                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"
              ></span>
              <span
                class="relative inline-flex rounded-full h-3 w-3 bg-red-500 border-2 border-white dark:border-slate-700"
              ></span>
            </span>
          </button>

          <!-- Aba Agendamento -->
          <button
            @click="trocarAba('agendamento')"
            class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-300"
            :class="
              aba == 'agendamento'
                ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-lg shadow-indigo-500/30 scale-[1.02]'
                : 'bg-white/80 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600/50 border border-slate-200/50 dark:border-slate-600/50'
            "
          >
            <i
              class="pi pi-calendar text-[10px]"
              :class="aba == 'agendamento' ? 'text-white' : 'text-purple-500'"
            ></i>
            <span>Agendamento</span>
          </button>

          <!-- Aba Histórico -->
          <button
            @click="trocarAba('historico')"
            class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-300"
            :class="
              aba == 'historico'
                ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-lg shadow-indigo-500/30 scale-[1.02]'
                : 'bg-white/80 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600/50 border border-slate-200/50 dark:border-slate-600/50'
            "
          >
            <i
              class="pi pi-history text-[10px]"
              :class="aba == 'historico' ? 'text-white' : 'text-slate-500'"
            ></i>
            <span>Histórico</span>
          </button>

          <!-- Aba Fotos -->
          <button
            @click="trocarAba('anexos')"
            class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-300"
            :class="
              aba == 'anexos'
                ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-lg shadow-indigo-500/30 scale-[1.02]'
                : 'bg-white/80 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600/50 border border-slate-200/50 dark:border-slate-600/50'
            "
          >
            <i
              class="pi pi-images text-[10px]"
              :class="aba == 'anexos' ? 'text-white' : 'text-rose-500'"
            ></i>
            <span>Fotos</span>
          </button>
        </div>
      </div>

      <!-- Área de Conteúdo Principal -->
      <div class="flex flex-1 min-h-0 overflow-hidden">
        <!-- Sidebar Desktop -->
        <div
          class="hidden ipad:flex flex-col justify-between w-48 h-full bg-gradient-to-b from-slate-50 via-white to-slate-50 dark:from-slate-800 dark:via-slate-750 dark:to-slate-800 border-r border-slate-200/80 dark:border-slate-700/50"
        >
          <!-- Menu de Navegação -->
          <div class="p-3 space-y-1">
            <!-- Aba Detalhes -->
            <button
              @click="trocarAba('solicitacao')"
              class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-300 group"
              :class="
                aba == 'solicitacao'
                  ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-lg shadow-indigo-500/25'
                  : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50'
              "
            >
              <div
                class="w-8 h-8 rounded-lg flex items-center justify-center transition-all"
                :class="
                  aba == 'solicitacao'
                    ? 'bg-white/20'
                    : 'bg-indigo-100 dark:bg-indigo-900/30 group-hover:scale-110'
                "
              >
                <i
                  class="pi pi-list text-xs"
                  :class="
                    aba == 'solicitacao'
                      ? 'text-white'
                      : 'text-indigo-600 dark:text-indigo-400'
                  "
                ></i>
              </div>
              <span>Detalhes</span>
            </button>

            <!-- Aba Comentários -->
            <button
              @click="trocarAba('acompanhar')"
              class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-300 group"
              :class="
                aba == 'acompanhar'
                  ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-lg shadow-indigo-500/25'
                  : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50'
              "
            >
              <div
                class="w-8 h-8 rounded-lg flex items-center justify-center transition-all"
                :class="
                  aba == 'acompanhar'
                    ? 'bg-white/20'
                    : 'bg-emerald-100 dark:bg-emerald-900/30 group-hover:scale-110'
                "
              >
                <i
                  class="pi pi-comments text-xs"
                  :class="
                    aba == 'acompanhar'
                      ? 'text-white'
                      : 'text-emerald-600 dark:text-emerald-400'
                  "
                ></i>
              </div>
              <span>Comentários</span>
            </button>

            <!-- Aba Aprovações -->
            <button
              @click="trocarAba('aprovacoes')"
              class="relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-300 group"
              :class="
                aba == 'aprovacoes'
                  ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-lg shadow-indigo-500/25'
                  : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50'
              "
            >
              <div
                class="w-8 h-8 rounded-lg flex items-center justify-center transition-all"
                :class="
                  aba == 'aprovacoes'
                    ? 'bg-white/20'
                    : 'bg-amber-100 dark:bg-amber-900/30 group-hover:scale-110'
                "
              >
                <i
                  class="pi pi-check-circle text-xs"
                  :class="
                    aba == 'aprovacoes'
                      ? 'text-white'
                      : 'text-amber-600 dark:text-amber-400'
                  "
                ></i>
              </div>
              <span>Aprovações</span>
              <!-- Indicador de problemas nas aprovações -->
              <span
                v-if="temProblemasAprovacao"
                class="absolute top-1.5 right-1.5 flex h-3 w-3"
              >
                <span
                  class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"
                ></span>
                <span
                  class="relative inline-flex rounded-full h-3 w-3 bg-red-500 border-2 border-white dark:border-slate-700"
                ></span>
              </span>
            </button>

            <!-- Aba Agendamento -->
            <button
              @click="trocarAba('agendamento')"
              class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-300 group"
              :class="
                aba == 'agendamento'
                  ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-lg shadow-indigo-500/25'
                  : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50'
              "
            >
              <div
                class="w-8 h-8 rounded-lg flex items-center justify-center transition-all"
                :class="
                  aba == 'agendamento'
                    ? 'bg-white/20'
                    : 'bg-purple-100 dark:bg-purple-900/30 group-hover:scale-110'
                "
              >
                <i
                  class="pi pi-calendar text-xs"
                  :class="
                    aba == 'agendamento'
                      ? 'text-white'
                      : 'text-purple-600 dark:text-purple-400'
                  "
                ></i>
              </div>
              <span>Agendamento</span>
            </button>

            <!-- Aba Histórico -->
            <button
              @click="trocarAba('historico')"
              class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-300 group"
              :class="
                aba == 'historico'
                  ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-lg shadow-indigo-500/25'
                  : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50'
              "
            >
              <div
                class="w-8 h-8 rounded-lg flex items-center justify-center transition-all"
                :class="
                  aba == 'historico'
                    ? 'bg-white/20'
                    : 'bg-slate-200 dark:bg-slate-600/30 group-hover:scale-110'
                "
              >
                <i
                  class="pi pi-history text-xs"
                  :class="
                    aba == 'historico'
                      ? 'text-white'
                      : 'text-slate-600 dark:text-slate-400'
                  "
                ></i>
              </div>
              <span>Histórico</span>
            </button>

            <!-- Aba Fotos -->
            <button
              @click="trocarAba('anexos')"
              class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-300 group"
              :class="
                aba == 'anexos'
                  ? 'bg-gradient-to-r from-indigo-500 to-blue-600 text-white shadow-lg shadow-indigo-500/25'
                  : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50'
              "
            >
              <div
                class="w-8 h-8 rounded-lg flex items-center justify-center transition-all"
                :class="
                  aba == 'anexos'
                    ? 'bg-white/20'
                    : 'bg-rose-100 dark:bg-rose-900/30 group-hover:scale-110'
                "
              >
                <i
                  class="pi pi-images text-xs"
                  :class="
                    aba == 'anexos'
                      ? 'text-white'
                      : 'text-rose-600 dark:text-rose-400'
                  "
                ></i>
              </div>
              <span>Fotos</span>
            </button>
          </div>

          <!-- Botões de Ação na Sidebar -->
          <div
            class="p-3 space-y-2 border-t border-slate-200/80 dark:border-slate-700/50"
          >
            <!-- Botão Atender -->
            <div
              v-if="
                solicitacao.usuario_responsavel &&
                solicitacao.usuario_responsavel.matricula ==
                  props.auth.matricula &&
                (solicitacao.status == 'pendente' ||
                  solicitacao.status == 'atendimento pausado' ||
                  solicitacao.status == 'resolução recusada' ||
                  (solicitacao.status == 'agendado' &&
                    solicitacao.agendamentos.every(
                      (ag) =>
                        ag.tipo == 'lembrete' ||
                        ag.status == 'cancelado' ||
                        ag.status == 'finalizado'
                    )))
              "
            >
              <Button
                @click="iniciarAtendimento()"
                label="Atender"
                icon="pi pi-play"
                class="w-full !h-10 !rounded-xl !bg-gradient-to-r !from-indigo-500 !to-blue-600 hover:!from-indigo-600 hover:!to-blue-700 !shadow-lg !shadow-indigo-500/25 !border-0"
              ></Button>
            </div>

            <!-- Botões de Ação durante Atendimento -->
            <div
              v-if="
                solicitacao.usuario_responsavel &&
                solicitacao.usuario_responsavel.matricula ==
                  props.auth.matricula &&
                solicitacao.status == 'em atendimento' &&
                !solicitacao.agendamentos.some(
                  (ag) =>
                    ag.status != 'finalizado' &&
                    ag.status != 'cancelado' &&
                    ag.tipo != 'lembrete'
                )
              "
              class="space-y-2"
            >
              <Button
                @click="dialogRetorno = true"
                label="Devolver"
                icon="pi pi-replay"
                class="w-full !h-9 !rounded-xl"
                severity="warn"
                outlined
              />
              <Button
                @click="pausarAtendimento()"
                label="Pausar"
                severity="danger"
                class="w-full !h-9 !rounded-xl"
                icon="pi pi-pause"
                outlined
              ></Button>

              <Button
                :disabled="
                  solicitacao.agendamentos.some(
                    (ag) =>
                      ag.status != 'finalizado' &&
                      ag.status != 'cancelado' &&
                      ag.tipo != 'lembrete'
                  )
                "
                @click="resolver()"
                label="Resolver"
                severity="success"
                class="w-full !h-10 !rounded-xl !bg-gradient-to-r !from-emerald-500 !to-green-600 hover:!from-emerald-600 hover:!to-green-700 !shadow-lg !shadow-emerald-500/25 !border-0"
                icon="pi pi-check"
              ></Button>
            </div>
          </div>
        </div>

        <!-- Área de Conteúdo -->
        <div
          class="flex flex-col w-full flex-1 min-h-0 overflow-hidden bg-gradient-to-br from-slate-50/50 via-white to-slate-50/50 dark:from-slate-800/50 dark:via-slate-750 dark:to-slate-800/50"
        >
          <div
            v-if="aba == 'solicitacao'"
            class="flex flex-col space-y-3 w-full overflow-auto flex-1 min-h-0 p-3 ipad:p-4"
          >
            <div class="select-text">
              <!-- SOLICITAÇÃO RESOLVIDA -->
              <div
                v-if="solicitacao.status == 'resolvida' && solicitacao.isOwner"
                class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 mb-5 bg-gradient-to-r from-emerald-50 via-green-50 to-teal-50 dark:from-emerald-900/30 dark:via-green-900/30 dark:to-teal-900/30 rounded-2xl border border-emerald-200/50 dark:border-emerald-700/50 shadow-lg shadow-emerald-500/10"
              >
                <div class="flex items-center gap-3">
                  <div
                    class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-green-500 rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-500/30"
                  >
                    <i class="pi pi-check-circle text-white text-xl"></i>
                  </div>
                  <div>
                    <h3
                      class="text-lg font-bold text-emerald-800 dark:text-emerald-200"
                    >
                      Sua ticket foi resolvida
                    </h3>
                    <p class="text-sm text-emerald-600 dark:text-emerald-400">
                      Confirme a resolução ou recuse para retornar ao atendente
                    </p>
                  </div>
                </div>

                <div class="flex items-center w-full sm:w-auto gap-3">
                  <Button
                    @click="finalizar()"
                    severity="success"
                    class="!h-11 flex-1 sm:flex-none !rounded-xl !bg-gradient-to-r !from-emerald-500 !to-green-600 hover:!from-emerald-600 hover:!to-green-700 !shadow-lg !shadow-emerald-500/25 !border-0 animate-pulse"
                    label="Finalizar"
                    icon="pi pi-check"
                  ></Button>
                  <Button
                    @click="showDialogRecusar()"
                    severity="danger"
                    class="!h-11 flex-1 sm:flex-none !rounded-xl"
                    title="Voltar ticket ao atendente"
                    label="Recusar"
                    icon="pi pi-times"
                    outlined
                  ></Button>
                </div>
              </div>

              <!-- ALERTA DE APROVAÇÕES -->
              <div
                v-if="temProblemasAprovacao"
                class="mb-4 rounded-xl border overflow-hidden"
                :class="{
                  'bg-gradient-to-r from-amber-50 to-yellow-50 border-amber-200':
                    aprovacaoStats.pendentes > 0,
                  'bg-gradient-to-r from-red-50 to-rose-50 border-red-200':
                    aprovacaoStats.rejeitadas > 0 &&
                    aprovacaoStats.pendentes === 0
                }"
              >
                <div class="flex items-center justify-between px-3 py-2">
                  <div class="flex items-center gap-2">
                    <div
                      class="w-7 h-7 rounded-lg flex items-center justify-center"
                      :class="{
                        'bg-amber-100': aprovacaoStats.pendentes > 0,
                        'bg-red-100':
                          aprovacaoStats.rejeitadas > 0 &&
                          aprovacaoStats.pendentes === 0
                      }"
                    >
                      <i
                        v-if="aprovacaoStats.pendentes > 0"
                        class="pi pi-clock text-amber-600 text-xs"
                      ></i>
                      <i
                        v-else-if="aprovacaoStats.rejeitadas > 0"
                        class="pi pi-times text-red-600 text-xs"
                      ></i>
                    </div>
                    <span
                      class="text-xs font-semibold"
                      :class="{
                        'text-amber-700': aprovacaoStats.pendentes > 0,
                        'text-red-700':
                          aprovacaoStats.rejeitadas > 0 &&
                          aprovacaoStats.pendentes === 0
                      }"
                    >
                      {{ mensagemAlerteAprovacao }}
                    </span>
                  </div>
                  <button
                    @click="trocarAba('aprovacoes')"
                    class="text-xs font-semibold px-2.5 py-1 rounded-lg transition-all"
                    :class="{
                      'text-amber-700 bg-amber-100 hover:bg-amber-200':
                        aprovacaoStats.pendentes > 0,
                      'text-red-700 bg-red-100 hover:bg-red-200':
                        aprovacaoStats.rejeitadas > 0 &&
                        aprovacaoStats.pendentes === 0
                    }"
                  >
                    Ver →
                  </button>
                </div>
              </div>

              <!-- RESPONSÁVEL PELA SOLICITAÇÃO -->
              <div
                class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl overflow-hidden mb-4"
              >
                <!-- Header do Card -->
                <div
                  class="bg-gradient-to-r from-indigo-50 to-blue-50 dark:from-slate-700 dark:to-slate-700 px-3 py-2 border-b border-indigo-100 dark:border-slate-600"
                >
                  <div class="flex items-center gap-2">
                    <div
                      class="w-6 h-6 bg-gradient-to-br from-indigo-500 to-blue-600 rounded-md flex items-center justify-center shadow-sm"
                    >
                      <i class="pi pi-user text-white text-[10px]"></i>
                    </div>
                    <h3 class="text-xs font-bold text-gray-800 dark:text-white">
                      Responsável pelo Ticket
                    </h3>
                  </div>
                </div>

                <!-- Conteúdo -->
                <div class="p-3">
                  <div
                    class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3"
                  >
                    <!-- Informações do Responsável -->
                    <div class="flex-1 flex">
                      <div
                        v-if="!solicitacao.usuario_responsavel"
                        class="flex items-center gap-2 p-2 bg-gradient-to-br from-gray-50 to-slate-100 dark:from-slate-800 dark:to-slate-700 rounded-lg border border-dashed border-gray-300 dark:border-slate-500 w-full"
                      >
                        <div
                          class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-600 flex items-center justify-center"
                        >
                          <i
                            class="pi pi-user-plus text-gray-400 dark:text-gray-500 text-xs"
                          ></i>
                        </div>
                        <div>
                          <p
                            class="text-xs font-medium text-gray-600 dark:text-gray-300"
                          >
                            Nenhum responsável atribuído
                          </p>
                          <p
                            class="text-[10px] text-gray-400 dark:text-gray-500"
                          >
                            Aguardando atribuição
                          </p>
                        </div>
                      </div>
                      <div
                        v-else
                        class="flex items-center gap-2 p-2 rounded-lg border border-gray-200/60 dark:border-slate-600/50"
                      >
                        <!-- Avatar do Responsável -->
                        <div class="relative flex-shrink-0">
                          <div
                            v-if="solicitacao.usuario_responsavel.foto_perfil"
                            class="w-9 h-9 rounded-lg overflow-hidden ring-2 ring-offset-1 ring-indigo-400"
                            v-tooltip.top="solicitacao.usuario_responsavel.nome"
                          >
                            <img
                              :src="solicitacao.usuario_responsavel.foto_perfil"
                              :alt="solicitacao.usuario_responsavel.nome"
                              class="w-full h-full object-cover"
                            />
                          </div>
                          <div
                            v-else
                            class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white font-bold text-xs ring-2 ring-offset-1 ring-indigo-400 shadow-md"
                            v-tooltip.top="solicitacao.usuario_responsavel.nome"
                          >
                            {{
                              obterIniciais(
                                solicitacao.usuario_responsavel.nome
                              )
                            }}
                          </div>
                          <!-- Online indicator -->
                          <span
                            class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 border-2 border-white dark:border-slate-700 rounded-full"
                          ></span>
                        </div>
                        <div class="flex-1 min-w-0">
                          <p
                            class="text-sm font-semibold text-gray-800 dark:text-white truncate"
                          >
                            {{
                              obterNomeSobrenome(
                                solicitacao.usuario_responsavel.nome
                              )
                            }}
                          </p>
                          <p
                            class="text-[10px] text-gray-500 dark:text-gray-400 truncate"
                          >
                            {{ solicitacao.usuario_responsavel.email }}
                          </p>
                          <p
                            class="text-[10px] text-indigo-600 dark:text-indigo-400 font-medium"
                          >
                            Mat. {{ solicitacao.usuario_responsavel.matricula }}
                          </p>
                        </div>
                      </div>
                    </div>

                    <!-- Previsão de Entrega -->
                    <div
                      v-if="mostrarPrevisao"
                      class="flex flex-col p-2 rounded-lg border border-gray-200/60 dark:border-slate-600/50 min-w-0 lg:w-[280px] lg:flex-shrink-0"
                    >
                      <div class="flex items-center gap-1.5 mb-1.5">
                        <span
                          class="w-5 h-5 bg-gradient-to-br from-amber-400 to-orange-500 rounded-md flex items-center justify-center shadow-sm"
                        >
                          <i class="pi pi-calendar text-white text-[10px]"></i>
                        </span>
                        <span
                          class="font-semibold text-xs text-gray-700 dark:text-gray-300"
                        >
                          Previsão de Entrega
                        </span>
                      </div>

                      <!-- Modo de Visualização -->
                      <div
                        v-if="!editandoPrevisao"
                        class="flex items-center justify-between"
                      >
                        <div
                          v-if="previsaoFormatada"
                          class="flex items-center gap-1.5"
                          v-tooltip.top="
                            'Data prevista para entrega desta ticket'
                          "
                        >
                          <div
                            class="text-sm font-bold text-gray-800 dark:text-white"
                          >
                            {{ previsaoFormatada }}
                          </div>
                        </div>
                        <div
                          v-else
                          class="text-xs text-gray-500 dark:text-gray-400"
                        >
                          Não definida
                        </div>

                        <button
                          v-if="podeEditarPrevisao"
                          @click="iniciarEdicaoPrevisao()"
                          class="ml-2 flex items-center justify-center w-6 h-6 text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 rounded-md hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-colors"
                          v-tooltip.top="
                            previsaoFormatada
                              ? 'Editar previsão'
                              : 'Adicionar previsão'
                          "
                        >
                          <i
                            :class="
                              previsaoFormatada ? 'pi pi-pencil' : 'pi pi-plus'
                            "
                            class="text-[10px]"
                          ></i>
                        </button>
                      </div>

                      <!-- Modo de Edição -->
                      <div
                        v-else
                        class="space-y-2"
                      >
                        <DatePicker
                          v-model="previsaoEntrega"
                          date-format="dd/mm/yy"
                          :min-date="new Date()"
                          placeholder="Selecione uma data"
                          class="w-full"
                          fluid
                          iconDisplay="input"
                          showButtonBar
                          :showClear="true"
                        />
                        <div class="flex items-center gap-2">
                          <Button
                            @click="cancelarEdicaoPrevisao()"
                            icon="pi pi-times"
                            label="Cancelar"
                            outlined
                            severity="secondary"
                            size="small"
                            text
                            class="flex-1 !h-7"
                          />
                          <Button
                            @click="atualizarPrevisaoEntrega()"
                            :disabled="loading"
                            icon="pi pi-check"
                            label="Salvar"
                            severity="success"
                            outlined
                            size="small"
                            class="flex-1 !h-7"
                          />
                        </div>
                      </div>
                    </div>

                    <!-- Botões de Ações -->
                    <div
                      v-if="
                        solicitacao.isDepartamento &&
                        validaPermissao(
                          'solicitacoes.lista.atribuir-responsaveis'
                        ) &&
                        solicitacao.status != 'cancelada' &&
                        solicitacao.status != 'finalizada' &&
                        solicitacao.status != 'resolvida'
                      "
                      class="flex flex-wrap items-center gap-1.5 lg:ml-1"
                    >
                      <Button
                        @click="showDialogTrocarResponsavel()"
                        outlined
                        :label="
                          solicitacao.usuario_responsavel
                            ? 'Alterar'
                            : 'Atribuir'
                        "
                        :icon="
                          solicitacao.usuario_responsavel
                            ? 'pi pi-user-edit'
                            : 'pi pi-user-plus'
                        "
                        class="flex-1 sm:flex-none !rounded-lg !h-8 !text-xs"
                        severity="info"
                      />

                      <Button
                        v-if="solicitacao.usuario_responsavel"
                        @click="removerResponsavel()"
                        :disabled="
                          solicitacao.agendamentos.length > 0 &&
                          solicitacao.agendamentos.some(
                            (ag) =>
                              ag.status != 'finalizado' &&
                              ag.status != 'cancelado'
                          )
                        "
                        label="Remover"
                        icon="pi pi-user-minus"
                        severity="danger"
                        outlined
                        class="flex-1 sm:flex-none !rounded-lg !h-8 !text-xs"
                      />

                      <Button
                        v-if="!solicitacao.usuario_responsavel"
                        @click="seAtribuir()"
                        icon="pi pi-user-plus"
                        v-tooltip.top="'Me atribuir'"
                        severity="success"
                        class="!rounded-xl !w-10 !h-10"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <!-- CABEÇALHO DA SOLICITAÇÃO -->
              <div
                class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl p-3 sm:p-4 mb-4"
              >
                <div
                  class="flex flex-col sm:flex-row flex-wrap items-start gap-4 sm:gap-6 lg:gap-8 min-w-0"
                >
                  <!-- Departamento Responsável -->
                  <div
                    class="flex flex-col gap-1 w-full sm:w-auto sm:min-w-[150px] shrink-0"
                  >
                    <label
                      class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-1"
                    >
                      <i
                        class="pi pi-building text-[10px] text-indigo-500 dark:text-indigo-400"
                      ></i>
                      Depto Responsável
                    </label>
                    <div class="flex items-center gap-2">
                      <span
                        class="text-sm font-medium text-gray-800 dark:text-white"
                      >
                        {{ solicitacao.departamento_responsavel }}
                      </span>
                      <i
                        @click="abrirTrocaDepartamento()"
                        v-if="
                          (solicitacao.status == 'pendente' ||
                            solicitacao.status == 'atendimento pausado' ||
                            solicitacao.status == 'resolução recusada') &&
                          validaPermissao(
                            'solicitacoes.lista.alterar-departamento'
                          )
                        "
                        class="pi pi-arrow-right-arrow-left text-xs cursor-pointer text-blue-500 hover:text-blue-700 transition-colors p-1 hover:bg-blue-50 dark:hover:bg-slate-700 rounded-md"
                        v-tooltip.top="'Alterar departamento responsável'"
                      ></i>
                    </div>
                  </div>

                  <!-- Assunto -->
                  <div
                    class="flex flex-col gap-1 w-full sm:w-auto sm:min-w-[120px] sm:max-w-[250px] min-w-0 shrink"
                  >
                    <label
                      class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-1"
                    >
                      <i
                        class="pi pi-tag text-[10px] text-emerald-500 dark:text-emerald-400"
                      ></i>
                      Assunto
                    </label>
                    <div class="flex items-center gap-2 min-w-0">
                      <span
                        class="text-sm font-medium text-gray-800 dark:text-white truncate"
                        v-tooltip.bottom="
                          solicitacao.assunto != null
                            ? solicitacao.assunto.assunto
                            : 'Transferido'
                        "
                      >
                        {{
                          solicitacao.assunto != null
                            ? solicitacao.assunto.assunto
                            : "Transferido"
                        }}
                      </span>
                      <i
                        @click="abrirTrocaAssunto()"
                        v-if="
                          (solicitacao.status == 'pendente' ||
                            solicitacao.status == 'atendimento pausado' ||
                            solicitacao.status == 'resolução recusada') &&
                          validaPermissao(
                            'solicitacoes.lista.alterar-departamento'
                          )
                        "
                        class="pi pi-arrow-right-arrow-left text-xs cursor-pointer text-blue-500 hover:text-blue-700 transition-colors p-1 hover:bg-blue-50 dark:hover:bg-slate-700 rounded-md"
                        v-tooltip.top="'Alterar assunto'"
                      ></i>
                    </div>
                  </div>

                  <!-- Etapa de Andamento (se o assunto tiver etapas configuradas OU se já tem etapa definida via workflow) -->
                  <div
                    v-if="
                      (solicitacao.etapas_disponiveis &&
                        solicitacao.etapas_disponiveis.length > 0) ||
                      solicitacao.etapa_atual
                    "
                    class="flex flex-col gap-1 w-full sm:w-auto sm:min-w-[150px] min-w-0 shrink"
                  >
                    <label
                      class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-1"
                    >
                      <i
                        class="pi pi-sitemap text-[10px] text-fuchsia-500 dark:text-fuchsia-400"
                      ></i>
                      Etapa de Andamento
                    </label>
                    <div class="flex items-center gap-2">
                      <!-- Select para quem pode editar -->
                      <Select
                        v-if="
                          solicitacao.isDepartamento &&
                          solicitacao.etapas_disponiveis &&
                          solicitacao.etapas_disponiveis.length > 0 &&
                          !['cancelada', 'finalizada'].includes(
                            solicitacao.status
                          )
                        "
                        v-model="etapaSelecionada"
                        :options="solicitacao.etapas_disponiveis"
                        optionLabel="nome"
                        optionValue="id"
                        placeholder="Selecione a etapa"
                        class="w-full text-sm"
                        :disabled="
                          fluxoDados?.etapa_atual?.etapa_andamento_id != null
                        "
                        @change="alterarEtapa"
                        v-tooltip.bottom="
                          fluxoDados?.etapa_atual?.etapa_andamento_id != null
                            ? 'Controlada automaticamente pelo workflow'
                            : null
                        "
                      >
                        <template #value="slotProps">
                          <div
                            v-if="slotProps.value"
                            class="flex items-center gap-2"
                          >
                            <span
                              class="w-3 h-3 rounded-full"
                              :style="{
                                backgroundColor: obterCorEtapa(slotProps.value)
                              }"
                            ></span>
                            <span class="font-medium">
                              {{ obterNomeEtapa(slotProps.value) }}
                            </span>
                          </div>
                          <span
                            v-else
                            class="text-gray-400"
                          >
                            Não definida
                          </span>
                        </template>
                        <template #option="slotProps">
                          <div
                            class="flex items-center gap-2"
                            v-tooltip.right="slotProps.option.descricao || null"
                          >
                            <span
                              class="w-3 h-3 rounded-full"
                              :style="{ backgroundColor: slotProps.option.cor }"
                            ></span>
                            <i
                              :class="slotProps.option.icone"
                              class="text-xs"
                            ></i>
                            <span>{{ slotProps.option.nome }}</span>
                          </div>
                        </template>
                      </Select>
                      <div
                        v-else-if="solicitacao.etapa_atual"
                        class="flex items-center gap-2"
                        v-tooltip.bottom="
                          solicitacao.etapa_atual.descricao || null
                        "
                      >
                        <span
                          class="w-3 h-3 rounded-full"
                          :style="{
                            backgroundColor: solicitacao.etapa_atual.cor
                          }"
                        ></span>
                        <i
                          :class="solicitacao.etapa_atual.icone"
                          class="text-xs"
                        ></i>
                        <span
                          class="text-sm font-medium text-gray-800 dark:text-white"
                        >
                          {{ solicitacao.etapa_atual.nome }}
                        </span>
                      </div>
                      <span
                        v-else
                        class="text-sm text-gray-400"
                      >
                        Não definida
                      </span>
                    </div>
                  </div>

                  <!-- Data Criação -->
                  <div class="flex flex-col gap-1 w-full sm:w-auto shrink-0">
                    <label
                      class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-1"
                    >
                      <i
                        class="pi pi-calendar text-[10px] text-orange-500 dark:text-orange-400"
                      ></i>
                      Data Criação
                    </label>
                    <div class="flex items-center gap-2">
                      <span
                        class="text-sm font-medium text-gray-800 dark:text-white"
                      >
                        {{ formatarData(solicitacao.created_at) }}
                      </span>
                      <span
                        class="text-xs px-2 py-0.5 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400 rounded-full font-medium"
                      >
                        {{ parseInt(solicitacao.diasAberto) }} dias
                      </span>
                    </div>
                  </div>

                  <!-- Ações Rápidas -->
                  <div
                    class="flex flex-col sm:flex-row items-start sm:items-center gap-2 w-full sm:w-auto sm:ml-auto shrink-0"
                  >
                    <Button
                      v-if="
                        validaPermissao(
                          'solicitacoes.lista.criar-agendamento'
                        ) && solicitacao.isDepartamento
                      "
                      @click="novoAgendamento()"
                      label="Agendamento"
                      outlined
                      icon="pi pi-calendar-plus"
                      size="small"
                      class="w-full sm:w-auto !rounded-xl !h-9"
                      :disabled="
                        (agendamentos.length > 0 &&
                          agendamentos.some(
                            (ag) =>
                              ag.status != 'cancelado' &&
                              ag.status != 'finalizado'
                          )) ||
                        ![
                          'pendente',
                          'atendimento pausado',
                          'resolução recusada'
                        ].includes(solicitacao.status)
                      "
                    />

                    <Button
                      v-if="
                        solicitacao.status != 'cancelada' &&
                        solicitacao.status != 'finalizada' &&
                        solicitacao.status != 'resolvida' &&
                        (solicitacao.isOwner || solicitacao.isDepartamento) &&
                        (validaPermissao(
                          'solicitacoes.lista.cancelar-solicitacao'
                        ) ||
                          solicitacao.usuarioLogado.matricula ==
                            solicitacao.usuario_solicitante.matricula)
                      "
                      severity="danger"
                      outlined
                      size="small"
                      class="w-full sm:w-auto !rounded-xl !h-9"
                      label="Cancelar"
                      icon="pi pi-times"
                      @click="showDialogCancelar()"
                    />
                  </div>
                </div>
              </div>

              <!-- CARD FLUXO/WORKFLOW -->
              <div
                v-if="fluxoDados"
                class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl overflow-hidden mb-4"
              >
                <!-- Header do Card -->
                <div
                  class="bg-gradient-to-r from-slate-50 to-gray-50 dark:from-slate-700 dark:to-slate-700 px-4 py-3 border-b border-gray-100 dark:border-slate-600"
                >
                  <div class="flex items-center gap-2">
                    <div
                      class="w-8 h-8 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-lg flex items-center justify-center shadow-sm"
                    >
                      <i class="pi pi-directions text-white text-xs"></i>
                    </div>
                    <h3 class="text-sm font-bold text-gray-800 dark:text-white">
                      Fluxo: {{ fluxoDados.fluxo?.nome }}
                    </h3>
                    <span
                      v-if="fluxoDados.is_concluido"
                      class="ml-auto flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400"
                    >
                      <i class="pi pi-check-circle"></i>
                      Concluido
                    </span>
                    <span
                      v-else-if="fluxoDados.is_cancelado"
                      class="ml-auto flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400"
                    >
                      <i class="pi pi-times-circle"></i>
                      Cancelado
                    </span>
                  </div>
                </div>

                <!-- Conteúdo -->
                <div class="p-4">
                  <!-- Timeline visual do fluxo -->
                  <div class="flex flex-wrap items-center gap-2">
                    <template
                      v-for="(etapaFluxo, idx) in fluxoDados.etapas"
                      :key="etapaFluxo.id"
                    >
                      <div
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-all"
                        :class="{
                          'ring-2 ring-offset-1 shadow-md':
                            fluxoDados.etapa_atual?.id === etapaFluxo.id,
                          'opacity-40':
                            fluxoDados.is_concluido &&
                            fluxoDados.etapa_atual?.id !== etapaFluxo.id
                        }"
                        :style="{
                          backgroundColor:
                            fluxoDados.etapa_atual?.id === etapaFluxo.id
                              ? etapaFluxo.cor || '#3B82F6'
                              : '#e5e7eb',
                          color:
                            fluxoDados.etapa_atual?.id === etapaFluxo.id
                              ? 'white'
                              : '#6b7280',
                          ringColor: etapaFluxo.cor || '#3B82F6'
                        }"
                        v-tooltip.bottom="
                          etapaFluxo.departamento +
                          (etapaFluxo.descricao
                            ? ' — ' + etapaFluxo.descricao
                            : '')
                        "
                      >
                        <i
                          :class="etapaFluxo.icone || 'pi pi-circle'"
                          class="text-[10px]"
                        ></i>
                        {{ etapaFluxo.nome }}
                      </div>
                      <i
                        v-if="idx < fluxoDados.etapas.length - 1"
                        class="pi pi-arrow-right text-gray-300 text-[10px]"
                      ></i>
                    </template>
                  </div>

                  <!-- SLA / Prazo da etapa atual -->
                  <div
                    v-if="fluxoDados.sla"
                    class="flex items-center gap-2 mt-2"
                  >
                    <span
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold"
                      :class="
                        fluxoDados.sla.atrasado
                          ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
                          : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'
                      "
                    >
                      <i
                        :class="
                          fluxoDados.sla.atrasado
                            ? 'pi pi-exclamation-triangle'
                            : 'pi pi-clock'
                        "
                        class="text-[10px]"
                      ></i>
                      <template v-if="fluxoDados.sla.atrasado">
                        SLA excedido
                      </template>
                      <template v-else>
                        {{ Math.round(fluxoDados.sla.horas_restantes) }}h
                        restantes
                      </template>
                      <span class="opacity-70">
                        (prazo: {{ fluxoDados.sla.prazo_horas }}h)
                      </span>
                    </span>
                  </div>

                  <!-- Quem atendeu cada etapa -->
                  <div
                    v-if="
                      fluxoDados.atendentes &&
                      Object.keys(fluxoDados.atendentes).length > 0
                    "
                    class="flex flex-wrap items-center gap-3 mt-3 pt-2 border-t border-dashed border-gray-200 dark:border-slate-700"
                  >
                    <template
                      v-for="(etapaFluxo, idx) in fluxoDados.etapas"
                      :key="'atendente-' + etapaFluxo.id"
                    >
                      <div
                        v-if="fluxoDados.atendentes[etapaFluxo.id]"
                        class="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400"
                      >
                        <i
                          class="pi pi-user text-[10px]"
                          :style="{ color: etapaFluxo.cor || '#6b7280' }"
                        ></i>
                        <span
                          class="font-medium"
                          :style="{ color: etapaFluxo.cor || '#6b7280' }"
                        >
                          {{ etapaFluxo.nome }}:
                        </span>
                        <span>
                          {{ fluxoDados.atendentes[etapaFluxo.id].nome_curto }}
                        </span>
                        <span class="opacity-60">
                          ({{ fluxoDados.atendentes[etapaFluxo.id].matricula }})
                        </span>
                      </div>
                    </template>
                  </div>

                  <!-- Campos preenchidos por etapa (oculto só para o solicitante que vê os editáveis) -->
                  <div
                    v-if="
                      fluxoDados.campos_preenchidos_por_etapa &&
                      Object.keys(fluxoDados.campos_preenchidos_por_etapa)
                        .length > 0 &&
                      !(
                        fluxoDados.is_aguardando_solicitante &&
                        fluxoDados.campos_etapa?.length &&
                        solicitacao.usuario_solicitante?.matricula ==
                          props.auth.matricula
                      )
                    "
                    class="mt-3 pt-3 border-t border-dashed border-gray-200 dark:border-slate-700"
                  >
                    <div
                      v-for="etapaFluxo in fluxoDados.etapas.filter((e) =>
                        fluxoDados.campos_preenchidos_por_etapa[e.id]?.some(
                          (c) =>
                            c.valor !== null &&
                            c.valor !== '' &&
                            c.valor !== undefined
                        )
                      )"
                      :key="'campos-' + etapaFluxo.id"
                      class="mb-2"
                    >
                      <div class="flex items-center gap-1.5 mb-1">
                        <i
                          class="pi pi-list-check text-[10px]"
                          :style="{ color: etapaFluxo.cor || '#6b7280' }"
                        ></i>
                        <span
                          class="text-[11px] font-semibold"
                          :style="{ color: etapaFluxo.cor || '#6b7280' }"
                        >
                          {{ etapaFluxo.nome }}
                        </span>
                      </div>
                      <div
                        class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-1 pl-4"
                      >
                        <div
                          v-for="(
                            campo, cIdx
                          ) in fluxoDados.campos_preenchidos_por_etapa[
                            etapaFluxo.id
                          ].filter(
                            (c) =>
                              c.valor !== null &&
                              c.valor !== '' &&
                              c.valor !== undefined
                          )"
                          :key="cIdx"
                          class="text-[11px]"
                        >
                          <span class="text-gray-500 dark:text-gray-400">
                            {{ campo.label }}:
                          </span>
                          <template
                            v-if="
                              campo.tipo === 'arquivo' &&
                              parseCampoArquivo(campo.valor)
                            "
                          >
                            <button
                              type="button"
                              @click="visualizarCampoArquivo(campo.valor)"
                              class="ml-1 text-blue-600 hover:underline font-medium inline-flex items-center gap-1 cursor-pointer"
                            >
                              <i class="pi pi-file text-[10px]"></i>
                              {{ parseCampoArquivo(campo.valor).file_name }}
                            </button>
                          </template>
                          <span
                            v-else
                            class="ml-1 font-medium text-gray-700 dark:text-gray-300"
                          >
                            {{ campo.valor }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Tickets vinculados -->
                  <div
                    v-if="
                      fluxoDados.solicitacao_pai ||
                      (fluxoDados.solicitacoes_filhas &&
                        fluxoDados.solicitacoes_filhas.length > 0)
                    "
                    class="mt-3 pt-3 border-t border-dashed border-gray-200 dark:border-slate-700"
                  >
                    <div class="flex items-center gap-1.5 mb-2">
                      <i class="pi pi-link text-[10px] text-violet-500"></i>
                      <span
                        class="text-[11px] font-semibold text-violet-600 dark:text-violet-400"
                      >
                        Tickets Vinculadas
                      </span>
                    </div>
                    <!-- Pai -->
                    <div
                      v-if="fluxoDados.solicitacao_pai"
                      class="flex items-center gap-2 text-[11px] text-gray-600 dark:text-gray-400 mb-1"
                    >
                      <i
                        class="pi pi-arrow-up-left text-[10px] text-gray-400"
                      ></i>
                      <span class="font-medium">Origem:</span>
                      <a
                        :href="
                          '/solicitacoes/lista/' + fluxoDados.solicitacao_pai.id
                        "
                        class="text-blue-600 dark:text-blue-400 hover:underline"
                      >
                        #{{ fluxoDados.solicitacao_pai.id }} —
                        {{ fluxoDados.solicitacao_pai.titulo }}
                      </a>
                      <span
                        class="px-1.5 py-0.5 rounded text-[10px] font-medium"
                        :class="{
                          'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400':
                            fluxoDados.solicitacao_pai.status === 'finalizada',
                          'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400':
                            !['finalizada', 'cancelada'].includes(
                              fluxoDados.solicitacao_pai.status
                            ),
                          'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400':
                            fluxoDados.solicitacao_pai.status === 'cancelada'
                        }"
                      >
                        {{ fluxoDados.solicitacao_pai.status }}
                      </span>
                    </div>
                    <!-- Filhas -->
                    <div
                      v-for="filha in fluxoDados.solicitacoes_filhas"
                      :key="filha.id"
                      class="flex items-center gap-2 text-[11px] text-gray-600 dark:text-gray-400 mb-1"
                    >
                      <i
                        class="pi pi-arrow-down-right text-[10px] text-gray-400"
                      ></i>
                      <span class="font-medium">Vinculada:</span>
                      <a
                        :href="'/solicitacoes/lista/' + filha.id"
                        class="text-blue-600 dark:text-blue-400 hover:underline"
                      >
                        #{{ filha.id }} — {{ filha.titulo }}
                      </a>
                      <span
                        class="px-1.5 py-0.5 rounded text-[10px] font-medium"
                        :class="{
                          'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400':
                            filha.status === 'finalizada',
                          'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400':
                            !['finalizada', 'cancelada'].includes(filha.status),
                          'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400':
                            filha.status === 'cancelada'
                        }"
                      >
                        {{ filha.status }}
                      </span>
                    </div>
                  </div>

                  <!-- Aviso: aguardando retorno do solicitante (mecanismo ad-hoc `voltar_solicitante`).
                       Não aparece quando a etapa é do Modo Exclusivo ('E'), pois nesse caso
                       o fluxo usa o bloco padrão de ações (Avançar) — ambos compartilham o
                       status `aguardando_solicitante`, mas têm UX distintas. -->
                  <div
                    v-if="
                      fluxoDados.is_aguardando_solicitante &&
                      !fluxoDados.is_modo_exclusivo
                    "
                    class="mt-3 pt-3 border-t border-dashed border-gray-200 dark:border-slate-700"
                  >
                    <div class="flex items-center justify-between">
                      <div
                        class="flex items-center gap-2 text-xs text-orange-600 dark:text-orange-400"
                      >
                        <i class="pi pi-replay text-sm"></i>
                        <span class="font-medium">
                          Aguardando retorno do solicitante para continuar o
                          fluxo
                        </span>
                      </div>
                    </div>

                    <!-- Motivo do retorno -->
                    <div
                      v-if="fluxoDados.motivo_retorno_solicitante"
                      class="mt-2 p-2 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg"
                    >
                      <div class="flex items-center gap-2">
                        <i
                          class="pi pi-info-circle text-orange-500 text-sm flex-shrink-0"
                        ></i>
                        <span
                          class="text-xs text-orange-700 dark:text-orange-300"
                        >
                          <span class="font-semibold">Motivo:</span>
                          {{ fluxoDados.motivo_retorno_solicitante }}
                        </span>
                      </div>
                    </div>

                    <!-- Campos da etapa para o solicitante corrigir -->
                    <div
                      v-if="
                        fluxoDados.campos_etapa?.length &&
                        solicitacao.usuario_solicitante?.matricula ==
                          props.auth.matricula
                      "
                      class="mt-3"
                    >
                      <div class="flex items-center gap-2 mb-3">
                        <i class="pi pi-list-check text-sm text-orange-500"></i>
                        <span
                          class="text-xs font-semibold text-gray-700 dark:text-gray-300"
                        >
                          Campos para correção
                        </span>
                      </div>
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div
                          v-for="campo in fluxoDados.campos_etapa"
                          :key="campo.id"
                          class="flex flex-col gap-1"
                        >
                          <label
                            class="text-xs font-medium text-gray-600 dark:text-gray-400"
                          >
                            {{ campo.label }}
                            <span
                              v-if="campo.obrigatorio === 'S'"
                              class="text-red-500"
                            >
                              *
                            </span>
                          </label>
                          <InputText
                            v-if="campo.tipo === 'texto'"
                            v-model="valoresCamposFluxo[campo.id]"
                            :placeholder="campo.placeholder || ''"
                            size="small"
                            class="w-full"
                          />
                          <Textarea
                            v-else-if="campo.tipo === 'textarea'"
                            v-model="valoresCamposFluxo[campo.id]"
                            :placeholder="campo.placeholder || ''"
                            rows="2"
                            autoResize
                            class="w-full text-sm !resize-none"
                          />
                          <InputNumber
                            v-else-if="campo.tipo === 'numero'"
                            v-model="valoresCamposFluxo[campo.id]"
                            :placeholder="campo.placeholder || ''"
                            size="small"
                            class="w-full"
                            :useGrouping="false"
                          />
                          <DatePicker
                            v-else-if="campo.tipo === 'data'"
                            v-model="valoresCamposFluxo[campo.id]"
                            :placeholder="campo.placeholder || 'dd/mm/aaaa'"
                            dateFormat="dd/mm/yy"
                            size="small"
                            fluid
                            class="w-full"
                          />
                          <Select
                            v-else-if="campo.tipo === 'selecao'"
                            v-model="valoresCamposFluxo[campo.id]"
                            :options="campo.opcoes || []"
                            :placeholder="campo.placeholder || 'Selecione'"
                            size="small"
                            class="w-full"
                          />
                          <div
                            v-else-if="campo.tipo === 'checkbox'"
                            class="flex items-center gap-2 mt-1"
                          >
                            <input
                              type="checkbox"
                              :checked="valoresCamposFluxo[campo.id] === 'S'"
                              @change="
                                valoresCamposFluxo[campo.id] = $event.target
                                  .checked
                                  ? 'S'
                                  : 'N'
                              "
                              class="rounded border-gray-300 text-blue-500 focus:ring-blue-500"
                            />
                            <span
                              class="text-xs text-gray-600 dark:text-gray-400"
                            >
                              {{ campo.placeholder || "Sim" }}
                            </span>
                          </div>
                          <!-- Arquivo -->
                          <div
                            v-else-if="campo.tipo === 'arquivo'"
                            class="flex flex-col gap-1.5"
                          >
                            <div
                              v-if="
                                parseCampoArquivo(valoresCamposFluxo[campo.id])
                              "
                              class="flex items-center gap-2 px-2.5 py-1.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-lg"
                            >
                              <i class="pi pi-file text-blue-500 text-sm"></i>
                              <button
                                type="button"
                                @click="
                                  visualizarCampoArquivo(
                                    valoresCamposFluxo[campo.id]
                                  )
                                "
                                class="text-xs text-blue-600 hover:underline truncate flex-1 text-left cursor-pointer"
                              >
                                {{
                                  parseCampoArquivo(
                                    valoresCamposFluxo[campo.id]
                                  ).file_name
                                }}
                              </button>
                              <button
                                type="button"
                                @click="removerCampoArquivo(campo.id)"
                                class="text-red-400 hover:text-red-600 transition-colors flex-shrink-0"
                                title="Remover arquivo"
                              >
                                <i class="pi pi-times text-xs"></i>
                              </button>
                            </div>
                            <div
                              v-else
                              class="flex items-center gap-2"
                            >
                              <label
                                :for="`campo-arquivo-correcao-${campo.id}`"
                                class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg cursor-pointer transition-colors"
                                :class="{
                                  'opacity-50 pointer-events-none':
                                    uploadingCampoArquivo[campo.id]
                                }"
                              >
                                <i class="pi pi-upload text-xs"></i>
                                {{
                                  uploadingCampoArquivo[campo.id]
                                    ? "Enviando..."
                                    : "Selecionar arquivo"
                                }}
                              </label>
                              <input
                                :id="`campo-arquivo-correcao-${campo.id}`"
                                type="file"
                                :accept="CAMPO_ARQUIVO_ACCEPT"
                                class="hidden"
                                @change="
                                  handleCampoArquivoUpload($event, campo.id)
                                "
                              />
                            </div>
                          </div>
                          <InputText
                            v-else
                            v-model="valoresCamposFluxo[campo.id]"
                            :placeholder="campo.placeholder || ''"
                            size="small"
                            class="w-full"
                          />
                        </div>
                      </div>
                    </div>

                    <!-- Botão reenviar -->
                    <div
                      v-if="
                        solicitacao.usuario_solicitante?.matricula ==
                        props.auth.matricula
                      "
                      class="flex justify-end mt-3"
                    >
                      <Button
                        label="Reenviar ao fluxo"
                        icon="pi pi-send"
                        severity="warning"
                        size="small"
                        outlined
                        :loading="loading"
                        @click="devolverAoFluxo"
                      />
                    </div>
                  </div>

                  <!-- Aviso: aguardando atribuição de responsável -->
                  <div
                    v-if="
                      !fluxoDados.is_aguardando_solicitante &&
                      solicitacao.isDepartamento &&
                      !solicitacao.usuario_responsavel &&
                      !fluxoDados.is_concluido &&
                      !fluxoDados.is_cancelado &&
                      !['cancelada', 'finalizada'].includes(solicitacao.status)
                    "
                    class="flex items-center gap-2 mt-3 pt-3 border-t border-dashed border-gray-200 dark:border-slate-700 text-xs text-amber-600 dark:text-amber-400"
                  >
                    <i class="pi pi-user-plus text-sm"></i>
                    <span>Atribua um responsável para avançar o fluxo</span>
                  </div>

                  <!-- Aviso: aguardando ser o responsável -->
                  <div
                    v-else-if="
                      !fluxoDados.is_aguardando_solicitante &&
                      solicitacao.isDepartamento &&
                      solicitacao.usuario_responsavel &&
                      solicitacao.usuario_responsavel.matricula !=
                        props.auth.matricula &&
                      !fluxoDados.is_concluido &&
                      !fluxoDados.is_cancelado &&
                      !['cancelada', 'finalizada'].includes(solicitacao.status)
                    "
                    class="flex items-center gap-2 mt-3 pt-3 border-t border-dashed border-gray-200 dark:border-slate-700 text-xs text-gray-400 dark:text-gray-500"
                  >
                    <i class="pi pi-lock text-sm"></i>
                    <span>
                      Somente
                      <strong class="font-semibold">
                        {{
                          solicitacao.usuario_responsavel.nome?.split(" ")[0]
                        }}
                      </strong>
                      pode avançar o fluxo
                    </span>
                  </div>

                  <!-- Campos da etapa atual do fluxo (só campos globais, sem decisao_id) -->
                  <div
                    v-if="
                      fluxoDados.campos_etapa?.filter((c) => !c.decisao_id)
                        .length && podeAgirNaEtapa
                    "
                    class="mt-3 pt-3 border-t border-dashed border-gray-200 dark:border-slate-700"
                  >
                    <div class="flex items-center gap-2 mb-3">
                      <i class="pi pi-list-check text-sm text-blue-500"></i>
                      <span
                        class="text-xs font-semibold text-gray-700 dark:text-gray-300"
                      >
                        Campos da Etapa
                      </span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div
                        v-for="campo in fluxoDados.campos_etapa.filter(
                          (c) => !c.decisao_id
                        )"
                        :key="campo.id"
                        class="flex flex-col gap-1"
                      >
                        <label
                          class="text-xs font-medium text-gray-600 dark:text-gray-400"
                        >
                          {{ campo.label }}
                          <span
                            v-if="campo.obrigatorio === 'S'"
                            class="text-red-500"
                          >
                            *
                          </span>
                        </label>
                        <!-- Texto -->
                        <InputText
                          v-if="campo.tipo === 'texto'"
                          v-model="valoresCamposFluxo[campo.id]"
                          :placeholder="campo.placeholder || ''"
                          size="small"
                          class="w-full"
                        />
                        <!-- Textarea -->
                        <Textarea
                          v-else-if="campo.tipo === 'textarea'"
                          v-model="valoresCamposFluxo[campo.id]"
                          :placeholder="campo.placeholder || ''"
                          rows="2"
                          autoResize
                          class="w-full text-sm !resize-none"
                        />
                        <!-- Número -->
                        <InputNumber
                          v-else-if="campo.tipo === 'numero'"
                          v-model="valoresCamposFluxo[campo.id]"
                          :placeholder="campo.placeholder || ''"
                          size="small"
                          class="w-full"
                          :useGrouping="false"
                        />
                        <!-- Data -->
                        <DatePicker
                          v-else-if="campo.tipo === 'data'"
                          v-model="valoresCamposFluxo[campo.id]"
                          :placeholder="campo.placeholder || 'dd/mm/aaaa'"
                          dateFormat="dd/mm/yy"
                          size="small"
                          fluid
                          class="w-full"
                        />
                        <!-- Seleção -->
                        <Select
                          v-else-if="campo.tipo === 'selecao'"
                          v-model="valoresCamposFluxo[campo.id]"
                          :options="campo.opcoes || []"
                          :placeholder="campo.placeholder || 'Selecione'"
                          size="small"
                          class="w-full"
                        />
                        <!-- Checkbox -->
                        <div
                          v-else-if="campo.tipo === 'checkbox'"
                          class="flex items-center gap-2 mt-1"
                        >
                          <input
                            type="checkbox"
                            :checked="valoresCamposFluxo[campo.id] === 'S'"
                            @change="
                              valoresCamposFluxo[campo.id] = $event.target
                                .checked
                                ? 'S'
                                : 'N'
                            "
                            class="rounded border-gray-300 text-blue-500 focus:ring-blue-500"
                          />
                          <span
                            class="text-xs text-gray-600 dark:text-gray-400"
                          >
                            {{ campo.placeholder || "Sim" }}
                          </span>
                        </div>
                        <!-- Arquivo -->
                        <div
                          v-else-if="campo.tipo === 'arquivo'"
                          class="flex flex-col gap-1.5"
                        >
                          <div
                            v-if="
                              parseCampoArquivo(valoresCamposFluxo[campo.id])
                            "
                            class="flex items-center gap-2 px-2.5 py-1.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-lg"
                          >
                            <i class="pi pi-file text-blue-500 text-sm"></i>
                            <button
                              type="button"
                              @click="
                                visualizarCampoArquivo(
                                  valoresCamposFluxo[campo.id]
                                )
                              "
                              class="text-xs text-blue-600 hover:underline truncate flex-1 text-left cursor-pointer"
                            >
                              {{
                                parseCampoArquivo(valoresCamposFluxo[campo.id])
                                  .file_name
                              }}
                            </button>
                            <button
                              type="button"
                              @click="removerCampoArquivo(campo.id)"
                              class="text-red-400 hover:text-red-600 transition-colors flex-shrink-0"
                              title="Remover arquivo"
                            >
                              <i class="pi pi-times text-xs"></i>
                            </button>
                          </div>
                          <div
                            v-else
                            class="flex items-center gap-2"
                          >
                            <label
                              :for="`campo-arquivo-etapa-${campo.id}`"
                              class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg cursor-pointer transition-colors"
                              :class="{
                                'opacity-50 pointer-events-none':
                                  uploadingCampoArquivo[campo.id]
                              }"
                            >
                              <i class="pi pi-upload text-xs"></i>
                              {{
                                uploadingCampoArquivo[campo.id]
                                  ? "Enviando..."
                                  : "Selecionar arquivo"
                              }}
                            </label>
                            <input
                              :id="`campo-arquivo-etapa-${campo.id}`"
                              type="file"
                              :accept="CAMPO_ARQUIVO_ACCEPT"
                              class="hidden"
                              @change="
                                handleCampoArquivoUpload($event, campo.id)
                              "
                            />
                          </div>
                        </div>
                        <!-- Fallback -->
                        <InputText
                          v-else
                          v-model="valoresCamposFluxo[campo.id]"
                          :placeholder="campo.placeholder || ''"
                          size="small"
                          class="w-full"
                        />
                      </div>
                    </div>
                  </div>

                  <!-- Campos Configuráveis do Assunto (exibidos quando etapa tem flag exibir_campos_assunto) -->
                  <div
                    v-if="
                      fluxoDados.exibir_campos_assunto &&
                      fluxoDados.campos_assunto?.length &&
                      podeAgirNaEtapa
                    "
                    class="mt-3 pt-3 border-t border-dashed border-gray-200 dark:border-slate-700"
                  >
                    <div class="flex items-center gap-2 mb-3">
                      <i class="pi pi-list text-sm text-purple-500"></i>
                      <span
                        class="text-xs font-semibold text-gray-700 dark:text-gray-300"
                      >
                        Campos do Processo
                      </span>
                    </div>
                    <div class="flex flex-wrap gap-3">
                      <div
                        v-for="select in fluxoDados.campos_assunto.filter((s) =>
                          campoDeveSerExibidoFluxo(s)
                        )"
                        :key="'assunto-' + select.id"
                        class="flex flex-col"
                      >
                        <label class="text-xs text-gray-600 font-medium mb-1">
                          {{ select.label }}
                          <span
                            v-if="select.obrigatorio === 'S'"
                            class="text-red-500"
                          >
                            *
                          </span>
                        </label>

                        <Select
                          v-if="select.tipo === 'selecao'"
                          class="!w-52"
                          :options="select.valores"
                          option-value="code"
                          option-label="label"
                          placeholder="Selecione..."
                          show-clear
                          @update:model-value="
                            (val) => addRespostaFluxo(select, val)
                          "
                        ></Select>

                        <!-- Campos pré-definidos Winthor -->
                        <template v-else-if="select.tipo === 'depto_compras'">
                          <MultiSelect
                            v-if="select.multiplo === 'S'"
                            class="!w-52"
                            :options="dadosWinthor.depto_compras"
                            option-value="value"
                            option-label="label"
                            placeholder="Selecione..."
                            filter
                            :loading="loadingWinthor.depto_compras"
                            @show="carregarDadosWinthor('depto_compras')"
                            @update:model-value="
                              (val) => addRespostaFluxo(select, val)
                            "
                          />
                          <Select
                            v-else
                            class="!w-52"
                            :options="dadosWinthor.depto_compras"
                            option-value="value"
                            option-label="label"
                            placeholder="Selecione..."
                            show-clear
                            filter
                            :loading="loadingWinthor.depto_compras"
                            @show="carregarDadosWinthor('depto_compras')"
                            @update:model-value="
                              (val) => addRespostaFluxo(select, val)
                            "
                          />
                        </template>

                        <template
                          v-else-if="select.tipo === 'depto_funcionario'"
                        >
                          <MultiSelect
                            v-if="select.multiplo === 'S'"
                            class="!w-52"
                            :options="dadosWinthor.depto_funcionario"
                            option-value="value"
                            option-label="label"
                            placeholder="Selecione..."
                            filter
                            :loading="loadingWinthor.depto_funcionario"
                            @show="carregarDadosWinthor('depto_funcionario')"
                            @update:model-value="
                              (val) => addRespostaFluxo(select, val)
                            "
                          />
                          <Select
                            v-else
                            class="!w-52"
                            :options="dadosWinthor.depto_funcionario"
                            option-value="value"
                            option-label="label"
                            placeholder="Selecione..."
                            show-clear
                            filter
                            :loading="loadingWinthor.depto_funcionario"
                            @show="carregarDadosWinthor('depto_funcionario')"
                            @update:model-value="
                              (val) => addRespostaFluxo(select, val)
                            "
                          />
                        </template>

                        <template v-else-if="select.tipo === 'filial_winthor'">
                          <MultiSelect
                            v-if="select.multiplo === 'S'"
                            class="!w-52"
                            :options="dadosWinthor.filial_winthor"
                            option-value="value"
                            option-label="label"
                            placeholder="Selecione..."
                            filter
                            :loading="loadingWinthor.filial_winthor"
                            @show="carregarDadosWinthor('filial_winthor')"
                            @update:model-value="
                              (val) => addRespostaFluxo(select, val)
                            "
                          />
                          <Select
                            v-else
                            class="!w-52"
                            :options="dadosWinthor.filial_winthor"
                            option-value="value"
                            option-label="label"
                            placeholder="Selecione..."
                            show-clear
                            filter
                            :loading="loadingWinthor.filial_winthor"
                            @show="carregarDadosWinthor('filial_winthor')"
                            @update:model-value="
                              (val) => addRespostaFluxo(select, val)
                            "
                          />
                        </template>

                        <template v-else-if="select.tipo === 'funcao'">
                          <MultiSelect
                            v-if="select.multiplo === 'S'"
                            class="!w-52"
                            :options="dadosWinthor.funcao"
                            option-value="value"
                            option-label="label"
                            placeholder="Selecione..."
                            filter
                            :loading="loadingWinthor.funcao"
                            @show="carregarDadosWinthor('funcao')"
                            @update:model-value="
                              (val) => addRespostaFluxo(select, val)
                            "
                          />
                          <Select
                            v-else
                            class="!w-52"
                            :options="dadosWinthor.funcao"
                            option-value="value"
                            option-label="label"
                            placeholder="Selecione..."
                            show-clear
                            filter
                            :loading="loadingWinthor.funcao"
                            @show="carregarDadosWinthor('funcao')"
                            @update:model-value="
                              (val) => addRespostaFluxo(select, val)
                            "
                          />
                        </template>

                        <template v-else-if="select.tipo === 'regional'">
                          <MultiSelect
                            v-if="select.multiplo === 'S'"
                            class="!w-52"
                            :options="dadosWinthor.regional"
                            option-value="value"
                            option-label="label"
                            placeholder="Selecione..."
                            filter
                            :loading="loadingWinthor.regional"
                            @show="carregarDadosWinthor('regional')"
                            @update:model-value="
                              (val) => addRespostaFluxo(select, val)
                            "
                          />
                          <Select
                            v-else
                            class="!w-52"
                            :options="dadosWinthor.regional"
                            option-value="value"
                            option-label="label"
                            placeholder="Selecione..."
                            show-clear
                            filter
                            :loading="loadingWinthor.regional"
                            @show="carregarDadosWinthor('regional')"
                            @update:model-value="
                              (val) => addRespostaFluxo(select, val)
                            "
                          />
                        </template>

                        <InputMask
                          v-else-if="select.tipo === 'cnpj'"
                          class="!w-52"
                          mask="99.999.999/9999-99"
                          placeholder="00.000.000/0000-00"
                          @update:model-value="
                            (val) => addRespostaFluxo(select, val)
                          "
                        />

                        <InputText
                          v-else-if="select.tipo === 'texto'"
                          class="!w-52"
                          :placeholder="select.placeholder || 'Digite aqui...'"
                          @update:model-value="
                            (val) => addRespostaFluxo(select, val)
                          "
                        />

                        <InputNumber
                          v-else-if="select.tipo === 'numero'"
                          class="!w-52"
                          :min="0"
                          placeholder="Digite um número..."
                          @update:model-value="
                            (val) => addRespostaFluxo(select, val)
                          "
                        />

                        <DatePicker
                          v-else-if="select.tipo === 'data'"
                          :selection-mode="select.tipo_data"
                          @update:model-value="
                            (val) => addRespostaFluxo(select, val)
                          "
                          show-icon
                          date-format="dd/mm/yy"
                          :min-date="calcularDataMinima(select)"
                          :placeholder="
                            select.tipo_data === 'range'
                              ? 'Selecione período'
                              : 'Selecione data'
                          "
                          class="w-52"
                        ></DatePicker>
                        <small
                          v-if="select.tipo === 'data' && select.dias_minimos"
                          class="flex items-center text-xs text-amber-600 mt-1"
                        >
                          <i class="pi pi-info-circle text-xs mr-1"></i>
                          Mínimo {{ select.dias_minimos }} dia(s) de
                          antecedência
                        </small>
                      </div>
                    </div>
                  </div>

                  <!-- Instruções da etapa atual -->
                  <!-- No modo exclusivo do solicitante (tipo 'E'), as instruções só são
                       exibidas ao ator autorizado (solicitante), alinhando com campos e decisões. -->
                  <div
                    v-if="
                      fluxoDados.instrucoes &&
                      !fluxoDados.is_concluido &&
                      !fluxoDados.is_cancelado &&
                      (!fluxoDados.is_modo_exclusivo || podeAgirNaEtapa)
                    "
                    class="mt-3 pt-3 border-t border-dashed border-gray-200 dark:border-slate-700"
                  >
                    <div
                      class="p-3 bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800 rounded-lg"
                    >
                      <div class="flex items-start gap-2">
                        <i
                          class="pi pi-info-circle text-sky-500 text-sm flex-shrink-0 mt-0.5"
                        ></i>
                        <div>
                          <span
                            class="text-[11px] font-semibold text-sky-700 dark:text-sky-400 block mb-0.5"
                          >
                            Instruções da Etapa
                          </span>
                          <p
                            class="text-xs text-sky-600 dark:text-sky-300 whitespace-pre-line"
                          >
                            {{ fluxoDados.instrucoes }}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Ações do fluxo (responsável atribuído OU solicitante com permissão) -->
                  <div
                    v-if="
                      !['cancelada', 'finalizada'].includes(
                        solicitacao.status
                      ) && podeAgirNaEtapa
                    "
                    class="flex flex-wrap items-center justify-end gap-2 mt-4 pt-3 border-t border-gray-100 dark:border-slate-700"
                  >
                    <!-- Sem decisões: botão voltar + avançar/concluir -->
                    <template
                      v-if="
                        !fluxoDados.decisoes || fluxoDados.decisoes.length === 0
                      "
                    >
                      <Button
                        v-if="
                          fluxoDados.etapa_atual &&
                          fluxoDados.etapas?.find(
                            (e) => e.ordem < fluxoDados.etapa_atual.ordem
                          )
                        "
                        label="Voltar Etapa"
                        icon="pi pi-arrow-left"
                        severity="secondary"
                        size="small"
                        outlined
                        :loading="loading"
                        @click="voltarFluxo"
                      />
                      <Button
                        :label="
                          fluxoDados.etapa_atual &&
                          !fluxoDados.etapas?.find(
                            (e) => e.ordem > fluxoDados.etapa_atual.ordem
                          )
                            ? 'Concluir Fluxo'
                            : 'Avançar Etapa'
                        "
                        icon="pi pi-arrow-right"
                        severity="success"
                        size="small"
                        outlined
                        :loading="loading"
                        @click="avancarFluxo"
                      />
                    </template>

                    <!-- Com decisões: apenas botões de decisão -->
                    <template v-else>
                      <span
                        class="text-xs text-gray-500 dark:text-gray-400 font-medium mr-1"
                      >
                        Decisão:
                      </span>
                      <Button
                        v-for="decisao in fluxoDados.decisoes.filter(
                          (d) =>
                            !fluxoDados.is_modo_exclusivo ||
                            d.acao !== 'voltar_solicitante'
                        )"
                        :key="decisao.id"
                        :label="decisao.label"
                        :icon="decisao.icone || ''"
                        size="small"
                        outlined
                        :loading="loading"
                        :style="{
                          '--decisao-cor': decisao.cor || '#3B82F6',
                          borderColor: decisao.cor || '#3B82F6',
                          color: decisao.cor || '#3B82F6'
                        }"
                        class="decisao-btn"
                        @click="decidirFluxo(decisao.id)"
                      />
                    </template>
                  </div>
                </div>
              </div>

              <!-- DETALHES DO SOLICITANTE -->
              <div
                class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl overflow-hidden mb-4"
              >
                <!-- Header do Card -->
                <div
                  class="bg-gradient-to-r from-slate-50 to-gray-50 dark:from-slate-700 dark:to-slate-700 px-4 py-3 border-b border-gray-100 dark:border-slate-600"
                >
                  <div class="flex items-center gap-2">
                    <div
                      class="w-8 h-8 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-lg flex items-center justify-center shadow-sm"
                    >
                      <i class="pi pi-id-card text-white text-xs"></i>
                    </div>
                    <h3 class="text-sm font-bold text-gray-800 dark:text-white">
                      Dados do Solicitante
                    </h3>
                  </div>
                </div>

                <!-- Conteúdo -->
                <div class="p-4">
                  <div class="flex flex-col lg:flex-row gap-4">
                    <!-- Avatar e Info Principal -->
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                      <!-- Avatar do Solicitante -->
                      <div class="relative flex-shrink-0">
                        <div
                          v-if="solicitacao.usuario_solicitante.foto_perfil"
                          class="w-9 h-9 rounded-lg overflow-hidden ring-2 ring-offset-1 ring-emerald-400"
                          v-tooltip.top="solicitacao.usuario_solicitante.nome"
                        >
                          <img
                            :src="solicitacao.usuario_solicitante.foto_perfil"
                            :alt="solicitacao.usuario_solicitante.nome"
                            class="w-full h-full object-cover"
                          />
                        </div>
                        <div
                          v-else
                          class="w-9 h-9 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white font-bold text-xs ring-2 ring-offset-1 ring-emerald-400 shadow-lg"
                          v-tooltip.top="solicitacao.usuario_solicitante.nome"
                        >
                          {{
                            obterIniciais(solicitacao.usuario_solicitante.nome)
                          }}
                        </div>
                      </div>

                      <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                          <p
                            class="font-bold text-gray-900 dark:text-white truncate text-base"
                          >
                            {{
                              obterNomeSobrenome(
                                solicitacao.usuario_solicitante.nome
                              )
                            }}
                          </p>
                          <i
                            @click="abrirTrocaSolicitante()"
                            v-if="
                              solicitacao.status != 'cancelada' &&
                              solicitacao.status != 'finalizada' &&
                              validaPermissao(
                                'solicitacoes.lista.alterar_solicitante'
                              )
                            "
                            class="pi pi-arrow-right-arrow-left text-xs cursor-pointer text-blue-500 hover:text-blue-700 transition-colors p-1 hover:bg-blue-50 rounded-md"
                            v-tooltip.top="`Alterar solicitante`"
                          ></i>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                          Matrícula:
                          <span
                            class="font-semibold text-gray-700 dark:text-gray-300"
                          >
                            {{ solicitacao.usuario_solicitante.matricula }}
                          </span>
                        </p>
                      </div>
                    </div>

                    <!-- Grid de Informações -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 flex-1">
                      <!-- Departamento -->
                      <div
                        class="p-2 bg-gray-50 dark:bg-slate-700/50 rounded-lg"
                      >
                        <p
                          class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-0.5"
                        >
                          Departamento
                        </p>
                        <p
                          class="text-xs font-medium text-gray-800 dark:text-white truncate"
                          v-tooltip.top="
                            solicitacao.usuario_solicitante.areaatuacao
                          "
                        >
                          {{ solicitacao.usuario_solicitante.areaatuacao }}
                        </p>
                      </div>

                      <!-- Filial -->
                      <div
                        class="p-2 bg-gray-50 dark:bg-slate-700/50 rounded-lg"
                      >
                        <p
                          class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-0.5"
                        >
                          Filial
                        </p>
                        <p
                          class="text-xs font-medium text-gray-800 dark:text-white truncate"
                          v-tooltip.top="
                            `${solicitacao.filial.codigo} - ${solicitacao.filial.fantasia}`
                          "
                        >
                          {{ solicitacao.filial.codigo }}-{{
                            solicitacao.filial.fantasia
                          }}
                        </p>
                      </div>

                      <!-- E-mail -->
                      <div
                        class="p-2 bg-gray-50 dark:bg-slate-700/50 rounded-lg"
                      >
                        <p
                          class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-0.5"
                        >
                          E-mail
                        </p>
                        <p
                          class="text-xs font-medium text-gray-800 dark:text-white truncate"
                          v-tooltip.top="solicitacao.usuario_solicitante.email"
                        >
                          {{ solicitacao.usuario_solicitante.email }}
                        </p>
                      </div>

                      <!-- Telefone -->
                      <div
                        class="p-2 bg-gray-50 dark:bg-slate-700/50 rounded-lg"
                      >
                        <p
                          class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-0.5"
                        >
                          Telefone
                        </p>
                        <p
                          class="text-xs font-medium text-gray-800 dark:text-white"
                        >
                          {{
                            formatarTelefoneFixo(
                              solicitacao.usuario_solicitante.celular ||
                                solicitacao.usuario_solicitante.fone
                            ) || "-"
                          }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- DETALHES DA SOLICITAÇÃO -->
              <div
                class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl overflow-hidden"
              >
                <!-- Header do Card -->
                <div
                  class="bg-gradient-to-r from-slate-50 to-gray-50 dark:from-slate-700 dark:to-slate-700 px-4 py-3 border-b border-gray-100 dark:border-slate-600"
                >
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                      <div
                        class="w-8 h-8 bg-gradient-to-br from-slate-600 to-gray-700 rounded-lg flex items-center justify-center shadow-sm"
                      >
                        <i class="pi pi-book text-white text-xs"></i>
                      </div>
                      <h3
                        class="text-sm font-bold text-gray-800 dark:text-white"
                      >
                        Detalhes do Ticket
                      </h3>
                    </div>

                    <!-- Botão Criar Branch (somente devs autorizados) -->
                    <div
                      v-if="podeCriarBranch"
                      class="flex flex-col items-end gap-1"
                    >
                      <button
                        @click="criarBranch()"
                        :disabled="criandoBranch || branchJaExiste"
                        :class="
                          branchJaExiste
                            ? 'bg-gradient-to-r from-amber-500 to-orange-500 cursor-not-allowed opacity-75'
                            : 'bg-gradient-to-r from-slate-700 to-gray-800 hover:from-emerald-600 hover:to-green-700 hover:scale-105 hover:shadow-md'
                        "
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white shadow-sm transition-all duration-300 disabled:hover:scale-100"
                      >
                        <i
                          :class="
                            criandoBranch
                              ? 'pi pi-spinner pi-spin'
                              : branchJaExiste
                                ? 'pi pi-exclamation-triangle'
                                : 'pi pi-github'
                          "
                          class="text-xs"
                        ></i>
                        {{
                          branchJaExiste
                            ? "Branch já existe"
                            : "Criar Branch Git"
                        }}
                      </button>
                      <span
                        v-if="branchJaExiste"
                        class="text-[10px] text-amber-600 dark:text-amber-400 font-mono"
                      >
                        {{ branchNomeExistente }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Conteúdo -->
                <div class="p-4 space-y-4">
                  <div
                    v-if="solicitacao.filial_depto_select"
                    class="flex flex-col gap-4"
                  >
                    <!-- Filial -->
                    <div
                      v-if="solicitacao.filial_depto_select.filial"
                      class="space-y-2 p-3 rounded-xl border border-gray-200/60 dark:border-slate-600/50"
                    >
                      <div class="flex items-center gap-2">
                        <i
                          class="pi pi-building text-blue-500 dark:text-blue-400"
                        ></i>
                        <label
                          class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                        >
                          Filial
                        </label>
                      </div>
                      <p
                        class="text-sm font-medium text-gray-800 dark:text-white ml-6"
                      >
                        {{ solicitacao.filial_depto_select.filial }}
                      </p>
                    </div>

                    <!-- Departamento -->
                    <div
                      v-if="solicitacao.filial_depto_select.departamento"
                      class="space-y-2 p-3 rounded-xl border border-gray-200/60 dark:border-slate-600/50"
                    >
                      <div class="flex items-center gap-2">
                        <i
                          class="pi pi-sitemap text-purple-500 dark:text-purple-400"
                        ></i>
                        <label
                          class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                        >
                          Departamento
                        </label>
                      </div>
                      <p
                        class="text-sm font-medium text-gray-800 dark:text-white ml-6"
                      >
                        {{ solicitacao.filial_depto_select.departamento }}
                      </p>
                    </div>
                  </div>

                  <div
                    class="flex flex-col gap-4"
                    v-if="respostasSelecaoAgrupadas.length > 0"
                  >
                    <div
                      v-for="grupo in respostasSelecaoAgrupadas"
                      :key="grupo.id"
                      class="space-y-2 p-3 rounded-xl border border-gray-200/60 dark:border-slate-600/50"
                    >
                      <div class="flex items-center gap-2">
                        <i
                          :class="[
                            grupo.selecao.tipo === 'selecao'
                              ? 'pi pi-list text-indigo-500 dark:text-indigo-400'
                              : grupo.selecao.tipo === 'texto'
                                ? 'pi pi-pencil text-orange-500 dark:text-orange-400'
                                : grupo.selecao.tipo === 'numero'
                                  ? 'pi pi-hashtag text-cyan-500 dark:text-cyan-400'
                                  : grupo.selecao.tipo === 'cnpj'
                                    ? 'pi pi-id-card text-teal-500 dark:text-teal-400'
                                    : grupo.selecao.tipo === 'data'
                                      ? 'pi pi-calendar text-rose-500 dark:text-rose-400'
                                      : grupo.selecao.tipo === 'arquivo'
                                        ? 'pi pi-file text-blue-500 dark:text-blue-400'
                                        : grupo.selecao.tipo === 'depto_compras'
                                          ? 'pi pi-shopping-cart text-amber-500 dark:text-amber-400'
                                          : grupo.selecao.tipo ===
                                              'depto_funcionario'
                                            ? 'pi pi-users text-lime-500 dark:text-lime-400'
                                            : grupo.selecao.tipo ===
                                                'filial_winthor'
                                              ? 'pi pi-building text-sky-500 dark:text-sky-400'
                                              : grupo.selecao.tipo === 'funcao'
                                                ? 'pi pi-briefcase text-violet-500 dark:text-violet-400'
                                                : grupo.selecao.tipo ===
                                                    'regional'
                                                  ? 'pi pi-map-marker text-pink-500 dark:text-pink-400'
                                                  : 'pi pi-info-circle text-gray-500 dark:text-gray-400'
                          ]"
                        ></i>
                        <label
                          class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                        >
                          {{ grupo.selecao.label }}
                        </label>
                      </div>

                      <!-- Tipo seleção (agrupado) -->
                      <template v-if="grupo.selecao.tipo == 'selecao'">
                        <p
                          class="text-sm font-medium text-gray-800 dark:text-white ml-6"
                        >
                          {{
                            grupo.respostas
                              .map((r) => r.item?.valor || r.texto_resposta)
                              .filter(Boolean)
                              .join(", ")
                          }}
                        </p>
                      </template>

                      <!-- Texto / Numero / CNPJ -->
                      <template
                        v-else-if="
                          ['texto', 'numero', 'cnpj'].includes(
                            grupo.selecao.tipo
                          )
                        "
                      >
                        <p
                          class="text-sm font-medium text-gray-800 dark:text-white ml-6"
                        >
                          {{ grupo.respostas[0]?.texto_resposta }}
                        </p>
                      </template>

                      <!-- Campos Winthor #3196 (agrupado) -->
                      <template
                        v-else-if="
                          [
                            'depto_compras',
                            'depto_funcionario',
                            'filial_winthor',
                            'funcao',
                            'regional'
                          ].includes(grupo.selecao.tipo)
                        "
                      >
                        <p
                          class="text-sm font-medium text-gray-800 dark:text-white ml-6"
                        >
                          {{
                            grupo.respostas
                              .map((r) => r.valor_winthor)
                              .filter(Boolean)
                              .join(", ")
                          }}
                        </p>
                      </template>

                      <!-- Data -->
                      <template
                        v-else-if="
                          grupo.selecao.tipo == 'data' &&
                          grupo.respostas[0]?.data1
                        "
                      >
                        <p
                          class="text-sm font-medium text-gray-800 dark:text-white ml-6 flex flex-row space-x-1"
                        >
                          <span>
                            {{ formatarDataSemHoras(grupo.respostas[0].data1) }}
                          </span>
                          <span v-if="grupo.respostas[0].data2">
                            -
                            {{ formatarDataSemHoras(grupo.respostas[0].data2) }}
                          </span>
                        </p>
                      </template>
                    </div>
                  </div>

                  <!-- Título -->
                  <div
                    v-if="solicitacao.titulo"
                    class="space-y-2 p-3 rounded-xl border border-gray-200/60 dark:border-slate-600/50"
                  >
                    <div class="flex items-center gap-2">
                      <i class="pi pi-tag text-blue-500 dark:text-blue-400"></i>
                      <label
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                      >
                        Título
                      </label>
                    </div>
                    <p
                      class="text-sm font-medium text-gray-800 dark:text-white ml-6"
                    >
                      {{ solicitacao.titulo }}
                    </p>
                  </div>

                  <!-- Descrição -->
                  <div
                    v-if="solicitacao.descricao"
                    class="space-y-2 p-3 rounded-xl border border-gray-200/60 dark:border-slate-600/50"
                  >
                    <div class="flex items-center gap-2">
                      <i
                        class="pi pi-align-left text-emerald-500 dark:text-emerald-400"
                      ></i>
                      <label
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                      >
                        Descrição
                      </label>
                    </div>
                    <p
                      class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap ml-6"
                    >
                      {{ solicitacao.descricao }}
                    </p>
                  </div>

                  <!-- Rotinas -->
                  <div
                    v-if="solicitacao.rotinas.length"
                    class="space-y-2 p-3 rounded-xl border border-gray-200/60 dark:border-slate-600/50"
                  >
                    <div class="flex items-center gap-2">
                      <i
                        class="pi pi-cog text-amber-500 dark:text-amber-400"
                      ></i>
                      <label
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                      >
                        Rotinas
                      </label>
                    </div>
                    <div class="flex flex-wrap gap-2 ml-6">
                      <div
                        v-for="rotina in solicitacao.rotinas"
                        class="flex space-x-2 text-xs items-center p-2 border rounded-lg bg-gray-50 dark:bg-slate-700 border-gray-200 dark:border-slate-600 w-[300px] ipad:w-[340px]"
                      >
                        <div
                          class="w-[50px] border-r border-gray-300 dark:border-slate-500 font-semibold text-gray-700 dark:text-gray-300"
                        >
                          {{ rotina.dados.codigo }}
                        </div>
                        <div
                          class="w-[250px] whitespace-nowrap overflow-hidden truncate text-gray-600 dark:text-gray-400"
                        >
                          {{ rotina.dados.nomerotina }}
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Dados Acesso -->
                  <div
                    v-if="solicitacao.dadosAcesso.length"
                    class="space-y-2 p-3 rounded-xl border border-gray-200/60 dark:border-slate-600/50"
                  >
                    <div class="flex items-center gap-2">
                      <i
                        class="pi pi-key text-yellow-500 dark:text-yellow-400"
                      ></i>
                      <label
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                      >
                        Dados Acesso
                      </label>
                    </div>
                    <div class="flex flex-wrap gap-2 ml-6">
                      <div
                        v-for="dadosAcesso in solicitacao.dadosAcesso"
                        @click="showDadosLiberacao(dadosAcesso)"
                        class="flex justify-center cursor-pointer hover:scale-95 transition-all text-xs items-center px-3 py-2 border rounded-lg bg-gray-50 dark:bg-slate-700 border-gray-200 dark:border-slate-600 hover:bg-gray-100 dark:hover:bg-slate-600"
                      >
                        <span
                          class="font-medium text-gray-700 dark:text-gray-300"
                        >
                          {{ dadosAcesso.tipo }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <!-- Usuário Origem -->
                  <div
                    v-if="solicitacao.usuario_origem.matricula"
                    class="space-y-2 p-3 rounded-xl border border-gray-200/60 dark:border-slate-600/50"
                  >
                    <div class="flex items-center gap-2">
                      <i
                        class="pi pi-user text-cyan-500 dark:text-cyan-400"
                      ></i>
                      <label
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                      >
                        Usuário Origem
                      </label>
                    </div>
                    <p
                      class="text-sm font-medium text-gray-800 dark:text-white ml-6"
                    >
                      {{ solicitacao.usuario_origem.matricula }} -
                      {{ solicitacao.usuario_origem.nome }}
                    </p>
                  </div>

                  <!-- Vendas Pendentes -->
                  <div
                    v-if="solicitacao.vendas.length > 0"
                    class="space-y-2 p-3 rounded-xl border border-gray-200/60 dark:border-slate-600/50"
                  >
                    <div class="flex items-center gap-2">
                      <i
                        class="pi pi-shopping-cart text-red-500 dark:text-red-400"
                      ></i>
                      <label
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                      >
                        Vendas Pendentes
                      </label>
                    </div>
                    <div class="ml-6 space-y-2">
                      <div
                        v-for="venda in solicitacao.vendas"
                        class="flex flex-col p-2 text-sm border rounded-lg bg-gray-50 dark:bg-slate-700 border-gray-200 dark:border-slate-600 ipad:items-center ipad:flex-row ipad:space-x-4"
                      >
                        <div class="flex flex-col ipad:justify-center">
                          <div
                            class="font-semibold text-gray-500 dark:text-gray-400 text-xs"
                          >
                            Filial
                          </div>
                          <div class="text-gray-700 dark:text-gray-300">
                            {{ venda.filial.codigo }} -
                            {{ venda.filial.fantasia }}
                          </div>
                        </div>
                        <div class="flex flex-col ipad:justify-center">
                          <div
                            class="font-semibold text-gray-500 dark:text-gray-400 text-xs"
                          >
                            Data
                          </div>
                          <div class="text-gray-700 dark:text-gray-300">
                            {{ formatarDataSemHoras(venda.data) }}
                          </div>
                        </div>
                        <div class="flex flex-col ipad:justify-center">
                          <div
                            class="font-semibold text-gray-500 dark:text-gray-400 text-xs"
                          >
                            Valor
                          </div>
                          <div class="text-gray-700 dark:text-gray-300">
                            {{ formatarParaReais(venda.valor) }}
                          </div>
                        </div>
                        <div class="flex flex-col ipad:justify-center">
                          <div
                            class="font-semibold text-gray-500 dark:text-gray-400 text-xs"
                          >
                            Operador
                          </div>
                          <div class="text-gray-700 dark:text-gray-300">
                            {{ venda.operador.matricula }} -
                            {{ venda.operador.nome }}
                          </div>
                        </div>
                        <Button
                          @click="verCaixasPendentes(venda)"
                          label="Caixas"
                          class="!h-7 !ml-auto"
                        ></Button>
                      </div>
                    </div>
                  </div>

                  <!-- Equipamentos -->
                  <div
                    v-if="solicitacao.equipamentos.length"
                    class="space-y-2 p-3 rounded-xl border border-gray-200/60 dark:border-slate-600/50"
                  >
                    <div class="flex items-center gap-2">
                      <i
                        class="pi pi-desktop text-violet-500 dark:text-violet-400"
                      ></i>
                      <label
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                      >
                        Equipamentos
                      </label>
                    </div>
                    <div
                      class="grid grid-cols-1 gap-3 ml-6 sm:grid-cols-2 lg:grid-cols-3"
                    >
                      <div
                        v-for="equipamento in solicitacao.equipamentos"
                        :key="equipamento.id"
                        class="p-3 transition-shadow duration-200 bg-gray-50 dark:bg-slate-700 border rounded-lg border-gray-200 dark:border-slate-600 hover:shadow-md"
                      >
                        <div class="flex items-center justify-between mb-1">
                          <span
                            class="font-semibold text-gray-700 dark:text-gray-200 text-sm"
                          >
                            {{ equipamento.quantidade }}x
                            {{ equipamento.equipamento }}
                          </span>
                        </div>
                        <div
                          class="text-xs text-gray-500 dark:text-gray-400 first-letter:capitalize"
                        >
                          {{ equipamento.observacao }}
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Usuários Destino -->
                  <div
                    v-if="solicitacao.usuariosDestino.length"
                    class="space-y-2 p-3 rounded-xl border border-gray-200/60 dark:border-slate-600/50"
                  >
                    <div class="flex items-center gap-2">
                      <i
                        class="pi pi-users text-lime-500 dark:text-lime-400"
                      ></i>
                      <label
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                      >
                        Usuários Destino
                      </label>
                    </div>
                    <div class="flex flex-col space-y-1 ml-6">
                      <div
                        v-for="usuario in solicitacao.usuariosDestino"
                        class="text-sm font-medium text-gray-800 dark:text-white"
                      >
                        {{ usuario.matricula }} - {{ usuario.nome }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <!-- ARQUIVOS AGRUPADOS -->
              <div
                v-for="(grupo, grupoIndex) in arquivosAgrupados"
                :key="'grupo-' + grupoIndex"
                class="mt-4 space-y-2 p-3 rounded-xl border border-gray-200/60 dark:border-slate-600/50"
              >
                <!-- Título do Grupo -->
                <div class="flex items-center gap-2">
                  <i
                    class="pi pi-folder text-orange-500 dark:text-orange-400"
                  ></i>
                  <label
                    class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                  >
                    {{ grupo.label }}
                  </label>
                </div>

                <!-- Lista de Arquivos do Grupo -->
                <div
                  class="grid w-full grid-cols-1 gap-4 pt-2 14pro:grid-cols-2 ipad:grid-cols-4 hd:grid-cols-5 fhd:grid-cols-8"
                >
                  <!-- Card de Arquivo -->
                  <div
                    v-for="arquivo in grupo.arquivos"
                    :key="arquivo.id"
                    class="relative flex flex-col w-40 overflow-hidden bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg shadow-md cursor-pointer hover:border-blue-400 transition-colors"
                    :class="{ 'opacity-50 pointer-events-none': loadingHeic }"
                    @click="verArquivo2(arquivo.file)"
                  >
                    <!-- Prévia ou Ícone do Arquivo -->
                    <div
                      class="flex items-center justify-center p-1 overflow-hidden border-b border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-800 h-28"
                    >
                      <img
                        v-if="
                          ['png', 'jpg', 'jpeg'].includes(
                            arquivo.file.extension
                          )
                        "
                        :src="arquivo.file.external_link"
                        :alt="arquivo.file.stored_name"
                        class="max-h-full max-w-full object-contain"
                      />
                      <div
                        v-else-if="
                          ['heic', 'heif'].includes(
                            arquivo.file.extension.toLowerCase()
                          )
                        "
                        class="flex items-center justify-center p-2 font-bold text-orange-500 uppercase bg-white border-2 border-orange-500 rounded-md hover:bg-orange-50"
                        title="Clique para visualizar arquivo HEIC"
                      >
                        <i class="fas fa-image mr-1"></i>
                        {{ arquivo.file.extension }}
                      </div>
                      <div
                        v-else
                        class="flex items-center justify-center p-2 font-bold text-blue-500 uppercase bg-white border-2 border-blue-500 rounded-md"
                      >
                        {{ arquivo.file.extension }}
                      </div>
                    </div>

                    <!-- Informações do Arquivo -->
                    <div class="flex flex-col p-3 space-y-2">
                      <div
                        class="text-sm font-semibold text-center text-gray-800 truncate capitalize"
                        v-tooltip.bottom="arquivo.file.stored_name"
                      >
                        {{ arquivo.file.stored_name }}
                      </div>
                    </div>

                    <!-- Enviar pro dossie -->
                    <div class="absolute top-1 left-1 flex justify-center">
                      <Button
                        @click.stop="enviarParaDossie(arquivo.file.id)"
                        icon="pi pi-user"
                        v-tooltip.top="'Enviar para Dossiê'"
                        icon-class="!text-xs"
                        outlined
                        class="!h-7 !w-7"
                        severity="contrast"
                        :class="'w-full'"
                      ></Button>
                    </div>

                    <!-- Ações -->
                    <div
                      class="absolute items-center hidden space-x-2 sm:flex top-1 right-1"
                    >
                      <Button
                        @click.stop="verArquivo2(arquivo.file)"
                        icon="pi pi-eye"
                        v-tooltip.top="'Visualizar'"
                        icon-class="!text-xs"
                        class="!h-7 !w-7"
                        outlined
                        severity="contrast"
                        :class="'w-full'"
                        :disabled="loadingHeic"
                      ></Button>
                      <Button
                        @click.stop="baixarArquivo(arquivo.file.id)"
                        icon="pi pi-download"
                        v-tooltip.top="'Baixar'"
                        outlined
                        icon-class="!text-xs"
                        class="!h-7 !w-7"
                        severity="info"
                        :class="'w-full'"
                        :disabled="loadingHeic"
                      ></Button>
                    </div>
                  </div>
                </div>
              </div>
              <div
                v-if="
                  solicitacao.status != 'cancelada' &&
                  solicitacao.status != 'finalizada' &&
                  (solicitacao.isOwner || solicitacao.isDepartamento) &&
                  validaPermissao('solicitacoes.lista.cancelar-solicitacao')
                "
                class="flex justify-end ipad:hidden"
              >
                <Button
                  severity="danger"
                  outlined
                  class="w-full ipad:w-auto !h-9 mt-2"
                  label="Cancelar Ticket"
                  @click="showDialogCancelar()"
                ></Button>
              </div>
            </div>
          </div>

          <!-- ABA COMENTÁRIO -->
          <div
            v-if="aba == 'acompanhar'"
            class="flex flex-col w-full flex-1 min-h-0 overflow-hidden select-text bg-gradient-to-br from-slate-50 via-white to-cyan-50/30"
          >
            <!-- Header com título -->
            <div
              class="flex-shrink-0 flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-white/80 backdrop-blur-sm"
            >
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/25"
                >
                  <i class="fas fa-comments text-white"></i>
                </div>
                <div>
                  <h3 class="text-lg font-bold text-gray-800">Comentários</h3>
                  <p class="text-xs text-gray-500">
                    {{ solicitacao.comentarios?.length || 0 }} mensagem(ns) na
                    conversa
                  </p>
                </div>
              </div>
            </div>

            <!-- Área de mensagens -->
            <div class="flex-1 min-h-0 space-y-4 overflow-auto p-4">
              <!-- Empty State -->
              <div
                v-if="
                  !solicitacao.comentarios ||
                  solicitacao.comentarios.length === 0
                "
                class="flex flex-col items-center justify-center h-full text-center py-12"
              >
                <div
                  class="w-20 h-20 rounded-full bg-gradient-to-br from-gray-100 to-slate-200 flex items-center justify-center mb-4"
                >
                  <i class="fas fa-comments text-3xl text-gray-400"></i>
                </div>
                <p class="text-gray-500 font-medium">Nenhum comentário ainda</p>
                <p class="text-gray-400 text-sm mt-1">
                  Seja o primeiro a comentar!
                </p>
              </div>

              <!-- Lista de comentários -->
              <div
                v-for="(comentario, index) in solicitacao.comentarios"
                :key="index"
                class="flex"
                :class="comentario.is_owner ? 'justify-end' : 'justify-start'"
              >
                <div
                  v-if="
                    comentario.private === null ||
                    (comentario.private === 'S' &&
                      comentario.usuario.matricula === props.auth.matricula) ||
                    (comentario.private === 'A' &&
                      (comentario.usuario.matricula === props.auth.matricula ||
                        comentario.usuario.areaatuacao ===
                          props.auth.areaatuacao))
                  "
                  class="max-w-[85%] ipad:max-w-[70%]"
                >
                  <!-- Card do comentário -->
                  <div
                    class="relative rounded-2xl shadow-sm transition-all hover:shadow-md bg-white border"
                    :class="[
                      comentario.is_owner
                        ? 'rounded-br-md border-gray-200'
                        : 'rounded-bl-md border-gray-100',
                      comentario.private === 'S'
                        ? 'ring-2 ring-violet-400 ring-offset-2'
                        : comentario.private === 'A'
                          ? 'ring-2 ring-blue-400 ring-offset-2'
                          : ''
                    ]"
                  >
                    <!-- Badge Privado -->
                    <div
                      v-if="comentario.private === 'S'"
                      class="absolute -top-2 -right-2 flex items-center gap-1 px-2 py-0.5 bg-gradient-to-r from-violet-500 to-purple-600 text-white text-[10px] font-bold rounded-full shadow-lg"
                    >
                      <i class="pi pi-lock text-[8px]"></i>
                      <span class="hidden sm:inline">Privado</span>
                    </div>
                    <!-- Badge Área de Atuação -->
                    <div
                      v-if="comentario.private === 'A'"
                      class="absolute -top-2 -right-2 flex items-center gap-1 px-2 py-0.5 bg-gradient-to-r from-blue-500 to-cyan-600 text-white text-[10px] font-bold rounded-full shadow-lg"
                    >
                      <i class="pi pi-users text-[8px]"></i>
                      <span class="hidden sm:inline">Área</span>
                    </div>

                    <!-- Header do comentário -->
                    <div
                      class="flex items-center gap-2 px-4 pt-3 pb-1"
                      :class="
                        comentario.is_owner ? 'justify-end' : 'justify-start'
                      "
                    >
                      <!-- Avatar (não owner) -->
                      <div
                        v-if="!comentario.is_owner && comentario.foto_perfil"
                        class="w-7 h-7 rounded-full overflow-hidden flex-shrink-0 ring-2 ring-emerald-400"
                      >
                        <img
                          :src="comentario.foto_perfil"
                          :alt="comentario.usuario.nome"
                          class="w-full h-full object-cover"
                        />
                      </div>
                      <div
                        v-else-if="!comentario.is_owner"
                        class="w-7 h-7 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                      >
                        {{ obterIniciais(comentario.usuario.nome) }}
                      </div>
                      <div
                        class="flex flex-col"
                        :class="
                          comentario.is_owner ? 'items-end' : 'items-start'
                        "
                      >
                        <span
                          class="text-xs font-semibold truncate max-w-[150px] text-gray-700"
                        >
                          {{ comentario.usuario.nome.split(" ")[0] }}
                        </span>
                        <span class="text-[10px] text-gray-400">
                          {{ formatarData(comentario.created_at) }}
                        </span>
                      </div>
                      <!-- Avatar do owner -->
                      <div
                        v-if="comentario.is_owner && comentario.foto_perfil"
                        class="w-7 h-7 rounded-full overflow-hidden flex-shrink-0 ring-2 ring-gray-300"
                      >
                        <img
                          :src="comentario.foto_perfil"
                          :alt="comentario.usuario.nome"
                          class="w-full h-full object-cover"
                        />
                      </div>
                      <div
                        v-else-if="comentario.is_owner"
                        class="w-7 h-7 rounded-full bg-gradient-to-br from-gray-400 to-slate-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                      >
                        {{ obterIniciais(comentario.usuario.nome) }}
                      </div>
                    </div>

                    <!-- Botão Excluir Comentário -->
                    <div
                      v-if="comentario.pode_excluir"
                      class="flex px-4 pb-1"
                      :class="
                        comentario.is_owner ? 'justify-end' : 'justify-start'
                      "
                    >
                      <button
                        @click="excluirComentario(comentario.id)"
                        :disabled="excluindoComentario"
                        class="flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium text-red-500 hover:text-red-700 hover:bg-red-50 rounded-md transition-colors"
                        v-tooltip.top="'Excluir comentário (até 5 min)'"
                      >
                        <i class="pi pi-trash text-[10px]"></i>
                        <span>Excluir</span>
                      </button>
                    </div>

                    <!-- Conteúdo do comentário -->
                    <div
                      v-html="comentario.comentario"
                      class="px-4 pb-3 prose-sm prose max-w-none break-words whitespace-pre-wrap text-gray-700"
                    />

                    <!-- Lista de Arquivos anexados -->
                    <div
                      v-if="
                        comentario.arquivos && comentario.arquivos.length > 0
                      "
                      class="px-3 pb-3"
                    >
                      <div
                        class="grid gap-2"
                        :class="
                          comentario.arquivos.length === 1
                            ? 'grid-cols-1'
                            : 'grid-cols-2'
                        "
                      >
                        <div
                          v-for="(arquivo, idx) in comentario.arquivos"
                          :key="idx"
                          class="relative group rounded-xl overflow-hidden border-2 transition-all border-gray-100 bg-gray-50"
                        >
                          <!-- Preview de imagem -->
                          <div
                            v-if="
                              ['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(
                                arquivo.file.extension?.toLowerCase()
                              )
                            "
                            class="relative h-24 overflow-hidden"
                          >
                            <Image
                              preview
                              :src="arquivo.file.external_link"
                              class="w-full h-full object-cover"
                            />
                            <div
                              class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"
                            ></div>
                          </div>
                          <!-- Ícone de arquivo não-imagem -->
                          <div
                            v-else
                            class="h-20 flex flex-col items-center justify-center gap-1 bg-gray-50"
                          >
                            <div
                              class="w-10 h-10 rounded-lg flex items-center justify-center bg-gradient-to-br from-blue-500 to-cyan-600"
                            >
                              <i class="fas fa-file text-white"></i>
                            </div>
                            <span
                              class="text-[10px] font-bold uppercase text-gray-500"
                            >
                              {{ arquivo.file.extension }}
                            </span>
                          </div>

                          <!-- Info e ações do arquivo -->
                          <div
                            class="p-2 flex items-center justify-between gap-2 bg-white"
                          >
                            <span
                              class="text-[10px] font-medium truncate flex-1 text-gray-600"
                              :title="arquivo.file.stored_name"
                            >
                              {{ arquivo.file.stored_name }}
                            </span>
                            <div class="flex items-center gap-1">
                              <button
                                @click="
                                  visualizarArquivoComentario(
                                    arquivo.file,
                                    comentario.arquivos
                                  )
                                "
                                class="w-6 h-6 rounded-md flex items-center justify-center transition-colors bg-emerald-50 hover:bg-emerald-100 text-emerald-600"
                                v-tooltip.top="'Visualizar'"
                              >
                                <i class="pi pi-eye text-[10px]"></i>
                              </button>
                              <button
                                @click="enviarParaDossie(arquivo.file.id)"
                                class="w-6 h-6 rounded-md flex items-center justify-center transition-colors bg-gray-100 hover:bg-gray-200 text-gray-600"
                                v-tooltip.top="'Enviar para Dossiê'"
                              >
                                <i class="fas fa-user text-[10px]"></i>
                              </button>
                              <button
                                @click="baixarArquivo(arquivo.file.id)"
                                class="w-6 h-6 rounded-md flex items-center justify-center transition-colors bg-blue-50 hover:bg-blue-100 text-blue-600"
                                v-tooltip.top="'Baixar'"
                              >
                                <i class="pi pi-download text-[10px]"></i>
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- SOLICITAÇÃO RESOLVIDA -->
            <div
              v-if="solicitacao.status == 'resolvida' && solicitacao.isOwner"
              class="flex-shrink-0 mx-4 mb-3 p-4 bg-gradient-to-r from-emerald-500 to-green-600 rounded-2xl shadow-lg shadow-emerald-500/25"
            >
              <div
                class="flex flex-col ipad:flex-row items-center justify-between gap-4"
              >
                <div class="flex items-center gap-3 text-white">
                  <div
                    class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center"
                  >
                    <i class="fas fa-check-circle text-2xl"></i>
                  </div>
                  <div>
                    <p class="font-bold text-lg">Ticket Resolvida!</p>
                    <p class="text-emerald-100 text-sm">
                      Sua ticket foi atendida com sucesso
                    </p>
                  </div>
                </div>
                <div class="flex items-center gap-2 w-full ipad:w-auto">
                  <Button
                    @click="finalizar()"
                    class="flex-1 ipad:flex-none !bg-white !text-emerald-600 !border-0 !rounded-xl !h-10 !font-bold hover:!bg-emerald-50 animate-pulse"
                    icon="pi pi-check"
                    label="Finalizar"
                  />
                  <Button
                    @click="showDialogRecusar()"
                    class="flex-1 ipad:flex-none !bg-white/20 !text-white !border-white/30 !rounded-xl !h-10 hover:!bg-white/30"
                    icon="pi pi-times"
                    label="Recusar"
                    outlined
                  />
                </div>
              </div>
            </div>

            <!-- Área de input -->
            <div
              v-if="
                solicitacao.status != 'resolvida' &&
                solicitacao.status != 'cancelada' &&
                solicitacao.status != 'finalizada'
              "
              class="flex-shrink-0 border-t border-gray-100 bg-white p-3"
            >
              <!-- Editor e Toggle numa linha -->
              <div class="flex items-start gap-3">
                <!-- Editor de texto -->
                <div
                  class="flex-1 rounded-xl border border-gray-200 overflow-hidden bg-gray-50 focus-within:ring-2 focus-within:ring-cyan-500/50 focus-within:border-cyan-400 transition-all"
                  :class="
                    enviandoComentario ? 'opacity-60 pointer-events-none' : ''
                  "
                >
                  <Editor
                    v-model="comentario"
                    :readonly="enviandoComentario"
                    placeholder="Escreva seu comentário..."
                    editorStyle="height: 100px; background: transparent;"
                    :pt="{
                      root: { class: '!border-0' },
                      toolbar: {
                        class:
                          '!border-0 !border-b !border-gray-200 !bg-white !py-1'
                      },
                      content: { class: '!bg-gray-50' }
                    }"
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
                          title="Limpar Formatação"
                          class="ql-clean"
                        ></button>
                      </span>
                    </template>
                  </Editor>
                </div>

                <!-- Botões verticais -->
                <div class="flex flex-col gap-2">
                  <!-- Toggle Privado com Menu -->
                  <div class="relative">
                    <button
                      @click="togglePrivado()"
                      :disabled="enviandoComentario"
                      class="w-10 h-10 rounded-xl flex items-center justify-center transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                      :class="
                        privateType === 'S'
                          ? 'bg-gradient-to-r from-violet-500 to-purple-600 text-white shadow-lg shadow-violet-500/25'
                          : privateType === 'A'
                            ? 'bg-gradient-to-r from-blue-500 to-cyan-600 text-white shadow-lg shadow-blue-500/25'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                      "
                      v-tooltip.top="
                        privateType === 'S'
                          ? 'Privado (só eu)'
                          : privateType === 'A'
                            ? 'Minha área de atuação'
                            : 'Comentário público'
                      "
                    >
                      <i
                        :class="
                          privateType === 'S'
                            ? 'pi pi-lock'
                            : privateType === 'A'
                              ? 'pi pi-users'
                              : 'pi pi-lock-open'
                        "
                        class="text-sm"
                      ></i>
                    </button>

                    <!-- Menu dropdown de privacidade -->
                    <div
                      v-if="showPrivateMenu"
                      class="absolute right-0 bottom-12 w-56 bg-white rounded-xl shadow-xl border border-gray-200 py-1 z-50 animate-fadeIn"
                    >
                      <div class="px-3 py-2 border-b border-gray-100">
                        <span
                          class="text-xs font-semibold text-gray-500 uppercase tracking-wide"
                        >
                          Visibilidade
                        </span>
                      </div>
                      <button
                        v-for="option in privateOptions"
                        :key="option.value"
                        @click="selecionarPrivacidade(option)"
                        class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 transition-colors text-left"
                        :class="
                          privateType === option.value ? 'bg-gray-50' : ''
                        "
                      >
                        <div
                          class="w-8 h-8 rounded-lg flex items-center justify-center"
                          :class="
                            option.value === 'S'
                              ? 'bg-violet-100'
                              : option.value === 'A'
                                ? 'bg-blue-100'
                                : 'bg-gray-100'
                          "
                        >
                          <i
                            :class="[option.icon, option.color]"
                            class="text-sm"
                          ></i>
                        </div>
                        <div class="flex-1">
                          <p class="text-sm font-medium text-gray-700">
                            {{ option.label }}
                          </p>
                          <p
                            class="text-[10px] text-gray-400"
                            v-if="option.value === null"
                          >
                            Todos podem ver
                          </p>
                          <p
                            class="text-[10px] text-gray-400"
                            v-else-if="option.value === 'S'"
                          >
                            Somente você
                          </p>
                          <p
                            class="text-[10px] text-gray-400"
                            v-else
                          >
                            Pessoas do mesmo setor
                          </p>
                        </div>
                        <i
                          v-if="privateType === option.value"
                          class="pi pi-check text-xs text-emerald-500"
                        ></i>
                      </button>
                    </div>

                    <!-- Overlay para fechar o menu -->
                    <div
                      v-if="showPrivateMenu"
                      class="fixed inset-0 z-40"
                      @click="showPrivateMenu = false"
                    ></div>
                  </div>

                  <!-- Anexar -->
                  <button
                    @click="showDialogAnexos()"
                    :disabled="enviandoComentario"
                    class="w-10 h-10 rounded-xl flex items-center justify-center transition-all relative disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="
                      arquivosComentario.length > 0
                        ? 'bg-cyan-100 text-cyan-600 hover:bg-cyan-200'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    "
                    v-tooltip.top="'Anexar arquivo'"
                  >
                    <i class="pi pi-paperclip text-sm"></i>
                    <span
                      v-if="arquivosComentario.length > 0"
                      class="absolute -top-1 -right-1 w-5 h-5 bg-cyan-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"
                    >
                      {{ arquivosComentario.length }}
                    </span>
                  </button>

                  <!-- Enviar -->
                  <button
                    @click="comentar('comentarios')"
                    :disabled="
                      enviandoComentario ||
                      (comentario == '' && arquivosComentario.length == 0)
                    "
                    class="w-10 h-10 rounded-xl flex items-center justify-center transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="
                      comentario != '' || arquivosComentario.length > 0
                        ? 'bg-gradient-to-r from-cyan-500 to-blue-600 text-white shadow-lg shadow-cyan-500/25 hover:shadow-xl'
                        : 'bg-gray-200 text-gray-400'
                    "
                    v-tooltip.top="
                      enviandoComentario ? 'Enviando...' : 'Enviar comentário'
                    "
                  >
                    <i
                      :class="
                        enviandoComentario
                          ? 'pi pi-spin pi-spinner'
                          : 'pi pi-send'
                      "
                      class="text-sm"
                    ></i>
                  </button>
                </div>
              </div>

              <!-- Anexos pendentes (se houver) -->
              <div
                v-if="arquivosComentario.length > 0"
                class="mt-2 flex flex-wrap gap-1"
              >
                <div
                  v-for="(arquivo, index) in arquivosComentario"
                  :key="index"
                  class="group flex items-center gap-1 px-2 py-1 bg-cyan-50 rounded-lg border border-cyan-200 text-xs"
                >
                  <i class="pi pi-file text-cyan-600 text-[10px]"></i>
                  <span
                    class="font-medium text-gray-700 truncate max-w-[80px]"
                    :title="arquivo.nome"
                  >
                    {{ arquivo.nome }}
                  </span>
                  <button
                    @click="arquivosComentario.splice(index, 1)"
                    class="w-4 h-4 rounded-full text-red-500 flex items-center justify-center hover:bg-red-100"
                  >
                    <i class="pi pi-times text-[8px]"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div
            v-if="aba == 'agendamento'"
            class="flex flex-col w-full flex-1 min-h-0 py-4 px-2 ipad:px-4 bg-gradient-to-br from-slate-50 via-white to-indigo-50/30"
          >
            <!-- Header com título e botões -->
            <div
              class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 px-2 gap-3"
            >
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/25"
                >
                  <i class="pi pi-calendar text-white"></i>
                </div>
                <div>
                  <h3 class="text-lg font-bold text-gray-800">Agendamentos</h3>
                  <p class="text-xs text-gray-500'">
                    {{ agendamentos?.length || 0 }} agendamento(s) registrado(s)
                  </p>
                </div>
              </div>

              <div class="flex flex-wrap gap-2">
                <!-- Botão Criar Lembrete -->
                <Button
                  v-if="
                    validaPermissao('solicitacoes.lista.criar-agendamento') &&
                    solicitacao.isDepartamento
                  "
                  @click="abrirLembrete()"
                  label="Criar Lembrete"
                  icon="pi pi-bookmark"
                  size="small"
                  outlined
                  class="!rounded-xl shrink-0 !border-amber-500 !text-amber-600 hover:!bg-amber-50 transition-all"
                  :disabled="
                    ![
                      'pendente',
                      'atendimento pausado',
                      'resolução recusada',
                      'em atendimento'
                    ].includes(solicitacao.status)
                  "
                ></Button>

                <!-- Botão Novo Agendamento (Visita) -->
                <Button
                  v-if="
                    validaPermissao('solicitacoes.lista.criar-agendamento') &&
                    solicitacao.isDepartamento &&
                    solicitacao.departamento_responsavel ===
                      'TECNOLOGIA DA INFORMACAO'
                  "
                  @click="abrirAgendamento()"
                  label="Nova Visita"
                  icon="pi pi-car"
                  size="small"
                  class="!rounded-xl shrink-0 !bg-gradient-to-r !from-indigo-500 !to-purple-600 !border-0 !shadow-lg !shadow-indigo-500/25 hover:!shadow-xl hover:!shadow-indigo-500/30 transition-all"
                  :disabled="
                    (agendamentos.length > 0 &&
                      agendamentos.some(
                        (ag) =>
                          ag.status !== 'cancelado' &&
                          ag.status !== 'finalizado' &&
                          ag.tipo !== 'lembrete'
                      )) ||
                    ![
                      'pendente',
                      'atendimento pausado',
                      'resolução recusada'
                    ].includes(solicitacao.status)
                  "
                ></Button>
              </div>
            </div>

            <!-- Lista de Agendamentos -->
            <div
              v-if="agendamentos.length > 0"
              class="flex-1 overflow-auto space-y-3 px-1"
            >
              <div
                v-for="(agendamento, index) in agendamentos"
                :key="agendamento.id"
                @click.stop="detalharAgendamento(agendamento)"
                class="group relative bg-white rounded-xl overflow-hidden cursor-pointer transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl border-l-4"
                :class="{
                  'opacity-70 border-gray-400':
                    agendamento.status == 'cancelado',
                  'border-green-500': agendamento.status == 'finalizado',
                  'border-amber-500':
                    agendamento.tipo == 'lembrete' &&
                    agendamento.status != 'cancelado' &&
                    agendamento.status != 'finalizado',
                  'border-indigo-500':
                    agendamento.tipo != 'lembrete' &&
                    agendamento.status != 'cancelado' &&
                    agendamento.status != 'finalizado',
                  'shadow-md': true
                }"
              >
                <!-- Efeito de brilho no hover -->
                <div
                  class="absolute inset-0 bg-gradient-to-r from-white/0 via-white/60 to-white/0 translate-x-[-200%] group-hover:translate-x-[200%] transition-transform duration-1000 skew-x-12"
                ></div>

                <!-- Ribbon de status - sempre visível -->
                <div
                  class="absolute -top-1 -right-1 w-20 h-20 overflow-hidden z-10"
                >
                  <div
                    class="absolute top-5 -right-6 w-28 h-7 rotate-45 flex items-center justify-center shadow-sm text-[10px] font-bold text-white uppercase tracking-wider"
                    :class="
                      agendamento.status == 'cancelado'
                        ? 'bg-gradient-to-r from-red-500 to-red-600'
                        : agendamento.status == 'finalizado'
                          ? 'bg-gradient-to-r from-green-500 to-green-600'
                          : agendamento.status == 'em atendimento'
                            ? 'bg-gradient-to-r from-cyan-500 to-blue-500'
                            : agendamento.tipo == 'lembrete'
                              ? 'bg-gradient-to-r from-amber-500 to-orange-500'
                              : 'bg-gradient-to-r from-indigo-500 to-purple-500'
                    "
                  >
                    <span v-if="agendamento.status == 'cancelado'">
                      Cancelado
                    </span>
                    <span v-else-if="agendamento.status == 'finalizado'">
                      Finalizado
                    </span>
                    <span v-else-if="agendamento.status == 'em atendimento'">
                      Atendimento
                    </span>
                    <span v-else-if="agendamento.tipo == 'lembrete'">
                      Lembrete
                    </span>
                    <span v-else>Visita</span>
                  </div>
                </div>

                <div class="relative p-4">
                  <!-- Header - Diferente para lembrete e visita -->
                  <div class="flex items-center gap-2 mb-3 pr-14">
                    <div
                      class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                      :class="
                        agendamento.tipo == 'lembrete'
                          ? 'bg-gradient-to-br from-amber-100 to-orange-100'
                          : 'bg-gradient-to-br from-blue-100 to-indigo-100'
                      "
                    >
                      <i
                        :class="
                          agendamento.tipo == 'lembrete'
                            ? 'pi pi-bookmark text-amber-600'
                            : 'fas fa-store text-indigo-600'
                        "
                        class="text-sm"
                      ></i>
                    </div>
                    <span class="font-bold text-gray-800">
                      <template v-if="agendamento.tipo == 'lembrete'">
                        Lembrete
                      </template>
                      <template v-else>
                        {{ agendamento.filial }} - {{ agendamento.nomeFilial }}
                      </template>
                    </span>
                  </div>

                  <!-- Grid com Info e Ações na mesma linha -->
                  <div
                    class="flex flex-col ipad:flex-row ipad:items-center gap-3"
                  >
                    <!-- Meta Info -->
                    <div class="flex flex-wrap items-center gap-2 flex-1">
                      <!-- Responsável -->
                      <div
                        v-tooltip.top="agendamento.nomeResponsavel"
                        class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 rounded-lg cursor-default"
                      >
                        <!-- Avatar com foto ou iniciais -->
                        <div
                          v-if="agendamento.fotoResponsavel"
                          class="w-6 h-6 rounded-full overflow-hidden flex-shrink-0 ring-2 ring-cyan-200"
                        >
                          <img
                            :src="agendamento.fotoResponsavel"
                            :alt="agendamento.nomeResponsavel"
                            class="w-full h-full object-cover"
                          />
                        </div>
                        <div
                          v-else
                          class="w-6 h-6 rounded-full bg-gradient-to-br from-cyan-400 to-blue-500 flex items-center justify-center flex-shrink-0 text-[9px] font-bold text-white"
                        >
                          {{ obterIniciais(agendamento.nomeResponsavel) }}
                        </div>
                        <div class="flex flex-col">
                          <span
                            class="text-[9px] text-gray-400 uppercase tracking-wider font-semibold leading-none"
                          >
                            Responsável
                          </span>
                          <span class="text-xs font-medium text-gray-700">
                            {{ tratarNome(agendamento.nomeResponsavel) }}
                          </span>
                        </div>
                      </div>

                      <!-- Data de Criação -->
                      <div
                        class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 rounded-lg"
                      >
                        <div
                          class="w-6 h-6 rounded-full bg-gradient-to-br from-emerald-400 to-green-500 flex items-center justify-center"
                        >
                          <i
                            class="fas fa-calendar-plus text-white text-[10px]"
                          ></i>
                        </div>
                        <div class="flex flex-col">
                          <span
                            class="text-[9px] text-gray-400 uppercase tracking-wider font-semibold leading-none"
                          >
                            Criado em
                          </span>
                          <span class="text-xs font-medium text-gray-700">
                            {{ formatarDataSemHoras(agendamento.created_at) }}
                          </span>
                        </div>
                      </div>

                      <!-- Data Agendada -->
                      <div
                        v-if="
                          agendamento.status != 'cancelado' &&
                          agendamento.status != 'finalizado'
                        "
                        class="flex items-center gap-2 px-3 py-1.5 bg-indigo-50 border border-indigo-200 rounded-lg"
                      >
                        <div
                          class="w-6 h-6 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center animate-pulse"
                        >
                          <i class="fas fa-clock text-white text-[10px]"></i>
                        </div>
                        <div class="flex flex-col">
                          <span
                            class="text-[9px] text-indigo-400 uppercase tracking-wider font-semibold leading-none"
                          >
                            Agendado
                          </span>
                          <span class="text-xs font-bold text-indigo-700">
                            {{ formatarData(agendamento.data_agendamento) }}
                          </span>
                        </div>
                      </div>
                    </div>

                    <!-- Ações - Alinhadas -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                      <Button
                        v-if="
                          agendamento.status != 'cancelado' &&
                          agendamento.status != 'finalizado' &&
                          validaPermissao(
                            'solicitacoes.lista.cancelar-agendamento'
                          ) &&
                          solicitacao.isDepartamento
                        "
                        @click.stop="cancelarAgendamento(agendamento.id)"
                        label="Cancelar"
                        icon="pi pi-ban"
                        severity="danger"
                        outlined
                        class="!rounded-lg !h-8 !text-xs"
                      />
                      <Button
                        v-if="
                          agendamento.rota && agendamento.tipo != 'lembrete'
                        "
                        @click.stop="redirectMaps(agendamento.rota)"
                        label="Rota"
                        outlined
                        icon="pi pi-map-marker"
                        severity="success"
                        class="!rounded-lg !h-8 !text-xs"
                      />
                      <Button
                        v-if="
                          agendamento.tipo != 'lembrete' &&
                          agendamento.status != 'finalizado' &&
                          agendamento.status != 'cancelado' &&
                          validaPermissao(
                            'solicitacoes.lista.criar-agendamento'
                          ) &&
                          solicitacao.departamento_responsavel ==
                            props.auth.areaatuacao
                        "
                        @click.stop="editarAgendamento()"
                        label="Editar"
                        icon="pi pi-pencil"
                        severity="info"
                        class="!rounded-lg !h-8 !text-xs"
                        outlined
                      />
                    </div>
                  </div>
                </div>

                <!-- Footer decorativo -->
                <div
                  class="h-1 w-full"
                  :class="
                    agendamento.status == 'cancelado'
                      ? 'bg-gradient-to-r from-gray-300 to-gray-400'
                      : agendamento.status == 'finalizado'
                        ? 'bg-gradient-to-r from-green-400 to-emerald-500'
                        : agendamento.tipo == 'lembrete'
                          ? 'bg-gradient-to-r from-amber-400 to-orange-500'
                          : 'bg-gradient-to-r from-indigo-400 to-purple-500'
                  "
                ></div>
              </div>
            </div>

            <!-- Empty State Moderno -->
            <div
              v-else
              class="flex-1 flex flex-col items-center justify-center"
            >
              <div class="relative">
                <div
                  class="w-32 h-32 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center mb-6 shadow-lg"
                >
                  <i class="pi pi-calendar-times !text-5xl text-indigo-400"></i>
                </div>
              </div>
              <h4 class="text-xl font-bold text-gray-700 mb-2">
                Nenhum agendamento
              </h4>
              <p class="text-gray-400 text-center max-w-xs">
                Ainda não existem agendamentos para essa ticket.
              </p>
            </div>
          </div>

          <div
            v-if="aba == 'historico'"
            class="flex flex-col w-full flex-1 min-h-0 py-4 px-2 ipad:px-4 overflow-auto bg-slate-50/60 dark:bg-slate-900"
          >
            <!-- Header do Histórico -->
            <div class="flex items-center justify-between mb-5 px-1">
              <div class="flex items-center gap-3">
                <div
                  class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center"
                >
                  <i
                    class="fas fa-history text-indigo-600 dark:text-indigo-400 text-sm"
                  ></i>
                </div>
                <div class="leading-tight">
                  <h3
                    class="text-sm font-semibold text-slate-800 dark:text-slate-100"
                  >
                    Linha do Tempo
                  </h3>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    {{ solicitacao.movimentacoes?.length || 0 }}
                    {{
                      (solicitacao.movimentacoes?.length || 0) === 1
                        ? "evento"
                        : "eventos"
                    }}
                  </p>
                </div>
              </div>
              <div
                class="hidden ipad:flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700"
              >
                <span
                  class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"
                ></span>
                Atualizado
              </div>
            </div>

            <!-- Timeline (componente compartilhado) -->
            <LinhaTempo
              :eventosExternos="eventosTimeline"
              labelInicio="Início da Jornada"
              :idInicio="`Ticket #${solicitacao.id}`"
            />
          </div>
          <!-- ═══════════════════════════════════════════════════════════════ -->
          <!-- ║                        ABA DE FOTOS                            ║ -->
          <!-- ═══════════════════════════════════════════════════════════════ -->
          <div
            class="flex flex-col w-full p-4"
            v-if="aba == 'anexos'"
          >
            <!-- Header da Seção -->
            <div class="mb-6">
              <div class="flex items-center gap-3 mb-2">
                <div
                  class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 flex items-center justify-center shadow-lg"
                >
                  <i class="fas fa-images text-white"></i>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-gray-800">
                    Galeria de Fotos
                  </h2>
                  <p class="text-sm text-gray-500">
                    Fotos e assinaturas dos agendamentos
                  </p>
                </div>
              </div>
            </div>

            <!-- Fotos Anexadas -->
            <div
              v-if="existeAnexo"
              class="space-y-6"
            >
              <!-- Seção de Fotos -->
              <div
                class="bg-gradient-to-br from-gray-50 to-slate-100 rounded-2xl p-5 border border-gray-200"
              >
                <div class="flex items-center gap-2 mb-4">
                  <div
                    class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center"
                  >
                    <i class="fas fa-camera text-white text-sm"></i>
                  </div>
                  <h3 class="font-bold text-gray-700">Fotos Anexadas</h3>
                  <span
                    class="ml-auto px-2 py-0.5 bg-cyan-100 text-cyan-700 text-xs font-semibold rounded-full"
                  >
                    {{
                      agendamentos.reduce(
                        (acc, ag) => acc + (ag.anexo?.length || 0),
                        0
                      )
                    }}
                    arquivo(s)
                  </span>
                </div>

                <!-- Grid de Fotos -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <template
                    v-for="agendamento in agendamentos"
                    :key="agendamento.id"
                  >
                    <div
                      v-for="arquivo in agendamento.anexo"
                      :key="arquivo.id"
                      @click="detalharAgendamento(agendamento)"
                      class="group relative bg-white rounded-xl border border-gray-200 overflow-hidden cursor-pointer hover:shadow-lg hover:border-cyan-300 transition-all duration-300"
                      :class="{ 'opacity-50 pointer-events-none': loadingHeic }"
                    >
                      <!-- Barra de cor lateral -->
                      <div
                        class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-cyan-400 to-blue-500"
                      ></div>

                      <div class="flex items-center gap-3 p-3 pl-4">
                        <!-- Ícone do arquivo -->
                        <div
                          class="w-12 h-12 rounded-xl bg-gradient-to-br from-cyan-50 to-blue-50 flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform"
                        >
                          <i
                            :class="[
                              getIcon(arquivo.extension),
                              'text-cyan-600 text-lg'
                            ]"
                          ></i>
                        </div>

                        <!-- Info do arquivo -->
                        <div class="flex-1 min-w-0">
                          <h4
                            class="font-semibold text-gray-800 truncate text-sm"
                          >
                            {{ arquivo.original_name }}
                          </h4>
                          <div class="flex items-center gap-2 mt-1">
                            <div
                              class="flex items-center gap-1 text-xs text-gray-500"
                            >
                              <i class="fas fa-user text-[10px]"></i>
                              <span>{{ tratarNome(arquivo.userName) }}</span>
                            </div>
                          </div>
                        </div>

                        <!-- Botão Visualizar -->
                        <button
                          @click.stop="verArquivo(arquivo)"
                          class="w-10 h-10 rounded-xl bg-gray-100 group-hover:bg-cyan-500 flex items-center justify-center transition-all duration-300"
                        >
                          <i
                            class="fas fa-eye text-gray-400 group-hover:text-white transition-colors"
                          ></i>
                        </button>
                      </div>
                    </div>
                  </template>
                </div>
              </div>
            </div>

            <!-- Estado Vazio -->
            <div
              v-else
              class="flex flex-col items-center justify-center py-16 px-4"
            >
              <div class="relative mb-6">
                <div
                  class="w-24 h-24 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center"
                >
                  <i class="pi pi-folder-open !text-4xl text-gray-400"></i>
                </div>
              </div>
              <h3 class="text-lg font-bold text-gray-700 mb-2">
                Nenhuma foto ainda
              </h3>
              <p class="text-sm text-gray-500 text-center max-w-xs">
                As fotos dos agendamentos aparecerão aqui quando forem
                adicionadas
              </p>
            </div>

            <!-- Seção de Assinaturas -->
            <div
              v-if="
                Array.isArray(agendamentos) &&
                agendamentos.some((item) => item.imagem_assinatura)
              "
              class="mt-6"
            >
              <div
                class="bg-gradient-to-br from-purple-50 to-violet-100 rounded-2xl p-5 border border-purple-200"
              >
                <div class="flex items-center gap-2 mb-4">
                  <div
                    class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center"
                  >
                    <i class="fas fa-signature text-white text-sm"></i>
                  </div>
                  <h3 class="font-bold text-gray-700">Assinaturas</h3>
                  <span
                    class="ml-auto px-2 py-0.5 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full"
                  >
                    {{
                      (Array.isArray(agendamentos) ? agendamentos : []).filter(
                        (item) => item.imagem_assinatura
                      ).length
                    }}
                    assinatura(s)
                  </span>
                </div>

                <!-- Grid de Assinaturas -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div
                    v-for="assinatura in (Array.isArray(agendamentos)
                      ? agendamentos
                      : []
                    ).filter((item) => item.imagem_assinatura)"
                    :key="assinatura.id"
                    @click="detalharAgendamento(assinatura)"
                    class="group relative bg-white rounded-xl border border-purple-200 overflow-hidden cursor-pointer hover:shadow-lg hover:border-purple-400 transition-all duration-300"
                    :class="{ 'opacity-50 pointer-events-none': loadingHeic }"
                  >
                    <!-- Barra de cor lateral -->
                    <div
                      class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-purple-400 to-violet-500"
                    ></div>

                    <div class="flex items-center gap-3 p-3 pl-4">
                      <!-- Ícone de assinatura -->
                      <div
                        class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-50 to-violet-50 flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform"
                      >
                        <i class="fas fa-pen-nib text-purple-600 text-lg"></i>
                      </div>

                      <!-- Info da assinatura -->
                      <div class="flex-1 min-w-0">
                        <h4
                          class="font-semibold text-gray-800 truncate text-sm"
                        >
                          {{ assinatura.imagem_assinatura.original_name }}
                        </h4>
                        <div class="flex items-center gap-2 mt-1">
                          <div
                            class="flex items-center gap-1 text-xs text-gray-500"
                          >
                            <i class="fas fa-user-check text-[10px]"></i>
                            <span>
                              {{
                                tratarNome(
                                  assinatura.imagem_assinatura.user_assinatura
                                )
                              }}
                            </span>
                          </div>
                        </div>
                      </div>

                      <!-- Botão Visualizar -->
                      <button
                        @click.stop="verArquivo(assinatura.imagem_assinatura)"
                        class="w-10 h-10 rounded-xl bg-gray-100 group-hover:bg-purple-500 flex items-center justify-center transition-all duration-300"
                      >
                        <i
                          class="fas fa-eye text-gray-400 group-hover:text-white transition-colors"
                        ></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Aba de Aprovações -->
          <div
            class="flex flex-col w-full flex-1 min-h-0 overflow-auto p-4"
            v-if="aba == 'aprovacoes'"
          >
            <Aprovacao
              ref="aprovacaoRef"
              :solicitacao-id="props.solicitacao_id"
              :auth="props.auth"
              :aprovacao-id-rejeitar="props.aprovacaoIdRejeitar"
              @atualizar="buscarSolicitacao"
              @aprovacoes-atualizadas="atualizarAprovacoes"
            />
          </div>
        </div>
      </div>

      <div
        v-if="
          solicitacao.usuario_responsavel &&
          solicitacao.usuario_responsavel.matricula == props.auth.matricula &&
          (solicitacao.status == 'pendente' ||
            solicitacao.status == 'atendimento pausado' ||
            solicitacao.status == 'resolução recusada' ||
            (solicitacao.status == 'agendado' &&
              solicitacao.agendamentos.every(
                (ag) =>
                  ag.tipo == 'lembrete' ||
                  ag.status == 'cancelado' ||
                  ag.status == 'finalizado'
              )))
        "
        class="flex p-3 ipad:hidden"
      >
        <Button
          label="Atender"
          @click="iniciarAtendimento()"
          icon="pi pi-play"
          class="w-full pb-10"
          outlined
        ></Button>
      </div>

      <div
        v-if="
          solicitacao.usuario_responsavel &&
          solicitacao.usuario_responsavel.matricula == props.auth.matricula &&
          solicitacao.status == 'em atendimento'
        "
        class="flex p-3 space-x-2 ipad:hidden"
      >
        <Button
          @click="dialogRetorno = true"
          label="Devolver"
          icon="pi pi-replay"
          icon-class="!text-sm"
          class="w-full"
          severity="warn"
          outlined
        />
        <Button
          label="Pausar"
          @click="pausarAtendimento()"
          severity="danger"
          class="w-full pb-10"
          icon="pi pi-pause"
          outlined
        ></Button>
        <Button
          @click="resolver()"
          label="Resolver"
          severity="success"
          class="w-full"
          icon="pi pi-check"
          outlined
        ></Button>
      </div>
    </div>
  </div>

  <Dialog
    v-model:visible="dialogMudarPrioridade"
    modal
    position="top"
    :closable="false"
    :pt="{
      root: {
        class:
          '!border-0 !rounded-2xl overflow-hidden w-[95vw] sm:w-[400px] !bg-transparent !shadow-none'
      },
      header: { class: 'hidden' },
      content: { class: '!p-0' },
      mask: { class: 'backdrop-blur-sm' }
    }"
  >
    <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden">
      <!-- Header Personalizado -->
      <div class="bg-gradient-to-r from-violet-500 to-purple-600 px-5 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
            >
              <i class="pi pi-flag text-white text-lg"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">Mudar Prioridade</h3>
              <p class="text-white/80 text-xs">
                Defina a prioridade desta ticket
              </p>
            </div>
          </div>
          <Button
            @click="dialogMudarPrioridade = false"
            icon="pi pi-times"
            rounded
            outlined
            severity="secondary"
            class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
          />
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="p-5 space-y-4">
        <div>
          <label
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            Selecione a Prioridade
          </label>
          <Select
            fluid
            v-model="prioridadeSelecionada"
            :options="['baixa', 'media', 'alta', 'urgente']"
            class="w-full"
          >
            <template #option="slot">
              <div class="flex items-center gap-2 py-1">
                <span
                  class="w-3 h-3 rounded-full"
                  :class="{
                    'bg-green-500': slot.option === 'baixa',
                    'bg-yellow-500': slot.option === 'media',
                    'bg-orange-500': slot.option === 'alta',
                    'bg-red-500': slot.option === 'urgente'
                  }"
                ></span>
                <span class="uppercase font-medium">{{ slot.option }}</span>
              </div>
            </template>
            <template #value="slot">
              <div
                class="flex items-center gap-2"
                v-if="slot.value"
              >
                <span
                  class="w-3 h-3 rounded-full"
                  :class="{
                    'bg-green-500': slot.value === 'baixa',
                    'bg-yellow-500': slot.value === 'media',
                    'bg-orange-500': slot.value === 'alta',
                    'bg-red-500': slot.value === 'urgente'
                  }"
                ></span>
                <span class="uppercase font-medium">{{ slot.value }}</span>
              </div>
            </template>
          </Select>
        </div>

        <Button
          @click="mudarPrioridade()"
          fluid
          class="!rounded-xl !h-11"
          severity="contrast"
          outlined
          label="Definir Prioridade"
          icon="pi pi-check"
        />
      </div>
    </div>
  </Dialog>

  <!-- Dialog Selecionar Novo Solicitante -->
  <Dialog
    v-model:visible="dialogTrocarSolicitante"
    modal
    position="top"
    :closable="false"
    :pt="{
      root: {
        class:
          '!border-0 !rounded-2xl overflow-hidden w-[95vw] sm:w-[450px] !bg-transparent !shadow-none'
      },
      header: { class: 'hidden' },
      content: { class: '!p-0' },
      mask: { class: 'backdrop-blur-sm' }
    }"
  >
    <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden">
      <!-- Header Personalizado -->
      <div class="bg-gradient-to-r from-blue-500 to-cyan-600 px-5 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
            >
              <i class="pi pi-users text-white text-lg"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">Trocar Solicitante</h3>
              <p class="text-white/80 text-xs">
                Alterar o solicitante desta ticket
              </p>
            </div>
          </div>
          <Button
            @click="dialogTrocarSolicitante = false"
            icon="pi pi-times"
            rounded
            outlined
            severity="secondary"
            class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
          />
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="p-5 space-y-4">
        <!-- Solicitante Atual -->
        <div
          class="p-4 bg-gradient-to-r from-blue-50 to-cyan-50 dark:from-slate-700 dark:to-slate-700 rounded-xl border border-blue-100 dark:border-slate-600"
        >
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center"
            >
              <i class="pi pi-user text-white text-sm"></i>
            </div>
            <div>
              <p
                class="text-[10px] uppercase tracking-wider font-semibold text-blue-600 dark:text-blue-400"
              >
                Solicitante Atual
              </p>
              <p class="text-sm font-bold text-gray-800 dark:text-white">
                {{ solicitacao.usuario_solicitante.nome }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Mat. {{ solicitacao.usuario_solicitante.matricula }}
              </p>
            </div>
          </div>
        </div>

        <!-- Novo Solicitante -->
        <div>
          <label
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            <i class="pi pi-user-plus text-xs mr-1"></i>
            Novo Solicitante
          </label>
          <Funcionario
            v-model="novoSolicitanteSelecionado"
            :retorna-objeto="true"
            placeholder="Selecione o novo solicitante..."
          />
        </div>

        <Button
          @click="dialogMotivoSolicitante = true"
          fluid
          class="!rounded-xl !h-11"
          severity="contrast"
          outlined
          label="Trocar Solicitante"
          icon="pi pi-arrow-right-arrow-left"
          :disabled="
            !novoSolicitanteSelecionado ||
            novoSolicitanteSelecionado.matricula ===
              solicitacao.usuario_solicitante.matricula
          "
        />
      </div>
    </div>
  </Dialog>

  <!-- Dialog Motivo da Troca -->
  <Dialog
    v-model:visible="dialogMotivoSolicitante"
    modal
    position="top"
    :closable="false"
    :pt="{
      root: {
        class:
          '!border-0 !rounded-2xl overflow-hidden w-[95vw] sm:w-[420px] !bg-transparent !shadow-none'
      },
      header: { class: 'hidden' },
      content: { class: '!p-0' },
      mask: { class: 'backdrop-blur-sm' }
    }"
  >
    <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden">
      <!-- Header Personalizado -->
      <div class="bg-gradient-to-r from-emerald-500 to-teal-600 px-5 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
            >
              <i class="pi pi-pencil text-white text-lg"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">Confirmar Alteração</h3>
              <p class="text-white/80 text-xs">Informe o motivo da troca</p>
            </div>
          </div>
          <Button
            @click="dialogMotivoSolicitante = false"
            icon="pi pi-times"
            rounded
            outlined
            severity="secondary"
            class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
          />
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="p-5 space-y-4">
        <div>
          <label
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            <i class="pi pi-comment text-xs mr-1"></i>
            Motivo da alteração do solicitante
          </label>
          <Textarea
            rows="6"
            v-model="comentarioMotivoSolicitante"
            placeholder="Descreva o motivo da alteração..."
            class="w-full !rounded-xl"
          />
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <span
              :class="
                comentarioMotivoSolicitante.length >= 10
                  ? 'text-emerald-600'
                  : 'text-orange-500'
              "
            >
              {{ comentarioMotivoSolicitante.length }}
            </span>
            / mínimo 10 caracteres
          </p>
        </div>

        <Button
          severity="success"
          icon="pi pi-check"
          @click="alterarSolicitante()"
          label="Confirmar Alteração"
          fluid
          class="!rounded-xl !h-11"
          :disabled="comentarioMotivoSolicitante.length < 10"
        />
      </div>
    </div>
  </Dialog>

  <Dialog
    v-model:visible="dialogMudarDepartamento"
    modal
    position="top"
    :closable="false"
    :pt="{
      root: {
        class:
          '!border-0 !rounded-2xl !overflow-hidden w-[95vw] sm:w-[500px] max-h-[90vh] !bg-transparent !shadow-none'
      },
      header: { class: 'hidden' },
      content: { class: '!p-0' },
      mask: { class: 'backdrop-blur-sm' }
    }"
  >
    <div
      class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden relative"
    >
      <!-- Overlay de Loading -->
      <div
        v-if="loading"
        class="absolute inset-0 bg-white/70 dark:bg-slate-800/70 z-50 flex items-center justify-center rounded-2xl"
      >
        <div class="flex flex-col items-center gap-3">
          <i class="pi pi-spin pi-spinner text-3xl text-blue-500"></i>
          <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
            Processando...
          </span>
        </div>
      </div>

      <!-- Header Personalizado -->
      <div
        class="bg-gradient-to-r from-sky-500 to-blue-600 px-5 py-4 rounded-t-2xl"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
            >
              <i
                :class="trocaAssunto ? 'pi pi-tag' : 'pi pi-building'"
                class="text-white text-lg"
              ></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">
                {{ trocaAssunto ? "Trocar Assunto" : "Mudar Departamento" }}
              </h3>
              <p class="text-white/80 text-xs">
                {{
                  trocaAssunto
                    ? "Altere o assunto da ticket"
                    : "Altere o departamento responsável"
                }}
              </p>
            </div>
          </div>
          <Button
            @click="dialogMudarDepartamento = false"
            icon="pi pi-times"
            rounded
            outlined
            severity="secondary"
            class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
            :disabled="loading"
          />
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="p-5 space-y-4 max-h-[60vh] overflow-y-auto">
        <!-- Departamento -->
        <div>
          <label
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            <i class="pi pi-building text-xs mr-1"></i>
            Departamento
          </label>
          <Select
            class="w-full"
            fluid
            v-model="deptoSelecionado"
            :options="deptos"
            option-label="condicao1"
            :disabled="trocaAssunto || loading"
          >
            <template #option="slot">
              <div class="uppercase">{{ slot.option.condicao1 }}</div>
            </template>
          </Select>
        </div>

        <!-- Assunto -->
        <div v-if="deptoSelecionado">
          <label
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            <i class="pi pi-tag text-xs mr-1"></i>
            Assunto
          </label>
          <Select
            fluid
            v-model="assuntoSelecionado"
            :options="assuntosFiltrados"
            option-value="id"
            option-label="assunto"
            placeholder="Selecione o Assunto"
            class="w-full uppercase"
            show-clear
            :disabled="loading"
          >
            <template #option="slot">
              <div class="uppercase">{{ slot.option.assunto }}</div>
            </template>
          </Select>
        </div>

        <!-- Campos customizados (selects) do assunto selecionado -->
        <div
          v-if="
            assuntoComSelects &&
            assuntoComSelects.selects &&
            assuntoComSelects.selects.filter(
              (s) =>
                s.tipo !== 'arquivo' &&
                s.exibir_atendimento === 'S' &&
                campoDeveSerExibidoAtendimento(s, assuntoComSelects)
            ).length > 0
          "
          class="p-4 bg-gray-50 dark:bg-slate-700/50 border border-gray-200 dark:border-slate-600 rounded-xl space-y-4"
        >
          <div
            class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
          >
            <i class="pi pi-list text-xs"></i>
            Campos do Processo
          </div>
          <div class="flex flex-wrap gap-3">
            <div
              v-for="select in assuntoComSelects.selects.filter(
                (s) =>
                  s.tipo !== 'arquivo' &&
                  s.exibir_atendimento === 'S' &&
                  campoDeveSerExibidoAtendimento(s, assuntoComSelects)
              )"
              :key="select.id"
              class="flex flex-col"
            >
              <label class="text-xs text-gray-600 font-medium mb-1">
                {{ select.label }}
                <span
                  v-if="select.obrigatorio === 'S'"
                  class="text-red-500"
                >
                  *
                </span>
              </label>

              <template v-if="select.tipo === 'selecao'">
                <MultiSelect
                  v-if="select.multiplo === 'S'"
                  class="!w-52"
                  :options="select.valores"
                  option-value="code"
                  option-label="label"
                  placeholder="Selecione..."
                  filter
                  @update:model-value="
                    (val) => addRespostaAlteracao(select, val)
                  "
                />
                <Select
                  v-else
                  class="!w-52"
                  :options="select.valores"
                  option-value="code"
                  option-label="label"
                  placeholder="Selecione..."
                  show-clear
                  @update:model-value="
                    (val) => addRespostaAlteracao(select, val)
                  "
                />
              </template>

              <!-- Campos pré-definidos Winthor -->
              <!-- Depto de Compras -->
              <template v-else-if="select.tipo === 'depto_compras'">
                <MultiSelect
                  v-if="select.multiplo === 'S'"
                  class="!w-52"
                  :options="dadosWinthor.depto_compras"
                  option-value="value"
                  option-label="label"
                  placeholder="Selecione..."
                  filter
                  :loading="loadingWinthor.depto_compras"
                  @show="carregarDadosWinthor('depto_compras')"
                  @update:model-value="
                    (val) => addRespostaAlteracao(select, val)
                  "
                />
                <Select
                  v-else
                  class="!w-52"
                  :options="dadosWinthor.depto_compras"
                  option-value="value"
                  option-label="label"
                  placeholder="Selecione..."
                  show-clear
                  filter
                  :loading="loadingWinthor.depto_compras"
                  @show="carregarDadosWinthor('depto_compras')"
                  @update:model-value="
                    (val) => addRespostaAlteracao(select, val)
                  "
                />
              </template>

              <!-- Depto de Funcionário -->
              <template v-else-if="select.tipo === 'depto_funcionario'">
                <MultiSelect
                  v-if="select.multiplo === 'S'"
                  class="!w-52"
                  :options="dadosWinthor.depto_funcionario"
                  option-value="value"
                  option-label="label"
                  placeholder="Selecione..."
                  filter
                  :loading="loadingWinthor.depto_funcionario"
                  @show="carregarDadosWinthor('depto_funcionario')"
                  @update:model-value="
                    (val) => addRespostaAlteracao(select, val)
                  "
                />
                <Select
                  v-else
                  class="!w-52"
                  :options="dadosWinthor.depto_funcionario"
                  option-value="value"
                  option-label="label"
                  placeholder="Selecione..."
                  show-clear
                  filter
                  :loading="loadingWinthor.depto_funcionario"
                  @show="carregarDadosWinthor('depto_funcionario')"
                  @update:model-value="
                    (val) => addRespostaAlteracao(select, val)
                  "
                />
              </template>

              <!-- Filial Winthor -->
              <template v-else-if="select.tipo === 'filial_winthor'">
                <MultiSelect
                  v-if="select.multiplo === 'S'"
                  class="!w-52"
                  :options="dadosWinthor.filial_winthor"
                  option-value="value"
                  option-label="label"
                  placeholder="Selecione..."
                  filter
                  :loading="loadingWinthor.filial_winthor"
                  @show="carregarDadosWinthor('filial_winthor')"
                  @update:model-value="
                    (val) => addRespostaAlteracao(select, val)
                  "
                />
                <Select
                  v-else
                  class="!w-52"
                  :options="dadosWinthor.filial_winthor"
                  option-value="value"
                  option-label="label"
                  placeholder="Selecione..."
                  show-clear
                  filter
                  :loading="loadingWinthor.filial_winthor"
                  @show="carregarDadosWinthor('filial_winthor')"
                  @update:model-value="
                    (val) => addRespostaAlteracao(select, val)
                  "
                />
              </template>

              <!-- Função -->
              <template v-else-if="select.tipo === 'funcao'">
                <MultiSelect
                  v-if="select.multiplo === 'S'"
                  class="!w-52"
                  :options="dadosWinthor.funcao"
                  option-value="value"
                  option-label="label"
                  placeholder="Selecione..."
                  filter
                  :loading="loadingWinthor.funcao"
                  @show="carregarDadosWinthor('funcao')"
                  @update:model-value="
                    (val) => addRespostaAlteracao(select, val)
                  "
                />
                <Select
                  v-else
                  class="!w-52"
                  :options="dadosWinthor.funcao"
                  option-value="value"
                  option-label="label"
                  placeholder="Selecione..."
                  show-clear
                  filter
                  :loading="loadingWinthor.funcao"
                  @show="carregarDadosWinthor('funcao')"
                  @update:model-value="
                    (val) => addRespostaAlteracao(select, val)
                  "
                />
              </template>

              <!-- Regional -->
              <template v-else-if="select.tipo === 'regional'">
                <MultiSelect
                  v-if="select.multiplo === 'S'"
                  class="!w-52"
                  :options="dadosWinthor.regional"
                  option-value="value"
                  option-label="label"
                  placeholder="Selecione..."
                  filter
                  :loading="loadingWinthor.regional"
                  @show="carregarDadosWinthor('regional')"
                  @update:model-value="
                    (val) => addRespostaAlteracao(select, val)
                  "
                />
                <Select
                  v-else
                  class="!w-52"
                  :options="dadosWinthor.regional"
                  option-value="value"
                  option-label="label"
                  placeholder="Selecione..."
                  show-clear
                  filter
                  :loading="loadingWinthor.regional"
                  @show="carregarDadosWinthor('regional')"
                  @update:model-value="
                    (val) => addRespostaAlteracao(select, val)
                  "
                />
              </template>

              <!-- CNPJ -->
              <InputMask
                v-else-if="select.tipo === 'cnpj'"
                class="!w-52"
                mask="99.999.999/9999-99"
                placeholder="00.000.000/0000-00"
                @update:model-value="(val) => addRespostaAlteracao(select, val)"
              />

              <InputText
                v-else-if="select.tipo === 'texto'"
                class="!w-52"
                placeholder="Digite aqui..."
                @update:model-value="(val) => addRespostaAlteracao(select, val)"
              />

              <InputNumber
                v-else-if="select.tipo === 'numero'"
                class="!w-52"
                :min="0"
                placeholder="Digite um número..."
                @update:model-value="(val) => addRespostaAlteracao(select, val)"
              />

              <DatePicker
                v-else-if="select.tipo === 'data'"
                :selection-mode="select.tipo_data"
                @update:model-value="(val) => addRespostaAlteracao(select, val)"
                show-icon
                date-format="dd/mm/yy"
                :min-date="calcularDataMinima(select)"
                :placeholder="
                  select.tipo_data === 'range'
                    ? 'Selecione período'
                    : 'Selecione data'
                "
                class="w-52"
              ></DatePicker>
              <small
                v-if="select.tipo === 'data' && select.dias_minimos"
                class="flex items-center text-xs text-amber-600 mt-1"
              >
                <i class="pi pi-info-circle text-xs mr-1"></i>
                Mínimo {{ select.dias_minimos }} dia(s) de antecedência
              </small>
            </div>
          </div>
        </div>

        <Button
          @click="dialogMotivoDepto = true"
          fluid
          class="!rounded-xl !h-11"
          severity="contrast"
          outlined
          :label="trocaAssunto ? 'Trocar Assunto' : 'Definir Departamento'"
          icon="pi pi-arrow-right"
          icon-pos="right"
          :disabled="loading"
        />
      </div>
    </div>
  </Dialog>

  <Dialog
    v-model:visible="dialogTrocarResponsavel"
    modal
    position="top"
    :closable="false"
    :pt="{
      root: {
        class:
          '!border-0 !rounded-2xl overflow-hidden w-[95vw] sm:w-[420px] !bg-transparent !shadow-none'
      },
      header: { class: 'hidden' },
      content: { class: '!p-0' },
      mask: { class: 'backdrop-blur-sm' }
    }"
  >
    <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden">
      <!-- Header Personalizado -->
      <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-5 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
            >
              <i class="pi pi-user-plus text-white text-lg"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">Atribuir Responsável</h3>
              <p class="text-white/80 text-xs">
                Defina quem irá atender esta ticket
              </p>
            </div>
          </div>
          <Button
            @click="dialogTrocarResponsavel = false"
            icon="pi pi-times"
            rounded
            outlined
            severity="secondary"
            class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
          />
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="p-5 space-y-4">
        <div>
          <label
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            <i class="pi pi-user text-xs mr-1"></i>
            Selecione o Responsável
          </label>

          <!-- Modo externo: autocomplete livre (etapa com permitir_responsavel_externo) -->
          <template v-if="fluxoDados?.permitir_responsavel_externo">
            <Funcionario
              v-model="responsavelSelecionado"
              :retorna-objeto="false"
              placeholder="Buscar qualquer funcionário..."
            />
            <p class="flex items-center gap-1 text-[10px] text-orange-500 mt-1">
              <i class="pi pi-info-circle"></i>
              Esta etapa permite atribuir pessoa de fora do departamento
            </p>
          </template>

          <!-- Modo padrão: Select restrito ao departamento -->
          <Select
            v-else
            fluid
            v-model="responsavelSelecionado"
            placeholder="Escolha o novo responsável"
            show-clear
            :options="responsaveisComputedOption"
            option-value="matricula"
            option-label="nome"
            class="w-full"
          />
        </div>

        <Button
          @click="mudarResponsavel()"
          fluid
          class="!rounded-xl !h-11"
          severity="contrast"
          outlined
          label="Definir Responsável"
          icon="pi pi-check"
          :disabled="
            (solicitacao.usuario_responsavel
              ? solicitacao.usuario_responsavel.matricula
              : null) == responsavelSelecionado
          "
        />
      </div>
    </div>
  </Dialog>

  <Dialog
    v-model:visible="dialogAnexos"
    modal
    position="top"
    :closable="false"
    :pt="{
      root: {
        class:
          '!border-0 !rounded-2xl overflow-hidden w-[95vw] sm:w-[500px] !bg-transparent !shadow-none'
      },
      header: { class: 'hidden' },
      content: { class: '!p-0' },
      mask: { class: 'backdrop-blur-sm' }
    }"
  >
    <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden">
      <!-- Header Personalizado -->
      <div class="bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
            >
              <i class="pi pi-paperclip text-white text-lg"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">Adicionar Anexos</h3>
              <p class="text-white/80 text-xs">
                Selecione os arquivos para anexar
              </p>
            </div>
          </div>
          <Button
            @click="dialogAnexos = false"
            icon="pi pi-times"
            rounded
            outlined
            severity="secondary"
            class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
          />
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="p-5 flex flex-col gap-4">
        <div
          class="border-2 border-dashed border-gray-200 dark:border-slate-600 rounded-xl p-4 bg-gray-50 dark:bg-slate-700/50"
        >
          <FileInput2
            @atualizar-lista="atualizarAnexos"
            :isMultiple="true"
          />
        </div>

        <div class="flex justify-end gap-2">
          <Button
            @click="dialogAnexos = false"
            label="Cancelar"
            outlined
            severity="secondary"
            class="!rounded-xl"
          />
          <Button
            @click="dialogAnexos = false"
            :disabled="arquivosComentario.length == 0"
            icon="pi pi-check"
            class="!rounded-xl"
            severity="info"
            :label="'Anexar ' + arquivosComentario.length + ' arquivo(s)'"
          />
        </div>
      </div>
    </div>
  </Dialog>

  <Dialog
    v-model:visible="dialogDadosLiberacao"
    modal
    position="top"
    :closable="false"
    :pt="{
      root: {
        class:
          '!border-0 !rounded-2xl overflow-hidden w-[95vw] sm:w-[400px] !bg-transparent !shadow-none'
      },
      header: { class: 'hidden' },
      content: { class: '!p-0' },
      mask: { class: 'backdrop-blur-sm' }
    }"
  >
    <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden">
      <!-- Header Personalizado -->
      <div class="bg-gradient-to-r from-teal-500 to-emerald-600 px-5 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
            >
              <i class="pi pi-list text-white text-lg"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">
                {{
                  dadosLiberacaoSelecionado
                    ? dadosLiberacaoSelecionado.tipo
                    : "Dados"
                }}
              </h3>
              <p class="text-white/80 text-xs">Itens vinculados</p>
            </div>
          </div>
          <Button
            @click="dialogDadosLiberacao = false"
            icon="pi pi-times"
            rounded
            outlined
            severity="secondary"
            class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
          />
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="p-4">
        <div class="flex flex-col space-y-2 overflow-auto max-h-80">
          <div
            v-for="(dados, index) in dadosLiberacaoSelecionado.dados"
            :key="index"
            class="p-3 bg-gray-50 dark:bg-slate-700/50 border border-gray-100 dark:border-slate-600 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
          >
            <div class="flex items-center gap-2 text-sm">
              <span class="font-bold text-gray-800 dark:text-white">
                {{ dados.codigo }}
              </span>
              <span class="text-gray-400">-</span>
              <span class="text-gray-600 dark:text-gray-300">
                {{ dados.descricao }}
              </span>
            </div>
          </div>
        </div>

        <div
          class="mt-4 pt-3 border-t border-gray-100 dark:border-slate-600 flex justify-between items-center"
        >
          <span class="text-xs text-gray-500 dark:text-gray-400">
            Total de itens
          </span>
          <span
            class="text-sm font-bold text-gray-800 dark:text-white bg-gray-100 dark:bg-slate-700 px-3 py-1 rounded-full"
          >
            {{ dadosLiberacaoSelecionado.dados.length }}
          </span>
        </div>
      </div>
    </div>
  </Dialog>

  <Dialog
    v-model:visible="dialogoCaixaVenda"
    modal
    position="top"
    :closable="false"
    :pt="{
      root: {
        class:
          '!border-0 !rounded-2xl overflow-hidden w-[95vw] sm:w-[400px] !bg-transparent !shadow-none'
      },
      header: { class: 'hidden' },
      content: { class: '!p-0' },
      mask: { class: 'backdrop-blur-sm' }
    }"
  >
    <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden">
      <!-- Header Personalizado -->
      <div class="bg-gradient-to-r from-rose-500 to-pink-600 px-5 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
            >
              <i class="pi pi-shopping-cart text-white text-lg"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">
                Caixas com Pendências
              </h3>
              <p class="text-white/80 text-xs">Caixas que possuem pendências</p>
            </div>
          </div>
          <Button
            @click="dialogoCaixaVenda = false"
            icon="pi pi-times"
            rounded
            outlined
            severity="secondary"
            class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
          />
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="p-4">
        <div class="flex flex-wrap gap-2 overflow-auto max-h-80">
          <div
            v-for="(dados, index) in vendasPendentesSelecionada.caixas"
            :key="index"
            class="px-4 py-2 bg-gradient-to-r from-gray-50 to-slate-50 dark:from-slate-700 dark:to-slate-600 border border-gray-100 dark:border-slate-600 rounded-xl font-medium text-gray-700 dark:text-gray-200 text-sm hover:shadow-md transition-shadow"
          >
            {{ dados }}
          </div>
        </div>

        <div
          class="mt-4 pt-3 border-t border-gray-100 dark:border-slate-600 flex justify-between items-center"
        >
          <span class="text-xs text-gray-500 dark:text-gray-400">
            Total de caixas
          </span>
          <span
            class="text-sm font-bold text-rose-700 dark:text-rose-400 bg-rose-100 dark:bg-rose-900/30 px-3 py-1 rounded-full"
          >
            {{ vendasPendentesSelecionada.caixas.length }}
          </span>
        </div>
      </div>
    </div>
  </Dialog>

  <Dialog
    v-model:visible="dialogMotivoDepto"
    modal
    position="top"
    :closable="false"
    :pt="{
      root: {
        class:
          '!border-0 !rounded-2xl overflow-hidden w-[95vw] sm:w-[420px] !bg-transparent !shadow-none'
      },
      header: { class: 'hidden' },
      content: { class: '!p-0' },
      mask: { class: 'backdrop-blur-sm' }
    }"
  >
    <div
      class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden relative"
    >
      <!-- Overlay de Loading -->
      <div
        v-if="loading"
        class="absolute inset-0 bg-white/70 dark:bg-slate-800/70 z-50 flex items-center justify-center rounded-2xl"
      >
        <div class="flex flex-col items-center gap-3">
          <i class="pi pi-spin pi-spinner text-3xl text-orange-500"></i>
          <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
            Processando...
          </span>
        </div>
      </div>

      <!-- Header Personalizado -->
      <div class="bg-gradient-to-r from-amber-500 to-orange-600 px-5 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
            >
              <i class="pi pi-building text-white text-lg"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">
                {{ trocaAssunto ? "Trocar Assunto" : "Alterar Departamento" }}
              </h3>
              <p class="text-white/80 text-xs">Informe o motivo da alteração</p>
            </div>
          </div>
          <Button
            @click="dialogMotivoDepto = false"
            icon="pi pi-times"
            rounded
            outlined
            severity="secondary"
            class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
            :disabled="loading"
          />
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="p-5 space-y-4">
        <div>
          <label
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            <i class="pi pi-comment text-xs mr-1"></i>
            {{
              trocaAssunto
                ? "Motivo da alteração do assunto"
                : "Motivo da alteração do departamento"
            }}
          </label>
          <Textarea
            rows="6"
            v-model="comentarioMotivo"
            placeholder="Descreva o motivo da alteração..."
            class="w-full !rounded-xl"
            auto-resize
          />
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <span
              :class="
                comentarioMotivo.length >= 10
                  ? 'text-emerald-600'
                  : 'text-orange-500'
              "
            >
              {{ comentarioMotivo.length }}
            </span>
            / mínimo 10 caracteres
          </p>
        </div>

        <Button
          severity="success"
          icon="pi pi-check"
          @click="alterarDepto()"
          :label="trocaAssunto ? 'Confirmar Troca' : 'Confirmar Alteração'"
          fluid
          outlined
          class="!rounded-xl !h-11"
          :disabled="comentarioMotivo.length < 10 || loading"
          :loading="loading"
        />
      </div>
    </div>
  </Dialog>

  <Dialog
    v-model:visible="dialogRecusar"
    modal
    position="top"
    :closable="false"
    :pt="{
      root: {
        class:
          '!border-0 !rounded-2xl overflow-hidden w-[95vw] sm:w-[420px] !bg-transparent !shadow-none'
      },
      header: { class: 'hidden' },
      content: { class: '!p-0' },
      mask: { class: 'backdrop-blur-sm' }
    }"
  >
    <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden">
      <!-- Header Personalizado -->
      <div class="bg-gradient-to-r from-red-500 to-rose-600 px-5 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
            >
              <i class="pi pi-times-circle text-white text-lg"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">Recusar Ticket</h3>
              <p class="text-white/80 text-xs">Informe o motivo da recusa</p>
            </div>
          </div>
          <Button
            @click="dialogRecusar = false"
            icon="pi pi-times"
            rounded
            outlined
            severity="secondary"
            class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
          />
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="p-5 space-y-4">
        <div>
          <label
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            <i class="pi pi-comment text-xs mr-1"></i>
            Explicar Recusa
          </label>
          <Textarea
            rows="6"
            v-model="comentario"
            placeholder="Descreva o motivo da recusa..."
            class="w-full !rounded-xl"
            auto-resize
          />
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <span
              :class="
                comentario.length >= 20 ? 'text-emerald-600' : 'text-red-500'
              "
            >
              {{ comentario.length }}
            </span>
            / mínimo 20 caracteres
          </p>
        </div>

        <Button
          severity="danger"
          icon="pi pi-times"
          @click="recusarAtendimento()"
          label="Confirmar Recusa"
          fluid
          outlined
          class="!rounded-xl !h-11"
          :disabled="comentario.length < 20"
        />
      </div>
    </div>
  </Dialog>

  <Dialog
    v-model:visible="dialogoCancelar"
    modal
    position="top"
    :closable="false"
    :pt="{
      root: {
        class:
          '!border-0 !rounded-2xl overflow-hidden w-[95vw] sm:w-[420px] !bg-transparent !shadow-none'
      },
      header: { class: 'hidden' },
      content: { class: '!p-0' },
      mask: { class: 'backdrop-blur-sm' }
    }"
  >
    <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden">
      <!-- Header Personalizado -->
      <div class="bg-gradient-to-r from-gray-600 to-slate-700 px-5 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
            >
              <i class="pi pi-ban text-white text-lg"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">Cancelar Ticket</h3>
              <p class="text-white/80 text-xs">
                Esta ação não pode ser desfeita
              </p>
            </div>
          </div>
          <Button
            @click="dialogoCancelar = false"
            icon="pi pi-times"
            rounded
            outlined
            severity="secondary"
            class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
          />
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="p-5 space-y-4">
        <!-- Aviso -->
        <div
          class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl"
        >
          <div class="flex items-start gap-2">
            <i class="pi pi-exclamation-triangle text-amber-500 mt-0.5"></i>
            <p class="text-xs text-amber-700 dark:text-amber-400">
              Após cancelada, a ticket não poderá ser reaberta.
            </p>
          </div>
        </div>

        <div>
          <label
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            <i class="pi pi-comment text-xs mr-1"></i>
            Motivo do Cancelamento
          </label>
          <Textarea
            rows="6"
            v-model="comentario"
            placeholder="Descreva o motivo do cancelamento..."
            class="w-full !rounded-xl"
          />
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <span
              :class="
                comentario.length >= 20 ? 'text-emerald-600' : 'text-orange-500'
              "
            >
              {{ comentario.length }}
            </span>
            / mínimo 20 caracteres
          </p>
        </div>

        <Button
          severity="danger"
          icon="pi pi-times"
          @click="cancelarAtendimento()"
          label="Confirmar Cancelamento"
          outlined
          fluid
          class="!rounded-xl !h-11"
          :disabled="comentario.length < 20"
        />
      </div>
    </div>
  </Dialog>

  <Dialog
    v-model:visible="dialogDetalhes"
    modal
    :closable="false"
    :showHeader="false"
    class="!w-[95vw] sm:!w-[450px] !h-auto sm:!max-h-[90vh]"
    :pt="{
      root: { class: '!rounded-2xl !overflow-hidden !border-0' },
      mask: { class: 'backdrop-blur-sm' },
      content: { class: '!p-0 !max-h-[85vh] !overflow-y-auto' }
    }"
  >
    <Detalhes
      :agendamento="agendamentoSelecionado"
      :usuarioLogado="solicitacao.usuarioLogado"
      :permissoes="props.permissoes"
      :auth="props.auth"
      @fechar="dialogDetalhes = false"
      @update:loading="(valor) => (loading = valor)"
      @acao:iniciar="atualizaAgendamento"
      @acao:finalizar="atualizaAgendamento"
      @acao:atualizar="updateDetalhes"
    />
  </Dialog>

  <!-- Dialog Simples para Detalhes do Lembrete -->
  <Dialog
    v-model:visible="dialogDetalhesLembrete"
    modal
    :closable="false"
    :showHeader="false"
    class="!w-[95vw] sm:!w-[400px]"
    :pt="{
      root: { class: '!rounded-2xl !overflow-hidden !border-0 !shadow-2xl' },
      mask: { class: 'backdrop-blur-sm' },
      content: { class: '!p-0' }
    }"
  >
    <!-- Header -->
    <div class="bg-gradient-to-r from-amber-400 to-orange-500 px-5 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
          >
            <i class="pi pi-bookmark text-white text-lg"></i>
          </div>
          <div>
            <h2 class="text-lg font-bold text-white">Lembrete</h2>
            <p class="text-xs text-white/70">Detalhes do lembrete</p>
          </div>
        </div>
        <button
          @click="dialogDetalhesLembrete = false"
          class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center transition-all"
        >
          <i class="pi pi-times text-white"></i>
        </button>
      </div>
    </div>

    <!-- Conteúdo -->
    <div class="p-5 space-y-4 bg-white dark:bg-slate-800">
      <!-- Data e Hora -->
      <div class="flex gap-3">
        <div
          class="flex-1 bg-amber-50 dark:bg-amber-900/20 rounded-xl p-3 border border-amber-200 dark:border-amber-800"
        >
          <div
            class="flex items-center gap-2 text-amber-600 dark:text-amber-400 mb-1"
          >
            <i class="pi pi-calendar text-sm"></i>
            <span class="text-xs font-semibold uppercase">Data</span>
          </div>
          <p class="text-sm font-bold text-gray-800 dark:text-white">
            {{
              agendamentoSelecionado?.data_agendamento
                ?.split(" ")[0]
                ?.split("-")
                .reverse()
                .join("/")
            }}
          </p>
        </div>
        <div
          class="flex-1 bg-orange-50 dark:bg-orange-900/20 rounded-xl p-3 border border-orange-200 dark:border-orange-800"
        >
          <div
            class="flex items-center gap-2 text-orange-600 dark:text-orange-400 mb-1"
          >
            <i class="pi pi-clock text-sm"></i>
            <span class="text-xs font-semibold uppercase">Hora</span>
          </div>
          <p class="text-sm font-bold text-gray-800 dark:text-white">
            {{
              agendamentoSelecionado?.data_agendamento
                ?.split(" ")[1]
                ?.substring(0, 5) || "Não definida"
            }}
          </p>
        </div>
      </div>

      <!-- Responsável -->
      <div
        class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-3 border border-gray-200 dark:border-slate-600"
      >
        <div
          class="flex items-center gap-2 text-gray-500 dark:text-gray-400 mb-1"
        >
          <i class="pi pi-user text-sm"></i>
          <span class="text-xs font-semibold uppercase">Responsável</span>
        </div>
        <p class="text-sm font-medium text-gray-800 dark:text-white">
          {{
            agendamentoSelecionado?.nomeResponsavel ||
            agendamentoSelecionado?.mat_responsavel
          }}
        </p>
      </div>

      <!-- Observação -->
      <div
        v-if="agendamentoSelecionado?.observacao"
        class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-3 border border-yellow-200 dark:border-yellow-800"
      >
        <div
          class="flex items-center gap-2 text-yellow-600 dark:text-yellow-400 mb-1"
        >
          <i class="pi pi-info-circle text-sm"></i>
          <span class="text-xs font-semibold uppercase">Observação</span>
        </div>
        <p class="text-sm text-gray-700 dark:text-gray-200">
          {{ agendamentoSelecionado?.observacao }}
        </p>
      </div>

      <!-- Status -->
      <div class="flex items-center justify-center">
        <span
          :class="[
            'px-4 py-2 rounded-full text-sm font-bold uppercase',
            agendamentoSelecionado?.status === 'aguardando'
              ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
              : agendamentoSelecionado?.status === 'cancelado'
                ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                : agendamentoSelecionado?.status === 'finalizado'
                  ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                  : 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-300'
          ]"
        >
          {{ agendamentoSelecionado?.status }}
        </span>
      </div>

      <!-- Botão Cancelar Lembrete -->
      <div
        v-if="
          agendamentoSelecionado?.status === 'aguardando' &&
          solicitacao.isDepartamento
        "
        class="pt-2 flex flex-col gap-2"
      >
        <Button
          @click="editarLembrete"
          label="Editar Lembrete"
          icon="pi pi-pencil"
          severity="warning"
          outlined
          fluid
          class="!rounded-xl"
        />
        <Button
          @click="cancelarLembreteEFechar"
          label="Cancelar Lembrete"
          icon="pi pi-ban"
          severity="danger"
          outlined
          fluid
          class="!rounded-xl"
        />
      </div>
    </div>
  </Dialog>

  <Dialog
    v-model:visible="dialogAgendamento"
    modal
    header="Agendamento"
    class="w-[350px] sm:w-auto max-w-7xl"
  >
    <Agendamento
      @atualizar="atualizaAgendamentos"
      :edit="editAgendamento"
      :agendamentoEdit="
        agendamentos.filter(
          (item) =>
            item.status != 'finalizado' &&
            item.status != 'cancelado' &&
            item.tipo != 'lembrete'
        )
      "
      :solicitacoes="[solicitacao]"
      :solicitacaoAtual="solicitacao"
    ></Agendamento>
  </Dialog>

  <!-- Dialog para Criar Lembrete -->
  <Dialog
    v-model:visible="dialogLembrete"
    modal
    :closable="true"
    :showHeader="false"
    class="!w-[95vw] sm:!w-[450px]"
    :pt="{
      root: { class: '!rounded-2xl !overflow-hidden !border-0 !shadow-2xl' },
      mask: { class: 'backdrop-blur-sm' },
      content: { class: '!p-0 !bg-white dark:!bg-slate-800' }
    }"
  >
    <!-- Header Customizado -->
    <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-5 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div
            class="w-12 h-12 bg-white/20 shrink-0 rounded-xl flex items-center justify-center shadow-lg"
          >
            <i class="pi pi-bookmark text-white text-xl"></i>
          </div>
          <div>
            <h2 class="text-xl font-bold text-white">
              {{ lembreteEdicao ? "Editar Lembrete" : "Criar Lembrete" }}
            </h2>
            <p class="text-sm text-white/70">
              {{
                lembreteEdicao
                  ? "Altere os dados do lembrete"
                  : "Agende uma data para atender esta ticket"
              }}
            </p>
          </div>
        </div>
        <button
          @click="dialogLembrete = false"
          class="w-10 h-10 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center transition-all duration-200 group"
        >
          <i
            class="pi pi-times text-white group-hover:scale-110 transition-transform"
          ></i>
        </button>
      </div>
    </div>

    <!-- Conteúdo -->
    <div class="p-5">
      <AgendamentoLembrete
        :solicitacao="solicitacao"
        :lembrete-edicao="lembreteEdicao"
        @atualizar="atualizaLembrete"
        @fechar="dialogLembrete = false"
      />
    </div>
  </Dialog>

  <Dialog
    v-model:visible="dialogImagem"
    modal
    :close-on-escape="false"
    header="Pré-visualização"
  >
    <Image
      class="ipad:w-96"
      :src="imagemSelecionada.external_link"
      preview
    ></Image>
  </Dialog>

  <!-- DIALOG PARA DEVOLVER AO SOLICITANTE -->
  <Dialog
    v-model:visible="dialogRetorno"
    modal
    position="top"
    :closable="false"
    :pt="{
      root: {
        class:
          '!border-0 !rounded-2xl overflow-hidden w-[95vw] sm:w-[420px] !bg-transparent !shadow-none'
      },
      header: { class: 'hidden' },
      content: { class: '!p-0' },
      mask: { class: 'backdrop-blur-sm' }
    }"
  >
    <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden">
      <!-- Header Personalizado -->
      <div class="bg-gradient-to-r from-amber-500 to-yellow-600 px-5 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
            >
              <i class="pi pi-replay text-white text-lg"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">
                Retorno ao Solicitante
              </h3>
              <p class="text-white/80 text-xs">
                Devolver ticket para esclarecimentos
              </p>
            </div>
          </div>
          <Button
            @click="dialogRetorno = false"
            icon="pi pi-times"
            rounded
            outlined
            severity="secondary"
            class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
          />
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="p-5 space-y-4">
        <!-- Aviso -->
        <div
          class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl"
        >
          <div class="flex items-start gap-2">
            <i class="pi pi-info-circle text-amber-500 mt-0.5"></i>
            <p class="text-xs text-amber-700 dark:text-amber-400">
              O solicitante receberá uma notificação para complementar as
              informações.
            </p>
          </div>
        </div>

        <div>
          <label
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
          >
            <i class="pi pi-comment text-xs mr-1"></i>
            Explique o que precisa ser esclarecido
          </label>
          <Textarea
            rows="6"
            v-model="comentarioDevolucao"
            placeholder="Descreva o que o solicitante precisa informar..."
            class="w-full !rounded-xl"
            auto-resize
          />
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            <span
              :class="
                comentarioDevolucao.length >= 20
                  ? 'text-emerald-600'
                  : 'text-orange-500'
              "
            >
              {{ comentarioDevolucao.length }}
            </span>
            / mínimo 20 caracteres
          </p>
        </div>

        <Button
          severity="warning"
          icon="pi pi-undo"
          @click="RetornarSolicitacao()"
          label="Devolver ao Solicitante"
          fluid
          class="!rounded-xl !h-11"
          outlined
          :disabled="comentarioDevolucao.length < 20"
        />
      </div>
    </div>
  </Dialog>
  <Dialog
    v-model:visible="showAtencaoRetorno"
    modal
    :closable="false"
    class="w-[370px] rounded-xl shadow-2xl border-0 bg-gradient-to-br from-orange-50 to-white"
    style="box-shadow: 0 8px 32px 0 rgba(255, 140, 0, 0.15)"
    position="top"
  >
    <template #container>
      <div
        class="flex flex-col items-center justify-center p-6 rounded-2xl overflow-hidden space-y-4 bg-gradient-to-br from-orange-100/80 to-white"
      >
        <div class="flex items-center justify-center">
          <i
            class="text-5xl text-orange-500 fas fa-exclamation-triangle drop-shadow-lg"
          />
        </div>
        <span class="text-lg font-semibold text-center text-orange-700">
          Esta ticket foi retornada para você
        </span>
        <span class="text-sm text-center text-gray-600">
          Para que o atendimento continue, informe os dados solicitados através
          de um novo comentário.
        </span>
        <div class="flex justify-end w-full pt-2">
          <Button
            @click="showAtencaoRetorno = false"
            label="Entendi"
            severity="warning"
            class="!bg-orange-500 !border-orange-500 !text-white px-6 py-2 shadow-md hover:!bg-orange-600 transition-all"
            icon="pi pi-check"
          ></Button>
        </div>
      </div>
    </template>
  </Dialog>

  <!-- Dialog para enviar arquivo para dossiê -->
  <Dialog
    v-model:visible="dialogEnviarDossie"
    header="Enviar para Dossiê"
    :style="{ width: '500px' }"
    modal
    :closable="!loadingDossie"
    position="top"
  >
    <div class="flex flex-col space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Colaborador
        </label>
        <Funcionario
          v-model="funcionarioSelecionadoDossie"
          :retorna-objeto="true"
          :apenas-clts="true"
          placeholder="Selecione um funcionário CLT..."
          @update:model-value="onFuncionarioChange"
        />
        <small class="text-gray-500">
          Apenas funcionários CLT podem ter dossiês
        </small>
      </div>

      <div v-if="funcionarioSelecionadoDossie">
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Pasta *
        </label>

        <!-- Seleção de Pasta -->
        <div class="mb-3">
          <select
            v-model="pastaSelecionadaDossie"
            class="w-full p-2 border border-gray-300 rounded-md text-sm"
            @change="onPastaChange"
          >
            <option value="">Selecione uma pasta...</option>
            <option
              v-for="pasta in configDossie.pastas"
              :key="pasta.id"
              :value="pasta"
            >
              {{ pasta.descricao }}
            </option>
          </select>
        </div>

        <!-- Seleção de Dossiê -->
        <div v-if="pastaSelecionadaDossie">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Dossiê *
          </label>
          <div class="flex space-x-2">
            <select
              v-model="listaSelecionadaDossie"
              class="flex-1 p-2 border border-gray-300 rounded-md text-sm"
            >
              <option
                v-for="lista in dossiesDaPasta"
                :key="lista.id"
                :value="lista"
              >
                {{ lista.descricao }}
              </option>
            </select>
            <Button
              @click="dialogNovoDossie = true"
              icon="pi pi-plus"
              title="Criar novo dossiê"
              size="small"
              severity="success"
            />
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end space-x-2">
        <Button
          @click="fecharDialogDossie"
          label="Cancelar"
          severity="secondary"
          :disabled="loadingDossie"
        />
        <Button
          @click="confirmarEnvioDossie"
          label="Enviar"
          outlined
          severity="success"
          :loading="loadingDossie"
          :disabled="
            !funcionarioSelecionadoDossie ||
            !pastaSelecionadaDossie ||
            !listaSelecionadaDossie
          "
        />
      </div>
    </template>
  </Dialog>

  <!-- Dialog para criar novo dossiê -->
  <Dialog
    v-model:visible="dialogNovoDossie"
    header="Criar Novo Dossiê"
    :style="{ width: '400px' }"
    modal
    :closable="!loadingDossie"
  >
    <div class="flex flex-col space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Descrição *
        </label>
        <InputText
          v-model="novoDossie.descricao"
          placeholder="Digite a descrição do dossiê..."
          class="w-full"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end space-x-2">
        <Button
          @click="cancelarNovoDossie"
          label="Cancelar"
          severity="secondary"
          :disabled="loadingDossie"
        />
        <Button
          @click="criarNovoDossie"
          label="Criar"
          severity="success"
          :loading="loadingDossie"
          :disabled="!novoDossie.descricao"
        />
      </div>
    </template>
  </Dialog>

  <ViewFiles
    v-model:visible="viewFileDialog"
    :arquivo-inicial-id="fileId"
    :lista-arquivos-id="listaIdsFile"
  />

  <Dialog
    v-model:visible="dialogResolver"
    modal
    header="Resolver Ticket"
    :style="{ width: '500px' }"
    position="top"
  >
    <div class="flex flex-col space-y-4">
      <!-- Explicação -->
      <div class="p-4 bg-blue-50 border-l-4 border-blue-500 rounded-md">
        <div class="flex items-start space-x-3">
          <i class="pi pi-info-circle text-blue-500 text-xl mt-1"></i>
          <div class="flex flex-col space-y-2 text-sm text-gray-700">
            <p class="font-semibold">Como deseja concluir esta ticket?</p>
          </div>
        </div>
      </div>

      <!-- Opção 1: Finalizar -->
      <div
        class="p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow"
      >
        <div class="flex items-start space-x-3 mb-3">
          <div class="flex-shrink-0 mt-1">
            <i class="pi pi-check-circle text-green-600 text-2xl"></i>
          </div>
          <div class="flex-1">
            <h3 class="font-semibold text-gray-900 mb-2">Finalizar Direto</h3>
            <p class="text-sm text-gray-600 mb-3">
              A ticket será
              <span class="font-semibold">concluída imediatamente</span>
              sem passar pela aprovação do solicitante. Use esta opção quando a
              resolução for óbvia ou já acordada previamente.
            </p>
            <Button
              label="Finalizar Agora"
              severity="success"
              @click="finalizar()"
              class="w-full"
              icon="pi pi-check"
            />
          </div>
        </div>
      </div>

      <!-- Opção 2: Resolver -->
      <div
        class="p-4 bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow"
      >
        <div class="flex items-start space-x-3 mb-3">
          <div class="flex-shrink-0 mt-1">
            <i class="pi pi-send text-blue-600 text-2xl"></i>
          </div>
          <div class="flex-1">
            <h3 class="font-semibold text-gray-900 mb-2">
              Enviar para Aprovação
            </h3>
            <p class="text-sm text-gray-600 mb-3">
              A ticket será marcada como
              <span class="font-semibold">resolvida</span>
              e enviada ao solicitante para confirmação. O solicitante poderá
              aceitar ou recusar a resolução.
            </p>
            <Button
              label="Enviar para Solicitante"
              severity="primary"
              @click="resolverAtendimento()"
              class="w-full"
              icon="pi pi-send"
            />
          </div>
        </div>
      </div>
    </div>
  </Dialog>

  <!-- Dialog: Atribuir responsável e avançar -->
  <Dialog
    v-model:visible="dialogAtribuirDev"
    modal
    header="Atribuir responsável e avançar"
    :style="{ width: '450px' }"
    position="top"
  >
    <div class="flex flex-col space-y-4">
      <p class="text-sm text-gray-600">
        Selecione o responsável que será atribuído à próxima etapa:
      </p>
      <Funcionario
        v-model="devSelecionado"
        :retorna-objeto="true"
        placeholder="Buscar responsável..."
      />
      <div class="flex justify-end gap-2 pt-2">
        <Button
          label="Cancelar"
          severity="secondary"
          @click="dialogAtribuirDev = false"
        />
        <Button
          label="Atribuir e Avançar"
          severity="primary"
          icon="pi pi-arrow-right"
          :disabled="!devSelecionado"
          :loading="loading"
          @click="confirmarAtribuirDev()"
        />
      </div>
    </div>
  </Dialog>

  <!-- Dialog: Campos vinculados à decisão -->
  <Dialog
    v-model:visible="dialogCamposDecisao"
    modal
    header="Preencher campos"
    :style="{ width: '500px' }"
    position="top"
  >
    <div class="flex flex-col space-y-4">
      <div
        v-for="campo in camposDecisaoAtual"
        :key="campo.id"
        class="flex flex-col gap-1"
      >
        <label class="text-xs font-medium text-gray-600 dark:text-gray-400">
          {{ campo.label }}
          <span
            v-if="campo.obrigatorio === 'S'"
            class="text-red-500"
          >
            *
          </span>
        </label>
        <InputText
          v-if="campo.tipo === 'texto'"
          v-model="valoresCamposFluxo[campo.id]"
          :placeholder="campo.placeholder || ''"
          size="small"
          class="w-full"
        />
        <Textarea
          v-else-if="campo.tipo === 'textarea'"
          v-model="valoresCamposFluxo[campo.id]"
          :placeholder="campo.placeholder || ''"
          rows="2"
          autoResize
          class="w-full text-sm !resize-none"
        />
        <InputNumber
          v-else-if="campo.tipo === 'numero'"
          v-model="valoresCamposFluxo[campo.id]"
          :placeholder="campo.placeholder || ''"
          size="small"
          class="w-full"
          :useGrouping="false"
        />
        <DatePicker
          v-else-if="campo.tipo === 'data'"
          v-model="valoresCamposFluxo[campo.id]"
          :placeholder="campo.placeholder || 'dd/mm/aaaa'"
          dateFormat="dd/mm/yy"
          size="small"
          fluid
          class="w-full"
        />
        <Select
          v-else-if="campo.tipo === 'selecao'"
          v-model="valoresCamposFluxo[campo.id]"
          :options="campo.opcoes || []"
          :placeholder="campo.placeholder || 'Selecione'"
          size="small"
          class="w-full"
        />
        <div
          v-else-if="campo.tipo === 'checkbox'"
          class="flex items-center gap-2 mt-1"
        >
          <input
            type="checkbox"
            :checked="valoresCamposFluxo[campo.id] === 'S'"
            @change="
              valoresCamposFluxo[campo.id] = $event.target.checked ? 'S' : 'N'
            "
            class="rounded border-gray-300 text-blue-500 focus:ring-blue-500"
          />
          <span class="text-xs text-gray-600 dark:text-gray-400">
            {{ campo.placeholder || "Sim" }}
          </span>
        </div>
        <!-- Arquivo -->
        <div
          v-else-if="campo.tipo === 'arquivo'"
          class="flex flex-col gap-1.5"
        >
          <div
            v-if="parseCampoArquivo(valoresCamposFluxo[campo.id])"
            class="flex items-center gap-2 px-2.5 py-1.5 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-lg"
          >
            <i class="pi pi-file text-blue-500 text-sm"></i>
            <button
              type="button"
              @click="visualizarCampoArquivo(valoresCamposFluxo[campo.id])"
              class="text-xs text-blue-600 hover:underline truncate flex-1 text-left cursor-pointer"
            >
              {{ parseCampoArquivo(valoresCamposFluxo[campo.id]).file_name }}
            </button>
            <button
              type="button"
              @click="removerCampoArquivo(campo.id)"
              class="text-red-400 hover:text-red-600 transition-colors flex-shrink-0"
              title="Remover arquivo"
            >
              <i class="pi pi-times text-xs"></i>
            </button>
          </div>
          <div
            v-else
            class="flex items-center gap-2"
          >
            <label
              :for="`campo-arquivo-decisao-${campo.id}`"
              class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg cursor-pointer transition-colors"
              :class="{
                'opacity-50 pointer-events-none':
                  uploadingCampoArquivo[campo.id]
              }"
            >
              <i class="pi pi-upload text-xs"></i>
              {{
                uploadingCampoArquivo[campo.id]
                  ? "Enviando..."
                  : "Selecionar arquivo"
              }}
            </label>
            <input
              :id="`campo-arquivo-decisao-${campo.id}`"
              type="file"
              :accept="CAMPO_ARQUIVO_ACCEPT"
              class="hidden"
              @change="handleCampoArquivoUpload($event, campo.id)"
            />
          </div>
        </div>
        <InputText
          v-else
          v-model="valoresCamposFluxo[campo.id]"
          :placeholder="campo.placeholder || ''"
          size="small"
          class="w-full"
        />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <Button
          label="Cancelar"
          severity="secondary"
          @click="dialogCamposDecisao = false"
        />
        <Button
          label="Confirmar"
          severity="primary"
          icon="pi pi-check"
          :loading="loading"
          @click="confirmarCamposDecisao()"
        />
      </div>
    </div>
  </Dialog>
</template>

<style scoped>
/* Botões de decisão do fluxo — hover respeita a cor da decisão */
.decisao-btn:hover {
  background-color: var(--decisao-cor) !important;
  border-color: var(--decisao-cor) !important;
  color: #fff !important;
}
</style>
