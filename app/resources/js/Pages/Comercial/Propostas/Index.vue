<script setup>
// ─────────────────────────────────────────────────────────────────────────────
// Controle de Propostas — portado 1:1 do protótipo Gestão 360º (view-propostas).
// HTML/CSS puro sob `.g360` (mesmo padrão das telas Cotação e Valores). Cores
// white-label: dentro do .g360 o dourado do protótipo (--brand-gold) aponta para
// --app-primary; tons rgba dourados viram color-mix(... var(--app-primary) ...);
// verde/azul/vermelho/laranja permanecem semânticos.
//
// Dados: vêm via props do Inertia (lista mapeada no ComercialPropostaController).
// Mutações (entrada manual, edição, mudança de situação, exclusão) batem nas rotas
// REST do controller e recarregam a lista via router.reload({ only: ['propostas'] }).
//
// Fora de escopo desta fatia (deixados como TODO): reabrir a proposta na Cotação e
// exportação real para XLSX/PDF (botão "Exportar" exibe toast "em breve").
// ─────────────────────────────────────────────────────────────────────────────
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { reactive, ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { useToast } from "primevue/usetoast"
import { swalConfirm } from "@/utils/globalFunctions"
import "@/../css/comercial-g360.css"

const props = defineProps({
  // Lista de propostas já mapeada pelo backend (ver ComercialPropostaController::lista()).
  propostas: { type: Array, default: () => [] },
  // Rótulos de situação (apenas informativo; os valores do filtro são fixos do protótipo).
  situacaoLabels: { type: Object, default: () => ({}) },
})

const toast = useToast()
const ok = (m) => toast.add({ severity: "success", summary: "Pronto", detail: m, life: 2500 })
const warn = (m) => toast.add({ severity: "warn", summary: "Atenção", detail: m, life: 3000 })
const fail = (m) => toast.add({ severity: "error", summary: "Erro", detail: m, life: 4000 })
const emBreve = (m) => toast.add({ severity: "info", summary: "Em breve", detail: m, life: 2800 })

// ─── Estrutura do Grupo 5 Estrelas (do protótipo) ──────────────────────────────
const GRUPO_EMPRESAS = [
  { id: "seg-df", short: "Segurança — Sede DF", tag: "SEG·DF", tipo: "seguranca", uf: "DF" },
  { id: "seg-go", short: "Segurança — Filial GO", tag: "SEG·GO", tipo: "seguranca", uf: "GO" },
  { id: "seg-mt", short: "Segurança — Filial MT", tag: "SEG·MT", tipo: "seguranca", uf: "MT" },
  { id: "seg-mg", short: "Segurança — Filial MG", tag: "SEG·MG", tipo: "seguranca", uf: "MG" },
  { id: "seg-sp", short: "Segurança — Filial SP", tag: "SEG·SP", tipo: "seguranca", uf: "SP" },
  { id: "apoio-df", short: "Apoio Administrativo — DF", tag: "APOIO·DF", tipo: "apoio", uf: "DF" },
  { id: "apoio-go", short: "Apoio Administrativo — GO", tag: "APOIO·GO", tipo: "apoio", uf: "GO" },
  { id: "apoio-sp", short: "Apoio Administrativo — SP", tag: "APOIO·SP", tipo: "apoio", uf: "SP" },
]
const EMPRESA_LEGACY_MAP = {
  "MATRIZ": "seg-df", "FILIAL - GO": "seg-go", "FILIAL - MG": "seg-mg",
  "APOIO - DF": "apoio-df", "APOIO - GO": "apoio-go", "APOIO - SP": "apoio-sp",
  "APOIO - MG": "seg-mg", "APOIO - MT": "seg-mt",
  "MATRIZ APOIO - DF": "seg-df", "MATRIZ E APOIO - DF": "seg-df",
}
function getEmpresa(idOrLegado) {
  if (!idOrLegado) return GRUPO_EMPRESAS[0]
  const direct = GRUPO_EMPRESAS.find((e) => e.id === idOrLegado)
  if (direct) return direct
  const mapped = EMPRESA_LEGACY_MAP[idOrLegado]
  if (mapped) return GRUPO_EMPRESAS.find((e) => e.id === mapped) || GRUPO_EMPRESAS[0]
  return GRUPO_EMPRESAS[0]
}
// Estilo do badge de empresa (white-label: dourado → var(--app-primary)).
function empresaBadgeStyle(idOrLegado) {
  const e = getEmpresa(idOrLegado)
  return e.tipo === "apoio"
    ? "background:rgba(74,144,217,0.12);color:var(--blue)"
    : "background:color-mix(in srgb, var(--app-primary) 12%, transparent);color:var(--brand-gold)"
}

// ─── Formatadores ──────────────────────────────────────────────────────────────
const fmtVal = (v) =>
  v != null && v > 0
    ? "R$ " + Number(v).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : "—"
const fmtData = (d) => {
  if (!d) return "—"
  try {
    // d vem como 'Y-m-d' do backend; força horário local sem deslocamento de fuso.
    return new Date(d + "T00:00:00").toLocaleDateString("pt-BR")
  } catch {
    return d
  }
}
const fmtKpi = (v) =>
  v >= 1e6
    ? "R$ " + (v / 1e6).toLocaleString("pt-BR", { minimumFractionDigits: 2 }) + "M"
    : "R$ " + (v / 1000).toLocaleString("pt-BR", { minimumFractionDigits: 0 }) + "k"

// ─── Situações (do protótipo) ───────────────────────────────────────────────────
const SITUACOES = ["EM ANÁLISE", "APROVADO", "REPROVADO", "ESTIMATIVA", "REDUÇÃO"]
const sitClass = (sit) =>
  ({
    APROVADO: "badge-green",
    REPROVADO: "badge-red",
    "EM ANÁLISE": "badge-blue",
    ESTIMATIVA: "badge-orange",
    REDUÇÃO: "badge-orange",
  })[sit] || "badge-blue"

// ─── Filtros e ordenação ────────────────────────────────────────────────────────
const fBusca = ref("")
const fSituacao = ref("")
const fEmpresa = ref("")
const sortField = ref("num")
const sortAsc = ref(false)

function sortPropostas(field) {
  if (sortField.value === field) sortAsc.value = !sortAsc.value
  else {
    sortField.value = field
    sortAsc.value = false
  }
}
const sortArrow = (field) => (sortField.value === field ? (sortAsc.value ? " ↑" : " ↓") : "")

function limparFiltros() {
  fBusca.value = ""
  fSituacao.value = ""
  fEmpresa.value = ""
}

const listaFiltrada = computed(() => {
  const busca = fBusca.value.toLowerCase()
  let lista = props.propostas.filter((p) => {
    const matchBusca =
      !busca ||
      (p.numero || "").toLowerCase().includes(busca) ||
      (p.cliente || "").toLowerCase().includes(busca) ||
      (p.servicos || "").toLowerCase().includes(busca) ||
      (p.contato || "").toLowerCase().includes(busca)
    const matchSit = !fSituacao.value || (p.situacao || "") === fSituacao.value
    const matchEmp =
      !fEmpresa.value ||
      (() => {
        const emp = p.empresa || ""
        if (fEmpresa.value === "seg") return emp.startsWith("seg")
        return emp === fEmpresa.value || getEmpresa(emp).id === fEmpresa.value
      })()
    return matchBusca && matchSit && matchEmp
  })

  lista = [...lista].sort((a, b) => {
    let va, vb
    if (sortField.value === "num") {
      va = parseInt(String(a.numero).replace(/\D/g, "")) || 0
      vb = parseInt(String(b.numero).replace(/\D/g, "")) || 0
    } else if (sortField.value === "valor") {
      va = a.valor || 0
      vb = b.valor || 0
    } else if (sortField.value === "data") {
      va = a.data_proposta || ""
      vb = b.data_proposta || ""
    } else if (sortField.value === "cliente") {
      va = (a.cliente || "").toLowerCase()
      vb = (b.cliente || "").toLowerCase()
    }
    return sortAsc.value ? (va > vb ? 1 : -1) : (va < vb ? 1 : -1)
  })
  return lista
})

// KPIs refletem a lista filtrada (igual ao protótipo).
const kpis = computed(() => {
  const base = listaFiltrada.value
  return {
    total: base.length,
    analise: base.filter((p) => p.situacao === "EM ANÁLISE").length,
    aprovadas: base.filter((p) => p.situacao === "APROVADO").length,
    reprovadas: base.filter((p) => p.situacao === "REPROVADO").length,
    valorAprovado: base.reduce((s, p) => s + (p.valor_aprovado || 0), 0),
  }
})
const filtroAtivo = computed(() => listaFiltrada.value.length !== props.propostas.length)

// ─── Refresh da lista (Inertia partial reload) ──────────────────────────────────
function recarregar() {
  router.reload({ only: ["propostas"], preserveScroll: true })
}

// ─── Modal de entrada manual / edição ────────────────────────────────────────────
const modalAberto = ref(false)
const editId = ref(null)
const salvando = ref(false)
const form = reactive({
  numero: "", revisao: "N/A", cliente: "", servicos: "", empresa: "seg-df",
  valor: "", posto: "", contato: "", data_proposta: "", situacao: "EM ANÁLISE",
  valor_aprovado: "", data_aprovacao: "", observacao: "",
})

function resetForm() {
  Object.assign(form, {
    numero: "", revisao: "N/A", cliente: "", servicos: "", empresa: "seg-df",
    valor: "", posto: "", contato: "", data_proposta: new Date().toISOString().slice(0, 10),
    situacao: "EM ANÁLISE", valor_aprovado: "", data_aprovacao: "", observacao: "",
  })
}

function abrirModalProposta() {
  editId.value = null
  resetForm()
  modalAberto.value = true
}

function editarProposta(p) {
  editId.value = p.id
  Object.assign(form, {
    numero: p.numero || "",
    revisao: p.revisao || "N/A",
    cliente: p.cliente || "",
    servicos: p.servicos || "",
    empresa: getEmpresa(p.empresa).id || "seg-df",
    valor: p.valor ?? "",
    posto: p.posto || "",
    contato: p.contato || "",
    data_proposta: p.data_proposta || "",
    situacao: p.situacao || "EM ANÁLISE",
    valor_aprovado: p.valor_aprovado ?? "",
    data_aprovacao: p.data_aprovacao || "",
    observacao: p.observacao || "",
  })
  modalAberto.value = true
}

function fecharModal() {
  modalAberto.value = false
  editId.value = null
}

async function salvarProposta() {
  if (!String(form.cliente).trim()) {
    warn("Preencha o nome do cliente")
    return
  }
  if (form.valor === "" || isNaN(Number(form.valor))) {
    warn("Informe o valor da proposta")
    return
  }

  const payload = {
    numero: form.numero || null,
    revisao: form.revisao || "N/A",
    cliente: form.cliente,
    servicos: form.servicos || null,
    empresa: form.empresa || null,
    posto: form.posto || null,
    contato: form.contato || null,
    valor: Number(form.valor),
    data_proposta: form.data_proposta || null,
    situacao: form.situacao,
    valor_aprovado: form.valor_aprovado === "" ? null : Number(form.valor_aprovado),
    data_aprovacao: form.data_aprovacao || null,
    observacao: form.observacao || null,
  }

  salvando.value = true
  try {
    if (editId.value) {
      await axios.put(`/comercial/propostas/${editId.value}`, payload)
      ok("Proposta atualizada!")
    } else {
      await axios.post("/comercial/propostas/manual", payload)
      ok("Proposta registrada!")
    }
    fecharModal()
    recarregar()
  } catch (e) {
    if (e?.response?.status === 422) {
      const errs = e.response.data?.errors || {}
      const first = Object.values(errs)[0]
      fail(Array.isArray(first) ? first[0] : e.response.data?.message || "Dados inválidos")
    } else {
      fail("Falha ao salvar a proposta")
    }
  } finally {
    salvando.value = false
  }
}

// ─── Alterar situação (modal dedicado, em vez do prompt() do protótipo) ───────────
const modalSituacao = ref(false)
const propostaSituacao = ref(null)

function alterarSituacao(p) {
  propostaSituacao.value = p
  modalSituacao.value = true
}

async function aplicarSituacao(novaSituacao) {
  const p = propostaSituacao.value
  if (!p) return

  // Reprovar exige confirmação (ação sensível, igual ao critério do projeto).
  if (novaSituacao === "REPROVADO") {
    const { isConfirmed } = await swalConfirm(
      "Reprovar proposta?",
      `A proposta ${p.numero || ""} (${p.cliente || ""}) será marcada como REPROVADA.`,
      "Reprovar",
      "Cancelar",
      { danger: true },
    )
    if (!isConfirmed) return
  }

  const payload = { situacao: novaSituacao }
  // Ao aprovar sem valor aprovado, espelha o valor da proposta (igual ao protótipo).
  if (novaSituacao === "APROVADO" && !p.valor_aprovado) {
    payload.valor_aprovado = p.valor
    payload.data_aprovacao = new Date().toISOString().slice(0, 10)
  }

  try {
    await axios.patch(`/comercial/propostas/${p.id}/situacao`, payload)
    ok("Situação atualizada!")
    modalSituacao.value = false
    propostaSituacao.value = null
    recarregar()
  } catch (e) {
    if (e?.response?.status === 403) fail("Você não tem permissão para alterar a situação")
    else fail("Falha ao atualizar a situação")
  }
}

// ─── Excluir ──────────────────────────────────────────────────────────────────
async function excluirProposta(p) {
  const { isConfirmed } = await swalConfirm(
    "Excluir proposta?",
    `A proposta ${p.numero || ""} (${p.cliente || ""}) será excluída. O número volta para a fila.`,
    "Excluir",
    "Cancelar",
    { danger: true, icon: "trash" },
  )
  if (!isConfirmed) return

  try {
    await axios.delete(`/comercial/propostas/${p.id}`)
    ok("Proposta excluída")
    recarregar()
  } catch (e) {
    if (e?.response?.status === 403) fail("Você não tem permissão para excluir")
    else fail("Falha ao excluir a proposta")
  }
}

// ─── Stubs (fora de escopo desta fatia) ───────────────────────────────────────────
function exportarPropostas() {
  emBreve("Exportação para XLSX — em breve.")
}
// TODO: reabrir uma proposta existente na tela de Cotação (clicar na linha).
</script>

<template>
  <AuthenticatedLayout>
    <div class="g360">
      <div class="view active" id="view-propostas">
        <!-- ── Cabeçalho ── -->
        <div class="page-title-row">
          <div>
            <div class="section-title">Controle de Propostas</div>
            <div class="section-desc">Histórico e acompanhamento de todas as propostas emitidas</div>
          </div>
          <div style="display:flex;gap:10px;align-items:center">
            <button class="btn btn-ghost" @click="exportarPropostas()">
              <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2v9M4 8l4 4 4-4"/><path d="M2 13h12"/></svg>
              Exportar
            </button>
            <button class="btn btn-gold" @click="abrirModalProposta()">+ Nova Entrada Manual</button>
          </div>
        </div>

        <!-- ── KPIs rápidos ── -->
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px">
          <div class="stat-card">
            <div class="stat-label">Total de Propostas</div>
            <div class="stat-value">{{ kpis.total }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Em Análise</div>
            <div class="stat-value" style="color:var(--blue)">{{ kpis.analise }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Aprovadas</div>
            <div class="stat-value" style="color:var(--green)">{{ kpis.aprovadas }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Reprovadas</div>
            <div class="stat-value" style="color:var(--red)">{{ kpis.reprovadas }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Valor Total Aprovado</div>
            <div class="stat-value" style="font-size:18px;color:var(--green)">{{ fmtKpi(kpis.valorAprovado) }}</div>
          </div>
          <!-- Badge de filtro ativo (1:1 com o protótipo) -->
          <div v-if="filtroAtivo" style="grid-column:1/-1;display:flex;align-items:center;gap:6px;padding:6px 12px;background:color-mix(in srgb, var(--app-primary) 8%, transparent);border:1px solid color-mix(in srgb, var(--app-primary) 20%, transparent);border-radius:6px;font-size:11px;color:var(--brand-gold);font-weight:600">
            <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4h12M5 8h6M7 12h2"/></svg>
            Exibindo {{ kpis.total }} proposta{{ kpis.total !== 1 ? "s" : "" }} filtrada{{ kpis.total !== 1 ? "s" : "" }} de {{ props.propostas.length }} total
          </div>
        </div>

        <!-- ── Filtros ── -->
        <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
          <input v-model="fBusca" type="text" class="form-input" placeholder="Buscar cliente, nº proposta, serviço..." style="width:280px;max-width:100%">
          <select v-model="fSituacao" class="form-select" style="width:160px">
            <option value="">Todas as situações</option>
            <option value="EM ANÁLISE">Em Análise</option>
            <option value="APROVADO">Aprovado</option>
            <option value="REPROVADO">Reprovado</option>
            <option value="ESTIMATIVA">Estimativa</option>
            <option value="REDUÇÃO">Redução</option>
          </select>
          <select v-model="fEmpresa" class="form-select" style="width:200px">
            <option value="">Todas as empresas</option>
            <option value="seg">Segurança (todas)</option>
            <option value="seg-df">Segurança — Sede DF</option>
            <option value="seg-go">Segurança — Filial GO</option>
            <option value="seg-mt">Segurança — Filial MT</option>
            <option value="seg-mg">Segurança — Filial MG</option>
            <option value="seg-sp">Segurança — Filial SP</option>
            <option value="apoio-df">Apoio Administrativo — DF</option>
            <option value="apoio-go">Apoio Administrativo — GO</option>
            <option value="apoio-sp">Apoio Administrativo — SP</option>
          </select>
          <button class="btn btn-ghost" @click="limparFiltros()" style="font-size:12px">Limpar filtros</button>
          <span style="margin-left:auto;font-size:12px;color:var(--text-muted)">
            {{ listaFiltrada.length }} de {{ props.propostas.length }} propostas
          </span>
        </div>

        <!-- ── Tabela ── -->
        <div class="contracts-table-wrap" style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;min-width:1100px">
            <thead>
              <tr>
                <th style="cursor:pointer" @click="sortPropostas('num')">Nº{{ sortArrow("num") }}</th>
                <th>Rev.</th>
                <th style="cursor:pointer" @click="sortPropostas('cliente')">Cliente{{ sortArrow("cliente") }}</th>
                <th>Serviços</th>
                <th>Empresa</th>
                <th>Posto</th>
                <th style="cursor:pointer;text-align:right" @click="sortPropostas('valor')">Valor{{ sortArrow("valor") }}</th>
                <th>Contato</th>
                <th style="cursor:pointer" @click="sortPropostas('data')">Envio{{ sortArrow("data") }}</th>
                <th>Situação</th>
                <th style="text-align:right">Vl. Aprovado</th>
                <th>Dt. Aprovação</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <!-- Estado vazio -->
              <tr v-if="listaFiltrada.length === 0">
                <td colspan="13" style="text-align:center;padding:48px 20px;color:var(--text-muted);cursor:default">
                  <div style="display:flex;flex-direction:column;align-items:center;gap:10px">
                    <svg width="40" height="40" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.2" style="opacity:.5"><path d="M3 2h7l3 3v9a1 1 0 01-1 1H3a1 1 0 01-1-1V3z"/><path d="M10 2v4h3"/><path d="M5 9h6M5 12h4"/></svg>
                    <div style="font-weight:600;color:var(--text-secondary)">
                      {{ props.propostas.length === 0 ? "Nenhuma proposta cadastrada" : "Nenhuma proposta encontrada" }}
                    </div>
                    <div style="font-size:12px">
                      {{ props.propostas.length === 0 ? "Crie uma entrada manual ou gere uma proposta na tela de Cotação." : "Ajuste os filtros para ver mais resultados." }}
                    </div>
                  </div>
                </td>
              </tr>

              <!-- Linhas -->
              <tr v-for="p in listaFiltrada" :key="p.id" :class="{ 'tr-da-plataforma': p.da_cotacao }">
                <td style="font-weight:700;white-space:nowrap">{{ p.numero || "—" }}</td>
                <td style="color:var(--text-muted)">{{ p.revisao || "N/A" }}</td>
                <td style="max-width:220px">
                  <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" :title="p.cliente || ''">{{ p.cliente || "—" }}</div>
                  <div v-if="p.da_cotacao" style="font-size:10px;color:var(--brand-gold);margin-top:2px">Gerada na plataforma</div>
                </td>
                <td style="font-size:12px;color:var(--text-secondary);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" :title="p.servicos || ''">{{ p.servicos || "—" }}</td>
                <td style="white-space:nowrap">
                  <span v-if="p.empresa" :style="empresaBadgeStyle(p.empresa)" style="border-radius:99px;font-weight:700;letter-spacing:.04em;font-size:10px;padding:1px 7px">{{ getEmpresa(p.empresa).tag }}</span>
                  <span v-else style="color:var(--text-muted)">—</span>
                </td>
                <td style="font-size:12px;color:var(--text-secondary)">{{ p.posto || "—" }}</td>
                <td style="text-align:right;font-weight:600;font-family:'Syne',sans-serif">{{ fmtVal(p.valor) }}</td>
                <td style="font-size:12px;color:var(--text-secondary)">{{ p.contato || "—" }}</td>
                <td style="font-size:12px;white-space:nowrap">{{ fmtData(p.data_proposta) }}</td>
                <td><span class="badge" :class="sitClass(p.situacao)">{{ p.situacao || "EM ANÁLISE" }}</span></td>
                <td style="text-align:right;font-size:12px;color:var(--green);font-weight:600">{{ fmtVal(p.valor_aprovado) }}</td>
                <td style="font-size:12px;white-space:nowrap">{{ fmtData(p.data_aprovacao) }}</td>
                <td style="white-space:nowrap">
                  <button @click="editarProposta(p)" class="prop-acao" title="Editar">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M11 2l3 3-9 9H2v-3l9-9z"/></svg>
                  </button>
                  <button @click="alterarSituacao(p)" class="prop-acao" title="Alterar situação">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6"/><path d="M8 5v4l2 2"/></svg>
                  </button>
                  <button @click="excluirProposta(p)" class="prop-acao prop-acao-del" title="Excluir proposta — o número volta para a fila">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10M6 4V2h4v2M5 4v9a1 1 0 001 1h4a1 1 0 001-1V4"/><path d="M7 7v4M9 7v4"/></svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Modal: entrada manual / edição ── -->
      <div class="modal-overlay" :class="{ open: modalAberto }" @click.self="fecharModal()">
        <div class="modal" style="width:680px;max-width:94vw">
          <div class="modal-title">{{ editId ? "Editar Proposta" : "Nova Entrada de Proposta" }}</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div class="form-group"><label class="form-label">Nº Proposta</label><input v-model="form.numero" type="text" class="form-input" placeholder="Nº 132 (auto se vazio)"></div>
            <div class="form-group"><label class="form-label">Revisão</label><input v-model="form.revisao" type="text" class="form-input" placeholder="N/A ou Rev.01"></div>
            <div class="form-group" style="grid-column:span 2"><label class="form-label">Cliente</label><input v-model="form.cliente" type="text" class="form-input"></div>
            <div class="form-group"><label class="form-label">Serviços</label><input v-model="form.servicos" type="text" class="form-input" placeholder="Vigilância, Portaria..."></div>
            <div class="form-group"><label class="form-label">Empresa</label>
              <select v-model="form.empresa" class="form-select">
                <option v-for="e in GRUPO_EMPRESAS" :key="e.id" :value="e.id">{{ e.short }}</option>
              </select>
            </div>
            <div class="form-group"><label class="form-label">Valor (R$)</label><input v-model="form.valor" type="number" step="0.01" class="form-input"></div>
            <div class="form-group"><label class="form-label">Tipo de Posto</label><input v-model="form.posto" type="text" class="form-input" placeholder="VIG 24H, PORT 12H..."></div>
            <div class="form-group"><label class="form-label">Contato de Envio</label><input v-model="form.contato" type="text" class="form-input"></div>
            <div class="form-group"><label class="form-label">Data de Envio</label><input v-model="form.data_proposta" type="date" class="form-input"></div>
            <div class="form-group"><label class="form-label">Situação</label>
              <select v-model="form.situacao" class="form-select">
                <option v-for="s in SITUACOES" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
            <div class="form-group"><label class="form-label">Valor Aprovado (R$)</label><input v-model="form.valor_aprovado" type="number" step="0.01" class="form-input"></div>
            <div class="form-group"><label class="form-label">Data de Aprovação</label><input v-model="form.data_aprovacao" type="date" class="form-input"></div>
            <div class="form-group" style="grid-column:span 2"><label class="form-label">Observação</label><input v-model="form.observacao" type="text" class="form-input"></div>
          </div>
          <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-ghost" @click="fecharModal()">Cancelar</button>
            <button class="btn btn-gold" :disabled="salvando" @click="salvarProposta()">{{ salvando ? "Salvando..." : "Salvar" }}</button>
          </div>
        </div>
      </div>

      <!-- ── Modal: alterar situação ── -->
      <div class="modal-overlay" :class="{ open: modalSituacao }" @click.self="modalSituacao = false">
        <div class="modal" style="width:420px;max-width:94vw">
          <div class="modal-title">Alterar Situação</div>
          <div v-if="propostaSituacao" style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">
            {{ propostaSituacao.numero }} — {{ propostaSituacao.cliente || "—" }}
            <div style="margin-top:6px">Situação atual:
              <span class="badge" :class="sitClass(propostaSituacao.situacao)">{{ propostaSituacao.situacao }}</span>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:18px">
            <button v-for="s in SITUACOES" :key="s" class="sit-opt" :class="{ atual: propostaSituacao && propostaSituacao.situacao === s }" @click="aplicarSituacao(s)">
              <span class="badge" :class="sitClass(s)">{{ s }}</span>
            </button>
          </div>
          <div style="display:flex;justify-content:flex-end">
            <button class="btn btn-ghost" @click="modalSituacao = false">Cancelar</button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
/* Linha de proposta gerada pela plataforma (injetada dinamicamente no protótipo). */
.g360 :deep(.tr-da-plataforma) { border-left: 3px solid var(--brand-gold) !important; }
.g360 .prop-acao {
  background: transparent; border: none; color: var(--text-muted);
  cursor: pointer; padding: 2px 6px; transition: color 0.15s;
}
.g360 .prop-acao:hover { color: var(--brand-gold); }
.g360 .prop-acao-del:hover { color: var(--red); }
.g360 thead th[onclick], .g360 thead th { user-select: none; }
.g360 .sit-opt {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 12px; border-radius: var(--radius-sm);
  border: 1px solid var(--brand-border-soft); background: transparent;
  cursor: pointer; transition: all 0.15s; text-align: left;
}
.g360 .sit-opt:hover { background: color-mix(in srgb, var(--app-primary) 5%, transparent); border-color: var(--brand-border); }
.g360 .sit-opt.atual { background: color-mix(in srgb, var(--app-primary) 8%, transparent); border-color: var(--brand-border); }
</style>
