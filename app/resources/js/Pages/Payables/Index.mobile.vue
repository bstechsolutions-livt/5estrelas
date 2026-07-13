<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import BottomSheet from '@/Components/Mobile/BottomSheet.vue'
import { useAuth } from '@/composables/useAuth'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import DatePicker from 'primevue/datepicker'
import BranchAccessBlocked from '@/Components/Financeiro/BranchAccessBlocked.vue'
import { DUE_DATE_PRESET_GROUPS, useDueDatePresets } from '@/composables/useDueDatePresets'
import { formatApiDate, parseApiDate, toApiDateString } from '@/utils/apiDate'
import { PAYABLE_SORT_GROUPS, sortQueryFromValue, sortValueFromQuery } from '@/composables/usePayableSort'

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
    priorityOptions: { type: Object, default: () => ({}) },
})

const { can } = useAuth()
const canBorderos = computed(() => can('financeiro.borderos.visualizar'))

const STORAGE_KEY = 'payables_filters_mobile'

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || 'pendente')
const filtersOpen = ref(false)
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

function selectDuePreset(key) {
    applyDuePreset(key)
    applyFilters()
}

const statusList = [
    { label: 'Pendentes', value: 'pendente' },
    { label: 'Preparação', value: 'em_preparacao' },
    { label: 'Aprovação', value: 'aguardando_aprovacao' },
    { label: 'Aprovados', value: 'aprovado' },
    { label: 'Pagos', value: 'pago' },
]

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

let timer = null
let restoring = false
watch(search, () => {
    if (restoring) return
    clearTimeout(timer)
    timer = setTimeout(applyFilters, 300)
})

// Tab de status: aplica na hora (sem opção "ver todos")
function selectStatus(s) {
    if (status.value === s) return
    status.value = s
    selected.value = []
    selectionMode.value = false
    applyFilters()
}

// Restaura último filtro usado em visita "limpa" (sem query string)
onMounted(() => {
    if (status.value === 'reprovado') {
        status.value = 'pendente'
        applyFilters()
        return
    }

    if (!window.location.search) {
        const cached = localStorage.getItem(STORAGE_KEY)
        if (cached) {
            try {
                const f = JSON.parse(cached)
                restoring = true
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
                setTimeout(() => { restoring = false }, 400)
                if (differs) applyFilters()
            } catch (e) { restoring = false }
        }
    }
})

function applyAndClose() {
    applyFilters()
    filtersOpen.value = false
}

function clearFilters() {
    search.value = ''
    codemp.value = null
    filial.value = null
    if (props.canChangeDepartmentFilter) {
        departmentId.value = null
    }
    amountMin.value = null
    amountMax.value = null
    paymentPriority.value = null
    sortValue.value = 'default'
    dueFrom.value = ''
    dueTo.value = ''
    clearDuePreset()
    localStorage.removeItem(STORAGE_KEY)
    applyFilters()
    filtersOpen.value = false
}

const activeFilterCount = computed(() => {
    let c = 0
    if (codemp.value) c++
    if (props.canChangeDepartmentFilter && departmentId.value) c++
    if (amountMin.value) c++
    if (amountMax.value) c++
    if (paymentPriority.value) c++
    if (dueFrom.value) c++
    if (dueTo.value) c++
    return c
})

function goShow(id) {
    sessionStorage.setItem('payables_scroll_mobile', document.querySelector('.mobile-main')?.scrollTop?.toString() || '0')
    router.visit(`/financeiro/contas-pagar/${id}`)
}

// Seleção pra criar borderô (mobile)
const selectableStatuses = ['pendente', 'em_preparacao']
const canSelect = computed(() => selectableStatuses.includes(status.value))
const canSelectBordero = computed(() => canSelect.value && canBorderos.value)
const selectionMode = ref(false)
const selected = ref([])
const createSheetOpen = ref(false)
const borderoDescription = ref('')

