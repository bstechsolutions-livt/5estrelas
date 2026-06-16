<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { onMounted, ref, computed, nextTick } from "vue"
import axios from "axios"
import * as XLSX from "xlsx"
import Toast from "primevue/toast"
import { useToast } from "primevue/usetoast"
import "@/../css/comercial-g360.css"

const props = defineProps({
  dados: Object,
  clientes: Array,
})

const toast = useToast()
const ok = (m) => toast.add({ severity: "success", summary: "Pronto", detail: m, life: 2500 })
const fail = (m) => toast.add({ severity: "error", summary: "Erro", detail: m, life: 4000 })

// ─── Constantes ─────────────────────────────────────────────
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'setembro', 'out', 'nov', 'dez']
const MESES_LABEL = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez']

// ─── Estado ─────────────────────────────────────────────────
const anoAtivo = ref(2025)
const faturamento = ref({ 2025: { locais: [] }, 2026: { locais: [] } })
const salvando = ref(false)
const novoLocalNome = ref("")
const showNovoLocal = ref(false)

// ─── Inicialização ──────────────────────────────────────────
onMounted(() => {
  if (props.dados) {
    faturamento.value = JSON.parse(JSON.stringify(props.dados))
  }
})

// ─── Computed ───────────────────────────────────────────────
const locaisAtivos = computed(() => {
  if (anoAtivo.value === 'comp') return faturamento.value[2026]?.locais || []
  return faturamento.value[anoAtivo.value]?.locais || []
})

const locais25 = computed(() => faturamento.value[2025]?.locais || [])
const locais26 = computed(() => faturamento.value[2026]?.locais || [])

// KPIs
const kpis = computed(() => {
  const calc = (ano) => {
    const locais = faturamento.value[ano]?.locais || []
    let total = 0
    locais.forEach(l => { MESES.forEach(m => { total += l[m] || 0 }) })
    return { total, locais: locais.length }
  }

  const t25 = calc(2025)
  const t26 = calc(2026)
  const isComp = anoAtivo.value === 'comp'
  const ano = isComp ? 2026 : anoAtivo.value
  const tAno = ano === 2025 ? t25 : t26

  const varPct = t25.total > 0 ? ((t26.total - t25.total) / t25.total * 100) : 0

  // Melhor mês
  const melhorMes = (a) => {
    const locais = faturamento.value[a]?.locais || []
    let best = { mes: '', val: 0 }
    MESES.forEach((m, i) => {
      const s = locais.reduce((acc, l) => acc + (l[m] || 0), 0)
      if (s > best.val) { best = { mes: MESES_LABEL[i], val: s } }
    })
    return best
  }

  if (isComp) {
    const mm25 = melhorMes(2025)
    return [
      { label: 'Total 2025', val: fmtK(t25.total), sub: '12 meses', cor: 'var(--text-muted)' },
      { label: 'Total 2026', val: fmtK(t26.total), sub: 'ano atual', cor: 'var(--brand-gold)', big: true },
      { label: 'Variação Anual', val: (varPct >= 0 ? '+' : '') + varPct.toFixed(2) + '%', sub: '2025 → 2026', cor: varPct >= 0 ? 'var(--green)' : 'var(--red)' },
      { label: 'Locais Cadastrados', val: String(Math.max(t25.locais, t26.locais)), sub: 'em ambos os anos', cor: 'var(--blue)' },
      { label: 'Melhor Mês 2025', val: mm25.mes || '—', sub: mm25.val > 0 ? fmtK(mm25.val) : '', cor: 'var(--orange)' },
    ]
  }

  const mm = melhorMes(ano)
  const mediaM = tAno.total / 12

  const lastCard = ano === 2026 && t25.total > 0
    ? { label: 'vs 2025', val: (varPct >= 0 ? '+' : '') + varPct.toFixed(2) + '%', sub: 'crescimento anual', cor: varPct >= 0 ? 'var(--green)' : 'var(--red)' }
    : {
        label: 'Meses Lançados',
        val: String(MESES.filter(m => (faturamento.value[ano]?.locais || []).some(l => l[m] > 0)).length),
        sub: 'de 12 meses',
        cor: 'var(--text-muted)',
      }

  return [
    { label: 'Faturamento ' + ano, val: fmtK(tAno.total), sub: 'acumulado anual', cor: 'var(--brand-gold)', big: true },
    { label: 'Média Mensal', val: fmtK(mediaM), sub: 'por mês', cor: 'var(--blue)' },
    { label: 'Melhor Mês', val: mm.mes || '—', sub: mm.val > 0 ? fmtK(mm.val) : '', cor: 'var(--green)' },
    { label: 'Locais', val: String(tAno.locais), sub: 'unidades faturadas', cor: 'var(--orange)' },
    lastCard,
  ]
})

