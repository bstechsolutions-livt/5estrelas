<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAuth } from '@/composables/useAuth'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import DatePicker from 'primevue/datepicker'
import BranchAccessBlocked from '@/Components/Financeiro/BranchAccessBlocked.vue'
import { DUE_DATE_PRESET_GROUPS, useDueDatePresets } from '@/composables/useDueDatePresets'
import { formatApiDate, parseApiDate, toApiDateString } from '@/utils/apiDate'
import {
    PAYABLE_SORT_GROUPS,
    sortQueryFromValue,
    sortValueFromQuery,
    sortValueFromTable,
    tableSortState,
} from '@/composables/usePayableSort'

const props = defineProps({
    payables: Object,
    totals: Object,
    filters: Object,
    empresas: Array,
    filiais: Array,
    departments: Array,
    branches: Array,
    statusOptions: Object,
    canChangeDepartmentFilter: { type: Boolean, default: true },
    lockedDepartment: { type: Object, default: null },
    lockedBranches: { type: Array, default: () => [] },
    noBranchAccess: { type: Boolean, default: false },
    canManageClassification: { type: Boolean, default: false },
    canManagePriority: { type: Boolean, default: false },
    priorityOptions: { type: Object, default: () => ({}) },
    canBypassApprovalDeadline: { type: Boolean, default: false },
    minDueDateForApproval: { type: String, default: null },
})

const { can } = useAuth()
const canBorderos = computed(() => can('financeiro.borderos.visualizar'))

const STORAGE_KEY = 'payables_filters'

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || 'pendente')
const codemp = ref(props.filters?.codemp ? Number(props.filters.codemp) : null)
const filial = ref(props.filters?.filial || null)
const departmentId = ref(
    props.canChangeDepartmentFilter
        ? (props.filters?.department_id ? Number(props.filters.department_id) : null)
        : (props.lockedDepartment?.id ?? null),
)
const amountMin = ref(props.filters?.amount_min ? Number(props.filters.amount_min) : null)
const amountMax = ref(props.filters?.amount_max ? Number(props.filters.amount_max) : null)
const paymentPriority = ref(props.filters?.payment_priority || null)
const dueFrom = ref(props.filters?.due_from || '')
const dueTo = ref(props.filters?.due_to || '')
const sortValue = ref(sortValueFromQuery(props.filters?.sort, props.filters?.dir))
const { duePreset, applyDuePreset, clearDuePreset, onDueDateManualChange, presetChipClass } = useDueDatePresets(dueFrom, dueTo)
const dueFromDate = computed({
    get: () => parseApiDate(dueFrom.value),
    set: (v) => {
        dueFrom.value = v ? toApiDateString(v) : ''
        onDueDateManualChange()
    },
})
const dueToDate = computed({
    get: () => parseApiDate(dueTo.value),
    set: (v) => {
        dueTo.value = v ? toApiDateString(v) : ''
        onDueDateManualChange()
    },
})

const tableSortField = computed(() => tableSortState(sortValue.value).field)
const tableSortOrder = computed(() => tableSortState(sortValue.value).order)
const advancedFiltersOpen = ref(false)

const hasAdvancedFilters = computed(() => {
    const deptActive = props.canChangeDepartmentFilter && departmentId.value
    return !!(
        search.value
        || codemp.value
        || filial.value
        || deptActive
        || amountMin.value
        || amountMax.value
        || paymentPriority.value
        || sortValue.value !== 'default'
    )
})

const advancedFilterCount = computed(() => {
    let c = 0
    if (search.value) c++
    if (codemp.value) c++
    if (filial.value) c++
    if (props.canChangeDepartmentFilter && departmentId.value) c++
    if (amountMin.value) c++
    if (amountMax.value) c++
    if (paymentPriority.value) c++
    if (sortValue.value !== 'default') c++
    return c
})

function selectDuePreset(key) {
    applyDuePreset(key)
    applyFilters()
}

const statusList = [
    { label: 'Pendentes', value: 'pendente', color: 'amber' },
    { label: 'Em Preparação', value: 'em_preparacao', color: 'blue' },
    { label: 'Em Aprovação', value: 'aguardando_aprovacao', color: 'orange' },
    { label: 'Aprovados', value: 'aprovado', color: 'green' },
    { label: 'Ag. Conciliação', value: 'aguardando_conciliacao', color: 'amber' },
    { label: 'Conciliados', value: 'conciliado', color: 'emerald' },
]

