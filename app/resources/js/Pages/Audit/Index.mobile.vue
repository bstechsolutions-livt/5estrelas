<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import BottomSheet from '@/Components/Mobile/BottomSheet.vue'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'

const props = defineProps({
    logs: Object,
    filters: Object,
    options: Object,
})

const search = ref(props.filters.search || '')
const moduleFilter = ref(props.filters.module || null)
const eventFilter = ref(props.filters.event || null)
const userId = ref(props.filters.user_id || null)
const initialDateRange = (() => {
    const from = props.filters.from ? new Date(props.filters.from + 'T00:00:00') : null
    const to = props.filters.to ? new Date(props.filters.to + 'T00:00:00') : null
    if (from || to) return [from, to]
    return null
})()
const dateRange = ref(initialDateRange)

const items = ref([...(props.logs?.data || [])])
const currentPage = ref(props.logs?.current_page || 1)
const lastPage = ref(props.logs?.last_page || 1)
const loadingMore = ref(false)

watch(() => props.logs, (val) => {
    items.value = [...(val?.data || [])]
    currentPage.value = val?.current_page || 1
    lastPage.value = val?.last_page || 1
})

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

function buildParams() {
    return {
        search: search.value || undefined,
        module: moduleFilter.value || undefined,
        event: eventFilter.value || undefined,
        user_id: userId.value || undefined,
        from: dateRange.value?.[0] ? formatDate(dateRange.value[0]) : undefined,
        to: dateRange.value?.[1] ? formatDate(dateRange.value[1]) : undefined,
    }
}

function applyFilters() {
    router.get('/auditoria', buildParams(), { preserveState: true, replace: true })
}

function formatDate(d) {
    return d.toISOString().split('T')[0]
}

let searchTimeout = null
watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => applyFilters(), 300)
})

const filtersOpen = ref(false)

function applyAndClose() {
    applyFilters()
    filtersOpen.value = false
}

function clearFilters() {
    search.value = ''
    moduleFilter.value = null
    eventFilter.value = null
    userId.value = null
    dateRange.value = null
    applyFilters()
    filtersOpen.value = false
}

const activeFilterCount = computed(() => {
    let c = 0
    if (moduleFilter.value) c++
    if (eventFilter.value) c++
    if (userId.value) c++
    if (dateRange.value?.[0] || dateRange.value?.[1]) c++
    return c
})

async function loadMore() {
    if (loadingMore.value || currentPage.value >= lastPage.value) return
    loadingMore.value = true
    try {
        const next = currentPage.value + 1
        const { data } = await axios.get('/auditoria', {
            params: { ...buildParams(), page: next },
            headers: { 'X-Json-Only': '1', 'Accept': 'application/json' },
        })
        if (data?.data) {
            items.value.push(...data.data)
            currentPage.value = data.current_page
            lastPage.value = data.last_page
        }
    } finally {
        loadingMore.value = false
    }
}

const hasMore = computed(() => currentPage.value < lastPage.value)

// Detalhes (bottom sheet)
const detailsOpen = ref(false)
const selectedLog = ref(null)

function openDetails(log) {
    selectedLog.value = log
    detailsOpen.value = true
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
        noticias: 'info',
    }[m] || 'secondary'
}

const diffKeys = computed(() => {
    if (!selectedLog.value) return []
    const oldKeys = Object.keys(selectedLog.value.old_values || {})
    const newKeys = Object.keys(selectedLog.value.new_values || {})
    return [...new Set([...oldKeys, ...newKeys])]
})
</script>

