<script setup>
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useTheme } from '@/composables/useTheme'
import { useAuth } from '@/composables/useAuth'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const page = usePage()
const { theme } = useTheme()
const { can } = useAuth()

const user = computed(() => page.props.auth?.user)
const appName = computed(() => theme.value?.app_name || '5 Estrelas')
const logoUrl = computed(() => theme.value?.logo_url)
const primaryColor = computed(() => theme.value?.primary_color || '#3b82f6')
const sidebarBg = computed(() => theme.value?.secondary_color || '#1e1e2d')

const initials = computed(() => {
    const parts = appName.value.split(' ').filter(Boolean)
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
    return appName.value.substring(0, 2).toUpperCase()
})

const items = computed(() => {
    // Usa o menuGrouped vindo do backend (com grupos/submenus)
    return page.props.menuGrouped || []
})

const openGroups = ref({})

function toggleGroup(label) {
    openGroups.value[label] = !openGroups.value[label]
}

function isGroupOpen(label) {
    if (openGroups.value[label]) return true
    const group = (page.props.menuGrouped || []).find(g => g.type === 'group' && g.label === label)
    if (!group) return false
    return group.items.some(i => isActive(i.href))
}

function isActive(href) {
    return page.url === href || page.url.startsWith(href + '/')
}

function close() {
    emit('update:modelValue', false)
}

function navigate(href) {
    router.visit(href)
    close()
}

function logout() {
    router.post('/logout')
}

watch(() => props.modelValue, (val) => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = val ? 'hidden' : ''
    }
})
</script>

<template>
    <Teleport to="body">
        <Transition name="drawer-overlay">
            <div v-if="modelValue" class="fixed inset-0 z-[90] bg-black/50" @click="close"></div>
        </Transition>
        <Transition name="drawer-sheet">
            <aside
                v-if="modelValue"
                class="fixed left-0 top-0 bottom-0 z-[91] w-[280px] flex flex-col"
                :style="{ backgroundColor: sidebarBg, color: 'var(--app-secondary-text, #ffffff)' }"
                @click.stop
            >
                <!-- Header -->
                <div class="flex items-center justify-between h-16 px-4 border-b" :style="{ borderColor: 'rgba(255,255,255,0.08)' }">
                    <div class="flex items-center gap-3 min-w-0">
                        <div
                            v-if="!logoUrl"
                            class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                            :style="{ backgroundColor: primaryColor, color: 'var(--app-primary-text, #ffffff)' }"
                        >
                            <span class="font-bold text-sm">{{ initials }}</span>
                        </div>
                        <img v-else :src="logoUrl" :alt="appName" class="w-8 h-8 object-contain flex-shrink-0 rounded" />
                        <span class="font-semibold text-base truncate">{{ appName }}</span>
                    </div>
                    <button @click="close" class="w-8 h-8 rounded-md flex items-center justify-center hover:bg-white/10">
                        <i class="pi pi-times text-sm"></i>
                    </button>
                </div>

                <!-- User -->
                <div v-if="user" class="px-4 py-4 border-b" :style="{ borderColor: 'rgba(255,255,255,0.08)' }">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0" :style="{ backgroundColor: user.avatar_url ? 'transparent' : primaryColor }">
                            <img v-if="user.avatar_url" :src="user.avatar_url" class="w-full h-full object-cover" />
                            <span v-else class="text-white text-sm font-semibold">{{ user.name?.charAt(0)?.toUpperCase() }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium truncate">{{ user.name }}</p>
                            <p class="text-xs truncate" style="color: var(--app-secondary-text-muted, rgba(255,255,255,0.6));">{{ user.email }}</p>
                        </div>
                    </div>
                </div>

                <!-- Menu -->
                <nav class="flex-1 py-3 px-2 space-y-1 overflow-y-auto">
                    <template v-for="entry in items" :key="entry.key || entry.label">
                        <!-- Item raiz -->
                        <button
                            v-if="entry.type === 'item'"
                            @click="navigate(entry.href)"
                            :class="[
                                'w-full flex items-center gap-3 px-3 py-3 rounded-lg transition-colors text-sm',
                                isActive(entry.href) ? 'text-white border-l-2' : ''
                            ]"
                            :style="isActive(entry.href)
                                ? { backgroundColor: primaryColor + '26', borderColor: primaryColor, color: '#fff' }
                                : { color: 'var(--app-secondary-text-muted, rgba(255,255,255,0.7))' }"
                        >
                            <i :class="[entry.icon, 'text-base']"></i>
                            <span>{{ entry.label }}</span>
                        </button>

                        <!-- Grupo com submenus -->
                        <div v-else-if="entry.type === 'group'">
                            <button
                                @click="toggleGroup(entry.label)"
                                class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs uppercase tracking-wider font-semibold mt-3 mb-1"
                                style="color: var(--app-secondary-text-muted, rgba(255,255,255,0.5));"
                            >
                                <span>{{ entry.label }}</span>
                                <i :class="['pi text-[10px]', isGroupOpen(entry.label) ? 'pi-chevron-down' : 'pi-chevron-right']"></i>
                            </button>
                            <div v-show="isGroupOpen(entry.label)" class="space-y-0.5">
                                <button
                                    v-for="item in entry.items"
                                    :key="item.href"
                                    @click="navigate(item.href)"
                                    :class="[
                                        'w-full flex items-center gap-3 px-3 py-3 rounded-lg transition-colors text-sm',
                                        isActive(item.href) ? 'text-white border-l-2' : ''
                                    ]"
                                    :style="isActive(item.href)
                                        ? { backgroundColor: primaryColor + '26', borderColor: primaryColor, color: '#fff' }
                                        : { color: 'var(--app-secondary-text-muted, rgba(255,255,255,0.7))' }"
                                >
                                    <i :class="[item.icon, 'text-base']"></i>
                                    <span>{{ item.label }}</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </nav>

                <!-- Footer -->
                <div class="p-3 border-t" :style="{ borderColor: 'rgba(255,255,255,0.08)' }">
                    <button
                        @click="logout"
                        class="w-full flex items-center gap-3 px-3 py-3 rounded-lg text-sm hover:bg-red-500/10"
                        style="color: rgba(248,113,113,0.85);"
                    >
                        <i class="pi pi-sign-out"></i>
                        <span>Sair</span>
                    </button>
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>

<style scoped>
.drawer-overlay-enter-active, .drawer-overlay-leave-active { transition: opacity 0.25s ease; }
.drawer-overlay-enter-from, .drawer-overlay-leave-to { opacity: 0; }
.drawer-sheet-enter-active, .drawer-sheet-leave-active { transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1); }
.drawer-sheet-enter-from, .drawer-sheet-leave-to { transform: translateX(-100%); }
</style>
