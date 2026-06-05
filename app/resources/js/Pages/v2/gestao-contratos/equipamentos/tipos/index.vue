<script setup>
// ╔══════════════════════════════════════════════════════════════╗
// ║                         Importação                           ║
// ╚══════════════════════════════════════════════════════════════╝
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import * as layoutJs from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.js"
import { onMounted, ref } from "vue"
import * as EquipJs from "../../gestao-equipamentos.js"
import Panel from "primevue/panel"
import Button from "primevue/button"
import InputText from "primevue/inputtext"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Tag from "primevue/tag"
import Dialog from "primevue/dialog"
import ToggleSwitch from "primevue/toggleswitch"
import { useTour } from "@/composables/useTour"
import "@/../css/tour.css"
import Swal from "sweetalert2"

// ╔══════════════════════════════════════════════════════════════╗
// ║                       ESTADO LOCAL                           ║
// ╚══════════════════════════════════════════════════════════════╝
const loadingInicial = ref(true)
const showDialog = ref(false)
const showConfirmDelete = ref(false)
const tipoParaExcluir = ref(null)
const erroExclusao = ref("")

const formTipo = ref({
  id: null,
  nome: "",
  descricao: "",
  ativo: true
})

const isEditTipo = ref(false)

// ╔══════════════════════════════════════════════════════════════╗
// ║                       TOUR GUIADO                            ║
// ╚══════════════════════════════════════════════════════════════╝
const { startTour, autoStart } = useTour("gestao-equipamentos-tipos")

const tourSteps = [
  {
    popover: {
      title: "Tipos de Equipamento",
      description: `
        Bem-vindo ao gerenciamento de <strong>Tipos de Equipamento</strong>!<br><br>
        Aqui você administra as categorias disponíveis, como:<br>
        • <strong>Extintor</strong>, Mangueira, Hidrante<br>
        • Sprinkler, Alarme e outros
      `,
      side: "over",
      align: "center"
    }
  },
  {
    element: "#tour-tipos-tabela",
    popover: {
      title: "Tabela de Tipos",
      description: `
        Lista todos os tipos cadastrados com:<br>
        • <strong>Nome</strong> da categoria<br>
        • <strong>Descrição</strong> detalhada<br>
        • <strong>Status</strong> ativo/inativo<br><br>
        Use os botões de ação para editar ou excluir.
      `,
      side: "bottom",
      align: "center"
    }
  },
  {
    element: "#tour-tipos-btn-novo",
    popover: {
      title: "Novo Tipo",
      description: `
        Clique aqui para <strong>criar uma nova categoria</strong> de equipamento.<br><br>
        Informe o nome, descrição e se o tipo estará ativo.
      `,
      side: "left",
      align: "center"
    }
  },
  {
    popover: {
      title: "Regras Importantes",
      description: `
        Algumas regras sobre tipos de equipamento:<br><br>
        • Tipos <strong>inativos</strong> não aparecem no cadastro de equipamentos<br>
        • Tipos com <strong>equipamentos vinculados</strong> não podem ser excluídos<br>
        • Você pode reativar um tipo inativo a qualquer momento
      `,
      side: "over",
      align: "center"
    }
  },
  {
    element: "#tour-btn-ajuda",
    popover: {
      title: "Precisa de ajuda?",
      description: `
        Sempre que tiver dúvidas, clique neste botão <strong>(?)</strong> para iniciar o tour guiado novamente.
      `,
      side: "bottom",
      align: "end"
    }
  }
]

// ╔══════════════════════════════════════════════════════════════╗
// ║                       FUNÇÕES                                ║
// ╚══════════════════════════════════════════════════════════════╝
onMounted(async () => {
  layoutJs.setPaginaNova(true)
  await EquipJs.getTiposEquipamento()
  loadingInicial.value = false

  // Tour Guiado
  setTimeout(() => {
    autoStart(tourSteps, Swal.fire.bind(Swal), 800)
  }, 500)
})

function novoTipo() {
  formTipo.value = { id: null, nome: "", descricao: "", ativo: true }
  isEditTipo.value = false
  showDialog.value = true
}

