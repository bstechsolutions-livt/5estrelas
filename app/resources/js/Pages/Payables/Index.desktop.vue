<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAuth } from '@/composables/useAuth'
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
    empresas: Array,
    departments: Array,
    branches: Array,
    statusOptions: Object,
    canChangeDepartmentFilter: { type: Boolean, default: true },
    lockedDepartment: { type: Object, default: null },
    canManageClassification: { type: Boolean, default: false },
})

const { can } = useAuth()
const canBorderos = computed(() => can('financeiro.borderos.visualizar'))

const STORAGE_KEY = 'payables_filters'

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || 'pendente')
const codemp = ref(props.filters?.codemp ? Number(props.filters.codemp) : null)
const departmentId = ref(
    props.canChangeDepartmentFilter
        ? (props.filters?.department_id ? Number(props.filters.department_id) : null)
        : (props.lockedDepartment?.id ?? null),
)
const amountMin = ref(props.filters?.amount_min ? Number(props.filters.amount_min) : null)
const amountMax = ref(props.filters?.amount_max ? Number(props.filters.amount_max) : null)
const dueFrom = ref(props.filters?.due_from || '')
const dueTo = ref(props.filters?.due_to || '')

const statusList = [
    { label: 'Pendentes', value: 'pendente', color: 'amber' },
    { label: 'Em Preparação', value: 'em_preparacao', color: 'blue' },
    { label: 'Aguardando Aprovação', value: 'aguardando_aprovacao', color: 'orange' },
    { label: 'Aprovados', value: 'aprovado', color: 'green' },
    { label: 'Pagos', value: 'pago', color: 'emerald' },
]

const empresaList = computed(() => [
    { label: 'Todas as empresas', value: null },
    ...(props.empresas || []).map(e => ({ label: e.label, value: e.value })),
])

const departmentList = computed(() => [
    { label: 'Todos os departamentos', value: null },
    ...(props.departments || []).map(d => ({ label: d.name, value: d.id })),
])

let timer = null
function currentFilters() {
    return {
        search: search.value || undefined,
        status: status.value || undefined,
        codemp: codemp.value || undefined,
        department_id: departmentId.value || undefined,
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
    const deptActive = props.canChangeDepartmentFilter && departmentId.value
    return !!(search.value || codemp.value || deptActive || amountMin.value || amountMax.value || dueFrom.value || dueTo.value)
})

const activeFilterCount = computed(() => {
    let c = 0
    if (search.value) c++
    if (codemp.value) c++
    if (props.canChangeDepartmentFilter && departmentId.value) c++
    if (amountMin.value) c++
    if (amountMax.value) c++
    if (dueFrom.value) c++
    if (dueTo.value) c++
    return c
})

