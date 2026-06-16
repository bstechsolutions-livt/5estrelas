<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { Head } from "@inertiajs/vue3"
import { onMounted, onUnmounted, ref, watch, computed, nextTick } from "vue"
import axios from "axios"
import {
  Select,
  Textarea,
  InputText,
  RadioButton,
  InputGroup,
  Button,
  PickList,
  Calendar,
  DatePicker,
  MultiSelect,
  InputMask,
  InputNumber,
  Dialog,
  Checkbox,
  Panel,
  IconField,
  InputIcon
} from "primevue"
import {
  deleteFile,
  swalErro,
  swalSucesso,
  swalValidacoes,
  tratarNome,
  uploadFile,
  downloadFile,
  toastSuccess
} from "@/utils/globalFunctions"
import Loader from "@/Components/Loader.vue"
import InputMoney2 from "@/Components/New/inputMoney2.vue"
import BsFile from "@/ComponentsV2/BsFile.vue"
import BsDepartamento from "@/ComponentsV2/BsDepartamento.vue"
import BsFilial from "@/ComponentsV2/BsFilial.vue"

// Marcar como página nova
layout.paginaNova.value = true

const props = defineProps([
  "departamentos",
  "filiais",
  "filialUsuario",
  "solicitante",
  "aplicacao",
  "dptos",
  "bancos",
  "moedas",
  "centrosCusto"
])

const solicitacao = ref({
  titulo: "",
  descricao: "",
  departamento: "",
  assunto: null,
  responsavel: null,
  filial: null,
  prioridade: "baixa",
  respostasSelect: [],
  arquivos: [],
  equipamentos: [],
  rotinas: [],
  usuariosDestino: [],
  usuarioOrigem: null,
  dadosLiberacao: [],
  vendasPendentes: [],
  departamentoSelect: "",
  filialSelect: ""
})

const loading = ref(null)
const criandoSolicitacao = ref(false) // Estado para controlar o botão de criar ticket
const textoBotao = ref("Solicitar") // Texto dinâmico do botão
const termoFuncionario = ref("")
const dialogFuncionario = ref(false)
const listaFuncionarios = ref([])
const buscouFuncionario = ref(false)
const tipoFuncionario = ref()
const dialogGenerico = ref(false)
const headerDialog = ref("")
const pickList = ref([[], []])
const pickListOriginal = ref([])
const campoSelecionado = ref(null)
const termo = ref("")
const termoTarget = ref("")
const preenchendoViaUrl = ref(false)
const pickItemSelecionadoSource = ref(null)
const pickItemSelecionadoTarget = ref(null)
const isMobile = ref(window.innerWidth < 768)
const vendaSelecionada = ref(null)
const obsSelecionada = ref("")
const dialogObs = ref(false)

const equipamentos = ref([])
const dadosLiberacao = ref([
  { nome: "Filiais", dados: [], situacao: false },
  { nome: "Departamentos", dados: [], situacao: false },
  { nome: "Bancos", dados: [], situacao: false },
  { nome: "Moedas", dados: [], situacao: false },
  { nome: "Centros de Custo", dados: [], situacao: false }
])

// Dados dos campos pré-definidos Winthor #3196
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

const vendasPendentes = ref([
  {
    filial: null,
    caixas: [],
    valor: 0,
    operador: null,
    data: null
  }
])

const rotinaTmp = ref("")

// Computed para filtrar departamentos baseado nas liberações
const departamentosPermitidos = computed(() => {
  const usuarioLogado = props.solicitante

  return props.departamentos
    .filter((departamento) => {
      // Filtrar assuntos que o usuário pode acessar
      const assuntosPermitidos = departamento.assuntos.filter((assunto) => {
        return podeAcessarAssunto(assunto, usuarioLogado)
      })

      // Manter departamento se tem pelo menos um assunto permitido
      if (assuntosPermitidos.length > 0) {
        // Criar uma cópia do departamento com apenas os assuntos permitidos
        return {
          ...departamento,
          assuntos: assuntosPermitidos
        }
      }

      return false
    })
    .map((departamento) => {
      // Filtrar assuntos que o usuário pode acessar
      const assuntosPermitidos = departamento.assuntos.filter((assunto) => {
        return podeAcessarAssunto(assunto, usuarioLogado)
      })

      // Retornar departamento com assuntos filtrados
      return {
        ...departamento,
        assuntos: assuntosPermitidos
      }
    })
    .sort((a, b) => a.condicao1.localeCompare(b.condicao1))
})

// #12173 - Computed para verificar se um campo condicional deve ser exibido
function campoDeveSerExibido(select) {
  // Se não tem campo pai, sempre exibir
  if (!select.campo_pai_id) return true

  // Converter para número para comparação consistente
  const campoPaiId = Number(select.campo_pai_id)

  // Buscar o campo pai para obter suas opções
  const campoPai = solicitacao.value.assunto?.selects?.find(
    (s) => Number(s.id) === campoPaiId
  )

  // Buscar resposta do campo pai
  const respostaPai = solicitacao.value.respostasSelect?.find(
    (r) => Number(r.selecao_id) === campoPaiId
  )

  // Se não há resposta do pai, não exibir o campo filho
  if (!respostaPai) return false

  // Verificar se a resposta corresponde ao valor condicional
  const valorResposta = respostaPai.resposta

  // Tratar diferentes formatos de resposta
  if (typeof valorResposta === "object" && valorResposta !== null) {
    // Para selects com formato {code, label}
    if (valorResposta.label) {
      return valorResposta.label === select.valor_condicional
    }
    if (Array.isArray(valorResposta)) {
      // Array de codes numéricos (MultiSelect) — resolver labels via opções do pai
      if (campoPai?.valores && Array.isArray(campoPai.valores)) {
        return valorResposta.some((code) => {
          // Se o item já é string, comparar diretamente
          if (typeof code === "string") {
            return code === select.valor_condicional
          }
          // Se é objeto com label, usar label
          if (typeof code === "object" && code?.label) {
            return code.label === select.valor_condicional
          }
          // Se é numérico (code), buscar o label correspondente nas opções do pai
          const opcao = campoPai.valores.find(
            (v) => v.code === code || Number(v.code) === Number(code)
          )
          return opcao?.label === select.valor_condicional
        })
      }
      // Fallback sem opções do pai
      return valorResposta.some(
        (v) =>
          (typeof v === "string" ? v : v?.label) === select.valor_condicional
      )
    }
  }

  // Se valorResposta é um número (code), buscar o label correspondente nas opções do campo pai
  if (campoPai?.valores && Array.isArray(campoPai.valores)) {
    const opcaoSelecionada = campoPai.valores.find(
      (v) =>
        v.code === valorResposta || Number(v.code) === Number(valorResposta)
    )
    if (opcaoSelecionada) {
      return opcaoSelecionada.label === select.valor_condicional
    }
  }

  // Comparação direta (caso já seja string ou outro formato)
  return valorResposta === select.valor_condicional
}

// #12173 - Computed para filtrar campos configuráveis visíveis
const selectsVisiveis = computed(() => {
  if (!solicitacao.value.assunto?.selects) return []

  return solicitacao.value.assunto.selects.filter((select) => {
    // Primeiro verificar se é para exibir na nova ticket
    if (select.exibir_nova !== "S") return false

    // Depois verificar condição do campo pai
    return campoDeveSerExibido(select)
  })
})

// #12173 - Computed separados por tipo (para manter compatibilidade com template existente)
const selectsVisivelSemArquivo = computed(() => {
  return selectsVisiveis.value.filter((s) => s.tipo !== "arquivo")
})

const selectsVisivelArquivo = computed(() => {
  return selectsVisiveis.value.filter((s) => s.tipo === "arquivo")
})

function podeAcessarAssunto(assunto, usuario) {
  // Se não tem liberações configuradas, libera para todos
  if (!assunto.liberacoes || assunto.liberacoes.length === 0) {
    return true
  }

  // Verifica se usuário está nas liberações
  return assunto.liberacoes.some((liberacao) => {
    if (liberacao.tipo === "filial") {
      // Se a filial do usuário for null, considerar como filial 2
      const filialUsuario = usuario.filial || props.filialUsuario || 2

      return (
        liberacao.valor == filialUsuario ||
        liberacao.valor == props.filialUsuario
      )
    }
    if (liberacao.tipo === "funcionario") {
      return liberacao.valor == usuario.matricula
    }
    if (liberacao.tipo === "areaatuacao") {
      return liberacao.valor == usuario.departamento
    }
    return false
  })
}

onMounted(async function () {
  await getEquipamentos()

  // Carregar todos os dados Winthor de uma vez #3196
  carregarDadosWinthor("depto_compras")
  carregarDadosWinthor("depto_funcionario")
  carregarDadosWinthor("filial_winthor")
  carregarDadosWinthor("funcao")
  carregarDadosWinthor("regional")

  // Listener para atualizar isMobile ao redimensionar
  window.addEventListener("resize", handleResize)

  // Atribuir filial do usuário como selecionada (se existir)
  var filialUsuario = props.filialUsuario
  var filiais = props.filiais

  // Verificar se o usuário tem filial 99 ou 0 se for transforma para filial 2
  if (filialUsuario == 99 || filialUsuario == 0) {
    filialUsuario = 2
  }

  // Verificar se a filialUsuário está na lista de filiais
  var filialEncontrada = filiais.find((f) => f.codigo == filialUsuario)

  // Se a filialUsuário não esta na lista de filiais
  if (filialEncontrada) {
    solicitacao.value.filial = filialEncontrada
  } else {
    solicitacao.value.filial = filiais[0]
  }

  // Pré-preencher via query params (ex: vindo do Dashboard RH)
  const urlParams = new URLSearchParams(window.location.search)
  const paramDepto = urlParams.get("departamento")
  const paramAssunto = urlParams.get("assunto")
  const paramTitulo = urlParams.get("titulo")

  if (paramDepto) {
    const depto = departamentosPermitidos.value.find(
      (d) => d.condicao1 === paramDepto
    )
    if (depto) {
      preenchendoViaUrl.value = true
      solicitacao.value.departamento = depto
      if (paramAssunto) {
        // Aguardar watch do departamento executar, depois setar assunto
        setTimeout(() => {
          const assunto = depto.assuntos.find((a) => a.id == paramAssunto)
          if (assunto) solicitacao.value.assunto = assunto
          preenchendoViaUrl.value = false
        }, 100)
      } else {
        preenchendoViaUrl.value = false
      }
    }
  }
  if (paramTitulo) {
    solicitacao.value.titulo = decodeURIComponent(paramTitulo)
  }
})

onUnmounted(() => {
  window.removeEventListener("resize", handleResize)
})

function handleResize() {
  isMobile.value = window.innerWidth < 768
}

function atualizarLista(arquivos) {
  solicitacao.value.arquivos = arquivos
}

watch(
  () => solicitacao.value.assunto,
  (newValue) => {
    // Atualiza somente os campos necessários
    if (newValue) {
      solicitacao.value.responsavel = solicitacao.value.assunto.responsavel

      if (solicitacao.value.assunto.prioridade) {
        solicitacao.value.prioridade = solicitacao.value.assunto.prioridade
      } else {
        solicitacao.value.prioridade = "baixa"
      }
    }
  },
  { deep: true }
)

watch(
  () => solicitacao.value.departamento,
  () => {
    if (preenchendoViaUrl.value) return
    if (solicitacao.value.departamento.assuntos.length == 1) {
      solicitacao.value.assunto = solicitacao.value.departamento.assuntos[0]
    } else {
      solicitacao.value.assunto = null
    }
  },
  { deep: true }
)

// Função para download de arquivos modelo
async function downloadFileById(fileId) {
  try {
    await downloadFile(fileId)
  } catch (error) {
    console.error("Erro ao fazer download:", error)
    swalErro("Erro ao fazer download do arquivo")
  }
}

async function mudarPrioridade(prioridade) {
  if (solicitacao.value.assunto.prioridade) {
    return
  }

  solicitacao.value.prioridade = prioridade
}

function limparCamposNaoHabilitados() {
  if (!solicitacao.value.assunto) return

  if (!habilitarCampo("titulo")) solicitacao.value.titulo = ""
  if (!habilitarCampo("descricao")) solicitacao.value.descricao = ""
  if (!habilitarCampo("equipamentos")) solicitacao.value.equipamentos = []
  if (!habilitarCampo("rotinas")) solicitacao.value.rotinas = []
  if (!habilitarCampo("usuarios destino"))
    solicitacao.value.usuariosDestino = []
  if (!habilitarCampo("usuario origem")) solicitacao.value.usuarioOrigem = null
  if (!habilitarCampo("dados acesso")) solicitacao.value.dadosLiberacao = []
  if (!habilitarCampo("vendas pendentes"))
    solicitacao.value.vendasPendentes = []
  if (!habilitarCampo("arquivos")) solicitacao.value.arquivos = []
}

