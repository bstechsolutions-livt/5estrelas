<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    bordero: Object,
    statusLabels: Object,
    statusColors: Object,
    payablesWorkflow: { type: Array, default: () => [] },
    canApproveStep: { type: Boolean, default: false },
    approvableCount: { type: Number, default: 0 },
    currentStepLabel: { type: String, default: null },
    departments: { type: Array, default: () => [] },
})

const { isMobile } = useDevice()
const showReject = ref(false)
const rejectReason = ref('')
const showDeptSelect = ref(false)
const selectedDeptId = ref(null)

function goBack() { window.history.back() }

function removePayable(payableId) {
    if (confirm('Remover este título do borderô?')) {
        router.delete(`/financeiro/borderos/${props.bordero.id}/titulos/${payableId}`, { preserveScroll: true })
    }
}

function sendForApproval() {
    router.post(`/financeiro/borderos/${props.bordero.id}/enviar-aprovacao`, {
        department_id: selectedDeptId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { showDeptSelect.value = false },
    })
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
const isAwaitingApproval = computed(() => props.bordero.status === 'aguardando_aprovacao')
const allHaveDocuments = computed(() =>
    props.payablesWorkflow.every((row) => (row.payable.documents_count || 0) > 0)
)
const levelLabels = {
    departamento: 'Departamento',
    gerencia: 'Gerência / Head',
    diretoria: 'Diretoria',
    financeiro: 'Financeiro',
    presidencia: 'Presidência',
    presidencia_2: 'Presidência (2ª assinatura)',
}

function openPayable(payableId) {
    router.visit(`/financeiro/contas-pagar/${payableId}`)
}

function reloadIfStale() {
    const key = `bordero-${props.bordero.id}-stale`
    if (!sessionStorage.getItem(key)) return
    sessionStorage.removeItem(key)
    router.reload({
        only: ['bordero', 'payablesWorkflow', 'canApproveStep', 'approvableCount', 'currentStepLabel'],
        preserveScroll: true,
    })
}

onMounted(reloadIfStale)
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

            <!-- Aviso: reprovado e devolvido para correção -->
            <div
                v-if="bordero.rejection_reason && isDraft"
                class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4"
                dusk="bordero-rejection-alert"
            >
                <h3 class="text-sm font-semibold text-red-800 mb-1 flex items-center gap-2">
                    <i class="pi pi-exclamation-triangle"></i>
                    Borderô recusado — precisa ser refeito
                </h3>
                <p class="text-sm text-red-700">{{ bordero.rejection_reason }}</p>
                <p class="text-xs text-red-600 mt-2">Corrija os títulos abaixo e envie novamente para aprovação.</p>
            </div>

            <!-- Envio para aprovação (mesmo fluxo do título individual) -->
            <div v-if="isDraft" class="bg-white rounded-xl border border-gray-100 p-4 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Enviar para aprovação</h3>
                <p class="text-xs text-gray-500 mb-3">Cada título seguirá o fluxo configurado (mesmo do envio individual).</p>
                <div v-if="!showDeptSelect">
                    <Button
                        label="Enviar borderô para aprovação"
                        icon="pi pi-send"
                        dusk="btn-bordero-send-approval"
                        :disabled="!allHaveDocuments"
                        @click="showDeptSelect = true"
                    />
                    <p v-if="!allHaveDocuments" class="text-[11px] text-amber-600 mt-2 flex items-center gap-1">
                        <i class="pi pi-exclamation-triangle text-[10px]"></i>
                        Todos os títulos precisam de ao menos um documento anexado.
                    </p>
                </div>
                <div v-else class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Departamento de origem *</label>
                        <Select
                            v-model="selectedDeptId"
                            :options="departments"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Selecione o departamento..."
                            class="w-full"
                            filter
                        />
                    </div>
                    <div class="flex gap-2">
                        <Button label="Confirmar envio" icon="pi pi-send" :disabled="!selectedDeptId" @click="sendForApproval" />
                        <Button label="Cancelar" severity="secondary" outlined @click="showDeptSelect = false" />
                    </div>
                </div>
            </div>

            <!-- Ações do aprovador (workflow multinível) -->
            <div v-if="isAwaitingApproval && payablesWorkflow.some(r => r.approval_steps?.length)" class="bg-white rounded-xl border border-gray-100 p-4 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Fluxo de aprovação</h3>
                <p v-if="canApproveStep" class="text-xs text-blue-600 font-medium mb-3">
                    Sua vez: {{ levelLabels[currentStepLabel] || currentStepLabel }}
                    · {{ approvableCount }} título(s) aguardando sua ação
                </p>
                <p v-else class="text-xs text-gray-500 mb-3">Aguardando outros aprovadores na cadeia configurada.</p>
                <div v-if="canApproveStep" class="flex flex-wrap gap-2">
                    <Button label="Aprovar etapa" icon="pi pi-check" severity="success" dusk="btn-bordero-approve" @click="approve" />
                    <Button label="Reprovar" icon="pi pi-times" severity="danger" outlined @click="showReject = !showReject" />
                </div>
            </div>

            <!-- Reprovação -->
            <div v-if="showReject" class="bg-white rounded-xl border border-red-200 p-4 mb-4">
                <h3 class="text-sm font-semibold text-red-700 mb-2">Motivo da reprovação</h3>
                <Textarea v-model="rejectReason" rows="3" class="w-full mb-2" placeholder="Justifique..." />
                <div class="flex gap-2 justify-end">
                    <Button label="Cancelar" severity="secondary" size="small" @click="showReject = false" />
                    <Button label="Confirmar reprovação" severity="danger" size="small" @click="reject" :disabled="!rejectReason.trim()" />
                </div>
            </div>

            <!-- Lista de títulos com stepper por título -->
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Títulos neste borderô</h3>
                </div>
                <div class="divide-y divide-gray-50">
                    <div v-for="row in payablesWorkflow" :key="row.payable.id" class="p-4">
                        <div
                            class="flex items-center justify-between cursor-pointer hover:bg-gray-50 -mx-2 px-2 py-1 rounded"
                            @click="openPayable(row.payable.id)"
                        >
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-col items-start gap-0.5">
                                    <span class="text-xs font-mono text-gray-600 whitespace-nowrap">{{ row.payable.title_number }}</span>
                                    <p class="text-sm font-medium text-gray-800 truncate w-full">{{ row.payable.supplier_name }}</p>
                                    <Tag
                                        v-if="row.payable.rejection_reason"
                                        value="Recusado"
                                        severity="danger"
                                        class="!text-[9px] !px-1.5 !py-0"
                                        v-tooltip.top="row.payable.rejection_reason"
                                    />
                                    <Tag v-else :value="row.payable.status" severity="secondary" class="!text-[9px]" />
                                    <span v-if="row.payable.documents_count" class="inline-flex items-center gap-1 text-gray-300 text-xs">
                                        <i class="pi pi-paperclip" />
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500">Venc: {{ formatDate(row.payable.due_date) }} · {{ row.payable.branch?.name || '' }}</p>
                                <p v-if="row.payable.rejection_reason" class="text-xs text-red-600 mt-1 line-clamp-2">
                                    {{ row.payable.rejection_reason }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-semibold text-gray-700">{{ formatMoney(row.payable.amount) }}</span>
                                <button
                                    v-if="isDraft"
                                    @click.stop="removePayable(row.payable.id)"
                                    class="text-red-400 hover:text-red-600 p-1"
                                    title="Remover do borderô"
                                >
                                    <i class="pi pi-times"></i>
                                </button>
                                <i v-else class="pi pi-chevron-right text-gray-300 text-xs"></i>
                            </div>
                        </div>

                        <div v-if="row.approval_steps?.length" class="mt-3 pl-2 border-l-2 border-gray-100 space-y-1">
                            <div v-for="step in row.approval_steps" :key="step.id" class="flex items-start gap-2">
                                <i
                                    v-if="step.status === 'aprovado'"
                                    class="pi pi-check-circle text-green-500 text-xs mt-0.5"
                                />
                                <i
                                    v-else-if="step.status === 'reprovado'"
                                    class="pi pi-times-circle text-red-500 text-xs mt-0.5"
                                />
                                <i
                                    v-else-if="row.current_step?.id === step.id"
                                    class="pi pi-circle-fill text-blue-500 text-xs mt-0.5"
                                />
                                <i v-else class="pi pi-circle text-gray-300 text-xs mt-0.5" />
                                <p
                                    class="text-[11px]"
                                    :class="step.status === 'aprovado' ? 'text-green-700' : step.status === 'reprovado' ? 'text-red-700' : row.current_step?.id === step.id ? 'text-blue-700' : 'text-gray-500'"
                                >
                                    {{ step.assignee?.name || levelLabels[step.level_name] || step.level_name }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
