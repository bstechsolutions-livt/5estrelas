<script setup>
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import BottomSheet from '@/Components/Mobile/BottomSheet.vue'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Tag from 'primevue/tag'

const props = defineProps({
    payables: Object,
    totals: Object,
    filters: Object,
    branches: Array,
    statusOptions: Object,
})

const STORAGE_KEY = 'payables_filters_mobile'

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || 'pendente')
const filtersOpen = ref(false)
const branchId = ref(props.filters?.branch_id || null)
const amountMin = ref(props.filters?.amount_min ? Number(props.filters.amount_min) : null)
const amountMax = ref(props.filters?.amount_max ? Number(props.filters.amount_max) : null)
const dueFrom = ref(props.filters?.due_from || '')
const dueTo = ref(props.filters?.due_to || '')

const statusList = [
    { label: 'Pendentes', value: 'pendente' },
    { label: 'Preparação', value: 'em_preparacao' },
    { label: 'Aprovação', value: 'aguardando_aprovacao' },
    { label: 'Aprovados', value: 'aprovado' },
    { label: 'Reprovados', value: 'reprovado' },
    { label: 'Pagos', value: 'pago' },
]

const branchList = computed(() => [
    { label: 'Todas as filiais', value: null },
    ...props.branches.map(b => ({ label: b.name, value: b.id })),
])

function currentFilters() {
    return {
        search: search.value || undefined,
        status: status.value || undefined,
        branch_id: branchId.value || undefined,
        amount_min: amountMin.value || undefined,
        amount_max: amountMax.value || undefined,
        due_from: dueFrom.value || undefined,
        due_to: dueTo.value || undefined,
    }
}

function saveFilters() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(currentFilters()))
}

function applyFilters() {
    saveFilters()
    router.get('/financeiro/contas-pagar', currentFilters(), { preserveState: true, replace: true })
}

let timer = null
watch(search, () => {
    clearTimeout(timer)
    timer = setTimeout(applyFilters, 300)
})

watch(status, applyFilters)

function applyAndClose() {
    applyFilters()
    filtersOpen.value = false
}

function clearFilters() {
    search.value = ''
    branchId.value = null
    amountMin.value = null
    amountMax.value = null
    dueFrom.value = ''
    dueTo.value = ''
    localStorage.removeItem(STORAGE_KEY)
    applyFilters()
    filtersOpen.value = false
}

const activeFilterCount = computed(() => {
    let c = 0
    if (branchId.value) c++
    if (amountMin.value) c++
    if (amountMax.value) c++
    if (dueFrom.value) c++
    if (dueTo.value) c++
    return c
})

function goShow(id) {
    sessionStorage.setItem('payables_scroll_mobile', document.querySelector('.mobile-main')?.scrollTop?.toString() || '0')
    router.visit(`/financeiro/contas-pagar/${id}`)
}

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}
function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}

const statusSeverity = { pendente: 'warn', em_preparacao: 'info', aguardando_aprovacao: 'warn', aprovado: 'success', reprovado: 'danger', pago: 'success' }

const currentTotal = computed(() => {
    const t = props.totals?.[status.value]
    return { count: t?.count || 0, total: t?.total || 0 }
})
</script>

<template>
    <AppLayoutMobile title="Contas a Pagar">
        <!-- Tabs de status (scroll horizontal) -->
        <div class="px-4 pt-3 pb-2 overflow-x-auto">
            <div class="flex gap-2 min-w-max">
                <button
                    v-for="s in statusList"
                    :key="s.value"
                    @click="status = s.value"
                    :class="[
                        'px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors',
                        status === s.value
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-100 text-gray-600 active:bg-gray-200'
                    ]"
                >
                    {{ s.label }}
                    <span v-if="totals?.[s.value]" class="ml-1 opacity-75">({{ totals[s.value]?.count || 0 }})</span>
                </button>
            </div>
        </div>

        <!-- Resumo da aba ativa -->
        <div class="px-4 pb-2">
            <div class="bg-white rounded-xl border border-gray-200 p-3 flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Total {{ statusOptions[status] }}</p>
                    <p class="text-lg font-bold text-gray-800">{{ formatMoney(currentTotal.total) }}</p>
                </div>
                <p class="text-xs text-gray-400">{{ currentTotal.count }} títulos</p>
            </div>
        </div>

        <!-- Busca + botão filtro -->
        <div class="px-4 pb-2 flex gap-2">
            <InputText v-model="search" placeholder="Buscar..." class="flex-1" style="height: 44px" />
            <button @click="filtersOpen = true"
                class="w-11 h-11 rounded-lg border border-gray-300 flex items-center justify-center relative">
                <i class="pi pi-filter text-gray-600"></i>
                <span v-if="activeFilterCount > 0"
                    class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-blue-600 text-white text-[10px] flex items-center justify-center font-bold">
                    {{ activeFilterCount }}
                </span>
            </button>
        </div>

        <!-- Lista -->
        <div v-if="payables.data.length" class="px-4 space-y-2 pb-20">
            <button v-for="p in payables.data" :key="p.id" @click="goShow(p.id)"
                class="w-full bg-white rounded-xl border border-gray-200 p-3 text-left active:bg-gray-50">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <p class="text-sm font-medium text-gray-800 truncate flex-1">{{ p.supplier_name }}</p>
                    <Tag :value="statusOptions[p.status]" :severity="statusSeverity[p.status]" class="text-[10px]" />
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">{{ formatMoney(p.amount) }}</span>
                    <span class="text-xs text-gray-400">Venc: {{ formatDate(p.due_date) }}</span>
                </div>
                <p v-if="p.title_number" class="text-[11px] text-gray-400 mt-1">{{ p.title_number }} · {{ p.branch?.name || '' }}</p>
                <span v-if="p.bordero" class="inline-block mt-1 text-[10px] font-medium text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">
                    {{ p.bordero.number }}
                </span>
            </button>
        </div>
        <div v-else class="text-center py-12 text-gray-400 text-sm">Nenhum título encontrado.</div>

        <!-- Bottom sheet filtros avançados -->
        <BottomSheet v-model="filtersOpen" title="Filtros">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Filial</label>
                    <Select v-model="branchId" :options="branchList" option-label="label" option-value="value" class="w-full" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Valor mínimo</label>
                        <InputText v-model="amountMin" type="number" placeholder="0" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Valor máximo</label>
                        <InputText v-model="amountMax" type="number" placeholder="0" class="w-full" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Vencimento de</label>
                        <InputText v-model="dueFrom" type="date" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Vencimento até</label>
                        <InputText v-model="dueTo" type="date" class="w-full" />
                    </div>
                </div>
            </div>
            <div class="flex gap-2 mt-4">
                <button @click="clearFilters" class="flex-1 py-3 rounded-lg border border-gray-300 text-gray-700 font-medium">
                    Limpar
                </button>
                <button @click="applyAndClose" class="flex-1 py-3 rounded-lg text-white font-medium" :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }">
                    Aplicar
                </button>
            </div>
        </BottomSheet>
    </AppLayoutMobile>
</template>
