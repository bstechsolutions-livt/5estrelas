<script setup>
import { ref, computed, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'

const props = defineProps({
    isConciliador: Boolean,
    bankAccounts: { type: Array, default: () => [] },
    days: { type: Array, default: () => [] },
    dayReport: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
    importResults: { type: Array, default: null },
})

const toast = useToast()
const confirm = useConfirm()
const page = usePage()

// ── Flash messages ────────────────────────────────────────────────────────────
watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.add({ severity: 'success', summary: 'Sucesso', detail: flash.success, life: 5000 })
    if (flash?.error)   toast.add({ severity: 'error',   summary: 'Erro',    detail: flash.error,   life: 5000 })
    if (flash && Object.prototype.hasOwnProperty.call(flash, 'importResults')) {
        localImportResults.value = flash.importResults?.length ? flash.importResults : null
    }
}, { deep: true })

// ── Import results (flashed from session or from page prop on load) ───────────
const localImportResults = ref(props.importResults ?? null)

// ── Upload batch (arrastar/soltar + clique) ───────────────────────────────────
const uploadForm = useForm({ files: [] })
const fileInput = ref(null)
const dragOver = ref(false)

function ofxFilesFromList(fileList) {
    return Array.from(fileList || []).filter((f) => /\.ofx$/i.test(f.name))
}

function submitOfxFiles(files) {
    if (!files.length) {
        toast.add({ severity: 'warn', summary: 'Nenhum OFX', detail: 'Solte ou selecione arquivos .ofx.', life: 4000 })
        return
    }
    uploadForm.files = files
    uploadForm.post('/financeiro/contas-pagar/conciliacao/upload-batch', {
        forceFormData: true,
        onError: (errors) => {
            const msg = errors.files || errors.file || Object.values(errors)[0]
            if (msg) toast.add({ severity: 'error', summary: 'Erro no upload', detail: msg, life: 6000 })
        },
    })
}

function onFileInputChange(event) {
    submitOfxFiles(ofxFilesFromList(event.target.files))
    event.target.value = ''
}

function onDrop(event) {
    dragOver.value = false
    if (uploadForm.processing) return
    submitOfxFiles(ofxFilesFromList(event.dataTransfer?.files))
}

// ── Day navigation ────────────────────────────────────────────────────────────
function openDay(date) {
    router.get('/financeiro/contas-pagar/conciliacao', { date }, { preserveState: false })
}

function clearDay() {
    router.get('/financeiro/contas-pagar/conciliacao', {}, { preserveState: false })
}

const selectedDate = computed(() => props.filters?.date || props.dayReport?.date || null)

function showOpenDayLink(card) {
    // Só mostra se o relatório do dia ainda não está aberto nessa data
    return card?.ok && card?.date && card.date !== selectedDate.value
}

// ── Day report interactions ───────────────────────────────────────────────────
const linkTxId = ref(null)
const linkPayableId = ref(null)
const linkSearchQuery = ref('')
const linkResults = ref([])
const linkLoading = ref(false)