const statusTabHint = computed(() => {
    const hints = {
        pendente: 'Títulos que ainda não foram enviados para aprovação.',
        em_preparacao: 'Títulos em preparação antes do envio.',
        aguardando_aprovacao: 'Títulos em fluxo de aprovação — a coluna Etapa mostra em qual nível cada um está.',
        aprovado: 'Títulos aprovados aguardando pagamento.',
        aguardando_conciliacao: 'Pagos no banco, aguardando conciliação/baixa na Senior.',
        conciliado: 'Títulos já conciliados e baixados.',
    }
    return hints[status.value] || null
})

const empresaList = computed(() => [
    { label: 'Todas as empresas', value: null },
    ...(props.empresas || []).map(e => ({ label: e.label, value: e.value })),
])

const filialList = computed(() => [
    { label: 'Todas as filiais', value: null },
    ...(props.filiais || []).map(f => ({ label: f.label, value: f.value })),
])

const departmentList = computed(() => [
    { label: 'Todos os departamentos', value: null },
    ...(props.departments || []).map(d => ({ label: d.name, value: d.id })),
])

const priorityList = computed(() => [
    { label: 'Todas', value: null },
    { label: 'Urgente', value: 'urgente' },
    { label: 'Alta', value: 'alta' },
    { label: 'Normal', value: 'normal' },
    { label: 'Sem prioridade', value: 'sem' },
])

watch(filial, (value) => {
    if (value) {
        codemp.value = null
    }
})

watch(codemp, (value) => {
    if (value) {
        filial.value = null
    }
})

let timer = null
function currentFilters() {
    return {
        search: search.value || undefined,
        status: status.value || undefined,
        codemp: filial.value ? undefined : (codemp.value || undefined),
        filial: filial.value || undefined,
        department_id: departmentId.value || undefined,
        amount_min: amountMin.value || undefined,
        amount_max: amountMax.value || undefined,
        payment_priority: paymentPriority.value || undefined,
        due_from: dueFrom.value || undefined,
        due_to: dueTo.value || undefined,
        ...sortQueryFromValue(sortValue.value),
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
    return hasAdvancedFilters.value || !!(dueFrom.value || dueTo.value)
})

const activeFilterCount = computed(() => {
    let c = 0
    if (search.value) c++
    if (codemp.value) c++
    if (filial.value) c++
    if (props.canChangeDepartmentFilter && departmentId.value) c++
    if (amountMin.value) c++
    if (amountMax.value) c++
    if (paymentPriority.value) c++
    if (sortValue.value !== 'default') c++
    if (dueFrom.value) c++
    if (dueTo.value) c++
    return c
})

function clearFilters() {
    search.value = ''
    codemp.value = null
    filial.value = null
    if (props.canChangeDepartmentFilter) {
        departmentId.value = null
    }
    amountMin.value = ''
    amountMax.value = ''
    paymentPriority.value = null
    sortValue.value = 'default'
    dueFrom.value = ''
    dueTo.value = ''
    clearDuePreset()
    applyFilters()
}

function onPage(event) {
    router.get('/financeiro/contas-pagar', {
        ...currentFilters(),
        page: event.page + 1,
        per_page: event.rows,
    }, { preserveState: true, replace: true })
}

