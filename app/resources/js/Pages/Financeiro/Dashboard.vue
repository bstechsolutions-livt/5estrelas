<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Chart from 'primevue/chart'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import BranchAccessBlocked from '@/Components/Financeiro/BranchAccessBlocked.vue'

const props = defineProps({
    kpis: Object,
    payables_by_status: Array,
    borderos_by_status: Array,
    vencimentos_semanas: Array,
    proximos_vencimentos: Array,
    minhas_aprovacoes: Object,
    conciliacao: Object,
    department: Object,
    permissions: Object,
    no_branch_access: { type: Boolean, default: false },
})

const fmt = (v) => v != null && v > 0
    ? 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : 'R$ 0,00'

const fmtK = (v) => {
    if (!v || v === 0) return 'R$ 0'
    if (v >= 1e6) return 'R$ ' + (v / 1e6).toLocaleString('pt-BR', { minimumFractionDigits: 1 }) + 'M'
    if (v >= 1000) return 'R$ ' + (v / 1000).toLocaleString('pt-BR', { minimumFractionDigits: 0 }) + 'k'
    return fmt(v)
}

const statusColors = {
    pendente: '#f59e0b',
    em_preparacao: '#3b82f6',
    aguardando_aprovacao: '#f97316',
    aprovado: '#22c55e',
    pago: '#10b981',
    pendente: '#94a3b8',
    em_preparacao: '#60a5fa',
}

const chartStatusData = computed(() => {
    const items = props.payables_by_status?.filter((i) => i.count > 0) ?? []
    return {
        labels: items.map((i) => i.label),
        datasets: [{
            data: items.map((i) => i.total),
            backgroundColor: items.map((i) => statusColors[i.status] ?? '#64748b'),
        }],
    }
})

const chartBorderoData = computed(() => {
    if (!props.borderos_by_status) return null
    const items = props.borderos_by_status.filter((i) => i.count > 0)
    return {
        labels: items.map((i) => i.label),
        datasets: [{
            label: 'Quantidade',
            data: items.map((i) => i.count),
            backgroundColor: items.map((i) => statusColors[i.status] ?? '#64748b'),
        }],
    }
})

const chartVencimentosData = computed(() => ({
    labels: props.vencimentos_semanas?.map((w) => w.label) ?? [],
    datasets: [{
        label: 'Títulos',
        data: props.vencimentos_semanas?.map((w) => w.count) ?? [],
        backgroundColor: '#3b82f6',
    }],
}))

const chartOptions = {
    plugins: { legend: { position: 'bottom' } },
    maintainAspectRatio: false,
}

const doughnutOptions = {
    plugins: { legend: { position: 'right' } },
    maintainAspectRatio: false,
}

function go(path) {
    router.visit(path)
}

function formatDate(d) {
    if (!d) return '—'
    const [y, m, day] = d.split('-')
    return `${day}/${m}/${y}`
}
</script>

