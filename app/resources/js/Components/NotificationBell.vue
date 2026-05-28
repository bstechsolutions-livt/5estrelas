<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { useNotifications } from '@/composables/useNotifications'

const { items, unreadCount, loading, loadingMore, hasMore, fetchList, loadMore, markRead, markAllRead, destroy } = useNotifications()

const open = ref(false)
const popoverRef = ref(null)
const scrollRef = ref(null)

async function toggle() {
    open.value = !open.value
    if (open.value) {
        await fetchList()
    }
}

function close() {
    open.value = false
}

function onScroll(e) {
    const el = e.target
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 50) {
        loadMore()
    }
}

function onClickItem(n) {
    markRead(n.id)
    if (n.link) {
        close()
        router.visit(n.link)
    }
}

function onDelete(e, id) {
    e.stopPropagation()
    destroy(id)
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

function typeColor(type) {
    return {
        info: 'text-blue-600',
        success: 'text-green-600',
        warning: 'text-amber-600',
        danger: 'text-red-600',
    }[type] || 'text-gray-600'
}

function typeIcon(type, fallbackIcon) {
    if (fallbackIcon) return fallbackIcon
    return {
        info: 'pi pi-info-circle',
        success: 'pi pi-check-circle',
        warning: 'pi pi-exclamation-triangle',
        danger: 'pi pi-times-circle',
    }[type] || 'pi pi-bell'
}

const hasUnread = computed(() => unreadCount.value > 0)
</script>

<template>
    <div class="relative" ref="popoverRef">
        <button
            @click="toggle"
            class="p-2 rounded-lg relative transition-colors sidebar-icon-btn"
            title="Notificações"
        >
            <i class="pi pi-bell text-lg"></i>
            <span
                v-if="hasUnread"
                class="absolute top-0 right-0 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <!-- Backdrop pra fechar ao clicar fora -->
        <Teleport to="body">
            <div v-if="open" class="fixed inset-0 z-40" @click="close"></div>
        </Teleport>

        <!-- Popover -->
        <Transition name="popover">
            <div
                v-if="open"
                class="absolute right-0 mt-2 w-[360px] max-w-[calc(100vw-2rem)] bg-white rounded-xl shadow-2xl border border-gray-200 z-50 overflow-hidden flex flex-col"
                style="max-height: 70vh"
            >
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                    <div>
                        <h3 class="font-semibold text-gray-800 text-sm">Notificações</h3>
                        <p v-if="hasUnread" class="text-xs text-gray-500 mt-0.5">{{ unreadCount }} não lida(s)</p>
                        <p v-else class="text-xs text-gray-400 mt-0.5">Todas lidas</p>
                    </div>
                    <button
                        v-if="hasUnread"
                        @click="markAllRead"
                        class="text-xs text-blue-600 hover:underline"
                    >
                        Marcar todas
                    </button>
                </div>

                <div class="overflow-y-auto flex-1" ref="scrollRef" @scroll="onScroll">
                    <div v-if="loading && !items.length" class="p-6 text-center text-sm text-gray-400">
                        Carregando...
                    </div>
                    <div v-else-if="!items.length" class="p-8 text-center text-sm text-gray-400">
                        <i class="pi pi-inbox text-3xl text-gray-300 block mb-2"></i>
                        Nenhuma notificação por enquanto.
                    </div>
                    <button
                        v-for="n in items"
                        :key="n.id"
                        @click="onClickItem(n)"
                        class="w-full flex items-start gap-3 px-4 py-3 hover:bg-gray-50 border-b border-gray-50 text-left transition-colors group"
                        :class="{ 'bg-blue-50/40': !n.read_at }"
                    >
                        <div class="mt-0.5 flex-shrink-0">
                            <i :class="[typeIcon(n.type, n.icon), 'text-lg', typeColor(n.type)]"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ n.title }}</p>
                                <span class="text-[11px] text-gray-400 flex-shrink-0 mt-0.5">{{ timeAgo(n.created_at) }}</span>
                            </div>
                            <p v-if="n.message" class="text-xs text-gray-600 mt-0.5 line-clamp-2">{{ n.message }}</p>
                            <div class="flex items-center justify-between mt-1">
                                <span v-if="!n.read_at" class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                                <span v-else></span>
                                <button
                                    @click="onDelete($event, n.id)"
                                    class="text-[11px] text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity"
                                    title="Excluir"
                                >
                                    <i class="pi pi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </button>
                    <div v-if="loadingMore" class="py-3 text-center text-xs text-gray-400">
                        Carregando mais...
                    </div>
                    <div v-else-if="!hasMore && items.length > 0" class="py-3 text-center text-[11px] text-gray-400">
                        Fim da lista
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.popover-enter-active,
.popover-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.popover-enter-from,
.popover-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
