<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import BranchAccessBlocked from '@/Components/Financeiro/BranchAccessBlocked.vue'
import { useDevice } from '@/composables/useDevice'

const props = defineProps({
    borderos: Object,
    totals: Object,
    filters: Object,
    statusOptions: Object,
    noBranchAccess: { type: Boolean, default: false },
    canManageAuto: { type: Boolean, default: false },
})

const STORAGE_KEY = 'borderos_status'
const { isMobile } = useDevice()
const status = ref(props.filters?.status || 'aguardando_aprovacao')

const statusList = [
    { label: 'Pendentes', value: 'pendente' },
    { label: 'Em Preparação', value: 'em_preparacao' },
    { label: 'Ag. Aprovação', value: 'aguardando_aprovacao' },
    { label: 'Aprovados', value: 'aprovado' },
    { label: 'Pagos', value: 'pago' },
]

function filterStatus(s) {
    if (status.value === s) return
    status.value = s
    localStorage.setItem(STORAGE_KEY, s)
    router.get('/financeiro/borderos', { status: s }, { preserveState: true, replace: true })
}

onMounted(() => {
    const legacy = { rascunho: 'pendente', reprovado: 'pendente' }
    if (legacy[status.value]) {
        status.value = legacy[status.value]
        localStorage.setItem(STORAGE_KEY, status.value)
        router.get('/financeiro/borderos', { status: status.value }, { preserveState: true, replace: true })
        return
    }

    if (!window.location.search) {
        const cached = localStorage.getItem(STORAGE_KEY)
        const normalized = legacy[cached] || cached
        if (normalized && normalized !== (props.filters?.status || 'aguardando_aprovacao')) {
            status.value = normalized
            router.get('/financeiro/borderos', { status: normalized }, { preserveState: true, replace: true })
        }
    }
})

function goShow(id) { router.visit(`/financeiro/borderos/${id}`) }

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}
function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}

const statusSeverity = {
    pendente: 'secondary',
    em_preparacao: 'info',
    aguardando_aprovacao: 'warn',
    aprovado: 'success',
    pago: 'success',
}

function wasRejectedBack(bordero) {
    return bordero.status === 'pendente' && !!bordero.rejection_reason
}
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout" :title="isMobile ? 'Borderôs' : undefined">
        <div :class="isMobile ? 'px-4 py-3' : 'max-w-6xl mx-auto'">
            <div v-if="!isMobile" class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Borderôs</h1>
                    <p class="text-sm text-gray-500">Agrupamento de títulos para aprovação em lote.</p>
                </div>
                <Button
                    v-if="canManageAuto"
                    label="Regras automáticas"
                    icon="pi pi-cog"
                    severity="secondary"
                    outlined
                    size="small"
                    @click="router.visit('/financeiro/borderos/automatico')"
                />
            </div>

            <BranchAccessBlocked v-if="noBranchAccess" />

            <template v-else>
                <div class="flex flex-wrap gap-2 mb-4">
                    <button
                        v-for="s in statusList"
                        :key="s.value"
                        type="button"
                        class="px-3 py-1.5 rounded-full text-xs font-medium border transition-colors"
                        :class="status === s.value ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                        @click="filterStatus(s.value)"
                    >
                        {{ s.label }}
                        <span v-if="totals?.[s.value]" class="ml-1 opacity-80">({{ totals[s.value].count }})</span>
                    </button>
                </div>

                <DataTable
                    v-if="!isMobile"
                    :value="borderos.data"
                    paginator
                    :rows="borderos.per_page || 20"
                    :total-records="borderos.total || 0"
                    class="text-sm cursor-pointer"
                    @row-click="(e) => goShow(e.data.id)"
                >
                    <Column field="number" header="Número" />
                    <Column header="Status">
                        <template #body="{ data }">
                            <Tag
                                :value="wasRejectedBack(data) ? 'Pendente (reprovado)' : (statusOptions[data.status] || data.status)"
                                :severity="wasRejectedBack(data) ? 'danger' : (statusSeverity[data.status] || 'secondary')"
                            />
                        </template>
                    </Column>
                    <Column field="items_count" header="Títulos" />
                    <Column header="Total">
                        <template #body="{ data }">{{ formatMoney(data.total_amount) }}</template>
                    </Column>
                    <Column header="Criado">
                        <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
                    </Column>
                </DataTable>

                <div v-else class="space-y-2">
                    <div
                        v-for="b in borderos.data"
                        :key="b.id"
                        class="bg-white rounded-xl border border-gray-100 p-4 active:bg-gray-50"
                        @click="goShow(b.id)"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-gray-800">{{ b.number }}</p>
                                <p class="text-xs text-gray-500">{{ b.items_count }} títulos</p>
                            </div>
                            <Tag
                                :value="wasRejectedBack(b) ? 'Pendente (reprovado)' : (statusOptions[b.status] || b.status)"
                                :severity="wasRejectedBack(b) ? 'danger' : (statusSeverity[b.status] || 'secondary')"
                                class="!text-[10px]"
                            />
                        </div>
                        <p class="text-sm font-medium text-gray-700 mt-2">{{ formatMoney(b.total_amount) }}</p>
                    </div>
                </div>
            </template>
        </div>
    </component>
</template>
