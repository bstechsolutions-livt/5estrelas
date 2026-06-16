<script setup>
// ─────────────────────────────────────────────────────────────────────────────
// Nova Cotação de Custos — portada 1:1 do protótipo Gestão 360º (view-cotacao).
// HTML/CSS puro sob `.g360` (mesmo padrão da tela Valores). Cores white-label:
// dentro do .g360 o dourado do protótipo (--brand-gold) aponta para --app-primary.
//
// Regra de cálculo: o motor é o backend (ComposicaoCustoService, fonte da verdade).
//  - A tela monta o payload e chama /comercial/cotacao/calcular (IN05) ou
//    /comercial/cotacao/calcular-5e (Modelo 5 Estrelas) de forma reativa (debounce).
//  - O número que vira "posto" no resumo usa SEMPRE o total retornado pelo backend.
//  - Os campos readonly do detalhamento (quebra por linha) são derivados localmente
//    apenas para EXIBIÇÃO, usando exatamente as mesmas fórmulas do protótipo/serviço
//    (logo coincidem ao centavo com o backend).
// ─────────────────────────────────────────────────────────────────────────────
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { onMounted, reactive, ref, computed, watch } from "vue"
import axios from "axios"
import Toast from "primevue/toast"
import { useToast } from "primevue/usetoast"
import * as XLSX from "xlsx"
import "@/../css/comercial-g360.css"

const toast = useToast()
const emBreve = (o) => toast.add({ severity: "info", summary: "Em breve", detail: o, life: 2800 })
const ok = (m) => toast.add({ severity: "success", summary: "Pronto", detail: m, life: 2500 })
const warn = (m) => toast.add({ severity: "warn", summary: "Atenção", detail: m, life: 3000 })
const fail = (m) => toast.add({ severity: "error", summary: "Erro", detail: m, life: 4000 })

const n = (v) => { const x = Number(v); return isNaN(x) ? 0 : x }
const fmt = (v) => {
  if (v == null || isNaN(Number(v))) return "R$ 0,00"
  return "R$ " + (Number(v) || 0).toFixed(2).replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".")
}
const r2 = (v) => Math.round(v * 100) / 100
const r4 = (v) => Math.round(v * 10000) / 10000
// Exibição de percentual (campos readonly do IN05): valor já em % (ex.: 8.33)
const pctTxt = (v) => (Number(v) || 0).toFixed(4) + "%"

// ─── SVG icons (do protótipo) ─────────────────────────────────────────────────
const ICONS = {
  shield: '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 1.5L2 4v4c0 3.3 2.6 5.8 6 6.5 3.4-.7 6-3.2 6-6.5V4L8 1.5z"/></svg>',
  building: '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="3" width="12" height="11" rx="1"/><path d="M6 14V9h4v5M1 6h14"/></svg>',
  broom: '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 13l3-3m0 0l5-5-2-2-5 5m2 2l-2 2M10 3l3 3"/></svg>',
  fire: '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2c0 3-3 4-3 7a3 3 0 006 0c0-1-.5-2-1-3-1 1-1.5 1.5-1.5 2.5C8 7 10 5.5 10 3c-.5.5-1 1.5-1 2.5C8.5 4 8 3 8 2z"/></svg>',
  star: '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 1l2 5h5l-4 3 1.5 5L8 11l-4.5 3L5 9 1 6h5z"/></svg>',
  user: '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="5" r="3"/><path d="M2 14c0-3 2.7-5 6-5s6 2 6 5"/></svg>',
  clock24: '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6"/><path d="M8 4v4l3 2"/></svg>',
  sun: '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="3"/><path d="M8 1v2M8 13v2M1 8h2M13 8h2M3.2 3.2l1.4 1.4M11.4 11.4l1.4 1.4M11.4 4.6l-1.4 1.4M4.6 11.4l-1.4 1.4"/></svg>',
  moon: '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M13 9A6 6 0 016 3a6 6 0 100 10 6 6 0 007-4z"/></svg>',
  calendar: '<svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="3" width="12" height="11" rx="1"/><path d="M5 1v3M11 1v3M2 7h12"/></svg>',
}
const icon = (name) => ICONS[name] || ICONS.user

// Metadados de escala (descrição/ícone/revezamento não vêm do backend) por nome
const ESCALA_META = {
  "12x36 — Diurno": { icone: "sun", revezamento: [
    { slot: "Func. A", dias: "Dias pares", ex: "2, 4, 6, 8..." },
    { slot: "Func. B", dias: "Dias ímpares", ex: "1, 3, 5, 7..." }] },
  "12x36 — Noturno": { icone: "moon", revezamento: [
    { slot: "Func. A", dias: "Dias pares", ex: "2, 4, 6, 8..." },
    { slot: "Func. B", dias: "Dias ímpares", ex: "1, 3, 5, 7..." }] },
  "24 Horas (12x36)": { icone: "clock24", revezamento: [
    { slot: "Func. A", dias: "Diurno · Pares", ex: "07h→19h, dias 2,4,6..." },
    { slot: "Func. B", dias: "Diurno · Ímpares", ex: "07h→19h, dias 1,3,5..." },
    { slot: "Func. C", dias: "Noturno · Pares", ex: "19h→07h, dias 2,4,6..." },
    { slot: "Func. D", dias: "Noturno · Ímpares", ex: "19h→07h, dias 1,3,5..." }] },
  "44h — 5×2": { icone: "calendar", revezamento: [{ slot: "Func. A", dias: "Seg a Sex", ex: "22 dias/mês" }] },
  "44h — 6×1": { icone: "calendar", revezamento: [{ slot: "Func. A", dias: "Seg a Sab", ex: "26 dias/mês" }] },
}
const HORARIOS = {
  "12x36 — Diurno": { D: "07:00 às 19:00", N: "—" },
  "12x36 — Noturno": { D: "—", N: "19:00 às 07:00" },
  "24 Horas (12x36)": { D: "07:00 às 19:00", N: "19:00 às 07:00" },
  "44h — 5×2": { D: "07:00 às 17:00", N: "—" },
  "44h — 6×1": { D: "07:00 às 17:00", N: "—" },
}
const escDescricao = (e) => {
  const f = Number(e?.func_por_posto || 0)
  if (f === 4) return "1 posto = 4 func. (2 diurnos + 2 noturnos, dias pares e ímpares)"
  if (f === 2) return "1 posto = 2 func. (dias pares + dias ímpares)"
  return "1 posto = 1 func. (jornada comercial)"
}
const escIcone = (e) => ESCALA_META[e?.nome]?.icone || "calendar"
const escRevez = (e) => ESCALA_META[e?.nome]?.revezamento || []

// ─── Dados de apoio (backend) ──────────────────────────────────────────────────
const ccts = ref([])
const escalas = ref([])
const categorias = ref([])
const indices = ref({})

// Proposta a reabrir (vem do controller quando a URL tem ?proposta={id}).
const props = defineProps({
  propostaInicial: { type: Object, default: null },
})

// ─── Identificação da proposta ──────────────────────────────────────────────────
const empresas = [
  { value: "seg-df", label: "Segurança — Sede DF", uf: "df" },
  { value: "seg-go", label: "Segurança — Filial GO", uf: "go" },
  { value: "seg-mt", label: "Segurança — Filial MT", uf: "mt" },
  { value: "seg-mg", label: "Segurança — Filial MG", uf: "mg" },
  { value: "seg-sp", label: "Segurança — Filial SP", uf: "sp" },
  { value: "apoio-df", label: "Apoio Administrativo — DF", uf: "df" },
]
const ident = reactive({
  numProposta: "Nº 132", // TODO: numeração automática real virá da Spec 3 (Propostas)
  data: new Date().toISOString().slice(0, 10),
  cliente: "",
  empresa: "seg-df",
  cct: "",
  periodicidade: "Mensal",
})
const numEditavel = ref(false)
function editarNumProposta() { numEditavel.value = true }

const modelo = ref("5estrelas") // 5estrelas | in05

// ─── Seleção de categoria / escala ────────────────────────────────────────────
const catSel = ref(null)
const escSel = ref(null)
const motoSel = ref(false)
const MOTO_EXTRA = 200.0

// ─── Configurar posto ─────────────────────────────────────────────────────────
const qtdPostos = ref(1)
const postoDescricao = ref("")
const bannerVisivel = ref(false)

// ─── Formulário 5 Estrelas ──────────────────────────────────────────────────────
const f5 = reactive({
  horarioDiurno: "07:00 às 19:00", qtdDiurno: 2, salDiurno: 2347.8, anDiurno: "0",
  horarioNoturno: "19:00 às 07:00", qtdNoturno: 2, salNoturno: 2347.8, anNoturno: "1",
  funcao: "Vigilante",
  encargos: 72.11,
  b_uniforme: 89.5, b_saude: 242.0, b_fundo: 31.5, b_sst: 18.0, b_cna: 22.0, b_seguro: 14.2,
  b_gta: 47.0, b_cofre: 55.0, b_arma: 126.0, b_reciclag: 32.0, b_vt: 10.4, b_va: 30.0,
  pctAdm: 5.0, pctLucro: 3.0, pctImpostos: 8.65, meses: "12",
})

// ─── Formulário IN 05 ─────────────────────────────────────────────────────────
const fin = reactive({
  municipio: "", anoCct: 2026, meses: 12, cbo: "",
  categoria: "Agente de Portaria", sindicato: "FETHE/MG", dataBase: "",
  sal: 1850.0, peric_pct: 0, insal_pct: 0, an_pct: 0, hnr_pct: 0, outros1_pct: 0,
  inss_pct: 20, saledu_pct: 2.5, sat_pct: 3.28, sesc_pct: 1.5, senai_pct: 1, sebrae_pct: 0.6, incra_pct: 0.2, fgts_pct: 8,
  vt_dia: 10.4, va_dia: 30.0, medico: 0, odonto: 0, cesta: 0, seguro: 14.2, pmq: 0, outros23: 0,
  avisoind_pct: 1, avistrab_pct: 0.59, ausleg_pct: 0.1, paterni_pct: 0.02, acident_pct: 0.1, matern_pct: 0.02, intrajornada: 0,
  uniforme: 89.5, materiais: 0, ferramental: 0, epi: 0, treinamento: 0, sso: 18.0,
  custoind_pct: 5, lucro_pct: 3, iss_pct: 5, pis_pct: 1.65, cofins_pct: 7.6,
  meses_geral: "12",
})

const detalhesAbertos = ref(false)
function toggleDetalhes() { detalhesAbertos.value = !detalhesAbertos.value }

// ─── Helpers de escala/categoria ────────────────────────────────────────────────
const escala = computed(() => escSel.value || {})
const cat = computed(() => catSel.value || {})
const diasMes = computed(() => n(escala.value.dias_mes) || 15.5)
const horasMes = computed(() => n(escala.value.horas_mes) || 220)
const funcPorPosto = computed(() => n(escala.value.func_por_posto) || (n(escala.value.qtd_diurno) + n(escala.value.qtd_noturno)) || 1)

// ─── Cálculo local de EXIBIÇÃO — Modelo 5 Estrelas (espelha o serviço) ──────────
const c5 = computed(() => {
  const qtdD = n(f5.qtdDiurno), salD = n(f5.salDiurno)
  const qtdN = n(f5.qtdNoturno), salN = n(f5.salNoturno)
  const anD = parseInt(f5.anDiurno || 0)
  const anN = parseInt(f5.anNoturno || 0)
  const dm = diasMes.value, hm = horasMes.value
  const pericPct = (n(cat.value.periculosidade_pct) || 0) / 100
  const anPct = 0.20
  const intraH = n(cat.value.intrajornada_h) || 1.5

  const pericD = salD * pericPct
  const adnD = anD ? r2((salD + pericD) / hm * anPct * 8 * dm) : 0
  const intraD = (salD + pericD + adnD) / hm * intraH * dm
  const totD = (salD + pericD + adnD + intraD) * qtdD

  const pericN = salN * pericPct
  const adnN = anN ? r2((salN + pericN) / hm * anPct * 8 * dm) : 0
  const intraN = (salN + pericN + adnN) / hm * intraH * dm
  const totN = (salN + pericN + adnN + intraN) * qtdN

  const totalFunc = qtdD + qtdN
  const remTotal = totD + totN
  const encVal = remTotal * (n(f5.encargos) / 100)
  const m1 = remTotal + encVal

  const salBase = salD || salN
  const bens = [["uniforme", totalFunc], ["saude", totalFunc], ["fundo", totalFunc], ["sst", totalFunc],
    ["cna", totalFunc], ["seguro", totalFunc], ["gta", 1], ["cofre", 1], ["arma", 1],
    ["reciclag", totalFunc], ["vt", dm * totalFunc], ["va", dm * totalFunc]]
  let m2 = 0
  const bt = {}
  bens.forEach(([id, mult]) => { const t = n(f5["b_" + id]) * mult; bt[id] = t; m2 += t })
  const descVT = ((n(cat.value.desconto_vt_pct) || 6) / 100) * salBase * totalFunc
  m2 -= descVT

  const base = m1 + m2
  const vAdm = base * (n(f5.pctAdm) / 100)
  const vLucro = (base + vAdm) * (n(f5.pctLucro) / 100)
  const total3 = base + vAdm + vLucro
  const vImp = total3 * (n(f5.pctImpostos) / 100)
  const grandTotal = total3 + vImp
  const meses = parseInt(f5.meses || 12)
  const vaTotal = n(f5.b_va) * dm * totalFunc
  const valorPessoa = totalFunc > 0 ? grandTotal / totalFunc : 0

  return { pericD, adnD, intraD, totD, pericN, adnN, intraN, totN, totalFunc, encVal, m1, bt, m2,
    vAdm, vLucro, vImp, m3: vAdm + vLucro + vImp, grandTotal, anual: grandTotal * meses, vaTotal, valorPessoa }
})

