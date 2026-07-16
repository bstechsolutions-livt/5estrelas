<script setup>
import { ref, computed, watch } from 'vue'
import { usePage, useForm, router } from '@inertiajs/vue3'
import MobileHeader from '@/Components/Mobile/MobileHeader.vue'
import BottomNav from '@/Components/Mobile/BottomNav.vue'
import SideDrawer from '@/Components/Mobile/SideDrawer.vue'
import BottomSheet from '@/Components/Mobile/BottomSheet.vue'
import Checkbox from 'primevue/checkbox'
import { useTheme } from '@/composables/useTheme'
import { useNotifications } from '@/composables/useNotifications'
import ImpersonationBanner from '@/Components/ImpersonationBanner.vue'

defineProps({
    title: { type: String, default: '' },
    showBack: { type: Boolean, default: false },
    hideBottomNav: { type: Boolean, default: false },
})

const page = usePage()
const drawerOpen = ref(false)
const configureOpen = ref(false)

const userShortcuts = computed(() => page.props.mobileNavShortcuts || [])
const menuOptions = computed(() => (page.props.menuOptions || []).filter(o => o.key !== 'dashboard'))

const selectedKeys = ref([])
const MAX_SHORTCUTS = 4

const form = useForm({ slot: 'mobile_nav', menu_keys: [] })

function openConfigure() {
    selectedKeys.value = userShortcuts.value.map(s => s.key)
    configureOpen.value = true
}

function toggle(key) {
    const idx = selectedKeys.value.indexOf(key)
    if (idx === -1) {
        if (selectedKeys.value.length >= MAX_SHORTCUTS) return
        selectedKeys.value.push(key)
    } else {
        selectedKeys.value.splice(idx, 1)
    }
}

function isChecked(key) {
    return selectedKeys.value.includes(key)
}

function disableUnchecked(key) {
    return !isChecked(key) && selectedKeys.value.length >= MAX_SHORTCUTS
}

function save() {
    form.menu_keys = selectedKeys.value
    form.put('/perfil/atalhos', {
        preserveScroll: true,
        onSuccess: () => {
            configureOpen.value = false
        },
    })
}

useTheme()

// Notificações
const notifOpen = ref(false)
const { items: notifications, unreadCount, loading: notifLoading, loadingMore: notifLoadingMore, hasMore: notifHasMore, fetchList, loadMore: notifLoadMore, markRead, markAllRead, destroy: destroyNotif } = useNotifications()

async function openNotifications() {
    notifOpen.value = true
    await fetchList()
}

function timeAgo(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    const sec = Math.floor((Date.now() - d.getTime()) / 1000)
    if (sec < 60) return `${sec}s`
    const min = Math.floor(sec / 60)
    if (min < 60) return `${min}min`
    const h = Math.floor(min / 60)
    if (h < 24) return `${h}h`
    const days = Math.floor(h / 24)
    if (days < 30) return `${days}d`
    return d.toLocaleDateString('pt-BR')
}

function typeIcon(type, fallback) {
    if (fallback) return fallback
    return {
        info: 'pi pi-info-circle',
        success: 'pi pi-check-circle',
        warning: 'pi pi-exclamation-triangle',
        danger: 'pi pi-times-circle',
    }[type] || 'pi pi-bell'
}

function typeColor(type) {
    return {
        info: 'text-blue-600',
        success: 'text-green-600',
        warning: 'text-amber-600',
        danger: 'text-red-600',
    }[type] || 'text-gray-600'
}

function onClickNotif(n) {
    markRead(n.id)
    if (n.link) {
        notifOpen.value = false
        router.visit(n.link)
    }
}

function onDeleteNotif(e, id) {
    e.stopPropagation()
    destroyNotif(id)
}
</script>

