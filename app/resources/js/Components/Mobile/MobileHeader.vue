<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useTheme } from '@/composables/useTheme'
import { useNotifications } from '@/composables/useNotifications'

const props = defineProps({
    title: { type: String, default: '' },
    showBack: { type: Boolean, default: false },
})

const emit = defineEmits(['menu', 'notifications'])

const page = usePage()
const { theme } = useTheme()
const { unreadCount } = useNotifications()

const user = computed(() => page.props.auth?.user)
const appName = computed(() => theme.value?.app_name || '5 Estrelas')
const logoUrl = computed(() => theme.value?.logo_url)
const primaryColor = computed(() => theme.value?.primary_color || '#3b82f6')
const sidebarBg = computed(() => theme.value?.secondary_color || '#1e1e2d')

const reloading = ref(false)

const initials = computed(() => {
    const parts = appName.value.split(' ').filter(Boolean)
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
    return appName.value.substring(0, 2).toUpperCase()
})

function back() {
    window.history.back()
}

function goProfile() {
    router.visit('/perfil')
}

function reload() {
    if (reloading.value) return
    reloading.value = true
    router.reload({
        preserveScroll: true,
        onFinish: () => {
            // Mantém spinning por pelo menos 500ms pra dar feedback
            setTimeout(() => { reloading.value = false }, 500)
        },
    })
}
</script>

<template>
    <header
        class="sticky top-0 z-30 flex items-center justify-between h-14 px-3"
        :style="{ backgroundColor: sidebarBg, color: 'var(--app-secondary-text, #ffffff)', paddingTop: 'env(safe-area-inset-top)' }"
    >
        <div class="flex items-center gap-2 flex-1 min-w-0">
            <button
                v-if="showBack"
                @click="back"
                class="w-9 h-9 rounded-md flex items-center justify-center hover:bg-white/10"
            >
                <i class="pi pi-arrow-left"></i>
            </button>
            <button
                v-else
                @click="emit('menu')"
                class="w-9 h-9 rounded-md flex items-center justify-center hover:bg-white/10"
            >
                <i class="pi pi-bars text-lg"></i>
            </button>
            <div class="flex items-center gap-2 min-w-0">
                <div
                    v-if="!logoUrl"
                    class="w-7 h-7 rounded-md flex items-center justify-center flex-shrink-0"
                    :style="{ backgroundColor: primaryColor, color: 'var(--app-primary-text, #ffffff)' }"
                >
                    <span class="font-bold text-xs">{{ initials }}</span>
                </div>
                <img v-else :src="logoUrl" :alt="appName" class="w-7 h-7 object-contain flex-shrink-0 rounded" />
                <span class="font-semibold text-sm truncate">{{ title || appName }}</span>
            </div>
        </div>
        <div class="flex items-center gap-1 flex-shrink-0">
            <button
                @click="reload"
                :disabled="reloading"
                class="w-9 h-9 rounded-full flex items-center justify-center hover:bg-white/10"
                title="Recarregar"
            >
                <i :class="['pi pi-refresh text-base', reloading ? 'pi-spin' : '']"></i>
            </button>
            <button
                @click="emit('notifications')"
                class="w-9 h-9 rounded-full flex items-center justify-center hover:bg-white/10 relative"
                title="Notificações"
            >
                <i class="pi pi-bell text-base"></i>
                <span
                    v-if="unreadCount > 0"
                    class="absolute top-0.5 right-0.5 min-w-[16px] h-[16px] px-1 rounded-full bg-red-500 text-white text-[9px] font-bold flex items-center justify-center"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </button>
            <button @click="goProfile" class="w-9 h-9 rounded-full overflow-hidden flex items-center justify-center" :style="{ backgroundColor: user?.avatar_url ? 'transparent' : primaryColor }">
                <img v-if="user?.avatar_url" :src="user.avatar_url" class="w-full h-full object-cover" />
                <span v-else class="text-white text-xs font-semibold">{{ user?.name?.charAt(0)?.toUpperCase() }}</span>
            </button>
        </div>
    </header>
</template>