// ─── Cálculo local de EXIBIÇÃO — IN 05 (espelha calcIN/serviço) ────────────────
const cin = computed(() => {
  const sal = n(fin.sal)
  const dm = diasMes.value, hm = horasMes.value

  // Módulo 1
  const peric = sal * (n(fin.peric_pct) / 100)
  const insal = sal * (n(fin.insal_pct) / 100)
  const anVal = r2((sal + peric) / hm * (n(fin.an_pct) / 100) * 8 * dm)
  const hnrVal = sal * (n(fin.hnr_pct) / 100)
  const out1 = sal * (n(fin.outros1_pct) / 100)
  const m1 = sal + peric + insal + anVal + hnrVal + out1

  // Módulo 2.2
  const inss = r4(m1 * (n(fin.inss_pct) / 100))
  const saledu = r4(m1 * (n(fin.saledu_pct) / 100))
  const sat = r4(m1 * (n(fin.sat_pct) / 100))
  const sesc = r4(m1 * (n(fin.sesc_pct) / 100))
  const senai = r4(m1 * (n(fin.senai_pct) / 100))
  const sebrae = r4(m1 * (n(fin.sebrae_pct) / 100))
  const incra = r4(m1 * (n(fin.incra_pct) / 100))
  const fgts = r4(m1 * (n(fin.fgts_pct) / 100))
  const sub22 = inss + saledu + sat + sesc + senai + sebrae + incra + fgts
  const pct22 = m1 > 0 ? sub22 / m1 : 0

  // Módulo 2.1
  const p13 = 0.0833
  const pFer = r4(p13 / 3)
  const pInc21 = r4((p13 + pFer) * pct22)
  const pMultaFgts = r4(pFer * 0.032)
  const v13 = m1 * p13, vFer = m1 * pFer, vInc21 = m1 * pInc21, vMultaFgts = m1 * pMultaFgts
  const sub21 = v13 + vFer + vInc21 + vMultaFgts

  // Módulo 2.3
  const vtBruto = n(fin.vt_dia) * dm
  const vtLiq = r2(Math.max(vtBruto - sal * 0.06, 0))
  const vaVal = r2(n(fin.va_dia) * dm)
  const medico = n(fin.medico), odonto = n(fin.odonto), cesta = n(fin.cesta)
  const seguro = n(fin.seguro), pmq = n(fin.pmq), out23 = n(fin.outros23)
  const sub23 = vtLiq + vaVal + medico + odonto + cesta + seguro + pmq + out23
  const m2 = sub21 + sub22 + sub23

  // Módulo 3
  const pAvisoInd = n(fin.avisoind_pct) / 100
  const pFgtsPct = n(fin.fgts_pct) / 100
  const pFgtsAviso = r4(pAvisoInd * pFgtsPct)
  const pAvisTrab = n(fin.avistrab_pct) / 100
  const pMultaInd = r4(pAvisoInd * 0.4)
  const pMultaResc = r4(pFgtsPct * 0.4)
  const pIncGPS = r4(pAvisTrab * pct22)
  const vAvisoInd = r2(m1 * pAvisoInd), vFgtsAviso = r2(m1 * pFgtsAviso), vAvisTrab = r2(m1 * pAvisTrab)
  const vMultaInd = r2(m1 * pMultaInd), vMultaResc = r2(m1 * pMultaResc), vIncGPS = r2(m1 * pIncGPS)
  const m3 = vAvisoInd + vFgtsAviso + vAvisTrab + vMultaInd + vMultaResc + vIncGPS

  // Módulo 4
  const pCobFer = r4((p13 + pFer) / 12 + p13)
  const pAusleg = n(fin.ausleg_pct) / 100, pPatern = n(fin.paterni_pct) / 100
  const pAcident = n(fin.acident_pct) / 100, pMatern = n(fin.matern_pct) / 100
  const subtot41pct = pCobFer + pAusleg + pPatern + pAcident + pMatern
  const pct21total = (p13 + pFer) * pct22 + (p13 + pFer)
  const pIncAus = r4(subtot41pct * (pct22 + pct21total))
  const vCobFer = r2(m1 * pCobFer), vAusleg = r2(m1 * pAusleg), vPatern = r2(m1 * pPatern)
  const vAcident = r2(m1 * pAcident), vMatern = r2(m1 * pMatern)
  const sub41 = vCobFer + vAusleg + vPatern + vAcident + vMatern
  const vIncAus = r2(m1 * pIncAus)
  const tot41 = sub41 + vIncAus
  const m4intra = n(fin.intrajornada)
  const m4 = tot41 + m4intra

  // Módulo 5
  const m5 = n(fin.uniforme) + n(fin.materiais) + n(fin.ferramental) + n(fin.epi) + n(fin.treinamento) + n(fin.sso)

  // Módulo 6
  const subtotal = m1 + m2 + m3 + m4 + m5
  const pCind = n(fin.custoind_pct) / 100, pLucro = n(fin.lucro_pct) / 100
  const pISS = n(fin.iss_pct) / 100, pPIS = n(fin.pis_pct) / 100, pCOFINS = n(fin.cofins_pct) / 100
  const pTrib = pISS + pPIS + pCOFINS
  const d140 = (1 + pCind) * (1 + pLucro) / (1 - pTrib) - 1
  const precoEmp = r2(subtotal * (1 + d140))
  const vCind = r2(subtotal * pCind)
  const vLucro = r2((subtotal + vCind) * pLucro)
  const vISS = r2(precoEmp * pISS), vPIS = r2(precoEmp * pPIS), vCOFINS = r2(precoEmp * pCOFINS)
  const m6 = vCind + vLucro + vISS + vPIS + vCOFINS
  const meses = parseInt(fin.meses_geral || 12)

  return {
    peric, insal, anVal, hnrVal, out1, m1,
    inss, saledu, sat, sesc, senai, sebrae, incra, fgts, sub22, pct22,
    p13, pFer, pInc21, pMultaFgts, v13, vFer, vInc21, vMultaFgts, sub21,
    vtLiq, vaVal, medico, odonto, cesta, seguro, pmq, out23, sub23, m2,
    pFgtsAviso, pMultaInd, pMultaResc, pIncGPS, vAvisoInd, vFgtsAviso, vAvisTrab, vMultaInd, vMultaResc, vIncGPS, m3,
    pCobFer, pIncAus, vCobFer, vAusleg, vPatern, vAcident, vMatern, sub41, vIncAus, tot41, m4intra, m4,
    m5, subtotal, vCind, vLucro, vISS, vPIS, vCOFINS, m6, precoEmp, anual: precoEmp * meses,
  }
})

// ─── Total autoritativo (backend) ──────────────────────────────────────────────
const backendGrand = ref(null) // { mensal, vaTotal } da última resposta do backend
const calculando = ref(false)

// Valor "headline" = o que o backend retornou (fonte da verdade); fallback local.
const grandTotal = computed(() => {
  if (backendGrand.value != null) return backendGrand.value.mensal
  return modelo.value === "in05" ? cin.value.precoEmp : c5.value.grandTotal
})
const vaTotalAtual = computed(() => {
  if (backendGrand.value?.vaTotal != null) return backendGrand.value.vaTotal
  return modelo.value === "in05" ? cin.value.vaVal : c5.value.vaTotal
})
const anual5 = computed(() => grandTotal.value * parseInt(f5.meses || 12))
const anualIn = computed(() => grandTotal.value * parseInt(fin.meses_geral || 12))

function payload5e() {
  return {
    qtd_diurno: n(f5.qtdDiurno), sal_diurno: n(f5.salDiurno),
    qtd_noturno: n(f5.qtdNoturno), sal_noturno: n(f5.salNoturno),
    an_diurno: parseInt(f5.anDiurno || 0), an_noturno: parseInt(f5.anNoturno || 0),
    encargos_pct: n(f5.encargos), pct_adm: n(f5.pctAdm), pct_lucro: n(f5.pctLucro), pct_impostos: n(f5.pctImpostos),
    peric_pct: n(cat.value.periculosidade_pct), intra_h: n(cat.value.intrajornada_h) || 1.5,
    desc_vt_pct: n(cat.value.desconto_vt_pct) || 6,
    dias_mes: diasMes.value, horas_mes: horasMes.value,
    beneficios: {
      uniforme: n(f5.b_uniforme), saude: n(f5.b_saude), fundo: n(f5.b_fundo), sst: n(f5.b_sst),
      cna: n(f5.b_cna), seguro: n(f5.b_seguro), gta: n(f5.b_gta), cofre: n(f5.b_cofre),
      arma: n(f5.b_arma), reciclag: n(f5.b_reciclag), vt: n(f5.b_vt), va: n(f5.b_va),
    },
    meses: parseInt(f5.meses || 12),
  }
}
function payloadIn05() {
  return {
    sal: n(fin.sal), dias_mes: diasMes.value, horas_mes: horasMes.value,
    peric_pct: n(fin.peric_pct), insal_pct: n(fin.insal_pct), an_pct: n(fin.an_pct), hnr_pct: n(fin.hnr_pct), outros1_pct: n(fin.outros1_pct),
    inss_pct: n(fin.inss_pct), saledu_pct: n(fin.saledu_pct), sat_pct: n(fin.sat_pct), sesc_pct: n(fin.sesc_pct),
    senai_pct: n(fin.senai_pct), sebrae_pct: n(fin.sebrae_pct), incra_pct: n(fin.incra_pct), fgts_pct: n(fin.fgts_pct),
    vt_dia: n(fin.vt_dia), va_dia: n(fin.va_dia), medico: n(fin.medico), odonto: n(fin.odonto), cesta: n(fin.cesta),
    seguro: n(fin.seguro), pmq: n(fin.pmq), outros23: n(fin.outros23),
    avisoind_pct: n(fin.avisoind_pct), avistrab_pct: n(fin.avistrab_pct), ausleg_pct: n(fin.ausleg_pct),
    paterni_pct: n(fin.paterni_pct), acident_pct: n(fin.acident_pct), matern_pct: n(fin.matern_pct), intrajornada: n(fin.intrajornada),
    uniforme: n(fin.uniforme), materiais: n(fin.materiais), ferramental: n(fin.ferramental), epi: n(fin.epi),
    treinamento: n(fin.treinamento), sso: n(fin.sso),
    custoind_pct: n(fin.custoind_pct), lucro_pct: n(fin.lucro_pct), iss_pct: n(fin.iss_pct), pis_pct: n(fin.pis_pct), cofins_pct: n(fin.cofins_pct),
    colaboradores: 1,
  }
}

let _timer = null
function recalcular() {
  // Recálculo reativo via backend (fonte da verdade), com debounce.
  clearTimeout(_timer)
  _timer = setTimeout(async () => {
    calculando.value = true
    try {
      if (modelo.value === "in05") {
        const { data } = await axios.post("/comercial/cotacao/calcular", payloadIn05())
        const res = data.resultado
        backendGrand.value = { mensal: Number(res.preco_empregado), vaTotal: Number(res.modulo2?.va || 0) }
      } else {
        const { data } = await axios.post("/comercial/cotacao/calcular-5e", payload5e())
        const res = data.resultado
        backendGrand.value = { mensal: Number(res.mensal), vaTotal: Number(res.va_total) }
      }
    } catch (e) {
      backendGrand.value = null // fallback p/ cálculo local de exibição
    } finally {
      calculando.value = false
    }
  }, 300)
}

// Recalcula quando qualquer entrada relevante muda
watch([f5, fin, escSel, catSel, modelo], recalcular, { deep: true })

// ─── Aplicar categoria/escala (preenche os formulários) ────────────────────────
function selecionarCategoria(c) { catSel.value = c; aplicar() }
function selecionarEscala(e) { escSel.value = e; aplicar() }
function toggleMoto(c) { motoSel.value = !motoSel.value; catSel.value = c; aplicar() }

function aplicar() {
  const c = cat.value, e = escala.value
  if (!c?.nome || !e?.nome) return
  let sal = n(c.salario_base)
  if (motoSel.value && c.tem_moto) sal += MOTO_EXTRA

  // Módulo 01 (5E)
  f5.salDiurno = sal; f5.salNoturno = sal
  f5.qtdDiurno = n(e.qtd_diurno); f5.qtdNoturno = n(e.qtd_noturno)
  f5.encargos = n(indices.value.encargos) || f5.encargos
  f5.funcao = c.nome + (motoSel.value && c.tem_moto ? " (Motorizado)" : "")
  const eh24 = (e.nome || "").includes("24")
  f5.anDiurno = (e.tem_an && n(e.qtd_diurno) > 0 && !eh24) ? "1" : "0"
  f5.anNoturno = (e.tem_an && n(e.qtd_noturno) > 0) ? "1" : "0"

  // Módulo 02 (5E)
  f5.b_saude = n(c.plano_saude); f5.b_fundo = n(c.fundo_social); f5.b_sst = n(c.sst)
  f5.b_cna = n(c.cna); f5.b_seguro = n(c.seguro_vida); f5.b_uniforme = n(c.uniforme)
  f5.b_reciclag = n(c.reciclagem); f5.b_va = n(c.va); f5.b_vt = n(c.vt)
  f5.b_gta = n(c.gta); f5.b_cofre = n(c.cofre); f5.b_arma = n(c.arma)

  // Módulo 03 (5E)
  if (indices.value.adm != null) f5.pctAdm = n(indices.value.adm)
  if (indices.value.lucro != null) f5.pctLucro = n(indices.value.lucro)
  if (indices.value.impostos != null) f5.pctImpostos = n(indices.value.impostos)

  // Horários conforme escala
  const hor = HORARIOS[e.nome] || { D: "07:00 às 19:00", N: "19:00 às 07:00" }
  f5.horarioDiurno = hor.D; f5.horarioNoturno = hor.N

  // IN 05
  fin.sal = sal; fin.vt_dia = n(c.vt); fin.va_dia = n(c.va); fin.seguro = n(c.seguro_vida)
  fin.uniforme = n(c.uniforme); fin.sso = n(c.sst); fin.peric_pct = n(c.periculosidade_pct)
  if (indices.value.adm != null) fin.custoind_pct = n(indices.value.adm)
  if (indices.value.lucro != null) fin.lucro_pct = n(indices.value.lucro)
  fin.categoria = c.nome

  bannerVisivel.value = true
  recalcular()
}

// ─── Resumo (postos adicionados) ────────────────────────────────────────────────
const itens = ref([])
let _idSeq = 1
function adicionarItem() {
  const unitVal = grandTotal.value
  if (!unitVal || unitVal <= 0) { warn("Calcule os valores antes de adicionar"); return }
  if (!cat.value?.nome || !escala.value?.nome) { warn("Selecione categoria e escala"); return }
  const catLabel = cat.value.nome + (motoSel.value && cat.value.tem_moto ? " (Motorizado)" : "")
  itens.value.push({
    id: _idSeq++,
    cat: catLabel,
    catIcone: cat.value.icone || "user",
    escala: escala.value.nome,
    funcPosto: funcPorPosto.value,
    qtdPostos: parseInt(qtdPostos.value || 1),
    descr: postoDescricao.value.trim(),
    unitVal,
    totalMensal: unitVal * parseInt(qtdPostos.value || 1),
    vaUnit: vaTotalAtual.value,
    modelo: modelo.value,
  })
  ok("Posto adicionado ao resumo!")
  postoDescricao.value = ""
  qtdPostos.value = 1
}
function removerItem(id) { itens.value = itens.value.filter((i) => i.id !== id) }
function limparItens() { if (itens.value.length) { itens.value = []; ok("Resumo limpo") } }

const escLabelCurto = (nome) => (nome || "").replace("12h ", "").replace(" Horas", "h").replace("Semanais", "sem.")
const totPostos = computed(() => itens.value.reduce((s, i) => s + i.qtdPostos, 0))
const totProfissionais = computed(() => itens.value.reduce((s, i) => s + i.qtdPostos * i.funcPosto, 0))
const totMensal = computed(() => itens.value.reduce((s, i) => s + i.totalMensal, 0))
const totVa = computed(() => itens.value.reduce((s, i) => s + (i.vaUnit || 0) * i.qtdPostos, 0))
const totCustoFunc = computed(() => totProfissionais.value > 0 ? totMensal.value / totProfissionais.value : 0)

// ─── Preview ──────────────────────────────────────────────────────────────────
const prevFunc = computed(() => escala.value?.nome ? funcPorPosto.value + " func." : "—")

