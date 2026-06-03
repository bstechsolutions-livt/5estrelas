<script setup>
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import BottomSheet from '@/Components/Mobile/BottomSheet.vue'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'

const props = defineProps({
    payables: Object,
    totals: Object,
    filters: Object,
    branches: Array,
    statusOptions: Object,
})

const search = ref(props.filters?.search || '')
const filtersOpen = ref(false)
const status = ref(props.filters?.status || null)

const statusList = computed(() => [
    { label: 'Todos', value: null },
    ...Object.entries(props.statusOptions).map(([k, v]) => ({ label: v, value: k })),
])

let timer = null
watch(search, (val) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
        router.get('/financeiro/contas-pagar', { search: val || undefined, status: status.value || undefined }, { preserveState: true, replace: true })
    }, 300)
})

function applyFilter() {
    router.get('/financeiro/contas-pagar', { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true })
    filtersOpen.value = false
}

function goShow(id) { router.visit(`/financeiro/contas-pagar/${id}`) }

function formatMoney(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0)
}
function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('pt-BR')
}

const statusSeverity = { pendente: 'warn', em_preparacao: 'info', aguardando_aprovacao: 'warn', aprovado: 'success', reprovado: 'danger', pago: 'success' }
const totalGeral = computed(() => Object.values(props.totals || {}).reduce((s, t) => s + Number(t.total || 0), 0))
const countGeral = computed(() => Object.values(props.totals || {}).reduce((s, t) => s + Number(t.count || 0), 0))
</script>

<template>
    <AppLayoutMobile title="Contas a Pagar">
        <!-- Resumo compacto -->
        <div class="px-4 pt-3 pb-2">
            <div class="bg-white rounded-xl border border-gray-200 p-3 flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Total em aberto</p>
                    <p class="text-lg font-bold text-gray-800">{{ formatMoney(totalGeral) }}</p>
                </div>
                <p class="text-xs text-gray-400">{{ countGeral }} títulos</p>
            </div>
        </div>

        <!-- Busca + filtro -->
        <div class="px-4 pb-2 flex gap-2">
            <InputText v-model="search" placeholder="Buscar..." class="flex-1" style="height: 44px" />
            <button @click="filtersOpen = true" class="w-11 h-11 rounded-lg border border-gray-300 flex items-center justify-center">
                <i class="pi pi-filter text-gray-600"></i>
            </button>
        </div>

        <!-- Lista -->
        <div v-if="payables.data.length" class="px-4 space-y-2 pb-20">
            <button v-for="p in payables.data" :key="p.id" @click="goShow(p.id)"
                class="w-full bg-white rounded-xl border border-gray-200 p-3 text-left active:bg-gray-50">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <p class="text-sm font-medium text-gray-800 truncate flex-1">{{ p.supplier_name }}</p>
                    <Tag :value="statusOptions[p.status]" :severity="statusSeverity[p.status]" class="text-[10px]" />
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">{{ formatMoney(p.amount) }}</span>
                    <span class="text-xs text-gray-400">Venc: {{ formatDate(p.due_date) }}</span>
                </div>
                <p v-if="p.title_number" class="text-[11px] text-gray-400 mt-1">Título: {{ p.title_number }}</p>
            </button>
        </div>
        <div v-else class="text-center py-12 text-gray-400 text-sm">Nenhum título encontrado.</div>

        <!-- Filtros bottom sheet -->
        <BottomSheet v-model="filtersOpen" title="Filtros">
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                    <Select v-model="status" :options="statusList" option-label="label" option-value="value" class="w-full" />
                </div>
            </div>
            <button @click="applyFilter" class="mt-4 w-full py-3 rounded-lg text-white font-medium" :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }">
                Aplicar
            </button>
        </BottomSheet>
    </AppLayoutMobile>
</template>
