<template>
  <Dialog
    v-model:visible="dialogVisible"
    modal
    :header="`Configurar Responsáveis - ${assunto?.assunto || 'Assunto'}`"
    class="w-[90vw] max-w-3xl"
    @show="carregarDados"
  >
    <div class="space-y-6">
      <!-- Descrição -->
      <div
        class="text-sm text-gray-600 bg-indigo-50 p-4 rounded-lg border border-indigo-200"
      >
        <div class="flex items-center mb-2">
          <i class="pi pi-info-circle text-indigo-600 mr-2"></i>
          <strong class="text-indigo-800">
            Como funcionam os Responsáveis?
          </strong>
        </div>
        <ul class="list-disc list-inside space-y-1 text-indigo-700">
          <li>
            <strong>Sem responsáveis configurados:</strong>
            Qualquer usuário do departamento pode ver e atender tickets
            deste assunto
          </li>
          <li>
            <strong>Com responsáveis configurados:</strong>
            Apenas os usuários listados podem ver e atender as tickets
            deste assunto
          </li>
        </ul>
      </div>

      <!-- Lista de responsáveis existentes -->
      <div v-if="responsaveis.length > 0">
        <h4 class="text-lg font-semibold text-gray-800 mb-3">
          Responsáveis Configurados ({{ responsaveis.length }})
        </h4>
        <div
          class="space-y-2 max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-4"
        >
          <div
            v-for="(responsavel, index) in responsaveis"
            :key="index"
            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
          >
            <div class="flex items-center space-x-3">
              <div
                class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium bg-indigo-100 text-indigo-600"
              >
                <i class="pi pi-user"></i>
              </div>
              <div>
                <div class="font-medium text-gray-800">
                  {{ responsavel.nome }}
                </div>
                <div class="text-xs text-gray-500">
                  Matrícula: {{ responsavel.matricula }}
                </div>
              </div>
            </div>
            <Button
              icon="pi pi-trash"
              severity="danger"
              outlined
              rounded
              size="small"
              @click="removerResponsavel(index)"
              class="!p-2"
            />
          </div>
        </div>
      </div>

      <!-- Formulário para adicionar novo responsável -->
      <div class="border-t pt-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-4">
          Adicionar Novo Responsável
        </h4>

        <div class="flex gap-4 items-end">
          <div class="flex-1 space-y-2">
            <label class="block text-sm font-medium text-gray-700">
              Funcionário *
            </label>
            <Funcionario
              v-model="novoResponsavel"
              :retorna-objeto="true"
              placeholder="Buscar funcionário..."
            />
          </div>

          <Button
            label="Adicionar"
            outlined
            icon="pi pi-plus"
            @click="adicionarResponsavel"
            :disabled="!novoResponsavel"
            class="px-6"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end space-x-3">
        <Button
          label="Cancelar"
          icon="pi pi-times"
          outlined
          severity="secondary"
          @click="fecharDialog"
        />
        <Button
          label="Salvar Responsáveis"
          icon="pi pi-check"
          outlined
          :loading="loading"
          @click="salvarResponsaveis"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed } from "vue"
import axios from "axios"
import { swalErro, swalSucesso } from "@/utils/globalFunctions"
import Funcionario from "@/Components/Componentes/Funcionario.vue"

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  assunto: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(["update:modelValue", "responsaveisSalvos"])

const dialogVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value)
})

const loading = ref(false)
const responsaveis = ref([])
const novoResponsavel = ref(null)

async function carregarDados() {
  if (!props.assunto?.id) {
    console.error("Assunto não tem ID:", props.assunto)
    return
  }

  try {
    loading.value = true

    // Se o assunto já tem responsáveis carregados, usar eles
    if (
      props.assunto.responsaveis &&
      Array.isArray(props.assunto.responsaveis)
    ) {
      responsaveis.value = props.assunto.responsaveis.map((resp) => ({
        id: resp.id,
        matricula: resp.matricula,
        nome: resp.funcionario?.nome || `Matrícula ${resp.matricula}`
      }))
    } else {
      // Carregar responsáveis via API
      const response = await axios.get(
        `/solicitacoes/configuracoes/responsaveis/${props.assunto.id}`
      )
      responsaveis.value = response.data
    }
  } catch (error) {
    console.error("Erro ao carregar dados:", error)
    swalErro("Erro ao carregar responsáveis")
  } finally {
    loading.value = false
  }
}

function adicionarResponsavel() {
  if (!novoResponsavel.value) return

  // Verificar se já existe
  const jaExiste = responsaveis.value.some(
    (resp) => resp.matricula == novoResponsavel.value.matricula
  )

  if (jaExiste) {
    swalErro("", "Este funcionário já foi adicionado como responsável")
    return
  }

  responsaveis.value.push({
    matricula: novoResponsavel.value.matricula,
    nome: novoResponsavel.value.nome
  })

  // Limpar seleção
  novoResponsavel.value = null
}

function removerResponsavel(index) {
  responsaveis.value.splice(index, 1)
}

async function salvarResponsaveis() {
  try {
    loading.value = true

    const response = await axios.post(
      "/solicitacoes/configuracoes/salvar-responsaveis",
      {
        assunto_id: props.assunto.id,
        responsaveis: responsaveis.value
      }
    )

    if (response.data.success) {
      await swalSucesso("Responsáveis salvos com sucesso!")
      emit("responsaveisSalvos")
      fecharDialog()
    } else {
      swalErro("Erro ao salvar responsáveis")
    }
  } catch (error) {
    console.error("Erro ao salvar responsáveis:", error)
    swalErro("Erro ao salvar responsáveis")
  } finally {
    loading.value = false
  }
}

function fecharDialog() {
  dialogVisible.value = false
  responsaveis.value = []
  novoResponsavel.value = null
}
</script>