function clearFilters() {
    search.value = ''
    codemp.value = null
    if (props.canChangeDepartmentFilter) {
        departmentId.value = null
    }
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
        codemp: codemp.value || undefined,
        department_id: departmentId.value || undefined,
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
    if (status.value === 'reprovado') {
        status.value = 'pendente'
        applyFilters()
        return
    }

    // Sem query string na URL = chegou pelo menu. Restaura último filtro usado.
    if (!window.location.search) {
        const cached = localStorage.getItem(STORAGE_KEY)
        if (cached) {
            try {
                const f = JSON.parse(cached)
                status.value = f.status === 'reprovado' ? 'pendente' : (f.status || 'pendente')
                search.value = f.search || ''
                codemp.value = f.codemp ? Number(f.codemp) : null
                if (props.canChangeDepartmentFilter) {
                    departmentId.value = f.department_id ? Number(f.department_id) : null
                }
                amountMin.value = f.amount_min ? Number(f.amount_min) : null
                amountMax.value = f.amount_max ? Number(f.amount_max) : null
                dueFrom.value = f.due_from || ''
                dueTo.value = f.due_to || ''
                const serverStatus = props.filters?.status || 'pendente'
                const differs = status.value !== serverStatus || f.search || f.codemp
                    || (props.canChangeDepartmentFilter && f.department_id)
                    || f.amount_min || f.amount_max || f.due_from || f.due_to
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
const canSelectBordero = computed(() => canSelect.value && canBorderos.value)

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

function wasRejectedBack(payable) {
    return payable.status === 'pendente' && !!payable.rejection_reason
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
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Buscar</label>
                        <InputText v-model="search" placeholder="Fornecedor ou título" class="w-full" @keyup.enter="applyFilters" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Empresa</label>
                        <Select v-model="codemp" :options="empresaList" option-label="label" option-value="value" placeholder="Todas" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Departamento</label>
                        <Select
                            v-if="canChangeDepartmentFilter"
                            v-model="departmentId"
                            :options="departmentList"
                            option-label="label"
                            option-value="value"
                            placeholder="Todos"
                            class="w-full"
                            @change="applyFilters"
                        />
                        <div v-else class="h-[38px] px-3 flex items-center rounded-md border border-gray-200 bg-gray-50 text-sm text-gray-700">
                            {{ lockedDepartment?.name || 'Sem departamento vinculado' }}
                        </div>
                        <p v-if="!canChangeDepartmentFilter" class="text-[11px] text-gray-400 mt-1">Exibindo apenas títulos do seu departamento.</p>
                        <a
                            v-if="canManageClassification"
                            href="/financeiro/contas-pagar/classificacao-departamentos"
                            class="inline-block text-[11px] text-blue-600 hover:underline mt-1"
                        >
                            Configurar regras de classificação →
                        </a>
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
                <div class="flex flex-wrap items-center justify-between gap-3 mt-4 pt-3 border-t border-gray-100">
                    <div class="flex items-center gap-2 min-h-[32px]">
                        <span v-if="hasActiveFilters" class="text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded">
                            {{ activeFilterCount }} filtro(s) ativo(s)
                        </span>
                        <span v-else class="text-xs text-gray-400">Nenhum filtro aplicado</span>
                    </div>
                    <div class="flex gap-2 w-full sm:w-auto">
                        <Button label="Limpar" severity="secondary" outlined size="small" class="flex-1 sm:flex-none" @click="clearFilters" :disabled="!hasActiveFilters" />
                        <Button label="Filtrar" icon="pi pi-search" size="small" class="flex-1 sm:flex-none" @click="applyFilters" />
                    </div>
                </div>
            </div>

            <!-- Barra de seleção pra borderô -->
            <div v-if="canSelectBordero && selected.length > 0" class="bg-blue-600 text-white rounded-xl p-3 mb-4 flex items-center justify-between">
                <span class="text-sm font-medium">{{ selected.length }} título(s) selecionado(s)</span>
                <div class="flex items-center gap-2">
                    <InputText v-model="createBorderoForm.description" placeholder="Descrição do borderô (opcional)" class="w-72" />
                    <Button label="Criar Borderô" icon="pi pi-list-check" severity="contrast" size="small" @click="createBordero" />
                    <Button icon="pi pi-times" severity="contrast" text size="small" @click="selected = []" />
                </div>
            </div>

            <!-- Tabela -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 payables-table overflow-hidden">
                <DataTable :value="payables.data" striped-rows size="small" class="cursor-pointer w-full"
                    table-style="table-layout: fixed; width: 100%"
                    :lazy="true" :paginator="true" :rows="payables.per_page" :total-records="payables.total"
                    :first="(payables.current_page - 1) * payables.per_page"
                    @page="onPage"
                    paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
                    :rows-per-page-options="[20, 50, 100]"
                >
                    <Column v-if="canSelectBordero" header="" style="width: 2.5rem">
                        <template #body="{ data }">
                            <input type="checkbox" :checked="isSelected(data.id)" @click.stop="toggleSelect(data.id)" class="w-4 h-4 cursor-pointer" />
                        </template>
                    </Column>
                    <Column field="title_number" header="Nº" style="width: 7%">
                        <template #body="{ data }">
                            <div class="flex flex-col items-start gap-0.5 py-0.5 min-w-0" @click="goShow(data.id)">
                                <span class="text-xs font-medium whitespace-nowrap leading-none" :title="data.title_number">{{ data.title_number }}</span>
                                <Tag
                                    v-if="wasRejectedBack(data)"
                                    value="Recusado"
                                    severity="danger"
                                    class="!text-[9px] !px-1.5 !py-0 leading-tight"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column header="Empresa" style="width: 8%" dusk="col-empresa">
                        <template #body="{ data }">
                            <span class="cell-truncate text-xs text-gray-700" :title="data.empresa_nome" @click="goShow(data.id)">{{ data.empresa_nome || '—' }}</span>
                        </template>
                    </Column>
                    <Column header="Depto" style="width: 8%" dusk="col-departamento">
                        <template #body="{ data }">
                            <span class="cell-truncate text-xs text-gray-600" :title="data.department_nome" @click="goShow(data.id)">{{ data.department_nome || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="supplier_name" header="Fornecedor" :style="{ width: status === 'pendente' ? '22%' : '18%' }">
                        <template #body="{ data }">
                            <span class="cell-truncate text-xs" :title="data.supplier_name" @click="goShow(data.id)">{{ data.supplier_name }}</span>
                        </template>
                    </Column>
                    <Column field="description" header="Descrição" :style="{ width: status === 'pendente' ? '22%' : '18%' }">
                        <template #body="{ data }">
                            <span class="cell-truncate text-xs text-gray-600" :title="data.description" @click="goShow(data.id)">{{ data.description || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="amount" header="Valor" style="width: 10%">
                        <template #body="{ data }">
                            <span class="text-xs font-semibold whitespace-nowrap" @click="goShow(data.id)">{{ formatMoney(data.amount) }}</span>
                        </template>
                    </Column>
                    <Column field="due_date" header="Vencimento" style="width: 8%">
                        <template #body="{ data }">
                            <span class="text-xs whitespace-nowrap" @click="goShow(data.id)">{{ formatDate(data.due_date) }}</span>
                        </template>
                    </Column>
                    <Column v-if="status !== 'pendente' && canBorderos" header="Borderô" style="width: 7%">
                        <template #body="{ data }">
                            <button v-if="data.bordero" @click.stop="router.visit(`/financeiro/borderos/${data.bordero.id}`)"
                                class="text-[10px] font-medium text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded hover:bg-blue-100 cursor-pointer whitespace-nowrap max-w-full truncate">
                                {{ data.bordero.number }}
                            </button>
                            <span v-else class="text-xs text-gray-300" @click="goShow(data.id)">—</span>
                        </template>
                    </Column>
                    <Column field="status" header="Status" :style="{ width: status === 'pendente' ? '9%' : '8%' }">
                        <template #body="{ data }">
                            <Tag :value="statusOptions[data.status]" :severity="statusSeverity[data.status]" class="!text-[10px] whitespace-nowrap" />
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

<style scoped>
.payables-table :deep(.p-datatable-wrapper) {
    overflow-x: hidden;
}
.payables-table :deep(.p-datatable-tbody > tr > td),
.payables-table :deep(.p-datatable-thead > tr > th) {
    font-size: 0.8125rem;
    overflow: hidden;
}
.payables-table :deep(.p-datatable-tbody .p-tag) {
    white-space: nowrap;
}
.cell-truncate {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
    min-width: 0;
}
</style>