function toggleSelectionMode() {
    selectionMode.value = !selectionMode.value
    if (!selectionMode.value) selected.value = []
}
function onCardTap(p) {
    if (selectionMode.value && canSelect.value) {
        const i = selected.value.indexOf(p.id)
        if (i >= 0) selected.value.splice(i, 1)
        else selected.value.push(p.id)
    } else {
        goShow(p.id)
    }
}
function isSelected(id) {
    return selected.value.includes(id)
}
function createBordero() {
    if (selected.value.length === 0) return
    router.post('/financeiro/borderos', {
        payable_ids: selected.value,
        description: borderoDescription.value || undefined,
    }, {
        onSuccess: () => {
            selected.value = []
            selectionMode.value = false
            createSheetOpen.value = false
            borderoDescription.value = ''
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

const statusSeverity = { pendente: 'warn', em_preparacao: 'info', aguardando_aprovacao: 'warn', aprovado: 'success', reprovado: 'danger', pago: 'success' }

const currentTotal = computed(() => {
    const t = props.totals?.[status.value]
    return { count: t?.count || 0, total: t?.total || 0 }
})
</script>

<template>
    <AppLayoutMobile title="Contas a Pagar">
        <div v-if="noBranchAccess" class="px-4 pt-3">
            <BranchAccessBlocked />
        </div>

        <!-- Tabs de status (scroll horizontal) -->
        <div class="px-4 pt-3 pb-2 overflow-x-auto">
            <div class="flex gap-2 min-w-max">
                <button
                    v-for="s in statusList"
                    :key="s.value"
                    @click="selectStatus(s.value)"
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

        <!-- Períodos rápidos -->
        <div class="px-4 pb-2 space-y-2">
            <div
                v-for="group in DUE_DATE_PRESET_GROUPS"
                :key="group.id"
                :class="[
                    'rounded-lg border px-2 py-2',
                    group.id === 'vencidos' ? 'border-amber-200 bg-amber-50/70' : 'border-blue-100 bg-white',
                ]"
            >
                <p
                    :class="[
                        'text-[11px] font-semibold mb-1.5',
                        group.id === 'vencidos' ? 'text-amber-800' : 'text-blue-800',
                    ]"
                >
                    {{ group.label }}
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="preset in group.presets"
                        :key="preset.key"
                        type="button"
                        :class="[
                            'px-2 py-1 rounded-full text-[11px] font-medium border',
                            presetChipClass(preset.key, group.id),
                        ]"
                        @click="selectDuePreset(preset.key)"
                    >
                        {{ preset.label }}
                    </button>
                </div>
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
            <button v-if="canSelectBordero" @click="toggleSelectionMode"
                :class="['w-11 h-11 rounded-lg border flex items-center justify-center',
                    selectionMode ? 'border-blue-600 bg-blue-50 text-blue-600' : 'border-gray-300 text-gray-600']">
                <i class="pi pi-check-square"></i>
            </button>
        </div>

        <p v-if="selectionMode" class="px-4 pb-1 text-xs text-blue-600">Toque nos títulos para agrupar em um borderô.</p>

        <!-- Lista -->
        <div v-if="payables.data.length" class="px-4 space-y-2" :class="selectionMode && selected.length ? 'pb-28' : 'pb-20'">
            <button v-for="p in payables.data" :key="p.id" @click="onCardTap(p)"
                :class="['w-full bg-white rounded-xl border p-3 text-left active:bg-gray-50 transition-colors',
                    selectionMode && isSelected(p.id) ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200']">
                <p v-if="p.department_nome" class="text-[11px] text-gray-500 truncate mb-0.5" dusk="m-departamento">{{ p.department_nome }}</p>
                <p v-if="p.empresa_nome" class="text-[11px] font-semibold text-blue-600 truncate mb-0.5" dusk="m-empresa">{{ p.empresa_nome }}</p>
                <p v-if="p.filial_nome && p.filial_nome !== p.empresa_nome" class="text-[11px] text-gray-600 truncate mb-0.5" dusk="m-filial">{{ p.filial_nome }}</p>
                <div class="flex items-start justify-between gap-2 mb-1">
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <i v-if="selectionMode" :class="['pi', isSelected(p.id) ? 'pi-check-circle text-blue-600' : 'pi-circle text-gray-300']"></i>
                        <p class="text-sm font-medium text-gray-800 truncate flex-1">{{ p.supplier_name }}</p>
                    </div>
                    <Tag :value="statusOptions[p.status]" :severity="statusSeverity[p.status]" class="text-[10px]" />
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">{{ formatMoney(p.amount) }}</span>
                    <span class="text-xs text-gray-400">Venc: {{ formatDate(p.due_date) }}</span>
                </div>
                <p v-if="p.description" class="text-[11px] text-gray-500 truncate mt-1">{{ p.description }}</p>
                <p v-if="p.title_number" class="text-[11px] text-gray-500 mt-1 flex items-center gap-1.5">
                    <span class="whitespace-nowrap">{{ p.title_number }}</span>
                    <span v-if="p.empresa_nome" class="text-gray-400">· {{ p.empresa_nome }}</span>
                </p>
                <Tag
                    v-if="wasRejectedBack(p)"
                    value="Recusado"
                    severity="danger"
                    class="!text-[9px] !px-1.5 !py-0 mt-1"
                />
                <Tag
                    v-if="p.document_pair_alert"
                    :value="documentPairAlertTag(p.document_pair_alert)"
                    severity="warn"
                    class="!text-[9px] !px-1.5 !py-0 mt-1"
                    :title="p.document_pair_alert.message"
                />
                <Tag
                    v-if="p.origem_hub"
                    value="Hub"
                    severity="info"
                    class="!text-[9px] !px-1.5 !py-0 mt-1"
                    title="Criado na intranet"
                />
                <Tag
                    v-if="p.origem_senior"
                    value="Senior"
                    severity="secondary"
                    class="!text-[9px] !px-1.5 !py-0 mt-1"
                    title="Importado da Senior (ERP)"
                    dusk="origem-senior-badge"
                />
                <span v-if="status !== 'pendente' && p.bordero" class="inline-block mt-1 text-[10px] font-medium text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">
                    {{ p.bordero.number }}
                </span>
            </button>
        </div>
        <div v-else class="text-center py-12 text-gray-400 text-sm">Nenhum título encontrado.</div>

        <!-- Barra de ação fixa quando há seleção -->
        <div v-if="selectionMode && selected.length" class="fixed bottom-16 left-0 right-0 px-4 z-30">
            <div class="bg-blue-600 text-white rounded-xl shadow-lg p-3 flex items-center justify-between">
                <span class="text-sm font-medium">{{ selected.length }} selecionado(s)</span>
                <button @click="createSheetOpen = true" class="bg-white text-blue-600 font-semibold text-sm px-4 py-2 rounded-lg flex items-center gap-1">
                    <i class="pi pi-list-check text-xs"></i> Criar Borderô
                </button>
            </div>
        </div>

        <!-- Bottom sheet: confirmar criação de borderô -->
        <BottomSheet v-model="createSheetOpen" title="Novo borderô">
            <p class="text-sm text-gray-600 mb-3">Agrupando {{ selected.length }} título(s) em um borderô.</p>
            <label class="block text-xs font-medium text-gray-600 mb-1">Descrição (opcional)</label>
            <InputText v-model="borderoDescription" placeholder="Ex: Pagamentos fornecedores 06/2026" class="w-full" style="height: 44px" />
            <div class="flex gap-2 mt-4">
                <button @click="createSheetOpen = false" class="flex-1 py-3 rounded-lg border border-gray-300 text-gray-700 font-medium">
                    Cancelar
                </button>
                <button @click="createBordero" class="flex-1 py-3 rounded-lg text-white font-medium" :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }">
                    Criar Borderô
                </button>
            </div>
        </BottomSheet>

        <!-- Bottom sheet filtros avançados -->
        <BottomSheet v-model="filtersOpen" title="Filtros">
            <div class="space-y-5">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Escopo</p>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Empresa</label>
                            <Select v-model="codemp" :options="empresaList" option-label="label" option-value="value" class="w-full" :disabled="!!filial" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Filial</label>
                            <Select v-model="filial" :options="filialList" option-label="label" option-value="value" class="w-full" dusk="filter-filial" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Departamento</label>
                            <Select
                                v-if="canChangeDepartmentFilter"
                                v-model="departmentId"
                                :options="departmentList"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                            />
                            <div v-else class="h-11 px-3 flex items-center rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-700">
                                {{ lockedDepartment?.name || 'Sem departamento vinculado' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-gray-100">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Valores e ordem</p>
                    <div class="space-y-3">
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
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Prioridade</label>
                            <Select
                                v-model="paymentPriority"
                                :options="priorityList"
                                option-label="label"
                                option-value="value"
                                placeholder="Todas"
                                class="w-full"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Ordenar por</label>
                            <Select
                                v-model="sortValue"
                                :options="PAYABLE_SORT_GROUPS"
                                option-label="label"
                                option-value="value"
                                option-group-label="label"
                                option-group-children="items"
                                class="w-full"
                            />
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-dashed border-gray-200 rounded-lg bg-slate-50 px-2 py-3 space-y-3">
                    <p class="text-xs font-semibold text-gray-600">Período de vencimento</p>

                    <div
                        v-for="group in DUE_DATE_PRESET_GROUPS"
                        :key="group.id"
                        :class="[
                            'rounded-lg border px-2 py-2',
                            group.id === 'vencidos' ? 'border-amber-200 bg-amber-50/70' : 'border-blue-100 bg-white',
                        ]"
                    >
                        <p
                            :class="[
                                'text-[11px] font-semibold mb-1.5',
                                group.id === 'vencidos' ? 'text-amber-800' : 'text-blue-800',
                            ]"
                        >
                            {{ group.label }}
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="preset in group.presets"
                                :key="preset.key"
                                type="button"
                                :class="[
                                    'px-2 py-1 rounded-full text-[11px] font-medium border',
                                    presetChipClass(preset.key, group.id),
                                ]"
                                @click="selectDuePreset(preset.key)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Vencimento de</label>
                            <DatePicker v-model="dueFromDate" date-format="dd/mm/yy" placeholder="dd/mm/aaaa" show-icon class="w-full" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Vencimento até</label>
                            <DatePicker v-model="dueToDate" date-format="dd/mm/yy" placeholder="dd/mm/aaaa" show-icon class="w-full" />
                        </div>
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
