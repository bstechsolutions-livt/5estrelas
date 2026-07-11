<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
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

const form = useForm({
    name: props.rule?.name ?? props.defaults.name,
    group_by: [...(props.rule?.group_by ?? props.defaults.group_by ?? ['empresa', 'departamento'])],
    min_titles_per_group: props.rule?.min_titles_per_group ?? props.defaults.min_titles_per_group,
    due_grouping: props.rule?.due_grouping ?? props.defaults.due_grouping,
    max_due_span_days: props.rule?.max_due_span_days ?? props.defaults.max_due_span_days,
    eligibility_mode: props.rule?.eligibility_mode ?? props.defaults.eligibility_mode,
    eligibility_due_days: props.rule?.eligibility_due_days ?? props.defaults.eligibility_due_days,
})

const livePreview = ref(props.preview)
const simulating = ref(false)
let simulateTimer = null

const dueGroupingOptions = computed(() =>
    Object.entries(props.options?.due_grouping ?? {}).map(([value, label]) => ({ value, label }))
)
const eligibilityOptions = computed(() =>
    Object.entries(props.options?.eligibility_mode ?? {}).map(([value, label]) => ({ value, label }))
)

const groupByOptions = computed(() =>
    (props.options?.group_by_order ?? Object.keys(props.options?.group_by ?? {}))
        .map(key => ({
            key,
            label: props.options?.group_by?.[key] ?? key,
        }))
)

const groupBySummary = computed(() => {
    const labels = props.options?.group_by ?? {}
    return form.group_by.map(k => labels[k] ?? k).join(' → ')
})

function toggleGroupBy(key, checked) {
    if (checked) {
        if (!form.group_by.includes(key)) {
            const order = props.options?.group_by_order ?? []
            const next = [...form.group_by, key].sort((a, b) => order.indexOf(a) - order.indexOf(b))
            form.group_by = next
        }
    } else {
        form.group_by = form.group_by.filter(k => k !== key)
    }
    scheduleSimulate()
}

const showMaxSpan = computed(() => form.due_grouping === 'max_span')
const showEligibilityDays = computed(() => form.eligibility_mode === 'due_within_days')
const canSubmit = computed(() => form.name && form.group_by.length > 0)

function scheduleSimulate() {
    clearTimeout(simulateTimer)
    simulateTimer = setTimeout(runSimulate, 400)
}

async function runSimulate() {
    simulating.value = true
    try {
        const { data } = await window.axios.post('/financeiro/borderos/automatico/simular', {
            name: form.name || 'Simulação',
            group_by: form.group_by,
            min_titles_per_group: form.min_titles_per_group,
            due_grouping: form.due_grouping,
            max_due_span_days: form.max_due_span_days,
            eligibility_mode: form.eligibility_mode,
            eligibility_due_days: form.eligibility_due_days,
        })
        livePreview.value = data
    } catch {
        // mantém última simulação válida
    } finally {
        simulating.value = false
    }
}

function submit(applyMode) {
    const payload = { ...form.data(), apply_mode: applyMode }
    if (isEditing.value) {
        form.transform(() => payload).put(`/financeiro/borderos/automatico/${props.rule.id}`)
    } else {
        form.transform(() => payload).post('/financeiro/borderos/automatico')
    }
}

