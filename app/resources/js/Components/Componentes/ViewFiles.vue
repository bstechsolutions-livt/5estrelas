<template>
  <Dialog
    v-model:visible="visibleLocal"
    :position="'top'"
    :modal="true"
    :closable="false"
    :style="{ width: '95vw', maxWidth: '1400px' }"
    :breakpoints="{
      '1199px': '95vw',
      '991px': '98vw',
      '767px': '100vw',
      '575px': '100vw'
    }"
    :pt="{
      content: {
        class: 'p-0 h-dvh sm:h-[90dvh] md:h-[88dvh] lg:h-[85dvh] !bg-white'
      },
      mask: { class: 'sm:!p-4' },
      root: {
        class:
          'p-0 !rounded-none sm:!rounded-lg md:!rounded-xl !m-0 sm:!m-auto !bg-white overflow-hidden max-sm:!h-[100dvh] max-sm:!max-h-[100dvh] max-sm:!top-0'
      }
    }"
  >
    <template #container>
      <div
        v-if="files && files.length > 0"
        class="flex flex-col h-full max-h-[100dvh] sm:max-h-[90dvh] md:max-h-[88dvh] lg:max-h-[85dvh] overflow-hidden bg-white"
      >
        <!-- Header com navegação - sempre visível -->
        <div
          class="flex items-center p-2 sm:p-3 md:p-4 border-b justify-between border-gray-200 gap-1 sm:gap-2 bg-white sticky top-0 z-10"
        >
          <!-- Navegação entre arquivos -->
          <div
            v-if="files.length > 1"
            class="flex items-center gap-1 sm:gap-2 flex-shrink-0"
          >
            <button
              @click="previousFile"
              :disabled="currentFileIndex === 0"
              class="p-1 sm:p-1.5 md:p-2 rounded-lg border disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 active:bg-gray-100 transition-colors"
            >
              <i class="pi pi-chevron-left text-xs sm:text-sm"></i>
            </button>
            <span
              class="text-[10px] sm:text-xs md:text-sm text-gray-600 whitespace-nowrap font-medium"
            >
              {{ currentFileIndex + 1 }}/{{ files.length }}
            </span>
            <button
              @click="nextFile"
              :disabled="currentFileIndex === files.length - 1"
              class="p-1 sm:p-1.5 md:p-2 rounded-lg border disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 active:bg-gray-100 transition-colors"
            >
              <i class="pi pi-chevron-right text-xs sm:text-sm"></i>
            </button>
            <!-- Botão para abrir miniaturas no mobile/tablet -->
            <button
              @click="showThumbnails = !showThumbnails"
              class="p-1 sm:p-1.5 md:p-2 rounded-lg border hover:bg-gray-50 active:bg-gray-100 transition-colors lg:hidden"
              :class="{ 'bg-blue-50 border-blue-300': showThumbnails }"
              title="Ver miniaturas"
            >
              <i class="pi pi-th-large text-xs sm:text-sm"></i>
            </button>
          </div>

          <!-- Nome do arquivo -->
          <div class="truncate flex-1 text-center px-1 sm:px-2">
            <span
              class="text-[10px] sm:text-xs md:text-sm text-gray-700 font-semibold"
            >
              {{
                currentFile.original_name ||
                currentFile.stored_name.replace(/^\d+_/, "")
              }}
            </span>
          </div>

          <!-- Ações -->
          <div class="flex items-center gap-1 sm:gap-2 md:gap-3 flex-shrink-0">
            <button
              @click.stop="downloadFileById(currentFile.id)"
              class="p-1 sm:p-1.5 md:p-2 rounded-lg hover:bg-blue-50 active:bg-blue-100 transition-colors"
              title="Baixar arquivo"
            >
              <i
                class="pi pi-download text-sm sm:text-base text-gray-600 hover:text-blue-600"
              ></i>
            </button>
            <button
              @click.stop="visibleLocal = false"
              class="p-1 sm:p-1.5 md:p-2 rounded-lg hover:bg-red-50 active:bg-red-100 transition-colors"
              title="Fechar"
            >
              <i
                class="pi pi-times text-sm sm:text-base text-gray-600 hover:text-red-600"
              ></i>
            </button>
          </div>
        </div>

        <!-- Container principal com sidebar e conteúdo -->
        <div class="flex flex-1 min-h-0 overflow-hidden relative">
          <!-- Sidebar com miniaturas - Desktop (sempre visível) / Mobile (toggle) -->
          <div
            v-if="files.length > 1"
            :class="[
              'bg-gray-50 border-r border-gray-200 overflow-y-auto transition-all duration-300 z-20',
              // Desktop: sidebar fixa
              'hidden lg:block lg:w-48 xl:w-52',
              // Mobile/Tablet: overlay quando ativo
              showThumbnails
                ? 'absolute inset-0 block w-full sm:w-64 md:w-72 shadow-xl'
                : ''
            ]"
          >
            <div class="p-2 sm:p-3">
              <div class="flex items-center justify-between mb-2 sm:mb-3">
                <h3 class="text-xs sm:text-sm font-semibold text-gray-700">
                  Arquivos ({{ files.length }})
                </h3>
                <!-- Fechar miniaturas no mobile -->
                <button
                  @click="showThumbnails = false"
                  class="lg:hidden p-1 rounded hover:bg-gray-200 transition-colors"
                >
                  <i class="pi pi-times text-xs"></i>
                </button>
              </div>

              <!-- Grid de miniaturas para mobile, lista para desktop -->
              <div
                :class="[
                  'gap-2',
                  showThumbnails && !isDesktop
                    ? 'grid grid-cols-3 sm:grid-cols-2'
                    : 'space-y-2'
                ]"
              >
                <div
                  v-for="(file, index) in files"
                  :key="file.id"
                  @click="selectFile(index)"
                  :class="[
                    'cursor-pointer p-1.5 sm:p-2 rounded-lg border transition-all duration-200',
                    currentFileIndex === index
                      ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-200'
                      : 'border-gray-200 hover:border-gray-300 hover:bg-white'
                  ]"
                >
                  <!-- Miniatura para imagem -->
                  <div
                    v-if="isImage(file.extension)"
                    class="mb-1 sm:mb-2"
                  >
                    <img
                      :src="file.external_link"
                      :alt="file.original_name || file.stored_name"
                      class="w-full h-14 sm:h-16 md:h-20 object-cover rounded"
                      @error="() => {}"
                    />
                  </div>
                  <!-- Miniatura para PDF -->
                  <div
                    v-else-if="file.extension === 'pdf'"
                    class="mb-1 sm:mb-2"
                  >
                    <div
                      class="w-full h-14 sm:h-16 md:h-20 bg-red-100 rounded flex flex-col items-center justify-center"
                    >
                      <i
                        class="pi pi-file-pdf text-red-600 text-lg sm:!text-xl md:!text-2xl"
                      />
                      <span class="text-[10px] sm:text-xs mt-0.5 sm:mt-1">
                        PDF
                      </span>
                    </div>
                  </div>
                  <!-- Miniatura para Word -->
                  <div
                    v-else-if="isWord(file.extension)"
                    class="mb-1 sm:mb-2"
                  >
                    <div
                      class="w-full h-14 sm:h-16 md:h-20 bg-blue-100 rounded flex flex-col items-center justify-center"
                    >
                      <i
                        class="pi pi-file-word text-blue-600 text-lg sm:!text-xl md:!text-2xl"
                      />
                      <span class="text-[10px] sm:text-xs mt-0.5 sm:mt-1">
                        WORD
                      </span>
                    </div>
                  </div>
                  <!-- Miniatura para Excel -->
                  <div
                    v-else-if="isExcel(file.extension)"
                    class="mb-1 sm:mb-2"
                  >
                    <div
                      class="w-full h-14 sm:h-16 md:h-20 bg-green-100 rounded flex flex-col items-center justify-center"
                    >
                      <i
                        class="pi pi-file-excel text-green-600 text-lg sm:!text-xl md:!text-2xl"
                      />
                      <span class="text-[10px] sm:text-xs mt-0.5 sm:mt-1">
                        EXCEL
                      </span>
                    </div>
                  </div>
                  <!-- Miniatura para Vídeo -->
                  <div
                    v-else-if="isVideo(file.extension)"
                    class="mb-1 sm:mb-2"
                  >
                    <div
                      class="w-full h-14 sm:h-16 md:h-20 bg-purple-100 rounded flex flex-col items-center justify-center relative overflow-hidden"
                    >
                      <i
                        class="pi pi-video text-purple-600 text-lg sm:!text-xl md:!text-2xl"
                      />
                      <span class="text-[10px] sm:text-xs mt-0.5 sm:mt-1">
                        {{ file.extension?.toUpperCase() }}
                      </span>
                    </div>
                  </div>
                  <!-- Miniatura para HEIC/HEIF -->
                  <div
                    v-else-if="isHeic(file.extension)"
                    class="mb-1 sm:mb-2"
                  >
                    <div
                      class="w-full h-14 sm:h-16 md:h-20 bg-orange-100 rounded flex flex-col items-center justify-center relative overflow-hidden"
                    >
                      <i
                        class="pi pi-image text-orange-600 text-lg sm:!text-xl md:!text-2xl"
                      />
                      <span class="text-[10px] sm:text-xs mt-0.5 sm:mt-1">
                        {{ file.extension?.toUpperCase() }}
                      </span>
                    </div>
                  </div>
                  <!-- Miniatura para HTML -->
                  <div
                    v-else-if="isHtml(file.extension)"
                    class="mb-1 sm:mb-2"
                  >
                    <div
                      class="w-full h-14 sm:h-16 md:h-20 bg-indigo-100 rounded flex flex-col items-center justify-center"
                    >
                      <i
                        class="pi pi-code text-indigo-600 text-lg sm:!text-xl md:!text-2xl"
                      />
                      <span class="text-[10px] sm:text-xs mt-0.5 sm:mt-1">
                        HTML
                      </span>
                    </div>
                  </div>
                  <!-- Miniatura para outros tipos -->
                  <div
                    v-else
                    class="mb-1 sm:mb-2"
                  >
                    <div
                      class="w-full h-14 sm:h-16 md:h-20 bg-gray-100 rounded flex flex-col items-center justify-center"
                    >
                      <i
                        class="pi pi-file text-gray-600 text-lg sm:!text-xl md:!text-2xl"
                      />
                      <span class="text-[10px] sm:text-xs mt-0.5 sm:mt-1">
                        {{ file.extension?.toUpperCase() }}
                      </span>
                    </div>
                  </div>
                  <p
                    class="text-[10px] sm:text-xs text-gray-600 truncate"
                    :title="file.original_name || file.stored_name"
                  >
                    {{
                      file.original_name ||
                      file.stored_name ||
                      `Arquivo ${index + 1}`
                    }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Overlay para fechar miniaturas no mobile -->
          <div
            v-if="showThumbnails && files.length > 1"
            @click="showThumbnails = false"
            class="fixed inset-0 bg-black/30 z-10 lg:hidden"
          ></div>

          <!-- Área principal do arquivo -->
          <div class="flex-1 min-h-0 overflow-hidden bg-gray-100 relative">
            <!-- Conteúdo do arquivo -->
            <div
              :class="[
                'h-full',
                isHtml(currentFile.extension)
                  ? 'overflow-hidden'
                  : currentFile.extension === 'pdf'
                    ? 'overflow-auto'
                    : 'p-2 sm:p-3 md:p-4 overflow-auto bg-gray-50'
              ]"
            >
              <!-- PDF - Usando PDF.js para todos os dispositivos -->
              <div
                v-if="currentFile.extension === 'pdf'"
                class="h-full w-full overflow-auto bg-gray-200"
              >
                <!-- Loading -->
                <div
                  v-if="pdfLoading"
                  class="h-full flex flex-col items-center justify-center"
                >
                  <div
                    class="animate-spin w-10 h-10 border-4 border-red-600 border-t-transparent rounded-full mb-3"
                  ></div>
                  <p class="text-gray-600">Carregando PDF...</p>
                </div>

                <!-- Error -->
                <div
                  v-else-if="pdfError"
                  class="h-full flex flex-col items-center justify-center p-4"
                >
                  <i class="pi pi-file-pdf text-red-500 text-5xl mb-4"></i>
                  <p class="text-gray-600 mb-4 text-center">
                    Não foi possível carregar o PDF
                  </p>
                  <a
                    :href="currentFile.external_link"
                    target="_blank"
                    class="px-6 py-3 bg-red-600 text-white rounded-xl font-semibold shadow-lg"
                  >
                    <i class="pi pi-external-link mr-2"></i>
                    Abrir em nova aba
                  </a>
                </div>

                <!-- PDF Pages Container -->
                <div
                  v-show="!pdfLoading && !pdfError"
                  ref="pdfCanvasContainer"
                  class="flex flex-col items-center gap-4 p-4"
                ></div>
              </div>

              <!-- Imagem -->
              <div
                v-else-if="isImage(currentFile.extension)"
                class="h-full flex items-center justify-center"
              >
                <Image
                  :src="currentFile.external_link"
                  :alt="currentFile.original_name || currentFile.stored_name"
                  class="max-w-full max-h-full object-contain rounded-lg"
                  preview
                  @error="handleMainImageError(currentFile)"
                />
              </div>

              <!-- Vídeo -->
              <div
                v-else-if="isVideo(currentFile.extension)"
                class="h-full flex items-center justify-center bg-black rounded-lg overflow-hidden"
              >
                <video
                  :key="currentFile.id"
                  :src="currentFile.external_link"
                  controls
                  controlslist="nodownload"
                  class="max-w-full max-h-full object-contain"
                  preload="metadata"
                  playsinline
                >
                  <source
                    :src="currentFile.external_link"
                    :type="'video/' + currentFile.extension"
                  />
                  Seu navegador não suporta a reprodução de vídeos.
                </video>
              </div>

              <!-- HEIC/HEIF - Conversão para visualização -->
              <div
                v-else-if="isHeic(currentFile.extension)"
                class="h-full flex items-center justify-center"
              >
                <!-- Loading de conversão -->
                <div
                  v-if="loadingHeic"
                  class="flex flex-col items-center justify-center"
                >
                  <div class="relative mb-4">
                    <div
                      class="w-16 h-16 border-4 border-orange-200 rounded-full animate-spin border-t-orange-600"
                    ></div>
                    <div
                      class="absolute inset-0 flex items-center justify-center"
                    >
                      <i class="pi pi-image text-orange-600 text-xl"></i>
                    </div>
                  </div>
                  <p class="text-gray-600 text-center">
                    Convertendo arquivo HEIC...
                  </p>
                  <p class="text-gray-400 text-sm text-center mt-1">
                    Isso pode levar alguns segundos
                  </p>
                </div>

                <!-- Imagem convertida -->
                <Image
                  v-else-if="heicConvertedUrl"
                  :src="heicConvertedUrl"
                  :alt="currentFile.original_name || currentFile.stored_name"
                  class="max-w-full max-h-full object-contain rounded-lg"
                  preview
                />

                <!-- Fallback se conversão falhou -->
                <div
                  v-else
                  class="flex flex-col items-center justify-center text-center p-4"
                >
                  <i class="pi pi-image text-orange-400 text-6xl mb-4"></i>
                  <h3 class="text-lg font-semibold text-gray-800 mb-2">
                    Arquivo HEIC/HEIF
                  </h3>
                  <p class="text-gray-500 mb-4 max-w-sm">
                    {{ currentFile.original_name || currentFile.stored_name }}
                  </p>
                  <button
                    @click="converterHeicParaJpeg(currentFile)"
                    class="px-6 py-3 bg-orange-600 text-white rounded-xl hover:bg-orange-700 active:bg-orange-800 transition-colors font-semibold shadow-lg mb-3"
                  >
                    <i class="pi pi-refresh mr-2"></i>
                    Tentar converter novamente
                  </button>
                  <button
                    @click="downloadFileById(currentFile.id)"
                    class="px-6 py-3 bg-gray-600 text-white rounded-xl hover:bg-gray-700 transition-colors font-medium"
                  >
                    <i class="pi pi-download mr-2"></i>
                    Baixar arquivo original
                  </button>
                </div>
              </div>

              <!-- Word / Excel - Visualização com bibliotecas JS -->
              <div
                v-else-if="
                  isWord(currentFile.extension) ||
                  isExcel(currentFile.extension)
                "
                class="h-full overflow-hidden"
              >
                <!-- Loading -->
                <div
                  v-if="loadingDocument"
                  class="h-full flex flex-col items-center justify-center bg-gray-50 rounded-lg"
                >
                  <div
                    class="animate-spin w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 border-4 border-blue-600 border-t-transparent rounded-full mb-3 sm:mb-4"
                  ></div>
                  <p class="text-gray-600 text-sm sm:text-base">
                    Carregando documento...
                  </p>
                </div>

                <!-- Conteúdo Word -->
                <div
                  v-else-if="isWord(currentFile.extension) && wordHtmlContent"
                  class="h-full overflow-auto bg-white rounded-lg shadow-inner p-3 sm:p-4 md:p-6 prose prose-sm max-w-none"
                  v-html="wordHtmlContent"
                ></div>

                <!-- Conteúdo Excel -->
                <div
                  v-else-if="isExcel(currentFile.extension) && excelHtmlContent"
                  class="h-full overflow-auto bg-white rounded-lg shadow-inner"
                >
                  <div
                    class="excel-table-wrapper text-xs sm:text-sm"
                    v-html="excelHtmlContent"
                  ></div>
                </div>

                <!-- Fallback se não conseguir carregar -->
                <div
                  v-else-if="!loadingDocument"
                  class="h-full flex flex-col items-center justify-center bg-gray-50 rounded-lg p-4 sm:p-6"
                >
                  <i
                    :class="[
                      isWord(currentFile.extension)
                        ? 'pi pi-file-word text-blue-500'
                        : 'pi pi-file-excel text-green-500',
                      'text-5xl sm:text-6xl md:text-7xl mb-4 sm:mb-5'
                    ]"
                  ></i>
                  <h3
                    class="text-lg sm:text-xl font-semibold mb-2 text-center text-gray-800"
                  >
                    {{
                      isWord(currentFile.extension)
                        ? "Documento Word"
                        : "Planilha Excel"
                    }}
                  </h3>
                  <p
                    class="text-gray-600 mb-2 text-center max-w-md text-sm sm:text-base px-2 break-all"
                  >
                    {{ currentFile.original_name || currentFile.stored_name }}
                  </p>
                  <p
                    class="text-gray-500 mb-4 sm:mb-5 text-center text-xs sm:text-sm px-2"
                  >
                    Toque no botão abaixo para baixar e visualizar
                  </p>
                  <button
                    @click="downloadFileById(currentFile.id)"
                    :class="[
                      'px-6 sm:px-8 py-3 sm:py-4 text-white rounded-xl transition-colors font-semibold text-base sm:text-lg shadow-lg',
                      isWord(currentFile.extension)
                        ? 'bg-blue-600 hover:bg-blue-700 active:bg-blue-800'
                        : 'bg-green-600 hover:bg-green-700 active:bg-green-800'
                    ]"
                  >
                    <i class="pi pi-download mr-2"></i>
                    Baixar arquivo
                  </button>
                </div>
              </div>

              <!-- HTML -->
              <div
                v-else-if="isHtml(currentFile.extension)"
                class="w-full overflow-hidden"
                style="height: 80dvh"
              >
                <iframe
                  :src="currentFile.external_link"
                  class="w-full h-full border-0"
                  sandbox="allow-same-origin allow-scripts allow-popups"
                  title="Visualização HTML"
                ></iframe>
              </div>

              <!-- Tipo não suportado -->
              <div
                v-else
                class="h-full flex flex-col items-center justify-center text-center text-gray-500 p-4 sm:p-6"
              >
                <i
                  class="pi pi-file text-gray-400 text-5xl sm:text-6xl md:text-7xl mb-4 sm:mb-5"
                ></i>
                <h3 class="text-lg sm:text-xl font-semibold text-gray-800 mb-2">
                  Tipo de arquivo não suportado
                </h3>
                <p class="text-sm sm:text-base mb-4 sm:mb-5 text-gray-500">
                  {{ currentFile.extension?.toUpperCase() || "Desconhecido" }}
                </p>
                <button
                  @click="downloadFileById(currentFile.id)"
                  class="px-6 sm:px-8 py-3 sm:py-4 bg-blue-600 text-white rounded-xl hover:bg-blue-700 active:bg-blue-800 transition-colors font-semibold text-base sm:text-lg shadow-lg"
                >
                  <i class="pi pi-download mr-2"></i>
                  Baixar arquivo
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading state -->
      <div
        v-else-if="!files"
        class="flex items-center justify-center h-[50dvh] sm:h-56 md:h-64"
      >
        <div class="text-center p-4">
          <div
            class="animate-spin w-6 h-6 sm:w-7 sm:h-7 md:w-8 md:h-8 border-4 border-blue-600 border-t-transparent rounded-full mx-auto mb-3 sm:mb-4"
          ></div>
          <p class="text-gray-600 text-sm sm:text-base">
            Carregando arquivo...
          </p>
        </div>
      </div>

      <!-- Empty state -->
      <div
        v-else-if="files.length === 0"
        class="flex items-center justify-center h-48 sm:h-56 md:h-64"
      >
        <div class="text-center p-4">
          <i
            class="pi pi-file text-gray-400 text-4xl sm:text-5xl md:text-6xl mb-3 sm:mb-4"
          ></i>
          <p class="text-base sm:text-lg font-semibold text-gray-600">
            Nenhum arquivo encontrado
          </p>
        </div>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from "vue"
