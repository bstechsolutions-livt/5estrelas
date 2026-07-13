<script setup>
import { ref, computed, onMounted } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import ApprovalFlowPreview from '@/Components/Financeiro/ApprovalFlowPreview.vue'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    bordero: Object,
    statusLabels: Object,
    statusColors: Object,
    payablesWorkflow: { type: Array, default: () => [] },
    canApproveStep: { type: Boolean, default: false },
    canReprovarBordero: { type: Boolean, default: false },
    canLiberarTitulo: { type: Boolean, default: false },
    canDesfazer: { type: Boolean, default: false },
    approvableCount: { type: Number, default: 0 },
    currentStepLabel: { type: String, default: null },
    requiresPriorityOnApprove: { type: Boolean, default: false },
    priorityOptions: { type: Object, default: () => ({}) },
    approvalPreview: { type: Object, default: () => ({ ok: false, errors: [], steps: [] }) },
    canBypassApprovalDeadline: { type: Boolean, default: false },
    minDueDateForApproval: { type: String, default: null },
})

const { isMobile } = useDevice()
const showRejectBordero = ref(false)
const rejectReason = ref('')
const showPriorityDialog = ref(false)
const showLiberarDialog = ref(false)
const showExpulsarDialog = ref(false)
const showDesfazerDialog = ref(false)
const actionPayableId = ref(null)
const actionReason = ref('')
const desfazerReason = ref('')

const approveForm = useForm({
    payment_priority: 'normal',
    payment_sla_date: null,
})

const prioritySelectOptions = computed(() =>
    Object.entries(props.priorityOptions || {}).map(([value, label]) => ({ value, label }))
)

function toYmd(d) {
    if (!d) return null
    const dt = d instanceof Date ? d : new Date(d)
    const y = dt.getFullYear()
    const m = String(dt.getMonth() + 1).padStart(2, '0')
    const day = String(dt.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
}

function goBack() { window.history.back() }

function removePayable(payableId) {
    if (confirm('Remover este título do borderô?')) {
        router.delete(`/financeiro/borderos/${props.bordero.id}/titulos/${payableId}`, { preserveScroll: true })
    }
}

function sendForApproval() {
    router.post(`/financeiro/borderos/${props.bordero.id}/enviar-aprovacao`, {
        urgente: urgentBypass.value && props.canBypassApprovalDeadline,
    }, { preserveScroll: true })
}

function parseDueDate(val) {
    if (!val) return null
    const d = new Date(val)
    return Number.isNaN(d.getTime()) ? null : d
}

const minApprovalDue = computed(() => parseDueDate(props.minDueDateForApproval))
const urgentBypass = ref(false)

const approvalDeadlineBlocked = computed(() => {
    if (!minApprovalDue.value) return false
    return (props.bordero.payables || []).some((p) => {
        const due = parseDueDate(p.due_date)
        return due && due < minApprovalDue.value
    })
})

const canSubmitApproval = computed(() =>
    props.approvalPreview?.ok && allHaveDocuments.value &&
    (!approvalDeadlineBlocked.value || (props.canBypassApprovalDeadline && urgentBypass.value))
)

function openApprove() {
    if (props.requiresPriorityOnApprove) {
        approveForm.payment_priority = 'normal'
        approveForm.payment_sla_date = null
        showPriorityDialog.value = true
        return
    }
    approve()
}

function confirmApproveWithPriority() {
    approveForm
        .transform((data) => ({
            payment_priority: data.payment_priority,
            payment_sla_date: data.payment_sla_date ? toYmd(data.payment_sla_date) : null,
        }))
        .post(`/financeiro/borderos/${props.bordero.id}/aprovar`, {
            preserveScroll: true,
            onSuccess: () => { showPriorityDialog.value = false },
        })
}

function approve() {
    router.post(`/financeiro/borderos/${props.bordero.id}/aprovar`, {}, { preserveScroll: true })
}

function rejectBordero() {
    router.post(`/financeiro/borderos/${props.bordero.id}/reprovar`, { reason: rejectReason.value }, {
        preserveScroll: true,
        onSuccess: () => { showRejectBordero.value = false; rejectReason.value = '' },
    })
}

function openLiberar(payableId) {
    actionPayableId.value = payableId
    actionReason.value = ''
    showLiberarDialog.value = true
}

function confirmLiberar() {
    router.post(`/financeiro/borderos/${props.bordero.id}/titulos/${actionPayableId.value}/liberar`, {
        reason: actionReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { showLiberarDialog.value = false; actionReason.value = '' },
    })
}

function openExpulsar(payableId) {
    actionPayableId.value = payableId
    actionReason.value = ''
    showExpulsarDialog.value = true
}

function confirmExpulsar() {
    router.post(`/financeiro/borderos/${props.bordero.id}/titulos/${actionPayableId.value}/expulsar`, {
        reason: actionReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { showExpulsarDialog.value = false; actionReason.value = '' },
    })
}

function confirmDesfazer() {
    router.post(`/financeiro/borderos/${props.bordero.id}/desfazer`, {
        reason: desfazerReason.value || undefined,
    })
}

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}
function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}

