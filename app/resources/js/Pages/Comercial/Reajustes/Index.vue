<script setup>
// ─────────────────────────────────────────────────────────────────────────────
// Reajuste de Contratos — portado do protótipo Gestão 360º (view-reajuste).
// HTML/CSS puro sob `.g360`, cores white-label (--app-primary). Agrupado por
// empresa (Segurança / Apoio). Dados reais via props (ComercialReajusteController).
//
// Fatia atual: listagem + KPIs + busca/filtro + mudar status + detalhe dos itens
// + excluir. A planilha de detalhe com configuração de índice e o assistente de
// "novo reajuste" do protótipo ficam para a próxima fatia (TODO).
// ─────────────────────────────────────────────────────────────────────────────
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { ref, computed, reactive } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import Toast from "primevue/toast"
import { useToast } from "primevue/usetoast"
import { swalConfirm } from "@/utils/globalFunctions"
import "@/../css/comercial-g360.css"

const props = defineProps({
  reajustes: { type: Array, default: () => [] },
  statusLabels: { type: Object, default: () => ({}) },
  clientes: { type: Array, default: () => [] },
})

const toast = useToast()
const ok = (m) => toast.add({ severity: "success", summary: "Pronto", detail: m, life: 2500 })
const fail = (m) => toast.add({ severity: "error", summary: "Erro", detail: m, life: 4000 })

const STATUS = ["pendente", "calculado", "enviado", "aprovado", "recusado"]
const sitClass = (s) => ({
  aprovado: "badge-green", recusado: "badge-red", enviado: "badge-gold",
  calculado: "badge-blue", pendente: "badge-orange",
}[s] || "badge-orange")

const fmt = (v) => v != null
  ? "R$ " + Number(v).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  : "—"
const fmtK = (v) => v >= 1e6
  ? "R$ " + (v / 1e6).toLocaleString("pt-BR", { minimumFractionDigits: 2 }) + "M"
  : "R$ " + (v / 1000).toLocaleString("pt-BR", { minimumFractionDigits: 0 }) + "k"

// ─── Filtros ──────────────────────────────────────────────────────────────────
const fBusca = ref("")
const fStatus = ref("")

const lista = computed(() => {
  const busca = fBusca.value.toLowerCase()
  return props.reajustes.filter((r) => {
    const mb = !busca || (r.cliente_nome || "").toLowerCase().includes(busca)
    const ms = !fStatus.value || r.status === fStatus.value
    return mb && ms
  })
})

const kpis = computed(() => {
  const base = lista.value
  return {
    total: base.length,
    pendente: base.filter((r) => r.status === "pendente").length,
    enviado: base.filter((r) => r.status === "enviado").length,
    aprovado: base.filter((r) => r.status === "aprovado").length,
    total2026: base.reduce((s, r) => s + (r.novo_valor || 0), 0),
    impacto: base.reduce((s, r) => s + (r.impacto_mensal || 0), 0),
  }
})

// ─── Seções por empresa ─────────────────────────────────────────────────────────
const grupoSeg = computed(() => lista.value.filter((r) => (r.empresa || "").startsWith("seg")))
const grupoApoio = computed(() => lista.value.filter((r) => (r.empresa || "").startsWith("apoio")))
const somaNovo = (arr) => arr.reduce((s, r) => s + (r.novo_valor || 0), 0)

function recarregar() {
  router.reload({ only: ["reajustes"], preserveScroll: true })
}

// ─── Detalhe (itens) ──────────────────────────────────────────────────────────
const modalDetalhe = ref(false)
const reajusteDetalhe = ref(null)
function verDetalhe(r) {
  reajusteDetalhe.value = r
  modalDetalhe.value = true
}

// ─── Alterar status ───────────────────────────────────────────────────────────
const modalStatus = ref(false)
const reajusteStatus = ref(null)
function alterarStatus(r) {
  reajusteStatus.value = r
  modalStatus.value = true
}
async function aplicarStatus(novo) {
  const r = reajusteStatus.value
  if (!r) return
  if (novo === "recusado") {
    const { isConfirmed } = await swalConfirm(
      "Recusar reajuste?",
      `O reajuste de ${r.cliente_nome} será marcado como RECUSADO.`,
      "Recusar", "Cancelar", { danger: true },
    )
    if (!isConfirmed) return
  }
  try {
    await axios.patch(`/comercial/reajustes/${r.id}/status`, { status: novo })
    ok("Status atualizado!")
    modalStatus.value = false
    reajusteStatus.value = null
    recarregar()
  } catch (e) {
    if (e?.response?.status === 403) fail("Você não tem permissão para alterar o status")
    else fail("Falha ao atualizar o status")
  }
}

