<script setup>
import { ref, computed, watch, onUnmounted, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useAuth } from '@/composables/useAuth'
import axios from 'axios'

const props = defineProps({
    highlights: { type: Array, default: () => [] },
})

const page = usePage()
const currentUser = computed(() => page.props.auth?.user)
const { can } = useAuth()

const STORY_DURATION = 6000

const isOpen = ref(false)
const currentIndex = ref(0)
const progress = ref(0)
const paused = ref(false)
const showCommentBox = ref(false)
const commentText = ref('')
const submittingComment = ref(false)

// Painel de comentários (lista)
const showCommentsPanel = ref(false)
const comments = ref([])
const commentsLoading = ref(false)

let progressTimer = null
let startTime = 0
let elapsedBeforePause = 0

const localState = ref({})

watch(() => props.highlights, (val) => {
    val.forEach(h => {
        if (!localState.value[h.id]) {
            localState.value[h.id] = {
                liked: h.liked || false,
                likes_count: h.likes_count || 0,
                comments_count: h.comments_count || 0,
            }
        }
    })
}, { immediate: true })

const current = computed(() => props.highlights[currentIndex.value] || null)
const currentState = computed(() => current.value ? localState.value[current.value.id] : null)

function open(idx) {
    currentIndex.value = idx
    progress.value = 0
    isOpen.value = true
    showCommentBox.value = false
    commentText.value = ''
    // Abre o painel de comentários e carrega
    showCommentsPanel.value = true
    loadComments()
    nextTick(() => startProgress())
}

function close() {
    stopProgress()
    isOpen.value = false
}

function startProgress() {
    stopProgress()
    elapsedBeforePause = 0
    startTime = Date.now()
    runTimer()
}

function runTimer() {
    progressTimer = requestAnimationFrame(() => {
        if (paused.value || !isOpen.value) return
        const elapsed = elapsedBeforePause + (Date.now() - startTime)
        progress.value = Math.min(100, (elapsed / STORY_DURATION) * 100)
        if (progress.value >= 100) {
            next()
        } else {
            runTimer()
        }
    })
}

function stopProgress() {
    if (progressTimer) cancelAnimationFrame(progressTimer)
    progressTimer = null
}

function pause() {
    if (paused.value) return
    paused.value = true
    elapsedBeforePause += Date.now() - startTime
    stopProgress()
}

function resume() {
    if (!paused.value) return
    paused.value = false
    startTime = Date.now()
    runTimer()
}

function next() {
    if (currentIndex.value < props.highlights.length - 1) {
        currentIndex.value++
        progress.value = 0
        showCommentBox.value = false
        commentText.value = ''
        if (showCommentsPanel.value) loadComments()
        startProgress()
    } else {
        close()
    }
}

function prev() {
    if (currentIndex.value > 0) {
        currentIndex.value--
        progress.value = 0
        showCommentBox.value = false
        commentText.value = ''
        if (showCommentsPanel.value) loadComments()
        startProgress()
    }
}