async function criarSolicitacao() {
  // Previne múltiplos cliques enquanto está processando
  if (criandoSolicitacao.value) {
    return
  }

  criandoSolicitacao.value = true
  loading.value = true
  textoBotao.value = "Processando..."

  try {
    // Realizar validação de obrigatoriedade dos campos
    var erros = []

    if (!solicitacao.value.filial) {
      erros.push("Filial é obrigatória")
    }

    if (!solicitacao.value.titulo && habilitarObrigatorio("titulo")) {
      erros.push("Título é obrigatório")
    }

    if (!solicitacao.value.descricao && habilitarObrigatorio("descricao")) {
      erros.push("Descrição é obrigatória")
    }

    if (!solicitacao.value.filialSelect && habilitarObrigatorio("filial")) {
      erros.push("Seleção de Filial é obrigatória")
    }

    if (
      !solicitacao.value.departamentoSelect &&
      habilitarObrigatorio("departamento")
    ) {
      erros.push("Seleção de Departamento é obrigatória")
    }

    if (
      solicitacao.value.arquivos.length < 1 &&
      habilitarObrigatorio("arquivos")
    ) {
      erros.push("É obrigatório anexo de pelo menos um arquivo")
    }

    if (
      habilitarObrigatorio("rotinas") &&
      solicitacao.value.rotinas.length < 1
    ) {
      erros.push("É obrigatório incluir pelo menos uma rotina")
    }

    if (
      habilitarObrigatorio("rotinas") &&
      solicitacao.value.rotinas.length < 1
    ) {
      erros.push("É obrigatório incluir pelo menos uma rotina")
    }

    if (habilitarObrigatorio("dados acesso")) {
      solicitacao.value.dadosLiberacao = dadosLiberacao.value.filter(
        (d) => d.situacao && d.dados.length > 0
      )

      if (solicitacao.value.dadosLiberacao.length < 1) {
        erros.push("É obrigatório incluir pelo menos um tipo de liberação")
      }
    }

    if (
      habilitarObrigatorio("usuario origem") &&
      !solicitacao.value.usuarioOrigem
    ) {
      erros.push("É obrigatório incluir usuário de origem")
    }

    if (habilitarObrigatorio("vendas pendentes")) {
      if (vendasPendentes.value.filter((d) => !d.filial).length > 0) {
        erros.push("É obrigatório incluir filial de todas as vendas")
      }

      if (vendasPendentes.value.filter((d) => d.caixas.length < 1).length > 0) {
        erros.push("É obrigatório incluir os caixas de todas as vendas")
      }

      if (vendasPendentes.value.filter((d) => !d.data).length > 0) {
        erros.push("É obrigatório incluir a data de todas as vendas")
      }

      solicitacao.value.vendasPendentes = vendasPendentes.value

      if (solicitacao.value.length < 1) {
        erros.push(
          "É obrigatório incluir pelo menos informações para uma venda"
        )
      }
    }

    if (habilitarObrigatorio("equipamentos")) {
      if (solicitacao.value.equipamentos.length < 1) {
        erros.push("É obrigatório incluir pelo menos um equipamento")
      }

      if (
        solicitacao.value.equipamentos.filter((d) => d.quantidade < 1).length >
        0
      ) {
        erros.push(
          "É obrigatório incluir pelo menos uma quantidade dos equipamentos escolhidos"
        )
      }

      if (
        solicitacao.value.equipamentos.filter(
          (d) => !d.observacao && d.nome == "Outros"
        ).length > 0
      ) {
        erros.push(
          'É obrigatório incluir a observação quando equipamentos é tipo "Outros"'
        )
      }
    }

    solicitacao.value.vendasPendentes = vendasPendentes.value.filter(
      (venda) => venda.filial && venda.caixas.length > 0 && venda.data
    )

    if (solicitacao.value.length < 1) {
      erros.push("É obrigatório incluir pelo menos informações para uma venda")
    }

    if (
      habilitarObrigatorio("usuarios destino") &&
      solicitacao.value.usuariosDestino.length < 1
    ) {
      erros.push("É obrigatório incluir pelo menos um usuário de destino")
    }

    //Verificar selects obrigatorios
    // #12173 - Usar selectsVisiveis para considerar campos condicionais
    selectsVisiveis.value.forEach((element) => {
      if (element.obrigatorio == "S") {
        const resp = solicitacao.value.respostasSelect?.filter(
          (i) => i.selecao_id == element.id
        )

        // Campos de arquivo são validados separadamente abaixo
        if (element.tipo !== "arquivo") {
          if (
            resp.length == 0 ||
            resp.some((r) => {
              if (element.tipo == "texto") {
                return r.resposta == null || r.resposta.trim() === ""
              }
              return r.resposta == null
            })
          ) {
            erros.push(
              `É obrigatório ${element.tipo == "texto" ? "preencher" : "selecionar um valor para"} o campo: "${element.label}"`
            )
          }
        }

        if (element.tipo == "data" && element.tipo_data == "range") {
          resp.forEach((r) => {
            if (r.resposta.datas[1] == null) {
              erros.push(
                `Você precisa adicionar a data final para o campo de período: "${element.label}"`
              )
            }
          })
        }

        if (element.tipo == "arquivo") {
          const respArquivo = solicitacao.value.respostasSelect?.filter(
            (i) => i.selecao_id == element.id
          )

          // Se não há nenhuma resposta para este campo de arquivo, é erro
          if (!respArquivo || respArquivo.length === 0) {
            erros.push(
              `Você precisa anexar um arquivo para o campo: "${element.label}"`
            )
          } else {
            respArquivo.forEach((r) => {
              // Verificar arquivos (array) - formato atual
              if (!r.resposta.arquivos || r.resposta.arquivos.length === 0) {
                erros.push(
                  `Você precisa anexar um arquivo para o campo: "${element.label}"`
                )
              }
            })
          }
        }
      }
    })

    // Testar se aconteceu algum erro
    if (erros.length > 0) {
      swalValidacoes("Realize os ajustes abaixo", erros)
      return
    }

    // Validar tamanho dos arquivos antes de fazer upload (limite: 50MB)
    const TAMANHO_MAXIMO = 50 * 1024 * 1024 // 50MB em bytes
    const arquivosGrandes = []

    for (let arquivo of solicitacao.value.arquivos) {
      if (arquivo.file && arquivo.file.size > TAMANHO_MAXIMO) {
        const tamanhoMB = (arquivo.file.size / (1024 * 1024)).toFixed(2)
        arquivosGrandes.push(`"${arquivo.file.name}" (${tamanhoMB} MB)`)
      }
    }

    // Validar arquivos dos selects de tipo arquivo também
    const selectsArquivoValidacao =
      solicitacao.value.assunto?.selects?.filter((s) => s.tipo == "arquivo") ||
      []
    for (let select of selectsArquivoValidacao) {
      const respostaSelect = solicitacao.value.respostasSelect?.find(
        (r) => r.selecao_id == select.id
      )
      if (respostaSelect?.resposta?.arquivos) {
        for (let arquivo of respostaSelect.resposta.arquivos) {
          if (arquivo.file && arquivo.file.size > TAMANHO_MAXIMO) {
            const tamanhoMB = (arquivo.file.size / (1024 * 1024)).toFixed(2)
            arquivosGrandes.push(`"${arquivo.file.name}" (${tamanhoMB} MB)`)
          }
        }
      }
    }

    if (arquivosGrandes.length > 0) {
      swalErro(
        "Arquivo muito grande",
        `O tamanho máximo permitido por arquivo é 50 MB. Os seguintes arquivos excedem o limite: ${arquivosGrandes.join(", ")}`
      )
      return
    }

    // Não aconteceu nenhum erro, salvar arquivos que foram informados na abertura
    var responseFile = null

    for (let arquivo of solicitacao.value.arquivos) {
      // Zerar variável para não pegar lixo
      responseFile = null

      // Criar um nome aleatório para não sobrescrever
      const sufixoAleatorio = Math.floor(Math.random() * 1000)
      const nome = "solicitacao_arq_" + sufixoAleatorio

      // Salvar arquivo
      responseFile = await uploadFile(
        arquivo.file,
        props.aplicacao,
        "solicitacao-arquivos",
        nome
      )

      if (responseFile.success) {
        arquivo.fileTab = responseFile.data.file
      } else {
        swalErro(
          "Erro no upload",
          `Não foi possível enviar o arquivo "${arquivo.file?.name || "desconhecido"}". Verifique o tamanho do arquivo e tente novamente.`
        )
        return
      }
    }
    limparCamposNaoHabilitados()

    // buscar select de tipo arquivo
    const selectsArquivo = solicitacao.value.assunto.selects.filter(
      (s) => s.tipo == "arquivo"
    )

    // Salvar arquivos dos selects de tipo arquivo (suporta múltiplos)
    for (let select of selectsArquivo) {
      const respostaSelect = solicitacao.value.respostasSelect.find(
        (r) => r.selecao_id == select.id
      )

      if (
        respostaSelect &&
        respostaSelect.resposta.arquivos &&
        respostaSelect.resposta.arquivos.length > 0
      ) {
        // Array para guardar os IDs dos arquivos salvos
        const fileIds = []

        // Fazer upload de cada arquivo
        for (let arquivo of respostaSelect.resposta.arquivos) {
          // Criar um nome aleatório para não sobrescrever
          const sufixoAleatorio = Math.floor(Math.random() * 1000)
          const nome = "solicitacao_arq_" + sufixoAleatorio

          // Salvar arquivo
          const responseFile = await uploadFile(
            arquivo.file,
            props.aplicacao,
            "solicitacao-arquivos",
            nome
          )

          if (responseFile.success) {
            fileIds.push(responseFile.data.file.id)
          } else {
            swalErro(
              "Erro no upload",
              `Não foi possível enviar o arquivo "${arquivo.file?.name || "desconhecido"}". Verifique o tamanho do arquivo e tente novamente.`
            )
            return
          }
        }

        // Atribuir os IDs dos arquivos à resposta
        respostaSelect.resposta.file_ids = fileIds

        // Remove os arquivos do objeto para não enviar dados desnecessários
        delete respostaSelect.resposta.arquivos
      }
    }

    // Criar parametros de envio
    const params = {
      titulo: solicitacao.value.titulo,
      descricao: solicitacao.value.descricao,
      departamento_responsavel: solicitacao.value.departamento.condicao1,
      prioridade: solicitacao.value.prioridade,
      filial_id: solicitacao.value.filial.codigo,
      arquivos: solicitacao.value.arquivos.map((arquivo) => arquivo.fileTab.id),
      assunto_id: solicitacao.value.assunto.id,
      usuario_responsavel: solicitacao.value.responsavel,
      rotinas: solicitacao.value.rotinas,
      dadosLiberacao: solicitacao.value.dadosLiberacao,
      usuarioOrigem: solicitacao.value.usuarioOrigem,
      infoVendas: solicitacao.value.vendasPendentes,
      equipamentos: solicitacao.value.equipamentos,
      usuariosDestino: solicitacao.value.usuariosDestino,
      arquivos: solicitacao.value.arquivos,
      selects: solicitacao.value.assunto.selects,
      respostas: solicitacao.value.respostasSelect,
      departamento: solicitacao.value.departamentoSelect,
      filial: solicitacao.value.filialSelect
        ? solicitacao.value.filialSelect.codigo +
          " - " +
          solicitacao.value.filialSelect.fantasia
        : null
    }

    // Criar ticket
    await axios
      .post("/solicitacoes/nova/criar", params)
      .then(async (res) => {
        // Remover apenas o loading geral, manter o botão bloqueado
        loading.value = false

        // Buscar Assunto para verificar se precisa redirecionar
        var assuntoSelecionado = solicitacao.value.departamento.assuntos.find(
          (ass) => ass.id === solicitacao.value.assunto.id
        )

        // Exibir toast de sucesso
        toastSuccess("Ticket criado com sucesso!")

        // Atualizar texto do botão para indicar redirecionamento
        textoBotao.value = "Redirecionando..."

        // Fazer redirecionamento após o SweetAlert (botão continua bloqueado)
        if (assuntoSelecionado.redirect) {
          window.location.href = `/solicitacoes/nova?departamento=${solicitacao.value.departamento.condicao1}&assunto=${assuntoSelecionado.id}`
        } else {
          // Redirecionar para página Minhas e abrir modal da ticket criada
          window.location.href = `/solicitacoes/minhas?solicitacao=${res.data.solicitacao_id}`
        }
      })
      .catch((err) => {
        // Se for erro de duplicata (código 409), mostra mensagem específica
        if (err.response?.status === 409) {
          swalErro(
            "Ticket Duplicada",
            err.response.data.mensagem ||
              "Esta ticket já foi criada recentemente."
          )
        }
        // Se for erro de rate limiting (código 429), mostra mensagem específica
        else if (err.response?.status === 429) {
          swalErro(
            "Muitas Tentativas",
            err.response.data.mensagem ||
              "Por favor, aguarde antes de tentar novamente."
          )
        } else {
          swalErro(
            "Erro",
            err.response?.data?.mensagem ||
              "Ocorreu um erro ao processar sua ticket."
          )
        }

        // Excluir arquivos se der algum erro, pra não ficar lixo no sistema
        for (let arquivo of solicitacao.value.arquivos) {
          if (arquivo.fileTab) {
            deleteFile(arquivo.fileTab.id)
          }
        }
      })
  } catch (error) {
    console.error("Erro ao criar ticket:", error)

    // Detectar se é erro de arquivo grande (413 Payload Too Large)
    if (error?.response?.status === 413) {
      swalErro(
        "Arquivo muito grande",
        "O arquivo enviado excede o tamanho máximo permitido (50 MB). Reduza o tamanho do arquivo e tente novamente."
      )
    } else if (
      error?.message?.toLowerCase().includes("network") ||
      error?.code === "ERR_NETWORK"
    ) {
      swalErro(
        "Erro de rede",
        "Ocorreu um erro de conexão, possivelmente o arquivo é muito grande. Verifique o tamanho dos arquivos (máximo 50 MB) e sua conexão."
      )
    } else {
      swalErro(
        "Erro inesperado ao criar ticket",
        "Ocorreu um erro inesperado. Tente novamente!"
      )
    }
  } finally {
    // Só restaura o estado se ainda não foi processado com sucesso
    // (em caso de sucesso, o loading geral já foi removido e o botão deve permanecer bloqueado)
    if (loading.value) {
      loading.value = false
      criandoSolicitacao.value = false
      textoBotao.value = "Solicitar"
    }
  }
}

