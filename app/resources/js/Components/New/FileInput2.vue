<script setup>
import { getDevice, swalErro, swalSucesso } from "@/utils/globalFunctions"
import { Dialog, Image, ProgressSpinner } from "primevue"
import { onMounted, onBeforeUnmount, ref } from "vue"

const emit = defineEmits(["atualizarLista"])
const props = defineProps({
  isMultiple: {
    type: Boolean,
    required: false
  }
})
const isFlutter = ref(typeof window.flutter_inappwebview !== "undefined")
const showTipoArquivo = ref(false) // Controle do diálogo para escolha no Flutter
const arquivos = ref([]) // Referência ao arquivo atual
const dragOver = ref(false)
const inputArq = ref(null)
const device = ref("")

// Receber arquivo no ambiente Flutter
onMounted(async () => {
  device.value = await getDevice()

  // Adiciona o evento de colar ao corpo do documento
  document.addEventListener("paste", handlePaste)
})

onBeforeUnmount(() => {
  document.removeEventListener("paste", handlePaste)
})

// Abrir câmera ou galeria no Flutter
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
        // fallback, caso venha só uma string
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
    const resp = await window.flutter_inappwebview.callHandler(
      "Loading",
      carregar
    )
    console.log("resp: ", resp)
  } catch (error) {
    console.log(error)
    swalErro("Erro", "Não foi possível acessar o recurso do Flutter.")
  }
}

// Processar arquivo recebido pelo Flutter
async function atribuirImagemFlutter(arquivo) {
  const matches = arquivo.match(/^data:(.*?);base64,/)
  const contentType = matches ? matches[1] : ""

  let extension = await getExtensionFromContentType(contentType)

  const base64Data = arquivo.replace(/^data:(.*?);base64,/, "")

  // Criando o arquivo como Blob
  const byteCharacters = atob(base64Data)
  const byteNumbers = new Array(byteCharacters.length)
  for (let i = 0; i < byteCharacters.length; i++) {
    byteNumbers[i] = byteCharacters.charCodeAt(i)
  }
  const byteArray = new Uint8Array(byteNumbers)

  const blob = new Blob([byteArray], { type: contentType })

  // Convertendo o Blob para File
  const nomeArquivo = `arquivo_${Date.now()}.${extension}`
  const file = new File([blob], nomeArquivo, { type: contentType })

  const nome = file.name
  const tamanho = (file.size / 1024).toFixed(2) // Tamanho em KB
  const extensao = nome.split(".").pop() // Pega a extensão do arquivo

  // Cria um objeto com as informações do arquivo
  const arquivoInfo = {
    file,
    nome,
    tamanho,
    extensao
  }

  if (props.isMultiple) {
    arquivos.value.push(arquivoInfo) // Adiciona o arquivo ao array
  } else {
    arquivos.value = [arquivoInfo]
  }
  emit("atualizarLista", arquivos.value)
}

async function getExtensionFromContentType(contentType) {
  if (!contentType || typeof contentType !== "string") return ""

  // separa tipo principal e subtype (remove parâmetros após ';')
  const [type, rawSubtype = ""] = contentType.split("/")
  const subtype = rawSubtype.split(";")[0].toLowerCase()

  // mapa de unificação para imagens
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
    // tenta pegar pelo subtype completo (ex: "svg+xml"), senão pelo base (antes do '+')
    if (imgMap[subtype]) return imgMap[subtype]
    const base = subtype.split("+")[0]
    return imgMap[base] || base
  }

  // não é imagem: devolve o subtype antes de qualquer '+'
  return subtype.split("+")[0]
}

// Processa múltiplos arquivos recebidos
function processarArquivos(payload) {
  // Normaliza em um Array<File>
  let lista = []

  // se payload for um Event de <input @change>
  if (payload instanceof Event) {
    const input = /** @type {HTMLInputElement} */ (payload.target)
    lista = input.files ? Array.from(input.files) : []
  }
  // se payload for FileList (drag&drop)
  else if (payload instanceof FileList) {
    lista = Array.from(payload)
  }
  // se for já um Array<File> (paste)
  else if (Array.isArray(payload)) {
    lista = payload
  }
  // se for apenas um File solto, por algum acaso
  else if (payload instanceof File) {
    lista = [payload]
  }

  if (!lista.length) return

  // agora gera seus objetos e empurra no reactive
  lista.forEach((file) => {
    const nome = file.name
    const tamanho = (file.size / 1024).toFixed(2) // KB
    const extensao = nome.split(".").pop()

    arquivos.value.push({ file, nome, tamanho, extensao })
  })

  emit("atualizarLista", arquivos.value)
}