const isPending = computed(() => props.bordero.status === 'pendente')
const isPreparing = computed(() => props.bordero.status === 'em_preparacao')
const isEditable = computed(() => isPending.value || isPreparing.value)
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
        only: ['bordero', 'payablesWorkflow', 'canApproveStep', 'canReprovarBordero', 'canLiberarTitulo', 'canDesfazer', 'approvableCount', 'currentStepLabel', 'approvalPreview'],
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

            <div
                v-if="bordero.rejection_reason && isPending"
                class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4"
                dusk="bordero-rejection-alert"
            >
                <h3 class="text-sm font-semibold text-red-800 mb-1 flex items-center gap-2">
                    <i class="pi pi-exclamation-triangle"></i>
                    Borderô reprovado — corrija e reenvie
                </h3>
                <p class="text-sm text-red-700">{{ bordero.rejection_reason }}</p>
            </div>

            <div v-if="canDesfazer" class="bg-white rounded-xl border border-gray-100 p-4 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Desfazer borderô</h3>
                <p class="text-xs text-gray-500 mb-3">Libera todos os títulos para Contas a Pagar (pendente avulso) e remove este borderô.</p>
                <Button label="Desfazer borderô" icon="pi pi-undo" severity="danger" outlined size="small" @click="showDesfazerDialog = true" />
            </div>

            <div v-if="isEditable" class="bg-white rounded-xl border border-gray-100 p-4 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Enviar para aprovação</h3>
                <p class="text-xs text-gray-500 mb-3">Todos os títulos seguirão o fluxo do departamento de quem envia.</p>
                <ApprovalFlowPreview :preview="approvalPreview" class="mb-3" />
                <Button
                    label="Enviar borderô para aprovação"
                    icon="pi pi-send"
                    dusk="btn-bordero-send-approval"
                    :disabled="!canSubmitApproval"
                    @click="sendForApproval"
                />
                <p v-if="approvalDeadlineBlocked && !canBypassApprovalDeadline" class="text-[11px] text-amber-600 mt-2 flex items-center gap-1">
                    <i class="pi pi-exclamation-triangle text-[10px]"></i>
                    Há título(s) com vencimento antes do prazo de 72h. Aguarde ou solicite ao financeiro.
                </p>
                <label
                    v-else-if="approvalDeadlineBlocked && canBypassApprovalDeadline"
                    class="flex items-start gap-2 mt-2 text-[11px] text-amber-700 cursor-pointer"
                >
                    <input v-model="urgentBypass" type="checkbox" class="mt-0.5" dusk="urgent-bordero-bypass" />
                    <span>Enviar mesmo assim (urgência — fora do prazo de 72h)</span>
                </label>
                <p v-if="!allHaveDocuments" class="text-[11px] text-amber-600 mt-2 flex items-center gap-1">
                    <i class="pi pi-exclamation-triangle text-[10px]"></i>
                    Todos os títulos precisam de ao menos um documento anexado.
                </p>
            </div>

            <div v-if="isAwaitingApproval && payablesWorkflow.some(r => r.approval_steps?.length)" class="bg-white rounded-xl border border-gray-100 p-4 mb-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Fluxo de aprovação</h3>
                <p v-if="canApproveStep" class="text-xs text-blue-600 font-medium mb-3">
                    Sua vez: {{ levelLabels[currentStepLabel] || currentStepLabel }}
                    · {{ approvableCount }} título(s) nesta etapa
                </p>
                <p v-else class="text-xs text-gray-500 mb-3">Aguardando outros aprovadores na cadeia configurada.</p>
                <div v-if="canApproveStep || canReprovarBordero" class="flex flex-wrap gap-2">
                    <Button v-if="canApproveStep" label="Aprovar etapa" icon="pi pi-check" severity="success" dusk="btn-bordero-approve" @click="openApprove" />
                    <Button v-if="canReprovarBordero" label="Reprovar borderô" icon="pi pi-times" severity="danger" outlined @click="showRejectBordero = !showRejectBordero" />
                </div>
            </div>

            <div v-if="showRejectBordero" class="bg-white rounded-xl border border-red-200 p-4 mb-4">
                <h3 class="text-sm font-semibold text-red-700 mb-2">Reprovar borderô inteiro</h3>
                <p class="text-xs text-gray-600 mb-2">O borderô voltará para <strong>pendente</strong> na lista de borderôs. Os títulos permanecem neste pacote e <strong>não</strong> aparecem em CP avulso.</p>
                <Textarea v-model="rejectReason" rows="3" class="w-full mb-2" placeholder="Motivo obrigatório..." />
                <div class="flex gap-2 justify-end">
                    <Button label="Cancelar" severity="secondary" size="small" @click="showRejectBordero = false" />
                    <Button label="Confirmar reprovação do borderô" severity="danger" size="small" @click="rejectBordero" :disabled="!rejectReason.trim()" />
                </div>
            </div>

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
                                    <Tag :value="row.payable.status" severity="secondary" class="!text-[9px]" />
                                </div>
                                <p class="text-xs text-gray-500">Venc: {{ formatDate(row.payable.due_date) }} · {{ row.payable.empresa_nome || row.payable.filial_nome || '' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-gray-700">{{ formatMoney(row.payable.amount) }}</span>
                                <button
                                    v-if="isEditable"
                                    @click.stop="removePayable(row.payable.id)"
                                    class="text-red-400 hover:text-red-600 p-1"
                                    title="Remover do borderô"
                                >
                                    <i class="pi pi-times"></i>
                                </button>
                                <i v-else class="pi pi-chevron-right text-gray-300 text-xs"></i>
                            </div>
                        </div>

                        <div v-if="isAwaitingApproval && (row.can_expulsar || canLiberarTitulo)" class="flex flex-wrap gap-2 mt-2">
                            <Button
                                v-if="canLiberarTitulo"
                                label="Liberar p/ avulso"
                                icon="pi pi-external-link"
                                severity="info"
                                outlined
                                size="small"
                                @click="openLiberar(row.payable.id)"
                            />
                            <Button
                                v-if="row.can_expulsar"
                                label="Expulsar"
                                icon="pi pi-ban"
                                severity="danger"
                                outlined
                                size="small"
                                @click="openExpulsar(row.payable.id)"
                            />
                        </div>

                        <div v-if="row.approval_steps?.length" class="mt-3 pl-2 border-l-2 border-gray-100 space-y-1">
                            <div v-for="step in row.approval_steps" :key="step.id" class="flex items-start gap-2">
                                <i v-if="step.status === 'aprovado'" class="pi pi-check-circle text-green-500 text-xs mt-0.5" />
                                <i v-else-if="step.status === 'reprovado'" class="pi pi-times-circle text-red-500 text-xs mt-0.5" />
                                <i v-else-if="row.current_step?.id === step.id" class="pi pi-circle-fill text-blue-500 text-xs mt-0.5" />
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

        <Dialog v-model:visible="showPriorityDialog" modal header="Prioridade de pagamento" :style="{ width: '420px' }">
            <p class="text-sm text-gray-600 mb-4">A prioridade será aplicada a todos os títulos aprovados nesta etapa do Financeiro.</p>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Prioridade *</label>
                    <Select v-model="approveForm.payment_priority" :options="prioritySelectOptions" option-label="label" option-value="value" class="w-full" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Prazo (SLA)</label>
                    <DatePicker v-model="approveForm.payment_sla_date" date-format="dd/mm/yy" class="w-full" show-icon />
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" severity="secondary" text @click="showPriorityDialog = false" />
                <Button label="Aprovar etapa" icon="pi pi-check" severity="success" :loading="approveForm.processing" @click="confirmApproveWithPriority" />
            </template>
        </Dialog>

        <Dialog v-model:visible="showLiberarDialog" modal header="Liberar título do borderô" :style="{ width: '480px' }">
            <p class="text-sm text-gray-600 mb-3">
                Este título <strong>sairá do borderô {{ bordero.number }}</strong> e seguirá o fluxo de aprovação <strong>avulso</strong> a partir da etapa atual.
                O borderô continuará em aprovação com os demais títulos. O histórico será registrado nos comentários do título.
            </p>
            <Textarea v-model="actionReason" rows="3" class="w-full" placeholder="Motivo obrigatório..." />
            <template #footer>
                <Button label="Cancelar" severity="secondary" text @click="showLiberarDialog = false" />
                <Button label="Liberar título" icon="pi pi-external-link" :disabled="!actionReason.trim()" @click="confirmLiberar" />
            </template>
        </Dialog>

        <Dialog v-model:visible="showExpulsarDialog" modal header="Expulsar título do borderô" :style="{ width: '480px' }">
            <p class="text-sm text-gray-600 mb-3">
                Este título será <strong>reprovado</strong>, sairá do borderô {{ bordero.number }} e voltará para <strong>Contas a Pagar pendente avulso</strong>.
                O borderô continuará em aprovação com os demais. O histórico será registrado nos comentários do título.
            </p>
            <Textarea v-model="actionReason" rows="3" class="w-full" placeholder="Motivo obrigatório..." />
            <template #footer>
                <Button label="Cancelar" severity="secondary" text @click="showExpulsarDialog = false" />
                <Button label="Expulsar título" icon="pi pi-ban" severity="danger" :disabled="!actionReason.trim()" @click="confirmExpulsar" />
            </template>
        </Dialog>

        <Dialog v-model:visible="showDesfazerDialog" modal header="Desfazer borderô" :style="{ width: '480px' }">
            <p class="text-sm text-gray-600 mb-3">
                Todos os títulos serão devolvidos para <strong>Contas a Pagar pendente avulso</strong> e este borderô será removido.
            </p>
            <Textarea v-model="desfazerReason" rows="2" class="w-full" placeholder="Motivo (opcional)..." />
            <template #footer>
                <Button label="Cancelar" severity="secondary" text @click="showDesfazerDialog = false" />
                <Button label="Desfazer borderô" icon="pi pi-undo" severity="danger" @click="confirmDesfazer" />
            </template>
        </Dialog>
    </component>
</template>
