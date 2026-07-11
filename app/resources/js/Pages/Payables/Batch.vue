<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAuth } from '@/composables/useAuth'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import BranchAccessBlocked from '@/Components/Financeiro/BranchAccessBlocked.vue'
import { formatApiDate } from '@/utils/apiDate'

const props = defineProps({
    payables: Object,
    totals: Object,
    filters: Object,
    statusOptions: Object,
    noBranchAccess: { type: Boolean, default: false },
})

const toast = useToast()
const { can } = useAuth()
const canBorderos = computed(() => can('financeiro.borderos.visualizar'))

const status = computed(() => props.filters?.status || 'pendente')
const selectableStatuses = ['pendente', 'em_preparacao', 'reprovado']
const canSelectBordero = computed(() => selectableStatuses.includes(status.value) && canBorderos.value)

const nicknames = ref({})
const saving = ref(false)
const selected = ref([])
const createBorderoForm = ref({ description: '' })

function initNicknames() {
    const map = {}
    for (const row of props.payables?.data ?? []) {
        map[row.id] = row.nickname ?? ''
    }
    nicknames.value = map
}

initNicknames()

watch(() => props.payables, initNicknames, { deep: true })

const dirtyItems = computed(() =>
    (props.payables?.data ?? [])
        .filter(row => (nicknames.value[row.id] ?? '') !== (row.nickname ?? ''))
        .map(row => ({
            id: row.id,
            nickname: nicknames.value[row.id] ?? '',
        }))
)

const hasDirtyNicknames = computed(() => dirtyItems.value.length > 0)

function onPage(event) {
    router.get('/financeiro/contas-pagar/lote', {
        ...props.filters,
        page: event.page + 1,
        per_page: event.rows,
    }, { preserveState: true, replace: true })
}

function goShow(id) {
    router.visit(`/financeiro/contas-pagar/${id}`)
}

function toggleSelect(id) {
    const i = selected.value.indexOf(id)
    if (i >= 0) selected.value.splice(i, 1)
    else selected.value.push(id)
}

function isSelected(id) {
    return selected.value.includes(id)
}

function createBordero() {
    if (selected.value.length === 0) return
    router.post('/financeiro/borderos', {
        payable_ids: selected.value,
        description: createBorderoForm.value.description || undefined,
    }, {
        onSuccess: () => { selected.value = [] },
    })
}

async function saveNicknames() {
    if (!hasDirtyNicknames.value) return
    saving.value = true
    try {
        await window.axios.post('/financeiro/contas-pagar/lote/apelidos', {
            items: dirtyItems.value,
        })
        toast.add({
            severity: 'success',
            summary: 'Salvo',
            detail: `${dirtyItems.value.length} apelido(s) atualizado(s).`,
            life: 3000,
        })
        router.reload({ only: ['payables'], preserveScroll: true })
    } catch {
        toast.add({ severity: 'error', summary: 'Erro', detail: 'Não foi possível salvar os apelidos.', life: 4000 })
    } finally {
        saving.value = false
    }
}

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}

function formatDate(d) {
    return formatApiDate(d)
}

function displayLabel(row) {
    return row.nickname || row.supplier_name
}

const statusSeverity = {
    pendente: 'warn',
    em_preparacao: 'info',
    aguardando_aprovacao: 'warn',
    aprovado: 'success',
    reprovado: 'danger',
    pago: 'success',
}
</script>

