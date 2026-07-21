<script setup>
import { ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
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
    if (flash?.success) toast.add({ severity: 'success', summary: 'Sucesso', detail: flash.success, life: 5000 })
    if (flash?.error) toast.add({ severity: 'error', summary: 'Erro', detail: flash.error, life: 5000 })
}, { deep: true })

function parseFilterDate(value) {
    if (!value) return new Date()
    const [y, m, d] = String(value).slice(0, 10).split('-').map(Number)
    return new Date(y, m - 1, d, 12, 0, 0)
}

const selectedDate = ref(parseFilterDate(props.filters.date))
const uploadForm = useForm({ files: [], date: null })
const fileInput = ref(null)

function dateParam() {
    const d = selectedDate.value
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function applyFilters() {
    router.get('/financeiro/contas-pagar/conciliacao', { date: dateParam() }, { preserveState: true })
}

function triggerUpload() {
    fileInput.value?.click()
}

function handleFileChange(event) {
    const files = Array.from(event.target.files || [])
    if (!files.length) return

    uploadForm.files = files
    uploadForm.date = dateParam()

    uploadForm.post('/financeiro/contas-pagar/conciliacao/upload-batch', {
        forceFormData: true,
        onError: (errors) => {
            const msg = errors.files || Object.values(errors)[0]
            if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 })
        },
    })
    event.target.value = ''
}

function goShow(id) {
    router.visit(`/financeiro/contas-pagar/conciliacao/${id}`)
}
</script>

<template>
    <AppLayoutMobile>
        <Toast />
        <div class="px-4 pb-20">
            <h1 class="text-xl font-bold text-gray-800 mb-1">Conciliação Bancária</h1>
            <p class="text-xs text-gray-500 mb-4">Conciliação diária · {{ periodLabel }}</p>

            <div class="bg-white rounded-xl border border-gray-100 p-4 mb-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">Data</label>
                <DatePicker v-model="selectedDate" date-format="dd/mm/yy" class="w-full" @update:model-value="applyFilters" />
            </div>

            <div v-if="isConciliador" class="bg-blue-50 rounded-xl p-4 mb-4 border border-blue-100" dusk="upload-ofx" @click="triggerUpload">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                        <i class="pi pi-upload text-white text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-blue-900">Importar OFX(s)</p>
                        <p class="text-xs text-blue-700">Conta detectada automaticamente</p>
                    </div>
                </div>
                <input ref="fileInput" type="file" accept=".ofx" multiple class="hidden" @change="handleFileChange" />
            </div>

            <div v-if="summary" class="grid grid-cols-2 gap-2 mb-4">
                <div class="bg-amber-50 rounded-lg p-3 border border-amber-100">
                    <p class="text-[10px] text-amber-700">Pagos no dia</p>
                    <p class="text-lg font-bold text-amber-900">{{ summary.pending_payables }}</p>
                </div>
                <div class="bg-blue-50 rounded-lg p-3 border border-blue-100">
                    <p class="text-[10px] text-blue-700">Sugestões</p>
                    <p class="text-lg font-bold text-blue-900">{{ summary.suggested_matches }}</p>
                </div>
            </div>

            <h2 class="text-sm font-semibold text-gray-700 mb-2">Extratos do dia</h2>
            <div v-if="!imports.data?.length" class="text-center py-8 text-gray-500 text-sm">Nenhum extrato importado.</div>
            <div v-else class="space-y-3">
                <div v-for="item in imports.data" :key="item.id" class="bg-white rounded-xl border border-gray-100 p-4" @click="goShow(item.id)">
                    <p class="text-sm font-semibold text-gray-800">{{ item.bank_account?.name || item.bank_name }}</p>
                    <p class="text-xs text-gray-500">{{ item.transaction_count }} transações · {{ item.matched_count }} matches</p>
                </div>
            </div>
        </div>
    </AppLayoutMobile>
</template>
