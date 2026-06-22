<script setup>
import { ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import FileUpload from 'primevue/fileupload'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
    imports: Object,
    isConciliador: Boolean,
})

const toast = useToast()
const page = usePage()

watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        toast.add({ severity: 'success', summary: 'Sucesso', detail: flash.success, life: 4000 })
    }
    if (flash?.error) {
        toast.add({ severity: 'error', summary: 'Erro', detail: flash.error, life: 5000 })
    }
}, { deep: true })

const uploadForm = useForm({ file: null })

function onUpload(event) {
    uploadForm.file = event.files[0]
    uploadForm.post('/financeiro/contas-pagar/conciliacao/upload', {
        forceFormData: true,
        onError: (errors) => {
            if (errors.file) {
                toast.add({ severity: 'error', summary: 'Erro no upload', detail: errors.file, life: 5000 })
            }
        },
    })
}

function goShow(id) {
    router.visit(`/financeiro/contas-pagar/conciliacao/${id}`)
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
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
                <h1 class="text-2xl font-bold text-gray-800">Conciliacao Bancaria</h1>
                <p class="text-sm text-gray-500 mt-1">Importe extratos OFX e concilie transacoes com titulos pagos.</p>
            </div>

            <!-- Upload area - only for conciliador -->
            <div v-if="isConciliador" class="bg-white rounded-xl border border-gray-100 p-6 mb-6" dusk="upload-ofx">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Importar extrato OFX</h2>
                <FileUpload
                    mode="basic"
                    accept=".ofx"
                    :auto="true"
                    choose-label="Selecionar arquivo .ofx"
                    :custom-upload="true"
                    @uploader="onUpload"
                    :disabled="uploadForm.processing"
                />
                <p v-if="uploadForm.processing" class="text-xs text-blue-600 mt-2">Processando arquivo...</p>
            </div>

            <!-- DataTable of imports -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <DataTable
                    :value="imports.data"
                    striped-rows
                    class="cursor-pointer"
                    :lazy="true"
                    :paginator="true"
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
                    <Column field="bank_name" header="Banco" style="min-width: 150px" />
                    <Column field="account_number" header="Conta" style="width: 150px" />
                    <Column header="Periodo" style="min-width: 180px">
                        <template #body="{ data }">
                            {{ formatPeriod(data.period_start, data.period_end) }}
                        </template>
                    </Column>
                    <Column field="transaction_count" header="Transacoes" style="width: 110px; text-align: center" />
                    <Column field="matched_count" header="Matches" style="width: 100px; text-align: center" />
                    <Column header="Status" style="width: 130px">
                        <template #body="{ data }">
                            <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center py-8 text-gray-500">Nenhuma importacao encontrada.</div>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