<template>
    <AppLayout>
        <Toast />
        <div class="max-w-[1600px] mx-auto">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                <div>
                    <button type="button" class="text-xs text-blue-600 hover:underline mb-2 inline-flex items-center gap-1"
                        @click="router.visit('/financeiro/contas-pagar')">
                        <i class="pi pi-arrow-left text-[10px]"></i> Voltar à lista
                    </button>
                    <h1 class="text-2xl font-bold text-gray-800">Visão em lote</h1>
                    <p class="text-sm text-gray-500 mt-1 max-w-2xl">
                        Veja mais títulos de uma vez, edite apelidos em linha e selecione para criar borderô.
                        Os mesmos filtros da lista principal se aplicam aqui.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    <Button
                        v-if="hasDirtyNicknames"
                        :label="`Salvar ${dirtyItems.length} apelido(s)`"
                        icon="pi pi-save"
                        size="small"
                        :loading="saving"
                        @click="saveNicknames"
                    />
                    <Button label="Lista padrão" icon="pi pi-list" severity="secondary" outlined size="small"
                        @click="router.visit('/financeiro/contas-pagar')" />
                </div>
            </div>

            <BranchAccessBlocked v-if="noBranchAccess" />

            <div v-if="canSelectBordero && selected.length > 0"
                class="bg-blue-600 text-white rounded-xl p-3 mb-4 flex items-center justify-between">
                <span class="text-sm font-medium">{{ selected.length }} título(s) selecionado(s)</span>
                <div class="flex items-center gap-2">
                    <InputText v-model="createBorderoForm.description" placeholder="Descrição do borderô (opcional)" class="w-72" />
                    <Button label="Criar Borderô" icon="pi pi-list-check" severity="contrast" size="small" @click="createBordero" />
                    <Button icon="pi pi-times" severity="contrast" text size="small" @click="selected = []" />
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <DataTable
                    :value="payables.data"
                    striped-rows
                    size="small"
                    class="batch-table w-full"
                    :lazy="true"
                    :paginator="true"
                    :rows="payables.per_page"
                    :total-records="payables.total"
                    :first="(payables.current_page - 1) * payables.per_page"
                    @page="onPage"
                    paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                    :rows-per-page-options="[50, 100, 200]"
                    current-page-report-template="{first}-{last} de {totalRecords}"
                >
                    <Column v-if="canSelectBordero" header="" style="width: 2.5rem">
                        <template #body="{ data }">
                            <input type="checkbox" :checked="isSelected(data.id)" class="w-4 h-4 cursor-pointer"
                                @click.stop="toggleSelect(data.id)" />
                        </template>
                    </Column>
                    <Column field="title_number" header="Nº" style="width: 7%">
                        <template #body="{ data }">
                            <button type="button" class="text-xs font-medium text-blue-600 hover:underline"
                                @click="goShow(data.id)">
                                {{ data.title_number || '—' }}
                            </button>
                        </template>
                    </Column>
                    <Column header="Apelido" style="width: 14%">
                        <template #body="{ data }">
                            <InputText
                                v-model="nicknames[data.id]"
                                class="w-full !text-xs"
                                placeholder="Ex: Energia jul"
                                size="small"
                                @click.stop
                            />
                        </template>
                    </Column>
                    <Column header="Fornecedor" style="width: 16%">
                        <template #body="{ data }">
                            <span class="text-xs truncate block" :title="data.supplier_name">{{ data.supplier_name }}</span>
                        </template>
                    </Column>
                    <Column header="Empresa" style="width: 10%">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600 truncate block">{{ data.empresa_nome || '—' }}</span>
                        </template>
                    </Column>
                    <Column header="Depto" style="width: 9%">
                        <template #body="{ data }">
                            <span class="text-xs text-gray-600 truncate block">{{ data.department_nome || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="amount" header="Valor" style="width: 9%">
                        <template #body="{ data }">
                            <span class="text-xs font-semibold whitespace-nowrap">{{ formatMoney(data.amount) }}</span>
                        </template>
                    </Column>
                    <Column field="due_date" header="Venc." style="width: 8%">
                        <template #body="{ data }">
                            <span class="text-xs whitespace-nowrap">{{ formatDate(data.due_date) }}</span>
                        </template>
                    </Column>
                    <Column header="Alertas" style="width: 10%">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Tag v-if="data.document_pair_alert" value="Doc" severity="warn" class="!text-[9px]" />
                                <Tag v-if="data.rejection_reason && data.status === 'pendente'" value="Recusado" severity="danger" class="!text-[9px]" />
                            </div>
                        </template>
                    </Column>
                    <Column field="status" header="Status" style="width: 9%">
                        <template #body="{ data }">
                            <Tag :value="statusOptions[data.status]" :severity="statusSeverity[data.status]" class="!text-[10px]" />
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center py-8 text-gray-500">Nenhum título encontrado com os filtros atuais.</div>
                    </template>
                </DataTable>
            </div>

            <p v-if="hasDirtyNicknames" class="text-xs text-amber-600 mt-3">
                Você tem alterações de apelido não salvas. Clique em "Salvar apelido(s)" antes de sair.
            </p>
        </div>
    </AppLayout>
</template>

<style scoped>
.batch-table :deep(.p-datatable-tbody > tr > td),
.batch-table :deep(.p-datatable-thead > tr > th) {
    font-size: 0.8125rem;
    padding-top: 0.45rem;
    padding-bottom: 0.45rem;
}
</style>
