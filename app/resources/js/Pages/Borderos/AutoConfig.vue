<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Checkbox from 'primevue/checkbox'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    config: Object,
    options: Object,
    preview: Object,
})

const { isMobile } = useDevice()
const page = usePage()
const toast = useToast()

const selectedKeys = ref(props.preview?.groups?.map(g => g.key) ?? [])

const dueGroupingOptions = computed(() =>
    Object.entries(props.options?.due_grouping ?? {}).map(([value, label]) => ({ value, label }))
)
const eligibilityOptions = computed(() =>
    Object.entries(props.options?.eligibility_mode ?? {}).map(([value, label]) => ({ value, label }))
)

const configForm = useForm({
    min_titles_per_group: props.config.min_titles_per_group,
    due_grouping: props.config.due_grouping,
    max_due_span_days: props.config.max_due_span_days,
    eligibility_mode: props.config.eligibility_mode,
    eligibility_due_days: props.config.eligibility_due_days,
    cron_enabled: props.config.cron_enabled,
})

const allSelected = computed({
    get: () => props.preview?.groups?.length > 0 && selectedKeys.value.length === props.preview.groups.length,
    set: (val) => {
        selectedKeys.value = val ? props.preview.groups.map(g => g.key) : []
    },
})

const generating = ref(false)

const showMaxSpan = computed(() => configForm.due_grouping === 'max_span')
const showEligibilityDays = computed(() => configForm.eligibility_mode === 'due_within_days')

function saveConfig() {
    configForm.post('/financeiro/borderos/automatico', {
        preserveScroll: true,
        onSuccess: () => {
            selectedKeys.value = props.preview?.groups?.map(g => g.key) ?? []
        },
    })
}

function toggleGroup(key, checked) {
    if (checked) {
        if (!selectedKeys.value.includes(key)) selectedKeys.value.push(key)
    } else {
        selectedKeys.value = selectedKeys.value.filter(k => k !== key)
    }
}

function generate() {
    if (!selectedKeys.value.length) return
    generating.value = true
    router.post('/financeiro/borderos/automatico/gerar', {
        group_keys: selectedKeys.value,
    }, {
        onFinish: () => { generating.value = false },
    })
}

function fmtMoney(v) {
    return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function fmtDate(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleString('pt-BR')
}

function segmentSeverity(type) {
    return type === 'dept' ? 'info' : type === 'ccu' ? 'warn' : 'secondary'
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Pronto', detail: msg, life: 4000 })
})
watch(() => page.props.flash?.error, (msg) => {
    if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 })
})

