<script setup>
import Loader from "@/Components/Loader.vue"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import Solicitacao from "@/Pages/Solicitacoes/Solicitação.vue"
import CardAprovacao from "./Components/CardAprovacao.vue"
import {
  formatarData,
  formatarDataSemHoras,
  swalErro,
  swalObservacao,
  swalConfirm,
  toastSuccess,
  toastError,
  tratarNome
} from "@/utils/globalFunctions"
import { Head, usePage } from "@inertiajs/vue3"
import {
  DatePicker,
  InputText,
  Select,
  MultiSelect,
  Checkbox,
  Button,
  DataTable,
  Column,
  Tag,
  Dialog
} from "primevue"
import { onMounted, ref, computed, onUnmounted, watch, Transition } from "vue"
import BsCheckBox from "@/Components/Componentes/BsCheckBox.vue"
import BsButton from "@/Components/Componentes/BsButton.vue"
import Agendamento from "../Agendamento.vue"
import BsIcone from "@/Components/Componentes/BsIcone.vue"
import Paginacao from "@/Components/Componentes/Paginacao.vue"
import Funcionario from "@/Components/Componentes/Funcionario.vue"
import { useUserPreferences } from "@/composables/useUserPreferences"
import { useSolicitacoesEcho } from "@/composables/useSolicitacoesEcho"
import * as layout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"

// ✅ Marcar como página nova IMEDIATAMENTE (antes de qualquer requisição)
layout.paginaNova.value = true

const page = usePage()
const userPreferences = useUserPreferences()
const { escutarDepartamento, escutarUsuario, desconectarTodos } =
  useSolicitacoesEcho()

// Referência para o canal ativo do departamento
let canalDepartamento = null
let canalUsuario = null

// Chaves de preferências
const PREF_KEYS = {
  filtroAtendimento: "solicitacoes.lista.filtroAtendimento",
  filtroMinhas: "solicitacoes.lista.filtroMinhas",
  situacoesTodasAtendimento: "solicitacoes.lista.situacoesTodasAtendimento",
  situacoesTodasMinhas: "solicitacoes.lista.situacoesTodasMinhas",
  visualizacaoTabela: "solicitacoes.lista.visualizacaoTabela",
  abaSelecionada: "solicitacoes.lista.abaSelecionada",
  mostrarIconesTabela: "solicitacoes.lista.mostrarIconesTabela"
}

const props = defineProps([
  "filiais",
  "departamentos",
  "usuarioLogado",
  "auth",
  "permissoes",
  "inputsMinhas"
])

const loading = ref(false)
const loadingInicial = ref(true) // Loading enquanto carrega configurações
const solicitações = ref(null)
const contagemCards = ref(null) // Contagem fixa para os cards (não muda com filtro por card)
const filtrandoPorCard = ref(false) // Flag para saber se o filtro veio de um clique no card
const totalRegistros = ref(0) // Total para paginação
const dialogSolicitacao = ref(false)
const solicitacaoSelecionada = ref(null)
const origemFuncionario = ref("")
const dialogFuncionario = ref(false)
const termoFuncionario = ref("")
const listaFuncionarios = ref([])
const showFiltros = ref(false)
const dialogAgendamento = ref(false)
const solicitacoesAgend = ref([])
const dialogConfig = ref(false)
const mostrarIconesTabela = ref(true) // Controla se exibe ícones decorativos nas colunas do DataTable
const colunasTemp = ref([]) // Cópia temporária para o dialog (evita re-render do DataTable)
const mostrarIconesTabelaTemp = ref(true)
const cardsExpandidos = ref(new Set()) // Controla quais cards mobile estão expandidos

// Função para toggle do card expandido
function toggleCardExpandido(id) {
  if (cardsExpandidos.value.has(id)) {
    cardsExpandidos.value.delete(id)
  } else {
    cardsExpandidos.value.add(id)
  }
  // Força reatividade
  cardsExpandidos.value = new Set(cardsExpandidos.value)
}

// Colunas padrão - será mesclado com servidor e cache
const colunasDefault = [
  { coluna: "id", ativarColuna: true },
  { coluna: "titulo", ativarColuna: true },
  { coluna: "prioridade", ativarColuna: true },
  { coluna: "assunto_id", ativarColuna: true },
  { coluna: "etapa_atual", ativarColuna: true },
  { coluna: "departamento_responsavel", ativarColuna: true },
  { coluna: "usuario_solicitante", ativarColuna: true },
  { coluna: "usuario_responsavel", ativarColuna: true },
  { coluna: "filial_id", ativarColuna: true },
  { coluna: "filial_uf", ativarColuna: false },
  { coluna: "filial_cidade", ativarColuna: false },
  { coluna: "status", ativarColuna: true },
  { coluna: "created_at", ativarColuna: true },
  { coluna: "previsao_entrega", ativarColuna: false },
  { coluna: "descricao", ativarColuna: false },
  { coluna: "dias_aberto", ativarColuna: false },

  { coluna: "updated_at", ativarColuna: false },
  { coluna: "usuarios_destino", ativarColuna: true },
  { coluna: "usuario_origem", ativarColuna: true },
  { coluna: "solicitacao_pai_id", ativarColuna: true }
]
const colunas = ref([...colunasDefault])
const colunasCarregadas = ref(false) // Flag para controlar se já carregou do cache
const successMessage = ref(null)
const toast = ref(false)
const optionsFiliais = ref([])
const cacheMarcadas = ref([])

// Computed: lista de UFs distintas a partir das filiais carregadas
const optionsUfs = computed(() => {
  const ufs = [
    ...new Set(optionsFiliais.value.map((f) => f.uf).filter(Boolean))
  ]
  return ufs.sort().map((uf) => ({ name: uf, value: uf }))
})

// Computed: lista de Cidades filtradas pela UF selecionada (cascata)
const optionsCidades = computed(() => {
  let filiais = optionsFiliais.value
  if (filtro.value.ufs && filtro.value.ufs.length > 0) {
    filiais = filiais.filter((f) => filtro.value.ufs.includes(f.uf))
  }
  const cidades = [...new Set(filiais.map((f) => f.cidade).filter(Boolean))]
  return cidades.sort().map((c) => ({ name: c, value: c }))
})

// Colunas internas que não devem ser exibidas ao usuário
const colunasOcultas = ["existe_acoes", "hash_duplicata", "data_conclusao"]

// Colunas visíveis ordenadas para renderização dinâmica
const colunasVisiveis = computed(() => {
  return colunas.value.filter(
    (col) => col.ativarColuna && !colunasOcultas.includes(col.coluna)
  )
})

// Helpers para colunas dinâmicas
function getColumnField(coluna) {
  const fieldMap = {
    assunto_id: "assunto.assunto",
    usuario_solicitante: "usuario_solicitante.nome",
    usuario_responsavel: "usuario_responsavel.nome",
    filial_id: "filial.fantasia",
    filial_uf: "filial.uf",
    filial_cidade: "filial.cidade",
    usuario_origem: "usuario_origem.nome"
  }
  return fieldMap[coluna] || coluna
}

function getColumnHeader(coluna) {
  const headerMap = {
    id: "ID",
    titulo: "Título",
    descricao: "Descrição",
    prioridade: "Prioridade",
    departamento_responsavel: "Departamento",
    usuario_responsavel: "Responsável",
    assunto_id: "Assunto",
    etapa_atual: "Etapa Andamento",
    usuario_solicitante: "Solicitante",
    usuarios_destino: "Destino",
    usuario_origem: "Usuário Origem",
    solicitacao_pai_id: "Sol. Pai",
    filial_id: "Filial",
    filial_uf: "UF",
    filial_cidade: "Cidade",
    created_at: "Data Criação",
    dias_aberto: "Dias Aberto",

    updated_at: "Última Atualização",
    status: "Situação",
    previsao_entrega: "Previsão de Entrega"
  }
  return headerMap[coluna] || coluna
}

function getColumnStyle(coluna) {
  const styleMap = {
    id: "min-width: 90px",
    titulo: "min-width: 200px; max-width: 300px",
    descricao: "min-width: 200px; max-width: 300px",
    prioridade: "min-width: 110px",
    departamento_responsavel: "min-width: 130px",
    usuario_responsavel: "min-width: 170px",
    assunto_id: "min-width: 150px",
    etapa_atual: "min-width: 160px",
    usuario_solicitante: "min-width: 170px",
    usuarios_destino: "min-width: 150px",
    usuario_origem: "min-width: 150px",
    solicitacao_pai_id: "min-width: 100px",
    filial_id: "min-width: 120px",
    filial_uf: "min-width: 60px",
    filial_cidade: "min-width: 120px",
    created_at: "min-width: 140px",
    dias_aberto: "min-width: 110px",

    updated_at: "min-width: 160px",
    status: "min-width: 130px",
    previsao_entrega: "min-width: 150px"
  }
  return styleMap[coluna] || ""
}

function getColumnClass(coluna) {
  const classMap = {
    dias_aberto: "whitespace-nowrap",

    updated_at: "whitespace-nowrap",
    previsao_entrega: "whitespace-nowrap",
    usuario_origem: "whitespace-nowrap",
    solicitacao_pai_id: "whitespace-nowrap"
  }
  return classMap[coluna] || ""
}

function isColumnSortable(coluna) {
  // Colunas que não são sortable
  const notSortable = ["descricao", "usuarios_destino", "etapa_atual"]
  return !notSortable.includes(coluna)
}

// Salvar ordem das colunas quando arrastar no DataTable
function onColumnReorder(event) {
  let { dragIndex, dropIndex } = event

  // Compensar a coluna de checkbox (se visível, ela é a primeira coluna)
  const temColunaCheckbox =
    validaPermissao("solicitacoes.lista.criar-agendamento") &&
    aba.value == "atendimento"
  if (temColunaCheckbox) {
    dragIndex = dragIndex - 1
    dropIndex = dropIndex - 1
  }

  // Pegar apenas colunas visíveis para reordenar
  const visiveisAtual = colunas.value.filter((c) => c.ativarColuna)
  const colunasOcultas = colunas.value.filter((c) => !c.ativarColuna)

  // Validar índices
  if (dragIndex < 0 || dropIndex < 0 || dragIndex >= visiveisAtual.length) {
    return
  }

  // Reordenar as colunas visíveis
  const [movedColumn] = visiveisAtual.splice(dragIndex, 1)
  visiveisAtual.splice(dropIndex, 0, movedColumn)

  // Reconstruir array: visíveis reordenadas + ocultas no final
  colunas.value = [...visiveisAtual, ...colunasOcultas]

  // Salvar automaticamente
  userPreferences.set(PREF_KEYS.visualizacaoTabela, colunas.value)
}

// Variáveis para aba de aprovações
const aprovacoesPendentes = ref([])
const loadingAprovacoes = ref(false)
const totalAprovacoesPendentes = ref(0)
const aba = ref("atendimento") // Página de Atendimento
const isResolver = ref(false)
const filtro = ref({
  id: null,
  prioridades: [],
  responsavel: [],
  solicitante: null,
  departamento: null,
  filiais: [],
  ufs: [],
  cidades: [],
  assuntos: [],
  situacoes: [],
  dataIni: null,
  dataFim: null,
  dataAltIni: null,
  dataAltFim: null,
  porPagina: 10,
  pagina: 1,
  isResponsavel: false,
  ordenacao: [
    {
      field: "id",
      order: -1
    }
  ]
})

// Situações disponíveis - Remove Finalizada/Cancelada na aba de Atendimento
const optionsSituacoes = computed(() => {
  const todasSituacoes = [
    { name: "Pendente", value: "pendente" },
    { name: "Em Atendimento", value: "em atendimento" },
    { name: "Pausado", value: "atendimento pausado" },
    { name: "Agendado", value: "agendado" },
    { name: "Resolvida", value: "resolvida" },
    { name: "Recusada", value: "resolução recusada" },
    { name: "Finalizada", value: "finalizada" },
    { name: "Cancelada", value: "cancelada" },
    { name: "Retorno Solicitante", value: "retorno solicitante" },
    { name: "Atrasadas", value: "atrasadas" }
  ]

  // Na aba de atendimento, não mostrar Finalizada e Cancelada
  if (aba.value === "atendimento") {
    return todasSituacoes.filter(
      (s) => s.value !== "finalizada" && s.value !== "cancelada"
    )
  }

  return todasSituacoes
})

const optionsAssunto = computed(() => {
  if (
    filtro.value.departamento &&
    filtro.value.departamento.assuntos &&
    Array.isArray(filtro.value.departamento.assuntos)
  ) {
    // Filtrar assuntos baseado nos responsáveis configurados
    // Se um assunto tem responsáveis, só mostrar se o usuário logado for um deles
    const matriculaLogado = props.usuarioLogado?.matricula

    const assuntosFiltrados = filtro.value.departamento.assuntos.filter(
      (assunto) => {
        // Se não tem responsáveis configurados, todos podem ver
        if (!assunto.responsaveis || assunto.responsaveis.length === 0) {
          return true
        }

        // Se tem responsáveis, verificar se o usuário logado está na lista
        return assunto.responsaveis.some(
          (resp) => resp.matricula == matriculaLogado
        )
      }
    )

    // Adicionar opção "TRANSFERIDO" para solicitações sem assunto
    return [...assuntosFiltrados, { id: null, assunto: "TRANSFERIDO" }]
  }
  return []
})

// Computed para verificar se busca por ID está ativa (desabilita outros filtros)
const buscaPorIdAtiva = computed(() => {
  return (
    filtro.value.id !== null &&
    filtro.value.id !== "" &&
    filtro.value.id !== undefined
  )
})