<template>
    <AppLayoutMobile title="Auditoria">
        <div class="px-4 pt-3 pb-2 space-y-2">
            <InputText
                v-model="search"
                placeholder="Buscar por descrição ou usuário..."
                class="w-full"
                style="height: 44px"
            />
            <button
                @click="filtersOpen = true"
                class="w-full flex items-center justify-center gap-2 py-2.5 rounded-lg border border-gray-300 bg-white text-sm text-gray-700 active:bg-gray-50"
            >
                <i class="pi pi-filter"></i>
                <span>Filtros</span>
                <span v-if="activeFilterCount > 0" class="ml-1 inline-flex items-center justify-center min-w-[20px] h-5 px-1 rounded-full text-white text-xs" :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }">
                    {{ activeFilterCount }}
                </span>
            </button>
        </div>

        <div v-if="items.length" class="space-y-2 px-4 pb-4">
            <button
                v-for="log in items"
                :key="log.id"
                @click="openDetails(log)"
                class="w-full bg-white rounded-xl border border-gray-200 p-3 active:bg-gray-50 text-left"
            >
                <div class="flex items-start gap-2 mb-1">
                    <Tag :value="log.module" :severity="moduleSeverity(log.module)" />
                    <span class="text-[11px] text-gray-400 ml-auto">{{ formatDateTime(log.created_at) }}</span>
                </div>
                <p class="text-sm text-gray-800 leading-snug">{{ log.description || '—' }}</p>
                <div class="flex items-center justify-between mt-1.5">
                    <p class="text-xs text-gray-500 truncate">
                        <i class="pi pi-user text-[10px]"></i> {{ log.user_name || 'Sistema' }}
                    </p>
                    <code class="text-[10px] text-gray-400 truncate ml-2">{{ log.event }}</code>
                </div>
            </button>

            <button
                v-if="hasMore"
                @click="loadMore"
                :disabled="loadingMore"
                class="w-full py-3 text-sm text-blue-600 active:bg-blue-50 rounded-lg disabled:opacity-50"
            >
                {{ loadingMore ? 'Carregando...' : 'Carregar mais' }}
            </button>
            <p v-else-if="items.length > 0" class="text-center text-xs text-gray-400 py-2">
                Você chegou ao fim da lista.
            </p>
        </div>

        <div v-else class="text-center py-12 text-gray-400 text-sm">
            Nenhum log encontrado.
        </div>

        <!-- Bottom sheet de filtros -->
        <BottomSheet v-model="filtersOpen" title="Filtros">
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Módulo</label>
                    <Select v-model="moduleFilter" :options="moduleOptions" option-label="label" option-value="value" class="w-full" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Evento</label>
                    <Select v-model="eventFilter" :options="eventOptions" option-label="label" option-value="value" class="w-full" filter />
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
            <div class="flex gap-2 mt-4">
                <button @click="clearFilters" class="flex-1 py-3 rounded-lg border border-gray-300 text-gray-700 font-medium">
                    Limpar
                </button>
                <button @click="applyAndClose" class="flex-1 py-3 rounded-lg text-white font-medium" :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }">
                    Aplicar
                </button>
            </div>
        </BottomSheet>

        <!-- Bottom sheet de detalhes -->
        <BottomSheet v-model="detailsOpen" title="Detalhes do log">
            <div v-if="selectedLog" class="space-y-3 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-xs text-gray-500">Data/Hora</p>
                        <p class="font-medium text-gray-800">{{ formatDateTime(selectedLog.created_at) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Usuário</p>
                        <p class="font-medium text-gray-800">{{ selectedLog.user_name || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Módulo</p>
                        <Tag :value="selectedLog.module" :severity="moduleSeverity(selectedLog.module)" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Evento</p>
                        <code class="text-[11px]">{{ selectedLog.event }}</code>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-500">Descrição</p>
                        <p class="text-gray-800">{{ selectedLog.description || '—' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-500">IP</p>
                        <p class="font-mono text-xs text-gray-700">{{ selectedLog.ip_address || '—' }}</p>
                    </div>
                </div>

                <div v-if="diffKeys.length" class="border-t border-gray-200 pt-3">
                    <h3 class="text-xs font-semibold text-gray-700 mb-2">Mudanças</h3>
                    <div class="space-y-2">
                        <div v-for="key in diffKeys" :key="key" class="bg-gray-50 rounded-lg p-2">
                            <p class="text-[11px] font-mono text-gray-700 mb-1">{{ key }}</p>
                            <div class="grid grid-cols-2 gap-1 text-[11px]">
                                <div>
                                    <p class="text-gray-400 mb-0.5">Antes</p>
                                    <p class="text-red-600 font-mono break-all">
                                        <span v-if="selectedLog.old_values?.[key] !== undefined">{{ JSON.stringify(selectedLog.old_values[key]) }}</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-400 mb-0.5">Depois</p>
                                    <p class="text-green-600 font-mono break-all">
                                        <span v-if="selectedLog.new_values?.[key] !== undefined">{{ JSON.stringify(selectedLog.new_values[key]) }}</span>
                                        <span v-else class="text-gray-400">—</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="selectedLog.metadata && Object.keys(selectedLog.metadata).length" class="border-t border-gray-200 pt-3">
                    <h3 class="text-xs font-semibold text-gray-700 mb-2">Metadata</h3>
                    <pre class="bg-gray-50 p-2 rounded text-[10px] overflow-x-auto">{{ JSON.stringify(selectedLog.metadata, null, 2) }}</pre>
                </div>
            </div>
        </BottomSheet>
    </AppLayoutMobile>
</template>