<template>
    <div class="min-h-screen flex flex-col bg-gray-50">
        <ImpersonationBanner />
        <MobileHeader :title="title" :show-back="showBack" @menu="drawerOpen = true" @notifications="openNotifications" />

        <main class="flex-1 mobile-main" :class="hideBottomNav ? 'pb-4' : 'pb-20'">
            <slot />
        </main>

        <BottomNav v-if="!hideBottomNav" :shortcuts="userShortcuts" @configure="openConfigure" />

        <SideDrawer v-model="drawerOpen" />

        <!-- Bottom sheet global de configuração da barra -->
        <BottomSheet v-model="configureOpen" title="Editar barra inferior">
            <p class="text-sm text-gray-500 mb-3">
                Escolha até {{ MAX_SHORTCUTS }} atalhos para a barra inferior.
                <span class="block text-xs text-gray-400 mt-0.5">Início e Editar são fixos.</span>
            </p>

            <div class="space-y-1">
                <label
                    v-for="opt in menuOptions"
                    :key="opt.key"
                    :class="[
                        'flex items-center gap-3 p-3 rounded-lg cursor-pointer',
                        disableUnchecked(opt.key) ? 'opacity-40 cursor-not-allowed' : 'active:bg-gray-100'
                    ]"
                >
                    <Checkbox
                        :model-value="isChecked(opt.key)"
                        :binary="true"
                        :disabled="disableUnchecked(opt.key)"
                        @update:model-value="toggle(opt.key)"
                    />
                    <i :class="[opt.icon, 'text-gray-500']"></i>
                    <span class="text-sm text-gray-700">{{ opt.label }}</span>
                </label>
            </div>

            <p v-if="selectedKeys.length >= MAX_SHORTCUTS" class="text-xs text-amber-600 mt-2 px-1">
                Limite de {{ MAX_SHORTCUTS }} atalhos atingido. Desmarque algum para escolher outro.
            </p>

            <button
                @click="save"
                :disabled="form.processing"
                class="mt-4 w-full py-3 rounded-lg text-white font-medium disabled:opacity-50"
                :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }"
            >
                {{ form.processing ? 'Salvando...' : 'Salvar atalhos' }}
            </button>
        </BottomSheet>

        <!-- Bottom sheet de notificações -->
        <BottomSheet v-model="notifOpen" title="Notificações">
            <div v-if="unreadCount > 0" class="flex items-center justify-between mb-2 -mt-1">
                <p class="text-xs text-gray-500">{{ unreadCount }} não lida(s)</p>
                <button
                    @click="markAllRead"
                    class="text-xs text-blue-600 active:underline"
                >
                    Marcar todas
                </button>
            </div>

            <div v-if="notifLoading && !notifications.length" class="py-8 text-center text-sm text-gray-400">
                Carregando...
            </div>
            <div v-else-if="!notifications.length" class="py-8 text-center text-sm text-gray-400">
                <i class="pi pi-inbox text-3xl text-gray-300 block mb-2"></i>
                Nenhuma notificação por enquanto.
            </div>
            <div v-else class="space-y-1 -mx-4">
                <button
                    v-for="n in notifications"
                    :key="n.id"
                    @click="onClickNotif(n)"
                    class="w-full flex items-start gap-3 px-4 py-3 active:bg-gray-100 border-b border-gray-100 text-left"
                    :class="{ 'bg-blue-50/40': !n.read_at }"
                >
                    <div class="mt-0.5 flex-shrink-0">
                        <i :class="[typeIcon(n.type, n.icon), 'text-lg', typeColor(n.type)]"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-medium text-gray-800">{{ n.title }}</p>
                            <span class="text-[11px] text-gray-400 flex-shrink-0 mt-0.5">{{ timeAgo(n.created_at) }}</span>
                        </div>
                        <p v-if="n.message" class="text-xs text-gray-600 mt-0.5">{{ n.message }}</p>
                        <div class="flex items-center justify-between mt-1">
                            <span v-if="!n.read_at" class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                            <span v-else></span>
                            <button
                                @click="onDeleteNotif($event, n.id)"
                                class="text-[11px] text-gray-400 active:text-red-500"
                                title="Excluir"
                            >
                                <i class="pi pi-trash"></i>
                            </button>
                        </div>
                    </div>
                </button>
                <button
                    v-if="notifHasMore"
                    @click="notifLoadMore"
                    :disabled="notifLoadingMore"
                    class="w-full py-3 text-sm text-blue-600 active:bg-blue-50 disabled:opacity-50"
                >
                    {{ notifLoadingMore ? 'Carregando...' : 'Carregar mais' }}
                </button>
                <p v-else class="text-center py-3 text-[11px] text-gray-400">Fim da lista</p>
            </div>
        </BottomSheet>
    </div>
</template>
