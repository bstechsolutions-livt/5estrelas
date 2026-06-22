<script setup>
import { ref, watch, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
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

// Filters
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

// Actions
function accept(id) {
    router.post(`/financeiro/contas-pagar/conciliacao/transactions/${id}/accept`, {}, { preserveScroll: true })
}

function reject(id) {
    router.post(`/financeiro/contas-pagar/conciliacao/transactions/${id}/reject`, {}, { preserveScroll: true })
}

function batchConciliate() {
    router.post(`/financeiro/contas-pagar/conciliacao/${stmtImport.value.id}/batch-conciliate`, {}, { preserveScroll: true })
}

// Manual link dialog
const linkDialogVisible = ref(false)
const linkTransactionId = ref(null)
const searchQuery = ref('')
const searchResults = ref([])
const searching = ref(false)

function openLinkDialog(txId) {
    linkTransactionId.value = txId
    searchQuery.value = ''
    searchResults.value = []
    linkDialogVisible.value = true
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
        onSuccess: () => {
            linkDialogVisible.value = false
        },
    })
}

// Formatting helpers
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

function matchStatusLabel(status) {
    return { pending: 'Pendente', accepted: 'Aceito', rejected: 'Rejeitado', manual: 'Manual', unmatched: 'Sem match' }[status] || status
}

const canBatch = computed(() => props.counters?.matched > 0)
</script>

