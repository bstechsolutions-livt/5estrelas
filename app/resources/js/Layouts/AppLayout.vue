<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useTheme } from '@/composables/useTheme'
import { useAuth } from '@/composables/useAuth'
import NotificationBell from '@/Components/NotificationBell.vue'
import GlobalSearch from '@/Components/GlobalSearch.vue'

const page = usePage()
const user = computed(() => page.props.auth?.user)
const { theme } = useTheme()
const { can } = useAuth()

const sidebarOpen = ref(true)
const mobileMenuOpen = ref(false)

// Usa menuGrouped do backend (agrupado com submenus)
const menuGrouped = computed(() => page.props.menuGrouped || [])
// Flat list pra busca
const menuItems = computed(() => page.props.menuOptions || [])

const openGroups = ref({})

function toggleGroup(label) {
    openGroups.value[label] = !openGroups.value[label]
}

function isGroupOpen(label) {
    // Aberto se marcado OU se algum item do grupo está ativo
    if (openGroups.value[label]) return true
    const group = menuGrouped.value.find(g => g.type === 'group' && g.label === label)
    if (!group) return false
    return group.items.some(i => isActive(i.href))
}

const searchQuery = ref('')

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value
}

function logout() {
    router.post('/logout')
}

function navigate(href) {
    router.visit(href)
    mobileMenuOpen.value = false
}

// Item ativo = match mais específico (href mais longo que casa com a URL atual).
// Evita que um item "pai" (ex: /pagina/gestao-contratos) fique ativo nas subpáginas.
const activeHref = computed(() => {
    const path = page.url.split('?')[0]
    let best = null
    for (const entry of menuGrouped.value) {
        const items = entry.type === 'group' ? (entry.items || []) : [entry]
        for (const it of items) {
            if (!it.href) continue
            if (path === it.href || path.startsWith(it.href + '/')) {
                if (!best || it.href.length > best.length) best = it.href
            }
        }
    }
    return best
})

function isActive(href) {
    return activeHref.value === href
}

