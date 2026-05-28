<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const open = ref(false)
const query = ref('')
const groups = ref([])
const loading = ref(false)
const inputRef = ref(null)
let timer = null

function openSearch() {
    open.value = true
    nextTick(() => inputRef.value?.focus())
}

function close() {
    open.value = false
    query.value = ''
    groups.value = []
}

async function search(q) {
    if (q.length < 2) {
        groups.value = []
        return
    }
    loading.value = true
    try {
        const { data } = await axios.get('/search', {
            params: { q },
            headers: { Accept: 'application/json' },
        })
        groups.value = data.groups || []
    } catch (e) {
        groups.value = []
    } finally {
        loading.value = false
    }
}

watch(query, (val) => {
    clearTimeout(timer)
    timer = setTimeout(() => search(val.trim()), 250)
})

function pickItem(item) {
    close()
    if (item.href) router.visit(item.href)
}

function onKeydown(e) {
    // Cmd/Ctrl + K
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        openSearch()
    }
    if (e.key === 'Escape' && open.value) {
        close()
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

defineExpose({ openSearch })
</script>

<template>
    <button
        @click="openSearch"
        class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm transition-colors sidebar-icon-btn"
        :style="{
            backgroundColor: 'rgba(127, 127, 127, 0.08)',
            border: '1px solid rgba(127, 127, 127, 0.15)',
        }"
        title="Buscar (Ctrl+K)"
    >
        <i class="pi pi-search text-xs"></i>
        <span class="text-xs">Buscar...</span>
        <kbd class="hidden lg:inline ml-2 px-1.5 py-0.5 rounded text-[10px] font-mono"
             :style="{ backgroundColor: 'rgba(127, 127, 127, 0.15)' }">
            Ctrl K
        </kbd>
    </button>

    <Teleport to="body">
        <Transition name="search-fade">
            <div v-if="open" class="fixed inset-0 z-[100] bg-black/50 flex items-start justify-center pt-20 px-4" @click="close">
                <div
                    class="bg-white rounded-xl shadow-2xl w-full max-w-xl max-h-[70vh] overflow-hidden flex flex-col"
                    @click.stop
                >
                    <div class="flex items-center px-4 py-3 border-b border-gray-100">
                        <i class="pi pi-search text-gray-400 mr-3"></i>
                        <input
                            ref="inputRef"
                            v-model="query"
                            type="text"
                            placeholder="Buscar usuários, notícias, páginas..."
                            class="flex-1 bg-transparent outline-none text-gray-800 placeholder-gray-400 text-sm"
                        />
                        <button @click="close" class="text-gray-400 hover:text-gray-600 ml-2">
                            <kbd class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-gray-100 text-gray-500">ESC</kbd>
                        </button>
                    </div>

                    <div class="overflow-y-auto flex-1">
                        <div v-if="loading" class="p-4 text-center text-sm text-gray-400">
                            Buscando...
                        </div>

                        <div v-else-if="query.length >= 2 && groups.length === 0" class="p-8 text-center text-sm text-gray-400">
                            <i class="pi pi-inbox text-3xl text-gray-300 block mb-2"></i>
                            Nenhum resultado para "{{ query }}".
                        </div>

                        <div v-else-if="query.length < 2" class="p-8 text-center text-sm text-gray-400">
                            Digite pelo menos 2 caracteres.
                        </div>

                        <div v-for="group in groups" :key="group.label" class="py-2">
                            <p class="px-4 py-1 text-[11px] font-semibold uppercase text-gray-400 tracking-wider">
                                {{ group.label }}
                            </p>
                            <button
                                v-for="item in group.items"
                                :key="item.id"
                                @click="pickItem(item)"
                                class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 text-left transition-colors"
                            >
                                <div class="w-8 h-8 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0 bg-gray-100">
                                    <img v-if="item.avatar_url" :src="item.avatar_url" class="w-full h-full object-cover" />
                                    <i v-else :class="[item.icon, 'text-gray-500 text-sm']"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ item.title }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ item.subtitle }}</p>
                                </div>
                                <i class="pi pi-arrow-right text-gray-300 text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.search-fade-enter-active, .search-fade-leave-active { transition: opacity 0.15s ease; }
.search-fade-enter-from, .search-fade-leave-to { opacity: 0; }
</style>
