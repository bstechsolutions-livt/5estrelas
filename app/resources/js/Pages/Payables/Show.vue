<script setup>
import { ref, computed, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import FileUpload from 'primevue/fileupload'
import Dialog from 'primevue/dialog'
import ApprovalFlowPreview from '@/Components/Financeiro/ApprovalFlowPreview.vue'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import DatePicker from 'primevue/datepicker'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import BottomSheet from '@/Components/Mobile/BottomSheet.vue'
import { useDevice } from '@/composables/useDevice'
import PayableDocumentPreviewCard from '@/Components/Financeiro/PayableDocumentPreviewCard.vue'
import DocumentViewerDialog from '@/Components/Financeiro/DocumentViewerDialog.vue'
import PayableDetailsOverview from '@/Components/Financeiro/PayableDetailsOverview.vue'
import PayableFieldOriginLabel from '@/Components/Financeiro/PayableFieldOriginLabel.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import { formatApiDate, parseApiDate } from '@/utils/apiDate'

const props = defineProps({
    payable: Object,
    statusLabels: Object,
    statusColors: Object,
    canPay: { type: Boolean, default: false },
    paymentMethods: { type: Object, default: () => ({}) },
    pagadorConfigured: { type: Boolean, default: true },
    canConciliate: { type: Boolean, default: false },
    conciliadorConfigured: { type: Boolean, default: true },
    approvalSteps: { type: Array, default: () => [] },
    currentStep: { type: Object, default: null },
    canApproveStep: { type: Boolean, default: false },
    canDelegateStep: { type: Boolean, default: false },
    delegateUsers: { type: Array, default: () => [] },
    canFinalSign: { type: Boolean, default: false },
    canEditDueDate: { type: Boolean, default: false },
    canManagePriority: { type: Boolean, default: false },
    canManagePaymentReceipt: { type: Boolean, default: false },
    requiresPriorityOnApprove: { type: Boolean, default: false },
    priorityLabels: { type: Object, default: () => ({}) },
    priorityColors: { type: Object, default: () => ({}) },
    approvalPreview: { type: Object, default: () => ({ ok: false, errors: [], steps: [] }) },
    canImportAllocations: { type: Boolean, default: false },
    canBypassApprovalDeadline: { type: Boolean, default: false },
    minDueDateForApproval: { type: String, default: null },
    maxDocumentBytes: { type: Number, default: 15 * 1024 * 1024 },
})

const maxDocumentMb = computed(() => Math.round(props.maxDocumentBytes / 1024 / 1024))

const DOCS_VIEW_STORAGE_KEY = 'payable_docs_view_mode'

function readDocsViewMode() {
    if (typeof window === 'undefined') return 'resumida'
    const cached = localStorage.getItem(DOCS_VIEW_STORAGE_KEY)
    return cached === 'detalhada' ? 'detalhada' : 'resumida'
}

const { isMobile } = useDevice()

const isAwaitingConciliation = computed(() =>
    ['pago', 'aguardando_conciliacao'].includes(props.payable?.status),
)
const docsViewMode = ref(readDocsViewMode())

function setDocsViewMode(mode) {
    const next = mode === 'detalhada' ? 'detalhada' : 'resumida'
    if (docsViewMode.value === next) return
    docsViewMode.value = next
    localStorage.setItem(DOCS_VIEW_STORAGE_KEY, next)
}
const page = usePage()
const authUser = page.props.auth?.user
const toast = useToast()
const confirm = useConfirm()

const showRejectDialog = ref(false)
const rejectForm = useForm({ reason: '' })
const approvalComment = ref('')
const showDelegateDialog = ref(false)
const delegateForm = useForm({
    step_id: null,
    delegate_user_id: null,
    expires_at: null,
    reason: '',
})
const delegateUserOptions = computed(() =>
    (props.delegateUsers || []).map(u => ({ label: u.name, value: u.id }))
)

function stepDisplayName(step) {
    if (step.delegatee?.name) {
        const original = step.assignee?.name
        return original ? `${original} → ${step.delegatee.name}` : step.delegatee.name
    }
    return step.assignee?.name || step.level_name
}

function isDelegationActive(step) {
    if (!step.delegated_to || step.status !== 'pendente') return false
    if (!step.delegation_expires_at) return true
    return new Date(step.delegation_expires_at) >= new Date()
}

function openDelegateDialog() {
    if (!props.currentStep) return
    delegateForm.step_id = props.currentStep.id
    delegateForm.delegate_user_id = props.currentStep.delegatee?.id || null
    delegateForm.expires_at = null
    delegateForm.reason = props.currentStep.delegation_reason || ''
    showDelegateDialog.value = true
}

function submitDelegate() {
    delegateForm
        .transform((data) => ({
            ...data,
            expires_at: data.expires_at ? toYmd(data.expires_at) : null,
        }))
        .post(`/financeiro/contas-pagar/${props.payable.id}/delegar-etapa`, {
            preserveScroll: true,
            onSuccess: () => {
                showDelegateDialog.value = false
                delegateForm.reset()
            },
        })
}

function revokeDelegation() {
    if (!props.currentStep?.delegated_to) return
    router.delete(`/financeiro/contas-pagar/${props.payable.id}/delegar-etapa`, {
        data: { step_id: props.currentStep.id },
        preserveScroll: true,
    })
}

// ── Registrar pagamento ──
const showPayment = ref(false)
const maxPaymentDate = new Date()
const paymentForm = useForm({ paid_at: new Date(), payment_method: null, file: null })
const paymentMethodOptions = computed(() => Object.keys(props.paymentMethods || {}))

function pad(n) { return String(n).padStart(2, '0') }

function toYmd(d) {
    const dt = parseApiDate(d)
    if (!dt) return null
    return `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}`
}

function onSelectComprovante(e) {
    paymentForm.file = e.files?.[0] || null
}

function submitPayment() {
    paymentForm
        .transform((data) => ({ ...data, paid_at: toYmd(data.paid_at) }))
        .post(`/financeiro/contas-pagar/${props.payable.id}/registrar-pagamento`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { showPayment.value = false; paymentForm.reset() },
        })
}

// ── Conciliação bancária ──
const showConciliation = ref(false)
const conciliationMode = ref(null) // 'conciliate' or 'diverge'
const conciliateForm = useForm({ notes: '' })
const divergeForm = useForm({ reason: '' })

function submitConciliation() {
    conciliateForm.post(`/financeiro/contas-pagar/${props.payable.id}/conciliar`, {
        preserveScroll: true,
        onSuccess: () => { showConciliation.value = false; conciliationMode.value = null; conciliateForm.reset() },
    })
}
function submitDivergence() {
    divergeForm.post(`/financeiro/contas-pagar/${props.payable.id}/divergencia`, {
        preserveScroll: true,
        onSuccess: () => { showConciliation.value = false; conciliationMode.value = null; divergeForm.reset() },
    })
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Pronto', detail: msg, life: 3000 })
})
watch(() => page.props.flash?.error, (msg) => {
    if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 })
})
watch(() => page.props.flash?.warning, (msg) => {
    if (msg) toast.add({ severity: 'warn', summary: 'Atenção', detail: msg, life: 6000 })
})

function uploadDoc(event, type = 'outro') {
    const files = [...(event.files || [])]
    if (!files.length) return
    const formData = new FormData()
    files.forEach((file) => formData.append('files[]', file))
    formData.append('type', type)
    router.post(`/financeiro/contas-pagar/${props.payable.id}/documentos`, formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => markBorderoStale(),
    })
}

