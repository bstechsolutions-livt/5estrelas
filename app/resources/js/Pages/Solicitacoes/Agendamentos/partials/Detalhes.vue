<script setup>
import {
  deleteFile,
  formatarDataSemHoras,
  isImagem,
  swalConfirm,
  swalErro,
  toastWarning,
  tratarNome,
  uploadFile
} from "@/utils/globalFunctions"
import { split } from "postcss/lib/list"
import { ref, onMounted, watch } from "vue"
import { usePage } from "@inertiajs/vue3"
import Button from "primevue/button"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import Image from "primevue/image"
import Skeleton from "primevue/skeleton"
import TabView from "primevue/tabview"
import TabPanel from "primevue/tabpanel"
import Ticket from "../../Ticket.vue"
import BsFile2 from "@/Components/Componentes/BsFile2.vue"
import Agendamento from "../../Agendamento.vue"
import ViewFiles from "@/Components/Componentes/ViewFiles.vue"
import heic2any from "heic2any"

const props = defineProps({
  agendamento: {
    type: Array,
    required: true
  },
  usuarioLogado: {
    type: Array,
    required: true
  },
  permissoes: {},
  auth: {}
})

const emits = defineEmits([
  "fechar",
  "update:loading",
  "fechar",
  "acao:iniciar",
  "acao:finalizar",
  "acao:atualizar"
])
const page = usePage()
const isFlutter = ref(typeof window.flutter_inappwebview !== "undefined") // Verifica se está no ambiente Flutter
const dataSemHoras = ref("")
const endFilial = ref({})
const solicitacaoSelecionada = ref(null)
const dialogSolicitacao = ref(false)
const dialogResolver = ref(false)
const comentario = ref("")
const caminhoAss = ref(null)
const dialogAnexos = ref(false)
const arquivos = ref([])
const arquivosSelecionados = ref([])
const dialogImagem = ref(false)
const imagemSelecionada = ref(null)
const dialogViewFiles = ref(false)
const listaArquivosId = ref([])
const arquivoInicialId = ref(null)
const solicitacoes = ref("")
const carregado = ref(false)
const matriculaUser = ref("")
const dialogAgendamento = ref(false)
const dadosAgendamento = ref({ ...props.agendamento })
const qtdMinAnexos = ref(0)
const loadingHeic = ref(false)
const imagemConvertida = ref(null)

watch(
  () => props.agendamento,
  (newVal) => {
    dadosAgendamento.value = { ...newVal }
  },
  { immediate: true, deep: true }
)

onMounted(async () => {
  await getSolicitacoes()
  await getEndFilial()
  await getAnexos()
  dadosAgendamento.value.horario_inicial = extractTime(
    dadosAgendamento.value.data_agendamento
  )
  dadosAgendamento.value.horario_final = extractTime(
    dadosAgendamento.value.data_fim_agendamento
  )
  dataSemHoras.value = formatarDataSemHoras(
    split(dadosAgendamento.value.data_agendamento, " ")[0]
  )
  matriculaUser.value = props.usuarioLogado.matricula
    ? props.usuarioLogado.matricula
    : props.usuarioLogado
  carregado.value = true
})

async function getAnexos() {
  await axios
    .get("/solicitacoes/agendamento/buscar-anexos/" + dadosAgendamento.value.id)
    .then((res) => {
      arquivos.value = res.data
    })
    .catch((err) => {
      console.error(err)
    })
}

function extractTime(datetime) {
  if (!datetime) return "--:--"

  let dateObj = new Date(datetime)

  // Se a data for inválida, tenta ajustar o formato
  if (isNaN(dateObj.getTime())) {
    // Se não estiver no formato ISO, tente substituir hífens por barras
    if (datetime.includes("-") && !datetime.includes("T")) {
      dateObj = new Date(datetime.replace(/-/g, "/"))
    }
  }

  // Se ainda for inválida, retorna o valor padrão
  if (isNaN(dateObj.getTime())) return "--:--"

  const hours = String(dateObj.getHours()).padStart(2, "0")
  const minutes = String(dateObj.getMinutes()).padStart(2, "0")
  return `${hours}:${minutes}`
}

async function getSolicitacoes() {
  await axios
    .get(
      "/solicitacoes/agendamento/buscar-solicitacoes/" +
        dadosAgendamento.value.id
    )
    .then((res) => {
      solicitacoes.value = res.data
      if (solicitacoes.value.length > 0) {
        qtdMinAnexos.value =
          solicitacoes.value[0].assunto != null
            ? solicitacoes.value[0].assunto.qtd_min_anexos
            : 0
      } else {
        qtdMinAnexos.value = 0
      }
    })
    .catch((err) => {
      swalErro()
      console.error(err)
    })
}

