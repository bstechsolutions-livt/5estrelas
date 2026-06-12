<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { onMounted, ref, computed } from "vue"
import axios from "axios"
import Select from "primevue/select"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Button from "primevue/button"
import SelectButton from "primevue/selectbutton"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import { useToast } from "primevue/usetoast"

const toast = useToast()
const fmt = (v) => "R$ " + (Number(v) || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// ─── Dados de apoio ──────────────────────────────────
const ccts = ref([])
const escalas = ref([])
const categorias = ref([])
const indices = ref({})

// ─── Identificação da Proposta ───────────────────────
const ident = ref({
  cliente: "", empresa: "", numero: "", data: new Date().toISOString().slice(0, 10),
  responsavel: "", periodicidade: "Mensal",
})
const modelo = ref("in05")
const modelos = [
  { label: "IN 05", value: "in05" },
  { label: "5 Estrelas", value: "5e" },
]
const cctSel = ref(null)
const escalaSel = ref(null)

// ─── Form de composição (IN 05) ──────────────────────
const form = ref({
  sal: 1850, dias_mes: 15.5, horas_mes: 220,
  peric_pct: 0, insal_pct: 0, an_pct: 0, hnr_pct: 0, outros1_pct: 0,
  inss_pct: 20, saledu_pct: 2.5, sat_pct: 3.28, sesc_pct: 1.5, senai_pct: 1, sebrae_pct: 0.6, incra_pct: 0.2, fgts_pct: 8,
  vt_dia: 10.4, va_dia: 30, medico: 0, odonto: 0, cesta: 0, seguro: 14.2, pmq: 0, outros23: 0,
  avisoind_pct: 1, avistrab_pct: 0.59, ausleg_pct: 0.1, paterni_pct: 0.02, acident_pct: 0.1, matern_pct: 0.02, intrajornada: 0,
  uniforme: 89.5, materiais: 0, ferramental: 0, epi: 0, treinamento: 0, sso: 18,
  custoind_pct: 5, lucro_pct: 3, iss_pct: 5, pis_pct: 1.65, cofins_pct: 7.6,
})

// ─── Configurar Posto ────────────────────────────────
const posto = ref({ descricao: "", qtd_postos: 1 })
const custoEmpregado = ref(0)        // preço por empregado calculado
const resultadoDetalhe = ref(null)
const calculando = ref(false)

const funcPorPosto = computed(() => Number(escalaSel.value?.func_por_posto || 1))
const custoUnitario = computed(() => +(custoEmpregado.value * funcPorPosto.value).toFixed(2)) // custo por posto
const totalConfig = computed(() => +(custoUnitario.value * Number(posto.value.qtd_postos || 0)).toFixed(2))

// ─── Resumo (múltiplos postos) ───────────────────────
const postos = ref([])
const totalGeral = computed(() => postos.value.reduce((s, p) => s + Number(p.total || 0), 0))

async function carregar() {
  try {
    const { data } = await axios.get("/comercial/cotacao/dados")
    ccts.value = (data.ccts || []).map((c) => ({ ...c, _label: `${c.uf?.toUpperCase()} — ${c.titulo || c.nome}` }))
    escalas.value = data.escalas || []
    categorias.value = data.categorias || []
    indices.value = data.indices || {}
    if (indices.value.administracao != null) form.value.custoind_pct = Number(indices.value.administracao)
    if (indices.value.lucro != null) form.value.lucro_pct = Number(indices.value.lucro)
    if (indices.value.iss != null) form.value.iss_pct = Number(indices.value.iss)
    if (indices.value.pis != null) form.value.pis_pct = Number(indices.value.pis)
    if (indices.value.cofins != null) form.value.cofins_pct = Number(indices.value.cofins)
  } catch (e) {
    toast.add({ severity: "error", summary: "Erro", detail: "Falha ao carregar dados", life: 4000 })
  }
}
onMounted(carregar)

function aplicarCct(c) {
  if (!c) return
  ident.value.empresa = c.titulo || c.nome
  form.value.sal = Number(c.salario_base)
  form.value.peric_pct = Number(c.periculosidade_pct)
  form.value.an_pct = Number(c.adicional_noturno_pct)
  form.value.vt_dia = Number(c.vt)
  form.value.va_dia = Number(c.va)
  form.value.seguro = Number(c.seguro_vida)
  form.value.medico = Number(c.plano_saude)
  form.value.uniforme = Number(c.uniforme)
}
function aplicarEscala(e) {
  if (!e) return
  form.value.dias_mes = Number(e.dias_mes)
  form.value.horas_mes = Number(e.horas_mes)
}

async function calcularCusto() {
  calculando.value = true
  try {
    const { data } = await axios.post("/comercial/cotacao/calcular", { ...form.value, colaboradores: 1 })
    custoEmpregado.value = Number(data.resultado.preco_empregado)
    resultadoDetalhe.value = data.resultado
    toast.add({ severity: "success", summary: "Calculado", detail: "Custo por empregado: " + fmt(custoEmpregado.value), life: 2500 })
  } catch (e) {
    toast.add({ severity: "error", summary: "Erro", detail: "Falha ao calcular", life: 4000 })
  } finally {
    calculando.value = false
  }
}

function adicionarPosto() {
  if (!custoEmpregado.value) {
    toast.add({ severity: "warn", summary: "Atenção", detail: "Calcule o custo antes de adicionar.", life: 3000 })
    return
  }
  postos.value.push({
    descricao: posto.value.descricao || (cctSel.value?._label ?? "Posto"),
    escala: escalaSel.value?.nome || "—",
    func_posto: funcPorPosto.value,
    custo_unitario: custoUnitario.value,
    qtd_postos: Number(posto.value.qtd_postos || 1),
    total: totalConfig.value,
  })
  posto.value = { descricao: "", qtd_postos: 1 }
  toast.add({ severity: "success", summary: "Adicionado", detail: "Posto incluído no resumo.", life: 2000 })
}
function removerPosto(i) {
  postos.value.splice(i, 1)
}

const camposGrupos = [
  { t: "Módulo 1 — Remuneração", campos: [["sal", "Salário (R$)"], ["peric_pct", "Periculosidade (%)"], ["insal_pct", "Insalubridade (%)"], ["an_pct", "Ad. Noturno (%)"], ["hnr_pct", "HNR (%)"], ["outros1_pct", "Outros (%)"]] },
  { t: "Encargos (2.2)", campos: [["inss_pct", "INSS (%)"], ["saledu_pct", "Sal.Educ (%)"], ["sat_pct", "SAT (%)"], ["sesc_pct", "SESC (%)"], ["senai_pct", "SENAI (%)"], ["sebrae_pct", "SEBRAE (%)"], ["incra_pct", "INCRA (%)"], ["fgts_pct", "FGTS (%)"]] },
  { t: "Benefícios (2.3)", campos: [["vt_dia", "VT (R$/dia)"], ["va_dia", "VA (R$/dia)"], ["medico", "Médico (R$)"], ["odonto", "Odonto (R$)"], ["cesta", "Cesta (R$)"], ["seguro", "Seguro (R$)"], ["pmq", "PMQ (R$)"], ["outros23", "Outros (R$)"]] },
  { t: "Módulo 3 — Rescisão", campos: [["avisoind_pct", "Aviso Ind. (%)"], ["avistrab_pct", "Aviso Trab. (%)"]] },
  { t: "Módulo 4 — Ausências", campos: [["ausleg_pct", "Aus. Legais (%)"], ["paterni_pct", "Paternidade (%)"], ["acident_pct", "Acidente (%)"], ["matern_pct", "Maternidade (%)"], ["intrajornada", "Intrajornada (R$)"]] },
  { t: "Módulo 5 — Insumos", campos: [["uniforme", "Uniforme (R$)"], ["materiais", "Materiais (R$)"], ["ferramental", "Ferramental (R$)"], ["epi", "EPI (R$)"], ["treinamento", "Treinamento (R$)"], ["sso", "SSO (R$)"]] },
  { t: "Módulo 6 — Tributos/Adm/Lucro", campos: [["custoind_pct", "Custos Ind. (%)"], ["lucro_pct", "Lucro (%)"], ["iss_pct", "ISS (%)"], ["pis_pct", "PIS (%)"], ["cofins_pct", "COFINS (%)"]] },
]
</script>

<template>
  <AuthenticatedLayout>
    <div class="p-4 md:p-6 max-w-7xl mx-auto space-y-4">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Nova Cotação de Custos</h1>

      <!-- Identificação da Proposta -->
      <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Identificação da Proposta</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div><label class="block text-xs text-gray-500 mb-1">Cliente</label><InputText v-model="ident.cliente" class="w-full" /></div>
          <div><label class="block text-xs text-gray-500 mb-1">Empresa / CNPJ</label><InputText v-model="ident.empresa" class="w-full" /></div>
          <div><label class="block text-xs text-gray-500 mb-1">Nº Proposta</label><InputText v-model="ident.numero" class="w-full" placeholder="auto" /></div>
          <div><label class="block text-xs text-gray-500 mb-1">Data</label><InputText v-model="ident.data" type="date" class="w-full" /></div>
          <div><label class="block text-xs text-gray-500 mb-1">Responsável</label><InputText v-model="ident.responsavel" class="w-full" /></div>
          <div><label class="block text-xs text-gray-500 mb-1">Periodicidade</label><InputText v-model="ident.periodicidade" class="w-full" /></div>
          <div class="sm:col-span-2">
            <label class="block text-xs text-gray-500 mb-1">CCT Vigente (Estado × Serviço)</label>
            <Select v-model="cctSel" :options="ccts" optionLabel="_label" placeholder="Selecione" class="w-full" filter @change="aplicarCct(cctSel)" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Modelo de Planilha</label>
            <SelectButton v-model="modelo" :options="modelos" optionLabel="label" optionValue="value" :allowEmpty="false" />
          </div>
        </div>
      </div>

      <!-- Configurar Posto -->
      <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Configurar Posto</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Escala</label>
            <Select v-model="escalaSel" :options="escalas" optionLabel="nome" placeholder="Selecione" class="w-full" @change="aplicarEscala(escalaSel)" />
          </div>
          <div><label class="block text-xs text-gray-500 mb-1">Qtd de Postos</label><InputNumber v-model="posto.qtd_postos" :min="1" fluid /></div>
          <div class="sm:col-span-2"><label class="block text-xs text-gray-500 mb-1">Descrição / Localização</label><InputText v-model="posto.descricao" class="w-full" /></div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3 text-sm">
          <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-2"><span class="block text-xs text-gray-500">Func./posto</span><strong>{{ funcPorPosto }}</strong></div>
          <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-2"><span class="block text-xs text-gray-500">Custo unitário</span><strong>{{ fmt(custoUnitario) }}</strong></div>
          <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-2"><span class="block text-xs text-gray-500">Qtd postos</span><strong>{{ posto.qtd_postos }}</strong></div>
          <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-2"><span class="block text-xs text-gray-500">Total mensal</span><strong class="text-amber-700 dark:text-amber-400">{{ fmt(totalConfig) }}</strong></div>
        </div>
        <div class="flex gap-2 mt-3">
          <Button label="Calcular Custo" icon="pi pi-calculator" severity="secondary" outlined @click="calcularCusto" :loading="calculando" />
          <Button label="Adicionar ao Resumo" icon="pi pi-plus" @click="adicionarPosto" />
        </div>
      </div>

      <!-- Composição detalhada (IN 05) -->
      <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Composição Detalhada (IN 05)</h3>
        <div v-for="grupo in camposGrupos" :key="grupo.t" class="mb-3">
          <p class="text-xs font-semibold text-gray-500 mb-1">{{ grupo.t }}</p>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <div v-for="[k, label] in grupo.campos" :key="k">
              <label class="block text-[11px] text-gray-400 mb-0.5">{{ label }}</label>
              <InputNumber v-model="form[k]" :minFractionDigits="2" :maxFractionDigits="4" fluid />
            </div>
          </div>
        </div>
      </div>

      <!-- Resumo dos Postos -->
      <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Resumo — Postos da Cotação</h3>
        <DataTable :value="postos" size="small" stripedRows>
          <Column field="descricao" header="Posto" />
          <Column field="escala" header="Escala" />
          <Column field="func_posto" header="Func/posto" />
          <Column header="Custo unitário"><template #body="{ data }">{{ fmt(data.custo_unitario) }}</template></Column>
          <Column field="qtd_postos" header="Qtd" />
          <Column header="Total"><template #body="{ data }">{{ fmt(data.total) }}</template></Column>
          <Column header=""><template #body="{ index }"><Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="removerPosto(index)" /></template></Column>
          <template #empty><div class="text-center text-gray-400 py-6">Nenhum posto adicionado ainda.</div></template>
        </DataTable>
        <div class="flex justify-end mt-3">
          <div class="bg-green-50 dark:bg-green-900/20 rounded-lg px-4 py-2 text-right">
            <span class="block text-xs text-gray-500">Total Geral Mensal</span>
            <strong class="text-lg text-green-700 dark:text-green-400">{{ fmt(totalGeral) }}</strong>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