// ─── Ações de proposta ──────────────────────────────────────────────────────────
// Salvar Proposta persiste de verdade (POST /comercial/propostas). PDF/Import/Export
// continuam stubs até as próximas fatias.
const salvando = ref(false)
async function salvarProposta() {
  if (!ident.cliente.trim()) { warn("Preencha o nome do cliente antes de salvar"); return }
  if (!totMensal.value || totMensal.value <= 0) { warn("Calcule os valores antes de salvar"); return }
  if (!itens.value.length) { warn("Adicione ao menos um posto ao resumo"); return }

  const payload = {
    cliente: ident.cliente,
    empresa: ident.empresa,
    modelo: modelo.value,
    periodicidade: ident.periodicidade,
    cct: ident.cct,
    data_proposta: ident.data,
    total_mensal: totMensal.value,
    total_anual: totMensal.value * 12,
    qtd_postos: totPostos.value,
    qtd_funcionarios: totProfissionais.value,
    va_total: totVa.value,
    postos: itens.value,
    identificacao: { ...ident, modelo: modelo.value },
  }

  salvando.value = true
  try {
    const { data } = await axios.post("/comercial/propostas", payload)
    ok("Proposta " + data.numero + " salva!")
    ident.numProposta = data.numero
  } catch (e) {
    if (e?.response?.status === 422) {
      const errs = e.response.data?.errors || {}
      const first = Object.values(errs)[0]
      fail(Array.isArray(first) ? first[0] : (e.response.data?.message || "Dados inválidos"))
    } else {
      fail("Falha ao salvar a proposta")
    }
  } finally {
    salvando.value = false
  }
}
// ─── Modal PDF ─────────────────────────────────────────────────────────────────
const modalPdfAberto = ref(false)
const pdfForm = reactive({
  numProposta: "",
  cliente: "",
  destinatario: "",
  objeto: "Prestação de serviços de vigilância/segurança patrimonial armada e desarmada, com fornecimento de mão de obra qualificada, uniformizada e equipada.",
  tituloCct: "",
  data: "",
  cidade: "Brasília",
  responsavel: "Leiliane Carolina",
  cargo: "Gerente de Contratos",
})

function gerarPdf() {
  if (!itens.value.length) { warn("Adicione ao menos um posto antes de gerar o PDF"); return }
  // Pré-preencher modal
  pdfForm.numProposta = ident.numProposta
  pdfForm.cliente = ident.cliente
  pdfForm.destinatario = ident.cliente
  pdfForm.data = formatarDataPT(ident.data)
  pdfForm.tituloCct = ident.cct || "CCT Vigente"
  modalPdfAberto.value = true
}

function formatarDataPT(iso) {
  if (!iso) return new Date().toLocaleDateString("pt-BR", { day: "numeric", month: "long", year: "numeric" })
  const d = new Date(iso + "T12:00:00")
  return d.toLocaleDateString("pt-BR", { day: "numeric", month: "long", year: "numeric" })
}

function confirmarGerarPdf() {
  modalPdfAberto.value = false
  const dados = {
    numero: pdfForm.numProposta,
    cliente: pdfForm.cliente.toUpperCase(),
    destinatario: pdfForm.destinatario,
    data: pdfForm.data,
    cidade: pdfForm.cidade,
    responsavel: pdfForm.responsavel,
    cargo: pdfForm.cargo,
    empresa_razao: "Grupo 5 Estrelas Segurança e Serviços LTDA.",
    objeto: pdfForm.objeto,
    cct_titulo: pdfForm.tituloCct,
    itens: itens.value.map(i => ({
      discriminacao: i.cat + (i.descr ? " — " + i.descr : ""),
      postos: i.qtdPostos,
      efetivo: i.qtdPostos * i.funcPosto,
      unitario: i.unitVal,
      mensal: i.totalMensal,
    })),
    totalMensal: totMensal.value,
  }
  gerarPDFNoBrowser(dados)
}

function gerarPDFNoBrowser(d) {
  const fmtR = (v) => "R$ " + (Number(v) || 0).toFixed(2).replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, ".")

  const CSS = `
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Inter','Calibri',sans-serif; font-size:11pt; color:#333; line-height:1.5; }
@page { size:A4; margin:0; }
.page { width:210mm; min-height:297mm; position:relative; overflow:hidden; page-break-after:always; }
.page:last-child { page-break-after:auto; }

/* Cores institucionais fixas */
:root { --azul-escuro:#0D2B5C; --azul:#2E6DB4; --azul-medio:#1F4E88; --dourado:#C8A84B; --cinza-claro:#EEF3FA; }

/* ─── PÁGINA 1: CAPA ─── */
.capa { background:var(--azul-escuro); color:#fff; display:flex; align-items:center; justify-content:center; }
.capa-content { text-align:center; z-index:2; position:relative; }
.capa::before { content:''; position:absolute; top:0; right:0; width:58%; height:100%; background:var(--azul); clip-path:polygon(20% 0,100% 0,100% 100%,0% 100%); }
.capa::after { content:''; position:absolute; top:0; right:0; width:50%; height:100%; background:var(--azul-medio); clip-path:polygon(30% 0,100% 0,100% 100%,10% 100%); opacity:.5; }
.capa .logo-text { font-size:14pt; font-weight:800; letter-spacing:2px; text-transform:uppercase; margin-bottom:6px; }
.capa .logo-sub { font-size:9pt; letter-spacing:4px; text-transform:uppercase; opacity:.8; margin-bottom:40px; }
.capa h1 { font-size:32pt; font-weight:900; letter-spacing:1px; margin-bottom:16px; }
.capa .estrelas { font-size:28pt; color:var(--dourado); margin-bottom:24px; letter-spacing:6px; }
.capa .num-proposta { font-size:12pt; opacity:.85; border:1px solid rgba(255,255,255,.3); display:inline-block; padding:8px 24px; border-radius:4px; }

/* ─── PÁGINA 2: APRESENTAÇÃO ─── */
.apresentacao { padding:50px 60px; }
.apresentacao .barra-lateral { position:absolute; left:0; top:0; width:4px; height:100%; background:var(--azul); }
.apresentacao .letra-grande { font-size:80pt; font-weight:900; color:var(--azul); opacity:.15; position:absolute; top:30px; right:50px; }
.apresentacao .cliente-destaque { font-size:16pt; font-weight:700; color:var(--azul-escuro); margin-bottom:20px; margin-top:20px; }
.apresentacao h2 { font-size:14pt; color:var(--azul-escuro); margin-bottom:12px; border-bottom:2px solid var(--azul); display:inline-block; padding-bottom:4px; }
.apresentacao p { margin-bottom:10px; text-align:justify; }
.apresentacao .caixa-objeto { background:var(--cinza-claro); border-left:4px solid var(--azul); padding:16px 20px; margin-top:24px; border-radius:0 8px 8px 0; }
.apresentacao .caixa-objeto h3 { font-size:11pt; color:var(--azul-escuro); margin-bottom:8px; }

/* ─── PÁGINA 3: TABELA + CONDIÇÕES ─── */
.tabela-page { padding:50px 60px; }
.tabela-page h2 { font-size:14pt; color:var(--azul-escuro); margin-bottom:16px; }
.tabela-postos { width:100%; border-collapse:collapse; margin-bottom:30px; font-size:10pt; }
.tabela-postos thead th { background:var(--azul-escuro); color:#fff; padding:10px 12px; text-align:left; font-weight:600; }
.tabela-postos thead th:last-child, .tabela-postos thead th:nth-child(4) { text-align:right; }
.tabela-postos tbody td { padding:9px 12px; background:var(--cinza-claro); border-bottom:1px solid #dde5f0; }
.tabela-postos tbody td:last-child, .tabela-postos tbody td:nth-child(4) { text-align:right; font-weight:600; }
.tabela-postos tfoot td { background:var(--azul-escuro); color:#fff; padding:10px 12px; font-weight:700; }
.tabela-postos tfoot td:last-child { text-align:right; }
.condicoes h3 { font-size:11pt; color:var(--azul-escuro); margin-top:20px; margin-bottom:10px; }
.condicoes ol { padding-left:20px; font-size:10pt; }
.condicoes li { margin-bottom:6px; }

/* ─── PÁGINA 4: REPACTUAÇÕES ─── */
.repactuacoes { padding:50px 60px; }
.repactuacoes h2 { font-size:14pt; color:var(--azul-escuro); margin-bottom:12px; }
.repactuacoes h3 { font-size:11pt; color:var(--azul-escuro); margin-top:20px; margin-bottom:10px; }
.repactuacoes ol, .repactuacoes ul { padding-left:20px; font-size:10pt; }
.repactuacoes li { margin-bottom:6px; }
.repactuacoes .iso-box { background:var(--cinza-claro); border:1px solid #dde5f0; border-radius:8px; padding:16px 20px; margin-top:16px; }
.repactuacoes .iso-box strong { color:var(--azul-escuro); }

/* ─── PÁGINA 5: ENCERRAMENTO ─── */
.encerramento { padding:50px 60px; display:flex; flex-direction:column; justify-content:space-between; }
.encerramento .texto-final { margin-bottom:40px; text-align:justify; font-size:10.5pt; }
.encerramento .assinatura { text-align:center; margin-top:40px; }
.encerramento .assinatura .linha { border-top:1px solid #333; width:300px; margin:0 auto 8px; }
.encerramento .assinatura .nome { font-weight:700; font-size:11pt; }
.encerramento .assinatura .cargo-empresa { font-size:9.5pt; color:#555; }
.encerramento .contatos { background:var(--cinza-claro); border-radius:8px; padding:16px 24px; margin-top:auto; display:flex; justify-content:center; gap:30px; font-size:9.5pt; color:#555; flex-wrap:wrap; }
.encerramento .contatos span { display:flex; align-items:center; gap:4px; }
`

  const tabelaRows = d.itens.map(i => `<tr><td>${i.discriminacao}</td><td>${i.postos}</td><td>${i.efetivo}</td><td>${fmtR(i.unitario)}</td><td>${fmtR(i.mensal)}</td></tr>`).join("")
  const totalEfetivo = d.itens.reduce((s, i) => s + i.efetivo, 0)
  const totalPostos = d.itens.reduce((s, i) => s + i.postos, 0)

  const HTML = `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Proposta ${d.numero}</title><style>${CSS}</style></head><body>

<!-- PÁGINA 1 — CAPA -->
<div class="page capa">
  <div class="capa-content">
    <div class="logo-text">GRUPO</div>
    <div class="logo-text" style="font-size:22pt;color:var(--dourado)">5 ESTRELAS</div>
    <div class="logo-sub">Segurança e Serviços</div>
    <h1>PROPOSTA COMERCIAL</h1>
    <div class="estrelas">★★★★★</div>
    <div class="num-proposta">Proposta ${d.numero}</div>
  </div>
</div>

<!-- PÁGINA 2 — APRESENTAÇÃO -->
<div class="page apresentacao">
  <div class="barra-lateral"></div>
  <div class="letra-grande">A</div>
  <h2>1. Apresentação</h2>
  <div class="cliente-destaque">${d.cliente}</div>
  <p>O <strong>Grupo 5 Estrelas Segurança e Serviços</strong>, empresa especializada na prestação de serviços de vigilância patrimonial armada e desarmada, segurança pessoal privada, escolta armada, segurança eletrônica e serviços de apoio, vem apresentar proposta comercial para prestação de serviços de segurança.</p>
  <p>Sede: SAAN Quadra 03, Lote 420, Sala 01 – Brasília/DF – CEP 70.632-300.</p>
  <p>Filiais: Cuiabá/MT · Goiânia/GO · Unaí/MG · São Paulo/SP.</p>
  <p>Autorização de funcionamento nº 1762/2018-DELESP/DREX/SR/DPF/DF, publicada no D.O.U.</p>
  <div class="caixa-objeto">
    <h3>Objeto</h3>
    <p>${d.objeto}</p>
  </div>
</div>

<!-- PÁGINA 3 — TABELA + CONDIÇÕES -->
<div class="page tabela-page">
  <h2>1.1 Quadro de Postos e Valores</h2>
  <table class="tabela-postos">
    <thead><tr><th>Discriminação</th><th>Qtde Postos</th><th>Efetivo</th><th>Valor Unitário</th><th>Valor Mensal</th></tr></thead>
    <tbody>${tabelaRows}</tbody>
    <tfoot><tr><td>TOTAL</td><td>${totalPostos}</td><td>${totalEfetivo}</td><td></td><td>${fmtR(d.totalMensal)}</td></tr></tfoot>
  </table>
  <div class="condicoes">
    <h3>2. Condições Gerais</h3>
    <ol>
      <li>A presente proposta tem validade de <strong>90 (noventa) dias</strong> a contar da data de emissão.</li>
      <li>Os profissionais serão rigorosamente selecionados, treinados e habilitados conforme legislação vigente.</li>
      <li>Não há vínculo empregatício entre os funcionários da CONTRATADA e a CONTRATANTE.</li>
      <li>Em caso de afastamento, a CONTRATADA providenciará substituição em até 2 horas.</li>
      <li>Os valores estão vinculados à ${d.cct_titulo}, podendo ser reajustados na data-base da categoria.</li>
      <li>O faturamento ocorrerá até o 20º dia do mês subsequente à prestação dos serviços, com multa de 2% ao mês e juros de 0,16% ao dia em caso de atraso.</li>
    </ol>
  </div>
</div>

<!-- PÁGINA 4 — REPACTUAÇÕES + ISO -->
<div class="page repactuacoes">
  <h2>3. Repactuações / Reequilíbrio Econômico-Financeiro</h2>
  <p>Os valores poderão ser repactuados nas seguintes hipóteses:</p>
  <ol>
    <li><strong>Salários e benefícios:</strong> reajuste conforme Convenção Coletiva de Trabalho com data-base em janeiro/2027.</li>
    <li><strong>Insumos:</strong> reajuste pelo INPC/IBGE acumulado dos últimos 12 meses.</li>
    <li><strong>Vale-transporte:</strong> reajuste conforme reajuste da tarifa de transporte público.</li>
    <li><strong>Encargos sociais:</strong> alteração por ato governamental que modifique alíquotas ou crie novos encargos.</li>
  </ol>
  <h3>4. Considerações Finais</h3>
  <p>O Grupo 5 Estrelas possui Sistema de Gestão Integrado com as certificações:</p>
  <div class="iso-box">
    <p><strong>ISO 9001</strong> (Qualidade) · <strong>ISO 14001</strong> (Meio Ambiente) · <strong>ISO 45001</strong> (Saúde e Segurança) · <strong>ISO 37001</strong> (Antissuborno) · <strong>ISO 37301</strong> (Compliance) · <strong>ISO 18788</strong> (Segurança Privada)</p>
    <p style="margin-top:8px;font-size:9.5pt;color:#555">Última auditoria de manutenção realizada em 12/03/2026.</p>
  </div>
</div>

<!-- PÁGINA 5 — ENCERRAMENTO -->
<div class="page encerramento">
  <div class="texto-final">
    <p>Comprometemo-nos a fornecer serviços de alta qualidade, com profissionais treinados e equipados, garantindo a segurança patrimonial e pessoal de nossos clientes. Estamos à disposição para esclarecimentos adicionais e adequações que se façam necessárias.</p>
    <p style="margin-top:16px">${d.cidade}, ${d.data}.</p>
  </div>
  <div class="assinatura">
    <div class="linha"></div>
    <div class="nome">${d.responsavel}</div>
    <div class="cargo-empresa">${d.cargo}<br>${d.empresa_razao}</div>
  </div>
  <div class="contatos">
    <span>📞 (61) 3234-5678</span>
    <span>✉ comercial@grupo5estrelas.com.br</span>
    <span>🌐 www.grupo5estrelas.com.br</span>
    <span>@ @grupo5estrelas</span>
  </div>
</div>

</body></html>`

  const blob = new Blob([HTML], { type: "text/html;charset=utf-8" })
  const url = URL.createObjectURL(blob)
  window.open(url, "_blank")
  ok("Proposta PDF aberta em nova aba — use Ctrl+P para imprimir/salvar como PDF")
}

