<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { useToast } from "primevue/usetoast"
import "@/../css/comercial-g360.css"

const props = defineProps({
  clientes: Array,
  situacaoLabels: Object,
})

const toast = useToast()
const ok = (m) => toast.add({ severity: "success", summary: "Pronto", detail: m, life: 2500 })
const fail = (m) => toast.add({ severity: "error", summary: "Erro", detail: m, life: 4000 })
const fmt = (v) => v ? "R$ " + Number(v).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "—"
const fmtK = (v) => {
  if (!v) return "R$ 0"
  if (v >= 1000000) return "R$ " + (v / 1e6).toLocaleString("pt-BR", { minimumFractionDigits: 2 }) + "M"
  if (v >= 1000) return "R$ " + (v / 1000).toLocaleString("pt-BR", { minimumFractionDigits: 1 }) + "k"
  return fmt(v)
}

// ─── Estado ──────────────────────────────────────────
const busca = ref("")
const filtroSituacao = ref("")
const showModal = ref(false)
const editandoId = ref(null)
const loading = ref(false)

// Form
const form = ref({
  nome: "",
  contato_nome: "",
  contato_email: "",
  contato_telefone: "",
  cidade: "",
  uf: "",
  situacao: "ativo",
  valor_mensal: 0,
  total_colaboradores: 0,
  total_postos: 0,
  observacao: "",
})

// ─── Filtros ──────────────────────────────────────────
const listaFiltrada = computed(() => {
  let lista = props.clientes || []
  if (busca.value) {
    const b = busca.value.toLowerCase()
    lista = lista.filter(c =>
      (c.nome || "").toLowerCase().includes(b) ||
      (c.cidade || "").toLowerCase().includes(b) ||
      (c.contato_nome || "").toLowerCase().includes(b)
    )
  }
  if (filtroSituacao.value) {
    lista = lista.filter(c => c.situacao === filtroSituacao.value)
  }
  return lista
})

// ─── KPIs ──────────────────────────────────────────
const kpiTotal = computed(() => listaFiltrada.value.length)
const kpiAtivos = computed(() => listaFiltrada.value.filter(c => c.situacao === "ativo").length)
const kpiFaturamento = computed(() => listaFiltrada.value.reduce((s, c) => s + (c.valor_mensal || 0), 0))
const kpiColaboradores = computed(() => listaFiltrada.value.reduce((s, c) => s + (c.total_colaboradores || 0), 0))

// ─── Ações ──────────────────────────────────────────
function abrirNovo() {
  editandoId.value = null
  form.value = { nome: "", contato_nome: "", contato_email: "", contato_telefone: "", cidade: "", uf: "", situacao: "ativo", valor_mensal: 0, total_colaboradores: 0, total_postos: 0, observacao: "" }
  showModal.value = true
}

function abrirEditar(cli) {
  editandoId.value = cli.id
  form.value = {
    nome: cli.nome || "",
    contato_nome: cli.contato_nome || "",
    contato_email: cli.contato_email || "",
    contato_telefone: cli.contato_telefone || "",
    cidade: cli.cidade || "",
    uf: cli.uf || "",
    situacao: cli.situacao || "ativo",
    valor_mensal: cli.valor_mensal || 0,
    total_colaboradores: cli.total_colaboradores || 0,
    total_postos: cli.total_postos || 0,
    observacao: cli.observacao || "",
  }
  showModal.value = true
}

async function salvar() {
  if (!form.value.nome.trim()) { fail("Informe o nome do cliente"); return }
  loading.value = true
  try {
    if (editandoId.value) {
      await axios.put(`/comercial/clientes/${editandoId.value}`, form.value)
      ok("Cliente atualizado")
    } else {
      await axios.post("/comercial/clientes", form.value)
      ok("Cliente cadastrado")
    }
    showModal.value = false
    router.reload()
  } catch (e) {
    fail(e.response?.data?.message || "Erro ao salvar")
  } finally {
    loading.value = false
  }
}

async function excluir(cli) {
  if (!confirm(`Excluir o cliente "${cli.nome}"? Esta ação não pode ser desfeita.`)) return
  try {
    await axios.delete(`/comercial/clientes/${cli.id}`)
    ok("Cliente excluído")
    router.reload()
  } catch (e) {
    fail("Erro ao excluir")
  }
}

function verDetalhe(cli) {
  router.visit(`/comercial/clientes/${cli.id}`)
}

