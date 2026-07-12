<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import BranchAccessBlocked from '@/Components/Financeiro/BranchAccessBlocked.vue'
import { formatApiDate } from '@/utils/apiDate'

const props = defineProps({
    receivables: Object,
    totals: Object,
    filters: Object,
    empresas: Array,
    statusOptions: Object,
    lockedBranches: { type: Array, default: () => [] },
    noBranchAccess: { type: Boolean, default: false },
})

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || '')
const codemp = ref(props.filters?.codemp ? Number(props.filters.codemp) : null)
const dueFrom = ref(props.filters?.due_from || '')
const dueTo = ref(props.filters?.due_to || '')

const empresaList = computed(() => [
    { label: 'Todas as empresas', value: null },
    ...(props.empresas || []).map(e => ({ label: e.label, value: e.value })),
])

const statusList = computed(() => [
    { label: 'Em aberto (padrão)', value: '' },
    ...Object.entries(props.statusOptions || {}).map(([value, label]) => ({ label, value })),
])

function applyFilters() {
    router.get('/financeiro/contas-receber', {
        search: search.value || undefined,
        status: status.value || undefined,
        codemp: codemp.value || undefined,
        due_from: dueFrom.value || undefined,
        due_to: dueTo.value || undefined,
    }, { preserveState: true, replace: true })
}

function formatMoney(v) {
    if (v == null) return '—'
    return Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function openReceivable(id) {
    router.visit(`/financeiro/contas-receber/${id}`)
}
</script>

<template>
    <AppLayout title="Contas a Receber">
        <div class="p-4 md:p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-surface-900">Contas a Receber</h1>
                    <p class="text-sm text-surface-500">Espelho read-only da Senior — sem baixa ou alteração no Hub.</p>
                </div>
                <Tag value="Senior ERP" severity="info" icon="pi pi-cloud" />
            </div>

            <BranchAccessBlocked v-if="noBranchAccess" :locked-branches="lockedBranches" />

            <template v-else>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 bg-surface-0 border border-surface-200 rounded-xl p-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-surface-500">Busca</label>
                        <InputText v-model="search" placeholder="Cliente, título, descrição" @keyup.enter="applyFilters" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-surface-500">Situação (Senior)</label>
                        <Select v-model="status" :options="statusList" option-label="label" option-value="value" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-surface-500">Empresa</label>
                        <Select v-model="codemp" :options="empresaList" option-label="label" option-value="value" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-surface-500">Vencimento de</label>
                        <DatePicker v-model="dueFrom" date-format="yy-mm-dd" show-icon fluid @update:model-value="v => dueFrom = formatApiDate(v)" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-surface-500">Vencimento até</label>
                        <DatePicker v-model="dueTo" date-format="yy-mm-dd" show-icon fluid @update:model-value="v => dueTo = formatApiDate(v)" />
                    </div>
                    <div class="lg:col-span-5 flex justify-end">
                        <Button label="Filtrar" icon="pi pi-search" @click="applyFilters" />
                    </div>
                </div>

                <DataTable
                    :value="receivables.data"
                    paginator
                    lazy
                    :rows="receivables.per_page || 20"
                    :total-records="receivables.total || 0"
                    :first="((receivables.current_page || 1) - 1) * (receivables.per_page || 20)"
                    data-key="id"
                    row-hover
                    class="text-sm"
                    @row-click="e => openReceivable(e.data.id)"
                >
                    <Column field="title_number" header="Título" />
                    <Column field="customer_name" header="Cliente" />
                    <Column header="Empresa">
                        <template #body="{ data }">{{ data.empresa_nome || data.codemp || '—' }}</template>
                    </Column>
                    <Column header="Vencimento">
                        <template #body="{ data }">{{ data.due_date ? new Date(data.due_date + 'T12:00:00').toLocaleDateString('pt-BR') : '—' }}</template>
                    </Column>
                    <Column header="Valor">
                        <template #body="{ data }">{{ formatMoney(data.amount) }}</template>
                    </Column>
                    <Column header="Saldo">
                        <template #body="{ data }">{{ formatMoney(data.open_amount) }}</template>
                    </Column>
                    <Column header="Situação">
                        <template #body="{ data }">
                            <Tag :value="data.situacao_label || data.senior_situacao_original || '—'" severity="secondary" />
                        </template>
                    </Column>
                    <Column header="">
                        <template #body="{ data }">
                            <Tag v-if="data.origem_senior" value="Senior" severity="info" class="text-xs" />
                        </template>
                    </Column>
                </DataTable>
            </template>
        </div>
    </AppLayout>
</template>
