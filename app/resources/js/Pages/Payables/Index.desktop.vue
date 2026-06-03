<script setup>
import { ref, watch, computed } from 'vue'
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
const status = ref(props.filters?.status || null)
const branchId = ref(props.filters?.branch_id || null)

const statusList = computed(() => [
    { label: 'Todos os status', value: null },
    ...Object.entries(props.statusOptions).map(([k, v]) => ({ label: v, value: k })),
])

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
    }, { preserveState: true, replace: true })
}

watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilters, 300) })
watch(status, applyFilters)
watch(branchId, applyFilters)

function goShow(id) { router.visit(`/financeiro/contas-pagar/${id}`) }

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

            <!-- Cards resumo -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Pendentes</p>
                    <p class="text-xl font-bold text-amber-600 mt-1">{{ formatMoney(totalPendente) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ countPendente }} título(s)</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Aguardando Aprovação</p>
                    <p class="text-xl font-bold text-blue-600 mt-1">{{ formatMoney(totalAguardando) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ countAguardando }} título(s)</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Aprovados</p>
                    <p class="text-xl font-bold text-green-600 mt-1">{{ formatMoney(totalAprovado) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ countAprovado }} título(s)</p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap gap-3 mb-4">
                <InputText v-model="search" placeholder="Buscar fornecedor, título..." class="w-64" />
                <Select v-model="status" :options="statusList" option-label="label" option-value="value" class="w-48" />
                <Select v-model="branchId" :options="branchList" option-label="label" option-value="value" class="w-52" />
            </div>

            <!-- Tabela -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <DataTable :value="payables.data" striped-rows @row-click="(e) => goShow(e.data.id)" class="cursor-pointer">
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
