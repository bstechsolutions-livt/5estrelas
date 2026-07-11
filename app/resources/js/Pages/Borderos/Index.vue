<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import { useFinanceiroViewMode } from '@/composables/useFinanceiroViewMode'
import BorderoDetailedCard from '@/Components/Financeiro/BorderoDetailedCard.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Paginator from 'primevue/paginator'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    borderos: Object,
    totals: Object,
    filters: Object,
    statusOptions: Object,
    payableStatusOptions: { type: Object, default: () => ({}) },
    documentTypes: { type: Object, default: () => ({}) },
})

const STORAGE_KEY = 'borderos_status'
const { isMobile } = useDevice()
const { viewMode, persistViewMode, withView } = useFinanceiroViewMode('borderos_view_mode', () => props.filters?.view)
const status = ref(props.filters?.status || 'aguardando_aprovacao')

const statusList = [
    { label: 'Rascunho', value: 'rascunho' },
    { label: 'Aguardando Aprovação', value: 'aguardando_aprovacao' },
    { label: 'Aprovados', value: 'aprovado' },
    { label: 'Pagos', value: 'pago' },
]

function filterStatus(s) {
    if (status.value === s) return
    status.value = s
    localStorage.setItem(STORAGE_KEY, s)
    router.get('/financeiro/borderos', withView({ status: s }), { preserveState: true, replace: true })
}

function setViewMode(mode) {
    if (viewMode.value === mode) return
    persistViewMode(mode)
    router.get('/financeiro/borderos', withView({ status: status.value }), { preserveState: true, replace: true })
}

function onDetailedPage(event) {
    router.get('/financeiro/borderos', {
        ...withView({ status: status.value }),
        page: event.page + 1,
        per_page: event.rows,
    }, { preserveState: true, replace: true })
}

onMounted(() => {
    if (status.value === 'reprovado') {
        status.value = 'rascunho'
        localStorage.setItem(STORAGE_KEY, 'rascunho')
        router.get('/financeiro/borderos', withView({ status: 'rascunho' }), { preserveState: true, replace: true })
        return
    }

    // Visita "limpa" (sem query): restaura último status e visão usados
    if (!window.location.search) {
        const cachedView = localStorage.getItem('borderos_view_mode')
        const cached = localStorage.getItem(STORAGE_KEY)
        const normalized = cached === 'reprovado' ? 'rascunho' : cached
        const serverStatus = props.filters?.status || 'aguardando_aprovacao'
        const serverView = props.filters?.view || 'resumida'

        if (cachedView && cachedView !== serverView) {
            persistViewMode(cachedView)
            router.get('/financeiro/borderos', withView({ status: normalized || serverStatus }), { preserveState: true, replace: true })
            return
        }

        if (normalized && normalized !== serverStatus) {
            status.value = normalized
            router.get('/financeiro/borderos', withView({ status: normalized }), { preserveState: true, replace: true })
        }
    }
})

function goShow(id) { router.visit(`/financeiro/borderos/${id}`) }
function goPayable(id) { router.visit(`/financeiro/contas-pagar/${id}`) }

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}
function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}

const statusSeverity = { rascunho: 'secondary', aguardando_aprovacao: 'warn', aprovado: 'success', reprovado: 'danger', pago: 'success' }

const prioritySeverity = {
    normal: 'secondary',
    alta: 'warn',
    urgente: 'danger',
}

