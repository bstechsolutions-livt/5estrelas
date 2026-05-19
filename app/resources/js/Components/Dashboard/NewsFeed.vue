<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import NewsCard from './NewsCard.vue'

const props = defineProps({
    news: Object, // paginação inicial vinda da prop
})

const items = ref([...(props.news?.data || [])])
const currentPage = ref(props.news?.current_page || 1)
const lastPage = ref(props.news?.last_page || 1)
const loading = ref(false)

async function loadMore() {
    if (loading.value || currentPage.value >= lastPage.value) return
    loading.value = true
    try {
        const next = currentPage.value + 1
        const { data } = await axios.get('/feed', { params: { page: next } })
        items.value.push(...data.data)
        currentPage.value = data.current_page
        lastPage.value = data.last_page
    } finally {
        loading.value = false
    }
}

const hasMore = () => currentPage.value < lastPage.value
</script>

<template>
    <div>
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3 px-1">
            Feed de notícias
        </h2>

        <div v-if="items.length" class="space-y-4">
            <NewsCard v-for="post in items" :key="post.id" :post="post" />

            <div v-if="hasMore()" class="text-center pt-2 pb-4">
                <button
                    type="button"
                    @click="loadMore"
                    :disabled="loading"
                    class="text-sm text-blue-600 hover:underline disabled:opacity-50"
                >
                    {{ loading ? 'Carregando...' : 'Carregar mais' }}
                </button>
            </div>
            <div v-else-if="items.length > 0" class="text-center pt-2 pb-4">
                <p class="text-xs text-gray-400">Você chegou ao fim do feed.</p>
            </div>
        </div>

        <div v-else class="bg-white rounded-xl border border-gray-200 p-8 text-center">
            <i class="pi pi-megaphone text-3xl text-gray-300 mb-2"></i>
            <p class="text-sm text-gray-500">Nenhuma notícia publicada no momento.</p>
        </div>
    </div>
</template>
