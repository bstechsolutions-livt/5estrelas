<script setup>
import { swalErro, getDevice, toastError } from "@/utils/globalFunctions"
import { Button, Dialog, Image } from "primevue"
import { onMounted, onBeforeUnmount, ref, computed } from "vue"

const emit = defineEmits(["atualizarLista", "deletarArquivo"])
const props = defineProps({
  aceitarUmArquivo: { type: Boolean, default: false },
  isVertical: { type: Boolean, default: false },
  extensoesPermitidas: { type: Array, default: () => [] },
  permitirPreview: { type: Boolean, default: true },
  maxWidth: { type: Number, default: null },
  maxHeight: { type: Number, default: null },
  minWidth: { type: Number, default: null },
  minHeight: { type: Number, default: null },
  maxFileSize: { type: Number, default: null }, // Tamanho máximo em MB
  maxVideoDuration: { type: Number, default: null }, // Duração máxima de vídeo em segundos
  videoProporcao: { type: String, default: null }, // Proporção do vídeo (ex: "10:18")
  modeloImagem: { type: String, default: null }, // banner, quadrado, retrato, etc.
  disabled: { type: Boolean, default: false },
  ocultarLista: { type: Boolean, default: false } // Se true, não mostra a lista de arquivos
})

const isFlutter = ref(typeof window.flutter_inappwebview !== "undefined")
const showTipoArquivo = ref(false)
const arquivos = ref([])
const dragOver = ref(false)
const device = ref("")
const isFocused = ref(false)

// Referência para o input de arquivo e a zona de drop
const fileInput = ref(null)
const dropZone = ref(null)

onMounted(async () => {
  device.value = await getDevice()
  document.addEventListener("paste", handlePaste)
})

onBeforeUnmount(() => {
  document.removeEventListener("paste", handlePaste)
})

const extensoesPreview = ["png", "jpg", "jpeg", "gif", "webp"]
const extensoesVideo = ["mp4", "avi", "mov", "webm", "mkv", "wmv"]
const podeFazerPreview = (extensao) =>
  props.permitirPreview && extensoesPreview.includes(extensao.toLowerCase())
const ehVideo = (extensao) => extensoesVideo.includes(extensao.toLowerCase())

// Modelos de imagem predefinidos
const modelosImagem = {
  banner: { proporção: "16:9", dimensoes: { w: 1920, h: 1080 } },
  bannerWide: { proporção: "21:9", dimensoes: { w: 2560, h: 1080 } },
  quadrado: { proporção: "1:1", dimensoes: { w: 1200, h: 1200 } },
  retrato: { proporção: "3:4", dimensoes: { w: 900, h: 1200 } },
  paisagem: { proporção: "4:3", dimensoes: { w: 1200, h: 900 } },
  card: { proporção: "5:4", dimensoes: { w: 1000, h: 800 } },
  thumbnail: { proporção: "16:10", dimensoes: { w: 640, h: 400 } },
  story: { proporção: "9:16", dimensoes: { w: 1080, h: 1920 } },
  capaFacebook: { proporção: "820:312", dimensoes: { w: 820, h: 312 } },
  postInstagram: { proporção: "1:1", dimensoes: { w: 1080, h: 1080 } },
  postagem: { proporção: "10:18", dimensoes: { w: 1080, h: 1944 } }
}

// Computed para obter dimensões baseadas no modelo
const dimensoesModelo = computed(() => {
  if (!props.modeloImagem || !modelosImagem[props.modeloImagem]) {
    return null
  }
  return modelosImagem[props.modeloImagem].dimensoes
})

