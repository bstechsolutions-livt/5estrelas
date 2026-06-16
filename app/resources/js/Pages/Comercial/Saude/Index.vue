<script setup>
// ─────────────────────────────────────────────────────────────────────────────
// Saúde Contratual — portado do protótipo Gestão 360º (view-saude).
// HTML/CSS puro sob `.g360`, cores white-label (--app-primary / --brand-gold).
// Score de saúde, composição financeira, evolução mensal, alertas e metas.
// ─────────────────────────────────────────────────────────────────────────────
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { ref, computed, reactive } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import Toast from "primevue/toast"
import { useToast } from "primevue/usetoast"
import "@/../css/comercial-g360.css"

const props = defineProps({
  clientes: { type: Array, default: () => [] },
  clienteAtivo: { type: Object, default: null },
  lancamentos: { type: Array, default: () => [] },
  metas: { type: Object, default: () => ({}) },
})

const toast = useToast()
const ok = (m) => toast.add({ severity: "success", summary: "Pronto", detail: m, life: 2500 })
const fail = (m) => toast.add({ severity: "error", summary: "Erro", detail: m, life: 4000 })

// ─── Formatação ───────────────────────────────────────────────────────────────
const fmt = (v) => v != null
  ? "R$ " + Number(v).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  : "—"
const fmtPct = (v) => v != null ? Number(v).toFixed(2) + "%" : "—"

// ─── Seleção de cliente ───────────────────────────────────────────────────────
const clienteSelecionado = ref(props.clienteAtivo?.id || "")

function navCliente() {
  if (clienteSelecionado.value) {
    router.get(`/comercial/saude?cliente=${clienteSelecionado.value}`)
  }
}

// ─── KPIs computados ──────────────────────────────────────────────────────────
const totalFaturamento = computed(() => props.lancamentos.reduce((s, l) => s + l.faturamento_real, 0))
const totalCusto = computed(() => props.lancamentos.reduce((s, l) => s + l.custo_total, 0))
const resultadoBruto = computed(() => totalFaturamento.value - totalCusto.value)
const margemContrato = computed(() => totalFaturamento.value > 0 ? (resultadoBruto.value / totalFaturamento.value) * 100 : 0)

const desvioPlanoReal = computed(() => {
  if (!props.clienteAtivo || !props.lancamentos.length) return 0
  const planejado = props.clienteAtivo.valor_mensal * props.lancamentos.length
  const custoReal = totalCusto.value
  return planejado > 0 ? ((custoReal - planejado) / planejado) * 100 : 0
})

const valorEmAberto = computed(() => props.lancamentos.reduce((s, l) => s + (l.inadimplencia || 0), 0))

const score = computed(() => {
  if (!props.lancamentos.length || !props.metas.margem_alvo) return 0
  const avg = margemContrato.value
  const alvo = Number(props.metas.margem_alvo)
  const ratio = alvo > 0 ? (avg / alvo) * 100 : 0
  return Math.max(0, Math.min(100, Math.round(ratio)))
})

const scoreColor = computed(() => {
  if (score.value >= 80) return "var(--green)"
  if (score.value >= 50) return "var(--orange)"
  return "var(--red)"
})

// ─── Composição Financeira ────────────────────────────────────────────────────
const composicao = computed(() => {
  const fat = totalFaturamento.value || 1
  const folha = props.lancamentos.reduce((s, l) => s + (l.custo_folha || 0), 0)
  const beneficios = props.lancamentos.reduce((s, l) => s + (l.custo_beneficios || 0), 0)
  const insumos = props.lancamentos.reduce((s, l) => s + (l.custo_insumos || 0), 0)
  const inad = props.lancamentos.reduce((s, l) => s + (l.inadimplencia || 0), 0)
  return [
    { label: "Faturamento", valor: totalFaturamento.value, pct: 100, cor: "var(--brand-gold)" },
    { label: "Folha", valor: folha, pct: (folha / fat) * 100, cor: "var(--blue)" },
    { label: "Benefícios", valor: beneficios, pct: (beneficios / fat) * 100, cor: "var(--orange)" },
    { label: "Insumos", valor: insumos, pct: (insumos / fat) * 100, cor: "var(--green)" },
    { label: "Inadimplência", valor: inad, pct: (inad / fat) * 100, cor: "var(--red)" },
  ]
})

