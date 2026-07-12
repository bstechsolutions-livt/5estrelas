<script setup>
import { ref, watch, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import BottomSheet from '@/Components/Mobile/BottomSheet.vue'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useAuth } from '@/composables/useAuth'

import { branchAccessSummary } from '@/utils/branchAccessLabel'

const props = defineProps({
    users: Object,
    filters: Object,
    totalBranches: { type: Number, default: 0 },
})

const page = usePage()
const toast = useToast()
const confirm = useConfirm()
const { can, user: authUser } = useAuth()

const search = ref(props.filters?.search || '')

// Lista local que cresce com "Carregar mais"
const items = ref([...(props.users?.data || [])])
const currentPage = ref(props.users?.current_page || 1)
const lastPage = ref(props.users?.last_page || 1)
const loadingMore = ref(false)

// Quando o Inertia atualiza props (após filtros), reseta a lista
watch(() => props.users, (val) => {
    items.value = [...(val?.data || [])]
    currentPage.value = val?.current_page || 1
    lastPage.value = val?.last_page || 1
})

let searchTimeout = null
watch(search, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get('/usuarios', { search: val }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 300)
})

async function loadMore() {
    if (loadingMore.value || currentPage.value >= lastPage.value) return
    loadingMore.value = true
    try {
        const next = currentPage.value + 1
        const { data } = await axios.get('/usuarios', {
            params: {
                search: search.value,
                page: next,
            },
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

function goCreate() { router.visit('/usuarios/criar') }

const actionsOpen = ref(false)
const selectedUser = ref(null)

function openActions(u) {
    selectedUser.value = u
    actionsOpen.value = true
}

function goEdit() {
    router.visit(`/usuarios/${selectedUser.value.id}/editar`)
    actionsOpen.value = false
}

function goPermissions() {
    router.visit(`/usuarios/${selectedUser.value.id}/permissoes`)
    actionsOpen.value = false
}

function toggleActive() {
    router.post(`/usuarios/${selectedUser.value.id}/toggle-active`, {}, { preserveScroll: true })
    actionsOpen.value = false
}

function unlockUser() {
    router.post(`/usuarios/${selectedUser.value.id}/unlock`, {}, { preserveScroll: true })
    actionsOpen.value = false
}

function confirmDelete() {
    const u = selectedUser.value
    actionsOpen.value = false
    confirm.require({
        message: `Excluir o usuário "${u.name}"?`,
        header: 'Confirmação',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Excluir', severity: 'danger' },
        accept: () => router.delete(`/usuarios/${u.id}`, { preserveScroll: true }),
    })
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Sucesso', detail: msg, life: 3000 })
})
watch(() => page.props.flash?.error, (msg) => {
    if (msg) toast.add({ severity: 'error', summary: 'Erro', detail: msg, life: 4000 })
})

function userInitial(u) {
    return (u?.name || '?').charAt(0).toUpperCase()
}

const hasMore = computed(() => currentPage.value < lastPage.value)
</script>

<template>
    <AppLayoutMobile title="Usuários">
        <Toast position="top-right" />
        <ConfirmDialog />

        <div class="px-4 pt-3 pb-2">
            <InputText
                v-model="search"
                placeholder="Buscar por nome ou e-mail..."
                class="w-full"
                style="height: 44px"
            />
        </div>

        <div v-if="items.length" class="space-y-2 px-4 pb-4">
            <button
                v-for="u in items"
                :key="u.id"
                @click="openActions(u)"
                class="w-full bg-white rounded-xl border border-gray-200 p-3 flex items-center gap-3 active:bg-gray-50 text-left"
            >
                <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                    <span class="text-sm font-semibold text-gray-700">{{ userInitial(u) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">{{ u.name }} <span class="text-xs text-gray-400 font-normal">#{{ u.id }}</span></p>
                    <p class="text-xs text-gray-500 truncate">{{ u.email }}</p>
                    <p v-if="u.branches?.length" class="text-[10px] text-blue-600 mt-1 truncate"
                        :title="branchAccessSummary(u.branches, totalBranches).title || undefined">
                        {{ branchAccessSummary(u.branches, totalBranches).label }}
                    </p>
                    <p v-else class="text-[10px] text-gray-400 mt-1">Sem filiais liberadas</p>
                </div>
                <Tag :value="u.is_active ? 'Ativo' : 'Inativo'" :severity="u.is_active ? 'success' : 'secondary'" />
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
            Nenhum usuário encontrado.
        </div>

        <!-- FAB Novo usuário -->
        <button
            v-if="can('usuarios.criar')"
            @click="goCreate"
            class="fixed right-4 bottom-20 w-14 h-14 rounded-full shadow-lg flex items-center justify-center text-white"
            :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }"
        >
            <i class="pi pi-plus text-lg"></i>
        </button>

        <!-- Bottom sheet de ações -->
        <BottomSheet v-model="actionsOpen" :title="selectedUser?.name || ''">
            <div class="space-y-1">
                <button
                    v-if="can('usuarios.editar')"
                    @click="goEdit"
                    class="w-full flex items-center gap-3 p-3 rounded-lg active:bg-gray-100 text-left"
                >
                    <i class="pi pi-pencil text-gray-500"></i>
                    <span class="text-sm">Editar</span>
                </button>
                <button
                    v-if="can('usuarios.gerenciar_permissoes')"
                    @click="goPermissions"
                    class="w-full flex items-center gap-3 p-3 rounded-lg active:bg-gray-100 text-left"
                >
                    <i class="pi pi-key text-gray-500"></i>
                    <span class="text-sm">Permissões</span>
                </button>
                <button
                    v-if="can('usuarios.editar') && selectedUser?.id !== authUser?.id"
                    @click="toggleActive"
                    class="w-full flex items-center gap-3 p-3 rounded-lg active:bg-gray-100 text-left"
                >
                    <i :class="selectedUser?.is_active ? 'pi pi-ban' : 'pi pi-check-circle'" class="text-gray-500"></i>
                    <span class="text-sm">{{ selectedUser?.is_active ? 'Desativar' : 'Ativar' }}</span>
                </button>
                <button
                    v-if="can('usuarios.editar') && selectedUser?.locked_until"
                    @click="unlockUser"
                    class="w-full flex items-center gap-3 p-3 rounded-lg active:bg-amber-50 text-left text-amber-700"
                >
                    <i class="pi pi-unlock"></i>
                    <span class="text-sm">Desbloquear conta</span>
                </button>
                <button
                    v-if="can('usuarios.excluir') && selectedUser?.id !== authUser?.id"
                    @click="confirmDelete"
                    class="w-full flex items-center gap-3 p-3 rounded-lg active:bg-red-50 text-left text-red-600"
                >
                    <i class="pi pi-trash"></i>
                    <span class="text-sm">Excluir</span>
                </button>
            </div>
        </BottomSheet>
    </AppLayoutMobile>
</template>
