<script setup>
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { useDevice } from '@/composables/useDevice'
import { computed, watch } from 'vue'

const props = defineProps({
    scheduler: Object,
    rules: Array,
})

const { isMobile } = useDevice()
const page = usePage()
const toast = useToast()

const schedulerOn = computed({
    get: () => !!props.scheduler?.cron_enabled,
    set: () => toggleScheduler(),
})

function fmtDate(iso) {
    if (!iso) return '—'
    return new Date(iso).toLocaleString('pt-BR')
}

function toggleScheduler() {
    router.post('/financeiro/borderos/automatico/agendamento/toggle', {}, { preserveScroll: true })
}

function toggleRule(id) {
    router.post(`/financeiro/borderos/automatico/${id}/toggle`, {}, { preserveScroll: true })
}

function deleteRule(id, name) {
    if (!confirm(`Remover a regra "${name}"?`)) return
    router.delete(`/financeiro/borderos/automatico/${id}`)
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Pronto', detail: msg, life: 4000 })
})
watch(() => page.props.flash?.error, (msg) => {
    if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 5000 })
})
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout" :title="isMobile ? 'Regras auto' : undefined" :show-back="isMobile">
        <Toast />
        <div :class="isMobile ? 'px-4 py-3 pb-20' : 'max-w-5xl mx-auto space-y-6'">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                        Regras de Borderô Automático
                    </h1>
                    <p class="text-sm text-gray-500 mt-1 max-w-xl">
                        Cada regra cria borderô quando o título bate com condições específicas — natureza, filial, CCU, fornecedor, etc.
                    </p>
                </div>
                <div class="flex gap-2 shrink-0">
                    <Button label="Nova regra" icon="pi pi-plus" size="small"
                        @click="router.visit('/financeiro/borderos/automatico/criar')" />
                    <Button label="Ver borderôs" icon="pi pi-list-check" severity="secondary" outlined size="small"
                        @click="router.visit('/financeiro/borderos?status=rascunho')" />
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm font-semibold text-gray-800">Agendamento automático</h2>
                            <Tag
                                :value="scheduler?.cron_enabled ? 'Ligado' : 'Pausado'"
                                :severity="scheduler?.cron_enabled ? 'success' : 'secondary'"
                                class="!text-[10px]"
                            />
                        </div>
                        <p class="text-xs text-gray-500 mt-1 max-w-2xl leading-relaxed">
                            <strong>O que é:</strong> todo dia às <strong>6h</strong> o sistema verifica títulos abertos
                            e cria borderôs em rascunho conforme as <strong>regras ativas</strong> abaixo.
                            Não precisa clicar em nada — roda sozinho no servidor.
                        </p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <label class="text-xs font-medium text-gray-600 whitespace-nowrap" for="scheduler-toggle">
                            {{ scheduler?.cron_enabled ? 'Agendamento ligado' : 'Agendamento pausado' }}
                        </label>
                        <ToggleSwitch v-model="schedulerOn" inputId="scheduler-toggle" />
                    </div>
                </div>
                <div class="px-4 py-2.5 flex flex-wrap gap-x-5 gap-y-1 text-[11px] text-gray-400 bg-gray-50/50">
                    <span>Horário: {{ scheduler?.schedule_label ?? 'Todo dia às 6h' }}</span>
                    <span>Regras ativas: {{ scheduler?.active_rules_count ?? 0 }} de {{ scheduler?.total_rules_count ?? 0 }}</span>
                    <span v-if="scheduler?.last_cron_at">
                        Última execução: {{ fmtDate(scheduler.last_cron_at) }}
                        ({{ scheduler.last_cron_count ?? 0 }} borderô(s))
                    </span>
                    <span v-else>Ainda não executou no agendamento</span>
                </div>
            </div>

            <div v-if="!rules.length" class="bg-white rounded-xl border border-gray-100 p-10 text-center">
                <i class="pi pi-bolt text-3xl text-gray-300 mb-3"></i>
                <p class="text-sm text-gray-600 font-medium">Nenhuma regra cadastrada</p>
                <p class="text-xs text-gray-400 mt-1 mb-4">Ex.: “quando natureza = 90500 e filial = 2, criar borderô”.</p>
                <Button label="Criar regra" icon="pi pi-plus" size="small"
                    @click="router.visit('/financeiro/borderos/automatico/criar')" />
            </div>

            <div v-else class="space-y-3">
                <p class="text-xs text-gray-500 px-1">
                    Cada regra pode ser ligada ou pausada individualmente. Só entram no agendamento as regras
                    <strong>ativas</strong> enquanto o agendamento geral estiver <strong>ligado</strong>.
                </p>

                <div v-for="rule in rules" :key="rule.id"
                    class="bg-white rounded-xl border border-gray-100 overflow-hidden"
                    :class="{ 'opacity-75': !rule.is_active }">
                    <div class="px-4 py-3 flex flex-wrap items-start justify-between gap-3 border-b border-gray-50">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-sm font-semibold text-gray-800">{{ rule.name }}</h2>
                                <Tag
                                    :value="rule.is_active ? 'Regra ativa' : 'Regra pausada'"
                                    :severity="rule.is_active ? 'success' : 'secondary'"
                                    class="!text-[10px]"
                                />
                            </div>
                            <ul class="mt-2 space-y-0.5">
                                <li v-for="(line, i) in rule.rules_summary" :key="i" class="text-xs text-gray-500">{{ line }}</li>
                            </ul>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 shrink-0">
                            <div class="flex items-center gap-2">
                                <label class="text-[11px] text-gray-500 whitespace-nowrap" :for="`rule-${rule.id}`">
                                    {{ rule.is_active ? 'Ativa' : 'Pausada' }}
                                </label>
                                <ToggleSwitch
                                    :model-value="rule.is_active"
                                    :inputId="`rule-${rule.id}`"
                                    @update:model-value="toggleRule(rule.id)"
                                />
                            </div>
                            <div class="flex gap-1">
                                <Button icon="pi pi-pencil" severity="secondary" text size="small"
                                    @click="router.visit(`/financeiro/borderos/automatico/${rule.id}/editar`)" />
                                <Button icon="pi pi-trash" severity="danger" text size="small"
                                    @click="deleteRule(rule.id, rule.name)" />
                            </div>
                        </div>
                    </div>
                    <div class="px-4 py-2.5 flex flex-wrap gap-4 text-[11px] text-gray-400 bg-gray-50/50">
                        <span v-if="rule.last_applied_at">
                            Última aplicação manual: {{ fmtDate(rule.last_applied_at) }}
                            ({{ rule.last_applied_count ?? 0 }} borderô(s))
                        </span>
                        <span v-if="rule.last_cron_at">
                            Última execução no agendamento: {{ fmtDate(rule.last_cron_at) }}
                            ({{ rule.last_cron_count ?? 0 }} borderô(s))
                        </span>
                        <span v-if="!rule.last_applied_at && !rule.last_cron_at">Ainda não executada</span>
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