function editarTipo(tipo) {
  formTipo.value = {
    id: tipo.id,
    nome: tipo.nome,
    descricao: tipo.descricao || "",
    ativo: tipo.ativo === 1 || tipo.ativo === true
  }
  isEditTipo.value = true
  showDialog.value = true
}

async function salvarTipo() {
  try {
    const dados = {
      ...formTipo.value,
      ativo: formTipo.value.ativo ? 1 : 0
    }
    await EquipJs.salvarTipoEquipamento(dados)
    showDialog.value = false
  } catch (e) { /* toast handled in composable */ }
}

function confirmarExclusao(tipo) {
  tipoParaExcluir.value = tipo
  erroExclusao.value = ""
  showConfirmDelete.value = true
}

async function excluirTipo() {
  if (!tipoParaExcluir.value) return
  try {
    await EquipJs.excluirTipoEquipamento(tipoParaExcluir.value.id)
    showConfirmDelete.value = false
    tipoParaExcluir.value = null
    erroExclusao.value = ""
  } catch (error) {
    erroExclusao.value = error.response?.data?.mensagem || "Erro ao excluir tipo de equipamento"
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <!-- Breadcrumb -->
    <div class="w-full flex flex-wrap items-center bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-xl mb-4 sm:mb-6 border border-gray-200 dark:border-slate-700">
      <div class="flex flex-wrap items-center gap-1 sm:gap-2 text-sm sm:text-base text-gray-600 dark:text-gray-300 font-medium w-full">
        <div class="flex items-center gap-1 sm:gap-2">
          <i class="pi pi-home"></i>
          <span>Home</span>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <a href="/pagina/gestao-contratos" class="hover:text-purple-600 dark:hover:text-purple-400">Gestão de Contratos</a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <a href="/pagina/gestao-contratos/equipamentos" class="hover:text-purple-600 dark:hover:text-purple-400">Equipamentos</a>
          <span class="mx-1 sm:mx-2 text-gray-400 dark:text-gray-500">/</span>
          <span class="text-gray-950 dark:text-white font-bold">Tipos de Equipamento</span>
        </div>
      </div>
    </div>

    <!-- Cabeçalho -->
    <div class="space-y-2 mb-6 mt-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3">
              <div class="w-1 h-8 bg-gradient-to-b from-purple-500 to-purple-700 rounded-full"></div>
              Tipos de Equipamento
            </h2>
          </div>
          <span class="block text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-bold pl-4 mt-1">
            Gerencie as categorias de equipamentos disponíveis.
          </span>
        </div>
        <div class="flex gap-2">
          <Button id="tour-btn-ajuda" icon="pi pi-question-circle" severity="secondary" text size="small" @click="startTour(tourSteps)" v-tooltip.top="'Tour Guiado'" />
        </div>
      </div>
    </div>

    <!-- Tabela -->
    <div id="tour-tipos-tabela" class="bg-white dark:bg-slate-800 rounded-3xl p-4 sm:p-6 relative overflow-hidden">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3 flex-1 min-w-0">
          <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-purple-200 dark:bg-purple-900/30 shadow-lg flex-shrink-0">
            <i class="pi pi-tags !text-xl text-purple-700 dark:text-purple-400"></i>
          </span>
          <div>
            <h2 class="text-xl sm:text-xl md:text-2xl font-extrabold text-black-800 dark:text-white drop-shadow truncate">
              Tipos
              <span class="text-base font-normal text-gray-500 dark:text-gray-400 ml-2">
                ({{ EquipJs.tiposEquipamento.value.length }} registro{{ EquipJs.tiposEquipamento.value.length !== 1 ? "s" : "" }})
              </span>
            </h2>
            <div class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 font-medium mt-1">
              Categorias de equipamentos cadastradas
            </div>
          </div>
        </div>
        <Button id="tour-tipos-btn-novo" label="Novo Tipo" icon="pi pi-plus" severity="help" outlined @click="novoTipo" />
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm">
        <DataTable
          :value="EquipJs.tiposEquipamento.value"
          :loading="loadingInicial"
          stripedRows
          showGridlines
          class="min-w-full text-sm"
          rowHover
        >
          <template #loading>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-full shadow-md text-sm">
              <i class="pi pi-spinner pi-spin text-xs"></i>
              <span class="font-medium">Carregando...</span>
            </div>
          </template>

          <template #empty>
            <div class="py-12 text-center">
              <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-slate-700 mb-4">
                <i class="pi pi-inbox text-3xl text-gray-400 dark:text-gray-500"></i>
              </span>
              <p class="text-gray-500 dark:text-gray-400 font-medium">Nenhum tipo cadastrado</p>
            </div>
          </template>

          <Column field="nome" header="Nome" sortable style="min-width: 200px">
            <template #body="{ data }">
              <span class="font-medium text-gray-900 dark:text-white">{{ data.nome }}</span>
            </template>
          </Column>

          <Column field="descricao" header="Descrição" sortable style="min-width: 300px">
            <template #body="{ data }">
              <span class="text-sm text-gray-700 dark:text-gray-300">{{ data.descricao || "-" }}</span>
            </template>
          </Column>

          <Column field="ativo" header="Ativo" sortable style="min-width: 100px">
            <template #body="{ data }">
              <Tag
                :value="data.ativo === 1 || data.ativo === true ? 'Ativo' : 'Inativo'"
                :severity="data.ativo === 1 || data.ativo === true ? 'success' : 'danger'"
                class="font-medium"
              />
            </template>
          </Column>

          <Column header="Ações" style="min-width: 120px" :exportable="false">
            <template #body="{ data }">
              <div class="flex items-center justify-center gap-1">
                <Button icon="pi pi-pencil" severity="help" text rounded @click="editarTipo(data)" v-tooltip.top="'Editar'" />
                <Button icon="pi pi-trash" severity="danger" text rounded @click="confirmarExclusao(data)" v-tooltip.top="'Excluir'" />
              </div>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- Dialog Criar/Editar Tipo -->
    <Dialog
      v-model:visible="showDialog"
      modal
      :header="isEditTipo ? 'Editar Tipo de Equipamento' : 'Novo Tipo de Equipamento'"
      class="w-full max-w-lg"
    >
      <div class="space-y-4">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Nome *</label>
          <InputText v-model="formTipo.nome" placeholder="Ex: Extintor, Mangueira, Hidrante" class="w-full" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Descrição</label>
          <InputText v-model="formTipo.descricao" placeholder="Descrição detalhada do tipo" class="w-full" />
        </div>
        <div class="flex items-center gap-3">
          <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Ativo</label>
          <ToggleSwitch v-model="formTipo.ativo" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" outlined @click="showDialog = false" />
        <Button
          :label="isEditTipo ? 'Atualizar' : 'Criar'"
          severity="help"
          outlined
          @click="salvarTipo"
          :loading="EquipJs.loading.value"
        />
      </template>
    </Dialog>

    <!-- Dialog Confirmar Exclusão -->
    <Dialog v-model:visible="showConfirmDelete" modal header="Confirmar Exclusão" class="w-full max-w-sm">
      <div class="text-center">
        <i class="pi pi-exclamation-triangle text-5xl text-yellow-500 mb-4"></i>
        <p class="text-gray-600 dark:text-gray-400 mb-2">
          Tem certeza que deseja excluir o tipo
          <strong class="text-gray-900 dark:text-white">{{ tipoParaExcluir?.nome }}</strong>?
        </p>
        <p v-if="erroExclusao" class="text-red-500 text-sm mt-2 p-2 bg-red-50 dark:bg-red-900/20 rounded">
          <i class="pi pi-times-circle mr-1"></i> {{ erroExclusao }}
        </p>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" outlined @click="showConfirmDelete = false" />
        <Button label="Excluir" severity="danger" @click="excluirTipo" :loading="EquipJs.loading.value" />
      </template>
    </Dialog>
  </AuthenticatedLayout>
</template>
