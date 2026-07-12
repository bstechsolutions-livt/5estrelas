<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import BranchAccessBlocked from '@/Components/Financeiro/BranchAccessBlocked.vue'

const props = defineProps({
    receivables: Object,
    filters: Object,
    empresas: Array,
    statusOptions: Object,
    lockedBranches: { type: Array, default: () => [] },
    noBranchAccess: { type: Boolean, default: false },
})

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || '')

const statusList = computed(() => [
    { label: 'Em aberto', value: '' },
    ...Object.entries(props.statusOptions || {}).map(([value, label]) => ({ label, value })),
])

function applyFilters() {
    router.get('/financeiro/contas-receber', {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true })
}

function formatMoney(v) {
    return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}
</script>

<template>
    <AppLayoutMobile title="Contas a Receber">
        <div class="p-4 space-y-4">
            <BranchAccessBlocked v-if="noBranchAccess" :locked-branches="lockedBranches" />
            <template v-else>
                <div class="space-y-2">
                    <InputText v-model="search" placeholder="Buscar..." class="w-full" />
                    <Select v-model="status" :options="statusList" option-label="label" option-value="value" class="w-full" />
                    <Button label="Filtrar" class="w-full" @click="applyFilters" />
                </div>
                <div class="space-y-2">
                    <button
                        v-for="item in receivables.data"
                        :key="item.id"
                        type="button"
                        class="w-full text-left p-3 rounded-xl border border-surface-200 bg-surface-0"
                        @click="router.visit(`/financeiro/contas-receber/${item.id}`)"
                    >
                        <div class="flex justify-between gap-2">
                            <span class="font-medium">{{ item.customer_name }}</span>
                            <Tag v-if="item.origem_senior" value="Senior" severity="info" class="text-xs" />
                        </div>
                        <div class="text-sm text-surface-500">{{ item.title_number }}</div>
                        <div class="text-sm font-semibold mt-1">{{ formatMoney(item.amount) }}</div>
                        <div class="text-xs text-surface-500">{{ item.situacao_label || item.senior_situacao_original }}</div>
                    </button>
                </div>
            </template>
        </div>
    </AppLayoutMobile>
</template>
