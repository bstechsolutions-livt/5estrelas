<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { onMounted, ref, computed } from "vue"
import axios from "axios"
import { useToast } from "primevue/usetoast"
import "@/../css/comercial-g360.css"

const toast = useToast()
const ok = (m) => toast.add({ severity: "success", summary: "Pronto", detail: m, life: 2500 })
const fail = (m) => toast.add({ severity: "error", summary: "Erro", detail: m, life: 4000 })
const fmt = (v) => v ? "R$ " + Number(v).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "—"

// ─── Estado ──────────────────────────────────────────
const aba = ref("cct")            // cct | taxas | insumos
const estadoUf = ref("df")
const estados = [
  { uf: "df", nome: "Brasília" }, { uf: "go", nome: "Goiás" }, { uf: "mg", nome: "Minas Gerais" },
  { uf: "mt", nome: "Mato Grosso" }, { uf: "sp", nome: "São Paulo" },
]
const ccts = ref([])
const indices = ref([])
const encargos = ref([])
const insumos = ref([])
const cctAtiva = ref(null)
const encargosAberto = ref(false)

const servicoMeta = {
  vigilancia: { nome: "Vigilância", tipo: "seg", icone: "🛡️", cor: "var(--brand-gold)", bg: "rgba(184,146,42,.12)" },
  bombeiro: { nome: "Bombeiro Civil", tipo: "seg", icone: "🔥", cor: "#E05454", bg: "rgba(224,84,84,.12)" },
  portaria: { nome: "Ag. de Portaria", tipo: "apoio", icone: "🏢", cor: "var(--blue)", bg: "rgba(41,128,185,.12)" },
  limpeza: { nome: "Limpeza", tipo: "apoio", icone: "🧹", cor: "#4CAF7D", bg: "rgba(76,175,125,.12)" },
}

const cctsDoEstado = computed(() => ccts.value.filter((c) => c.uf === estadoUf.value))
const cctsSeg = computed(() => cctsDoEstado.value.filter((c) => servicoMeta[c.servico]?.tipo === "seg"))
const cctsApoio = computed(() => cctsDoEstado.value.filter((c) => servicoMeta[c.servico]?.tipo === "apoio"))
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

async function carregar() {
  try {
    const { data } = await axios.get("/comercial/configuracoes/dados")
    ccts.value = data.ccts || []
    indices.value = data.indices || []
    encargos.value = data.encargos || []
    insumos.value = data.insumos || []
  } catch (e) { fail("Falha ao carregar dados") }
}
onMounted(carregar)

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
</script>

<template>
  <AuthenticatedLayout>
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
          <button class="indices-tab" :class="{ active: aba === 'cct' }" @click="aba = 'cct'">Convenções Coletivas</button>
          <button class="indices-tab" :class="{ active: aba === 'taxas' }" @click="aba = 'taxas'">Taxas</button>
          <button class="indices-tab" :class="{ active: aba === 'insumos' }" @click="aba = 'insumos'">Insumos</button>
        </div>

        <!-- ABA: CONVENÇÕES COLETIVAS -->
        <div v-show="aba === 'cct'">
          <!-- Estado tabs -->
          <div style="display:flex;gap:0;margin-bottom:28px;border-bottom:2px solid var(--brand-border-soft);overflow-x:auto">
            <button v-for="e in estados" :key="e.uf" class="cct-estado-tab" :class="{ active: estadoUf === e.uf }"
              @click="estadoUf = e.uf; fecharPainel()">
              <span class="cct-tab-uf">{{ e.uf.toUpperCase() }}</span><span class="cct-tab-nome">{{ e.nome }}</span>
            </button>
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
                <div class="cct-card-icon" :style="{ background: servicoMeta[c.servico]?.bg, color: servicoMeta[c.servico]?.cor }">{{ servicoMeta[c.servico]?.icone }}</div>
                <div class="cct-card-nome">{{ servicoMeta[c.servico]?.nome }}</div>
                <div class="cct-card-sal" :style="{ color: servicoMeta[c.servico]?.cor }">{{ fmt(c.salario_base) }}</div>
                <div class="cct-card-sub">VA: {{ fmt(c.va) }}/dia</div>
              </div>
            </div>
            <div class="cct-secao-label" style="color:var(--blue)">
              <span style="font-family:Syne,sans-serif;font-weight:800;font-size:17px;margin-right:4px">APOIO</span>
              <span style="font-size:11px;font-weight:700;letter-spacing:.06em">5 Estrelas Apoio Administrativo</span>
            </div>
            <div class="cct-card-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
              <div v-for="c in cctsApoio" :key="c.id" class="cct-card apoio-card" :class="{ active: cctAtiva?.id === c.id }" @click="abrirCard(c)">
                <div class="cct-card-icon" :style="{ background: servicoMeta[c.servico]?.bg, color: servicoMeta[c.servico]?.cor }">{{ servicoMeta[c.servico]?.icone }}</div>
                <div class="cct-card-nome">{{ servicoMeta[c.servico]?.nome }}</div>
                <div class="cct-card-sal apoio-sal" :style="{ color: servicoMeta[c.servico]?.cor }">{{ fmt(c.salario_base) }}</div>
                <div class="cct-card-sub">VA: {{ fmt(c.va) }}/dia</div>
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
                <div class="module-num" style="background:rgba(184,146,42,.15)">%</div>
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
      </div>
    </div>
  </AuthenticatedLayout>
</template>