const filteredMenuItems = computed(() => {
    if (!searchQuery.value) return menuItems.value
    return menuItems.value.filter(item =>
        item.label.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
})

const appName = computed(() => theme.value?.app_name || '5 Estrelas')
const logoUrl = computed(() => theme.value?.logo_url)
const logoMobileUrl = computed(() => theme.value?.logo_mobile_url || theme.value?.logo_url)
const initials = computed(() => {
    const parts = appName.value.split(' ').filter(Boolean)
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
    return appName.value.substring(0, 2).toUpperCase()
})
const primaryColor = computed(() => theme.value?.primary_color || '#3b82f6')
const sidebarBg = computed(() => theme.value?.secondary_color || '#1e1e2d')
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-100">
        <!-- Sidebar wrapper -->
        <div
            :class="[
                'transition-all duration-300 ease-in-out flex-shrink-0 hidden lg:block overflow-hidden',
                sidebarOpen ? 'w-[260px]' : 'w-0'
            ]"
        >
            <aside
                class="h-full w-[260px] flex flex-col"
                :style="{ backgroundColor: sidebarBg, color: 'var(--app-secondary-text)' }"
            >
                <!-- Logo + collapse -->
                <div class="flex items-center justify-between h-16 px-4 border-b" :style="{ borderColor: 'rgba(255,255,255,0.08)' }">
                    <div class="flex items-center gap-3 min-w-0">
                        <div
                            v-if="!logoUrl"
                            class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                            :style="{ backgroundColor: primaryColor, color: 'var(--app-primary-text)' }"
                        >
                            <span class="font-bold text-sm">{{ initials }}</span>
                        </div>
                        <img v-else :src="logoUrl" :alt="appName" class="w-8 h-8 object-contain flex-shrink-0 rounded" />
                        <span class="font-semibold text-lg truncate" :style="{ color: 'var(--app-secondary-text)' }">{{ appName }}</span>
                    </div>
                    <button
                        @click="toggleSidebar"
                        class="w-7 h-7 rounded-md flex items-center justify-center transition-colors flex-shrink-0 sidebar-icon-btn"
                    >
                        <i class="pi pi-angle-left text-sm"></i>
                    </button>
                </div>

                <!-- Search -->
                <div class="px-3 pt-4 pb-2">
                    <div class="relative">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" :style="{ color: 'var(--app-secondary-text-muted)' }"></i>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Buscar página..."
                            class="sidebar-search w-full rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none transition-colors"
                        />
                    </div>
                </div>

                <!-- Menu -->
                <nav class="flex-1 py-2 px-3 space-y-1 overflow-y-auto">
                    <template v-for="entry in menuGrouped" :key="entry.key || entry.label">
                        <!-- Item raiz (sem grupo) -->
                        <button
                            v-if="entry.type === 'item'"
                            @click="navigate(entry.href)"
                            :class="[
                                'sidebar-menu-item w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-sm',
                                isActive(entry.href) ? 'sidebar-menu-active border-l-2' : ''
                            ]"
                            :style="isActive(entry.href) ? { backgroundColor: primaryColor + '26', borderColor: primaryColor } : {}"
                        >
                            <i :class="[entry.icon, 'text-base']"></i>
                            <span>{{ entry.label }}</span>
                        </button>

                        <!-- Grupo com submenus -->
                        <div v-else-if="entry.type === 'group'">
                            <button
                                @click="toggleGroup(entry.label)"
                                class="sidebar-menu-item w-full flex items-center justify-between px-3 py-2 rounded-lg transition-colors text-xs uppercase tracking-wider font-semibold mt-3 mb-1"
                            >
                                <span>{{ entry.label }}</span>
                                <i :class="['pi text-[10px]', isGroupOpen(entry.label) ? 'pi-chevron-down' : 'pi-chevron-right']"></i>
                            </button>
                            <div v-show="isGroupOpen(entry.label)" class="space-y-0.5 ml-1">
                                <button
                                    v-for="item in entry.items"
                                    :key="item.href"
                                    @click="navigate(item.href)"
                                    :class="[
                                        'sidebar-menu-item w-full flex items-center gap-3 px-3 py-2 rounded-lg transition-colors text-sm',
                                        isActive(item.href) ? 'sidebar-menu-active border-l-2' : ''
                                    ]"
                                    :style="isActive(item.href) ? { backgroundColor: primaryColor + '26', borderColor: primaryColor } : {}"
                                >
                                    <i :class="[item.icon, 'text-base']"></i>
                                    <span>{{ item.label }}</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </nav>
            </aside>
        </div>

        <!-- Mobile sidebar -->
        <Teleport to="body">
            <Transition name="sidebar-mobile">
                <aside
                    v-if="mobileMenuOpen"
                    class="fixed inset-y-0 left-0 z-50 w-[260px] flex flex-col lg:hidden"
                    :style="{ backgroundColor: sidebarBg, color: 'var(--app-secondary-text)' }"
                >
                    <div class="flex items-center justify-between h-16 px-4 border-b" :style="{ borderColor: 'rgba(255,255,255,0.08)' }">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                v-if="!logoMobileUrl"
                                class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                :style="{ backgroundColor: primaryColor, color: 'var(--app-primary-text)' }"
                            >
                                <span class="font-bold text-sm">{{ initials }}</span>
                            </div>
                            <img v-else :src="logoMobileUrl" :alt="appName" class="w-8 h-8 object-contain flex-shrink-0 rounded" />
                            <span class="font-semibold text-lg truncate" :style="{ color: 'var(--app-secondary-text)' }">{{ appName }}</span>
                        </div>
                        <button
                            @click="mobileMenuOpen = false"
                            class="w-7 h-7 rounded-md flex items-center justify-center transition-colors sidebar-icon-btn"
                        >
                            <i class="pi pi-times text-sm"></i>
                        </button>
                    </div>

                    <div class="px-3 pt-4 pb-2">
                        <div class="relative">
                            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" :style="{ color: 'var(--app-secondary-text-muted)' }"></i>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar página..."
                                class="sidebar-search w-full rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none transition-colors"
                            />
                        </div>
                    </div>

                    <nav class="flex-1 py-2 px-3 space-y-1 overflow-y-auto">
                        <template v-for="entry in menuGrouped" :key="entry.key || entry.label">
                            <button
                                v-if="entry.type === 'item'"
                                @click="navigate(entry.href)"
                                :class="[
                                    'sidebar-menu-item w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-sm',
                                    isActive(entry.href) ? 'sidebar-menu-active border-l-2' : ''
                                ]"
                                :style="isActive(entry.href) ? { backgroundColor: primaryColor + '26', borderColor: primaryColor } : {}"
                            >
                                <i :class="[entry.icon, 'text-base']"></i>
                                <span>{{ entry.label }}</span>
                            </button>
                            <div v-else-if="entry.type === 'group'">
                                <button
                                    @click="toggleGroup(entry.label)"
                                    class="sidebar-menu-item w-full flex items-center justify-between px-3 py-2 rounded-lg transition-colors text-xs uppercase tracking-wider font-semibold mt-3 mb-1"
                                >
                                    <span>{{ entry.label }}</span>
                                    <i :class="['pi text-[10px]', isGroupOpen(entry.label) ? 'pi-chevron-down' : 'pi-chevron-right']"></i>
                                </button>
                                <div v-show="isGroupOpen(entry.label)" class="space-y-0.5 ml-1">
                                    <button
                                        v-for="item in entry.items"
                                        :key="item.href"
                                        @click="navigate(item.href)"
                                        :class="[
                                            'sidebar-menu-item w-full flex items-center gap-3 px-3 py-2 rounded-lg transition-colors text-sm',
                                            isActive(item.href) ? 'sidebar-menu-active border-l-2' : ''
                                        ]"
                                        :style="isActive(item.href) ? { backgroundColor: primaryColor + '26', borderColor: primaryColor } : {}"
                                    >
                                        <i :class="[item.icon, 'text-base']"></i>
                                        <span>{{ item.label }}</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </nav>
                </aside>
            </Transition>

            <Transition name="fade">
                <div
                    v-if="mobileMenuOpen"
                    @click="mobileMenuOpen = false"
                    class="fixed inset-0 bg-black/50 z-40 lg:hidden"
                ></div>
            </Transition>
        </Teleport>

        <!-- Main content -->
        <div class="flex-1 flex flex-col overflow-hidden min-w-0">
            <header class="h-16 flex items-center justify-between px-4 lg:px-6 border-b flex-shrink-0" :style="{ backgroundColor: sidebarBg, color: 'var(--app-secondary-text)', borderColor: 'rgba(255,255,255,0.08)' }">
                <div class="flex items-center gap-3">
                    <button
                        v-if="!sidebarOpen"
                        @click="toggleSidebar"
                        class="hidden lg:flex p-2 rounded-lg transition-colors sidebar-icon-btn"
                    >
                        <i class="pi pi-bars text-lg"></i>
                    </button>
                    <button
                        @click="mobileMenuOpen = true"
                        class="lg:hidden p-2 rounded-lg sidebar-icon-btn"
                    >
                        <i class="pi pi-bars text-lg"></i>
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <GlobalSearch />

                    <button
                        type="button"
                        class="flex items-center gap-3 hover:opacity-80 transition-opacity"
                        @click="navigate('/perfil')"
                        title="Ver perfil"
                    >
                        <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden" :style="{ backgroundColor: user?.avatar_url ? 'transparent' : primaryColor, color: 'var(--app-primary-text)' }">
                            <img v-if="user?.avatar_url" :src="user.avatar_url" alt="Avatar" class="w-full h-full object-cover" />
                            <span v-else class="text-sm font-semibold">
                                {{ user?.name?.charAt(0)?.toUpperCase() }}
                            </span>
                        </div>
                        <div class="hidden sm:block text-left">
                            <p class="text-sm font-medium leading-tight" :style="{ color: 'var(--app-secondary-text)' }">{{ user?.name }}</p>
                            <p class="text-xs leading-tight" :style="{ color: 'var(--app-secondary-text-muted)' }">Ver perfil</p>
                        </div>
                    </button>

                    <div class="flex items-center gap-1 pl-3 border-l" :style="{ borderColor: 'rgba(255,255,255,0.08)' }">
                        <NotificationBell />
                        <button
                            @click="logout"
                            class="p-2 rounded-lg transition-colors sidebar-icon-btn"
                            title="Sair"
                        >
                            <i class="pi pi-sign-out text-lg"></i>
                        </button>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-4 lg:p-6 bg-gray-50">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.sidebar-mobile-enter-active,
