<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import Dialog from 'primevue/dialog'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { useDevice } from '@/composables/useDevice'
import { useDueDatePresets } from '@/composables/useDueDatePresets'
import PayableDocumentPreviewCard from '@/Components/Financeiro/PayableDocumentPreviewCard.vue'
import DueDatePeriodChips from '@/Components/Financeiro/DueDatePeriodChips.vue'
import DocumentViewerDialog from '@/Components/Financeiro/DocumentViewerDialog.vue'
import { formatApiDate } from '@/utils/apiDate'

const VIEW_STORAGE_KEY = 'presidency_desk_view_mode'

const props = defineProps({
    payables: { type: Array, default: () => [] },
    pendingCount: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({}) },
    docTypeLabels: { type: Object, default: () => ({}) },
})

const { isMobile } = useDevice()
const page = usePage()
const toast = useToast()

const dueFrom = ref(props.filters?.due_from || '')
const dueTo = ref(props.filters?.due_to || '')
const { duePreset, applyDuePreset, clearDuePreset } = useDueDatePresets(dueFrom, dueTo)

const hasDueFilter = computed(() => !!(dueFrom.value || dueTo.value))

/** card | list */
const viewMode = ref('card')

const viewerDocs = ref([])
const viewerInitialId = ref(null)
const showViewer = computed({
    get: () => viewerDocs.value.length > 0,
    set: (v) => {
        if (!v) {
            viewerDocs.value = []
            viewerInitialId.value = null
        }
    },
})
const showRejectDialog = ref(false)
const rejectingId = ref(null)
const rejectForm = useForm({ reason: '' })
const approvingId = ref(null)

onMounted(() => {
    try {
        const saved = localStorage.getItem(VIEW_STORAGE_KEY)
        if (saved === 'list' || saved === 'card') viewMode.value = saved
    } catch {
        /* ignore */
    }
})

watch(viewMode, (mode) => {
    try {
        localStorage.setItem(VIEW_STORAGE_KEY, mode)
    } catch {
        /* ignore */
    }
})

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Pronto', detail: msg, life: 3000 })
})
watch(() => page.props.flash?.error, (msg) => {
    if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 })
})

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}

function formatDateTime(d) {
    if (!d) return '—'
    return new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

function docTypeLabel(doc) {
    return props.docTypeLabels[doc.doc_type] || props.docTypeLabels.outro || 'Documento'
}

/** Abre o viewer com TODOS os docs do título, rolando até o clicado. */
function openViewer(doc, payable) {
    const docs = payable?.documents?.length ? payable.documents : (doc ? [doc] : [])
    viewerDocs.value = docs
    viewerInitialId.value = doc?.id ?? docs[0]?.id ?? null
}

function applyFilters(extra = {}) {
    router.get('/financeiro/presidencia', {
        due_from: dueFrom.value || undefined,
        due_to: dueTo.value || undefined,
        ...extra,
    }, { preserveState: true, replace: true, preserveScroll: true })
}

function selectDuePreset(key) {
    applyDuePreset(key)
    applyFilters()
}

function clearDueFilters() {
    dueFrom.value = ''
    dueTo.value = ''
    clearDuePreset()
    router.get('/financeiro/presidencia', { all: 1 }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    })
}

function approve(payable) {
    if (!payable.documents?.length) {
        toast.add({ severity: 'warn', summary: 'Sem documentos', detail: 'Não é possível aprovar sem anexos.', life: 4000 })
        return
    }
    approvingId.value = payable.id
    router.post(`/financeiro/contas-pagar/${payable.id}/aprovar`, {}, {
        preserveScroll: true,
        onFinish: () => { approvingId.value = null },
    })
}

function openReject(payable) {
    rejectingId.value = payable.id
    rejectForm.reset()
    showRejectDialog.value = true
}

function submitReject() {
    if (!rejectingId.value) return
    rejectForm.post(`/financeiro/contas-pagar/${rejectingId.value}/reprovar`, {
        preserveScroll: true,
        onSuccess: () => {
            showRejectDialog.value = false
            rejectingId.value = null
            rejectForm.reset()
        },
    })
}