// ✅ Watcher para reconectar ao Reverb quando mudar o departamento
watch(
  () => filtro.value.departamento?.condicao1,
  (novoDepartamento, antigoDepartamento) => {
    if (novoDepartamento && novoDepartamento !== antigoDepartamento) {
      iniciarReverbListener()
    }
  }
)

onMounted(async () => {
  // ✅ Buscar filiais PRIMEIRO para poder deserializar o filtro corretamente
  await buscarFiliais()

  // ✅ Buscar preferências de filtro
  const filtroSalvo = await userPreferences.get(PREF_KEYS.filtroAtendimento)

  // ✅ Criar contexto para deserialização com todos os dados necessários
  const contextoDeserializacao = {
    departamentos: props.departamentos,
    filiais: optionsFiliais.value,
    matriculaLogado: props.usuarioLogado?.matricula,
    areaatuacaoLogado: props.usuarioLogado?.areaatuacao
  }

  // ✅ Deserializar filtro salvo (rehidrata com dados atuais)
  const filtroRehidratado = userPreferences.deserializarFiltro(
    filtroSalvo,
    contextoDeserializacao
  )

  if (filtroRehidratado) {
    filtro.value = filtroRehidratado
  } else {
    // Se não tem filtro salvo, usar departamento do usuário ou o primeiro
    const departamentoUsuario = props.departamentos.find(
      (d) => d.condicao1 === props.usuarioLogado?.areaatuacao
    )
    filtro.value.departamento = departamentoUsuario || props.departamentos[0]
  }

  const query = new URLSearchParams(window.location.search)
  if (query.has("solicitacao")) {
    solicitacaoSelecionada.value = { id: query.get("solicitacao") }
    dialogSolicitacao.value = true
  }

  // ✅ Buscar solicitações e aprovações em PARALELO
  await Promise.all([getSolicitacoes(), buscarAprovacoesPendentes()])

  // Aguardar as solicitações para garantir que temos as colunas do servidor
  // A mesclagem será feita na função getSolicitacoes()
  isResolver.value = page.url == "/solicitacoes/lista?resolvidas=true"
  // se vier da notificação de tem resolvidas para finalizar
  if (isResolver.value) {
    filtrarPorLegenda("resolvida")
    getSolicitacoes()
  }

  // ✅ Carregar preferência de ícones na tabela
  const iconesSalvo = await userPreferences.get(PREF_KEYS.mostrarIconesTabela)
  if (iconesSalvo !== null && iconesSalvo !== undefined) {
    mostrarIconesTabela.value = iconesSalvo
  }

  // ✅ Iniciar listener Reverb para atualizações em tempo real
  iniciarReverbListener()

  // ✅ Finalizar loading inicial após carregar tudo
  loadingInicial.value = false
})

// ✅ Verifica se a solicitação recebida via Reverb corresponde aos filtros ativos
function solicitacaoCorrespondeAosFiltros(solicitacao) {
  if (!solicitacao) return false

  // Verificar filtro de assuntos
  if (filtro.value.assuntos && filtro.value.assuntos.length > 0) {
    const assuntosIds = filtro.value.assuntos.map((a) => a.id || a)
    if (!assuntosIds.includes(solicitacao.assunto_id)) {
      return false
    }
  }

  // Verificar filtro de filiais
  if (filtro.value.filiais && filtro.value.filiais.length > 0) {
    const filiaisIds = filtro.value.filiais.map((f) => f.code || f)
    if (!filiaisIds.includes(solicitacao.filial_id)) {
      return false
    }
  }

  // Verificar filtro de prioridades
  if (filtro.value.prioridades && filtro.value.prioridades.length > 0) {
    const prioridadesValues = filtro.value.prioridades.map((p) => p.value || p)
    if (!prioridadesValues.includes(solicitacao.prioridade)) {
      return false
    }
  }

  // Verificar filtro de responsável
  if (filtro.value.responsavel && filtro.value.responsavel.length > 0) {
    const responsaveisMatriculas = filtro.value.responsavel.map(
      (r) => r.matricula || r
    )
    // Se não tiver responsável na solicitação e filtro exige responsável específico
    if (
      !solicitacao.usuario_responsavel &&
      !responsaveisMatriculas.includes("nao_atribuido")
    ) {
      return false
    }
    // Se tiver responsável, verificar se está no filtro
    if (
      solicitacao.usuario_responsavel &&
      !responsaveisMatriculas.includes(solicitacao.usuario_responsavel)
    ) {
      return false
    }
  }

  return true
}

// ✅ Iniciar listener do Reverb para o departamento selecionado
function iniciarReverbListener() {
  // Desconectar canal anterior se existir
  desconectarTodos()

  const departamento = filtro.value.departamento?.condicao1
  if (!departamento) return

  canalDepartamento = escutarDepartamento(departamento, {
    filtroAtual: (solicitacao) => solicitacaoCorrespondeAosFiltros(solicitacao),
    onCriada: (data) => {
      // Verificar se a solicitação corresponde aos filtros ativos
      if (solicitacaoCorrespondeAosFiltros(data.solicitacao)) {
        // Nova solicitação criada e corresponde aos filtros - mostrar toast
        toastSuccess(`Nova solicitação #${data.solicitacao?.id} criada!`)
      }
      // Recarregar lista para obter dados completos
      getSolicitacoesSilenciosa()
    },
    onAtualizada: (data) => {
      // Solicitação atualizada - recarregar lista para obter dados completos (foto, nome, etc)
      getSolicitacoesSilenciosa()

      // ✅ Se é atualização de aprovação, recarregar aprovações também
      const tipoAtualizacao = data.tipo_atualizacao || ""
      if (tipoAtualizacao.includes("aprovacao")) {
        buscarAprovacoesPendentes()
      }
    }
  })

  // ✅ Escutar canal do usuário para aprovações pessoais
  const matricula = props.auth?.user?.matricula
  if (matricula) {
    canalUsuario = escutarUsuario(matricula, (data) => {
      // Notificação pessoal recebida (nova aprovação, atribuição, etc)
      console.log("[Lista] Notificação pessoal via Reverb:", data)

      // Atualizar aprovações pendentes
      if (data.tipo === "aprovacao") {
        buscarAprovacoesPendentes()
      }

      // Atualizar lista de solicitações
      getSolicitacoesSilenciosa()
    })
  }
}

function alterarPorPagina() {
  getSolicitacoes()
}

function alterarItensPorPagina(qtd) {
  filtro.value.porPagina = qtd
  filtro.value.pagina = 1
  getSolicitacoes()
}

// Handler para mudança de página do DataTable
function onPageChange(event) {
  filtro.value.pagina = event.page + 1 // DataTable usa 0-based, backend usa 1-based
  filtro.value.porPagina = event.rows
  getSolicitacoes()
}

const solicitacoesFiltered = computed(() => {
  if (!Array.isArray(solicitações.value)) {
    return []
  }

  var existeMarcado = solicitações.value.some((s) => s.checked)

  if (!existeMarcado) {
    return solicitações.value.filter((i) => !i.ocultarSolicitacao)
  }

  var solicitacaoBase = solicitações.value.filter((s) => s.checked)[0]
  return solicitações.value.filter((sol) => {
    if (sol.filial.codigo != solicitacaoBase.filial.codigo) {
      return false
    } else if (
      sol.status == "cancelada" ||
      sol.status == "finalizada" ||
      sol.status == "resolvida" ||
      sol.status == "em atendimento" ||
      sol.status == "agendado"
    ) {
      return false
    }
    return true
  })
})
const responsaveisComputed = computed(() => {
  const responsaveis = filtro.value.departamento?.responsaveis || []

  const sorted = [...responsaveis].sort((a, b) =>
    a.nome.localeCompare(b.nome, "pt-BR", { sensitivity: "base" })
  )

  return [{ nome: "NÃO ATRIBUIDO", matricula: "nao_atribuido" }, ...sorted]
})

async function getSolicitacoes() {
  loading.value = true
  await buscarSolicitacoesInterno()
  // ✅ Salvar preferências apenas em ações manuais (não via Reverb)
  salvarPreferenciasFiltro()
  loading.value = false
}

// ✅ Versão silenciosa para atualizações via Reverb (sem loading, sem salvar prefs)
async function getSolicitacoesSilenciosa() {
  await buscarSolicitacoesInterno()
}

// ✅ Salvar preferências de filtro no servidor (separado do fetch)
function salvarPreferenciasFiltro() {
  const filtroSerializado = userPreferences.serializarFiltro(filtro.value)
  if (aba.value === "minhas") {
    userPreferences.set(PREF_KEYS.filtroMinhas, filtroSerializado)
    if (filtro.value.situacoes.length != 1) {
      userPreferences.set(
        PREF_KEYS.situacoesTodasMinhas,
        filtro.value.situacoes
      )
    }
  } else {
    userPreferences.set(PREF_KEYS.filtroAtendimento, filtroSerializado)
    if (filtro.value.situacoes.length != 1) {
      userPreferences.set(
        PREF_KEYS.situacoesTodasAtendimento,
        filtro.value.situacoes
      )
    }
  }
}