function onTableSort(event) {
    sortValue.value = sortValueFromTable(event.sortField, event.sortOrder)
    applyFilters()
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
                filial.value = f.filial || null
                if (props.canChangeDepartmentFilter) {
                    departmentId.value = f.department_id ? Number(f.department_id) : null
                }
                amountMin.value = f.amount_min ? Number(f.amount_min) : null
                amountMax.value = f.amount_max ? Number(f.amount_max) : null
                paymentPriority.value = f.payment_priority || null
                sortValue.value = sortValueFromQuery(f.sort, f.dir)
                dueFrom.value = f.due_from || ''
                dueTo.value = f.due_to || ''
                onDueDateManualChange()
                const serverStatus = props.filters?.status || 'pendente'
                const differs = status.value !== serverStatus || f.search || f.codemp || f.filial
                    || (props.canChangeDepartmentFilter && f.department_id)
                    || f.amount_min || f.amount_max || f.payment_priority || f.due_from || f.due_to
                    || (f.sort && f.sort !== 'default')
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

// Seleção e ações em lote
const selected = ref([])

const prepareStatuses = ['pendente', 'em_preparacao']
const canSelect = computed(() => [...prepareStatuses, 'aguardando_aprovacao'].includes(status.value))
const canSelectBordero = computed(() => prepareStatuses.includes(status.value) && canBorderos.value)
const canBatchSend = computed(() => prepareStatuses.includes(status.value))
const canBatchApprove = computed(() => status.value === 'aguardando_aprovacao')

const currentPageIds = computed(() => (props.payables?.data || []).map(p => p.id))
const allPageSelected = computed(() =>
    currentPageIds.value.length > 0 && currentPageIds.value.every(id => selected.value.includes(id))
)
const somePageSelected = computed(() =>
    currentPageIds.value.some(id => selected.value.includes(id)) && !allPageSelected.value
)

function toggleSelect(id) {
    const i = selected.value.indexOf(id)
    if (i >= 0) selected.value.splice(i, 1)
    else selected.value.push(id)
}

function toggleSelectAll() {
    if (allPageSelected.value) {
        selected.value = selected.value.filter(id => !currentPageIds.value.includes(id))
    } else {
        const toAdd = currentPageIds.value.filter(id => !selected.value.includes(id))
        selected.value.push(...toAdd)
    }
}

function isSelected(id) {
    return selected.value.includes(id)
}

function clearSelection() {
    selected.value = []
    borderoMode.value = false
    createBorderoForm.value.description = ''
}

watch(() => status.value, clearSelection)
watch(() => props.payables?.current_page, () => {
    selected.value = selected.value.filter(id => currentPageIds.value.includes(id))
})

const borderoMode = ref(false)
const createBorderoForm = ref({ description: '' })
const urgentBatchBypass = ref(false)

function startBordero() {
    if (selected.value.length === 0) return
    createBorderoForm.value.description = ''
    borderoMode.value = true
}

function cancelBordero() {
    borderoMode.value = false
    createBorderoForm.value.description = ''
}

function confirmBordero() {
    if (selected.value.length === 0) return
    router.post('/financeiro/borderos', {
        payable_ids: selected.value,
        description: createBorderoForm.value.description?.trim() || undefined,
    }, {
        onSuccess: () => {
            borderoMode.value = false
            clearSelection()
        },
    })
}

function batchSendForApproval() {
    if (selected.value.length === 0) return
    router.post('/financeiro/contas-pagar/lote/enviar-aprovacao', {
        payable_ids: selected.value,
        urgente: urgentBatchBypass.value && props.canBypassApprovalDeadline,
    }, { onSuccess: () => { clearSelection(); urgentBatchBypass.value = false } })
}

const showApproveDialog = ref(false)
const approveForm = useForm({
    payment_priority: 'normal',
    payment_sla_date: null,
    comment: '',
})

const prioritySelectOptions = computed(() =>
    Object.entries(props.priorityOptions || {}).map(([value, label]) => ({ value, label }))
)

function toYmd(d) {
    if (!d) return null
    const dt = d instanceof Date ? d : new Date(d)
    const y = dt.getFullYear()
    const m = String(dt.getMonth() + 1).padStart(2, '0')
    const day = String(dt.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
}

function openBatchApprove() {
    if (selected.value.length === 0) return
    approveForm.payment_priority = 'normal'
    approveForm.payment_sla_date = null
    approveForm.comment = ''
    showApproveDialog.value = true
}

function batchApprove() {
    const payload = {
        payable_ids: selected.value,
        comment: approveForm.comment || undefined,
    }
    if (props.canManagePriority) {
        payload.payment_priority = approveForm.payment_priority
        payload.payment_sla_date = approveForm.payment_sla_date ? toYmd(approveForm.payment_sla_date) : null
    }
    router.post('/financeiro/contas-pagar/lote/aprovar', payload, {
        preserveScroll: true,
        onSuccess: () => {
            showApproveDialog.value = false
            clearSelection()
        },
    })
}

const showPriorityDialog = ref(false)
const batchPriorityForm = useForm({
    payment_priority: 'normal',
    payment_sla_date: null,
})

function openPriorityDialog() {
    batchPriorityForm.payment_priority = 'normal'
    batchPriorityForm.payment_sla_date = null
    showPriorityDialog.value = true
}

function applyBatchPriority() {
    batchPriorityForm
        .transform((data) => ({
            payable_ids: selected.value,
            payment_priority: data.payment_priority,
            payment_sla_date: data.payment_sla_date ? toYmd(data.payment_sla_date) : null,
        }))
        .post('/financeiro/contas-pagar/lote/prioridade', {
            preserveScroll: true,
            onSuccess: () => {
                showPriorityDialog.value = false
                clearSelection()
            },
        })
}

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}

function formatDate(d) {
    return formatApiDate(d)
}

function wasRejectedBack(payable) {
    return payable.status === 'pendente' && !!payable.rejection_reason
}

function documentPairAlertTag(alert) {
    if (!alert) return null
    return alert.code === 'missing_nota' ? 'Falta NF' : 'Falta boleto'
}

const prioritySeverity = {
    normal: 'secondary',
    alta: 'warn',
    urgente: 'danger',
}

function workflowMomentTextClass(data) {
    const tone = data.workflow_moment_tone
    if (tone === 'danger') return 'text-red-700'
    if (tone === 'success') return 'text-green-700'
    if (tone === 'warn') return 'text-amber-800'
    if (tone === 'info') return 'text-blue-700'
    return 'text-gray-800'
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

            <BranchAccessBlocked v-if="noBranchAccess" />
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
            <p v-if="statusTabHint" class="text-xs text-gray-500 mb-4 -mt-3">{{ statusTabHint }}</p>

            <!-- Filtros -->
            <div class="bg-white rounded-xl border border-gray-100 p-4 mb-4 space-y-3">
                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Período de vencimento</p>

                    <div
                        v-for="group in DUE_DATE_PRESET_GROUPS"
                        :key="group.id"
                        :class="[
                            'rounded-lg border px-3 py-2.5',
                            group.id === 'vencidos' ? 'border-amber-200 bg-amber-50/60' : 'border-blue-100 bg-white/80',
                        ]"
                    >
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 mb-2">
                            <span
                                :class="[
                                    'text-xs font-semibold',
                                    group.id === 'vencidos' ? 'text-amber-800' : 'text-blue-800',
                                ]"
                            >
                                {{ group.label }}
                            </span>
                            <span class="text-[11px] text-gray-500">{{ group.hint }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="preset in group.presets"
                                :key="preset.key"
                                type="button"
                                :class="[
                                    'px-2.5 py-1 rounded-full text-xs font-medium transition-colors border',
                                    presetChipClass(preset.key, group.id),
                                ]"
                                @click="selectDuePreset(preset.key)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-800 transition-colors"
                        @click="advancedFiltersOpen = !advancedFiltersOpen"
                    >
                        <i :class="['pi text-xs', advancedFiltersOpen ? 'pi-chevron-up' : 'pi-chevron-down']" />
                        {{ advancedFiltersOpen ? 'Ocultar filtros' : 'Mais filtros' }}
                        <span
                            v-if="advancedFilterCount"
                            class="text-[10px] font-semibold bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full"
                        >
                            {{ advancedFilterCount }}
                        </span>
                    </button>
                    <div class="flex flex-wrap items-center gap-2">
                        <span v-if="hasActiveFilters" class="text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded">
                            {{ activeFilterCount }} filtro(s) ativo(s)
                        </span>
                        <span v-else class="text-xs text-gray-400">Nenhum filtro aplicado</span>
                        <Button label="Limpar" severity="secondary" outlined size="small" @click="clearFilters" :disabled="!hasActiveFilters" />
                        <Button v-if="advancedFiltersOpen" label="Filtrar" icon="pi pi-search" size="small" @click="applyFilters" />
                    </div>
                </div>

                <div v-show="advancedFiltersOpen" class="space-y-4 pt-3 border-t border-gray-100">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-3">Busca e escopo</p>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Buscar</label>
                            <InputText v-model="search" placeholder="Fornecedor, título ou descrição" class="w-full" @keyup.enter="applyFilters" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Empresa</label>
                            <Select v-model="codemp" :options="empresaList" option-label="label" option-value="value" placeholder="Todas" class="w-full" :disabled="!!filial" />
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Filial</label>
                            <Select v-model="filial" :options="filialList" option-label="label" option-value="value" placeholder="Todas" class="w-full" dusk="filter-filial" @change="applyFilters" />
                        </div>
                        <div class="md:col-span-3">
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
                        </div>
                    </div>
                    <div v-if="!canChangeDepartmentFilter || lockedBranches?.length" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-500">
                        <span v-if="!canChangeDepartmentFilter">Exibindo apenas títulos do seu departamento.</span>
                        <span v-if="lockedBranches?.length" class="text-amber-700" dusk="locked-branches-hint">
                            Filiais liberadas: {{ lockedBranches.map(b => b.name).join(', ') }}
                        </span>
                    </div>
                </div>

                <div class="pt-3 border-t border-gray-100">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-3">Valores, prioridade e ordem</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Valor mínimo</label>
                            <InputNumber v-model="amountMin" mode="currency" currency="BRL" locale="pt-BR" placeholder="R$ 0,00" class="w-full" :input-class="'w-full'" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Valor máximo</label>
                            <InputNumber v-model="amountMax" mode="currency" currency="BRL" locale="pt-BR" placeholder="R$ 0,00" class="w-full" :input-class="'w-full'" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Prioridade</label>
                            <Select
                                v-model="paymentPriority"
                                :options="priorityList"
                                option-label="label"
                                option-value="value"
                                placeholder="Todas"
                                class="w-full"
                                @change="applyFilters"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Ordenar por</label>
                            <Select
                                v-model="sortValue"
                                :options="PAYABLE_SORT_GROUPS"
                                option-label="label"
                                option-value="value"
                                option-group-label="label"
                                option-group-children="items"
                                class="w-full"
                                @change="applyFilters"
                            />
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-gray-100">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-3">Período personalizado</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-xl">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Vencimento de</label>
                            <DatePicker v-model="dueFromDate" date-format="dd/mm/yy" placeholder="dd/mm/aaaa" show-icon class="w-full" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Vencimento até</label>
                            <DatePicker v-model="dueToDate" date-format="dd/mm/yy" placeholder="dd/mm/aaaa" show-icon class="w-full" />
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <!-- Barra de ações em lote -->
            <div v-if="canSelect && selected.length > 0" class="batch-action-bar bg-blue-600 text-white rounded-xl p-3 mb-4 flex flex-wrap items-center justify-between gap-3">
                <template v-if="borderoMode">
                    <span class="text-sm font-medium">Criar borderô com {{ selected.length }} título(s)</span>
                    <div class="flex flex-wrap items-center gap-2 flex-1 justify-end">
                        <InputText
                            v-model="createBorderoForm.description"
                            placeholder="Descrição do borderô (opcional)"
                            class="w-64 batch-bar-input"
                            dusk="bordero-description-input"
                            @keyup.enter="confirmBordero"
                        />
                        <Button label="Confirmar borderô" icon="pi pi-check" size="small" class="batch-bar-btn-primary"
                            dusk="btn-bordero-confirm" @click="confirmBordero" />
                        <Button label="Cancelar" icon="pi pi-times" size="small" outlined class="batch-bar-btn-secondary"
                            dusk="btn-bordero-cancel" @click="cancelBordero" />
                    </div>
                </template>
                <template v-else>
                    <span class="text-sm font-medium">{{ selected.length }} título(s) selecionado(s)</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <label
                            v-if="canBatchSend && canBypassApprovalDeadline"
                            class="flex items-center gap-1.5 text-xs text-blue-100 cursor-pointer mr-1"
                        >
                            <input v-model="urgentBatchBypass" type="checkbox" class="rounded" dusk="urgent-batch-bypass" />
                            <span>Urgência (fora 72h)</span>
                        </label>
                        <Button v-if="canBatchSend" label="Enviar para aprovação" icon="pi pi-send" size="small" class="batch-bar-btn-primary"
                            dusk="btn-batch-send-approval" @click="batchSendForApproval" />
                        <Button v-if="canBatchApprove" label="Aprovar selecionados" icon="pi pi-check" severity="success" size="small"
                            dusk="btn-batch-approve" @click="openBatchApprove" />
                        <Button v-if="canSelectBordero" label="Criar borderô" icon="pi pi-list-check" size="small" class="batch-bar-btn-primary"
                            dusk="btn-batch-bordero" @click="startBordero" />
                        <Button v-if="canManagePriority" label="Definir prioridade" icon="pi pi-flag" size="small" class="batch-bar-btn-primary"
                            dusk="btn-batch-priority" @click="openPriorityDialog" />
                        <Button icon="pi pi-times" text size="small" class="batch-bar-btn-ghost" title="Limpar seleção" @click="clearSelection" />
                    </div>
                </template>
            </div>

            <!-- Tabela -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 payables-table overflow-hidden">
                <DataTable :value="payables.data" striped-rows size="small" class="cursor-pointer w-full"
                    table-style="table-layout: fixed; width: 100%"
                    :lazy="true" :paginator="true" :rows="payables.per_page" :total-records="payables.total"
                    :first="(payables.current_page - 1) * payables.per_page"
                    :sort-field="tableSortField"
                    :sort-order="tableSortOrder"
                    @page="onPage"
                    @sort="onTableSort"
                    paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
                    :rows-per-page-options="[20, 50, 100, 200, 500, 1000]"
                >
                    <Column v-if="canSelect" header="" style="width: 2.5rem">
                        <template #header>
                            <input
                                type="checkbox"
                                :checked="allPageSelected"
                                :indeterminate="somePageSelected"
                                class="w-4 h-4 cursor-pointer"
                                dusk="select-all-payables"
                                @click.stop="toggleSelectAll"
                            />
                        </template>
                        <template #body="{ data }">
                            <input type="checkbox" :checked="isSelected(data.id)" @click.stop="toggleSelect(data.id)" class="w-4 h-4 cursor-pointer" />
                        </template>
                    </Column>
                    <Column field="title_number" header="Nº" style="width: 7%" sortable>
                        <template #body="{ data }">
                            <div class="flex flex-col items-start gap-0.5 py-0.5 min-w-0" @click="goShow(data.id)">
                                <span class="text-xs font-medium whitespace-nowrap leading-none" :title="data.title_number">{{ data.title_number }}</span>
                                <Tag
                                    v-if="wasRejectedBack(data)"
                                    value="Recusado"
                                    severity="danger"
                                    class="!text-[9px] !px-1.5 !py-0 leading-tight"
                                />
                                <Tag
                                    v-if="data.document_pair_alert"
                                    :value="documentPairAlertTag(data.document_pair_alert)"
                                    severity="warn"
                                    class="!text-[9px] !px-1.5 !py-0 leading-tight"
                                    :title="data.document_pair_alert.message"
                                    dusk="doc-pair-alert"
                                />
                                <Tag
                                    v-if="data.origem_hub"
                                    value="Hub"
                                    severity="info"
                                    class="!text-[9px] !px-1.5 !py-0 leading-tight"
                                    title="Criado na intranet"
                                    dusk="origem-hub-badge"
                                />
                                <Tag
                                    v-if="data.origem_senior"
                                    value="Senior"
                                    severity="secondary"
                                    class="!text-[9px] !px-1.5 !py-0 leading-tight"
                                    title="Importado da Senior (ERP)"
                                    dusk="origem-senior-badge"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column field="department_nome" header="Depto" style="width: 9%" sortable dusk="col-departamento">
                        <template #body="{ data }">
                            <span class="cell-truncate text-xs text-gray-600" :title="data.department_nome" @click="goShow(data.id)">{{ data.department_nome || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="filial_nome" header="Filial" style="width: 10%" sortable dusk="col-filial">
                        <template #body="{ data }">
                            <span class="cell-truncate text-xs font-medium text-gray-800" :title="data.filial_label || data.filial_nome"
                                @click="goShow(data.id)">{{ data.filial_label || data.filial_nome || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="supplier_name" header="Fornecedor" :style="{ width: status === 'pendente' ? '20%' : '18%' }" sortable>
                        <template #body="{ data }">
                            <span class="cell-truncate text-xs" :title="data.supplier_display_name || data.supplier_name" @click="goShow(data.id)">{{ data.supplier_display_name || data.supplier_name }}</span>
                        </template>
                    </Column>
                    <Column field="description" header="Descrição" :style="{ width: status === 'pendente' ? '18%' : '16%' }" sortable>
                        <template #body="{ data }">
                            <span class="cell-truncate text-xs text-gray-600" :title="data.description" @click="goShow(data.id)">{{ data.description || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="amount" header="Valor" style="width: 10%" sortable>
                        <template #body="{ data }">
                            <span class="text-xs font-semibold whitespace-nowrap" @click="goShow(data.id)">{{ formatMoney(data.amount) }}</span>
                        </template>
                    </Column>
                    <Column field="due_date" header="Vencimento" style="width: 8%" sortable>
                        <template #body="{ data }">
                            <span class="text-xs whitespace-nowrap" @click="goShow(data.id)">{{ formatDate(data.due_date) }}</span>
                        </template>
                    </Column>
                    <Column v-if="status !== 'pendente'" field="payment_priority" header="Prioridade" style="width: 7%" sortable>
                        <template #body="{ data }">
                            <Tag
                                v-if="data.payment_priority"
                                :value="data.priority_label"
                                :severity="prioritySeverity[data.payment_priority] || 'secondary'"
                                class="!text-[10px] whitespace-nowrap"
                                @click="goShow(data.id)"
                            />
                            <span v-else class="text-xs text-gray-300" @click="goShow(data.id)">—</span>
                        </template>
                    </Column>
                    <Column v-if="!['pago', 'aguardando_conciliacao', 'conciliado'].includes(status)" header="Aprovador" style="width: 14%; min-width: 8.5rem" dusk="col-etapa">
                        <template #body="{ data }">
                            <div class="flex flex-col gap-0.5 min-w-0" @click="goShow(data.id)">
                                <span
                                    class="cell-truncate text-xs font-medium"
                                    :class="workflowMomentTextClass(data)"
                                    :title="data.workflow_moment"
                                >{{ data.workflow_moment }}</span>
                                <Tag
                                    v-if="data.workflow_moment_detail"
                                    :value="data.workflow_moment_detail"
                                    :severity="data.workflow_moment_tone || 'secondary'"
                                    class="approver-step-tag !text-[10px]"
                                    :title="data.workflow_moment_detail"
                                />
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center py-8 text-gray-500">Nenhum título encontrado.</div>
                    </template>
                </DataTable>
            </div>

            <Dialog v-model:visible="showPriorityDialog" header="Prioridade em lote" modal :style="{ width: '28rem' }">
                <p class="text-sm text-gray-600 mb-3">Definir prioridade em {{ selected.length }} título(s).</p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Prioridade</label>
                        <Select v-model="batchPriorityForm.payment_priority" :options="prioritySelectOptions" option-label="label" option-value="value" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">SLA (opcional)</label>
                        <DatePicker v-model="batchPriorityForm.payment_sla_date" date-format="dd/mm/yy" show-icon class="w-full" />
                    </div>
                </div>
                <template #footer>
                    <Button label="Cancelar" severity="secondary" text @click="showPriorityDialog = false" />
                    <Button label="Aplicar" icon="pi pi-check" :loading="batchPriorityForm.processing" dusk="batch-priority-confirm" @click="applyBatchPriority" />
                </template>
            </Dialog>

            <Dialog v-model:visible="showApproveDialog" header="Aprovar selecionados" modal :style="{ width: '28rem' }">
                <p class="text-sm text-gray-600 mb-3">Aprovar {{ selected.length }} título(s) na etapa atual.</p>
                <div class="space-y-3">
                    <template v-if="canManagePriority">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Prioridade de pagamento</label>
                            <Select v-model="approveForm.payment_priority" :options="prioritySelectOptions" option-label="label" option-value="value" class="w-full" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">SLA (opcional)</label>
                            <DatePicker v-model="approveForm.payment_sla_date" date-format="dd/mm/yy" show-icon class="w-full" />
                        </div>
                    </template>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Comentário (opcional)</label>
                        <InputText v-model="approveForm.comment" class="w-full" />
                    </div>
                </div>
                <template #footer>
                    <Button label="Cancelar" severity="secondary" text @click="showApproveDialog = false" />
                    <Button label="Aprovar" icon="pi pi-check" severity="success" dusk="batch-approve-confirm" @click="batchApprove" />
                </template>
            </Dialog>
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
.approver-step-tag {
    align-self: flex-start;
    max-width: 100%;
}
.approver-step-tag :deep(.p-tag-label) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
}
.batch-action-bar :deep(.batch-bar-btn-primary.p-button) {
    background: #fff;
    color: #1d4ed8;
    border-color: #fff;
}
.batch-action-bar :deep(.batch-bar-btn-primary.p-button:hover) {
    background: #eff6ff;
    color: #1e40af;
    border-color: #eff6ff;
}
.batch-action-bar :deep(.batch-bar-btn-secondary.p-button) {
    background: transparent;
    color: #fff;
    border-color: rgba(255, 255, 255, 0.95);
}
.batch-action-bar :deep(.batch-bar-btn-secondary.p-button:hover) {
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    border-color: #fff;
}
.batch-action-bar :deep(.batch-bar-btn-ghost.p-button) {
    color: #fff;
}
.batch-action-bar :deep(.batch-bar-btn-ghost.p-button:hover) {
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
}
.batch-action-bar :deep(.batch-bar-input) {
    background: #fff;
    color: #1f2937;
    border-color: #fff;
}
</style>
