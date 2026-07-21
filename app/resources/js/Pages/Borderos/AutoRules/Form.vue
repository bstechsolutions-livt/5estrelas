<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    rule: { type: Object, default: null },
    defaults: Object,
    options: Object,
    preview: Object,
})

const { isMobile } = useDevice()
const page = usePage()
const toast = useToast()

const isEditing = computed(() => !!props.rule?.id)

const emptyFilter = () => ({ field: 'description', operator: 'contains', value: '' })

const form = useForm({
    name: props.rule?.name ?? props.defaults.name,
    filters: props.rule?.filters?.length
        ? props.rule.filters.map(f => ({ ...f }))
        : [emptyFilter()],
    filter_logic: props.rule?.filter_logic ?? props.defaults.filter_logic ?? 'and',
    min_titles_per_group: props.rule?.min_titles_per_group ?? props.defaults.min_titles_per_group,
    due_grouping: props.rule?.due_grouping ?? props.defaults.due_grouping,
    max_due_span_days: props.rule?.max_due_span_days ?? props.defaults.max_due_span_days,
    eligibility_mode: props.rule?.eligibility_mode ?? props.defaults.eligibility_mode,
    eligibility_due_days: props.rule?.eligibility_due_days ?? props.defaults.eligibility_due_days,
})

const livePreview = ref(props.preview)
const simulating = ref(false)
const fieldOptionsCache = ref({})
let simulateTimer = null

const fieldChoices = computed(() =>
    Object.entries(props.options?.filter_fields ?? {}).map(([value, meta]) => ({
        value,
        label: meta.label,
    }))
)

const filterLogicOptions = [
    { value: 'and', label: 'Todas as condições (E)' },
    { value: 'or', label: 'Qualquer condição (OU)' },
]

const dueGroupingOptions = computed(() =>
    Object.entries(props.options?.due_grouping ?? {}).map(([value, label]) => ({ value, label }))
)
const eligibilityOptions = computed(() =>
    Object.entries(props.options?.eligibility_mode ?? {}).map(([value, label]) => ({ value, label }))
)

const showMaxSpan = computed(() => form.due_grouping === 'max_span')
const showEligibilityDays = computed(() => form.eligibility_mode === 'due_within_days')

const hasValidFilters = computed(() =>
    form.filters.some(f => String(f.value ?? '').trim() !== '')
)

const canSubmit = computed(() => form.name && hasValidFilters.value)

function operatorsFor(field) {
    const ops = props.options?.filter_fields?.[field]?.operators ?? ['eq']
    const labels = props.options?.operators ?? {}
    return ops.map(op => ({ value: op, label: labels[op] ?? op }))
}

function valueOptionsFor(index) {
    const field = form.filters[index]?.field
    return fieldOptionsCache.value[field]?.options ?? []
}

function usesSelectValue(index) {
    const field = form.filters[index]?.field
    const opts = valueOptionsFor(index)
    return ['codemp', 'department_id'].includes(field) || opts.length > 0
}

async function loadFieldOptions(field) {
    if (!field || fieldOptionsCache.value[field]) return
    try {
        const { data } = await window.axios.get('/financeiro/borderos/automatico/opcoes-filtro', {
            params: { field },
        })
        fieldOptionsCache.value[field] = data
    } catch {
        fieldOptionsCache.value[field] = { options: [] }
    }
}

function onFieldChange(index) {
    const field = form.filters[index].field
    const ops = operatorsFor(field)
    form.filters[index].operator = ops[0]?.value ?? 'eq'
    form.filters[index].value = ''
    loadFieldOptions(field)
    scheduleSimulate()
}

function addCondition() {
    form.filters.push(emptyFilter())
}

function removeCondition(index) {
    if (form.filters.length <= 1) {
        form.filters[0] = emptyFilter()
    } else {
        form.filters.splice(index, 1)
    }
    scheduleSimulate()
}

function scheduleSimulate() {
    clearTimeout(simulateTimer)
    simulateTimer = setTimeout(runSimulate, 450)
}

async function runSimulate() {
    if (!hasValidFilters.value) {
        livePreview.value = {
            groups: [],
            summary: { eligible_titles: 0, suggested_groups: 0, titles_in_groups: 0, titles_outside_groups: 0 },
        }
        return
    }

    simulating.value = true
    try {
        const { data } = await window.axios.post('/financeiro/borderos/automatico/simular', {
            name: form.name || 'Simulação',
            filters: form.filters.filter(f => String(f.value).trim() !== ''),
            filter_logic: form.filter_logic,
            min_titles_per_group: form.min_titles_per_group,
            due_grouping: form.due_grouping,
            max_due_span_days: form.max_due_span_days,
            eligibility_mode: form.eligibility_mode,
            eligibility_due_days: form.eligibility_due_days,
        })
        livePreview.value = data
    } catch {
        // mantém última simulação
    } finally {
        simulating.value = false
    }
}

