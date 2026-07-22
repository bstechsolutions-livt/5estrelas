<script setup>
import { ref, computed, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
    isConciliador: Boolean,
    bankAccounts: { type: Array, default: () => [] },
    days: { type: Array, default: () => [] },
    dayReport: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
    importResults: { type: Array, default: null },
})

const toast = useToast()
const page = usePage()

// ── Flash messages ────────────────────────────────────────────────────────────
watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.add({ severity: 'success', summary: 'Sucesso', detail: flash.success, life: 5000 })
    if (flash?.error)   toast.add({ severity: 'error',   summary: 'Erro',    detail: flash.error,   life: 5000 })
    if (flash?.importResults) localImportResults.value = flash.importResults
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
    if (!props.dayReport?.date) return
    batchDayForm.date = props.dayReport.date
    batchDayForm.post('/financeiro/contas-pagar/conciliacao/batch-conciliate-day', {
        onSuccess: () => router.reload(),
    })
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatDate(d) {
    if (!d) return '—'
    return new Date(String(d).slice(0, 10) + 'T12:00:00').toLocaleDateString('pt-BR')
}
function formatMoney(v) {
    return Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}
function statusTagSeverity(status) {
    return { pending: 'info', accepted: 'success', manual: 'success', rejected: 'danger', unmatched: 'warn' }[status] ?? 'secondary'
}
function confidenceLabel(c) {
    return { high: 'Alta', medium: 'Média', low: 'Baixa', none: '—' }[c] ?? c
}
function dayRowClass(d) {
    return props.dayReport?.date === d.date ? 'bg-blue-50' : ''
}

const kpis = computed(() => props.dayReport?.kpis ?? null)
const matched  = computed(() => props.dayReport?.matched  ?? [])
const ofxOnly  = computed(() => props.dayReport?.ofx_only  ?? [])
const payableOnly = computed(() => props.dayReport?.payable_only ?? [])
const ambiguous   = computed(() => props.dayReport?.ambiguous   ?? [])
</script>

