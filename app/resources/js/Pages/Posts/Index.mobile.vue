<script setup>
import { ref, watch, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import BottomSheet from '@/Components/Mobile/BottomSheet.vue'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'

const props = defineProps({
    posts: Object,
    filters: Object,
})

const page = usePage()
const toast = useToast()
const confirm = useConfirm()

const type = ref(props.filters?.type || 'news')

const items = ref([...(props.posts?.data || [])])
const currentPage = ref(props.posts?.current_page || 1)
const lastPage = ref(props.posts?.last_page || 1)
const loadingMore = ref(false)

watch(() => props.posts, (val) => {
    items.value = [...(val?.data || [])]
    currentPage.value = val?.current_page || 1
    lastPage.value = val?.last_page || 1
})

watch(type, (val) => {
    router.get('/noticias', { type: val }, { preserveState: true, preserveScroll: false, replace: true })
})

async function loadMore() {
    if (loadingMore.value || currentPage.value >= lastPage.value) return
    loadingMore.value = true
    try {
        const next = currentPage.value + 1
        const { data } = await axios.get('/noticias', {
            params: { type: type.value, page: next },
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

function goCreate() {
    router.visit(`/noticias/criar?type=${type.value}`)
}

const actionsOpen = ref(false)
const selectedPost = ref(null)

function openActions(p) {
    selectedPost.value = p
    actionsOpen.value = true
}

function goEdit() {
    router.visit(`/noticias/${selectedPost.value.id}/editar`)
    actionsOpen.value = false
}

function toggleActive() {
    router.post(`/noticias/${selectedPost.value.id}/toggle-active`, {}, { preserveScroll: true })
    actionsOpen.value = false
}

function confirmDelete() {
    const p = selectedPost.value
    actionsOpen.value = false
    confirm.require({
        message: `Excluir "${p.title}"?`,
        header: 'Confirmação',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Cancelar', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Excluir', severity: 'danger' },
        accept: () => router.delete(`/noticias/${p.id}`, { preserveScroll: true }),
    })
}

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

function statusInfo(p) {
    const now = new Date()
    if (!p.is_active) return { label: 'Inativo', severity: 'secondary' }
    if (p.expires_at && new Date(p.expires_at) < now) return { label: 'Expirado', severity: 'warn' }
    if (p.published_at && new Date(p.published_at) > now) return { label: 'Agendado', severity: 'info' }
    return { label: 'Ativo', severity: 'success' }
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) toast.add({ severity: 'success', summary: 'Sucesso', detail: msg, life: 3000 })
})

const hasMore = computed(() => currentPage.value < lastPage.value)
</script>

<template>
    <AppLayoutMobile title="Notícias">
        <Toast position="top-right" />
        <ConfirmDialog />

        <!-- Tabs Notícias / Destaques -->
        <div class="bg-white border-b border-gray-200 sticky top-14 z-10">
            <div class="flex">
                <button
                    @click="type = 'news'"
                    :class="[
                        'flex-1 py-3 text-sm font-medium transition-colors',
                        type === 'news' ? 'border-b-2 text-[var(--app-primary)]' : 'text-gray-500'
                    ]"
                    :style="type === 'news' ? { borderColor: 'var(--app-primary)' } : ''"
                >
                    Notícias
                </button>
                <button
                    @click="type = 'highlight'"
                    :class="[
                        'flex-1 py-3 text-sm font-medium transition-colors',
                        type === 'highlight' ? 'border-b-2 text-[var(--app-primary)]' : 'text-gray-500'
                    ]"
                    :style="type === 'highlight' ? { borderColor: 'var(--app-primary)' } : ''"
                >
                    Destaques
                </button>
            </div>
        </div>

        <div v-if="items.length" class="space-y-2 px-4 pt-3 pb-4">
            <button
                v-for="p in items"
                :key="p.id"
                @click="openActions(p)"
                class="w-full bg-white rounded-xl border border-gray-200 p-3 flex items-center gap-3 active:bg-gray-50 text-left"
            >
                <div class="w-14 h-14 rounded-lg bg-gray-100 overflow-hidden flex items-center justify-center flex-shrink-0">
                    <img v-if="p.image_url" :src="p.image_url" class="w-full h-full object-cover" />
                    <i v-else class="pi pi-image text-gray-300"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">{{ p.title }}</p>
                    <div class="flex items-center gap-2 mt-1">
                        <Tag :value="statusInfo(p).label" :severity="statusInfo(p).severity" />
                        <span class="text-xs text-gray-400 truncate">{{ formatDate(p.published_at) }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-gray-400 mt-1">
                        <span><i class="pi pi-heart-fill text-red-400 text-[10px]"></i> {{ p.likes_count || 0 }}</span>
                        <span><i class="pi pi-comment text-blue-400 text-[10px]"></i> {{ p.comments_count || 0 }}</span>
                    </div>
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

        <div v-else class="text-center py-12 text-gray-400 text-sm px-4">
            Nenhuma postagem encontrada.
        </div>

        <!-- FAB -->
        <button
            @click="goCreate"
            class="fixed right-4 bottom-20 w-14 h-14 rounded-full shadow-lg flex items-center justify-center text-white"
            :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }"
        >
            <i class="pi pi-plus text-lg"></i>
        </button>

        <!-- Bottom sheet de ações -->
        <BottomSheet v-model="actionsOpen" :title="selectedPost?.title || ''">
            <div class="space-y-1">
                <button @click="goEdit" class="w-full flex items-center gap-3 p-3 rounded-lg active:bg-gray-100 text-left">
                    <i class="pi pi-pencil text-gray-500"></i>
                    <span class="text-sm">Editar</span>
                </button>
                <button @click="toggleActive" class="w-full flex items-center gap-3 p-3 rounded-lg active:bg-gray-100 text-left">
                    <i :class="selectedPost?.is_active ? 'pi pi-ban' : 'pi pi-check-circle'" class="text-gray-500"></i>
                    <span class="text-sm">{{ selectedPost?.is_active ? 'Inativar' : 'Ativar' }}</span>
                </button>
                <button @click="confirmDelete" class="w-full flex items-center gap-3 p-3 rounded-lg active:bg-red-50 text-left text-red-600">
                    <i class="pi pi-trash"></i>
                    <span class="text-sm">Excluir</span>
                </button>
            </div>
        </BottomSheet>
    </AppLayoutMobile>
</template>
