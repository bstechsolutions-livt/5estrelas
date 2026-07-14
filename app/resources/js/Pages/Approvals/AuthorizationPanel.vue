<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import BranchAccessBlocked from '@/Components/Financeiro/BranchAccessBlocked.vue'
import { useDevice } from '@/composables/useDevice'
import { formatApiDate } from '@/utils/apiDate'

const props = defineProps({
    summary: Object,
    my_action: { type: Array, default: () => [] },
    in_approval: { type: Array, default: () => [] },
    department: Object,
    permissions: Object,
    no_branch_access: { type: Boolean, default: false },
})

const { isMobile } = useDevice()
const activeTab = ref('my_action')

const tabs = computed(() => [
    { key: 'my_action', label: 'Aguardando minha ação', count: props.my_action?.length ?? 0 },
    { key: 'in_approval', label: 'Todos em aprovação', count: props.in_approval?.length ?? 0 },
])

const activeItems = computed(() =>
    activeTab.value === 'my_action' ? props.my_action : props.in_approval
)

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}

function formatDate(d) {
    return formatApiDate(d)
}

function go(path) {
    router.visit(path)
}

function typeLabel(type) {
    return type === 'bordero' ? 'Borderô' : 'Título'
}

function typeSeverity(type) {
    return type === 'bordero' ? 'info' : 'secondary'
}
</script>

<template>
    <Head title="Painel de Autorizações" />
    <component :is="isMobile ? AppLayoutMobile : AppLayout" :title="isMobile ? 'Autorizações' : undefined">
        <div :class="isMobile ? 'px-4 py-3 pb-20 space-y-4' : 'p-4 md:p-6 max-w-5xl mx-auto space-y-6'">
            <div>
                <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-slate-800 dark:text-slate-100'">
                    Painel de Autorizações
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Visão das aprovações de contas a pagar e borderôs
                    <span v-if="department">· {{ department.name }}</span>
                </p>
            </div>

            <BranchAccessBlocked v-if="no_branch_access" />

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <button
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-left hover:border-orange-400 transition-colors"
                    @click="go('/financeiro/contas-pagar?status=aguardando_aprovacao')"
                >
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Pendente de aprovação</div>
                    <div class="text-2xl font-bold text-orange-600 mt-1">{{ summary?.aguardando_aprovacao?.count ?? 0 }}</div>
                    <div class="text-xs text-slate-400 mt-1">
                        {{ summary?.aguardando_aprovacao?.payables ?? 0 }} título(s)
                        <span v-if="permissions?.borderos"> · {{ summary?.aguardando_aprovacao?.borderos ?? 0 }} borderô(s)</span>
                    </div>
                </button>
                <button
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-left hover:border-green-400 transition-colors"
                    @click="go('/financeiro/contas-pagar?status=aprovado')"
                >
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Aprovados</div>
                    <div class="text-2xl font-bold text-green-600 mt-1">{{ summary?.aprovado?.count ?? 0 }}</div>
                    <div class="text-xs text-slate-400 mt-1">
                        {{ summary?.aprovado?.payables ?? 0 }} título(s)
                        <span v-if="permissions?.borderos"> · {{ summary?.aprovado?.borderos ?? 0 }} borderô(s)</span>
                    </div>
                </button>
                <button
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 text-left hover:border-red-400 transition-colors"
                    @click="go('/financeiro/contas-pagar?status=pendente')"
                >
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Recusados</div>
                    <div class="text-2xl font-bold text-red-600 mt-1">{{ summary?.recusado?.count ?? 0 }}</div>
                    <div class="text-xs text-slate-400 mt-1">
                        {{ summary?.recusado?.payables ?? 0 }} título(s)
                        <span v-if="permissions?.borderos"> · {{ summary?.recusado?.borderos ?? 0 }} borderô(s)</span>
                    </div>
                </button>
            </div>

            <div class="flex flex-wrap gap-2 border-b border-slate-200 dark:border-slate-700 pb-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
                    :class="activeTab === tab.key
                        ? 'bg-blue-600 text-white'
                        : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                    <span v-if="tab.count" class="ml-1 opacity-80">({{ tab.count }})</span>
                </button>
            </div>

            <div v-if="activeItems.length === 0" class="text-center py-16 text-slate-400">
                <i class="pi pi-check-circle text-4xl mb-3 block"></i>
                <p class="text-lg font-medium">
                    {{ activeTab === 'my_action' ? 'Nenhuma ação pendente com você' : 'Nenhum item em aprovação' }}
                </p>
                <p class="text-sm mt-1">
                    {{ activeTab === 'my_action'
                        ? 'Quando houver títulos ou borderôs aguardando sua aprovação, eles aparecerão aqui.'
                        : 'Não há títulos ou borderôs aguardando aprovação no seu escopo.' }}
                </p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="item in activeItems"
                    :key="`${item.type}-${item.id}`"
                    class="bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700 p-4 hover:shadow-sm transition-shadow cursor-pointer"
                    @click="go(item.href)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <Tag :value="typeLabel(item.type)" :severity="typeSeverity(item.type)" />
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate">{{ item.label }}</p>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ item.subtitle }}
                                <span v-if="item.due_date"> · Venc: {{ formatDate(item.due_date) }}</span>
                                <span v-if="item.sent_at"> · Enviado: {{ formatDate(item.sent_at) }}</span>
                            </p>
                            <p v-if="item.preparer" class="text-[10px] text-slate-400 mt-1">
                                Enviado por {{ item.preparer }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ formatMoney(item.amount) }}</p>
                            <Tag :value="item.status_label" severity="warn" class="mt-1" />
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!isMobile" class="flex flex-wrap gap-2 pt-2">
                <Button label="Contas a Pagar" icon="pi pi-wallet" size="small" outlined @click="go('/financeiro/contas-pagar')" />
                <Button
                    v-if="permissions?.borderos"
                    label="Borderôs"
                    icon="pi pi-list-check"
                    size="small"
                    outlined
                    severity="secondary"
                    @click="go('/financeiro/borderos')"
                />
                <Button label="Atualizar" icon="pi pi-refresh" size="small" text @click="router.reload()" />
            </div>
        </div>
    </component>
</template>
