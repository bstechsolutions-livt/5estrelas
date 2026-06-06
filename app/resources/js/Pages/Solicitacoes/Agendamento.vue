<script setup>
import BsData from "@/Components/Componentes/BsData.vue"
import BsSelectFiliais from "@/Components/Componentes/BsSelectFiliais.vue"
import Loader from "@/Components/Loader.vue"
import { swalConfirm, swalErro } from "@/utils/globalFunctions"
import { onMounted, ref, watch, computed } from "vue"
import Button from "primevue/button"
import Select from "primevue/select"
import Textarea from "primevue/textarea"
import Avatar from "primevue/avatar"

const props = defineProps({
  solicitacaoAtual: {
    type: Object,
    required: false
  },
  solicitacoes: {
    type: Array,
    required: false
  },
  agendamentoEdit: {
    required: false
  },
  edit: {
    default: false,
    type: Boolean,
    required: true
  },
  user: {
    type: Object,
    required: false
  }
})

const emit = defineEmits(["atualizar"])
const agendamento = ref({
  id: null,
  usuarioResponsavel: null,
  data: null,
  dataFim: null,
  filial:
    Array.isArray(props.solicitacoes) && props.solicitacoes.length > 0
      ? props.solicitacoes[0].filial
        ? props.solicitacoes[0].filial.codigo
        : props.solicitacoes[0].filial_id
      : props.agendamentoEdit
        ? props.agendamentoEdit.filial
        : props.user.codFilial,
  observacao: ""
})
const endFilial = ref({})
const dados = ref(null)
const loading = ref(false)
const urlMaps = ref(null)
const isFlutter = ref(typeof window.flutter_inappwebview !== "undefined")
const selectResponsavel = ref(null)

// Função para focar no filtro quando abrir o Select
function focarFiltroResponsavel() {
  setTimeout(() => {
    const filterInput = document.querySelector(
      ".p-select-overlay .p-select-filter"
    )
    if (filterInput) {
      filterInput.focus()
    }
  }, 50)
}

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES AVATAR                         ║
// ╚══════════════════════════════════════════════════════════════╝

// Função para obter iniciais (nome e sobrenome)
function obterIniciais(nome) {
  if (!nome) return "?"
  const partes = nome
    .trim()
    .split(" ")
    .filter((p) => p.length > 0)
  if (partes.length === 0) return "?"
  if (partes.length === 1) return partes[0].charAt(0).toUpperCase()
  return (
    partes[0].charAt(0) + partes[partes.length - 1].charAt(0)
  ).toUpperCase()
}

// Função para obter nome e sobrenome formatado
function obterNomeSobrenome(nome) {
  if (!nome) return "Não informado"
  const partes = nome
    .trim()
    .split(" ")
    .filter((p) => p.length > 0)
  if (partes.length === 0) return "Não informado"
  if (partes.length === 1) return partes[0]
  return `${partes[0]} ${partes[partes.length - 1]}`
}

const responsaveisComputed = computed(() => {
  if (!dados.value?.responsaveis) return []
  const responsaveis = [...dados.value.responsaveis].sort((a, b) =>
    a.nome.localeCompare(b.nome, "pt-BR", { sensitivity: "base" })
  )
  return responsaveis
})

onMounted(async () => {
  await getDados()

  await getEndFilial(
    props.solicitacoes?.[0]?.filial?.codigo ??
      props.solicitacoes?.[0]?.filial_id ??
      props.user?.codFilial ??
      null
  )
  if (props.edit) {
    await atribuiAgendamentoExistente()
  }

  if (props.solicitacaoAtual) {
    if (props.solicitacaoAtual.usuario_responsavel) {
      agendamento.value.usuarioResponsavel =
        props.solicitacaoAtual.usuario_responsavel.matricula
    }
  }
})

async function atribuiAgendamentoExistente() {
  agendamento.value.id = props.agendamentoEdit[0].id
  // Converte para número para garantir compatibilidade com o Select
  agendamento.value.usuarioResponsavel = Number(
    props.agendamentoEdit[0].mat_responsavel
  )
  agendamento.value.data = props.agendamentoEdit[0].data_agendamento
  agendamento.value.dataFim = props.agendamentoEdit[0].data_fim_agendamento
  agendamento.value.filial = props.agendamentoEdit[0].filial
  agendamento.value.observacao = props.agendamentoEdit[0].observacao
  await getEndFilial(agendamento.value.filial)
}

