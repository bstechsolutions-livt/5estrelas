<template>
  <div
    class="flex flex-col items-center justify-center p-4 border-2 border-gray-400 border-dashed rounded-lg"
  >
    <!-- Input de arquivo -->
    <label
      class="flex flex-col items-center justify-center w-full h-40 cursor-pointer"
      for="file-upload"
    >
      <div class="flex flex-col items-center">
        <i class="mb-2 text-4xl text-green-600 pi pi-file-excel"></i>
        <p class="text-sm text-gray-500">
          Clique ou arraste o arquivo XLSX aqui
        </p>
      </div>
      <input
        id="file-upload"
        type="file"
        class="hidden"
        accept=".xlsx"
        @change="handleFileUpload"
      />
    </label>

    <!-- Exibe nome do arquivo se existir -->
    <div
      v-if="file"
      class="mt-4 text-sm font-medium text-green-700"
    >
      Arquivo selecionado: {{ file.name }}
    </div>

    <!-- Botão para enviar ou processar -->
    <div class="mt-4">
      <button
        @click="prepararImportacao()"
        :disabled="!file"
        class="px-4 py-2 text-white bg-green-600 rounded hover:bg-green-700 disabled:opacity-50"
      >
        Preparar Importação
      </button>
    </div>
  </div>

  <div
    v-if="lista.length"
    class="mt-5 font-bold text-center text-blue-900"
  >
    LISTA PREPARADA
  </div>
  <div
    v-if="lista.length"
    class="space-y-2 h-[350px] overflow-auto shadow-md drop-shadow-md border p-2 rounded-md divide-y-2"
  >
    <div
      v-for="(linha, key) in lista"
      class="flex items-center justify-between px-2"
    >
      <div class="flex flex-wrap items-center pt-1 space-x-2">
        <div
          class="truncate w-52"
          :title="linha.titulo + '\n\n' + linha.descricao"
        >
          {{ linha.titulo }}
        </div>
        <DatePicker
          show-icon
          :manual-input="false"
          class="w-[160px]"
          date-format="dd/mm/yy"
          v-model="linha.data"
        />
        <Funcionario v-model="linha.solicitante" />
        <Filial
          v-model="linha.filial"
          inputClass="h-10 !w-52"
        />
        <Select
          v-model="linha.prioridade"
          :options="['baixa', 'media', 'alta', 'urgente']"
          placeholder="Definida pelo usuário"
          class="!w-[130px]"
        />
      </div>
      <i
        @click="lista.splice(key, 1)"
        class="text-red-700 cursor-pointer pi pi-trash hover:text-red-500"
      ></i>
    </div>
  </div>

  <div
    v-if="lista.length"
    class="flex mt-3 space-x-1"
  >
    <Select
      :options="props.departamentos"
      v-model="departamentoSelecionado"
      option-label="nome"
      fluid
    />
    <Select
      v-if="departamentoSelecionado"
      :options="departamentoSelecionado.assuntos"
      v-model="assuntoSelecionado"
      option-label="assunto"
      fluid
    />
    <Button
      label="Importar"
      class="w-44"
      @click="importar()"
    />
  </div>
</template>

<script setup>
import Funcionario from "@/Components/Componentes/Funcionario.vue"
import Filial from "@/Components/New/Filial.vue"
import { swalErro, swalSucesso } from "@/utils/globalFunctions"
import { ref } from "vue"

const props = defineProps(["departamentos"])
const file = ref(null)
const lista = ref([])
const departamentoSelecionado = ref(null)
const assuntoSelecionado = ref(null)

function handleFileUpload(event) {
  const selectedFile = event.target.files[0]

  if (!selectedFile) return

  const isValidType =
    selectedFile.type ===
      "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" ||
    selectedFile.name.endsWith(".xlsx")

  if (!isValidType) {
    swalErro("Por favor, selecione um arquivo .xlsx válido.")
    return
  }

  file.value = selectedFile
}

async function prepararImportacao() {
  if (!file.value) return

  const formData = new FormData()
  formData.append("arquivo", file.value)

  try {
    const response = await axios.post(
      "/solicitacoes/configuracoes/preparar-importacao",
      formData,
      {
        headers: {
          "Content-Type": "multipart/form-data"
        }
      }
    )

    response.data.forEach((r) => {
      r.data = new Date(r.data)
    })

    lista.value = response.data
    await swalSucesso("Arquivo preparado com sucesso!")
  } catch (error) {
    swalErro(error)
  }
}

async function importar() {
  // Validação: verifica se todos os campos obrigatórios estão preenchidos
  for (let i = 0; i < lista.value.length; i++) {
    const item = lista.value[i]

    if (
      !item.titulo ||
      !item.data ||
      !item.solicitante ||
      !item.filial ||
      !item.prioridade
    ) {
      swalErro(`Preencha todos os campos obrigatórios da linha ${i + 1}.`)
      return
    }
  }

  if (!departamentoSelecionado.value) {
    swalErro("", "Selecione um departamento.")
    return
  }

  if (!assuntoSelecionado.value) {
    swalErro("", "Selecione um assunto.")
    return
  }

  // Aqui segue o envio dos dados
  try {
    const payload = {
      departamento_id: departamentoSelecionado.value.nome,
      assunto_id: assuntoSelecionado.value.id,
      lista: lista.value
    }

    const response = await axios.post(
      "/solicitacoes/configuracoes/importar",
      payload
    )

    await swalSucesso("Importação realizada com sucesso!")
    lista.value = []
    file.value = null
    assuntoSelecionado.value = null
    departamentoSelecionado.value = null
  } catch (error) {
    swalErro("Erro ao importar os dados.")
  }
}
</script>