function goToPayable(id) {
    router.visit(`/financeiro/contas-pagar/${id}`)
}
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout" title="Assinaturas">
        <Toast />

        <div :class="isMobile ? 'px-4 py-3 pb-24' : 'max-w-7xl mx-auto px-4 py-6'">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 :class="isMobile ? 'text-xl font-bold text-gray-900' : 'text-2xl font-bold text-gray-900'">
                        Assinaturas
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Títulos na sua etapa final — documentos e aprovação sem sair desta tela.
                    </p>
                </div>
                <Tag v-if="pendingCount > 0" :value="`${pendingCount} aguardando`" severity="warn" class="!text-sm" />
            </div>

            <div class="bg-white rounded-xl border border-gray-100 p-4 mb-4 space-y-3">
                <DueDatePeriodChips
                    :active-key="duePreset"
                    :compact="isMobile"
                    @select="selectDuePreset"
                />
                <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                    <span v-if="hasDueFilter" class="text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded">
                        Filtro de vencimento ativo
                    </span>
                    <span v-else class="text-xs text-gray-400">Nenhum filtro de vencimento</span>
                    <div class="flex items-center gap-2 ml-auto">
                        <div
                            class="inline-flex rounded-lg border border-gray-200 overflow-hidden"
                            role="group"
                            aria-label="Formato de visualização"
                        >
                            <button
                                type="button"
                                class="px-2.5 py-1.5 text-xs font-medium transition-colors inline-flex items-center gap-1"
                                :class="viewMode === 'card'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white text-gray-600 hover:bg-gray-50'"
                                title="Cards"
                                @click="viewMode = 'card'"
                            >
                                <i class="pi pi-th-large text-[11px]"></i>
                                Cards
                            </button>
                            <button
                                type="button"
                                class="px-2.5 py-1.5 text-xs font-medium transition-colors inline-flex items-center gap-1 border-l border-gray-200"
                                :class="viewMode === 'list'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white text-gray-600 hover:bg-gray-50'"
                                title="Lista"
                                @click="viewMode = 'list'"
                            >
                                <i class="pi pi-list text-[11px]"></i>
                                Lista
                            </button>
                        </div>
                        <Button
                            label="Limpar"
                            severity="secondary"
                            outlined
                            size="small"
                            :disabled="!hasDueFilter"
                            @click="clearDueFilters"
                        />
                    </div>
                </div>
            </div>

            <div v-if="payables.length === 0" class="text-center py-20 text-gray-400 bg-white rounded-2xl border border-gray-100">
                <template v-if="hasDueFilter">
                    <i class="pi pi-calendar text-5xl mb-4 block text-gray-300"></i>
                    <p class="text-lg font-medium text-gray-600">Nenhum título neste período</p>
                    <p class="text-sm mt-1">Ajuste ou limpe o filtro de vencimento.</p>
                    <Button label="Limpar filtro" severity="secondary" outlined size="small" class="mt-4" @click="clearDueFilters" />
                </template>
                <template v-else>
                    <i class="pi pi-check-circle text-5xl mb-4 block text-green-400"></i>
                    <p class="text-lg font-medium text-gray-600">Nada pendente</p>
                    <p class="text-sm mt-1">Nenhum título aguarda sua assinatura no momento.</p>
                </template>
            </div>

            <!-- Lista: info + ações em duas linhas (sem overflow) -->
            <div
                v-else-if="viewMode === 'list'"
                class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100"
            >
                <div
                    v-for="(p, idx) in payables"
                    :key="p.id"
                    class="px-3 py-2.5 grid gap-2 min-w-0"
                    :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
                >
                    <!-- Linha 1: infos -->
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            <span class="text-sm font-bold text-gray-900 shrink-0">{{ p.title_number }}</span>
                            <span class="text-sm text-gray-800 truncate min-w-0">
                                {{ p.supplier_display_name || p.supplier_name || '—' }}
                            </span>
                            <Tag v-if="p.origem_senior" value="Senior" severity="secondary" class="!text-[9px] !py-0 shrink-0" />
                        </div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-gray-500">
                            <span class="font-semibold text-gray-800 shrink-0">{{ formatMoney(p.amount) }}</span>
                            <span class="shrink-0">Venc. {{ formatApiDate(p.due_date) }}</span>
                            <span v-if="p.empresa_nome" class="truncate min-w-0 max-w-[12rem]">{{ p.empresa_nome }}</span>
                            <span v-if="p.filial_nome" class="truncate min-w-0 max-w-[10rem]">{{ p.filial_nome }}</span>
                            <span v-if="p.department?.name" class="truncate min-w-0 max-w-[10rem]">{{ p.department.name }}</span>
                        </div>
                    </div>

                    <!-- Linha 2: links à esquerda | decisões à direita -->
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 min-w-0">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 min-w-0">
                            <button
                                v-if="p.documents?.length"
                                type="button"
                                class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline whitespace-nowrap shrink-0"
                                @click="openViewer(p.documents[0], p)"
                            >
                                <i class="pi pi-file text-[10px]"></i>
                                <span>Abrir documento</span>
                                <span v-if="p.documents.length > 1" class="text-gray-400 font-normal">({{ p.documents.length }})</span>
                            </button>
                            <span v-else class="text-[11px] text-amber-600 whitespace-nowrap shrink-0">
                                <i class="pi pi-exclamation-triangle mr-0.5"></i>Sem docs
                            </span>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-xs font-medium text-gray-600 hover:text-gray-900 hover:underline whitespace-nowrap shrink-0"
                                @click="goToPayable(p.id)"
                            >
                                <i class="pi pi-external-link text-[10px]"></i>
                                <span>Ver título</span>
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0 ml-auto">
                            <Button
                                label="Reprovar"
                                icon="pi pi-times"
                                severity="danger"
                                outlined
                                size="small"
                                @click="openReject(p)"
                            />
                            <Button
                                label="Aprovar"
                                icon="pi pi-check"
                                severity="success"
                                size="small"
                                :loading="approvingId === p.id"
                                :disabled="!p.documents?.length"
                                @click="approve(p)"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cards: preview de documentos -->
            <div v-else class="space-y-4">
                <article
                    v-for="p in payables"
                    :key="p.id"
                    class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden"
                >
                    <div class="px-4 py-3 border-b border-gray-50 flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="text-base font-bold text-gray-900">{{ p.title_number }}</span>
                                <Tag value="Presidência" severity="info" class="!text-[10px]" />
                                <Tag v-if="p.origem_senior" value="Senior" severity="secondary" class="!text-[10px]" />
                            </div>
                            <p class="text-sm font-semibold text-gray-800 truncate">
                                {{ p.supplier_display_name || p.supplier_name || '—' }}
                            </p>
                            <p v-if="p.nickname" class="text-xs text-gray-500 truncate">{{ p.nickname }}</p>
                            <p v-if="p.description" class="text-xs text-gray-500 mt-1 line-clamp-2">{{ p.description }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-lg font-bold text-gray-900">{{ formatMoney(p.amount) }}</p>
                            <p class="text-xs text-gray-500">Venc. {{ formatApiDate(p.due_date) }}</p>
                        </div>
                    </div>

                    <div class="px-4 py-2 bg-gray-50/80 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-500">
                        <span v-if="p.empresa_nome"><i class="pi pi-building mr-1"></i>{{ p.empresa_nome }}</span>
                        <span v-if="p.filial_nome"><i class="pi pi-map-marker mr-1"></i>{{ p.filial_nome }}</span>
                        <span v-if="p.department?.name"><i class="pi pi-sitemap mr-1"></i>{{ p.department.name }}</span>
                        <span v-if="p.preparer"><i class="pi pi-user mr-1"></i>{{ p.preparer.name }}</span>
                        <span v-if="p.sent_for_approval_at"><i class="pi pi-clock mr-1"></i>Enviado {{ formatDateTime(p.sent_for_approval_at) }}</span>
                    </div>

                    <div class="px-4 py-3">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">
                            Documentos ({{ p.documents?.length || 0 }})
                        </p>
                        <div v-if="p.documents?.length" class="flex gap-3 overflow-x-auto pb-1 -mx-1 px-1">
                            <div
                                v-for="doc in p.documents"
                                :key="doc.id"
                                class="shrink-0 w-44 sm:w-52"
                            >
                                <PayableDocumentPreviewCard
                                    :doc="doc"
                                    :type-label="docTypeLabel(doc)"
                                    @open="(d) => openViewer(d, p)"
                                />
                            </div>
                        </div>
                        <p v-else class="text-xs text-amber-600 flex items-center gap-1">
                            <i class="pi pi-exclamation-triangle"></i> Sem documentos anexados
                        </p>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-50 flex flex-wrap items-center justify-end gap-2 bg-white">
                        <Button
                            label="Ver título"
                            icon="pi pi-external-link"
                            severity="secondary"
                            text
                            size="small"
                            @click="goToPayable(p.id)"
                        />
                        <div class="inline-flex items-center gap-2 flex-nowrap shrink-0">
                            <Button
                                label="Reprovar"
                                icon="pi pi-times"
                                severity="danger"
                                outlined
                                size="small"
                                @click="openReject(p)"
                            />
                            <Button
                                label="Aprovar"
                                icon="pi pi-check"
                                severity="success"
                                size="small"
                                :loading="approvingId === p.id"
                                :disabled="!p.documents?.length"
                                @click="approve(p)"
                            />
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <DocumentViewerDialog
            v-model:visible="showViewer"
            :docs="viewerDocs"
            :initial-doc-id="viewerInitialId"
        />

        <Dialog
            v-model:visible="showRejectDialog"
            modal
            header="Reprovar título"
            :style="{ width: isMobile ? '95vw' : '28rem' }"
            @hide="rejectingId = null"
        >
            <p class="text-sm text-gray-600 mb-3">Informe o motivo da reprovação.</p>
            <Textarea v-model="rejectForm.reason" rows="4" class="w-full" placeholder="Justifique..." />
            <div class="flex gap-2 mt-4 justify-end">
                <Button label="Cancelar" severity="secondary" text @click="showRejectDialog = false" />
                <Button
                    label="Confirmar reprovação"
                    severity="danger"
                    :loading="rejectForm.processing"
                    :disabled="!rejectForm.reason?.trim()"
                    @click="submitReject"
                />
            </div>
        </Dialog>
    </component>
</template>