async function atribuirAss(ass) {
  emits("update:loading", true)

  const id_agendamento = props.agendamento.id
  const matches = ass.match(/^data:(.*?);base64,/)
  const contentType = matches ? matches[1] : ""

  if (!contentType.startsWith("image/")) {
    swalErro(
      "Opss..",
      "O arquivo selecionado não é uma imagem válida. Por favor, envie uma imagem nos formatos JPG, PNG ou GIF."
    )
    return
  }

  const base64Data = ass.replace(/^data:(.*?);base64,/, "")
  const byteCharacters = atob(base64Data)
  const byteNumbers = new Array(byteCharacters.length)
  for (let i = 0; i < byteCharacters.length; i++) {
    byteNumbers[i] = byteCharacters.charCodeAt(i)
  }
  const byteArray = new Uint8Array(byteNumbers)
  const blob = new Blob([byteArray], { type: contentType })
  const nomeArquivo = `imagem-agendamento_${id_agendamento}_assinatura_${Date.now()}.png`
  const file = new File([blob], nomeArquivo, { type: contentType })

  if (file) {
    const response = await uploadFile(
      file,
      "intranet",
      "ass_agendamentos",
      nomeArquivo,
      props.usuarioLogado
    )
    if (response.success) {
      caminhoAss.value = response.data.file.id
      finalizaWeb(true)
    }
  } else {
    swalErro()
    return
  }
}

async function getEndFilial() {
  await axios
    .get(
      "/solicitacoes/agendamento/end-filial/" + dadosAgendamento.value.filial
    )
    .then((res) => {
      endFilial.value = res.data
    })
    .catch((err) => {
      console.error(err)
    })
}

function abrirSolicitacao(solicitacao_id) {
  solicitacaoSelecionada.value = solicitacao_id

  dialogSolicitacao.value = true
}

async function cancelarAgendamento(idAgendamento) {
  emits("update:loading", true)
  var params = {
    id: idAgendamento
  }
  await axios
    .post("/solicitacoes/agendamento/cancelar-agendamento", params)
    .then(async (res) => {
      window.location.reload()
    })
    .catch((e) => {
      console.error(e)
      swalErro()
    })
  emits("update:loading", false)
}

function fecharDialog() {
  emits("update:loading", false)
  emits("fechar")
}

async function iniciarAtendimento() {
  emits("update:loading", true)
  let params = {
    id_agendamento: dadosAgendamento.value.id
  }

  await axios
    .post("/solicitacoes/agendamento/iniciar-agendamento", params)
    .then((res) => {
      emits("acao:iniciar", res.data)
    })
    .catch((e) => {
      console.error(e)
    })
  emits("update:loading", false)
}

async function finalizarAgendamento() {
  if (arquivos.value.length < qtdMinAnexos.value) {
    swalErro(
      "",
      "São necessários no mínimo " +
        qtdMinAnexos.value +
        " anexos para finalizar o atendimento."
    )
    return
  }

  const response = await swalConfirm(
    "O serviço foi concluído ?",
    "",
    "Sim",
    "Não"
  )

  if (isFlutter.value) {
    if (response.isConfirmed) {
      finalizaFlutter()
    } else {
      finalizaWeb(false)
    }
  } else {
    finalizaWeb(response.isConfirmed)
  }
}

async function finalizaWeb(isConfirmed) {
  emits("update:loading", true)

  const resolveSolicitacao = isConfirmed

  if (resolveSolicitacao == false) {
    emits("update:loading", false)
    dialogResolver.value = true
    return
  }

  let params = {
    id_agendamento: dadosAgendamento.value.id,
    resolveSolicitacao: resolveSolicitacao,
    caminho_assinatura: caminhoAss.value ?? null
  }

  await axios
    .post("/solicitacoes/agendamento/finalizar-agendamento", params)
    .then((res) => {
      window.location.reload()
    })
    .catch((e) => {
      swalErro()
      console.error(e)
    })
  emits("update:loading", false)
}

async function finalizaFlutter() {
  emits("update:loading", true)

  if (isFlutter.value) {
    const assinatura =
      await window.flutter_inappwebview.callHandler("Assinatura")
    if (assinatura) {
      atribuirAss(assinatura)
    }
  } else {
    console.error("Canal 'Assinatura' não está disponível no Flutter.")
  }
  emits("update:loading", false)
}

async function resolverAgendamento() {
  emits("update:loading", true)

  let params = {
    id_agendamento: dadosAgendamento.value.id,
    resolveSolicitacao: false,
    comentario: comentario.value
  }

  await axios
    .post("/solicitacoes/agendamento/finalizar-agendamento", params)
    .then((res) => {
      window.location.reload()
    })
    .catch((e) => {
      swalErro()
      console.error(e)
    })
  emits("update:loading", false)
}

function atribuirArquivo(arquivo) {
  arquivosSelecionados.value = arquivo.map((arq) => ({
    nome_arquivo: arq.nome,
    arquivo: arq.file,
    tipo_arquivo: arq.extensao
  }))
}

async function loadingWebview(carregar) {
  if (!isFlutter.value) return
  try {
    await window.flutter_inappwebview.callHandler("Loading", carregar)
  } catch (error) {
    swalErro("Erro", "Não foi possível acessar o recurso do Flutter.")
  }
}