// Totais por mês (footer)
const totaisMes = computed(() => {
  const isComp = anoAtivo.value === 'comp'
  const ano = isComp ? 2026 : anoAtivo.value
  const locais = faturamento.value[ano]?.locais || []
  const locais25v = isComp ? (faturamento.value[2025]?.locais || []) : []

  const totais = {}
  MESES.forEach(m => {
    totais[m] = locais.reduce((s, l) => s + (l[m] || 0), 0)
    if (isComp) totais[m + '_25'] = locais25v.reduce((s, l) => s + (l[m] || 0), 0)
  })
  totais.total = MESES.reduce((s, m) => s + totais[m], 0)
  if (isComp) totais.total_25 = MESES.reduce((s, m) => s + (totais[m + '_25'] || 0), 0)
  return totais
})

// Nomes ordenados (comp = union dos dois anos)
const nomesOrdenados = computed(() => {
  if (anoAtivo.value !== 'comp') return locaisAtivos.value.map(l => l.local_nome)
  const set = new Set()
  locais26.value.forEach(l => set.add(l.local_nome))
  locais25.value.forEach(l => set.add(l.local_nome))
  return [...set]
})

// ─── Helpers format ─────────────────────────────────────────
function fmtK(v) {
  if (v >= 1e9) return 'R$ ' + (v / 1e9).toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) + 'B'
  if (v >= 1e6) return 'R$ ' + (v / 1e6).toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) + 'M'
  return 'R$ ' + (v / 1000).toLocaleString('pt-BR', { minimumFractionDigits: 0 }) + 'k'
}