<template>
    <AppLayout>
        <Toast />
        <div class="max-w-7xl mx-auto">
            <!-- Header + back -->
            <div class="mb-6">
                <button @click="router.visit('/financeiro/contas-pagar/conciliacao')" class="text-sm text-blue-600 hover:underline mb-2 inline-flex items-center gap-1">
                    <i class="pi pi-arrow-left text-xs"></i> Voltar
                </button>
                <h1 class="text-2xl font-bold text-gray-800">{{ stmtImport.bank_name || 'Importacao OFX' }}</h1>
                <p class="text-sm text-gray-500 mt-1">Conta: {{ stmtImport.account_number }} &middot; Periodo: {{ formatDate(stmtImport.period_start) }} a {{ formatDate(stmtImport.period_end) }}</p>
            </div>

            <!-- Counters -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-800">{{ counters.total_debits }}</p>
                    <p class="text-xs text-gray-500">Debitos</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 text-center" dusk="counter-matched">
                    <p class="text-2xl font-bold text-green-600">{{ counters.matched }}</p>
                    <p class="text-xs text-gray-500">Aceitos</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 text-center" dusk="counter-pending">
                    <p class="text-2xl font-bold text-amber-600">{{ counters.pending }}</p>
                    <p class="text-xs text-gray-500">Pendentes</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-400">{{ counters.unmatched }}</p>
                    <p class="text-xs text-gray-500">Sem match</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
                    <p class="text-2xl font-bold text-red-500">{{ counters.rejected }}</p>
                    <p class="text-xs text-gray-500">Rejeitados</p>
                </div>
            </div>

            <!-- Filter tabs -->
            <div class="flex flex-wrap gap-2 mb-4">
                <button
                    v-for="tab in filterTabs"
                    :key="tab.value"
                    @click="applyFilter(tab.value)"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                        activeFilter === tab.value
                            ? 'bg-blue-600 text-white shadow-sm'
                            : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'
                    ]"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Transactions DataTable -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <DataTable
                    :value="transactions.data"
                    striped-rows
                    :lazy="true"
                    :paginator="true"
                    :rows="transactions.per_page"
                    :total-records="transactions.total"
                    :first="(transactions.current_page - 1) * transactions.per_page"
                >
                    <Column header="Data" style="width: 100px">
                        <template #body="{ data }">
                            <span :dusk="`transaction-row-${data.id}`">{{ formatDate(data.date) }}</span>
                        </template>
                    </Column>
                    <Column header="Valor" style="width: 130px">
                        <template #body="{ data }">
                            <span :class="data.type === 'credit' ? 'text-green-600' : 'text-red-600'" class="font-semibold">
                                {{ data.type === 'credit' ? '+' : '-' }}{{ formatMoney(Math.abs(data.amount)) }}
                            </span>
                        </template>
                    </Column>
                    <Column field="description" header="Descricao" style="min-width: 200px">
                        <template #body="{ data }">
                            <span class="text-sm">{{ data.description || data.memo || '—' }}</span>
                        </template>
                    </Column>
                    <Column header="Match" style="min-width: 180px">
                        <template #body="{ data }">
                            <div v-if="data.matched_payable">
                                <p class="text-sm font-medium text-blue-700">{{ data.matched_payable.title_number }}</p>
                                <p class="text-xs text-gray-500">{{ data.matched_payable.supplier_name }}</p>
                            </div>
                            <span v-else class="text-gray-400 text-sm">—</span>
                        </template>
                    </Column>
                    <Column header="Confianca" style="width: 100px">
                        <template #body="{ data }">
                            <Tag :value="confidenceLabel(data.match_confidence)" :severity="confidenceSeverity(data.match_confidence)" />
                        </template>
                    </Column>
                    <Column header="Acoes" style="width: 220px" v-if="isConciliador">
                        <template #body="{ data }">
                            <div class="flex gap-1 flex-wrap">
                                <!-- Pending with match -->
                                <template v-if="data.match_status === 'pending' && data.matched_payable">
                                    <Button label="Aceitar" severity="success" size="small" outlined :dusk="`btn-accept-${data.id}`" @click="accept(data.id)" />
                                    <Button label="Rejeitar" severity="danger" size="small" outlined :dusk="`btn-reject-${data.id}`" @click="reject(data.id)" />
                                </template>
                                <!-- Accepted -->
                                <template v-else-if="data.match_status === 'accepted' || data.match_status === 'manual'">
                                    <Tag value="Aceito" severity="success" class="mr-1" />
                                    <Button label="Desfazer" severity="secondary" size="small" text @click="reject(data.id)" />
                                </template>
                                <!-- Rejected -->
                                <template v-else-if="data.match_status === 'rejected'">
                                    <Tag value="Rejeitado" severity="danger" class="mr-1" />
                                    <Button label="Vincular" severity="info" size="small" outlined :dusk="`btn-link-${data.id}`" @click="openLinkDialog(data.id)" />
                                </template>
                                <!-- Unmatched -->
                                <template v-else-if="data.match_status === 'unmatched'">
                                    <Button label="Vincular" severity="info" size="small" outlined :dusk="`btn-link-${data.id}`" @click="openLinkDialog(data.id)" />
                                </template>
                                <!-- Pending without match -->
                                <template v-else-if="data.match_status === 'pending' && !data.matched_payable">
                                    <Button label="Vincular" severity="info" size="small" outlined :dusk="`btn-link-${data.id}`" @click="openLinkDialog(data.id)" />
                                </template>
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center py-8 text-gray-500">Nenhuma transacao encontrada.</div>
                    </template>
                </DataTable>
            </div>

            <!-- Sticky footer - Batch button -->
            <div v-if="isConciliador" class="sticky bottom-0 bg-white border-t border-gray-200 p-4 mt-4 rounded-xl shadow-lg flex justify-end">
                <Button
                    :label="`Conciliar Aceitos (${counters.matched})`"
                    icon="pi pi-check-circle"
                    :disabled="!canBatch"
                    dusk="btn-batch-conciliate"
                    @click="batchConciliate"
                />
            </div>
        </div>

        <!-- Link Dialog -->
        <Dialog v-model:visible="linkDialogVisible" header="Vincular titulo manualmente" :modal="true" :style="{ width: '600px' }" dusk="search-payable-dialog">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar titulo pago</label>
                <div class="flex gap-2">
                    <InputText
                        v-model="searchQuery"
                        placeholder="Numero, fornecedor ou valor..."
                        class="flex-1"
                        dusk="search-payable-input"
                        @keyup.enter="searchPayables"
                    />
                    <Button label="Buscar" icon="pi pi-search" @click="searchPayables" :loading="searching" />
                </div>
            </div>

            <div v-if="searchResults.length > 0" class="max-h-64 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Titulo</th>
                            <th class="px-3 py-2 text-left">Fornecedor</th>
                            <th class="px-3 py-2 text-right">Valor</th>
                            <th class="px-3 py-2 text-center">Pago em</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in searchResults" :key="p.id" class="border-t hover:bg-gray-50" :dusk="`search-payable-result-${p.id}`">
                            <td class="px-3 py-2">{{ p.title_number }}</td>
                            <td class="px-3 py-2">{{ p.supplier_name }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ formatMoney(p.amount) }}</td>
                            <td class="px-3 py-2 text-center">{{ formatDate(p.paid_at) }}</td>
                            <td class="px-3 py-2 text-center">
                                <Button label="Vincular" size="small" @click="linkPayable(p.id)" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else-if="searchQuery.length >= 2 && !searching" class="text-sm text-gray-500 text-center py-4">Nenhum resultado encontrado.</p>
        </Dialog>
    </AppLayout>
</template>
