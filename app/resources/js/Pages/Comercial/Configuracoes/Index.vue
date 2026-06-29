<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { onMounted, ref, computed } from "vue"
import axios from "axios"
import Toast from "primevue/toast"
import { useToast } from "primevue/usetoast"
import SearchSelect from "@/Components/Comercial/SearchSelect.vue"
import { UF_OPTIONS } from "@/Components/Comercial/ufs.js"
import "@/../css/comercial-g360.css"

const toast = useToast()
const ok = (m) => toast.add({ severity: "success", summary: "Pronto", detail: m, life: 2500 })
const fail = (m) => toast.add({ severity: "error", summary: "Erro", detail: m, life: 4000 })
const fmt = (v) => v ? "R$ " + Number(v).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "—"

// ─── Mapa de UFs do Brasil ──────────────────────────────
const ufNomes = {
  ac: "Acre", al: "Alagoas", ap: "Amapá", am: "Amazonas", ba: "Bahia",
  ce: "Ceará", df: "Brasília", es: "Espírito Santo", go: "Goiás",
  ma: "Maranhão", mt: "Mato Grosso", ms: "Mato Grosso do Sul",
  mg: "Minas Gerais", pa: "Pará", pb: "Paraíba", pr: "Paraná",
  pe: "Pernambuco", pi: "Piauí", rj: "Rio de Janeiro", rn: "Rio Grande do Norte",
  rs: "Rio Grande do Sul", ro: "Rondônia", rr: "Roraima", sc: "Santa Catarina",
  sp: "São Paulo", se: "Sergipe", to: "Tocantins",
}

// ─── Estado ──────────────────────────────────────────
const aba = ref("cct")            // cct | taxas | insumos | filiais
const estadoUf = ref("")
const ccts = ref([])
const indices = ref([])
const encargos = ref([])
const insumos = ref([])
const filiais = ref([])
const cctAtiva = ref(null)
const encargosAberto = ref(false)

// ─── Estados derivados das CCTs ──────────────────────
const estados = computed(() => {
  const ufs = [...new Set(ccts.value.map((c) => (c.uf || "").toLowerCase()).filter(Boolean))]
  ufs.sort()
  return ufs.map((uf) => ({ uf, nome: ufNomes[uf] || uf.toUpperCase() }))
})

// UFs disponíveis para novo estado (que ainda não têm CCTs)
const ufsDisponiveis = computed(() => {
  const existentes = new Set(estados.value.map((e) => e.uf))
  return Object.entries(ufNomes)
    .filter(([uf]) => !existentes.has(uf))
    .map(([uf, nome]) => ({ uf, nome }))
    .sort((a, b) => a.nome.localeCompare(b.nome))
})

// ─── servicoMeta extensível ──────────────────────────
const servicoMetaBase = {
  vigilancia: { nome: "Vigilância", tipo: "seg", icone: "🛡️", cor: "var(--brand-gold)", bg: "color-mix(in srgb, var(--app-primary) 12%, transparent)" },
  bombeiro: { nome: "Bombeiro Civil", tipo: "seg", icone: "🔥", cor: "#E05454", bg: "rgba(224,84,84,.12)" },
  portaria: { nome: "Ag. de Portaria", tipo: "apoio", icone: "🏢", cor: "var(--blue)", bg: "rgba(41,128,185,.12)" },
  limpeza: { nome: "Limpeza", tipo: "apoio", icone: "🧹", cor: "#4CAF7D", bg: "rgba(76,175,125,.12)" },
}

const defaultMeta = { icone: "⭐", cor: "var(--text-secondary)", bg: "rgba(127,127,127,.1)" }

function getMeta(c) {
  if (servicoMetaBase[c.servico]) return servicoMetaBase[c.servico]
  return {
    nome: c.nome || c.servico || "Serviço",
    tipo: c.tipo || "apoio",
    icone: c.icone || defaultMeta.icone,
    cor: defaultMeta.cor,
    bg: defaultMeta.bg,
  }
}

const cctsDoEstado = computed(() => ccts.value.filter((c) => (c.uf || "").toLowerCase() === estadoUf.value))
const cctsSeg = computed(() => cctsDoEstado.value.filter((c) => {
  if (c.tipo) return c.tipo === "seg"
  const meta = getMeta(c)
  return meta.tipo === "seg"
}))
const cctsApoio = computed(() => cctsDoEstado.value.filter((c) => {
  if (c.tipo) return c.tipo === "apoio"
  const meta = getMeta(c)
  return meta.tipo === "apoio"
}))
const sindSeg = computed(() => cctsDoEstado.value.find((c) => c.servico === "vigilancia")?.sindicato || "—")
const sindApoio = computed(() => cctsDoEstado.value.find((c) => c.servico === "portaria")?.sindicato || "—")

