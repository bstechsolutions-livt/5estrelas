<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { onMounted, ref } from "vue"
import axios from "axios"
import Tabs from "primevue/tabs"
import TabList from "primevue/tablist"
import Tab from "primevue/tab"
import TabPanels from "primevue/tabpanels"
import TabPanel from "primevue/tabpanel"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Button from "primevue/button"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Dialog from "primevue/dialog"
import Tag from "primevue/tag"
import ToggleSwitch from "primevue/toggleswitch"
import { useToast } from "primevue/usetoast"

const toast = useToast()

const loading = ref(true)
const ccts = ref([])
const categorias = ref([])
const escalas = ref([])
const indices = ref([])

async function carregar() {
  loading.value = true
  try {
    const { data } = await axios.get("/comercial/configuracoes/dados")
    ccts.value = data.ccts || []
    categorias.value = data.categorias || []
    escalas.value = data.escalas || []
    indices.value = data.indices || []
  } catch (e) {
    toast.add({ severity: "error", summary: "Erro", detail: "Falha ao carregar dados", life: 4000 })
  } finally {
    loading.value = false
  }
}

onMounted(carregar)

function ok(msg) {
  toast.add({ severity: "success", summary: "Pronto", detail: msg, life: 3000 })
}
function fail(msg) {
  toast.add({ severity: "error", summary: "Erro", detail: msg, life: 4000 })
}

// ─── Confirmação de exclusão (dialog) ────────────────
const confirmDialog = ref(false)
const confirmTexto = ref("")
const confirmAcao = ref(null)
function pedirConfirmacao(texto, acao) {
  confirmTexto.value = texto
  confirmAcao.value = acao
  confirmDialog.value = true
}
async function confirmarExclusao() {
  const acao = confirmAcao.value
  confirmDialog.value = false
  if (acao) await acao()
  confirmAcao.value = null
}

// ─── CCT ─────────────────────────────────────────────
const cctDialog = ref(false)
const cctForm = ref({})
function novoCct() {
  cctForm.value = { id: null, nome: "", sindicato: "", uf: "", ano_base: "", ativo: true }
  cctDialog.value = true
}
function editarCct(row) {
  cctForm.value = { ...row }
  cctDialog.value = true
}
async function salvarCct() {
  try {
    if (cctForm.value.id) {
      await axios.put(`/comercial/configuracoes/ccts/${cctForm.value.id}`, cctForm.value)
    } else {
      await axios.post("/comercial/configuracoes/ccts", cctForm.value)
    }
    cctDialog.value = false
    ok("CCT salva")
    carregar()
  } catch (e) { fail("Não foi possível salvar a CCT") }
}
async function excluirCct(row) {
  pedirConfirmacao(`Excluir a CCT "${row.nome}"?`, async () => {
    try { await axios.delete(`/comercial/configuracoes/ccts/${row.id}`); ok("CCT excluída"); carregar() }
    catch (e) { fail("Não foi possível excluir") }
  })
}

// ─── Categorias ──────────────────────────────────────
const catDialog = ref(false)
const catForm = ref({})
function novaCat() {
  catForm.value = {
    id: null, nome: "", cbo: "", cct_id: null,
    salario_base: 0, periculosidade_pct: 0, intrajornada_h: 1.5, desconto_vt_pct: 6,
    va: 0, vt: 0, plano_saude: 0, fundo_social: 0, sst: 0, cna: 0, seguro_vida: 0,
    uniforme: 0, reciclagem: 0, gta: 0, cofre: 0, arma: 0, colete: 0,
    tem_arma: false, tem_moto: false, ativo: true,
  }
  catDialog.value = true
}
function editarCat(row) {
  catForm.value = { ...row }
  catDialog.value = true
}
async function salvarCat() {
  try {
    if (catForm.value.id) {
      await axios.put(`/comercial/configuracoes/categorias/${catForm.value.id}`, catForm.value)
    } else {
      await axios.post("/comercial/configuracoes/categorias", catForm.value)
    }
    catDialog.value = false
    ok("Categoria salva")
    carregar()
  } catch (e) { fail("Não foi possível salvar a categoria") }
}
async function excluirCat(row) {
  pedirConfirmacao(`Excluir a categoria "${row.nome}"?`, async () => {
    try { await axios.delete(`/comercial/configuracoes/categorias/${row.id}`); ok("Categoria excluída"); carregar() }
    catch (e) { fail("Não foi possível excluir") }
  })
}

