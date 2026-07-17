<script setup>
import { ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
    imports: Object,
    isConciliador: Boolean,
    bankAccounts: { type: Array, default: () => [] },
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

const uploadForm = useForm({ file: null, bank_account_id: null })
const fileInput = ref(null)

function triggerUpload() {
    fileInput.value?.click()
}

function handleFileChange(event) {
    const file = event.target.files[0]
    if (!file) return
    uploadForm.file = file
    uploadForm.post('/financeiro/contas-pagar/conciliacao/upload', {
        forceFormData: true,
        onError: (errors) => {
            if (errors.file) {
                toast.add({ severity: 'error', summary: 'Erro', detail: errors.file, life: 5000 })
            }
        },
    })
    // Reset input
    event.target.value = ''
}

function goShow(id) {
    router.visit(`/financeiro/contas-pagar/conciliacao/${id}`)
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}

function statusSeverity(status) {
    return { processing: 'warn', done: 'success', error: 'danger' }[status] || 'info'
}

function statusLabel(status) {
    return { processing: 'Processando', done: 'Concluido', error: 'Erro' }[status] || status
}
</script>

<template>
    <AppLayoutMobile>
        <Toast />
        <div class="px-4 pb-20">
            <h1 class="text-xl font-bold text-gray-800 mb-4">Conciliacao Bancaria</h1>

            <!-- Upload card - only for conciliador -->
            <div v-if="isConciliador" class="bg-blue-50 rounded-xl p-4 mb-4 border border-blue-100" dusk="upload-ofx" @click="triggerUpload">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                        <i class="pi pi-upload text-white text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-blue-900">Importar extrato OFX</p>
                        <p class="text-xs text-blue-700">Toque para selecionar arquivo</p>
                    </div>
                </div>
                <input ref="fileInput" type="file" accept=".ofx" class="hidden" @change="handleFileChange" />
            </div>

            <!-- Import cards -->
            <div v-if="imports.data.length === 0" class="text-center py-12 text-gray-500">
                <i class="pi pi-file-import text-4xl text-gray-300 mb-3 block"></i>
                <p>Nenhuma importacao encontrada.</p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="item in imports.data"
                    :key="item.id"
                    class="bg-white rounded-xl border border-gray-100 p-4 active:bg-gray-50 transition-colors"
                    :dusk="`import-row-${item.id}`"
                    @click="goShow(item.id)"
                >
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ item.bank_account?.name || item.bank_name || 'Banco' }}</p>
                            <p class="text-xs text-gray-500">OFX: {{ item.account_number }}</p>
                        </div>
                        <Tag :value="statusLabel(item.status)" :severity="statusSeverity(item.status)" class="text-xs" />
                    </div>
                    <div class="flex justify-between items-end">
                        <div class="text-xs text-gray-500">
                            <span>{{ formatDate(item.created_at) }}</span>
                            <span class="mx-1">&middot;</span>
                            <span>{{ item.transaction_count }} transacoes</span>
                        </div>
                        <span class="text-xs font-medium text-green-600">{{ item.matched_count }} matches</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayoutMobile>
</template>