// Função interna compartilhada
async function buscarSolicitacoesInterno() {
  const filtroTmp = {
    ...filtro.value,
    aba: aba.value,
    solicitacoesMarcadasIds: cacheMarcadas.value
  }
  try {
    const response = await axios.post(
      "/solicitacoes/lista/buscar-solicitacoes",
      filtroTmp
    )

    // Manter porPagina atual se backend não retornar valor válido
    if (response.data.paginacao?.porPagina) {
      filtro.value.porPagina = response.data.paginacao.porPagina
    }
    solicitações.value = response.data["solicitacoes"].data
    solicitações.value.paginacao = response.data["paginacao"]
    solicitações.value.contagem = response.data["contagem"]
    // Atualizar contagem dos cards apenas quando NÃO for filtro por clique no card
    if (!filtrandoPorCard.value) {
      contagemCards.value = response.data["contagem"]
    }
    filtrandoPorCard.value = false
    totalRegistros.value = response.data["paginacao"]?.total || 0

    // Mesclar colunas do servidor com cache local
    const colunasServidor = response.data["colunas"] || colunasDefault
    const colunasCache = await userPreferences.get(
      PREF_KEYS.visualizacaoTabela,
      []
    )

    // Carregar colunas do cache apenas uma vez na inicialização
    if (!colunasCarregadas.value) {
      colunas.value = mesclarColunasComCache(colunasServidor, colunasCache)
      colunasCarregadas.value = true
    }

    solicitações.value.forEach((item) => {
      if (
        item.status == "cancelada" ||
        item.status == "finalizada" ||
        item.status == "resolvida" ||
        item.status == "em atendimento" ||
        item.status == "agendado"
      ) {
        item.desabilitaCheckbox = true
      }

      // Verificar se o ID está no array de marcados
      if (cacheMarcadas.value.includes(item.id)) {
        item.checked = true
      } else {
        item.checked = false
      }
    })

    solicitações.value.totalAbertos = solicitações.value.filter(
      (i) =>
        i.status != "finalizada" &&
        i.status != "cancelada" &&
        i.status != "resolvida"
    ).length
  } catch (error) {
    console.error(error)
    swalErro()
  } finally {
    if (solicitações.value?.contagem?.resolvida == 0) {
      isResolver.value = false
    }
  }
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

// Severity da prioridade para Tag do PrimeVue
function getPrioridadeSeverity(prioridade) {
  const severities = {
    urgente: "danger",
    alta: "warn",
    media: "info",
    baixa: "secondary"
  }
  return severities[prioridade] || "secondary"
}

// Label da prioridade formatado
function getPrioridadeLabel(prioridade) {
  const labels = {
    urgente: "Urgente",
    alta: "Alta",
    media: "Média",
    baixa: "Baixa"
  }
  return labels[prioridade] || prioridade
}

// Texto formatado do status
function textoStatus(status) {
  switch (status) {
    case "pendente":
      return "Pendente"
    case "em atendimento":
      return "Atendimento"
    case "atendimento pausado":
      return "Pausado"
    case "agendado":
      return "Agendado"
    case "resolvida":
      return "Resolvida"
    case "resolução recusada":
      return "Recusada"
    case "finalizada":
      return "Finalizada"
    case "cancelada":
      return "Cancelada"
    case "retorno solicitante":
      return "Retorno"
    default:
      return status
  }
}

// Severity do status para Tag do PrimeVue
function getStatusSeverity(status) {
  const severities = {
    pendente: "warn",
    "em atendimento": "info",
    "atendimento pausado": "secondary",
    agendado: "help",
    resolvida: "success",
    "resolução recusada": "danger",
    finalizada: "success",
    cancelada: "secondary",
    "retorno solicitante": "info"
  }
  return severities[status] || "secondary"
}

// Verificar se a solicitação está atrasada (previsão de entrega vencida)
function estaAtrasada(solicitacao) {
  if (!solicitacao.previsao_entrega) return false

  // Status que podem estar atrasados
  const statusAtrasaveis = [
    "pendente",
    "em atendimento",
    "atendimento pausado",
    "agendado"
  ]
  if (!statusAtrasaveis.includes(solicitacao.status)) return false

  const hoje = new Date()
  hoje.setHours(0, 0, 0, 0)
  const previsao = new Date(solicitacao.previsao_entrega)
  previsao.setHours(0, 0, 0, 0)

  return previsao < hoje
}

// Calcular dias de atraso
function diasDeAtraso(solicitacao) {
  if (!estaAtrasada(solicitacao)) return 0

  const hoje = new Date()
  hoje.setHours(0, 0, 0, 0)
  const previsao = new Date(solicitacao.previsao_entrega)
  previsao.setHours(0, 0, 0, 0)

  const diffTime = hoje - previsao
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  return diffDays
}

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

// Função para obter nome e sobrenome
function obterNomeSobrenome(nome) {
  if (!nome) return "Não atribuído"
  const partes = nome
    .trim()
    .split(" ")
    .filter((p) => p.length > 0)
  if (partes.length === 0) return "Não atribuído"
  if (partes.length === 1) return partes[0]
  return `${partes[0]} ${partes[partes.length - 1]}`
}

function selecionarSolicitacaoEvent(event) {
  selecionarSolicitacao(event.data)
}

function selecionarSolicitacao(item) {
  solicitacaoSelecionada.value = item
  dialogSolicitacao.value = true
}

function showDialogFuncionario(origem) {
  origemFuncionario.value = origem
  dialogFuncionario.value = true
}

async function buscarFuncionario() {
  loading.value = true

  if (!termoFuncionario.value || termoFuncionario.value == "") {
    listaFuncionarios.value = []
  } else {
    await axios
      .post("/util/usuarios", { termo: termoFuncionario.value })
      .then((res) => {
        listaFuncionarios.value = res.data.dados
      })
      .catch((err) => {
        swalErro(err.response.data.mensagem)
      })
  }

  loading.value = false
}

const habilitaMultAgendamento = computed(() => {
  if (solicitações.value) {
    var habilitar = false
    solicitações.value.map((item) => {
      if (item.checked) {
        habilitar = true
      }
    })
    return habilitar
  }
})

function adicionarFunc(func) {
  if (origemFuncionario.value == "responsavel") {
    filtro.value.responsavel = func
  } else if (origemFuncionario.value == "solicitante") {
    filtro.value.solicitante = func
  }

  dialogFuncionario.value = false
  termoFuncionario.value = ""
  listaFuncionarios.value = []
}

async function criarAgendamento() {
  solicitacoesAgend.value = solicitações.value.filter((item) => {
    return item.checked == true
  })
  dialogAgendamento.value = true
}

async function atualizaAgendamentos() {
  // Limpar cache de solicitações marcadas
  cacheMarcadas.value = []
  getSolicitacoes()
  dialogAgendamento.value = false
}

function validaPermissao(perm) {
  return props.permissoes.includes(perm)
}

async function limparFiltros() {
  filtro.value.id = null
  filtro.value.prioridades = []
  filtro.value.filiais = []
  filtro.value.ufs = []
  filtro.value.cidades = []
  filtro.value.responsavel = []
  filtro.value.solicitante = null
  filtro.value.departamento = props.departamentos.find(
    (d) => d.condicao1 == props.usuarioLogado.areaatuacao
  )
  filtro.value.assuntos = []
  filtro.value.situacoes = []
  filtro.value.dataIni = null
  filtro.value.dataFim = null
  filtro.value.dataAltIni = null
  filtro.value.dataAltFim = null
  filtro.value.isResponsavel = false

  // Limpar cache salvo da aba atual
  if (aba.value === "minhas") {
    await userPreferences.remove(PREF_KEYS.filtroMinhas)
    await userPreferences.remove(PREF_KEYS.situacoesTodasMinhas)
  } else {
    await userPreferences.remove(PREF_KEYS.filtroAtendimento)
    await userPreferences.remove(PREF_KEYS.situacoesTodasAtendimento)
  }

  // Atualizar a lista com os filtros limpos
  getSolicitacoes()
}

function abrirDialogConfig() {
  colunasTemp.value = colunas.value.map((col) => ({ ...col }))
  mostrarIconesTabelaTemp.value = mostrarIconesTabela.value
  dialogConfig.value = true
}

function deParaColunas(coluna) {
  const mapa = {
    id: "ID",
    titulo: "Titulo",
    prioridade: "Prioridade",
    departamento_responsavel: "Departamento",
    usuario_responsavel: "Responsável",
    assunto_id: "Assunto",
    etapa_atual: "Etapa Andamento",
    usuario_solicitante: "Solicitante",
    filial_id: "Filial",
    filial_uf: "UF",
    filial_cidade: "Cidade",
    created_at: "Data Criação",
    dias_aberto: "Dias Aberto",

    updated_at: "Última Atualização",
    descricao: "Descrição",
    status: "Situação",
    usuario_origem: "Usuario Origem",
    previsao_entrega: "Previsão de Entrega",
    solicitacao_pai_id: "Solicitação Pai",
    usuarios_destino: "Usuários Destino"
  }

  // Se não estiver no mapa, retorna com substituição simples
  return mapa[coluna]
}

async function salvarVisualizacao() {
  colunas.value = colunasTemp.value.map((col) => ({ ...col }))
  mostrarIconesTabela.value = mostrarIconesTabelaTemp.value
  await userPreferences.set(PREF_KEYS.visualizacaoTabela, colunas.value)
  await userPreferences.set(
    PREF_KEYS.mostrarIconesTabela,
    mostrarIconesTabela.value
  )

  dialogConfig.value = false
}

async function limparVisualizacao() {
  const confirmacao = await swalConfirm(
    "Confirmar Reset",
    "Deseja realmente resetar as configurações de colunas para o padrão?",
    "Sim, resetar",
    "Cancelar"
  )

  if (!confirmacao.isConfirmed) return

  await userPreferences.remove(PREF_KEYS.visualizacaoTabela)
  await userPreferences.remove(PREF_KEYS.mostrarIconesTabela)
  colunas.value = colunasDefault.map((col) => ({ ...col }))
  mostrarIconesTabela.value = true
  dialogConfig.value = false
}

async function buscarFiliais() {
  await axios
    .get("/util/filiais")
    .then((res) => {
      optionsFiliais.value = res.data
        .map((item) => {
          return {
            code: item.codigo,
            name: `${item.codigo} - ${item.fantasia}`,
            cidade: item.cidade,
            uf: item.uf
          }
        })
        .filter((i) => i.code != 99)
    })
    .catch((err) => {
      console.error(err)
    })
}

function getStatusVisual(status) {
  switch (status) {
    case "pendente":
      return { icon: "far fa-clock", color: "#eab308" }
    case "em atendimento":
      return { icon: "fas fa-spinner", color: "#60a5fa" }
    case "atendimento pausado":
      return { icon: "fas fa-pause", color: "#f0e258" }
    case "agendado":
      return { icon: "fas fa-calendar-check", color: "#06b6d4" }
    case "resolvida":
      return { icon: "fas fa-check", color: "#34d399" }
    case "resolução recusada":
      return { icon: "fas fa-ban", color: "#f87171" }
    case "finalizada":
      return { icon: "fas fa-check-double", color: "#10b981" }
    case "cancelada":
      return { icon: "fas fa-times", color: "#9ca3af" }
    case "retorno solicitante":
      return { icon: "fas fa-undo", color: "#FFA500" }
    case "atrasadas":
      return { icon: "fas fa-clock", color: "#dc2626" }
    default:
      return { icon: "fas fa-question", color: "#d1d5db" }
  }
}

function calcularDiasRestantes(dataPrevisao, status = null) {
  if (!dataPrevisao) return ""

  // Se o status for "resolvida" ou "finalizada", mostrar como entregue
  if (status && (status === "resolvida" || status === "finalizada")) {
    return "Entregue"
  }

  const hoje = new Date()
  const previsao = new Date(dataPrevisao)

  // Zera as horas para comparação apenas de datas
  hoje.setHours(0, 0, 0, 0)
  previsao.setHours(0, 0, 0, 0)

  const diferenca = previsao.getTime() - hoje.getTime()
  const diasRestantes = Math.ceil(diferenca / (1000 * 60 * 60 * 24))

  if (diasRestantes < 0) {
    return `Atrasado ${Math.abs(diasRestantes)} dia(s)`
  } else if (diasRestantes === 0) {
    return "Hoje"
  } else if (diasRestantes === 1) {
    return "Amanhã"
  } else {
    return `${diasRestantes} dia(s) restante(s)`
  }
}

// Função para verificar se uma solicitação está atrasada
function solicitacaoAtrasada(solicitacao) {
  if (!solicitacao.previsao_entrega) return false

  const statusAtrasaveis = [
    "pendente",
    "em atendimento",
    "atendimento pausado",
    "agendado",
    "retorno solicitante"
  ]
  if (!statusAtrasaveis.includes(solicitacao.status)) return false

  const hoje = new Date()
  const previsao = new Date(solicitacao.previsao_entrega)

  hoje.setHours(0, 0, 0, 0)
  previsao.setHours(0, 0, 0, 0)

  return previsao.getTime() < hoje.getTime()
}

function aoOrdenar(event) {
  filtro.value.ordenacao = event.multiSortMeta
  getSolicitacoes()
}

const colunaVisivel = (pColuna) => {
  return (
    Array.isArray(colunas.value) &&
    colunas.value.some((c) => c.coluna === pColuna && c.ativarColuna)
  )
}

// Função para mesclar colunas do servidor com cache local (respeitando ORDEM)
function mesclarColunasComCache(colunasServidor, colunasCache) {
  if (!Array.isArray(colunasCache) || colunasCache.length === 0) {
    return colunasServidor
  }

  // Criar um mapa das colunas do servidor para fácil acesso
  const servidorMap = colunasServidor.reduce((map, col) => {
    map[col.coluna] = col
    return map
  }, {})

  // Resultado: primeiro as colunas do cache na ordem salva
  const resultado = []

  // Adicionar colunas na ordem do cache
  colunasCache.forEach((colCache) => {
    if (servidorMap[colCache.coluna]) {
      resultado.push({
        coluna: colCache.coluna,
        ativarColuna: colCache.ativarColuna
      })
    }
  })

  // Adicionar colunas novas do servidor que não existiam no cache
  colunasServidor.forEach((colServidor) => {
    if (!resultado.find((c) => c.coluna === colServidor.coluna)) {
      resultado.push(colServidor)
    }
  })

  return resultado
}

async function mudarAba(pAba) {
  aba.value = pAba

  userPreferences.set(PREF_KEYS.abaSelecionada, pAba)

  if (pAba === "aprovacoes") {
    buscarAprovacoesPendentes()
    return
  }

  // ✅ Buscar filtro salvo da aba correspondente
  let filtroSalvo = null
  if (pAba == "minhas") {
    filtroSalvo = await userPreferences.get(PREF_KEYS.filtroMinhas)
  } else {
    filtroSalvo = await userPreferences.get(PREF_KEYS.filtroAtendimento)
  }

  // ✅ Criar contexto para deserialização
  const contextoDeserializacao = {
    departamentos: props.departamentos,
    filiais: optionsFiliais.value,
    matriculaLogado: props.usuarioLogado?.matricula,
    areaatuacaoLogado: props.usuarioLogado?.areaatuacao
  }

  // ✅ Deserializar filtro salvo (rehidrata com dados atuais)
  const filtroRehidratado = userPreferences.deserializarFiltro(
    filtroSalvo,
    contextoDeserializacao
  )

  if (filtroRehidratado) {
    filtro.value = filtroRehidratado
  } else {
    // Se não tem filtro salvo, usar valores padrão
    const departamentoUsuario = props.departamentos.find(
      (d) => d.condicao1 === props.usuarioLogado?.areaatuacao
    )
    filtro.value.departamento = departamentoUsuario || props.departamentos[0]
    filtro.value.assuntos = []
    filtro.value.filiais = []
    filtro.value.responsavel = []
  }

  getSolicitacoes()
}

function redirecionarNovaSolicitacao() {
  window.location.href = "/solicitacoes/nova"
}

async function filtrarPorLegenda(legenda) {
  // Se clicar na mesma situação já filtrada, restaura todas
  if (
    filtro.value.situacoes.length === 1 &&
    filtro.value.situacoes[0] === legenda
  ) {
    await filtrarTodos()
    return
  }
  filtrandoPorCard.value = true
  filtro.value.situacoes = [legenda]

  getSolicitacoes()
}

async function filtrarTodos() {
  let situacaoTodos = null
  if (aba.value == "minhas") {
    situacaoTodos = await userPreferences.get(PREF_KEYS.situacoesTodasMinhas)
  } else if (aba.value == "atendimento") {
    situacaoTodos = await userPreferences.get(
      PREF_KEYS.situacoesTodasAtendimento
    )
  }

  if (situacaoTodos) {
    filtro.value.situacoes = situacaoTodos
  }
  getSolicitacoes()
}

function atualizarListaSelecionados(solicitacao, novoValor) {
  // Atualizar cache com todos os IDs marcados
  if (novoValor) {
    // Adicionar ID ao array se marcado
    if (!cacheMarcadas.value.includes(solicitacao.id)) {
      cacheMarcadas.value.push(solicitacao.id)
    }
  } else {
    // Remover ID do array se desmarcado
    cacheMarcadas.value = cacheMarcadas.value.filter(
      (id) => id !== solicitacao.id
    )
  }
}

// ========== FUNÇÕES PARA APROVAÇÕES ==========

// Buscar aprovações pendentes do usuário
async function buscarAprovacoesPendentes() {
  loadingAprovacoes.value = true
  try {
    const response = await axios.get("/solicitacoes/aprovacoes/usuario")
    aprovacoesPendentes.value = response.data.aprovacoes || []
    totalAprovacoesPendentes.value = response.data.total || 0
  } catch (error) {
    console.error("Erro ao buscar aprovações:", error)
    swalErro("Erro ao carregar aprovações pendentes")
    aprovacoesPendentes.value = []
    totalAprovacoesPendentes.value = 0
  } finally {
    loadingAprovacoes.value = false
  }
}

// Aprovar rapidamente uma solicitação
async function aprovarRapido(aprovacao) {
  const confirmacao = await swalConfirm(
    "Confirmar Aprovação",
    `Deseja aprovar a solicitação #${aprovacao.solicitacao_id}?`,
    "Sim, aprovar",
    "Cancelar"
  )

  if (!confirmacao.isConfirmed) return

  try {
    loadingAprovacoes.value = true
    await axios.post(`/solicitacoes/aprovacoes/${aprovacao.id}/responder`, {
      status: "aprovada",
      resposta: "Aprovado via ação rápida"
    })

    toastSuccess("Solicitação aprovada com sucesso!")
    await buscarAprovacoesPendentes() // Recarregar lista
  } catch (error) {
    console.error("Erro ao aprovar:", error)
    swalErro("Erro ao aprovar solicitação")
  } finally {
    loadingAprovacoes.value = false
  }
}

// Rejeitar rapidamente uma solicitação
async function rejeitarRapido(aprovacao) {
  // Abre diretamente a solicitação na aba de aprovações e já abre o dialog de rejeição
  solicitacaoSelecionada.value = {
    id: aprovacao.solicitacao_id,
    abaInicial: "aprovacoes",
    aprovacaoIdRejeitar: aprovacao.id
  }
  dialogSolicitacao.value = true
}

// Abrir solicitação para ver detalhes
function abrirSolicitacaoAprovacao(aprovacao, abaInicial = "aprovacoes") {
  solicitacaoSelecionada.value = { id: aprovacao.solicitacao_id, abaInicial }
  dialogSolicitacao.value = true
}

// Computed para mostrar se há aprovações pendentes
const temAprovacoesPendentes = computed(() => {
  return totalAprovacoesPendentes.value > 0
})

// Se há aprovações pendentes e não é uma das abas existentes, verificar se deve mostrar aba aprovações
const mostrarAbaAprovacoes = computed(() => {
  return temAprovacoesPendentes.value
})

// Função para atualizar dados após mudanças em solicitações
async function atualizarDados() {
  getSolicitacoes()
  await buscarAprovacoesPendentes()
}

const loadingExport = ref(false)

// #12173 - Exportar relatório com campos configuráveis
async function exportarTabela() {
  // Validar departamento obrigatório
  if (!filtro.value.departamento) {
    toastError("Selecione um departamento para exportar.")
    return
  }

  loadingExport.value = true

  // filtro.value.departamento é um objeto, pegar o nome em condicao1
  const nomeDepartamento = filtro.value.departamento.condicao1

  // Preparar parâmetros com os mesmos filtros usados na listagem
  const params = {
    departamento: nomeDepartamento,
    assuntos: filtro.value.assuntos?.map((a) => a.id || a) || [],
    filiais: filtro.value.filiais?.map((f) => f.codigo || f) || [],
    ufs: filtro.value.ufs || [],
    cidades: filtro.value.cidades || [],
    situacoes: filtro.value.situacoes || [],
    prioridades: filtro.value.prioridades || [],
    responsavel: filtro.value.responsavel?.map((r) => r.matricula || r) || [],
    solicitante: filtro.value.solicitante?.matricula || null,
    data_inicio: filtro.value.dataIni || null,
    data_fim: filtro.value.dataFim || null,
    data_alt_inicio: filtro.value.dataAltIni || null,
    data_alt_fim: filtro.value.dataAltFim || null,
    id: filtro.value.id || null,
    aba: aba.value,
    colunas: colunas.value.filter((c) => c.ativarColuna).map((c) => c.coluna)
  }

  try {
    const response = await axios.post(
      "/solicitacoes/configuracoes/exportar-relatorio",
      params,
      { responseType: "blob" }
    )

    // Criar link para download
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement("a")
    link.href = url
    link.setAttribute(
      "download",
      `relatorio_solicitacoes_${nomeDepartamento}_${new Date().toISOString().slice(0, 10)}.xlsx`
    )
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    console.error(err)
    toastError("Erro ao exportar relatório.")
  } finally {
    loadingExport.value = false
  }
}
</script>

<template>
  <Head title="Atendimento - Solicitações" />

  <AuthenticatedLayout>
    <!-- Loading Inicial -->
    <div
      v-if="loadingInicial"
      class="fixed inset-0 z-50 flex items-center justify-center bg-gradient-to-br from-slate-50/95 via-white/95 to-blue-50/95 dark:from-slate-900/95 dark:via-slate-800/95 dark:to-slate-900/95 backdrop-blur-md"
    >
      <div class="flex flex-col items-center gap-6">
        <!-- Spinner animado -->
        <div class="relative">
          <div
            class="w-20 h-20 rounded-full border-4 border-blue-100 dark:border-slate-700"
          ></div>
          <div
            class="absolute top-0 left-0 w-20 h-20 rounded-full border-4 border-transparent border-t-blue-500 dark:border-t-blue-400 animate-spin"
          ></div>
          <div
            class="absolute top-2 left-2 w-16 h-16 rounded-full border-4 border-transparent border-t-indigo-400 dark:border-t-indigo-500 animate-spin"
            style="animation-duration: 1.5s; animation-direction: reverse"
          ></div>
        </div>
        <!-- Texto -->
        <div class="text-center">
          <p class="text-xl font-semibold text-gray-700 dark:text-gray-200">
            Carregando Solicitações
          </p>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Aguarde um momento...
          </p>
        </div>
      </div>
    </div>

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
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span>Solicitações</span>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span
            class="text-gray-950 dark:text-white font-bold truncate max-w-[120px] sm:max-w-none"
          >
            Atendimento
          </span>
        </div>
      </div>
    </div>

    <!-- Cabeçalho da Página -->
    <div class="space-y-2 mb-6">
      <div class="flex items-center gap-3">
        <h2
          class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3"
        >
          <div
            class="w-1 h-8 bg-gradient-to-b from-blue-500 to-blue-700 rounded-full"
          ></div>
          Atendimento de Solicitações
        </h2>
      </div>
      <span
        class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-bold pl-4 pr-2 sm:pr-0 break-words whitespace-normal"
      >
        Gerencie as solicitações do seu departamento e acompanhe o status de
        cada uma.
      </span>
    </div>

    <!-- Banner de Aprovações Pendentes (mostrar apenas quando não está na aba de aprovações) -->
    <div
      v-if="temAprovacoesPendentes && aba !== 'aprovacoes'"
      class="mb-4 bg-gradient-to-r from-amber-50 via-yellow-50 to-orange-50 dark:from-amber-900/20 dark:via-yellow-900/20 dark:to-orange-900/20 rounded-2xl border border-amber-200/50 dark:border-amber-700/50 overflow-hidden shadow-lg shadow-amber-500/10"
    >
      <div
        class="flex flex-col sm:flex-row items-center justify-between gap-3 p-4"
      >
        <div class="flex items-center gap-3">
          <div class="relative">
            <div
              class="w-12 h-12 bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl flex items-center justify-center shadow-lg shadow-amber-500/30"
            >
              <i class="pi pi-bell text-white text-lg"></i>
            </div>
            <!-- Pulsing indicator -->
            <span class="absolute -top-1 -right-1 flex h-4 w-4">
              <span
                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"
              ></span>
              <span
                class="relative inline-flex rounded-full h-4 w-4 bg-red-500 border-2 border-white dark:border-slate-800"
              ></span>
            </span>
          </div>
          <div>
            <h4 class="text-base font-bold text-amber-800 dark:text-amber-200">
              Você tem {{ totalAprovacoesPendentes }} Aprovação{{
                totalAprovacoesPendentes > 1 ? "ões" : ""
              }}
              pendente{{ totalAprovacoesPendentes > 1 ? "s" : "" }}
            </h4>
            <p class="text-sm text-amber-600 dark:text-amber-400">
              Solicitações aguardando sua aprovação ou rejeição
            </p>
          </div>
        </div>
        <button
          @click="mudarAba('aprovacoes')"
          class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-semibold text-sm rounded-xl shadow-lg shadow-amber-500/25 transition-all duration-300 hover:scale-[1.02]"
        >
          <span>Ver Aprovações</span>
          <i class="pi pi-arrow-right text-xs"></i>
        </button>
      </div>
    </div>

    <!-- Filtros principais: Departamento + Assunto + Botões (sempre visíveis) -->
    <div
      v-if="aba !== 'aprovacoes'"
      class="mb-4 bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm"
    >
      <div class="flex flex-wrap items-end gap-4">
        <!-- Departamento Responsável -->
        <div
          class="flex flex-col gap-1 min-w-[240px] flex-1"
          :class="{ 'opacity-50': buscaPorIdAtiva }"
        >
          <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Departamento Responsável
            <span
              @click="
                swalObservacao(
                  'Departamento responsável por tratar a solicitação.'
                )
              "
              v-tooltip.top="
                'Departamento responsável por tratar a solicitação.'
              "
              class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
            >
              i
            </span>
          </label>
          <Select
            @change="filtro.assuntos = []"
            :options="props.departamentos"
            v-model="filtro.departamento"
            option-label="condicao1"
            placeholder="Selecione"
            class="w-full h-10"
            :disabled="buscaPorIdAtiva"
          />
        </div>

        <!-- Assuntos -->
        <div
          v-if="filtro.departamento"
          class="flex flex-col gap-1 min-w-[240px] flex-1"
          :class="{ 'opacity-50': buscaPorIdAtiva }"
        >
          <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Assuntos
            <span
              @click="
                swalObservacao(
                  'Assunto no qual a solicitação foi vinculada/atribuída.'
                )
              "
              v-tooltip.top="
                'Assunto no qual a solicitação foi vinculada/atribuída.'
              "
              class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
            >
              i
            </span>
          </label>
          <MultiSelect
            :options="optionsAssunto"
            v-model="filtro.assuntos"
            option-label="assunto"
            placeholder="Todos"
            class="w-full h-10"
            :maxSelectedLabels="1"
            selectedItemsLabel="{0} selecionados"
            :disabled="buscaPorIdAtiva"
          />
        </div>

        <!-- Spacer para empurrar botões à direita -->
        <div class="flex-1"></div>

        <!-- Botões -->
        <div class="flex items-end gap-2">
          <Button
            v-if="aba !== 'aprovacoes'"
            :label="showFiltros ? 'Menos Filtros' : 'Mais Filtros'"
            :icon="showFiltros ? 'pi pi-filter-slash' : 'pi pi-filter'"
            severity="help"
            outlined
            class="min-w-[10rem]"
            @click="showFiltros = !showFiltros"
          />
          <Button
            label="Aplicar Filtros"
            icon="pi pi-search"
            severity="info"
            outlined
            @click="getSolicitacoes()"
          />
          <Button
            label="Limpar"
            icon="pi pi-times"
            severity="secondary"
            outlined
            @click="limparFiltros()"
          />
        </div>
      </div>
    </div>

    <!-- Filtros adicionais (ocultar na aba de aprovações) -->
    <div
      v-if="showFiltros && aba !== 'aprovacoes'"
      class="mb-6 bg-white dark:bg-slate-800 rounded-3xl p-6 relative overflow-hidden border border-gray-200 dark:border-gray-700"
    >
      <div class="flex flex-col gap-4 w-full">
        <!-- Grid de filtros - Linha 1 -->
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 items-end gap-4 w-full"
        >
          <!-- Filtro por ID -->
          <div class="flex flex-col gap-1">
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              ID
              <span
                @click="
                  swalObservacao(
                    'ID é um número único que identifica sua solicitação no sistema.'
                  )
                "
                v-tooltip.top="
                  'ID é um número único que identifica sua solicitação no sistema.'
                "
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <InputText
              v-model="filtro.id"
              placeholder="Ex: 12345"
              class="w-full h-10 px-3"
            />
          </div>

          <!-- Filtro por prioridade -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Prioridade
              <span
                @click="
                  swalObservacao(
                    'Filtre as solicitações por prioridade (urgente, alta, média ou baixa).'
                  )
                "
                v-tooltip.top="
                  'Filtre as solicitações por prioridade (urgente, alta, média ou baixa).'
                "
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <MultiSelect
              :options="[
                { label: 'Baixa', value: 'baixa' },
                { label: 'Média', value: 'media' },
                { label: 'Alta', value: 'alta' },
                { label: 'Urgente', value: 'urgente' }
              ]"
              optionLabel="label"
              optionValue="value"
              v-model="filtro.prioridades"
              placeholder="Todas"
              class="w-full h-10"
              :maxSelectedLabels="2"
              selectedItemsLabel="{0} selecionadas"
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Filtro por situação -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Situação
              <span
                @click="
                  swalObservacao(
                    'Filtre as solicitações pelo status atual (pendente, em atendimento, finalizada, etc).'
                  )
                "
                v-tooltip.top="
                  'Filtre as solicitações pelo status atual (pendente, em atendimento, finalizada, etc).'
                "
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <MultiSelect
              :options="optionsSituacoes"
              option-label="name"
              option-value="value"
              v-model="filtro.situacoes"
              placeholder="Todas"
              class="w-full h-10"
              :maxSelectedLabels="2"
              selectedItemsLabel="{0} selecionadas"
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Filtro por Filial -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Filial
              <span
                @click="
                  swalObservacao(
                    'Filtre as solicitações pela filial de origem.'
                  )
                "
                v-tooltip.top="'Filtre as solicitações pela filial de origem.'"
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <MultiSelect
              v-model="filtro.filiais"
              :options="optionsFiliais"
              option-label="name"
              option-value="code"
              placeholder="Todas"
              class="w-full h-10"
              :maxSelectedLabels="1"
              selectedItemsLabel="{0} selecionadas"
              filter
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Filtro por UF -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              UF
              <span
                @click="
                  swalObservacao(
                    'Filtre as solicitações pelo estado (UF) da filial.'
                  )
                "
                v-tooltip.top="
                  'Filtre as solicitações pelo estado (UF) da filial.'
                "
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <MultiSelect
              v-model="filtro.ufs"
              :options="optionsUfs"
              option-label="name"
              option-value="value"
              placeholder="Todos"
              class="w-full h-10"
              :maxSelectedLabels="2"
              selectedItemsLabel="{0} selecionados"
              filter
              :disabled="buscaPorIdAtiva"
              @change="filtro.cidades = []"
            />
          </div>

          <!-- Filtro por Cidade -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Cidade
              <span
                @click="
                  swalObservacao(
                    'Filtre as solicitações pela cidade da filial.'
                  )
                "
                v-tooltip.top="'Filtre as solicitações pela cidade da filial.'"
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <MultiSelect
              v-model="filtro.cidades"
              :options="optionsCidades"
              option-label="name"
              option-value="value"
              placeholder="Todas"
              class="w-full h-10"
              :maxSelectedLabels="1"
              selectedItemsLabel="{0} selecionadas"
              filter
              :disabled="buscaPorIdAtiva"
            />
          </div>
        </div>

        <!-- Grid de filtros - Linha 2: Solicitante, Responsável e Datas -->
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 items-end gap-4 w-full border-t border-gray-200 dark:border-gray-700 pt-4"
        >
          <!-- Filtro por Solicitante -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Solicitante
              <span
                @click="
                  swalObservacao('Filtre as solicitações por quem as criou.')
                "
                v-tooltip.top="'Filtre as solicitações por quem as criou.'"
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <Funcionario
              :multiple="false"
              class="w-full h-10"
              v-model="filtro.solicitante"
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Filtro por Responsável -->
          <div
            v-if="filtro.departamento"
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Responsável
              <span
                @click="swalObservacao('Responsável atual pela solicitação.')"
                v-tooltip.top="'Responsável atual pela solicitação.'"
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <MultiSelect
              v-model="filtro.responsavel"
              :options="responsaveisComputed"
              option-label="nome"
              option-value="matricula"
              placeholder="Todos"
              class="w-full h-10"
              :maxSelectedLabels="1"
              selectedItemsLabel="{0} responsáveis"
              filter
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Filtro por data de criação início -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Criação Início
              <span
                @click="
                  swalObservacao(
                    'Buscar solicitações criadas a partir desta data.'
                  )
                "
                v-tooltip.top="
                  'Buscar solicitações criadas a partir desta data.'
                "
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <DatePicker
              v-model="filtro.dataIni"
              dateFormat="dd/mm/yy"
              placeholder="dd/mm/aaaa"
              showIcon
              showButtonBar
              fluid
              class="w-full"
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Filtro por data de criação fim -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Criação Fim
              <span
                @click="
                  swalObservacao('Buscar solicitações criadas até esta data.')
                "
                v-tooltip.top="'Buscar solicitações criadas até esta data.'"
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <DatePicker
              v-model="filtro.dataFim"
              dateFormat="dd/mm/yy"
              placeholder="dd/mm/aaaa"
              showIcon
              showButtonBar
              fluid
              class="w-full"
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Filtro por data de alteração início -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Alteração Início
              <span
                @click="
                  swalObservacao(
                    'Buscar solicitações alteradas a partir desta data.'
                  )
                "
                v-tooltip.top="
                  'Buscar solicitações alteradas a partir desta data.'
                "
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <DatePicker
              v-model="filtro.dataAltIni"
              dateFormat="dd/mm/yy"
              placeholder="dd/mm/aaaa"
              showIcon
              showButtonBar
              fluid
              class="w-full"
              :disabled="buscaPorIdAtiva"
            />
          </div>

          <!-- Filtro por data de alteração fim -->
          <div
            class="flex flex-col gap-1"
            :class="{ 'opacity-50': buscaPorIdAtiva }"
          >
            <label
              class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
              Alteração Fim
              <span
                @click="
                  swalObservacao('Buscar solicitações alteradas até esta data.')
                "
                v-tooltip.top="'Buscar solicitações alteradas até esta data.'"
                class="inline-flex items-center justify-center w-4 h-4 text-[10px] text-white bg-blue-600 rounded-full cursor-pointer ml-1"
              >
                i
              </span>
            </label>
            <DatePicker
              v-model="filtro.dataAltFim"
              dateFormat="dd/mm/yy"
              placeholder="dd/mm/aaaa"
              showIcon
              showButtonBar
              fluid
              class="w-full"
              :disabled="buscaPorIdAtiva"
            />
          </div>
        </div>

        <!-- Checkbox Meus Atendimentos -->
        <div class="flex items-center gap-2 pt-2">
          <Checkbox
            binary
            input-id="meus-atendimentos"
            v-model="filtro.isResponsavel"
          />
          <label
            for="meus-atendimentos"
            class="text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer"
          >
            Meus Atendimentos
          </label>
        </div>
      </div>
    </div>

    <div class="relative w-full mx-auto">
      <!-- Dashboard - Cards de Estatísticas -->
      <div
        v-if="!buscaPorIdAtiva && solicitações && aba !== 'aprovacoes'"
        class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4 2xl:grid-cols-8 gap-3 mt-6 px-1 sm:px-0"
      >
        <!-- Card Total -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-indigo-500 scale-[1.02]': filtro.situacoes.length !== 1
          }"
          @click="filtrarTodos()"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-indigo-600"
            >
              <i class="pi pi-list !text-[11px] flex-shrink-0"></i>
              Total
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.total ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Pendentes -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-yellow-400 scale-[1.02]':
              filtro.situacoes.length === 1 &&
              filtro.situacoes[0] === 'pendente'
          }"
          @click="filtrarPorLegenda('pendente')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-yellow-400"
            >
              <i class="pi pi-clock !text-[11px] flex-shrink-0"></i>
              Pendentes
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.pendentes ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Em Atendimento -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-blue-500 scale-[1.02]':
              filtro.situacoes.length === 1 &&
              filtro.situacoes[0] === 'em atendimento'
          }"
          @click="filtrarPorLegenda('em atendimento')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-blue-500"
            >
              <i class="pi pi-comments !text-[11px] flex-shrink-0"></i>
              Atendimento
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.em_atendimento ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Agendado -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-purple-500 scale-[1.02]':
              filtro.situacoes.length === 1 &&
              filtro.situacoes[0] === 'agendado'
          }"
          @click="filtrarPorLegenda('agendado')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-purple-500"
            >
              <i class="pi pi-calendar !text-[11px] flex-shrink-0"></i>
              Agendado
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.agendado ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Pausado -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-gray-500 scale-[1.02]':
              filtro.situacoes.length === 1 &&
              filtro.situacoes[0] === 'atendimento pausado'
          }"
          @click="filtrarPorLegenda('atendimento pausado')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-gray-500"
            >
              <i class="pi pi-pause !text-[11px] flex-shrink-0"></i>
              Pausado
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.atendimento_pausado ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Atrasadas -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'animate-pulse': contagemCards?.atrasadas > 0,
            'ring-2 ring-red-500 scale-[1.02]':
              filtro.situacoes.length === 1 &&
              filtro.situacoes[0] === 'atrasadas'
          }"
          @click="filtrarPorLegenda('atrasadas')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-red-600"
            >
              <i
                class="pi pi-exclamation-triangle !text-[11px] flex-shrink-0"
              ></i>
              Atrasadas
            </span>
          </div>
          <div
            class="flex w-full justify-start text-lg font-bold"
            :class="
              contagemCards?.atrasadas > 0
                ? 'text-red-600'
                : 'text-gray-950 dark:text-white'
            "
          >
            <span>{{ contagemCards?.atrasadas ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Recusada -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-orange-500 scale-[1.02]':
              filtro.situacoes.length === 1 &&
              filtro.situacoes[0] === 'resolução recusada'
          }"
          @click="filtrarPorLegenda('resolução recusada')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-orange-500"
            >
              <i class="pi pi-times-circle !text-[11px] flex-shrink-0"></i>
              Recusada
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.resolucao_recusada ?? 0 }}</span>
          </div>
        </div>

        <!-- Card Retorno -->
        <div
          class="bg-white dark:bg-gray-900 border border-slate-400 dark:border-slate-700 rounded-xl p-3 shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
          :class="{
            'ring-2 ring-cyan-500 scale-[1.02]':
              filtro.situacoes.length === 1 &&
              filtro.situacoes[0] === 'retorno solicitante'
          }"
          @click="filtrarPorLegenda('retorno solicitante')"
        >
          <div class="flex items-center justify-between mb-1">
            <span
              class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold text-white truncate max-w-full bg-cyan-500"
            >
              <i class="pi pi-reply !text-[11px] flex-shrink-0"></i>
              Retorno
            </span>
          </div>
          <div
            class="flex w-full justify-start text-gray-950 dark:text-white text-lg font-bold"
          >
            <span>{{ contagemCards?.retorno_solicitante ?? 0 }}</span>
          </div>
        </div>
      </div>

      <!-- Conteúdo da Aba Aprovações -->
      <div
        v-if="aba === 'aprovacoes'"
        class="bg-white dark:bg-slate-800 w-full rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 overflow-hidden mt-6"
      >
        <!-- Header moderno -->
        <div
          class="bg-gradient-to-r from-amber-50 via-orange-50 to-yellow-50 dark:from-amber-900/20 dark:via-orange-900/20 dark:to-yellow-900/20 border-b border-amber-100 dark:border-amber-800/30 px-6 py-4"
        >
          <div
            class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
          >
            <div class="flex items-center gap-3">
              <div
                class="w-12 h-12 bg-gradient-to-br from-amber-400 to-orange-500 rounded-2xl flex items-center justify-center shadow-lg shadow-amber-500/25"
              >
                <i class="pi pi-check-circle text-white text-xl"></i>
              </div>
              <div>
                <h2 class="text-xl font-bold text-slate-800 dark:text-white">
                  Aprovações Pendentes
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                  {{ totalAprovacoesPendentes }} solicitação{{
                    totalAprovacoesPendentes !== 1 ? "ões" : ""
                  }}
                  aguardando sua decisão
                </p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="mudarAba('atendimento')"
                class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-medium text-sm rounded-xl border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600 transition-all shadow-sm"
              >
                <i class="pi pi-arrow-left text-xs"></i>
                <span>Voltar</span>
              </button>
              <button
                @click="buscarAprovacoesPendentes"
                class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-medium text-sm rounded-xl border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600 transition-all shadow-sm"
                :class="{ 'animate-spin-slow': loadingAprovacoes }"
              >
                <i class="pi pi-refresh text-xs"></i>
                <span>Atualizar</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Conteúdo -->
        <div class="p-6">
          <!-- Loading State -->
          <div
            v-if="loadingAprovacoes"
            class="flex flex-col items-center justify-center py-16"
          >
            <div class="relative">
              <div
                class="w-16 h-16 rounded-full border-4 border-amber-100 dark:border-slate-700"
              ></div>
              <div
                class="absolute top-0 left-0 w-16 h-16 rounded-full border-4 border-transparent border-t-amber-500 animate-spin"
              ></div>
            </div>
            <p class="mt-4 text-slate-500 dark:text-slate-400 font-medium">
              Carregando aprovações...
            </p>
          </div>

          <!-- Empty State -->
          <div
            v-else-if="aprovacoesPendentes.length === 0"
            class="flex flex-col items-center justify-center py-16"
          >
            <div
              class="w-24 h-24 bg-gradient-to-br from-emerald-100 to-green-100 dark:from-emerald-900/30 dark:to-green-900/30 rounded-3xl flex items-center justify-center mb-6"
            >
              <i class="pi pi-check-circle !text-4xl text-emerald-500"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2">
              Tudo em dia! 🎉
            </h3>
            <p class="text-slate-500 dark:text-slate-400 text-center max-w-md">
              Você não possui aprovações pendentes no momento. Novas
              solicitações aparecerão aqui quando necessitarem sua aprovação.
            </p>
          </div>

          <!-- Grid de Cards -->
          <div
            v-else
            class="grid grid-cols-1 lg:grid-cols-2 gap-4"
          >
            <CardAprovacao
              v-for="aprovacao in aprovacoesPendentes"
              :key="aprovacao.id"
              :aprovacao="aprovacao"
              @aprovar-rapido="aprovarRapido"
              @rejeitar-rapido="rejeitarRapido"
              @abrir-solicitacao="abrirSolicitacaoAprovacao"
            />
          </div>
        </div>
      </div>

      <!-- Tabela de Solicitações -->
      <div
        v-if="solicitações && aba !== 'aprovacoes'"
        class="bg-white dark:bg-slate-800 rounded-3xl p-4 sm:p-6 mt-6 relative overflow-hidden hidden ipad:block"
      >
        <!-- Cabeçalho da Tabela -->
        <div
          class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6"
        >
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <span
              class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-200 dark:bg-indigo-900/30 shadow-lg flex-shrink-0"
            >
              <i
                class="pi pi-list !text-xl text-indigo-700 dark:text-indigo-400"
              ></i>
            </span>
            <div>
              <h2
                class="text-xl sm:text-xl md:text-2xl font-extrabold text-black-800 dark:text-white drop-shadow truncate"
              >
                Resultados
                <span
                  class="text-base font-normal text-gray-500 dark:text-gray-400 ml-2"
                >
                  ({{ solicitações?.contagem?.total ?? 0 }} registro{{
                    (solicitações?.contagem?.total ?? 0) !== 1 ? "s" : ""
                  }})
                </span>
              </h2>
              <div
                class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 font-medium mt-1"
              >
                Clique em uma linha para abrir a solicitação
              </div>
            </div>
          </div>

          <!-- Configurar e Exportar -->
          <div class="flex flex-wrap items-center gap-1 sm:gap-2">
            <Button
              v-if="habilitaMultAgendamento"
              icon="pi pi-calendar"
              label="Agendar"
              severity="help"
              outlined
              v-tooltip.top="'Agendar Solicitações Selecionadas'"
              @click="criarAgendamento()"
            />
            <Button
              icon="pi pi-cog"
              label="Configurar"
              severity="secondary"
              outlined
              v-tooltip.top="'Configurar Tabela'"
              @click="abrirDialogConfig"
            />
            <Button
              label="Exportar"
              icon="pi pi-file-excel"
              severity="success"
              outlined
              v-tooltip.top="'Exportar para Excel'"
              @click="exportarTabela()"
              :loading="loadingExport"
              :disabled="solicitacoesFiltered.length === 0"
            />
          </div>
        </div>

        <!-- DataTable -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm">
          <DataTable
            :value="solicitacoesFiltered"
            dataKey="id"
            :loading="loading"
            lazy
            paginator
            :rows="filtro.porPagina"
            :totalRecords="solicitações?.contagem?.total ?? 0"
            :rowsPerPageOptions="[10, 25, 50, 100]"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            currentPageReportTemplate="Mostrando {first} a {last} de {totalRecords}"
            @page="onPageChange"
            @sort="aoOrdenar"
            sortMode="multiple"
            :multiSortMeta="filtro.ordenacao"
            removableSort
            @column-reorder="onColumnReorder"
            reorderableColumns
            stripedRows
            showGridlines
            scrollable
            class="min-w-full text-sm"
            rowHover
            @rowClick="selecionarSolicitacaoEvent($event)"
            :rowClass="
              () =>
                'cursor-pointer hover:bg-blue-50 dark:hover:bg-slate-700 transition-colors'
            "
          >
            <template #loading>
              <div
                class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-full shadow-md text-sm"
              >
                <i class="pi pi-spinner pi-spin text-xs"></i>
                <span class="font-medium">Carregando...</span>
              </div>
            </template>

            <template #empty>
              <div class="py-12 text-center">
                <span
                  class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-700 mb-4"
                >
                  <i
                    class="pi pi-inbox text-3xl text-gray-400 dark:text-gray-500"
                  ></i>
                </span>
                <template v-if="buscaPorIdAtiva">
                  <p class="text-gray-500 dark:text-gray-400 font-medium">
                    Solicitação #{{ filtro.id }} não encontrada
                  </p>
                  <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">
                    A solicitação não existe ou você não tem permissão para
                    visualizá-la.
                  </p>
                </template>
                <template v-else>
                  <p class="text-gray-500 dark:text-gray-400 font-medium">
                    Nenhuma solicitação encontrada
                  </p>
                  <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">
                    Tente ajustar os filtros
                  </p>
                </template>
              </div>
            </template>

            <Column
              header=""
              v-if="
                validaPermissao('solicitacoes.lista.criar-agendamento') &&
                aba == 'atendimento'
              "
            >
              <template #body="slot">
                <div @click.stop>
                  <BsCheckBox
                    v-model="slot.data.checked"
                    @update:modelValue="
                      (val) => atualizarListaSelecionados(slot.data, val)
                    "
                    :index="'sol-check-' + slot.data.id"
                    severity="info"
                    :title="
                      slot.data.desabilitaCheckbox
                        ? 'O status da solicitação não permite novos agendamentos'
                        : ''
                    "
                    :disabled="slot.data.desabilitaCheckbox"
                  ></BsCheckBox>
                </div>
              </template>
            </Column>

            <!-- Colunas dinâmicas baseadas na ordem de colunasVisiveis -->
            <Column
              v-for="col in colunasVisiveis"
              :key="col.coluna"
              :field="getColumnField(col.coluna)"
              :header="getColumnHeader(col.coluna)"
              :style="getColumnStyle(col.coluna)"
              :class="getColumnClass(col.coluna)"
              :sortable="isColumnSortable(col.coluna)"
            >
              <template #body="{ data }">
                <!-- ID -->
                <div
                  v-if="col.coluna === 'id'"
                  class="flex items-center gap-1"
                >
                  <i
                    v-if="mostrarIconesTabela"
                    class="pi pi-hashtag text-blue-500"
                  ></i>
                  <span
                    class="font-mono text-sm text-gray-800 dark:text-gray-200 bg-blue-50 dark:bg-blue-900/30 px-2 py-1 rounded-lg shadow-sm"
                  >
                    {{ data.id }}
                  </span>
                </div>

                <!-- Título -->
                <div
                  v-else-if="col.coluna === 'titulo'"
                  class="truncate font-medium text-gray-800 dark:text-gray-200"
                >
                  <i
                    v-if="mostrarIconesTabela"
                    class="pi pi-file text-blue-400 mr-1"
                  ></i>
                  {{ data.titulo || "-" }}
                </div>

                <!-- Descrição -->
                <div
                  v-else-if="col.coluna === 'descricao'"
                  class="text-gray-600 dark:text-gray-300 truncate"
                >
                  {{ data.descricao || "-" }}
                </div>

                <!-- Prioridade -->
                <Tag
                  v-else-if="col.coluna === 'prioridade'"
                  :value="getPrioridadeLabel(data.prioridade)"
                  :severity="getPrioridadeSeverity(data.prioridade)"
                  class="font-medium"
                />

                <!-- Departamento -->
                <div
                  v-else-if="col.coluna === 'departamento_responsavel'"
                  class="truncate text-gray-600 dark:text-gray-300"
                >
                  {{ data.departamento_responsavel || "-" }}
                </div>

                <!-- Responsável -->
                <div
                  v-else-if="col.coluna === 'usuario_responsavel'"
                  class="flex items-center gap-2"
                  v-tooltip.top="
                    data.usuario_responsavel?.nome || 'Não atribuído'
                  "
                >
                  <div class="relative flex-shrink-0">
                    <img
                      v-if="data.responsavel_foto"
                      :src="data.responsavel_foto"
                      :alt="data.usuario_responsavel?.nome"
                      class="w-7 h-7 rounded-full object-cover ring-2 ring-indigo-100"
                    />
                    <span
                      v-else
                      class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold"
                    >
                      {{
                        data.usuario_responsavel
                          ? obterIniciais(data.usuario_responsavel.nome)
                          : "NA"
                      }}
                    </span>
                  </div>
                  <span
                    class="max-w-[120px] truncate text-gray-700 dark:text-gray-300 font-medium"
                  >
                    {{ obterNomeSobrenome(data.usuario_responsavel?.nome) }}
                  </span>
                </div>

                <!-- Assunto -->
                <div
                  v-else-if="col.coluna === 'assunto_id'"
                  class="truncate text-gray-600 dark:text-gray-300"
                >
                  <i
                    v-if="mostrarIconesTabela"
                    class="pi pi-tags text-blue-400 mr-1"
                  ></i>
                  {{ data.assunto?.assunto || "Transferido" }}
                </div>

                <!-- Solicitante -->
                <div
                  v-else-if="col.coluna === 'usuario_solicitante'"
                  class="flex items-center gap-2"
                  v-tooltip.top="data.usuario_solicitante?.nome"
                >
                  <div class="relative flex-shrink-0">
                    <img
                      v-if="data.solicitante_foto"
                      :src="data.solicitante_foto"
                      :alt="data.usuario_solicitante?.nome"
                      class="w-7 h-7 rounded-full object-cover ring-2 ring-purple-100"
                    />
                    <span
                      v-else
                      class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-purple-100 text-purple-600 text-xs font-bold"
                    >
                      {{ obterIniciais(data.usuario_solicitante?.nome) }}
                    </span>
                  </div>
                  <span
                    class="max-w-[120px] truncate text-gray-700 dark:text-gray-300 font-medium"
                  >
                    {{ obterNomeSobrenome(data.usuario_solicitante?.nome) }}
                  </span>
                </div>

                <!-- Destino -->
                <template v-else-if="col.coluna === 'usuarios_destino'">
                  <div
                    v-if="
                      data.usuarios_destino && data.usuarios_destino.length > 0
                    "
                    class="truncate text-gray-600 dark:text-gray-300"
                  >
                    {{
                      data.usuarios_destino
                        .map((u) => tratarNome(u.nome))
                        .join(", ")
                    }}
                  </div>
                  <div
                    v-else
                    class="text-gray-400 dark:text-gray-500"
                  >
                    -
                  </div>
                </template>

                <!-- Usuario Origem -->
                <template v-else-if="col.coluna === 'usuario_origem'">
                  <div
                    v-if="data.usuario_origem"
                    class="flex items-center gap-2"
                  >
                    <span
                      class="flex items-center justify-center w-7 h-7 rounded-full bg-gradient-to-br from-purple-500 to-purple-600 text-white text-xs font-semibold shadow-sm"
                    >
                      {{ obterIniciais(data.usuario_origem?.nome) }}
                    </span>
                    <span class="truncate text-gray-700 dark:text-gray-200">
                      {{ obterNomeSobrenome(data.usuario_origem?.nome) }}
                    </span>
                  </div>
                  <div
                    v-else
                    class="text-gray-400 dark:text-gray-500"
                  >
                    -
                  </div>
                </template>

                <!-- Solicitação Pai -->
                <template v-else-if="col.coluna === 'solicitacao_pai_id'">
                  <Tag
                    v-if="data.solicitacao_pai_id"
                    :value="'#' + data.solicitacao_pai_id"
                    severity="info"
                    class="text-xs font-mono"
                  />
                  <span
                    v-else
                    class="text-gray-400 dark:text-gray-500"
                  >
                    -
                  </span>
                </template>

                <!-- Filial -->
                <div
                  v-else-if="col.coluna === 'filial_id'"
                  class="truncate text-gray-600 dark:text-gray-300"
                >
                  {{ data.filial?.codigo }} - {{ data.filial?.fantasia || "-" }}
                </div>

                <!-- UF da Filial -->
                <div
                  v-else-if="col.coluna === 'filial_uf'"
                  class="truncate text-gray-600 dark:text-gray-300"
                >
                  {{ data.filial?.uf || "-" }}
                </div>

                <!-- Cidade da Filial -->
                <div
                  v-else-if="col.coluna === 'filial_cidade'"
                  class="truncate text-gray-600 dark:text-gray-300"
                >
                  {{ data.filial?.cidade || "-" }}
                </div>

                <!-- Criado em -->
                <div
                  v-else-if="col.coluna === 'created_at'"
                  class="flex items-center gap-2"
                >
                  <i
                    v-if="mostrarIconesTabela"
                    class="pi pi-calendar text-blue-400 text-xs"
                  ></i>
                  <span class="text-gray-600 dark:text-gray-300 truncate">
                    {{ formatarData(data.created_at) }}
                  </span>
                </div>

                <!-- Dias Aberto -->
                <span
                  v-else-if="col.coluna === 'dias_aberto'"
                  class="inline-flex items-center gap-1 bg-blue-50 dark:bg-blue-900/30 text-gray-700 dark:text-gray-300 text-xs font-medium px-2 py-1 rounded-full"
                >
                  <i
                    v-if="mostrarIconesTabela"
                    class="pi pi-clock text-blue-400 text-xs"
                  ></i>
                  {{ parseInt(data.dias_aberto) || 0 }} dias
                </span>

                <!-- Última Atualização -->
                <div
                  v-else-if="col.coluna === 'updated_at'"
                  class="flex items-center gap-2"
                >
                  <i
                    v-if="mostrarIconesTabela"
                    class="pi pi-clock text-green-400 text-xs"
                  ></i>
                  <span class="text-gray-600 dark:text-gray-300 truncate">
                    {{ formatarData(data.updated_at) }}
                  </span>
                </div>

                <!-- Situação/Status -->
                <div
                  v-else-if="col.coluna === 'status'"
                  class="flex items-center gap-1.5"
                >
                  <Tag
                    :value="textoStatus(data.status)"
                    :severity="getStatusSeverity(data.status)"
                    class="font-medium"
                  />
                  <!-- Indicador de Atraso -->
                  <i
                    v-if="estaAtrasada(data)"
                    v-tooltip.top="
                      'Atrasada há ' + diasDeAtraso(data) + ' dia(s)'
                    "
                    class="pi pi-clock text-red-500 text-sm animate-pulse"
                  ></i>
                </div>

                <!-- Etapa de Andamento (Timeline Style) -->
                <div
                  v-else-if="col.coluna === 'etapa_atual'"
                  class="flex items-center"
                >
                  <div
                    v-if="data.etapa_atual?.etapa"
                    class="flex items-center gap-2"
                    v-tooltip.top="
                      'Etapa atual: ' + data.etapa_atual.etapa.nome
                    "
                  >
                    <!-- Indicador visual tipo timeline -->
                    <div class="relative flex items-center">
                      <div
                        class="w-2.5 h-2.5 rounded-full ring-2 ring-offset-1 ring-offset-white dark:ring-offset-slate-800 animate-pulse"
                        :style="{
                          backgroundColor:
                            data.etapa_atual.etapa.cor || '#10b981',
                          boxShadow:
                            '0 0 8px ' +
                            (data.etapa_atual.etapa.cor || '#10b981') +
                            '60'
                        }"
                      ></div>
                    </div>
                    <!-- Badge com nome da etapa -->
                    <span
                      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold shadow-sm transition-all duration-200"
                      :style="{
                        backgroundColor:
                          (data.etapa_atual.etapa.cor || '#10b981') + '18',
                        color: data.etapa_atual.etapa.cor || '#10b981',
                        border:
                          '1px solid ' +
                          (data.etapa_atual.etapa.cor || '#10b981') +
                          '35'
                      }"
                    >
                      <i
                        v-if="data.etapa_atual.etapa.icone"
                        :class="data.etapa_atual.etapa.icone"
                        class="text-[10px]"
                      ></i>
                      <i
                        v-else
                        class="fas fa-sitemap text-[10px]"
                      ></i>
                      <span class="truncate max-w-[100px]">
                        {{ data.etapa_atual.etapa.nome }}
                      </span>
                    </span>
                  </div>
                  <span
                    v-else
                    class="text-gray-400 dark:text-gray-500 text-xs italic"
                  >
                    -
                  </span>
                </div>

                <!-- Previsão de Entrega -->
                <div
                  v-else-if="col.coluna === 'previsao_entrega'"
                  class="flex items-center gap-2"
                >
                  <i
                    v-if="mostrarIconesTabela"
                    class="pi pi-calendar-plus text-orange-400 text-xs"
                  ></i>
                  <span class="text-gray-600 dark:text-gray-300">
                    {{
                      data.previsao_entrega
                        ? formatarDataSemHoras(data.previsao_entrega)
                        : "Não definida"
                    }}
                  </span>
                </div>
              </template>
            </Column>
          </DataTable>
        </div>
      </div>

      <!-- Conteúdo da Aba Aprovações - Mobile -->
      <div
        v-if="aba === 'aprovacoes'"
        class="block mt-5 ipad:hidden"
      >
        <div
          v-if="loadingAprovacoes"
          class="flex justify-center items-center py-8 bg-white rounded-md shadow-md"
        >
          <i class="pi pi-spin pi-spinner text-2xl text-blue-500"></i>
          <span class="ml-2 text-gray-600">Carregando aprovações...</span>
        </div>

        <div
          v-else-if="aprovacoesPendentes.length === 0"
          class="text-center py-8 bg-white rounded-md shadow-md"
        >
          <div class="flex flex-col items-center justify-center">
            <i class="pi pi-check-circle text-6xl text-green-500 mb-4"></i>
            <h3 class="text-lg font-semibold text-gray-700 mb-2">
              Nenhuma Aprovação Pendente
            </h3>
            <p class="text-gray-500 text-sm">
              Você não possui aprovações pendentes no momento.
            </p>
          </div>
        </div>

        <div
          v-else
          class="space-y-4"
        >
          <!-- Header da seção - Mobile -->
          <div class="bg-white p-4 rounded-md shadow-md">
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-2">
                <i class="pi pi-list text-lg text-blue-600"></i>
                <h2 class="text-lg font-semibold text-gray-800">
                  Aprovações Pendentes
                </h2>
                <span
                  class="bg-red-100 text-red-800 text-xs font-medium px-2 py-1 rounded-full"
                >
                  {{ totalAprovacoesPendentes }}
                </span>
              </div>
              <button
                @click="buscarAprovacoesPendentes"
                class="flex items-center px-2 py-1 text-xs font-medium text-blue-600 bg-blue-50 rounded-md hover:bg-blue-100 transition-colors"
                title="Atualizar lista de aprovações"
              >
                <i class="pi pi-refresh mr-1"></i>
                Atualizar
              </button>
            </div>
          </div>

          <!-- Lista de Cards - Mobile (1 coluna) -->
          <div class="space-y-4">
            <CardAprovacao
              v-for="aprovacao in aprovacoesPendentes"
              :key="aprovacao.id"
              :aprovacao="aprovacao"
              @aprovar-rapido="aprovarRapido"
              @rejeitar-rapido="rejeitarRapido"
              @abrir-solicitacao="abrirSolicitacaoAprovacao"
            />
          </div>
        </div>
      </div>

      <div
        v-if="solicitações && aba !== 'aprovacoes'"
        class="block mt-5 space-y-4 ipad:hidden px-2"
      >
        <!-- Placeholder mobile quando não encontrar -->
        <div
          v-if="solicitacoesFiltered.length === 0"
          class="flex flex-col items-center justify-center py-12 text-gray-500 bg-white dark:bg-slate-800 rounded-2xl shadow-lg border border-gray-200 dark:border-slate-700"
        >
          <div
            class="w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-4"
          >
            <i
              class="pi pi-search text-3xl text-gray-400 dark:text-gray-500"
            ></i>
          </div>
          <template v-if="buscaPorIdAtiva">
            <p class="text-lg font-bold text-gray-700 dark:text-gray-200">
              Solicitação #{{ filtro.id }} não encontrada
            </p>
            <p
              class="text-sm mt-2 text-gray-500 dark:text-gray-400 text-center px-4"
            >
              A solicitação não existe ou você não tem permissão para
              visualizá-la.
            </p>
          </template>
          <template v-else>
            <p class="text-lg font-bold text-gray-700 dark:text-gray-200">
              Nenhuma solicitação encontrada
            </p>
            <p class="text-sm mt-2 text-gray-500 dark:text-gray-400">
              Tente ajustar os filtros.
            </p>
          </template>
        </div>

        <!-- Cards de Solicitações Mobile -->
        <div
          v-for="(item, index) in solicitacoesFiltered"
          :key="index"
          class="mb-4"
        >
          <div
            class="bg-white dark:bg-slate-800 rounded-2xl shadow-md overflow-hidden border-l-4 transition-all duration-200"
            :class="{
              'border-yellow-400 dark:border-yellow-500':
                item.status === 'pendente',
              'border-blue-400 dark:border-blue-500':
                item.status === 'em atendimento',
              'border-gray-400 dark:border-gray-500':
                item.status === 'atendimento pausado',
              'border-cyan-400 dark:border-cyan-500':
                item.status === 'agendado',
              'border-green-400 dark:border-green-500':
                item.status === 'resolvida' || item.status === 'finalizada',
              'border-red-400 dark:border-red-500':
                item.status === 'resolução recusada' ||
                item.status === 'cancelada',
              'border-orange-400 dark:border-orange-500':
                item.status === 'retorno solicitante'
            }"
          >
            <!-- Header do Card - SEMPRE VISÍVEL -->
            <div
              class="bg-gradient-to-r from-slate-200 to-slate-300 dark:from-slate-600 dark:to-slate-700 p-3 border-b-2 border-gray-300 dark:border-slate-500"
            >
              <div class="flex items-center justify-between">
                <!-- ID e Badge Prioridade -->
                <div class="flex items-center gap-2">
                  <span
                    @click="selecionarSolicitacao(item)"
                    class="inline-flex items-center justify-center px-3 py-1.5 rounded-xl bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 font-bold text-sm border border-indigo-200 dark:border-indigo-700 cursor-pointer hover:bg-indigo-200 dark:hover:bg-indigo-800/40 active:scale-95 transition-all"
                  >
                    #{{ item.id }}
                  </span>
                  <span
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold border"
                    :class="{
                      'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 border-red-300 dark:border-red-700':
                        item.prioridade === 'urgente',
                      'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300 border-yellow-300 dark:border-yellow-700':
                        item.prioridade === 'alta',
                      'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-700':
                        item.prioridade === 'media',
                      'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600':
                        item.prioridade === 'baixa'
                    }"
                  >
                    <i class="pi pi-flag !text-[10px]"></i>
                    {{ item.prioridade?.toUpperCase() }}
                  </span>
                </div>

                <!-- Botão expandir/colapsar -->
                <div
                  @click.stop="toggleCardExpandido(item.id)"
                  class="flex items-center justify-center w-8 h-8 bg-white dark:bg-slate-600 rounded-lg border border-gray-200 dark:border-slate-500 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-500 transition-colors"
                >
                  <i
                    class="pi text-gray-500 dark:text-gray-400 transition-transform duration-300"
                    :class="
                      cardsExpandidos.has(item.id)
                        ? 'pi-chevron-up'
                        : 'pi-chevron-down'
                    "
                  ></i>
                </div>
              </div>
            </div>

            <!-- Solicitante - SEMPRE VISÍVEL -->
            <div
              class="bg-white dark:bg-slate-800 px-4 py-2.5 cursor-pointer border-b border-gray-200 dark:border-slate-600"
              @click="selecionarSolicitacao(item)"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <!-- Avatar Solicitante -->
                  <div class="relative flex-shrink-0">
                    <img
                      v-if="item.solicitante_foto"
                      :src="item.solicitante_foto"
                      :alt="item.usuario_solicitante?.nome"
                      class="w-8 h-8 rounded-full object-cover ring-2 ring-emerald-400 dark:ring-emerald-500 shadow-sm"
                    />
                    <div
                      v-else
                      class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center border-2 border-emerald-300 dark:border-emerald-500 shadow-sm"
                    >
                      <span class="text-white text-[10px] font-bold">
                        {{ obterIniciais(item.usuario_solicitante?.nome) }}
                      </span>
                    </div>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p
                      class="text-[9px] uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-semibold"
                    >
                      Solicitante
                    </p>
                    <p
                      class="text-sm font-bold text-gray-800 dark:text-white truncate"
                    >
                      {{ obterNomeSobrenome(item.usuario_solicitante?.nome) }}
                    </p>
                  </div>
                </div>
                <!-- Dias aberto -->
                <div
                  class="flex items-center gap-1.5 px-2 py-1 bg-gray-100 dark:bg-slate-700 rounded-lg"
                >
                  <i
                    class="pi pi-clock !text-xs text-gray-500 dark:text-gray-400"
                  ></i>
                  <span
                    class="text-xs font-bold text-gray-600 dark:text-gray-300"
                  >
                    {{ parseInt(item.dias_aberto) || 0 }} dias
                  </span>
                </div>
              </div>
            </div>

            <!-- Conteúdo Colapsável -->
            <Transition name="collapse">
              <div
                v-show="cardsExpandidos.has(item.id)"
                class="overflow-hidden"
              >
                <!-- Filial -->
                <div
                  class="px-4 py-2.5 border-b border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800"
                >
                  <div class="flex items-center gap-2">
                    <div
                      class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"
                    >
                      <i
                        class="pi pi-building text-blue-600 dark:text-blue-400 !text-xs"
                      ></i>
                    </div>
                    <div class="min-w-0 flex-1">
                      <p
                        class="text-[9px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold"
                      >
                        Filial
                      </p>
                      <p
                        class="text-sm font-bold text-gray-800 dark:text-white truncate"
                      >
                        {{ item.filial?.codigo }} - {{ item.filial?.fantasia }}
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Departamento e Assunto -->
                <div
                  class="px-4 py-3 border-b-2 border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800"
                >
                  <div class="flex items-start gap-3">
                    <div
                      class="w-10 h-10 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center flex-shrink-0 border border-orange-200 dark:border-orange-700"
                    >
                      <i
                        class="pi pi-folder text-orange-600 dark:text-orange-400 text-sm"
                      ></i>
                    </div>
                    <div class="min-w-0 flex-1">
                      <p
                        class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold"
                      >
                        {{ item.departamento_responsavel }}
                      </p>
                      <p
                        class="text-sm font-bold text-gray-800 dark:text-white truncate mt-0.5"
                      >
                        {{
                          item.assunto != null
                            ? item.assunto.assunto
                            : "Transferido"
                        }}
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Responsável -->
                <div
                  class="px-4 py-2.5 border-b border-gray-200 dark:border-slate-600 bg-blue-50/50 dark:bg-blue-900/10"
                >
                  <div class="flex items-center gap-2">
                    <!-- Avatar com foto ou iniciais - Responsável -->
                    <div class="relative flex-shrink-0">
                      <img
                        v-if="item.responsavel_foto"
                        :src="item.responsavel_foto"
                        :alt="item.usuario_responsavel?.nome"
                        class="w-8 h-8 rounded-full object-cover ring-2 ring-blue-400 dark:ring-blue-500 shadow-md"
                      />
                      <div
                        v-else
                        class="w-8 h-8 rounded-full flex items-center justify-center shadow-md border-2"
                        :class="
                          item.usuario_responsavel
                            ? 'bg-gradient-to-br from-blue-400 to-blue-600 border-blue-300 dark:border-blue-500'
                            : 'bg-gray-200 dark:bg-slate-600 border-gray-300 dark:border-slate-500'
                        "
                      >
                        <span class="text-white text-[10px] font-bold">
                          {{
                            item.usuario_responsavel
                              ? obterIniciais(item.usuario_responsavel?.nome)
                              : "?"
                          }}
                        </span>
                      </div>
                    </div>
                    <div class="min-w-0 flex-1">
                      <p
                        class="text-[9px] uppercase tracking-wider text-blue-600 dark:text-blue-400 font-bold"
                      >
                        Responsável
                      </p>
                      <p
                        class="text-sm font-bold truncate"
                        :class="
                          item.usuario_responsavel
                            ? 'text-gray-800 dark:text-white'
                            : 'text-gray-400 dark:text-gray-500'
                        "
                      >
                        {{
                          item.usuario_responsavel
                            ? obterNomeSobrenome(item.usuario_responsavel?.nome)
                            : "Não atribuído"
                        }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </Transition>

            <!-- Footer - Status - SEMPRE VISÍVEL -->
            <div
              @click="selecionarSolicitacao(item)"
              class="px-4 py-3 flex items-center justify-between border-t-2 cursor-pointer hover:brightness-95 active:scale-[0.99] transition-all"
              :class="{
                'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-300 dark:border-yellow-700':
                  item.status === 'pendente',
                'bg-blue-50 dark:bg-blue-900/20 border-blue-300 dark:border-blue-700':
                  item.status === 'em atendimento',
                'bg-gray-100 dark:bg-gray-700/30 border-gray-300 dark:border-gray-600':
                  item.status === 'atendimento pausado',
                'bg-cyan-50 dark:bg-cyan-900/20 border-cyan-300 dark:border-cyan-700':
                  item.status === 'agendado',
                'bg-green-50 dark:bg-green-900/20 border-green-300 dark:border-green-700':
                  item.status === 'resolvida' || item.status === 'finalizada',
                'bg-red-50 dark:bg-red-900/20 border-red-300 dark:border-red-700':
                  item.status === 'resolução recusada' ||
                  item.status === 'cancelada',
                'bg-orange-50 dark:bg-orange-900/20 border-orange-300 dark:border-orange-700':
                  item.status === 'retorno solicitante'
              }"
            >
              <div class="flex items-center gap-2">
                <div
                  class="w-8 h-8 rounded-xl flex items-center justify-center border-2"
                  :class="{
                    'bg-yellow-100 dark:bg-yellow-800/40 border-yellow-400 dark:border-yellow-600':
                      item.status === 'pendente',
                    'bg-blue-100 dark:bg-blue-800/40 border-blue-400 dark:border-blue-600':
                      item.status === 'em atendimento',
                    'bg-gray-200 dark:bg-gray-600/40 border-gray-400 dark:border-gray-500':
                      item.status === 'atendimento pausado',
                    'bg-cyan-100 dark:bg-cyan-800/40 border-cyan-400 dark:border-cyan-600':
                      item.status === 'agendado',
                    'bg-green-100 dark:bg-green-800/40 border-green-400 dark:border-green-600':
                      item.status === 'resolvida' ||
                      item.status === 'finalizada',
                    'bg-red-100 dark:bg-red-800/40 border-red-400 dark:border-red-600':
                      item.status === 'resolução recusada' ||
                      item.status === 'cancelada',
                    'bg-orange-100 dark:bg-orange-800/40 border-orange-400 dark:border-orange-600':
                      item.status === 'retorno solicitante'
                  }"
                >
                  <i
                    :class="getStatusVisual(item.status).icon"
                    :style="{ color: getStatusVisual(item.status).color }"
                    class="text-sm"
                  ></i>
                </div>
                <span
                  class="text-sm font-bold uppercase tracking-wide"
                  :class="{
                    'text-yellow-700 dark:text-yellow-300':
                      item.status === 'pendente',
                    'text-blue-700 dark:text-blue-300':
                      item.status === 'em atendimento',
                    'text-gray-600 dark:text-gray-300':
                      item.status === 'atendimento pausado',
                    'text-cyan-700 dark:text-cyan-300':
                      item.status === 'agendado',
                    'text-green-700 dark:text-green-300':
                      item.status === 'resolvida' ||
                      item.status === 'finalizada',
                    'text-red-700 dark:text-red-300':
                      item.status === 'resolução recusada' ||
                      item.status === 'cancelada',
                    'text-orange-700 dark:text-orange-300':
                      item.status === 'retorno solicitante'
                  }"
                >
                  {{ textoStatus(item.status) }}
                </span>
                <!-- Indicador de Atraso (Mobile) -->
                <i
                  v-if="estaAtrasada(item)"
                  v-tooltip.top="
                    'Atrasada há ' + diasDeAtraso(item) + ' dia(s)'
                  "
                  class="pi pi-clock text-red-500 text-sm animate-pulse ml-1"
                ></i>
              </div>

              <!-- Etapa de Andamento (se existir) -->
              <div
                v-if="item.etapa_atual?.etapa"
                class="flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-semibold"
                :style="{
                  backgroundColor: item.etapa_atual.etapa.cor + '20',
                  color: item.etapa_atual.etapa.cor,
                  borderColor: item.etapa_atual.etapa.cor + '40'
                }"
                style="border-width: 1px; border-style: solid"
                v-tooltip.top="
                  'Etapa de Andamento: ' + item.etapa_atual.etapa.nome
                "
              >
                <i
                  :class="item.etapa_atual.etapa.icone || 'pi pi-circle'"
                  class="text-[10px]"
                ></i>
                <span class="truncate max-w-[80px]">
                  {{ item.etapa_atual.etapa.nome }}
                </span>
              </div>

              <!-- Data de criação -->
              <span
                class="text-xs text-gray-500 dark:text-gray-400 font-semibold bg-white dark:bg-slate-700 px-2 py-1 rounded-lg border border-gray-200 dark:border-slate-600"
              >
                {{ formatarDataSemHoras(item.created_at) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Paginação Mobile Melhorada -->
        <div
          class="mt-6 bg-white dark:bg-slate-800 rounded-2xl shadow-lg border-2 border-gray-200 dark:border-slate-600 p-4"
        >
          <div class="flex items-center justify-between gap-3">
            <!-- Botão Anterior -->
            <button
              @click="filtro.pagina > 1 && (filtro.pagina--, getSolicitacoes())"
              :disabled="filtro.pagina <= 1"
              class="flex items-center justify-center w-10 h-10 rounded-xl border-2 transition-all duration-200"
              :class="
                filtro.pagina <= 1
                  ? 'bg-gray-100 dark:bg-slate-700 border-gray-200 dark:border-slate-600 text-gray-400 dark:text-gray-500 cursor-not-allowed'
                  : 'bg-white dark:bg-slate-700 border-blue-500 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 active:scale-95'
              "
            >
              <i class="pi pi-chevron-left font-bold"></i>
            </button>

            <!-- Info de Página -->
            <div class="flex items-center gap-2 flex-1 justify-center">
              <span
                class="text-sm text-gray-600 dark:text-gray-400 font-medium"
              >
                Página
              </span>
              <span
                class="inline-flex items-center justify-center min-w-[40px] h-9 px-3 bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 font-bold text-sm rounded-xl border border-indigo-200 dark:border-indigo-700"
              >
                {{ filtro.pagina }}
              </span>
              <span
                class="text-sm text-gray-600 dark:text-gray-400 font-medium"
              >
                de
              </span>
              <span class="text-sm text-gray-800 dark:text-gray-200 font-bold">
                {{ solicitações?.paginacao?.paginas || 1 }}
              </span>
            </div>

            <!-- Botão Próximo -->
            <button
              @click="
                filtro.pagina < (solicitações?.paginacao?.paginas || 1) &&
                (filtro.pagina++, getSolicitacoes())
              "
              :disabled="
                filtro.pagina >= (solicitações?.paginacao?.paginas || 1)
              "
              class="flex items-center justify-center w-10 h-10 rounded-xl border-2 transition-all duration-200"
              :class="
                filtro.pagina >= (solicitações?.paginacao?.paginas || 1)
                  ? 'bg-gray-100 dark:bg-slate-700 border-gray-200 dark:border-slate-600 text-gray-400 dark:text-gray-500 cursor-not-allowed'
                  : 'bg-white dark:bg-slate-700 border-blue-500 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 active:scale-95'
              "
            >
              <i class="pi pi-chevron-right font-bold"></i>
            </button>
          </div>

          <!-- Itens por página -->
          <div
            class="flex items-center justify-center gap-2 mt-3 pt-3 border-t border-gray-200 dark:border-slate-600"
          >
            <span class="text-xs text-gray-500 dark:text-gray-400">
              Exibir:
            </span>
            <div class="flex gap-1">
              <button
                v-for="qtd in [5, 10, 20, 50]"
                :key="qtd"
                @click="alterarItensPorPagina(qtd)"
                class="px-2.5 py-1 text-xs font-bold rounded-lg border transition-all"
                :class="
                  filtro.porPagina === qtd
                    ? 'bg-blue-500 text-white border-blue-500'
                    : 'bg-white dark:bg-slate-700 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-slate-500 hover:border-blue-400'
                "
              >
                {{ qtd }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      class="absolute text-white transition-all duration-500 ease-in-out border rounded-md top-5 right-5"
      :class="[toast ? 'bg-green-500 opacity-100 p-2  ' : 'opacity-0']"
    >
      {{ successMessage }}
    </div>

    <Dialog
      v-model:visible="dialogSolicitacao"
      modal
      class="!bg-transparent !border-0 !shadow-none"
    >
      <template #container>
        <Solicitacao
          :solicitacao_id="solicitacaoSelecionada.id"
          :permissoes="props.permissoes"
          :auth="props.auth"
          :abaInicial="solicitacaoSelecionada.abaInicial"
          :aprovacaoIdRejeitar="solicitacaoSelecionada.aprovacaoIdRejeitar"
          @fecharDialogo="dialogSolicitacao = false"
          @atualizar="atualizarDados"
        ></Solicitacao>
      </template>
    </Dialog>

    <Dialog
      v-model:visible="dialogFuncionario"
      modal
      header="Escolha um funcionário"
      class="ipad:w-[500px]"
    >
      <InputGroup>
        <InputText
          fluid
          v-model="termoFuncionario"
          @keypress.enter="buscarFuncionario()"
          placeholder="Digite a matricula ou nome"
        ></InputText>
        <Button
          @click="buscarFuncionario()"
          icon="pi pi-search"
        ></Button>
      </InputGroup>

      <div
        v-if="listaFuncionarios.length"
        class="p-2 m-1 overflow-auto h-96"
      >
        <div
          v-for="func in listaFuncionarios"
          class=""
        >
          <div
            @click="adicionarFunc(func)"
            class="flex p-1 space-x-1 border-b cursor-pointer hover:bg-orange-950 hover:text-white"
          >
            <div class="w-12">
              {{ func.matricula }}
            </div>
            <div class="truncate w-60">
              {{ func.nome }}
            </div>
          </div>
        </div>
      </div>
    </Dialog>

    <Dialog
      v-model:visible="dialogAgendamento"
      modal
      position="top"
      header="Agendamento"
      class="w-[95vw] sm:w-[90vw] md:w-[80vw] lg:w-[900px] max-w-4xl !mt-8"
      :pt="{
        root: { class: '!rounded-2xl dark:!bg-slate-800' },
        header: {
          class:
            '!rounded-t-2xl !pb-3 !border-b !border-gray-100 dark:!border-slate-700 dark:!bg-slate-800 dark:!text-white'
        },
        content: { class: '!pt-4 dark:!bg-slate-800' },
        mask: { class: '!backdrop-blur-sm' }
      }"
    >
      <Agendamento
        @atualizar="atualizaAgendamentos"
        :solicitacoes="solicitacoesAgend"
      ></Agendamento>
    </Dialog>

    <!-- Dialog Configurar Tabela -->
    <Dialog
      v-model:visible="dialogConfig"
      modal
      position="top"
      :closable="false"
      :pt="{
        root: {
          class:
            '!border-0 !rounded-2xl overflow-hidden w-[95vw] sm:w-[380px] !bg-transparent !shadow-none'
        },
        header: { class: 'hidden' },
        content: { class: '!p-0' },
        mask: { class: 'backdrop-blur-sm' }
      }"
    >
      <div
        class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden shadow-2xl"
      >
        <!-- Header Personalizado -->
        <div class="bg-gradient-to-r from-emerald-500 to-teal-600 px-5 py-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
              >
                <i class="pi pi-table text-white text-lg"></i>
              </div>
              <div>
                <h3 class="text-lg font-bold text-white">Configurar Tabela</h3>
                <p class="text-white/80 text-xs">
                  Escolha quais colunas exibir
                </p>
              </div>
            </div>
            <Button
              @click="dialogConfig = false"
              icon="pi pi-times"
              rounded
              outlined
              severity="secondary"
              class="!w-9 !h-9 !border-white/30 !text-white hover:!bg-white/20"
            />
          </div>
        </div>

        <!-- Conteúdo -->
        <div class="p-5">
          <!-- Toggle de Ícones -->
          <div
            class="flex items-center justify-between p-3 mb-4 rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
            @click="mostrarIconesTabelaTemp = !mostrarIconesTabelaTemp"
          >
            <div class="flex items-center gap-3">
              <i
                class="pi pi-sparkles text-lg"
                :class="
                  mostrarIconesTabelaTemp ? 'text-emerald-500' : 'text-gray-400'
                "
              ></i>
              <div>
                <span
                  class="text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                  Exibir ícones nas colunas
                </span>
                <p class="text-xs text-gray-400 dark:text-gray-500">
                  Ícones decorativos ao lado dos valores
                </p>
              </div>
            </div>
            <Checkbox
              v-model="mostrarIconesTabelaTemp"
              :binary="true"
              class="pointer-events-none"
            />
          </div>

          <!-- Separator -->
          <div
            class="border-b border-slate-200 dark:border-slate-600 mb-3"
          ></div>

          <div
            class="max-h-[50vh] overflow-y-auto space-y-2.5 pr-1 custom-scrollbar"
          >
            <template
              v-for="(col, index) in colunasTemp"
              :key="index"
            >
              <div
                v-if="!colunasOcultas.includes(col.coluna)"
                class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer group"
                @click="col.ativarColuna = !col.ativarColuna"
              >
                <Checkbox
                  v-model="col.ativarColuna"
                  :binary="true"
                  :inputId="'col-' + index"
                  class="pointer-events-none"
                />
                <label
                  :for="'col-' + index"
                  class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors flex-1"
                >
                  {{ deParaColunas(col.coluna) }}
                </label>
                <i
                  class="pi text-xs transition-all"
                  :class="
                    col.ativarColuna
                      ? 'pi-eye text-emerald-500'
                      : 'pi-eye-slash text-gray-400'
                  "
                ></i>
              </div>
            </template>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-5 pb-5 flex gap-3">
          <Button
            label="Resetar"
            icon="pi pi-refresh"
            severity="secondary"
            outlined
            class="!rounded-xl flex-1"
            @click="limparVisualizacao"
          />
          <Button
            label="Salvar"
            icon="pi pi-check"
            severity="success"
            class="!rounded-xl flex-1 !bg-gradient-to-r !from-emerald-500 !to-teal-600 !border-0"
            @click="salvarVisualizacao"
          />
        </div>
      </div>
    </Dialog>
  </AuthenticatedLayout>
</template>

<style scoped>
/* Transição Collapse para cards mobile */
.collapse-enter-active,
.collapse-leave-active {
  transition: all 0.3s ease;
  max-height: 500px;
  opacity: 1;
}

.collapse-enter-from,
.collapse-leave-to {
  max-height: 0;
  opacity: 0;
}
</style>