async function salvarArquivos() {
  emits("update:loading", true)
  loadingWebview(true)

  // Validar tamanho dos arquivos antes de fazer upload (limite: 50MB)
  const TAMANHO_MAXIMO = 50 * 1024 * 1024 // 50MB em bytes
  const arquivosGrandes = []

  for (const arq of arquivosSelecionados.value) {
    if (arq.arquivo && arq.arquivo.size > TAMANHO_MAXIMO) {
      const tamanhoMB = (arq.arquivo.size / (1024 * 1024)).toFixed(2)
      arquivosGrandes.push(
        `"${arq.arquivo.name || arq.nome_arquivo}" (${tamanhoMB} MB)`
      )
    }
  }

  if (arquivosGrandes.length > 0) {
    swalErro(
      "Arquivo muito grande",
      `O tamanho máximo permitido por arquivo é 50 MB. Os seguintes arquivos excedem o limite: ${arquivosGrandes.join(", ")}`
    )
    loadingWebview(false)
    emits("update:loading", false)
    return
  }

  for (const arq of arquivosSelecionados.value) {
    if (arq.arquivo) {
      const response = await uploadFile(
        arq.arquivo,
        "intranet",
        "anexos_agendamento",
        arq.descricao,
        props.usuarioLogado
      )

      if (response.success) {
        arq.id_caminho = response.data.file.id // Adiciona o ID ao objeto
      } else {
        swalErro(
          "Erro no upload",
          `Não foi possível enviar o arquivo "${arq.arquivo.name || arq.nome_arquivo || "desconhecido"}". Verifique o tamanho do arquivo e tente novamente.`
        )
        loadingWebview(false)
        emits("update:loading", false)
        return
      }
    }
  }

  let params = {
    id_agendamento: dadosAgendamento.value.id,
    arquivos: arquivosSelecionados.value
  }

  axios
    .post("/solicitacoes/agendamento/salvar-anexos", params)
    .then((res) => {
      // swalSucesso('Anexos salvos com sucesso!');
      getAnexos()
      dialogAnexos.value = false
    })
    .catch((e) => {
      swalErro()
      console.error(e)
    })

  arquivosSelecionados.value = []
  loadingWebview(false)
  emits("update:loading", false)
}

function formatDescricao(descricao) {
  const descFormat = descricao.split(".")[0]
  return descFormat
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
    case "heic":
    case "heif":
      return "fas fa-file-image"
    default:
      return "fas fa-file"
  }
}

async function redirectMaps(url) {
  if (isFlutter.value) {
    const rotas = await window.flutter_inappwebview.callHandler("Rotas", url)
  } else {
    window.open(url, "_blank")
  }
}

