<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import HighlightStories from '@/Components/Dashboard/HighlightStories.vue'
import ShortcutsCard from '@/Components/Dashboard/ShortcutsCard.vue'
import NewsFeed from '@/Components/Dashboard/NewsFeed.vue'

const props = defineProps({
    highlights: { type: Array, default: () => [] },
    news: { type: Object, default: () => ({ data: [] }) },
    shortcuts: { type: Array, default: () => [] },
    menuOptions: { type: Array, default: () => [] },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const firstName = computed(() => user.value?.name?.split(' ')[0] || 'Usuário')

const leftColumnRef = ref(null)
const feedShift = ref(0)
const feedScale = ref(1)

let rafId = null
let scrollContainer = null

function updateShift() {
    if (!leftColumnRef.value || !scrollContainer) return
    if (window.innerWidth < 1024) {
        feedShift.value = 0
        feedScale.value = 1
        return
    }
    const rect = leftColumnRef.value.getBoundingClientRect()
    const containerRect = scrollContainer === window
        ? { top: 0, bottom: window.innerHeight }
        : scrollContainer.getBoundingClientRect()
    const top = Math.max(rect.top, containerRect.top)
    const bottom = Math.min(rect.bottom, containerRect.bottom)
    const visibleBottom = Math.max(0, bottom - top)
    const leftHeight = rect.height
    const ratio = leftHeight > 0 ? visibleBottom / leftHeight : 1
    const start = 0.4
    const end = 0
    const t = Math.min(1, Math.max(0, (start - ratio) / (start - end)))
    const leftWidth = rect.width + 20
    feedShift.value = -leftWidth * 0.5 * t
    feedScale.value = 1 + 0.2 * t
}

function onScroll() {
    if (rafId) cancelAnimationFrame(rafId)
    rafId = requestAnimationFrame(updateShift)
}

onMounted(() => {
    let el = leftColumnRef.value
    while (el && el !== document.body) {
        const overflow = window.getComputedStyle(el).overflowY
        if (overflow === 'auto' || overflow === 'scroll') {
            scrollContainer = el
            break
        }
        el = el.parentElement
    }
    if (!scrollContainer) scrollContainer = window
    scrollContainer.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll)
    updateShift()
})

onBeforeUnmount(() => {
    if (scrollContainer) scrollContainer.removeEventListener('scroll', onScroll)
    window.removeEventListener('resize', onScroll)
    if (rafId) cancelAnimationFrame(rafId)
})
</script>

<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto space-y-5">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800">
                    Olá, {{ firstName }} 👋
                </h1>
                <p class="text-gray-500 text-sm mt-1">Bem-vindo ao painel</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
                <div ref="leftColumnRef" class="lg:col-span-2 space-y-5">
                    <HighlightStories :highlights="highlights" />
                    <ShortcutsCard :shortcuts="shortcuts" :menu-options="menuOptions" />
                </div>

                <div
                    class="lg:col-span-1 lg:sticky lg:top-6 origin-top"
                    :style="{
                        transform: `translateX(${feedShift}px) scale(${feedScale})`,
                        transition: 'transform 0.3s ease-out'
                    }"
                >
                    <NewsFeed :news="news" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
