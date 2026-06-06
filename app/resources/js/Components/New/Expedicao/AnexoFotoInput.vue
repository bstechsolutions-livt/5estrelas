<script setup>
/**
 * AnexoFotoInput
 * --------------------------------------------------------------------------
 * Componente reutilizável para upload de anexos (imagem e, opcionalmente,
 * PDF) no fluxo de Expedição → Entrega → Devolução.
 *
 * Comportamentos suportados via props:
 *   - capturaCamera=true + acceptPdf=false → accept="image/*" + capture="environment"
 *     (usado no Canhoto da NF e na Placa do Caminhão — em mobile abre a
 *     câmera traseira diretamente, sem permitir galeria).
 *   - capturaCamera=false + acceptPdf=true → accept="image/*,application/pdf"
 *     SEM capture (usado na Imagem do Produto Recebido — permite câmera,
 *     galeria ou PDF, tanto em mobile quanto em desktop).
 *   - Os outros dois combos são suportados defensivamente:
 *     - capturaCamera=false + acceptPdf=false → accept="image/*" sem capture.
 *     - capturaCamera=true + acceptPdf=true → accept="image/*,application/pdf"
 *       + capture="environment".
 *
 * Validação client-side (a validação final é feita no servidor):
 *   - Extensão pertence à whitelist (jpg, jpeg, png, webp; + pdf quando
 *     acceptPdf=true).
 *   - Tamanho ≤ maxSizeMb * 1024 * 1024 bytes.
 *
 * Emits:
 *   - update:modelValue (File | null) — segue o contrato de v-model padrão
 *     do Vue 3. Só é emitido com um File válido; em caso de erro o arquivo
 *     é descartado, o input é resetado e uma mensagem é exibida.
 */

import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: [File, null],
    default: null
  },
  label: {
    type: String,
    required: true
  },
  name: {
    type: String,
    required: true
  },
  /**
   * true → abre a câmera traseira no mobile (canhoto e placa).
   * false → permite seleção livre de arquivo.
   */
  capturaCamera: {
    type: Boolean,
    default: false
  },
  /**
   * true → adiciona PDF à lista de extensões/MIME types aceitos.
   */
  acceptPdf: {
    type: Boolean,
    default: false
  },
  required: {
    type: Boolean,
    default: false
  },
  /**
   * Tamanho máximo em MB. Alinhado com Tamanho_Maximo_Anexo (10 MB) do
   * backend (`max:10240` em KB).
   */
  maxSizeMb: {
    type: Number,
    default: 10
  }
})

const emit = defineEmits(['update:modelValue'])

const inputRef = ref(null)
const erro = ref('')

// ── Whitelist de extensões e atributos derivados das props ──

/**
 * Lista de extensões aceitas. Sempre inclui as extensões de imagem
 * (jpg, jpeg, png, webp). PDF é opcional, conforme acceptPdf.
 */
const extensoesPermitidas = computed(() => {
  const imagens = ['jpg', 'jpeg', 'png', 'webp']
  return props.acceptPdf ? [...imagens, 'pdf'] : imagens
})

/**
 * Atributo `accept` do <input>. Quando acceptPdf=true também libera
 * application/pdf. Usamos image/* para ser tolerante com HEIC no iOS —
 * a validação fina é feita pela whitelist de extensões no @change.
 */
const acceptAttr = computed(() =>
  props.acceptPdf ? 'image/*,application/pdf' : 'image/*'
)

/**
 * Atributo `capture` — presente somente quando capturaCamera=true.
 * Em Dispositivo_Mobile, "environment" abre diretamente a câmera
 * traseira e bloqueia a seleção pela galeria. Em desktop, é ignorado.
 */
const captureAttr = computed(() =>
  props.capturaCamera ? 'environment' : null
)

// Info do arquivo selecionado para exibir preview textual
const arquivoSelecionado = computed(() => props.modelValue)

const tamanhoFormatado = computed(() => {
  const arquivo = arquivoSelecionado.value
  if (!arquivo) return ''
  return `${(arquivo.size / 1024 / 1024).toFixed(2)} MB`
})

const ehPdf = computed(() => {
  const arquivo = arquivoSelecionado.value
  if (!arquivo) return false
  const ext = (arquivo.name.split('.').pop() || '').toLowerCase()
  return ext === 'pdf'
})

