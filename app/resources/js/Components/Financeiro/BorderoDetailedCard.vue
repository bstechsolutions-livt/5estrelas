<script setup>
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import PayableDetailedCard from '@/Components/Financeiro/PayableDetailedCard.vue'

const props = defineProps({
    bordero: { type: Object, required: true },
    statusOptions: { type: Object, default: () => ({}) },
    statusSeverity: { type: Object, default: () => ({}) },
    prioritySeverity: { type: Object, default: () => ({}) },
    documentTypes: { type: Object, default: () => ({}) },
    payableStatusOptions: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['open-bordero', 'open-payable'])

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}

function wasRejectedBack(bordero) {
    return bordero.status === 'rascunho' && !!bordero.rejection_reason
}
</script>

<template>
    <article class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" dusk="bordero-detailed-card">
        <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="font-bold text-gray-800">{{ bordero.number }}</h3>
                    <Tag :value="statusOptions[bordero.status]" :severity="statusSeverity[bordero.status] || 'secondary'" />
                    <Tag v-if="wasRejectedBack(bordero)" value="Recusado" severity="danger" class="!text-[10px]" />
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    {{ bordero.items_count }} títulos · {{ formatMoney(bordero.total_amount) }}
                    · {{ bordero.creator?.name || '—' }} · {{ formatDate(bordero.created_at) }}
                </p>
                <p v-if="bordero.description" class="text-xs text-gray-600 mt-1">{{ bordero.description }}</p>
                <p v-if="wasRejectedBack(bordero)" class="text-xs text-red-600 mt-1">{{ bordero.rejection_reason }}</p>
            </div>
            <Button label="Abrir borderô" icon="pi pi-external-link" size="small" outlined @click="emit('open-bordero', bordero.id)" />
        </div>

        <div class="p-4 space-y-4">
            <PayableDetailedCard
                v-for="payable in bordero.payables || []"
                :key="payable.id"
                :payable="payable"
                :status-options="payableStatusOptions"
                :status-severity="statusSeverity"
                :priority-severity="prioritySeverity"
                :document-types="documentTypes"
                compact
                @open="(id) => emit('open-payable', id)"
            />
            <p v-if="!bordero.payables?.length" class="text-sm text-gray-400 text-center py-4">Sem títulos neste borderô.</p>
        </div>
    </article>
</template>
