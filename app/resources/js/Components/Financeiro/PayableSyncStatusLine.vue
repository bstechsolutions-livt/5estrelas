<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    syncStatus: {
        type: Object,
        default: null,
    },
})

function formatDt(iso) {
    if (!iso) return '—'
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return '—'
    return d.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    })
}

const lastLabel = computed(() => formatDt(props.syncStatus?.last_finished_at))
const nextLabel = computed(() => formatDt(props.syncStatus?.next_estimated_at))
const statusLabel = computed(() => props.syncStatus?.status_label || '—')
const statusClass = computed(() => {
    if (props.syncStatus?.online === true) return 'text-emerald-600'
    if (props.syncStatus?.online === false) return 'text-rose-600'
    return 'text-gray-400'
})
const canViewMonitor = computed(() => !!props.syncStatus?.can_view_monitor)
</script>

<template>
    <p
        v-if="syncStatus"
        class="text-xs text-gray-400 mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-0.5"
        dusk="payables-sync-status"
    >
        <span>Última sincronização: {{ lastLabel }}</span>
        <span class="text-gray-300 hidden sm:inline" aria-hidden="true">·</span>
        <span>Próxima: {{ nextLabel }}</span>
        <span class="text-gray-300 hidden sm:inline" aria-hidden="true">·</span>
        <span>
            Status:
            <span :class="['font-medium', statusClass]">{{ statusLabel }}</span>
        </span>
        <Link
            v-if="canViewMonitor"
            href="/financeiro/sync-senior"
            class="text-gray-400 hover:text-gray-600 underline-offset-2 hover:underline ml-0.5"
        >
            detalhes
        </Link>
    </p>
</template>
