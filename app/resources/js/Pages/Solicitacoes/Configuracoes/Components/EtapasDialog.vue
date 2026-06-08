<template>
  <Dialog
    v-model:visible="dialogVisible"
    modal
    :closable="false"
    :header="`Etapas de Andamento - ${assunto?.assunto || 'Assunto'}`"
    class="w-full sm:w-[95vw] max-w-4xl mx-0 sm:mx-auto"
    @show="carregarEtapas"
    :pt="{
      root: {
        class: 'overflow-hidden !rounded-none sm:!rounded-xl m-0 sm:m-4'
      },
      header: {
        class:
          'border-b border-gray-100 dark:border-slate-700 text-base sm:text-lg'
      },
      content: { class: 'p-0 max-h-[85vh] sm:max-h-[75vh] overflow-y-auto' }
    }"
  >
    <div class="p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-5">
      <!-- Descrição - Oculta em mobile para economizar espaço -->
      <div
        class="hidden sm:block text-sm text-gray-600 bg-gradient-to-r from-violet-50 to-purple-50 p-3 sm:p-4 rounded-xl border border-violet-200"
      >
        <div class="flex items-center mb-2">
          <i class="pi pi-info-circle text-violet-600 mr-2"></i>
          <strong class="text-violet-800 text-xs sm:text-sm">
            Como funcionam as Etapas de Andamento?
          </strong>
        </div>
        <ul
          class="list-disc list-inside space-y-1 text-violet-700 text-xs sm:text-sm"
        >
          <li>
            Configure etapas para acompanhar o progresso das tickets deste
            assunto (ex: "Triagem", "Desenvolvimento", "Teste")
          </li>
          <li>
            O gestor poderá ver e alterar a etapa atual de cada ticket na
            listagem
          </li>
          <li>
            Quando vinculadas ao
            <strong>Fluxo/Workflow</strong>
            , as etapas são atualizadas automaticamente ao avançar
          </li>
          <li>
            Use
            <strong>Clonar Etapas</strong>
            para copiar as etapas de outro assunto que já esteja configurado
          </li>
          <li>
            Todas as alterações de etapa ficam registradas no histórico e na
            timeline da ticket
          </li>
        </ul>
      </div>

      <!-- Header com botão adicionar -->
      <div
        class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-0"
      >
        <h4 class="text-base sm:text-lg font-semibold text-gray-800">
          Etapas Configuradas ({{ etapas.length }})
        </h4>
        <div
          class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 w-full sm:w-auto"
        >
          <Button
            label="Nova Etapa"
            icon="pi pi-plus"
            severity="success"
            outlined
            size="small"
            class="w-full sm:w-auto"
            @click="adicionarEtapa"
          />
          <Button
            v-if="etapas.length === 0"
            label="Clonar Etapas"
            icon="pi pi-copy"
            severity="info"
            outlined
            size="small"
            class="w-full sm:w-auto"
            @click="dialogClonarEtapas = true"
          />
        </div>
      </div>

      <!-- Lista de etapas vazia -->
      <div
        v-if="etapas.length === 0"
        class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-500"
      >
        <div
          class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 rounded-full flex items-center justify-center mb-3 sm:mb-4"
        >
          <i class="pi pi-sitemap text-2xl sm:text-3xl text-gray-400"></i>
        </div>
        <p class="text-base sm:text-lg font-medium mb-2">
          Nenhuma etapa configurada
        </p>
        <p class="text-xs sm:text-sm text-center max-w-md px-4">
          Clique em "Nova Etapa" para começar a configurar as etapas de
          andamento para este assunto
        </p>
      </div>

      <!-- Lista de etapas -->
      <div v-else>
        <draggable
          v-model="etapas"
          item-key="id"
          handle=".drag-handle"
          ghost-class="opacity-50"
          animation="200"
          class="space-y-3 sm:space-y-4"
        >
          <template #item="{ element: etapa, index }">
            <div
              class="p-2 sm:p-3 bg-white border rounded-xl hover:shadow-md transition-all duration-200"
              :class="{ 'border-violet-300 bg-violet-50/30': etapa.editando }"
            >
              <div class="flex items-start gap-2">
                <!-- Drag handle e número da ordem -->
                <div class="flex flex-col items-center gap-1">
                  <button
                    type="button"
                    class="drag-handle cursor-grab active:cursor-grabbing p-1 sm:p-1.5 hover:bg-gray-100 rounded-lg transition-colors"
                    v-tooltip.top="'Arrastar para reordenar'"
                  >
                    <i class="pi pi-bars text-gray-400 text-sm"></i>
                  </button>
                  <span
                    class="w-5 h-5 sm:w-6 sm:h-6 rounded-full flex items-center justify-center text-[10px] sm:text-xs font-bold text-white"
                    :style="{ backgroundColor: etapa.cor || '#3B82F6' }"
                  >
                    {{ index + 1 }}
                  </span>
                </div>

                <!-- Conteúdo da etapa -->
                <div class="flex-1 space-y-2">
                  <!-- Linha 1: Nome e ações -->
                  <div
                    class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3"
                  >
                    <input
                      type="text"
                      v-model="etapa.nome"
                      class="flex-1 font-medium px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all"
                      :class="{ 'border-red-500': !etapa.nome }"
                      placeholder="Nome da Etapa"
                    />

                    <!-- Controles em linha -->
                    <div
                      class="flex items-center gap-2 justify-between sm:justify-start"
                    >
                      <!-- Seletor de cor -->
                      <div class="relative">
                        <button
                          type="button"
                          @click="toggleColorPicker(index)"
                          class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg border-2 border-gray-200 hover:border-gray-400 transition-colors flex items-center justify-center"
                          :style="{ backgroundColor: etapa.cor || '#3B82F6' }"
                          v-tooltip.top="'Escolher cor'"
                        >
                          <i
                            class="pi pi-palette text-white text-xs sm:text-sm drop-shadow"
                          ></i>
                        </button>

                        <!-- Color picker dropdown -->
                        <div
                          v-if="colorPickerAberto === index"
                          class="absolute top-12 right-0 z-50 bg-white rounded-xl shadow-xl border border-gray-200 p-3 w-52"
                        >
                          <div class="grid grid-cols-5 gap-1.5 mb-2">
                            <button
                              v-for="cor in coresPredefinidas"
                              :key="cor"
                              type="button"
                              class="w-8 h-8 rounded-lg border-2 hover:scale-110 transition-transform flex-shrink-0"
                              :class="
                                etapa.cor === cor
                                  ? 'border-gray-800 ring-2 ring-offset-1 ring-gray-400'
                                  : 'border-transparent'
                              "
                              :style="{ backgroundColor: cor }"
                              @click="selecionarCor(index, cor)"
                            ></button>
                          </div>
                          <InputText
                            v-model="etapa.cor"
                            placeholder="#3B82F6"
                            class="w-full text-xs"
                            @blur="colorPickerAberto = null"
                          />
                        </div>
                      </div>

                      <!-- Seletor de ícone -->
                      <Select
                        v-model="etapa.icone"
                        :options="iconesPredefinidos"
                        option-value="value"
                        option-label="label"
                        placeholder="Ícone"
                        class="w-28 sm:w-36 flex-shrink-0"
                      >
                        <template #value="slotProps">
                          <div
                            v-if="slotProps.value"
                            class="flex items-center gap-1 sm:gap-2"
                          >
                            <i
                              :class="slotProps.value"
                              class="text-xs sm:text-sm"
                            ></i>
                            <span
                              class="text-[10px] sm:text-xs hidden sm:inline"
                            >
                              {{ obterLabelIcone(slotProps.value) }}
                            </span>
                          </div>
                        </template>
                        <template #option="slotProps">
                          <div class="flex items-center gap-2">
                            <i
                              :class="slotProps.option.value"
                              class="text-sm"
                            ></i>
                            <span>{{ slotProps.option.label }}</span>
                          </div>
                        </template>
                      </Select>

                      <!-- Botão remover -->
                      <Button
                        icon="pi pi-trash"
                        severity="danger"
                        text
                        rounded
                        size="small"
                        @click="removerEtapa(index)"
                        v-tooltip.top="'Remover etapa'"
                      />
                    </div>
                  </div>

                  <!-- Linha 2: Descrição -->
                  <input
                    type="text"
                    v-model="etapa.descricao"
                    class="w-full text-xs sm:text-sm px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-all"
                    placeholder="Descrição (opcional)"
                  />
                </div>
              </div>
            </div>
          </template>
        </draggable>
      </div>

      <!-- Botão para ver fluxo -->
      <div
        v-if="etapas.length > 0"
        class="border-t pt-3 sm:pt-4 flex justify-center sm:justify-end"
      >
        <Button
          label="Ver Fluxo"
          icon="pi pi-eye"
          severity="info"
          outlined
          size="small"
          class="w-full sm:w-auto"
          @click="dialogFluxo = true"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex flex-row justify-end gap-2 sm:gap-3">
        <Button
          label="Cancelar"
          icon="pi pi-times"
          outlined
          severity="secondary"
          @click="fecharDialog"
        />
        <Button
          label="Salvar Etapas"
          icon="pi pi-check"
          severity="success"
          outlined
          :loading="loading"
          @click="salvarEtapas"
        />
      </div>
    </template>
  </Dialog>

  <!-- Dialog de visualização do fluxo -->
  <Dialog
    v-model:visible="dialogFluxo"
    modal
    :closable="false"
    header="Visualização do Fluxo"
    class="w-full sm:w-[95vw] max-w-5xl mx-0 sm:mx-auto"
    :pt="{
      root: { class: '!rounded-none sm:!rounded-xl m-0 sm:m-4' },
      content: { class: 'p-3 sm:p-6' }
    }"
  >
    <div
      class="flex flex-col sm:flex-row sm:flex-nowrap items-center justify-center gap-3 sm:gap-3 overflow-x-auto pb-2"
    >
      <template
        v-for="(etapa, index) in etapas"
        :key="index"
      >
        <div
          class="w-full sm:w-auto flex-shrink-0 px-4 py-2.5 rounded-full text-white text-sm font-medium flex items-center justify-center gap-2 shadow-lg whitespace-nowrap"
          :style="{ backgroundColor: etapa.cor || '#3B82F6' }"
        >
          <i
            :class="etapa.icone || 'pi pi-circle'"
            class="text-sm"
          ></i>
          {{ etapa.nome || "Etapa " + (index + 1) }}
        </div>
        <!-- Seta para baixo (apenas mobile) -->
        <i
          v-if="index < etapas.length - 1"
          class="pi pi-arrow-down text-gray-400 text-lg block sm:!hidden"
        ></i>
        <!-- Seta para direita (apenas desktop) -->
        <i
          v-if="index < etapas.length - 1"
          class="pi pi-arrow-right text-gray-400 text-lg !hidden sm:!block"
        ></i>
      </template>
    </div>
    <template #footer>
      <Button
        label="Fechar"
        icon="pi pi-times"
        severity="secondary"
        outlined
        class="w-full sm:w-auto"
        @click="dialogFluxo = false"
      />
    </template>
  </Dialog>

  <!-- Dialog Clonar Etapas -->
  <Dialog
    v-model:visible="dialogClonarEtapas"
    header="Clonar Etapas de outro Assunto"
    modal
    :style="{ width: '400px' }"
    :closable="!clonarLoading"
    @show="carregarAssuntosComEtapas"
  >
    <div class="space-y-4">
      <p class="text-sm text-gray-600">
        Selecione o assunto de onde deseja copiar as etapas de andamento.
      </p>
      <Select
        v-model="clonarAssuntoId"
        :options="assuntosParaClonar"
        optionLabel="label"
        optionValue="value"
        placeholder="Selecione o assunto..."
        filter
        fluid
        :loading="loadingAssuntosClonar"
      />
    </div>
    <template #footer>
      <Button
        label="Cancelar"
        icon="pi pi-times"
        severity="secondary"
        outlined
        :disabled="clonarLoading"
        @click="dialogClonarEtapas = false"
      />
      <Button
        label="Clonar"
        icon="pi pi-copy"
        severity="info"
        :disabled="!clonarAssuntoId"
        :loading="clonarLoading"
        @click="clonarEtapasDeAssunto"
      />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed } from "vue"