// ─── Evolução mensal — status badge ──────────────────────────────────────────
function statusMargem(margem) {
  const alvo = Number(props.metas.margem_alvo || 3)
  const min = Number(props.metas.margem_minima || 2.5)
  if (margem >= alvo) return { cls: "badge-green", texto: "Saudável" }
  if (margem >= min) return { cls: "badge-orange", texto: "Atenção" }
  return { cls: "badge-red", texto: "Crítico" }
}

// ─── Modal lançamento ─────────────────────────────────────────────────────────
const modalLanc = ref(false)
const lancForm = reactive({
  mes_ref: "",
  faturamento_real: "",
  custo_folha: "",
  custo_beneficios: "",
  custo_insumos: "",
  inadimplencia: "",
  obs: "",
})
const salvandoLanc = ref(false)

function abrirLancar() {
  Object.assign(lancForm, { mes_ref: "", faturamento_real: "", custo_folha: "", custo_beneficios: "", custo_insumos: "", inadimplencia: "", obs: "" })
  modalLanc.value = true
}

async function salvarLanc() {
  if (!props.clienteAtivo) return
  salvandoLanc.value = true
  try {
    await axios.post(`/comercial/saude/${props.clienteAtivo.id}/lancamento`, {
      mes_ref: lancForm.mes_ref,
      faturamento_real: Number(lancForm.faturamento_real) || 0,
      custo_folha: Number(lancForm.custo_folha) || 0,
      custo_beneficios: Number(lancForm.custo_beneficios) || 0,
      custo_insumos: Number(lancForm.custo_insumos) || 0,
      inadimplencia: Number(lancForm.inadimplencia) || 0,
      obs: lancForm.obs || null,
    })
    ok("Lançamento salvo!")
    modalLanc.value = false
    router.reload({ only: ["lancamentos"], preserveScroll: true })
  } catch (e) {
    if (e?.response?.status === 422) fail("Dados inválidos — verifique os campos")
    else if (e?.response?.status === 403) fail("Sem permissão")
    else fail("Falha ao salvar lançamento")
  } finally {
    salvandoLanc.value = false
  }
}

// ─── Excluir lançamento ───────────────────────────────────────────────────────
async function excluirLanc(lanc) {
  if (!confirm(`Excluir lançamento ${lanc.mes_ref}?`)) return
  try {
    await axios.delete(`/comercial/saude/${props.clienteAtivo.id}/lancamento/${lanc.id}`)
    ok("Lançamento excluído")
    router.reload({ only: ["lancamentos"], preserveScroll: true })
  } catch (e) {
    fail("Falha ao excluir")
  }
}

// ─── Alertas automáticos ──────────────────────────────────────────────────────
const alertas = computed(() => {
  const list = []
  if (!props.lancamentos.length) return list
  const alvo = Number(props.metas.margem_alvo || 3)
  const maxFolha = Number(props.metas.max_folha_pct || 75)
  const maxInad = Number(props.metas.inadimplencia_max || 0)

  if (margemContrato.value < alvo) {
    list.push({ tipo: "warning", texto: `Margem abaixo da meta alvo (${fmtPct(margemContrato.value)} < ${fmtPct(alvo)})` })
  }

  const folhaPct = totalFaturamento.value > 0 ? (props.lancamentos.reduce((s, l) => s + (l.custo_folha || 0), 0) / totalFaturamento.value) * 100 : 0
  if (folhaPct > maxFolha) {
    list.push({ tipo: "danger", texto: `Folha acima do teto (${fmtPct(folhaPct)} > ${fmtPct(maxFolha)})` })
  }

  if (maxInad > 0 && valorEmAberto.value > maxInad) {
    list.push({ tipo: "danger", texto: `Inadimplência acima do limite (${fmt(valorEmAberto.value)} > ${fmt(maxInad)})` })
  }

  // último mês
  const ultimo = props.lancamentos[props.lancamentos.length - 1]
  if (ultimo && ultimo.margem < Number(props.metas.margem_minima || 2.5)) {
    list.push({ tipo: "danger", texto: `Último mês (${ultimo.mes_ref}) com margem crítica: ${fmtPct(ultimo.margem)}` })
  }

  return list
})

