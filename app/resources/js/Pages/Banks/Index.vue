<script setup>
import { computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Paginator from 'primevue/paginator'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { useDevice } from '@/composables/useDevice'
import { ref } from 'vue'

const props = defineProps({
    accounts: Object,
    filters: Object,
    canManage: Boolean,
})

const { isMobile } = useDevice()
const page = usePage()
const toast = useToast()

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

const statusOptions = [
    { value: '', label: 'Todas' },
    { value: 'active', label: 'Ativas' },
    { value: 'inactive', label: 'Inativas' },
]

const rows = computed(() => props.accounts?.data ?? [])

function applyFilters() {
    router.get('/financeiro/bancos', {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true })
}

function toggle(id) {
    router.post(`/financeiro/bancos/${id}/toggle`, {}, { preserveScroll: true })
}

function onPage(event) {
    router.get('/financeiro/bancos', {
        search: search.value || undefined,
        status: status.value || undefined,
        page: event.page + 1,
    }, { preserveState: true, replace: true })
}

function bankLabel(row) {
    if (!row.bank_code && !row.account_number) return '—'
    const parts = []
    if (row.bank_code) parts.push(row.bank_code + (row.bank_name ? ` ${row.bank_name}` : ''))
    if (row.agency) parts.push(`Ag ${row.agency}`)
    if (row.account_number) parts.push(`CC ${row.account_number}${row.account_digit ? '-' + row.account_digit : ''}`)
    return parts.join(' · ')
}

function formatCurrency(value) {
    if (value === null || value === undefined) return 'Saldo não informado'
    return Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatDate(value) {
    if (!value) return null
    const [year, month, day] = value.split('-')
    return `${day}/${month}/${year}`
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Pronto', detail: msg, life: 5000 })
})
watch(() => page.props.flash?.error, (msg) => {
    if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 6000 })
})
</script>

<template>
    <component :is="isMobile ? AppLayoutMobile : AppLayout" :title="isMobile ? 'Bancos' : undefined" :show-back="isMobile">
        <Toast />
        <div :class="isMobile ? 'px-4 py-3 pb-24 space-y-4' : 'max-w-6xl mx-auto space-y-6'">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <h1 :class="isMobile ? 'text-lg font-bold text-gray-800' : 'text-2xl font-bold text-gray-800'">
                        Contas bancárias
                    </h1>
                    <p class="text-sm text-gray-500 mt-1 max-w-2xl">
                        Cadastro usado na conciliação OFX. Complete banco/agência/conta de cada conta aqui.
                    </p>
                </div>
                <div v-if="canManage" class="flex flex-wrap gap-2 shrink-0">
                    <Button label="Nova conta" icon="pi pi-plus" size="small"
                        @click="router.visit('/financeiro/bancos/criar')" />
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-100 p-3 sm:p-4 flex flex-col sm:flex-row gap-3">
                <InputText v-model="search" class="w-full sm:flex-1" placeholder="Buscar nome, conta Senior, banco..."
                    @keyup.enter="applyFilters" />
                <Select v-model="status" :options="statusOptions" option-label="label" option-value="value"
                    class="w-full sm:w-44" placeholder="Status" />
                <Button label="Filtrar" icon="pi pi-search" size="small" @click="applyFilters" />
            </div>

            <div v-if="!rows.length" class="bg-white rounded-xl border border-gray-100 p-10 text-center">
                <i class="pi pi-building text-3xl text-gray-300 mb-3"></i>
                <p class="text-sm text-gray-600 font-medium">Nenhuma conta cadastrada</p>
                <p class="text-xs text-gray-400 mt-1 mb-4">Cadastre manualmente ou peça a importação inicial.</p>
                <div v-if="canManage" class="flex justify-center gap-2">
                    <Button label="Nova conta" icon="pi pi-plus" size="small"
                        @click="router.visit('/financeiro/bancos/criar')" />
                </div>
            </div>

            <div v-else class="space-y-2">
                <div
                    v-for="row in rows"
                    :key="row.id"
                    class="bg-white rounded-xl border border-gray-100 p-4 flex flex-col sm:flex-row sm:items-center gap-3"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ row.name }}</p>
                            <Tag :value="row.is_active ? 'Ativa' : 'Inativa'" :severity="row.is_active ? 'success' : 'secondary'" class="!text-[10px]" />
                            <Tag v-if="row.from_senior" value="Senior" severity="info" class="!text-[10px]" />
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            <span v-if="row.senior_num_cco">Conta interna {{ row.senior_num_cco }}</span>
                            <span v-if="row.senior_codemp"> · Emp {{ row.senior_codemp }}</span>
                        </p>
                        <p class="text-xs text-gray-600 mt-0.5">{{ bankLabel(row) }}</p>
                    </div>
                    <div class="sm:text-right shrink-0 sm:min-w-40">
                        <p
                            class="text-sm font-semibold"
                            :class="row.current_balance === null ? 'text-gray-400' : (row.current_balance < 0 ? 'text-red-600' : 'text-emerald-700')"
                        >
                            {{ formatCurrency(row.current_balance) }}
                        </p>
                        <p v-if="row.balance_date" class="text-[11px] text-gray-400 mt-0.5">
                            {{ row.balance_source === 'ofx' ? 'Saldo OFX' : 'Saldo inicial' }}
                            · {{ formatDate(row.balance_date) }}
                        </p>
                    </div>
                    <div v-if="canManage" class="flex gap-2 shrink-0">
                        <Button icon="pi pi-pencil" text rounded size="small" title="Editar"
                            @click="router.visit(`/financeiro/bancos/${row.id}/editar`)" />
                        <Button
                            :icon="row.is_active ? 'pi pi-eye-slash' : 'pi pi-eye'"
                            text rounded size="small"
                            :title="row.is_active ? 'Desativar' : 'Ativar'"
                            @click="toggle(row.id)"
                        />
                    </div>
                </div>
            </div>

            <Paginator
                v-if="accounts?.last_page > 1"
                :rows="accounts.per_page"
                :total-records="accounts.total"
                :first="(accounts.current_page - 1) * accounts.per_page"
                @page="onPage"
            />
        </div>
    </component>
</template>