async function searchForLink(query) {
    if (!query || query.length < 2) { linkResults.value = []; return }
    linkLoading.value = true
    try {
        const date = props.dayReport?.date ?? props.filters?.date
        const params = new URLSearchParams({ query, ...(date ? { date } : {}) })
        const resp = await fetch(`/financeiro/contas-pagar/conciliacao/search-payables?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
        })
        linkResults.value = await resp.json()
    } finally {
        linkLoading.value = false
    }
}

watch(linkSearchQuery, (q) => searchForLink(q))

function openLink(txId) {
    linkTxId.value = txId
    linkPayableId.value = null
    linkSearchQuery.value = ''
    linkResults.value = []
}

function closeLink() {
    linkTxId.value = null
}

const linkForm = useForm({ payable_id: null })

function submitLink() {
    if (!linkPayableId.value) return
    linkForm.payable_id = linkPayableId.value
    linkForm.post(`/financeiro/contas-pagar/conciliacao/transactions/${linkTxId.value}/link`, {
        onSuccess: () => { closeLink(); router.reload() },
    })
}

const acceptForm = useForm({})
function acceptTx(id) {
    acceptForm.post(`/financeiro/contas-pagar/conciliacao/transactions/${id}/accept`, {
        onSuccess: () => router.reload(),
        onError: (errors) => {
            const msg = Object.values(errors)[0]
            if (msg) toast.add({ severity: 'warn', summary: 'Atenção', detail: msg, life: 5000 })
        },
    })
}

const rejectForm = useForm({})
function rejectTx(id) {
    rejectForm.post(`/financeiro/contas-pagar/conciliacao/transactions/${id}/reject`, {
        onSuccess: () => router.reload(),
    })
}

const batchDayForm = useForm({ date: null })
function batchDay() {
    if (!props.dayReport?.date || batchDayForm.processing) return

    const blockers = props.dayReport.conciliate_blockers ?? []
    if (blockers.length) {
        confirm.require({
            header: 'Dia incompleto',
            message: blockers.join(' '),
            icon: 'pi pi-exclamation-triangle',
            rejectProps: { label: 'Fechar', severity: 'secondary', outlined: true },
            acceptProps: { label: 'Entendi', severity: 'secondary' },
            accept: () => {},
        })
        return
    }

    const s = props.dayReport.summary ?? {}
    const lines = [
        `Títulos aceitos a conciliar: ${s.accepted ?? 0}`,
        `Tarifas: ${s.tarifas ?? 0} · Aplicações: ${s.aplicacoes ?? 0} · Resgates: ${s.resgates ?? 0}`,
        `Arquivos OFX que serão preservados: ${s.ofx_files ?? 0}`,
    ]
    if ((s.payable_only ?? 0) > 0) {
        lines.push(`Atenção: ${s.payable_only} título(s) só no sistema (sem OFX) ficam de fora.`)
    }
    lines.push('Confirma a conciliação completa deste dia?')

    confirm.require({
        header: `Conciliar dia ${props.dayReport.label}`,
        message: lines.join('\n'),
        icon: 'pi pi-check-circle',
        rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Conciliar dia', severity: 'success' },
        accept: () => {
            batchDayForm.date = props.dayReport.date
            batchDayForm.post('/financeiro/contas-pagar/conciliacao/batch-conciliate-day', {
                onSuccess: () => router.reload(),
            })
        },
    })
}

const resetDayForm = useForm({ date: null })
function resetDay() {
    if (!props.dayReport?.date || resetDayForm.processing) return
    const label = props.dayReport.label || props.dayReport.date
    confirm.require({
        header: 'Começar do zero',
        message: `Apagar todos os extratos OFX de ${label} e importar de novo? Os títulos do Contas a Pagar não são alterados.`,
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Apagar OFX do dia', severity: 'danger' },
        accept: () => {
            resetDayForm.date = props.dayReport.date
            resetDayForm.post('/financeiro/contas-pagar/conciliacao/reset-day', {
                onSuccess: () => { localImportResults.value = null },
            })
        },
    })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatDate(d) {
    if (!d) return '—'
    return new Date(String(d).slice(0, 10) + 'T12:00:00').toLocaleDateString('pt-BR')
}
/** Débitos OFX vêm negativos — mostra R$ sem traço quebrado na linha. */
function formatMoney(v) {
    const n = Number(v)
    if (Number.isNaN(n)) return '—'
    return Math.abs(n).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}
function statusTagSeverity(status) {
    return { pending: 'info', accepted: 'success', manual: 'success', rejected: 'danger', unmatched: 'warn' }[status] ?? 'secondary'
}
function confidenceLabel(c) {
    return { high: 'Alta', medium: 'Média', low: 'Baixa', none: '—' }[c] ?? c
}
function operationCategoryLabel(c) {
    return { tarifa: 'Tarifa', aplicacao: 'Aplicação', resgate: 'Resgate' }[c] ?? c
}
function dayRowClass(d) {
    return props.dayReport?.date === d.date ? 'bg-blue-50' : ''
}

const kpis = computed(() => props.dayReport?.kpis ?? null)
const matched  = computed(() => props.dayReport?.matched  ?? [])
const ofxOnly  = computed(() => props.dayReport?.ofx_only  ?? [])
const bankOps  = computed(() => props.dayReport?.bank_ops  ?? [])
const tarifas = computed(() => bankOps.value.filter((t) => t.operation_category === 'tarifa'))
const aplicacoes = computed(() => bankOps.value.filter((t) => t.operation_category === 'aplicacao'))
const resgates = computed(() => bankOps.value.filter((t) => t.operation_category === 'resgate'))
const payableOnly = computed(() => props.dayReport?.payable_only ?? [])
const ambiguous   = computed(() => props.dayReport?.ambiguous   ?? [])
const canConciliateDay = computed(() => !!props.dayReport?.can_conciliate_day)
const dayConciliated = computed(() => !!props.dayReport?.day_conciliated)

/** Accordion: uma seção aberta por vez */
const openSection = ref(null)
function toggleSection(id) {
    openSection.value = openSection.value === id ? null : id
}
function isSectionOpen(id) {
    return openSection.value === id
}
function sumAbs(items) {
    return items.reduce((s, t) => s + Math.abs(Number(t.amount ?? 0)), 0)
}
function defaultOpenSection() {
    if (matched.value.some((t) => t.match_status === 'pending')) return 'matched'
    if (ambiguous.value.length) return 'ambiguous'
    if (ofxOnly.value.length) return 'ofx_only'
    if (matched.value.length) return 'matched'
    if (tarifas.value.length) return 'tarifas'
    if (aplicacoes.value.length) return 'aplicacoes'
    if (resgates.value.length) return 'resgates'
    if (payableOnly.value.length) return 'payable_only'
    return null
}
watch(() => props.dayReport?.date, () => {
    openSection.value = defaultOpenSection()
}, { immediate: true })
</script>

<template>
    <AppLayout>
        <Toast />
        <ConfirmDialog />
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Conciliação Bancária</h1>
                <p class="text-sm text-gray-500 mt-1">Suba os OFX — data e conta detectadas automaticamente.</p>
            </div>

            <!-- Upload card -->
            <div v-if="isConciliador" class="bg-white rounded-xl border border-gray-100 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-1">Importar extratos OFX</h2>
                <p class="text-xs text-gray-400 mb-3">
                    Arraste um ou vários .ofx — data e conta são detectadas do próprio arquivo.
                </p>
                <input
                    ref="fileInput"
                    type="file"
                    accept=".ofx,application/x-ofx,application/ofx"
                    multiple
                    class="hidden"
                    :disabled="uploadForm.processing"
                    @change="onFileInputChange"
                />
                <div
                    role="button"
                    tabindex="0"
                    class="rounded-xl border-2 border-dashed px-4 py-10 text-center transition cursor-pointer select-none"
                    :class="dragOver
                        ? 'border-blue-500 bg-blue-50'
                        : uploadForm.processing
                            ? 'border-gray-200 bg-gray-50 opacity-60 cursor-wait'
                            : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50/40'"
                    @click="!uploadForm.processing && fileInput?.click()"
                    @keydown.enter.prevent="!uploadForm.processing && fileInput?.click()"
                    @dragenter.prevent="dragOver = true"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="onDrop"
                >
                    <i class="pi pi-cloud-upload text-3xl text-gray-400 mb-2 block" />
                    <p class="text-sm font-medium text-gray-700">
                        {{ dragOver ? 'Solte os arquivos aqui' : 'Arraste os .ofx aqui' }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">ou clique para selecionar</p>
                </div>
                <p v-if="uploadForm.processing" class="text-xs text-blue-600 mt-2">Processando arquivos…</p>
            </div>

            <!-- Import results -->
            <div v-if="localImportResults && localImportResults.length">
                <h2 class="text-sm font-semibold text-gray-700 mb-2">Resultado da importação</h2>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                        v-for="(card, i) in localImportResults"
                        :key="i"
                        :class="['rounded-xl border p-4', card.ok ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200']"
                    >
                        <p class="font-medium text-sm truncate" :class="card.ok ? 'text-green-800' : 'text-red-800'">
                            {{ card.file_name }}
                        </p>
                        <div v-if="card.ok" class="mt-2 space-y-1 text-xs text-green-700">
                            <p>{{ formatDate(card.date) }} · {{ card.bank_account_name ?? 'Conta não identificada' }}</p>
                            <p v-if="card.account_created" class="text-amber-700 font-medium">Conta criada automaticamente no Hub</p>
                            <p>{{ card.transaction_count }} transações ({{ card.debit_count }} déb / {{ card.credit_count }} créd)</p>
                            <button
                                v-if="showOpenDayLink(card)"
                                type="button"
                                class="mt-1 text-blue-600 hover:underline"
                                @click="openDay(card.date)"
                            >Abrir relatório deste dia →</button>
                        </div>
                        <p v-else class="mt-2 text-xs text-red-700">{{ card.error }}</p>
                    </div>
                </div>
            </div>

            <!-- Recent days list -->
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700">Dias com conciliação</h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2 text-left">Data</th>
                            <th class="px-4 py-2 text-right">Extratos</th>
                            <th class="px-4 py-2 text-right">Sugeridos</th>
                            <th class="px-4 py-2 text-right">Ambíguos</th>
                            <th class="px-4 py-2 text-right">Só OFX</th>
                            <th class="px-4 py-2 text-right">Só título</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="d in days"
                            :key="d.date"
                            :class="['cursor-pointer hover:bg-blue-50 transition', dayRowClass(d)]"
                            @click="openDay(d.date)"
                        >
                            <td class="px-4 py-2 font-medium text-blue-700">{{ d.label }}</td>
                            <td class="px-4 py-2 text-right">{{ d.imports }}</td>
                            <td class="px-4 py-2 text-right text-green-700">{{ d.suggested }}</td>
                            <td class="px-4 py-2 text-right text-amber-600">{{ d.ambiguous }}</td>
                            <td class="px-4 py-2 text-right text-red-600">{{ d.unmatched }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ d.pending_payables }}</td>
                        </tr>
                    </tbody>
                    <tbody v-if="!days.length">
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400">Nenhum dia com conciliação ainda.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Day report -->
            <template v-if="dayReport">
                <div class="bg-white rounded-xl border border-gray-100 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <button
                                    type="button"
                                    class="text-sm text-blue-700 hover:underline inline-flex items-center gap-1"
                                    @click="clearDay"
                                >
                                    <i class="pi pi-arrow-left text-xs" />
                                    Voltar aos dias
                                </button>
                            </div>
                            <h2 class="text-lg font-bold text-gray-800">Relatório: {{ dayReport.label }}</h2>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ dayReport.accounts?.map(a => a.name).join(', ') || 'Nenhuma conta com extrato neste dia' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-if="isConciliador && kpis && kpis.imports > 0 && !dayConciliated"
                                type="button"
                                :disabled="resetDayForm.processing"
                                class="px-3 py-2 text-sm rounded-lg border border-red-200 text-red-700 hover:bg-red-50 disabled:opacity-50"
                                @click="resetDay"
                            >
                                Começar do zero
                            </button>
                            <button
                                v-if="isConciliador && kpis && kpis.imports > 0"
                                type="button"
                                :disabled="batchDayForm.processing || dayConciliated || !canConciliateDay"
                                class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                :title="!canConciliateDay && !dayConciliated ? 'Resolva pendências do OFX antes de conciliar' : undefined"
                                @click="batchDay"
                            >
                                {{ dayConciliated ? 'Dia já conciliado' : 'Conciliar dia' }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="dayConciliated"
                        class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900"
                    >
                        Dia conciliado. Tarifas/aplicações/resgates e arquivos OFX estão preservados para consulta.
                    </div>
                    <div
                        v-else-if="isConciliador && (dayReport.conciliate_blockers?.length)"
                        class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    >
                        <p class="font-medium mb-1">Dia incompleto — Conciliar dia bloqueado</p>
                        <p class="text-xs text-amber-800/80 mb-2">
                            Todo débito do OFX precisa estar resolvido (match aceito ou tarifa/aplicação/resgate).
                            Títulos só no sistema (sem OFX) podem sobrar e não bloqueiam.
                        </p>
                        <ul class="list-disc pl-5 space-y-0.5">
                            <li v-for="(b, i) in dayReport.conciliate_blockers" :key="i">{{ b }}</li>
                        </ul>
                    </div>

                    <div
                        v-if="kpis && kpis.imports === 0"
                        class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    >
                        Nenhum extrato OFX importado neste dia ainda.
                        Se você acabou de enviar arquivos e eles falharam (conta não cadastrada, período etc.), veja o resultado da importação acima e corrija antes de abrir o relatório.
                    </div>

                    <!-- KPIs -->
                    <div v-if="kpis" class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-3">
                            <p class="text-xs text-blue-700">Extratos</p>
                            <p class="text-xl font-bold text-blue-900">{{ kpis.imports }}</p>
                        </div>
                        <div class="bg-green-50 border border-green-100 rounded-xl p-3">
                            <p class="text-xs text-green-700">Sugeridos</p>
                            <p class="text-xl font-bold text-green-900">{{ kpis.matched }}</p>
                        </div>
                        <div class="bg-amber-50 border border-amber-100 rounded-xl p-3">
                            <p class="text-xs text-amber-700">Ambíguos</p>
                            <p class="text-xl font-bold text-amber-900">{{ kpis.ambiguous }}</p>
                        </div>
                        <div class="bg-red-50 border border-red-100 rounded-xl p-3">
                            <p class="text-xs text-red-700">Só OFX</p>
                            <p class="text-xl font-bold text-red-900">{{ kpis.ofx_only }}</p>
                        </div>
                        <div class="bg-violet-50 border border-violet-100 rounded-xl p-3">
                            <p class="text-xs text-violet-700">Tarifa/Apl/Resg</p>
                            <p class="text-xl font-bold text-violet-900">{{ kpis.bank_ops }}</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 rounded-xl p-3">
                            <p class="text-xs text-gray-600">Só título</p>
                            <p class="text-xl font-bold text-gray-800">{{ kpis.payable_only }}</p>
                        </div>
                    </div>

                    <!-- Seções em accordion (uma aberta por vez) -->
                    <div class="space-y-2 mb-2">

                    <!-- Matches -->
                    <section v-if="matched.length" class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                        <button
                            type="button"
                            class="w-full px-4 py-3 flex items-center gap-3 text-left hover:bg-gray-50"
                            @click="toggleSection('matched')"
                        >
                            <i :class="['pi text-gray-400 text-xs', isSectionOpen('matched') ? 'pi-chevron-down' : 'pi-chevron-right']" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-800">Sugestões de match</p>
                                <p class="text-xs text-gray-400">OFX × título · aceite ou rejeite</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-gray-900">{{ matched.length }}</p>
                                <p class="text-xs text-gray-500 whitespace-nowrap">{{ formatMoney(sumAbs(matched)) }}</p>
                            </div>
                        </button>
                        <div v-if="isSectionOpen('matched')" class="border-t border-gray-100 px-4 py-3 space-y-3">
                            <div
                                v-for="tx in matched"
                                :key="tx.id"
                                class="rounded-xl border border-gray-200 bg-white overflow-hidden"
                            >
                                <div class="grid md:grid-cols-[1fr_auto_1fr] gap-0">
                                    <div class="p-4 bg-slate-50 border-b md:border-b-0 md:border-r border-gray-100">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mb-2">Extrato OFX</p>
                                        <p class="text-sm font-medium text-gray-900 leading-snug">{{ tx.description || tx.memo || '—' }}</p>
                                        <p v-if="tx.memo && tx.description && tx.memo !== tx.description" class="text-xs text-gray-500 mt-1 leading-snug">{{ tx.memo }}</p>
                                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600 items-baseline">
                                            <span class="font-semibold text-red-700 text-sm whitespace-nowrap">{{ formatMoney(tx.amount) }}</span>
                                            <span class="text-gray-400">débito</span>
                                            <span>{{ formatDate(tx.date) }}</span>
                                            <span v-if="tx.bank_account_name">{{ tx.bank_account_name }}</span>
                                            <span v-if="tx.ofx_file_name" class="text-gray-400 truncate max-w-[12rem]">{{ tx.ofx_file_name }}</span>
                                        </div>
                                    </div>
                                    <div class="hidden md:flex items-center justify-center px-2 bg-white">
                                        <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xs font-bold">↔</div>
                                    </div>
                                    <div class="p-4 bg-emerald-50/40">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700 mb-2">Título no Hub</p>
                                        <p class="text-sm font-medium text-gray-900">
                                            Nº {{ tx.payable?.title_number ?? '—' }}
                                            <span v-if="tx.payable?.nickname" class="font-normal text-gray-700"> — {{ tx.payable.nickname }}</span>
                                        </p>
                                        <p v-if="tx.payable?.description" class="text-xs text-gray-600 mt-1 leading-snug">{{ tx.payable.description }}</p>
                                        <p class="text-xs text-gray-800 mt-2 leading-snug font-medium">{{ tx.payable?.supplier_name ?? '—' }}</p>
                                        <div class="mt-3 space-y-1 text-xs text-gray-600">
                                            <p><span class="text-gray-400">Empresa:</span> {{ tx.payable?.empresa_nome || '—' }}</p>
                                            <p v-if="tx.payable?.filial_label"><span class="text-gray-400">Filial:</span> {{ tx.payable.filial_label }}</p>
                                            <p>
                                                <span class="text-gray-400">Valor:</span>
                                                <span class="font-semibold text-gray-900 whitespace-nowrap">{{ formatMoney(tx.payable?.amount) }}</span>
                                                <span v-if="tx.payable?.paid_at" class="text-gray-400"> · pago {{ formatDate(tx.payable.paid_at) }}</span>
                                            </p>
                                            <p class="flex flex-wrap gap-2 items-center pt-1">
                                                <Tag :value="confidenceLabel(tx.match_confidence)" severity="info" class="text-xs" />
                                                <Tag
                                                    :value="tx.match_status === 'pending' ? 'Sugerido' : (tx.match_status === 'accepted' ? 'Aceito' : 'Manual')"
                                                    :severity="statusTagSeverity(tx.match_status)"
                                                    class="text-xs"
                                                />
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="isConciliador" class="px-4 py-3 border-t border-gray-100 bg-white flex flex-wrap gap-2 justify-end">
                                    <button
                                        v-if="tx.match_status === 'pending'"
                                        type="button"
                                        class="text-sm px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700"
                                        @click="acceptTx(tx.id)"
                                    >Aceitar</button>
                                    <button
                                        type="button"
                                        class="text-sm px-3 py-1.5 border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50"
                                        @click="rejectTx(tx.id)"
                                    >{{ tx.match_status === 'pending' ? 'Rejeitar sugestão' : 'Desfazer' }}</button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Ambíguos -->
                    <section v-if="ambiguous.length" class="rounded-xl border border-amber-200 bg-white overflow-hidden">
                        <button type="button" class="w-full px-4 py-3 flex items-center gap-3 text-left hover:bg-amber-50/50" @click="toggleSection('ambiguous')">
                            <i :class="['pi text-amber-500 text-xs', isSectionOpen('ambiguous') ? 'pi-chevron-down' : 'pi-chevron-right']" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-amber-800">Ambíguos</p>
                                <p class="text-xs text-amber-600/80">Vincule manualmente</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-amber-900">{{ ambiguous.length }}</p>
                                <p class="text-xs text-amber-700 whitespace-nowrap">{{ formatMoney(sumAbs(ambiguous)) }}</p>
                            </div>
                        </button>
                        <div v-if="isSectionOpen('ambiguous')" class="border-t border-amber-100 px-4 py-3 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-amber-50 text-xs text-amber-600 uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Descrição OFX</th>
                                        <th class="px-3 py-2 text-right">Valor</th>
                                        <th class="px-3 py-2 text-left">Candidatos</th>
                                        <th v-if="isConciliador" class="px-3 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="tx in ambiguous" :key="tx.id" class="border-t border-amber-50">
                                        <td class="px-3 py-2 text-gray-700 truncate max-w-xs">{{ tx.description || tx.memo || '—' }}</td>
                                        <td class="px-3 py-2 text-right font-medium">{{ formatMoney(tx.amount) }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-500">
                                            <template v-if="tx.ambiguous_candidates?.length">
                                                <div v-for="(c, i) in tx.ambiguous_candidates" :key="i" class="mb-0.5">
                                                    {{ c.title_number }}
                                                    <span v-if="c.empresa_nome" class="text-gray-400"> · {{ c.empresa_nome }}</span>
                                                    — {{ c.supplier_name }}
                                                </div>
                                            </template>
                                            <template v-else>—</template>
                                        </td>
                                        <td v-if="isConciliador" class="px-3 py-2 text-right">
                                            <button type="button" class="text-xs px-2 py-1 bg-amber-100 text-amber-700 rounded hover:bg-amber-200" @click="openLink(tx.id)">Vincular</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Tarifas -->
                    <section v-if="tarifas.length" class="rounded-xl border border-violet-200 bg-white overflow-hidden">
                        <button type="button" class="w-full px-4 py-3 flex items-center gap-3 text-left hover:bg-violet-50/40" @click="toggleSection('tarifas')">
                            <i :class="['pi text-violet-500 text-xs', isSectionOpen('tarifas') ? 'pi-chevron-down' : 'pi-chevron-right']" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-violet-900">Tarifas</p>
                                <p class="text-xs text-violet-600/70">Sem ação — gravadas ao conciliar o dia</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-violet-900">{{ tarifas.length }}</p>
                                <p class="text-xs text-violet-700 whitespace-nowrap">{{ formatMoney(sumAbs(tarifas)) }}</p>
                            </div>
                        </button>
                        <div v-if="isSectionOpen('tarifas')" class="border-t border-violet-100 px-4 py-3 space-y-2">
                            <div v-for="tx in tarifas" :key="tx.id" class="rounded-lg border border-violet-100 bg-violet-50/40 px-4 py-3">
                                <p class="text-sm font-medium text-gray-900 leading-snug">{{ tx.description || tx.memo || '—' }}</p>
                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-500">
                                    <span class="font-semibold text-gray-800 whitespace-nowrap">{{ formatMoney(tx.amount) }}</span>
                                    <span>{{ formatDate(tx.date) }}</span>
                                    <span v-if="tx.bank_account_name">{{ tx.bank_account_name }}</span>
                                    <span v-if="tx.ofx_file_name" class="truncate max-w-[14rem]">{{ tx.ofx_file_name }}</span>
                                    <span v-if="tx.match_status === 'non_payable'" class="text-green-700 font-medium">Registrado</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Aplicações -->
                    <section v-if="aplicacoes.length" class="rounded-xl border border-indigo-200 bg-white overflow-hidden">
                        <button type="button" class="w-full px-4 py-3 flex items-center gap-3 text-left hover:bg-indigo-50/40" @click="toggleSection('aplicacoes')">
                            <i :class="['pi text-indigo-500 text-xs', isSectionOpen('aplicacoes') ? 'pi-chevron-down' : 'pi-chevron-right']" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-indigo-900">Aplicações</p>
                                <p class="text-xs text-indigo-600/70">Sem ação — gravadas ao conciliar o dia</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-indigo-900">{{ aplicacoes.length }}</p>
                                <p class="text-xs text-indigo-700 whitespace-nowrap">{{ formatMoney(sumAbs(aplicacoes)) }}</p>
                            </div>
                        </button>
                        <div v-if="isSectionOpen('aplicacoes')" class="border-t border-indigo-100 px-4 py-3 space-y-2">
                            <div v-for="tx in aplicacoes" :key="tx.id" class="rounded-lg border border-indigo-100 bg-indigo-50/40 px-4 py-3">
                                <p class="text-sm font-medium text-gray-900 leading-snug">{{ tx.description || tx.memo || '—' }}</p>
                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-500">
                                    <span class="font-semibold text-gray-800 whitespace-nowrap">{{ formatMoney(tx.amount) }}</span>
                                    <span>{{ formatDate(tx.date) }}</span>
                                    <span v-if="tx.bank_account_name">{{ tx.bank_account_name }}</span>
                                    <span v-if="tx.ofx_file_name" class="truncate max-w-[14rem]">{{ tx.ofx_file_name }}</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Resgates -->
                    <section v-if="resgates.length" class="rounded-xl border border-sky-200 bg-white overflow-hidden">
                        <button type="button" class="w-full px-4 py-3 flex items-center gap-3 text-left hover:bg-sky-50/40" @click="toggleSection('resgates')">
                            <i :class="['pi text-sky-500 text-xs', isSectionOpen('resgates') ? 'pi-chevron-down' : 'pi-chevron-right']" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-sky-900">Resgates</p>
                                <p class="text-xs text-sky-600/70">Sem ação — gravados ao conciliar o dia</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-sky-900">{{ resgates.length }}</p>
                                <p class="text-xs text-sky-700 whitespace-nowrap">{{ formatMoney(sumAbs(resgates)) }}</p>
                            </div>
                        </button>
                        <div v-if="isSectionOpen('resgates')" class="border-t border-sky-100 px-4 py-3 space-y-2">
                            <div v-for="tx in resgates" :key="tx.id" class="rounded-lg border border-sky-100 bg-sky-50/40 px-4 py-3">
                                <p class="text-sm font-medium text-gray-900 leading-snug">{{ tx.description || tx.memo || '—' }}</p>
                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-500">
                                    <span class="font-semibold text-gray-800 whitespace-nowrap">{{ formatMoney(tx.amount) }}</span>
                                    <span>{{ formatDate(tx.date) }}</span>
                                    <span v-if="tx.bank_account_name">{{ tx.bank_account_name }}</span>
                                    <span v-if="tx.ofx_file_name" class="truncate max-w-[14rem]">{{ tx.ofx_file_name }}</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Só OFX -->
                    <section v-if="ofxOnly.length" class="rounded-xl border border-red-200 bg-white overflow-hidden">
                        <button type="button" class="w-full px-4 py-3 flex items-center gap-3 text-left hover:bg-red-50/40" @click="toggleSection('ofx_only')">
                            <i :class="['pi text-red-500 text-xs', isSectionOpen('ofx_only') ? 'pi-chevron-down' : 'pi-chevron-right']" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-red-800">Só no OFX — sem título</p>
                                <p class="text-xs text-red-600/70">Vincule a um título antes de conciliar o dia</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-red-900">{{ ofxOnly.length }}</p>
                                <p class="text-xs text-red-700 whitespace-nowrap">{{ formatMoney(sumAbs(ofxOnly)) }}</p>
                            </div>
                        </button>
                        <div v-if="isSectionOpen('ofx_only')" class="border-t border-red-100 px-4 py-3 space-y-2">
                            <div
                                v-for="tx in ofxOnly"
                                :key="tx.id"
                                class="rounded-lg border border-gray-200 bg-white px-4 py-3 flex flex-wrap items-center gap-3 justify-between"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 leading-snug">{{ tx.description || tx.memo || '—' }}</p>
                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-500">
                                        <span class="font-semibold text-red-700 whitespace-nowrap">{{ formatMoney(tx.amount) }}</span>
                                        <span class="text-gray-400">débito</span>
                                        <span>{{ formatDate(tx.date) }}</span>
                                        <span v-if="tx.bank_account_name">{{ tx.bank_account_name }}</span>
                                        <span v-if="tx.ofx_file_name" class="truncate max-w-[14rem]">{{ tx.ofx_file_name }}</span>
                                    </div>
                                </div>
                                <button
                                    v-if="isConciliador"
                                    type="button"
                                    class="text-sm px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shrink-0"
                                    @click="openLink(tx.id)"
                                >Vincular título</button>
                            </div>
                        </div>
                    </section>

                    <!-- Só título -->
                    <section v-if="payableOnly.length" class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                        <button type="button" class="w-full px-4 py-3 flex items-center gap-3 text-left hover:bg-gray-50" @click="toggleSection('payable_only')">
                            <i :class="['pi text-gray-400 text-xs', isSectionOpen('payable_only') ? 'pi-chevron-down' : 'pi-chevron-right']" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-700">Só no sistema — sem OFX</p>
                                <p class="text-xs text-gray-400">Pode sobrar — não bloqueia a conciliação do dia</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-gray-800">{{ payableOnly.length }}</p>
                                <p class="text-xs text-gray-500 whitespace-nowrap">{{ formatMoney(sumAbs(payableOnly)) }}</p>
                            </div>
                        </button>
                        <div v-if="isSectionOpen('payable_only')" class="border-t border-gray-100 px-4 py-3 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Título</th>
                                        <th class="px-3 py-2 text-left">Empresa</th>
                                        <th class="px-3 py-2 text-left">Fornecedor</th>
                                        <th class="px-3 py-2 text-right">Valor</th>
                                        <th class="px-3 py-2 text-left">Pago em</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in payableOnly" :key="p.id" class="border-t border-gray-50">
                                        <td class="px-3 py-2">{{ p.title_number ?? '—' }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-600">
                                            {{ p.empresa_nome || '—' }}
                                            <span v-if="p.filial_label" class="block text-gray-400">{{ p.filial_label }}</span>
                                        </td>
                                        <td class="px-3 py-2 truncate max-w-xs">{{ p.supplier_name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatMoney(p.amount) }}</td>
                                        <td class="px-3 py-2">{{ formatDate(p.paid_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    </div>
                </div>
            </template>
        </div>

        <!-- Link modal -->
        <div v-if="linkTxId !== null" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="closeLink">
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-base font-bold text-gray-800 mb-3">Vincular título manualmente</h3>
                <input
                    v-model="linkSearchQuery"
                    type="text"
                    placeholder="Buscar por título, fornecedor…"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-blue-300"
                />
                <div v-if="linkLoading" class="text-xs text-gray-400 mb-2">Buscando…</div>
                <div v-if="linkResults.length" class="max-h-48 overflow-y-auto border border-gray-100 rounded-lg divide-y divide-gray-50 mb-3">
                    <div
                        v-for="p in linkResults"
                        :key="p.id"
                        :class="['px-3 py-2 cursor-pointer hover:bg-blue-50 text-sm', linkPayableId === p.id ? 'bg-blue-100' : '']"
                        @click="linkPayableId = p.id"
                    >
                        <span class="font-medium">{{ p.title_number }}</span>
                        <span v-if="p.empresa_nome" class="text-xs text-blue-700 ml-2">{{ p.empresa_nome }}</span>
                        <span class="text-gray-500 ml-2">{{ p.supplier_name }}</span>
                        <span class="float-right font-medium">{{ formatMoney(p.amount) }}</span>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 text-sm rounded-lg border border-gray-200 hover:bg-gray-50" @click="closeLink">Cancelar</button>
                    <button
                        type="button"
                        :disabled="!linkPayableId || linkForm.processing"
                        class="px-4 py-2 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
                        @click="submitLink"
                    >Vincular</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