function fmtR(v) {
  if (!v || v === 0) return '—'
  return 'R$\u00a0' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function fmtRaw(v) {
  return v > 0 ? Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : ''
}

// ─── Ações ──────────────────────────────────────────────────
function selecionarAno(ano) {
  anoAtivo.value = ano
}

function totalLinha(local) {
  return MESES.reduce((s, m) => s + (local[m] || 0), 0)
}

function onCellBlur(local, mes, event) {
  const raw = event.target.value.replace(/\./g, '').replace(',', '.')
  const val = parseFloat(raw) || 0
  local[mes] = Math.round(val * 100) / 100
}

async function salvar() {
  salvando.value = true
  const ano = anoAtivo.value === 'comp' ? 2026 : anoAtivo.value
  const locais = faturamento.value[ano]?.locais || []

  try {
    await axios.post('/comercial/faturamento/salvar', { ano, locais })
    ok('Faturamento salvo')
  } catch (e) {
    fail(e.response?.data?.mensagem || 'Erro ao salvar')
  } finally {
    salvando.value = false
  }
}

function abrirNovoLocal() {
  novoLocalNome.value = ''
  showNovoLocal.value = true
}

// ─── Exportar Excel (mesmo formato do Importar — round-trip) ─
// Aba "Faturamento": Local + 12 meses + Total, uma linha por local + linha TOTAL.
function exportarExcel() {
  const ano = anoAtivo.value === "comp" ? 2026 : anoAtivo.value
  const locais = faturamento.value[ano]?.locais || []
  if (!locais.length) { fail("Nenhum local para exportar neste ano"); return }

  try {
    const header = ["Local", ...MESES_LABEL, "Total"]
    const rows = locais.map((l) => {
      const vals = MESES.map((m) => Number(l[m] || 0))
      return [l.local_nome, ...vals, vals.reduce((s, v) => s + v, 0)]
    })
    const totMeses = MESES.map((m) => locais.reduce((s, l) => s + (Number(l[m]) || 0), 0))
    const totRow = ["TOTAL", ...totMeses, totMeses.reduce((s, v) => s + v, 0)]

    const wb = XLSX.utils.book_new()
    const ws = XLSX.utils.aoa_to_sheet([header, ...rows, totRow])
    ws["!cols"] = [{ wch: 28 }, ...MESES.map(() => ({ wch: 12 })), { wch: 14 }]
    XLSX.utils.book_append_sheet(wb, ws, "Faturamento")

    const dataStr = new Date().toISOString().slice(0, 10).replace(/-/g, "")
    const filename = `Faturamento_${ano}_${dataStr}.xlsx`
    XLSX.writeFile(wb, filename)
    ok(`Planilha "${filename}" exportada (${locais.length} locais)`)
  } catch (e) {
    console.error(e)
    fail("Erro ao gerar a planilha")
  }
}

// ─── Importar Excel ─────────────────────────────────────────
// Formato esperado: 1ª coluna = nome do Local, colunas 2..13 = meses (Jan..Dez).
// Linha de cabeçalho opcional (detectada quando a 1ª célula não é o nome de um local
// numérico). Importa para o ano ativo (comp → 2026), mesclando por nome do local; o
// usuário revisa e clica em Salvar para persistir.
function impNum(v) {
  if (v === null || v === undefined || v === "") return 0
  if (typeof v === "number") return v
  const s = String(v).replace(/[R$\s]/g, "").replace(/\./g, "").replace(",", ".")
  const n = parseFloat(s)
  return isNaN(n) ? 0 : n
}

function importarExcel(ev) {
  const file = ev?.target?.files?.[0]
  if (ev?.target) ev.target.value = ""
  if (!file) return
  if (!/\.xlsx?$/i.test(file.name)) { fail("Envie um arquivo .xlsx ou .xls"); return }

  const reader = new FileReader()
  reader.onload = (e) => {
    try {
      const wb = XLSX.read(new Uint8Array(e.target.result), { type: "array" })
      const ws = wb.Sheets[wb.SheetNames[0]]
      if (!ws) { fail("Planilha vazia"); return }

      const rows = XLSX.utils.sheet_to_json(ws, { header: 1, blankrows: false })
      if (!rows.length) { fail("Planilha sem dados"); return }

      // Detecta cabeçalho: 1ª célula "Local" ou a coluna de mês (col 1) não-numérica.
      let start = 0
      const first = rows[0] || []
      const c0 = String(first[0] ?? "").trim().toLowerCase()
      const col1 = first[1]
      const col1Numerica = col1 !== undefined && col1 !== "" &&
        !(typeof col1 === "string" && /[a-z]/i.test(col1)) && !isNaN(impNum(col1))
      if (c0 === "local" || !col1Numerica) {
        start = 1
      }

      const ano = anoAtivo.value === "comp" ? 2026 : anoAtivo.value
      if (!faturamento.value[ano]) faturamento.value[ano] = { locais: [] }
      const arr = faturamento.value[ano].locais

      let novos = 0
      let atualizados = 0
      for (let r = start; r < rows.length; r++) {
        const row = rows[r] || []
        const nome = String(row[0] ?? "").trim()
        if (!nome || /^total$/i.test(nome)) continue

        const vals = MESES.map((m, i) => impNum(row[i + 1]))
        const total = vals.reduce((s, v) => s + v, 0)
        const existente = arr.find((l) => (l.local_nome || "").toLowerCase() === nome.toLowerCase())

        if (existente) {
          MESES.forEach((m, i) => { existente[m] = vals[i] })
          existente.total = total
          atualizados++
        } else {
          const linha = { id: null, local_nome: nome, cliente_id: null }
          MESES.forEach((m, i) => { linha[m] = vals[i] })
          linha.total = total
          arr.push(linha)
          novos++
        }
      }

      if (novos + atualizados === 0) {
        fail("Nenhum local válido encontrado na planilha")
        return
      }

      ok(`Importado para ${ano}: ${novos} novo(s), ${atualizados} atualizado(s) — clique em Salvar`)
    } catch (err) {
      console.error(err)
      fail("Erro ao ler a planilha: " + (err?.message || err))
    }
  }
  reader.readAsArrayBuffer(file)
}

async function criarLocal() {
  if (!novoLocalNome.value.trim()) return
  const ano = anoAtivo.value === 'comp' ? 2026 : anoAtivo.value

  try {
    const { data } = await axios.post('/comercial/faturamento/local', {
      ano,
      local_nome: novoLocalNome.value.trim(),
    })
    if (data.sucesso) {
      const novaLinha = { id: data.id, local_nome: novoLocalNome.value.trim(), cliente_id: null }
      MESES.forEach(m => { novaLinha[m] = 0 })
      novaLinha.total = 0

      if (!faturamento.value[ano]) faturamento.value[ano] = { locais: [] }
      faturamento.value[ano].locais.push(novaLinha)
      showNovoLocal.value = false
      ok('Local adicionado')
    }
  } catch (e) {
    fail(e.response?.data?.mensagem || 'Erro ao adicionar local')
  }
}

async function excluirLocal(local) {
  if (!confirm(`Excluir "${local.local_nome}"?`)) return
  try {
    await axios.delete(`/comercial/faturamento/${local.id}`)
    const ano = anoAtivo.value === 'comp' ? 2026 : anoAtivo.value
    const arr = faturamento.value[ano]?.locais
    if (arr) {
      const idx = arr.findIndex(l => l.id === local.id)
      if (idx >= 0) arr.splice(idx, 1)
    }
    ok('Local excluído')
  } catch (e) {
    fail('Erro ao excluir')
  }
}

// Encontra local do outro ano (comparativo)
function findLocal25(nome) {
  return locais25.value.find(l => l.local_nome === nome) || null
}
function findLocal26(nome) {
  return locais26.value.find(l => l.local_nome === nome) || null
}

// Mês atual helpers
const mesAtualIdx = new Date().getMonth()
const mesAtual = MESES[mesAtualIdx]
const anoAtual = new Date().getFullYear()
</script>

<template>
  <AuthenticatedLayout>
    <Toast />
    <div class="g360">
      <div class="view active" id="view-faturamento">
        <!-- Header -->
        <div class="page-title-row">
          <div>
            <div class="section-title">Faturamento</div>
            <div class="section-desc">Faturamento mensal por local · Auditoria e comparativo anual</div>
          </div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <!-- Tabs de ano -->
            <div style="display:flex;gap:2px;background:var(--brand-surface);border-radius:8px;padding:3px">
              <button
                v-for="a in [2025, 2026, 'comp']" :key="a"
                @click="selecionarAno(a)"
                :dusk="'tab-' + a"
                :style="{
                  padding: '5px 14px', border: 'none', borderRadius: '6px',
                  fontFamily: 'inherit', fontSize: '12px', cursor: 'pointer', transition: '.15s',
                  fontWeight: anoAtivo === a ? '700' : '600',
                  background: anoAtivo === a ? 'var(--brand-gold)' : 'transparent',
                  color: anoAtivo === a ? '#0a1225' : 'var(--text-muted)',
                  boxShadow: anoAtivo === a ? '0 1px 4px rgba(184,146,42,0.4)' : 'none',
                }"
              >{{ a === 'comp' ? 'Comparativo' : a }}</button>
            </div>

            <!-- Importar Excel -->
            <label style="display:flex;align-items:center;gap:6px;padding:7px 12px;border:1px solid var(--brand-border-soft);border-radius:8px;cursor:pointer;font-size:12px;color:var(--text-secondary);font-family:inherit;white-space:nowrap">
              <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 2h7l3 3v9H3z"/><path d="M8 7v5M6 10l2 2 2-2"/></svg>
              Importar Excel
              <input type="file" accept=".xlsx,.xls" dusk="fat-importar" style="display:none" @change="importarExcel">
            </label>

            <!-- Exportar Excel -->
            <button @click="exportarExcel" dusk="fat-exportar" class="btn btn-ghost" style="font-size:12px;display:flex;align-items:center;gap:5px;white-space:nowrap">
              <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2v9M4 8l4 4 4-4"/><path d="M2 13h12"/></svg>
              Exportar Excel
            </button>

            <!-- Adicionar local -->
            <button @click="abrirNovoLocal" dusk="btn-adicionar-local" class="btn btn-ghost" style="font-size:12px;display:flex;align-items:center;gap:5px;white-space:nowrap">
              <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>
              Adicionar local
            </button>

            <!-- Salvar -->
            <button @click="salvar" dusk="btn-salvar" class="btn btn-gold" style="font-size:12px" :disabled="salvando">
              {{ salvando ? 'Salvando...' : 'Salvar' }}
            </button>
          </div>
        </div>

        <!-- KPIs -->
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:14px">
          <div v-for="(card, i) in kpis" :key="i" class="stat-card" style="padding:16px 18px">
            <div class="stat-label" style="font-size:10px;margin-bottom:6px">{{ card.label }}</div>
            <div :style="{ fontFamily: 'Syne, sans-serif', fontWeight: 800, fontSize: card.big ? '20px' : '16px', color: card.cor }">{{ card.val }}</div>
            <div v-if="card.sub" style="font-size:10px;color:var(--text-muted);margin-top:3px">{{ card.sub }}</div>
          </div>
        </div>

        <!-- Modo comparativo: tabela comparativa -->
        <div v-if="anoAtivo === 'comp'" style="margin-bottom:16px;background:var(--brand-card);border-radius:10px;border:1px solid var(--brand-border-soft);padding:16px 20px">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);margin-bottom:12px">Faturamento Mensal — 2025 vs 2026</div>
          <div style="max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch">
            <table style="width:max-content;min-width:1300px;border-collapse:collapse;font-size:12px">
              <thead>
                <tr>
                  <th style="padding:10px 12px;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:700;white-space:nowrap;text-align:left;min-width:220px;border-bottom:2px solid var(--brand-border-soft);background:rgba(0,0,0,0.03)">Local</th>
                  <th v-for="(m, i) in MESES" :key="m" colspan="2" style="padding:6px 8px;font-size:9px;text-transform:uppercase;letter-spacing:.08em;font-weight:700;white-space:nowrap;text-align:center;border-bottom:2px solid var(--brand-border-soft);background:rgba(0,0,0,0.03);border-left:1px solid var(--brand-border-soft)">
                    {{ MESES_LABEL[i] }}
                  </th>
                  <th colspan="2" style="padding:6px 8px;font-size:9px;text-transform:uppercase;letter-spacing:.08em;font-weight:700;white-space:nowrap;text-align:center;border-bottom:2px solid var(--brand-border-soft);border-left:2px solid rgba(184,146,42,0.3);background:rgba(184,146,42,0.05)">TOTAL</th>
                </tr>
                <tr style="background:rgba(0,0,0,0.015)">
                  <th style="padding:6px 12px;font-size:9px;background:rgba(0,0,0,0.015)"></th>
                  <template v-for="m in MESES" :key="m + '_sub'">
                    <th style="padding:6px 8px;font-size:9px;text-align:right;border-left:1px solid var(--brand-border-soft);color:var(--text-muted);font-weight:700">'25</th>
                    <th style="padding:6px 8px;font-size:9px;text-align:right;color:var(--green);font-weight:700">'26</th>
                  </template>
                  <th style="padding:6px 8px;font-size:9px;text-align:right;border-left:2px solid rgba(184,146,42,0.3);color:var(--text-muted);font-weight:700;background:rgba(184,146,42,0.05)">'25</th>
                  <th style="padding:6px 8px;font-size:9px;text-align:right;color:var(--green);font-weight:700;background:rgba(184,146,42,0.05)">'26</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="nome in nomesOrdenados" :key="nome" style="transition:background .1s" @mouseover="$event.currentTarget.style.background='rgba(184,146,42,0.06)'" @mouseout="$event.currentTarget.style.background=''">
                  <td style="padding:9px 12px;border-bottom:1px solid rgba(0,0,0,0.05);text-align:left;font-weight:600;font-size:12px">{{ nome }}</td>
                  <template v-for="m in MESES" :key="nome + m">
                    <td style="padding:9px 8px;border-bottom:1px solid rgba(0,0,0,0.05);text-align:right;white-space:nowrap;font-size:11px;border-left:1px solid rgba(0,0,0,0.04);color:var(--text-muted)">{{ fmtR((findLocal25(nome) || {})[m]) }}</td>
                    <td :style="{
                      padding: '9px 8px', borderBottom: '1px solid rgba(0,0,0,0.05)', textAlign: 'right', whiteSpace: 'nowrap', fontSize: '12px',
                      fontWeight: ((findLocal26(nome) || {})[m] || 0) > 0 ? '600' : '400',
                      color: ((findLocal26(nome) || {})[m] || 0) > ((findLocal25(nome) || {})[m] || 0) ? 'var(--green)' : ((findLocal26(nome) || {})[m] || 0) < ((findLocal25(nome) || {})[m] || 0) && ((findLocal25(nome) || {})[m] || 0) > 0 ? 'var(--red)' : '',
                    }">{{ fmtR((findLocal26(nome) || {})[m]) }}</td>
                  </template>
                  <td style="padding:9px 8px;border-bottom:1px solid rgba(0,0,0,0.05);text-align:right;white-space:nowrap;font-size:11px;border-left:2px solid rgba(184,146,42,0.3);color:var(--text-muted);background:rgba(184,146,42,0.02)">{{ fmtR(findLocal25(nome) ? totalLinha(findLocal25(nome)) : 0) }}</td>
                  <td style="padding:9px 8px;border-bottom:1px solid rgba(0,0,0,0.05);text-align:right;white-space:nowrap;font-weight:700;color:var(--brand-gold);background:rgba(184,146,42,0.04)">
                    {{ fmtR(findLocal26(nome) ? totalLinha(findLocal26(nome)) : 0) }}
                    <div v-if="findLocal25(nome) && totalLinha(findLocal25(nome)) > 0" :style="{ fontSize: '9px', marginTop: '1px', color: (totalLinha(findLocal26(nome) || {}) - totalLinha(findLocal25(nome))) >= 0 ? 'var(--green)' : 'var(--red)' }">
                      {{ ((totalLinha(findLocal26(nome) || {}) - totalLinha(findLocal25(nome))) / totalLinha(findLocal25(nome)) * 100) >= 0 ? '+' : '' }}{{ ((totalLinha(findLocal26(nome) || {}) - totalLinha(findLocal25(nome))) / totalLinha(findLocal25(nome)) * 100).toFixed(1) }}%
                    </div>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td style="padding:11px 12px;border-top:2px solid rgba(184,146,42,0.25);text-align:left;font-family:Syne,sans-serif;font-weight:800;font-size:11px;white-space:nowrap;background:rgba(184,146,42,0.05)">TOTAL</td>
                  <template v-for="m in MESES" :key="m + '_foot'">
                    <td style="padding:11px 8px;border-top:2px solid rgba(184,146,42,0.25);text-align:right;font-family:Syne,sans-serif;font-weight:800;font-size:11px;white-space:nowrap;background:rgba(184,146,42,0.05);border-left:1px solid rgba(184,146,42,0.1);color:var(--text-muted)">{{ fmtR(totaisMes[m + '_25']) }}</td>
                    <td style="padding:11px 8px;border-top:2px solid rgba(184,146,42,0.25);text-align:right;font-family:Syne,sans-serif;font-weight:800;font-size:12px;white-space:nowrap;background:rgba(184,146,42,0.05);color:var(--brand-gold)">{{ fmtR(totaisMes[m]) }}</td>
                  </template>
                  <td style="padding:11px 8px;border-top:2px solid rgba(184,146,42,0.25);text-align:right;font-family:Syne,sans-serif;font-weight:800;font-size:11px;white-space:nowrap;border-left:2px solid rgba(184,146,42,0.3);color:var(--text-muted);background:rgba(184,146,42,0.05)">{{ fmtR(totaisMes.total_25) }}</td>
                  <td style="padding:11px 8px;border-top:2px solid rgba(184,146,42,0.25);text-align:right;font-family:Syne,sans-serif;font-weight:800;font-size:14px;white-space:nowrap;color:var(--brand-gold);background:rgba(184,146,42,0.05)">{{ fmtR(totaisMes.total) }}</td>
                </tr>
                <!-- Variação % -->
                <tr v-if="totaisMes.total_25 > 0" style="background:rgba(0,0,0,0.01)">
                  <td style="padding:8px 12px;font-size:10px;color:var(--text-muted);font-weight:600;background:rgba(184,146,42,0.05)">VARIAÇÃO %</td>
                  <template v-for="m in MESES" :key="m + '_var'">
                    <td colspan="2" :style="{
                      padding: '8px 8px', textAlign: 'right', fontSize: '10px', fontWeight: '700',
                      borderLeft: '1px solid rgba(184,146,42,0.1)', background: 'rgba(184,146,42,0.05)',
                      color: (totaisMes[m + '_25'] > 0) ? ((totaisMes[m] - totaisMes[m + '_25']) / totaisMes[m + '_25'] * 100 >= 0 ? 'var(--green)' : 'var(--red)') : 'var(--text-muted)',
                    }">
                      {{ totaisMes[m + '_25'] > 0 ? ((totaisMes[m] - totaisMes[m + '_25']) / totaisMes[m + '_25'] * 100 >= 0 ? '+' : '') + ((totaisMes[m] - totaisMes[m + '_25']) / totaisMes[m + '_25'] * 100).toFixed(2) + '%' : '—' }}
                    </td>
                  </template>
                  <td colspan="2" :style="{
                    padding: '8px 8px', textAlign: 'right', fontSize: '11px', fontWeight: '700',
                    borderLeft: '2px solid rgba(184,146,42,0.3)', background: 'rgba(184,146,42,0.05)',
                    color: ((totaisMes.total - totaisMes.total_25) / totaisMes.total_25 * 100 >= 0) ? 'var(--green)' : 'var(--red)',
                  }">
                    {{ ((totaisMes.total - totaisMes.total_25) / totaisMes.total_25 * 100 >= 0 ? '+' : '') + ((totaisMes.total - totaisMes.total_25) / totaisMes.total_25 * 100).toFixed(2) + '%' }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Tabela editável (modo normal) -->
        <div v-if="anoAtivo !== 'comp'" style="max-width:100%;overflow-x:auto;overflow-y:hidden;border-radius:10px;border:1px solid var(--brand-border-soft);box-shadow:0 1px 4px rgba(0,0,0,0.04);-webkit-overflow-scrolling:touch;padding-bottom:8px">
          <table style="width:max-content;min-width:1300px;border-collapse:collapse;font-size:12px">
            <thead>
              <tr>
                <th style="padding:10px 12px;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:700;white-space:nowrap;text-align:left;min-width:220px;border-bottom:2px solid var(--brand-border-soft);background:rgba(0,0,0,0.03);position:sticky;left:0;z-index:2">Local</th>
                <th v-for="(m, i) in MESES" :key="m" :style="{
                  padding: '10px 12px', fontSize: '10px', textTransform: 'uppercase', letterSpacing: '.08em', fontWeight: '700', whiteSpace: 'nowrap', textAlign: 'right',
                  borderBottom: '2px solid var(--brand-border-soft)', background: 'rgba(0,0,0,0.03)',
                  color: m === mesAtual && anoAtivo === anoAtual ? 'var(--brand-gold)' : '',
                  borderBottomColor: m === mesAtual && anoAtivo === anoAtual ? 'var(--brand-gold)' : '',
                }">{{ MESES_LABEL[i] }}/{{ String(anoAtivo).slice(2) }}</th>
                <th style="padding:10px 12px;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:700;white-space:nowrap;text-align:right;border-bottom:2px solid var(--brand-border-soft);border-left:2px solid rgba(184,146,42,0.3);background:rgba(184,146,42,0.05);color:var(--brand-gold)">TOTAL</th>
                <th style="padding:10px 12px;font-size:10px;width:36px;border-bottom:2px solid var(--brand-border-soft);background:rgba(0,0,0,0.03)"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="local in locaisAtivos" :key="local.id || local.local_nome" style="transition:background .1s" @mouseover="$event.currentTarget.style.background='rgba(184,146,42,0.06)'" @mouseout="$event.currentTarget.style.background=''">
                <td style="padding:9px 12px;border-bottom:1px solid rgba(0,0,0,0.05);text-align:left;font-weight:600;font-size:12px;position:sticky;left:0;z-index:1;background:inherit">{{ local.local_nome }}</td>
                <td v-for="m in MESES" :key="m" :style="{
                  padding: '9px 6px', borderBottom: '1px solid rgba(0,0,0,0.05)', textAlign: 'right', whiteSpace: 'nowrap',
                  background: (!local[m] || local[m] === 0) ? 'rgba(224,84,84,0.03)' : '',
                  fontWeight: m === mesAtual && anoAtivo === anoAtual && local[m] > 0 ? '700' : '',
                }">
                  <input
                    type="text"
                    :value="fmtRaw(local[m])"
                    @blur="onCellBlur(local, m, $event)"
                    style="outline:none;border:none;background:transparent;width:100%;min-width:90px;text-align:right;font-size:12px;font-variant-numeric:tabular-nums;font-family:inherit;padding:2px 4px;border-radius:4px"
                    @focus="$event.target.select()"
                  >
                </td>
                <td style="padding:9px 12px;border-bottom:1px solid rgba(0,0,0,0.05);text-align:right;white-space:nowrap;border-left:2px solid rgba(184,146,42,0.3);font-family:Syne,sans-serif;font-weight:800;font-size:13px;color:var(--brand-gold);background:rgba(184,146,42,0.04)">{{ fmtR(totalLinha(local)) }}</td>
                <td style="padding:4px;border-bottom:1px solid rgba(0,0,0,0.05);text-align:center">
                  <button @click="excluirLocal(local)" style="background:transparent;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;line-height:1;opacity:.5;transition:opacity .15s" @mouseover="$event.target.style.opacity=1" @mouseout="$event.target.style.opacity=.5">×</button>
                </td>
              </tr>
              <tr v-if="!locaisAtivos.length">
                <td colspan="15" style="padding:32px;text-align:center;color:var(--text-muted)">Nenhum dado — clique em <strong>Adicionar local</strong> ou <strong>Importar Excel</strong></td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td style="padding:11px 12px;border-top:2px solid rgba(184,146,42,0.25);text-align:left;font-family:Syne,sans-serif;font-weight:800;font-size:11px;white-space:nowrap;background:rgba(184,146,42,0.05);position:sticky;left:0;z-index:1">TOTAL {{ anoAtivo }}</td>
                <td v-for="(m, i) in MESES" :key="m" :style="{
                  padding: '11px 12px', borderTop: '2px solid rgba(184,146,42,0.25)', textAlign: 'right', fontFamily: 'Syne,sans-serif', fontWeight: '800', fontSize: '12px', whiteSpace: 'nowrap', background: 'rgba(184,146,42,0.05)',
                  color: m === mesAtual && anoAtivo === anoAtual ? 'var(--brand-gold)' : '',
                }">{{ fmtR(totaisMes[m]) }}</td>
                <td style="padding:11px 12px;border-top:2px solid rgba(184,146,42,0.25);text-align:right;font-family:Syne,sans-serif;font-weight:800;font-size:15px;white-space:nowrap;border-left:2px solid rgba(184,146,42,0.3);color:var(--brand-gold);background:rgba(184,146,42,0.05)">{{ fmtR(totaisMes.total) }}</td>
                <td style="border-top:2px solid rgba(184,146,42,0.25);background:rgba(184,146,42,0.05)"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Modal: Novo Local -->
      <div v-if="showNovoLocal" class="g360-modal-overlay" @click.self="showNovoLocal = false">
        <div class="g360-modal">
          <div class="g360-modal-header">
            <span>Adicionar Local</span>
            <button class="g360-modal-close" @click="showNovoLocal = false">✕</button>
          </div>
          <div class="g360-modal-body">
            <div class="form-group">
              <label class="form-label">Nome do local / contrato</label>
              <input class="form-input" type="text" dusk="input-novo-local" v-model="novoLocalNome" placeholder="Ex: Hospital São Lucas — Vigilância" @keyup.enter="criarLocal">
            </div>
          </div>
          <div class="g360-modal-footer">
            <button class="btn btn-ghost" @click="showNovoLocal = false">Cancelar</button>
            <button class="btn btn-gold" :disabled="!novoLocalNome.trim()" @click="criarLocal">Adicionar</button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