function uploadPaymentReceipt(event) {
    const file = event.files?.[0]
    if (!file) return

    const formData = new FormData()
    formData.append('file', file)
    router.post(`/financeiro/contas-pagar/${props.payable.id}/comprovante-pagamento`, formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => markBorderoStale(),
    })
}

function confirmRemovePaymentReceipt() {
    confirm.require({
        message: 'Remover o comprovante de pagamento deste título? O arquivo será excluído.',
        header: 'Remover comprovante',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Remover', severity: 'danger' },
        accept: () => {
            router.delete(`/financeiro/contas-pagar/${props.payable.id}/comprovante-pagamento`, {
                preserveScroll: true,
                onSuccess: () => markBorderoStale(),
            })
        },
    })
}

// Tipos de documento (feedback do cliente): lista separada por tipo.
const DOC_TYPES = [
    { key: 'nota_fiscal', label: 'Notas Fiscais', icon: 'pi-file-edit' },
    { key: 'boleto', label: 'Boletos', icon: 'pi-money-bill' },
    { key: 'relatorio', label: 'Relatórios', icon: 'pi-chart-bar' },
    { key: 'comprovacao', label: 'Comprovante de pagamento', icon: 'pi-check-circle' },
    { key: 'outro', label: 'Outros', icon: 'pi-file' },
]
const KNOWN_DOC_TYPES = ['nota_fiscal', 'boleto', 'relatorio', 'comprovacao']
function docsByType(typeKey) {
    const docs = props.payable.documents || []
    if (typeKey === 'outro') {
        return docs.filter(d => !d.doc_type || !KNOWN_DOC_TYPES.includes(d.doc_type))
    }
    return docs.filter(d => d.doc_type === typeKey)
}

function removeDoc(docId) {
    router.delete(`/financeiro/contas-pagar/${props.payable.id}/documentos/${docId}`, {
        preserveScroll: true,
        onSuccess: () => markBorderoStale(),
    })
}

const allocationTotal = computed(() => {
    const lines = props.payable.allocation_lines || []
    return lines.reduce((sum, line) => sum + Number(line.amount || 0), 0)
})

function uploadAllocation(event) {
    const file = event.files?.[0]
    if (!file) return
    const formData = new FormData()
    formData.append('file', file)
    router.post(`/financeiro/contas-pagar/${props.payable.id}/rateio/importar`, formData, {
        preserveScroll: true,
        forceFormData: true,
    })
}

const showPriorityDialog = ref(false)
const approvePriorityForm = useForm({
    payment_priority: 'normal',
    payment_sla_date: null,
})

function defaultSlaDate() {
    return parseApiDate(props.payable.payment_sla_date) ?? parseApiDate(props.payable.due_date)
}

const priorityOptions = computed(() =>
    Object.entries(props.priorityLabels || {}).map(([value, label]) => ({ value, label }))
)

function openApprove() {
    if (props.requiresPriorityOnApprove) {
        approvePriorityForm.payment_priority = props.payable.payment_priority || 'normal'
        approvePriorityForm.payment_sla_date = defaultSlaDate()
        showPriorityDialog.value = true
        return
    }
    approve()
}

function confirmApproveWithPriority() {
    approvePriorityForm
        .transform((data) => ({
            payment_priority: data.payment_priority,
            payment_sla_date: data.payment_sla_date ? toYmd(data.payment_sla_date) : null,
            comment: approvalComment.value.trim() || undefined,
        }))
        .post(`/financeiro/contas-pagar/${props.payable.id}/aprovar`, {
            preserveScroll: true,
            onSuccess: () => {
                showPriorityDialog.value = false
                approvalComment.value = ''
                window.dispatchEvent(new Event('notifications:refresh'))
            },
        })
}

function approve() {
    router.post(`/financeiro/contas-pagar/${props.payable.id}/aprovar`, {
        comment: approvalComment.value.trim() || undefined,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            approvalComment.value = ''
            window.dispatchEvent(new Event('notifications:refresh'))
        },
    })
}

// Reprovar: se já escreveu o comentário na caixa do fluxo, usa direto como motivo.
// Senão, abre o dialog pedindo a justificativa (motivo é obrigatório).
function openReject() {
    const text = approvalComment.value.trim()
    if (text) {
        rejectForm.reason = text
        reject()
        return
    }
    showRejectDialog.value = true
}

function reject() {
    rejectForm.post(`/financeiro/contas-pagar/${props.payable.id}/reprovar`, {
        preserveScroll: true,
        onSuccess: () => { showRejectDialog.value = false; rejectForm.reset(); approvalComment.value = '' },
    })
}

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}

function formatCommentBody(body) {
    if (!body) return ''
    // Transforma @[Nome](id:N) em badge inline
    return body.replace(/@\[([^\]]+)\]\((?:id:)?\d+\)/g, '<span class="inline-flex items-center px-1.5 py-0.5 bg-blue-100 text-blue-700 text-xs font-medium rounded-full">@$1</span>')
}
function formatDate(d) {
    return formatApiDate(d)
}
function formatDateTime(d) {
    if (!d) return '—'
    return new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

const canPrepare = ['pendente', 'em_preparacao'].includes(props.payable.status)
// Se está num borderô, não pode enviar individual — o borderô controla o envio
const inBordero = !!props.payable.bordero_id
const canSendIndividual = canPrepare && !inBordero
const canApprove = props.payable.status === 'aguardando_aprovacao' && !inBordero && !props.approvalSteps?.length
const timelineEntries = computed(() =>
    (props.payable.comments || []).filter(comment => comment.type !== 'comment'),
)

// Trava (feedback do cliente): não envia pra aprovação sem ao menos 1 documento.
const hasDocuments = computed(() => (props.payable.documents?.length || 0) > 0)
const urgentBypass = ref(false)

const minApprovalDue = computed(() => parseApiDate(props.minDueDateForApproval))
const approvalDeadlineBlocked = computed(() => {
    const due = parseApiDate(props.payable.due_date)
    if (!due || !minApprovalDue.value) return false
    return due < minApprovalDue.value
})

const wasRejectedBack = computed(() =>
    props.payable.status === 'pendente' && !!props.payable.rejection_reason
)

// Envio usa o departamento do título (preview em approvalPreview).
function sendForApproval() {
    router.post(`/financeiro/contas-pagar/${props.payable.id}/enviar-aprovacao`, {
        urgente: urgentBypass.value && props.canBypassApprovalDeadline,
    }, { preserveScroll: true })
}

const canSubmitApproval = computed(() =>
    props.approvalPreview?.ok && hasDocuments.value &&
    (!approvalDeadlineBlocked.value || (props.canBypassApprovalDeadline && urgentBypass.value))
)

// Sidebar de ações aparece quando há qualquer ação/condição lateral a mostrar.
const showSidebar = computed(() =>
    canSendIndividual || canApprove || inBordero || showRejectDialog.value ||
    props.approvalSteps?.length || timelineEntries.value.length ||
    props.canPay || isAwaitingConciliation.value ||
    (props.payable.status === 'aprovado' && !props.pagadorConfigured) ||
    props.canConciliate ||
    props.payable.status === 'conciliado' || props.payable.status === 'divergente' ||
    (isAwaitingConciliation.value && !props.conciliadorConfigured) ||
    props.canManagePriority || !!props.payable.payment_priority ||
    !!(props.payable.preparer || props.payable.approver || props.payable.approved_at)
)

function markBorderoStale() {
    if (props.payable.bordero_id) {
        sessionStorage.setItem(`bordero-${props.payable.bordero_id}-stale`, '1')
    }
}

function goToBordero() {
    if (!props.payable.bordero_id) return
    sessionStorage.removeItem(`bordero-${props.payable.bordero_id}-stale`)
    router.visit(`/financeiro/borderos/${props.payable.bordero_id}`, {
        preserveState: false,
        preserveScroll: false,
    })
}

function goBack() {
    if (props.payable.bordero_id) {
        goToBordero()
        return
    }
    window.history.back()
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1048576).toFixed(1) + ' MB'
}