async function getDados() {
  var params = {
    departamento_responsavel: props.solicitacoes
      ? props.solicitacoes.departamento_responsavel
      : props.user.areaatuacao
  }

  await axios
    .post("/solicitacoes/agendamento/dados", params)
    .then((res) => {
      dados.value = res.data
    })
    .catch((err) => {
      console.error(err)
    })
}

watch(
  () => agendamento.value.filial,
  async (newVal) => {
    await getEndFilial(newVal)
  }
)

async function getEndFilial(codFilial) {
  await axios
    .get("/solicitacoes/agendamento/end-filial/" + codFilial)
    .then((res) => {
      endFilial.value = res.data
    })
    .catch((err) => {
      console.error(err)
    })
}

async function redirectMaps() {
  if (isFlutter.value) {
    const rotas = await window.flutter_inappwebview.callHandler(
      "Rotas",
      endFilial.value.link_maps
    )
  } else {
    window.open(endFilial.value.link_maps, "_blank")
  }
}

async function criarAgendamento() {
  const dataValida = validarDatas(
    agendamento.value.data,
    agendamento.value.dataFim
  )

  if (!dataValida.valido) {
    swalErro("Erro", dataValida.mensagem)
    return
  }

  const response = await swalConfirm(
    "Você tem certeza ?",
    "O responsável pelo agendamento será atribuido como responsável pela solicitação."
  )

  if (!response.isConfirmed) {
    return
  }

  loading.value = true

  var params = {
    agendamento: agendamento.value,
    solicitacoes: props.solicitacoes
  }

  await axios
    .post("/solicitacoes/agendamento/criar-agendamento", params)
    .then((res) => {
      emit("atualizar")
    })
    .catch((e) => {
      console.error(e)

      swalErro("Erro", e.response.data.message)
    })
  loading.value = false
}

function validarDatas(dataInicio) {
  if (!dataInicio) {
    return { valido: false, mensagem: "A data de início é obrigatória." }
  }

  const dataInicioFormatada = dataInicio.split(" ")[0]

  const hoje = new Date()
  const hojeFormatada = hoje.toISOString().split("T")[0]

  if (dataInicioFormatada < hojeFormatada) {
    return {
      valido: false,
      mensagem: "A data de início deve ser posterior à data de hoje."
    }
  }

  return { valido: true }
}

async function atualizarAgendamento() {
  const dataValida = validarDatas(
    agendamento.value.data,
    agendamento.value.dataFim
  )

  if (!dataValida.valido) {
    swalErro("Erro", dataValida.mensagem)
    return
  }

  const response = await swalConfirm(
    "Você tem certeza ?",
    "O responsável pelo agendamento será atribuido como responsável pela solicitação."
  )

  if (!response.isConfirmed) {
    return
  }

  loading.value = true

  var params = {
    agendamento: agendamento.value,
    rota: urlMaps.value ? urlMaps.value : null,
    solicitacoes: props.solicitacoes,
    rota: urlMaps.value ? urlMaps.value : null
  }

  await axios
    .post("/solicitacoes/agendamento/atualizar-agendamento", params)
    .then((res) => {
      emit("atualizar", res.data)
    })
    .catch((e) => {
      console.error(e)
      swalErro()
    })
  loading.value = false
}

// Verifica se o formulário está válido
const formValido = computed(() => {
  return (
    agendamento.value.usuarioResponsavel &&
    agendamento.value.filial &&
    agendamento.value.data
  )
})
</script>