// ─── Exportar XLSX ─────────────────────────────────────────────────────────────
function exportarXlsx() {
  if (!itens.value.length) { warn("Adicione ao menos um posto antes de exportar"); return }
  try {

  const wb = XLSX.utils.book_new()

  // ─── Aba "Identificação" ──────────────────────────────────────────────────────
  const identData = [
    ["Campo", "Valor"],
    ["Nº Proposta", ident.numProposta],
    ["Cliente", ident.cliente],
    ["Empresa", empresas.find(e => e.value === ident.empresa)?.label || ident.empresa],
    ["Data", ident.data],
    ["CCT", ident.cct],
    ["Modelo", modelo.value === "5estrelas" ? "Modelo 5 Estrelas" : "Modelo IN 05"],
    ["Periodicidade", ident.periodicidade],
  ]
  const wsIdent = XLSX.utils.aoa_to_sheet(identData)
  wsIdent["!cols"] = [{ wch: 16 }, { wch: 40 }]
  XLSX.utils.book_append_sheet(wb, wsIdent, "Identificação")

  // ─── Aba "Resumo" ────────────────────────────────────────────────────────────
  const resumoHeader = ["Discriminação", "Escala", "Qtd Postos", "Func/Posto", "Custo Unitário", "Total Mensal"]
  const resumoRows = itens.value.map(i => [
    i.cat + (i.descr ? " — " + i.descr : ""),
    i.escala,
    i.qtdPostos,
    i.funcPosto,
    i.unitVal,
    i.totalMensal,
  ])
  resumoRows.push(["TOTAL GERAL", "", totPostos.value, totProfissionais.value, "", totMensal.value])
  const wsResumo = XLSX.utils.aoa_to_sheet([resumoHeader, ...resumoRows])
  wsResumo["!cols"] = [{ wch: 30 }, { wch: 18 }, { wch: 12 }, { wch: 12 }, { wch: 16 }, { wch: 16 }]
  XLSX.utils.book_append_sheet(wb, wsResumo, "Resumo")

  // ─── Aba "Composição" ────────────────────────────────────────────────────────
  const compRows = []
  if (modelo.value === "5estrelas") {
    compRows.push(["MODELO 5 ESTRELAS — Composição Detalhada"])
    compRows.push([])
    compRows.push(["─── TURNO DIURNO ───"])
    compRows.push(["Horário", f5.horarioDiurno])
    compRows.push(["Nº Funcionários", f5.qtdDiurno])
    compRows.push(["Salário", f5.salDiurno])
    compRows.push(["Periculosidade", c5.value.pericD])
    compRows.push(["Ad. Noturno", c5.value.adnD])
    compRows.push(["Intrajornada", c5.value.intraD])
    compRows.push(["Total Turno Diurno", c5.value.totD])
    compRows.push([])
    compRows.push(["─── TURNO NOTURNO ───"])
    compRows.push(["Horário", f5.horarioNoturno])
    compRows.push(["Nº Funcionários", f5.qtdNoturno])
    compRows.push(["Salário", f5.salNoturno])
    compRows.push(["Periculosidade", c5.value.pericN])
    compRows.push(["Ad. Noturno", c5.value.adnN])
    compRows.push(["Intrajornada", c5.value.intraN])
    compRows.push(["Total Turno Noturno", c5.value.totN])
    compRows.push([])
    compRows.push(["─── MÓDULO 01 — Remuneração ───"])
    compRows.push(["Total Funcionários", c5.value.totalFunc])
    compRows.push(["Encargos (%)", f5.encargos])
    compRows.push(["Valor Encargos", c5.value.encVal])
    compRows.push(["TOTAL Módulo 01", c5.value.m1])
    compRows.push([])
    compRows.push(["─── MÓDULO 02 — Benefícios ───"])
    compRows.push(["Item", "Unitário", "Total"])
    compRows.push(["Uniforme", f5.b_uniforme, c5.value.bt.uniforme])
    compRows.push(["Plano de Saúde", f5.b_saude, c5.value.bt.saude])
    compRows.push(["Fundo Social/Odonto", f5.b_fundo, c5.value.bt.fundo])
    compRows.push(["Saúde Ocupacional", f5.b_sst, c5.value.bt.sst])
    compRows.push(["Contrib. Negocial", f5.b_cna, c5.value.bt.cna])
    compRows.push(["Seguro de Vida", f5.b_seguro, c5.value.bt.seguro])
    compRows.push(["Guia Tráfego Arm.", f5.b_gta, c5.value.bt.gta])
    compRows.push(["Cofre", f5.b_cofre, c5.value.bt.cofre])
    compRows.push(["Armamento", f5.b_arma, c5.value.bt.arma])
    compRows.push(["Reciclagem", f5.b_reciclag, c5.value.bt.reciclag])
    compRows.push(["Vale Transporte", f5.b_vt, c5.value.bt.vt])
    compRows.push(["VA (Alimentação)", f5.b_va, c5.value.bt.va])
    compRows.push(["TOTAL Módulo 02", "", c5.value.m2])
    compRows.push([])
    compRows.push(["─── MÓDULO 03 — Custos Indiretos, Tributos e Lucro ───"])
    compRows.push(["Administração (%)", f5.pctAdm, c5.value.vAdm])
    compRows.push(["Lucro (%)", f5.pctLucro, c5.value.vLucro])
    compRows.push(["Impostos (%)", f5.pctImpostos, c5.value.vImp])
    compRows.push(["TOTAL Módulo 03", "", c5.value.m3])
    compRows.push([])
    compRows.push(["═══ TOTAL GERAL MENSAL ═══", "", c5.value.grandTotal])
  } else {
    compRows.push(["MODELO IN 05 — Composição Detalhada"])
    compRows.push([])
    compRows.push(["─── MÓDULO 1 — Remuneração ───"])
    compRows.push(["Item", "%", "Valor"])
    compRows.push(["Salário Base", "", n(fin.sal)])
    compRows.push(["Periculosidade", fin.peric_pct, cin.value.peric])
    compRows.push(["Insalubridade", fin.insal_pct, cin.value.insal])
    compRows.push(["Adicional Noturno", fin.an_pct, cin.value.anVal])
    compRows.push(["Hora Noturna Reduzida", fin.hnr_pct, cin.value.hnrVal])
    compRows.push(["Outros", fin.outros1_pct, cin.value.out1])
    compRows.push(["TOTAL Módulo 1", "", cin.value.m1])
    compRows.push([])
    compRows.push(["─── MÓDULO 2.1 — 13º, Férias ───"])
    compRows.push(["13º Salário", (cin.value.p13 * 100).toFixed(4) + "%", cin.value.v13])
    compRows.push(["Férias + 1/3", (cin.value.pFer * 100).toFixed(4) + "%", cin.value.vFer])
    compRows.push(["Incidência 2.2 s/ 2.1", (cin.value.pInc21 * 100).toFixed(4) + "%", cin.value.vInc21])
    compRows.push(["Multa FGTS s/ 13º e Férias", (cin.value.pMultaFgts * 100).toFixed(4) + "%", cin.value.vMultaFgts])
    compRows.push(["Total 2.1", "", cin.value.sub21])
    compRows.push([])
    compRows.push(["─── MÓDULO 2.2 — GPS, FGTS ───"])
    compRows.push(["INSS", fin.inss_pct, cin.value.inss])
    compRows.push(["Sal. Educação", fin.saledu_pct, cin.value.saledu])
    compRows.push(["SAT (RAT×FAP)", fin.sat_pct, cin.value.sat])
    compRows.push(["SESC/SESI", fin.sesc_pct, cin.value.sesc])
    compRows.push(["SENAI/SENAC", fin.senai_pct, cin.value.senai])
    compRows.push(["SEBRAE", fin.sebrae_pct, cin.value.sebrae])
    compRows.push(["INCRA", fin.incra_pct, cin.value.incra])
    compRows.push(["FGTS", fin.fgts_pct, cin.value.fgts])
    compRows.push(["Total 2.2", "", cin.value.sub22])
    compRows.push([])
    compRows.push(["─── MÓDULO 2.3 — Benefícios ───"])
    compRows.push(["Vale Transporte (líq.)", fin.vt_dia + "/dia", cin.value.vtLiq])
    compRows.push(["VA/Alimentação", fin.va_dia + "/dia", cin.value.vaVal])
    compRows.push(["Assistência Médica", "", cin.value.medico])
    compRows.push(["Assist. Odontológica", "", cin.value.odonto])
    compRows.push(["Cesta Básica", "", cin.value.cesta])
    compRows.push(["Seguro de Vida", "", cin.value.seguro])
    compRows.push(["PMQ", "", cin.value.pmq])
    compRows.push(["Outros", "", cin.value.out23])
    compRows.push(["Total 2.3", "", cin.value.sub23])
    compRows.push(["TOTAL Módulo 2", "", cin.value.m2])
    compRows.push([])
    compRows.push(["─── MÓDULO 3 — Rescisão ───"])
    compRows.push(["Aviso Prévio Inden.", fin.avisoind_pct, cin.value.vAvisoInd])
    compRows.push(["FGTS s/ Aviso", "", cin.value.vFgtsAviso])
    compRows.push(["Aviso Prévio Trab.", fin.avistrab_pct, cin.value.vAvisTrab])
    compRows.push(["Multa FGTS Inden.", "", cin.value.vMultaInd])
    compRows.push(["Multa FGTS Rescisão", "", cin.value.vMultaResc])
    compRows.push(["Inc. GPS/FGTS Trab.", "", cin.value.vIncGPS])
    compRows.push(["TOTAL Módulo 3", "", cin.value.m3])
    compRows.push([])
    compRows.push(["─── MÓDULO 4 — Reposição Ausente ───"])
    compRows.push(["Cobertura Férias", "", cin.value.vCobFer])
    compRows.push(["Ausências Legais", fin.ausleg_pct, cin.value.vAusleg])
    compRows.push(["Lic. Paternidade", fin.paterni_pct, cin.value.vPatern])
    compRows.push(["Acidente de Trabalho", fin.acident_pct, cin.value.vAcident])
    compRows.push(["Afast. Maternidade", fin.matern_pct, cin.value.vMatern])
    compRows.push(["Incidências", "", cin.value.vIncAus])
    compRows.push(["Intrajornada", "", cin.value.m4intra])
    compRows.push(["TOTAL Módulo 4", "", cin.value.m4])
    compRows.push([])
    compRows.push(["─── MÓDULO 5 — Insumos ───"])
    compRows.push(["Uniformes", "", fin.uniforme])
    compRows.push(["Materiais", "", fin.materiais])
    compRows.push(["Ferramental", "", fin.ferramental])
    compRows.push(["EPIs", "", fin.epi])
    compRows.push(["Treinamento", "", fin.treinamento])
    compRows.push(["SSO", "", fin.sso])
    compRows.push(["TOTAL Módulo 5", "", cin.value.m5])
    compRows.push([])
    compRows.push(["─── MÓDULO 6 — Custos Ind./Trib./Lucro ───"])
    compRows.push(["Custos Indiretos", fin.custoind_pct, cin.value.vCind])
    compRows.push(["Lucro", fin.lucro_pct, cin.value.vLucro])
    compRows.push(["ISS", fin.iss_pct, cin.value.vISS])
    compRows.push(["PIS", fin.pis_pct, cin.value.vPIS])
    compRows.push(["COFINS", fin.cofins_pct, cin.value.vCOFINS])
    compRows.push(["TOTAL Módulo 6", "", cin.value.m6])
    compRows.push([])
    compRows.push(["═══ PREÇO POR EMPREGADO ═══", "", cin.value.precoEmp])
  }
  const wsComp = XLSX.utils.aoa_to_sheet(compRows)
  wsComp["!cols"] = [{ wch: 36 }, { wch: 16 }, { wch: 16 }]
  XLSX.utils.book_append_sheet(wb, wsComp, "Composição")

  // ─── Gerar arquivo ──────────────────────────────────────────────────────────
  const dataStr = ident.data ? ident.data.replace(/-/g, "") : new Date().toISOString().slice(0, 10).replace(/-/g, "")
  const numLimpo = (ident.numProposta || "000").replace(/[^0-9]/g, "") || "000"
  const filename = `Cotacao_${numLimpo}_${dataStr}.xlsx`
  XLSX.writeFile(wb, filename)
  ok(`Planilha "${filename}" exportada com sucesso!`)
  } catch (e) {
    console.error("Erro ao exportar XLSX:", e)
    fail("Erro ao gerar a planilha. Verifique o console.")
  }
}
// ─── Importar Planilha (round-trip do nosso Exportar XLSX) ──────────────────────
// Lê um arquivo gerado por esta tela (aba "Resumo" + "Identificação") e reconstrói
// a lista de postos do resumo. Observação: o VA por posto não está na aba Resumo,
// então é reimportado como 0 (os totais recalculam).
function impNum(v) {
  if (v === null || v === undefined || v === "") return 0
  if (typeof v === "number") return v
  const s = String(v).replace(/[R$\s]/g, "").replace(/\./g, "").replace(",", ".")
  const n = parseFloat(s)
  return isNaN(n) ? 0 : n
}

function importarPlanilha(ev) {
  const file = ev?.target?.files?.[0]
  if (ev?.target) ev.target.value = ""
  if (!file) return
  if (!/\.xlsx?$/i.test(file.name)) { fail("Envie um arquivo .xlsx ou .xls"); return }

  const reader = new FileReader()
  reader.onload = (e) => {
    try {
      const wb = XLSX.read(new Uint8Array(e.target.result), { type: "array" })
      const wsResumo = wb.Sheets["Resumo"]
      if (!wsResumo) {
        fail("Planilha sem aba 'Resumo'. Use um arquivo exportado por esta tela.")
        return
      }

      const rows = XLSX.utils.sheet_to_json(wsResumo, { header: 1, blankrows: false })
      const novos = []
      let seq = 1
      for (let r = 1; r < rows.length; r++) {
        const row = rows[r] || []
        const disc = String(row[0] ?? "").trim()
        if (!disc || /^total geral$/i.test(disc)) continue
        const partes = disc.split(" — ")
        const cat = (partes.shift() || "").trim()
        const qtdPostos = Math.max(1, parseInt(impNum(row[2])) || 1)
        const unitVal = impNum(row[4])
        novos.push({
          id: seq++,
          cat,
          catIcone: "user",
          escala: String(row[1] ?? "").trim(),
          funcPosto: Math.max(1, parseInt(impNum(row[3])) || 1),
          qtdPostos,
          descr: partes.join(" — ").trim(),
          unitVal,
          totalMensal: impNum(row[5]) || unitVal * qtdPostos,
          vaUnit: 0,
          modelo: modelo.value,
        })
      }

      if (!novos.length) {
        fail("Nenhum posto encontrado na aba 'Resumo'.")
        return
      }

      // Identificação (opcional): re-hidrata os campos do cabeçalho.
      const wsId = wb.Sheets["Identificação"] || wb.Sheets["Identificacao"]
      if (wsId) {
        const map = {}
        XLSX.utils.sheet_to_json(wsId, { header: 1, blankrows: false }).forEach((r) => {
          if (r && r[0] != null) map[String(r[0]).trim().toLowerCase()] = r[1]
        })
        const g = (k) => (map[k] != null ? String(map[k]) : "")
        if (g("cliente")) ident.cliente = g("cliente")
        if (g("nº proposta")) ident.numProposta = g("nº proposta")
        if (g("cct")) ident.cct = g("cct")
        if (g("periodicidade")) ident.periodicidade = g("periodicidade")
        if (/^\d{4}-\d{2}-\d{2}/.test(g("data"))) ident.data = g("data").slice(0, 10)
        if (g("empresa")) {
          const emp = empresas.find((x) => x.label === g("empresa") || x.value === g("empresa"))
          if (emp) ident.empresa = emp.value
        }
        if (g("modelo")) modelo.value = /in\s*0?5/i.test(g("modelo")) ? "in05" : "5estrelas"
      }

      itens.value = novos
      _idSeq = novos.length + 1
      ok(`Planilha importada — ${novos.length} posto(s) no resumo`)
    } catch (err) {
      console.error(err)
      fail("Erro ao ler a planilha: " + (err?.message || err))
    }
  }
  reader.readAsArrayBuffer(file)
}