function habilitarObrigatorio(campo) {
  if (solicitacao.value.assunto) {
    var campoSel = solicitacao.value.assunto.campos.find(
      (c) => c.descricao == campo
    )

    if (campoSel.obrigatorio) {
      return true
    } else {
      return false
    }
  } else {
    return false
  }
}

function habilitarCampo(campo) {
  if (solicitacao.value.assunto) {
    var campoSel = solicitacao.value.assunto.campos.find(
      (c) => c.descricao == campo
    )

    if (campoSel.ativo) {
      return true
    } else {
      return false
    }
  } else {
    return false
  }
}

function obsCampo(campo) {
  if (solicitacao.value.assunto) {
    var campoSel = solicitacao.value.assunto.campos.find(
      (c) => c.descricao == campo
    )
    return campoSel.observacao
  } else {
    return ""
  }
}

// Função para obter o tipo do campo (texto ou selecao)
function tipoCampo(campo) {
  if (solicitacao.value.assunto) {
    var campoSel = solicitacao.value.assunto.campos.find(
      (c) => c.descricao == campo
    )
    return campoSel?.tipo || "texto"
  } else {
    return "texto"
  }
}

// Função para obter as opções do campo titulo (quando tipo = selecao)
function opcoesTitulo() {
  if (solicitacao.value.assunto) {
    var campoSel = solicitacao.value.assunto.campos.find(
      (c) => c.descricao == "titulo"
    )
    return campoSel?.opcoes_titulo || []
  } else {
    return []
  }
}

function showDialogObs(obs) {
  obsSelecionada.value = obs
  dialogObs.value = true
}

function moverEquipamento(equipamento) {
  solicitacao.value.equipamentos.push(equipamento)
}

function removerEquipamento(equipamento) {
  const index = solicitacao.value.equipamentos.indexOf(equipamento)
  if (index !== -1) {
    solicitacao.value.equipamentos.splice(index, 1)
  }
}

async function setRotina() {
  loading.value = true

  await axios
    .post("/util/info-rotina", {
      codigo: rotinaTmp.value
    })
    .then((res) => {
      solicitacao.value.rotinas.push(res.data.dados)
      rotinaTmp.value = ""
    })
    .catch((err) => {
      swalErro("Opss...", err.response.data.mensagem)
    })

  loading.value = false
}

function removerRotina(rotina) {
  const index = solicitacao.value.rotinas.indexOf(rotina)
  if (index !== -1) {
    solicitacao.value.rotinas.splice(index, 1)
  }
}

function showBuscaFunc(tipo) {
  tipoFuncionario.value = tipo
  termoFuncionario.value = ""
  dialogFuncionario.value = true
  listaFuncionarios.value = []
  buscouFuncionario.value = false
}

async function buscarFuncionario() {
  loading.value = true
  buscouFuncionario.value = false

  if (!termoFuncionario.value || termoFuncionario.value == "") {
    listaFuncionarios.value = []
  } else {
    await axios
      .post("/util/usuarios", { termo: termoFuncionario.value })
      .then((res) => {
        listaFuncionarios.value = res.data.dados
      })
      .catch((err) => {
        // Se for 404 (nenhum usuário encontrado), apenas limpa a lista sem mostrar erro
        if (err.response?.status === 404) {
          listaFuncionarios.value = []
        } else {
          swalErro(err.response?.data?.mensagem || "Erro ao buscar funcionário")
        }
      })
  }

  buscouFuncionario.value = true
  loading.value = false
}

function adicionarFunc(func) {
  if (tipoFuncionario.value == "destino") {
    solicitacao.value.usuariosDestino.push(func)
  } else if (tipoFuncionario.value == "operador") {
    vendasPendentes.value[vendaSelecionada.value].operador = func
  } else {
    solicitacao.value.usuarioOrigem = func
  }
  dialogFuncionario.value = false
  termoFuncionario.value = ""
  listaFuncionarios.value = []
}

function removerFuncionario(func, tipo) {
  if (tipo == "destino") {
    const index = solicitacao.value.usuariosDestino.indexOf(func)
    if (index !== -1) {
      solicitacao.value.usuariosDestino.splice(index, 1)
    }
  } else {
    solicitacao.value.usuarioOrigem = null
  }
}

function showDialogGenerico(campo) {
  campoSelecionado.value = campo
  termo.value = ""
  termoTarget.value = ""
  pickItemSelecionadoSource.value = null
  pickItemSelecionadoTarget.value = null
  if (campo.nome == "Filiais") {
    headerDialog.value = "Filiais"

    // Primeiramente, atribue o conteúdo a pickList[0] com as filiais que vêm de props

    // Agora, atribua o conteúdo a pickList[1], excluindo as filiais que já estão no [0]
    pickList.value[1] = [
      ...(solicitacao.value.dadosLiberacao.find((d) => d.nome == "Filiais")
        ?.dados || [])
    ]

    pickList.value[0] = [...props.filiais].filter(
      (c) => !pickList.value[1].includes(c)
    )
  }

  if (campo.nome == "Departamentos") {
    headerDialog.value = "Departamentos"

    // Primeiramente, atribue o conteúdo a pickList[0] com as filiais que vêm de props

    // Agora, atribua o conteúdo a pickList[1], excluindo as filiais que já estão no [0]
    pickList.value[1] = [
      ...(solicitacao.value.dadosLiberacao.find(
        (d) => d.nome == "Departamentos"
      )?.dados || [])
    ]

    pickList.value[0] = [...props.dptos].filter(
      (c) => !pickList.value[1].includes(c)
    )
  }

  if (campo.nome == "Bancos") {
    headerDialog.value = "Bancos"

    // Primeiramente, atribue o conteúdo a pickList[0] com as filiais que vêm de props

    // Agora, atribua o conteúdo a pickList[1], excluindo as filiais que já estão no [0]
    pickList.value[1] = [
      ...(solicitacao.value.dadosLiberacao.find((d) => d.nome == "Bancos")
        ?.dados || [])
    ]

    pickList.value[0] = [...props.bancos].filter(
      (c) => !pickList.value[1].includes(c)
    )
  }

  if (campo.nome == "Moedas") {
    headerDialog.value = "Moedas"

    // Primeiramente, atribue o conteúdo a pickList[0] com as filiais que vêm de props

    // Agora, atribua o conteúdo a pickList[1], excluindo as filiais que já estão no [0]
    pickList.value[1] = [
      ...(solicitacao.value.dadosLiberacao.find((d) => d.nome == "Moedas")
        ?.dados || [])
    ]

    pickList.value[0] = [...props.moedas].filter(
      (c) => !pickList.value[1].includes(c)
    )
  }

  if (campo.nome == "Centros de Custo") {
    headerDialog.value = "Centros de Custo"

    // Primeiramente, atribue o conteúdo a pickList[0] com as filiais que vêm de props

    // Agora, atribua o conteúdo a pickList[1], excluindo as filiais que já estão no [0]
    pickList.value[1] = [
      ...(solicitacao.value.dadosLiberacao.find(
        (d) => d.nome == "Centros de Custo"
      )?.dados || [])
    ]

    pickList.value[0] = [...props.centrosCusto].filter(
      (c) => !pickList.value[1].includes(c)
    )
  }

  pickListOriginal.value = pickList.value[0]

  dialogGenerico.value = true
}

function salvarDialogGenerico() {
  campoSelecionado.value.dados = pickList.value[1]
  solicitacao.value.dadosLiberacao.push(campoSelecionado.value)

  dialogGenerico.value = false
}

function atualizarPick() {
  const termoNormalizado = termo.value.toLowerCase()

  // Verifica qual é o header atual e filtra os dados baseados no termo de busca
  pickList.value[0] = pickListOriginal.value.filter((item) => {
    switch (headerDialog.value) {
      case "Filiais":
        return (
          (item.codigo?.toString() || "").includes(termoNormalizado) ||
          (item.fantasia?.toLowerCase() || "").includes(termoNormalizado)
        )
      case "Departamentos":
        return (
          (item.codepto?.toString() || "").includes(termoNormalizado) ||
          (item.descricao?.toLowerCase() || "").includes(termoNormalizado)
        )
      case "Bancos":
        return (
          (item.codbanco?.toString() || "").includes(termoNormalizado) ||
          (item.nome?.toLowerCase() || "").includes(termoNormalizado)
        )
      case "Moedas":
        return (
          (item.codmoeda?.toString() || "").includes(termoNormalizado) ||
          (item.moeda?.toLowerCase() || "").includes(termoNormalizado)
        )
      case "Centros de Custo":
        return (
          (item.codigocentrocusto?.toString() || "").includes(
            termoNormalizado
          ) || (item.descricao?.toLowerCase() || "").includes(termoNormalizado)
        )
      default:
        return true
    }
  })
}

// Computed para filtrar a lista target do PickList
const pickListTargetFiltrado = computed(() => {
  if (!pickList.value[1]) return []
  if (!termoTarget.value) return pickList.value[1]

  const termoNormalizado = termoTarget.value.toLowerCase()

  return pickList.value[1].filter((item) => {
    switch (headerDialog.value) {
      case "Filiais":
        return (
          (item.codigo?.toString() || "").includes(termoNormalizado) ||
          (item.fantasia?.toLowerCase() || "").includes(termoNormalizado)
        )
      case "Departamentos":
        return (
          (item.codepto?.toString() || "").includes(termoNormalizado) ||
          (item.descricao?.toLowerCase() || "").includes(termoNormalizado)
        )
      case "Bancos":
        return (
          (item.codbanco?.toString() || "").includes(termoNormalizado) ||
          (item.nome?.toLowerCase() || "").includes(termoNormalizado)
        )
      case "Moedas":
        return (
          (item.codmoeda?.toString() || "").includes(termoNormalizado) ||
          (item.moeda?.toLowerCase() || "").includes(termoNormalizado)
        )
      case "Centros de Custo":
        return (
          (item.codigocentrocusto?.toString() || "").includes(
            termoNormalizado
          ) || (item.descricao?.toLowerCase() || "").includes(termoNormalizado)
        )
      default:
        return true
    }
  })
})

// Função para mover item para a lista de selecionados (target)
function moverParaTarget() {
  if (!pickItemSelecionadoSource.value) return

  const index = pickList.value[0].findIndex(
    (item) => item === pickItemSelecionadoSource.value
  )
  if (index !== -1) {
    const [item] = pickList.value[0].splice(index, 1)
    pickList.value[1].push(item)

    // Também remover do original para não aparecer novamente ao filtrar
    const indexOriginal = pickListOriginal.value.findIndex((i) => i === item)
    if (indexOriginal !== -1) {
      pickListOriginal.value.splice(indexOriginal, 1)
    }

    pickItemSelecionadoSource.value = null
  }
}

// Função para mover item de volta para a lista disponíveis (source)
function moverParaSource() {
  if (!pickItemSelecionadoTarget.value) return

  const index = pickList.value[1].findIndex(
    (item) => item === pickItemSelecionadoTarget.value
  )
  if (index !== -1) {
    const [item] = pickList.value[1].splice(index, 1)
    pickList.value[0].push(item)
    pickListOriginal.value.push(item)

    pickItemSelecionadoTarget.value = null
  }
}

// Funções para selecionar itens no PickList customizado
function selecionarItemSource(item) {
  pickItemSelecionadoSource.value = item
  pickItemSelecionadoTarget.value = null
}

function selecionarItemTarget(item) {
  pickItemSelecionadoTarget.value = item
  pickItemSelecionadoSource.value = null
}

function adicionarVendaPendente() {
  vendasPendentes.value.push({
    filial: null,
    caixas: [],
    valor: 0,
    operador: null,
    data: null
  })
}

const removerVenda = (index) => {
  if (vendasPendentes.value.length > 1) {
    vendasPendentes.value.splice(index, 1)
  }
}

const showDialogOperador = (venda) => {
  tipoFuncionario.value = "operador"
  vendaSelecionada.value = venda
  dialogFuncionario.value = true
}

async function getEquipamentos() {
  loading.value = true
  axios
    .get("/solicitacoes/configuracoes/buscar-equipamentos")
    .then(async (res) => {
      // { nome: 'Monitor', quantidade: 1, operacao: null, observacao: '' },

      const resposta = await res.data
      resposta.map((resp) => {
        equipamentos.value.push({
          nome: resp.equipamento,
          quantidade: 1,
          operacao: null,
          observacao: ""
        })
      })
    })
    .catch((err) => {
      console.error(err)
    })
  loading.value = false
}

/**
 * Calcula a data mínima permitida para campos de data com prazo mínimo configurado.
 * @param {Object} select - Campo de seleção do tipo 'data'
 * @returns {Date|null} Data mínima ou null se não houver restrição
 */
function calcularDataMinima(select) {
  if (!select.dias_minimos) return null
  const data = new Date()
  data.setDate(data.getDate() + Number(select.dias_minimos))
  data.setHours(0, 0, 0, 0)
  return data
}