// Badge class por situação
function badgeClass(situacao) {
  return { ativo: "badge-green", inativo: "badge-orange", prospecto: "badge-blue" }[situacao] || "badge-blue"
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="g360">
      <div class="view active" id="view-clientes">
        <!-- Cabeçalho -->
        <div class="page-title-row">
          <div>
            <div class="section-title">Clientes / Contratos</div>
            <div class="section-desc">Gestão de clientes e contratos ativos — {{ kpiTotal }} no total</div>
          </div>
          <button class="btn btn-gold" @click="abrirNovo">+ Novo Cliente</button>
        </div>

        <!-- KPIs -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px">
          <div class="stat-card">
            <div class="stat-label">Total Clientes</div>
            <div class="stat-value">{{ kpiTotal }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Ativos</div>
            <div class="stat-value" style="color:var(--green)">{{ kpiAtivos }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Faturamento Mensal</div>
            <div class="stat-value" style="color:var(--brand-gold)">{{ fmtK(kpiFaturamento) }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Colaboradores</div>
            <div class="stat-value">{{ kpiColaboradores }}</div>
          </div>
        </div>

        <!-- Filtros -->
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:20px">
          <input type="text" class="form-input" v-model="busca" placeholder="Buscar cliente..." style="max-width:320px">
          <select class="form-input" v-model="filtroSituacao" style="max-width:180px">
            <option value="">Todas as situações</option>
            <option v-for="(label, key) in situacaoLabels" :key="key" :value="key">{{ label }}</option>
          </select>
          <span style="font-size:12px;color:var(--text-muted);margin-left:auto">
            {{ listaFiltrada.length }} cliente{{ listaFiltrada.length !== 1 ? 's' : '' }} encontrado{{ listaFiltrada.length !== 1 ? 's' : '' }}
          </span>
        </div>

        <!-- Tabela -->
        <div class="contracts-table-wrap">
          <table>
            <thead>
              <tr>
                <th>Cliente</th>
                <th>Contato</th>
                <th>Cidade/UF</th>
                <th>Valor Mensal</th>
                <th>Postos</th>
                <th>Propostas</th>
                <th>Situação</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cli in listaFiltrada" :key="cli.id" @click="verDetalhe(cli)" style="cursor:pointer">
                <td style="font-weight:600">{{ cli.nome }}</td>
                <td>{{ cli.contato_nome || '—' }}</td>
                <td>{{ [cli.cidade, cli.uf].filter(Boolean).join('/') || '—' }}</td>
                <td style="font-weight:700;color:var(--brand-gold)">{{ fmtK(cli.valor_mensal) }}</td>
                <td>{{ cli.total_postos }}</td>
                <td>{{ cli.propostas_count }}</td>
                <td><span class="badge" :class="badgeClass(cli.situacao)">{{ situacaoLabels[cli.situacao] || cli.situacao }}</span></td>
                <td @click.stop>
                  <div style="display:flex;gap:6px">
                    <button @click="abrirEditar(cli)" style="background:transparent;border:1px solid var(--brand-border-soft);border-radius:6px;padding:4px 10px;font-size:11px;color:var(--text-secondary);cursor:pointer;font-family:inherit">Editar</button>
                    <button @click="excluir(cli)" style="background:transparent;border:1px solid var(--brand-border-soft);border-radius:6px;padding:4px 10px;font-size:11px;color:var(--red);cursor:pointer;font-family:inherit">Excluir</button>
                  </div>
                </td>
              </tr>
              <tr v-if="listaFiltrada.length === 0">
                <td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
                  Nenhum cliente encontrado
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══════ Modal: Novo/Editar Cliente ══════ -->
      <div v-if="showModal" class="g360-modal-overlay" @click.self="showModal = false">
        <div class="g360-modal" style="width:620px">
          <div class="g360-modal-header">
            <span>{{ editandoId ? 'Editar Cliente' : 'Novo Cliente' }}</span>
            <button class="g360-modal-close" @click="showModal = false">✕</button>
          </div>
          <div class="g360-modal-body" style="flex-direction:column">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div class="form-group" style="grid-column:span 2">
                <label class="form-label">Nome do Cliente *</label>
                <input type="text" class="form-input" v-model="form.nome" placeholder="Nome completo ou razão social">
              </div>
              <div class="form-group">
                <label class="form-label">Situação</label>
                <select class="form-input" v-model="form.situacao">
                  <option v-for="(label, key) in situacaoLabels" :key="key" :value="key">{{ label }}</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Cidade</label>
                <input type="text" class="form-input" v-model="form.cidade" placeholder="Cidade">
              </div>
              <div class="form-group">
                <label class="form-label">UF</label>
                <input type="text" class="form-input" v-model="form.uf" placeholder="UF" maxlength="2" style="text-transform:uppercase">
              </div>
              <div class="form-group">
                <label class="form-label">Contato Principal</label>
                <input type="text" class="form-input" v-model="form.contato_nome" placeholder="Nome do contato">
              </div>
              <div class="form-group">
                <label class="form-label">E-mail do Contato</label>
                <input type="email" class="form-input" v-model="form.contato_email" placeholder="email@empresa.com">
              </div>
              <div class="form-group">
                <label class="form-label">Telefone</label>
                <input type="text" class="form-input" v-model="form.contato_telefone" placeholder="(61) 99999-0000">
              </div>
              <div class="form-group">
                <label class="form-label">Valor Mensal (R$)</label>
                <input type="number" class="form-input" v-model.number="form.valor_mensal" step="0.01" placeholder="0,00">
              </div>
              <div class="form-group">
                <label class="form-label">Nº de Colaboradores</label>
                <input type="number" class="form-input" v-model.number="form.total_colaboradores" min="0" placeholder="0">
              </div>
              <div class="form-group">
                <label class="form-label">Nº de Postos</label>
                <input type="number" class="form-input" v-model.number="form.total_postos" min="0" placeholder="0">
              </div>
              <div class="form-group" style="grid-column:span 2">
                <label class="form-label">Observações</label>
                <textarea class="form-input" v-model="form.observacao" style="min-height:80px;resize:vertical;line-height:1.6" placeholder="Notas sobre o cliente..."></textarea>
              </div>
            </div>
          </div>
          <div class="g360-modal-footer">
            <button class="btn btn-ghost" @click="showModal = false">Cancelar</button>
            <button class="btn btn-gold" :disabled="loading" @click="salvar">
              {{ loading ? 'Salvando...' : 'Salvar' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