.sidebar-mobile-leave-active {
    transition: transform 0.3s ease;
}
.sidebar-mobile-enter-from,
.sidebar-mobile-leave-to {
    transform: translateX(-100%);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Botões de ícone na sidebar/header (adapta a cor) */
.sidebar-icon-btn {
    color: var(--app-secondary-text-muted, rgba(255,255,255,0.6));
    transition: background-color 0.15s ease, color 0.15s ease;
}
.sidebar-icon-btn:hover {
    color: var(--app-secondary-text, #ffffff);
    background-color: rgba(127, 127, 127, 0.15);
}

/* Item de menu da sidebar */
.sidebar-menu-item {
    color: var(--app-secondary-text-muted, rgba(255,255,255,0.6));
}
.sidebar-menu-item:hover:not(.sidebar-menu-active) {
    color: var(--app-secondary-text, #ffffff);
    background-color: rgba(127, 127, 127, 0.1);
}
.sidebar-menu-active {
    color: var(--app-secondary-text, #ffffff) !important;
}

/* Input de busca da sidebar */
.sidebar-search {
    background-color: rgba(127, 127, 127, 0.08);
    border: 1px solid rgba(127, 127, 127, 0.15);
    color: var(--app-secondary-text, #ffffff);
}
.sidebar-search::placeholder {
    color: var(--app-secondary-text-muted, rgba(255,255,255,0.5));
}
.sidebar-search:focus {
    background-color: rgba(127, 127, 127, 0.15);
    border-color: var(--app-primary, #3b82f6);
}
</style>
