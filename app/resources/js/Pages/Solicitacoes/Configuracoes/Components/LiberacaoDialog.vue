<template>
  <Dialog
    v-model:visible="dialogVisible"
    modal
    :header="`Configurar Liberação - ${assunto?.assunto || 'Assunto'}`"
    class="w-[90vw] max-w-4xl"
    @show="carregarDados"
  >
    <div class="space-y-6">
      <!-- Descrição -->
      <div
        class="text-sm text-gray-600 bg-blue-50 p-4 rounded-lg border border-blue-200"
      >
        <div class="flex items-center mb-2">
          <i class="pi pi-info-circle text-blue-600 mr-2"></i>
          <strong class="text-blue-800">Como funciona a liberação?</strong>
        </div>
        <ul class="list-disc list-inside space-y-1 text-blue-700">
          <li>
            <strong>Sem liberações configuradas:</strong>
            Qualquer usuário pode abrir tickets deste assunto
          </li>
          <li>
            <strong>Com liberações configuradas:</strong>
            Apenas usuários das filiais, funcionários ou departamentos
            especificados podem abrir tickets
          </li>
        </ul>
      </div>

      <!-- Lista de liberações existentes -->
      <div v-if="liberacoes.length > 0">
        <h4 class="text-lg font-semibold text-gray-800 mb-3">
          Liberações Configuradas ({{ liberacoes.length }})
        </h4>
        <div
          class="space-y-2 max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-4"
        >
          <div
            v-for="(liberacao, index) in liberacoes"
            :key="index"
            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
          >
            <div class="flex items-center space-x-3">
              <div
                class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium"
                :class="{
                  'bg-blue-100 text-blue-600': liberacao.tipo === 'filial',
                  'bg-green-100 text-green-600':
                    liberacao.tipo === 'funcionario',
                  'bg-purple-100 text-purple-600':
                    liberacao.tipo === 'areaatuacao'
                }"
              >
                <i
                  class="pi"
                  :class="{
                    'pi-building': liberacao.tipo === 'filial',
                    'pi-user': liberacao.tipo === 'funcionario',
                    'pi-users': liberacao.tipo === 'areaatuacao'
                  }"
                ></i>
              </div>
              <div>
                <div class="font-medium text-gray-800">
                  {{ liberacao.nome }}
                </div>
                <div class="text-xs text-gray-500 capitalize">
                  {{ liberacao.tipo.replace("areaatuacao", "departamento") }}
                </div>
              </div>
            </div>
            <Button
              icon="pi pi-trash"
              severity="danger"
              outlined
              rounded
              size="small"
              @click="removerLiberacao(index)"
              class="!p-2"
            />
          </div>
        </div>
      </div>

      <!-- Formulário para adicionar nova liberação -->
      <div class="border-t pt-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-4">
          Adicionar Nova Liberação
        </h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Tipo de liberação -->
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">
              Tipo de Liberação *
            </label>
            <Select
              v-model="novaLiberacao.tipo"
              :options="tiposLiberacao"
              option-label="label"
              option-value="value"
              placeholder="Selecione o tipo"
              class="w-full"
              @change="limparSelecoes"
            />
          </div>

          <!-- Campo dinâmico baseado no tipo -->
          <div
            class="space-y-2"
            v-if="novaLiberacao.tipo"
          >
            <label class="block text-sm font-medium text-gray-700">
              {{ getTituloConteudo() }} *
            </label>

            <!-- Filial -->
            <Filial2
              v-if="novaLiberacao.tipo === 'filial'"
              v-model="novaLiberacao.filialSelecionada"
              :retorna-objeto="true"
            />

            <!-- Funcionário -->
            <Funcionario
              v-else-if="novaLiberacao.tipo === 'funcionario'"
              v-model="novaLiberacao.funcionarioSelecionado"
              :retorna-objeto="true"
            />

            <!-- Departamento -->
            <Select
              v-else-if="novaLiberacao.tipo === 'areaatuacao'"
              v-model="novaLiberacao.valor"
              :options="dadosLiberacao.departamentos"
              placeholder="Selecione o departamento"
              class="w-full"
              filter
            />
          </div>
        </div>

        <div class="flex justify-end mt-4">
          <Button
            label="Adicionar"
            outlined
            icon="pi pi-plus"
            @click="adicionarLiberacao"
            :disabled="!podeAdicionar"
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
          label="Salvar Liberações"
          icon="pi pi-check"
          outlined
          :loading="loading"
          @click="salvarLiberacoes"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from "vue"
import axios from "axios"
import { swalErro, swalSucesso } from "@/utils/globalFunctions"
import Filial2 from "@/Components/New/Filial2.vue"
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

const emit = defineEmits(["update:modelValue", "liberacoesSalvas"])

const dialogVisible = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value)
})

const loading = ref(false)
const liberacoes = ref([])
const dadosLiberacao = ref({
  departamentos: []
})

const novaLiberacao = ref({
  tipo: null,
  valor: null,
  filialSelecionada: null,
  funcionarioSelecionado: null
})

const tiposLiberacao = [
  { label: "Por Filial", value: "filial" },
  { label: "Por Funcionário", value: "funcionario" },
  { label: "Por Departamento", value: "areaatuacao" }
]

const podeAdicionar = computed(() => {
  if (!novaLiberacao.value.tipo) return false

  switch (novaLiberacao.value.tipo) {
    case "filial":
      return !!novaLiberacao.value.filialSelecionada
    case "funcionario":
      return !!novaLiberacao.value.funcionarioSelecionado
    case "areaatuacao":
      return !!novaLiberacao.value.valor
    default:
      return false
  }
})

