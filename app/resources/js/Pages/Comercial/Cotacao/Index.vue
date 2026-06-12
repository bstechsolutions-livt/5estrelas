<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { onMounted, ref, computed } from "vue"
import axios from "axios"
import Select from "primevue/select"
import InputNumber from "primevue/inputnumber"
import Button from "primevue/button"
import { useToast } from "primevue/usetoast"

const toast = useToast()

const ccts = ref([])
const escalas = ref([])
const indices = ref({})
const cctSel = ref(null)
const escalaSel = ref(null)
const resultado = ref(null)
const calculando = ref(false)

// Form IN 05 — defaults idênticos ao protótipo
const form = ref({
  sal: 1850, dias_mes: 15.5, horas_mes: 220, colaboradores: 1,
  peric_pct: 0, insal_pct: 0, an_pct: 0, hnr_pct: 0, outros1_pct: 0,
  inss_pct: 20, saledu_pct: 2.5, sat_pct: 3.28, sesc_pct: 1.5, senai_pct: 1, sebrae_pct: 0.6, incra_pct: 0.2, fgts_pct: 8,
  vt_dia: 10.4, va_dia: 30, medico: 0, odonto: 0, cesta: 0, seguro: 14.2, pmq: 0, outros23: 0,
  avisoind_pct: 1, avistrab_pct: 0.59, ausleg_pct: 0.1, paterni_pct: 0.02, acident_pct: 0.1, matern_pct: 0.02, intrajornada: 0,
  uniforme: 89.5, materiais: 0, ferramental: 0, epi: 0, treinamento: 0, sso: 18,
  custoind_pct: 5, lucro_pct: 3, iss_pct: 5, pis_pct: 1.65, cofins_pct: 7.6,
})

