<script setup>
import { ref, watch, computed } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import FileUpload from 'primevue/fileupload'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
    imports: Object,
    isConciliador: Boolean,
    bankAccounts: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    session: { type: Object, default: null },
    summary: { type: Object, default: null },
    periodLabel: { type: String, default: '' },
    pendingPayables: { type: Array, default: () => [] },
})

const toast = useToast()
const page = usePage()

watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        toast.add({ severity: 'success', summary: 'Sucesso', detail: flash.success, life: 5000 })
    }
    if (flash?.error) {
        toast.add({ severity: 'error', summary: 'Erro', detail: flash.error, life: 5000 })
    }
}, { deep: true })

function parseFilterDate(value) {
    if (!value) return new Date()
    const [y, m, d] = String(value).slice(0, 10).split('-').map(Number)
    return new Date(y, m - 1, d, 12, 0, 0)
}

const selectedDate = ref(parseFilterDate(props.filters.date))
const selectedBankAccountId = ref(props.filters.bank_account_id ?? null)

const bankAccountOptions = computed(() => [
    { id: null, label: 'Todas as contas' },
    ...props.bankAccounts,
])

const uploadForm = useForm({
    files: [],
    date: null,
})

function dateParam() {
    const d = selectedDate.value
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
}

function applyFilters() {
    const params = { date: dateParam() }
    if (selectedBankAccountId.value) {
        params.bank_account_id = selectedBankAccountId.value
    }
    router.get('/financeiro/contas-pagar/conciliacao', params, { preserveState: true, preserveScroll: true })
}

function shiftDay(delta) {
    const d = new Date(selectedDate.value)
    d.setDate(d.getDate() + delta)
    selectedDate.value = d
    applyFilters()
}

function onBatchUpload(event) {
    uploadForm.files = event.files
    uploadForm.date = dateParam()

    uploadForm.post('/financeiro/contas-pagar/conciliacao/upload-batch', {
        forceFormData: true,
        onError: (errors) => {
            const msg = errors.files || errors.file || Object.values(errors)[0]
            if (msg) {
                toast.add({ severity: 'error', summary: 'Erro no upload', detail: msg, life: 5000 })
            }
        },
    })
}

function goShow(id) {
    router.visit(`/financeiro/contas-pagar/conciliacao/${id}`)
}

function formatDate(d) {
    if (!d) return '—'
    const s = typeof d === 'string' ? d.slice(0, 10) : d
    return new Date(s + 'T12:00:00').toLocaleDateString('pt-BR')
}