import { Dialog, Image } from "primevue"
import axios from "axios"
import { toastError, toastWarning, downloadFile } from "@/utils/globalFunctions"
import mammoth from "mammoth"
import * as XLSX from "xlsx"
import * as pdfjsLib from "pdfjs-dist"
import pdfjsWorker from "pdfjs-dist/build/pdf.worker.mjs?url"
import heic2any from "heic2any"

// Configurar worker do PDF.js
pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker

const props = defineProps({
  visible: {
    type: Boolean,
    required: true
  },
  listaArquivosId: {
    type: Array,
    required: true
  },
  arquivoInicialId: {
    type: Number,
    default: null
  }
})

const emits = defineEmits(["update:visible"])

const files = ref(null)
const currentFileIndex = ref(0)
const wordHtmlContent = ref("")
const pdfCanvasContainer = ref(null)
const pdfLoading = ref(false)
const pdfError = ref(false)
const pdfPages = ref([])
const excelHtmlContent = ref("")
const loadingDocument = ref(false)
const showThumbnails = ref(false)
const loadingHeic = ref(false)
const heicConvertedUrl = ref(null)
const windowWidth = ref(
  typeof window !== "undefined" ? window.innerWidth : 1024
)

// Computed para o arquivo atual
const currentFile = computed(() => {
  if (!files.value || files.value.length === 0) return null
  return files.value[currentFileIndex.value] || files.value[0]
})