<template>
    <Head title="Dashboard Financeiro" />
    <AppLayout>
        <div class="p-4 md:p-6 max-w-[1400px] mx-auto space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Dashboard Financeiro</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Visão geral de contas a pagar, borderôs e conciliação
                        <span v-if="department" class="ml-1">· {{ department.name }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button label="Atualizar" icon="pi pi-refresh" severity="secondary" outlined size="small" @click="router.reload()" />
                    <Button label="Contas a Pagar" icon="pi pi-wallet" size="small" @click="go('/financeiro/contas-pagar')" />
                    <Button
                        v-if="permissions?.borderos"
                        label="Borderôs"
                        icon="pi pi-list-check"
                        severity="secondary"
                        size="small"
                        @click="go('/financeiro/borderos')"
                    />
                    <Button
                        v-if="permissions?.conciliacao"
                        label="Conciliação"
                        icon="pi pi-file-import"
                        severity="secondary"
                        size="small"
                        @click="go('/financeiro/contas-pagar/conciliacao')"
                    />
                </div>
            </div>

            <BranchAccessBlocked v-if="no_branch_access" />

            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-8 gap-3">
                <button
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-left hover:border-amber-400 transition-colors"
                    @click="go('/financeiro/contas-pagar?status=pendente')"
                >
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Em aberto</div>
                    <div class="text-2xl font-bold text-amber-600 mt-1">{{ kpis?.em_aberto?.count ?? 0 }}</div>
                    <div class="text-xs text-slate-400 mt-1">{{ fmtK(kpis?.em_aberto?.total) }}</div>
                </button>
                <button
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-left hover:border-orange-400 transition-colors"
                    @click="go('/financeiro/contas-pagar?status=aguardando_aprovacao')"
                >
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Aguard. aprovação</div>
                    <div class="text-2xl font-bold text-orange-600 mt-1">{{ kpis?.aguardando_aprovacao?.count ?? 0 }}</div>
                    <div class="text-xs text-slate-400 mt-1">{{ fmtK(kpis?.aguardando_aprovacao?.total) }}</div>
                </button>
                <button
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-left hover:border-green-400 transition-colors"
                    @click="go('/financeiro/contas-pagar?status=aprovado')"
                >
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Aprovados</div>
                    <div class="text-2xl font-bold text-green-600 mt-1">{{ kpis?.aprovado?.count ?? 0 }}</div>
                    <div class="text-xs text-slate-400 mt-1">{{ fmtK(kpis?.aprovado?.total) }}</div>
                </button>
                <button
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-left hover:border-red-400 transition-colors"
                    @click="go('/financeiro/contas-pagar?status=pendente')"
                >
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Vencidos</div>
                    <div class="text-2xl font-bold text-red-600 mt-1">{{ kpis?.vencidos?.count ?? 0 }}</div>
                    <div class="text-xs text-slate-400 mt-1">{{ fmtK(kpis?.vencidos?.total) }}</div>
                </button>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Vencendo 7 dias</div>
                    <div class="text-2xl font-bold text-blue-600 mt-1">{{ kpis?.vencendo_7d?.count ?? 0 }}</div>
                    <div class="text-xs text-slate-400 mt-1">{{ fmtK(kpis?.vencendo_7d?.total) }}</div>
                </div>
                <button
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-left hover:border-emerald-400 transition-colors"
                    @click="go('/financeiro/pendencias')"
                >
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Minhas aprovações</div>
                    <div class="text-2xl font-bold text-emerald-600 mt-1">{{ kpis?.minhas_aprovacoes ?? 0 }}</div>
                    <div class="text-xs text-slate-400 mt-1">pendentes comigo</div>
                </button>
                <button
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-left hover:border-red-500 transition-colors"
                    @click="go('/financeiro/contas-pagar?status=aprovado')"
                >
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Urgentes</div>
                    <div class="text-2xl font-bold text-red-600 mt-1">{{ kpis?.urgentes?.count ?? 0 }}</div>
                    <div class="text-xs text-slate-400 mt-1">{{ fmtK(kpis?.urgentes?.total) }}</div>
                </button>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <div class="text-xs text-slate-500 uppercase tracking-wide">SLA estourado</div>
                    <div class="text-2xl font-bold text-orange-600 mt-1">{{ kpis?.sla_estourado?.count ?? 0 }}</div>
                    <div class="text-xs text-slate-400 mt-1">{{ fmtK(kpis?.sla_estourado?.total) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-3">CP por status (valor)</h2>
                    <div class="h-64">
                        <Chart v-if="chartStatusData.labels.length" type="doughnut" :data="chartStatusData" :options="doughnutOptions" class="h-full" />
                        <p v-else class="text-sm text-slate-400 text-center py-16">Nenhum título no pipeline</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                    <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-3">Vencimentos — próximas 4 semanas</h2>
                    <div class="h-64">
                        <Chart type="bar" :data="chartVencimentosData" :options="chartOptions" class="h-full" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div
                    v-if="permissions?.borderos && chartBorderoData"
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4"
                >
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-semibold text-slate-700 dark:text-slate-200">Borderôs</h2>
                        <span v-if="kpis?.borderos_abertos" class="text-xs text-slate-500">
                            {{ kpis.borderos_abertos.count }} em aberto · {{ fmtK(kpis.borderos_abertos.total) }}
                        </span>
                    </div>
                    <div class="h-48">
                        <Chart v-if="chartBorderoData.labels.length" type="bar" :data="chartBorderoData" :options="chartOptions" class="h-full" />
                        <p v-else class="text-sm text-slate-400 text-center py-12">Nenhum borderô</p>
                    </div>
                </div>

                <div
                    v-if="permissions?.conciliacao && conciliacao"
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4"
                >
                    <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-3">Conciliação bancária</h2>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-slate-50 dark:bg-slate-900 p-3">
                            <div class="text-xs text-slate-500">Importações</div>
                            <div class="text-xl font-bold">{{ conciliacao.imports }}</div>
                        </div>
                        <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 p-3">
                            <div class="text-xs text-amber-700 dark:text-amber-400">Pendentes</div>
                            <div class="text-xl font-bold text-amber-700">{{ conciliacao.pending }}</div>
                        </div>
                        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 p-3">
                            <div class="text-xs text-red-700 dark:text-red-400">Sem match</div>
                            <div class="text-xl font-bold text-red-700">{{ conciliacao.unmatched }}</div>
                        </div>
                        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-3">
                            <div class="text-xs text-green-700 dark:text-green-400">Conciliados</div>
                            <div class="text-xl font-bold text-green-700">{{ conciliacao.matched }}</div>
                        </div>
                    </div>
                    <Button label="Abrir conciliação" class="mt-3 w-full" size="small" outlined @click="go('/financeiro/contas-pagar/conciliacao')" />
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 lg:col-span-1">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-semibold text-slate-700 dark:text-slate-200">Minhas aprovações</h2>
                        <Button v-if="minhas_aprovacoes?.count" label="Ver todas" link size="small" @click="go('/financeiro/pendencias')" />
                    </div>
                    <ul v-if="minhas_aprovacoes?.items?.length" class="space-y-2">
                        <li
                            v-for="item in minhas_aprovacoes.items"
                            :key="item.id"
                            class="flex items-center justify-between gap-2 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-900 cursor-pointer"
                            @click="go(`/financeiro/contas-pagar/${item.id}`)"
                        >
                            <div class="min-w-0">
                                <div class="text-sm font-medium truncate">{{ item.supplier_name }}</div>
                                <div class="text-xs text-slate-500">{{ item.title_number }} · venc. {{ formatDate(item.due_date) }}</div>
                            </div>
                            <span class="text-sm font-semibold shrink-0">{{ fmt(item.amount) }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-slate-400 py-6 text-center">Nenhuma aprovação pendente com você</p>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="font-semibold text-slate-700 dark:text-slate-200">Próximos vencimentos</h2>
                    <div class="text-xs text-slate-500">
                        Pagos no mês: {{ kpis?.pagos_mes?.count ?? 0 }} · {{ fmtK(kpis?.pagos_mes?.total) }}
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900 text-slate-500 text-xs uppercase">
                            <tr>
                                <th class="text-left px-4 py-2">Fornecedor</th>
                                <th class="text-left px-4 py-2">Título</th>
                                <th class="text-left px-4 py-2">Vencimento</th>
                                <th class="text-left px-4 py-2">Status</th>
                                <th class="text-right px-4 py-2">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in proximos_vencimentos"
                                :key="row.id"
                                class="border-t border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-900 cursor-pointer"
                                @click="go(`/financeiro/contas-pagar/${row.id}`)"
                            >
                                <td class="px-4 py-2 font-medium">{{ row.supplier_name }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ row.title_number }}</td>
                                <td class="px-4 py-2">{{ formatDate(row.due_date) }}</td>
                                <td class="px-4 py-2"><Tag :value="row.status_label" severity="secondary" /></td>
                                <td class="px-4 py-2 text-right font-semibold">{{ fmt(row.amount) }}</td>
                            </tr>
                            <tr v-if="!proximos_vencimentos?.length">
                                <td colspan="5" class="px-4 py-8 text-center text-slate-400">Nenhum vencimento futuro em aberto</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