// ─── Excluir ──────────────────────────────────────────────────────────────────
async function excluir(r) {
  const { isConfirmed } = await swalConfirm(
    "Excluir reajuste?",
    `O reajuste de ${r.cliente_nome} será excluído.`,
    "Excluir", "Cancelar", { danger: true, icon: "trash" },
  )
  if (!isConfirmed) return
  try {
    await axios.delete(`/comercial/reajustes/${r.id}`)
    ok("Reajuste excluído")
    recarregar()
  } catch (e) {
    if (e?.response?.status === 403) fail("Você não tem permissão para excluir")
    else fail("Falha ao excluir")
  }
}
// ─── Novo Reajuste (Iniciar) ────────────────────────────────────────────────────
const modalNovo = ref(false)
const buscaCli = ref("")
const novoForm = reactive({ cliente_id: null, cliente_nome: "", valor_atual: 0, pct: "", tipo: "manual", competencia: "", obs: "" })
const salvandoNovo = ref(false)

const clientesFiltrados = computed(() => {
  const b = buscaCli.value.toLowerCase()
  return props.clientes.filter((c) => !b || (c.nome || "").toLowerCase().includes(b)).slice(0, 50)
})

function abrirNovo() {
  Object.assign(novoForm, { cliente_id: null, cliente_nome: "", valor_atual: 0, pct: "", tipo: "manual", competencia: "", obs: "" })
  buscaCli.value = ""
  modalNovo.value = true
}
function escolherCliente(c) {
  novoForm.cliente_id = c.id
  novoForm.cliente_nome = c.nome
  novoForm.valor_atual = c.valor_mensal || 0
}
async function salvarNovo() {
  if (!novoForm.cliente_nome) { fail("Selecione um cliente"); return }
  if (novoForm.pct === "" || isNaN(Number(novoForm.pct))) { fail("Informe o percentual"); return }
  salvandoNovo.value = true
  try {
    await axios.post("/comercial/reajustes", {
      cliente_id: novoForm.cliente_id,
      cliente_nome: novoForm.cliente_nome,
      pct: Number(novoForm.pct),
      tipo: novoForm.tipo,
      competencia: novoForm.competencia || null,
      obs: novoForm.obs || null,
      valor_atual: Number(novoForm.valor_atual) || 0,
    })
    ok("Reajuste iniciado!")
    modalNovo.value = false
    recarregar()
  } catch (e) {
    if (e?.response?.status === 403) fail("Sem permissão para criar reajuste")
    else fail("Falha ao criar o reajuste")
  } finally {
    salvandoNovo.value = false
  }
}

// ─── Editar planilha ──────────────────────────────────────────────────────────
const modalEditar = ref(false)
const editId = ref(null)
const editForm = reactive({ tipo: "manual", pct: "", competencia: "", obs: "", itens: [] })
const salvandoEdit = ref(false)

function abrirEditar(r) {
  editId.value = r.id
  editForm.tipo = r.tipo || "manual"
  editForm.pct = r.pct ?? ""
  editForm.competencia = r.competencia || ""
  editForm.obs = r.obs || ""
  // Clona os itens com os campos editáveis.
  editForm.itens = (r.itens || []).map((it) => ({
    nome: it.nome || "—",
    valorAtual: Number(it.valorAtual || 0),
    pct: Number(it.pct ?? r.pct ?? 0),
    selecionado: it.selecionado !== false,
  }))
  if (!editForm.itens.length) {
    editForm.itens = [{ nome: "Contrato", valorAtual: Number(r.valor_atual || 0), pct: Number(r.pct || 0), selecionado: true }]
  }
  modalEditar.value = true
}

// Aplica o % global do índice a todos os itens selecionados.
function aplicarIndice() {
  const p = Number(editForm.pct)
  if (isNaN(p)) return
  editForm.itens.forEach((it) => { if (it.selecionado) it.pct = p })
}

