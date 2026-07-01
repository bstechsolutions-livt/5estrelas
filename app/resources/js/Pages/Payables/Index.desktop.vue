<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
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

const STORAGE_KEY = 'payables_filters'

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || 'pendente')
const branchId = ref(props.filters?.branch_id || null)
const amountMin = ref(props.filters?.amount_min ? Number(props.filters.amount_min) : null)
const amountMax = ref(props.filters?.amount_max ? Number(props.filters.amount_max) : null)
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

// Tab de status: aplica na hora (sem opção "ver todos")
function selectStatus(s) {
    if (status.value === s) return
    status.value = s
    applyFilters()
}

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

// Restaura scroll ao voltar do detalhe + filtros do cache em visita "limpa"
onMounted(() => {
    // Sem query string na URL = chegou pelo menu. Restaura último filtro usado.
    if (!window.location.search) {
        const cached = localStorage.getItem(STORAGE_KEY)
        if (cached) {
            try {
                const f = JSON.parse(cached)
                status.value = f.status || 'pendente'
                search.value = f.search || ''
                branchId.value = f.branch_id || null
                amountMin.value = f.amount_min ? Number(f.amount_min) : null
                amountMax.value = f.amount_max ? Number(f.amount_max) : null
                dueFrom.value = f.due_from || ''
                dueTo.value = f.due_to || ''
                const serverStatus = props.filters?.status || 'pendente'
                const differs = status.value !== serverStatus || f.search || f.branch_id || f.amount_min || f.amount_max || f.due_from || f.due_to
                if (differs) {
                    applyFilters()
                    return
                }
            } catch (e) { /* cache inválido, ignora */ }
        }
    }

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

// Seleção para borderô
const selected = ref([])

// Só pode selecionar títulos que estão livres pra agrupar
const selectableStatuses = ['pendente', 'em_preparacao', 'reprovado']
const canSelect = computed(() => selectableStatuses.includes(status.value))

function toggleSelect(id) {
    const i = selected.value.indexOf(id)
    if (i >= 0) selected.value.splice(i, 1)
    else selected.value.push(id)
}

function isSelected(id) {
    return selected.value.includes(id)
}

const createBorderoForm = ref({ description: '' })
function createBordero() {
    if (selected.value.length === 0) return
    router.post('/financeiro/borderos', {
        payable_ids: selected.value,
        description: createBorderoForm.value.description || undefined,
    }, {
        onSuccess: () => { selected.value = [] },
    })
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
                    @click="selectStatus(s.value)"
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
            <div class="bg-white rounded-xl border border-gray-100 p-4 mb-4">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Buscar</label>
                        <InputText v-model="search" placeholder="Fornecedor ou título" class="w-full" @keyup.enter="applyFilters" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Filial</label>
                        <Select v-model="branchId" :options="branchList" option-label="label" option-value="value" placeholder="Todas" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Valor mínimo</label>
                        <InputNumber v-model="amountMin" mode="currency" currency="BRL" locale="pt-BR" placeholder="R$ 0,00" class="w-full" :input-class="'w-full'" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Valor máximo</label>
                        <InputNumber v-model="amountMax" mode="currency" currency="BRL" locale="pt-BR" placeholder="R$ 0,00" class="w-full" :input-class="'w-full'" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Vencimento de</label>
                        <InputText v-model="dueFrom" type="date" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Vencimento até</label>
                        <InputText v-model="dueTo" type="date" class="w-full" />
                    </div>
                </div>
                <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100">
                    <span v-if="hasActiveFilters" class="text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded">
                        {{ activeFilterCount }} filtro(s) ativo(s)
                    </span>
                    <span v-else class="text-xs text-gray-400">Nenhum filtro aplicado</span>
                    <div class="flex gap-2">
                        <Button label="Limpar" severity="secondary" outlined size="small" @click="clearFilters" :disabled="!hasActiveFilters" />
                        <Button label="Filtrar" icon="pi pi-search" size="small" @click="applyFilters" />
                    </div>
                </div>
            </div>

            <!-- Barra de seleção pra borderô -->
            <div v-if="canSelect && selected.length > 0" class="bg-blue-600 text-white rounded-xl p-3 mb-4 flex items-center justify-between">
                <span class="text-sm font-medium">{{ selected.length }} título(s) selecionado(s)</span>
                <div class="flex items-center gap-2">
                    <InputText v-model="createBorderoForm.description" placeholder="Descrição do borderô (opcional)" class="w-72" />
                    <Button label="Criar Borderô" icon="pi pi-list-check" severity="contrast" size="small" @click="createBordero" />
                    <Button icon="pi pi-times" severity="contrast" text size="small" @click="selected = []" />
                </div>
            </div>

            <!-- Tabela -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <DataTable :value="payables.data" striped-rows class="cursor-pointer"
                    :lazy="true" :paginator="true" :rows="payables.per_page" :total-records="payables.total"
                    :first="(payables.current_page - 1) * payables.per_page"
                    @page="onPage"
                    paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
                    :rows-per-page-options="[20, 50, 100]"
                >
                    <Column v-if="canSelect" header="" style="width: 48px">
                        <template #body="{ data }">
                            <input type="checkbox" :checked="isSelected(data.id)" @click.stop="toggleSelect(data.id)" class="w-4 h-4 cursor-pointer" />
                        </template>
                    </Column>
                    <Column field="title_number" header="Nº" style="width: 90px; white-space: nowrap">
                        <template #body="{ data }">
                            <span @click="goShow(data.id)">{{ data.title_number }}</span>
                        </template>
                    </Column>
                    <Column header="Empresa" style="min-width: 140px" dusk="col-empresa">
                        <template #body="{ data }">
                            <span class="text-sm text-gray-700 truncate block max-w-[160px]" :title="data.empresa_nome" @click="goShow(data.id)">{{ data.empresa_nome || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="supplier_name" header="Fornecedor" style="min-width: 200px">
                        <template #body="{ data }">
                            <span @click="goShow(data.id)">{{ data.supplier_name }}</span>
                        </template>
                    </Column>
                    <Column field="description" header="Descrição" style="min-width: 200px">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600 truncate block max-w-[220px]" :title="data.description" @click="goShow(data.id)">{{ data.description || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="amount" header="Valor" style="width: 140px; white-space: nowrap">
                        <template #body="{ data }">
                            <span class="font-semibold" @click="goShow(data.id)">{{ formatMoney(data.amount) }}</span>
                        </template>
                    </Column>
                    <Column field="due_date" header="Vencimento" style="width: 110px; white-space: nowrap">
                        <template #body="{ data }">
                            <span @click="goShow(data.id)">{{ formatDate(data.due_date) }}</span>
                        </template>
                    </Column>
                    <Column header="Filial" style="width: 180px">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600 truncate block max-w-[160px]" :title="data.branch?.name" @click="goShow(data.id)">{{ data.branch?.name || '—' }}</span>
                        </template>
                    </Column>
                    <Column header="Borderô" style="width: 110px">
                        <template #body="{ data }">
                            <button v-if="data.bordero" @click.stop="router.visit(`/financeiro/borderos/${data.bordero.id}`)"
                                class="text-xs font-medium text-blue-600 bg-blue-50 px-2 py-0.5 rounded hover:bg-blue-100 cursor-pointer">
                                {{ data.bordero.number }}
                            </button>
                            <span v-else class="text-xs text-gray-300" @click="goShow(data.id)">—</span>
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