function timeAgo(dateStr) {
    if (!dateStr) return ''
    const diff = Date.now() - new Date(dateStr).getTime()
    const minutes = Math.floor(diff / 60000)
    if (minutes < 1) return 'agora'
    if (minutes < 60) return `${minutes}m`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h`
    const days = Math.floor(hours / 24)
    if (days < 7) return `${days}d`
    return new Date(dateStr).toLocaleDateString('pt-BR')
}

function userInitial(name) {
    return (name || '?').charAt(0).toUpperCase()
}

async function toggleLike() {
    if (!current.value) return
    const id = current.value.id
    const state = localState.value[id]
    const previous = { ...state }
    state.liked = !state.liked
    state.likes_count += state.liked ? 1 : -1
    try {
        const { data } = await axios.post(`/posts/${id}/like`)
        state.liked = data.liked
        state.likes_count = data.likes_count
    } catch (e) {
        Object.assign(state, previous)
    }
}

function openCommentBox() {
    showCommentBox.value = true
    pause()
}

function closeCommentBox() {
    showCommentBox.value = false
    commentText.value = ''
    resume()
}

async function submitComment() {
    if (!commentText.value.trim() || submittingComment.value) return
    submittingComment.value = true
    try {
        const { data } = await axios.post(`/posts/${current.value.id}/comentarios`, { content: commentText.value })
        localState.value[current.value.id].comments_count = data.comments_count
        // Adiciona no painel se estiver aberto
        if (showCommentsPanel.value && data.comment) {
            comments.value.unshift(data.comment)
        }
        commentText.value = ''
        showCommentBox.value = false
        resume()
    } finally {
        submittingComment.value = false
    }
}

async function loadComments() {
    if (!current.value || commentsLoading.value) return
    commentsLoading.value = true
    try {
        const { data } = await axios.get(`/posts/${current.value.id}/comentarios`)
        comments.value = data.data
    } finally {
        commentsLoading.value = false
    }
}

async function toggleCommentsPanel() {
    if (showCommentsPanel.value) {
        showCommentsPanel.value = false
        resume()
    } else {
        pause()
        showCommentsPanel.value = true
        await loadComments()
    }
}

function closeCommentsPanel() {
    showCommentsPanel.value = false
    resume()
}

async function deleteComment(c) {
    if (!confirm('Excluir este comentário?')) return
    const { data } = await axios.delete(`/posts/${current.value.id}/comentarios/${c.id}`)
    comments.value = comments.value.filter(x => x.id !== c.id)
    localState.value[current.value.id].comments_count = data.comments_count
}

function commentDate(c) {
    return new Date(c.created_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

function canDeleteComment(c) {
    return c.user_id === currentUser.value?.id || can('noticias.gerenciar')
}

function handleKeydown(e) {
    if (!isOpen.value) return
    if (e.key === 'Escape') {
        if (showCommentBox.value) closeCommentBox()
        else close()
    } else if (!showCommentBox.value) {
        if (e.key === 'ArrowRight') next()
        else if (e.key === 'ArrowLeft') prev()
    }
}

watch(isOpen, (val) => {
    if (val) {
        document.addEventListener('keydown', handleKeydown)
        document.body.style.overflow = 'hidden'
    } else {
        document.removeEventListener('keydown', handleKeydown)
        document.body.style.overflow = ''
    }
})

onUnmounted(() => {
    stopProgress()
    document.removeEventListener('keydown', handleKeydown)
    document.body.style.overflow = ''
})

function progressFor(idx) {
    if (idx < currentIndex.value) return 100
    if (idx === currentIndex.value) return progress.value
    return 0
}

// Sincroniza altura do painel com altura do card
const cardRef = ref(null)
const cardHeight = ref('auto')

function syncCardHeight() {
    if (cardRef.value) {
        cardHeight.value = cardRef.value.offsetHeight + 'px'
    }
}

watch([isOpen, currentIndex, showCommentsPanel], () => {
    nextTick(() => syncCardHeight())
})

if (typeof window !== 'undefined') {
    window.addEventListener('resize', () => {
        if (isOpen.value) syncCardHeight()
    })
}
</script>

<template>
    <div v-if="highlights.length" class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                Destaques
            </h2>
        </div>

        <div class="flex gap-4 overflow-x-auto pb-2 scroll-stories">
            <button
                v-for="(h, idx) in highlights"
                :key="h.id"
                @click="open(idx)"
                class="flex flex-col items-center gap-2 flex-shrink-0 group"
                style="width: 80px;"
            >
                <div class="w-16 h-16 rounded-full p-[3px] transition-transform group-hover:scale-105"
                     :style="{ background: 'linear-gradient(135deg, var(--app-primary, #3b82f6), #f59e0b, #ec4899)' }">
                    <div class="w-full h-full rounded-full bg-white p-[2px]">
                        <img v-if="h.image_url" :src="h.image_url" :alt="h.title" class="w-full h-full rounded-full object-cover" />
                        <div v-else class="w-full h-full rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="pi pi-image text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <span class="text-[11px] text-gray-700 text-center leading-tight line-clamp-2">{{ h.title }}</span>
            </button>
        </div>

        <!-- Story viewer -->
        <Teleport to="body">
            <Transition name="story-fade">
                <div v-if="isOpen" class="fixed inset-0 z-[100] bg-black/85 backdrop-blur-sm flex items-center justify-center p-4" @click.self="close">

                    <!-- Setas externas (fora do card, lateralizadas) -->
                    <button v-if="currentIndex > 0 && !showCommentsPanel" @click="prev" class="hidden md:flex absolute left-6 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 items-center justify-center text-white transition-colors">
                        <i class="pi pi-chevron-left"></i>
                    </button>
                    <button v-if="currentIndex < highlights.length - 1 && !showCommentsPanel" @click="next" class="hidden md:flex absolute right-6 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 items-center justify-center text-white transition-colors">
                        <i class="pi pi-chevron-right"></i>
                    </button>

                    <div class="flex items-start gap-3 md:gap-4 max-h-[calc(100vh-2rem)]">
                        <!-- Card centralizado -->
                        <div ref="cardRef" class="relative w-full max-w-[480px] flex flex-col flex-shrink-0" @click.stop>

                            <!-- Progress bars -->
                            <div class="flex gap-1 mb-2">
                                <div v-for="(h, idx) in highlights" :key="h.id" class="flex-1 h-[3px] bg-white/25 rounded-full overflow-hidden">
                                    <div class="h-full bg-white" :style="{ width: progressFor(idx) + '%', transition: idx === currentIndex ? 'none' : 'width 0.1s' }"></div>
                                </div>
                            </div>

                        <!-- Header -->
                        <div class="flex items-center justify-between mb-2 px-1">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-gray-700 overflow-hidden flex items-center justify-center flex-shrink-0">
                                    <img v-if="current?.creator?.avatar_url" :src="current.creator.avatar_url" class="w-full h-full object-cover" />
                                    <span v-else class="text-white text-xs font-semibold">{{ userInitial(current?.creator?.name) }}</span>
                                </div>
                                <span class="text-white text-sm font-medium">{{ current?.creator?.name || 'Sistema' }}</span>
                                <span class="text-white/60 text-xs">{{ timeAgo(current?.published_at) }}</span>
                            </div>
                            <button @click="close" class="w-8 h-8 rounded-full hover:bg-white/10 flex items-center justify-center text-white">
                                <i class="pi pi-times"></i>
                            </button>
                        </div>

                        <!-- Imagem 1:1 -->
                        <div class="relative aspect-square bg-black rounded-2xl overflow-hidden" @mousedown="pause" @mouseup="resume" @mouseleave="resume" @touchstart="pause" @touchend="resume">
                            <img v-if="current?.image_url" :src="current.image_url" :alt="current.title" class="w-full h-full object-contain" />
                            <div v-else class="absolute inset-0 flex items-center justify-center" :style="{ background: 'linear-gradient(135deg, var(--app-primary, #3b82f6), var(--app-primary-active, #1d4ed8))' }">
                                <i class="pi pi-image text-7xl text-white/40"></i>
                            </div>

                            <!-- Tap zonas -->
                            <button @click="prev" class="absolute left-0 top-0 bottom-0 w-1/3 cursor-pointer" aria-label="Anterior"></button>
                            <button @click="next" class="absolute right-0 top-0 bottom-0 w-2/3 cursor-pointer" aria-label="Próximo"></button>

                            <!-- Caption sobre a imagem (canto inferior) -->
                            <div v-if="current?.title || current?.content" class="pointer-events-none absolute left-0 right-0 bottom-0 px-4 pb-4 pt-20 bg-gradient-to-t from-black/95 via-black/70 to-transparent">
                                <h3 class="text-white text-base font-semibold mb-1.5 drop-shadow line-clamp-2">{{ current.title }}</h3>
                                <p v-if="current.content" class="text-white/85 text-xs whitespace-pre-line drop-shadow line-clamp-2">{{ current.content }}</p>
                            </div>
                        </div>

                        <!-- Footer com curtir + comentar -->
                        <div class="mt-3 flex items-center gap-2">
                            <button v-if="!showCommentBox" @click="openCommentBox" class="flex-1 bg-white/10 hover:bg-white/15 border border-white/20 text-white/80 text-sm rounded-full px-4 py-2.5 text-left transition-colors">
                                Enviar mensagem...
                            </button>

                            <input
                                v-else
                                v-model="commentText"
                                @keydown.enter="submitComment"
                                @keydown.esc="closeCommentBox"
                                placeholder="Escreva uma resposta..."
                                class="flex-1 bg-white/10 border border-white/30 text-white placeholder-white/60 text-sm rounded-full px-4 py-2.5 focus:outline-none focus:border-white"
                                autofocus
                            />

                            <button v-if="showCommentBox" @click="submitComment" :disabled="!commentText.trim() || submittingComment" class="px-4 py-2.5 rounded-full bg-white text-black text-sm font-medium disabled:opacity-50">
                                Enviar
                            </button>
                            <button v-if="showCommentBox" @click="closeCommentBox" class="w-10 h-10 rounded-full hover:bg-white/10 flex items-center justify-center text-white">
                                <i class="pi pi-times text-sm"></i>
                            </button>

                            <template v-if="!showCommentBox">
                                <button @click="toggleLike" class="text-white p-1" :title="currentState?.liked ? 'Remover curtida' : 'Curtir'">
                                    <i :class="['text-2xl transition-transform', currentState?.liked ? 'pi pi-heart-fill text-red-500 scale-110' : 'pi pi-heart hover:scale-110']"></i>
                                </button>
                                <button @click="next" class="text-white p-1" title="Próximo">
                                    <i class="pi pi-send text-2xl"></i>
                                </button>
                            </template>
                        </div>

                        <!-- Contadores -->
                        <div v-if="!showCommentBox" class="flex items-center gap-3 mt-2 text-white/70 text-xs px-1">
                            <span>
                                <i :class="['pi', currentState?.liked ? 'pi-heart-fill text-red-500' : 'pi-heart text-white/50']"></i>
                                {{ currentState?.likes_count || 0 }} curtida{{ (currentState?.likes_count || 0) === 1 ? '' : 's' }}
                            </span>
                            <button @click="toggleCommentsPanel" class="hover:underline">
                                <i class="pi pi-comment text-white/50"></i> {{ currentState?.comments_count || 0 }} comentário{{ (currentState?.comments_count || 0) === 1 ? '' : 's' }}
                            </button>
                        </div>
                        </div>

                        <!-- Painel de comentários (lateral no desktop) -->
                        <Transition name="comments-panel">
                            <div v-if="showCommentsPanel" class="hidden md:flex w-[360px] flex-col bg-white rounded-2xl overflow-hidden min-h-0" :style="{ height: cardHeight }" @click.stop>
                                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                                    <h3 class="text-sm font-semibold text-gray-800">
                                        Comentários
                                        <span v-if="currentState?.comments_count" class="text-gray-500 font-normal">({{ currentState.comments_count }})</span>
                                    </h3>
                                    <button @click="closeCommentsPanel" class="w-7 h-7 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-500">
                                        <i class="pi pi-times text-sm"></i>
                                    </button>
                                </div>

                                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                                    <div v-if="commentsLoading" class="text-center text-xs text-gray-400 py-3">
                                        Carregando...
                                    </div>
                                    <div v-else-if="!comments.length" class="text-center text-xs text-gray-400 py-6">
                                        Nenhum comentário ainda.<br>Seja o primeiro a comentar.
                                    </div>
                                    <div v-else v-for="c in comments" :key="c.id" class="flex gap-2">
                                        <div class="w-8 h-8 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0 bg-gray-300">
                                            <img v-if="c.user?.avatar_path" :src="`/storage/${c.user.avatar_path}`" class="w-full h-full object-cover" />
                                            <span v-else class="text-white text-xs font-semibold">{{ userInitial(c.user?.name) }}</span>
                                        </div>
                                        <div class="flex-1">
                                            <div class="bg-gray-50 rounded-lg px-3 py-2">
                                                <div class="flex items-center justify-between gap-2 mb-0.5">
                                                    <span class="text-xs font-semibold text-gray-700">{{ c.user?.name || 'Usuário' }}</span>
                                                    <button v-if="canDeleteComment(c)" @click="deleteComment(c)" class="text-xs text-gray-400 hover:text-red-500">
                                                        excluir
                                                    </button>
                                                </div>
                                                <p class="text-sm text-gray-700 whitespace-pre-line">{{ c.content }}</p>
                                            </div>
                                            <p class="text-[10px] text-gray-400 mt-0.5 ml-1">{{ commentDate(c) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.scroll-stories::-webkit-scrollbar { height: 6px; }
.scroll-stories::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 4px; }
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.story-fade-enter-active, .story-fade-leave-active {
    transition: opacity 0.2s ease;
}
.story-fade-enter-from, .story-fade-leave-to {
    opacity: 0;
}
.comments-panel-enter-active, .comments-panel-leave-active {
    transition: all 0.25s ease;
}
.comments-panel-enter-from, .comments-panel-leave-to {
    opacity: 0;
    transform: translateX(-10px);
}
</style>
