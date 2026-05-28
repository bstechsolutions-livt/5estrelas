<script setup>
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'

const props = defineProps({
    logs: Object,
    filters: Object,
    options: Object,
})

const search = ref(props.filters.search || '')
const module = ref(props.filters.module || null)
const event = ref(props.filters.event || null)
const userId = ref(props.filters.user_id || null)

const initialDateRange = (() => {
    const from = props.filters.from ? new Date(props.filters.from + 'T00:00:00') : null
    const to = props.filters.to ? new Date(props.filters.to + 'T00:00:00') : null
    if (from || to) return [from, to]
    return null
})()

const dateRange = ref(initialDateRange)

const moduleOptions = computed(() => [
    { label: 'Todos os módulos', value: null },
    ...props.options.modules.map(m => ({ label: m, value: m })),
])
const eventOptions = computed(() => [
    { label: 'Todos os eventos', value: null },
    ...props.options.events.map(e => ({ label: e, value: e })),
])
const userOptions = computed(() => [
    { label: 'Todos os usuários', value: null },
    ...props.options.users.map(u => ({ label: u.name, value: u.id })),
])

let searchTimeout = null
function applyFilters(opts = {}) {
    const params = {
        search: search.value || undefined,
        module: module.value || undefined,
        event: event.value || undefined,
        user_id: userId.value || undefined,
        from: dateRange.value?.[0] ? formatDate(dateRange.value[0]) : undefined,
        to: dateRange.value?.[1] ? formatDate(dateRange.value[1]) : undefined,
        per_page: opts.per_page || props.filters.per_page,
        page: opts.page || 1,
    }
    router.get('/auditoria', params, { preserveState: true, preserveScroll: true, replace: true })
}

function formatDate(d) {
    return d.toISOString().split('T')[0]
}

watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => applyFilters(), 300)
})

watch([module, event, userId, dateRange], () => applyFilters())

function onPage(e) {
    applyFilters({ per_page: e.rows, page: e.page + 1 })
}

function clearFilters() {
    search.value = ''
    module.value = null
    event.value = null
    userId.value = null
    dateRange.value = null
}

// Dialog detalhes
const dialogOpen = ref(false)
const selectedLog = ref(null)

function openDetails(log) {
    selectedLog.value = log
    dialogOpen.value = true
}

function formatDateTime(iso) {
    if (!iso) return ''
    return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'medium' })
}

function moduleSeverity(m) {
    return {
        auth: 'info',
        usuarios: 'success',
        aparencia: 'warn',
        perfil: 'secondary',
        auditoria: 'help',
    }[m] || 'secondary'
}

function jsonOrDash(v) {
    if (v === null || v === undefined) return '—'
    if (Object.keys(v).length === 0) return '—'
    return JSON.stringify(v, null, 2)
}

