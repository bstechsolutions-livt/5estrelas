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
  modeloImagem: { type: String, default: null } // banner, quadrado, retrato, etc.
})

const isFlutter = ref(typeof window.flutter_inappwebview !== "undefined")
const showTipoArquivo = ref(false)
const arquivos = ref([])
const dragOver = ref(false)
const device = ref("")

// ID único para esta instância do componente
const instanceId = ref(`bsfile-${Math.random().toString(36).substr(2, 9)}`)

// Referência para o input de arquivo e container
const fileInput = ref(null)
const containerRef = ref(null)
const isFocused = ref(false)

onMounted(async () => {
  device.value = await getDevice()
  document.addEventListener("paste", handlePaste)
})

onBeforeUnmount(() => {
  document.removeEventListener("paste", handlePaste)
})

// Handlers de foco para o container
function onContainerFocus() {
  isFocused.value = true
}

function onContainerBlur(event) {
  // Só perde o foco se o novo elemento não estiver dentro do container
  if (containerRef.value && !containerRef.value.contains(event.relatedTarget)) {
    isFocused.value = false
  }
}

const extensoesPreview = ["png", "jpg", "jpeg", "jfif", "gif", "webp"]
const podeFazerPreview = (extensao) =>
  props.permitirPreview && extensoesPreview.includes(extensao.toLowerCase())

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
  postInstagram: { proporção: "1:1", dimensoes: { w: 1080, h: 1080 } }
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
        for (const uri of imagem) await atribuirImagemFlutter(uri)
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

  const arquivoInfo = {
    file,
    nome: file.name,
    tamanho: (file.size / 1024).toFixed(2),
    extensao
  }

  arquivos.value = props.aceitarUmArquivo
    ? [arquivoInfo]
    : [...arquivos.value, arquivoInfo]
  emit("atualizarLista", arquivos.value)

  // Redefine o valor do input de arquivo
  if (fileInput.value) {
    fileInput.value.value = null
  }
}

async function processarArquivos(files) {
  if (!files) return
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
  // Só processa se o componente tiver foco ou se o evento veio de dentro dele
  if (
    !isFocused.value &&
    containerRef.value &&
    !containerRef.value.contains(event.target)
  ) {
    return
  }

  const arquivosPaste = []
  for (const item of event.clipboardData.items) {
    if (item.type.startsWith("image/")) arquivosPaste.push(item.getAsFile())
  }
  if (arquivosPaste.length) await processarArquivos(arquivosPaste)
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
  emit("deletar-arquivo") // Emite o evento para o componente pai

  // Redefine o valor do input de arquivo
  if (fileInput.value) {
    fileInput.value.value = null
  }
}
</script>

<template>
  <div
    ref="containerRef"
    class="flex flex-col w-full space-y-2 select-none"
    tabindex="-1"
    @focusin="onContainerFocus"
    @focusout="onContainerBlur"
  >
    <div
      :class="{
        'grid grid-cols-1 hd:grid-cols-3 gap-4':
          !props.aceitarUmArquivo && !props.isVertical,
        'grid-cols-1 gap-4 grid': props.aceitarUmArquivo || props.isVertical
      }"
    >
      <div
        v-for="(arquivo, index) in arquivos"
        :key="index"
        class="relative flex h-auto min-h-[80px] p-2 gap-2 sm:gap-4 bg-gray-100 border rounded-md items-center"
      >
        <Image
          v-if="podeFazerPreview(arquivo.extensao)"
          preview
          :src="verArquivo(arquivo.file)"
          image-class="w-14 h-14 sm:w-[76px] sm:h-[76px] object-contain"
        />
        <div
          v-else
          class="w-14 h-14 sm:w-[80px] sm:h-[76px] flex items-center justify-center border-2 p-1 sm:p-2 font-bold uppercase rounded-md text-blue-500 bg-white shadow-md text-xs sm:text-base shrink-0"
        >
          {{ arquivo.extensao }}
        </div>
        <div class="flex-1 min-w-0">
          <div
            class="text-[10px] sm:text-sm font-bold truncate"
            :title="arquivo.nome"
          >
            {{ arquivo.nome }}
          </div>
          <div class="text-[10px] sm:text-xs text-gray-500">
            <div>Extensão: {{ arquivo.extensao }}</div>
            <div>Tamanho: {{ arquivo.tamanho }} KB</div>
          </div>
        </div>
        <Button
          @click="removerArquivo(index)"
          icon="pi pi-trash"
          severity="danger"
          class="!w-8 !h-8 shrink-0"
          icon-class="!text-md"
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
            severity="contrast"
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
            class="relative w-full p-3 text-center border-2 border-dashed rounded-md transition-all duration-200"
            :class="{
              'border-blue-600 bg-blue-50': dragOver,
              'border-green-500 bg-green-50 ring-2 ring-green-300':
                isFocused && !dragOver,
              'border-gray-300': !dragOver && !isFocused
            }"
          >
            <p class="hidden mb-4 text-sm text-gray-600 hd:block">
              <template v-if="isFocused">
                <span class="text-green-600 font-medium">
                  <i class="pi pi-check-circle mr-1"></i>
                  Pronto para colar! Pressione
                  <span class="font-bold">Ctrl + V</span>
                </span>
              </template>
              <template v-else>
                Arraste e solte um arquivo aqui ou pressione
                <span class="font-bold">Ctrl + V</span>
                para colar uma imagem.
              </template>
            </p>
            <p class="block mb-4 text-sm text-gray-600 hd:hidden">
              <template v-if="isFocused">
                <span class="text-green-600 font-medium">
                  Pronto para colar!
                </span>
              </template>
              <template v-else>Nenhum arquivo foi adicionado ainda.</template>
            </p>
            <label
              class="px-4 py-2 text-white bg-blue-600 rounded-md cursor-pointer"
              :for="instanceId"
            >
              {{
                aceitarUmArquivo && arquivos.length
                  ? "Trocar arquivo"
                  : "Selecionar arquivo"
              }}
            </label>
            <input
              ref="fileInput"
              :id="instanceId"
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
