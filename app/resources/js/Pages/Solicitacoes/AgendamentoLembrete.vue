<script setup>
import BsData from "@/Components/Componentes/BsData.vue"
import { swalErro, toastSuccess } from "@/utils/globalFunctions"
import { ref, computed, watch, onMounted } from "vue"
import Button from "primevue/button"
import Textarea from "primevue/textarea"
import InputText from "primevue/inputtext"

const props = defineProps({
  solicitacao: {
    type: Object,
    required: true
  },
  lembreteEdicao: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(["atualizar", "fechar"])

const lembrete = ref({
  id: null,
  data: null,
  hora: null,
  observacao: ""
})
const loading = ref(false)

// Modo edição
const modoEdicao = computed(() => !!props.lembreteEdicao)

// Carregar dados para edição
onMounted(() => {
  if (props.lembreteEdicao) {
    carregarDadosEdicao()
  }
})

watch(
  () => props.lembreteEdicao,
  (newVal) => {
    if (newVal) {
      carregarDadosEdicao()
    }
  },
  { immediate: true }
)

function carregarDadosEdicao() {
  if (props.lembreteEdicao) {
    lembrete.value.id = props.lembreteEdicao.id

    // Formatar data para o componente BsData (YYYY-MM-DD)
    if (props.lembreteEdicao.data_agendamento) {
      const dataHora = props.lembreteEdicao.data_agendamento.split(" ")
      // O BsData usa input type="date" que espera YYYY-MM-DD
      lembrete.value.data = dataHora[0]

      // Hora - só carrega se não for a hora padrão 08:00
      if (dataHora[1]) {
        const hora = dataHora[1].substring(0, 5)
        // Se a hora for 08:00 (padrão), deixa vazio para o usuário escolher
        lembrete.value.hora = hora !== "08:00" ? hora : null
      }
    }

    lembrete.value.observacao = props.lembreteEdicao.observacao || ""
  }
}

async function salvarLembrete() {
  if (!lembrete.value.data) {
    swalErro("Erro", "A data é obrigatória.")
    return
  }

  // Valida se a data é futura (só para novos lembretes)
  if (!modoEdicao.value) {
    // Pega a data selecionada no formato YYYY-MM-DD
    const dataSelecionada = lembrete.value.data.split(" ")[0]
    const [ano, mes, dia] = dataSelecionada.split("-").map(Number)

    // Data selecionada (usando horário local)
    const dataLembrete = new Date(ano, mes - 1, dia)
    dataLembrete.setHours(0, 0, 0, 0)

    // Hoje (início do dia no horário local)
    const hoje = new Date()
    hoje.setHours(0, 0, 0, 0)

    if (dataLembrete < hoje) {
      swalErro("Erro", "A data deve ser igual ou posterior a hoje.")
      return
    }
  }

  loading.value = true

  const params = {
    solicitacao_id: props.solicitacao.id,
    data: lembrete.value.data,
    hora: lembrete.value.hora,
    observacao: lembrete.value.observacao
  }

  // Se for edição, adiciona o ID
  if (modoEdicao.value) {
    params.lembrete_id = lembrete.value.id
  }

  const url = modoEdicao.value
    ? "/solicitacoes/agendamento/editar-lembrete"
    : "/solicitacoes/agendamento/criar-lembrete"

  await axios
    .post(url, params)
    .then((res) => {
      const msg = modoEdicao.value ? "Lembrete atualizado!" : "Lembrete criado!"
      toastSuccess(msg)
      emit("atualizar")
    })
    .catch((e) => {
      console.error(e)
      swalErro("Erro", e.response?.data?.message || "Erro ao salvar lembrete.")
    })

  loading.value = false
}

// Verifica se o formulário está válido
const formValido = computed(() => {
  return lembrete.value.data
})
</script>

<template>
  <div class="space-y-5">
    <!-- Info do Ticket -->
    <div
      class="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-xl p-4 border border-amber-200 dark:border-amber-800"
    >
      <div class="flex items-start gap-3">
        <div
          class="w-10 h-10 shrink-0 rounded-xl bg-amber-500 flex items-center justify-center shadow"
        >
          <i class="pi pi-bookmark text-white"></i>
        </div>
        <div class="min-w-0">
          <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">
            Ticket #{{ solicitacao.id }}
          </p>
          <p
            class="text-xs text-amber-600 dark:text-amber-400 truncate"
            :title="solicitacao.titulo"
          >
            {{ solicitacao.titulo }}
          </p>
        </div>
      </div>
    </div>

    <!-- Explicação -->
    <div
      class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 border border-blue-200 dark:border-blue-800"
    >
      <div class="flex items-start gap-3">
        <i class="pi pi-info-circle text-blue-500 text-lg mt-0.5"></i>
        <div class="text-sm text-blue-700 dark:text-blue-300">
          <p class="font-medium mb-1">O que é um lembrete?</p>
          <ul
            class="list-disc list-inside text-xs space-y-1 text-blue-600 dark:text-blue-400"
          >
            <li>Marca uma data para atender esta ticket</li>
            <li>Aparece no calendário de agendamentos</li>
            <li>Ao clicar, abre a ticket para atendimento</li>
            <li>Finaliza automaticamente quando a ticket é resolvida</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Campos do Formulário -->
    <div class="space-y-4">
      <!-- Data -->
      <div class="flex flex-col gap-2">
        <label
          class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
        >
          <span
            class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-gradient-to-br from-amber-400 to-amber-600 shadow-sm"
          >
            <i class="pi pi-calendar text-white text-xs"></i>
          </span>
          Data do Lembrete
          <span class="text-red-500">*</span>
        </label>
        <BsData
          v-model="lembrete.data"
          :show-calendar="true"
          class="w-full"
          placeholder="Selecione a data"
        />
      </div>

      <!-- Hora (Opcional) -->
      <div class="flex flex-col gap-2">
        <label
          class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
        >
          <span
            class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 shadow-sm"
          >
            <i class="pi pi-clock text-white text-xs"></i>
          </span>
          Horário
          <span class="text-xs text-gray-400 font-normal">(opcional)</span>
        </label>
        <InputText
          v-model="lembrete.hora"
          type="time"
          class="w-full !rounded-xl !border-gray-300 dark:!border-slate-600"
          placeholder="HH:MM"
        />
      </div>

      <!-- Observação -->
      <div class="flex flex-col gap-2">
        <label
          class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
        >
          <span
            class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-sm"
          >
            <i class="pi pi-file-edit text-white text-xs"></i>
          </span>
          Observação
          <span class="text-xs text-gray-400 font-normal">(opcional)</span>
        </label>
        <Textarea
          v-model="lembrete.observacao"
          rows="3"
          autoResize
          placeholder="Ex: Aguardando resposta do cliente..."
          class="w-full !rounded-xl !border-gray-300 dark:!border-slate-600 !bg-white dark:!bg-slate-700 dark:!text-white focus:!border-amber-500 focus:!ring-amber-500 !text-sm"
        />
      </div>
    </div>

    <!-- Botões -->
    <div
      class="flex flex-col sm:flex-row justify-end gap-2 pt-4 border-t border-gray-100 dark:border-slate-700"
    >
      <Button
        @click="emit('fechar')"
        label="Cancelar"
        severity="secondary"
        text
        class="!rounded-xl w-full sm:w-auto"
      />
      <Button
        @click="salvarLembrete"
        :loading="loading"
        :disabled="!formValido"
        :icon="modoEdicao ? 'pi pi-save' : 'pi pi-bookmark'"
        :label="modoEdicao ? 'Salvar Alterações' : 'Criar Lembrete'"
        class="!bg-gradient-to-r !from-amber-500 !to-orange-500 !border-0 !rounded-xl w-full sm:w-auto"
      />
    </div>
  </div>
</template>