watch(() => props.preview?.groups, (groups) => {
    selectedKeys.value = groups?.map(g => g.key) ?? []
}, { deep: true })
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout" :title="isMobile ? 'Borderô auto' : undefined" :show-back="isMobile">
        <Toast />
        <div :class="isMobile ? 'px-4 py-3 pb-28' : 'max-w-6xl mx-auto'" dusk="bordero-auto-config-page">
            <div class="mb-6 flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                        Borderô Automático
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Configure as regras e veja a simulação com os <strong>títulos abertos agora</strong> — o mesmo cálculo do cron das 6h.
                    </p>
                </div>
                <Button label="Ver borderôs" icon="pi pi-list-check" severity="secondary" outlined size="small"
                    @click="router.visit('/financeiro/borderos?status=rascunho')" />
            </div>

            <!-- O que será aplicado -->
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6 text-sm text-blue-900">
                <p class="font-semibold mb-2">Regras que serão aplicadas (após salvar)</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li v-for="(line, i) in preview.rules_summary" :key="i">{{ line }}</li>
                </ul>
                <p v-if="config.cron_enabled" class="text-xs text-blue-700 mt-3">
                    Cron: todo dia às <strong>06:00</strong> gera <em>todos</em> os grupos abaixo em rascunho.
                    <span v-if="config.last_cron_run_at">
                        Última execução: {{ fmtDate(config.last_cron_run_at) }}
                        ({{ config.last_cron_created_count ?? 0 }} borderô(s)).
                    </span>
                    <span v-else> Ainda não rodou em produção.</span>
                </p>
            </div>

            <!-- Config -->
            <form @submit.prevent="saveConfig" class="bg-white rounded-xl border border-gray-100 p-4 mb-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-800">Configuração</h2>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Quais títulos entram na simulação?</label>
                        <Select v-model="configForm.eligibility_mode" :options="eligibilityOptions"
                            option-label="label" option-value="value" class="w-full" />
                    </div>
                    <div v-if="showEligibilityDays">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Vencimento até quantos dias à frente?</label>
                        <InputNumber v-model="configForm.eligibility_due_days" :min="1" :max="365" class="w-full" />
                        <p class="text-[11px] text-gray-400 mt-1">Inclui títulos já vencidos.</p>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Como agrupar por vencimento?</label>
                        <Select v-model="configForm.due_grouping" :options="dueGroupingOptions"
                            option-label="label" option-value="value" class="w-full" />
                    </div>
                    <div v-if="showMaxSpan">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Diferença máxima entre vencimentos (dias)</label>
                        <InputNumber v-model="configForm.max_due_span_days" :min="1" :max="90" class="w-full" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-6">
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-600">Mínimo de títulos por borderô</label>
                        <InputNumber v-model="configForm.min_titles_per_group" :min="2" :max="50" class="w-24" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="configForm.cron_enabled" :binary="true" input-id="cron-enabled" />
                        <label for="cron-enabled" class="text-sm text-gray-700 cursor-pointer">
                            Ativar cron diário às <strong>06:00</strong>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button type="submit" label="Salvar e atualizar simulação" icon="pi pi-save"
                        :loading="configForm.processing" size="small" dusk="save-bordero-auto-config" />
                </div>
            </form>

            <!-- Resumo simulação -->
            <div class="mb-2">
                <h2 class="text-sm font-semibold text-gray-800">Simulação agora ({{ preview.summary.eligible_titles }} títulos elegíveis)</h2>
                <p class="text-xs text-gray-500">É exatamente isso que o cron criaria se rodasse neste momento (ou o que você gera manualmente ao clicar abaixo).</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 p-3 text-center">
                    <p class="text-2xl font-bold text-gray-800">{{ preview.summary.eligible_titles }}</p>
                    <p class="text-xs text-gray-500">Elegíveis</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-3 text-center">
                    <p class="text-2xl font-bold text-blue-600">{{ preview.summary.suggested_groups }}</p>
                    <p class="text-xs text-gray-500">Grupos / borderôs</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-3 text-center">
                    <p class="text-2xl font-bold text-emerald-600">{{ preview.summary.titles_in_groups }}</p>
                    <p class="text-xs text-gray-500">Títulos agrupados</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-3 text-center">
                    <p class="text-2xl font-bold text-amber-600">{{ preview.summary.titles_outside_groups }}</p>
                    <p class="text-xs text-gray-500">Fora (&lt; mínimo)</p>
                </div>
            </div>

            <!-- Grupos -->
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden mb-20">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800">Grupos sugeridos</h3>
                    <label v-if="preview.groups.length" class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                        <Checkbox v-model="allSelected" :binary="true" />
                        Selecionar todos
                    </label>
                </div>

                <div v-if="!preview.groups.length" class="p-8 text-center text-sm text-gray-500">
                    Nenhum grupo atende ao mínimo de {{ config.min_titles_per_group }} títulos com a configuração atual.
                </div>

                <div v-else class="divide-y divide-gray-50">
                    <div v-for="group in preview.groups" :key="group.key"
                        class="p-4 hover:bg-gray-50/50 transition-colors"
                        :class="{ 'bg-blue-50/30': selectedKeys.includes(group.key) }">
                        <div class="flex items-start gap-3">
                            <Checkbox :model-value="selectedKeys.includes(group.key)" :binary="true"
                                class="mt-1" @update:model-value="(v) => toggleGroup(group.key, v)" />
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <p class="text-sm font-semibold text-gray-800">{{ group.label }}</p>
                                    <Tag :value="group.segment_type === 'dept' ? 'Dept' : group.segment_type === 'ccu' ? 'CCU' : 'Outros'"
                                        :severity="segmentSeverity(group.segment_type)" class="!text-[10px]" />
                                </div>
                                <p class="text-xs text-gray-500 mb-2">
                                    {{ group.titles_count }} títulos · {{ fmtMoney(group.total_amount) }}
                                </p>
                                <ul class="text-[11px] text-gray-400 space-y-0.5">
                                    <li v-for="t in group.sample_titles" :key="t.id">
                                        {{ t.title_number }} — {{ t.supplier_name }} — {{ fmtMoney(t.amount) }}
                                        <span v-if="t.due_date"> ({{ t.due_date }})</span>
                                    </li>
                                    <li v-if="group.titles_count > 3" class="text-gray-300">
                                        + {{ group.titles_count - 3 }} título(s)…
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="preview.groups.length"
                :class="isMobile ? 'fixed bottom-16 left-0 right-0 px-4 z-20' : 'flex justify-end gap-2'">
                <Button :label="`Gerar manualmente ${selectedKeys.length} borderô(s)`" icon="pi pi-bolt" severity="success"
                    :disabled="!selectedKeys.length" :loading="generating" class="w-full md:w-auto"
                    dusk="generate-auto-borderos" @click="generate" />
            </div>
        </div>
    </component>
</template>