function wasRejectedBack(bordero) {
    return bordero.status === 'rascunho' && !!bordero.rejection_reason
}
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout" :title="isMobile ? 'Borderôs' : undefined">
        <div :class="isMobile ? 'px-4 py-3' : 'max-w-6xl mx-auto'">
            <div v-if="!isMobile" class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Borderôs</h1>
                    <p class="text-sm text-gray-500 mt-1">Agrupamentos de títulos para pagamento.</p>
                </div>
                <div class="flex rounded-lg border border-gray-200 bg-white p-0.5 shrink-0" dusk="borderos-view-toggle">
                    <button
                        type="button"
                        :class="['px-3 py-1.5 rounded-md text-sm font-medium transition-colors', viewMode === 'resumida' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50']"
                        @click="setViewMode('resumida')"
                    >
                        Resumida
                    </button>
                    <button
                        type="button"
                        :class="['px-3 py-1.5 rounded-md text-sm font-medium transition-colors', viewMode === 'detalhada' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50']"
                        @click="setViewMode('detalhada')"
                    >
                        Detalhada
                    </button>
                </div>
            </div>

            <!-- Tabs status -->
            <div class="flex flex-wrap gap-2 mb-5 overflow-x-auto">
                <button v-for="s in statusList" :key="s.value" @click="filterStatus(s.value)"
                    :class="['px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition-colors',
                        status === s.value ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50']">
                    {{ s.label }}
                    <span v-if="totals?.[s.value]" class="ml-1 text-xs opacity-75">({{ totals[s.value]?.count || 0 }})</span>
                </button>
            </div>

            <!-- Toggle visão (mobile) -->
            <div v-if="isMobile" class="px-0 pb-3">
                <div class="flex rounded-lg border border-gray-200 bg-white p-0.5" dusk="borderos-view-toggle">
                    <button
                        type="button"
                        :class="['flex-1 py-2 rounded-md text-xs font-medium', viewMode === 'resumida' ? 'bg-blue-600 text-white' : 'text-gray-600']"
                        @click="setViewMode('resumida')"
                    >
                        Resumida
                    </button>
                    <button
                        type="button"
                        :class="['flex-1 py-2 rounded-md text-xs font-medium', viewMode === 'detalhada' ? 'bg-blue-600 text-white' : 'text-gray-600']"
                        @click="setViewMode('detalhada')"
                    >
                        Detalhada
                    </button>
                </div>
            </div>

            <!-- Mobile: cards resumidos -->
            <div v-if="isMobile && viewMode === 'resumida'">
                <div v-if="borderos.data.length" class="space-y-2 pb-20">
                    <button v-for="b in borderos.data" :key="b.id" @click="goShow(b.id)"
                        class="w-full bg-white rounded-xl border border-gray-200 p-3 text-left active:bg-gray-50">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-800 whitespace-nowrap">{{ b.number }}</p>
                                <Tag
                                    v-if="wasRejectedBack(b)"
                                    value="Recusado"
                                    severity="danger"
                                    class="!text-[9px] !px-1.5 !py-0 mt-1"
                                />
                            </div>
                            <Tag :value="statusOptions[b.status]" :severity="statusSeverity[b.status]" class="text-[10px] shrink-0" />
                        </div>
                        <p v-if="wasRejectedBack(b)" class="text-xs text-red-600 mb-1 line-clamp-2">{{ b.rejection_reason }}</p>
                        <p class="text-sm font-bold text-gray-700">{{ formatMoney(b.total_amount) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ b.items_count }} títulos · {{ formatDate(b.created_at) }}</p>
                    </button>
                </div>
                <div v-else class="text-center py-12 text-gray-400 text-sm">Nenhum borderô.</div>
            </div>

            <!-- Mobile: cards detalhados -->
            <div v-else-if="isMobile && viewMode === 'detalhada'" class="space-y-3 pb-20">
                <BorderoDetailedCard
                    v-for="b in borderos.data"
                    :key="b.id"
                    :bordero="b"
                    :status-options="statusOptions"
                    :status-severity="statusSeverity"
                    :priority-severity="prioritySeverity"
                    :document-types="documentTypes"
                    :payable-status-options="payableStatusOptions"
                    @open-bordero="goShow"
                    @open-payable="goPayable"
                />
                <div v-if="!borderos.data.length" class="text-center py-12 text-gray-400 text-sm">Nenhum borderô.</div>
                <Paginator
                    v-if="borderos.total > borderos.per_page"
                    :rows="borderos.per_page"
                    :total-records="borderos.total"
                    :first="(borderos.current_page - 1) * borderos.per_page"
                    :rows-per-page-options="[5, 10]"
                    @page="onDetailedPage"
                    class="bg-white rounded-xl border border-gray-200 p-2"
                />
            </div>

            <!-- Desktop: tabela resumida -->
            <div v-else-if="!isMobile && viewMode === 'resumida'" class="bg-white rounded-xl shadow-sm border border-gray-100">
                <DataTable :value="borderos.data" striped-rows @row-click="(e) => goShow(e.data.id)" class="cursor-pointer">
                    <Column field="number" header="Número" style="width: 8.5rem; min-width: 8.5rem">
                        <template #body="{ data }">
                            <div class="flex flex-col items-start gap-1 py-0.5">
                                <span class="font-medium whitespace-nowrap leading-none">{{ data.number }}</span>
                                <Tag
                                    v-if="wasRejectedBack(data)"
                                    value="Recusado"
                                    severity="danger"
                                    class="!text-[9px] !px-1.5 !py-0 leading-tight"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column field="description" header="Descrição">
                        <template #body="{ data }">
                            <div class="min-w-0">
                                <span class="text-gray-700">{{ data.description || '—' }}</span>
                                <p
                                    v-if="wasRejectedBack(data)"
                                    class="text-xs text-red-600 mt-1 line-clamp-2"
                                    :title="data.rejection_reason"
                                >
                                    {{ data.rejection_reason }}
                                </p>
                            </div>
                        </template>
                    </Column>
                    <Column field="items_count" header="Títulos" style="width: 90px" />
                    <Column field="total_amount" header="Total" style="width: 150px">
                        <template #body="{ data }"><span class="font-semibold">{{ formatMoney(data.total_amount) }}</span></template>
                    </Column>
                    <Column header="Criado por" style="width: 160px">
                        <template #body="{ data }"><span class="text-xs text-gray-600">{{ data.creator?.name || '—' }}</span></template>
                    </Column>
                    <Column field="status" header="Status" style="width: 160px">
                        <template #body="{ data }"><Tag :value="statusOptions[data.status]" :severity="statusSeverity[data.status]" /></template>
                    </Column>
                    <template #empty><div class="text-center py-8 text-gray-500">Nenhum borderô criado.</div></template>
                </DataTable>
            </div>

            <!-- Desktop: cards detalhados -->
            <div v-else-if="!isMobile && viewMode === 'detalhada'" class="space-y-4">
                <BorderoDetailedCard
                    v-for="b in borderos.data"
                    :key="b.id"
                    :bordero="b"
                    :status-options="statusOptions"
                    :status-severity="statusSeverity"
                    :priority-severity="prioritySeverity"
                    :document-types="documentTypes"
                    :payable-status-options="payableStatusOptions"
                    @open-bordero="goShow"
                    @open-payable="goPayable"
                />
                <div v-if="!borderos.data.length" class="text-center py-12 text-gray-500 bg-white rounded-xl border border-gray-100">
                    Nenhum borderô criado.
                </div>
                <Paginator
                    v-if="borderos.total > borderos.per_page"
                    :rows="borderos.per_page"
                    :total-records="borderos.total"
                    :first="(borderos.current_page - 1) * borderos.per_page"
                    :rows-per-page-options="[5, 10]"
                    @page="onDetailedPage"
                    class="bg-white rounded-xl border border-gray-100 p-2"
                />
            </div>
        </div>
    </component>
</template>