const isMobile = computed(() => {
  return (
    /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
      navigator.userAgent
    ) || windowWidth.value <= 768
  )
})

const isDesktop = computed(() => {
  return windowWidth.value >= 1024
})

const visibleLocal = computed({
  get() {
    return props.visible
  },
  set(value) {
    emits("update:visible", value)
  }
})

// Função para selecionar arquivo
const selectFile = (index) => {
  currentFileIndex.value = index
  showThumbnails.value = false
}

// Função para verificar se é uma imagem
const isImage = (extension) => {
  if (!extension) return false
  const imageExtensions = ["jpg", "jpeg", "jfif", "png", "gif", "bmp", "webp", "svg"]
  return imageExtensions.includes(extension.toLowerCase())
}

// Função para verificar se é Word
const isWord = (extension) => {
  if (!extension) return false
  const wordExtensions = ["doc", "docx"]
  return wordExtensions.includes(extension.toLowerCase())
}

// Função para verificar se é Excel
const isExcel = (extension) => {
  if (!extension) return false
  const excelExtensions = ["xls", "xlsx"]
  return excelExtensions.includes(extension.toLowerCase())
}

// Função para verificar se é vídeo
const isVideo = (extension) => {
  if (!extension) return false
  const videoExtensions = ["mp4", "webm", "ogg", "mov", "avi", "mkv"]
  return videoExtensions.includes(extension.toLowerCase())
}

