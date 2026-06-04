<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'

const props = defineProps({
    payables: Object,
    totals: Object,
    filters: Object,
    branches: Array,
    statusOptions: Object,
})

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || 'pendente')
const branchId = ref(props.filters?.branch_id || null)
const amountMin = ref(props.filters?.amount_min || '')
const amountMax = ref(props.filters?.amount_max || '')
const dueFrom = ref(props.filters?.due_from || '')
const dueTo = ref(props.filters?.due_to || '')

const statusList = [
    { label: 'Pendentes', value: 'pendente', color: 'amber' },
    { label: 'Em Preparação', value: 'em_preparacao', color: 'blue' },
    { label: 'Aguardando Aprovação', value: 'aguardando_aprovacao', color: 'orange' },
    { label: 'Aprovados', value: 'aprovado', color: 'green' },
    { label: 'Reprovados', value: 'reprovado', color: 'red' },
    { label: 'Pagos', value: 'pago', color: 'emerald' },
]

const branchList = computed(() => [
    { label: 'Todas as filiais', value: null },
    ...props.branches.map(b => ({ label: b.name, value: b.id })),
])

let timer = null
function applyFilters() {
    router.get('/financeiro/contas-pagar', {
        search: search.value || undefined,
        status: status.value || undefined,
        branch_id: branchId.value || undefined,
        amount_min: amountMin.value || undefined,
        amount_max: amountMax.value || undefined,
        due_from: dueFrom.value || undefined,
        due_to: dueTo.value || undefined,
    }, { preserveState: true, replace: true })
}

watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilters, 300) })
watch(status, applyFilters)
watch(branchId, applyFilters)

const hasActiveFilters = computed(() => {
    return !!(search.value || branchId.value || amountMin.value || amountMax.value || dueFrom.value || dueTo.value)
})

const activeFilterCount = computed(() => {
    let c = 0
    if (search.value) c++
    if (branchId.value) c++
    if (amountMin.value) c++
    if (amountMax.value) c++
    if (dueFrom.value) c++
    if (dueTo.value) c++
    return c
})

function clearFilters() {
    search.value = ''
    branchId.value = null
    amountMin.value = ''
    amountMax.value = ''
    dueFrom.value = ''
    dueTo.value = ''
    applyFilters()
}

function onPage(event) {
    router.get('/financeiro/contas-pagar', {
        search: search.value || undefined,
        status: status.value || undefined,
        branch_id: branchId.value || undefined,
        amount_min: amountMin.value || undefined,
        amount_max: amountMax.value || undefined,
        due_from: dueFrom.value || undefined,
        due_to: dueTo.value || undefined,
        page: event.page + 1,
        per_page: event.rows,
    }, { preserveState: true, replace: true })
}

// Restaura scroll ao voltar do detalhe
onMounted(() => {
    const saved = sessionStorage.getItem('payables_scroll')
    if (saved) {
        setTimeout(() => {
            const main = document.querySelector('main.overflow-y-auto')
            if (main) main.scrollTo(0, parseInt(saved))
        }, 100)
        sessionStorage.removeItem('payables_scroll')
    }
})

function goShow(id) {
    // Salva scroll position do main container antes de sair
    const main = document.querySelector('main.overflow-y-auto')
    if (main) sessionStorage.setItem('payables_scroll', main.scrollTop.toString())
    router.visit(`/financeiro/contas-pagar/${id}`)
}

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}

const statusSeverity = {
    pendente: 'warn',
    em_preparacao: 'info',
    aguardando_aprovacao: 'warn',
    aprovado: 'success',
    reprovado: 'danger',
    pago: 'success',
}

// Cards de resumo
const totalPendente = computed(() => props.totals?.pendente?.total || 0)
const totalAguardando = computed(() => props.totals?.aguardando_aprovacao?.total || 0)
const totalAprovado = computed(() => props.totals?.aprovado?.total || 0)
const countPendente = computed(() => props.totals?.pendente?.count || 0)
const countAguardando = computed(() => props.totals?.aguardando_aprovacao?.count || 0)
const countAprovado = computed(() => props.totals?.aprovado?.count || 0)
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Contas a Pagar</h1>
                <p class="text-sm text-gray-500 mt-1">Gerencie títulos, anexe documentos e envie para aprovação.</p>
            </div>

            <!-- Tabs de status -->
            <div class="flex flex-wrap gap-2 mb-5">
                <button
                    v-for="s in statusList"
                    :key="s.value"
                    @click="status = s.value"
                    :class="[
                        'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                        status === s.value
                            ? 'bg-blue-600 text-white shadow-sm'
                            : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'
                    ]"
                >
                    {{ s.label }}
                    <span v-if="totals?.[s.value]" class="ml-1.5 text-xs opacity-75">({{ totals[s.value]?.count || 0 }})</span>
                </button>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <InputText v-model="search" placeholder="Buscar fornecedor, título..." class="w-56" />
                <Select v-model="branchId" :options="branchList" option-label="label" option-value="value" placeholder="Filial" class="w-44" />
                <InputText v-model="amountMin" placeholder="Valor mín" class="w-28" @change="applyFilters" />
                <InputText v-model="amountMax" placeholder="Valor máx" class="w-28" @change="applyFilters" />
                <InputText v-model="dueFrom" type="date" class="w-34" @change="applyFilters" />
                <InputText v-model="dueTo" type="date" class="w-34" @change="applyFilters" />
                <button v-if="hasActiveFilters" @click="clearFilters" class="text-xs text-red-600 hover:underline cursor-pointer ml-1">Limpar</button>
            </div>

            <!-- Tabela -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <DataTable :value="payables.data" striped-rows @row-click="(e) => goShow(e.data.id)" class="cursor-pointer"
                    :lazy="true" :paginator="true" :rows="payables.per_page" :total-records="payables.total"
                    :first="(payables.current_page - 1) * payables.per_page"
                    @page="onPage"
                    paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
                    :rows-per-page-options="[20, 50, 100]"
                >
                    <Column field="title_number" header="Nº" style="width: 90px; white-space: nowrap" />
                    <Column field="supplier_name" header="Fornecedor" style="min-width: 200px" />
                    <Column field="amount" header="Valor" style="width: 140px; white-space: nowrap">
                        <template #body="{ data }">
                            <span class="font-semibold">{{ formatMoney(data.amount) }}</span>
                        </template>
                    </Column>
                    <Column field="due_date" header="Vencimento" style="width: 110px; white-space: nowrap">
                        <template #body="{ data }">
                            {{ formatDate(data.due_date) }}
                        </template>
                    </Column>
                    <Column header="Filial" style="width: 180px">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600 truncate block max-w-[160px]" :title="data.branch?.name">{{ data.branch?.name || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="status" header="Status" style="width: 150px; white-space: nowrap">
                        <template #body="{ data }">
                            <Tag :value="statusOptions[data.status]" :severity="statusSeverity[data.status]" />
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center py-8 text-gray-500">Nenhum título encontrado.</div>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