// Encargos por grupo
const encGrupo = (g) => encargos.value.filter((e) => e.grupo === g)
const encTotGrupo = (g) => encGrupo(g).reduce((s, e) => s + Number(e.percentual || 0), 0)
const encTotal = computed(() => encargos.value.reduce((s, e) => s + Number(e.percentual || 0), 0))
const grupoNomes = { A: "Grupo A — Custos Previdenciários sobre a Folha", B: "Grupo B — Aprovisionamentos", C: "Grupo C — Rescisão", D: "Grupo D — Incidências Cumulativas" }

// Índices (taxas)
const idx = (chave) => indices.value.find((i) => i.chave === chave)

// Insumos agrupados
const insGrupos = [
  { t: "Uniformes e EPIs", chaves: ["uniforme", "epi", "colete"] },
  { t: "Treinamento e Reciclagem", chaves: ["reciclag", "treinamento", "aso"] },
  { t: "Armamento e Segurança", chaves: ["gta", "cofre", "arma"] },
  { t: "Outros Insumos", chaves: ["guarita", "radio", "moto"] },
]
const ins = (chave) => insumos.value.find((i) => i.chave === chave)
const insTotal = computed(() => insumos.value.reduce((s, i) => s + Number(i.valor || 0), 0))

// ─── Modal: Novo Estado ──────────────────────────────
const showNovoEstado = ref(false)
const novoEstadoUf = ref("")
const novoEstadoLoading = ref(false)

// ─── Modal: Novo Serviço ─────────────────────────────
const showNovoServico = ref(false)
const novoServicoTipo = ref("seg")
const novoServicoNome = ref("")
const novoServicoIcone = ref("⭐")
const novoServicoLoading = ref(false)
const iconesDisponiveis = ["🛡️", "🔥", "🏢", "🧹", "⭐", "👤", "🚗"]

async function carregar() {
  try {
    const { data } = await axios.get("/comercial/configuracoes/dados")
    ccts.value = data.ccts || []
    indices.value = data.indices || []
    encargos.value = data.encargos || []
    insumos.value = data.insumos || []
    filiais.value = data.filiais || []
    // Selecionar primeiro estado se necessário (normalizado em minúsculas,
    // pois toda a filtragem por UF compara em minúsculas — dados podem vir
    // com UF em maiúsculas/minúsculas misturadas).
    if (!estadoUf.value || !ccts.value.some((c) => (c.uf || "").toLowerCase() === estadoUf.value)) {
      const ufs = [...new Set(ccts.value.map((c) => (c.uf || "").toLowerCase()).filter(Boolean))].sort()
      estadoUf.value = ufs[0] || ""
    }
  } catch (e) { fail("Falha ao carregar dados") }
}
onMounted(carregar)

// ─── Filiais / Empresas (espelhadas da Senior) ───────────────────────────────
// A fonte é a Senior (cad_filial). Aqui só sincronizamos, ligamos/desligamos a
// exibição e ajustamos a apresentação local (tipo/tag). Sem criar/excluir manual.
const TIPO_FILIAL_OPTIONS = [
  { value: "seguranca", label: "Segurança" },
  { value: "apoio", label: "Apoio / Serviços" },
]
const modalFilial = ref(false)
const editFilialId = ref(null)
const salvandoFilial = ref(false)
const sincronizandoFiliais = ref(false)
const filialForm = ref({ tipo: "seguranca", tag: "" })

const tipoFilialLabel = (t) => (t === "apoio" ? "Apoio / Serviços" : "Segurança")

function abrirEditarFilial(f) {
  editFilialId.value = f.id
  filialForm.value = { tipo: f.tipo || "seguranca", tag: f.tag || "" }
  modalFilial.value = true
}

async function salvarFilial() {
  salvandoFilial.value = true
  try {
    const { data } = await axios.put(`/comercial/configuracoes/filiais/${editFilialId.value}`, filialForm.value)
    const i = filiais.value.findIndex((x) => x.id === editFilialId.value)
    if (i !== -1) filiais.value[i] = data.dados
    ok("Filial atualizada")
    modalFilial.value = false
  } catch (e) {
    fail("Não foi possível salvar a filial")
  } finally {
    salvandoFilial.value = false
  }
}