// Função para verificar se é HEIC/HEIF
const isHeic = (extension) => {
  if (!extension) return false
  const heicExtensions = ["heic", "heif"]
  return heicExtensions.includes(extension.toLowerCase())
}

// Função para verificar se é HTML
const isHtml = (extension) => {
  if (!extension) return false
  const htmlExtensions = ["html", "htm"]
  return htmlExtensions.includes(extension.toLowerCase())
}

// Função para converter HEIC para JPEG
const converterHeicParaJpeg = async (file) => {
  try {
    loadingHeic.value = true
    heicConvertedUrl.value = null

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
      // Segunda tentativa: fetch sem CORS
      try {
        response = await fetch(file.external_link, {
          headers: {
            Accept: "image/*,*/*"
          }
        })
      } catch (fetchError) {
        // Terceira tentativa: através do backend Laravel
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

    // Verifica se o blob é válido
    if (blob.size === 0) {
      throw new Error("Arquivo vazio ou inacessível")
    }

    // Converte HEIC para JPEG
    const convertedBlob = await heic2any({
      blob: blob,
      toType: "image/jpeg",
      quality: 0.8,
      multiple: false
    })

    // Cria URL temporária para a imagem convertida
    const jpegUrl = URL.createObjectURL(convertedBlob)
    heicConvertedUrl.value = jpegUrl

    return jpegUrl
  } catch (error) {
    console.error("Erro ao converter HEIC:", error.message)

    let mensagemErro = "Não foi possível converter o arquivo HEIC."

    if (error.message.includes("fetch") || error.message.includes("HTTP")) {
      mensagemErro += " Problema ao acessar o arquivo no servidor."
    } else if (
      error.message.includes("heic2any") ||
      error.message.includes("Failed to decode")
    ) {
      mensagemErro += " O arquivo pode estar corrompido ou não ser HEIC válido."
    } else if (error.message.includes("vazio")) {
      mensagemErro += " O arquivo está vazio ou inacessível."
    }

    toastWarning(mensagemErro)
    return null
  } finally {
    loadingHeic.value = false
  }
}

// Função para limpar cache de HEIC
const limparCacheHeic = () => {
  if (heicConvertedUrl.value && heicConvertedUrl.value.startsWith("blob:")) {
    URL.revokeObjectURL(heicConvertedUrl.value)
    heicConvertedUrl.value = null
  }
}

// Função para carregar e renderizar PDF (desktop e mobile)
const loadPdfDocument = async (file) => {
  pdfLoading.value = true
  pdfError.value = false
  pdfPages.value = []

  try {
    // Baixa o arquivo como ArrayBuffer via backend (evita problemas de CORS/mixed content em produção)
    const response = await axios.get(`/api/files/download/${file.id}`, {
      responseType: "arraybuffer"
    })

    // Carrega o PDF
    const loadingTask = pdfjsLib.getDocument({ data: response.data })
    const pdf = await loadingTask.promise
    const numPages = pdf.numPages

    // Aguardar um pouco para garantir que o DOM está pronto
    await nextTick()
    await new Promise((resolve) => setTimeout(resolve, 100))

    // Calcular largura do container
    // Desktop: usa largura maior, Mobile: usa largura da tela
    const containerEl = pdfCanvasContainer.value?.parentElement
    const containerWidth = containerEl
      ? containerEl.clientWidth - 32
      : window.innerWidth - 32
    const maxWidth = Math.min(containerWidth, 900) // Máximo 900px para legibilidade
    const pixelRatio = window.devicePixelRatio || 2 // Para telas de alta resolução

    for (let i = 1; i <= numPages; i++) {
      const page = await pdf.getPage(i)
      const viewport = page.getViewport({ scale: 1 })

      // Calcular escala para caber na tela com boa qualidade
      const baseScale = maxWidth / viewport.width
      const scale = baseScale * pixelRatio // Aumenta a qualidade
      const scaledViewport = page.getViewport({ scale })

      // Criar canvas para esta página
      const canvas = document.createElement("canvas")
      const context = canvas.getContext("2d")

      // Canvas em alta resolução
      canvas.height = scaledViewport.height
      canvas.width = scaledViewport.width

      // Mas exibir no tamanho correto via CSS
      canvas.style.width = `${scaledViewport.width / pixelRatio}px`
      canvas.style.height = `${scaledViewport.height / pixelRatio}px`
      canvas.className = "shadow-lg rounded bg-white mb-3"

      // Adicionar ao array
      pdfPages.value.push({ pageNum: i, canvas: canvas })

      // Renderizar
      await page.render({
        canvasContext: context,
        viewport: scaledViewport
      }).promise

      // Adicionar canvas ao container
      if (pdfCanvasContainer.value) {
        pdfCanvasContainer.value.appendChild(canvas)
      }
    }
  } catch (error) {
    console.error("Erro ao carregar PDF:", error)
    pdfError.value = true
  } finally {
    pdfLoading.value = false
  }
}

// Função para carregar e renderizar documento Word
const loadWordDocument = async (file) => {
  loadingDocument.value = true
  wordHtmlContent.value = ""

  try {
    // Baixa o arquivo como ArrayBuffer via backend (evita problemas de CORS/mixed content em produção)
    const response = await axios.get(`/api/files/download/${file.id}`, {
      responseType: "arraybuffer"
    })

    // Converte para HTML usando mammoth
    const result = await mammoth.convertToHtml({ arrayBuffer: response.data })
    wordHtmlContent.value = result.value

    if (result.messages.length > 0) {
      console.warn("Avisos do mammoth:", result.messages)
    }
  } catch (error) {
    console.error("Erro ao carregar documento Word:", error)
    // No mobile, apenas mostra o fallback sem fazer download automático
    wordHtmlContent.value = ""
  } finally {
    loadingDocument.value = false
  }
}

// Função para carregar e renderizar planilha Excel
const loadExcelDocument = async (file) => {
  loadingDocument.value = true
  excelHtmlContent.value = ""

  try {
    // Baixa o arquivo como ArrayBuffer via backend (evita problemas de CORS/mixed content em produção)
    const response = await axios.get(`/api/files/download/${file.id}`, {
      responseType: "arraybuffer"
    })

    // Lê o arquivo Excel
    const workbook = XLSX.read(response.data, { type: "array" })

    // Pega a primeira planilha
    const firstSheetName = workbook.SheetNames[0]
    const worksheet = workbook.Sheets[firstSheetName]

    // Converte para HTML
    excelHtmlContent.value = XLSX.utils.sheet_to_html(worksheet, {
      editable: false,
      header: "",
      footer: ""
    })
  } catch (error) {
    console.error("Erro ao carregar planilha Excel:", error)
    // No mobile, apenas mostra o fallback sem fazer download automático
    excelHtmlContent.value = ""
  } finally {
    loadingDocument.value = false
  }
}

// Função para gerar URL do Microsoft Office Online Viewer
const getOfficeViewerUrl = (fileUrl) => {
  return `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(fileUrl)}`
}

// Função para fazer download do arquivo via URL
const downloadFileFromUrl = async (file) => {
  try {
    const link = document.createElement("a")
    link.href = file.external_link
    link.download = file.original_name || file.stored_name || "arquivo"
    link.target = "_blank"

    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch (error) {
    console.error("Erro ao fazer download:", error)
    window.open(file.external_link, "_blank")
  }
}

// Função para fazer download usando a globalFunction
const downloadFileById = async (fileId) => {
  try {
    await downloadFile(fileId)
  } catch (error) {
    console.error("Erro ao fazer download:", error)
    toastError("Erro ao fazer download do arquivo")
  }
}

// Navegação entre arquivos
const nextFile = () => {
  if (currentFileIndex.value < files.value.length - 1) {
    currentFileIndex.value++
  }
}

const previousFile = () => {
  if (currentFileIndex.value > 0) {
    currentFileIndex.value--
  }
}

// Definir arquivo inicial se especificado
const setInitialFile = () => {
  if (props.arquivoInicialId && files.value) {
    const initialIndex = files.value.findIndex(
      (file) => file.id === props.arquivoInicialId
    )
    if (initialIndex !== -1) {
      currentFileIndex.value = initialIndex
    }
  }
}

// Função para lidar com erro de carregamento de imagem principal
const handleMainImageError = async (file) => {
  toastWarning(
    "Não foi possível exibir a imagem. Fazendo download automático..."
  )
  await downloadFileFromUrl(file)
}

// Watch para resetar o index quando a lista de arquivos mudar
watch(
  () => props.listaArquivosId,
  () => {
    currentFileIndex.value = 0
    wordHtmlContent.value = ""
    excelHtmlContent.value = ""
    pdfPages.value = []
    pdfError.value = false
    showThumbnails.value = false
    limparCacheHeic()
    if (props.listaArquivosId.length > 0) {
      fetchFile()
    }
  }
)

// Watch para carregar documento Word/Excel/PDF/HEIC quando mudar de arquivo
watch(
  () => currentFile.value,
  async (newFile) => {
    if (!newFile) return

    wordHtmlContent.value = ""
    excelHtmlContent.value = ""
    pdfPages.value = []
    pdfError.value = false
    limparCacheHeic()

    // Limpar container do PDF
    if (pdfCanvasContainer.value) {
      pdfCanvasContainer.value.innerHTML = ""
    }

    if (isWord(newFile.extension)) {
      await loadWordDocument(newFile)
    } else if (isExcel(newFile.extension)) {
      await loadExcelDocument(newFile)
    } else if (newFile.extension === "pdf") {
      await loadPdfDocument(newFile)
    } else if (isHeic(newFile.extension)) {
      await converterHeicParaJpeg(newFile)
    }
  },
  { immediate: true }
)

// Função para atualizar largura da janela
const handleResize = () => {
  windowWidth.value = window.innerWidth
  // Fecha miniaturas automaticamente quando muda para desktop
  if (windowWidth.value >= 1024) {
    showThumbnails.value = false
  }
}

// Suporte a navegação por teclado
const handleKeydown = (e) => {
  if (!props.visible || !files.value || files.value.length <= 1) return

  if (e.key === "ArrowLeft") {
    previousFile()
  } else if (e.key === "ArrowRight") {
    nextFile()
  } else if (e.key === "Escape") {
    visibleLocal.value = false
  }
}

onMounted(() => {
  if (props.listaArquivosId?.length > 0) {
    fetchFile()
  }
  window.addEventListener("resize", handleResize)
  window.addEventListener("keydown", handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener("resize", handleResize)
  window.removeEventListener("keydown", handleKeydown)
})

async function fetchFile() {
  console.log("Enviando IDs para buscar arquivos:", props.listaArquivosId)
  const params = props.listaArquivosId
  files.value = null // Reset para mostrar loading

  try {
    const response = await axios.post("/util/files-view", params)
    console.log("Dados recebidos da API:", response.data)

    // Normalizar external_link para usar o origin atual (evita erro de porta diferente)
    const origin = window.location.origin
    files.value = (response.data || []).map((f) => ({
      ...f,
      external_link: f.external_link
        ? f.external_link.replace(/^https?:\/\/[^/]+/, origin)
        : f.external_link
    }))
    setInitialFile()
  } catch (error) {
    console.error("Erro ao buscar arquivo:", error)
    toastError("Erro ao buscar arquivos. Verifique sua conexão.")
    files.value = []
  }
}
</script>

<style scoped>
/* Estilos para tabela Excel */
.excel-table-wrapper :deep(table) {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

@media (min-width: 640px) {
  .excel-table-wrapper :deep(table) {
    font-size: 13px;
  }
}

@media (min-width: 768px) {
  .excel-table-wrapper :deep(table) {
    font-size: 14px;
  }
}

.excel-table-wrapper :deep(th),
.excel-table-wrapper :deep(td) {
  border: 1px solid #e5e7eb;
  padding: 4px 6px;
  text-align: left;
  white-space: nowrap;
}

@media (min-width: 640px) {
  .excel-table-wrapper :deep(th),
  .excel-table-wrapper :deep(td) {
    padding: 6px 10px;
  }
}

@media (min-width: 768px) {
  .excel-table-wrapper :deep(th),
  .excel-table-wrapper :deep(td) {
    padding: 8px 12px;
  }
}

.excel-table-wrapper :deep(th) {
  background-color: #f3f4f6;
  font-weight: 600;
  position: sticky;
  top: 0;
  z-index: 1;
}

.excel-table-wrapper :deep(tr:nth-child(even)) {
  background-color: #f9fafb;
}

.excel-table-wrapper :deep(tr:hover) {
  background-color: #f3f4f6;
}

/* Estilos para documento Word */
.prose :deep(img) {
  max-width: 100%;
  height: auto;
}

.prose :deep(table) {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

@media (min-width: 640px) {
  .prose :deep(table) {
    font-size: 13px;
  }
}

@media (min-width: 768px) {
  .prose :deep(table) {
    font-size: 14px;
  }
}

.prose :deep(th),
.prose :deep(td) {
  border: 1px solid #e5e7eb;
  padding: 4px 6px;
}

@media (min-width: 640px) {
  .prose :deep(th),
  .prose :deep(td) {
    padding: 6px 8px;
  }
}

@media (min-width: 768px) {
  .prose :deep(th),
  .prose :deep(td) {
    padding: 8px;
  }
}

/* Suporte para gestos de swipe */
@media (max-width: 1023px) {
  .swipe-container {
    touch-action: pan-y pinch-zoom;
  }
}

/* Animações suaves */
.transition-all {
  transition-property: all;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 200ms;
}

/* Fix para Dialog sem fundo escuro */
:deep(.p-dialog),
:deep(.p-dialog-content),
:deep(.p-dialog-root) {
  background-color: white !important;
}

/* PDF viewer - forçar ocupar todo espaço */
object[type="application/pdf"],
embed[type="application/pdf"] {
  display: block;
  width: 100% !important;
  height: 100% !important;
}
</style>