// Função para validar dimensões da imagem
function validarDimensoesImagem(file) {
  return new Promise((resolve) => {
    const extensao = file.name.split(".").pop().toLowerCase()

    // Se não for imagem, aceita automaticamente
    if (!extensoesPreview.includes(extensao)) {
      resolve({ valido: true })
      return
    }

    // Se não tiver limites definidos e nem modelo, aceita
    const temLimites =
      props.maxWidth || props.maxHeight || props.minWidth || props.minHeight
    const temModelo = props.modeloImagem && dimensoesModelo.value

    if (!temLimites && !temModelo) {
      resolve({ valido: true })
      return
    }

    const img = new window.Image()
    img.onload = function () {
      let mensagem = ""
      let valido = true

      // Validação com modelo predefinido
      if (temModelo) {
        const modelo = dimensoesModelo.value
        if (this.width !== modelo.w || this.height !== modelo.h) {
          const modeloInfo = modelosImagem[props.modeloImagem]
          mensagem = `Imagem deve ter exatamente ${modelo.w}x${modelo.h}px (proporção ${modeloInfo.proporção}). Tamanho atual: ${this.width}x${this.height}px.`
          valido = false
        }
      } else {
        // Validações de dimensões mínimas e máximas
        const larguraAbaixoMin = props.minWidth && this.width < props.minWidth
        const alturaAbaixoMin = props.minHeight && this.height < props.minHeight
        const larguraExcedida = props.maxWidth && this.width > props.maxWidth
        const alturaExcedida = props.maxHeight && this.height > props.maxHeight

        if (
          larguraAbaixoMin ||
          alturaAbaixoMin ||
          larguraExcedida ||
          alturaExcedida
        ) {
          valido = false
          mensagem = "Dimensões da imagem inválidas! "

          if (larguraAbaixoMin) {
            mensagem += `Largura mínima: ${props.minWidth}px. Atual: ${this.width}px. `
          }
          if (alturaAbaixoMin) {
            mensagem += `Altura mínima: ${props.minHeight}px. Atual: ${this.height}px. `
          }
          if (larguraExcedida) {
            mensagem += `Largura máxima: ${props.maxWidth}px. Atual: ${this.width}px. `
          }
          if (alturaExcedida) {
            mensagem += `Altura máxima: ${props.maxHeight}px. Atual: ${this.height}px. `
          }
        }
      }

      if (!valido) {
        resolve({
          valido: false,
          mensagem,
          dimensoes: { width: this.width, height: this.height }
        })
      } else {
        resolve({ valido: true })
      }
    }

    img.onerror = function () {
      resolve({ valido: true }) // Se não conseguir carregar, aceita (pode não ser imagem válida)
    }

    img.src = URL.createObjectURL(file)
  })
}

// Função para validar duração e proporção do vídeo
function validarDuracaoVideo(file) {
  return new Promise((resolve) => {
    const extensao = file.name.split(".").pop().toLowerCase()

    // Se não for vídeo, aceita automaticamente
    if (!ehVideo(extensao)) {
      resolve({ valido: true })
      return
    }

    // Se não tiver limite de duração e nem proporção definida, aceita
    if (!props.maxVideoDuration && !props.videoProporcao) {
      resolve({ valido: true })
      return
    }

    const video = document.createElement("video")
    video.preload = "metadata"

    video.onloadedmetadata = function () {
      window.URL.revokeObjectURL(video.src)
      const duracao = video.duration
      const videoWidth = video.videoWidth
      const videoHeight = video.videoHeight

      // Validar duração (com tolerância de 1 segundo para arredondamentos)
      const toleranciaDuracao = 1
      if (
        props.maxVideoDuration &&
        duracao > props.maxVideoDuration + toleranciaDuracao
      ) {
        const minutos = Math.floor(props.maxVideoDuration / 60)
        const segundos = props.maxVideoDuration % 60
        const duracaoAtualMin = Math.floor(duracao / 60)
        const duracaoAtualSeg = Math.floor(duracao % 60)

        const limiteFormatado =
          minutos > 0
            ? `${minutos}:${segundos.toString().padStart(2, "0")} minutos`
            : `${segundos} segundos`
        const atualFormatado =
          duracaoAtualMin > 0
            ? `${duracaoAtualMin}:${duracaoAtualSeg.toString().padStart(2, "0")} minutos`
            : `${duracaoAtualSeg} segundos`

        resolve({
          valido: false,
          mensagem: `Vídeo muito longo! Duração máxima: ${limiteFormatado}. Duração atual: ${atualFormatado}.`
        })
        return
      }

      // Validar proporção do vídeo
      if (props.videoProporcao) {
        const [propW, propH] = props.videoProporcao.split(":").map(Number)
        const proporcaoEsperada = propW / propH
        const proporcaoAtual = videoWidth / videoHeight
        const tolerancia = 0.05 // 5% de tolerância para pequenas variações

        if (Math.abs(proporcaoEsperada - proporcaoAtual) > tolerancia) {
          resolve({
            valido: false,
            mensagem: `Proporção do vídeo inválida! Esperado: ${props.videoProporcao}. Atual: ${videoWidth}x${videoHeight}px.`
          })
          return
        }
      }

      resolve({ valido: true })
    }

    video.onerror = function () {
      resolve({ valido: true }) // Se não conseguir carregar, aceita
    }

    video.src = URL.createObjectURL(file)
  })
}