// ─── Escalas ─────────────────────────────────────────
const escDialog = ref(false)
const escForm = ref({})
function novaEsc() {
  escForm.value = { id: null, nome: "", dias_mes: 30, horas_mes: 220, ativo: true }
  escDialog.value = true
}
function editarEsc(row) {
  escForm.value = { ...row }
  escDialog.value = true
}
async function salvarEsc() {
  try {
    if (escForm.value.id) {
      await axios.put(`/comercial/configuracoes/escalas/${escForm.value.id}`, escForm.value)
    } else {
      await axios.post("/comercial/configuracoes/escalas", escForm.value)
    }
    escDialog.value = false
    ok("Escala salva")
    carregar()
  } catch (e) { fail("Não foi possível salvar a escala") }
}
async function excluirEsc(row) {
  pedirConfirmacao(`Excluir a escala "${row.nome}"?`, async () => {
    try { await axios.delete(`/comercial/configuracoes/escalas/${row.id}`); ok("Escala excluída"); carregar() }
    catch (e) { fail("Não foi possível excluir") }
  })
}

// ─── Índices ─────────────────────────────────────────
async function salvarIndices() {
  try {
    await axios.post("/comercial/configuracoes/indices", { indices: indices.value })
    ok("Índices salvos")
  } catch (e) { fail("Não foi possível salvar os índices") }
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="p-4 md:p-6 max-w-7xl mx-auto">
      <div class="mb-5">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Comercial — Valores</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Parâmetros que alimentam a planilha de custos (IN 05): CCTs, categorias, escalas e índices.
        </p>
      </div>

      <Tabs value="0">
        <TabList>
          <Tab value="0">CCTs</Tab>
          <Tab value="1">Categorias</Tab>
          <Tab value="2">Escalas</Tab>
          <Tab value="3">Índices</Tab>
        </TabList>
        <TabPanels>
          <!-- CCTs -->
          <TabPanel value="0">
            <div class="flex justify-end mb-3">
              <Button label="Nova CCT" icon="pi pi-plus" size="small" @click="novoCct" />
            </div>
            <DataTable :value="ccts" :loading="loading" dataKey="id" paginator :rows="10" size="small" stripedRows>
              <Column field="nome" header="Nome" sortable />
              <Column field="sindicato" header="Sindicato" />
              <Column field="uf" header="UF" />
              <Column field="ano_base" header="Ano-base" />
              <Column header="Status">
                <template #body="{ data }">
                  <Tag :value="data.ativo ? 'Ativa' : 'Inativa'" :severity="data.ativo ? 'success' : 'secondary'" />
                </template>
              </Column>
              <Column header="Ações" style="width:120px">
                <template #body="{ data }">
                  <Button icon="pi pi-pencil" text rounded size="small" @click="editarCct(data)" />
                  <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="excluirCct(data)" />
                </template>
              </Column>
            </DataTable>
          </TabPanel>

          <!-- Categorias -->
          <TabPanel value="1">
            <div class="flex justify-end mb-3">
              <Button label="Nova Categoria" icon="pi pi-plus" size="small" @click="novaCat" />
            </div>
            <DataTable :value="categorias" :loading="loading" dataKey="id" paginator :rows="10" size="small" stripedRows>
              <Column field="nome" header="Categoria" sortable />
              <Column field="cbo" header="CBO" />
              <Column header="Salário base">
                <template #body="{ data }">R$ {{ Number(data.salario_base).toFixed(2) }}</template>
              </Column>
              <Column header="Peric. %">
                <template #body="{ data }">{{ Number(data.periculosidade_pct).toFixed(0) }}%</template>
              </Column>
              <Column header="Status">
                <template #body="{ data }">
                  <Tag :value="data.ativo ? 'Ativa' : 'Inativa'" :severity="data.ativo ? 'success' : 'secondary'" />
                </template>
              </Column>
              <Column header="Ações" style="width:120px">
                <template #body="{ data }">
                  <Button icon="pi pi-pencil" text rounded size="small" @click="editarCat(data)" />
                  <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="excluirCat(data)" />
                </template>
              </Column>
            </DataTable>
          </TabPanel>

          <!-- Escalas -->
          <TabPanel value="2">
            <div class="flex justify-end mb-3">
              <Button label="Nova Escala" icon="pi pi-plus" size="small" @click="novaEsc" />
            </div>
            <DataTable :value="escalas" :loading="loading" dataKey="id" size="small" stripedRows>
              <Column field="nome" header="Escala" sortable />
              <Column field="dias_mes" header="Dias/mês" />
              <Column field="horas_mes" header="Horas/mês" />
              <Column header="Status">
                <template #body="{ data }">
                  <Tag :value="data.ativo ? 'Ativa' : 'Inativa'" :severity="data.ativo ? 'success' : 'secondary'" />
                </template>
              </Column>
              <Column header="Ações" style="width:120px">
                <template #body="{ data }">
                  <Button icon="pi pi-pencil" text rounded size="small" @click="editarEsc(data)" />
                  <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="excluirEsc(data)" />
                </template>
              </Column>
            </DataTable>
          </TabPanel>

          <!-- Índices -->
          <TabPanel value="3">
            <div class="flex justify-between items-center mb-3">
              <p class="text-sm text-gray-500">Percentuais globais aplicados no cálculo.</p>
              <Button label="Salvar Índices" icon="pi pi-save" size="small" @click="salvarIndices" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <div v-for="idx in indices" :key="idx.chave" class="border border-gray-200 dark:border-slate-700 rounded-lg p-3">
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">
                  {{ idx.descricao || idx.chave }}
                </label>
                <InputNumber v-model="idx.valor" :minFractionDigits="2" :maxFractionDigits="4" suffix=" %" class="w-full" />
              </div>
            </div>
          </TabPanel>
        </TabPanels>
      </Tabs>
    </div>

    <!-- Dialog CCT -->
    <Dialog v-model:visible="cctDialog" modal header="CCT" :style="{ width: '480px' }">
      <div class="space-y-3">
        <div><label class="text-sm font-medium">Nome *</label><InputText v-model="cctForm.nome" class="w-full" /></div>
        <div><label class="text-sm font-medium">Sindicato</label><InputText v-model="cctForm.sindicato" class="w-full" /></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-sm font-medium">UF</label><InputText v-model="cctForm.uf" maxlength="2" class="w-full" /></div>
          <div><label class="text-sm font-medium">Ano-base</label><InputText v-model="cctForm.ano_base" class="w-full" /></div>
        </div>
        <div class="flex items-center gap-2"><ToggleSwitch v-model="cctForm.ativo" /><span class="text-sm">Ativa</span></div>
      </div>
      <template #footer>
        <Button label="Cancelar" text @click="cctDialog = false" />
        <Button label="Salvar" icon="pi pi-check" @click="salvarCct" />
      </template>
    </Dialog>

    <!-- Dialog Categoria -->
    <Dialog v-model:visible="catDialog" modal header="Categoria Profissional" :style="{ width: '640px' }">
      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2"><label class="text-sm font-medium">Nome *</label><InputText v-model="catForm.nome" class="w-full" /></div>
        <div><label class="text-sm font-medium">CBO</label><InputText v-model="catForm.cbo" class="w-full" /></div>
        <div><label class="text-sm font-medium">Salário base (R$)</label><InputNumber v-model="catForm.salario_base" :minFractionDigits="2" class="w-full" /></div>
        <div><label class="text-sm font-medium">Periculosidade (%)</label><InputNumber v-model="catForm.periculosidade_pct" class="w-full" /></div>
        <div><label class="text-sm font-medium">Intrajornada (h)</label><InputNumber v-model="catForm.intrajornada_h" :minFractionDigits="1" class="w-full" /></div>
        <div><label class="text-sm font-medium">Desconto VT (%)</label><InputNumber v-model="catForm.desconto_vt_pct" class="w-full" /></div>
        <div><label class="text-sm font-medium">VA (R$)</label><InputNumber v-model="catForm.va" :minFractionDigits="2" class="w-full" /></div>
        <div><label class="text-sm font-medium">VT (R$/dia)</label><InputNumber v-model="catForm.vt" :minFractionDigits="2" class="w-full" /></div>
        <div><label class="text-sm font-medium">Plano de saúde (R$)</label><InputNumber v-model="catForm.plano_saude" :minFractionDigits="2" class="w-full" /></div>
        <div><label class="text-sm font-medium">Seguro de vida (R$)</label><InputNumber v-model="catForm.seguro_vida" :minFractionDigits="2" class="w-full" /></div>
        <div><label class="text-sm font-medium">Uniforme (R$)</label><InputNumber v-model="catForm.uniforme" :minFractionDigits="2" class="w-full" /></div>
        <div><label class="text-sm font-medium">Reciclagem (R$)</label><InputNumber v-model="catForm.reciclagem" :minFractionDigits="2" class="w-full" /></div>
        <div class="flex items-center gap-2"><ToggleSwitch v-model="catForm.tem_arma" /><span class="text-sm">Tem arma</span></div>
        <div class="flex items-center gap-2"><ToggleSwitch v-model="catForm.ativo" /><span class="text-sm">Ativa</span></div>
      </div>
      <template #footer>
        <Button label="Cancelar" text @click="catDialog = false" />
        <Button label="Salvar" icon="pi pi-check" @click="salvarCat" />
      </template>
    </Dialog>

    <!-- Dialog Escala -->
    <Dialog v-model:visible="escDialog" modal header="Escala" :style="{ width: '420px' }">
      <div class="space-y-3">
        <div><label class="text-sm font-medium">Nome *</label><InputText v-model="escForm.nome" class="w-full" /></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-sm font-medium">Dias/mês</label><InputNumber v-model="escForm.dias_mes" :minFractionDigits="0" class="w-full" /></div>
          <div><label class="text-sm font-medium">Horas/mês</label><InputNumber v-model="escForm.horas_mes" :minFractionDigits="0" class="w-full" /></div>
        </div>
        <div class="flex items-center gap-2"><ToggleSwitch v-model="escForm.ativo" /><span class="text-sm">Ativa</span></div>
      </div>
      <template #footer>
        <Button label="Cancelar" text @click="escDialog = false" />
        <Button label="Salvar" icon="pi pi-check" @click="salvarEsc" />
      </template>
    </Dialog>

    <!-- Dialog Confirmar Exclusão -->
    <Dialog v-model:visible="confirmDialog" modal header="Confirmar Exclusão" :style="{ width: '420px' }">
      <div class="flex items-start gap-3">
        <i class="pi pi-exclamation-triangle text-3xl text-amber-500"></i>
        <p class="text-gray-700 dark:text-gray-200 mt-1">{{ confirmTexto }}</p>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" outlined @click="confirmDialog = false" />
        <Button label="Excluir" icon="pi pi-trash" severity="danger" @click="confirmarExclusao" />
      </template>
    </Dialog>
  </AuthenticatedLayout>
</template>