async function carregar() {
  try {
    const { data } = await axios.get("/comercial/cotacao/dados")
    ccts.value = (data.ccts || []).map((c) => ({ ...c, _label: `${c.uf?.toUpperCase()} — ${c.titulo || c.nome}` }))
    escalas.value = data.escalas || []
    indices.value = data.indices || {}
    // aplica índices (adm/lucro/tributos) ao form
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
  form.value.sal = Number(c.salario_base)
  form.value.peric_pct = Number(c.periculosidade_pct)
  form.value.an_pct = Number(c.adicional_noturno_pct)
  form.value.vt_dia = Number(c.vt)
  form.value.va_dia = Number(c.va)
  form.value.seguro = Number(c.seguro_vida)
  form.value.medico = Number(c.plano_saude)
  form.value.uniforme = Number(c.uniforme)
  if (c.horas_mes) form.value.horas_mes = Number(c.horas_mes)
  if (c.dias_mes) form.value.dias_mes = Number(c.dias_mes)
}
function aplicarEscala(e) {
  if (!e) return
  form.value.dias_mes = Number(e.dias_mes)
  form.value.horas_mes = Number(e.horas_mes)
}

async function calcular() {
  calculando.value = true
  try {
    const { data } = await axios.post("/comercial/cotacao/calcular", form.value)
    resultado.value = data.resultado
  } catch (e) {
    toast.add({ severity: "error", summary: "Erro", detail: "Falha ao calcular", life: 4000 })
  } finally {
    calculando.value = false
  }
}

const fmt = (v) => "R$ " + (Number(v) || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// Definição dos campos por módulo (para renderização)
const camposM1 = [
  ["sal", "Salário Base (R$)"], ["peric_pct", "Periculosidade (%)"], ["insal_pct", "Insalubridade (%)"],
  ["an_pct", "Adicional Noturno (%)"], ["hnr_pct", "Hora Noturna Reduzida (%)"], ["outros1_pct", "Outros (%)"],
]
const camposEnc = [
  ["inss_pct", "INSS (%)"], ["saledu_pct", "Sal. Educação (%)"], ["sat_pct", "SAT/RAT (%)"], ["sesc_pct", "SESC/SESI (%)"],
  ["senai_pct", "SENAI (%)"], ["sebrae_pct", "SEBRAE (%)"], ["incra_pct", "INCRA (%)"], ["fgts_pct", "FGTS (%)"],
]
const camposBen = [
  ["vt_dia", "VT (R$/dia)"], ["va_dia", "VA (R$/dia)"], ["medico", "Assist. Médica (R$)"], ["odonto", "Odontológica (R$)"],
  ["cesta", "Cesta Básica (R$)"], ["seguro", "Seguro de Vida (R$)"], ["pmq", "Qualificação (R$)"], ["outros23", "Outros (R$)"],
]
const camposResc = [
  ["avisoind_pct", "Aviso Indenizado (%)"], ["avistrab_pct", "Aviso Trabalhado (%)"],
]
const camposAus = [
  ["ausleg_pct", "Ausências Legais (%)"], ["paterni_pct", "Lic. Paternidade (%)"],
  ["acident_pct", "Acid. Trabalho (%)"], ["matern_pct", "Lic. Maternidade (%)"], ["intrajornada", "Intrajornada (R$)"],
]
const camposIns = [
  ["uniforme", "Uniformes (R$)"], ["materiais", "Materiais (R$)"], ["ferramental", "Ferramental (R$)"],
  ["epi", "EPIs (R$)"], ["treinamento", "Treinamento (R$)"], ["sso", "SSO (R$)"],
]
const camposTrib = [
  ["custoind_pct", "Custos Indiretos (%)"], ["lucro_pct", "Lucro (%)"], ["iss_pct", "ISS (%)"], ["pis_pct", "PIS (%)"], ["cofins_pct", "COFINS (%)"],
]
</script>

<template>
  <AuthenticatedLayout>
    <div class="p-4 md:p-6 max-w-7xl mx-auto">
      <div class="mb-5">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Nova Cotação de Custos</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Planilha de composição de custos (IN 05) por posto.</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Formulário -->
        <div class="lg:col-span-2 space-y-4">
          <!-- Seletores -->
          <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">CCT (Estado × Serviço)</label>
                <Select v-model="cctSel" :options="ccts" optionLabel="_label" placeholder="Selecione" class="w-full" filter
                  @change="aplicarCct(cctSel)" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Escala</label>
                <Select v-model="escalaSel" :options="escalas" optionLabel="nome" placeholder="Selecione" class="w-full"
                  @change="aplicarEscala(escalaSel)" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Nº Colaboradores</label>
                <InputNumber v-model="form.colaboradores" :min="1" fluid />
              </div>
            </div>
          </div>

          <!-- Grupos de campos -->
          <div v-for="grupo in [
              { t: 'Módulo 1 — Remuneração', campos: camposM1 },
              { t: 'Encargos (Submódulo 2.2)', campos: camposEnc },
              { t: 'Benefícios (Submódulo 2.3)', campos: camposBen },
              { t: 'Módulo 3 — Rescisão', campos: camposResc },
              { t: 'Módulo 4 — Reposição de Ausências', campos: camposAus },
              { t: 'Módulo 5 — Insumos', campos: camposIns },
              { t: 'Módulo 6 — Tributos, Adm e Lucro', campos: camposTrib },
            ]" :key="grupo.t"
            class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">{{ grupo.t }}</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <div v-for="[key, label] in grupo.campos" :key="key">
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ label }}</label>
                <InputNumber v-model="form[key]" :minFractionDigits="2" :maxFractionDigits="4" fluid />
              </div>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <Button label="Calcular" icon="pi pi-calculator" @click="calcular" :loading="calculando" />
            <span class="text-xs text-gray-400">Cálculo no servidor (IN 05) — verificado contra a planilha oficial.</span>
          </div>
        </div>

        <!-- Resultado -->
        <div class="lg:col-span-1">
          <div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4 sticky top-4">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Quadro-Resumo</h3>
            <div v-if="!resultado" class="text-sm text-gray-400 py-8 text-center">
              <i class="pi pi-calculator text-3xl mb-2 block"></i>
              Preencha e clique em Calcular.
            </div>
            <div v-else class="space-y-2 text-sm">
              <div class="flex justify-between"><span class="text-gray-500">Módulo 1</span><span>{{ fmt(resultado.modulo1.total) }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">Módulo 2</span><span>{{ fmt(resultado.modulo2.total) }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">Módulo 3</span><span>{{ fmt(resultado.modulo3.total) }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">Módulo 4</span><span>{{ fmt(resultado.modulo4.total) }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">Módulo 5</span><span>{{ fmt(resultado.modulo5.total) }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">Módulo 6</span><span>{{ fmt(resultado.modulo6.total) }}</span></div>
              <div class="flex justify-between border-t border-gray-200 dark:border-slate-700 pt-2 font-medium">
                <span>Subtotal</span><span>{{ fmt(resultado.subtotal) }}</span>
              </div>
              <div class="flex justify-between bg-amber-50 dark:bg-amber-900/20 -mx-2 px-2 py-2 rounded-lg font-bold text-amber-700 dark:text-amber-400">
                <span>Custo / empregado</span><span>{{ fmt(resultado.preco_empregado) }}</span>
              </div>
              <div class="flex justify-between"><span class="text-gray-500">Colaboradores</span><span>{{ resultado.colaboradores }}</span></div>
              <div class="flex justify-between border-t border-gray-200 dark:border-slate-700 pt-2 text-base font-bold text-green-700 dark:text-green-400">
                <span>Valor do Posto (mês)</span><span>{{ fmt(resultado.valor_posto_mensal) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