async function escolherTipoArquivo(tipo) {
  if (!isFlutter.value) return
  showTipoArquivo.value = false
  try {
    const imagem = await window.flutter_inappwebview.callHandler(
      "Imagem",
      tipo,
      props.aceitarUmArquivo
    )
    if (imagem) {
      loadingWebview(true)
      if (Array.isArray(imagem)) {
        // Se aceitar apenas um arquivo, processa apenas o primeiro
        if (props.aceitarUmArquivo) {
          await atribuirImagemFlutter(imagem[0])
        } else {
          // Se aceitar múltiplos, processa todos
          for (const uri of imagem) await atribuirImagemFlutter(uri)
        }
      } else {
        await atribuirImagemFlutter(imagem)
      }
      loadingWebview(false)
    }
  } catch (error) {
    swalErro("Erro", "Não foi possível acessar o recurso do Flutter.")
  }
}

async function loadingWebview(carregar) {
  if (!isFlutter.value) return
  try {
    await window.flutter_inappwebview.callHandler("Loading", carregar)
  } catch (error) {
    swalErro("Erro", "Não foi possível acessar o recurso do Flutter.")
  }
}

async function atribuirImagemFlutter(arquivo) {
  const matches = arquivo.match(/^data:(.*?);base64,/)
  const contentType = matches ? matches[1] : ""
  let extension = await getExtensionFromContentType(contentType)
  const base64Data = arquivo.replace(/^data:(.*?);base64,/, "")
  const byteCharacters = atob(base64Data)
  const byteNumbers = new Array(byteCharacters.length)
  for (let i = 0; i < byteCharacters.length; i++)
    byteNumbers[i] = byteCharacters.charCodeAt(i)
  const byteArray = new Uint8Array(byteNumbers)
  const blob = new Blob([byteArray], { type: contentType })
  const nomeArquivo = `arquivo_${Date.now()}.${extension}`
  const file = new File([blob], nomeArquivo, { type: contentType })
  await adicionarArquivo(file)
}

async function getExtensionFromContentType(contentType) {
  if (!contentType || typeof contentType !== "string") return ""
  const [type, rawSubtype = ""] = contentType.split("/")
  const subtype = rawSubtype.split(";")[0].toLowerCase()
  const imgMap = {
    jpeg: "jpg",
    jfif: "jpg",
    jpg: "jpg",
    png: "png",
    gif: "gif",
    bmp: "bmp",
    webp: "webp",
    "svg+xml": "svg",
    ico: "ico"
  }
  if (type === "image") {
    if (imgMap[subtype]) return imgMap[subtype]
    const base = subtype.split("+")[0]
    return imgMap[base] || base
  }
  return subtype.split("+")[0]
}

