<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    bordero: Object,
    statusLabels: Object,
    statusColors: Object,
})

const { isMobile } = useDevice()
const showReject = ref(false)
const rejectReason = ref('')

function goBack() { window.history.back() }

function removePayable(payableId) {
    if (confirm('Remover este título do borderô?')) {
        router.delete(`/financeiro/borderos/${props.bordero.id}/titulos/${payableId}`, { preserveScroll: true })
    }
}

function sendForApproval() {
    router.post(`/financeiro/borderos/${props.bordero.id}/enviar-aprovacao`, {}, { preserveScroll: true })
}
function approve() {
    router.post(`/financeiro/borderos/${props.bordero.id}/aprovar`, {}, { preserveScroll: true })
}
function reject() {
    router.post(`/financeiro/borderos/${props.bordero.id}/reprovar`, { reason: rejectReason.value }, {
        preserveScroll: true,
        onSuccess: () => { showReject.value = false; rejectReason.value = '' },
    })
}

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}
function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}

const isDraft = computed(() => props.bordero.status === 'rascunho')
const canApprove = computed(() => props.bordero.status === 'aguardando_aprovacao')
const statusSeverity = { pendente: 'warn', em_preparacao: 'info', aguardando_aprovacao: 'warn', aprovado: 'success', reprovado: 'danger', pago: 'success' }
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout" :title="isMobile ? 'Borderô' : undefined" :show-back="isMobile">
        <div :class="isMobile ? 'px-4 py-3 pb-20' : 'max-w-5xl mx-auto'">
            <button @click="goBack" class="text-sm text-blue-600 hover:underline mb-2 flex items-center gap-1 cursor-pointer">
                <i class="pi pi-arrow-left text-xs"></i> Voltar
            </button>

            <!-- Header -->
            <div class="flex items-start justify-between mb-6">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-gray-800">{{ bordero.number }}</h1>
                        <Tag :value="statusLabels[bordero.status]" :severity="statusColors[bordero.status]" />
                    </div>
                    <p v-if="bordero.description" class="text-sm text-gray-500 mt-1">{{ bordero.description }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ bordero.items_count }} títulos · Criado por {{ bordero.creator?.name }}</p>
                </div>
                <p class="text-2xl font-bold text-gray-800">{{ formatMoney(bordero.total_amount) }}</p>
            </div>

            <!-- Ações -->
            <div v-if="isDraft || canApprove" class="bg-white rounded-xl border border-gray-100 p-4 mb-4 flex flex-wrap gap-2">
                <Button v-if="isDraft" label="Enviar Borderô para Aprovação" icon="pi pi-send" @click="sendForApproval" />
                <template v-if="canApprove">
                    <Button label="Aprovar Borderô" icon="pi pi-check" severity="success" @click="approve" />
                    <Button label="Reprovar" icon="pi pi-times" severity="danger" outlined @click="showReject = !showReject" />
                </template>
            </div>

            <!-- Reprovação -->
            <div v-if="showReject" class="bg-white rounded-xl border border-red-200 p-4 mb-4">
                <h3 class="text-sm font-semibold text-red-700 mb-2">Motivo da reprovação</h3>
                <Textarea v-model="rejectReason" rows="3" class="w-full mb-2" placeholder="Justifique..." />
                <div class="flex gap-2 justify-end">
                    <Button label="Cancelar" severity="secondary" size="small" @click="showReject = false" />
                    <Button label="Confirmar Reprovação" severity="danger" size="small" @click="reject" :disabled="!rejectReason.trim()" />
                </div>
            </div>

            <!-- Lista de títulos -->
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Títulos neste borderô</h3>
                </div>
                <div class="divide-y divide-gray-50">
                    <div v-for="p in bordero.payables" :key="p.id"
                        @click="router.visit(`/financeiro/contas-pagar/${p.id}`)"
                        class="flex items-center justify-between p-3 hover:bg-gray-50 cursor-pointer">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono text-gray-400">{{ p.title_number }}</span>
                                <p class="text-sm font-medium text-gray-800 truncate">{{ p.supplier_name }}</p>
                                <i v-if="p.documents_count" class="pi pi-paperclip text-gray-300 text-xs" :title="`${p.documents_count} documento(s)`"></i>
                            </div>
                            <p class="text-xs text-gray-500">Venc: {{ formatDate(p.due_date) }} · {{ p.branch?.name || '' }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-semibold text-gray-700">{{ formatMoney(p.amount) }}</span>
                            <button v-if="isDraft" @click.stop="removePayable(p.id)" class="text-red-400 hover:text-red-600 p-1" title="Remover do borderô">
                                <i class="pi pi-times"></i>
                            </button>
                            <i v-else class="pi pi-chevron-right text-gray-300 text-xs"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
