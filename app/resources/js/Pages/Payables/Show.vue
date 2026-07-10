<script setup>
import { ref, computed, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import FileUpload from 'primevue/fileupload'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import BottomSheet from '@/Components/Mobile/BottomSheet.vue'
import { useDevice } from '@/composables/useDevice'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'

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
    canFinalSign: { type: Boolean, default: false },
    canEditDueDate: { type: Boolean, default: false },
    mentionableUsers: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
})

const { isMobile } = useDevice()
const page = usePage()
const authUser = page.props.auth?.user
const toast = useToast()

const commentForm = useForm({ body: '' })
const showRejectDialog = ref(false)
const rejectForm = useForm({ reason: '' })

// ── @Mention autocomplete ──
const mentionQuery = ref('')
const mentionActive = ref(false)
const mentionedUsers = ref([]) // lista de {id, name} já mencionados
const mentionResults = computed(() => {
    if (!mentionQuery.value || !mentionActive.value) return []
    const q = mentionQuery.value.toLowerCase()
    const alreadyIds = new Set(mentionedUsers.value.map(u => u.id))
    return (props.mentionableUsers || [])
        .filter(u => u.name.toLowerCase().includes(q) && !alreadyIds.has(u.id))
        .slice(0, 8)
})
const commentInput = ref(null)

function onCommentInput(e) {
    const val = commentForm.body
    const cursorPos = e.target?.selectionStart || val.length
    const textBefore = val.substring(0, cursorPos)
    const atMatch = textBefore.match(/@([^\s@]*)$/)
    if (atMatch) {
        mentionQuery.value = atMatch[1]
        mentionActive.value = true
    } else {
        mentionActive.value = false
        mentionQuery.value = ''
    }
}

function insertMention(user) {
    // Remove o @query do texto
    const val = commentForm.body
    const atIdx = val.lastIndexOf('@' + mentionQuery.value)
    if (atIdx >= 0) {
        const before = val.substring(0, atIdx)
        const after = val.substring(atIdx + 1 + mentionQuery.value.length)
        commentForm.body = (before + after).trim()
    }
    // Adiciona ao array de mencionados (badge)
    if (!mentionedUsers.value.find(u => u.id === user.id)) {
        mentionedUsers.value.push({ id: user.id, name: user.name })
    }
    mentionActive.value = false
    mentionQuery.value = ''
}

function removeMention(userId) {
    mentionedUsers.value = mentionedUsers.value.filter(u => u.id !== userId)
}

function submitComment() {
    // Monta o body incluindo as menções no formato @[Nome](id:N) no final
    let body = commentForm.body.trim()
    if (mentionedUsers.value.length > 0) {
        const mentionTags = mentionedUsers.value.map(u => `@[${u.name}](id:${u.id})`).join(' ')
        body = body ? `${body} ${mentionTags}` : mentionTags
    }
    if (!body) return

    const form = useForm({ body })
    form.post(`/financeiro/contas-pagar/${props.payable.id}/comentarios`, {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset()
            mentionedUsers.value = []
        },
    })
}

// ── Registrar pagamento ──
const showPayment = ref(false)
const maxPaymentDate = new Date()
const paymentForm = useForm({ paid_at: new Date(), payment_method: null, file: null })
const paymentMethodOptions = computed(() => Object.keys(props.paymentMethods || {}))

function pad(n) { return String(n).padStart(2, '0') }
function toYmd(d) {
    if (!d) return null
    const dt = d instanceof Date ? d : new Date(d)
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

function uploadDoc(event, type = 'outro') {
    const file = event.files?.[0]
    if (!file) return
    const formData = new FormData()
    formData.append('file', file)
    formData.append('type', type)
    router.post(`/financeiro/contas-pagar/${props.payable.id}/documentos`, formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => markBorderoStale(),
    })
}