// ─── Carga inicial ──────────────────────────────────────────────────────────────
async function carregar() {
  try {
    const { data } = await axios.get("/comercial/cotacao/dados")
    ccts.value = data.ccts || []
    escalas.value = data.escalas || []
    categorias.value = data.categorias || []
    indices.value = data.indices || {}
    // Defaults: Vigilante + escala 24h (func_por_posto 4), como no protótipo
    catSel.value = categorias.value.find((c) => /vigilante/i.test(c.nome)) || categorias.value[0] || null
    escSel.value = escalas.value.find((e) => (e.nome || "").includes("24")) || escalas.value[0] || null
    aplicar()

    // Reabrir proposta existente (?proposta={id}) — restaura o resumo.
    if (props.propostaInicial) {
      hidratarProposta(props.propostaInicial)
    }
  } catch (e) {
    fail("Falha ao carregar dados da cotação")
  }
}
onMounted(carregar)

/**
 * Re-hidrata a cotação a partir de uma proposta salva (1:1 com o snapshot do salvar):
 * identificação + modelo + lista de postos do resumo. Postos só existem quando a
 * proposta foi gerada na plataforma (da_cotacao); caso contrário só pré-preenche
 * a identificação, como o abrirCotacaoDaProposta do protótipo.
 */
function hidratarProposta(p) {
  const idt = p.identificacao && typeof p.identificacao === "object" ? p.identificacao : {}
  ident.numProposta = p.numero || idt.numProposta || ident.numProposta
  ident.cliente = p.cliente ?? idt.cliente ?? ""
  ident.empresa = p.empresa ?? idt.empresa ?? ident.empresa
  ident.cct = p.cct ?? idt.cct ?? ident.cct
  ident.periodicidade = p.periodicidade ?? idt.periodicidade ?? ident.periodicidade
  ident.data = p.data_proposta ?? idt.data ?? ident.data

  if (p.modelo === "in05" || p.modelo === "5estrelas") {
    modelo.value = p.modelo
  }

  if (Array.isArray(p.postos) && p.postos.length) {
    itens.value = p.postos.map((x) => ({ ...x }))
    _idSeq = Math.max(0, ...itens.value.map((i) => Number(i.id) || 0)) + 1
    ok(`Proposta ${p.numero || ""} reaberta — ${itens.value.length} posto(s) no resumo`)
  } else {
    ok(`Proposta ${p.numero || ""} aberta na cotação`)
  }
}
</script>


