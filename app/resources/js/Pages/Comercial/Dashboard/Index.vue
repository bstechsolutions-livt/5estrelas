<script setup>
// ─────────────────────────────────────────────────────────────────────────────
// Dashboard do Comercial — portado do protótipo Gestão 360º (view-dashboard).
// KPIs globais, split SEG/APOIO, top clientes, funil de propostas e distribuição por UF.
// ─────────────────────────────────────────────────────────────────────────────
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { router } from "@inertiajs/vue3"
import "@/../css/comercial-g360.css"

const props = defineProps({
  kpis: Object,
  split: Object,
  topClientes: Array,
  funil: Array,
  distribuicao: Array,
})

const fmt = (v) => v != null && v > 0
  ? "R$ " + Number(v).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  : "—"
const fmtK = (v) => {
  if (!v || v === 0) return "—"
  if (v >= 1e9) return "R$ " + (v / 1e9).toLocaleString("pt-BR", { minimumFractionDigits: 2 }) + "B"
  if (v >= 1e6) return "R$ " + (v / 1e6).toLocaleString("pt-BR", { minimumFractionDigits: 2 }) + "M"
  if (v >= 1000) return "R$ " + (v / 1000).toLocaleString("pt-BR", { minimumFractionDigits: 0 }) + "k"
  return fmt(v)
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="g360">
      <div class="view active" id="view-dashboard">
        <!-- Cabeçalho -->
        <div class="page-title-row">
          <div>
            <div class="section-title">Dashboard</div>
            <div class="section-desc">Visão geral do módulo Comercial — dados em tempo real</div>
          </div>
          <div style="display:flex;gap:10px;align-items:center">
            <button class="btn btn-ghost" @click="router.reload()" style="font-size:12px">↺ Atualizar</button>
            <button class="btn btn-gold" @click="router.visit('/comercial/cotacao')">+ Nova Cotação</button>
          </div>
        </div>

        <!-- KPIs -->
        <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:20px">
          <div class="stat-card">
            <div class="stat-label">Clientes Ativos</div>
            <div class="stat-value" style="color:var(--green)">{{ kpis?.clientes_ativos ?? '—' }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Faturamento Mensal</div>
            <div class="stat-value" style="font-size:20px;color:var(--brand-gold)">{{ fmtK(kpis?.faturamento_mensal) }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Fat. Anual Projetado</div>
            <div class="stat-value" style="font-size:18px">{{ fmtK(kpis?.anual_projetado) }}</div>
            <div class="stat-sub" style="color:var(--text-muted)">× 12 meses</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Propostas em Análise</div>
            <div class="stat-value" style="color:var(--blue)">{{ kpis?.propostas_analise ?? 0 }}</div>
            <div class="stat-sub" style="color:var(--text-muted)">{{ fmtK(kpis?.propostas_analise_valor) }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Reajustes Pendentes</div>
            <div class="stat-value" style="color:var(--orange)">{{ kpis?.reajustes_pendentes ?? 0 }}</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Taxa de Aprovação</div>
            <div class="stat-value" style="font-size:24px">{{ kpis?.taxa_aprovacao ?? 0 }}%</div>
            <div class="stat-sub" style="color:var(--text-muted)">das propostas</div>
          </div>
        </div>

        <!-- Split por empresa -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">
          <!-- Segurança -->
          <div class="stat-card" style="padding:20px;border-top:3px solid var(--brand-gold)">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
              <span style="font-family:Syne,sans-serif;font-weight:800;font-size:22px;color:var(--brand-gold)">SEG</span>
              <div>
                <div style="font-weight:700;font-size:13px">5 Estrelas Sistemas de Segurança</div>
                <div style="font-size:11px;color:var(--text-muted)">Vigilância · Brigada · DF / GO / MG / MT / SP</div>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
              <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:3px">Clientes</div><div style="font-family:Syne,sans-serif;font-weight:800;font-size:22px">{{ split?.seg?.clientes ?? '—' }}</div></div>
              <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:3px">Fat. Mensal</div><div style="font-family:Syne,sans-serif;font-weight:800;font-size:16px;color:var(--brand-gold)">{{ fmtK(split?.seg?.fat) }}</div></div>
              <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:3px">Propostas</div><div style="font-family:Syne,sans-serif;font-weight:800;font-size:22px;color:var(--blue)">{{ split?.seg?.propostas ?? 0 }}</div></div>
            </div>
          </div>
          <!-- Apoio -->
          <div class="stat-card" style="padding:20px;border-top:3px solid var(--blue)">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
              <span style="font-family:Syne,sans-serif;font-weight:800;font-size:22px;color:var(--blue)">APOIO</span>
              <div>
                <div style="font-weight:700;font-size:13px">5 Estrelas Apoio Administrativo</div>
                <div style="font-size:11px;color:var(--text-muted)">Portaria · Limpeza · Facilities — DF / GO / SP</div>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
              <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:3px">Clientes</div><div style="font-family:Syne,sans-serif;font-weight:800;font-size:22px">{{ split?.apoio?.clientes ?? '—' }}</div></div>
              <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:3px">Fat. Mensal</div><div style="font-family:Syne,sans-serif;font-weight:800;font-size:16px;color:var(--blue)">{{ fmtK(split?.apoio?.fat) }}</div></div>
              <div><div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:3px">Propostas</div><div style="font-family:Syne,sans-serif;font-weight:800;font-size:22px;color:var(--blue)">{{ split?.apoio?.propostas ?? 0 }}</div></div>
            </div>
          </div>
        </div>

        <!-- Grid: Top clientes + lateral -->
        <div style="display:grid;grid-template-columns:1fr 340px;gap:16px">
          <!-- Tabela top clientes -->
          <div class="contracts-table-wrap">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--brand-border-soft)">
              <div style="font-weight:700;font-size:13px">Maiores Contratos — Faturamento Mensal</div>
              <button class="btn btn-ghost" style="font-size:12px" @click="router.visit('/comercial/clientes')">Ver todos →</button>
            </div>
            <table style="width:100%;border-collapse:collapse">
              <thead>
                <tr>
                  <th style="text-align:left">Cliente</th>
                  <th style="text-align:center">UF</th>
                  <th style="text-align:right">Fat. Mensal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in topClientes" :key="c.id" style="cursor:pointer" @click="router.visit('/comercial/clientes/' + c.id)">
                  <td style="font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ c.nome }}</td>
                  <td style="text-align:center;font-size:12px">{{ c.uf || '—' }}</td>
                  <td style="text-align:right;font-weight:700;font-family:Syne,sans-serif;color:var(--brand-gold)">{{ fmtK(c.valor_mensal) }}</td>
                </tr>
                <tr v-if="!topClientes?.length">
                  <td colspan="3" style="text-align:center;padding:28px;color:var(--text-muted)">Nenhum cliente ativo</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Lateral: Funil + Distribuição -->
          <div style="display:flex;flex-direction:column;gap:14px">
            <!-- Funil de Propostas -->
            <div class="module-card">
              <div class="module-header"><div class="module-title">Funil de Propostas</div></div>
              <div class="module-body" style="padding:14px">
                <div v-for="f in funil" :key="f.label" style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                  <div style="width:10px;height:10px;border-radius:50%" :style="{ background: f.cor }"></div>
                  <div style="flex:1;font-size:12px;font-weight:600">{{ f.label }}</div>
                  <div style="font-family:Syne,sans-serif;font-weight:800;font-size:14px">{{ f.count }}</div>
                </div>
              </div>
            </div>

            <!-- Distribuição por estado -->
            <div class="module-card">
              <div class="module-header"><div class="module-title">Distribuição por Estado</div></div>
              <div class="module-body" style="padding:14px">
                <div v-for="d in distribuicao" :key="d.uf" style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                  <span style="font-size:12px;font-weight:700;width:28px">{{ d.uf }}</span>
                  <div style="flex:1;height:6px;background:rgba(0,0,0,0.04);border-radius:3px;overflow:hidden">
                    <div :style="{ width: (distribuicao.length ? (d.valor / distribuicao[0].valor) * 100 : 0) + '%', height: '100%', background: 'var(--brand-gold)', borderRadius: '3px' }"></div>
                  </div>
                  <span style="font-size:11px;color:var(--text-muted)">{{ d.count }} cli</span>
                </div>
                <div v-if="!distribuicao?.length" style="text-align:center;color:var(--text-muted);font-size:12px;padding:8px">Sem dados</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