// ── Validação client-side ──
//
// No @change é feita a validação de extensão e tamanho. Se falhar:
//   1. NÃO emitimos update:modelValue com o arquivo (mantemos null).
//   2. Guardamos a mensagem em `erro` para renderização abaixo do campo.
//   3. Resetamos o <input> atribuindo value = '' para permitir que o
//      usuário tente novamente com o mesmo arquivo (sem o reset o
//      @change não dispara se o usuário re-selecionar o mesmo arquivo).
function onChange(event) {
  erro.value = ''
  const arquivo = event.target.files && event.target.files[0]

  if (!arquivo) {
    emit('update:modelValue', null)
    return
  }

  const extensao = (arquivo.name.split('.').pop() || '').toLowerCase()
  const limiteBytes = props.maxSizeMb * 1024 * 1024

  // 1) Extensão dentro da whitelist?
  if (!extensoesPermitidas.value.includes(extensao)) {
    erro.value = `Formato não suportado: .${extensao}. Use ${extensoesPermitidas.value
      .map((e) => `.${e}`)
      .join(', ')}.`
    resetarInput()
    emit('update:modelValue', null)
    return
  }

  // 2) Tamanho dentro do limite?
  if (arquivo.size > limiteBytes) {
    const tamanhoMb = (arquivo.size / 1024 / 1024).toFixed(1)
    erro.value = `Arquivo muito grande: ${tamanhoMb} MB (limite ${props.maxSizeMb} MB).`
    resetarInput()
    emit('update:modelValue', null)
    return
  }

  // Arquivo válido → propaga via v-model
  emit('update:modelValue', arquivo)
}

function resetarInput() {
  if (inputRef.value) {
    inputRef.value.value = ''
  }
}

function limpar() {
  erro.value = ''
  resetarInput()
  emit('update:modelValue', null)
}

// Sincroniza reset externo (quando o pai zera o v-model)
watch(
  () => props.modelValue,
  (novo) => {
    if (!novo) {
      resetarInput()
    }
  }
)
</script>

<template>
  <div class="w-full">
    <!-- Label -->
    <label
      :for="name"
      class="block text-xs font-medium text-gray-500 uppercase mb-2 dark:text-gray-400"
    >
      {{ label }}
      <span
        v-if="required"
        class="text-red-500"
        aria-hidden="true"
      >*</span>
    </label>

    <!-- Campo de upload: botão estilizado + input escondido -->
    <label
      :class="[
        'flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg transition cursor-pointer border',
        erro
          ? 'bg-red-50 text-red-700 border-red-300 hover:bg-red-100'
          : 'bg-gray-100 text-gray-700 border-gray-300 hover:bg-gray-200 dark:bg-slate-700 dark:text-gray-200 dark:border-slate-600 dark:hover:bg-slate-600'
      ]"
    >
      <!-- Ícone customizável via slot; default varia com capturaCamera -->
      <slot name="icone">
        <i
          :class="
            capturaCamera
              ? 'pi pi-camera'
              : 'pi pi-cloud-upload'
          "
        ></i>
      </slot>

      <span v-if="!arquivoSelecionado">
        {{ capturaCamera ? 'Capturar Foto' : 'Selecionar Arquivo' }}
      </span>
      <span v-else>Trocar Arquivo</span>

      <input
        :id="name"
        ref="inputRef"
        :name="name"
        type="file"
        class="hidden"
        :accept="acceptAttr"
        :capture="captureAttr"
        :required="required"
        @change="onChange"
      />
    </label>

    <!-- Preview do arquivo selecionado (nome + ícone + tamanho) -->
    <div
      v-if="arquivoSelecionado && !erro"
      class="mt-2 flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300"
    >
      <i
        :class="
          ehPdf
            ? 'pi pi-file-pdf text-red-500'
            : 'pi pi-image text-emerald-600'
        "
      ></i>
      <span
        class="truncate max-w-xs font-medium"
        :title="arquivoSelecionado.name"
      >
        {{ arquivoSelecionado.name }}
      </span>
      <span class="text-gray-400">·</span>
      <span class="text-gray-500">{{ tamanhoFormatado }}</span>
      <button
        type="button"
        class="ml-1 text-red-500 hover:text-red-700"
        aria-label="Remover arquivo"
        @click="limpar"
      >
        <i class="pi pi-times text-[10px]"></i>
      </button>
    </div>

    <!-- Mensagem de erro de validação client-side -->
    <p
      v-if="erro"
      class="mt-1 text-xs text-red-600 dark:text-red-400"
    >
      {{ erro }}
    </p>
  </div>
</template>
