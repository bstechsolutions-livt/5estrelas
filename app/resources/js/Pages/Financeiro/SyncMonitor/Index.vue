<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Chart from 'primevue/chart'

const props = defineProps({
    config: { type: Object, required: true },
    current_run: { type: Object, default: null },
    runs: { type: Array, default: () => [] },
    stats: { type: Object, required: true },
    charts_12h: {
        type: Object,
        default: () => ({
            labels: [],
            sucesso: [],
            falha: [],
            ignorado: [],
            inserted: [],
            updated: [],
            missing: [],
        }),
    },
    by_empresa: { type: Array, default: null },
    next_steps: { type: Array, default: () => [] },
})

function statusSeverity(status) {
    switch (status) {
        case 'sucesso':
            return 'success'
        case 'falha':
            return 'danger'
        case 'em_andamento':
            return 'warn'
        case 'ignorado':
            return 'secondary'
        default:
            return 'info'
    }
}

function formatDt(iso) {
    if (!iso) return '—'
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return '—'
    // Datetime com hora: locale pt-BR (dd/mm/yyyy HH:mm:ss)
    return d.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    })
}

function formatDuration(seconds) {
    if (seconds == null) return '—'
    const raw = Number(seconds)
    if (!Number.isFinite(raw) || raw < 0) return '—'

    if (raw < 60) {
        const oneDecimal = Math.round(raw * 10) / 10
        if (Number.isInteger(oneDecimal)) {
            return `${oneDecimal}s`
        }
        return `${oneDecimal.toFixed(1).replace('.', ',')}s`
    }

    const total = Math.round(raw)
    const m = Math.floor(total / 60)
    const rem = total % 60
    return rem === 0 ? `${m}min` : `${m}min ${rem}s`
}

const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { position: 'bottom' },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { precision: 0 },
        },
    },
}

const chartStatusData = computed(() => {
    const c = props.charts_12h
    if (!c?.labels?.length) return null
    return {
        labels: c.labels,
        datasets: [
            {
                label: 'Sucesso',
                data: c.sucesso ?? [],
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34, 197, 94, 0.15)',
                tension: 0.3,
                fill: false,
                pointRadius: 3,
            },
            {
                label: 'Erro',
                data: c.falha ?? [],
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.15)',
                tension: 0.3,
                fill: false,
                pointRadius: 3,
            },
            {
                label: 'Ignorado',
                data: c.ignorado ?? [],
                borderColor: '#94a3b8',
                backgroundColor: 'rgba(148, 163, 184, 0.12)',
                tension: 0.3,
                fill: false,
                pointRadius: 2,
                borderDash: [4, 4],
            },
        ],
    }
})

const chartMutationsData = computed(() => {
    const c = props.charts_12h
    if (!c?.labels?.length) return null
    return {
        labels: c.labels,
        datasets: [
            {
                label: 'Insert',
                data: c.inserted ?? [],
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.12)',
                tension: 0.3,
                fill: false,
                pointRadius: 3,
            },
            {
                label: 'Update',
                data: c.updated ?? [],
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.12)',
                tension: 0.3,
                fill: false,
                pointRadius: 3,
            },
            {
                label: 'Ausentes',
                data: c.missing ?? [],
                borderColor: '#a855f7',
                backgroundColor: 'rgba(168, 85, 247, 0.12)',
                tension: 0.3,
                fill: false,
                pointRadius: 3,
            },
        ],
    }
})
</script>