// Lida com o evento de arrastar e soltar (Drag and Drop)
function handleDrop(event) {
  event.preventDefault()
  dragOver.value = false // Remove a classe de "drag over"

  const files = event.dataTransfer.files // Pega todos os arquivos que foram soltos
  if (!files.length) return

  processarArquivos(files) // Passa todos os arquivos para o método que processa
}

// Lida com a área de transferência (Ctrl + V)
function handlePaste(event) {
  const clipboardItems = event.clipboardData.items
  const arquivosPaste = []

  // Percorre todos os itens copiados na área de transferência
  for (const item of clipboardItems) {
    if (item.type.startsWith("image/")) {
      const file = item.getAsFile() // Obtém o arquivo da área de transferência
      arquivosPaste.push(file) // Adiciona o arquivo ao array
    }
  }

  if (arquivosPaste.length) {
    processarArquivos(arquivosPaste) // Processa todos os arquivos de imagem
  }
}

// Eventos de arrastar
function handleDragOver(event) {
  event.preventDefault()
  dragOver.value = true // Adiciona a classe de "drag over"
}
function handleDragLeave() {
  dragOver.value = false // Remove a classe de "drag over"
}

function verArquivo(file) {
  return URL.createObjectURL(file)
}

function removerArquivo(index) {
  arquivos.value.splice(index, 1)
  emit("atualizarLista", arquivos.value)
}

function handleDevices() {
  if (device.value == "Android") {
    showTipoArquivo.value = true
  } else {
    inputArq.value.click()
  }
}
</script>

<template>
  <div class="flex flex-col w-full space-y-3 select-none">
    <!-- Lista de arquivos já adicionados -->
    <div
      v-if="arquivos.length > 0"
      class="flex flex-col gap-3"
    >
      <div
        v-for="(arquivo, index) in arquivos"
        :key="index"
        class="flex items-center gap-4 p-3 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg shadow-sm"
      >
        <!-- Prévia da imagem ou ícone -->
        <div class="flex-shrink-0">
          <Image
            v-if="
              ['png', 'jpg', 'jpeg', 'jfif', 'gif', 'webp'].includes(
                arquivo.extensao?.toLowerCase()
              )
            "
            preview
            :src="verArquivo(arquivo.file)"
            image-class="w-16 h-16 object-cover rounded-md border"
          ></Image>
          <div
            v-else
            class="w-16 h-16 flex items-center justify-center bg-gradient-to-br from-blue-50 to-blue-100 dark:from-slate-600 dark:to-slate-700 rounded-md border"
          >
            <span
              class="text-sm font-bold uppercase text-blue-600 dark:text-blue-400"
            >
              {{ arquivo.extensao }}
            </span>
          </div>
        </div>

        <!-- Info do arquivo -->
        <div class="flex-1 min-w-0">
          <div
            class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate"
            :title="arquivo.nome"
          >
            {{ arquivo.nome }}
          </div>
          <div class="text-xs text-gray-500 dark:text-gray-400">
            Extensão: {{ arquivo.extensao }}
          </div>
          <div class="text-xs text-gray-500 dark:text-gray-400">
            Tamanho: {{ arquivo.tamanho }} KB
          </div>
        </div>

        <!-- Botão de Remover -->
        <div class="flex-shrink-0">
          <Button
            @click="removerArquivo(index)"
            icon="pi pi-trash"
            severity="danger"
            outlined
            class="!w-10 !h-10"
          />
        </div>
      </div>
    </div>

    <!-- Área de Drop/Upload -->
    <div
      class="w-full"
      @dragover="handleDragOver"
      @dragleave="handleDragLeave"
      @drop="handleDrop"
    >
      <!-- Botão Flutter -->
      <div
        v-if="isFlutter"
        class="flex flex-col items-center justify-center w-full p-2 space-y-2 border rounded-md"
      >
        <div class="font-bold text-center text-gray-500">
          Nenhum arquivo foi adicionado ainda
        </div>
        <Button
          @click="handleDevices()"
          icon="pi pi-cloud-upload"
          severity="contrast"
          label="Selecionar arquivo"
        ></Button>
        <input
          ref="inputArq"
          type="file"
          class="hidden"
          multiple
          @change="processarArquivos($event)"
        />
      </div>

      <!-- Input para Web -->
      <div
        v-else
        class="w-full"
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
          <label
            class="px-4 py-2 text-white bg-blue-600 rounded-md cursor-pointer"
            for="fileInput"
          >
            Selecionar Arquivo
          </label>
          <input
            id="fileInput"
            type="file"
            class="hidden"
            multiple
            @change="(e) => processarArquivos(e.target.files)"
          />
        </div>
      </div>
    </div>
  </div>

  <!-- Diálogo para escolha no Flutter -->
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