<template>
  <AuthenticatedLayout>
    <Toast />
    <div class="g360">
      <div class="view active" id="view-cotacao">

        <!-- ── Cabeçalho (fixo no topo ao rolar) ── -->
        <div class="page-title-row" style="position:sticky;top:0;z-index:30;background:#F0F2F7;padding-top:8px;padding-bottom:14px;margin-bottom:14px;box-shadow:0 6px 12px -8px rgba(0,0,0,.18);flex-wrap:wrap;gap:10px">
          <div>
            <div class="section-title">Nova Cotação de Custos</div>
            <div class="section-desc" id="cotacao-desc">Configure os postos e adicione ao resumo da proposta</div>
          </div>
          <div style="display:flex;gap:10px;align-items:center">
            <label style="display:flex;align-items:center;gap:6px;padding:7px 14px;border:1px solid var(--brand-border-soft);border-radius:var(--radius-sm);cursor:pointer;font-size:13px;color:var(--text-secondary);font-family:inherit;background:transparent"
              title="Importar planilha existente para preencher a cotação">
              <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 2h7l3 3v9H3z"/><path d="M10 2v4h3M8 7v5M6 10l2 2 2-2"/></svg>
              Importar Planilha
              <input type="file" accept=".xlsx,.xls" dusk="cot-importar" style="display:none" @change="importarPlanilha">
            </label>
            <button class="btn btn-ghost" @click="exportarXlsx()">↓ Exportar XLSX</button>
            <button class="btn btn-ghost" @click="gerarPdf()" style="display:flex;align-items:center;gap:6px">
              <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 2h7l3 3v9a1 1 0 01-1 1H3a1 1 0 01-1-1V3z"/><path d="M10 2v4h3"/><path d="M5 9h6M5 12h4"/></svg>
              Gerar Proposta PDF
            </button>
            <button class="btn btn-gold" dusk="btn-salvar-proposta" @click="salvarProposta()">Salvar Proposta</button>
          </div>
        </div>

        <!-- ── LAYOUT PRINCIPAL: Configurador + Resumo ── -->
        <div class="cot-layout">

          <!-- COLUNA ESQUERDA -->
          <div>

            <!-- Identificação da Proposta -->
            <div class="module-card" style="margin-bottom:16px">
              <div class="module-header">
                <div class="module-title" style="color:var(--text-secondary)">Identificação da Proposta</div>
              </div>
              <div class="module-body">
                <div class="form-grid-4">
                  <div class="form-group">
                    <label class="form-label" style="display:flex;align-items:center;gap:6px">
                      Nº Proposta
                      <span style="font-size:10px;color:var(--brand-gold);font-weight:600;background:color-mix(in srgb, var(--app-primary) 10%, transparent);padding:1px 7px;border-radius:99px">AUTO</span>
                    </label>
                    <div style="display:flex;gap:6px;align-items:center">
                      <input type="text" class="form-input readonly" id="num-proposta" v-model="ident.numProposta" :readonly="!numEditavel"
                        style="font-family:'Syne',sans-serif;font-weight:800;font-size:16px;color:var(--brand-gold);flex:1">
                      <button @click="editarNumProposta()" title="Editar número manualmente"
                        style="background:transparent;border:1px solid var(--brand-border-soft);border-radius:6px;padding:0 10px;height:38px;color:var(--text-muted);cursor:pointer;font-size:13px;flex-shrink:0"><svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M11 2l3 3-9 9H2v-3l9-9z"/></svg></button>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Data</label>
                    <input type="date" class="form-input" id="data-proposta" v-model="ident.data">
                  </div>
                  <div class="form-group" style="grid-column:span 2">
                    <label class="form-label">Cliente</label>
                    <input type="text" class="form-input" id="cliente" dusk="cot-cliente" v-model="ident.cliente" placeholder="Nome do cliente">
                  </div>
                </div>
                <div class="form-grid-3">
                  <div class="form-group">
                    <label class="form-label">Empresa / CNPJ</label>
                    <select class="form-select" id="cotacao-empresa" v-model="ident.empresa">
                      <option v-for="e in empresas" :key="e.value" :value="e.value">{{ e.label }}</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">CCT Vigente</label>
                    <input type="text" class="form-input" id="cct" v-model="ident.cct" placeholder="2024/2025">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Modelo de Planilha</label>
                    <select class="form-select" id="modelo-select" v-model="modelo">
                      <option value="5estrelas">Modelo 5 Estrelas</option>
                      <option value="in05">Modelo IN 05</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Periodicidade</label>
                    <select class="form-select" v-model="ident.periodicidade">
                      <option>Mensal</option>
                      <option>Anual</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- Configurador de posto -->
            <div class="module-card" style="margin-bottom:16px">
              <div class="module-header">
                <div class="module-num" style="background:var(--blue);color:#fff">+</div>
                <div class="module-title">Configurar Posto</div>
                <div style="margin-left:auto;font-size:12px;color:var(--text-muted)">Configure e adicione ao resumo →</div>
              </div>
              <div class="module-body">

                <!-- Seletor categoria + escala -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">
                  <!-- Categoria -->
                  <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:8px">Categoria</div>
                    <div style="display:flex;flex-direction:column;gap:5px" id="cat-btns">
                      <button v-for="c in categorias" :key="c.id" @click="selecionarCategoria(c)"
                        :style="{ display:'flex', alignItems:'center', gap:'8px', padding:'8px 12px', borderRadius:'8px', border:'1px solid ' + (catSel?.id===c.id ? 'var(--brand-gold)' : 'var(--brand-border-soft)'), background: catSel?.id===c.id ? 'color-mix(in srgb, var(--app-primary) 10%, transparent)' : 'transparent', cursor:'pointer', fontFamily:'inherit', fontSize:'12px', color: catSel?.id===c.id ? 'var(--brand-gold)' : 'var(--text-secondary)', textAlign:'left', fontWeight: catSel?.id===c.id ? '600' : '400' }">
                        <span class="cat-icon" v-html="icon(c.icone)"></span>
                        <span style="flex:1">{{ c.nome }}</span>
                        <label v-if="c.tem_moto && catSel?.id===c.id" @click.stop
                          style="display:flex;align-items:center;gap:4px;font-size:10px;color:var(--text-muted);cursor:pointer;white-space:nowrap">
                          <input type="checkbox" :checked="motoSel" @change="toggleMoto(c)" style="accent-color:var(--brand-gold)"> Moto
                        </label>
                      </button>
                    </div>
                  </div>
                  <!-- Escala -->
                  <div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:8px">Escala</div>
                    <div style="display:flex;flex-direction:column;gap:5px" id="esc-btns">
                      <button v-for="e in escalas" :key="e.id" @click="selecionarEscala(e)"
                        :style="{ display:'flex', alignItems:'flex-start', gap:'10px', padding:'10px 12px', borderRadius:'8px', border:'1.5px solid ' + (escSel?.id===e.id ? 'var(--brand-gold)' : 'var(--brand-border-soft)'), background: escSel?.id===e.id ? 'color-mix(in srgb, var(--app-primary) 10%, transparent)' : 'transparent', cursor:'pointer', fontFamily:'inherit', color: escSel?.id===e.id ? 'var(--brand-gold)' : 'var(--text-secondary)', textAlign:'left', width:'100%' }">
                        <span class="cat-icon" style="margin-top:1px" v-html="icon(escIcone(e))"></span>
                        <div style="flex:1;min-width:0">
                          <div style="font-weight:700;font-size:12px">{{ e.nome }}</div>
                          <div style="font-size:10px;opacity:.7;margin-top:2px;line-height:1.4">{{ escDescricao(e) }}</div>
                          <div v-if="escSel?.id===e.id" style="margin-top:6px;display:flex;flex-direction:column;gap:3px">
                            <div v-for="(r, ri) in escRevez(e)" :key="ri" style="display:flex;align-items:center;gap:6px;font-size:10px">
                              <span style="background:color-mix(in srgb, var(--app-primary) 15%, transparent);color:var(--brand-gold);padding:1px 6px;border-radius:4px;font-weight:700;white-space:nowrap">{{ r.slot }}</span>
                              <span style="color:var(--text-muted)">{{ r.dias }}</span>
                              <span style="color:var(--text-muted);opacity:.6">{{ r.ex }}</span>
                            </div>
                          </div>
                        </div>
                        <div style="text-align:right;flex-shrink:0">
                          <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:14px" :style="{ color: escSel?.id===e.id ? 'var(--brand-gold)' : 'var(--text-secondary)' }">{{ e.func_por_posto }}</div>
                          <div style="font-size:9px;opacity:.6">func/posto</div>
                        </div>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Qtd de postos + descrição local -->
                <div style="display:grid;grid-template-columns:120px 1fr;gap:12px;margin-bottom:16px">
                  <div class="form-group">
                    <label class="form-label">Qtd de Postos</label>
                    <input type="number" class="form-input" id="qtd-postos" v-model.number="qtdPostos" min="1" style="font-size:16px;font-weight:700;text-align:center">
                  </div>
                  <div class="form-group">
                    <label class="form-label">Descrição / Localização (opcional)</label>
                    <input type="text" class="form-input" id="posto-descricao" v-model="postoDescricao" placeholder="Ex: Portaria Principal, Bloco A...">
                  </div>
                </div>

                <!-- Banner valores aplicados -->
                <div v-if="bannerVisivel && cat?.nome && escala?.nome" id="valores-banner"
                  style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(46,139,87,0.08);border:1px solid rgba(46,139,87,0.25);border-radius:var(--radius-sm);margin-bottom:14px;font-size:12px;color:var(--text-secondary)">
                  <span class="cat-icon sm"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l4 4 6-7"/></svg></span>
                  <span>
                    <strong style="color:var(--brand-gold)">{{ cat.nome }}</strong>
                    <span v-if="motoSel && cat.tem_moto" style="background:rgba(41,128,185,.15);color:var(--blue);padding:1px 8px;border-radius:99px;font-size:11px;font-weight:600;margin-left:4px">Motorizado</span>
                    — Escala <strong style="color:var(--text-primary)">{{ escala.nome }}</strong> —
                    <strong>{{ funcPorPosto }}</strong> func./posto | <strong>{{ diasMes }}</strong>d/mês
                  </span>
                  <a href="/comercial/configuracoes" style="margin-left:auto;background:transparent;border:1px solid var(--brand-border);border-radius:6px;padding:4px 12px;color:var(--brand-gold);font-size:12px;cursor:pointer;font-family:inherit;text-decoration:none;white-space:nowrap">Ajustar valores ›</a>
                </div>

                <!-- Resumo do custo calculado (preview) -->
                <div style="background:var(--brand-surface);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:14px;display:grid;grid-template-columns:repeat(4,1fr);gap:12px" id="calc-preview">
                  <div>
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:4px">Func./posto</div>
                    <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:18px" id="prev-func">{{ prevFunc }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:4px">Custo unitário</div>
                    <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:18px;color:var(--brand-gold)" id="prev-unit">{{ fmt(grandTotal) }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:4px">Qtd postos</div>
                    <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:18px" id="prev-qtd">{{ qtdPostos }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:4px">Total mensal</div>
                    <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:18px;color:var(--green)" id="prev-total">{{ fmt(grandTotal * (parseInt(qtdPostos) || 0)) }}</div>
                  </div>
                </div>

                <button class="btn btn-gold" dusk="btn-adicionar-posto" style="width:100%;justify-content:center;padding:12px" @click="adicionarItem()">
                  + Adicionar este posto ao resumo
                </button>
              </div>
            </div>

            <!-- Toggle detalhes -->
            <div style="margin-bottom:8px">
              <button @click="toggleDetalhes()" style="background:transparent;border:none;color:var(--text-muted);font-size:12px;cursor:pointer;display:flex;align-items:center;gap:6px;font-family:inherit;padding:0">
                <span id="icone-detalhes">{{ detalhesAbertos ? '▼' : '▶' }}</span> Ver / editar detalhes do cálculo
              </button>
            </div>

            <div id="form-detalhes" v-show="detalhesAbertos">

              <!-- ════════ MODELO 5 ESTRELAS ════════ -->
              <div id="form-5estrelas" v-show="modelo === '5estrelas'">

                <!-- MÓDULO 01 -->
                <div class="module-card" style="margin-bottom:16px">
                  <div class="module-header">
                    <div class="module-num">1</div>
                    <div class="module-title">Módulo 01 — Composição da Remuneração</div>
                    <div class="module-total" id="total-m1">{{ fmt(c5.m1) }}</div>
                  </div>
                  <div class="module-body">
                    <div class="turnos-grid">
                      <!-- Turno Diurno -->
                      <div class="turno-card">
                        <div class="turno-title"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="3"/><path d="M8 1v2M8 13v2M1 8h2M13 8h2M3.2 3.2l1.4 1.4M11.4 11.4l1.4 1.4M11.4 4.6l-1.4 1.4M4.6 11.4l-1.4 1.4"/></svg> Turno Diurno</div>
                        <div class="form-inline-row" style="grid-template-columns:2fr 1fr 1fr 1fr 1fr">
                          <div><div class="form-inline-label">Horário</div>
                            <input class="form-input" id="horario-diurno" type="text" v-model="f5.horarioDiurno" style="font-size:13px"></div>
                          <div><div class="form-inline-label">Nº Func.</div>
                            <input class="form-input" id="qtd-diurno" type="number" v-model.number="f5.qtdDiurno" min="0" style="font-size:13px"></div>
                          <div><div class="form-inline-label">Salário</div>
                            <input class="form-input" id="sal-diurno" type="number" v-model.number="f5.salDiurno" step="0.01" style="font-size:13px"></div>
                          <div><div class="form-inline-label">Função</div>
                            <input class="form-input funcao-label-turno" :value="f5.funcao" readonly style="font-size:13px"></div>
                          <div><div class="form-inline-label">AN?</div>
                            <select class="form-select" id="an-diurno" v-model="f5.anDiurno" style="font-size:13px;padding:10px 8px">
                              <option value="0">Não</option><option value="1">Sim</option>
                            </select></div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:8px">
                          <div class="form-group"><label class="form-label" style="font-size:10px">Periculosidade</label><input class="form-input readonly" :value="fmt(c5.pericD)" readonly style="font-size:13px"></div>
                          <div class="form-group"><label class="form-label" style="font-size:10px">Intrajornada</label><input class="form-input readonly" :value="fmt(c5.intraD)" readonly style="font-size:13px"></div>
                          <div class="form-group"><label class="form-label" style="font-size:10px">Total Turno</label><input class="form-input readonly" :value="fmt(c5.totD)" readonly style="font-size:13px"></div>
                        </div>
                      </div>
                      <!-- Turno Noturno -->
                      <div class="turno-card">
                        <div class="turno-title"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M13 9A6 6 0 016 3a6 6 0 100 10 6 6 0 007-4z"/></svg> Turno Noturno</div>
                        <div class="form-inline-row" style="grid-template-columns:2fr 1fr 1fr 1fr 1fr">
                          <div><div class="form-inline-label">Horário</div>
                            <input class="form-input" id="horario-noturno" type="text" v-model="f5.horarioNoturno" style="font-size:13px"></div>
                          <div><div class="form-inline-label">Nº Func.</div>
                            <input class="form-input" id="qtd-noturno" type="number" v-model.number="f5.qtdNoturno" min="0" style="font-size:13px"></div>
                          <div><div class="form-inline-label">Salário</div>
                            <input class="form-input" id="sal-noturno" type="number" v-model.number="f5.salNoturno" step="0.01" style="font-size:13px"></div>
                          <div><div class="form-inline-label">Função</div>
                            <input class="form-input funcao-label-turno" :value="f5.funcao" readonly style="font-size:13px"></div>
                          <div><div class="form-inline-label">AN?</div>
                            <select class="form-select" id="an-noturno" v-model="f5.anNoturno" style="font-size:13px;padding:10px 8px">
                              <option value="1">Sim</option><option value="0">Não</option>
                            </select></div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px;margin-top:8px">
                          <div class="form-group"><label class="form-label" style="font-size:10px">Periculosidade</label><input class="form-input readonly" :value="fmt(c5.pericN)" readonly style="font-size:13px"></div>
                          <div class="form-group"><label class="form-label" style="font-size:10px">Ad. Noturno</label><input class="form-input readonly" :value="fmt(c5.adnN)" readonly style="font-size:13px"></div>
                          <div class="form-group"><label class="form-label" style="font-size:10px">Intrajornada</label><input class="form-input readonly" :value="fmt(c5.intraN)" readonly style="font-size:13px"></div>
                          <div class="form-group"><label class="form-label" style="font-size:10px">Total Turno</label><input class="form-input readonly" :value="fmt(c5.totN)" readonly style="font-size:13px"></div>
                        </div>
                      </div>
                    </div>
                    <div class="form-grid-3" style="margin-top:16px">
                      <div class="form-group"><label class="form-label">Total Funcionários</label><input class="form-input readonly" :value="c5.totalFunc" readonly></div>
                      <div class="form-group"><label class="form-label">Encargos Sociais (%)</label><input class="form-input" id="encargos" type="number" v-model.number="f5.encargos" step="0.01"></div>
                      <div class="form-group"><label class="form-label">Total Encargos</label><input class="form-input readonly" :value="fmt(c5.encVal)" readonly></div>
                    </div>
                    <div style="text-align:right;padding-top:8px;border-top:1px solid var(--brand-border-soft)">
                      <span style="color:var(--text-muted);font-size:12px;margin-right:16px">TOTAL MÓDULO 01</span>
                      <span class="module-total" id="total-m1-box">{{ fmt(c5.m1) }}</span>
                    </div>
                  </div>
                </div>

                <!-- MÓDULO 02 -->
                <div class="module-card" style="margin-bottom:16px">
                  <div class="module-header">
                    <div class="module-num">2</div>
                    <div class="module-title">Módulo 02 — Benefícios</div>
                    <div class="module-total" id="total-m2">{{ fmt(c5.m2) }}</div>
                  </div>
                  <div class="module-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                      <div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px">
                          <span class="line-label" style="font-size:11px;color:var(--text-muted)">Item</span>
                          <span class="line-label" style="text-align:right;font-size:11px;color:var(--text-muted)">Unitário</span>
                          <span class="line-label" style="text-align:right;font-size:11px;color:var(--text-muted)">Total</span>
                        </div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px"><span class="line-label" title="Valor padrão de Insumos">Uniforme <span style="font-size:9px;color:var(--text-muted)">(Insumos)</span></span><input class="form-input" type="number" v-model.number="f5.b_uniforme" step="0.01" style="font-size:12px;padding:5px 8px"><input class="form-input readonly" :value="fmt(c5.bt.uniforme)" readonly style="font-size:12px;padding:5px 8px"></div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px"><span class="line-label">Plano de Saúde</span><input class="form-input" type="number" v-model.number="f5.b_saude" step="0.01" style="font-size:12px;padding:5px 8px"><input class="form-input readonly" :value="fmt(c5.bt.saude)" readonly style="font-size:12px;padding:5px 8px"></div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px"><span class="line-label">Fundo Social/Odonto</span><input class="form-input" type="number" v-model.number="f5.b_fundo" step="0.01" style="font-size:12px;padding:5px 8px"><input class="form-input readonly" :value="fmt(c5.bt.fundo)" readonly style="font-size:12px;padding:5px 8px"></div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px"><span class="line-label">Saúde Ocup.</span><input class="form-input" type="number" v-model.number="f5.b_sst" step="0.01" style="font-size:12px;padding:5px 8px"><input class="form-input readonly" :value="fmt(c5.bt.sst)" readonly style="font-size:12px;padding:5px 8px"></div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px"><span class="line-label">Contrib. Negocial</span><input class="form-input" type="number" v-model.number="f5.b_cna" step="0.01" style="font-size:12px;padding:5px 8px"><input class="form-input readonly" :value="fmt(c5.bt.cna)" readonly style="font-size:12px;padding:5px 8px"></div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px"><span class="line-label">Seguro de Vida</span><input class="form-input" type="number" v-model.number="f5.b_seguro" step="0.01" style="font-size:12px;padding:5px 8px"><input class="form-input readonly" :value="fmt(c5.bt.seguro)" readonly style="font-size:12px;padding:5px 8px"></div>
                      </div>
                      <div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px">
                          <span class="line-label" style="font-size:11px;color:var(--text-muted)">Item</span>
                          <span class="line-label" style="text-align:right;font-size:11px;color:var(--text-muted)">Unitário</span>
                          <span class="line-label" style="text-align:right;font-size:11px;color:var(--text-muted)">Total</span>
                        </div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px"><span class="line-label">Guia Tráfego Arm.</span><input class="form-input" type="number" v-model.number="f5.b_gta" step="0.01" style="font-size:12px;padding:5px 8px"><input class="form-input readonly" :value="fmt(c5.bt.gta)" readonly style="font-size:12px;padding:5px 8px"></div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px"><span class="line-label">Cofre</span><input class="form-input" type="number" v-model.number="f5.b_cofre" step="0.01" style="font-size:12px;padding:5px 8px"><input class="form-input readonly" :value="fmt(c5.bt.cofre)" readonly style="font-size:12px;padding:5px 8px"></div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px"><span class="line-label">Armamento</span><input class="form-input" type="number" v-model.number="f5.b_arma" step="0.01" style="font-size:12px;padding:5px 8px"><input class="form-input readonly" :value="fmt(c5.bt.arma)" readonly style="font-size:12px;padding:5px 8px"></div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px"><span class="line-label" title="Valor padrão de Insumos">Reciclagem <span style="font-size:9px;color:var(--text-muted)">(Insumos)</span></span><input class="form-input" type="number" v-model.number="f5.b_reciclag" step="0.01" style="font-size:12px;padding:5px 8px"><input class="form-input readonly" :value="fmt(c5.bt.reciclag)" readonly style="font-size:12px;padding:5px 8px"></div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px"><span class="line-label">Vale Transporte</span><input class="form-input" type="number" v-model.number="f5.b_vt" step="0.01" style="font-size:12px;padding:5px 8px"><input class="form-input readonly" :value="fmt(c5.bt.vt)" readonly style="font-size:12px;padding:5px 8px"></div>
                        <div class="line-row" style="grid-template-columns:1fr 110px 90px"><span class="line-label">VA (Alimentação)</span><input class="form-input" type="number" v-model.number="f5.b_va" step="0.01" style="font-size:12px;padding:5px 8px"><input class="form-input readonly" :value="fmt(c5.bt.va)" readonly style="font-size:12px;padding:5px 8px"></div>
                      </div>
                    </div>
                    <div style="grid-column:1/-1;padding-top:8px;border-top:1px solid var(--brand-border-soft);margin-top:4px;display:flex;align-items:center;justify-content:space-between">
                      <button @click="emBreve('Adicionar item ao Módulo 02 — em breve.')" style="background:transparent;border:1px dashed var(--brand-border-soft);border-radius:6px;padding:5px 12px;color:var(--text-muted);cursor:pointer;font-family:inherit;font-size:12px;display:flex;align-items:center;gap:6px">
                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>
                        Adicionar item ao Módulo 02
                      </button>
                      <div>
                        <span style="color:var(--text-muted);font-size:12px;margin-right:16px">TOTAL MÓDULO 02</span>
                        <span class="module-total" id="total-m2-box">{{ fmt(c5.m2) }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- MÓDULO 03 -->
                <div class="module-card">
                  <div class="module-header">
                    <div class="module-num">3</div>
                    <div class="module-title">Módulo 03 — Custos Indiretos, Tributos e Lucro</div>
                    <div class="module-total" id="total-m3">{{ fmt(c5.m3) }}</div>
                  </div>
                  <div class="module-body">
                    <div class="form-grid-3">
                      <div class="form-group"><label class="form-label">Administração (%)</label><input class="form-input" id="pct-adm" type="number" v-model.number="f5.pctAdm" step="0.01"></div>
                      <div class="form-group"><label class="form-label">Lucro (%)</label><input class="form-input" id="pct-lucro" type="number" v-model.number="f5.pctLucro" step="0.01"></div>
                      <div class="form-group"><label class="form-label">Impostos (%)</label><input class="form-input" id="pct-impostos" type="number" v-model.number="f5.pctImpostos" step="0.01"></div>
                    </div>
                    <div class="form-grid-3">
                      <div class="form-group"><label class="form-label">Valor Administração</label><input class="form-input readonly" :value="fmt(c5.vAdm)" readonly></div>
                      <div class="form-group"><label class="form-label">Valor Lucro</label><input class="form-input readonly" :value="fmt(c5.vLucro)" readonly></div>
                      <div class="form-group"><label class="form-label">Valor Impostos</label><input class="form-input readonly" :value="fmt(c5.vImp)" readonly></div>
                    </div>
                  </div>
                </div>

                <!-- TOTAL GERAL — Modelo 5 Estrelas -->
                <div style="background:var(--brand-card);border:1px solid var(--brand-border);border-radius:var(--radius);overflow:hidden;margin-top:4px">
                  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;border-bottom:1px solid var(--brand-border-soft)">
                    <div style="padding:14px 16px;border-right:1px solid var(--brand-border-soft)">
                      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;margin-bottom:5px">Módulo 01</div>
                      <div style="font-size:13px;font-weight:600;color:var(--text-secondary)">{{ fmt(c5.m1) }}</div>
                      <div style="font-size:10px;color:var(--text-muted);margin-top:2px">Remuneração + Encargos</div>
                    </div>
                    <div style="padding:14px 16px;border-right:1px solid var(--brand-border-soft)">
                      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;margin-bottom:5px">Módulo 02</div>
                      <div style="font-size:13px;font-weight:600;color:var(--text-secondary)">{{ fmt(c5.m2) }}</div>
                      <div style="font-size:10px;color:var(--text-muted);margin-top:2px">Benefícios e Insumos</div>
                    </div>
                    <div style="padding:14px 16px">
                      <div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;margin-bottom:5px">Módulo 03</div>
                      <div style="font-size:13px;font-weight:600;color:var(--text-secondary)">{{ fmt(c5.m3) }}</div>
                      <div style="font-size:10px;color:var(--text-muted);margin-top:2px">Custos Indiretos e Lucro</div>
                    </div>
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:0">
                    <div style="padding:18px 20px;border-right:1px solid var(--brand-border-soft)">
                      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;margin-bottom:6px">Total Mensal por Posto</div>
                      <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;color:var(--brand-gold)" id="gt5-mensal">{{ fmt(grandTotal) }}</div>
                      <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
                        <span id="gt5-func-info">{{ c5.totalFunc }} func. × {{ fmt(c5.valorPessoa) }} /func.</span>
                      </div>
                    </div>
                    <div style="padding:18px 20px">
                      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;margin-bottom:6px">
                        Total Anual
                        <select v-model="f5.meses" style="margin-left:8px;background:var(--brand-surface);border:1px solid var(--brand-border-soft);border-radius:4px;color:var(--text-secondary);font-size:10px;padding:2px 6px;cursor:pointer;font-family:inherit">
                          <option value="12">12 meses</option><option value="11">11 meses</option><option value="10">10 meses</option><option value="9">9 meses</option><option value="6">6 meses</option>
                        </select>
                      </div>
                      <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;color:var(--text-primary)" id="gt5-anual">{{ fmt(anual5) }}</div>
                      <div style="font-size:11px;color:var(--text-muted);margin-top:4px">VA Total / mês: <span id="gt5-va">{{ fmt(vaTotalAtual) }}</span></div>
                    </div>
                  </div>
                </div>

              </div><!-- /form-5estrelas -->

              <!-- ════════ MODELO IN 05 ════════ -->
              <div id="form-in05" v-show="modelo === 'in05'">

                <!-- Dados de Identificação -->
                <div class="module-card" style="margin-bottom:16px">
                  <div class="module-header">
                    <div class="module-title" style="color:var(--text-secondary)">Dados para Composição — IN 05</div>
                  </div>
                  <div class="module-body">
                    <div class="form-grid-4">
                      <div class="form-group"><label class="form-label">Município / UF</label><input type="text" class="form-input" v-model="fin.municipio" placeholder="Brasília / DF"></div>
                      <div class="form-group"><label class="form-label">Ano CCT/Dissídio</label><input type="number" class="form-input" v-model.number="fin.anoCct"></div>
                      <div class="form-group"><label class="form-label">Meses de Execução</label><input type="number" class="form-input" v-model.number="fin.meses"></div>
                      <div class="form-group"><label class="form-label">CBO</label><input type="text" class="form-input" v-model="fin.cbo" placeholder="5174-10"></div>
                    </div>
                    <div class="form-grid-3">
                      <div class="form-group"><label class="form-label">Categoria Profissional</label><input type="text" class="form-input" v-model="fin.categoria"></div>
                      <div class="form-group"><label class="form-label">Sindicato / Convenção</label><input type="text" class="form-input" v-model="fin.sindicato"></div>
                      <div class="form-group"><label class="form-label">Data-Base</label><input type="date" class="form-input" v-model="fin.dataBase"></div>
                    </div>
                  </div>
                </div>

                <!-- Módulo 1 -->
                <div class="module-card" style="margin-bottom:16px">
                  <div class="module-header">
                    <div class="module-num">1</div>
                    <div class="module-title">Módulo 1 — Composição da Remuneração</div>
                    <div class="module-total">{{ fmt(cin.m1) }}</div>
                  </div>
                  <div class="module-body" style="padding:0">
                    <table class="cct-table">
                      <thead><tr><th width="30">It.</th><th>Descrição</th><th style="width:100px;text-align:right">%</th><th style="width:150px;text-align:right">Valor (R$)</th></tr></thead>
                      <tbody>
                        <tr><td class="in-item-ref">A</td><td class="td-name">Salário Base</td><td></td><td style="text-align:right;padding-right:12px"><input class="td-input" type="number" v-model.number="fin.sal" step="0.01" style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">B</td><td class="td-name">Adicional de Periculosidade</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.peric_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.peric)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">C</td><td class="td-name">Adicional de Insalubridade</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.insal_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.insal)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">D</td><td class="td-name">Adicional Noturno</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.an_pct" step="0.01" style="width:80px" placeholder="%"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.anVal)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">E</td><td class="td-name">Adicional de Hora Noturna Reduzida</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.hnr_pct" step="0.01" style="width:80px" placeholder="%"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.hnrVal)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">G</td><td class="td-name">Outros (especificar)</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.outros1_pct" step="0.01" style="width:80px" placeholder="%"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.out1)" readonly style="width:130px"></td></tr>
                        <tr class="in-total-row"><td colspan="3" style="padding:10px 16px;font-weight:700">Total Módulo 1</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly in-total" :value="fmt(cin.m1)" readonly style="width:130px"></td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Módulo 2 -->
                <div class="module-card" style="margin-bottom:16px">
                  <div class="module-header">
                    <div class="module-num">2</div>
                    <div class="module-title">Módulo 2 — Encargos e Benefícios Anuais, Mensais e Diários</div>
                    <div class="module-total">{{ fmt(cin.m2) }}</div>
                  </div>
                  <div class="module-body" style="padding:0">
                    <!-- 2.1 -->
                    <div class="in-submodulo-header">Submódulo 2.1 — 13º Salário, Férias e Adicional de Férias</div>
                    <table class="cct-table">
                      <thead><tr><th width="50">It.</th><th>Descrição</th><th style="width:100px;text-align:right">%</th><th style="width:150px;text-align:right">Valor (R$)</th></tr></thead>
                      <tbody>
                        <tr><td class="in-item-ref">A</td><td class="td-name">13º (décimo terceiro) Salário</td><td style="text-align:right;padding-right:8px"><input class="td-input readonly" :value="pctTxt(cin.p13*100)" readonly style="width:80px;font-size:11px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.v13)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">B</td><td class="td-name">Férias e Adicional de Férias (1/3)</td><td style="text-align:right;padding-right:8px"><input class="td-input readonly" :value="pctTxt(cin.pFer*100)" readonly style="width:80px;font-size:11px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vFer)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">C</td><td class="td-name">Incidência do Submód. 2.2 sobre A e B</td><td style="text-align:right;padding-right:8px"><input class="td-input readonly" :value="pctTxt(cin.pInc21*100)" readonly style="width:80px;font-size:11px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vInc21)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">D</td><td class="td-name">Multa FGTS sobre 13º e Adicional de Férias</td><td style="text-align:right;padding-right:8px"><input class="td-input readonly" :value="pctTxt(cin.pMultaFgts*100)" readonly style="width:80px;font-size:11px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vMultaFgts)" readonly style="width:130px"></td></tr>
                        <tr class="in-subtotal-row"><td colspan="3" style="padding:8px 16px;font-weight:600;font-size:12px">Total 2.1</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.sub21)" readonly style="width:130px;font-weight:600"></td></tr>
                      </tbody>
                    </table>
                    <!-- 2.2 -->
                    <div class="in-submodulo-header">Submódulo 2.2 — GPS, FGTS e Outras Contribuições</div>
                    <table class="cct-table">
                      <thead><tr><th width="50">It.</th><th>Descrição</th><th style="width:100px;text-align:right">%</th><th style="width:150px;text-align:right">Valor (R$)</th></tr></thead>
                      <tbody>
                        <tr><td class="in-item-ref">A</td><td class="td-name">INSS</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.inss_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.inss)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">B</td><td class="td-name">Salário Educação</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.saledu_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.saledu)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">C</td><td class="td-name">SAT (RAT × FAP)</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.sat_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.sat)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">D</td><td class="td-name">SESC ou SESI</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.sesc_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.sesc)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">E</td><td class="td-name">SENAI / SENAC</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.senai_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.senai)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">F</td><td class="td-name">SEBRAE</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.sebrae_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.sebrae)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">G</td><td class="td-name">INCRA</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.incra_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.incra)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">H</td><td class="td-name">FGTS</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.fgts_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.fgts)" readonly style="width:130px"></td></tr>
                        <tr class="in-subtotal-row"><td colspan="3" style="padding:8px 16px;font-weight:600;font-size:12px">Total 2.2</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.sub22)" readonly style="width:130px;font-weight:600"></td></tr>
                      </tbody>
                    </table>
                    <!-- 2.3 -->
                    <div class="in-submodulo-header">Submódulo 2.3 — Benefícios Mensais e Diários</div>
                    <table class="cct-table">
                      <thead><tr><th width="50">It.</th><th>Descrição</th><th style="width:140px;text-align:right">Referência</th><th style="width:150px;text-align:right">Valor (R$)</th></tr></thead>
                      <tbody>
                        <tr><td class="in-item-ref">A</td><td class="td-name">Transporte (desconto 6% s/ salário)</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.vt_dia" step="0.01" style="width:120px" placeholder="R$/dia"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vtLiq)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">B</td><td class="td-name">Auxílio-Refeição / Alimentação (VA)</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.va_dia" step="0.01" style="width:120px" placeholder="R$/dia"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vaVal)" readonly style="width:130px"></td></tr>
                        <tr><td></td><td class="td-name">Assistência Médica</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.medico" step="0.01" style="width:120px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.medico)" readonly style="width:130px"></td></tr>
                        <tr><td></td><td class="td-name">Assistência Odontológica</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.odonto" step="0.01" style="width:120px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.odonto)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">C</td><td class="td-name">Cesta Básica</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.cesta" step="0.01" style="width:120px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.cesta)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">D</td><td class="td-name">Seguro de Vida</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.seguro" step="0.01" style="width:120px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.seguro)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">E</td><td class="td-name">PMQ — Qualificação Profissional</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.pmq" step="0.01" style="width:120px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.pmq)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">F</td><td class="td-name">Outros / Contribuição Patronal</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.outros23" step="0.01" style="width:120px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.out23)" readonly style="width:130px"></td></tr>
                        <tr class="in-subtotal-row"><td colspan="3" style="padding:8px 16px;font-weight:600;font-size:12px">Total 2.3</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.sub23)" readonly style="width:130px;font-weight:600"></td></tr>
                      </tbody>
                    </table>
                    <div class="in-module-total-row">
                      <span>TOTAL MÓDULO 2</span>
                      <span class="module-total">{{ fmt(cin.m2) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Módulo 3 -->
                <div class="module-card" style="margin-bottom:16px">
                  <div class="module-header">
                    <div class="module-num">3</div>
                    <div class="module-title">Módulo 3 — Provisão para Rescisão</div>
                    <div class="module-total">{{ fmt(cin.m3) }}</div>
                  </div>
                  <div class="module-body" style="padding:0">
                    <table class="cct-table">
                      <thead><tr><th width="50">It.</th><th>Descrição</th><th style="width:100px;text-align:right">%</th><th style="width:150px;text-align:right">Valor (R$)</th></tr></thead>
                      <tbody>
                        <tr><td class="in-item-ref">A</td><td class="td-name">Aviso Prévio Indenizado</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.avisoind_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vAvisoInd)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">B</td><td class="td-name">Incidência FGTS s/ Aviso Prévio Indenizado</td><td style="text-align:right;padding-right:8px"><input class="td-input readonly" :value="pctTxt(cin.pFgtsAviso*100)" readonly style="width:80px;font-size:11px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vFgtsAviso)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">C</td><td class="td-name">Aviso Prévio Trabalhado</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.avistrab_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vAvisTrab)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">D</td><td class="td-name">Multa FGTS s/ Aviso Prévio Indenizado</td><td style="text-align:right;padding-right:8px"><input class="td-input readonly" :value="pctTxt(cin.pMultaInd*100)" readonly style="width:80px;font-size:11px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vMultaInd)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">E</td><td class="td-name">Multa FGTS s/ Rescisão sem justa causa</td><td style="text-align:right;padding-right:8px"><input class="td-input readonly" :value="pctTxt(cin.pMultaResc*100)" readonly style="width:80px;font-size:11px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vMultaResc)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">F</td><td class="td-name">Incidência GPS/FGTS s/ Aviso Prévio Trabalhado</td><td style="text-align:right;padding-right:8px"><input class="td-input readonly" :value="pctTxt(cin.pIncGPS*100)" readonly style="width:80px;font-size:11px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vIncGPS)" readonly style="width:130px"></td></tr>
                        <tr class="in-total-row"><td colspan="3" style="padding:10px 16px;font-weight:700">Total Módulo 3</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly in-total" :value="fmt(cin.m3)" readonly style="width:130px"></td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Módulo 4 -->
                <div class="module-card" style="margin-bottom:16px">
                  <div class="module-header">
                    <div class="module-num">4</div>
                    <div class="module-title">Módulo 4 — Custo de Reposição do Profissional Ausente</div>
                    <div class="module-total">{{ fmt(cin.m4) }}</div>
                  </div>
                  <div class="module-body" style="padding:0">
                    <div class="in-submodulo-header">Submódulo 4.1 — Ausências Legais</div>
                    <table class="cct-table">
                      <thead><tr><th width="50">It.</th><th>Descrição</th><th style="width:100px;text-align:right">%</th><th style="width:150px;text-align:right">Valor (R$)</th></tr></thead>
                      <tbody>
                        <tr><td class="in-item-ref">A</td><td class="td-name">Substituto na cobertura de Férias</td><td style="text-align:right;padding-right:8px"><input class="td-input readonly" :value="pctTxt(cin.pCobFer*100)" readonly style="width:80px;font-size:11px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vCobFer)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">B</td><td class="td-name">Substituto — Ausências Legais</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.ausleg_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vAusleg)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">C</td><td class="td-name">Substituto — Licença Paternidade</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.paterni_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vPatern)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">D</td><td class="td-name">Substituto — Acidente de Trabalho</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.acident_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vAcident)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">E</td><td class="td-name">Substituto — Afastamento Maternidade</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.matern_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vMatern)" readonly style="width:130px"></td></tr>
                        <tr class="in-subtotal-row"><td colspan="3" style="padding:8px 16px;font-weight:600;font-size:12px">Subtotal 4.1</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.sub41)" readonly style="width:130px;font-weight:600"></td></tr>
                        <tr><td class="in-item-ref">F</td><td class="td-name">Incidências (Submód. 2.2 + 2.1) sobre A a E</td><td style="text-align:right;padding-right:8px"><input class="td-input readonly" :value="pctTxt(cin.pIncAus*100)" readonly style="width:80px;font-size:11px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vIncAus)" readonly style="width:130px"></td></tr>
                        <tr class="in-subtotal-row"><td colspan="3" style="padding:8px 16px;font-weight:600;font-size:12px">Total 4.1</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.tot41)" readonly style="width:130px;font-weight:600"></td></tr>
                      </tbody>
                    </table>
                    <div class="in-submodulo-header">Submódulo 4.2 — Intrajornada</div>
                    <table class="cct-table">
                      <tbody>
                        <tr><td class="in-item-ref" style="padding:8px 16px">A</td><td class="td-name">Substituto — Intervalo Repouso/Alimentação</td><td style="width:100px"></td><td style="text-align:right;padding-right:12px"><input class="td-input" type="number" v-model.number="fin.intrajornada" step="0.01" style="width:130px"></td></tr>
                        <tr class="in-total-row"><td colspan="3" style="padding:10px 16px;font-weight:700">Total Módulo 4</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly in-total" :value="fmt(cin.m4)" readonly style="width:130px"></td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Módulo 5 -->
                <div class="module-card" style="margin-bottom:16px">
                  <div class="module-header">
                    <div class="module-num">5</div>
                    <div class="module-title">Módulo 5 — Insumos Diversos</div>
                    <div class="module-total">{{ fmt(cin.m5) }}</div>
                  </div>
                  <div class="module-body" style="padding:0">
                    <table class="cct-table">
                      <thead><tr><th width="50">It.</th><th>Descrição</th><th></th><th style="width:150px;text-align:right">Valor (R$)</th></tr></thead>
                      <tbody>
                        <tr><td class="in-item-ref">A</td><td class="td-name">Uniformes (Cláusula 52ª)</td><td></td><td style="text-align:right;padding-right:12px"><input class="td-input" type="number" v-model.number="fin.uniforme" step="0.01" style="width:130px"></td></tr>
                        <tr><td></td><td class="td-name">Materiais</td><td></td><td style="text-align:right;padding-right:12px"><input class="td-input" type="number" v-model.number="fin.materiais" step="0.01" style="width:130px"></td></tr>
                        <tr><td></td><td class="td-name">Ferramental</td><td></td><td style="text-align:right;padding-right:12px"><input class="td-input" type="number" v-model.number="fin.ferramental" step="0.01" style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">B</td><td class="td-name">EPIs</td><td></td><td style="text-align:right;padding-right:12px"><input class="td-input" type="number" v-model.number="fin.epi" step="0.01" style="width:130px"></td></tr>
                        <tr><td></td><td class="td-name">Outros (treinamento e reciclagem)</td><td></td><td style="text-align:right;padding-right:12px"><input class="td-input" type="number" v-model.number="fin.treinamento" step="0.01" style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">C</td><td class="td-name">SSO — Saúde e Seg. Ocupacional (Cláusula 55ª)</td><td></td><td style="text-align:right;padding-right:12px"><input class="td-input" type="number" v-model.number="fin.sso" step="0.01" style="width:130px"></td></tr>
                        <tr class="in-total-row"><td colspan="3" style="padding:10px 16px;font-weight:700">Total Módulo 5</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly in-total" :value="fmt(cin.m5)" readonly style="width:130px"></td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Módulo 6 -->
                <div class="module-card" style="margin-bottom:16px">
                  <div class="module-header">
                    <div class="module-num">6</div>
                    <div class="module-title">Módulo 6 — Custos Indiretos, Tributos e Lucro</div>
                    <div class="module-total">{{ fmt(cin.m6) }}</div>
                  </div>
                  <div class="module-body" style="padding:0">
                    <table class="cct-table">
                      <thead><tr><th width="50">It.</th><th>Descrição</th><th style="width:100px;text-align:right">%</th><th style="width:150px;text-align:right">Valor (R$)</th></tr></thead>
                      <tbody>
                        <tr><td class="in-item-ref">A</td><td class="td-name">Custos Indiretos</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.custoind_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vCind)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">B</td><td class="td-name">Lucro</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.lucro_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vLucro)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">C</td><td class="td-name">Tributos — ISS</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.iss_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vISS)" readonly style="width:130px"></td></tr>
                        <tr><td></td><td class="td-name">PIS</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.pis_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vPIS)" readonly style="width:130px"></td></tr>
                        <tr><td></td><td class="td-name">COFINS</td><td style="text-align:right;padding-right:8px"><input class="td-input" type="number" v-model.number="fin.cofins_pct" step="0.01" style="width:80px"></td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.vCOFINS)" readonly style="width:130px"></td></tr>
                        <tr class="in-total-row"><td colspan="3" style="padding:10px 16px;font-weight:700">Preço por Empregado (Total)</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly in-total" :value="fmt(cin.precoEmp)" readonly style="width:130px"></td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Quadro Resumo Final -->
                <div class="module-card">
                  <div class="module-header">
                    <div class="module-title">Quadro-Resumo do Custo por Empregado</div>
                    <div class="module-total">{{ fmt(cin.precoEmp) }}</div>
                  </div>
                  <div class="module-body" style="padding:0">
                    <table class="cct-table">
                      <thead><tr><th width="30">It.</th><th>Módulo</th><th style="width:150px;text-align:right">Valor (R$)</th></tr></thead>
                      <tbody>
                        <tr><td class="in-item-ref">A</td><td class="td-name">Módulo 1 — Composição da Remuneração</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.m1)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">B</td><td class="td-name">Módulo 2 — Encargos e Benefícios</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.m2)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">C</td><td class="td-name">Módulo 3 — Provisão para Rescisão</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.m3)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">D</td><td class="td-name">Módulo 4 — Reposição do Profissional Ausente</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.m4)" readonly style="width:130px"></td></tr>
                        <tr><td class="in-item-ref">E</td><td class="td-name">Módulo 5 — Insumos Diversos</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.m5)" readonly style="width:130px"></td></tr>
                        <tr class="in-subtotal-row"><td colspan="2" style="padding:8px 16px;font-weight:600;font-size:12px">Subtotal (A+B+C+D+E)</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.subtotal)" readonly style="width:130px;font-weight:600"></td></tr>
                        <tr><td class="in-item-ref">F</td><td class="td-name">Módulo 6 — Custos Indiretos, Tributos e Lucro</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly" :value="fmt(cin.m6)" readonly style="width:130px"></td></tr>
                        <tr class="in-total-row"><td colspan="2" style="padding:12px 16px;font-weight:800;font-size:14px">Valor Total por Empregado</td><td style="text-align:right;padding-right:12px"><input class="td-input readonly in-total" :value="fmt(cin.precoEmp)" readonly style="width:130px"></td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- TOTAL GERAL — Modelo IN 05 -->
                <div style="background:var(--brand-card);border:1px solid var(--brand-border);border-radius:var(--radius);overflow:hidden;margin-top:4px">
                  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-bottom:1px solid var(--brand-border-soft)">
                    <div style="padding:12px 14px;border-right:1px solid var(--brand-border-soft)"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;margin-bottom:4px">M1 — Remuneração</div><div style="font-size:12px;font-weight:600;color:var(--text-secondary)">{{ fmt(cin.m1) }}</div></div>
                    <div style="padding:12px 14px;border-right:1px solid var(--brand-border-soft)"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;margin-bottom:4px">M2 — Encargos e Ben.</div><div style="font-size:12px;font-weight:600;color:var(--text-secondary)">{{ fmt(cin.m2) }}</div></div>
                    <div style="padding:12px 14px"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;margin-bottom:4px">M3 — Rescisão</div><div style="font-size:12px;font-weight:600;color:var(--text-secondary)">{{ fmt(cin.m3) }}</div></div>
                  </div>
                  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-bottom:1px solid var(--brand-border-soft)">
                    <div style="padding:12px 14px;border-right:1px solid var(--brand-border-soft)"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;margin-bottom:4px">M4 — Ausências</div><div style="font-size:12px;font-weight:600;color:var(--text-secondary)">{{ fmt(cin.m4) }}</div></div>
                    <div style="padding:12px 14px;border-right:1px solid var(--brand-border-soft)"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;margin-bottom:4px">M5 — Insumos</div><div style="font-size:12px;font-weight:600;color:var(--text-secondary)">{{ fmt(cin.m5) }}</div></div>
                    <div style="padding:12px 14px"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;margin-bottom:4px">M6 — Ind./Trib./Lucro</div><div style="font-size:12px;font-weight:600;color:var(--text-secondary)">{{ fmt(cin.m6) }}</div></div>
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:0">
                    <div style="padding:18px 20px;border-right:1px solid var(--brand-border-soft)">
                      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;margin-bottom:6px">Valor Total por Empregado</div>
                      <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;color:var(--brand-gold)">{{ fmt(grandTotal) }}</div>
                      <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Subtotal M1–M5: <span>{{ fmt(cin.subtotal) }}</span></div>
                    </div>
                    <div style="padding:18px 20px">
                      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);font-weight:700;margin-bottom:6px">
                        Total Anual
                        <select v-model="fin.meses_geral" style="margin-left:8px;background:var(--brand-surface);border:1px solid var(--brand-border-soft);border-radius:4px;color:var(--text-secondary);font-size:10px;padding:2px 6px;cursor:pointer;font-family:inherit">
                          <option value="12">12 meses</option><option value="11">11 meses</option><option value="10">10 meses</option><option value="9">9 meses</option><option value="6">6 meses</option>
                        </select>
                      </div>
                      <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:26px;color:var(--text-primary)">{{ fmt(anualIn) }}</div>
                      <div style="font-size:11px;color:var(--text-muted);margin-top:4px">VA / mês: <span>{{ fmt(cin.vaVal) }}</span></div>
                    </div>
                  </div>
                </div>

              </div><!-- /form-in05 -->

            </div><!-- /form-detalhes -->
          </div><!-- /coluna esquerda -->

          <!-- COLUNA DIREITA: Resumo da proposta (fixo; só a lista de postos rola) -->
          <div class="cot-resumo">
            <div class="module-card">
              <div class="module-header" style="flex-wrap:wrap;gap:8px;flex-shrink:0">
                <div class="module-title"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4h8M4 8h8M4 12h5"/><circle cx="1.5" cy="4" r="1" fill="currentColor" stroke="none"/><circle cx="1.5" cy="8" r="1" fill="currentColor" stroke="none"/><circle cx="1.5" cy="12" r="1" fill="currentColor" stroke="none"/></svg> Resumo dos Postos</div>
                <button @click="limparItens()" style="margin-left:auto;background:transparent;border:1px solid var(--brand-border-soft);border-radius:6px;padding:3px 10px;color:var(--text-muted);font-size:11px;cursor:pointer;font-family:inherit">Limpar</button>
              </div>

              <!-- Estado vazio -->
              <div v-if="!itens.length" id="resumo-vazio" style="padding:32px;text-align:center;color:var(--text-muted);font-size:13px">
                <div style="margin-bottom:12px;opacity:.3"><svg width="28" height="28" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="10" width="24" height="18" rx="2"/><path d="M4 14h24M12 10V6a4 4 0 018 0v4"/></svg></div>
                Nenhum posto adicionado.<br>Configure e clique em <strong style="color:var(--text-primary)">+ Adicionar</strong>.
              </div>

              <!-- Tabela -->
              <div v-else id="resumo-table">
                <div style="display:flex;justify-content:space-between;padding:8px 12px;background:rgba(0,0,0,0.02);border-bottom:1px solid var(--brand-border-soft)">
                  <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted)">Discriminação</span>
                  <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted)">Mensal</span>
                </div>
                <div id="resumo-tbody" class="cot-resumo-list">
                  <div v-for="(item, idx) in itens" :key="item.id"
                    :style="{ display:'flex', gap:'8px', alignItems:'flex-start', padding:'8px 12px', borderBottom:'1px solid var(--brand-border-soft)', background: idx%2===1 ? 'rgba(0,0,0,0.012)' : '' }">
                    <div style="flex:1;min-width:0">
                      <div style="font-size:12px;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:5px;line-height:1.2">
                        <span class="cat-icon sm" v-html="icon(item.catIcone)"></span><span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ item.cat }}</span>
                      </div>
                      <div style="font-size:10px;color:var(--text-muted);margin-top:2px;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ escLabelCurto(item.escala) }} · {{ item.qtdPostos }} posto{{ item.qtdPostos !== 1 ? 's' : '' }} · {{ item.qtdPostos * item.funcPosto }} func. · unit {{ fmt(item.unitVal) }}</div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:2px;flex-shrink:0;white-space:nowrap">
                      <span style="font-family:'Syne',sans-serif;font-weight:700;font-size:13px;color:var(--text-primary)">{{ fmt(item.totalMensal) }}</span>
                      <button @click="removerItem(item.id)" style="background:transparent;border:none;color:var(--text-muted);cursor:pointer;font-size:11px;padding:0" title="Remover">remover</button>
                    </div>
                  </div>
                </div>

                <!-- Rodapé totalizador -->
                <div id="resumo-totais" style="border-top:2px solid var(--brand-border-soft)">
                  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:9px 12px;background:rgba(0,0,0,0.02);border-bottom:1px solid var(--brand-border-soft)">
                    <span style="font-size:11px;font-weight:700;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Subtotal · {{ totPostos }} posto{{ totPostos !== 1 ? 's' : '' }} · {{ totProfissionais }} func.</span>
                    <span style="font-size:12px;font-weight:700;color:var(--text-primary);white-space:nowrap">{{ fmt(totMensal) }}</span>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border-bottom:1px solid var(--brand-border-soft)">
                    <span style="font-size:11px;color:var(--text-muted)">Valor Anual</span>
                    <span style="font-size:13px;font-weight:600;color:var(--text-secondary);white-space:nowrap">{{ fmt(totMensal * 12) }}</span>
                  </div>
                  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:14px 12px">
                    <span style="font-size:14px;font-weight:700;color:var(--text-primary)">Total Mensal</span>
                    <span style="font-family:'Syne',sans-serif;font-weight:800;font-size:20px;color:var(--brand-gold);white-space:nowrap">{{ fmt(totMensal) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Mini KPIs -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px">
              <div class="stat-card" style="padding:12px">
                <div class="stat-label">VA Total / mês</div>
                <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:15px;color:var(--brand-gold);margin-top:3px">{{ fmt(totVa) }}</div>
              </div>
              <div class="stat-card" style="padding:12px">
                <div class="stat-label">Custo médio / func.</div>
                <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:15px;color:var(--text-primary);margin-top:3px">{{ fmt(totCustoFunc) }}</div>
              </div>
            </div>
          </div><!-- /coluna direita -->

        </div><!-- /layout principal -->

      <!-- Modal Gerar Proposta PDF -->
      <div v-if="modalPdfAberto" class="g360-modal-overlay" @click.self="modalPdfAberto = false">
        <div class="g360-modal" style="max-width:560px">
          <div class="g360-modal-header">
            <h3>Gerar Proposta PDF</h3>
            <button @click="modalPdfAberto = false" class="g360-modal-close">&times;</button>
          </div>
          <div class="g360-modal-body">
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px">Confira e ajuste os dados que aparecerão no documento da proposta.</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;width:100%">
              <div class="form-group">
                <label class="form-label">Nº Proposta</label>
                <input type="text" class="form-input" v-model="pdfForm.numProposta">
              </div>
              <div class="form-group">
                <label class="form-label">Data</label>
                <input type="text" class="form-input" v-model="pdfForm.data">
              </div>
              <div class="form-group" style="grid-column:span 2">
                <label class="form-label">Cliente / Destinatário</label>
                <input type="text" class="form-input" v-model="pdfForm.cliente">
              </div>
              <div class="form-group" style="grid-column:span 2">
                <label class="form-label">Objeto</label>
                <textarea class="form-input" v-model="pdfForm.objeto" rows="3" style="resize:vertical"></textarea>
              </div>
              <div class="form-group" style="grid-column:span 2">
                <label class="form-label">Título CCT / Convenção</label>
                <input type="text" class="form-input" v-model="pdfForm.tituloCct">
              </div>
              <div class="form-group">
                <label class="form-label">Cidade</label>
                <input type="text" class="form-input" v-model="pdfForm.cidade">
              </div>
              <div class="form-group">
                <label class="form-label">Responsável</label>
                <input type="text" class="form-input" v-model="pdfForm.responsavel">
              </div>
              <div class="form-group" style="grid-column:span 2">
                <label class="form-label">Cargo</label>
                <input type="text" class="form-input" v-model="pdfForm.cargo">
              </div>
            </div>
          </div>
          <div class="g360-modal-footer" style="display:flex;justify-content:flex-end;gap:10px;padding:16px 20px;border-top:1px solid var(--brand-border-soft)">
            <button class="btn btn-ghost" @click="modalPdfAberto = false">Cancelar</button>
            <button class="btn btn-gold" @click="confirmarGerarPdf()">Gerar Proposta</button>
          </div>
        </div>
      </div>

      </div><!-- /view-cotacao -->
    </div><!-- /g360 -->

  </AuthenticatedLayout>
</template>

<style scoped>
/* Layout responsivo da Cotação (desktop-first). */
.cot-layout {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 20px;
  align-items: start;
}
.cot-resumo {
  position: sticky;
  top: 6rem;
  padding-right: 2px;
}
/* Só a lista de postos rola; altura limitada para o painel não ficar alto demais. */
.cot-resumo-list {
  max-height: 38vh;
  overflow-y: auto;
}

/* Telas médias: coluna do resumo mais estreita. */
@media (max-width: 1366px) {
  .cot-layout { grid-template-columns: 1fr 340px; }
}

/* Telas menores: empilha (resumo abaixo), sem sticky, ocupando largura total. */
@media (max-width: 1100px) {
  .cot-layout { grid-template-columns: 1fr; }
  .cot-resumo { position: static; padding-right: 0; }
  .cot-resumo-list { max-height: 50vh; }
}
</style>
