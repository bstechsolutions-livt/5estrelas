<script setup>
import Tag from 'primevue/tag'
import Button from 'primevue/button'

const props = defineProps({
    payable: { type: Object, required: true },
    statusOptions: { type: Object, default: () => ({}) },
    statusSeverity: { type: Object, default: () => ({}) },
    prioritySeverity: { type: Object, default: () => ({}) },
    documentTypes: { type: Object, default: () => ({}) },
    compact: { type: Boolean, default: false },
})

const emit = defineEmits(['open'])

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}

function docLabel(type) {
    return props.documentTypes[type] || type || 'Documento'
}

function isImage(doc) {
    return doc.mime_type?.startsWith('image/')
}

function isPdf(doc) {
    return doc.mime_type === 'application/pdf'
}

function documentPairAlertTag(alert) {
    if (!alert) return null
    return alert.code === 'missing_nota' ? 'Falta NF' : 'Falta boleto'
}
</script>

<template>
    <article
        class="bg-white rounded-xl border border-gray-200 overflow-hidden"
        :class="compact ? '' : 'shadow-sm'"
        dusk="payable-detailed-card"
    >
        <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold text-gray-800 truncate">{{ payable.supplier_name }}</h3>
                    <Tag :value="statusOptions[payable.status]" :severity="statusSeverity[payable.status] || 'secondary'" class="!text-[10px]" />
                    <Tag
                        v-if="payable.payment_priority"
                        :value="payable.priority_label"
                        :severity="prioritySeverity[payable.payment_priority] || 'secondary'"
                        class="!text-[10px]"
                    />
                    <Tag
                        v-if="payable.document_pair_alert"
                        :value="documentPairAlertTag(payable.document_pair_alert)"
                        severity="warn"
                        class="!text-[10px]"
                    />
                    <Tag v-if="payable.origem_hub" value="Hub" severity="info" class="!text-[10px]" />
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    {{ payable.title_number || '—' }}
                    · Venc. {{ formatDate(payable.due_date) }}
                    <span v-if="payable.empresa_nome"> · {{ payable.empresa_nome }}</span>
                    <span v-if="payable.department_nome"> · {{ payable.department_nome }}</span>
                </p>
                <p v-if="payable.description && !compact" class="text-xs text-gray-600 mt-1 line-clamp-2">{{ payable.description }}</p>
            </div>
            <div class="text-right shrink-0">
                <p class="font-bold text-gray-800">{{ formatMoney(payable.amount) }}</p>
                <Button label="Abrir" icon="pi pi-external-link" size="small" text class="mt-1" @click="emit('open', payable.id)" />
            </div>
        </div>

        <div class="p-4">
            <p v-if="!payable.documents?.length" class="text-sm text-gray-400 text-center py-4">Nenhum documento anexado.</p>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div
                    v-for="doc in payable.documents"
                    :key="doc.id"
                    class="border border-gray-100 rounded-lg overflow-hidden bg-gray-50"
                >
                    <div class="px-2 py-1 bg-white border-b border-gray-100 flex items-center justify-between gap-2">
                        <span class="text-[10px] font-medium text-gray-600 uppercase">{{ docLabel(doc.doc_type) }}</span>
                        <a :href="doc.url" target="_blank" rel="noopener" class="text-[10px] text-blue-600 hover:underline">Abrir</a>
                    </div>
                    <a :href="doc.url" target="_blank" rel="noopener" class="block">
                        <img v-if="isImage(doc)" :src="doc.url" :alt="doc.name" class="w-full max-h-44 object-contain bg-white" />
                        <iframe
                            v-else-if="isPdf(doc)"
                            :src="doc.url"
                            class="w-full h-44 bg-white border-0"
                            :title="doc.name"
                        />
                        <div v-else class="h-24 flex items-center justify-center text-gray-400">
                            <i class="pi pi-file text-2xl"></i>
                        </div>
                    </a>
                    <p class="px-2 py-1 text-[11px] text-gray-600 truncate" :title="doc.name">{{ doc.name }}</p>
                </div>
            </div>
        </div>
    </article>
</template>
