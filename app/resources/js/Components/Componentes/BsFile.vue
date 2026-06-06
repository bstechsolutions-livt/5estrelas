<script setup>
import { swalErro, getDevice, log, uploadFile } from "@/utils/globalFunctions"
import { Dialog, Image, Button, Checkbox } from "primevue"
import { onMounted, onBeforeUnmount, ref } from "vue"

const emit = defineEmits(["atualizarLista"])
const props = defineProps({
  aceitarUmArquivo: {
    type: Boolean,
    default: false
  },
  isVertical: {
    type: Boolean,
    default: false
  }
})

const isFlutter = ref(typeof window.flutter_inappwebview !== "undefined")
const showTipoArquivo = ref(false)
const arquivos = ref([])
const dragOver = ref(false)
const device = ref("")
const isElectron = ref(false)
const duplex = ref(false) // controla checkbox duplex para escanear frente e verso (começa desativado)
const loading = ref(false)

onMounted(async () => {
  device.value = await getDevice()

  document.addEventListener("paste", handlePaste)
  isElectron.value = typeof window.electronAPI !== "undefined"

  if (isElectron.value) {
    window.electronAPI.onScannerResponse(handleScannerResponse)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener("paste", handlePaste)
  if (isElectron.value) {
    window.electronAPI.removeScannerResponseListener(handleScannerResponse)
  }
})

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
        for (const uri of imagem) {
          await atribuirImagemFlutter(uri)
        }
      } else if (imagem) {
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
  for (let i = 0; i < byteCharacters.length; i++) {
    byteNumbers[i] = byteCharacters.charCodeAt(i)
  }
  const byteArray = new Uint8Array(byteNumbers)
  const blob = new Blob([byteArray], { type: contentType })
  const nomeArquivo = `arquivo_${Date.now()}.${extension}`
  const file = new File([blob], nomeArquivo, { type: contentType })

  const arquivoInfo = {
    file,
    nome: file.name,
    tamanho: (file.size / 1024).toFixed(2),
    extensao: file.name.split(".").pop()
  }

  arquivos.value = props.aceitarUmArquivo
    ? [arquivoInfo]
    : [...arquivos.value, arquivoInfo]
  emit("atualizarLista", arquivos.value)
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

function processarArquivos(files) {
  if (!files) return

  const arquivosProcessados = Array.from(files).map((file) => ({
    file,
    nome: file.name,
    tamanho: (file.size / 1024).toFixed(2),
    extensao: file.name.split(".").pop()
  }))

  if (props.aceitarUmArquivo) {
    arquivos.value = [arquivosProcessados[0]]
  } else {
    arquivos.value.push(...arquivosProcessados)
  }

  emit(
    "atualizarLista",
    arquivos.value.map((a) => ({ ...a }))
  )
}

function handleDrop(event) {
  event.preventDefault()
  dragOver.value = false

  const files = event.dataTransfer.files
  if (!files.length) return

  processarArquivos(files)
}

function handlePaste(event) {
  const clipboardItems = event.clipboardData.items
  const arquivosPaste = []

  for (const item of clipboardItems) {
    if (item.type.startsWith("image/")) {
      arquivosPaste.push(item.getAsFile())
    }
  }

  if (arquivosPaste.length) processarArquivos(arquivosPaste)
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

function base64ToBlob(base64, mime) {
  const bytes = atob(base64)
  let length = bytes.length
  const out = new Uint8Array(length)
  while (length--) {
    out[length] = bytes.charCodeAt(length)
  }
  return new Blob([out], { type: mime })
}

function adicionarArquivo(file) {
  const arquivoInfo = {
    file,
    nome: file.name,
    tamanho: (file.size / 1024).toFixed(2),
    extensao: file.name.split(".").pop()
  }
  arquivos.value = [...arquivos.value, arquivoInfo]
  emit("atualizarLista", arquivos.value)
}

function handleScannerResponse(response) {
  loading.value = false
  if (response.success) {
    const base64Data = response.base64

    const blob = base64ToBlob(base64Data, "application/pdf")
    const file = new File([blob], `scan_${Date.now()}.pdf`, {
      type: "application/pdf"
    })

    adicionarArquivo(file)
    startLog("Escaneamento concluído e arquivo adicionado.")
  } else {
    if (response.error === "Scanner não conectado") {
      swalErro(
        "Erro Scanner",
        "Por favor, conecte o scanner e tente novamente."
      )
    } else if (response.error === "Nenhuma página escaneada") {
      swalErro("Aviso", "Nenhuma página foi capturada no scanner.")
    } else {
      swalErro("Erro", response.error)
    }
  }
}

function iniciarScanner() {
  if (!isElectron.value) return
  startLog("Iniciando escaneamento...")
  loading.value = true
  window.electronAPI.startScanner(duplex.value)
}

function startLog(msg) {
  console.log(msg)
}
</script>

<template>
  <div class="flex flex-col w-full space-y-2 select-none">
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
        class="relative flex h-24 p-2 space-x-4 bg-gray-100 border rounded-md"
      >
        <Image
          v-if="['png', 'jpg', 'jpeg'].includes(arquivo.extensao)"
          preview
          :src="verArquivo(arquivo.file)"
          image-class="w-[76px] h-[76px] object-contain"
        ></Image>
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
        <div>
          <Button
            @click="arquivos.splice(index, 1)"
            icon="pi pi-trash"
            severity="danger"
            class="!w-8 !h-8"
            icon-class="!text-md"
          ></Button>
        </div>
      </div>
      <div
        class="w-full h-48"
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
          ></Button>
        </div>
        <div
          class="w-full"
          v-else
        >
          <div
            class="relative w-full p-3 text-center border-2 border-gray-300 border-dashed rounded-md"
            :class="{ 'border-blue-600 bg-blue-50': dragOver }"
          >
            <p class="hidden mb-4 text-sm text-gray-600 hd:block">
              Arraste e solte um arquivo aqui ou pressione
              <span class="font-bold">Ctrl + V</span>
              para colar uma imagem.
            </p>
            <p class="block mb-4 text-sm text-gray-600 hd:hidden">
              Nenhum arquivo foi adicionado ainda.
            </p>
            <div class="">
              <label
                class="px-4 py-2 text-white bg-blue-600 rounded-md cursor-pointer"
                for="fileInput"
              >
                {{
                  aceitarUmArquivo && arquivos.length
                    ? "Trocar arquivo"
                    : "Selecionar arquivo"
                }}
              </label>
              <div v-if="isElectron">
                <Button
                  @click="iniciarScanner"
                  class="px-1 h-8 mt-4 text-white bg-green-600 rounded hover:bg-green-700"
                >
                  Escanear documento
                </Button>
                <label class="inline-flex items-center space-x-2 ml-4">
                  <Checkbox
                    binary
                    v-model="duplex"
                  />
                  <span>Frente e Verso</span>
                </label>
              </div>
            </div>
            <input
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

  <!-- Loading Overlay -->
  <div
    v-if="loading"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
  >
    <div class="p-4 bg-white rounded shadow-lg flex items-center space-x-2">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
      <span>Escaneando...</span>
    </div>
  </div>
</template>