const diffKeys = computed(() => {
    if (!selectedLog.value) return []
    const oldKeys = Object.keys(selectedLog.value.old_values || {})
    const newKeys = Object.keys(selectedLog.value.new_values || {})
    return [...new Set([...oldKeys, ...newKeys])]
})
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-800">Auditoria</h1>
                <p class="text-gray-500 text-sm mt-1">Trilha de ações executadas no sistema</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
                        <InputText v-model="search" placeholder="Descrição, usuário..." class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Módulo</label>
                        <Select v-model="module" :options="moduleOptions" option-label="label" option-value="value" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Evento</label>
                        <Select v-model="event" :options="eventOptions" option-label="label" option-value="value" class="w-full" filter />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Usuário</label>
                        <Select v-model="userId" :options="userOptions" option-label="label" option-value="value" class="w-full" filter />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Período</label>
                        <DatePicker
                            v-model="dateRange"
                            selection-mode="range"
                            date-format="dd/mm/yy"
                            placeholder="Início — Fim"
                            class="w-full"
                            show-button-bar
                        />
                    </div>
                </div>
                <div class="flex justify-end mt-3">
                    <Button label="Limpar filtros" icon="pi pi-filter-slash" severity="secondary" outlined size="small" @click="clearFilters" />
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <DataTable
                    :value="logs.data"
                    lazy
                    paginator
                    :rows="logs.per_page"
                    :total-records="logs.total"
                    :first="(logs.current_page - 1) * logs.per_page"
                    :rows-per-page-options="[15, 25, 50, 100]"
                    @page="onPage"
                    striped-rows
                    data-key="id"
                    responsive-layout="scroll"
                >
                    <Column header="Data/Hora" style="min-width: 160px">
                        <template #body="{ data }">
                            <span class="text-sm text-gray-700">{{ formatDateTime(data.created_at) }}</span>
                        </template>
                    </Column>
                    <Column header="Usuário" style="min-width: 180px">
                        <template #body="{ data }">
                            <div>
                                <p class="text-sm text-gray-700">{{ data.user_name || '—' }}</p>
                                <p class="text-xs text-gray-400">{{ data.ip_address || '' }}</p>
                            </div>
                        </template>
                    </Column>
                    <Column header="Módulo" style="width: 120px">
                        <template #body="{ data }">
                            <Tag :value="data.module" :severity="moduleSeverity(data.module)" />
                        </template>
                    </Column>
                    <Column header="Evento" style="min-width: 200px">
                        <template #body="{ data }">
                            <code class="text-xs text-gray-500">{{ data.event }}</code>
                        </template>
                    </Column>
                    <Column header="Descrição">
                        <template #body="{ data }">
                            <span class="text-sm">{{ data.description || '—' }}</span>
                        </template>
                    </Column>
                    <Column header="" style="width: 80px">
                        <template #body="{ data }">
                            <Button icon="pi pi-eye" severity="secondary" text rounded title="Detalhes" @click="openDetails(data)" />
                        </template>
                    </Column>

                    <template #empty>
                        <div class="text-center py-8 text-gray-500">Nenhum log encontrado.</div>
                    </template>
                </DataTable>
            </div>
        </div>

        <Dialog v-model:visible="dialogOpen" modal header="Detalhes do log" :style="{ width: '760px', maxWidth: '95vw' }">
            <div v-if="selectedLog" class="space-y-4">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-xs text-gray-500">Data/Hora</p>
                        <p class="font-medium text-gray-700">{{ formatDateTime(selectedLog.created_at) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Usuário</p>
                        <p class="font-medium text-gray-700">{{ selectedLog.user_name || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Módulo</p>
                        <Tag :value="selectedLog.module" :severity="moduleSeverity(selectedLog.module)" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Evento</p>
                        <code class="text-xs">{{ selectedLog.event }}</code>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-500">Descrição</p>
                        <p class="font-medium text-gray-700">{{ selectedLog.description || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">IP</p>
                        <p class="font-mono text-xs text-gray-600">{{ selectedLog.ip_address || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Recurso afetado</p>
                        <p class="text-xs text-gray-600">{{ selectedLog.auditable_type ? `${selectedLog.auditable_type}#${selectedLog.auditable_id}` : '—' }}</p>
                    </div>
                </div>

                <div v-if="diffKeys.length" class="border-t border-gray-200 pt-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Mudanças</h3>
                    <div class="rounded-lg border border-gray-200 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left px-3 py-2 text-xs font-medium text-gray-500">Campo</th>
                                    <th class="text-left px-3 py-2 text-xs font-medium text-gray-500">Antes</th>
                                    <th class="text-left px-3 py-2 text-xs font-medium text-gray-500">Depois</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="key in diffKeys" :key="key" class="border-t border-gray-100">
                                    <td class="px-3 py-2 font-mono text-xs text-gray-700">{{ key }}</td>
                                    <td class="px-3 py-2 text-xs text-red-600 break-all">
                                        <span v-if="selectedLog.old_values && selectedLog.old_values[key] !== undefined">{{ JSON.stringify(selectedLog.old_values[key]) }}</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-green-600 break-all">
                                        <span v-if="selectedLog.new_values && selectedLog.new_values[key] !== undefined">{{ JSON.stringify(selectedLog.new_values[key]) }}</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="selectedLog.metadata && Object.keys(selectedLog.metadata).length" class="border-t border-gray-200 pt-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Metadata</h3>
                    <pre class="bg-gray-50 p-3 rounded text-xs overflow-auto">{{ jsonOrDash(selectedLog.metadata) }}</pre>
                </div>

                <div v-if="selectedLog.user_agent" class="border-t border-gray-200 pt-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">User Agent</h3>
                    <p class="text-xs text-gray-500 break-all">{{ selectedLog.user_agent }}</p>
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