function formatMoney(v) {
    return Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatPeriod(start, end) {
    if (!start && !end) return '—'
    return `${formatDate(start)} a ${formatDate(end)}`
}

function statusSeverity(status) {
    return { processing: 'warn', done: 'success', error: 'danger' }[status] || 'info'
}

function statusLabel(status) {
    return { processing: 'Processando', done: 'Concluído', error: 'Erro' }[status] || status
}
</script>

<template>
    <AppLayout>
        <Toast />
        <div class="max-w-7xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Conciliação Bancária</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Escolha o dia, suba os OFX (conta detectada automaticamente) e concilie os pagamentos daquele dia.
                </p>
            </div>

            <!-- Data + upload -->
            <div class="bg-white rounded-xl border border-gray-100 p-5 mb-6">
                <div class="flex flex-wrap items-end gap-3 mb-4">
                    <button type="button" class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 shrink-0" @click="shiftDay(-1)">
                        <i class="pi pi-chevron-left text-sm" />
                    </button>
                    <div class="w-52 shrink-0">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Data da conciliação</label>
                        <DatePicker
                            v-model="selectedDate"
                            date-format="dd/mm/yy"
                            show-icon
                            icon-display="input"
                            class="w-full"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <button type="button" class="p-2 rounded-lg border border-gray-200 hover:bg-gray-50 shrink-0" @click="shiftDay(1)">
                        <i class="pi pi-chevron-right text-sm" />
                    </button>
                    <div class="flex-1 min-w-[10rem] text-right">
                        <p class="text-sm font-semibold text-gray-700">{{ periodLabel || session?.period_label }}</p>
                        <p class="text-xs text-gray-400">Conciliação diária</p>
                    </div>
                </div>

                <div v-if="isConciliador" class="border-t border-gray-100 pt-4" dusk="upload-ofx">
                    <h2 class="text-sm font-semibold text-gray-700 mb-1">Importar extratos OFX</h2>
                    <p class="text-xs text-gray-400 mb-3">
                        Vários arquivos de uma vez — cada OFX detecta a conta no cadastro Hub.
                    </p>
                    <FileUpload
                        mode="basic"
                        accept=".ofx"
                        multiple
                        :auto="true"
                        choose-label="Selecionar arquivo(s) .ofx"
                        :custom-upload="true"
                        @uploader="onBatchUpload"
                        :disabled="uploadForm.processing"
                    />
                    <p v-if="uploadForm.processing" class="text-xs text-blue-600 mt-2">Processando arquivos...</p>
                </div>
            </div>

            <!-- Filtro opcional por conta -->
            <div class="bg-white rounded-xl border border-gray-100 p-4 mb-6 max-w-md">
                <label class="block text-xs font-medium text-gray-500 mb-1">Filtrar visualização por conta (opcional)</label>
                <Select
                    v-model="selectedBankAccountId"
                    :options="bankAccountOptions"
                    option-label="label"
                    option-value="id"
                    placeholder="Todas as contas"
                    class="w-full"
                    @change="applyFilters"
                />
            </div>

            <!-- KPIs -->
            <div v-if="summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-amber-50 border border-amber-100 rounded-xl p-4">
                    <p class="text-xs text-amber-700 font-medium">Títulos pagos no dia</p>
                    <p class="text-2xl font-bold text-amber-900">{{ summary.pending_payables }}</p>
                </div>
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                    <p class="text-xs text-blue-700 font-medium">Sugestões de match</p>
                    <p class="text-2xl font-bold text-blue-900">{{ summary.suggested_matches }}</p>
                </div>
                <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                    <p class="text-xs text-green-700 font-medium">Matches aceitos</p>
                    <p class="text-2xl font-bold text-green-900">{{ summary.accepted_matches }}</p>
                </div>
                <div class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                    <p class="text-xs text-gray-600 font-medium">Débitos sem match</p>
                    <p class="text-2xl font-bold text-gray-800">{{ summary.unmatched_debits }}</p>
                </div>
            </div>

            <!-- Títulos aguardando conciliação -->
            <div class="bg-white rounded-xl border border-gray-100 mb-6 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h2 class="text-sm font-semibold text-gray-700">Títulos pagos aguardando conciliação</h2>
                    <span class="text-xs text-gray-400">Pagos em {{ periodLabel }}</span>
                </div>
                <DataTable :value="pendingPayables" striped-rows class="text-sm">
                    <Column field="title_number" header="Título" style="width: 120px" />
                    <Column field="supplier_name" header="Fornecedor" />
                    <Column header="Valor" style="width: 120px">
                        <template #body="{ data }">{{ formatMoney(data.amount) }}</template>
                    </Column>
                    <Column header="Pago em" style="width: 110px">
                        <template #body="{ data }">{{ formatDate(data.paid_at) }}</template>
                    </Column>
                    <Column header="Status" style="width: 140px">
                        <template #body>
                            <Tag value="Aguardando conciliação" severity="warn" />
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center py-8 text-gray-500">Nenhum título pago neste dia.</div>
                    </template>
                </DataTable>
            </div>

            <!-- Importações do dia -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700">Extratos importados no dia</h2>
                </div>
                <DataTable
                    :value="imports.data"
                    striped-rows
                    class="cursor-pointer"
                    :lazy="true"
                    :paginator="imports.total > imports.per_page"
                    :rows="imports.per_page"
                    :total-records="imports.total"
                    :first="(imports.current_page - 1) * imports.per_page"
                    @row-click="({ data }) => goShow(data.id)"
                >
                    <Column header="Data" style="width: 120px">
                        <template #body="{ data }">
                            <span :dusk="`import-row-${data.id}`">{{ formatDate(data.created_at) }}</span>
                        </template>
                    </Column>
                    <Column header="Conta Hub" style="min-width: 160px">
                        <template #body="{ data }">
                            <span class="text-sm">{{ data.bank_account?.name || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="bank_name" header="Banco OFX" style="min-width: 140px" />
                    <Column field="account_number" header="Conta OFX" style="width: 140px" />
                    <Column header="Período extrato" style="min-width: 180px">
                        <template #body="{ data }">
                            {{ formatPeriod(data.period_start, data.period_end) }}
                        </template>
                    </Column>
                    <Column field="transaction_count" header="Transações" style="width: 110px; text-align: center" />
                    <Column field="matched_count" header="Matches" style="width: 100px; text-align: center" />
                    <Column header="Status" style="width: 130px">
                        <template #body="{ data }">
                            <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center py-8 text-gray-500">
                            Nenhum extrato importado neste dia. Suba os OFX acima.
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