async function toggleFilial(f) {
  try {
    const { data } = await axios.patch(`/comercial/configuracoes/filiais/${f.id}/toggle`)
    const i = filiais.value.findIndex((x) => x.id === f.id)
    if (i !== -1) filiais.value[i] = data.dados
    ok(data.dados.ativo ? "Filial ativada" : "Filial desativada")
  } catch (e) {
    fail("Não foi possível alterar a filial")
  }
}

async function sincronizarFiliais() {
  sincronizandoFiliais.value = true
  try {
    const { data } = await axios.post("/comercial/configuracoes/filiais/sincronizar")
    if (Array.isArray(data.filiais)) filiais.value = data.filiais
    ;(data.sucesso ? ok : fail)(data.mensagem || "Sincronização concluída")
  } catch (e) {
    fail("Falha ao sincronizar com a Senior")
  } finally {
    sincronizandoFiliais.value = false
  }
}

function abrirCard(c) { cctAtiva.value = c }
function fecharPainel() { cctAtiva.value = null }

async function salvarCct() {
  if (!cctAtiva.value) return
  try {
    await axios.put(`/comercial/configuracoes/ccts/${cctAtiva.value.id}`, cctAtiva.value)
    ok("CCT salva")
  } catch (e) { fail("Não foi possível salvar a CCT") }
}
async function salvarEncargos() {
  try { await axios.post("/comercial/configuracoes/encargos", { encargos: encargos.value }); ok("Encargos salvos") }
  catch (e) { fail("Erro ao salvar encargos") }
}
async function salvarIndices() {
  try { await axios.post("/comercial/configuracoes/indices", { indices: indices.value }); ok("Taxas salvas") }
  catch (e) { fail("Erro ao salvar taxas") }
}
async function salvarInsumos() {
  try { await axios.post("/comercial/configuracoes/insumos", { insumos: insumos.value }); ok("Insumos salvos") }
  catch (e) { fail("Erro ao salvar insumos") }
}

// ─── Criar novo estado ───────────────────────────────
async function criarEstado() {
  if (!novoEstadoUf.value) return
  novoEstadoLoading.value = true
  try {
    const { data } = await axios.post("/comercial/configuracoes/estados", {
      uf: novoEstadoUf.value,
      nome: ufNomes[novoEstadoUf.value] || novoEstadoUf.value.toUpperCase(),
    })
    if (data.sucesso) {
      ok(`Estado ${novoEstadoUf.value.toUpperCase()} criado com sucesso`)
      showNovoEstado.value = false
      novoEstadoUf.value = ""
      await carregar()
      // Selecionar o novo estado
      const ufCriada = data.ccts?.[0]?.uf
      if (ufCriada) estadoUf.value = ufCriada
    }
  } catch (e) {
    const msg = e.response?.data?.mensagem || "Erro ao criar estado"
    fail(msg)
  } finally {
    novoEstadoLoading.value = false
  }
}

// ─── Criar novo serviço/CCT ──────────────────────────
function abrirNovoServico(tipo) {
  novoServicoTipo.value = tipo
  novoServicoNome.value = ""
  novoServicoIcone.value = "⭐"
  showNovoServico.value = true
}

async function criarServico() {
  if (!novoServicoNome.value.trim()) return
  novoServicoLoading.value = true
  const slug = novoServicoNome.value.trim().toLowerCase()
    .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "_").replace(/^_|_$/g, "")
  try {
    const { data } = await axios.post("/comercial/configuracoes/ccts", {
      nome: `CCT ${novoServicoNome.value.trim()} — ${estadoUf.value.toUpperCase()}`,
      titulo: `CCT ${novoServicoNome.value.trim()} — ${estadoUf.value.toUpperCase()}`,
      servico: slug,
      tipo: novoServicoTipo.value,
      icone: novoServicoIcone.value,
      uf: estadoUf.value,
      ativo: true,
      ano_base: String(new Date().getFullYear()),
      horas_mes: novoServicoTipo.value === "seg" ? 220 : 220,
      dias_mes: novoServicoTipo.value === "seg" ? 15.5 : 22,
      salario_base: 0,
      periculosidade_pct: 0,
      adicional_noturno_pct: 0,
      intrajornada_h: 1.5,
      desconto_vt_pct: 6,
      va: 0, vt: 0, plano_saude: 0, fundo_social: 0,
      sst: 0, cna: 0, seguro_vida: 0,
      uniforme: 0, reciclagem: 0, gta: 0, cofre: 0, arma: 0, colete: 0,
    })
    if (data.sucesso) {
      ok(`Serviço "${novoServicoNome.value}" criado`)
      showNovoServico.value = false
      await carregar()
    }
  } catch (e) {
    fail("Erro ao criar serviço")
  } finally {
    novoServicoLoading.value = false
  }
}
</script>