function addResposta(select, valor) {
  const idx = solicitacao.value.respostasSelect.findIndex(
    (r) => r.selecao_id === select.id
  )

  let resposta

  // Tipos pré-definidos Winthor #3196
  const tiposWinthor = [
    "depto_compras",
    "depto_funcionario",
    "filial_winthor",
    "funcao",
    "regional"
  ]

  if (tiposWinthor.includes(select.tipo)) {
    // Para tipos Winthor, converter o value para label
    const dados = dadosWinthor.value[select.tipo]
    if (Array.isArray(valor)) {
      // Múltipla seleção - pegar os labels
      resposta = valor.map((v) => {
        const item = dados.find((d) => d.value === v)
        return item ? item.label : v
      })
    } else {
      // Seleção única - pegar o label
      const item = dados.find((d) => d.value === valor)
      resposta = item ? item.label : valor
    }
  } else if (select.tipo === "selecao") {
    resposta = valor // valor normal de select
  } else if (select.tipo === "cnpj") {
    resposta = valor // valor do CNPJ digitado
  } else if (select.tipo === "data") {
    let datas

    if (select.tipo_data === "range") {
      datas = valor?.map((v) => (v ? v.toISOString().split("T")[0] : null))
    } else {
      datas = [valor ? valor.toISOString().split("T")[0] : null]
    }

    resposta = {
      datas: datas
    }
  } else if (select.tipo === "texto" || select.tipo === "numero") {
    resposta = valor // valor do texto digitado
  } else if (select.tipo === "arquivo") {
    // Suporta múltiplos arquivos
    resposta = {
      arquivos: valor, // array de arquivos selecionados
      file_ids: [] // file_ids serão preenchidos após upload
    }
  }

  const novaResposta = {
    assunto_id: select.assunto_id,
    selecao_id: select.id,
    resposta: resposta
  }

  if (idx !== -1) {
    solicitacao.value.respostasSelect[idx] = novaResposta
  } else {
    solicitacao.value.respostasSelect.push(novaResposta)
  }
}

