<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { useToast } from "primevue/usetoast"
import "@/../css/comercial-g360.css"

const props = defineProps({
  cliente: Object,
  propostas: Array,
  situacaoLabels: Object,
})

const toast = useToast()
const ok = (m) => toast.add({ severity: "success", summary: "Pronto", detail: m, life: 2500 })
const fail = (m) => toast.add({ severity: "error", summary: "Erro", detail: m, life: 4000 })
const fmt = (v) => v ? "R$ " + Number(v).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "R$ 0,00"
const fmtK = (v) => {
  if (!v) return "R$ 0"
  if (v >= 1000000) return "R$ " + (v / 1e6).toLocaleString("pt-BR", { minimumFractionDigits: 2 }) + "M"
  if (v >= 1000) return "R$ " + (v / 1000).toLocaleString("pt-BR", { minimumFractionDigits: 1 }) + "k"
  return fmt(v)
}

// ─── Estado ──────────────────────────────────────────
const showVincular = ref(false)
const showEditar = ref(false)
const buscaVincular = ref("")
const loadingVincular = ref(false)
const loadingEditar = ref(false)
const propostasDisponiveis = ref([])
const observacao = ref(props.cliente.observacao || "")

const formEditar = ref({ ...props.cliente })

// ─── KPIs ──────────────────────────────────────────
const kpiMensal = computed(() => props.cliente.valor_mensal || 0)
const kpiColaboradores = computed(() => props.cliente.total_colaboradores || 0)
const kpiPostos = computed(() => props.cliente.total_postos || 0)
const kpiPropostas = computed(() => (props.propostas || []).length)

// ─── Postos ativos (derivados das propostas aprovadas) ──────
const postosAtivos = computed(() => {
  const postos = []
  for (const p of (props.propostas || [])) {
    if (p.situacao === "APROVADO" && Array.isArray(p.postos)) {
      for (const posto of p.postos) {
        postos.push({
          tipo: posto.cat || posto.tipo || "—",
          escala: posto.escala || "—",
          qtd: posto.qtdPostos || posto.qtd || 1,
          colab: (posto.qtdPostos || 1) * (posto.funcPosto || 1),
          valor: posto.totalMensal || posto.valor || 0,
          proposta: p.numero,
        })
      }
    }
  }
  return postos
})

// ─── Badge ──────────────────────────────────────────
function badgeClass(situacao) {
  return { ativo: "badge-green", inativo: "badge-orange", prospecto: "badge-blue" }[situacao] || "badge-blue"
}

function propostaBadgeClass(situacao) {
  return {
    "APROVADO": "badge-green",
    "REPROVADO": "badge-red",
    "EM ANÁLISE": "badge-blue",
    "ESTIMATIVA": "badge-orange",
    "REDUÇÃO": "badge-orange",
  }[situacao] || "badge-blue"
}

// ─── Vincular proposta ──────────────────────────────
async function abrirVincular() {
  buscaVincular.value = ""
  loadingVincular.value = true
  showVincular.value = true
  try {
    const { data } = await axios.get("/comercial/propostas/dados")
    // Filtrar propostas que NÃO estão vinculadas a este cliente
    const idsVinculadas = new Set((props.propostas || []).map(p => p.id))
    propostasDisponiveis.value = (data.propostas || []).filter(p => !idsVinculadas.has(p.id))
  } catch (e) {
    fail("Erro ao carregar propostas")
  } finally {
    loadingVincular.value = false
  }
}

const propostasFiltradas = computed(() => {
  if (!buscaVincular.value) return propostasDisponiveis.value
  const b = buscaVincular.value.toLowerCase()
  return propostasDisponiveis.value.filter(p =>
    (p.numero || "").toLowerCase().includes(b) ||
    (p.cliente || "").toLowerCase().includes(b) ||
    (p.servicos || "").toLowerCase().includes(b)
  )
})

async function vincular(proposta) {
  try {
    await axios.post(`/comercial/clientes/${props.cliente.id}/vincular`, { proposta_id: proposta.id })
    ok("Proposta vinculada")
    showVincular.value = false
    router.reload()
  } catch (e) {
    fail("Erro ao vincular")
  }
}

async function desvincular(proposta) {
  if (!confirm(`Desvincular a proposta ${proposta.numero} deste cliente?`)) return
  try {
    await axios.delete(`/comercial/clientes/${props.cliente.id}/desvincular/${proposta.id}`)
    ok("Proposta desvinculada")
    router.reload()
  } catch (e) {
    fail("Erro ao desvincular")
  }
}