<template>
  <AuthenticatedLayout>
    <Toast />
    <div class="g360">
      <div class="view active" id="view-cct">
        <div class="page-title-row">
          <div>
            <div class="section-title">Índices</div>
            <div class="section-desc">Convenções Coletivas, Encargos Sociais e parâmetros de formação de preço</div>
          </div>
        </div>

        <!-- Abas internas -->
        <div style="display:flex;gap:0;border-bottom:2px solid var(--brand-border-soft);margin-bottom:28px">
          <button class="indices-tab" dusk="cfg-tab-cct" :class="{ active: aba === 'cct' }" @click="aba = 'cct'">Convenções Coletivas</button>
          <button class="indices-tab" dusk="cfg-tab-taxas" :class="{ active: aba === 'taxas' }" @click="aba = 'taxas'">Taxas</button>
          <button class="indices-tab" dusk="cfg-tab-insumos" :class="{ active: aba === 'insumos' }" @click="aba = 'insumos'">Insumos</button>
          <button class="indices-tab" dusk="cfg-tab-filiais" :class="{ active: aba === 'filiais' }" @click="aba = 'filiais'">Filiais</button>
        </div>

        <!-- ABA: CONVENÇÕES COLETIVAS -->
        <div v-show="aba === 'cct'">
          <!-- Estado tabs -->
          <div style="display:flex;gap:0;margin-bottom:28px;border-bottom:2px solid var(--brand-border-soft);overflow-x:auto;align-items:center">
            <button v-for="e in estados" :key="e.uf" class="cct-estado-tab" :class="{ active: estadoUf === e.uf }"
              @click="estadoUf = e.uf; fecharPainel()">
              <span class="cct-tab-uf">{{ e.uf.toUpperCase() }}</span><span class="cct-tab-nome">{{ e.nome }}</span>
            </button>
            <!-- Botão + Novo Estado -->
            <button class="cct-add-tab" @click="showNovoEstado = true" title="Adicionar novo estado">+</button>
          </div>

          <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;font-size:12px;color:var(--text-muted)">
            <span><strong>Segurança:</strong> {{ sindSeg }}</span>
            <span><strong>Apoio:</strong> {{ sindApoio }}</span>
          </div>

          <!-- Cards -->
          <div style="margin-bottom:28px">
            <div class="cct-secao-label" style="color:var(--brand-gold)">
              <span style="font-family:Syne,sans-serif;font-weight:800;font-size:17px;margin-right:4px">SEG</span>
              <span style="font-size:11px;font-weight:700;letter-spacing:.06em">5 Estrelas Sistemas de Segurança</span>
            </div>
            <div class="cct-card-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));margin-bottom:20px">
              <div v-for="c in cctsSeg" :key="c.id" class="cct-card" :class="{ active: cctAtiva?.id === c.id }" @click="abrirCard(c)">
                <div class="cct-card-icon" :style="{ background: getMeta(c).bg, color: getMeta(c).cor }">{{ getMeta(c).icone }}</div>
                <div class="cct-card-nome">{{ getMeta(c).nome }}</div>
                <div class="cct-card-sal" :style="{ color: getMeta(c).cor }">{{ fmt(c.salario_base) }}</div>
                <div class="cct-card-sub">VA: {{ fmt(c.va) }}/dia</div>
              </div>
              <!-- Botão + Novo serviço SEG -->
              <div class="cct-card cct-card-add" @click="abrirNovoServico('seg')">
                <div class="cct-card-add-icon">+</div>
                <div class="cct-card-add-label">Novo serviço</div>
              </div>
            </div>
            <div class="cct-secao-label" style="color:var(--blue)">
              <span style="font-family:Syne,sans-serif;font-weight:800;font-size:17px;margin-right:4px">APOIO</span>
              <span style="font-size:11px;font-weight:700;letter-spacing:.06em">5 Estrelas Apoio Administrativo</span>
            </div>
            <div class="cct-card-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
              <div v-for="c in cctsApoio" :key="c.id" class="cct-card apoio-card" :class="{ active: cctAtiva?.id === c.id }" @click="abrirCard(c)">
                <div class="cct-card-icon" :style="{ background: getMeta(c).bg, color: getMeta(c).cor }">{{ getMeta(c).icone }}</div>
                <div class="cct-card-nome">{{ getMeta(c).nome }}</div>
                <div class="cct-card-sal apoio-sal" :style="{ color: getMeta(c).cor }">{{ fmt(c.salario_base) }}</div>
                <div class="cct-card-sub">VA: {{ fmt(c.va) }}/dia</div>
              </div>
              <!-- Botão + Novo serviço APOIO -->
              <div class="cct-card cct-card-add" @click="abrirNovoServico('apoio')">
                <div class="cct-card-add-icon">+</div>
                <div class="cct-card-add-label">Novo serviço</div>
              </div>
            </div>
          </div>

          <!-- Painel de detalhe -->
          <div v-if="cctAtiva" id="cct-painel">
            <div style="display:flex;align-items:center;gap:14px;padding:16px 20px;background:var(--brand-surface);border-radius:var(--radius-sm);border:1px solid var(--brand-border-soft);margin-bottom:20px">
              <div style="flex:1">
                <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:15px">{{ cctAtiva.titulo || cctAtiva.nome }}</div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px">{{ cctAtiva.sindicato }}</div>
              </div>
              <button @click="fecharPainel" style="background:transparent;border:1px solid var(--brand-border-soft);border-radius:6px;color:var(--text-muted);cursor:pointer;font-size:13px;padding:4px 12px">✕ Fechar</button>
              <button @click="salvarCct" class="btn btn-sm" style="background:var(--brand-gold);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-weight:700;padding:5px 14px">Salvar</button>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">
              <div class="module-card">
                <div class="module-header"><div class="module-num">1</div><div class="module-title">Remuneração Base</div></div>
                <div class="module-body" style="padding:0">
                  <table class="cct-table"><thead><tr><th>Item</th><th style="text-align:right">Valor</th><th>Base</th></tr></thead>
                    <tbody>
                      <tr><td class="td-name">Salário Base</td><td style="text-align:right"><input class="td-input" type="number" step="0.01" v-model.number="cctAtiva.salario_base"></td><td><span class="td-tag">Mensal</span></td></tr>
                      <tr><td class="td-name">Horas / Mês</td><td style="text-align:right"><input class="td-input" type="number" v-model.number="cctAtiva.horas_mes"></td><td><span class="td-tag">h/mês</span></td></tr>
                      <tr><td class="td-name">Dias / Mês</td><td style="text-align:right"><input class="td-input" type="number" step="0.5" v-model.number="cctAtiva.dias_mes"></td><td><span class="td-tag">dias</span></td></tr>
                      <tr><td class="td-name">Periculosidade</td><td style="text-align:right"><input class="td-input" type="number" step="0.1" v-model.number="cctAtiva.periculosidade_pct"></td><td><span class="td-tag">%</span></td></tr>
                      <tr><td class="td-name">Adicional Noturno</td><td style="text-align:right"><input class="td-input" type="number" step="0.1" v-model.number="cctAtiva.adicional_noturno_pct"></td><td><span class="td-tag">%</span></td></tr>
                      <tr><td class="td-name">Intrajornada</td><td style="text-align:right"><input class="td-input" type="number" step="0.5" v-model.number="cctAtiva.intrajornada_h"></td><td><span class="td-tag">h/dia</span></td></tr>
                    </tbody></table>
                </div>
              </div>
              <div class="module-card">
                <div class="module-header"><div class="module-num">2</div><div class="module-title">Benefícios</div></div>
                <div class="module-body" style="padding:0">
                  <table class="cct-table"><thead><tr><th>Item</th><th style="text-align:right">Valor</th><th>Base</th></tr></thead>
                    <tbody>
                      <tr><td class="td-name">VA — Auxílio Alimentação</td><td style="text-align:right"><input class="td-input" type="number" step="0.01" v-model.number="cctAtiva.va"></td><td><span class="td-tag">× Dias × Func.</span></td></tr>
                      <tr><td class="td-name">VT — Vale Transporte</td><td style="text-align:right"><input class="td-input" type="number" step="0.01" v-model.number="cctAtiva.vt"></td><td><span class="td-tag">× Dias × Func.</span></td></tr>
                      <tr><td class="td-name">Desconto VT</td><td style="text-align:right"><input class="td-input" type="number" step="0.1" v-model.number="cctAtiva.desconto_vt_pct"></td><td><span class="td-tag">% salário</span></td></tr>
                      <tr><td class="td-name">Auxílio Saúde</td><td style="text-align:right"><input class="td-input" type="number" step="0.01" v-model.number="cctAtiva.plano_saude"></td><td><span class="td-tag">× Func.</span></td></tr>
                      <tr><td class="td-name">Fundo Social/Odonto</td><td style="text-align:right"><input class="td-input" type="number" step="0.01" v-model.number="cctAtiva.fundo_social"></td><td><span class="td-tag">× Func.</span></td></tr>
                      <tr><td class="td-name">Seguro de Vida</td><td style="text-align:right"><input class="td-input" type="number" step="0.01" v-model.number="cctAtiva.seguro_vida"></td><td><span class="td-tag">× Func.</span></td></tr>
                      <tr><td class="td-name">Contribuição Patronal</td><td style="text-align:right"><input class="td-input" type="number" step="0.01" v-model.number="cctAtiva.cna"></td><td><span class="td-tag">× Func.</span></td></tr>
                      <tr><td class="td-name">SSO — Saúde Ocupacional</td><td style="text-align:right"><input class="td-input" type="number" step="0.01" v-model.number="cctAtiva.sst"></td><td><span class="td-tag">× Func.</span></td></tr>
                    </tbody></table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ABA: TAXAS -->
        <div v-show="aba === 'taxas'">
          <div class="module-card encargos-card" style="margin-bottom:20px">
            <div class="module-header" style="cursor:pointer" @click="encargosAberto = !encargosAberto">
              <div style="display:flex;align-items:center;gap:10px;flex:1">
                <div class="module-num" style="background:color-mix(in srgb, var(--app-primary) 15%, transparent)">%</div>
                <div><div class="module-title">Encargos Sociais</div>
                  <div style="font-size:11px;color:var(--text-muted)">Tabela completa — Grupos A, B, C e D · Base da composição</div></div>
              </div>
              <div style="text-align:right">
                <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase">Total encargos</div>
                <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:24px;color:var(--brand-gold)">{{ encTotal.toFixed(2) }}%</div>
              </div>
            </div>
            <div v-show="encargosAberto" style="padding:0 20px 20px">
              <div v-for="g in ['A','B','C','D']" :key="g">
                <div class="enc-grupo-header" style="margin-top:16px">{{ grupoNomes[g] }}</div>
                <table class="cct-table enc-table">
                  <thead><tr><th>Nº</th><th>Rubrica</th><th style="text-align:right;width:100px">%</th></tr></thead>
                  <tbody>
                    <tr v-for="(e, i) in encGrupo(g)" :key="e.id">
                      <td class="enc-num">{{ String(i + 1).padStart(2, '0') }}</td>
                      <td class="td-name">{{ e.label }}</td>
                      <td style="text-align:right"><input class="td-input enc-input" type="number" step="0.01" v-model.number="e.percentual"></td>
                    </tr>
                  </tbody>
                  <tfoot><tr class="enc-total-row"><td colspan="2" style="padding:10px 16px;font-weight:700">Total Grupo {{ g }}</td>
                    <td style="text-align:right;padding-right:16px;font-family:'Syne',sans-serif;font-weight:800;color:var(--brand-gold)">{{ encTotGrupo(g).toFixed(2) }}%</td></tr></tfoot>
                </table>
              </div>
              <div style="margin-top:16px;text-align:right">
                <button @click="salvarEncargos" class="btn btn-sm" style="background:var(--brand-gold);color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700;padding:6px 16px">Salvar Encargos</button>
              </div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <div class="module-card">
              <div class="module-header"><div class="module-num" style="background:rgba(41,128,185,.15);color:var(--blue)">Adm</div><div class="module-title">Administração</div></div>
              <div class="module-body">
                <label class="form-label">Taxa de Administração</label>
                <div style="display:flex;align-items:center;gap:8px;margin-top:6px">
                  <input v-if="idx('administracao')" type="number" step="0.1" class="form-input" style="width:120px;font-size:18px;font-weight:700;text-align:center;color:var(--blue)" v-model.number="idx('administracao').valor">
                  <span style="font-size:18px;color:var(--text-muted)">%</span>
                </div>
              </div>
            </div>
            <div class="module-card">
              <div class="module-header"><div class="module-num" style="background:rgba(76,175,125,.15);color:var(--green)">%</div><div class="module-title">Lucro</div></div>
              <div class="module-body">
                <label class="form-label">Taxa de Lucro</label>
                <div style="display:flex;align-items:center;gap:8px;margin-top:6px">
                  <input v-if="idx('lucro')" type="number" step="0.1" class="form-input" style="width:120px;font-size:18px;font-weight:700;text-align:center;color:var(--green)" v-model.number="idx('lucro').valor">
                  <span style="font-size:18px;color:var(--text-muted)">%</span>
                </div>
              </div>
            </div>
            <div class="module-card" style="grid-column:1/-1">
              <div class="module-header"><div class="module-num">Trib</div><div class="module-title">Tributos</div></div>
              <div class="module-body" style="display:flex;gap:24px;flex-wrap:wrap">
                <template v-for="t in ['iss','pis','cofins']" :key="t">
                  <div v-if="idx(t)">
                    <label class="form-label">{{ t.toUpperCase() }}</label>
                    <div style="display:flex;align-items:center;gap:6px;margin-top:6px">
                      <input type="number" step="0.01" class="form-input" style="width:90px;text-align:center" v-model.number="idx(t).valor"><span>%</span>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </div>
          <div style="margin-top:16px;text-align:right">
            <button @click="salvarIndices" class="btn btn-sm" style="background:var(--brand-gold);color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700;padding:6px 16px">Salvar Taxas</button>
          </div>
        </div>

        <!-- ABA: INSUMOS -->
        <div v-show="aba === 'insumos'">
          <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px">Custos de insumos operacionais por funcionário/posto, usados automaticamente na cotação.</p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <div v-for="grupo in insGrupos" :key="grupo.t" class="module-card">
              <div class="module-header"><div class="module-title">{{ grupo.t }}</div></div>
              <div class="module-body" style="padding:0">
                <table class="cct-table"><tbody>
                  <template v-for="ch in grupo.chaves" :key="ch">
                    <tr v-if="ins(ch)">
                      <td class="td-name">{{ ins(ch).label }}</td>
                      <td style="text-align:right"><input class="td-input" type="number" step="0.01" v-model.number="ins(ch).valor"></td>
                    </tr>
                  </template>
                </tbody></table>
              </div>
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px">
            <div>
              <div style="font-size:11px;text-transform:uppercase;color:var(--text-muted)">Total insumos / funcionário</div>
              <div style="font-family:Syne,sans-serif;font-weight:800;font-size:22px;color:var(--brand-gold)">{{ fmt(insTotal) }}</div>
            </div>
            <button @click="salvarInsumos" class="btn btn-sm" style="background:var(--brand-gold);color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700;padding:6px 16px">Salvar Insumos</button>
          </div>
        </div>

        <!-- ABA: FILIAIS -->
        <div v-show="aba === 'filiais'">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap">
            <p style="font-size:12px;color:var(--text-muted);margin:0">
              Empresas/filiais do grupo, sincronizadas da <strong>Senior</strong>. Classificação (tipo/sigla) e exibição são ajustáveis aqui.
            </p>
            <button class="btn btn-gold" dusk="cfg-filial-sincronizar" :disabled="sincronizandoFiliais" @click="sincronizarFiliais">
              {{ sincronizandoFiliais ? 'Sincronizando...' : '↻ Sincronizar com Senior' }}
            </button>
          </div>
          <div class="contracts-table-wrap">
            <table>
              <thead>
                <tr><th>Cód.</th><th>Empresa</th><th>Sigla</th><th>Tipo</th><th>CNPJ</th><th>UF</th><th>Exibir</th><th style="text-align:right">Ações</th></tr>
              </thead>
              <tbody>
                <tr v-for="f in filiais" :key="f.id" :dusk="'cfg-filial-row-' + f.id">
                  <td style="font-weight:700;color:var(--text-muted)">{{ f.cod_emp ?? '—' }}</td>
                  <td style="font-weight:600">{{ f.nome }}</td>
                  <td>{{ f.tag || '—' }}</td>
                  <td>{{ tipoFilialLabel(f.tipo) }}</td>
                  <td style="font-size:12px;color:var(--text-secondary)">{{ f.cnpj || '—' }}</td>
                  <td>{{ f.uf || '—' }}</td>
                  <td>
                    <button :dusk="'cfg-filial-toggle-' + f.id" @click="toggleFilial(f)"
                      :style="{ background: f.ativo ? 'color-mix(in srgb, var(--green) 16%, transparent)' : 'rgba(0,0,0,0.06)', color: f.ativo ? 'var(--green)' : 'var(--text-muted)' }"
                      style="border:none;border-radius:99px;padding:3px 12px;font-size:11px;font-weight:600;cursor:pointer;font-family:inherit">
                      {{ f.ativo ? 'Ativa' : 'Inativa' }}
                    </button>
                  </td>
                  <td style="text-align:right">
                    <button :dusk="'cfg-filial-editar-' + f.id" @click="abrirEditarFilial(f)" style="background:transparent;border:1px solid var(--brand-border-soft);border-radius:6px;padding:4px 10px;font-size:11px;color:var(--text-secondary);cursor:pointer;font-family:inherit">Editar</button>
                  </td>
                </tr>
                <tr v-if="!filiais.length">
                  <td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">Nenhuma empresa sincronizada</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ══════ Modal: Filial (apresentação local) ══════ -->
      <div v-if="modalFilial" class="g360-modal-overlay" @click.self="modalFilial = false">
        <div class="g360-modal">
          <div class="g360-modal-header">
            <span>Editar Empresa (apresentação)</span>
            <button class="g360-modal-close" @click="modalFilial = false">✕</button>
          </div>
          <div class="g360-modal-body" style="flex-direction:column">
            <p style="font-size:11px;color:var(--text-muted);margin-bottom:12px">
              Nome, CNPJ e código vêm da Senior e não são editáveis aqui. Ajuste apenas a classificação e a sigla de exibição.
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div class="form-group">
                <label class="form-label">Tipo</label>
                <SearchSelect v-model="filialForm.tipo" :options="TIPO_FILIAL_OPTIONS" :clearable="false" dusk="cfg-filial-tipo" option-dusk-prefix="cfg-filial-tipo-opt" />
              </div>
              <div class="form-group">
                <label class="form-label">Sigla (badge)</label>
                <input type="text" class="form-input" dusk="cfg-filial-tag" v-model="filialForm.tag" placeholder="Ex: 5 ESTRELAS">
              </div>
            </div>
          </div>
          <div class="g360-modal-footer">
            <button class="btn btn-ghost" @click="modalFilial = false">Cancelar</button>
            <button class="btn btn-gold" dusk="cfg-filial-salvar" :disabled="salvandoFilial" @click="salvarFilial">{{ salvandoFilial ? 'Salvando...' : 'Salvar' }}</button>
          </div>
        </div>
      </div>

      <!-- ══════ Modal: Novo Estado ══════ -->
      <div v-if="showNovoEstado" class="g360-modal-overlay" @click.self="showNovoEstado = false">
        <div class="g360-modal">
          <div class="g360-modal-header">
            <span>Adicionar novo Estado</span>
            <button class="g360-modal-close" @click="showNovoEstado = false">✕</button>
          </div>
          <div class="g360-modal-body">
            <div class="form-group">
              <label class="form-label">UF (Estado)</label>
              <select class="form-input" v-model="novoEstadoUf">
                <option value="" disabled>Selecione...</option>
                <option v-for="u in ufsDisponiveis" :key="u.uf" :value="u.uf">{{ u.uf.toUpperCase() }} — {{ u.nome }}</option>
              </select>
            </div>
          </div>
          <div class="g360-modal-footer">
            <button class="btn btn-ghost" @click="showNovoEstado = false">Cancelar</button>
            <button class="btn btn-gold" :disabled="!novoEstadoUf || novoEstadoLoading" @click="criarEstado">
              {{ novoEstadoLoading ? "Criando..." : "Criar Estado" }}
            </button>
          </div>
        </div>
      </div>

      <!-- ══════ Modal: Novo Serviço ══════ -->
      <div v-if="showNovoServico" class="g360-modal-overlay" @click.self="showNovoServico = false">
        <div class="g360-modal">
          <div class="g360-modal-header">
            <span>Novo Serviço ({{ novoServicoTipo === 'seg' ? 'Segurança' : 'Apoio' }}) — {{ estadoUf.toUpperCase() }}</span>
            <button class="g360-modal-close" @click="showNovoServico = false">✕</button>
          </div>
          <div class="g360-modal-body">
            <div class="form-group">
              <label class="form-label">Nome do serviço</label>
              <input class="form-input" type="text" v-model="novoServicoNome" placeholder="Ex: Recepção, Escolta, CFTV...">
            </div>
            <div class="form-group">
              <label class="form-label">Tipo</label>
              <select class="form-input" v-model="novoServicoTipo">
                <option value="seg">Segurança</option>
                <option value="apoio">Apoio</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Ícone</label>
              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px">
                <button
                  v-for="ic in iconesDisponiveis" :key="ic"
                  class="icone-pick" :class="{ selected: novoServicoIcone === ic }"
                  @click="novoServicoIcone = ic"
                >{{ ic }}</button>
              </div>
            </div>
          </div>
          <div class="g360-modal-footer">
            <button class="btn btn-ghost" @click="showNovoServico = false">Cancelar</button>
            <button class="btn btn-gold" :disabled="!novoServicoNome.trim() || novoServicoLoading" @click="criarServico">
              {{ novoServicoLoading ? "Criando..." : "Criar Serviço" }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
