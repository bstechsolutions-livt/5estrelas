<script setup>
import { ref, computed, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
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

watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.add({ severity: 'success', summary: 'Sucesso', detail: flash.success, life: 5000 })
    if (flash?.error)   toast.add({ severity: 'error',   summary: 'Erro',    detail: flash.error,   life: 5000 })
    if (flash && Object.prototype.hasOwnProperty.call(flash, 'importResults')) {
        localImportResults.value = flash.importResults?.length ? flash.importResults : null
    }
}, { deep: true })

const localImportResults = ref(props.importResults ?? null)

// Upload — arrastar/soltar + toque
const uploadForm = useForm({ files: [] })
const fileInput = ref(null)
const dragOver = ref(false)

function ofxFilesFromList(fileList) {
    return Array.from(fileList || []).filter((f) => /\.ofx$/i.test(f.name))
}

function submitOfxFiles(files) {
    if (!files.length) {
        toast.add({ severity: 'warn', summary: 'Nenhum OFX', detail: 'Use arquivos .ofx.', life: 4000 })
        return
    }
    uploadForm.files = files
    uploadForm.post('/financeiro/contas-pagar/conciliacao/upload-batch', {
        forceFormData: true,
        onError: (errors) => {
            const msg = errors.files || errors.file || Object.values(errors)[0]
            if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 })
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

function openDay(date) {
    router.get('/financeiro/contas-pagar/conciliacao', { date }, { preserveState: false })
}

function clearDay() {
    router.get('/financeiro/contas-pagar/conciliacao', {}, { preserveState: false })
}

const kpis = computed(() => props.dayReport?.kpis ?? null)
const matched     = computed(() => props.dayReport?.matched     ?? [])
const ofxOnly     = computed(() => props.dayReport?.ofx_only    ?? [])
const payableOnly = computed(() => props.dayReport?.payable_only ?? [])
const ambiguous   = computed(() => props.dayReport?.ambiguous   ?? [])

// Link
const linkTxId = ref(null)
const linkPayableId = ref(null)
const linkSearchQuery = ref('')
const linkResults = ref([])

async function searchForLink(query) {
    if (!query || query.length < 2) { linkResults.value = []; return }
    const date = props.dayReport?.date ?? props.filters?.date
    const params = new URLSearchParams({ query, ...(date ? { date } : {}) })
    const resp = await fetch(`/financeiro/contas-pagar/conciliacao/search-payables?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin',
    })
    linkResults.value = await resp.json()
}

watch(linkSearchQuery, (q) => searchForLink(q))

const linkForm = useForm({ payable_id: null })
function submitLink() {
    if (!linkPayableId.value) return
    linkForm.payable_id = linkPayableId.value
    linkForm.post(`/financeiro/contas-pagar/conciliacao/transactions/${linkTxId.value}/link`, {
        onSuccess: () => { linkTxId.value = null; router.reload() },
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

function formatDate(d) {
    if (!d) return '—'
    return new Date(String(d).slice(0, 10) + 'T12:00:00').toLocaleDateString('pt-BR')
}
function formatMoney(v) {
    const n = Number(v)
    if (Number.isNaN(n)) return '—'
    return Math.abs(n).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}
</script>

<template>
    <AppLayout>
        <Toast />
        <ConfirmDialog />
        <div class="px-3 pb-8 space-y-4">
            <h1 class="text-xl font-bold text-gray-800 pt-2">Conciliação Bancária</h1>

            <!-- Upload -->
            <div v-if="isConciliador" class="bg-white rounded-xl border border-gray-100 p-4">
                <p class="text-sm font-semibold text-gray-700 mb-2">Importar OFX</p>
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
                    class="rounded-xl border-2 border-dashed px-3 py-8 text-center transition"
                    :class="dragOver
                        ? 'border-blue-500 bg-blue-50'
                        : uploadForm.processing
                            ? 'border-gray-200 bg-gray-50 opacity-60'
                            : 'border-gray-300'"
                    @click="!uploadForm.processing && fileInput?.click()"
                    @dragenter.prevent="dragOver = true"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="onDrop"
                >
                    <i class="pi pi-cloud-upload text-2xl text-gray-400 mb-1 block" />
                    <p class="text-sm font-medium text-gray-700">
                        {{ dragOver ? 'Solte aqui' : 'Arraste os .ofx' }}
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">ou toque para selecionar</p>
                </div>
                <p v-if="uploadForm.processing" class="text-xs text-blue-600 mt-2">Processando…</p>
            </div>

            <!-- Import results -->
            <div v-if="localImportResults && localImportResults.length" class="space-y-2">
                <div
                    v-for="(card, i) in localImportResults"
                    :key="i"
                    :class="['rounded-xl border p-3 text-sm', card.ok ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200']"
                >
                    <p class="font-medium truncate" :class="card.ok ? 'text-green-800' : 'text-red-800'">{{ card.file_name }}</p>
                    <p v-if="card.ok" class="text-xs text-green-700 mt-1">
                        {{ formatDate(card.date) }} · {{ card.bank_account_name ?? '?' }} · {{ card.transaction_count }} transações
                    </p>
                    <p v-if="card.ok && card.account_created" class="text-xs text-amber-700 mt-0.5">Conta criada automaticamente</p>
                    <p v-else class="text-xs text-red-700 mt-1">{{ card.error }}</p>
                    <button
                        v-if="card.ok && card.date && card.date !== (filters?.date || dayReport?.date)"
                        type="button"
                        class="text-xs text-blue-600 mt-1"
                        @click="openDay(card.date)"
                    >
                        Abrir relatório deste dia →
                    </button>
                </div>
            </div>

            <!-- Days list -->
            <div class="bg-white rounded-xl border border-gray-100">
                <p class="px-4 py-3 text-sm font-semibold text-gray-700 border-b border-gray-100">Dias recentes</p>
                <div v-for="d in days" :key="d.date" class="px-4 py-3 border-b border-gray-50 last:border-0 cursor-pointer" @click="openDay(d.date)">
                    <div class="flex justify-between">
                        <span class="font-medium text-blue-700 text-sm">{{ d.label }}</span>
                        <span class="text-xs text-gray-400">{{ d.imports }} extrato(s)</span>
                    </div>
                    <div class="flex gap-3 text-xs mt-1">
                        <span class="text-green-600">✓ {{ d.suggested }}</span>
                        <span class="text-amber-500">? {{ d.ambiguous }}</span>
                        <span class="text-red-500">✗ {{ d.unmatched }}</span>
                    </div>
                </div>
                <div v-if="!days.length" class="px-4 py-6 text-center text-sm text-gray-400">Nenhum dia ainda.</div>
            </div>

            <!-- Day report -->
            <div v-if="dayReport" class="bg-white rounded-xl border border-gray-100 p-4 space-y-4">
                <button type="button" class="text-sm text-blue-700" @click="clearDay">
                    ← Voltar aos dias
                </button>
                <div class="flex justify-between items-start gap-2">
                    <h2 class="font-bold text-gray-800">{{ dayReport.label }}</h2>
                    <div class="flex flex-col gap-1 items-end">
                        <button
                            v-if="isConciliador && kpis && kpis.imports > 0"
                            type="button"
                            class="text-xs px-3 py-1.5 border border-red-200 text-red-700 rounded-lg"
                            @click="resetDay"
                        >Começar do zero</button>
                        <button
                            v-if="isConciliador && kpis && kpis.accepted > 0"
                            type="button"
                            class="text-xs px-3 py-1.5 bg-green-600 text-white rounded-lg"
                            @click="batchDay"
                        >Conciliar</button>
                    </div>
                </div>

                <div
                    v-if="kpis && kpis.imports === 0"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
                >
                    Nenhum extrato OFX neste dia. Se o upload falhou (conta não cadastrada), corrija e envie de novo.
                </div>

                <!-- KPIs -->
                <div v-if="kpis" class="grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="bg-green-50 rounded-lg p-2">
                        <p class="text-green-700">Sugeridos</p>
                        <p class="font-bold text-green-900 text-lg">{{ kpis.matched }}</p>
                    </div>
                    <div class="bg-amber-50 rounded-lg p-2">
                        <p class="text-amber-700">Ambíguos</p>
                        <p class="font-bold text-amber-900 text-lg">{{ kpis.ambiguous }}</p>
                    </div>
                    <div class="bg-red-50 rounded-lg p-2">
                        <p class="text-red-700">Só OFX</p>
                        <p class="font-bold text-red-900 text-lg">{{ kpis.ofx_only }}</p>
                    </div>
                </div>

                <!-- Matched -->
                <section v-if="matched.length">
                    <p class="text-sm font-semibold text-gray-700 mb-1">Sugestões de match</p>
                    <p class="text-xs text-gray-400 mb-2">OFX à esquerda · título à direita. Desfazer volta para “Só no OFX”.</p>
                    <div v-for="tx in matched" :key="tx.id" class="border border-gray-200 rounded-xl overflow-hidden mb-3 bg-white">
                        <div class="p-3 bg-slate-50 border-b border-gray-100">
                            <p class="text-[10px] font-semibold uppercase text-slate-500 mb-1">Extrato OFX</p>
                            <p class="text-sm font-medium text-gray-900 leading-snug">{{ tx.description || tx.memo || '—' }}</p>
                            <p class="text-xs text-gray-600 mt-1">
                                <span class="font-semibold text-red-700 whitespace-nowrap">{{ formatMoney(tx.amount) }}</span>
                                <span class="text-gray-400 mx-1">débito</span>
                                <span>{{ formatDate(tx.date) }}</span>
                            </p>
                            <p v-if="tx.bank_account_name" class="text-xs text-gray-400 mt-0.5">{{ tx.bank_account_name }}</p>
                        </div>
                        <div class="p-3 bg-emerald-50/50">
                            <p class="text-[10px] font-semibold uppercase text-emerald-700 mb-1">Título no Hub</p>
                            <p class="text-sm font-medium text-gray-900">
                                Nº {{ tx.payable?.title_number ?? '—' }}
                                <span v-if="tx.payable?.nickname" class="font-normal text-gray-700"> — {{ tx.payable.nickname }}</span>
                            </p>
                            <p v-if="tx.payable?.description" class="text-xs text-gray-600 mt-1 leading-snug">{{ tx.payable.description }}</p>
                            <p class="text-xs text-gray-800 mt-1.5 font-medium">{{ tx.payable?.supplier_name ?? '—' }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ tx.payable?.empresa_nome || '—' }}</p>
                            <p v-if="tx.payable?.filial_label" class="text-xs text-gray-400">{{ tx.payable.filial_label }}</p>
                            <p class="text-xs text-gray-600 mt-1 whitespace-nowrap">
                                {{ formatMoney(tx.payable?.amount) }}
                                <span v-if="tx.payable?.paid_at" class="text-gray-400"> · pago {{ formatDate(tx.payable.paid_at) }}</span>
                            </p>
                        </div>
                        <div v-if="isConciliador" class="px-3 py-2 border-t border-gray-100 flex gap-2 justify-end">
                            <button
                                v-if="tx.match_status === 'pending'"
                                type="button"
                                class="text-xs px-2.5 py-1.5 bg-green-600 text-white rounded-lg"
                                @click="acceptTx(tx.id)"
                            >Aceitar</button>
                            <button
                                type="button"
                                class="text-xs px-2.5 py-1.5 border border-gray-200 text-gray-700 rounded-lg"
                                @click="rejectTx(tx.id)"
                            >{{ tx.match_status === 'pending' ? 'Rejeitar' : 'Desfazer' }}</button>
                        </div>
                    </div>
                </section>

                <!-- Ambiguous -->
                <section v-if="ambiguous.length">
                    <p class="text-sm font-semibold text-amber-700 mb-2">⚠️ Ambíguos</p>
                    <div v-for="tx in ambiguous" :key="tx.id" class="border border-amber-100 rounded-lg p-3 mb-2">
                        <div class="flex justify-between text-sm">
                            <span class="truncate text-gray-700 max-w-[60%]">{{ tx.description || '—' }}</span>
                            <span class="font-medium">{{ formatMoney(tx.amount) }}</span>
                        </div>
                        <button
                            v-if="isConciliador"
                            type="button"
                            class="mt-2 text-xs px-2 py-1 bg-amber-100 text-amber-700 rounded"
                            @click="linkTxId = tx.id; linkPayableId = null; linkSearchQuery = ''; linkResults = []"
                        >Vincular</button>
                    </div>
                </section>

                <!-- OFX only -->
                <section v-if="ofxOnly.length">
                    <p class="text-sm font-semibold text-red-700 mb-1">Só no OFX — sem título</p>
                    <p class="text-xs text-gray-400 mb-2">Inclui débitos após rejeitar/desfazer. Pode vincular de novo.</p>
                    <div v-for="tx in ofxOnly" :key="tx.id" class="border border-red-100 rounded-lg p-3 mb-2 bg-white">
                        <p class="text-sm font-medium text-gray-900 leading-snug">{{ tx.description || tx.memo || '—' }}</p>
                        <p class="text-xs text-gray-600 mt-1">
                            <span class="font-semibold">{{ formatMoney(tx.amount) }}</span>
                            <span class="mx-1">·</span>{{ formatDate(tx.date) }}
                        </p>
                        <p v-if="tx.bank_account_name" class="text-xs text-gray-400 mt-0.5">{{ tx.bank_account_name }}</p>
                        <button
                            v-if="isConciliador"
                            type="button"
                            class="mt-2 text-xs px-2.5 py-1.5 bg-blue-600 text-white rounded-lg"
                            @click="linkTxId = tx.id; linkPayableId = null; linkSearchQuery = ''; linkResults = []"
                        >Vincular título</button>
                    </div>
                </section>

                <!-- Payable only -->
                <section v-if="payableOnly.length">
                    <p class="text-sm font-semibold text-gray-600 mb-2">🟡 Só no sistema</p>
                    <div v-for="p in payableOnly" :key="p.id" class="border border-gray-100 rounded-lg p-3 mb-2 text-sm">
                        <div class="flex justify-between">
                            <span>{{ p.title_number ?? '—' }}</span>
                            <span class="font-medium">{{ formatMoney(p.amount) }}</span>
                        </div>
                        <p v-if="p.empresa_nome" class="text-xs text-blue-700 mt-0.5">{{ p.empresa_nome }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ p.supplier_name }}</p>
                    </div>
                </section>
            </div>
        </div>

        <!-- Link modal -->
        <div v-if="linkTxId !== null" class="fixed inset-0 bg-black/40 flex items-end z-50" @click.self="linkTxId = null">
            <div class="bg-white rounded-t-xl p-5 w-full">
                <p class="font-bold text-gray-800 mb-3">Vincular título</p>
                <input
                    v-model="linkSearchQuery"
                    type="text"
                    placeholder="Buscar título ou fornecedor…"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-2"
                />
                <div class="max-h-48 overflow-y-auto divide-y divide-gray-50 mb-3">
                    <div
                        v-for="p in linkResults"
                        :key="p.id"
                        :class="['py-2 px-1 cursor-pointer text-sm', linkPayableId === p.id ? 'bg-blue-50' : '']"
                        @click="linkPayableId = p.id"
                    >
                        <span class="font-medium">{{ p.title_number }}</span>
                        <span v-if="p.empresa_nome" class="text-blue-700 ml-2 text-xs">{{ p.empresa_nome }}</span>
                        <span class="text-gray-500 ml-2">{{ p.supplier_name }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="flex-1 py-2 text-sm border border-gray-200 rounded-lg" @click="linkTxId = null">Cancelar</button>
                    <button
                        type="button"
                        :disabled="!linkPayableId"
                        class="flex-1 py-2 text-sm bg-blue-600 text-white rounded-lg disabled:opacity-50"
                        @click="submitLink"
                    >Vincular</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
