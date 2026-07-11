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

function fmtDueDate(iso) {
    if (!iso) return '—'
    return new Date(iso + 'T12:00:00').toLocaleDateString('pt-BR')
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
        <div :class="isMobile ? 'px-4 py-3 pb-28' : 'max-w-6xl mx-auto space-y-6'" dusk="bordero-auto-config-page">
            <!-- Cabeçalho -->
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                        Borderô Automático
                    </h1>
                    <p class="text-sm text-gray-500 mt-1 max-w-2xl">
                        Configure as regras e veja a simulação com os títulos abertos agora — o mesmo cálculo do cron das 6h.
                    </p>
                </div>
                <Button label="Ver borderôs" icon="pi pi-list-check" severity="secondary" outlined size="small"
                    class="shrink-0 self-start" @click="router.visit('/financeiro/borderos?status=rascunho')" />
            </div>

            <!-- Regras ativas -->
            <div class="bg-white rounded-xl border border-blue-100 overflow-hidden">
                <div class="px-4 py-3 bg-blue-50 border-b border-blue-100">
                    <p class="text-sm font-semibold text-blue-900">Regras que serão aplicadas</p>
                    <p class="text-xs text-blue-700 mt-0.5">Após salvar, a simulação abaixo usa estas regras.</p>
                </div>
                <div class="px-4 py-3">
                    <ul class="grid md:grid-cols-2 gap-x-6 gap-y-1.5 text-sm text-gray-700">
                        <li v-for="(line, i) in preview.rules_summary" :key="i" class="flex gap-2">
                            <span class="text-blue-400 shrink-0">•</span>
                            <span>{{ line }}</span>
                        </li>
                    </ul>
                    <p v-if="config.cron_enabled" class="text-xs text-gray-500 mt-3 pt-3 border-t border-gray-100">
                        <i class="pi pi-clock text-[10px] mr-1"></i>
                        Cron diário às <strong>06:00</strong> gera todos os grupos em rascunho.
                        <template v-if="config.last_cron_run_at">
                            Última execução: {{ fmtDate(config.last_cron_run_at) }}
                            ({{ config.last_cron_created_count ?? 0 }} borderô(s)).
                        </template>
                        <template v-else> Ainda não executou.</template>
                    </p>
                </div>
            </div>

            <!-- Configuração -->
            <form @submit.prevent="saveConfig" class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-800">Configuração</h2>
                </div>

                <div class="p-4 space-y-5">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-gray-600">Quais títulos entram na simulação?</label>
                            <Select v-model="configForm.eligibility_mode" :options="eligibilityOptions"
                                option-label="label" option-value="value" class="w-full" />
                        </div>
                        <div v-if="showEligibilityDays" class="space-y-1">
                            <label class="block text-xs font-medium text-gray-600">Vencimento até quantos dias à frente?</label>
                            <InputNumber v-model="configForm.eligibility_due_days" :min="1" :max="365" class="w-full" input-class="w-full" />
                            <p class="text-[11px] text-gray-400">Inclui títulos já vencidos.</p>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-gray-600">Como agrupar por vencimento?</label>
                            <Select v-model="configForm.due_grouping" :options="dueGroupingOptions"
                                option-label="label" option-value="value" class="w-full" />
                        </div>
                        <div v-if="showMaxSpan" class="space-y-1">
                            <label class="block text-xs font-medium text-gray-600">Diferença máxima entre vencimentos (dias)</label>
                            <InputNumber v-model="configForm.max_due_span_days" :min="1" :max="90" class="w-full" input-class="w-full" />
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4 items-end">
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-gray-600">Mínimo de títulos por borderô</label>
                            <InputNumber v-model="configForm.min_titles_per_group" :min="2" :max="50" class="w-full md:max-w-[140px]" input-class="w-full" />
                        </div>
                        <div class="flex items-center gap-2.5 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5 min-h-[42px]">
                            <Checkbox v-model="configForm.cron_enabled" :binary="true" input-id="cron-enabled" />
                            <label for="cron-enabled" class="text-sm text-gray-700 cursor-pointer leading-snug">
                                Ativar cron diário às <strong>06:00</strong>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50 flex justify-end">
                    <Button type="submit" label="Salvar e atualizar simulação" icon="pi pi-save"
                        :loading="configForm.processing" size="small" dusk="save-bordero-auto-config" />
                </div>
            </form>

            <!-- Simulação -->
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-800">Simulação agora</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ preview.summary.eligible_titles }} títulos elegíveis — mesmo resultado que o cron geraria neste instante.
                    </p>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-y md:divide-y-0 divide-gray-100 border-b border-gray-100">
                    <div class="p-4 text-center">
                        <p class="text-2xl font-bold text-gray-800 tabular-nums">{{ preview.summary.eligible_titles }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">Elegíveis</p>
                    </div>
                    <div class="p-4 text-center">
                        <p class="text-2xl font-bold text-blue-600 tabular-nums">{{ preview.summary.suggested_groups }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">Grupos / borderôs</p>
                    </div>
                    <div class="p-4 text-center">
                        <p class="text-2xl font-bold text-emerald-600 tabular-nums">{{ preview.summary.titles_in_groups }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">Títulos agrupados</p>
                    </div>
                    <div class="p-4 text-center">
                        <p class="text-2xl font-bold text-amber-600 tabular-nums">{{ preview.summary.titles_outside_groups }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">Fora (&lt; mínimo)</p>
                    </div>
                </div>

                <!-- Grupos -->
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-gray-800">Grupos sugeridos</h3>
                    <label v-if="preview.groups.length" class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer shrink-0">
                        <Checkbox v-model="allSelected" :binary="true" />
                        Selecionar todos
                    </label>
                </div>

                <div v-if="!preview.groups.length" class="p-8 text-center text-sm text-gray-500">
                    Nenhum grupo atende ao mínimo de {{ config.min_titles_per_group }} títulos com a configuração atual.
                </div>

                <div v-else class="divide-y divide-gray-100">
                    <div v-for="group in preview.groups" :key="group.key"
                        class="p-4 transition-colors"
                        :class="selectedKeys.includes(group.key) ? 'bg-blue-50/40' : 'hover:bg-gray-50/60'">
                        <div class="flex items-start gap-3">
                            <Checkbox :model-value="selectedKeys.includes(group.key)" :binary="true"
                                class="mt-0.5 shrink-0" @update:model-value="(v) => toggleGroup(group.key, v)" />
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                                    <div class="flex flex-wrap items-center gap-2 min-w-0">
                                        <p class="text-sm font-semibold text-gray-800">{{ group.label }}</p>
                                        <Tag :value="group.segment_type === 'dept' ? 'Dept' : group.segment_type === 'ccu' ? 'CCU' : 'Outros'"
                                            :severity="segmentSeverity(group.segment_type)" class="!text-[10px]" />
                                    </div>
                                    <p class="text-sm font-semibold text-gray-700 shrink-0 tabular-nums">
                                        {{ fmtMoney(group.total_amount) }}
                                    </p>
                                </div>

                                <p class="text-xs text-gray-500 mb-2">
                                    {{ group.titles_count }} título{{ group.titles_count !== 1 ? 's' : '' }}
                                    <span v-if="group.due_label"> · {{ group.due_label }}</span>
                                </p>

                                <div class="rounded-lg border border-gray-100 bg-gray-50/80 overflow-hidden">
                                    <div v-for="t in group.sample_titles" :key="t.id"
                                        class="grid grid-cols-[72px_1fr_auto_auto] gap-2 items-center px-3 py-2 text-xs border-b border-gray-100 last:border-b-0">
                                        <span class="font-medium text-gray-500 tabular-nums">{{ t.title_number }}</span>
                                        <span class="text-gray-700 truncate" :title="t.supplier_name">{{ t.supplier_name }}</span>
                                        <span class="text-gray-500 shrink-0">{{ fmtDueDate(t.due_date) }}</span>
                                        <span class="font-medium text-gray-800 shrink-0 tabular-nums">{{ fmtMoney(t.amount) }}</span>
                                    </div>
                                    <p v-if="group.titles_count > 3" class="px-3 py-1.5 text-[11px] text-gray-400 bg-white">
                                        + {{ group.titles_count - 3 }} título(s) neste grupo
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="preview.groups.length"
                    class="px-4 py-3 border-t border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-xs text-gray-500">
                        {{ selectedKeys.length }} de {{ preview.groups.length }} grupo(s) selecionado(s)
                    </p>
                    <Button :label="`Gerar manualmente ${selectedKeys.length} borderô(s)`" icon="pi pi-bolt" severity="success"
                        :disabled="!selectedKeys.length" :loading="generating" class="w-full sm:w-auto shrink-0"
                        dusk="generate-auto-borderos" @click="generate" />
                </div>
            </div>
        </div>
    </component>
</template>
