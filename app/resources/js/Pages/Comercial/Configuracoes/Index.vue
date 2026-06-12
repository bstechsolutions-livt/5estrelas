<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { onMounted, ref, computed } from "vue"
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
import Select from "primevue/select"
import Tag from "primevue/tag"
import ToggleSwitch from "primevue/toggleswitch"
import { useToast } from "primevue/usetoast"

const toast = useToast()

const loading = ref(true)
const ccts = ref([])
const categorias = ref([])
const escalas = ref([])
const indices = ref([])
const encargos = ref([])
const insumos = ref([])

async function carregar() {
  loading.value = true
  try {
    const { data } = await axios.get("/comercial/configuracoes/dados")
    ccts.value = data.ccts || []
    categorias.value = data.categorias || []
    escalas.value = data.escalas || []
    indices.value = data.indices || []
    encargos.value = data.encargos || []
    insumos.value = data.insumos || []
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
const servicoOpts = [
  { label: "Vigilância", value: "vigilancia" },
  { label: "Portaria", value: "portaria" },
  { label: "Limpeza", value: "limpeza" },
  { label: "Bombeiro Civil", value: "bombeiro" },
]
function novoCct() {
  cctForm.value = {
    id: null, nome: "", titulo: "", servico: "vigilancia", sindicato: "", uf: "", ano_base: "2026", ativo: true,
    salario_base: 0, periculosidade_pct: 0, adicional_noturno_pct: 0, intrajornada_h: 1.5, desconto_vt_pct: 6,
    horas_mes: 220, dias_mes: 30,
    va: 0, vt: 0, plano_saude: 0, fundo_social: 0, sst: 0, cna: 0, seguro_vida: 0,
    uniforme: 0, reciclagem: 0, gta: 0, cofre: 0, arma: 0, colete: 0,
  }
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
  escForm.value = { id: null, nome: "", dias_mes: 30, horas_mes: 220, qtd_diurno: 0, qtd_noturno: 0, func_por_posto: 1, tem_an: false, jornada: "", ativo: true }
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

// ─── Encargos (A/B/C/D) ──────────────────────────────
const GRUPOS_ENC = {
  A: "Grupo A — Encargos Sociais e Trabalhistas",
  B: "Grupo B — 13º, Férias e Afastamentos",
  C: "Grupo C — Provisão para Rescisão",
  D: "Grupo D — Incidência (A sobre B e C)",
}
function encargosDoGrupo(g) {
  return encargos.value.filter((e) => e.grupo === g)
}
function subtotalGrupo(g) {
  return encargosDoGrupo(g).reduce((s, e) => s + Number(e.percentual || 0), 0)
}
const totalEncargos = computed(() =>
  encargos.value.reduce((s, e) => s + Number(e.percentual || 0), 0)
)
async function salvarEncargos() {
  try {
    await axios.post("/comercial/configuracoes/encargos", { encargos: encargos.value })
    ok("Encargos salvos")
    carregar()
  } catch (e) { fail("Não foi possível salvar os encargos") }
}

// ─── Insumos (global) ────────────────────────────────
async function salvarInsumos() {
  try {
    await axios.post("/comercial/configuracoes/insumos", { insumos: insumos.value })
    ok("Insumos salvos")
  } catch (e) { fail("Não foi possível salvar os insumos") }
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
          <Tab value="4">Encargos</Tab>
          <Tab value="5">Insumos</Tab>
        </TabList>
        <TabPanels>
          <!-- CCTs -->
          <TabPanel value="0">
            <div class="flex justify-end mb-3">
              <Button label="Nova CCT" icon="pi pi-plus" size="small" @click="novoCct" />
            </div>
            <DataTable :value="ccts" :loading="loading" dataKey="id" paginator :rows="10" size="small" stripedRows>
              <Column field="titulo" header="CCT" sortable />
              <Column field="servico" header="Serviço" sortable />
              <Column field="uf" header="UF" sortable />
              <Column field="sindicato" header="Sindicato" />
              <Column header="Salário base">
                <template #body="{ data }">R$ {{ Number(data.salario_base).toFixed(2) }}</template>
              </Column>
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
                <InputNumber v-model="idx.valor" :minFractionDigits="2" :maxFractionDigits="4" suffix=" %" fluid />
              </div>
            </div>
          </TabPanel>

          <!-- Encargos (A/B/C/D) -->
          <TabPanel value="4">
            <div class="flex justify-between items-center mb-3">
              <p class="text-sm text-gray-500">
                Detalhamento dos encargos sociais (IN 05). Total geral:
                <strong class="text-amber-600">{{ totalEncargos.toFixed(2) }}%</strong>
              </p>
              <Button label="Salvar Encargos" icon="pi pi-save" size="small" @click="salvarEncargos" />
            </div>
            <div v-for="g in ['A', 'B', 'C', 'D']" :key="g" class="mb-5">
              <div class="flex justify-between items-center bg-gray-50 dark:bg-slate-800 px-3 py-2 rounded-t-lg border border-gray-200 dark:border-slate-700">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ GRUPOS_ENC[g] }}</span>
                <span class="text-sm font-bold text-amber-600">{{ subtotalGrupo(g).toFixed(2) }}%</span>
              </div>
              <div class="border-x border-b border-gray-200 dark:border-slate-700 rounded-b-lg divide-y divide-gray-100 dark:divide-slate-700">
                <div v-for="e in encargosDoGrupo(g)" :key="e.id" class="flex items-center justify-between gap-3 px-3 py-2">
                  <span class="text-sm text-gray-600 dark:text-gray-300 flex-1">{{ e.label }}</span>
                  <InputNumber v-model="e.percentual" :minFractionDigits="2" :maxFractionDigits="2" suffix=" %" :inputStyle="{ width: '120px', textAlign: 'right' }" />
                </div>
              </div>
            </div>
          </TabPanel>

          <!-- Insumos (global) -->
          <TabPanel value="5">
            <div class="flex justify-between items-center mb-3">
              <p class="text-sm text-gray-500">Valores unitários de insumos (uniforme, EPI, armamento, etc.).</p>
              <Button label="Salvar Insumos" icon="pi pi-save" size="small" @click="salvarInsumos" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <div v-for="ins in insumos" :key="ins.id" class="border border-gray-200 dark:border-slate-700 rounded-lg p-3">
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">{{ ins.label }}</label>
                <InputNumber v-model="ins.valor" :minFractionDigits="2" mode="currency" currency="BRL" locale="pt-BR" fluid />
              </div>
            </div>
          </TabPanel>
        </TabPanels>
      </Tabs>
    </div>

    <!-- Dialog CCT -->
    <Dialog v-model:visible="cctDialog" modal header="CCT" :style="{ width: '640px' }">
      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2"><label class="text-sm font-medium">Título / Nome *</label><InputText v-model="cctForm.titulo" class="w-full" @input="cctForm.nome = cctForm.titulo" /></div>
        <div><label class="text-sm font-medium">Serviço</label><Select v-model="cctForm.servico" :options="servicoOpts" optionLabel="label" optionValue="value" class="w-full" /></div>
        <div><label class="text-sm font-medium">UF</label><InputText v-model="cctForm.uf" maxlength="2" class="w-full" /></div>
        <div class="col-span-2"><label class="text-sm font-medium">Sindicato</label><InputText v-model="cctForm.sindicato" class="w-full" /></div>
        <div><label class="text-sm font-medium">Ano-base</label><InputText v-model="cctForm.ano_base" class="w-full" /></div>
        <div><label class="text-sm font-medium">Salário base (R$)</label><InputNumber v-model="cctForm.salario_base" :minFractionDigits="2" fluid /></div>
        <div><label class="text-sm font-medium">Periculosidade (%)</label><InputNumber v-model="cctForm.periculosidade_pct" fluid /></div>
        <div><label class="text-sm font-medium">Ad. noturno (%)</label><InputNumber v-model="cctForm.adicional_noturno_pct" fluid /></div>
        <div><label class="text-sm font-medium">Horas/mês</label><InputNumber v-model="cctForm.horas_mes" fluid /></div>
        <div><label class="text-sm font-medium">Dias/mês</label><InputNumber v-model="cctForm.dias_mes" :minFractionDigits="1" fluid /></div>
        <div><label class="text-sm font-medium">VA (R$)</label><InputNumber v-model="cctForm.va" :minFractionDigits="2" fluid /></div>
        <div><label class="text-sm font-medium">VT (R$/dia)</label><InputNumber v-model="cctForm.vt" :minFractionDigits="2" fluid /></div>
        <div><label class="text-sm font-medium">Plano de saúde (R$)</label><InputNumber v-model="cctForm.plano_saude" :minFractionDigits="2" fluid /></div>
        <div><label class="text-sm font-medium">Seguro de vida (R$)</label><InputNumber v-model="cctForm.seguro_vida" :minFractionDigits="2" fluid /></div>
        <div><label class="text-sm font-medium">Uniforme (R$)</label><InputNumber v-model="cctForm.uniforme" :minFractionDigits="2" fluid /></div>
        <div><label class="text-sm font-medium">Reciclagem (R$)</label><InputNumber v-model="cctForm.reciclagem" :minFractionDigits="2" fluid /></div>
        <div class="col-span-2 flex items-center gap-2"><ToggleSwitch v-model="cctForm.ativo" /><span class="text-sm">Ativa</span></div>
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
        <div><label class="text-sm font-medium">Salário base (R$)</label><InputNumber v-model="catForm.salario_base" :minFractionDigits="2" fluid /></div>
        <div><label class="text-sm font-medium">Periculosidade (%)</label><InputNumber v-model="catForm.periculosidade_pct" fluid /></div>
        <div><label class="text-sm font-medium">Intrajornada (h)</label><InputNumber v-model="catForm.intrajornada_h" :minFractionDigits="1" fluid /></div>
        <div><label class="text-sm font-medium">Desconto VT (%)</label><InputNumber v-model="catForm.desconto_vt_pct" fluid /></div>
        <div><label class="text-sm font-medium">VA (R$)</label><InputNumber v-model="catForm.va" :minFractionDigits="2" fluid /></div>
        <div><label class="text-sm font-medium">VT (R$/dia)</label><InputNumber v-model="catForm.vt" :minFractionDigits="2" fluid /></div>
        <div><label class="text-sm font-medium">Plano de saúde (R$)</label><InputNumber v-model="catForm.plano_saude" :minFractionDigits="2" fluid /></div>
        <div><label class="text-sm font-medium">Seguro de vida (R$)</label><InputNumber v-model="catForm.seguro_vida" :minFractionDigits="2" fluid /></div>
        <div><label class="text-sm font-medium">Uniforme (R$)</label><InputNumber v-model="catForm.uniforme" :minFractionDigits="2" fluid /></div>
        <div><label class="text-sm font-medium">Reciclagem (R$)</label><InputNumber v-model="catForm.reciclagem" :minFractionDigits="2" fluid /></div>
        <div class="flex items-center gap-2"><ToggleSwitch v-model="catForm.tem_arma" /><span class="text-sm">Tem arma</span></div>
        <div class="flex items-center gap-2"><ToggleSwitch v-model="catForm.ativo" /><span class="text-sm">Ativa</span></div>
      </div>
      <template #footer>
        <Button label="Cancelar" text @click="catDialog = false" />
        <Button label="Salvar" icon="pi pi-check" @click="salvarCat" />
      </template>
    </Dialog>

    <!-- Dialog Escala -->
    <Dialog v-model:visible="escDialog" modal header="Escala" :style="{ width: '520px' }">
      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2"><label class="text-sm font-medium">Nome *</label><InputText v-model="escForm.nome" class="w-full" /></div>
        <div><label class="text-sm font-medium">Dias/mês</label><InputNumber v-model="escForm.dias_mes" :minFractionDigits="0" fluid /></div>
        <div><label class="text-sm font-medium">Horas/mês</label><InputNumber v-model="escForm.horas_mes" :minFractionDigits="0" fluid /></div>
        <div><label class="text-sm font-medium">Qtd Diurno</label><InputNumber v-model="escForm.qtd_diurno" fluid /></div>
        <div><label class="text-sm font-medium">Qtd Noturno</label><InputNumber v-model="escForm.qtd_noturno" fluid /></div>
        <div><label class="text-sm font-medium">Func. por posto</label><InputNumber v-model="escForm.func_por_posto" fluid /></div>
        <div class="flex items-end gap-2"><ToggleSwitch v-model="escForm.tem_an" /><span class="text-sm">Tem adicional noturno</span></div>
        <div class="col-span-2"><label class="text-sm font-medium">Jornada</label><InputText v-model="escForm.jornada" class="w-full" placeholder="ex: 07h00 às 19h00" /></div>
        <div class="col-span-2 flex items-center gap-2"><ToggleSwitch v-model="escForm.ativo" /><span class="text-sm">Ativa</span></div>
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