// Tipos de documento (feedback do cliente): lista separada por tipo.
const DOC_TYPES = [
    { key: 'nota_fiscal', label: 'Notas Fiscais', icon: 'pi-file-edit' },
    { key: 'boleto', label: 'Boletos', icon: 'pi-money-bill' },
    { key: 'relatorio', label: 'Relatórios', icon: 'pi-chart-bar' },
    { key: 'comprovacao', label: 'Comprovações', icon: 'pi-check-circle' },
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

function approve() {
    router.post(`/financeiro/contas-pagar/${props.payable.id}/aprovar`, {}, { preserveScroll: true })
}

function reject() {
    rejectForm.post(`/financeiro/contas-pagar/${props.payable.id}/reprovar`, {
        preserveScroll: true,
        onSuccess: () => { showRejectDialog.value = false; rejectForm.reset() },
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
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}
function formatDateTime(d) {
    if (!d) return '—'
    return new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

const canPrepare = ['pendente', 'em_preparacao', 'reprovado'].includes(props.payable.status)
// Se está num borderô, não pode enviar individual — o borderô controla o envio
const inBordero = !!props.payable.bordero_id
const canSendIndividual = canPrepare && !inBordero
const canApprove = props.payable.status === 'aguardando_aprovacao' && !inBordero && !props.approvalSteps?.length

// Trava (feedback do cliente): não envia pra aprovação sem ao menos 1 documento.
const hasDocuments = computed(() => (props.payable.documents?.length || 0) > 0)
const wasRejectedBack = computed(() =>
    props.payable.status === 'pendente' && !!props.payable.rejection_reason
)

// Departamento de origem (seletor ao enviar pra aprovação)
const showDeptSelect = ref(false)
const selectedDeptId = ref(props.payable.department_id || null)

function sendForApproval() {
    router.post(`/financeiro/contas-pagar/${props.payable.id}/enviar-aprovacao`, {
        department_id: selectedDeptId.value,
    }, { preserveScroll: true })
}

// Sidebar de ações aparece quando há qualquer ação/condição lateral a mostrar.
const showSidebar = computed(() =>
    canSendIndividual || canApprove || inBordero || showRejectDialog.value ||
    props.canPay || props.payable.status === 'pago' ||
    (props.payable.status === 'aprovado' && !props.pagadorConfigured) ||
    props.canConciliate ||
    props.payable.status === 'conciliado' || props.payable.status === 'divergente' ||
    (props.payable.status === 'pago' && !props.conciliadorConfigured) ||
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
const viewerDoc = ref(null)
const showViewer = computed({
    get: () => !!viewerDoc.value,
    set: (v) => { if (!v) viewerDoc.value = null },
})
function openViewer(doc) { viewerDoc.value = doc }
function closeViewer() { viewerDoc.value = null }
function isPdf(doc) { return doc?.mime_type === 'application/pdf' }

// ── A4: edição de vencimento (restrita ao financeiro) ──
const showDueDate = ref(false)
const dueDateForm = useForm({ due_date: props.payable.due_date ? new Date(props.payable.due_date) : new Date() })
function submitDueDate() {
    dueDateForm
        .transform((data) => ({ due_date: toYmd(data.due_date) }))
        .post(`/financeiro/contas-pagar/${props.payable.id}/vencimento`, {
            preserveScroll: true,
            onSuccess: () => { showDueDate.value = false },
        })
}
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout" :title="isMobile ? 'Detalhe' : undefined" :show-back="isMobile">
        <Toast />
        <div :class="isMobile ? 'px-4 py-3 pb-20' : 'max-w-5xl mx-auto'">
            <!-- Header -->
            <div class="flex items-start justify-between mb-6">
                <div>
                    <button @click="goBack" class="text-sm text-blue-600 hover:underline mb-2 flex items-center gap-1 cursor-pointer">
                        <i class="pi pi-arrow-left text-xs"></i> Voltar para lista
                    </button>
                    <div class="flex items-center gap-3">
                        <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                            {{ payable.supplier_name }}
                        </h1>
                        <Tag :value="statusLabels[payable.status]" :severity="statusColors[payable.status]" />
                    </div>
                    <p class="text-sm text-gray-500 mt-1">
                        Título: {{ payable.title_number || '—' }} · Vencimento: {{ formatDate(payable.due_date) }}
                        <button v-if="canEditDueDate" @click="showDueDate = true" dusk="btn-edit-due-date"
                            class="ml-1 text-blue-600 hover:text-blue-800 cursor-pointer align-middle" title="Alterar vencimento (financeiro)">
                            <i class="pi pi-pencil text-xs"></i>
                        </button>
                    </p>
                </div>
                <p class="text-xl font-bold text-gray-800">{{ formatMoney(payable.amount) }}</p>
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

            <div :class="isMobile ? 'space-y-4' : (showSidebar) ? 'grid grid-cols-3 gap-6' : ''">
                <!-- Coluna principal -->
                <div :class="isMobile ? '' : (showSidebar) ? 'col-span-2 space-y-4' : 'space-y-4'">
                    <!-- Info -->
                    <div class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Informações</h3>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div><p class="text-xs text-gray-500">Empresa</p><p class="text-gray-800">{{ payable.empresa_nome || '—' }}</p></div>
                            <div><p class="text-xs text-gray-500">Filial</p><p class="text-gray-800">{{ payable.branch?.name || '—' }}</p></div>
                            <div><p class="text-xs text-gray-500">Categoria</p><p class="text-gray-800">{{ payable.category || '—' }}</p></div>
                            <div><p class="text-xs text-gray-500">Emissão</p><p class="text-gray-800">{{ formatDate(payable.issue_date) }}</p></div>
                            <div><p class="text-xs text-gray-500">CNPJ</p><p class="text-gray-800 font-mono text-xs">{{ payable.supplier_cnpj || '—' }}</p></div>
                            <div class="col-span-2" v-if="payable.description"><p class="text-xs text-gray-500">Descrição</p><p class="text-gray-800">{{ payable.description }}</p></div>
                        </div>
                    </div>

                    <!-- Documentos -->
                    <div class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Documentos ({{ payable.documents?.length || 0 }})</h3>
                        <p v-if="!payable.documents?.length" class="text-sm text-gray-400 mb-4">Nenhum documento anexado.</p>
                        <div class="space-y-3">
                            <div v-for="t in DOC_TYPES" :key="t.key" v-show="canPrepare || docsByType(t.key).length"
                                :dusk="`doc-section-${t.key}`" class="border border-gray-100 rounded-lg p-3">
                                <h4 class="text-xs font-semibold text-gray-600 flex items-center gap-1.5 mb-2">
                                    <i :class="['pi', t.icon, 'text-gray-400']"></i> {{ t.label }}
                                    <span class="text-gray-400 font-normal">({{ docsByType(t.key).length }})</span>
                                </h4>
                                <div v-if="docsByType(t.key).length" class="space-y-2 mb-2">
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
                                            <button v-if="canPrepare" @click="removeDoc(doc.id)" class="text-red-400 hover:text-red-600 p-1.5 cursor-pointer" title="Remover">
                                                <i class="pi pi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-[11px] text-gray-400 mb-2">Nenhum anexo deste tipo.</p>
                                <FileUpload v-if="canPrepare" mode="basic" :auto="true" :choose-label="`Anexar ${t.label}`"
                                    :max-file-size="10485760" @select="(e) => uploadDoc(e, t.key)" class="w-full"
                                    invalid-file-size-message="O arquivo é muito grande. O tamanho máximo permitido é {1}." />
                            </div>
                        </div>
                        <p v-if="canPrepare" class="text-[11px] text-gray-400 mt-2 text-center">Tamanho máximo por arquivo: 10 MB.</p>
                    </div>

                    <!-- Timeline/Comentários -->
                    <div class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Timeline</h3>
                        <div class="space-y-3 mb-4 max-h-80 overflow-y-auto">
                            <div v-for="c in payable.comments" :key="c.id" class="flex gap-2">
                                <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 text-xs font-semibold text-blue-600">
                                    {{ c.user?.name?.charAt(0) || 'S' }}
                                </div>
                                <div class="flex-1">
                                    <p class="text-xs text-gray-500">
                                        <span class="font-medium text-gray-700">{{ c.user?.name || 'Sistema' }}</span>
                                        · {{ formatDateTime(c.created_at) }}
                                    </p>
                                    <p :class="['text-sm mt-0.5', c.type === 'rejection' ? 'text-red-600' : c.type === 'approval' ? 'text-green-600' : 'text-gray-700']" v-html="formatCommentBody(c.body)">
                                    </p>
                                </div>
                            </div>
                            <p v-if="!payable.comments?.length" class="text-sm text-gray-400">Nenhuma atividade.</p>
                        </div>

                        <!-- Form de comentário com @mention -->
                        <form @submit.prevent="submitComment" class="relative">
                            <!-- Badges dos mencionados -->
                            <div v-if="mentionedUsers.length" class="flex flex-wrap gap-1.5 mb-2">
                                <span v-for="u in mentionedUsers" :key="u.id" class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">
                                    @{{ u.name }}
                                    <button type="button" @click="removeMention(u.id)" class="text-blue-500 hover:text-blue-800 ml-0.5 cursor-pointer">
                                        <i class="pi pi-times text-[9px]"></i>
                                    </button>
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <div class="flex-1 relative">
                                    <Textarea v-model="commentForm.body" placeholder="Escreva um comentário... Use @ para mencionar" rows="2" class="w-full" @input="onCommentInput" ref="commentInput" />
                                    <!-- @Mention autocomplete popup -->
                                    <div v-if="mentionActive && mentionResults.length" class="absolute bottom-full left-0 mb-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto z-50">
                                        <div v-for="u in mentionResults" :key="u.id" @click="insertMention(u)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-xs font-semibold text-blue-600">{{ u.name.charAt(0) }}</div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-800">{{ u.name }}</p>
                                                <p class="text-[10px] text-gray-400">{{ u.email }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <Button type="submit" icon="pi pi-send" :loading="commentForm.processing" :disabled="!commentForm.body.trim() && !mentionedUsers.length" />
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">Use <strong>@nome</strong> para mencionar alguém e notificá-lo.</p>
                        </form>
                    </div>
                </div>

                <!-- Sidebar de ações -->
                <div v-if="showSidebar" class="space-y-4">
                    <!-- Aviso: está em borderô -->
                    <div v-if="inBordero" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <h3 class="text-sm font-semibold text-amber-700 mb-1 flex items-center gap-2">
                            <i class="pi pi-list-check"></i> Em um borderô
                        </h3>
                        <p class="text-xs text-amber-600 mb-2">Este título faz parte de um borderô. O envio é feito pelo borderô; a aprovação segue o mesmo fluxo configurado (pode ser feita aqui ou no borderô).</p>
                        <Button label="Ver borderô" icon="pi pi-arrow-right" size="small" outlined class="w-full" @click="goToBordero" />
                    </div>

                    <!-- Ações do preparador (só se NÃO está em borderô) -->
                    <div v-if="canSendIndividual" class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Ações</h3>
                        <div v-if="!showDeptSelect" class="space-y-2">
                            <Button label="Enviar para Aprovação" icon="pi pi-send" class="w-full" dusk="btn-send-approval" :disabled="!hasDocuments" @click="showDeptSelect = true" />
                            <p v-if="!hasDocuments" dusk="no-docs-hint" class="text-[11px] text-amber-600 text-center flex items-center justify-center gap-1">
                                <i class="pi pi-exclamation-triangle text-[10px]"></i> Anexe ao menos um documento para enviar.
                            </p>
                        </div>
                        <div v-else class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Departamento de origem *</label>
                                <Select v-model="selectedDeptId" :options="departments" optionLabel="name" optionValue="id" placeholder="Selecione o departamento..." class="w-full" filter />
                            </div>
                            <Button label="Confirmar envio" icon="pi pi-send" class="w-full" :disabled="!selectedDeptId" @click="sendForApproval" />
                            <Button label="Cancelar" severity="secondary" text class="w-full" @click="showDeptSelect = false" />
                        </div>
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
                                        {{ step.assignee?.name || step.level_name }}
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
                            <Button label="Aprovar" icon="pi pi-check" severity="success" class="w-full" @click="approve" />
                            <Button label="Reprovar" icon="pi pi-times" severity="danger" outlined class="w-full" @click="showRejectDialog = true" />
                        </div>
                    </div>

                    <!-- Aprovação antiga (fallback se não tem steps) -->
                    <div v-else-if="canApprove && !approvalSteps?.length" class="bg-white rounded-xl border border-gray-100 p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Aprovação</h3>
                        <div class="space-y-2">
                            <Button label="Aprovar" icon="pi pi-check" severity="success" class="w-full" @click="approve" />
                            <Button label="Reprovar" icon="pi pi-times" severity="danger" outlined class="w-full" @click="showRejectDialog = true" />
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
                    <div v-if="payable.status === 'pago'" class="bg-white rounded-xl border border-gray-100 p-4 text-sm" dusk="payment-info">
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
                    <div v-else-if="payable.status === 'pago' && !conciliadorConfigured" class="bg-amber-50 border border-amber-200 rounded-xl p-4">
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
                    <FileUpload mode="basic" :auto="false" choose-label="Anexar comprovante" :max-file-size="10485760" @select="onSelectComprovante" class="w-full" dusk="payment-file" />
                    <p class="text-[11px] text-gray-400 mt-1">Opcional. Tamanho máximo: 10 MB.</p>
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" severity="secondary" text @click="showPayment = false" />
                <Button label="Confirmar pagamento" icon="pi pi-check" severity="success" :loading="paymentForm.processing" dusk="confirm-payment" @click="submitPayment" />
            </template>
        </Dialog>

        <!-- Registrar pagamento — Bottom sheet no mobile -->
        <BottomSheet v-else v-model="showPayment" title="Registrar pagamento">
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
                    <FileUpload mode="basic" :auto="false" choose-label="Anexar comprovante" :max-file-size="10485760" @select="onSelectComprovante" class="w-full" dusk="payment-file" />
                    <p class="text-[11px] text-gray-400 mt-1">Opcional. Tamanho máximo: 10 MB.</p>
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

        <!-- A2: Visualizador de anexo inline (abre na mesma página, com opção de voltar) -->
        <Dialog v-model:visible="showViewer" modal :header="viewerDoc?.name || 'Documento'"
            :style="{ width: '92vw', maxWidth: '1100px' }" :dismissable-mask="true">
            <div dusk="doc-viewer" class="min-h-[50vh]">
                <template v-if="viewerDoc">
                    <iframe v-if="isPdf(viewerDoc)" :src="viewerDoc.url" class="w-full h-[75vh] border-0 rounded" title="Visualização do documento"></iframe>
                    <div v-else-if="isImage(viewerDoc)" class="flex items-center justify-center bg-gray-50 rounded p-2">
                        <img :src="viewerDoc.url" :alt="viewerDoc.name" class="max-w-full max-h-[75vh] object-contain" />
                    </div>
                    <div v-else class="flex flex-col items-center justify-center text-center py-16 text-gray-500">
                        <i class="pi pi-file text-5xl mb-4 text-gray-300"></i>
                        <p class="mb-4">Pré-visualização não disponível para este tipo de arquivo.</p>
                        <a :href="viewerDoc.url" :download="viewerDoc.name" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">
                            <i class="pi pi-download"></i> Baixar arquivo
                        </a>
                    </div>
                </template>
            </div>
            <template #footer>
                <Button label="Voltar" icon="pi pi-arrow-left" severity="secondary" text dusk="doc-viewer-close" @click="closeViewer" />
                <a v-if="viewerDoc" :href="viewerDoc.url" :download="viewerDoc.name" class="inline-flex items-center gap-2 px-3 py-2 text-sm text-blue-600 hover:underline">
                    <i class="pi pi-download"></i> Baixar
                </a>
            </template>
        </Dialog>

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
