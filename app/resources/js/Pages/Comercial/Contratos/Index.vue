<script setup>
// ─────────────────────────────────────────────────────────────────────────────
// Contratos Ativos — portado do protótipo Gestão 360º (view-contratos).
// Tabela read-only dos clientes com contrato ativo + totais.
// ─────────────────────────────────────────────────────────────────────────────
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout/AuthenticatedLayout.vue"
import { computed } from "vue"
import { router } from "@inertiajs/vue3"
import "@/../css/comercial-g360.css"

const props = defineProps({
  contratos: { type: Array, default: () => [] },
})

const fmt = (v) => v != null && v > 0
  ? "R$ " + Number(v).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  : "—"

const totalPostos = computed(() => props.contratos.reduce((s, c) => s + (c.postos || 0), 0))
const totalFunc = computed(() => props.contratos.reduce((s, c) => s + (c.funcionarios || 0), 0))
const totalMensal = computed(() => props.contratos.reduce((s, c) => s + (c.custo_mensal || 0), 0))
</script>

<template>
  <AuthenticatedLayout>
    <div class="g360">
      <div class="view active" id="view-contratos">
        <!-- Cabeçalho -->
        <div class="page-title-row">
          <div>
            <div class="section-title">Contratos Ativos</div>
            <div class="section-desc">Todos os contratos em vigência — {{ contratos.length }} no total</div>
          </div>
          <button class="btn btn-gold" @click="router.visit('/comercial/cotacao')">+ Nova Proposta</button>
        </div>

        <!-- Tabela -->
        <div class="contracts-table-wrap" style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;min-width:900px">
            <thead>
              <tr>
                <th>Nº Contrato</th>
                <th>Cliente</th>
                <th>Serviço</th>
                <th style="text-align:center">Postos</th>
                <th style="text-align:center">Func.</th>
                <th style="text-align:right">Custo Mensal</th>
                <th style="text-align:center">UF</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in contratos" :key="c.id" :dusk="'contrato-' + c.id"
                  style="cursor:pointer" @click="router.visit('/comercial/clientes/' + c.id)">
                <td style="font-weight:700;white-space:nowrap">{{ c.numero }}</td>
                <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" :title="c.cliente">{{ c.cliente }}</td>
                <td style="font-size:12px;color:var(--text-secondary);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ c.servico }}</td>
                <td style="text-align:center">{{ c.postos || '—' }}</td>
                <td style="text-align:center">{{ c.funcionarios || '—' }}</td>
                <td style="text-align:right;font-weight:600;font-family:'Syne',sans-serif">{{ fmt(c.custo_mensal) }}</td>
                <td style="text-align:center;font-size:12px">{{ c.uf || '—' }}</td>
                <td><span class="badge badge-green">Ativo</span></td>
              </tr>
              <tr v-if="!contratos.length">
                <td colspan="8" style="text-align:center;padding:48px;color:var(--text-muted)">
                  Nenhum contrato ativo cadastrado
                </td>
              </tr>
            </tbody>
            <tfoot v-if="contratos.length">
              <tr style="border-top:2px solid var(--brand-border-soft);background:rgba(184,146,42,0.05)">
                <td colspan="3" style="font-weight:700;padding:12px 14px">TOTAL</td>
                <td style="text-align:center;font-weight:700">{{ totalPostos }}</td>
                <td style="text-align:center;font-weight:700">{{ totalFunc }}</td>
                <td style="text-align:right;font-weight:800;font-family:'Syne',sans-serif;color:var(--brand-gold)">{{ fmt(totalMensal) }}</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