function fmtMoney(v) {
    return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function segmentSeverity(type) {
    const map = {
        departamento: 'info',
        ccu: 'warn',
        fornecedor: 'success',
        empresa: 'secondary',
    }
    return map[type] ?? 'secondary'
}

watch(
    () => [form.group_by, form.min_titles_per_group, form.due_grouping, form.max_due_span_days, form.eligibility_mode, form.eligibility_due_days],
    scheduleSimulate,
    { deep: true },
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
        <div :class="isMobile ? 'px-4 py-3 pb-28' : 'max-w-5xl mx-auto space-y-6'">
            <div>
                <button type="button" class="text-xs text-blue-600 hover:underline mb-2 inline-flex items-center gap-1"
                    @click="router.visit('/financeiro/borderos/automatico')">
                    <i class="pi pi-arrow-left text-[10px]"></i> Voltar às regras
                </button>
                <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                    {{ isEditing ? 'Editar regra' : 'Nova regra' }}
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    Defina os parâmetros e veja a simulação antes de salvar.
                </p>
            </div>

            <div class="grid lg:grid-cols-2 gap-6 items-start">
                <!-- Parâmetros -->
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-800">Parâmetros da regra</h2>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-gray-600">Nome da regra</label>
                            <InputText v-model="form.name" class="w-full" placeholder="Ex: DP/RH — vencimento próximo" />
                            <p v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-xs font-medium text-gray-600">Agrupar títulos por</label>
                            <p class="text-[11px] text-gray-400">Marque um ou mais critérios. Títulos com os mesmos valores vão no mesmo borderô.</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <label v-for="opt in groupByOptions" :key="opt.key"
                                    class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 cursor-pointer hover:bg-gray-50"
                                    :class="form.group_by.includes(opt.key) ? 'bg-blue-50/60 border-blue-100' : ''">
                                    <Checkbox :model-value="form.group_by.includes(opt.key)" :binary="true"
                                        @update:model-value="(v) => toggleGroupBy(opt.key, v)" />
                                    <span class="text-xs text-gray-700">{{ opt.label }}</span>
                                </label>
                            </div>
                            <p v-if="form.errors.group_by" class="text-xs text-red-500">{{ form.errors.group_by }}</p>
                            <p v-else-if="form.group_by.length" class="text-[11px] text-blue-700 bg-blue-50 rounded px-2 py-1">
                                Ordem: {{ groupBySummary }}
                            </p>
                            <p v-else class="text-[11px] text-amber-600">Selecione ao menos um critério.</p>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-gray-600">Quais títulos entram?</label>
                            <Select v-model="form.eligibility_mode" :options="eligibilityOptions"
                                option-label="label" option-value="value" class="w-full" />
                        </div>

                        <div v-if="showEligibilityDays" class="space-y-1">
                            <label class="block text-xs font-medium text-gray-600">Vencimento até (dias)</label>
                            <InputNumber v-model="form.eligibility_due_days" :min="1" :max="365" class="w-full" input-class="w-full" />
                            <p class="text-[11px] text-gray-400">Inclui vencidos.</p>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-gray-600">Subdividir por vencimento</label>
                            <Select v-model="form.due_grouping" :options="dueGroupingOptions"
                                option-label="label" option-value="value" class="w-full" />
                        </div>

                        <div v-if="showMaxSpan" class="space-y-1">
                            <label class="block text-xs font-medium text-gray-600">Diferença máxima (dias)</label>
                            <InputNumber v-model="form.max_due_span_days" :min="1" :max="90" class="w-full" input-class="w-full" />
                        </div>

                        <div class="space-y-1">
                            <label class="block text-xs font-medium text-gray-600">Mínimo de títulos por borderô</label>
                            <InputNumber v-model="form.min_titles_per_group" :min="2" :max="50" class="w-full md:max-w-[140px]" input-class="w-full" />
                        </div>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row gap-2">
                        <Button label="Salvar e aguardar cron" icon="pi pi-clock" severity="secondary" outlined size="small"
                            class="flex-1" :loading="form.processing" :disabled="!canSubmit"
                            @click="submit('cron')" />
                        <Button label="Salvar e aplicar nos abertos" icon="pi pi-bolt" severity="success" size="small"
                            class="flex-1" :loading="form.processing" :disabled="!canSubmit"
                            @click="submit('now')" />
                    </div>
                </div>

                <!-- Simulação -->
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-800">Simulação agora</h2>
                            <p class="text-xs text-gray-500 mt-0.5">Com os títulos abertos no momento</p>
                        </div>
                        <i v-if="simulating" class="pi pi-spin pi-spinner text-gray-400 text-sm"></i>
                    </div>

                    <div class="grid grid-cols-2 divide-x divide-y divide-gray-100 border-b border-gray-100">
                        <div class="p-3 text-center">
                            <p class="text-xl font-bold text-blue-600 tabular-nums">{{ livePreview?.summary?.suggested_groups ?? 0 }}</p>
                            <p class="text-[11px] text-gray-500">Borderôs</p>
                        </div>
                        <div class="p-3 text-center">
                            <p class="text-xl font-bold text-emerald-600 tabular-nums">{{ livePreview?.summary?.titles_in_groups ?? 0 }}</p>
                            <p class="text-[11px] text-gray-500">Títulos</p>
                        </div>
                        <div class="p-3 text-center">
                            <p class="text-lg font-semibold text-gray-700 tabular-nums">{{ livePreview?.summary?.eligible_titles ?? 0 }}</p>
                            <p class="text-[11px] text-gray-500">Elegíveis</p>
                        </div>
                        <div class="p-3 text-center">
                            <p class="text-lg font-semibold text-amber-600 tabular-nums">{{ livePreview?.summary?.titles_outside_groups ?? 0 }}</p>
                            <p class="text-[11px] text-gray-500">Fora (&lt; mín.)</p>
                        </div>
                    </div>

                    <div v-if="!livePreview?.groups?.length" class="p-6 text-center text-sm text-gray-500">
                        Nenhum borderô seria criado com estes parâmetros.
                    </div>

                    <div v-else class="max-h-[420px] overflow-y-auto divide-y divide-gray-50">
                        <div v-for="group in livePreview.groups" :key="group.key" class="px-4 py-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-gray-800 leading-snug">{{ group.label }}</p>
                                    <p class="text-[11px] text-gray-500 mt-0.5">{{ group.titles_count }} títulos</p>
                                </div>
                                <span class="text-xs font-semibold text-gray-700 shrink-0 tabular-nums">{{ fmtMoney(group.total_amount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