<template>
    <AppLayout>
        <Toast />
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
                            <p>📅 {{ formatDate(card.date) }}</p>
                            <p>🏦 {{ card.bank_account_name ?? 'Conta não identificada' }}</p>
                            <p>{{ card.transaction_count }} transações ({{ card.debit_count }} déb / {{ card.credit_count }} créd)</p>
                            <button
                                v-if="card.date"
                                type="button"
                                class="mt-1 text-blue-600 hover:underline"
                                @click="openDay(card.date)"
                            >Ver dia →</button>
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
                            <h2 class="text-lg font-bold text-gray-800">Relatório: {{ dayReport.label }}</h2>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ dayReport.accounts?.map(a => a.name).join(', ') || 'Nenhuma conta' }}
                            </p>
                        </div>
                        <button
                            v-if="isConciliador && kpis && kpis.matched > 0"
                            type="button"
                            :disabled="batchDayForm.processing"
                            class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 disabled:opacity-50"
                            @click="batchDay"
                        >
                            Conciliar dia ({{ kpis.matched }} aceitos)
                        </button>
                    </div>

                    <!-- KPIs -->
                    <div v-if="kpis" class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
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
                        <div class="bg-gray-50 border border-gray-100 rounded-xl p-3">
                            <p class="text-xs text-gray-600">Só título</p>
                            <p class="text-xl font-bold text-gray-800">{{ kpis.payable_only }}</p>
                        </div>
                    </div>

                    <!-- ── Matched ───────────────────────────────────────────────────── -->
                    <section v-if="matched.length" class="mb-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">✅ Sugestões de match</h3>
                        <div class="overflow-x-auto rounded-lg border border-gray-100">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Descrição OFX</th>
                                        <th class="px-3 py-2 text-right">Valor</th>
                                        <th class="px-3 py-2 text-left">Título</th>
                                        <th class="px-3 py-2 text-left">Fornecedor</th>
                                        <th class="px-3 py-2 text-center">Conf.</th>
                                        <th class="px-3 py-2 text-center">Status</th>
                                        <th v-if="isConciliador" class="px-3 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="tx in matched" :key="tx.id" class="border-t border-gray-50 hover:bg-gray-50">
                                        <td class="px-3 py-2 text-gray-700 truncate max-w-xs">{{ tx.description || tx.memo || '—' }}</td>
                                        <td class="px-3 py-2 text-right font-medium">{{ formatMoney(tx.amount) }}</td>
                                        <td class="px-3 py-2">{{ tx.payable?.title_number ?? '—' }}</td>
                                        <td class="px-3 py-2 truncate max-w-xs">{{ tx.payable?.supplier_name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <Tag :value="confidenceLabel(tx.match_confidence)" severity="info" class="text-xs" />
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <Tag :value="tx.match_status" :severity="statusTagSeverity(tx.match_status)" class="text-xs" />
                                        </td>
                                        <td v-if="isConciliador" class="px-3 py-2 text-right whitespace-nowrap space-x-2">
                                            <button
                                                v-if="tx.match_status === 'pending'"
                                                type="button"
                                                class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200"
                                                @click="acceptTx(tx.id)"
                                            >Aceitar</button>
                                            <button
                                                v-if="['pending', 'accepted', 'manual'].includes(tx.match_status)"
                                                type="button"
                                                class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200"
                                                @click="rejectTx(tx.id)"
                                            >Rejeitar</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- ── Ambiguous ─────────────────────────────────────────────────── -->
                    <section v-if="ambiguous.length" class="mb-6">
                        <h3 class="text-sm font-semibold text-amber-700 mb-2">⚠️ Ambíguos — vincule manualmente</h3>
                        <div class="overflow-x-auto rounded-lg border border-amber-100">
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
                                            {{ tx.ambiguous_candidates?.map(c => c.title_number ?? c.supplier_name).join(', ') || '—' }}
                                        </td>
                                        <td v-if="isConciliador" class="px-3 py-2 text-right">
                                            <button
                                                type="button"
                                                class="text-xs px-2 py-1 bg-amber-100 text-amber-700 rounded hover:bg-amber-200"
                                                @click="openLink(tx.id)"
                                            >Vincular</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- ── OFX only ──────────────────────────────────────────────────── -->
                    <section v-if="ofxOnly.length" class="mb-6">
                        <h3 class="text-sm font-semibold text-red-700 mb-2">🔴 Só no OFX — sem título</h3>
                        <div class="overflow-x-auto rounded-lg border border-red-100">
                            <table class="w-full text-sm">
                                <thead class="bg-red-50 text-xs text-red-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Descrição</th>
                                        <th class="px-3 py-2 text-right">Valor</th>
                                        <th class="px-3 py-2 text-left">Data</th>
                                        <th v-if="isConciliador" class="px-3 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="tx in ofxOnly" :key="tx.id" class="border-t border-red-50">
                                        <td class="px-3 py-2 text-gray-700 truncate max-w-xs">{{ tx.description || tx.memo || '—' }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatMoney(tx.amount) }}</td>
                                        <td class="px-3 py-2">{{ formatDate(tx.date) }}</td>
                                        <td v-if="isConciliador" class="px-3 py-2 text-right">
                                            <button
                                                type="button"
                                                class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
                                                @click="openLink(tx.id)"
                                            >Vincular</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- ── Payable only ──────────────────────────────────────────────── -->
                    <section v-if="payableOnly.length">
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">🟡 Só no sistema — sem OFX</h3>
                        <div class="overflow-x-auto rounded-lg border border-gray-100">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Título</th>
                                        <th class="px-3 py-2 text-left">Fornecedor</th>
                                        <th class="px-3 py-2 text-right">Valor</th>
                                        <th class="px-3 py-2 text-left">Pago em</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in payableOnly" :key="p.id" class="border-t border-gray-50">
                                        <td class="px-3 py-2">{{ p.title_number ?? '—' }}</td>
                                        <td class="px-3 py-2 truncate max-w-xs">{{ p.supplier_name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatMoney(p.amount) }}</td>
                                        <td class="px-3 py-2">{{ formatDate(p.paid_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
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
