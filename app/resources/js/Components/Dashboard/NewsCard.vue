<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
    post: Object,
})

const page = usePage()
const currentUser = computed(() => page.props.auth?.user)

const liked = ref(props.post.liked)
const likesCount = ref(props.post.likes_count)
const commentsCount = ref(props.post.comments_count)
const expanded = ref(false)
const showComments = ref(true)
const comments = ref([])
const commentsLoading = ref(false)
const newComment = ref('')
const submittingComment = ref(false)
const showAllComments = ref(false)

const visibleComments = computed(() => {
    if (showAllComments.value) return comments.value
    return comments.value.slice(0, 1)
})

onMounted(() => {
    loadComments()
})

const formattedDate = computed(() => {
    if (!props.post.published_at) return ''
    return new Date(props.post.published_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
})

const truncatedContent = computed(() => {
    if (!props.post.content) return ''
    if (expanded.value) return props.post.content
    if (props.post.content.length > 240) return props.post.content.substring(0, 240) + '...'
    return props.post.content
})

const showSeeMore = computed(() => props.post.content && props.post.content.length > 240)

async function toggleLike() {
    const previous = { liked: liked.value, count: likesCount.value }
    liked.value = !liked.value
    likesCount.value += liked.value ? 1 : -1
    try {
        const { data } = await axios.post(`/posts/${props.post.id}/like`)
        liked.value = data.liked
        likesCount.value = data.likes_count
    } catch (e) {
        liked.value = previous.liked
        likesCount.value = previous.count
    }
}

async function loadComments() {
    if (commentsLoading.value) return
    commentsLoading.value = true
    try {
        const { data } = await axios.get(`/posts/${props.post.id}/comentarios`)
        comments.value = data.data
    } finally {
        commentsLoading.value = false
    }
}

async function toggleComments() {
    showComments.value = !showComments.value
    if (showComments.value && comments.value.length === 0) {
        await loadComments()
    }
}

async function submitComment() {
    if (!newComment.value.trim() || submittingComment.value) return
    submittingComment.value = true
    try {
        const { data } = await axios.post(`/posts/${props.post.id}/comentarios`, { content: newComment.value })
        comments.value.unshift(data.comment)
        commentsCount.value = data.comments_count
        newComment.value = ''
    } finally {
        submittingComment.value = false
    }
}

async function deleteComment(c) {
    if (!confirm('Excluir este comentário?')) return
    const { data } = await axios.delete(`/posts/${props.post.id}/comentarios/${c.id}`)
    comments.value = comments.value.filter(x => x.id !== c.id)
    commentsCount.value = data.comments_count
}

function commentDate(c) {
    return new Date(c.created_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

function userInitial(u) {
    return (u?.name || '?').charAt(0).toUpperCase()
}
</script>

<template>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div v-if="post.image_url" class="relative bg-gray-100">
            <img :src="post.image_url" :alt="post.title" class="w-full object-cover" style="aspect-ratio: 4/5; max-height: 600px;" />
        </div>

        <div class="p-5">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ post.title }}</h3>
            <p v-if="post.content" class="text-sm text-gray-600 whitespace-pre-line">{{ truncatedContent }}</p>
            <button v-if="showSeeMore" class="text-xs text-blue-600 hover:underline mt-1" @click="expanded = !expanded">
                {{ expanded ? 'ver menos' : 'ver mais' }}
            </button>
        </div>

        <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button @click="toggleLike" class="flex items-center gap-1.5 group" :title="liked ? 'Remover curtida' : 'Curtir'">
                    <i :class="['text-lg transition-colors', liked ? 'pi pi-heart-fill text-red-500' : 'pi pi-heart text-gray-400 group-hover:text-red-500']"></i>
                    <span class="text-sm text-gray-600">{{ likesCount }}</span>
                </button>
                <button @click="toggleComments" class="flex items-center gap-1.5 group">
                    <i class="pi pi-comment text-lg text-gray-400 group-hover:text-blue-500"></i>
                    <span class="text-sm text-gray-600">{{ commentsCount }}</span>
                </button>
            </div>
            <span class="text-xs text-gray-400">{{ formattedDate }}</span>
        </div>

        <div v-if="showComments" class="border-t border-gray-100 p-4 bg-gray-50">
            <div class="flex gap-2 mb-4">
                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden" :style="{ backgroundColor: currentUser?.avatar_url ? 'transparent' : 'var(--app-primary, #3b82f6)' }">
                    <img v-if="currentUser?.avatar_url" :src="currentUser.avatar_url" class="w-full h-full object-cover" />
                    <span v-else class="text-white text-xs font-semibold">{{ userInitial(currentUser) }}</span>
                </div>
                <div class="flex-1 flex gap-2">
                    <input v-model="newComment" @keydown.enter.prevent="submitComment" placeholder="Escreva um comentário..." class="flex-1 text-sm bg-white border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-gray-400" />
                    <button @click="submitComment" :disabled="!newComment.trim() || submittingComment" class="px-3 rounded-lg text-white text-sm disabled:opacity-50" :style="{ backgroundColor: 'var(--app-primary, #3b82f6)' }">
                        Enviar
                    </button>
                </div>
            </div>

            <div v-if="commentsLoading" class="text-center text-xs text-gray-400 py-3">Carregando...</div>

            <div v-else>
                <button
                    v-if="!showAllComments && comments.length > 1"
                    @click="showAllComments = true"
                    class="text-xs text-gray-500 hover:text-gray-700 mb-3"
                >
                    Ver todos os {{ comments.length }} comentários
                </button>

                <div class="space-y-3">
                    <div v-for="c in visibleComments" :key="c.id" class="flex gap-2">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden bg-gray-300">
                            <img v-if="c.user?.avatar_path" :src="`/storage/${c.user.avatar_path}`" class="w-full h-full object-cover" />
                            <span v-else class="text-white text-xs font-semibold">{{ userInitial(c.user) }}</span>
                        </div>
                        <div class="flex-1">
                            <div class="bg-white rounded-lg px-3 py-2 border border-gray-100">
                                <div class="flex items-center justify-between gap-2 mb-0.5">
                                    <span class="text-xs font-semibold text-gray-700">{{ c.user?.name || 'Usuário' }}</span>
                                    <button v-if="c.user_id === currentUser?.id" @click="deleteComment(c)" class="text-xs text-gray-400 hover:text-red-500">
                                        excluir
                                    </button>
                                </div>
                                <p class="text-sm text-gray-700 whitespace-pre-line">{{ c.content }}</p>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-0.5 ml-1">{{ commentDate(c) }}</p>
                        </div>
                    </div>
                    <p v-if="!comments.length && !commentsLoading" class="text-xs text-gray-400 text-center py-3">
                        Seja o primeiro a comentar.
                    </p>
                </div>

                <button
                    v-if="showAllComments && comments.length > 1"
                    @click="showAllComments = false"
                    class="text-xs text-gray-500 hover:text-gray-700 mt-3"
                >
                    Recolher comentários
                </button>
            </div>
        </div>
    </div>
</template>