function submit(applyMode) {
    const payload = {
        ...form.data(),
        filters: form.filters.filter(f => String(f.value).trim() !== ''),
        apply_mode: applyMode,
    }
    if (isEditing.value) {
        form.transform(() => payload).put(`/financeiro/borderos/automatico/${props.rule.id}`)
    } else {
        form.transform(() => payload).post('/financeiro/borderos/automatico')
    }
}

function fmtMoney(v) {
    return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

form.filters.forEach((row, i) => {
    if (row.field) loadFieldOptions(row.field)
})

watch(
    () => [form.filter_logic, form.min_titles_per_group, form.due_grouping, form.max_due_span_days, form.eligibility_mode, form.eligibility_due_days],
    scheduleSimulate,
)

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Pronto', detail: msg, life: 4000 })
})
watch(() => page.props.flash?.error, (msg) => {
    if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 })
})
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout"
        :title="isMobile ? (isEditing ? 'Editar regra' : 'Nova regra') : undefined"
        :show-back="isMobile">
        <Toast />
        <div :class="isMobile ? 'px-4 py-3 pb-28' : 'max-w-6xl mx-auto space-y-6'">
            <div>
                <button type="button" class="text-xs text-blue-600 hover:underline mb-2 inline-flex items-center gap-1"
                    @click="router.visit('/financeiro/borderos/automatico')">
                    <i class="pi pi-arrow-left text-[10px]"></i> Voltar às regras
                </button>
                <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                    {{ isEditing ? 'Editar regra' : 'Nova regra' }}
                </h1>
                <p class="text-sm text-gray-500 mt-1 max-w-2xl">
                    Defina <strong>quando</strong> criar o borderô — por exemplo descrição contendo “FUNDO FIXO: TIAGO”, filial, CCU, etc.
                    O agrupamento dos títulos que batem continua sendo por <strong>vencimento</strong> — dia, mês ou faixa de dias (opções abaixo).
                </p>
            </div>

            <div class="grid lg:grid-cols-2 gap-6 items-start">
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-800">Condições da regra</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Só entram títulos que batem com estas condições.</p>
                    </div>

                    <div class="p-4 space-y-4">
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-gray-600">Nome da regra</label>
                            <InputText v-model="form.name" class="w-full" placeholder="Ex: Fundo fixo Tiago — Filial 6" />
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <label class="text-xs font-medium text-gray-600">Quando o título…</label>
                                <Select v-model="form.filter_logic" :options="filterLogicOptions"
                                    option-label="label" option-value="value" class="w-48" size="small" />
                            </div>

                            <div v-for="(cond, index) in form.filters" :key="index"
                                class="rounded-lg border border-gray-100 p-3 space-y-2 bg-gray-50/40">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <Select v-model="cond.field" :options="fieldChoices"
                                        option-label="label" option-value="value" placeholder="Campo"
                                        class="w-full" @update:model-value="onFieldChange(index)" />
                                    <Select v-model="cond.operator" :options="operatorsFor(cond.field)"
                                        option-label="label" option-value="value" class="w-full"
                                        @update:model-value="scheduleSimulate" />
                                    <div class="flex gap-1">
                                        <Select v-if="usesSelectValue(index)" v-model="cond.value"
                                            :options="valueOptionsFor(index)" option-label="label" option-value="value"
                                            placeholder="Valor" class="w-full flex-1" filter show-clear
                                            @update:model-value="scheduleSimulate" />
                                        <InputText v-else v-model="cond.value" class="w-full flex-1"
                                            :placeholder="cond.field === 'description'
                                                ? (cond.operator === 'contains' ? 'Ex: FUNDO FIXO: TIAGO' : 'Descrição exata')
                                                : (cond.operator === 'in' ? 'Ex: 2363, 2566' : 'Valor')"
                                            @update:model-value="scheduleSimulate" />
                                        <Button icon="pi pi-trash" severity="danger" text size="small"
                                            @click="removeCondition(index)" />
                                    </div>
                                </div>
                            </div>

                            <Button label="Adicionar condição" icon="pi pi-plus" severity="secondary" text size="small"
                                @click="addCondition" />
                        </div>

                        <div class="border-t border-gray-100 pt-4 space-y-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Opções extras</p>

                            <div class="space-y-1">
                                <label class="block text-xs font-medium text-gray-600">Janela de vencimento</label>
                                <Select v-model="form.eligibility_mode" :options="eligibilityOptions"
                                    option-label="label" option-value="value" class="w-full" />
                            </div>

                            <div v-if="showEligibilityDays" class="space-y-1">
                                <label class="block text-xs font-medium text-gray-600">Até quantos dias</label>
                                <InputNumber v-model="form.eligibility_due_days" :min="1" :max="365" class="w-full md:max-w-[140px]" input-class="w-full" />
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-medium text-gray-600">Se muitos títulos baterem</label>
                                <Select v-model="form.due_grouping" :options="dueGroupingOptions"
                                    option-label="label" option-value="value" class="w-full" />
                            </div>

                            <div v-if="showMaxSpan" class="space-y-1">
                                <label class="block text-xs font-medium text-gray-600">Diferença máxima (dias)</label>
                                <InputNumber v-model="form.max_due_span_days" :min="1" :max="90" class="w-full md:max-w-[140px]" input-class="w-full" />
                            </div>

                            <div class="space-y-1">
                                <label class="block text-xs font-medium text-gray-600">Mínimo de títulos</label>
                                <InputNumber v-model="form.min_titles_per_group" :min="2" :max="50" class="w-full md:max-w-[140px]" input-class="w-full" />
                            </div>
                        </div>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50">
                        <p class="text-[11px] text-gray-500 mb-2 leading-relaxed">
                            <strong>Aguardar agendamento:</strong> salva a regra e espera a execução automática das 6h (se o agendamento e a regra estiverem ativos).
                            <strong class="ml-1">Aplicar nos abertos:</strong> salva e já cria borderôs agora.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-2">
                        <Button label="Salvar e aguardar agendamento" icon="pi pi-clock" severity="secondary" outlined size="small"
                            class="flex-1" :loading="form.processing" :disabled="!canSubmit" @click="submit('cron')" />
                        <Button label="Salvar e aplicar nos abertos" icon="pi pi-bolt" severity="success" size="small"
                            class="flex-1" :loading="form.processing" :disabled="!canSubmit" @click="submit('now')" />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-800">Simulação agora</h2>
                            <p class="text-xs text-gray-500 mt-0.5">Títulos abertos que batem com as condições</p>
                        </div>
                        <i v-if="simulating" class="pi pi-spin pi-spinner text-gray-400 text-sm"></i>
                    </div>

                    <div class="grid grid-cols-2 divide-x divide-y divide-gray-100 border-b border-gray-100">
                        <div class="p-3 text-center">
                            <p class="text-xl font-bold text-blue-600 tabular-nums">{{ livePreview?.summary?.suggested_groups ?? 0 }}</p>
                            <p class="text-[11px] text-gray-500">Borderô(s)</p>
                        </div>
                        <div class="p-3 text-center">
                            <p class="text-xl font-bold text-emerald-600 tabular-nums">{{ livePreview?.summary?.titles_in_groups ?? 0 }}</p>
                            <p class="text-[11px] text-gray-500">Títulos</p>
                        </div>
                        <div class="p-3 text-center">
                            <p class="text-lg font-semibold text-gray-700 tabular-nums">{{ livePreview?.summary?.eligible_titles ?? 0 }}</p>
                            <p class="text-[11px] text-gray-500">Batem na regra</p>
                        </div>
                        <div class="p-3 text-center">
                            <p class="text-lg font-semibold text-amber-600 tabular-nums">{{ livePreview?.summary?.titles_outside_groups ?? 0 }}</p>
                            <p class="text-[11px] text-gray-500">Abaixo do mín.</p>
                        </div>
                    </div>

                    <div v-if="!hasValidFilters" class="p-6 text-center text-sm text-gray-500">
                        Preencha ao menos uma condição com valor.
                    </div>
                    <div v-else-if="!livePreview?.groups?.length" class="p-6 text-center text-sm text-gray-500">
                        Nenhum título aberto bate com estas condições (ou abaixo do mínimo).
                    </div>
                    <div v-else class="max-h-[480px] overflow-y-auto divide-y divide-gray-50 p-4 space-y-3">
                        <div v-for="group in livePreview.groups" :key="group.key"
                            class="rounded-lg border border-gray-100 p-3">
                            <div class="flex justify-between gap-2 mb-2">
                                <p class="text-sm font-semibold text-gray-800">{{ group.label }}</p>
                                <span class="text-sm font-semibold tabular-nums shrink-0">{{ fmtMoney(group.total_amount) }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">{{ group.titles_count }} título(s)</p>
                            <ul class="text-[11px] text-gray-600 space-y-0.5">
                                <li v-for="t in group.sample_titles" :key="t.id" class="truncate">
                                    {{ t.title_number }} — {{ t.supplier_name }} — {{ fmtMoney(t.amount) }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
