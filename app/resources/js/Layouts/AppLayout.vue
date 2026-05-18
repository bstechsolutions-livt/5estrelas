<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

const page = usePage()
const user = computed(() => page.props.auth?.user)

const sidebarOpen = ref(true)
const mobileMenuOpen = ref(false)

const menuItems = [
    { label: 'Dashboard', icon: 'pi pi-home', href: '/dashboard' },
    { label: 'Configurações', icon: 'pi pi-cog', href: '/settings' },
]

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

function isActive(href) {
    return page.url === href || page.url.startsWith(href + '/')
}

const filteredMenuItems = computed(() => {
    if (!searchQuery.value) return menuItems
    return menuItems.filter(item =>
        item.label.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
})
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-100">
        <!-- Sidebar wrapper - controla o espaço ocupado -->
        <div
            :class="[
                'transition-all duration-300 ease-in-out flex-shrink-0 hidden lg:block overflow-hidden',
                sidebarOpen ? 'w-[260px]' : 'w-0'
            ]"
        >
            <!-- Sidebar content - largura fixa, fica oculto pelo overflow do wrapper -->
            <aside
                class="h-full w-[260px] flex flex-col"
                style="background-color: #1e1e2d;"
            >
                <!-- Logo + collapse button -->
                <div class="flex items-center justify-between h-16 px-4 border-b border-gray-700/30">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-500 flex items-center justify-center flex-shrink-0">
                            <span class="text-white font-bold text-sm">5E</span>
                        </div>
                        <span class="text-white font-semibold text-lg">5 Estrelas</span>
                    </div>
                    <button
                        @click="toggleSidebar"
                        class="w-7 h-7 rounded-md flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/10 transition-colors"
                    >
                        <i class="pi pi-angle-left text-sm"></i>
                    </button>
                </div>

                <!-- Search -->
                <div class="px-3 pt-4 pb-2">
                    <div class="relative">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs"></i>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Buscar página..."
                            class="w-full bg-white/5 border border-gray-700/50 rounded-lg pl-9 pr-3 py-2 text-sm text-gray-300 placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-colors"
                        />
                    </div>
                </div>

                <!-- Menu -->
                <nav class="flex-1 py-2 px-3 space-y-1 overflow-y-auto">
                    <button
                        v-for="item in filteredMenuItems"
                        :key="item.href"
                        @click="navigate(item.href)"
                        :class="[
                            'w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-sm',
                            isActive(item.href)
                                ? 'bg-blue-500/15 text-white border-l-2 border-blue-400'
                                : 'text-gray-400 hover:text-white hover:bg-white/5'
                        ]"
                    >
                        <i :class="[item.icon, 'text-base']"></i>
                        <span>{{ item.label }}</span>
                    </button>
                </nav>
            </aside>
        </div>

        <!-- Mobile sidebar -->
        <Teleport to="body">
            <Transition name="sidebar-mobile">
                <aside
                    v-if="mobileMenuOpen"
                    class="fixed inset-y-0 left-0 z-50 w-[260px] flex flex-col lg:hidden"
                    style="background-color: #1e1e2d;"
                >
                    <!-- Logo -->
                    <div class="flex items-center justify-between h-16 px-4 border-b border-gray-700/30">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-500 flex items-center justify-center flex-shrink-0">
                                <span class="text-white font-bold text-sm">5E</span>
                            </div>
                            <span class="text-white font-semibold text-lg">5 Estrelas</span>
                        </div>
                        <button
                            @click="mobileMenuOpen = false"
                            class="w-7 h-7 rounded-md flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/10 transition-colors"
                        >
                            <i class="pi pi-times text-sm"></i>
                        </button>
                    </div>

                    <!-- Search -->
                    <div class="px-3 pt-4 pb-2">
                        <div class="relative">
                            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs"></i>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar página..."
                                class="w-full bg-white/5 border border-gray-700/50 rounded-lg pl-9 pr-3 py-2 text-sm text-gray-300 placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-colors"
                            />
                        </div>
                    </div>

                    <!-- Menu -->
                    <nav class="flex-1 py-2 px-3 space-y-1 overflow-y-auto">
                        <button
                            v-for="item in filteredMenuItems"
                            :key="item.href"
                            @click="navigate(item.href)"
                            :class="[
                                'w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-sm',
                                isActive(item.href)
                                    ? 'bg-blue-500/15 text-white border-l-2 border-blue-400'
                                    : 'text-gray-400 hover:text-white hover:bg-white/5'
                            ]"
                        >
                            <i :class="[item.icon, 'text-base']"></i>
                            <span>{{ item.label }}</span>
                        </button>
                    </nav>
                </aside>
            </Transition>

            <!-- Mobile overlay -->
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
            <!-- Header (dark) -->
            <header class="h-16 flex items-center justify-between px-4 lg:px-6 border-b border-gray-700/30 flex-shrink-0" style="background-color: #1e1e2d;">
                <div class="flex items-center gap-3">
                    <!-- Open sidebar / hamburger -->
                    <button
                        @click="sidebarOpen ? toggleSidebar() : toggleSidebar()"
                        v-if="!sidebarOpen"
                        class="hidden lg:flex p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 transition-colors"
                    >
                        <i class="pi pi-bars text-lg"></i>
                    </button>
                    <!-- Mobile menu button -->
                    <button
                        @click="mobileMenuOpen = true"
                        class="lg:hidden p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/10"
                    >
                        <i class="pi pi-bars text-lg"></i>
                    </button>
                </div>

                <!-- Right side: user area -->
                <div class="flex items-center gap-3">
                    <!-- User -->
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-blue-500 flex items-center justify-center flex-shrink-0">
                            <span class="text-white text-sm font-semibold">
                                {{ user?.name?.charAt(0)?.toUpperCase() }}
                            </span>
                        </div>
                        <div class="hidden sm:block">
                            <p class="text-sm text-white font-medium leading-tight">{{ user?.name }}</p>
                            <p class="text-xs text-gray-500 leading-tight">Ver perfil</p>
                        </div>
                    </div>

                    <!-- Actions: bell + logout -->
                    <div class="flex items-center gap-1 pl-3 border-l border-gray-700/30">
                        <button class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 relative transition-colors">
                            <i class="pi pi-bell text-lg"></i>
                        </button>
                        <button
                            @click="logout"
                            class="p-2 rounded-lg text-gray-400 hover:text-red-400 hover:bg-red-500/10 transition-colors"
                            title="Sair"
                        >
                            <i class="pi pi-sign-out text-lg"></i>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Page content -->
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
</style>