function getTituloConteudo() {
  switch (novaLiberacao.value.tipo) {
    case "filial":
      return "Filial"
    case "funcionario":
      return "Funcionário"
    case "areaatuacao":
      return "Departamento"
    default:
      return ""
  }
}

async function carregarDados() {
  if (!props.assunto?.id) {
    return
  }

  try {
    loading.value = true

    // Se o assunto já tem liberações carregadas, usar elas
    if (props.assunto.liberacoes && Array.isArray(props.assunto.liberacoes)) {
      // Mapear as liberações para o formato esperado
      liberacoes.value = props.assunto.liberacoes.map((liberacao) => {
        let nome = ""
        switch (liberacao.tipo) {
          case "filial":
            nome = `${liberacao.valor} - Filial` // Será carregado dinamicamente se necessário
            break
          case "funcionario":
            nome = `${liberacao.valor} - Funcionário` // Será carregado dinamicamente se necessário
            break
          case "areaatuacao":
            nome = liberacao.valor
            break
        }

        return {
          id: liberacao.id,
          tipo: liberacao.tipo,
          valor: liberacao.valor,
          nome: nome
        }
      })

      // Carregar nomes detalhados para filiais e funcionários
      await carregarNomesDetalhados()
    } else {
      // Carregar liberações via API
      const responseLiberacoes = await axios.get(
        `/solicitacoes/configuracoes/liberacoes/${props.assunto.id}`
      )
      liberacoes.value = responseLiberacoes.data
    }

    // Carregar apenas departamentos (filiais e funcionários são carregados pelos componentes)
    const responseDados = await axios.get(
      "/solicitacoes/configuracoes/dados-liberacao"
    )
    dadosLiberacao.value.departamentos = responseDados.data.departamentos
  } catch (error) {
    swalErro("Erro ao carregar dados de liberação")
  } finally {
    loading.value = false
  }
}

async function carregarNomesDetalhados() {
  // Carregar nomes detalhados para filiais e funcionários
  for (let liberacao of liberacoes.value) {
    if (liberacao.tipo === "filial") {
      try {
        const response = await axios.get("/util/filiais-usuario")
        const filial = response.data.find((f) => f.codigo == liberacao.valor)
        if (filial) {
          liberacao.nome = `${filial.codigo} - ${filial.fantasia}`
        }
      } catch (e) {
        console.error("Erro ao buscar filial:", e)
      }
    } else if (liberacao.tipo === "funcionario") {
      try {
        const response = await axios.get("/util/funcionarios2", {
          params: { busca: liberacao.valor }
        })
        const funcionario = response.data.find(
          (f) => f.matricula == liberacao.valor
        )
        if (funcionario) {
          liberacao.nome = `${funcionario.matricula} - ${funcionario.nome}`
        }
      } catch (e) {
        console.error("Erro ao buscar funcionário:", e)
      }
    }
  }
}

function adicionarLiberacao() {
  let valor = null
  let nome = ""

  // Determinar valor e nome baseado no tipo
  switch (novaLiberacao.value.tipo) {
    case "filial":
      if (!novaLiberacao.value.filialSelecionada) return
      valor = novaLiberacao.value.filialSelecionada.codigo
      nome = `${novaLiberacao.value.filialSelecionada.codigo} - ${novaLiberacao.value.filialSelecionada.fantasia}`
      break
    case "funcionario":
      if (!novaLiberacao.value.funcionarioSelecionado) return
      valor = novaLiberacao.value.funcionarioSelecionado.matricula
      nome = `${novaLiberacao.value.funcionarioSelecionado.matricula} - ${novaLiberacao.value.funcionarioSelecionado.nome}`
      break
    case "areaatuacao":
      if (!novaLiberacao.value.valor) return
      valor = novaLiberacao.value.valor
      nome = novaLiberacao.value.valor
      break
    default:
      return
  }

  // Verificar se já existe
  const jaExiste = liberacoes.value.some(
    (lib) => lib.tipo === novaLiberacao.value.tipo && lib.valor == valor
  )

  if (jaExiste) {
    swalErro("", "Esta liberação já foi adicionada")
    return
  }

  liberacoes.value.push({
    tipo: novaLiberacao.value.tipo,
    valor: valor,
    nome: nome
  })

  // Limpar formulário
  novaLiberacao.value = {
    tipo: null,
    valor: null,
    filialSelecionada: null,
    funcionarioSelecionado: null
  }
}

function removerLiberacao(index) {
  liberacoes.value.splice(index, 1)
}

function limparSelecoes() {
  novaLiberacao.value.valor = null
  novaLiberacao.value.filialSelecionada = null
  novaLiberacao.value.funcionarioSelecionado = null
}

async function salvarLiberacoes() {
  try {
    loading.value = true

    const response = await axios.post(
      "/solicitacoes/configuracoes/salvar-liberacoes",
      {
        assunto_id: props.assunto.id,
        liberacoes: liberacoes.value
      }
    )

    if (response.data.success) {
      await swalSucesso("Liberações salvas com sucesso!")
      emit("liberacoesSalvas")
      fecharDialog()
    } else {
      swalErro("Erro ao salvar liberações")
    }
  } catch (error) {
    console.error("Erro ao salvar liberações:", error)
    swalErro("Erro ao salvar liberações")
  } finally {
    loading.value = false
  }
}

function fecharDialog() {
  dialogVisible.value = false
  // Limpar dados ao fechar
  liberacoes.value = []
  novaLiberacao.value = {
    tipo: null,
    valor: null,
    filialSelecionada: null,
    funcionarioSelecionado: null
  }
}
</script>