function atualizarArqSelect(arquivos, select) {
  // Recebe array de arquivos do BsFile
  addResposta(select, arquivos)
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

// Função para obter nome e sobrenome
function obterNomeSobrenome(nome) {
  if (!nome) return "N/A"
  const partes = nome
    .trim()
    .split(" ")
    .filter((p) => p.length > 0)
  if (partes.length === 0) return "N/A"
  if (partes.length === 1) return partes[0]
  return `${partes[0]} ${partes[partes.length - 1]}`
}

// Função para gerar cor do avatar baseado no nome
function getAvatarColor(nome) {
  if (!nome) return "bg-gray-400"
  const colors = [
    "bg-gradient-to-br from-blue-400 to-blue-600",
    "bg-gradient-to-br from-emerald-400 to-emerald-600",
    "bg-gradient-to-br from-violet-400 to-violet-600",
    "bg-gradient-to-br from-rose-400 to-rose-600",
    "bg-gradient-to-br from-amber-400 to-amber-600",
    "bg-gradient-to-br from-cyan-400 to-cyan-600",
    "bg-gradient-to-br from-pink-400 to-pink-600",
    "bg-gradient-to-br from-indigo-400 to-indigo-600",
    "bg-gradient-to-br from-teal-400 to-teal-600",
    "bg-gradient-to-br from-fuchsia-400 to-fuchsia-600"
  ]
  let hash = 0
  for (let i = 0; i < nome.length; i++) {
    hash = nome.charCodeAt(i) + ((hash << 5) - hash)
  }
  return colors[Math.abs(hash) % colors.length]
}
</script>

<template>
  <Head title="Novo Ticket" />

  <AuthenticatedLayout>
    <!-- Loading Overlay -->
    <div
      v-if="loading"
      class="fixed inset-0 z-50 flex items-center justify-center bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm"
    >
      <div class="flex flex-col items-center gap-4">
        <i class="pi pi-spin pi-spinner text-5xl text-blue-600"></i>
        <span class="text-lg font-medium text-gray-600 dark:text-gray-300">
          Processando...
        </span>
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
          <span>Tickets</span>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span
            class="text-gray-950 dark:text-white font-bold truncate max-w-[120px] sm:max-w-none"
          >
            Novo Ticket
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
          Novo Ticket
        </h2>
      </div>
      <span
        class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium pl-4"
      >
        Preencha os campos abaixo para criar uma nova ticket.
      </span>
    </div>

    <div class="space-y-6">
      <!-- Card Solicitante e Seleções Principais -->
      <Panel
        toggleable
        :collapsed="false"
        class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-md overflow-hidden"
      >
        <template #header>
          <div class="flex items-center gap-3">
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex-shrink-0"
            >
              <i
                class="pi pi-user text-blue-600 dark:text-blue-400 text-lg"
              ></i>
            </span>
            <div>
              <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                Dados do Ticket
              </h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Informações do solicitante e seleção de departamento
              </p>
            </div>
          </div>
        </template>

        <div class="space-y-4">
          <!-- Linha 1: Solicitante, Departamento Solicitante, Filial -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="flex flex-col gap-1">
              <label
                class="text-sm font-semibold text-gray-700 dark:text-gray-300"
              >
                Solicitante
                <span class="text-red-500">*</span>
              </label>
              <InputText
                maxlength="200"
                v-model="props.solicitante.nome"
                disabled
                class="w-full"
              />
            </div>

            <div class="flex flex-col gap-1">
              <label
                class="text-sm font-semibold text-gray-700 dark:text-gray-300"
              >
                Departamento Solicitante
                <span class="text-red-500">*</span>
              </label>
              <InputText
                maxlength="200"
                v-model="props.solicitante.departamento"
                disabled
                class="w-full"
              />
            </div>

            <div class="flex flex-col gap-1">
              <label
                class="text-sm font-semibold text-gray-700 dark:text-gray-300"
              >
                Filial
                <span class="text-red-500">*</span>
              </label>
              <Select
                v-model="solicitacao.filial"
                placeholder="Selecione uma filial"
                class="w-full"
                :options="props.filiais"
                option-label="fantasia"
                filter
                :filter-fields="['codigo', 'fantasia']"
              >
                <template #option="slot">
                  <div class="flex items-center gap-2">
                    <span class="font-medium">{{ slot.option.codigo }}</span>
                    <span class="text-gray-500 dark:text-gray-400">-</span>
                    <span>{{ slot.option.fantasia }}</span>
                  </div>
                </template>
              </Select>
            </div>
          </div>

          <!-- Linha 2: Departamento Responsável, Assunto, Responsável -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="flex flex-col gap-1">
              <label
                class="text-sm font-semibold text-gray-700 dark:text-gray-300"
              >
                Departamento Responsável
                <span class="text-red-500">*</span>
              </label>
              <Select
                v-model="solicitacao.departamento"
                dusk="nova-departamento"
                placeholder="Selecione um departamento"
                :options="departamentosPermitidos"
                option-label="condicao1"
                class="w-full"
              ></Select>
            </div>

            <div class="flex flex-col gap-1">
              <label
                class="text-sm font-semibold text-gray-700 dark:text-gray-300"
              >
                Assunto
                <span class="text-red-500">*</span>
              </label>
              <Select
                v-model="solicitacao.assunto"
                dusk="nova-assunto"
                placeholder="Selecione um assunto"
                :disabled="!solicitacao.departamento"
                :options="solicitacao.departamento.assuntos"
                option-label="assunto"
                class="w-full"
              ></Select>
            </div>

            <div class="flex flex-col gap-1">
              <label
                class="text-sm font-semibold text-gray-700 dark:text-gray-300"
              >
                Responsável
              </label>
              <Select
                class="w-full"
                v-model="solicitacao.responsavel"
                disabled
                :options="solicitacao.departamento.responsaveis"
                option-label="nome"
                option-value="matricula"
                placeholder="Nenhum responsável"
              ></Select>
            </div>
          </div>
        </div>
      </Panel>

      <div
        v-if="solicitacao.assunto"
        class="space-y-6"
      >
        <!-- Instruções do Assunto -->
        <div
          v-if="solicitacao.assunto.instrucoes"
          class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl p-5 shadow-sm"
        >
          <div class="flex items-center gap-3 mb-4">
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex-shrink-0"
            >
              <i
                class="pi pi-info-circle text-blue-600 dark:text-blue-400 text-lg"
              ></i>
            </span>
            <h3 class="text-lg font-bold text-blue-800 dark:text-blue-300">
              Instruções
            </h3>
          </div>
          <div
            class="prose max-w-none text-gray-700 dark:text-gray-300 break-words"
            v-html="solicitacao.assunto.instrucoes"
          ></div>
        </div>

        <!-- Modelos do Assunto -->
        <div
          v-if="
            solicitacao.assunto.modelos &&
            solicitacao.assunto.modelos.length > 0
          "
          class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-2xl p-5 shadow-sm"
        >
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
              <span
                class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex-shrink-0"
              >
                <i
                  class="pi pi-file text-emerald-600 dark:text-emerald-400 text-lg"
                ></i>
              </span>
              <h3
                class="text-lg font-bold text-emerald-800 dark:text-emerald-300"
              >
                Modelos Disponíveis para Download
              </h3>
            </div>
            <span
              class="text-xs shrink-0 font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/50 px-3 py-1 rounded-full"
            >
              {{ solicitacao.assunto.modelos.length }} arquivo(s)
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div
              v-for="modelo in solicitacao.assunto.modelos"
              :key="modelo.id"
              class="group flex items-center justify-between p-3 bg-white dark:bg-slate-800 border border-emerald-200 dark:border-emerald-700 rounded-xl hover:shadow-md hover:border-emerald-300 dark:hover:border-emerald-600 transition-all duration-200"
            >
              <div class="flex items-center flex-1 min-w-0">
                <div
                  class="flex-shrink-0 w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center mr-3"
                >
                  <i
                    class="pi pi-file-o text-emerald-600 dark:text-emerald-400"
                  ></i>
                </div>
                <div class="min-w-0 flex-1">
                  <span
                    class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate block"
                    :title="modelo.arquivo.original_name"
                  >
                    {{ modelo.arquivo.original_name }}
                  </span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">
                    Clique para baixar
                  </span>
                </div>
              </div>
              <Button
                @click="downloadFileById(modelo.arquivo.id)"
                icon="pi pi-download"
                severity="success"
                outlined
                rounded
                class="ml-2"
                title="Baixar modelo"
              />
            </div>

            <div
              class="mt-4 p-3 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl"
            >
              <p
                class="text-sm text-emerald-700 dark:text-emerald-300 flex items-center gap-2"
              >
                <i class="pi pi-lightbulb"></i>
                <strong>Dica:</strong>
                Baixe os modelos para preencher corretamente sua ticket
              </p>
            </div>
          </div>
        </div>

        <!-- Campos Configuráveis (Selects) -->
        <div
          v-if="selectsVisivelSemArquivo.length > 0"
          class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-5 shadow-md"
        >
          <div class="flex items-center gap-3 mb-4">
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900/30 flex-shrink-0"
            >
              <i
                class="pi pi-sliders-h text-purple-600 dark:text-purple-400 text-lg"
              ></i>
            </span>
            <div>
              <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                Campos Adicionais
              </h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Preencha os campos específicos deste assunto
              </p>
            </div>
          </div>
          <div
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"
          >
            <template
              v-for="select in selectsVisivelSemArquivo"
              :key="'select-' + select.id"
            >
              <!-- Colaborador - ocupa largura total -->
              <div
                v-if="select.tipo == 'colaborador'"
                class="col-span-full flex flex-col gap-2"
              >
                <div class="flex items-center justify-between">
                  <label
                    class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
                  >
                    <i class="pi pi-users text-cyan-500"></i>
                    {{ select.label }}
                    <span
                      v-if="select.obrigatorio == 'S'"
                      class="text-red-500"
                    >
                      *
                    </span>
                    <button
                      v-if="select.observacao"
                      @click="showDialogObs(select.observacao)"
                      class="inline-flex items-center justify-center w-5 h-5 text-xs text-white bg-blue-500 hover:bg-blue-600 rounded-full transition-colors"
                      :title="select.observacao"
                    >
                      i
                    </button>
                  </label>
                  <Button
                    @click="showBuscaFunc('destino')"
                    icon="pi pi-search"
                    label="Buscar Colaborador"
                    outlined
                    severity="info"
                    size="small"
                  />
                </div>
                <div class="min-h-[80px]">
                  <div
                    v-if="solicitacao.usuariosDestino.length == 0"
                    class="flex flex-col items-center justify-center h-20 bg-gray-50 dark:bg-slate-700/50 rounded-lg border-2 border-dashed border-gray-200 dark:border-slate-600"
                  >
                    <i
                      class="pi pi-user-plus text-xl text-gray-300 dark:text-gray-500 mb-1"
                    ></i>
                    <span class="text-xs text-gray-400 dark:text-gray-500">
                      Nenhum colaborador adicionado
                    </span>
                  </div>
                  <div
                    v-else
                    class="flex flex-wrap gap-2"
                  >
                    <div
                      v-for="func in solicitacao.usuariosDestino"
                      :key="func.matricula"
                      class="group flex items-center gap-2 px-3 py-2 bg-gray-50 dark:bg-slate-700/50 rounded-lg border border-gray-200 dark:border-slate-600 hover:border-cyan-300"
                    >
                      <div class="relative flex-shrink-0">
                        <img
                          v-if="func.foto"
                          :src="func.foto"
                          :alt="func.nome"
                          class="h-7 w-7 rounded-full object-cover"
                        />
                        <div
                          v-else
                          :class="[
                            'h-7 w-7 rounded-full flex items-center justify-center text-white font-bold text-xs',
                            getAvatarColor(func.nome)
                          ]"
                        >
                          {{ obterIniciais(func.nome) }}
                        </div>
                      </div>
                      <span
                        class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate max-w-[150px]"
                        v-tooltip.top="func.nome"
                      >
                        {{ obterNomeSobrenome(func.nome) }}
                      </span>
                      <button
                        @click="removerFuncionario(func, 'destino')"
                        class="w-5 h-5 flex items-center justify-center rounded-full text-gray-400 hover:text-red-500 transition-colors"
                      >
                        <i class="pi pi-times text-xs"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Outros campos - layout normal -->
              <div
                v-else
                class="flex flex-col gap-1"
              >
                <label
                  class="text-sm font-semibold text-gray-700 dark:text-gray-300"
                >
                  {{ select.label }}
                  <span
                    v-if="select.obrigatorio == 'S'"
                    class="text-red-500"
                  >
                    *
                  </span>
                </label>

                <template v-if="select.tipo == 'selecao'">
                  <MultiSelect
                    v-if="select.multiplo == 'S'"
                    class="w-full"
                    :options="select.valores"
                    option-value="code"
                    option-label="label"
                    placeholder="Nenhum Selecionado"
                    filter
                    @update:model-value="(val) => addResposta(select, val)"
                  />
                  <Select
                    v-else
                    class="w-full"
                    :options="select.valores"
                    option-value="code"
                    option-label="label"
                    placeholder="Nenhum Selecionado"
                    show-clear
                    @update:model-value="(val) => addResposta(select, val)"
                  />
                </template>

                <!-- Campos pré-definidos Winthor #3196 -->
                <!-- Depto de Compras -->
                <template v-else-if="select.tipo == 'depto_compras'">
                  <MultiSelect
                    v-if="select.multiplo == 'S'"
                    class="w-full"
                    :options="dadosWinthor.depto_compras"
                    option-value="value"
                    option-label="label"
                    placeholder="Selecione..."
                    filter
                    :loading="loadingWinthor.depto_compras"
                    @show="carregarDadosWinthor('depto_compras')"
                    @update:model-value="(val) => addResposta(select, val)"
                  />
                  <Select
                    v-else
                    class="w-full"
                    :options="dadosWinthor.depto_compras"
                    option-value="value"
                    option-label="label"
                    placeholder="Selecione..."
                    show-clear
                    filter
                    :loading="loadingWinthor.depto_compras"
                    @show="carregarDadosWinthor('depto_compras')"
                    @update:model-value="(val) => addResposta(select, val)"
                  />
                </template>

                <!-- Depto de Funcionário -->
                <template v-else-if="select.tipo == 'depto_funcionario'">
                  <MultiSelect
                    v-if="select.multiplo == 'S'"
                    class="w-full"
                    :options="dadosWinthor.depto_funcionario"
                    option-value="value"
                    option-label="label"
                    placeholder="Selecione..."
                    filter
                    :loading="loadingWinthor.depto_funcionario"
                    @show="carregarDadosWinthor('depto_funcionario')"
                    @update:model-value="(val) => addResposta(select, val)"
                  />
                  <Select
                    v-else
                    class="w-full"
                    :options="dadosWinthor.depto_funcionario"
                    option-value="value"
                    option-label="label"
                    placeholder="Selecione..."
                    show-clear
                    filter
                    :loading="loadingWinthor.depto_funcionario"
                    @show="carregarDadosWinthor('depto_funcionario')"
                    @update:model-value="(val) => addResposta(select, val)"
                  />
                </template>

                <!-- Filial Winthor -->
                <template v-else-if="select.tipo == 'filial_winthor'">
                  <MultiSelect
                    v-if="select.multiplo == 'S'"
                    class="w-full"
                    :options="dadosWinthor.filial_winthor"
                    option-value="value"
                    option-label="label"
                    placeholder="Selecione..."
                    filter
                    :loading="loadingWinthor.filial_winthor"
                    @show="carregarDadosWinthor('filial_winthor')"
                    @update:model-value="(val) => addResposta(select, val)"
                  />
                  <Select
                    v-else
                    class="w-full"
                    :options="dadosWinthor.filial_winthor"
                    option-value="value"
                    option-label="label"
                    placeholder="Selecione..."
                    show-clear
                    filter
                    :loading="loadingWinthor.filial_winthor"
                    @show="carregarDadosWinthor('filial_winthor')"
                    @update:model-value="(val) => addResposta(select, val)"
                  />
                </template>

                <!-- Função -->
                <template v-else-if="select.tipo == 'funcao'">
                  <MultiSelect
                    v-if="select.multiplo == 'S'"
                    class="w-full"
                    :options="dadosWinthor.funcao"
                    option-value="value"
                    option-label="label"
                    placeholder="Selecione..."
                    filter
                    :loading="loadingWinthor.funcao"
                    @show="carregarDadosWinthor('funcao')"
                    @update:model-value="(val) => addResposta(select, val)"
                  />
                  <Select
                    v-else
                    class="w-full"
                    :options="dadosWinthor.funcao"
                    option-value="value"
                    option-label="label"
                    placeholder="Selecione..."
                    show-clear
                    filter
                    :loading="loadingWinthor.funcao"
                    @show="carregarDadosWinthor('funcao')"
                    @update:model-value="(val) => addResposta(select, val)"
                  />
                </template>

                <!-- Regional -->
                <template v-else-if="select.tipo == 'regional'">
                  <MultiSelect
                    v-if="select.multiplo == 'S'"
                    class="w-full"
                    :options="dadosWinthor.regional"
                    option-value="value"
                    option-label="label"
                    placeholder="Selecione..."
                    filter
                    :loading="loadingWinthor.regional"
                    @show="carregarDadosWinthor('regional')"
                    @update:model-value="(val) => addResposta(select, val)"
                  />
                  <Select
                    v-else
                    class="w-full"
                    :options="dadosWinthor.regional"
                    option-value="value"
                    option-label="label"
                    placeholder="Selecione..."
                    show-clear
                    filter
                    :loading="loadingWinthor.regional"
                    @show="carregarDadosWinthor('regional')"
                    @update:model-value="(val) => addResposta(select, val)"
                  />
                </template>

                <!-- CNPJ -->
                <InputMask
                  v-else-if="select.tipo == 'cnpj'"
                  class="w-full"
                  mask="99.999.999/9999-99"
                  placeholder="00.000.000/0000-00"
                  @update:model-value="(val) => addResposta(select, val)"
                />

                <InputText
                  v-else-if="select.tipo == 'texto'"
                  class="w-full"
                  placeholder="Digite aqui..."
                  @update:model-value="(val) => addResposta(select, val)"
                />

                <InputNumber
                  v-else-if="select.tipo == 'numero'"
                  class="w-full"
                  :min="0"
                  placeholder="Digite um número..."
                  @update:model-value="(val) => addResposta(select, val)"
                />

                <DatePicker
                  v-else
                  :selection-mode="select.tipo_data"
                  @update:model-value="(val) => addResposta(select, val)"
                  show-icon
                  fluid
                  date-format="dd/mm/yy"
                  :min-date="calcularDataMinima(select)"
                  :placeholder="
                    select.tipo_data == 'range'
                      ? 'Selecione um período'
                      : 'Selecione uma data'
                  "
                />
                <small
                  v-if="select.dias_minimos"
                  class="flex items-center text-xs text-amber-600 mt-1"
                >
                  <i class="pi pi-info-circle text-xs mr-1"></i>
                  Mínimo {{ select.dias_minimos }} dia(s) de antecedência
                </small>
              </div>
            </template>
          </div>
        </div>

        <!-- Campos de Arquivo -->
        <template
          v-for="select in selectsVisivelArquivo"
          :key="'arquivo-select-' + select.id"
        >
          <div
            class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-5 shadow-md"
          >
            <div class="flex items-center gap-3 mb-4">
              <span
                class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900/30 flex-shrink-0"
              >
                <i
                  class="pi pi-upload text-orange-600 dark:text-orange-400 text-lg"
                ></i>
              </span>
              <div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                  {{ select.label }}
                  <span
                    v-if="select.obrigatorio == 'S'"
                    class="text-red-500"
                  >
                    *
                  </span>
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  Anexe os arquivos necessários
                </p>
              </div>
            </div>
            <div class="w-full">
              <BsFile
                :key="'bs-file-' + select.id"
                :aceitar-um-arquivo="false"
                @atualizar-lista="atualizarArqSelect($event, select)"
              />
            </div>
          </div>
        </template>

        <!-- Colaboradores/Usuários Destino -->
        <div
          v-if="habilitarCampo('usuarios destino')"
          class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-5 shadow-md"
        >
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
              <span
                class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-cyan-100 dark:bg-cyan-900/30 flex-shrink-0"
              >
                <i
                  class="pi pi-users text-cyan-600 dark:text-cyan-400 text-lg"
                ></i>
              </span>
              <div>
                <h3
                  class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2"
                >
                  Colaborador
                  <span
                    v-if="habilitarObrigatorio('usuarios destino')"
                    class="text-red-500"
                  >
                    *
                  </span>
                  <button
                    v-if="obsCampo('usuarios destino')"
                    @click="showDialogObs(obsCampo('usuarios destino'))"
                    class="inline-flex items-center justify-center w-5 h-5 text-xs text-white bg-blue-500 hover:bg-blue-600 rounded-full transition-colors"
                    :title="obsCampo('usuarios destino')"
                  >
                    i
                  </button>
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  Adicione os colaboradores relacionados
                </p>
              </div>
            </div>
            <Button
              @click="showBuscaFunc('destino')"
              icon="pi pi-search"
              label="Buscar"
              outlined
              severity="info"
            />
          </div>

          <div class="min-h-[120px]">
            <div
              v-if="solicitacao.usuariosDestino.length == 0"
              class="flex flex-col items-center justify-center h-32 bg-gray-50 dark:bg-slate-700/50 rounded-xl border-2 border-dashed border-gray-200 dark:border-slate-600"
            >
              <i
                class="pi pi-user-plus text-3xl text-gray-300 dark:text-gray-500 mb-2"
              ></i>
              <span class="text-sm text-gray-400 dark:text-gray-500">
                Nenhum colaborador adicionado
              </span>
            </div>

            <div
              v-else
              class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"
            >
              <div
                v-for="func in solicitacao.usuariosDestino"
                :key="func.matricula"
                class="group flex items-center gap-3 p-2.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl border border-gray-200 dark:border-slate-600 hover:border-cyan-300 dark:hover:border-cyan-600 hover:shadow-sm transition-all"
              >
                <!-- Avatar com foto ou iniciais -->
                <div class="relative flex-shrink-0">
                  <img
                    v-if="func.foto"
                    :src="func.foto"
                    :alt="func.nome"
                    class="h-9 w-9 rounded-full object-cover shadow-sm"
                  />
                  <div
                    v-else
                    :class="[
                      'h-9 w-9 rounded-full flex items-center justify-center text-white font-bold text-xs shadow-sm',
                      getAvatarColor(func.nome)
                    ]"
                  >
                    {{ obterIniciais(func.nome) }}
                  </div>
                </div>

                <!-- Info -->
                <div
                  v-tooltip.top="func.nome"
                  class="flex-1 min-w-0"
                >
                  <p
                    class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate"
                  >
                    {{ obterNomeSobrenome(func.nome) }}
                  </p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ func.matricula }}
                  </p>
                </div>

                <!-- Botão remover -->
                <button
                  @click="removerFuncionario(func, 'destino')"
                  class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors"
                >
                  <i class="pi pi-times text-xs"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Filial e Departamento -->
        <div
          v-if="habilitarCampo('filial') || habilitarCampo('departamento')"
          class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-5 shadow-md"
        >
          <div class="flex items-center gap-3 mb-4">
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex-shrink-0"
            >
              <i
                class="pi pi-building text-indigo-600 dark:text-indigo-400 text-lg"
              ></i>
            </span>
            <div>
              <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                Localização
              </h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Informe a filial e/ou departamento relacionado
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-if="habilitarCampo('filial')"
              class="flex flex-col gap-1"
            >
              <label
                class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
              >
                Filial
                <span
                  v-if="habilitarObrigatorio('filial')"
                  class="text-red-500"
                >
                  *
                </span>
                <button
                  v-if="obsCampo('filial')"
                  @click="showDialogObs(obsCampo('filial'))"
                  class="inline-flex items-center justify-center w-5 h-5 text-xs text-white bg-blue-500 hover:bg-blue-600 rounded-full transition-colors"
                  :title="obsCampo('filial')"
                >
                  i
                </button>
              </label>
              <BsFilial
                v-model="solicitacao.filialSelect"
                :placeholder="'Selecione uma Filial...'"
                :show-clear="true"
              />
            </div>

            <div
              v-if="habilitarCampo('departamento')"
              class="flex flex-col gap-1"
            >
              <label
                class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
              >
                Departamento
                <span
                  v-if="habilitarObrigatorio('departamento')"
                  class="text-red-500"
                >
                  *
                </span>
                <button
                  v-if="obsCampo('departamento')"
                  @click="showDialogObs(obsCampo('departamento'))"
                  class="inline-flex items-center justify-center w-5 h-5 text-xs text-white bg-blue-500 hover:bg-blue-600 rounded-full transition-colors"
                  :title="obsCampo('departamento')"
                >
                  i
                </button>
              </label>
              <BsDepartamento
                v-model="solicitacao.departamentoSelect"
                :placeholder="'Selecione um Departamento...'"
                :show-clear="true"
              />
            </div>
          </div>
        </div>

        <!-- Título e Descrição -->
        <div
          v-if="habilitarCampo('titulo') || habilitarCampo('descricao')"
          class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-5 shadow-md"
        >
          <div class="flex items-center gap-3 mb-4">
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex-shrink-0"
            >
              <i
                class="pi pi-pencil text-amber-600 dark:text-amber-400 text-lg"
              ></i>
            </span>
            <div>
              <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                Detalhes do Ticket
              </h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Descreva sua ticket com o máximo de detalhes
              </p>
            </div>
          </div>

          <div class="space-y-4">
            <div
              v-if="habilitarCampo('titulo')"
              class="flex flex-col gap-1"
            >
              <label
                class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
              >
                Título
                <span
                  v-if="habilitarObrigatorio('titulo')"
                  class="text-red-500"
                >
                  *
                </span>
                <button
                  v-if="obsCampo('titulo')"
                  @click="showDialogObs(obsCampo('titulo'))"
                  class="inline-flex items-center justify-center w-5 h-5 text-xs text-white bg-blue-500 hover:bg-blue-600 rounded-full transition-colors"
                  :title="obsCampo('titulo')"
                >
                  i
                </button>
              </label>
              <!-- Renderizar Select quando tipo = selecao -->
              <Select
                v-if="tipoCampo('titulo') === 'selecao'"
                v-model="solicitacao.titulo"
                :options="opcoesTitulo()"
                placeholder="Selecione o título da ticket"
                class="w-full"
                :filter="opcoesTitulo().length > 5"
                filterPlaceholder="Buscar..."
                showClear
              />
              <!-- Renderizar InputText quando tipo = texto (padrão) -->
              <InputText
                v-else
                dusk="nova-titulo"
                placeholder="Escreva um breve resumo sobre o assunto da ticket"
                maxlength="200"
                v-model="solicitacao.titulo"
                class="w-full"
              />
            </div>

            <div
              v-if="habilitarCampo('descricao')"
              class="flex flex-col gap-1"
            >
              <label
                class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
              >
                Descrição
                <span
                  v-if="habilitarObrigatorio('descricao')"
                  class="text-red-500"
                >
                  *
                </span>
                <button
                  v-if="obsCampo('descricao')"
                  @click="showDialogObs(obsCampo('descricao'))"
                  class="inline-flex items-center justify-center w-5 h-5 text-xs text-white bg-blue-500 hover:bg-blue-600 rounded-full transition-colors"
                  :title="obsCampo('descricao')"
                >
                  i
                </button>
              </label>
              <Textarea
                rows="8"
                dusk="nova-descricao"
                maxlength="4000"
                placeholder="Informe o máximo de detalhes sobre o que você está solicitando, para que possamos entender claramente e atender sua demanda."
                v-model="solicitacao.descricao"
                class="w-full"
                autoResize
              />
            </div>
          </div>
        </div>

        <!-- Rotinas -->
        <div
          v-if="habilitarCampo('rotinas')"
          class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-5 shadow-md"
        >
          <div class="flex items-center gap-3 mb-4">
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-teal-100 dark:bg-teal-900/30 flex-shrink-0"
            >
              <i class="pi pi-cog text-teal-600 dark:text-teal-400 text-lg"></i>
            </span>
            <div>
              <h3
                class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2"
              >
                Rotinas
                <span
                  v-if="habilitarObrigatorio('rotinas')"
                  class="text-red-500"
                >
                  *
                </span>
                <button
                  v-if="obsCampo('rotinas')"
                  @click="showDialogObs(obsCampo('rotinas'))"
                  class="inline-flex items-center justify-center w-5 h-5 text-xs text-white bg-blue-500 hover:bg-blue-600 rounded-full transition-colors"
                  :title="obsCampo('rotinas')"
                >
                  i
                </button>
              </h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Adicione as rotinas relacionadas
              </p>
            </div>
          </div>

          <div class="space-y-3">
            <InputGroup class="flex">
              <InputText
                @keypress.enter="setRotina"
                v-model="rotinaTmp"
                placeholder="Digite o código da rotina"
                class="flex-1"
              />
              <Button
                @click="setRotina"
                icon="pi pi-plus"
                severity="info"
              />
            </InputGroup>

            <div
              v-if="solicitacao.rotinas.length"
              class="max-h-40 overflow-auto space-y-2"
            >
              <div
                v-for="rotina in solicitacao.rotinas"
                :key="rotina.codigo"
                class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-xl border border-gray-200 dark:border-slate-600"
              >
                <div class="flex items-center gap-3">
                  <span
                    class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-teal-100 dark:bg-teal-900/30 text-sm font-bold text-teal-600 dark:text-teal-400"
                  >
                    {{ rotina.codigo }}
                  </span>
                  <span
                    class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate max-w-[300px]"
                  >
                    {{ rotina.nomerotina }}
                  </span>
                </div>
                <Button
                  @click="removerRotina(rotina)"
                  icon="pi pi-times"
                  severity="danger"
                  text
                  rounded
                />
              </div>
            </div>
            <div
              v-else
              class="flex flex-col items-center justify-center h-24 bg-gray-50 dark:bg-slate-700/50 rounded-xl border-2 border-dashed border-gray-200 dark:border-slate-600"
            >
              <i
                class="pi pi-cog text-3xl text-gray-300 dark:text-gray-500 mb-2"
              ></i>
              <span class="text-sm text-gray-400 dark:text-gray-500">
                Nenhuma rotina adicionada
              </span>
            </div>
          </div>
        </div>

        <!-- Dados de Acesso -->
        <div
          v-if="habilitarCampo('dados acesso')"
          class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-5 shadow-md"
        >
          <div class="flex items-center gap-3 mb-4">
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-900/30 flex-shrink-0"
            >
              <i
                class="pi pi-lock text-rose-600 dark:text-rose-400 text-lg"
              ></i>
            </span>
            <div>
              <h3
                class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2"
              >
                Dados de Acesso
                <span
                  v-if="habilitarObrigatorio('dados acesso')"
                  class="text-red-500"
                >
                  *
                </span>
                <button
                  v-if="obsCampo('dados acesso')"
                  @click="showDialogObs(obsCampo('dados acesso'))"
                  class="inline-flex items-center justify-center w-5 h-5 text-xs text-white bg-blue-500 hover:bg-blue-600 rounded-full transition-colors"
                  :title="obsCampo('dados acesso')"
                >
                  i
                </button>
              </h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Configure as liberações de acesso
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div
              v-for="campo in dadosLiberacao"
              :key="campo.nome"
              :class="[
                'flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer',
                campo.situacao
                  ? 'bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-700 shadow-sm'
                  : 'bg-gray-50 dark:bg-slate-700/50 border-gray-200 dark:border-slate-600 hover:border-gray-300 dark:hover:border-slate-500'
              ]"
              @click="campo.situacao = !campo.situacao"
            >
              <!-- Checkbox customizado -->
              <div
                :class="[
                  'flex-shrink-0 w-5 h-5 rounded flex items-center justify-center transition-all',
                  campo.situacao
                    ? 'bg-rose-500 dark:bg-rose-600'
                    : 'bg-white dark:bg-slate-600 border-2 border-gray-300 dark:border-slate-500'
                ]"
              >
                <i
                  v-if="campo.situacao"
                  class="pi pi-check text-white text-xs"
                ></i>
              </div>
              <span
                :class="[
                  'flex-1 text-sm font-medium transition-colors',
                  campo.situacao
                    ? 'text-rose-700 dark:text-rose-300'
                    : 'text-gray-700 dark:text-gray-200'
                ]"
              >
                {{ campo.nome }}
              </span>
              <Button
                @click.stop="showDialogGenerico(campo)"
                :disabled="!campo.situacao"
                :label="
                  '(' +
                  (campo.dados && campo.situacao ? campo.dados.length : 0) +
                  ')'
                "
                icon="pi pi-list"
                :severity="campo.situacao ? 'danger' : 'secondary'"
                :outlined="!campo.situacao"
                size="small"
                class="!rounded-lg"
              />
            </div>
          </div>
        </div>

        <!-- Usuário de Origem -->
        <div
          v-if="habilitarCampo('usuario origem')"
          class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-5 shadow-md"
        >
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
              <span
                class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-violet-100 dark:bg-violet-900/30 flex-shrink-0"
              >
                <i
                  class="pi pi-user text-violet-600 dark:text-violet-400 text-lg"
                ></i>
              </span>
              <div>
                <h3
                  class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2"
                >
                  Usuário de Origem
                  <span
                    v-if="habilitarObrigatorio('usuario origem')"
                    class="text-red-500"
                  >
                    *
                  </span>
                  <button
                    v-if="obsCampo('usuario origem')"
                    @click="showDialogObs(obsCampo('usuario origem'))"
                    class="inline-flex items-center justify-center w-5 h-5 text-xs text-white bg-blue-500 hover:bg-blue-600 rounded-full transition-colors"
                    :title="obsCampo('usuario origem')"
                  >
                    i
                  </button>
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  Informe o usuário de origem
                </p>
              </div>
            </div>
            <Button
              @click="showBuscaFunc('origem')"
              icon="pi pi-search"
              label="Buscar"
              outlined
              severity="info"
            />
          </div>

          <div
            v-if="!solicitacao.usuarioOrigem"
            class="flex flex-col items-center justify-center h-20 bg-gray-50 dark:bg-slate-700/50 rounded-xl border-2 border-dashed border-gray-200 dark:border-slate-600"
          >
            <i
              class="pi pi-user text-2xl text-gray-300 dark:text-gray-500 mb-1"
            ></i>
            <span class="text-sm text-gray-400 dark:text-gray-500">
              Não informado Usuário de Origem
            </span>
          </div>

          <div
            v-else
            v-tooltip.top="solicitacao.usuarioOrigem.nome"
            class="group flex items-center gap-3 p-2.5 bg-gray-50 dark:bg-slate-700/50 rounded-xl border border-gray-200 dark:border-slate-600 hover:border-violet-300 dark:hover:border-violet-600 hover:shadow-sm transition-all max-w-md"
          >
            <!-- Avatar com foto ou iniciais -->
            <div class="relative flex-shrink-0">
              <img
                v-if="solicitacao.usuarioOrigem.foto"
                :src="solicitacao.usuarioOrigem.foto"
                :alt="solicitacao.usuarioOrigem.nome"
                class="h-9 w-9 rounded-full object-cover shadow-sm"
              />
              <div
                v-else
                :class="[
                  'h-9 w-9 rounded-full flex items-center justify-center text-white font-bold text-xs shadow-sm',
                  getAvatarColor(solicitacao.usuarioOrigem.nome)
                ]"
              >
                {{ obterIniciais(solicitacao.usuarioOrigem.nome) }}
              </div>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
              <p
                class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate"
              >
                {{ obterNomeSobrenome(solicitacao.usuarioOrigem.nome) }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ solicitacao.usuarioOrigem.matricula }}
              </p>
            </div>

            <!-- Botão remover -->
            <button
              @click="
                removerFuncionario(solicitacao.usuarioOrigem.nome, 'origem')
              "
              class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors opacity-0 group-hover:opacity-100"
            >
              <i class="pi pi-times text-xs"></i>
            </button>
          </div>
        </div>

        <!-- Vendas Pendentes -->
        <div
          v-if="habilitarCampo('vendas pendentes')"
          class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-5 shadow-md"
        >
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
              <span
                class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-pink-100 dark:bg-pink-900/30 flex-shrink-0"
              >
                <i
                  class="pi pi-shopping-cart text-pink-600 dark:text-pink-400 text-lg"
                ></i>
              </span>
              <div>
                <h3
                  class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2"
                >
                  Informações das Vendas
                  <span
                    v-if="habilitarObrigatorio('vendas pendentes')"
                    class="text-red-500"
                  >
                    *
                  </span>
                  <button
                    v-if="obsCampo('vendas pendentes')"
                    @click="showDialogObs(obsCampo('vendas pendentes'))"
                    class="inline-flex items-center justify-center w-5 h-5 text-xs text-white bg-blue-500 hover:bg-blue-600 rounded-full transition-colors"
                    :title="obsCampo('vendas pendentes')"
                  >
                    i
                  </button>
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  Adicione as informações das vendas pendentes
                </p>
              </div>
            </div>
            <Button
              @click="adicionarVendaPendente()"
              icon="pi pi-plus"
              label="Adicionar"
              severity="info"
              outlined
            />
          </div>

          <div class="space-y-4">
            <!-- Empty State -->
            <div
              v-if="vendasPendentes.length === 0"
              class="flex flex-col items-center justify-center h-32 bg-gray-50 dark:bg-slate-700/50 rounded-xl border-2 border-dashed border-gray-200 dark:border-slate-600"
            >
              <i
                class="pi pi-shopping-cart text-3xl text-gray-300 dark:text-gray-500 mb-2"
              ></i>
              <span class="text-sm text-gray-400 dark:text-gray-500">
                Nenhuma venda adicionada
              </span>
            </div>

            <!-- Cards de Vendas -->
            <div
              v-for="(venda, index) in vendasPendentes"
              :key="index"
              class="relative bg-gradient-to-br from-gray-50 to-white dark:from-slate-700/50 dark:to-slate-800 rounded-xl border border-gray-200 dark:border-slate-600 shadow-sm hover:shadow-md transition-shadow overflow-hidden"
            >
              <!-- Header do Card -->
              <div
                class="flex items-center justify-between px-4 py-3 bg-pink-50/50 dark:bg-pink-900/10 border-b border-gray-200 dark:border-slate-600"
              >
                <div class="flex items-center gap-2">
                  <span
                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-pink-100 dark:bg-pink-900/30 text-pink-600 dark:text-pink-400 text-sm font-bold"
                  >
                    {{ index + 1 }}
                  </span>
                  <span
                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                  >
                    Venda #{{ index + 1 }}
                  </span>
                </div>
                <Button
                  icon="pi pi-trash"
                  @click="removerVenda(index)"
                  severity="danger"
                  text
                  rounded
                  size="small"
                  v-tooltip.top="'Remover venda'"
                />
              </div>

              <!-- Conteúdo do Card -->
              <div class="p-4">
                <div
                  class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4"
                >
                  <!-- Filial -->
                  <div class="flex flex-col gap-1.5">
                    <label
                      class="text-xs font-semibold text-gray-600 dark:text-gray-400 flex items-center gap-1"
                    >
                      <i class="pi pi-building text-xs text-pink-500"></i>
                      Filial
                    </label>
                    <Select
                      @change="venda.caixas = []"
                      v-model="venda.filial"
                      placeholder="Selecione a filial"
                      class="w-full"
                      :options="props.filiais"
                      option-label="fantasia"
                      filter
                      :filter-fields="['codigo', 'fantasia']"
                    >
                      <template #option="slot">
                        <div class="text-sm">
                          <span class="font-medium">
                            {{ slot.option.codigo }}
                          </span>
                          - {{ slot.option.fantasia }}
                        </div>
                      </template>
                    </Select>
                  </div>

                  <!-- Caixas -->
                  <div class="flex flex-col gap-1.5">
                    <label
                      class="text-xs font-semibold text-gray-600 dark:text-gray-400 flex items-center gap-1"
                    >
                      <i class="pi pi-inbox text-xs text-pink-500"></i>
                      Caixas
                    </label>
                    <MultiSelect
                      v-model="venda.caixas"
                      :options="venda.filial?.caixas || []"
                      option-label="descricao"
                      max-selected-labels="1"
                      :selected-items-label="
                        (venda.caixas.length ===
                        (venda.filial?.caixas?.length || 0)
                          ? 'Todos os'
                          : venda.caixas.length) + ' caixas'
                      "
                      placeholder="Selecione os caixas"
                      class="w-full"
                      :disabled="!venda.filial"
                    />
                  </div>

                  <!-- Valor -->
                  <div class="flex flex-col gap-1.5">
                    <label
                      class="text-xs font-semibold text-gray-600 dark:text-gray-400 flex items-center gap-1"
                    >
                      <i class="pi pi-dollar text-xs text-pink-500"></i>
                      Valor
                    </label>
                    <InputMoney2
                      class="w-full"
                      v-model="venda.valor"
                    />
                  </div>

                  <!-- Data -->
                  <div class="flex flex-col gap-1.5">
                    <label
                      class="text-xs font-semibold text-gray-600 dark:text-gray-400 flex items-center gap-1"
                    >
                      <i class="pi pi-calendar text-xs text-pink-500"></i>
                      Data
                    </label>
                    <DatePicker
                      placeholder="Selecione a data"
                      v-model="venda.data"
                      class="w-full"
                      date-format="dd/mm/yy"
                      show-icon
                      fluid
                    />
                  </div>

                  <!-- Operador -->
                  <div class="flex flex-col gap-1.5">
                    <label
                      class="text-xs font-semibold text-gray-600 dark:text-gray-400 flex items-center gap-1"
                    >
                      <i class="pi pi-user text-xs text-pink-500"></i>
                      Operador
                    </label>
                    <div class="relative">
                      <InputText
                        @click="showDialogOperador(index)"
                        placeholder="Clique para buscar"
                        class="w-full cursor-pointer !pr-8"
                        :value="tratarNome(venda.operador?.nome)"
                        readonly
                      />
                      <button
                        v-if="venda.operador"
                        @click.stop="venda.operador = null"
                        class="absolute top-1/2 right-2 -translate-y-1/2 w-5 h-5 flex items-center justify-center cursor-pointer hover:scale-110 transition-all bg-red-500 hover:bg-red-600 rounded-full text-white"
                      >
                        <i class="pi pi-times text-xs"></i>
                      </button>
                      <i
                        v-else
                        class="pi pi-search absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"
                      ></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Equipamentos -->
        <div
          v-if="habilitarCampo('equipamentos')"
          class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-5 shadow-md"
        >
          <div class="flex items-center gap-3 mb-4">
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-sky-100 dark:bg-sky-900/30 flex-shrink-0"
            >
              <i
                class="pi pi-desktop text-sky-600 dark:text-sky-400 text-lg"
              ></i>
            </span>
            <div>
              <h3
                class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2"
              >
                Equipamentos
                <span
                  v-if="habilitarObrigatorio('equipamentos')"
                  class="text-red-500"
                >
                  *
                </span>
                <button
                  v-if="obsCampo('equipamentos')"
                  @click="showDialogObs(obsCampo('equipamentos'))"
                  class="inline-flex items-center justify-center w-5 h-5 text-xs text-white bg-blue-500 hover:bg-blue-600 rounded-full transition-colors"
                  :title="obsCampo('equipamentos')"
                >
                  i
                </button>
              </h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Selecione os equipamentos necessários
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Lista de equipamentos disponíveis -->
            <div>
              <div class="flex items-center justify-between mb-3">
                <label
                  class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
                >
                  <i class="pi pi-list text-sky-500"></i>
                  Disponíveis
                </label>
                <span
                  class="text-xs font-medium text-sky-600 dark:text-sky-400 bg-sky-100 dark:bg-sky-900/30 px-2 py-0.5 rounded-full"
                >
                  {{ equipamentos.length }} itens
                </span>
              </div>
              <div
                class="h-64 overflow-auto bg-gradient-to-br from-gray-50 to-white dark:from-slate-700/50 dark:to-slate-800 rounded-xl border border-gray-200 dark:border-slate-600 p-3 space-y-2"
              >
                <div
                  v-for="(equipamento, index) in equipamentos"
                  :key="'equip-' + index"
                  @click="moverEquipamento(equipamento)"
                  class="group flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 hover:border-sky-300 dark:hover:border-sky-600 hover:shadow-md hover:scale-[1.02]"
                >
                  <span
                    class="flex-shrink-0 w-8 h-8 rounded-lg bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center group-hover:bg-sky-500 transition-colors"
                  >
                    <i
                      class="pi pi-plus text-sky-600 dark:text-sky-400 group-hover:text-white transition-colors"
                    ></i>
                  </span>
                  <span
                    class="flex-1 text-sm font-medium text-gray-700 dark:text-gray-200"
                  >
                    {{ equipamento.nome }}
                  </span>
                  <i
                    class="pi pi-arrow-right text-gray-300 dark:text-gray-600 group-hover:text-sky-500 transition-colors"
                  ></i>
                </div>

                <div
                  v-if="equipamentos.length === 0"
                  class="flex flex-col items-center justify-center h-full text-gray-400 dark:text-gray-500"
                >
                  <i class="pi pi-check-circle text-3xl mb-2"></i>
                  <span class="text-sm">
                    Todos os equipamentos foram selecionados
                  </span>
                </div>
              </div>
            </div>

            <!-- Equipamentos selecionados -->
            <div>
              <div class="flex items-center justify-between mb-3">
                <label
                  class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
                >
                  <i class="pi pi-check-square text-emerald-500"></i>
                  Selecionados
                </label>
                <span
                  class="text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 rounded-full"
                >
                  {{ solicitacao.equipamentos.length }} itens
                </span>
              </div>
              <div
                v-if="solicitacao.equipamentos.length > 0"
                class="space-y-3 max-h-64 overflow-auto pr-1"
              >
                <div
                  v-for="(equipamento, index) in solicitacao.equipamentos"
                  :key="index"
                  class="group relative bg-gradient-to-br from-emerald-50 to-white dark:from-emerald-900/20 dark:to-slate-800 rounded-xl border border-emerald-200 dark:border-emerald-800 shadow-sm hover:shadow-md transition-shadow overflow-hidden"
                >
                  <!-- Header do equipamento -->
                  <div
                    class="flex items-center justify-between px-4 py-2.5 bg-emerald-100/50 dark:bg-emerald-900/30 border-b border-emerald-200 dark:border-emerald-800"
                  >
                    <div class="flex items-center gap-2">
                      <span
                        class="w-6 h-6 rounded-md bg-emerald-500 flex items-center justify-center"
                      >
                        <i class="pi pi-desktop text-white text-xs"></i>
                      </span>
                      <span
                        class="text-sm font-semibold text-emerald-800 dark:text-emerald-300"
                      >
                        {{ equipamento.nome }}
                      </span>
                    </div>
                    <Button
                      @click="removerEquipamento(equipamento)"
                      icon="pi pi-times"
                      severity="danger"
                      text
                      rounded
                      size="small"
                      v-tooltip.top="'Remover'"
                    />
                  </div>

                  <!-- Campos do equipamento -->
                  <div class="p-3 flex flex-col sm:flex-row gap-3">
                    <div class="flex flex-col gap-1 sm:w-24">
                      <label
                        class="text-xs font-medium text-gray-500 dark:text-gray-400"
                      >
                        Quantidade
                      </label>
                      <InputText
                        class="w-full text-center"
                        placeholder="Qtd"
                        v-model="equipamento.quantidade"
                      />
                    </div>
                    <div class="flex flex-col gap-1 flex-1">
                      <label
                        class="text-xs font-medium text-gray-500 dark:text-gray-400"
                      >
                        Observação
                      </label>
                      <InputText
                        class="w-full"
                        placeholder="Adicione uma observação (opcional)"
                        v-model="equipamento.observacao"
                      />
                    </div>
                  </div>
                </div>
              </div>
              <div
                v-else
                class="flex flex-col items-center justify-center h-64 bg-gradient-to-br from-gray-50 to-white dark:from-slate-700/50 dark:to-slate-800 rounded-xl border-2 border-dashed border-gray-200 dark:border-slate-600"
              >
                <div
                  class="w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-3"
                >
                  <i
                    class="pi pi-desktop text-3xl text-gray-300 dark:text-gray-500"
                  ></i>
                </div>
                <span
                  class="text-sm font-medium text-gray-500 dark:text-gray-400"
                >
                  Nenhum equipamento selecionado
                </span>
                <span class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                  Clique em um item ao lado para adicionar
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Arquivos -->
        <div
          v-if="habilitarCampo('arquivos')"
          class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-2xl p-5 shadow-md"
        >
          <div class="flex items-center gap-3 mb-4">
            <span
              class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900/30 flex-shrink-0"
            >
              <i
                class="pi pi-paperclip text-orange-600 dark:text-orange-400 text-lg"
              ></i>
            </span>
            <div>
              <h3
                class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2"
              >
                Arquivos
                <span
                  v-if="habilitarObrigatorio('arquivos')"
                  class="text-red-500"
                >
                  *
                </span>
                <button
                  v-if="obsCampo('arquivos')"
                  @click="showDialogObs(obsCampo('arquivos'))"
                  class="inline-flex items-center justify-center w-5 h-5 text-xs text-white bg-blue-500 hover:bg-blue-600 rounded-full transition-colors"
                  :title="obsCampo('arquivos')"
                >
                  i
                </button>
              </h3>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Anexe arquivos relacionados à sua ticket
              </p>
            </div>
          </div>
          <div>
            <BsFile
              :aceitar-um-arquivo="false"
              @atualizar-lista="atualizarLista"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Prioridade e Botão Enviar -->
    <div
      v-if="solicitacao.departamento && solicitacao.assunto"
      class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-slate-700 rounded-xl p-1 sm:p-4 mt-4 shadow-sm"
    >
      <div
        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
      >
        <!-- Prioridade -->
        <div class="flex flex-wrap items-center gap-3">
          <span
            class="text-sm font-medium text-gray-600 ml-2 sm:ml-1 dark:text-gray-400"
          >
            Prioridade
          </span>

          <div
            class="flex sm:flex-wrap justify-between items-center gap-1 rounded-xl w-full bg-gray-100/80 dark:bg-slate-800/60 p-1 backdrop-blur"
          >
            <!-- Baixa -->
            <button
              type="button"
              @click="mudarPrioridade('baixa')"
              :aria-pressed="solicitacao.prioridade === 'baixa'"
              class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400"
              :class="
                solicitacao.prioridade === 'baixa'
                  ? 'bg-white dark:bg-slate-700 text-gray-700 dark:text-gray-200 shadow-sm'
                  : 'text-gray-500 dark:text-gray-400 hover:bg-white/70 dark:hover:bg-slate-700/60'
              "
            >
              <i class="pi pi-minus text-[11px] opacity-70"></i>
              Baixa
            </button>

            <!-- Média -->
            <button
              type="button"
              @click="mudarPrioridade('media')"
              :aria-pressed="solicitacao.prioridade === 'media'"
              class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
              :class="
                solicitacao.prioridade === 'media'
                  ? 'bg-blue-600/10 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 shadow-sm'
                  : 'text-blue-500 dark:text-blue-400 hover:bg-blue-500/10'
              "
            >
              <i class="pi pi-equals text-[11px] opacity-80"></i>
              Média
            </button>

            <!-- Alta -->
            <button
              type="button"
              @click="mudarPrioridade('alta')"
              :aria-pressed="solicitacao.prioridade === 'alta'"
              class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400"
              :class="
                solicitacao.prioridade === 'alta'
                  ? 'bg-amber-600/10 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 shadow-sm'
                  : 'text-amber-600 dark:text-amber-400 hover:bg-amber-500/10'
              "
            >
              <i class="pi pi-arrow-up text-[11px] opacity-80"></i>
              Alta
            </button>

            <!-- Urgente -->
            <button
              type="button"
              @click="mudarPrioridade('urgente')"
              :aria-pressed="solicitacao.prioridade === 'urgente'"
              class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
              :class="
                solicitacao.prioridade === 'urgente'
                  ? 'bg-red-600/10 dark:bg-red-500/20 text-red-700 dark:text-red-300 shadow-sm'
                  : 'text-red-500 dark:text-red-400 hover:bg-red-500/10'
              "
            >
              <i class="pi pi-exclamation-triangle text-[11px] opacity-80"></i>
              Urgente
            </button>
          </div>
        </div>

        <!-- Botão Enviar -->
        <Button
          class="w-full sm:w-auto sm:mr-8 sm:mt-4"
          dusk="nova-submit"
          @click="criarSolicitacao()"
          :label="textoBotao"
          :disabled="criandoSolicitacao"
          :loading="criandoSolicitacao"
          severity="success"
          outlined
          icon="pi pi-send"
        />
      </div>
    </div>

    <!-- Dialog Busca Funcionário -->
    <Dialog
      v-model:visible="dialogFuncionario"
      position="top"
      :closable="false"
      modal
      class="w-full max-w-md !rounded-2xl overflow-hidden"
      :pt="{
        root: { class: '!rounded-2xl overflow-hidden !border-0' },
        header: { class: '!p-0' },
        content: { class: '!p-0' },
        mask: { class: 'backdrop-blur-sm' }
      }"
    >
      <template #header>
        <div
          class="flex items-center justify-between px-5 py-4 bg-gradient-to-r from-cyan-500 to-blue-500 w-full"
        >
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center"
            >
              <i class="pi pi-users text-white text-lg"></i>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">Buscar Funcionário</h3>
              <p class="text-xs text-white/70">Digite a matrícula ou nome</p>
            </div>
          </div>
          <button
            @click="dialogFuncionario = false"
            class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-colors"
          >
            <i class="pi pi-times text-white text-sm"></i>
          </button>
        </div>
      </template>

      <!-- Content -->
      <div class="p-5 bg-white dark:bg-slate-800 space-y-4">
        <!-- Campo de busca -->
        <InputGroup>
          <InputText
            v-model="termoFuncionario"
            @keypress.enter="buscarFuncionario()"
            placeholder="Matrícula ou nome..."
            class="w-full !rounded-l-xl"
          />
          <Button
            @click="buscarFuncionario()"
            icon="pi pi-search"
            :loading="loading"
            class="!rounded-r-xl"
            severity="info"
          />
        </InputGroup>

        <!-- Lista de resultados -->
        <div
          v-if="listaFuncionarios.length"
          class="max-h-72 overflow-auto space-y-2 scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-slate-600"
        >
          <div
            v-for="func in listaFuncionarios"
            :key="func.matricula"
            @click="adicionarFunc(func)"
            class="group flex items-center gap-3 p-3 cursor-pointer rounded-xl border border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 hover:bg-cyan-50 dark:hover:bg-cyan-900/20 hover:border-cyan-200 dark:hover:border-cyan-700 transition-all"
          >
            <!-- Avatar com foto ou iniciais -->
            <div class="relative flex-shrink-0">
              <img
                v-if="func.foto"
                :src="func.foto"
                :alt="func.nome"
                class="h-10 w-10 rounded-full object-cover shadow-sm"
              />
              <div
                v-else
                :class="[
                  'h-10 w-10 rounded-full flex items-center justify-center text-white font-bold text-sm shadow-sm',
                  getAvatarColor(func.nome)
                ]"
              >
                {{ obterIniciais(func.nome) }}
              </div>
            </div>

            <!-- Info -->
            <div
              v-tooltip.top="func.nome"
              class="flex-1 min-w-0"
            >
              <p
                class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate"
              >
                {{ obterNomeSobrenome(func.nome) }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Matrícula: {{ func.matricula }}
              </p>
            </div>

            <!-- Indicador de ação -->
            <div
              class="flex-shrink-0 w-7 h-7 rounded-full bg-cyan-100 dark:bg-cyan-900/30 flex items-center justify-center"
            >
              <i
                class="pi pi-plus text-cyan-600 dark:text-cyan-400 text-xs"
              ></i>
            </div>
          </div>
        </div>

        <!-- Estado vazio - Nenhum funcionário encontrado -->
        <div
          v-else-if="buscouFuncionario && termoFuncionario"
          class="flex flex-col items-center justify-center py-10 bg-orange-50 dark:bg-orange-900/20 rounded-xl border border-orange-200 dark:border-orange-800"
        >
          <div
            class="w-16 h-16 rounded-full bg-orange-100 dark:bg-orange-900/50 flex items-center justify-center mb-3"
          >
            <i class="pi pi-user-minus text-2xl text-orange-500"></i>
          </div>
          <span
            class="text-base font-semibold text-orange-700 dark:text-orange-400"
          >
            Nenhum funcionário encontrado
          </span>
          <span class="text-sm text-orange-600 dark:text-orange-500 mt-1">
            Não encontramos resultados para "{{ termoFuncionario }}"
          </span>
          <span class="text-xs text-gray-500 dark:text-gray-400 mt-2">
            Verifique a matrícula ou nome e tente novamente
          </span>
        </div>

        <!-- Estado inicial -->
        <div
          v-else
          class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-500"
        >
          <div
            class="w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-3"
          >
            <i class="pi pi-user-plus text-2xl"></i>
          </div>
          <span class="text-sm font-medium">Busque um funcionário</span>
          <span class="text-xs mt-1">Digite a matrícula ou nome acima</span>
        </div>
      </div>
    </Dialog>

    <!-- Dialog PickList Genérico -->
    <Dialog
      v-model:visible="dialogGenerico"
      modal
      :showHeader="false"
      class="w-full max-w-5xl"
      :pt="{
        root: '!rounded-2xl overflow-hidden',
        content: '!p-0 bg-white dark:bg-slate-800'
      }"
    >
      <!-- Header Customizado -->
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
          <div class="flex-1">
            <h3 class="font-semibold text-gray-800 dark:text-gray-100">
              Selecionar {{ headerDialog }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Mova os itens entre as listas para habilitar ou desabilitar
            </p>
          </div>
          <button
            @click="dialogGenerico = false"
            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors"
          >
            <i class="pi pi-times text-gray-500 dark:text-gray-400"></i>
          </button>
        </div>
      </div>

      <!-- Conteúdo - PickList Customizado -->
      <div class="p-4 md:p-6">
        <div
          class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 items-start"
        >
          <!-- Card: Itens Disponíveis -->
          <div
            class="bg-white dark:bg-slate-900 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 border-l-4 border-l-red-400 dark:border-l-red-500 border-y border-r border-gray-200 dark:border-slate-700"
          >
            <div class="p-4 border-b border-gray-200 dark:border-slate-700">
              <h4
                class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-3 flex items-center"
              >
                <i class="pi pi-times-circle mr-2 text-red-500"></i>
                {{ headerDialog }} Disponíveis
                <span
                  class="ml-auto px-2 py-0.5 bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-400 text-xs rounded-full font-medium"
                >
                  {{ pickList[0]?.length || 0 }}
                </span>
              </h4>
              <div class="relative">
                <i
                  class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                ></i>
                <input
                  v-model="termo"
                  @input="atualizarPick()"
                  type="text"
                  :placeholder="'Buscar ' + headerDialog.toLowerCase() + '...'"
                  class="w-full pl-10 pr-4 py-2.5 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all"
                />
              </div>
            </div>

            <div class="overflow-y-auto h-80 p-2">
              <div
                v-for="(item, index) in pickList[0]"
                :key="'source-' + index"
                @click="selecionarItemSource(item)"
                class="p-3 mb-1 rounded-lg cursor-pointer transition-all duration-200 border border-transparent"
                :class="{
                  'bg-red-50 dark:bg-red-900/30 border-red-400 dark:border-red-600 shadow-sm':
                    pickItemSelecionadoSource === item,
                  'bg-gray-50 dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 hover:border-red-300 dark:hover:border-red-700 hover:shadow-sm':
                    pickItemSelecionadoSource !== item
                }"
              >
                <div class="flex items-center">
                  <span
                    class="font-semibold text-red-600 dark:text-red-400 mr-3 min-w-[28px] text-center"
                  >
                    {{ index + 1 }}
                  </span>
                  <span
                    class="text-gray-700 dark:text-gray-200 text-sm font-medium"
                  >
                    <template v-if="headerDialog == 'Filiais'">
                      <span class="font-bold">{{ item.codigo }}</span>
                      - {{ item.fantasia }}
                    </template>
                    <template v-else-if="headerDialog == 'Departamentos'">
                      <span class="font-bold">{{ item.codepto }}</span>
                      - {{ item.descricao }}
                    </template>
                    <template v-else-if="headerDialog == 'Bancos'">
                      <span class="font-bold">{{ item.codbanco }}</span>
                      - {{ item.nome }}
                    </template>
                    <template v-else-if="headerDialog == 'Moedas'">
                      <span class="font-bold">{{ item.codmoeda }}</span>
                      - {{ item.moeda }}
                    </template>
                    <template v-else-if="headerDialog == 'Centros de Custo'">
                      <span class="font-bold">
                        {{ item.codigocentrocusto }}
                      </span>
                      - {{ item.descricao }}
                    </template>
                  </span>
                </div>
              </div>

              <div
                v-if="pickList[0]?.length === 0"
                class="text-center py-12 text-gray-400 dark:text-gray-500"
              >
                <i class="pi pi-inbox text-4xl mb-3 block"></i>
                <p class="font-medium">Nenhum item encontrado</p>
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
              @click="moverParaTarget"
              :disabled="!pickItemSelecionadoSource"
              v-tooltip.top="'Adicionar item'"
            />
            <Button
              :icon="isMobile ? 'pi pi-arrow-up' : 'pi pi-arrow-left'"
              class="!w-14 !h-14 !rounded-full shadow-xl hover:scale-110 transition-transform duration-200"
              severity="danger"
              @click="moverParaSource"
              :disabled="!pickItemSelecionadoTarget"
              v-tooltip.top="'Remover item'"
            />
          </div>

          <!-- Card: Itens Selecionados -->
          <div
            class="bg-white dark:bg-slate-900 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 border-l-4 border-l-green-400 dark:border-l-green-500 border-y border-r border-gray-200 dark:border-slate-700"
          >
            <div class="p-4 border-b border-gray-200 dark:border-slate-700">
              <h4
                class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-3 flex items-center"
              >
                <i class="pi pi-check-circle mr-2 text-green-500"></i>
                {{ headerDialog }} Selecionados
                <span
                  class="ml-auto px-2 py-0.5 bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-400 text-xs rounded-full font-medium"
                >
                  {{ pickList[1]?.length || 0 }}
                </span>
              </h4>
              <div class="relative">
                <i
                  class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                ></i>
                <input
                  v-model="termoTarget"
                  type="text"
                  :placeholder="'Buscar ' + headerDialog.toLowerCase() + '...'"
                  class="w-full pl-10 pr-4 py-2.5 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-800 text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all"
                />
              </div>
            </div>

            <div class="overflow-y-auto h-80 p-2">
              <div
                v-for="(item, index) in pickListTargetFiltrado"
                :key="'target-' + index"
                @click="selecionarItemTarget(item)"
                class="p-3 mb-1 rounded-lg cursor-pointer transition-all duration-200 border border-transparent"
                :class="{
                  'bg-green-50 dark:bg-green-900/30 border-green-400 dark:border-green-600 shadow-sm':
                    pickItemSelecionadoTarget === item,
                  'bg-gray-50 dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 hover:border-green-300 dark:hover:border-green-700 hover:shadow-sm':
                    pickItemSelecionadoTarget !== item
                }"
              >
                <div class="flex items-center">
                  <span
                    class="font-semibold text-green-600 dark:text-green-400 mr-3 min-w-[28px] text-center"
                  >
                    {{ index + 1 }}
                  </span>
                  <span
                    class="text-gray-700 dark:text-gray-200 text-sm font-medium"
                  >
                    <template v-if="headerDialog == 'Filiais'">
                      <span class="font-bold">{{ item.codigo }}</span>
                      - {{ item.fantasia }}
                    </template>
                    <template v-else-if="headerDialog == 'Departamentos'">
                      <span class="font-bold">{{ item.codepto }}</span>
                      - {{ item.descricao }}
                    </template>
                    <template v-else-if="headerDialog == 'Bancos'">
                      <span class="font-bold">{{ item.codbanco }}</span>
                      - {{ item.nome }}
                    </template>
                    <template v-else-if="headerDialog == 'Moedas'">
                      <span class="font-bold">{{ item.codmoeda }}</span>
                      - {{ item.moeda }}
                    </template>
                    <template v-else-if="headerDialog == 'Centros de Custo'">
                      <span class="font-bold">
                        {{ item.codigocentrocusto }}
                      </span>
                      - {{ item.descricao }}
                    </template>
                  </span>
                </div>
              </div>

              <div
                v-if="pickListTargetFiltrado?.length === 0"
                class="text-center py-12 text-gray-400 dark:text-gray-500"
              >
                <i class="pi pi-inbox text-4xl mb-3 block"></i>
                <p class="font-medium">Nenhum item selecionado</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div
        class="bg-gray-50 dark:bg-slate-700/50 px-6 py-4 border-t border-gray-200 dark:border-slate-700 flex justify-end"
      >
        <Button
          @click="salvarDialogGenerico()"
          label="Salvar Alterações"
          icon="pi pi-check"
          severity="success"
          outlined
          class="!rounded-lg sm:w-auto w-full"
        />
      </div>
    </Dialog>

    <!-- Dialog Observação -->
    <Dialog
      v-model:visible="dialogObs"
      modal
      header="Observação"
      class="w-full max-w-sm"
      :pt="{
        header: 'bg-blue-50 dark:bg-blue-900/30 rounded-t-2xl',
        content: 'bg-white dark:bg-slate-800'
      }"
    >
      <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
        {{ obsSelecionada }}
      </div>
    </Dialog>
  </AuthenticatedLayout>
</template>