<template>
    <Head title="Sync Senior CP" />
    <AppLayout title="Sync Senior">
        <div class="p-4 md:p-6 max-w-6xl mx-auto space-y-6" dusk="payable-sync-monitor">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Sincronização Senior (CP)</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Acompanhamento read-only das execuções do sync Contas a Pagar. Sem disparo manual nesta tela.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Integração</div>
                    <div class="mt-1 font-semibold text-slate-800 dark:text-slate-100">
                        {{ config.enabled ? 'Ativa' : 'Desligada' }}
                        <span class="text-slate-400 font-normal">· {{ config.environment }}</span>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Intervalo / timeout</div>
                    <div class="mt-1 font-semibold text-slate-800 dark:text-slate-100">
                        {{ config.sync_interval_minutes }} min · {{ config.sync_http_timeout }}s HTTP
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Falhas (24h)</div>
                    <div class="mt-1 font-semibold text-slate-800 dark:text-slate-100">
                        {{ stats.failed_24h }}
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">503 / timeout (24h)</div>
                    <div class="mt-1 font-semibold text-slate-800 dark:text-slate-100">
                        {{ stats.failed_503_or_timeout_24h }}
                    </div>
                </div>
            </div>

            <div
                class="rounded-xl border p-4"
                :class="current_run
                    ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/40'
                    : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800'"
                dusk="sync-current-run"
            >
                <div class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-2">Run em andamento</div>
                <div v-if="current_run" class="space-y-1 text-sm text-slate-700 dark:text-slate-200">
                    <div class="flex flex-wrap items-center gap-2">
                        <Tag :value="current_run.status" :severity="statusSeverity(current_run.status)" />
                        <span>#{{ current_run.id }} · {{ current_run.mode }} · {{ current_run.trigger }}</span>
                    </div>
                    <div>Início: {{ formatDt(current_run.started_at) }} · duração {{ formatDuration(current_run.duration_seconds) }}</div>
                </div>
                <p v-else class="text-sm text-slate-500">Nenhum sync em andamento no momento.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" dusk="sync-charts-12h">
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
                    <div class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-1">
                        Sucesso / erro (últimas 12h)
                    </div>
                    <p class="text-xs text-slate-500 mb-3">Quantidade de runs por hora · fuso America/Sao_Paulo</p>
                    <div class="h-56">
                        <Chart
                            v-if="chartStatusData"
                            type="line"
                            :data="chartStatusData"
                            :options="lineOptions"
                            class="h-full"
                        />
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
                    <div class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-1">
                        Insert / update / ausentes (últimas 12h)
                    </div>
                    <p class="text-xs text-slate-500 mb-3">Somas por hora nas runs · fuso America/Sao_Paulo</p>
                    <div class="h-56">
                        <Chart
                            v-if="chartMutationsData"
                            type="line"
                            :data="chartMutationsData"
                            :options="lineOptions"
                            class="h-full"
                        />
                    </div>
                </div>
            </div>

            <div
                v-if="by_empresa && by_empresa.length"
                class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4"
            >
                <div class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-3">Títulos Senior por empresa</div>
                <div class="flex flex-wrap gap-3">
                    <div
                        v-for="row in by_empresa"
                        :key="String(row.cod_emp)"
                        class="px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700 text-sm"
                    >
                        <span class="text-slate-500">Emp {{ row.cod_emp ?? '—' }}</span>
                        <span class="ml-2 font-semibold text-slate-800 dark:text-slate-100">{{ row.total }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
                    <h2 class="font-semibold text-slate-800 dark:text-slate-100">Últimas execuções</h2>
                </div>
                <DataTable
                    :value="runs"
                    :paginator="runs.length > 15"
                    :rows="15"
                    striped-rows
                    size="small"
                    empty-message="Nenhuma execução registrada."
                    dusk="sync-runs-table"
                >
                    <Column header="Status" style="width: 8rem">
                        <template #body="{ data }">
                            <Tag :value="data.status" :severity="statusSeverity(data.status)" />
                        </template>
                    </Column>
                    <Column header="Início">
                        <template #body="{ data }">{{ formatDt(data.started_at) }}</template>
                    </Column>
                    <Column header="Fim">
                        <template #body="{ data }">{{ formatDt(data.finished_at) }}</template>
                    </Column>
                    <Column header="Duração" style="width: 6rem">
                        <template #body="{ data }">{{ formatDuration(data.duration_seconds) }}</template>
                    </Column>
                    <Column field="mode" header="Modo" style="width: 7rem" />
                    <Column field="trigger" header="Gatilho" style="width: 7rem" />
                    <Column header="Ins / Upd / Aus" style="width: 8rem">
                        <template #body="{ data }">
                            <span class="font-mono text-xs">{{ data.inserted_count }} / {{ data.updated_count }} / {{ data.missing_count }}</span>
                        </template>
                    </Column>
                    <Column header="Erro">
                        <template #body="{ data }">
                            <span
                                v-if="data.error_message"
                                class="text-xs text-red-600 dark:text-red-400 line-clamp-2"
                                :title="data.error_message"
                            >{{ data.error_message }}</span>
                            <span v-else class="text-slate-400">—</span>
                        </template>
                    </Column>
                </DataTable>
            </div>

            <p v-if="next_steps.length" class="text-xs text-slate-400">
                {{ next_steps[0] }}
            </p>
        </div>
    </AppLayout>
</template>
