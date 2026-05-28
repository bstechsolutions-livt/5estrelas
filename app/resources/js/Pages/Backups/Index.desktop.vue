<script setup>
import { ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import ConfirmDialog from 'primevue/confirmdialog'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { watch } from 'vue'

const props = defineProps({
    backups: Array,
    config: Object,
})

const page = usePage()
const confirm = useConfirm()
const toast = useToast()

const runForm = useForm({})

function runBackup() {
    runForm.post('/backups/run', {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['backups'] })
        },
    })
}

function downloadBackup(filename) {
    window.location.href = `/backups/${encodeURIComponent(filename)}/download`
}

function confirmDelete(filename) {
    confirm.require({
        message: `Excluir o backup "${filename}"? Essa ação não pode ser desfeita.`,
        header: 'Confirmar exclusão',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        acceptLabel: 'Excluir',
        rejectLabel: 'Cancelar',
        accept: () => {
            router.delete(`/backups/${encodeURIComponent(filename)}`, {
                preserveScroll: true,
            })
        },
    })
}

function formatDate(iso) {
    return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'medium' })
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'OK', detail: msg, life: 4000 })
})
watch(() => page.props.flash?.error, (msg) => {
    if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 })
})
</script>

<template>
    <AppLayout>
        <Toast />
        <ConfirmDialog />

        <div class="max-w-6xl mx-auto">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Backups</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Backup automático do banco de dados. Agendado: <strong>{{ config.schedule }}</strong>.
                        Retenção: <strong>{{ config.retention_days }} dias</strong>.
                    </p>
                </div>
                <Button
                    label="Executar agora"
                    icon="pi pi-play"
                    :loading="runForm.processing"
                    @click="runBackup"
                />
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <DataTable
                    :value="backups"
                    :paginator="backups.length > 10"
                    :rows="10"
                    striped-rows
                    table-style="min-width: 100%"
                    empty-message="Nenhum backup ainda. Clique em 'Executar agora'."
                >
                    <Column field="name" header="Arquivo" sortable>
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-database text-gray-400"></i>
                                <span class="font-mono text-xs">{{ data.name }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column field="size_human" header="Tamanho" sortable />
                    <Column field="created_at" header="Data" sortable>
                        <template #body="{ data }">
                            {{ formatDate(data.created_at) }}
                        </template>
                    </Column>
                    <Column header="Ações" style="width: 200px">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button
                                    icon="pi pi-download"
                                    severity="secondary"
                                    size="small"
                                    text
                                    title="Baixar"
                                    @click="downloadBackup(data.name)"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    size="small"
                                    text
                                    title="Excluir"
                                    @click="confirmDelete(data.name)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