import axios from "axios"
import { toastError, toastSuccess } from "@/utils/globalFunctions"
import draggable from "vuedraggable"
import { FloatLabel } from "primevue"

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  assunto: {
    type: Object,
    default: null
  },
  assuntos: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(["update:modelValue", "etapasSalvas"])

const dialogVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value)
})

const loading = ref(false)
const etapas = ref([])
const colorPickerAberto = ref(null)
const dialogFluxo = ref(false)
const dialogClonarEtapas = ref(false)
const clonarAssuntoId = ref(null)
const clonarLoading = ref(false)
const loadingAssuntosClonar = ref(false)
const assuntosParaClonar = ref([])

// Cores predefinidas para seleção rápida
const coresPredefinidas = [
  "#3B82F6", // Azul
  "#10B981", // Verde
  "#F59E0B", // Amarelo
  "#EF4444", // Vermelho
  "#8B5CF6", // Roxo
  "#EC4899", // Rosa
  "#06B6D4", // Cyan
  "#F97316", // Laranja
  "#6366F1", // Índigo
  "#14B8A6", // Teal
  "#84CC16", // Lima
  "#A855F7", // Violeta
  "#22C55E", // Verde Claro
  "#0EA5E9", // Azul Céu
  "#64748B" // Cinza
]

// Ícones predefinidos
const iconesPredefinidos = [
  { value: "pi pi-circle", label: "Círculo" },
  { value: "pi pi-check-circle", label: "Check" },
  { value: "pi pi-clock", label: "Relógio" },
  { value: "pi pi-search", label: "Busca/Triagem" },
  { value: "pi pi-users", label: "Usuários" },
  { value: "pi pi-user", label: "Usuário" },
  { value: "pi pi-briefcase", label: "Trabalho" },
  { value: "pi pi-file", label: "Documento" },
  { value: "pi pi-folder", label: "Pasta" },
  { value: "pi pi-building", label: "Empresa" },
  { value: "pi pi-phone", label: "Telefone" },
  { value: "pi pi-envelope", label: "Email" },
  { value: "pi pi-calendar", label: "Calendário" },
  { value: "pi pi-star", label: "Estrela" },
  { value: "pi pi-flag", label: "Flag" },
  { value: "pi pi-send", label: "Enviar" },
  { value: "pi pi-check", label: "Concluído" },
  { value: "pi pi-times", label: "Cancelado" },
  { value: "pi pi-exclamation-triangle", label: "Alerta" },
  { value: "pi pi-info-circle", label: "Info" },
  { value: "fas fa-handshake", label: "Entrevista" },
  { value: "fas fa-user-tie", label: "Gestor" },
  { value: "fas fa-id-card", label: "Documentação" },
  { value: "fas fa-store", label: "Loja" },
  { value: "fas fa-user-plus", label: "Admissão" }
]