// ─── Metas (editável) ─────────────────────────────────────────────────────────
const metasForm = reactive({
  margem_minima: props.metas.margem_minima ?? 2.5,
  margem_alvo: props.metas.margem_alvo ?? 3.0,
  max_folha_pct: props.metas.max_folha_pct ?? 75,
  inadimplencia_max: props.metas.inadimplencia_max ?? 0,
})
const salvandoMetas = ref(false)

async function salvarMetas() {
  if (!props.clienteAtivo) return
  salvandoMetas.value = true
  try {
    await axios.post(`/comercial/saude/${props.clienteAtivo.id}/metas`, {
      margem_minima: Number(metasForm.margem_minima),
      margem_alvo: Number(metasForm.margem_alvo),
      max_folha_pct: Number(metasForm.max_folha_pct),
      inadimplencia_max: Number(metasForm.inadimplencia_max),
    })
    ok("Metas atualizadas!")
    router.reload({ only: ["metas"], preserveScroll: true })
  } catch (e) {
    if (e?.response?.status === 403) fail("Sem permissão para alterar metas")
    else fail("Falha ao salvar metas")
  } finally {
    salvandoMetas.value = false
  }
}

// SVG score ring
const circumference = 2 * Math.PI * 45
const scoreDash = computed(() => (score.value / 100) * circumference)
</script>