function isImage(doc) {
    return doc.mime_type?.startsWith('image/')
}

// ── A2: visualizador de anexo inline (feedback do cliente: abrir na mesma
// página, não em outra aba, com opção de voltar). ──
const showViewer = ref(false)
const viewerInitialDocId = ref(null)
function openViewer(doc) {
    viewerInitialDocId.value = doc?.id ?? null
    showViewer.value = true
}

// ── A4: edição de vencimento (restrita ao financeiro) ──
const showDueDate = ref(false)
const dueDateForm = useForm({ due_date: parseApiDate(props.payable.due_date) ?? new Date() })
function submitDueDate() {
    dueDateForm
        .transform((data) => ({ due_date: toYmd(data.due_date) }))
        .post(`/financeiro/contas-pagar/${props.payable.id}/vencimento`, {
            preserveScroll: true,
            onSuccess: () => { showDueDate.value = false },
        })
}

const priorityForm = useForm({
    payment_priority: props.payable.payment_priority || 'normal',
    payment_sla_date: defaultSlaDate(),
})

function submitPriority() {
    priorityForm
        .transform((data) => ({
            payment_priority: data.payment_priority,
            payment_sla_date: data.payment_sla_date ? toYmd(data.payment_sla_date) : null,
        }))
        .post(`/financeiro/contas-pagar/${props.payable.id}/prioridade`, { preserveScroll: true })
}

const slaAlertClass = computed(() => {
    if (props.payable.sla_status === 'overdue') return 'text-red-600'
    if (props.payable.sla_status === 'warning') return 'text-amber-600'
    return 'text-gray-600'
})

const fieldOrigins = computed(() => props.payable.field_origins ?? null)
const isFromSenior = computed(() => !!props.payable.origem_senior)

const naturezaGasto = computed(() => {
    const ng = props.payable.codntg
    if (ng !== null && ng !== undefined && ng !== '') {
        return String(ng)
    }
    const tns = props.payable.codtns
    if (tns !== null && tns !== undefined && tns !== '') {
        return String(tns)
    }
    return null
})

const centroCusto = computed(() => {
    return props.payable.centro_custo_label
        || (props.payable.codccu !== null && props.payable.codccu !== undefined && props.payable.codccu !== ''
            ? String(props.payable.codccu)
            : null)
})

const contaFinanceira = computed(() => {
    return props.payable.conta_financeira_label
        || (props.payable.ctafin ? String(props.payable.ctafin) : null)
})

