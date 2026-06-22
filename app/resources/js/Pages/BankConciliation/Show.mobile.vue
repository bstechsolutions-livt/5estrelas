<script setup>
import { ref, watch, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import BottomSheet from '@/Components/Mobile/BottomSheet.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import InputText from 'primevue/inputtext'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
    import: Object,
    transactions: Object,
    counters: Object,
    isConciliador: Boolean,
    filters: Object,
})

// 'import' is a reserved word in JS — alias it to avoid Vue compiler issues
const stmtImport = computed(() => props.import)

const toast = useToast()
const page = usePage()

watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        toast.add({ severity: 'success', summary: 'Sucesso', detail: flash.success, life: 4000 })
    }
    if (flash?.error) {
        toast.add({ severity: 'error', summary: 'Erro', detail: flash.error, life: 5000 })
    }
}, { deep: true })

// Filter
const activeFilter = ref(props.filters?.match_status || null)

const filterTabs = [
    { label: 'Todos', value: null },
    { label: 'Pendentes', value: 'pending' },
    { label: 'Aceitos', value: 'accepted' },
    { label: 'Rejeitados', value: 'rejected' },
    { label: 'Sem match', value: 'unmatched' },
]

function applyFilter(status) {
    activeFilter.value = status
    router.get(`/financeiro/contas-pagar/conciliacao/${stmtImport.value.id}`, {
        match_status: status || undefined,
    }, { preserveState: true, replace: true })
}

// Transaction detail bottom sheet
const detailSheetOpen = ref(false)
const selectedTx = ref(null)

function openDetail(tx) {
    selectedTx.value = tx
    detailSheetOpen.value = true
}

// Actions
function accept(id) {
    router.post(`/financeiro/contas-pagar/conciliacao/transactions/${id}/accept`, {}, {
        preserveScroll: true,
        onSuccess: () => { detailSheetOpen.value = false },
    })
}

function reject(id) {
    router.post(`/financeiro/contas-pagar/conciliacao/transactions/${id}/reject`, {}, {
        preserveScroll: true,
        onSuccess: () => { detailSheetOpen.value = false },
    })
}

// Batch
const batchSheetOpen = ref(false)

function batchConciliate() {
    router.post(`/financeiro/contas-pagar/conciliacao/${stmtImport.value.id}/batch-conciliate`, {}, {
        preserveScroll: true,
        onSuccess: () => { batchSheetOpen.value = false },
    })
}

// Manual link bottom sheet
const linkSheetOpen = ref(false)
const linkTransactionId = ref(null)
const searchQuery = ref('')
const searchResults = ref([])
const searching = ref(false)

function openLinkSheet(txId) {
    linkTransactionId.value = txId
    searchQuery.value = ''
    searchResults.value = []
    linkSheetOpen.value = true
    detailSheetOpen.value = false
}

async function searchPayables() {
    if (searchQuery.value.length < 2) return
    searching.value = true
    try {
        const response = await fetch(`/financeiro/contas-pagar/conciliacao/search-payables?query=${encodeURIComponent(searchQuery.value)}`)
        searchResults.value = await response.json()
    } catch (e) {
        searchResults.value = []
    }
    searching.value = false
}

function linkPayable(payableId) {
    router.post(`/financeiro/contas-pagar/conciliacao/transactions/${linkTransactionId.value}/link`, {
        payable_id: payableId,
    }, {
        preserveScroll: true,
        onSuccess: () => { linkSheetOpen.value = false },
    })
}

// Helpers
function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}

function confidenceSeverity(confidence) {
    return { high: 'success', medium: 'warn', low: 'danger', none: 'secondary' }[confidence] || 'secondary'
}

function confidenceLabel(confidence) {
    return { high: 'Alta', medium: 'Media', low: 'Baixa', none: '—' }[confidence] || '—'
}

const canBatch = computed(() => props.counters?.matched > 0)
</script>