function obterLabelIcone(value) {
  const icone = iconesPredefinidos.find((i) => i.value === value)
  return icone ? icone.label : value
}

function toggleColorPicker(index) {
  colorPickerAberto.value = colorPickerAberto.value === index ? null : index
}

function selecionarCor(index, cor) {
  etapas.value[index].cor = cor
  colorPickerAberto.value = null
}

async function carregarEtapas() {
  if (!props.assunto?.id) {
    etapas.value = []
    return
  }

  loading.value = true
  try {
    const { data } = await axios.get(
      `/solicitacoes/configuracoes/etapas/${props.assunto.id}`
    )
    etapas.value = data
      .filter((e) => e.ativo === "S")
      .map((e) => ({
        ...e,
        editando: false
      }))
  } catch (error) {
    console.error("Erro ao carregar etapas:", error)
    toastError("Erro ao carregar etapas")
    etapas.value = []
  } finally {
    loading.value = false
  }
}

async function carregarAssuntosComEtapas() {
  assuntosParaClonar.value = []
  clonarAssuntoId.value = null
  loadingAssuntosClonar.value = true
  const outrosAssuntos = props.assuntos.filter(
    (a) => a.id !== props.assunto?.id
  )
  try {
    const results = await Promise.allSettled(
      outrosAssuntos.map((a) =>
        axios
          .get(`/solicitacoes/configuracoes/etapas/${a.id}`)
          .then(({ data }) => ({
            assunto: a,
            data
          }))
      )
    )
    for (const r of results) {
      if (
        r.status === "fulfilled" &&
        r.value.data?.filter((e) => e.ativo === "S").length > 0
      ) {
        assuntosParaClonar.value.push({
          label: r.value.assunto.assunto,
          value: r.value.assunto.id
        })
      }
    }
  } finally {
    loadingAssuntosClonar.value = false
  }
}