async function adicionarArquivo(file) {
  const extensao = file.name.split(".").pop().toLowerCase()

  if (
    props.extensoesPermitidas.length &&
    !props.extensoesPermitidas.includes(extensao)
  ) {
    toastError(`Tipo de arquivo .${extensao} não é permitido.`)
    return
  }

  // Validar tamanho do arquivo
  if (props.maxFileSize) {
    const tamanhoMB = file.size / (1024 * 1024) // Converter para MB
    if (tamanhoMB > props.maxFileSize) {
      toastError(
        `Arquivo muito grande! Tamanho máximo: ${props.maxFileSize}MB. Tamanho atual: ${tamanhoMB.toFixed(2)}MB.`
      )
      return
    }
  }

  // Validar dimensões da imagem
  const validacao = await validarDimensoesImagem(file)
  if (!validacao.valido) {
    toastError(validacao.mensagem)
    return
  }

  // Validar duração do vídeo
  const validacaoVideo = await validarDuracaoVideo(file)
  if (!validacaoVideo.valido) {
    toastError(validacaoVideo.mensagem)
    return
  }

  const arquivoInfo = {
    file: file, // Garante que o objeto File seja mantido
    nome: file.name,
    tamanho: (file.size / 1024).toFixed(2),
    extensao
  }

  // Verifica se o objeto File foi preservado corretamente
  if (!arquivoInfo.file || typeof arquivoInfo.file !== "object") {
    console.error(
      "Objeto File não foi preservado corretamente:",
      arquivoInfo.file
    )
    toastError("Erro ao processar o arquivo. Tente novamente.")
    return
  }

  arquivos.value = props.aceitarUmArquivo
    ? [arquivoInfo]
    : [...arquivos.value, arquivoInfo]

  // Quando aceitarUmArquivo = true, emite apenas o objeto, senão emite o array
  const valorEmitir = props.aceitarUmArquivo ? arquivoInfo : arquivos.value
  emit("atualizarLista", valorEmitir)

  // Redefine o valor do input de arquivo
  if (fileInput.value) {
    fileInput.value.value = null
  }
}

async function processarArquivos(files) {
  if (!files) return

  // Se aceitar apenas um arquivo, processa apenas o primeiro
  if (props.aceitarUmArquivo) {
    const firstFile = Array.from(files)[0]
    if (firstFile) {
      await adicionarArquivo(firstFile)
    }
    return
  }

  // Se aceitar múltiplos arquivos, processa todos
  for (const file of Array.from(files)) {
    await adicionarArquivo(file)
  }
}

async function handleDrop(event) {
  event.preventDefault()
  dragOver.value = false
  await processarArquivos(event.dataTransfer.files)
}

async function handlePaste(event) {
  // Só processa o paste se este componente estiver focado
  if (!isFocused.value) return

  const arquivosPaste = []
  for (const item of event.clipboardData.items) {
    if (item.type.startsWith("image/")) arquivosPaste.push(item.getAsFile())
  }
  if (arquivosPaste.length) await processarArquivos(arquivosPaste)
}

function handleFocus() {
  isFocused.value = true
}

function handleBlur() {
  isFocused.value = false
}

function handleDragOver(event) {
  event.preventDefault()
  dragOver.value = true
}

function handleDragLeave() {
  dragOver.value = false
}

function verArquivo(file) {
  return URL.createObjectURL(file)
}

function removerArquivo(index) {
  arquivos.value.splice(index, 1) // Remove o arquivo da lista interna

  // Emite o evento atualizarLista com o valor correto baseado na prop aceitarUmArquivo
  const valorEmitir = props.aceitarUmArquivo ? null : arquivos.value
  emit("atualizarLista", valorEmitir)
  emit("deletarArquivo") // Mantém compatibilidade com o evento original

  // Redefine o valor do input de arquivo
  if (fileInput.value) {
    fileInput.value.value = null
  }
}
</script>