const editItemNovo = (it) => round2(Number(it.valorAtual || 0) * (1 + Number(it.pct || 0) / 100))
const editItemVar = (it) => round2(editItemNovo(it) - Number(it.valorAtual || 0))
const round2 = (n) => Math.round(n * 100) / 100

const editResumo = computed(() => {
  let atual = 0, novo = 0
  editForm.itens.forEach((it) => {
    if (it.selecionado) { atual += Number(it.valorAtual || 0); novo += editItemNovo(it) }
  })
  return { atual: round2(atual), novo: round2(novo), impacto: round2(novo - atual), sel: editForm.itens.filter((i) => i.selecionado).length }
})

async function salvarEditar() {
  salvandoEdit.value = true
  try {
    await axios.put(`/comercial/reajustes/${editId.value}`, {
      tipo: editForm.tipo,
      pct: editForm.pct === "" ? null : Number(editForm.pct),
      competencia: editForm.competencia || null,
      obs: editForm.obs || null,
      itens: editForm.itens,
    })
    ok("Reajuste salvo!")
    modalEditar.value = false
    recarregar()
  } catch (e) {
    if (e?.response?.status === 403) fail("Sem permissão para editar")
    else if (e?.response?.status === 422) fail("Dados inválidos")
    else fail("Falha ao salvar")
  } finally {
    salvandoEdit.value = false
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <Toast />
    <div class="g360">
      <div class="view active" id="view-reajuste">
        <!-- Cabeçalho -->
        <div class="page-title-row">
          <div>
            <div class="section-title">Reajuste de Contratos</div>
            <div class="section-desc">Acompanhe e aplique reajustes — por empresa e estado</div>
          </div>
          <div style="display:flex;gap:10px;align-items:center">
            <button class="btn btn-gold" dusk="raj-novo" @click="abrirNovo()">+ Iniciar Reajuste</button>
          </div>
        </div>

        <!-- KPIs -->
        <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:24px">
          <div class="stat-card">
            <div class="stat-label">Total de Contratos</div>
            <div class="stat-value">{{ kpis.total }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Em Análise</div>
            <div class="stat-value" style="color:var(--orange)">{{ kpis.pendente }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Enviados</div>
            <div class="stat-value" style="color:var(--brand-gold)">{{ kpis.enviado }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Aprovados</div>
            <div class="stat-value" style="color:var(--green)">{{ kpis.aprovado }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Total 2026</div>
            <div class="stat-value" style="font-size:16px;color:var(--brand-gold)">{{ fmtK(kpis.total2026) }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px">valor novo total</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Diferença Total</div>
            <div class="stat-value" style="font-size:16px;color:var(--green)">{{ fmtK(kpis.impacto) }}</div>
            <div style="font-size:10px;color:var(--text-muted);margin-top:2px">impacto mensal</div>
          </div>
        </div>

        <!-- Busca -->
        <div style="display:flex;gap:10px;margin-bottom:24px;align-items:center;flex-wrap:wrap">
          <input v-model="fBusca" type="text" class="form-input" dusk="raj-busca" placeholder="Buscar cliente..." style="width:280px;max-width:100%">
          <select v-model="fStatus" class="form-select" dusk="raj-filtro-status" style="width:160px">
            <option value="">Todos os status</option>
            <option v-for="s in STATUS" :key="s" :value="s">{{ statusLabels[s] || s }}</option>
          </select>
          <span style="margin-left:auto;font-size:12px;color:var(--text-muted)">{{ lista.length }} de {{ reajustes.length }}</span>
        </div>

        <!-- Seções por empresa -->
        <template v-for="grupo in [
          { key: 'seg', nome: '5 Estrelas Sistemas de Segurança Ltda', sub: 'Vigilância · Brigada · Bombeiro Civil', sigla: 'SEG', cor: 'var(--brand-gold)', itens: grupoSeg },
          { key: 'apoio', nome: '5 Estrelas Serviços de Apoio Administrativo', sub: 'Portaria · Limpeza · Facilities', sigla: 'APOIO', cor: 'var(--blue)', itens: grupoApoio },
        ]" :key="grupo.key">
          <div style="margin-bottom:36px">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid var(--brand-border-soft)">
              <span style="font-family:Syne,sans-serif;font-weight:800;font-size:13px" :style="{ color: grupo.cor }">{{ grupo.sigla }}</span>
              <div style="flex:1">
                <div style="font-weight:700;font-size:14px">{{ grupo.nome }}</div>
                <div style="font-size:11px;color:var(--text-muted)">{{ grupo.sub }}</div>
              </div>
              <div style="text-align:right">
                <div style="font-family:Syne,sans-serif;font-weight:800;font-size:14px" :style="{ color: grupo.cor }">{{ fmt(somaNovo(grupo.itens)) }}</div>
                <div style="font-size:11px;color:var(--text-muted)">{{ grupo.itens.length }} contrato{{ grupo.itens.length !== 1 ? 's' : '' }}</div>
              </div>
            </div>

            <div v-if="grupo.itens.length === 0" style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px">
              Nenhum reajuste no filtro atual
            </div>

            <div class="contracts-table-wrap" v-else style="overflow-x:auto">
              <table style="width:100%;border-collapse:collapse;min-width:820px">
                <thead>
                  <tr>
                    <th style="text-align:left">Cliente</th>
                    <th style="text-align:center">%</th>
                    <th style="text-align:right">Valor Atual</th>
                    <th style="text-align:right">Novo Valor</th>
                    <th style="text-align:right">Impacto</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="r in grupo.itens" :key="r.id" :dusk="'raj-row-' + r.id">
                    <td style="font-weight:600;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" :title="r.cliente_nome">{{ r.cliente_nome }}</td>
                    <td style="text-align:center;font-weight:700;color:var(--brand-gold)">{{ Number(r.pct).toFixed(2) }}%</td>
                    <td style="text-align:right;font-size:12px;color:var(--text-secondary)">{{ fmt(r.valor_atual) }}</td>
                    <td style="text-align:right;font-weight:700;font-family:Syne,sans-serif">{{ fmt(r.novo_valor) }}</td>
                    <td style="text-align:right;font-size:12px;color:var(--green);font-weight:600">{{ fmt(r.impacto_mensal) }}</td>
                    <td><span class="badge" :class="sitClass(r.status)">{{ statusLabels[r.status] || r.status }}</span></td>
                    <td style="white-space:nowrap">
                      <button @click="verDetalhe(r)" class="raj-acao" :dusk="'raj-detalhe-' + r.id" title="Ver itens">
                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M2 8s2.5-4.5 6-4.5S14 8 14 8s-2.5 4.5-6 4.5S2 8 2 8z"/><circle cx="8" cy="8" r="1.8"/></svg>
                      </button>
                      <button @click="abrirEditar(r)" class="raj-acao" :dusk="'raj-editar-' + r.id" title="Editar planilha">
                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M11 2l3 3-9 9H2v-3l9-9z"/></svg>
                      </button>
                      <button @click="alterarStatus(r)" class="raj-acao" :dusk="'raj-status-' + r.id" title="Alterar status">
                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6"/><path d="M8 5v4l2 2"/></svg>
                      </button>
                      <button @click="excluir(r)" class="raj-acao raj-acao-del" :dusk="'raj-excluir-' + r.id" title="Excluir">
                        <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10M6 4V2h4v2M5 4v9a1 1 0 001 1h4a1 1 0 001-1V4"/></svg>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </template>
      </div>

      <!-- Modal: detalhe (itens) -->
      <div class="modal-overlay" :class="{ open: modalDetalhe }" @click.self="modalDetalhe = false">
        <div class="modal" style="width:640px;max-width:94vw">
          <div class="modal-title">Itens do Reajuste — {{ reajusteDetalhe?.cliente_nome }}</div>
          <div v-if="reajusteDetalhe" style="margin-bottom:14px;font-size:12px;color:var(--text-secondary)">
            {{ Number(reajusteDetalhe.pct).toFixed(2) }}% · {{ fmt(reajusteDetalhe.valor_atual) }} → {{ fmt(reajusteDetalhe.novo_valor) }}
          </div>
          <div class="contracts-table-wrap" style="overflow-x:auto;margin-bottom:16px">
            <table style="width:100%;border-collapse:collapse;min-width:520px">
              <thead>
                <tr>
                  <th style="text-align:left">Item</th>
                  <th style="text-align:right">Atual</th>
                  <th style="text-align:center">%</th>
                  <th style="text-align:right">Novo</th>
                  <th style="text-align:right">Variação</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(it, i) in (reajusteDetalhe?.itens || [])" :key="i">
                  <td style="text-align:left;font-size:12px">{{ it.nome || '—' }}</td>
                  <td style="text-align:right;font-size:12px">{{ fmt(it.valorAtual) }}</td>
                  <td style="text-align:center;font-size:12px;color:var(--brand-gold)">{{ Number(it.pct || 0).toFixed(2) }}%</td>
                  <td style="text-align:right;font-size:12px;font-weight:600">{{ fmt(it.novoValor) }}</td>
                  <td style="text-align:right;font-size:12px;color:var(--green)">{{ fmt(it.variacao) }}</td>
                </tr>
                <tr v-if="!(reajusteDetalhe?.itens || []).length">
                  <td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">Sem itens detalhados</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div style="display:flex;justify-content:flex-end">
            <button class="btn btn-ghost" @click="modalDetalhe = false">Fechar</button>
          </div>
        </div>
      </div>

      <!-- Modal: Iniciar Reajuste (novo) -->
      <div class="modal-overlay" :class="{ open: modalNovo }" @click.self="modalNovo = false">
        <div class="modal" style="width:560px;max-width:94vw">
          <div class="modal-title">Iniciar Reajuste</div>
          <div style="margin-bottom:12px">
            <label class="form-label">Selecionar Cliente</label>
            <input v-model="buscaCli" type="text" class="form-input" dusk="raj-novo-busca" placeholder="Buscar cliente..." style="margin-top:6px">
          </div>
          <div style="max-height:220px;overflow-y:auto;border:1px solid var(--brand-border-soft);border-radius:8px;margin-bottom:12px">
            <button v-for="c in clientesFiltrados" :key="c.id" :dusk="'raj-novo-cli-' + c.id"
              @click="escolherCliente(c)"
              :style="{ background: novoForm.cliente_id === c.id ? 'color-mix(in srgb, var(--app-primary) 10%, transparent)' : 'transparent' }"
              style="width:100%;text-align:left;border:none;border-bottom:1px solid var(--brand-border-soft);padding:9px 12px;cursor:pointer;font-family:inherit;display:flex;justify-content:space-between;gap:10px">
              <span style="font-size:12px;font-weight:600">{{ c.nome }}</span>
              <span style="font-size:11px;color:var(--text-muted)">{{ fmt(c.valor_mensal) }}</span>
            </button>
            <div v-if="!clientesFiltrados.length" style="padding:14px;text-align:center;color:var(--text-muted);font-size:12px">Nenhum cliente</div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
            <div class="form-group"><label class="form-label">Percentual (%)</label><input v-model="novoForm.pct" type="number" step="0.01" class="form-input" dusk="raj-novo-pct"></div>
            <div class="form-group"><label class="form-label">Valor Atual</label><input v-model="novoForm.valor_atual" type="number" step="0.01" class="form-input"></div>
            <div class="form-group"><label class="form-label">Competência</label><input v-model="novoForm.competencia" type="month" class="form-input"></div>
            <div class="form-group"><label class="form-label">Observação</label><input v-model="novoForm.obs" type="text" class="form-input"></div>
          </div>
          <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-ghost" @click="modalNovo = false">Cancelar</button>
            <button class="btn btn-gold" dusk="raj-novo-salvar" :disabled="salvandoNovo" @click="salvarNovo()">{{ salvandoNovo ? 'Salvando...' : 'Iniciar' }}</button>
          </div>
        </div>
      </div>

      <!-- Modal: Editar planilha -->
      <div class="modal-overlay" :class="{ open: modalEditar }" @click.self="modalEditar = false">
        <div class="modal" style="width:760px;max-width:96vw">
          <div class="modal-title">Editar Reajuste</div>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px">
            <div class="form-group"><label class="form-label">Tipo de Índice</label>
              <select v-model="editForm.tipo" class="form-select">
                <option value="inpc">INPC</option><option value="ipca">IPCA</option>
                <option value="cct">Dissídio / CCT</option><option value="manual">Percentual Manual</option>
              </select>
            </div>
            <div class="form-group"><label class="form-label">Percentual (%)</label>
              <input v-model="editForm.pct" type="number" step="0.01" class="form-input" dusk="raj-edit-pct" @input="aplicarIndice()">
            </div>
            <div class="form-group"><label class="form-label">Competência</label><input v-model="editForm.competencia" type="month" class="form-input"></div>
          </div>

          <div class="contracts-table-wrap" style="overflow-x:auto;margin-bottom:14px">
            <table style="width:100%;border-collapse:collapse;min-width:560px">
              <thead>
                <tr>
                  <th style="text-align:center;width:36px"></th>
                  <th style="text-align:left">Posto / Serviço</th>
                  <th style="text-align:right">Valor Atual</th>
                  <th style="text-align:center">%</th>
                  <th style="text-align:right">Novo Valor</th>
                  <th style="text-align:right">Variação</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(it, i) in editForm.itens" :key="i">
                  <td style="text-align:center"><input type="checkbox" v-model="it.selecionado"></td>
                  <td style="text-align:left;font-size:12px">{{ it.nome }}</td>
                  <td style="text-align:right;font-size:12px">{{ fmt(it.valorAtual) }}</td>
                  <td style="text-align:center"><input v-model.number="it.pct" type="number" step="0.01" class="form-input" style="width:80px;text-align:center;padding:4px 6px" :dusk="'raj-edit-item-pct-' + i"></td>
                  <td style="text-align:right;font-size:12px;font-weight:600">{{ fmt(editItemNovo(it)) }}</td>
                  <td style="text-align:right;font-size:12px;color:var(--green)">{{ fmt(editItemVar(it)) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:14px">
            <div style="font-size:12px;color:var(--text-muted)">
              Atual: <strong>{{ fmt(editResumo.atual) }}</strong> · Novo: <strong style="color:var(--brand-gold)">{{ fmt(editResumo.novo) }}</strong>
              · Impacto: <strong style="color:var(--green)" dusk="raj-edit-impacto">{{ fmt(editResumo.impacto) }}</strong>
              ({{ editResumo.sel }} de {{ editForm.itens.length }} itens)
            </div>
          </div>
          <div class="form-group" style="margin-bottom:14px"><label class="form-label">Observação</label><input v-model="editForm.obs" type="text" class="form-input"></div>

          <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-ghost" @click="modalEditar = false">Cancelar</button>
            <button class="btn btn-gold" dusk="raj-edit-salvar" :disabled="salvandoEdit" @click="salvarEditar()">{{ salvandoEdit ? 'Salvando...' : 'Salvar' }}</button>
          </div>
        </div>
      </div>

      <!-- Modal: alterar status -->
      <div class="modal-overlay" :class="{ open: modalStatus }" @click.self="modalStatus = false">
        <div class="modal" style="width:420px;max-width:94vw">
          <div class="modal-title">Alterar Status</div>
          <div v-if="reajusteStatus" style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">
            {{ reajusteStatus.cliente_nome }}
            <div style="margin-top:6px">Status atual:
              <span class="badge" :class="sitClass(reajusteStatus.status)">{{ statusLabels[reajusteStatus.status] || reajusteStatus.status }}</span>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:18px">
            <button v-for="s in STATUS" :key="s" class="raj-opt" :dusk="'raj-opt-' + s" @click="aplicarStatus(s)">
              <span class="badge" :class="sitClass(s)">{{ statusLabels[s] || s }}</span>
            </button>
          </div>
          <div style="display:flex;justify-content:flex-end">
            <button class="btn btn-ghost" @click="modalStatus = false">Cancelar</button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.g360 .raj-acao { background: transparent; border: none; color: var(--text-muted); cursor: pointer; padding: 2px 6px; transition: color 0.15s; }
.g360 .raj-acao:hover { color: var(--brand-gold); }
.g360 .raj-acao-del:hover { color: var(--red); }
.g360 .raj-opt {
  display: flex; align-items: center; gap: 8px; padding: 10px 12px;
  border-radius: var(--radius-sm); border: 1px solid var(--brand-border-soft);
  background: transparent; cursor: pointer; transition: all 0.15s; text-align: left;
}
.g360 .raj-opt:hover { background: color-mix(in srgb, var(--app-primary) 5%, transparent); border-color: var(--brand-border); }
</style>