<template>
  <Loader :loading="loading"></Loader>

  <div class="space-y-5">
    <!-- Dados do Agendamento -->
    <div
      v-if="dados"
      class="space-y-4"
    >
      <!-- Campos principais em grid responsivo -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Responsável -->
        <div class="flex flex-col gap-2">
          <label
            class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
          >
            <span
              class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-gradient-to-br from-cyan-400 to-cyan-600 shadow-sm"
            >
              <i class="pi pi-user text-white text-xs"></i>
            </span>
            Responsável
          </label>
          <Select
            ref="selectResponsavel"
            v-model="agendamento.usuarioResponsavel"
            :options="responsaveisComputed"
            optionLabel="nome"
            showClear
            optionValue="matricula"
            placeholder="Selecione o responsável"
            filter
            filterPlaceholder="Buscar..."
            @show="focarFiltroResponsavel"
            class="w-full"
            :pt="{
              root: {
                class: '!rounded-xl !border-gray-300 dark:!border-slate-600'
              },
              label: { class: 'dark:!text-gray-300' },
              trigger: { class: 'dark:!text-gray-300' }
            }"
          >
            <!-- Template para opção selecionada -->
            <template #value="slotProps">
              <div
                v-if="slotProps.value"
                class="flex items-center gap-2"
              >
                <div
                  v-if="
                    responsaveisComputed.find(
                      (r) => r.matricula === slotProps.value
                    )?.foto_perfil
                  "
                  class="w-7 h-7 rounded-lg overflow-hidden"
                >
                  <img
                    :src="
                      responsaveisComputed.find(
                        (r) => r.matricula === slotProps.value
                      )?.foto_perfil
                    "
                    class="w-full h-full object-cover"
                  />
                </div>
                <div
                  v-else
                  class="w-7 h-7 rounded-lg bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center text-white text-xs font-bold"
                >
                  {{
                    obterIniciais(
                      responsaveisComputed.find(
                        (r) => r.matricula === slotProps.value
                      )?.nome
                    )
                  }}
                </div>
                <span class="font-medium text-sm">
                  {{
                    obterNomeSobrenome(
                      responsaveisComputed.find(
                        (r) => r.matricula === slotProps.value
                      )?.nome
                    )
                  }}
                </span>
              </div>
              <span
                v-else
                class="text-gray-400"
              >
                {{ slotProps.placeholder }}
              </span>
            </template>

            <!-- Template para cada opção da lista -->
            <template #option="slotProps">
              <div class="flex items-center gap-3 py-1">
                <div
                  v-if="slotProps.option.foto_perfil"
                  class="w-9 h-9 rounded-xl overflow-hidden ring-2 ring-offset-1 ring-cyan-400/50"
                  v-tooltip.left="slotProps.option.nome"
                >
                  <img
                    :src="slotProps.option.foto_perfil"
                    class="w-full h-full object-cover"
                  />
                </div>
                <div
                  v-else
                  class="w-9 h-9 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-offset-1 ring-cyan-400/50"
                  v-tooltip.left="slotProps.option.nome"
                >
                  {{ obterIniciais(slotProps.option.nome) }}
                </div>
                <div class="flex flex-col">
                  <span
                    class="font-semibold text-gray-800 dark:text-white text-sm"
                  >
                    {{ obterNomeSobrenome(slotProps.option.nome) }}
                  </span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">
                    Mat. {{ slotProps.option.matricula }}
                  </span>
                </div>
              </div>
            </template>
          </Select>
        </div>

        <!-- Data/Hora Início -->
        <div class="flex flex-col gap-2">
          <label
            class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
          >
            <span
              class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-gradient-to-br from-blue-400 to-blue-600 shadow-sm"
            >
              <i class="pi pi-calendar text-white text-xs"></i>
            </span>
            Início do Atendimento
          </label>
          <BsData
            v-model="agendamento.data"
            showHours
            :show-calendar="true"
            class="w-full"
          />
        </div>

        <!-- Filial -->
        <div class="flex flex-col gap-2 sm:col-span-2 lg:col-span-1">
          <label
            class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
          >
            <span
              class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-gradient-to-br from-purple-400 to-purple-600 shadow-sm"
            >
              <i class="pi pi-building text-white text-xs"></i>
            </span>
            Filial
          </label>
          <BsSelectFiliais
            v-model="agendamento.filial"
            :disabled="props.solicitacoes && props.solicitacoes.length > 0"
            class="w-full"
          />
        </div>
      </div>
    </div>

    <!-- Card de Endereço -->
    <div
      v-if="agendamento.filial && endFilial.cidade"
      class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 overflow-hidden shadow-sm"
    >
      <!-- Header do Card -->
      <div
        class="bg-gradient-to-r from-blue-500 via-blue-600 to-indigo-600 px-4 py-3"
      >
        <div
          class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
        >
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center shadow-lg"
            >
              <i class="pi pi-map-marker text-white text-lg"></i>
            </div>
            <div>
              <h3 class="text-white font-bold text-base">Endereço da Filial</h3>
              <p class="text-white/80 text-xs">Local do atendimento</p>
            </div>
          </div>

          <!-- Botão Rota -->
          <Button
            v-if="endFilial.link_maps"
            @click="redirectMaps()"
            icon="pi pi-directions"
            label="Ver Rota"
            class="!bg-white/20 hover:!bg-white/30 !border-white/30 !text-white !text-sm !rounded-xl backdrop-blur-sm w-full sm:w-auto"
            size="small"
          />
        </div>
      </div>

      <!-- Conteúdo do Endereço -->
      <div class="p-4">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
          <!-- Cidade -->
          <div
            v-if="endFilial.cidade"
            class="flex flex-col gap-1 p-2 bg-gray-50 dark:bg-slate-700/50 rounded-lg"
          >
            <span
              class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
            >
              Cidade
            </span>
            <span class="text-sm font-bold text-gray-800 dark:text-white">
              {{ endFilial.cidade }}
            </span>
          </div>

          <!-- UF -->
          <div
            v-if="endFilial.uf"
            class="flex flex-col gap-1 p-2 bg-gray-50 dark:bg-slate-700/50 rounded-lg"
          >
            <span
              class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
            >
              UF
            </span>
            <span class="text-sm font-bold text-gray-800 dark:text-white">
              {{ endFilial.uf }}
            </span>
          </div>

          <!-- Logradouro -->
          <div
            v-if="endFilial.endereco"
            class="flex flex-col gap-1 p-2 bg-gray-50 dark:bg-slate-700/50 rounded-lg col-span-2"
          >
            <span
              class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
            >
              Logradouro
            </span>
            <span class="text-sm font-bold text-gray-800 dark:text-white">
              {{ endFilial.endereco }}
            </span>
          </div>

          <!-- Bairro -->
          <div
            v-if="endFilial.bairro"
            class="flex flex-col gap-1 p-2 bg-gray-50 dark:bg-slate-700/50 rounded-lg col-span-2 sm:col-span-1"
          >
            <span
              class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
            >
              Bairro
            </span>
            <span
              class="text-sm font-bold text-gray-800 dark:text-white truncate"
              :title="endFilial.bairro"
            >
              {{ endFilial.bairro }}
            </span>
          </div>

          <!-- CEP -->
          <div
            v-if="endFilial.cep"
            class="flex flex-col gap-1 p-2 bg-gray-50 dark:bg-slate-700/50 rounded-lg"
          >
            <span
              class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
            >
              CEP
            </span>
            <span class="text-sm font-bold text-gray-800 dark:text-white">
              {{ endFilial.cep }}
            </span>
          </div>
        </div>

        <!-- Complemento separado -->
        <div
          v-if="endFilial.complemento"
          class="flex flex-col gap-1 p-2 bg-gray-50 dark:bg-slate-700/50 rounded-lg mt-3"
        >
          <span
            class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
          >
            Complemento
          </span>
          <span class="text-sm font-bold text-gray-800 dark:text-white">
            {{ endFilial.complemento }}
          </span>
        </div>
      </div>
    </div>

    <!-- Observação -->
    <div class="space-y-2">
      <label
        class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2"
      >
        <span
          class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-sm"
        >
          <i class="pi pi-file-edit text-white text-xs"></i>
        </span>
        Observação
      </label>
      <Textarea
        v-model="agendamento.observacao"
        rows="3"
        autoResize
        maxlength="4000"
        placeholder="Adicione observações sobre o agendamento..."
        class="w-full !rounded-xl !border-gray-300 dark:!border-slate-600 !bg-white dark:!bg-slate-700 dark:!text-white focus:!border-cyan-500 focus:!ring-cyan-500 !text-sm"
        :pt="{
          root: { class: '!rounded-xl' }
        }"
      />
      <small
        v-if="agendamento.observacao"
        class="text-gray-400 text-xs text-right block mt-1"
      >
        {{ agendamento.observacao.length }}/4000
      </small>
    </div>

    <!-- Botão de Ação -->
    <div
      class="flex justify-end pt-2 border-t border-gray-100 dark:border-slate-700"
    >
      <Button
        @click="props.edit ? atualizarAgendamento() : criarAgendamento()"
        :loading="loading"
        :disabled="!formValido"
        outlined
        :icon="props.edit ? 'pi pi-refresh' : 'pi pi-calendar-plus'"
        :label="props.edit ? 'Atualizar Agendamento' : 'Criar Agendamento'"
        class="!rounded-xl w-full sm:w-auto"
        :pt="{
          root: { class: '!rounded-xl' }
        }"
      />
    </div>
  </div>
</template>