<template>
  <div class="flex flex-col w-full space-y-2 select-none">
    <div class="grid grid-cols-1 gap-4">
      <div
        v-if="!ocultarLista"
        v-for="(arquivo, index) in arquivos"
        :key="index"
        class="relative flex h-24 p-2 space-x-4 bg-slate-50 border rounded-md"
      >
        <Image
          v-if="podeFazerPreview(arquivo.extensao)"
          preview
          :src="verArquivo(arquivo.file)"
          image-class="w-[76px] h-[76px] object-contain"
        />
        <div
          v-else
          class="w-[80px] h-[76px] flex items-center justify-center border-2 p-2 font-bold uppercase rounded-md text-blue-500 bg-white shadow-md truncate"
        >
          {{ arquivo.extensao }}
        </div>
        <div class="flex-1">
          <div
            class="text-[10px] hd:text-sm font-bold truncate w-40"
            :title="arquivo.nome"
          >
            {{ arquivo.nome }}
          </div>
          <div class="text-xs text-gray-500 hd:text-sm">
            <div>Extensão: {{ arquivo.extensao }}</div>
            <div>Tamanho: {{ arquivo.tamanho }} KB</div>
          </div>
        </div>
        <Button
          @click="removerArquivo(index)"
          icon="pi pi-trash"
          severity="danger"
          outlined
          class="!w-8 !h-8"
          icon-class="!text-md"
          :disabled="props.disabled"
          title="Remover arquivo"
        />
      </div>

      <div
        class="w-full h-24"
        @dragover="handleDragOver"
        @dragleave="handleDragLeave"
        @drop="handleDrop"
      >
        <div
          v-if="isFlutter"
          class="flex flex-col items-center justify-center w-full p-2 space-y-2 border rounded-md"
        >
          <div class="font-bold text-center text-gray-500">
            Nenhum arquivo foi adicionado ainda
          </div>
          <Button
            @click="showTipoArquivo = true"
            icon="pi pi-cloud-upload"
            severity="info"
            outlined
            :disabled="props.disabled"
            :label="
              aceitarUmArquivo && arquivos.length
                ? 'Trocar arquivo'
                : 'Selecionar arquivo'
            "
          />
        </div>
        <div
          v-else
          class="w-full"
        >
          <div
            ref="dropZone"
            tabindex="0"
            @focus="handleFocus"
            @blur="handleBlur"
            class="relative w-full p-3 text-center border-2 border-dashed rounded-md outline-none transition-colors"
            :class="{
              'border-blue-600 bg-blue-100': dragOver,
              'border-blue-600 bg-blue-50 ring-2 ring-blue-300':
                isFocused && !dragOver,
              'border-blue-400': !isFocused && !dragOver
            }"
          >
            <p class="hidden mb-4 text-sm text-gray-600 hd:block">
              <span
                v-if="isFocused"
                class="text-blue-600 font-semibold"
              >
                Pronto para colar! Pressione
                <span class="font-bold">Ctrl + V</span>
                para colar uma imagem.
              </span>
              <span v-else>
                Clique aqui para selecionar ou arraste e solte um arquivo.
              </span>
            </p>
            <p class="block mb-4 text-sm text-gray-600 hd:hidden">
              Nenhum arquivo foi adicionado ainda.
            </p>
            <Button
              :label="
                aceitarUmArquivo && arquivos.length
                  ? 'Trocar arquivo'
                  : 'Selecionar arquivo'
              "
              icon="pi pi-cloud-upload"
              severity="info"
              outlined
              class=""
              :disabled="props.disabled"
              @click="fileInput?.click()"
            />
            <input
              ref="fileInput"
              id="fileInput"
              type="file"
              class="hidden"
              :multiple="!aceitarUmArquivo"
              @change="(e) => processarArquivos(e.target.files)"
            />
          </div>
        </div>
      </div>
    </div>
  </div>

  <Dialog
    v-model:visible="showTipoArquivo"
    modal
    header="Escolha uma opção"
  >
    <div class="flex flex-wrap items-center justify-center space-x-4">
      <button
        @click="escolherTipoArquivo('camera')"
        class="w-32 p-2 text-white bg-gray-800 rounded-md"
      >
        Câmera
      </button>
      <button
        @click="escolherTipoArquivo('local')"
        class="w-32 p-2 text-white bg-blue-800 rounded-md"
      >
        Galeria
      </button>
      <button
        v-if="device == 'iOS'"
        @click="escolherTipoArquivo('documentos')"
        class="w-32 p-2 mt-2 text-white bg-green-800 rounded-md"
      >
        Arquivos
      </button>
    </div>
  </Dialog>
</template>
