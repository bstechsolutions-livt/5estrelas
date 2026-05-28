<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayoutMobile from '@/Layouts/AppLayoutMobile.vue'
import HighlightStories from '@/Components/Dashboard/HighlightStories.vue'
import NewsFeed from '@/Components/Dashboard/NewsFeed.vue'

defineProps({
    highlights: { type: Array, default: () => [] },
    news: { type: Object, default: () => ({ data: [] }) },
    shortcuts: { type: Array, default: () => [] },
    menuOptions: { type: Array, default: () => [] },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const firstName = computed(() => user.value?.name?.split(' ')[0] || 'Usuário')
</script>

<template>
    <AppLayoutMobile>
        <div class="space-y-4 pt-3">
            <!-- Saudação -->
            <div class="px-4">
                <h1 class="text-xl font-semibold text-gray-800">
                    Olá, {{ firstName }} 👋
                </h1>
                <p class="text-gray-500 text-xs mt-0.5">Bem-vindo ao painel</p>
            </div>

            <!-- Stories edge-to-edge -->
            <div v-if="highlights.length" class="bg-white border-y border-gray-200 py-3">
                <HighlightStories :highlights="highlights" :embedded="true" />
            </div>

            <!-- Feed -->
            <div id="feed" class="px-4 pb-4">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Feed</h2>
                <NewsFeed :news="news" />
            </div>
        </div>
    </AppLayoutMobile>
</template>