async function clonarEtapasDeAssunto() {
  if (!clonarAssuntoId.value) return

  clonarLoading.value = true
  try {
    const { data } = await axios.post(
      "/solicitacoes/configuracoes/clonar-etapas",
      {
        origem_assunto_id: clonarAssuntoId.value,
        destino_assunto_id: props.assunto.id
      }
    )
    if (data.success) {
      etapas.value = (data.etapas || []).map((e) => ({ ...e, editando: false }))
      dialogClonarEtapas.value = false
      clonarAssuntoId.value = null
      toastSuccess(data.message)
    } else {
      toastError(data.message)
    }
  } catch (e) {
    toastError("Erro ao clonar etapas")
  } finally {
    clonarLoading.value = false
  }
}

function adicionarEtapa() {
  etapas.value.push({
    id: null,
    nome: null,
    descricao: null,
    cor: coresPredefinidas[etapas.value.length % coresPredefinidas.length],
    icone: "pi pi-circle",
    ordem: etapas.value.length,
    editando: true
  })
}

function removerEtapa(index) {
  etapas.value.splice(index, 1)
}

async function salvarEtapas() {
  // Validar que todas as etapas têm nome
  const etapasSemNome = etapas.value.filter((e) => !e.nome?.trim())
  if (etapasSemNome.length > 0) {
    toastError("Todas as etapas devem ter um nome")
    return
  }

  loading.value = true
  try {
    const { data } = await axios.post(
      "/solicitacoes/configuracoes/salvar-etapas",
      {
        assunto_id: props.assunto.id,
        etapas: etapas.value.map((e, index) => ({
          id: e.id,
          nome: e.nome,
          descricao: e.descricao,
          cor: e.cor,
          icone: e.icone,
          ordem: index
        }))
      }
    )

    if (data.success) {
      toastSuccess(data.message)
      emit("etapasSalvas", data.etapas)
      fecharDialog()
    } else {
      toastError(data.message)
    }
  } catch (error) {
    console.error("Erro ao salvar etapas:", error)
    toastError(error.response?.data?.message || "Erro ao salvar etapas")
  } finally {
    loading.value = false
  }
}

function fecharDialog() {
  dialogVisible.value = false
  colorPickerAberto.value = null
}
</script>

<style scoped>
.drag-handle:active {
  cursor: grabbing;
}
</style>