<template>
    <AppLayoutMobile>
        <Toast />
        <div class="px-4 pb-24">
            <!-- Header -->
            <div class="mb-4">
                <button @click="router.visit('/financeiro/contas-pagar/conciliacao')" class="text-sm text-blue-600 mb-2 inline-flex items-center gap-1">
                    <i class="pi pi-arrow-left text-xs"></i> Voltar
                </button>
                <h1 class="text-lg font-bold text-gray-800">{{ stmtImport.bank_name || 'Importacao' }}</h1>
                <p class="text-xs text-gray-500">Conta: {{ stmtImport.account_number }}</p>
            </div>

            <!-- Summary cards -->
            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="bg-white rounded-lg border border-gray-100 p-3 text-center" dusk="counter-matched">
                    <p class="text-lg font-bold text-green-600">{{ counters.matched }}</p>
                    <p class="text-[10px] text-gray-500">Aceitos</p>
                </div>
                <div class="bg-white rounded-lg border border-gray-100 p-3 text-center" dusk="counter-pending">
                    <p class="text-lg font-bold text-amber-600">{{ counters.pending }}</p>
                    <p class="text-[10px] text-gray-500">Pendentes</p>
                </div>
                <div class="bg-white rounded-lg border border-gray-100 p-3 text-center">
                    <p class="text-lg font-bold text-gray-400">{{ counters.unmatched }}</p>
                    <p class="text-[10px] text-gray-500">Sem match</p>
                </div>
            </div>

            <!-- Filter tabs -->
            <div class="flex gap-2 overflow-x-auto mb-4 pb-1">
                <button
                    v-for="tab in filterTabs"
                    :key="tab.value"
                    @click="applyFilter(tab.value)"
                    :class="[
                        'px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors',
                        activeFilter === tab.value
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-100 text-gray-600'
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Transaction cards -->
            <div v-if="transactions.data.length === 0" class="text-center py-12 text-gray-500">
                <p>Nenhuma transacao encontrada.</p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="tx in transactions.data"
                    :key="tx.id"
                    class="bg-white rounded-xl border border-gray-100 p-4 active:bg-gray-50"
                    :dusk="`transaction-row-${tx.id}`"
                    @click="openDetail(tx)"
                >
                    <div class="flex justify-between items-start mb-1">
                        <span :class="tx.type === 'credit' ? 'text-green-600' : 'text-red-600'" class="font-semibold text-sm">
                            {{ tx.type === 'credit' ? '+' : '-' }}{{ formatMoney(Math.abs(tx.amount)) }}
                        </span>
                        <Tag :value="confidenceLabel(tx.match_confidence)" :severity="confidenceSeverity(tx.match_confidence)" class="text-[10px]" />
                    </div>
                    <p class="text-xs text-gray-700 truncate">{{ tx.description || tx.memo || '—' }}</p>
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-[10px] text-gray-400">{{ formatDate(tx.date) }}</span>
                        <span v-if="tx.matched_payable" class="text-[10px] text-blue-600 font-medium">{{ tx.matched_payable.title_number }}</span>
                    </div>
                </div>
            </div>

            <!-- Batch button fixed -->
            <div v-if="isConciliador && canBatch" class="fixed bottom-16 left-0 right-0 px-4 pb-4">
                <Button
                    :label="`Conciliar Aceitos (${counters.matched})`"
                    icon="pi pi-check-circle"
                    class="w-full"
                    dusk="btn-batch-conciliate"
                    @click="batchSheetOpen = true"
                />
            </div>
        </div>

        <!-- Detail bottom sheet -->
        <BottomSheet v-model:visible="detailSheetOpen" title="Detalhes da transacao">
            <div v-if="selectedTx" class="space-y-3 px-4 pb-6">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Valor</span>
                    <span :class="selectedTx.type === 'credit' ? 'text-green-600' : 'text-red-600'" class="font-semibold">
                        {{ formatMoney(Math.abs(selectedTx.amount)) }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">Data</span>
                    <span class="text-sm">{{ formatDate(selectedTx.date) }}</span>
                </div>
                <div>
                    <span class="text-sm text-gray-500">Descricao</span>
                    <p class="text-sm">{{ selectedTx.description || selectedTx.memo || '—' }}</p>
                </div>
                <div v-if="selectedTx.matched_payable" class="bg-blue-50 rounded-lg p-3">
                    <p class="text-xs text-blue-600 font-medium">Match sugerido</p>
                    <p class="text-sm font-semibold">{{ selectedTx.matched_payable.title_number }} — {{ selectedTx.matched_payable.supplier_name }}</p>
                    <p class="text-xs text-gray-600">{{ formatMoney(selectedTx.matched_payable.amount) }}</p>
                </div>

                <!-- Actions -->
                <div v-if="isConciliador" class="pt-3 space-y-2">
                    <template v-if="selectedTx.match_status === 'pending' && selectedTx.matched_payable">
                        <Button label="Aceitar" severity="success" class="w-full" :dusk="`btn-accept-${selectedTx.id}`" @click="accept(selectedTx.id)" />
                        <Button label="Rejeitar" severity="danger" outlined class="w-full" :dusk="`btn-reject-${selectedTx.id}`" @click="reject(selectedTx.id)" />
                    </template>
                    <template v-else-if="selectedTx.match_status === 'accepted' || selectedTx.match_status === 'manual'">
                        <Tag value="Aceito" severity="success" class="mb-2" />
                        <Button label="Desfazer" severity="secondary" class="w-full" @click="reject(selectedTx.id)" />
                    </template>
                    <template v-else-if="selectedTx.match_status === 'rejected' || selectedTx.match_status === 'unmatched' || (selectedTx.match_status === 'pending' && !selectedTx.matched_payable)">
                        <Button label="Vincular manualmente" severity="info" class="w-full" :dusk="`btn-link-${selectedTx.id}`" @click="openLinkSheet(selectedTx.id)" />
                    </template>
                </div>
            </div>
        </BottomSheet>

        <!-- Batch confirm bottom sheet -->
        <BottomSheet v-model:visible="batchSheetOpen" title="Confirmar conciliacao">
            <div class="px-4 pb-6 space-y-4">
                <p class="text-sm text-gray-700">Voce esta prestes a conciliar <strong>{{ counters.matched }}</strong> transacao(oes) aceitas.</p>
                <p class="text-xs text-gray-500">Os titulos vinculados terao o status alterado para "conciliado".</p>
                <Button label="Confirmar conciliacao" icon="pi pi-check-circle" class="w-full" dusk="batch-confirm" @click="batchConciliate" />
                <Button label="Cancelar" severity="secondary" outlined class="w-full" @click="batchSheetOpen = false" />
            </div>
        </BottomSheet>

        <!-- Link bottom sheet -->
        <BottomSheet v-model:visible="linkSheetOpen" title="Vincular titulo" dusk="search-payable-dialog">
            <div class="px-4 pb-6 space-y-4">
                <div class="flex gap-2">
                    <InputText
                        v-model="searchQuery"
                        placeholder="Buscar por titulo, fornecedor..."
                        class="flex-1"
                        dusk="search-payable-input"
                        @keyup.enter="searchPayables"
                    />
                    <Button icon="pi pi-search" @click="searchPayables" :loading="searching" />
                </div>

                <div v-if="searchResults.length > 0" class="max-h-64 overflow-y-auto space-y-2">
                    <div
                        v-for="p in searchResults"
                        :key="p.id"
                        class="bg-gray-50 rounded-lg p-3 flex justify-between items-center"
                        :dusk="`search-payable-result-${p.id}`"
                    >
                        <div>
                            <p class="text-sm font-medium">{{ p.title_number }}</p>
                            <p class="text-xs text-gray-500">{{ p.supplier_name }} &middot; {{ formatMoney(p.amount) }}</p>
                        </div>
                        <Button label="Vincular" size="small" @click="linkPayable(p.id)" />
                    </div>
                </div>
                <p v-else-if="searchQuery.length >= 2 && !searching" class="text-sm text-gray-500 text-center">Nenhum resultado.</p>
            </div>
        </BottomSheet>
    </AppLayoutMobile>
</template>