const departamentoTitulo = computed(() => props.payable.department_nome || null)
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout" :title="isMobile ? 'Detalhe' : undefined" :show-back="isMobile">
        <Toast />
        <ConfirmDialog />
        <div :class="isMobile ? 'px-4 py-3 pb-20' : 'max-w-5xl mx-auto'">
            <!-- Header -->
            <div class="flex items-start justify-between mb-6">
                <div>
                    <button @click="goBack" class="text-sm text-blue-600 hover:underline mb-2 flex items-center gap-1 cursor-pointer">
                        <i class="pi pi-arrow-left text-xs"></i> Voltar para lista
                    </button>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                            {{ payable.title_number || payable.nickname || 'Título' }}
                        </h1>
                        <i
                            v-if="fieldOrigins?.title_number === 'senior'"
                            class="pi pi-cloud-download text-sm text-slate-400"
                            title="Número do título preenchido automaticamente pela Senior (ERP)"
                            aria-label="Número do título preenchido automaticamente pela Senior (ERP)"
                        />
                        <Tag :value="statusLabels[payable.status]" :severity="statusColors[payable.status]" />
                        <i
                            v-if="fieldOrigins?.status === 'hub'"
                            class="pi pi-pencil text-[10px] text-blue-400"
                            title="Status do fluxo de aprovação na intranet Hub"
                            aria-label="Status do fluxo de aprovação na intranet Hub"
                        />
                        <Tag
                            v-if="payable.payment_priority"
                            :value="payable.priority_label || priorityLabels[payable.payment_priority]"
                            :severity="priorityColors[payable.payment_priority] || 'secondary'"
                            class="!text-xs"
                            dusk="payable-priority-badge"
                        />
                        <Tag v-if="payable.origem_hub" value="Hub" severity="info" class="!text-xs" dusk="origem-hub-badge" title="Criado na intranet (não importado da Senior)" />
                        <Tag v-if="isFromSenior" value="Senior" severity="secondary" class="!text-xs" dusk="origem-senior-badge" title="Importado da Senior (ERP)" />
                    </div>
                    <p class="text-sm text-gray-500 mt-1 flex flex-wrap items-center gap-x-1">
                        <PayableFieldOriginLabel v-if="fieldOrigins" label="Vencimento:" field="due_date" :field-origins="fieldOrigins" class="!inline-flex shrink-0" />
                        <span v-else class="text-sm text-gray-500">Vencimento:</span>
                        <span>{{ formatDate(payable.due_date) }}</span>
                        <button v-if="canEditDueDate" @click="showDueDate = true" dusk="btn-edit-due-date"
                            class="ml-1 text-blue-600 hover:text-blue-800 cursor-pointer align-middle" title="Alterar vencimento (financeiro)">
                            <i class="pi pi-pencil text-xs"></i>
                        </button>
                    </p>
                    <p v-if="payable.filial_label" class="text-sm text-gray-600 mt-1 flex items-center gap-1.5" dusk="payable-filial-label">
                        <i class="pi pi-building text-xs text-gray-400" aria-hidden="true" />
                        <PayableFieldOriginLabel v-if="fieldOrigins" label="" field="filial_nome" :field-origins="fieldOrigins" class="!inline-flex shrink-0" />
                        <span class="font-medium text-gray-700">{{ payable.filial_label }}</span>
                    </p>
                </div>
                <div class="text-right">
                    <PayableFieldOriginLabel v-if="fieldOrigins" label="Valor" field="amount" :field-origins="fieldOrigins" class="justify-end mb-0.5" />
                    <p class="text-xl font-bold text-gray-800">{{ formatMoney(payable.amount) }}</p>
                </div>
            </div>

            <div
                v-if="payable.description"
                class="bg-white border border-gray-100 rounded-xl p-4 mb-4"
                dusk="payable-observacao"
            >
                <PayableFieldOriginLabel v-if="fieldOrigins" label="Observação" field="description" :field-origins="fieldOrigins" class="mb-1" />
                <p v-else class="text-xs font-medium text-gray-500 mb-1">Observação</p>
                <p class="text-sm text-gray-800 leading-relaxed">{{ payable.description }}</p>
            </div>

            <div
                v-if="isFromSenior"
                class="bg-slate-50 border border-slate-200 rounded-xl p-3 mb-4 text-sm text-slate-700"
                dusk="origem-senior-legend"
            >
                <p class="font-medium text-slate-800 mb-1 flex items-center gap-2">
                    <i class="pi pi-info-circle text-slate-500"></i>
                    Campos importados da Senior
                </p>
                <p class="text-xs text-slate-600">
                    <i class="pi pi-cloud-download text-[10px] text-slate-400"></i>
                    <span class="ml-1">Dados do ERP (fornecedor, valor, vencimento, nº título, empresa)</span>
                    <span class="mx-2">·</span>
                    <i class="pi pi-pencil text-[10px] text-blue-400"></i>
                    <span class="ml-1">Campos da intranet (prioridade, SLA, anexos, comentários, status)</span>
                </p>
            </div>

            <div
                v-if="payable.document_pair_alert"
                class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4"
                dusk="payable-doc-pair-alert"
            >
                <h3 class="text-sm font-semibold text-amber-800 mb-1 flex items-center gap-2">
                    <i class="pi pi-exclamation-triangle"></i>
                    Documentação incompleta
                </h3>
                <p class="text-sm text-amber-700">{{ payable.document_pair_alert.message }}</p>
                <p class="text-xs text-amber-600 mt-1">Anexe o par boleto + nota fiscal antes de enviar para aprovação.</p>
            </div>

            <div
                v-if="wasRejectedBack"
                class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4"
                dusk="payable-rejection-alert"
            >
                <h3 class="text-sm font-semibold text-red-800 mb-1 flex items-center gap-2">
                    <i class="pi pi-exclamation-triangle"></i>
                    Título recusado — precisa ser corrigido e reenviado
                </h3>
                <p class="text-sm text-red-700">{{ payable.rejection_reason }}</p>
            </div>

            <PayableDetailsOverview
                class="mb-4"
                :payable="payable"
                :field-origins="fieldOrigins"
                :natureza-gasto="naturezaGasto"
                :centro-custo="centroCusto"
                :conta-financeira="contaFinanceira"
                :departamento-titulo="departamentoTitulo"
                :can-manage-priority="canManagePriority"
                :priority-form="priorityForm"
                :priority-options="priorityOptions"
                :priority-colors="priorityColors"
                :sla-alert-class="slaAlertClass"
                :format-date="formatDate"
                @save-priority="submitPriority"
            />

            <div :class="isMobile ? 'space-y-4' : (showSidebar) ? 'grid grid-cols-3 gap-6' : ''">
                <!-- Coluna principal -->
                <div :class="isMobile ? '' : (showSidebar) ? 'col-span-2 space-y-4' : 'space-y-4'">
                    <!-- Documentos -->
                    <div class="bg-white rounded-xl border border-gray-100 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                                    Documentos ({{ payable.documents?.length || 0 }})
                                    <i
                                        v-if="fieldOrigins?.documents === 'hub'"
                                        class="pi pi-pencil text-[10px] text-blue-400"
                                        title="Anexos gerenciados na intranet Hub"
                                        aria-label="Anexos gerenciados na intranet Hub"
                                    />
                                </h3>
                                <p v-if="payable.documents?.length" class="text-[11px] text-gray-400 mt-0.5">
                                    Abra qualquer documento para analisar todos na mesma galeria.
                                </p>
                            </div>
                            <div class="flex rounded-lg border border-gray-200 bg-gray-50 p-0.5" dusk="payable-docs-view-toggle">
                                <button
                                    type="button"
                                    :class="['px-2.5 py-1 rounded-md text-xs font-medium transition-colors', docsViewMode === 'resumida' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-white']"
                                    @click="setDocsViewMode('resumida')"
                                >
                                    Resumida
                                </button>
                                <button
                                    type="button"
                                    :class="['px-2.5 py-1 rounded-md text-xs font-medium transition-colors', docsViewMode === 'detalhada' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-white']"
                                    @click="setDocsViewMode('detalhada')"
                                >
                                    Detalhada
                                </button>
                            </div>
                        </div>
                        <p v-if="!payable.documents?.length" class="text-sm text-gray-400 mb-4">Nenhum documento anexado.</p>
                        <div class="space-y-3">
                            <div v-for="t in DOC_TYPES" :key="t.key" v-show="canPrepare || (t.key === 'comprovacao' && canManagePaymentReceipt) || docsByType(t.key).length"
                                :dusk="`doc-section-${t.key}`" class="border border-gray-100 rounded-lg p-3">
                                <h4 class="text-xs font-semibold text-gray-600 flex items-center gap-1.5 mb-2">
                                    <i :class="['pi', t.icon, 'text-gray-400']"></i> {{ t.label }}
                                    <span class="text-gray-400 font-normal">({{ docsByType(t.key).length }})</span>
                                </h4>

                                <!-- Visão resumida: lista com ações -->
                                <div v-if="docsViewMode === 'resumida' && docsByType(t.key).length" class="space-y-2 mb-2">
                                    <div v-for="doc in docsByType(t.key)" :key="doc.id"
                                        class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                                        <button type="button" @click="openViewer(doc)" dusk="doc-open"
                                            class="flex items-center gap-2 min-w-0 flex-1 group text-left cursor-pointer bg-transparent border-0 p-0">
                                            <i :class="['pi', isImage(doc) ? 'pi-image' : doc.mime_type === 'application/pdf' ? 'pi-file-pdf' : 'pi-file', 'text-gray-400']"></i>
                                            <div class="min-w-0">
                                                <p class="text-sm text-gray-800 truncate group-hover:text-blue-600 group-hover:underline">{{ doc.name }}</p>
                                                <p class="text-[11px] text-gray-400">{{ formatSize(doc.size) }} · {{ doc.uploader?.name }}</p>
                                            </div>
                                        </button>
                                        <div class="flex items-center gap-1 flex-shrink-0">
                                            <button type="button" @click="openViewer(doc)"
                                                class="text-gray-400 hover:text-blue-600 p-1.5 cursor-pointer bg-transparent border-0" title="Visualizar">
                                                <i class="pi pi-eye"></i>
                                            </button>
                                            <a :href="doc.url" :download="doc.name"
                                                class="text-gray-400 hover:text-blue-600 p-1.5 cursor-pointer" title="Baixar">
                                                <i class="pi pi-download"></i>
                                            </a>
                                            <button
                                                v-if="t.key === 'comprovacao' ? canManagePaymentReceipt : canPrepare"
                                                @click="t.key === 'comprovacao' ? confirmRemovePaymentReceipt() : removeDoc(doc.id)"
                                                class="text-red-400 hover:text-red-600 p-1.5 cursor-pointer"
                                                title="Remover"
                                            >
                                                <i class="pi pi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Visão detalhada: previews inline -->
                                <div v-else-if="docsViewMode === 'detalhada' && docsByType(t.key).length" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-2">
                                    <PayableDocumentPreviewCard
                                        v-for="doc in docsByType(t.key)"
                                        :key="doc.id"
                                        :doc="doc"
                                        :type-label="t.label.replace(/s$/, '')"
                                        :can-remove="t.key === 'comprovacao' ? canManagePaymentReceipt : canPrepare"
                                        @open="openViewer"
                                        @remove="(id) => t.key === 'comprovacao' ? confirmRemovePaymentReceipt() : removeDoc(id)"
                                    />
                                </div>

                                <p v-else-if="!docsByType(t.key).length" class="text-[11px] text-gray-400 mb-2">Nenhum anexo deste tipo.</p>
                                <FileUpload
                                    v-if="t.key === 'comprovacao' && canManagePaymentReceipt"
                                    mode="basic"
                                    :auto="true"
                                    :choose-label="docsByType('comprovacao').length ? 'Substituir comprovante' : 'Adicionar comprovante'"
                                    :max-file-size="maxDocumentBytes"
                                    @select="uploadPaymentReceipt"
                                    class="w-full"
                                    dusk="payment-receipt-upload"
                                    invalid-file-size-message="O arquivo é muito grande. O tamanho máximo permitido é {1}."
                                />
                                <FileUpload v-else-if="canPrepare && t.key !== 'comprovacao'" mode="basic" multiple :auto="true" :choose-label="`Anexar ${t.label}`"
                                    :max-file-size="maxDocumentBytes" @select="(e) => uploadDoc(e, t.key)" class="w-full"
                                    invalid-file-size-message="O arquivo é muito grande. O tamanho máximo permitido é {1}." />
                                <p v-if="t.key === 'comprovacao' && canManagePaymentReceipt" class="text-[11px] text-gray-400 mt-1">
                                    Disponível em qualquer status. Um novo arquivo substitui o comprovante atual.
                                </p>
                                <p v-else-if="canPrepare && t.key !== 'comprovacao'" class="text-[11px] text-gray-400 mt-1">Ctrl ou Shift para selecionar vários arquivos de uma vez.</p>
                            </div>
                        </div>
                        <p v-if="canPrepare || canManagePaymentReceipt" class="text-[11px] text-gray-400 mt-2 text-center">Tamanho máximo por arquivo: {{ maxDocumentMb }} MB.</p>
                    </div>

                    <!-- Rateio (planilha) -->
                    <div class="bg-white rounded-xl border border-gray-100 p-4" dusk="payable-allocation-section">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                                Rateio para conciliação
                                <i class="pi pi-table text-[10px] text-blue-400" title="Detalhamento importado por planilha" />
                            </h3>
                            <FileUpload
                                v-if="canImportAllocations"
                                mode="basic"
                                :auto="true"
                                accept=".xlsx,.xls,.csv"
                                choose-label="Importar planilha"
                                :max-file-size="maxDocumentBytes"
                                @select="uploadAllocation"
                                dusk="payable-allocation-upload"
                            />
                        </div>
                        <p class="text-xs text-gray-500 mb-3">
                            Use quando um título no Senior cobre vários pagamentos (ex.: folha/quadro fixo).
                            Reimportar substitui o rateio anterior.
                        </p>
                        <p v-if="!payable.allocation_lines?.length" class="text-sm text-gray-400">
                            Nenhum rateio importado.
                        </p>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
                                        <th class="py-2 pr-2">#</th>
                                        <th class="py-2 pr-2">Nome</th>
                                        <th class="py-2 pr-2">CPF</th>
                                        <th class="py-2 pr-2">Função</th>
                                        <th class="py-2 pr-2">PIX</th>
                                        <th class="py-2 pr-2 text-right">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="line in payable.allocation_lines"
                                        :key="line.id"
                                        class="border-b border-gray-50"
                                        dusk="payable-allocation-line"
                                    >
                                        <td class="py-2 pr-2 text-gray-500">{{ line.line_order ?? '—' }}</td>
                                        <td class="py-2 pr-2">{{ line.person_name || '—' }}</td>
                                        <td class="py-2 pr-2 font-mono text-xs">{{ line.document_id || '—' }}</td>
                                        <td class="py-2 pr-2">{{ line.role_label || '—' }}</td>
                                        <td class="py-2 pr-2 text-xs">{{ line.pix_key || '—' }}</td>
                                        <td class="py-2 pr-2 text-right font-medium">{{ formatMoney(line.amount) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="text-sm font-semibold text-gray-700">
                                        <td colspan="5" class="py-2 text-right">Total</td>
                                        <td class="py-2 text-right">{{ formatMoney(allocationTotal) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                            <p v-if="payable.allocation_source_file" class="text-[11px] text-gray-400 mt-2">
                                Arquivo: {{ payable.allocation_source_file }}
                                <span v-if="payable.allocation_importer"> · {{ payable.allocation_importer.name }}</span>
                                <span v-if="payable.allocation_imported_at"> · {{ formatDateTime(payable.allocation_imported_at) }}</span>
                            </p>
                        </div>
                    </div>

                </div>

                <!-- Sidebar de ações -->
                <div v-if="showSidebar" class="space-y-4">
                    <!-- Aviso: está em borderô -->
                    <div v-if="inBordero" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <h3 class="text-sm font-semibold text-amber-700 mb-1 flex items-center gap-2">
                            <i class="pi pi-list-check"></i>
                            Em um borderô
                            <i
                                v-if="fieldOrigins?.bordero === 'hub'"
                                class="pi pi-pencil text-[10px] text-blue-400"
                                title="Borderô gerenciado na intranet Hub"
                                aria-label="Borderô gerenciado na intranet Hub"
                            />
                        </h3>
                        <p class="text-xs text-amber-600 mb-2">Este título faz parte de um borderô. O envio é feito pelo borderô; a aprovação segue o mesmo fluxo configurado (pode ser feita aqui ou no borderô).</p>
                        <Button label="Ver borderô" icon="pi pi-arrow-right" size="small" outlined class="w-full" @click="goToBordero" />
                    </div>

                    <!-- Ações do preparador (só se NÃO está em borderô) -->
                    <div v-if="canSendIndividual" class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Ações</h3>
                        <ApprovalFlowPreview :preview="approvalPreview" class="mb-3" />
                        <Button
                            label="Enviar para Aprovação"
                            icon="pi pi-send"
                            class="w-full"
                            dusk="btn-send-approval"
                            :disabled="!canSubmitApproval"
                            @click="sendForApproval"
                        />
                        <p v-if="approvalDeadlineBlocked && !canBypassApprovalDeadline" class="text-[11px] text-amber-600 text-center mt-2 flex items-center justify-center gap-1">
                            <i class="pi pi-exclamation-triangle text-[10px]"></i>
                            Vencimento antes do prazo de 72h (mín. {{ formatDate(minDueDateForApproval) }}). Aguarde ou solicite ao financeiro.
                        </p>
                        <label
                            v-else-if="approvalDeadlineBlocked && canBypassApprovalDeadline"
                            class="flex items-start gap-2 mt-2 text-[11px] text-amber-700 cursor-pointer"
                        >
                            <input v-model="urgentBypass" type="checkbox" class="mt-0.5" dusk="urgent-approval-bypass" />
                            <span>Enviar mesmo assim (urgência — fora do prazo de 72h)</span>
                        </label>
                        <p v-if="!hasDocuments" dusk="no-docs-hint" class="text-[11px] text-amber-600 text-center mt-2 flex items-center justify-center gap-1">
                            <i class="pi pi-exclamation-triangle text-[10px]"></i> Anexe ao menos um documento para enviar.
                        </p>
                    </div>

                    <!-- Ações do aprovador (workflow multinível) -->
                    <div v-if="payable.status === 'aguardando_aprovacao' && approvalSteps?.length" class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Fluxo de Aprovação</h3>
                        <!-- Stepper visual -->
                        <div class="space-y-2 mb-4">
                            <div v-for="step in approvalSteps" :key="step.id" class="flex items-start gap-2">
                                <div class="mt-0.5">
                                    <i v-if="step.status === 'aprovado'" class="pi pi-check-circle text-green-500 text-sm"></i>
                                    <i v-else-if="step.status === 'reprovado'" class="pi pi-times-circle text-red-500 text-sm"></i>
                                    <i v-else-if="currentStep?.id === step.id" class="pi pi-circle-fill text-blue-500 text-sm"></i>
                                    <i v-else class="pi pi-circle text-gray-300 text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium" :class="step.status === 'aprovado' ? 'text-green-700' : step.status === 'reprovado' ? 'text-red-700' : currentStep?.id === step.id ? 'text-blue-700' : 'text-gray-500'">
                                        {{ stepDisplayName(step) }}
                                    </p>
                                    <p v-if="isDelegationActive(step)" class="text-[10px] text-amber-600">
                                        Delegado{{ step.delegation_expires_at ? ` até ${formatDate(step.delegation_expires_at)}` : '' }}
                                        <span v-if="step.delegation_set_by"> por {{ step.delegation_set_by.name }}</span>
                                    </p>
                                    <p v-if="step.resolver" class="text-[10px] text-gray-400">
                                        {{ step.status === 'aprovado' ? 'Aprovado' : 'Reprovado' }} por {{ step.resolver.name }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <!-- Botões de ação se o usuário pode aprovar o step atual -->
                        <div v-if="canApproveStep" class="space-y-2">
                            <p class="text-xs text-blue-600 font-medium mb-2">Sua vez: {{ currentStep?.level_name }}</p>
                            <Textarea
                                v-model="approvalComment"
                                placeholder="Comentário (opcional ao aprovar, vira o motivo ao reprovar)"
                                rows="2"
                                class="w-full"
                                dusk="approval-comment"
                            />
                            <Button label="Aprovar" icon="pi pi-check" severity="success" class="w-full" @click="openApprove" />
                            <Button label="Reprovar" icon="pi pi-times" severity="danger" outlined class="w-full" @click="openReject" />
                            <p class="text-[10px] text-gray-400">O comentário aparece na timeline em <span class="text-green-600 font-medium">verde</span> se aprovar ou <span class="text-red-600 font-medium">vermelho</span> se reprovar.</p>
                        </div>
                        <div v-if="canDelegateStep" class="space-y-2 mt-3 pt-3 border-t border-gray-100">
                            <p class="text-[11px] text-gray-500">Aprovador indisponível? Indique quem aprova temporariamente nesta etapa.</p>
                            <Button
                                :label="isDelegationActive(currentStep) ? 'Alterar substituto' : 'Delegar aprovação'"
                                icon="pi pi-user-plus"
                                severity="secondary"
                                outlined
                                class="w-full"
                                size="small"
                                @click="openDelegateDialog"
                            />
                            <Button
                                v-if="isDelegationActive(currentStep)"
                                label="Remover delegação"
                                icon="pi pi-user-minus"
                                severity="secondary"
                                text
                                class="w-full"
                                size="small"
                                @click="revokeDelegation"
                            />
                        </div>
                    </div>

                    <!-- Aprovação antiga (fallback se não tem steps) -->
                    <div v-else-if="canApprove && !approvalSteps?.length" class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Aprovação</h3>
                        <div class="space-y-2">
                            <Textarea
                                v-model="approvalComment"
                                placeholder="Comentário (opcional ao aprovar, vira o motivo ao reprovar)"
                                rows="2"
                                class="w-full"
                            />
                            <Button label="Aprovar" icon="pi pi-check" severity="success" class="w-full" @click="openApprove" />
                            <Button label="Reprovar" icon="pi pi-times" severity="danger" outlined class="w-full" @click="openReject" />
                        </div>
                    </div>

                    <!-- Dialog de reprovação -->
                    <div v-if="showRejectDialog" class="bg-white rounded-xl border border-red-200 p-4">
                        <h3 class="text-sm font-semibold text-red-700 mb-2">Motivo da reprovação</h3>
                        <Textarea v-model="rejectForm.reason" placeholder="Justifique..." rows="3" class="w-full mb-2" />
                        <div class="flex gap-2">
                            <Button label="Cancelar" severity="secondary" size="small" @click="showRejectDialog = false" class="flex-1" />
                            <Button label="Confirmar" severity="danger" size="small" @click="reject" :disabled="!rejectForm.reason.trim()" class="flex-1" />
                        </div>
                    </div>

                    <!-- Timeline automática, próxima das ações de aprovação -->
                    <div class="bg-white rounded-xl border border-gray-100 p-4" dusk="payable-timeline">
                        <h3 class="text-sm font-semibold text-gray-700 mb-1 flex items-center gap-1.5">
                            <i class="pi pi-history text-gray-400"></i>
                            Timeline
                        </h3>
                        <p class="text-[10px] text-gray-400 mb-3">
                            Histórico automático. Observações são registradas somente ao aprovar ou reprovar.
                        </p>
                        <div class="space-y-3 max-h-80 overflow-y-auto">
                            <div v-for="c in timelineEntries" :key="c.id" class="flex gap-2">
                                <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 text-xs font-semibold text-blue-600">
                                    {{ c.user?.name?.charAt(0) || 'S' }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-gray-500">
                                        <span class="font-medium text-gray-700">{{ c.user?.name || 'Sistema' }}</span>
                                        · {{ formatDateTime(c.created_at) }}
                                    </p>
                                    <p
                                        :class="['text-sm mt-0.5', c.type === 'rejection' ? 'text-red-600' : c.type === 'approval' ? 'text-green-600' : 'text-gray-700']"
                                        v-html="formatCommentBody(c.body)"
                                    ></p>
                                </div>
                            </div>
                            <p v-if="!timelineEntries.length" class="text-sm text-gray-400">Nenhuma atividade automática.</p>
                        </div>
                    </div>

                    <!-- Ação: registrar pagamento (governada pela alçada) -->
                    <div v-if="canPay" class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Pagamento</h3>
                        <Button label="Registrar pagamento" icon="pi pi-dollar" severity="success" class="w-full" dusk="open-payment" @click="showPayment = true" />
                    </div>

                    <!-- Hint: alçada de pagamento não configurada -->
                    <div v-else-if="payable.status === 'aprovado' && !pagadorConfigured" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <h3 class="text-sm font-semibold text-amber-700 mb-1 flex items-center gap-2"><i class="pi pi-exclamation-triangle"></i> Alçada não configurada</h3>
                        <p class="text-xs text-amber-600">Defina um pagador na alçada do Contas a Pagar para registrar pagamentos.</p>
                    </div>

                    <!-- Pagamento registrado (read-only) -->
                    <div v-if="isAwaitingConciliation" class="bg-white rounded-xl border border-gray-100 p-4 text-sm" dusk="payment-info">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">Pagamento</h3>
                        <div class="mb-2"><p class="text-xs text-gray-500">Data</p><p class="text-gray-800">{{ formatDate(payable.paid_at) }}</p></div>
                        <div v-if="payable.payment_method" class="mb-2"><p class="text-xs text-gray-500">Forma</p><p class="text-gray-800">{{ payable.payment_method }}</p></div>
                        <div v-if="payable.payer"><p class="text-xs text-gray-500">Pago por</p><p class="text-gray-800">{{ payable.payer.name }}</p></div>
                    </div>

                    <!-- Ação: conciliar (governada pela alçada) -->
                    <div v-if="canConciliate" class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Conciliação</h3>
                        <Button label="Conciliar" icon="pi pi-check-circle" severity="info" class="w-full" dusk="open-conciliation" @click="showConciliation = true" />
                    </div>

                    <!-- Hint: alçada de conciliação não configurada -->
                    <div v-else-if="isAwaitingConciliation && !conciliadorConfigured" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <h3 class="text-sm font-semibold text-amber-700 mb-1 flex items-center gap-2"><i class="pi pi-exclamation-triangle"></i> Alçada não configurada</h3>
                        <p class="text-xs text-amber-600">Defina um conciliador na alçada do Contas a Pagar para conciliar pagamentos.</p>
                    </div>

                    <!-- Conciliação read-only -->
                    <div v-if="payable.status === 'conciliado'" class="bg-white rounded-xl border border-gray-100 p-4 text-sm" dusk="conciliation-info">
                        <h3 class="text-sm font-semibold text-green-700 mb-2 flex items-center gap-2"><i class="pi pi-check-circle"></i> Conciliação</h3>
                        <div class="mb-2"><p class="text-xs text-gray-500">Data</p><p class="text-gray-800">{{ formatDate(payable.conciliated_at) }}</p></div>
                        <div v-if="payable.conciliator" class="mb-2"><p class="text-xs text-gray-500">Conciliado por</p><p class="text-gray-800">{{ payable.conciliator.name }}</p></div>
                        <div v-if="payable.conciliation_notes"><p class="text-xs text-gray-500">Observação</p><p class="text-gray-800">{{ payable.conciliation_notes }}</p></div>
                    </div>

                    <!-- Divergência read-only -->
                    <div v-if="payable.status === 'divergente'" class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm" dusk="divergence-info">
                        <h3 class="text-sm font-semibold text-red-700 mb-2 flex items-center gap-2"><i class="pi pi-exclamation-circle"></i> Divergência</h3>
                        <div class="mb-2"><p class="text-xs text-gray-500">Data</p><p class="text-gray-800">{{ formatDate(payable.conciliated_at) }}</p></div>
                        <div v-if="payable.conciliator" class="mb-2"><p class="text-xs text-gray-500">Registrado por</p><p class="text-gray-800">{{ payable.conciliator.name }}</p></div>
                        <div><p class="text-xs text-gray-500">Motivo</p><p class="text-red-700">{{ payable.divergence_reason }}</p></div>
                    </div>

                    <!-- 2ª Assinatura do Presidente (encerramento) -->
                    <div v-if="canFinalSign" class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">2ª Assinatura (Encerramento)</h3>
                        <p class="text-xs text-gray-500 mb-3">Título conciliado aguardando validação final da Presidência.</p>
                        <Button label="Encerrar ciclo" icon="pi pi-check-circle" severity="success" class="w-full" @click="router.post(`/financeiro/contas-pagar/${payable.id}/encerrar`, {}, { preserveScroll: true })" />
                    </div>

                    <!-- Status encerrado read-only -->
                    <div v-if="payable.status === 'encerrado'" class="bg-green-50 border border-green-200 rounded-xl p-4 text-sm">
                        <h3 class="text-sm font-semibold text-green-700 mb-1 flex items-center gap-2"><i class="pi pi-verified"></i> Ciclo encerrado</h3>
                        <p class="text-xs text-green-600">Título finalizado com 2ª assinatura da Presidência.</p>
                    </div>

                    <!-- Info lateral -->
                    <div v-if="payable.preparer || payable.approver || payable.approved_at" class="bg-white rounded-xl border border-gray-100 p-4 text-sm">
                        <div v-if="payable.preparer" class="mb-2">
                            <p class="text-xs text-gray-500">Preparado por</p>
                            <p class="text-gray-800">{{ payable.preparer.name }}</p>
                        </div>
                        <div v-if="payable.approver" class="mb-2">
                            <p class="text-xs text-gray-500">{{ payable.status === 'aprovado' ? 'Aprovado por' : 'Reprovado por' }}</p>
                            <p class="text-gray-800">{{ payable.approver.name }}</p>
                        </div>
                        <div v-if="payable.approved_at">
                            <p class="text-xs text-gray-500">Data da decisão</p>
                            <p class="text-gray-800">{{ formatDateTime(payable.approved_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Registrar pagamento — Dialog no desktop -->
        <Dialog v-if="!isMobile" v-model:visible="showPayment" modal header="Registrar pagamento" :style="{ width: '420px' }">
            <div class="space-y-4 pt-1">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Data do pagamento *</label>
                    <DatePicker v-model="paymentForm.paid_at" date-format="dd/mm/yy" :max-date="maxPaymentDate" class="w-full" input-id="payment-date" dusk="payment-date" />
                    <p v-if="paymentForm.errors.paid_at" class="text-xs text-red-500 mt-1">{{ paymentForm.errors.paid_at }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Forma de pagamento</label>
                    <Select v-model="paymentForm.payment_method" :options="paymentMethodOptions" placeholder="Selecione (opcional)" show-clear class="w-full" dusk="payment-method" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Comprovante</label>
                    <FileUpload mode="basic" :auto="false" choose-label="Anexar comprovante" :max-file-size="maxDocumentBytes" @select="onSelectComprovante" class="w-full" dusk="payment-file" />
                    <p class="text-[11px] text-gray-400 mt-1">Opcional. Tamanho máximo: {{ maxDocumentMb }} MB.</p>
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" severity="secondary" text @click="showPayment = false" />
                <Button label="Confirmar pagamento" icon="pi pi-check" severity="success" :loading="paymentForm.processing" dusk="confirm-payment" @click="submitPayment" />
            </template>
        </Dialog>

        <Dialog v-model:visible="showDelegateDialog" modal header="Delegar aprovação" :style="{ width: isMobile ? '95vw' : '420px' }">
            <div class="space-y-3 pt-1">
                <p class="text-sm text-gray-600">
                    O substituto poderá aprovar ou reprovar <strong>em nome do aprovador desta etapa</strong>, até a data limite (se informada).
                </p>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Substituto *</label>
                    <Select
                        v-model="delegateForm.delegate_user_id"
                        :options="delegateUserOptions"
                        option-label="label"
                        option-value="value"
                        placeholder="Selecione o usuário"
                        filter
                        class="w-full"
                    />
                    <p v-if="delegateForm.errors.delegate_user_id" class="text-xs text-red-500 mt-1">{{ delegateForm.errors.delegate_user_id }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Válido até (opcional)</label>
                    <DatePicker v-model="delegateForm.expires_at" date-format="dd/mm/yy" :min-date="new Date()" class="w-full" show-icon />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Motivo (opcional)</label>
                    <Textarea v-model="delegateForm.reason" rows="2" class="w-full" placeholder="Ex.: férias, viagem..." />
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" severity="secondary" text @click="showDelegateDialog = false" />
                <Button label="Confirmar delegação" icon="pi pi-user-plus" :loading="delegateForm.processing" :disabled="!delegateForm.delegate_user_id" @click="submitDelegate" />
            </template>
        </Dialog>

        <!-- Prioridade na aprovação do Financeiro -->
        <Dialog v-model:visible="showPriorityDialog" modal header="Prioridade de pagamento" :style="{ width: '420px' }" dusk="priority-approve-dialog">
            <p class="text-sm text-gray-600 mb-4">Defina a prioridade e o prazo antes de aprovar esta etapa.</p>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Prioridade *</label>
                    <Select v-model="approvePriorityForm.payment_priority" :options="priorityOptions" option-label="label" option-value="value" class="w-full" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Prazo (SLA)</label>
                    <DatePicker v-model="approvePriorityForm.payment_sla_date" date-format="dd/mm/yy" placeholder="dd/mm/aaaa" class="w-full" show-icon />
                    <p class="text-[11px] text-gray-400 mt-1">Sugestão: vencimento do título ({{ formatDate(payable.due_date) }})</p>
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" severity="secondary" text @click="showPriorityDialog = false" />
                <Button label="Aprovar" icon="pi pi-check" severity="success" :loading="approvePriorityForm.processing" @click="confirmApproveWithPriority" />
            </template>
        </Dialog>

        <!-- Registrar pagamento — Bottom sheet no mobile -->
        <BottomSheet v-if="isMobile" v-model="showPayment" title="Registrar pagamento">
            <div class="space-y-4" dusk="payment-sheet">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Data do pagamento *</label>
                    <DatePicker v-model="paymentForm.paid_at" date-format="dd/mm/yy" :max-date="maxPaymentDate" class="w-full" input-id="payment-date-m" dusk="payment-date" />
                    <p v-if="paymentForm.errors.paid_at" class="text-xs text-red-500 mt-1">{{ paymentForm.errors.paid_at }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Forma de pagamento</label>
                    <Select v-model="paymentForm.payment_method" :options="paymentMethodOptions" placeholder="Selecione (opcional)" show-clear class="w-full" dusk="payment-method" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Comprovante</label>
                    <FileUpload mode="basic" :auto="false" choose-label="Anexar comprovante" :max-file-size="maxDocumentBytes" @select="onSelectComprovante" class="w-full" dusk="payment-file" />
                    <p class="text-[11px] text-gray-400 mt-1">Opcional. Tamanho máximo: {{ maxDocumentMb }} MB.</p>
                </div>
                <Button label="Confirmar pagamento" icon="pi pi-check" severity="success" class="w-full" :loading="paymentForm.processing" dusk="confirm-payment" @click="submitPayment" />
            </div>
        </BottomSheet>

        <!-- Conciliação — Dialog no desktop -->
        <Dialog v-if="!isMobile" v-model:visible="showConciliation" modal header="Conciliação Bancária" :style="{ width: '450px' }" @hide="conciliationMode = null">
            <!-- Mode selection -->
            <div v-if="!conciliationMode" class="space-y-3">
                <p class="text-sm text-gray-600 mb-3">Verifique se o pagamento confere com o extrato bancário:</p>
                <Button label="Conciliar (confere)" icon="pi pi-check" severity="success" class="w-full" dusk="action-conciliate" @click="conciliationMode = 'conciliate'" />
                <Button label="Registrar divergência" icon="pi pi-times" severity="danger" outlined class="w-full" dusk="action-diverge" @click="conciliationMode = 'diverge'" />
            </div>
            <!-- Conciliate form -->
            <div v-if="conciliationMode === 'conciliate'" class="space-y-4 pt-1">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Observação (opcional)</label>
                    <Textarea v-model="conciliateForm.notes" placeholder="Ex.: Conferido com extrato Banco X..." rows="3" class="w-full" dusk="conciliation-notes" />
                </div>
                <div class="flex gap-2 justify-end">
                    <Button label="Voltar" severity="secondary" text @click="conciliationMode = null" />
                    <Button label="Confirmar conciliação" icon="pi pi-check" severity="success" :loading="conciliateForm.processing" dusk="confirm-conciliation" @click="submitConciliation" />
                </div>
            </div>
            <!-- Diverge form -->
            <div v-if="conciliationMode === 'diverge'" class="space-y-4 pt-1">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Motivo da divergência *</label>
                    <Textarea v-model="divergeForm.reason" placeholder="Descreva o que não confere (mín. 10 caracteres)..." rows="3" class="w-full" dusk="divergence-reason" />
                    <p v-if="divergeForm.errors.reason" class="text-xs text-red-500 mt-1">{{ divergeForm.errors.reason }}</p>
                </div>
                <div class="flex gap-2 justify-end">
                    <Button label="Voltar" severity="secondary" text @click="conciliationMode = null" />
                    <Button label="Confirmar divergência" icon="pi pi-times" severity="danger" :loading="divergeForm.processing" dusk="confirm-divergence" @click="submitDivergence" />
                </div>
            </div>
        </Dialog>

        <!-- Conciliação — Bottom sheet no mobile -->
        <BottomSheet v-else v-model="showConciliation" title="Conciliação Bancária">
            <div dusk="conciliation-sheet">
                <!-- Mode selection -->
                <div v-if="!conciliationMode" class="space-y-3">
                    <p class="text-sm text-gray-600 mb-3">Verifique se o pagamento confere com o extrato bancário:</p>
                    <Button label="Conciliar (confere)" icon="pi pi-check" severity="success" class="w-full" dusk="action-conciliate" @click="conciliationMode = 'conciliate'" />
                    <Button label="Registrar divergência" icon="pi pi-times" severity="danger" outlined class="w-full" dusk="action-diverge" @click="conciliationMode = 'diverge'" />
                </div>
                <!-- Conciliate form -->
                <div v-if="conciliationMode === 'conciliate'" class="space-y-4 pt-1">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Observação (opcional)</label>
                        <Textarea v-model="conciliateForm.notes" placeholder="Ex.: Conferido com extrato Banco X..." rows="3" class="w-full" dusk="conciliation-notes" />
                    </div>
                    <Button label="Confirmar conciliação" icon="pi pi-check" severity="success" class="w-full" :loading="conciliateForm.processing" dusk="confirm-conciliation" @click="submitConciliation" />
                </div>
                <!-- Diverge form -->
                <div v-if="conciliationMode === 'diverge'" class="space-y-4 pt-1">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Motivo da divergência *</label>
                        <Textarea v-model="divergeForm.reason" placeholder="Descreva o que não confere (mín. 10 caracteres)..." rows="3" class="w-full" dusk="divergence-reason" />
                        <p v-if="divergeForm.errors.reason" class="text-xs text-red-500 mt-1">{{ divergeForm.errors.reason }}</p>
                    </div>
                    <Button label="Confirmar divergência" icon="pi pi-times" severity="danger" class="w-full" :loading="divergeForm.processing" dusk="confirm-divergence" @click="submitDivergence" />
                </div>
            </div>
        </BottomSheet>

        <DocumentViewerDialog
            v-model:visible="showViewer"
            :docs="payable.documents || []"
            :initial-doc-id="viewerInitialDocId"
        />

        <!-- A4: Editar vencimento (restrito ao financeiro) -->
        <Dialog v-model:visible="showDueDate" modal header="Alterar vencimento" :style="{ width: '380px' }">
            <div class="space-y-3" dusk="due-date-dialog">
                <p class="text-xs text-gray-500">Apenas o departamento financeiro pode alterar o vencimento de um título.</p>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Novo vencimento</label>
                    <DatePicker v-model="dueDateForm.due_date" date-format="dd/mm/yy" class="w-full" input-id="due-date-input" dusk="due-date-input" />
                    <p v-if="dueDateForm.errors.due_date" class="text-xs text-red-500 mt-1">{{ dueDateForm.errors.due_date }}</p>
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" severity="secondary" text @click="showDueDate = false" />
                <Button label="Salvar" icon="pi pi-check" :loading="dueDateForm.processing" dusk="confirm-due-date" @click="submitDueDate" />
            </template>
        </Dialog>
    </component>
</template>