async function downloadArquivo(file) {
  try {
    // Verifica se existe caminho do arquivo
    if (!file.caminho_ext) {
      toastWarning("Arquivo não encontrado ou caminho inválido.")
      return
    }

    // Método 1: Tentar download direto via link
    const link = document.createElement("a")
    link.href = file.caminho_ext
    link.download = file.nome_arquivo || "arquivo"
    link.target = "_blank"

    // Adiciona o link ao DOM temporariamente
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch (error) {
    // Fallback: Tentar abrir em nova aba
    try {
      window.open(file.caminho_ext, "_blank")
    } catch (fallbackError) {
      toastWarning(
        "Não foi possível acessar o arquivo. Verifique se o link está funcionando."
      )
    }
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
      response = await fetch(file.caminho_ext, {
        mode: "cors",
        headers: {
          Accept: "image/*,*/*"
        }
      })
    } catch (corsError) {
      // Segunda tentativa: fetch sem CORS
      try {
        response = await fetch(file.caminho_ext, {
          headers: {
            Accept: "image/*,*/*"
          }
        })
      } catch (fetchError) {
        // Terceira tentativa: através do backend Laravel (se disponível)
        response = await fetch(
          `/api/proxy-image?url=${encodeURIComponent(file.caminho_ext)}`
        )
      }
    }

    if (!response || !response.ok) {
      throw new Error(
        `Erro HTTP: ${response?.status || "N/A"} - ${response?.statusText || "Falha na requisição"}`
      )
    }

    blob = await response.blob()

    // Verifica se o blob é válido
    if (blob.size === 0) {
      throw new Error("Arquivo vazio ou inacessível")
    }

    // Converte HEIC para JPEG com configurações mais robustas
    const convertedBlob = await heic2any({
      blob: blob,
      toType: "image/jpeg",
      quality: 0.8,
      multiple: false
    })

    // Cria URL temporária para a imagem convertida
    const jpegUrl = URL.createObjectURL(convertedBlob)

    // Cria objeto de arquivo simulado para o diálogo
    imagemConvertida.value = {
      ...file,
      caminho_ext: jpegUrl,
      tipo_arquivo: "jpeg",
      nome_arquivo: file.nome_arquivo.replace(/\.(heic|heif)$/i, ".jpeg")
    }

    return jpegUrl
  } catch (error) {
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

// Função para codificar URL de arquivo (trata caracteres especiais como # e acentos)
function encodeFileUrl(url) {
  if (!url) return url
  // Separa a URL em partes: protocolo + domínio + caminho
  const urlParts = url.split("/")
  // Codifica apenas o nome do arquivo (última parte)
  const filename = urlParts.pop()
  // Primeiro decodifica para evitar dupla codificação, depois codifica
  let decodedFilename
  try {
    decodedFilename = decodeURIComponent(filename)
  } catch (e) {
    // Se falhar na decodificação, usa o filename original
    decodedFilename = filename
  }
  const encodedFilename = encodeURIComponent(decodedFilename)
  return [...urlParts, encodedFilename].join("/")
}

async function verArquivo(file) {
  // Verifica se é arquivo HEIC/HEIF - usa visualização própria com conversão
  if (
    file.tipo_arquivo === "heic" ||
    file.tipo_arquivo === "heif" ||
    file.nome_arquivo.toLowerCase().endsWith(".heic") ||
    file.nome_arquivo.toLowerCase().endsWith(".heif")
  ) {
    // Tenta converter HEIC para visualização
    const jpegUrl = await converterHeicParaJpeg(file)

    if (jpegUrl) {
      // Se conversão foi bem-sucedida, abre no diálogo de imagem
      imagemSelecionada.value = imagemConvertida.value
      dialogImagem.value = true
    }
    // Se conversão falhou, o download automático já foi feito na função converterHeicParaJpeg
    return
  }

  // Para outros tipos de arquivo, usa o ViewFiles
  // Monta lista de IDs de todos os arquivos
  listaArquivosId.value = arquivos.value
    .filter((arq) => arq.id_caminho) // Filtra apenas arquivos com id_caminho
    .map((arq) => arq.id_caminho)

  // Define o arquivo inicial como o clicado
  arquivoInicialId.value = file.id_caminho

  // Abre o dialog do ViewFiles
  dialogViewFiles.value = true
}

function validaPermissao(perm) {
  const lista =
    props.permissoes ?? page.props?.auth?.user?.permissions ?? []
  return lista.includes("*") || lista.includes(perm)
}

async function editarAgendamento() {
  dialogAgendamento.value = true
}

function atualizaAgendamento(vAgendamento) {
  dadosAgendamento.value.mat_responsavel = vAgendamento.mat_esponsavel
  dadosAgendamento.value.nomeResponsavel = vAgendamento.nomeResponsavel

  dadosAgendamento.value.horario_inicial = extractTime(
    vAgendamento.data_agendamento
  )

  dadosAgendamento.value.data_agendamento = vAgendamento.data_agendamento
  dadosAgendamento.value.observacao = vAgendamento.observacao
  dataSemHoras.value = formatarDataSemHoras(
    split(vAgendamento.data_agendamento, " ")[0]
  )
  emits("acao:atualizar")
  dialogAgendamento.value = false
}

async function deletarAnexo(id, idCaminho) {
  await swalConfirm("Deseja realmente excluir o anexo?", "", "Sim", "Não").then(
    async (res) => {
      if (res.isConfirmed) {
        let params = {
          id: id
        }

        await axios
          .post("/solicitacoes/agendamento/deletar-anexo", params)
          .then(async (res) => {
            await deleteFile(idCaminho)
            await getAnexos()
          })
          .catch((e) => {
            swalErro()
            console.error(e)
          })
      }
    }
  )
}

function fecharDialogImagem() {
  // Limpa URL temporária se existir
  if (
    imagemConvertida.value &&
    imagemConvertida.value.caminho_ext.startsWith("blob:")
  ) {
    URL.revokeObjectURL(imagemConvertida.value.caminho_ext)
    imagemConvertida.value = null
  }
  dialogImagem.value = false
}
</script>

<template>
  <!-- Overlay de Loading HEIC -->
  <div
    v-if="loadingHeic"
    class="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-50 backdrop-blur-sm"
  >
    <div
      class="bg-white dark:bg-slate-800 rounded-2xl p-8 shadow-2xl max-w-sm mx-4 text-center border border-gray-200 dark:border-slate-700"
    >
      <div class="flex justify-center mb-4">
        <div class="relative">
          <div
            class="w-16 h-16 border-4 border-blue-200 dark:border-blue-900 rounded-full animate-spin border-t-blue-600 dark:border-t-blue-400"
          ></div>
          <div class="absolute inset-0 flex items-center justify-center">
            <i
              class="fas fa-image text-blue-600 dark:text-blue-400 text-xl"
            ></i>
          </div>
        </div>
      </div>
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">
        Convertendo arquivo HEIC
      </h3>
      <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">
        Preparando arquivo para visualização...
      </p>
      <p class="text-xs text-gray-500 dark:text-gray-500 mt-3">
        Isso pode levar alguns segundos
      </p>
    </div>
  </div>

  <!-- Loading Skeleton -->
  <div
    v-if="!carregado"
    class="p-6 bg-white dark:bg-slate-800"
  >
    <Skeleton class="!h-12 !rounded-xl mb-4"></Skeleton>
    <div class="flex gap-4 mb-4">
      <Skeleton class="!h-8 !w-24 !rounded-lg"></Skeleton>
      <Skeleton class="!h-8 !w-32 !rounded-lg"></Skeleton>
      <Skeleton class="!h-8 !w-24 !rounded-lg"></Skeleton>
    </div>
    <Skeleton class="!h-40 !rounded-xl mb-4"></Skeleton>
    <Skeleton class="!h-12 !rounded-xl"></Skeleton>
  </div>

  <!-- Conteúdo Principal -->
  <div
    v-else
    class="flex flex-col h-[100vh] sm:h-auto sm:max-h-[85vh] bg-white dark:bg-slate-800 sm:rounded-2xl overflow-hidden"
  >
    <!-- Header com Gradiente -->
    <div
      class="relative bg-gradient-to-r from-blue-500 to-blue-600 p-5 flex-shrink-0"
    >
      <!-- Botão Fechar -->
      <button
        @click="fecharDialog()"
        class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all duration-200 text-white"
      >
        <i class="pi pi-times"></i>
      </button>

      <!-- Info do Agendamento -->
      <div class="flex items-start gap-4">
        <div
          class="w-14 h-14 rounded-xl bg-white/20 flex items-center justify-center flex-shrink-0"
        >
          <i class="fas fa-calendar-day text-2xl text-white"></i>
        </div>
        <div class="flex-1 min-w-0">
          <h2 class="text-lg font-bold text-white truncate pr-8">
            {{ dadosAgendamento.descricao || "Agendamento" }}
          </h2>
          <p class="text-blue-100 text-sm flex items-center gap-2 mt-1">
            <i class="pi pi-user"></i>
            {{ tratarNome(dadosAgendamento.nomeResponsavel) }}
          </p>
        </div>
      </div>

      <!-- Status e Data -->
      <div class="flex flex-wrap items-center gap-3 mt-4">
        <!-- Badge de Status -->
        <span
          :class="[
            'px-3 py-1.5 rounded-full text-xs font-bold flex items-center gap-1.5',
            dadosAgendamento.status === 'aguardando'
              ? 'bg-amber-100 text-amber-700'
              : dadosAgendamento.status === 'em atendimento'
                ? 'bg-blue-100 text-blue-700'
                : dadosAgendamento.status === 'finalizado'
                  ? 'bg-emerald-100 text-emerald-700'
                  : 'bg-red-100 text-red-700'
          ]"
        >
          <i
            :class="[
              dadosAgendamento.status === 'aguardando'
                ? 'pi pi-clock'
                : dadosAgendamento.status === 'em atendimento'
                  ? 'pi pi-spin pi-cog'
                  : dadosAgendamento.status === 'finalizado'
                    ? 'pi pi-check-circle'
                    : 'pi pi-times-circle'
            ]"
          ></i>
          {{
            dadosAgendamento.status === "em atendimento"
              ? "Em Atendimento"
              : dadosAgendamento.status?.charAt(0).toUpperCase() +
                dadosAgendamento.status?.slice(1)
          }}
        </span>

        <!-- Data e Hora -->
        <span
          class="px-3 py-1.5 rounded-full bg-white/20 text-white text-xs font-medium flex items-center gap-1.5"
        >
          <i class="pi pi-calendar"></i>
          {{ dataSemHoras }}
        </span>
        <span
          class="px-3 py-1.5 rounded-full bg-white/20 text-white text-xs font-medium flex items-center gap-1.5"
        >
          <i class="pi pi-clock"></i>
          {{ dadosAgendamento.horario_inicial }}
        </span>
      </div>
    </div>

    <!-- Tabs de Conteúdo -->
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden">
      <TabView
        class="flex flex-col h-full [&_.p-tabview-panels]:flex-1 [&_.p-tabview-panels]:overflow-y-auto [&_.p-tabview-panels]:min-h-0 [&_.p-tabview-panels]:h-0 [&_.p-tabview-nav-container]:flex-shrink-0"
        :pt="{
          nav: {
            class:
              '!bg-gray-50 dark:!bg-slate-700/50 !border-b !border-gray-200 dark:!border-slate-600 !flex-nowrap'
          },
          navContainer: { class: '!overflow-visible' },
          tabpanel: { class: '!p-0 !h-full' }
        }"
      >
        <!-- Tab Localização -->
        <TabPanel>
          <template #header>
            <div class="flex items-center gap-1 sm:gap-2 px-0.5 sm:px-1">
              <i class="pi pi-map-marker text-blue-500 text-sm"></i>
              <span class="text-xs sm:text-sm">Localização</span>
            </div>
          </template>

          <div class="p-3 space-y-3">
            <!-- Card de Endereço Compacto -->
            <div
              class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-3 border border-gray-200 dark:border-slate-600"
            >
              <div class="flex items-center gap-2 mb-2">
                <i class="pi pi-building text-cyan-500 text-sm"></i>
                <h4 class="font-semibold text-gray-800 dark:text-white text-sm">
                  Endereço da Filial
                </h4>
              </div>

              <!-- Endereço em formato compacto -->
              <div class="space-y-1.5 text-sm">
                <!-- Logradouro + Número -->
                <p
                  v-if="endFilial.endereco"
                  class="text-gray-800 dark:text-gray-200 font-medium"
                >
                  {{ endFilial.endereco }}
                  <span v-if="endFilial.numero">, {{ endFilial.numero }}</span>
                </p>

                <!-- Bairro - Cidade/UF - CEP (uma linha) -->
                <p class="text-gray-600 dark:text-gray-300">
                  <span v-if="endFilial.bairro">{{ endFilial.bairro }}</span>
                  <span
                    v-if="
                      endFilial.bairro && (endFilial.cidade || endFilial.uf)
                    "
                  >
                    ·
                  </span>
                  <span v-if="endFilial.cidade">{{ endFilial.cidade }}</span>
                  <span v-if="endFilial.uf">/{{ endFilial.uf }}</span>
                  <span v-if="endFilial.cep">· {{ endFilial.cep }}</span>
                </p>

                <!-- Complemento -->
                <p
                  v-if="endFilial.complemento"
                  class="text-gray-500 dark:text-gray-400 text-xs"
                >
                  {{ endFilial.complemento }}
                </p>
              </div>

              <!-- Botão Rota -->
              <Button
                v-if="dadosAgendamento.rota"
                @click="redirectMaps(dadosAgendamento.rota)"
                icon="pi pi-map"
                label="Abrir Rota no Maps"
                outlined
                severity="info"
                size="small"
                class="w-full mt-3"
              />
            </div>

            <!-- Observação -->
            <div
              v-if="dadosAgendamento.observacao"
              class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-4 border border-amber-200 dark:border-amber-800"
            >
              <div class="flex items-center gap-2 mb-2">
                <i class="pi pi-info-circle text-amber-500"></i>
                <h4 class="font-semibold text-amber-800 dark:text-amber-200">
                  Observação
                </h4>
              </div>
              <p class="text-sm text-amber-700 dark:text-amber-300">
                {{ dadosAgendamento.observacao }}
              </p>
            </div>
          </div>
        </TabPanel>

        <!-- Tab Tickets -->
        <TabPanel>
          <template #header>
            <div class="flex items-center gap-1 sm:gap-2 px-0.5 sm:px-1">
              <i class="pi pi-list text-violet-500 text-sm"></i>
              <span class="text-xs sm:text-sm">Tickets</span>
              <span
                v-if="solicitacoes?.length"
                class="w-4 h-4 sm:w-5 sm:h-5 rounded-full bg-violet-500 text-white text-[10px] sm:text-xs flex items-center justify-center"
              >
                {{ solicitacoes.length }}
              </span>
            </div>
          </template>

          <div class="p-4">
            <div
              v-if="solicitacoes?.length"
              class="space-y-3"
            >
              <div
                v-for="solicitacao in solicitacoes.filter((sol) =>
                  sol.agendamentos.filter((ag) => ag.id == dadosAgendamento.id)
                )"
                :key="solicitacao.id"
                @click="abrirSolicitacao(solicitacao.id)"
                class="bg-white dark:bg-slate-700 rounded-xl p-4 border border-gray-200 dark:border-slate-600 cursor-pointer hover:border-violet-300 dark:hover:border-violet-500 hover:shadow-md transition-all duration-200 group"
              >
                <div class="flex items-start gap-3">
                  <div
                    class="w-10 h-10 rounded-lg bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform"
                  >
                    <i
                      class="pi pi-file text-violet-600 dark:text-violet-400"
                    ></i>
                  </div>
                  <div class="flex-1 min-w-0">
                    <h4
                      class="font-semibold text-gray-800 dark:text-white text-sm"
                    >
                      #{{ solicitacao.id }} -
                      {{ solicitacao.assunto?.assunto || "Transferida" }}
                    </h4>
                    <p
                      class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mt-1"
                    >
                      <i class="pi pi-user"></i>
                      {{ solicitacao.nomeSolicitante }}
                    </p>
                  </div>
                  <i
                    class="pi pi-chevron-right text-gray-400 group-hover:text-violet-500 transition-colors"
                  ></i>
                </div>
              </div>
            </div>

            <!-- Estado Vazio -->
            <div
              v-else
              class="text-center py-8"
            >
              <div
                class="w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4"
              >
                <i class="pi pi-inbox text-2xl text-gray-400"></i>
              </div>
              <p class="text-gray-500 dark:text-gray-400">
                Nenhuma ticket vinculada
              </p>
            </div>
          </div>
        </TabPanel>

        <!-- Tab Anexos -->
        <TabPanel>
          <template #header>
            <div class="flex items-center gap-1 sm:gap-2 px-0.5 sm:px-1">
              <i class="pi pi-paperclip text-rose-500 text-sm"></i>
              <span class="text-xs sm:text-sm">Anexos</span>
              <span
                v-if="arquivos?.length"
                class="w-4 h-4 sm:w-5 sm:h-5 rounded-full bg-rose-500 text-white text-[10px] sm:text-xs flex items-center justify-center"
              >
                {{ arquivos.length }}
              </span>
            </div>
          </template>

          <div class="p-4">
            <!-- Botão Adicionar Anexos -->
            <Button
              v-if="
                matriculaUser == dadosAgendamento.mat_responsavel &&
                dadosAgendamento.status == 'em atendimento'
              "
              @click="dialogAnexos = true"
              icon="pi pi-plus"
              severity="info"
              label="Adicionar Anexos"
              outlined
              :disabled="loadingHeic"
              class="w-full mb-4"
            />

            <!-- Lista de Anexos -->
            <div
              v-if="arquivos?.length"
              class="space-y-3"
            >
              <div
                v-for="arquivo in arquivos"
                :key="arquivo.id"
                :class="[
                  'bg-white dark:bg-slate-700 rounded-xl p-4 border border-gray-200 dark:border-slate-600 cursor-pointer hover:border-rose-300 dark:hover:border-rose-500 hover:shadow-md transition-all duration-200',
                  { 'opacity-50 pointer-events-none': loadingHeic }
                ]"
                @click="verArquivo(arquivo)"
              >
                <div class="flex items-center gap-3">
                  <div
                    :class="[
                      'w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0',
                      arquivo.tipo_arquivo === 'pdf'
                        ? 'bg-red-100 dark:bg-red-900/30'
                        : ['xlsx', 'xls'].includes(arquivo.tipo_arquivo)
                          ? 'bg-green-100 dark:bg-green-900/30'
                          : ['doc', 'docx'].includes(arquivo.tipo_arquivo)
                            ? 'bg-blue-100 dark:bg-blue-900/30'
                            : 'bg-rose-100 dark:bg-rose-900/30'
                    ]"
                  >
                    <i
                      :class="[
                        getIcon(arquivo.tipo_arquivo),
                        arquivo.tipo_arquivo === 'pdf'
                          ? 'text-red-600 dark:text-red-400'
                          : ['xlsx', 'xls'].includes(arquivo.tipo_arquivo)
                            ? 'text-green-600 dark:text-green-400'
                            : ['doc', 'docx'].includes(arquivo.tipo_arquivo)
                              ? 'text-blue-600 dark:text-blue-400'
                              : 'text-rose-600 dark:text-rose-400'
                      ]"
                    ></i>
                  </div>
                  <div class="flex-1 min-w-0">
                    <h4
                      class="font-medium text-gray-800 dark:text-white text-sm truncate"
                    >
                      {{ arquivo.nome_arquivo }}
                    </h4>
                    <p
                      class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mt-0.5"
                    >
                      <i class="pi pi-user"></i>
                      {{ arquivo.nome_user }}
                    </p>
                  </div>
                  <div class="flex items-center gap-2">
                    <button
                      class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-600 flex items-center justify-center hover:bg-cyan-100 dark:hover:bg-cyan-900/30 transition-colors"
                    >
                      <i
                        class="pi pi-eye text-gray-500 dark:text-gray-400 hover:text-cyan-600"
                      ></i>
                    </button>
                    <button
                      v-if="dadosAgendamento.status === 'em atendimento'"
                      @click.stop="deletarAnexo(arquivo.id, arquivo.id_caminho)"
                      class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-600 flex items-center justify-center hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors"
                    >
                      <i
                        class="pi pi-trash text-gray-500 dark:text-gray-400 hover:text-red-600"
                      ></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Estado Vazio -->
            <div
              v-else
              class="text-center py-8"
            >
              <div
                class="w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4"
              >
                <i class="pi pi-paperclip text-2xl text-gray-400"></i>
              </div>
              <p class="text-gray-500 dark:text-gray-400">
                Nenhum anexo adicionado
              </p>
            </div>
          </div>
        </TabPanel>
      </TabView>
    </div>

    <!-- Footer com Ações -->
    <div
      v-if="
        dadosAgendamento.status !== 'finalizado' &&
        dadosAgendamento.status !== 'cancelado'
      "
      class="p-4 pb-[max(1rem,env(safe-area-inset-bottom))] border-t border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-700/50 flex-shrink-0"
    >
      <div class="flex gap-2">
        <!-- Botão Iniciar/Finalizar Atendimento -->
        <Button
          v-if="matriculaUser == agendamento.mat_responsavel"
          @click="
            dadosAgendamento.status === 'em atendimento'
              ? finalizarAgendamento()
              : iniciarAtendimento()
          "
          :icon="
            dadosAgendamento.status === 'em atendimento'
              ? 'pi pi-check'
              : 'pi pi-play'
          "
          outlined
          :label="
            dadosAgendamento.status === 'em atendimento'
              ? 'Finalizar Atendimento'
              : 'Iniciar Atendimento'
          "
          class="flex-1 !font-bold !py-3"
        />

        <!-- Botões Editar e Cancelar (apenas para aguardando) -->
        <template
          v-if="
            validaPermissao('solicitacoes.lista.cancelar-agendamento') &&
            dadosAgendamento.status === 'aguardando'
          "
        >
          <Button
            @click="editarAgendamento()"
            icon="pi pi-pencil"
            label="Editar"
            outlined
            severity="info"
            class="!py-3 [&_.p-button-label]:hidden sm:[&_.p-button-label]:inline"
          />
          <Button
            @click="cancelarAgendamento(dadosAgendamento.id)"
            icon="pi pi-times"
            label="Cancelar"
            outlined
            severity="danger"
            class="!py-3 [&_.p-button-label]:hidden sm:[&_.p-button-label]:inline"
          />
        </template>
      </div>
    </div>
  </div>

  <!-- Dialog Editar Agendamento -->
  <Dialog
    position="top"
    v-model:visible="dialogAgendamento"
    modal
    :closable="false"
    :showHeader="false"
    class="!w-[100vw] sm:!w-auto !max-w-4xl"
    :pt="{
      root: { class: '!rounded-2xl !overflow-hidden !border-0' },
      mask: { class: 'backdrop-blur-sm' },
      content: { class: '!p-0 !bg-white dark:!bg-slate-800' }
    }"
  >
    <!-- Header Customizado -->
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-5 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div
            class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"
          >
            <i class="pi pi-pencil text-white text-lg"></i>
          </div>
          <h3 class="text-white font-semibold text-lg">Editar Agendamento</h3>
        </div>
        <button
          @click="dialogAgendamento = false"
          class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all duration-200 text-white"
        >
          <i class="pi pi-times text-sm"></i>
        </button>
      </div>
    </div>

    <div class="p-5">
      <Agendamento
        @atualizar="atualizaAgendamento($event)"
        :edit="true"
        :agendamentoEdit="[dadosAgendamento]"
        :solicitacoes="solicitacoes"
      />
    </div>
  </Dialog>

  <!-- Dialog Ticket -->
  <Dialog
    v-model:visible="dialogSolicitacao"
    modal
    position="top"
    :showHeader="false"
    :dismissableMask="true"
    class="!bg-transparent !border-0 !shadow-none !w-screen !h-screen !max-w-none !max-h-none !m-0"
    :pt="{
      root: {
        class: '!w-screen !h-screen !max-w-none !max-h-none !rounded-none'
      },
      mask: { class: 'backdrop-blur-sm bg-black/50 !overflow-hidden' },
      content: {
        class: '!p-0 !bg-transparent !w-screen !h-screen !overflow-hidden'
      }
    }"
  >
    <div class="w-screen h-screen overflow-hidden">
      <Ticket
        :solicitacao_id="solicitacaoSelecionada"
        :permissoes
        :auth="props.auth"
        @fecharDialogo="dialogSolicitacao = false"
      />
    </div>
  </Dialog>

  <!-- Dialog Anexos -->
  <Dialog
    position="top"
    v-model:visible="dialogAnexos"
    modal
    :showHeader="false"
    :closeOnEscape="false"
    class="!w-[95vw] sm:!w-[500px]"
    :pt="{
      root: { class: '!rounded-2xl !overflow-hidden !border-0' },
      mask: { class: 'backdrop-blur-sm' },
      content: { class: '!p-0 !bg-white dark:!bg-slate-800' }
    }"
  >
    <!-- Header Customizado -->
    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-5 py-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center"
          >
            <i class="pi pi-paperclip text-white text-lg"></i>
          </div>
          <h3 class="text-white font-semibold text-lg">Adicionar Anexos</h3>
        </div>
        <button
          @click="dialogAnexos = false"
          class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all duration-200 text-white"
        >
          <i class="pi pi-times text-sm"></i>
        </button>
      </div>
    </div>

    <div class="p-5">
      <BsFile2
        @atualizarLista="atribuirArquivo"
        :isVertical="true"
        :max-file-size="20"
        :aceitar-um-arquivo="false"
      />

      <Button
        @click="salvarArquivos()"
        icon="pi pi-save"
        label="Salvar Arquivos"
        :disabled="!arquivosSelecionados.length > 0"
        outlined
        class="w-full mt-10"
      />
    </div>
  </Dialog>

  <!-- Dialog Resolver -->
  <Dialog
    position="top"
    v-model:visible="dialogResolver"
    modal
    header="Finalizar Agendamento"
    class="!w-[95vw] sm:!w-[400px]"
    :pt="{
      root: { class: '!rounded-2xl' },
      mask: { class: 'backdrop-blur-sm' },
      header: {
        class:
          '!bg-gradient-to-r !from-amber-500 !to-orange-600 !text-white !rounded-t-2xl'
      },
      content: { class: '!bg-white dark:!bg-slate-800' }
    }"
  >
    <div class="space-y-4">
      <div
        class="bg-amber-50 dark:bg-amber-900/20 rounded-xl p-4 border border-amber-200 dark:border-amber-800"
      >
        <p class="text-sm text-amber-700 dark:text-amber-300">
          Por favor, descreva o motivo pelo qual o serviço não foi finalizado:
        </p>
      </div>

      <Textarea
        v-model="comentario"
        rows="6"
        placeholder="Descreva o motivo..."
        class="w-full !rounded-xl"
        :pt="{
          root: {
            class: '!border-gray-300 dark:!border-slate-600 dark:!bg-slate-700'
          }
        }"
      />

      <Button
        @click="resolverAgendamento()"
        icon="pi pi-check"
        label="Confirmar Finalização"
        :disabled="comentario.length < 10"
        class="w-full !bg-gradient-to-r !from-emerald-500 !to-green-600 hover:!from-emerald-600 hover:!to-green-700 !border-0"
      />
    </div>
  </Dialog>

  <!-- Dialog Imagem -->
  <Dialog
    position="top"
    v-model:visible="dialogImagem"
    modal
    header="Pré-visualização"
    :closeOnEscape="false"
    @update:visible="
      (value) => {
        if (!value) fecharDialogImagem()
      }
    "
    :pt="{
      root: { class: '!rounded-2xl' },
      mask: { class: 'backdrop-blur-sm' },
      header: { class: '!bg-gray-100 dark:!bg-slate-700 !rounded-t-2xl' },
      content: { class: '!bg-white dark:!bg-slate-800 !p-4' }
    }"
  >
    <div
      v-if="loadingHeic"
      class="flex flex-col items-center justify-center p-8"
    >
      <div class="flex justify-center mb-4">
        <div class="relative">
          <div
            class="w-12 h-12 border-4 border-cyan-200 rounded-full animate-spin border-t-cyan-600"
          ></div>
          <div class="absolute inset-0 flex items-center justify-center">
            <i class="fas fa-image text-cyan-600"></i>
          </div>
        </div>
      </div>
      <p class="text-gray-600 dark:text-gray-400 text-center">
        Convertendo arquivo HEIC...
      </p>
    </div>
    <Image
      v-else-if="imagemSelecionada"
      class="max-w-full rounded-xl"
      :src="imagemSelecionada.caminho_ext"
      preview
    />
  </Dialog>

  <!-- ViewFiles para visualização de arquivos -->
  <ViewFiles
    v-model:visible="dialogViewFiles"
    :listaArquivosId="listaArquivosId"
    :arquivoInicialId="arquivoInicialId"
  />
</template>