// ─── Editar cliente ──────────────────────────────────
function abrirEditar() {
  formEditar.value = { ...props.cliente }
  showEditar.value = true
}

async function salvarEditar() {
  if (!formEditar.value.nome?.trim()) { fail("Informe o nome do cliente"); return }
  loadingEditar.value = true
  try {
    await axios.put(`/comercial/clientes/${props.cliente.id}`, formEditar.value)
    ok("Cliente atualizado")
    showEditar.value = false
    router.reload()
  } catch (e) {
    fail(e.response?.data?.message || "Erro ao salvar")
  } finally {
    loadingEditar.value = false
  }
}

// ─── Observação ──────────────────────────────────────
async function salvarObs() {
  try {
    await axios.put(`/comercial/clientes/${props.cliente.id}`, {
      ...props.cliente,
      observacao: observacao.value,
    })
    ok("Observações salvas")
  } catch (e) {
    fail("Erro ao salvar")
  }
}

function voltar() {
  router.visit("/comercial/clientes")
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="g360">
      <div class="view active" id="view-cliente-detalhe">
        <!-- Cabeçalho -->
        <div class="page-title-row">
          <div style="display:flex;align-items:center;gap:12px">
            <button @click="voltar" style="background:transparent;border:1px solid var(--brand-border-soft);border-radius:8px;padding:6px 12px;color:var(--text-secondary);cursor:pointer;font-family:inherit;font-size:13px;display:flex;align-items:center;gap:6px">
              <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 3L5 8l5 5"/></svg>
              Clientes
            </button>
            <div>
              <div class="section-title">{{ cliente.nome }}</div>
              <div class="section-desc">
                {{ [cliente.cidade, cliente.uf].filter(Boolean).join('/') }}
                <span v-if="cliente.contato_nome"> · {{ cliente.contato_nome }}</span>
              </div>
            </div>
          </div>
          <div style="display:flex;gap:10px">
            <button class="btn btn-ghost" @click="abrirEditar">Editar</button>
            <button class="btn btn-gold" @click="abrirVincular">Vincular Proposta</button>
          </div>
        </div>

        <!-- KPIs do cliente -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px">
          <div class="stat-card">
            <div class="stat-label">Valor Mensal Contratado</div>
            <div class="stat-value" style="font-size:20px;color:var(--brand-gold)">{{ fmt(kpiMensal) }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Colaboradores</div>
            <div class="stat-value">{{ kpiColaboradores }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Postos</div>
            <div class="stat-value">{{ kpiPostos }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Propostas Vinculadas</div>
            <div class="stat-value" style="color:var(--blue)">{{ kpiPropostas }}</div>
          </div>
        </div>

        <!-- Layout 2 colunas -->
        <div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

          <!-- Coluna principal -->
          <div>
            <!-- Informações do contrato -->
            <div class="module-card" style="margin-bottom:16px">
              <div class="module-header">
                <div class="module-title">Informações do Contrato</div>
                <button @click="abrirEditar" style="margin-left:auto;background:transparent;border:none;color:var(--brand-gold);font-size:12px;cursor:pointer;font-family:inherit">Editar</button>
              </div>
              <div class="module-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                  <div class="form-group">
                    <label class="form-label">Status</label>
                    <div style="padding:8px 0;font-size:13px">
                      <span class="badge" :class="badgeClass(cliente.situacao)">{{ situacaoLabels[cliente.situacao] || cliente.situacao }}</span>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Cidade / UF</label>
                    <div style="padding:8px 0;font-size:13px;color:var(--text-secondary)">{{ [cliente.cidade, cliente.uf].filter(Boolean).join(' / ') || '—' }}</div>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Contato Principal</label>
                    <div style="padding:8px 0;font-size:13px;color:var(--text-secondary)">{{ cliente.contato_nome || '—' }}</div>
                  </div>
                  <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <div style="padding:8px 0;font-size:13px;color:var(--text-secondary)">{{ cliente.contato_email || '—' }}</div>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Telefone</label>
                    <div style="padding:8px 0;font-size:13px;color:var(--text-secondary)">{{ cliente.contato_telefone || '—' }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Propostas Vinculadas -->
            <div class="module-card" style="margin-bottom:16px">
              <div class="module-header">
                <div style="display:flex;align-items:center;gap:10px;flex:1">
                  <div class="module-title">Propostas Vinculadas</div>
                  <span style="font-size:11px;color:var(--text-muted)">{{ kpiPropostas }} proposta{{ kpiPropostas !== 1 ? 's' : '' }}</span>
                </div>
                <div style="display:flex;gap:8px">
                  <button @click="abrirVincular" style="background:transparent;border:1px solid var(--brand-border-soft);border-radius:6px;padding:4px 11px;color:var(--brand-gold);font-size:11px;cursor:pointer;font-family:inherit">+ Vincular</button>
                </div>
              </div>
              <div style="padding:0">
                <!-- Lista de propostas -->
                <template v-if="propostas && propostas.length > 0">
                  <div v-for="p in propostas" :key="p.id" class="prop-item">
                    <div class="prop-item-header" style="padding:12px 16px">
                      <!-- Ícone -->
                      <div style="width:32px;height:32px;border-radius:8px;background:rgba(74,144,217,0.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--blue)">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="2" width="12" height="12" rx="2"/><path d="M8 5v6M5 8h6"/></svg>
                      </div>
                      <!-- Texto -->
                      <div style="flex:1;min-width:0;margin-left:10px">
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px">
                          <span style="font-size:11px;font-weight:800;color:var(--brand-gold)">{{ p.numero || '—' }}</span>
                          <span style="font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ p.servicos || p.posto || '—' }}</span>
                        </div>
                        <div style="font-size:10px;color:var(--text-muted);display:flex;align-items:center;gap:6px">
                          <span>{{ p.data_proposta ? new Date(p.data_proposta).toLocaleDateString('pt-BR') : '—' }}</span>
                          <span v-if="p.empresa">· {{ p.empresa }}</span>
                        </div>
                      </div>
                      <!-- Valor + badges -->
                      <div style="text-align:right;flex-shrink:0">
                        <div style="font-family:Syne,sans-serif;font-weight:800;font-size:14px">{{ p.valor ? fmtK(p.valor) : '—' }}</div>
                        <div style="margin-top:3px;display:flex;gap:4px;justify-content:flex-end">
                          <span class="badge" :class="propostaBadgeClass(p.situacao)" style="font-size:9px">{{ p.situacao || '—' }}</span>
                        </div>
                      </div>
                      <!-- Ações -->
                      <div style="display:flex;gap:4px;flex-shrink:0;margin-left:10px">
                        <button @click="desvincular(p)"
                          title="Desvincular do cliente"
                          style="background:transparent;border:1px solid var(--brand-border-soft);border-radius:5px;color:var(--text-muted);cursor:pointer;padding:3px 7px;font-size:10px;font-family:inherit;white-space:nowrap;transition:.12s">Desvincular</button>
                      </div>
                    </div>
                  </div>
                </template>
                <div v-else style="padding:28px;text-align:center;color:var(--text-muted);display:flex;flex-direction:column;align-items:center;gap:12px">
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" opacity=".4">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/>
                  </svg>
                  <div style="font-size:13px">Nenhuma proposta vinculada</div>
                  <button class="btn btn-gold btn-sm" @click="abrirVincular" style="font-size:11px">+ Vincular proposta</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Coluna lateral -->
          <div>
            <!-- Observações -->
            <div class="module-card" style="margin-bottom:14px">
              <div class="module-header">
                <div class="module-title">Observações</div>
                <button @click="salvarObs" style="margin-left:auto;background:transparent;border:none;color:var(--brand-gold);font-size:12px;cursor:pointer;font-family:inherit">Salvar</button>
              </div>
              <div class="module-body">
                <textarea v-model="observacao" style="width:100%;min-height:120px;background:var(--brand-surface);border:1px solid var(--brand-border-soft);border-radius:8px;padding:12px;color:var(--text-primary);font-family:inherit;font-size:13px;resize:vertical;line-height:1.6" placeholder="Anotações, pendências, histórico do cliente..."></textarea>
              </div>
            </div>

            <!-- Postos ativos -->
            <div class="module-card">
              <div class="module-header">
                <div class="module-title">Postos / Escalas Ativas</div>
              </div>
              <div class="module-body" style="padding:0">
                <template v-if="postosAtivos.length > 0">
                  <div v-for="(p, i) in postosAtivos" :key="i" style="display:flex;align-items:center;gap:8px;padding:10px 14px;border-bottom:1px solid var(--brand-border-soft)">
                    <div style="flex:1;min-width:0">
                      <div style="font-size:12px;font-weight:600;color:var(--text-primary)">{{ p.tipo }}</div>
                      <div style="font-size:10px;color:var(--text-muted)">{{ p.escala }} · {{ p.qtd }} posto(s) · {{ p.colab }} func. · {{ p.proposta }}</div>
                    </div>
                    <div style="font-size:12px;font-weight:700;color:var(--brand-gold);white-space:nowrap">{{ fmt(p.valor) }}</div>
                  </div>
                </template>
                <div v-else style="padding:20px;text-align:center;color:var(--text-muted);font-size:12px">
                  Postos aparecem aqui quando propostas aprovadas são vinculadas
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══════ Modal: Vincular Proposta ══════ -->
      <div v-if="showVincular" class="g360-modal-overlay" @click.self="showVincular = false">
        <div class="g360-modal" style="width:560px">
          <div class="g360-modal-header">
            <span>Vincular Proposta ao Cliente</span>
            <button class="g360-modal-close" @click="showVincular = false">✕</button>
          </div>
          <div class="g360-modal-body" style="flex-direction:column">
            <input type="text" class="form-input" v-model="buscaVincular" placeholder="Buscar pelo nº ou cliente da proposta..." style="margin-bottom:14px">
            <div v-if="loadingVincular" style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px">Carregando...</div>
            <div v-else style="max-height:320px;overflow-y:auto;border:1px solid var(--brand-border-soft);border-radius:8px">
              <div v-if="propostasFiltradas.length === 0" style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px">Nenhuma proposta disponível</div>
              <div v-for="p in propostasFiltradas" :key="p.id" style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--brand-border-soft)">
                <div style="font-weight:700;font-size:12px;color:var(--brand-gold);white-space:nowrap;width:60px">{{ p.numero }}</div>
                <div style="flex:1;min-width:0">
                  <div style="font-size:12px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ p.cliente || '—' }}</div>
                  <div style="font-size:10px;color:var(--text-muted)">{{ p.servicos || '' }} · {{ fmtK(p.valor) }}</div>
                </div>
                <span class="badge" :class="propostaBadgeClass(p.situacao)" style="font-size:10px;flex-shrink:0">{{ p.situacao || '—' }}</span>
                <button @click="vincular(p)" style="background:rgba(200,168,75,0.1);border:none;color:var(--brand-gold);cursor:pointer;font-size:11px;padding:4px 10px;border-radius:6px;white-space:nowrap;font-family:inherit">+ Vincular</button>
              </div>
            </div>
          </div>
          <div class="g360-modal-footer">
            <button class="btn btn-ghost" @click="showVincular = false">Fechar</button>
          </div>
        </div>
      </div>

      <!-- ══════ Modal: Editar Cliente ══════ -->
      <div v-if="showEditar" class="g360-modal-overlay" @click.self="showEditar = false">
        <div class="g360-modal" style="width:620px">
          <div class="g360-modal-header">
            <span>Editar Cliente</span>
            <button class="g360-modal-close" @click="showEditar = false">✕</button>
          </div>
          <div class="g360-modal-body" style="flex-direction:column">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div class="form-group" style="grid-column:span 2">
                <label class="form-label">Nome do Cliente *</label>
                <input type="text" class="form-input" v-model="formEditar.nome" placeholder="Nome completo ou razão social">
              </div>
              <div class="form-group">
                <label class="form-label">Situação</label>
                <select class="form-input" v-model="formEditar.situacao">
                  <option v-for="(label, key) in situacaoLabels" :key="key" :value="key">{{ label }}</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Cidade</label>
                <input type="text" class="form-input" v-model="formEditar.cidade" placeholder="Cidade">
              </div>
              <div class="form-group">
                <label class="form-label">UF</label>
                <input type="text" class="form-input" v-model="formEditar.uf" placeholder="UF" maxlength="2" style="text-transform:uppercase">
              </div>
              <div class="form-group">
                <label class="form-label">Contato Principal</label>
                <input type="text" class="form-input" v-model="formEditar.contato_nome" placeholder="Nome do contato">
              </div>
              <div class="form-group">
                <label class="form-label">E-mail</label>
                <input type="email" class="form-input" v-model="formEditar.contato_email" placeholder="email@empresa.com">
              </div>
              <div class="form-group">
                <label class="form-label">Telefone</label>
                <input type="text" class="form-input" v-model="formEditar.contato_telefone" placeholder="(61) 99999-0000">
              </div>
              <div class="form-group" style="grid-column:span 2">
                <label class="form-label">Observações</label>
                <textarea class="form-input" v-model="formEditar.observacao" style="min-height:80px;resize:vertical;line-height:1.6" placeholder="Notas..."></textarea>
              </div>
            </div>
          </div>
          <div class="g360-modal-footer">
            <button class="btn btn-ghost" @click="showEditar = false">Cancelar</button>
            <button class="btn btn-gold" :disabled="loadingEditar" @click="salvarEditar">
              {{ loadingEditar ? 'Salvando...' : 'Salvar' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