<template>
  <AuthenticatedLayout>
    <Toast />
    <div class="g360">
      <div class="view active" id="view-saude">
        <!-- Cabeçalho -->
        <div class="page-title-row">
          <div>
            <div class="section-title">Saúde Contratual</div>
            <div class="section-desc">Score de saúde financeira, composição e evolução dos contratos</div>
          </div>
          <div style="display:flex;gap:10px;align-items:center">
            <select
              v-model="clienteSelecionado"
              class="form-select"
              dusk="saude-select-cliente"
              @change="navCliente"
              style="width:280px"
            >
              <option value="">Selecione um contrato...</option>
              <option v-for="c in clientes" :key="c.id" :value="c.id">{{ c.nome }}</option>
            </select>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="!clienteAtivo" style="text-align:center;padding:80px 20px">
          <div style="font-size:48px;margin-bottom:16px;opacity:0.3">📋</div>
          <div style="font-size:18px;font-weight:600;color:var(--text-secondary);margin-bottom:8px">Selecione um contrato para análise</div>
          <div style="font-size:13px;color:var(--text-muted)">Escolha um cliente no seletor acima para visualizar a saúde contratual</div>
        </div>

        <!-- Conteúdo quando há cliente selecionado -->
        <template v-if="clienteAtivo">
          <!-- Top bar: Score + 4 KPIs -->
          <div style="display:grid;grid-template-columns:140px 1fr 1fr 1fr 1fr;gap:16px;margin-bottom:28px">
            <!-- Score ring -->
            <div class="stat-card" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px">
              <svg width="100" height="100" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(0,0,0,0.06)" stroke-width="8" />
                <circle
                  cx="50" cy="50" r="45" fill="none"
                  :stroke="scoreColor" stroke-width="8"
                  stroke-linecap="round"
                  :stroke-dasharray="`${scoreDash} ${circumference}`"
                  transform="rotate(-90 50 50)"
                  style="transition:stroke-dasharray 0.6s"
                />
                <text x="50" y="54" text-anchor="middle" font-size="22" font-weight="800" font-family="Syne,sans-serif" :fill="scoreColor">{{ score }}</text>
              </svg>
              <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);margin-top:4px;font-weight:600">Score</div>
            </div>

            <div class="stat-card">
              <div class="stat-label">Resultado Bruto</div>
              <div class="stat-value" style="font-size:20px" :style="{ color: resultadoBruto >= 0 ? 'var(--green)' : 'var(--red)' }">{{ fmt(resultadoBruto) }}</div>
              <div class="stat-sub">Fat. - Custo total</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Margem do Contrato</div>
              <div class="stat-value" style="font-size:20px" :style="{ color: margemContrato >= Number(metas.margem_alvo || 3) ? 'var(--green)' : 'var(--orange)' }">{{ fmtPct(margemContrato) }}</div>
              <div class="stat-sub">Resultado / Faturamento</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Desvio Plan.×Real</div>
              <div class="stat-value" style="font-size:20px" :style="{ color: desvioPlanoReal <= 0 ? 'var(--green)' : 'var(--red)' }">{{ desvioPlanoReal >= 0 ? '+' : '' }}{{ fmtPct(desvioPlanoReal) }}</div>
              <div class="stat-sub">Custo real vs planejado</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Valor em Aberto</div>
              <div class="stat-value" style="font-size:20px" :style="{ color: valorEmAberto > 0 ? 'var(--red)' : 'var(--green)' }">{{ fmt(valorEmAberto) }}</div>
              <div class="stat-sub">Inadimplência acumulada</div>
            </div>
          </div>

          <!-- Grid principal: esquerda + direita -->
          <div style="display:grid;grid-template-columns:1fr 340px;gap:24px">
            <!-- Coluna esquerda -->
            <div>
              <!-- Composição Financeira -->
              <div class="module-card" style="margin-bottom:24px">
                <div class="module-header">
                  <div class="module-title">Composição Financeira</div>
                </div>
                <div class="module-body">
                  <div v-for="item in composicao" :key="item.label" style="margin-bottom:12px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                      <span style="font-size:12px;font-weight:600;color:var(--text-secondary)">{{ item.label }}</span>
                      <span style="font-size:12px;color:var(--text-muted)">{{ fmt(item.valor) }} ({{ item.pct.toFixed(1) }}%)</span>
                    </div>
                    <div style="height:8px;background:rgba(0,0,0,0.04);border-radius:4px;overflow:hidden">
                      <div :style="{ width: item.pct + '%', height: '100%', background: item.cor, borderRadius: '4px', transition: 'width 0.4s' }"></div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Evolução Mensal -->
              <div class="module-card">
                <div class="module-header">
                  <div class="module-title">Evolução Mensal</div>
                  <button class="btn btn-gold" dusk="saude-btn-lancar" @click="abrirLancar()" style="margin-left:auto">+ Lançar mês</button>
                </div>
                <div style="overflow-x:auto">
                  <table style="width:100%;border-collapse:collapse;min-width:700px">
                    <thead>
                      <tr>
                        <th style="text-align:left">Mês</th>
                        <th style="text-align:right">Fat. Real</th>
                        <th style="text-align:right">Custo Real</th>
                        <th style="text-align:right">Resultado</th>
                        <th style="text-align:center">Margem %</th>
                        <th style="text-align:center">Status</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="l in lancamentos" :key="l.id">
                        <td style="font-weight:600">{{ l.mes_ref }}</td>
                        <td style="text-align:right">{{ fmt(l.faturamento_real) }}</td>
                        <td style="text-align:right">{{ fmt(l.custo_total) }}</td>
                        <td style="text-align:right;font-weight:600" :style="{ color: l.resultado >= 0 ? 'var(--green)' : 'var(--red)' }">{{ fmt(l.resultado) }}</td>
                        <td style="text-align:center;font-weight:600">{{ fmtPct(l.margem) }}</td>
                        <td style="text-align:center"><span class="badge" :class="statusMargem(l.margem).cls">{{ statusMargem(l.margem).texto }}</span></td>
                        <td style="text-align:center">
                          <button @click="excluirLanc(l)" class="saude-acao saude-acao-del" :dusk="'saude-del-' + l.id" title="Excluir">
                            <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10M6 4V2h4v2M5 4v9a1 1 0 001 1h4a1 1 0 001-1V4"/></svg>
                          </button>
                        </td>
                      </tr>
                      <tr v-if="!lancamentos.length">
                        <td colspan="7" style="text-align:center;padding:28px;color:var(--text-muted)">Nenhum lançamento registrado</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Coluna direita -->
            <div>
              <!-- Alertas Automáticos -->
              <div class="module-card" style="margin-bottom:24px">
                <div class="module-header">
                  <div class="module-title">Alertas</div>
                </div>
                <div class="module-body">
                  <div v-if="!alertas.length" style="text-align:center;padding:20px;color:var(--text-muted);font-size:13px">
                    ✅ Nenhum alerta — contrato saudável
                  </div>
                  <div v-for="(a, i) in alertas" :key="i" style="margin-bottom:10px;display:flex;align-items:flex-start;gap:8px">
                    <span v-if="a.tipo === 'danger'" style="color:var(--red);font-size:14px">⚠️</span>
                    <span v-else style="color:var(--orange);font-size:14px">⚡</span>
                    <span style="font-size:12px;color:var(--text-secondary)">{{ a.texto }}</span>
                  </div>
                </div>
              </div>

              <!-- Metas -->
              <div class="module-card">
                <div class="module-header">
                  <div class="module-title">Metas</div>
                </div>
                <div class="module-body">
                  <div class="form-group" style="margin-bottom:12px">
                    <label class="form-label">Margem Mínima (%)</label>
                    <input v-model="metasForm.margem_minima" type="number" step="0.01" class="form-input">
                  </div>
                  <div class="form-group" style="margin-bottom:12px">
                    <label class="form-label">Margem Alvo (%)</label>
                    <input v-model="metasForm.margem_alvo" type="number" step="0.01" class="form-input">
                  </div>
                  <div class="form-group" style="margin-bottom:12px">
                    <label class="form-label">Máximo Folha (%)</label>
                    <input v-model="metasForm.max_folha_pct" type="number" step="0.01" class="form-input" dusk="saude-form-folha">
                  </div>
                  <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">Inadimplência Máx. (R$)</label>
                    <input v-model="metasForm.inadimplencia_max" type="number" step="0.01" class="form-input">
                  </div>
                  <button class="btn btn-gold" dusk="saude-salvar-metas" :disabled="salvandoMetas" @click="salvarMetas()" style="width:100%">
                    {{ salvandoMetas ? 'Salvando...' : 'Salvar Metas' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Modal: Lançar mês -->
      <div class="modal-overlay" :class="{ open: modalLanc }" @click.self="modalLanc = false">
        <div class="modal" style="width:560px;max-width:94vw">
          <div class="modal-title">Lançar Mês</div>
          <div class="form-group" style="margin-bottom:14px">
            <label class="form-label">Mês de Referência</label>
            <input v-model="lancForm.mes_ref" type="month" class="form-input">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
            <div class="form-group">
              <label class="form-label">Faturamento Real</label>
              <input v-model="lancForm.faturamento_real" type="number" step="0.01" class="form-input" dusk="saude-form-fat">
            </div>
            <div class="form-group">
              <label class="form-label">Custo Folha</label>
              <input v-model="lancForm.custo_folha" type="number" step="0.01" class="form-input" dusk="saude-form-folha-lanc">
            </div>
            <div class="form-group">
              <label class="form-label">Custo Benefícios</label>
              <input v-model="lancForm.custo_beneficios" type="number" step="0.01" class="form-input">
            </div>
            <div class="form-group">
              <label class="form-label">Custo Insumos</label>
              <input v-model="lancForm.custo_insumos" type="number" step="0.01" class="form-input">
            </div>
            <div class="form-group">
              <label class="form-label">Inadimplência</label>
              <input v-model="lancForm.inadimplencia" type="number" step="0.01" class="form-input">
            </div>
            <div class="form-group">
              <label class="form-label">Observação</label>
              <input v-model="lancForm.obs" type="text" class="form-input">
            </div>
          </div>
          <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-ghost" @click="modalLanc = false">Cancelar</button>
            <button class="btn btn-gold" dusk="saude-salvar-lanc" :disabled="salvandoLanc" @click="salvarLanc()">{{ salvandoLanc ? 'Salvando...' : 'Salvar' }}</button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.g360 .saude-acao { background: transparent; border: none; color: var(--text-muted); cursor: pointer; padding: 2px 6px; transition: color 0.15s; }
.g360 .saude-acao:hover { color: var(--brand-gold); }
.g360 .saude-acao-del:hover { color: var(--red); }
</style>
